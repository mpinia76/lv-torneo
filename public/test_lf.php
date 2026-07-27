<?php
// TEST TEMPORAL — borrar después de usar.
// Reproduce el mismo fetch que HttpHelper::getHtmlContent() para ver qué recibe el SERVER.
// Uso:  .../public/test_lf.php   (o  ?url=...  para probar otra URL de livefutbol)

$url = isset($_GET['url']) ? $_GET['url']
     : 'https://www.livefutbol.com/competition/co103/argentina-superliga-argentina/se112372';

// Seguridad mínima: solo livefutbol / worldfootball
if (!preg_match('~^https://(www\.)?(livefutbol\.com|arg\.worldfootball\.net)/~', $url)) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "URL no permitida en este test.\n";
    exit;
}

$headers = [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
    'Accept-Encoding: gzip, deflate',
    'Referer: https://www.livefutbol.com/',
    'Connection: keep-alive',
    'Upgrade-Insecure-Requests: 1',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_ENCODING, '');
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$eff  = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$err  = curl_error($ch);
curl_close($ch);

header('Content-Type: text/plain; charset=utf-8');
echo "URL pedida : $url\n";
echo "URL final  : $eff\n";
echo "HTTP code  : $code\n";
echo "cURL error : " . ($err ?: '(ninguno)') . "\n";
echo "Bytes      : " . strlen((string)$body) . "\n\n";

foreach (['hs-block-header', 'Jornada', 'team-name-home', 'data-match_id', 'match-result'] as $k) {
    echo str_pad($k, 16) . ": " . substr_count((string)$body, $k) . "\n";
}

echo "\n----- primeros 1500 caracteres de lo que recibió el server -----\n";
echo substr((string)$body, 0, 1500) . "\n";
