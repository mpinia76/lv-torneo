<?php
/**
 * proxy.php — Relay para saltar el geo-bloqueo de Transfermarkt.
 *
 * SUBIR A UN HOSTING EUROPEO (NL / ES / DE). En tu servidor actual (EE.UU.)
 * NO sirve, porque está en un país bloqueado igual que Argentina.
 *
 * Uso desde la app:  https://TU-HOST-EU/proxy.php?token=SECRETO&url=<url-encoded>
 *
 * Seguridad:
 *  - Exige un token (para que no lo use cualquiera como proxy abierto).
 *  - Solo deja pasar URLs de transfermarkt (no es un proxy general).
 */

// ⬇⬇ CAMBIÁ ESTE TOKEN por uno tuyo, largo y único. Tiene que ser EL MISMO
//     que pongas en HttpHelper.php (constante TM_PROXY_TOKEN).
$SECRET = 'lvt_7f3aK9pQ2xR8vM5nZ_CAMBIAME';

if (!isset($_GET['token']) || !hash_equals($SECRET, (string) $_GET['token'])) {
    http_response_code(403);
    exit('forbidden');
}

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    exit('bad url');
}

// Restricción de seguridad: solo transfermarkt.
$host = strtolower((string) parse_url($url, PHP_URL_HOST));
if ($host === '' || strpos($host, 'transfermarkt') === false) {
    http_response_code(400);
    exit('host not allowed');
}

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_ENCODING       => '',            // gzip/deflate/br
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json, text/html;q=0.9, */*;q=0.8',
        'Accept-Language: es-AR,es;q=0.9,en;q=0.8',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ],
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'text/plain; charset=utf-8';
$err  = curl_errno($ch);
curl_close($ch);

if ($err || $body === false) {
    http_response_code(502);
    exit('fetch failed (curl ' . $err . ')');
}

http_response_code($code ?: 200);
header('Content-Type: ' . $ct);
echo $body;
