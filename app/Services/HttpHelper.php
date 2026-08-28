<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class HttpHelper
{
    // ScraperAPI: usado como fallback cuando el origen bloquea a nuestro server.
    // tmapi.transfermarkt.technology quedó geo-bloqueado por CloudFront (403 para AR);
    // saliendo desde la UE en modo básico (1 crédito) devuelve el JSON normal.
    //
    // Las claves y el país viven en el .env y se leen vía config/scraper.php.
    // NO volver a hardcodearlas acá: este archivo va a git.
    // Si cambiás el .env acordate de correr `php artisan config:clear`.

    /** Texto del error cuando no hay clave configurada. */
    const SIN_CLAVE = 'Falta SCRAPERAPI_KEY en el .env de ESTE servidor '
        . '(ojo: el .env no viaja por git, hay que cargarlo a mano en cada entorno). '
        . 'Si ya está, corré php artisan config:clear o borrá bootstrap/cache/config.php.';

    /** Clave principal: modo básico, 1 crédito (tmapi, HTML de TM, fotos). */
    private static function apiKey()
    {
        return (string) config('scraper.key', '');
    }

    /** Clave para render+premium (25 créditos). Si no hay, cae a la principal. */
    private static function apiKeyRender()
    {
        $k = (string) config('scraper.key_render', '');
        return $k !== '' ? $k : self::apiKey();
    }

    /** País de salida. 'eu' es el que pasa el geo-block de tmapi. */
    private static function country()
    {
        return (string) config('scraper.country', 'eu');
    }

    /** Proxy propio en la UE. Vacío = desactivado (ver config/scraper.php). */
    private static function proxyUrl()
    {
        return (string) config('scraper.proxy_url', '');
    }

    private static function proxyToken()
    {
        return (string) config('scraper.proxy_token', '');
    }

    // Guarda la causa del último fallo de getJson/getJsonViaScraper, para que el
    // controlador pueda mostrar un mensaje específico en vez de uno genérico.
    // Estructura: ['code' => string, 'http' => int, 'message' => string, 'snippet' => string]
    private static $lastJsonError = null;

    public static function getLastJsonError()
    {
        return self::$lastJsonError;
    }

    public static function getHtmlContent_new(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        $urlOriginal = trim($urlOriginal);

        if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
            //Log::channel('mi_log')->error("URL inválida: [$urlOriginal]");
            return false;
        }

        $host = parse_url($urlOriginal, PHP_URL_HOST);

        // Dominios con Cloudflare → directo al scraper, sin perder tiempo con cURL
        $dominiosCloudflare = ['www.livefutbol.com', 'livefutbol.com', 'www.footballdatabase.eu', 'footballdatabase.eu'];

        if ($usarScraperRemoto || in_array($host, $dominiosCloudflare, true)) {
            return self::fetchRemoto($urlOriginal);
        }

        // Para otros dominios, intento directo SIN fallback interno
        $response = self::fetchDirecto($urlOriginal);

        if ($response === false || self::esCloudflareChallenge($response)) {
            return self::fetchRemoto($urlOriginal);
        }

        return $response;
    }

    private static function esCloudflareChallenge(string $html): bool
    {
        return str_contains($html, 'Just a moment')
            || str_contains($html, 'cf-browser-verification')
            || str_contains($html, 'challenge-platform');
    }


    // ---------------------------------------------------
    // Direct cURL — mimics a real browser as closely as possible
    // ---------------------------------------------------
    private static function fetchDirecto(string $url)
    {
        $parsed   = parse_url($url);
        $host     = $parsed['host'] ?? '';
        $origin   = ($parsed['scheme'] ?? 'https') . '://' . $host;
        $referer  = $origin . '/';

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: es-AR,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'sec-ch-ua: "Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "Windows"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            "Referer: $referer",
            "Origin: $origin",
        ];

        $response = self::curlGet($url, $headers, 30);

        if ($response !== false) {
            return $response;
        }

        // Retry x3 with increasing delay
        for ($i = 1; $i <= 3; $i++) {
            sleep($i * 2); // 2s, 4s, 6s
            //Log::channel('mi_log')->debug("[DIRECTO] Retry $i para: $url");
            $response = self::curlGet($url, $headers, 30);
            if ($response !== false) {
                return $response;
            }
        }

        // Last resort: ScraperAPI
        //Log::channel('mi_log')->debug("[DIRECTO] Fallback a remoto para: $url");
        return self::fetchRemoto($url);
    }

    // ---------------------------------------------------
    // ScraperAPI — only called as last resort
    // ---------------------------------------------------
    private static function fetchRemoto(string $url)
    {
        $params = [
            'api_key'      => self::apiKey(),
            'url'          => $url,
            'render'       => 'true',
            'premium'      => 'true',
            'country_code' => 'es', // Use Spanish IPs — less flagged for footballdatabase/livefutbol
            'keep_headers' => 'true',
        ];

        $endpoint = 'http://api.scraperapi.com?' . http_build_query($params);

        //Log::channel('mi_log')->debug("[REMOTO] Usando ScraperAPI para: $url");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // render=true needs more time
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        if ($curlErr || $httpCode >= 400 || empty($response)) {
            //Log::channel('mi_log')->warning("[REMOTO] Falló HTTP $httpCode para: $url");
            return false;
        }

        return $response;
    }

    // ---------------------------------------------------
    // Shared cURL executor — returns false on any failure
    // ---------------------------------------------------
    private static function curlGet(string $url, array $headers, int $timeout)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // some servers need this

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        if ($curlErr) {
            //Log::channel('mi_log')->debug("[CURL] Error: " . curl_strerror($curlErr) . " para: $url");
            return false;
        }

        if ($httpCode === 404) {
            return false; // Don't retry 404s
        }

        if ($httpCode !== 200 || empty($response)) {
            //Log::channel('mi_log')->debug("[CURL] HTTP $httpCode para: $url");
            return false;
        }

        // Detect Cloudflare challenge page
        if (str_contains($response, 'cf-browser-verification') ||
            str_contains($response, 'challenge-platform') ||
            str_contains($response, 'Just a moment')) {
            //Log::channel('mi_log')->debug("[CURL] Cloudflare challenge detectado para: $url");
            return false;
        }

        return $response;
    }


    public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        $urlOriginal = trim($urlOriginal); // evita espacios invisibles
        //Log::channel('mi_log')->debug("[INICIO] usarScraperRemoto=" . ($usarScraperRemoto ? 'true' : 'false') . " | URL: $urlOriginal");
        if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
            //Log::channel('mi_log')->error("URL inválida recibida: [$urlOriginal]");
            return false;
        }

        // Transfermarkt quedó geo-bloqueado por CloudFront (403 para AR/EE.UU.). Su HTML
        // vuelve bien vía ScraperAPI saliendo desde la UE en modo básico (1 crédito).
        // Solo se enruta transfermarkt; livefutbol/footballdatabase/etc. siguen igual.
        $hostHtml = strtolower((string) parse_url($urlOriginal, PHP_URL_HOST));
        if ($hostHtml !== '' && strpos($hostHtml, 'transfermarkt') !== false) {
            return self::getHtmlViaScraper($urlOriginal);
        }

        if ($usarScraperRemoto) {
            $urlOriginal = trim($urlOriginal); // elimina espacios invisibles o newlines

            $scraperEndpoint = 'http://api.scraperapi.com?' . http_build_query([
                    'api_key' => self::apiKeyRender(),
                    'url'     => $urlOriginal,
                    'render'  => 'true',
                    'premium' => 'true',
                ]);

            //Log::channel('mi_log')->debug("Usando scraper remoto para: $scraperEndpoint");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $scraperEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                //Log::channel('mi_log')->error('Error en cURL (remoto): ' . curl_error($ch));
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            //Log::channel('mi_log')->debug("[REMOTO] HTTP Code: $httpCode | URL: $urlOriginal");
            //Log::channel('mi_log')->debug("[REMOTO] Response (500 chars): " . substr($response, 0, 500));


            if ($httpCode >= 400) {
                //Log::channel('mi_log')->warning("Error HTTP $httpCode al usar scraper remoto para: $urlOriginal");
                return false;
            }

            if (empty($response)) {
                //Log::channel('mi_log')->warning("Scraper remoto devolvió HTML vacío para: $urlOriginal");
            }

            return $response;
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlOriginal);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $headers = [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate',
                'Referer: https://www.livefutbol.com/',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // maneja gzip/deflate automáticamente

            //Log::channel('mi_log')->debug("[DIRECTO] Antes de curl_exec | URL: $urlOriginal");

            $response = curl_exec($ch);

            //Log::channel('mi_log')->debug("[DIRECTO] Después de curl_exec | bytes: " . strlen($response ?: ''));

            if (curl_errno($ch)) {
                $errNo  = curl_errno($ch);
                $errMsg = curl_error($ch);
                //Log::channel('mi_log')->error("[DIRECTO] cURL error #$errNo: $errMsg | URL: $urlOriginal");
                curl_close($ch);
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            //Log::channel('mi_log')->debug("[DIRECTO] HTTP Code: $httpCode | URL: $urlOriginal");
            //Log::channel('mi_log')->debug("[DIRECTO] Response (500 chars): " . substr($response, 0, 500));

            if ($httpCode == 404) {
                return false;
            }

// Retry up to 3 times if 403 or empty
            if ($httpCode == 403 || empty($response)) {
                for ($i = 1; $i <= 3; $i++) {
                    sleep(2);
                    //Log::channel('mi_log')->debug("[DIRECTO] Retry $i para: $urlOriginal");

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $urlOriginal);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($ch, CURLOPT_ENCODING, '');
                    $response = curl_exec($ch);
                    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    //Log::channel('mi_log')->debug("[DIRECTO] Retry $i HTTP Code: $httpCode");

                    if ($httpCode == 200 && !empty($response)) {
                        return $response;
                    }
                }
                // All retries failed, try remote scraper as last resort
                return self::getHtmlContent($urlOriginal, true);
            }

            return $response;
        }
    }

    public static function getHtmlContent_old(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        $urlOriginal = trim($urlOriginal);

        if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
            return false;
        }

        $targetUrl = $usarScraperRemoto
            ? 'https://scrape-prod.up.railway.app/scrape?' . http_build_query(['url' => $urlOriginal])
            : $urlOriginal;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $targetUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_errno($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError || $response === false) {
            return false;
        }

        if ($httpCode >= 400) {
            return false;
        }

        return $response;
    }

    // ---------------------------------------------------
    // GET JSON — para APIs (ej: tmapi.transfermarkt.technology).
    // Devuelve el array decodificado o null.
    // ---------------------------------------------------
    public static function getJson(string $url, array $extraHeaders = [])
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        // Orígenes de Transfermarkt (tmapi) quedaron geo-bloqueados por CloudFront
        // (403 para AR). Salimos por ScraperAPI desde la UE. NO afecta a promiedos
        // ni a ninguna otra fuente: solo se enruta lo que apunta a transfermarkt.
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== '' && strpos($host, 'transfermarkt') !== false) {
            return self::getJsonViaScraper($url, $extraHeaders);
        }

        $headers = [
            'Accept: application/json',
            'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ];

        // Headers extra por fuente (ej: promiedos requiere/espera 'X-VER').
        if (!empty($extraHeaders)) {
            $headers = array_merge($headers, $extraHeaders);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // gzip/deflate/br
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        curl_close($ch);

        // Retry simple ante fallo transitorio
        if ($curlErr || $httpCode >= 400 || empty($response)) {
            for ($i = 1; $i <= 2; $i++) {
                sleep($i);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_errno($ch);
                curl_close($ch);
                if (!$curlErr && $httpCode < 400 && !empty($response)) break;
            }
        }

        if ($curlErr || $httpCode >= 400 || empty($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    // ---------------------------------------------------
    // HTML vía ScraperAPI en modo premium + render (ejecuta el JS del desafío
    // de Cloudflare). Gasta bastantes más créditos que el modo básico, así que
    // se usa SOLO como último recurso, cuando getHtmlContent() volvió vacío o
    // con la página de desafío en vez del contenido real.
    // Devuelve el HTML o false.
    // ---------------------------------------------------
    public static function getHtmlPremium(string $url)
    {
        $url = trim($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $endpoint = 'https://api.scraperapi.com/?' . http_build_query([
                'api_key'      => self::apiKey(),
                'url'          => $url,
                'country_code' => self::country(),
                'render'       => 'true',
                'premium'      => 'true',
            ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90); // render=true tarda bastante más
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_errno($ch);
        $curlMsg  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode >= 400 || empty($response)) {
            Log::warning('getHtmlPremium: falló (HTTP ' . $httpCode . ', errno ' . $curlErr
                . ' ' . $curlMsg . ') para ' . $url, []);
            return false;
        }

        return $response;
    }

    // ---------------------------------------------------
    // GET JSON vía ScraperAPI saliendo desde la UE (modo básico, 1 crédito).
    // Para orígenes geo-bloqueados por CloudFront (ej: tmapi.transfermarkt.technology,
    // que rechaza a Argentina con un 403). Devuelve el array decodificado o null.
    // ---------------------------------------------------
    // Detecta el desafío de AWS WAF (HTML con window.awsWafCookie) que tmapi
    // devuelve, de forma intermitente por IP, en vez del JSON. Requiere JS para
    // resolverse, así que si aparece hay que reintentar (otra IP) o renderizar.
    private static function esWafChallenge($resp): bool
    {
        if (!is_string($resp) || $resp === '') return false;
        $head = ltrim(substr($resp, 0, 600));
        return stripos($resp, 'awsWaf') !== false
            || stripos($resp, 'aws-waf-token') !== false
            || stripos($resp, 'challenge-container') !== false
            || stripos($head, '<!DOCTYPE html') === 0
            || stripos($head, '<html') === 0;
    }

    /**
     * Cuántas veces se cuenta EF BF BD antes de decir que el binario vino
     * pasado por texto. Una imagen de verdad no tiene ninguno: la secuencia
     * aparece por azar cada 16 millones de bytes.
     */
    const TOPE_REEMPLAZOS = 20;

    // ---------------------------------------------------
    // Descarga BINARIA (imágenes): PRIMERO DIRECTO, el proxy es la red de
    // contención.
    //
    // Durante mucho tiempo esto salía siempre por ScraperAPI, con el argumento
    // de que los hosts de imágenes de TM estaban geo-bloqueados para el server.
    // Eso quedó escrito en un comentario y nunca se volvió a comprobar. El
    // 28-ago-2026 se midió, y era falso: la descarga directa anda perfecto.
    //
    // Medición sobre el retrato de Almeida (TM 1116061), misma URL, mismo
    // momento:
    //
    //   directo               → 200, image/webp,                  24.540 bytes, 0 rotos
    //   directo sin webp      → 200, image/png,                  191.628 bytes, 0 rotos
    //   proxy (5 variantes)   → 200, image/webp; CHARSET=UTF-8,   38.936 bytes, 494 rotos
    //
    // Ese `charset=utf-8` pegado a una respuesta binaria es la firma del delito:
    // ScraperAPI la trata como texto y cada byte alto se convierte en `EF BF BD`.
    // Por eso el archivo pesa 38.936 en vez de 24.540 y no lo dibuja nadie.
    //
    // OJO — el problema que originó los reintentos (ago-2026): ScraperAPI
    // devuelve **a veces** la imagen pasada por UTF-8, con cada byte alto
    // convertido en U+FFFD (`EF BF BD`). Llega con HTTP 200 y Content-Type
    // image/png, así que los chequeos de siempre la dan por buena y lo que se
    // guarda es basura: 103 fotos rotas en producción salieron de ahí.
    //
    // Los reintentos por proxy quedan igual —un cuerpo mangleado cuenta como
    // intento fallido y se vuelve a pedir, porque cada request sale por otra
    // IP— pero ahora son el plan B: si la directa anda, no se gasta un crédito.
    //
    // Devuelve: ['ok'=>bool, 'body'=>string, 'http'=>int, 'contentType'=>string,
    //            'error'=>string, 'intentos'=>int, 'mangleados'=>int]
    // ---------------------------------------------------
    public static function getBinary(string $url, int $intentos = 5): array
    {
        $url = trim($url);
        $out = ['ok' => false, 'body' => '', 'http' => 0, 'contentType' => '', 'error' => '',
                'intentos' => 0, 'mangleados' => 0];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $out['error'] = 'url_invalida';
            return $out;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $hayProxy = ($host !== '' && strpos($host, 'transfermarkt') !== false && self::apiKey() !== '');

        $endpointProxy = $hayProxy
            ? ('https://api.scraperapi.com/?' . http_build_query([
                    'api_key'      => self::apiKey(),
                    'url'          => $url,
                    'country_code' => self::country(),
                ]))
            : '';

        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer: https://www.transfermarkt.com/',
        ];

        $body = false; $httpCode = 0; $contentType = ''; $curlErr = 0;

        $intentos = max(1, min($intentos, 8));
        $mangleados = 0;

        // El PRIMER intento va derecho al host de la imagen, sin proxy: es gratis
        // y —medido el 28-ago-2026 contra la ficha de Almeida— es el único que
        // trae el binario entero. El proxy queda de red de contención.
        $planes = [['proxy' => false, 'endpoint' => $url]];
        for ($i = 0; $i < $intentos && $hayProxy; $i++) {
            $planes[] = ['proxy' => true, 'endpoint' => $endpointProxy];
        }

        foreach ($planes as $n => $plan) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $plan['endpoint']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $plan['proxy'] ? 40 : 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $body        = curl_exec($ch);
            $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlErr     = curl_errno($ch);
            curl_close($ch);

            if (!$curlErr && $httpCode === 200 && !empty($body) && strpos($contentType, 'image') !== false) {
                // 200 + content-type de imagen NO garantiza que lo que llegó sea
                // la imagen: el proxy la manda pasada por texto y le pega un
                // `charset=utf-8` al content-type, que es la firma del delito.
                if (self::pasadoPorTexto($body)) {
                    $mangleados++;
                    Log::warning('HttpHelper: binario mangleado por el proxy (intento ' . ($n + 1)
                        . ') en ' . $url);
                    usleep(300000);
                    continue;
                }

                return ['ok' => true, 'body' => $body, 'http' => $httpCode,
                        'contentType' => $contentType, 'error' => '',
                        'intentos' => $n + 1, 'mangleados' => $mangleados,
                        'via' => $plan['proxy'] ? 'proxy' : 'directo'];
            }

            if ($plan['proxy']) {
                // Créditos de ScraperAPI agotados: no tiene sentido reintentar.
                if (is_string($body) && stripos($body, 'exhausted the API Credits') !== false) {
                    $out['http'] = $httpCode; $out['error'] = 'sin_creditos';
                    $out['intentos'] = $n + 1; $out['mangleados'] = $mangleados;
                    return $out;
                }
                usleep(300000); // 0,3s → fuerza rotación de IP en ScraperAPI
            } else {
                // La directa falló: recién ahí tiene sentido gastar en el proxy.
                Log::info('HttpHelper: la descarga directa falló (HTTP ' . $httpCode
                    . ($curlErr ? ', curl ' . $curlErr : '') . '), voy por el proxy: ' . $url);
            }
        }

        $out['http']        = $httpCode;
        $out['contentType'] = $contentType;
        $out['intentos']    = count($planes);
        $out['mangleados']  = $mangleados;
        $out['error']       = $mangleados > 0
            ? ('binario_mangleado_' . $mangleados . '_intentos')
            : ($curlErr ? ('curl_errno_' . $curlErr) : ('http_' . $httpCode));

        return $out;
    }

    /**
     * ¿El cuerpo binario viene pasado por texto?
     *
     * Cuenta el carácter de reemplazo UTF-8 (`EF BF BD`). Una imagen real no
     * tiene ninguno; las que devuelve rota el proxy tienen entre 60 y 80.000.
     * Es la única señal que sirve para cualquier formato: la cabecera PNG o RIFF
     * puede quedar intacta y engañar a `getimagesize()`.
     */
    public static function pasadoPorTexto($body): bool
    {
        if (!is_string($body) || $body === '') return false;

        return substr_count($body, "\xef\xbf\xbd") > self::TOPE_REEMPLAZOS;
    }

    private static function getJsonViaScraper(string $url, array $extraHeaders = [])
    {
        self::$lastJsonError = null;
        $sinCreditos = false;

        // Sin clave, ScraperAPI devuelve un 401 que parece un problema de la
        // cuenta y en realidad es de configuración. Lo decimos claro acá.
        if (self::apiKey() === '') {
            self::$lastJsonError = ['code' => 'sin_api_key', 'http' => 0,
                'message' => self::SIN_CLAVE, 'snippet' => ''];
            Log::error('HttpHelper: ' . self::SIN_CLAVE);
            return false;
        }

        $params = [
            'api_key'      => self::apiKey(),
            'url'          => $url,
            'country_code' => self::country(), // 'eu' pasa el geo-block de tmapi
        ];
        $endpoint = 'https://api.scraperapi.com/?' . http_build_query($params);

        $headers = array_merge(['Accept: application/json'], $extraHeaders);

        $response = false;
        $httpCode = 0;
        $curlErr  = 0;

        // Hasta 4 intentos en modo básico (1 crédito c/u). OJO: el challenge de AWS
        // WAF llega con HTTP 200 y cuerpo no vacío, por eso lo detectamos aparte y
        // NO cortamos el bucle: cada request de ScraperAPI sale por otra IP, y
        // reintentando suele caer en una IP sin WAF (sin gastar render=true).
        for ($i = 0; $i < 4; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_errno($ch);
            curl_close($ch);
            if (!$curlErr && $httpCode < 400 && !empty($response) && !self::esWafChallenge($response)) break;
            // Créditos agotados: no tiene sentido reintentar, vamos directo al proxy.
            if (is_string($response) && stripos($response, 'exhausted the API Credits') !== false) {
                $sinCreditos = true;
                break;
            }
            usleep(300000); // 0,3s entre intentos → fuerza rotación de IP en ScraperAPI
        }

        // ScraperAPI devuelve 200 con un cuerpo de "créditos agotados": lo tratamos como fallo real.
        if (is_string($response) && stripos($response, 'exhausted the API Credits') !== false) {
            $sinCreditos = true;
        }

        // Si tras los reintentos por IP sigue el challenge de AWS WAF, último recurso:
        // un único intento con render=true (headless browser) que ejecuta el JS del
        // WAF. Cuesta más créditos, por eso solo se hace acá y una sola vez.
        if (!$sinCreditos && self::esWafChallenge($response)) {
            $renderEndpoint = 'https://api.scraperapi.com/?' . http_build_query(array_merge($params, [
                    'render'  => 'true',
                    'premium' => 'true',
                ]));
            Log::info('getJsonViaScraper: WAF persistente por IP, intento final con render=true para ' . $url, []);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $renderEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 55);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_errno($ch);
            curl_close($ch);
            if (is_string($response) && stripos($response, 'exhausted the API Credits') !== false) {
                $sinCreditos = true;
            }
        }

        // ¿El WAF sobrevivió incluso al render? Lo tratamos como fallo (no intentamos
        // extraer JSON de la página del challenge, que daría basura).
        $wafPersiste = self::esWafChallenge($response);

        if ($curlErr || $httpCode >= 400 || empty($response) || $sinCreditos || $wafPersiste) {
            // ScraperAPI falló o sin créditos → probamos el proxy propio en la UE (si está configurado).
            $viaProxy = self::tmProxyGet($url);
            if ($viaProxy !== false) {
                $dp = json_decode($viaProxy, true);
                if (is_array($dp)) return $dp;
                if (preg_match('/\{.*\}/s', $viaProxy, $mp)) {
                    $dp = json_decode($mp[0], true);
                    if (is_array($dp)) return $dp;
                }
            }
            $code = $sinCreditos ? 'sin_creditos' : ($wafPersiste ? 'waf' : 'http_error');
            self::$lastJsonError = [
                'code'    => $code,
                'http'    => (int) $httpCode,
                'message' => $sinCreditos
                    ? 'ScraperAPI se quedó sin créditos del mes.'
                    : ($wafPersiste
                        ? 'AWS WAF bloqueó tmapi incluso tras reintentos y render=true.'
                        : ('ScraperAPI falló (HTTP ' . $httpCode . ', errno ' . $curlErr . ').')),
                'snippet' => is_string($response) ? substr($response, 0, 300) : '',
            ];
            Log::warning('getJsonViaScraper: falló (' . self::$lastJsonError['code']
                . ', HTTP ' . $httpCode . ', errno ' . $curlErr . ') para ' . $url
                . ' | ' . self::$lastJsonError['snippet'], []);
            return null;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // ScraperAPI a veces envuelve el JSON en HTML (<pre>…</pre>): lo extraemos.
        if (preg_match('/\{.*\}/s', $response, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        self::$lastJsonError = [
            'code'    => 'no_json',
            'http'    => (int) $httpCode,
            'message' => 'La respuesta de ScraperAPI/tmapi no era JSON (¿cambió el endpoint del DT?).',
            'snippet' => is_string($response) ? substr($response, 0, 300) : '',
        ];
        Log::warning('getJsonViaScraper: respuesta no era JSON (HTTP ' . $httpCode . ') para ' . $url
            . ' | ' . self::$lastJsonError['snippet'], []);
        return null;
    }

    // ---------------------------------------------------
    // GET HTML vía ScraperAPI saliendo desde la UE (modo básico, 1 crédito).
    // Para páginas de transfermarkt.com.ar geo-bloqueadas por CloudFront.
    // Devuelve el HTML crudo o false.
    // ---------------------------------------------------
    private static function getHtmlViaScraper(string $url)
    {
        if (self::apiKey() === '') {
            Log::error('HttpHelper: ' . self::SIN_CLAVE);
            return false;
        }

        $endpoint = 'https://api.scraperapi.com/?' . http_build_query([
                'api_key'      => self::apiKey(),
                'url'          => $url,
                'country_code' => self::country(), // 'eu' pasa el geo-block de TM
            ]);

        $response = false;
        $httpCode = 0;
        $curlErr  = 0;

        for ($i = 0; $i < 2; $i++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_errno($ch);
            curl_close($ch);
            if (!$curlErr && $httpCode < 400 && !empty($response)) {
                return $response;
            }
            // Créditos agotados: cortamos y vamos al proxy.
            if (is_string($response) && stripos($response, 'exhausted the API Credits') !== false) break;
        }

        // ScraperAPI falló o sin créditos → proxy propio en la UE (si está configurado).
        $viaProxy = self::tmProxyGet($url);
        if ($viaProxy !== false) {
            return $viaProxy;
        }

        Log::warning('getHtmlViaScraper: falló (HTTP ' . $httpCode . ', errno ' . $curlErr . ') para ' . $url, []);
        return false;
    }

    // ---------------------------------------------------
    // Proxy propio en la UE (respaldo cuando ScraperAPI se queda sin créditos).
    // Devuelve el cuerpo crudo (JSON o HTML) o false si no está configurado / falla.
    // ---------------------------------------------------
    private static function tmProxyGet(string $url)
    {
        if (self::proxyUrl() === '') {
            return false; // proxy no configurado todavía
        }

        $sep      = (strpos(self::proxyUrl(), '?') === false) ? '?' : '&';
        $endpoint = self::proxyUrl() . $sep
            . 'token=' . urlencode(self::proxyToken())
            . '&url='  . urlencode($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 70);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $body    = curl_exec($ch);
        $code    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_errno($ch);
        curl_close($ch);

        if ($curlErr || $code >= 400 || empty($body)) {
            Log::warning('tmProxyGet: falló (HTTP ' . $code . ', errno ' . $curlErr . ') para ' . $url, []);
            return false;
        }

        return $body;
    }
}
