<?php
/**
 * Diagnóstico de eventos de promiedos — para ver si trae penales errados/atajados.
 * Standalone, NO toca la base de datos.
 *
 * Uso: pasar el ID del partido de promiedos (el de /game/slug/ID):
 *   http://209.217.241.186/~torneospinia/public/pm_diag.php?id=EL_ID
 *
 * Elegí un partido que HAYA tenido un penal errado o atajado.
 * Borrar este archivo cuando termines.
 */
header('Content-Type: text/plain; charset=utf-8');

$id = isset($_GET['id']) ? preg_replace('/[^a-z0-9]/i', '', $_GET['id']) : '';
if (!$id) { die("Falta ?id=EL_ID_DE_PROMIEDOS (el de la URL /game/slug/ID)\n"); }

$url = 'https://api.promiedos.com.ar/gamecenter/' . $id;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 40,
    CURLOPT_ENCODING       => '',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'X-VER: 1.11.7.5',
        'Referer: https://www.promiedos.com.ar/',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "== Eventos promiedos — id $id ==\n";
echo "HTTP $code\n" . str_repeat('-', 70) . "\n\n";

$j = json_decode($body, true);
if (!is_array($j) || empty($j['game'])) {
    echo "No se pudo leer el JSON. Primeros 400:\n" . substr((string)$body, 0, 400) . "\n";
    exit;
}

$g = $j['game'];

// Aplanar events -> stages -> rows -> events
$tipos = [];
$n = 0;
foreach (($g['events'] ?? []) as $stage) {
    foreach (($stage['rows'] ?? []) as $row) {
        foreach (($row['events'] ?? []) as $ev) {
            $n++;
            $type  = $ev['type'] ?? '?';
            $tipos[$type] = ($tipos[$type] ?? 0) + 1;
            $time  = $ev['time'] ?? '';
            $team  = $ev['team'] ?? '';
            $jersey= $ev['player_jersey_num'] ?? '';
            $texts = isset($ev['texts']) && is_array($ev['texts']) ? implode(' | ', $ev['texts']) : '';
            $name  = $ev['name'] ?? ($ev['text'] ?? '');
            echo sprintf("type=%-3s  min=%-6s  team=%-2s  dorsal=%-4s  name=%-20s  texts=%s\n",
                $type, $time, $team, $jersey, $name, $texts);
            // Volcamos el evento completo por si hay campos útiles que no mostramos
            echo "     raw: " . json_encode($ev, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
}

echo "\n" . str_repeat('-', 70) . "\n";
echo "Total eventos: $n\n";
echo "Conteo por type: " . json_encode($tipos) . "\n";
echo "\nBuscá el evento del penal errado/atajado y fijate su 'type' y campos.\n";
