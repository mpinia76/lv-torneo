<?php
    // app/Services/HttpHelper.php

    namespace App\Services;

    use Illuminate\Support\Facades\Log;

    class HttpHelper
    {
        public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
        {
            $urlOriginal = trim($urlOriginal); // evita espacios invisibles
            $usarScraperRemoto=true;
            if (!filter_var($urlOriginal, FILTER_VALIDATE_URL)) {
                //Log::channel('mi_log')->error("URL inválida recibida: [$urlOriginal]");
                return false;
            }

            if ($usarScraperRemoto) {
                $urlOriginal = trim($urlOriginal); // elimina espacios invisibles o newlines

                $scraperEndpoint = 'https://scrape-prod.up.railway.app/scrape?' . http_build_query([
                        'url' => $urlOriginal
                    ]);

                //Log::channel('mi_log')->debug("Usando scraper remoto para: $scraperEndpoint");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $scraperEndpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    //Log::channel('mi_log')->error('Error en cURL (remoto): ' . curl_error($ch));
                    return false;
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode >= 400) {
                    Log::channel('mi_log')->warning("Error HTTP $httpCode al usar scraper remoto para: $urlOriginal");
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

                // Evita el 403 añadiendo User-Agent tipo navegador
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/118.0.5993.90 Safari/537.36');


                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    //Log::channel('mi_log')->error('Error en cURL: ' . curl_error($ch));
                    return false;
                }

                if ($response === false) {
                    //Log::channel('mi_log')->error('Fallo en la solicitud cURL para la URL: ' . $urlOriginal);
                    curl_close($ch);
                    return false;
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode == 404) {
                    //Log::channel('mi_log')->warning('PÃ¡gina no encontrada (404) para la URL: ' . $urlOriginal);
                    return false;
                }

                return $response;
            }
        }
    }


