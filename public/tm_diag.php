<?php
/**
 * Diagnóstico tmapi de Transfermarkt — standalone, NO toca la base de datos.
 * Uso:  http://209.217.241.186/~torneospinia/public/tm_diag.php?id=4889642
 * Borrar este archivo cuando termines de diagnosticar.
 */
header('Content-Type: text/plain; charset=utf-8');

$id   = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '4889642';
$base = 'https://tmapi.transfermarkt.technology';

// Endpoints a probar (mismo que usa la app + variantes por si cambió la ruta)
$urls = [
    'game (falla)'     => "$base/game/$id",
    'player (funciona)'=> "$base/player/189448",  // control: import jugador anda con este host
    'game_report'      => "$base/game/$id/report", // por si movieron el reporte a subruta
];

$headers = [
    'Accept: application/json',
    'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
];

echo "== Diagnóstico tmapi ==\n";
echo "PHP: " . PHP_VERSION . "   curl: " . (function_exists('curl_init') ? curl_version()['version'] : 'NO DISPONIBLE') . "\n";
echo "game id probado: $id\n";
echo str_repeat('-', 60) . "\n\n";

foreach ($urls as $label => $url) {
    echo "### $label\n$url\n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER         => true,   // incluir headers de respuesta
    ]);
    $t0   = microtime(true);
    $resp = curl_exec($ch);
    $ms   = round((microtime(true) - $t0) * 1000);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    $errno = curl_errno($ch);
    $hlen = $info['header_size'] ?? 0;
    curl_close($ch);

    $respHeaders = $resp !== false ? substr($resp, 0, $hlen) : '';
    $body        = $resp !== false ? substr($resp, $hlen) : '';

    echo "HTTP:        " . ($info['http_code'] ?? 0) . "\n";
    echo "curl errno:  $errno" . ($errno ? "  ($err)" : '') . "\n";
    echo "tiempo:      {$ms} ms\n";
    echo "size body:   " . strlen($body) . " bytes\n";
    echo "content-type:" . ($info['content_type'] ?? '') . "\n";
    echo "IP resuelta: " . ($info['primary_ip'] ?? '') . "\n";
    if ($respHeaders) echo "--- headers ---\n" . trim($respHeaders) . "\n";
    echo "--- body (primeros 800) ---\n" . substr($body, 0, 800) . "\n";
    // ¿Es JSON válido con 'data'?
    $j = json_decode($body, true);
    if (is_array($j)) {
        echo "JSON válido. keys: " . implode(', ', array_slice(array_keys($j), 0, 10)) . "\n";
        echo "tiene 'data': " . (isset($j['data']) && !empty($j['data']) ? 'SÍ' : 'NO / vacío') . "\n";
    } else {
        echo "JSON: NO parseable\n";
    }
    echo str_repeat('=', 60) . "\n\n";
}
