<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class HttpHelper
{
    public static function getHtmlContent_new(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        $urlOriginal = trim($urlOriginal);

        if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
            return false;
        }

        if ($usarScraperRemoto) {
            return self::fetchRemoto($urlOriginal);
        }

        return self::fetchDirecto($urlOriginal);
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
            Log::channel('mi_log')->debug("[DIRECTO] Retry $i para: $url");
            $response = self::curlGet($url, $headers, 30);
            if ($response !== false) {
                return $response;
            }
        }

        // Last resort: ScraperAPI
        Log::channel('mi_log')->debug("[DIRECTO] Fallback a remoto para: $url");
        return self::fetchRemoto($url);
    }

    // ---------------------------------------------------
    // ScraperAPI — only called as last resort
    // ---------------------------------------------------
    private static function fetchRemoto(string $url)
    {
        $params = [
            'api_key'      => 'a36c0383b6153a740f783cc5ba9bd54c',
            'url'          => $url,
            'render'       => 'true',
            'premium'      => 'true',
            'country_code' => 'es', // Use Spanish IPs — less flagged for footballdatabase/livefutbol
            'keep_headers' => 'true',
        ];

        $endpoint = 'http://api.scraperapi.com?' . http_build_query($params);

        Log::channel('mi_log')->debug("[REMOTO] Usando ScraperAPI para: $url");

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
            Log::channel('mi_log')->warning("[REMOTO] Falló HTTP $httpCode para: $url");
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
            Log::channel('mi_log')->debug("[CURL] Error: " . curl_strerror($curlErr) . " para: $url");
            return false;
        }

        if ($httpCode === 404) {
            return false; // Don't retry 404s
        }

        if ($httpCode !== 200 || empty($response)) {
            Log::channel('mi_log')->debug("[CURL] HTTP $httpCode para: $url");
            return false;
        }

        // Detect Cloudflare challenge page
        if (str_contains($response, 'cf-browser-verification') ||
            str_contains($response, 'challenge-platform') ||
            str_contains($response, 'Just a moment')) {
            Log::channel('mi_log')->debug("[CURL] Cloudflare challenge detectado para: $url");
            return false;
        }

        return $response;
    }


    public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        $urlOriginal = trim($urlOriginal); // evita espacios invisibles
        //Log::channel('mi_log')->debug("[INICIO] usarScraperRemoto=" . ($usarScraperRemoto ? 'true' : 'false') . " | URL: $urlOriginal");
        if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
            Log::channel('mi_log')->error("URL inválida recibida: [$urlOriginal]");
            return false;
        }

        if ($usarScraperRemoto) {
            $urlOriginal = trim($urlOriginal); // elimina espacios invisibles o newlines

            $scraperEndpoint = 'http://api.scraperapi.com?' . http_build_query([
                    'api_key' => 'a36c0383b6153a740f783cc5ba9bd54c',
                    'url'     => $urlOriginal,
                    'render'  => 'true',
                    'premium' => 'true',
                ]);

            Log::channel('mi_log')->debug("Usando scraper remoto para: $scraperEndpoint");

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

}
