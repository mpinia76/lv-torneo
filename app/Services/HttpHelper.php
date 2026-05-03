<?php
namespace App\Services;

use Illuminate\Support\Facades\Log;

class HttpHelper
{
    public static function getHtmlContent(string $urlOriginal, bool $usarScraperRemoto = false)
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
}
