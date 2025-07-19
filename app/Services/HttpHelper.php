<?php
// app/Services/HttpHelper.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HttpHelper
{
    public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
    {
        if ($usarScraperRemoto) {
            $scraperEndpoint = 'https://scrape-prod.up.railway.app/scrape?url=' . urlencode($urlOriginal);

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
                //Log::channel('mi_log')->warning("Error HTTP $httpCode al usar scraper remoto para: $urlOriginal");
                return false;
            }

            // Acá NO decodificás JSON, simplemente devolvés el HTML crudo
            return $response;
        } else {
            // Hace scraping local (cURL directo)
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlOriginal);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                //Log::channel('mi_log')->error('Error en cURL (local): ' . curl_error($ch));
                return false;
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                //Log::channel('mi_log')->warning("Error HTTP $httpCode al usar scraping local para: $urlOriginal");
                return false;
            }

            return $response;
        }
    }
}

