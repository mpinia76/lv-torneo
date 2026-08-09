<?php
/**
 * Diagnóstico de actions de tmapi (transfermarkt) — para ver cómo marca los
 * penales errados / atajados. Standalone, NO toca la base de datos.
 *
 * Uso: pasar el ID del partido de transfermarkt (el de /spielbericht/index/spielbericht/ID):
 *   http://209.217.241.186/~torneospinia/public/tm_actions.php?id=EL_ID
 *
 * Elegí un partido que HAYA tenido un penal errado y/o atajado.
 * Borrar este archivo cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$id     = isset($_GET['id']) ? preg_replace('/\D+/', '', $_GET['id']) : '';
$apiKey = 'a36c0383b6153a740f783cc5ba9bd54c'; // misma key/plan que usa la app
if (!$id) { die("Falta ?id=EL_ID_DE_TRANSFERMARKT\n"); }

$target   = 'https://tmapi.transfermarkt.technology/game/' . $id;
$endpoint = 'https://api.scraperapi.com/?' . http_build_query([
    'api_key'      => $apiKey,
    'url'          => $target,
    'country_code' => 'eu',
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "== Actions tmapi — game $id ==\n";
echo "HTTP $code\n" . str_repeat('-', 70) . "\n\n";

$j = json_decode($body, true);
if (!is_array($j) && preg_match('/\{.*\}/s', (string)$body, $m)) $j = json_decode($m[0], true);
if (!is_array($j) || empty($j['data'])) {
    echo "No se pudo leer el JSON. Primeros 400:\n" . substr((string)$body, 0, 400) . "\n";
    exit;
}

$d = $j['data'];
$tipos = [];
$n = 0;
foreach (($d['actions'] ?? []) as $a) {
    $n++;
    $type   = $a['type'] ?? '?';
    $aid    = $a['actionId'] ?? '';
    $rid    = $a['actionReasonId'] ?? '';
    $min    = $a['minute'] ?? '';
    $add    = $a['addedTime'] ?? '';
    $active = $a['activePlayerId'] ?? '';
    $pass   = $a['passivePlayerId'] ?? '';
    $tipos[$type] = ($tipos[$type] ?? 0) + 1;
    echo sprintf("type=%-14s actionId=%-5s reasonId=%-5s min=%s+%s  active=%s passive=%s\n",
        $type, $aid, $rid, $min, $add, $active, $pass);
    echo "     raw: " . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n" . str_repeat('-', 70) . "\n";
echo "Total actions: $n\n";
echo "Conteo por type: " . json_encode($tipos) . "\n";
echo "\nBuscá la accion del penal errado/atajado: su 'type', 'actionId' y los campos de 'details'.\n";
