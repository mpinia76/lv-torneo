<?php
    // app/Services/HttpHelper.php

    namespace App\Services;

    use Illuminate\Support\Facades\Log;

    class HttpHelper
    {
        public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
        {
            $urlOriginal = trim($urlOriginal); // evita espacios invisibles

            if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
                //Log::channel('mi_log')->error("URL inválida recibida: [$urlOriginal]");
                return false;
            }

            if ($usarScraperRemoto) {
                $urlOriginal = trim($urlOriginal); // elimina espacios invisibles o newlines

                $scraperEndpoint = 'http://api.scraperapi.com?' . http_build_query([
                        'api_key' => 'a36c0383b6153a740f783cc5ba9bd54c',
                        'url'     => $urlOriginal,
                        'render'  => 'true',
                    ]);

                Log::channel('mi_log')->debug("Usando scraper remoto para: $scraperEndpoint");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $scraperEndpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    Log::channel('mi_log')->error('Error en cURL (remoto): ' . curl_error($ch));
                    return false;
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                Log::channel('mi_log')->debug("[REMOTO] HTTP Code: $httpCode | URL: $urlOriginal");
                Log::channel('mi_log')->debug("[REMOTO] Response (500 chars): " . substr($response, 0, 500));


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
                    'Accept-Encoding: gzip, deflate, br',
                    'Referer: https://www.livefutbol.com/',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_ENCODING, ''); // maneja gzip/deflate automáticamente

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    curl_close($ch);
                    return false;
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                Log::channel('mi_log')->debug("[DIRECTO] HTTP Code: $httpCode | URL: $urlOriginal");
                Log::channel('mi_log')->debug("[DIRECTO] Response (500 chars): " . substr($response, 0, 500));


                if ($httpCode == 404) {
                    return false;
                }

                // Si sigue dando 403, intentar con el scraper remoto como fallback
                if ($httpCode == 403 || empty($response)) {
                    //Log::channel('mi_log')->warning("403 en modo directo, reintentando con scraper remoto: $urlOriginal");
                    return self::getHtmlContent($urlOriginal, true);
                }

                return $response;
            }
        }
    }


