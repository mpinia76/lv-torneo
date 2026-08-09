<?php
/**
 * Diagnóstico geo-block tmapi vía ScraperAPI (modo básico + country_code).
 * Standalone, NO toca la base de datos.
 * http://209.217.241.186/~torneospinia/public/tm_diag.php?id=4889642&key=TU_KEY
 * Borrar cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);

$id     = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '4889642';
$base   = 'https://tmapi.transfermarkt.technology';
$apiKey = isset($_GET['key']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['key']) : '';
if (!$apiKey) { die("Falta ?key=TU_KEY\n"); }
echo "(key: " . substr($apiKey, 0, 6) . "…)\n";

function scraperapi_get($apiKey, $targetUrl, $extra = []) {
    $params = array_merge(['api_key' => $apiKey, 'url' => $targetUrl], $extra);
    $endpoint = 'https://api.scraperapi.com/?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 70,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $i    = curl_getinfo($ch);
    $en   = curl_errno($ch);
    curl_close($ch);
    return [$body, $i['http_code'] ?? 0, $en];
}

function verData($body) {
    if (!is_string($body)) return null;
    $j = json_decode($body, true);
    if (!is_array($j) && preg_match('/\{.*\}/s', $body, $m)) $j = json_decode($m[0], true);
    return is_array($j) ? $j : null;
}

// Países a probar (básico, sin premium). Alemania primero: transfermarkt es alemán.
$paises = ['(default)' => [], 'de' => ['country_code'=>'de'], 'eu' => ['country_code'=>'eu'],
           'us' => ['country_code'=>'us'], 'es' => ['country_code'=>'es'], 'gb' => ['country_code'=>'gb'],
           'fr' => ['country_code'=>'fr'], 'it' => ['country_code'=>'it']];

echo "== tmapi player/189448 — probando países (modo básico) ==\n";
echo str_repeat('-', 60) . "\n";
$ganador = null;
foreach ($paises as $label => $extra) {
    list($body, $code, $en) = scraperapi_get($apiKey, "$base/player/189448", $extra);
    $j = verData($body);
    $hayData = ($j && !empty($j['data']));
    $nota = $hayData ? 'DATA SÍ ✅' : (is_array($j) ? 'json sin data' : ('sin data: ' . substr(trim((string)$body),0,90)));
    echo sprintf("  %-9s HTTP %-3d errno %-2d  %s\n", $label, $code, $en, $nota);
    if ($hayData && !$ganador) $ganador = $label;
}

echo "\n";
if ($ganador) {
    echo ">>> País que PASA: $ganador — probando el partido $id desde ahí...\n";
    $extra = $paises[$ganador];
    list($body, $code, $en) = scraperapi_get($apiKey, "$base/game/$id", $extra);
    $j = verData($body);
    echo sprintf("  game %-4s HTTP %-3d  %s\n", $ganador, $code, ($j && !empty($j['data'])) ? 'DATA SÍ ✅' : 'sin data');
    echo "\n>>> SOLUCIÓN: agregar country_code=$ganador (modo básico) al fallback de getJson.\n";
} else {
    echo ">>> Ningún país pasó en modo básico. Puede requerir premium+country_code (plan pago).\n";
}
echo "\nFIN\n";
