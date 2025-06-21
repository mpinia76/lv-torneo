<?php
// app/Services/HttpHelper.php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class HttpHelper
{
    public static function getHtmlContent($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            Log::channel('mi_log')->error('Error en cURL: ' . curl_error($ch));
            return false;
        }

        if ($response === false) {
            Log::channel('mi_log')->error('Fallo en la solicitud cURL para la URL: ' . $url);
            curl_close($ch);
            return false;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 404) {
            Log::channel('mi_log')->warning('Página no encontrada (404) para la URL: ' . $url);
            return false;
        }

        return $response;
    }
}
