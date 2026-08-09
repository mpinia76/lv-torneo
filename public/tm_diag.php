<?php
/**
 * Diagnóstico proxy tmapi — standalone, NO toca la base de datos.
 * http://209.217.241.186/~torneospinia/public/tm_diag.php?id=4889642
 * Borrar cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$id    = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '4889642';
$base  = 'https://tmapi.transfermarkt.technology';
$proxy = 'https://scrape-prod.up.railway.app/scrape';

function fetch_raw($url, $extraOpts = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ] + $extraOpts);
    $t0   = microtime(true);
    $body = curl_exec($ch);
    $ms   = round((microtime(true) - $t0) * 1000);
    $i    = curl_getinfo($ch);
    $en   = curl_errno($ch);
    $er   = curl_error($ch);
    curl_close($ch);
    return [$body, $i, $en, $er, $ms];
}

function reportar($titulo, $url, $extraOpts = []) {
    echo "### $titulo\n$url\n";
    list($body, $i, $en, $er, $ms) = fetch_raw($url, $extraOpts);
    echo sprintf("HTTP %d   errno %d   %dms   ct:%s   size:%d\n",
        $i['http_code'] ?? 0, $en, $ms, $i['content_type'] ?? '', is_string($body) ? strlen($body) : 0);
    if ($en) { echo "curl error: $er\n"; }
    $j = is_string($body) ? json_decode($body, true) : null;
    if (is_array($j)) {
        echo "JSON OK. keys: " . implode(', ', array_slice(array_keys($j), 0, 8)) . "\n";
        echo "tiene 'data': " . (!empty($j['data']) ? 'SÍ ✅' : 'NO/vacío') . "\n";
    } else {
        echo "cuerpo (primeros 300): " . substr((string)$body, 0, 300) . "\n";
    }
    echo str_repeat('=', 60) . "\n\n";
}

echo "== Proxy tmapi ==\n";
echo "game id: $id\n" . str_repeat('-', 60) . "\n\n";

// 1) Directo (esperamos que falle con errno 35, para confirmar)
reportar('DIRECTO player (control, debería fallar)', "$base/player/189448");

// 2) Vía proxy: player
reportar('PROXY player 189448', $proxy . '?' . http_build_query(['url' => "$base/player/189448"]));

// 3) Vía proxy: game
reportar('PROXY game ' . $id, $proxy . '?' . http_build_query(['url' => "$base/game/$id"]));

echo "FIN\n";
