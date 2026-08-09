<?php
/**
 * Diagnóstico ScraperAPI -> tmapi. Standalone, NO toca la base de datos.
 * http://209.217.241.186/~torneospinia/public/tm_diag.php?id=4889642
 * Borrar cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(180);

$id      = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '4889642';
$base    = 'https://tmapi.transfermarkt.technology';
$apiKey  = '44182b1d4649eb00f3c41258721c4884'; // misma key que usa HttpHelper::fetchRemoto

function scraperapi_get($apiKey, $targetUrl, $extra = []) {
    $params = array_merge([
        'api_key'      => $apiKey,
        'url'          => $targetUrl,
        'keep_headers' => 'true',
    ], $extra);
    $endpoint = 'https://api.scraperapi.com/?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $t0   = microtime(true);
    $body = curl_exec($ch);
    $ms   = round((microtime(true) - $t0) * 1000);
    $i    = curl_getinfo($ch);
    $en   = curl_errno($ch);
    $er   = curl_error($ch);
    curl_close($ch);
    return [$body, $i, $en, $er, $ms];
}

function extraerJson($body) {
    if (!is_string($body)) return null;
    $j = json_decode($body, true);
    if (is_array($j)) return $j;
    // ScraperAPI a veces envuelve el JSON en HTML (<pre>...</pre>)
    if (preg_match('/\{.*\}/s', $body, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) return $j;
    }
    return null;
}

function reportar($titulo, $apiKey, $targetUrl, $extra = []) {
    echo "### $titulo\n$targetUrl\n";
    echo "opts: " . (empty($extra) ? '(básico)' : http_build_query($extra)) . "\n";
    list($body, $i, $en, $er, $ms) = scraperapi_get($apiKey, $targetUrl, $extra);
    echo sprintf("HTTP %d   errno %d   %dms   ct:%s   size:%d\n",
        $i['http_code'] ?? 0, $en, $ms, $i['content_type'] ?? '', is_string($body) ? strlen($body) : 0);
    if ($en) echo "curl error: $er\n";
    $j = extraerJson($body);
    if (is_array($j)) {
        echo "JSON OK. keys: " . implode(', ', array_slice(array_keys($j), 0, 8)) . "\n";
        echo "tiene 'data': " . (!empty($j['data']) ? 'SÍ ✅' : 'NO/vacío') . "\n";
    } else {
        echo "cuerpo (primeros 300): " . substr((string)$body, 0, 300) . "\n";
    }
    echo str_repeat('=', 60) . "\n\n";
}

echo "== ScraperAPI -> tmapi ==\ngame id: $id\n" . str_repeat('-', 60) . "\n\n";

// player, escalando nivel de proxy hasta que uno pase CloudFront
reportar('player  basico',        $apiKey, "$base/player/189448");
reportar('player  premium',       $apiKey, "$base/player/189448", ['premium' => 'true']);
reportar('player  ultra_premium', $apiKey, "$base/player/189448", ['ultra_premium' => 'true']);

// game con el nivel que probablemente funcione
reportar('game    premium',       $apiKey, "$base/game/$id", ['premium' => 'true']);
reportar('game    ultra_premium', $apiKey, "$base/game/$id", ['ultra_premium' => 'true']);

echo "FIN\n";
