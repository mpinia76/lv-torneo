<?php
/**
 * Diagnóstico TLS tmapi — standalone, NO toca la base de datos.
 * http://209.217.241.186/~torneospinia/public/tm_diag.php?id=4889642
 * Borrar cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$id   = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '4889642';
$base = 'https://tmapi.transfermarkt.technology';

$v = curl_version();
echo "== Diagnóstico TLS ==\n";
echo "PHP:  " . PHP_VERSION . "\n";
echo "curl: " . $v['version'] . "\n";
echo "SSL:  " . $v['ssl_version'] . "\n";   // <-- clave: contra qué OpenSSL está linkeado
echo "protocolos: " . implode(',', $v['protocols']) . "\n";
echo str_repeat('-', 60) . "\n\n";

// Combinaciones de TLS a probar contra tmapi
$tests = [
    'default'        => [],
    'TLS 1.2 forzado'=> [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2],
    'TLS 1.3 forzado'=> defined('CURL_SSLVERSION_TLSv1_3') ? [CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3] : null,
    'cipher moderno' => [CURLOPT_SSL_CIPHER_LIST => 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384'],
];

// Hosts de control para saber si el TLS del server anda en general
$controles = [
    'CONTROL google'        => 'https://www.google.com',
    'CONTROL tm web'        => 'https://www.transfermarkt.com.ar',
    'CONTROL scrape proxy'  => 'https://scrape-prod.up.railway.app',
];

function probar($url, $opts) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,   // solo handshake + headers
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ] + ($opts ?: []));
    $t0 = microtime(true);
    curl_exec($ch);
    $ms = round((microtime(true) - $t0) * 1000);
    $i  = curl_getinfo($ch);
    $en = curl_errno($ch);
    $er = curl_error($ch);
    curl_close($ch);
    return sprintf("HTTP %-3d  errno %-2d  %5dms  ip:%-15s  %s",
        $i['http_code'] ?? 0, $en, $ms, $i['primary_ip'] ?? '-', $en ? $er : 'OK');
}

echo "### tmapi: $base/player/189448\n";
foreach ($tests as $label => $opts) {
    if ($opts === null) { echo sprintf("  %-16s : (no soportado por este curl)\n", $label); continue; }
    echo sprintf("  %-16s : %s\n", $label, probar("$base/player/189448", $opts));
}
echo "\n### tmapi: $base/game/$id\n";
foreach ($tests as $label => $opts) {
    if ($opts === null) { echo sprintf("  %-16s : (no soportado)\n", $label); continue; }
    echo sprintf("  %-16s : %s\n", $label, probar("$base/game/$id", $opts));
}

echo "\n### Controles (para saber si el TLS del server anda en general)\n";
foreach ($controles as $label => $url) {
    echo sprintf("  %-20s : %s\n", $label, probar($url, []));
}
echo "\nFIN\n";
