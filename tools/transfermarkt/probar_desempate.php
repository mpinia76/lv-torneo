<?php
/**
 * Banco de pruebas de App\Services\DesempatePartido, sin Laravel y sin base.
 *
 *     php tools/transfermarkt/probar_desempate.php
 *
 * Fija el caso que lo motivó —Estudiantes (LP) vs Boca Juniors dos veces en el
 * mismo mes, las dos de local— y, sobre todo, fija que ANTE LA DUDA NO ELIGE.
 * Esa mitad importa más que la otra: elegir mal escribe la alineación de otro
 * partido encima del que estabas mirando.
 */

require __DIR__ . '/../../app/Services/DesempatePartido.php';

use App\Services\DesempatePartido;

$fallos = 0;

function chequear($cond, $texto) {
    global $fallos;
    if ($cond) { echo "  ok: $texto\n"; return; }
    $fallos++;
    echo "  FALLA: $texto\n";
}

function partido($id, $local, $visita, $gl, $gv) {
    return (object) ['id' => $id, 'equipol_id' => $local, 'equipov_id' => $visita,
        'golesl' => $gl, 'golesv' => $gv];
}

const EST = 16;
const BOC = 14;

echo "\n== 1) El caso real: el mismo cruce, de local, dos veces en el mes\n";
$dos = [partido(11988, EST, BOC, 1, 0), partido(12100, EST, BOC, 1, 1)];
$r = DesempatePartido::porResultado($dos, '1:0', EST, BOC);
chequear($r && $r->id === 11988, 'con 1:0 elige la fecha 11, no la semifinal');

echo "\n== 2) El texto del resultado trae los penales\n";
$r = DesempatePartido::porResultado($dos, '1:1 (3-1 p)', EST, BOC);
chequear($r && $r->id === 12100, 'toma el primer par y elige la semifinal');
$r = DesempatePartido::porResultado([partido(9, EST, BOC, 5, 4)], '5:4pen.', EST, BOC);
chequear($r && $r->id === 9, 'y «5:4pen.» también');

echo "\n== 3) Orden invertido: los goles se dan vuelta\n";
$r = DesempatePartido::porResultado(
    [partido(200, BOC, EST, 0, 1), partido(201, BOC, EST, 2, 2)], '1:0', EST, BOC);
chequear($r && $r->id === 200, 'TM dice 1:0 con Estudiantes local y tu fila es 0-1 con Boca local');

echo "\n== 4) Ante la duda, NO elige\n";
chequear(DesempatePartido::porResultado(
    [partido(300, EST, BOC, 1, 0), partido(301, EST, BOC, 1, 0)], '1:0', EST, BOC) === null,
    'dos partidos con el mismo resultado: se abstiene');
chequear(DesempatePartido::porResultado($dos, '', EST, BOC) === null,
    'sin resultado en TM: se abstiene');
chequear(DesempatePartido::porResultado($dos, 'aplazado', EST, BOC) === null,
    'con un texto que no es un resultado: se abstiene');
chequear(DesempatePartido::porResultado($dos, '9:9', EST, BOC) === null,
    'con un resultado que no coincide con ninguno: se abstiene');
chequear(DesempatePartido::porResultado([], '1:0', EST, BOC) === null,
    'sin candidatos: se abstiene');

echo "\n== 5) Un partido tuyo sin goles cargados no puede desempatar\n";
$r = DesempatePartido::porResultado(
    [partido(400, EST, BOC, null, null), partido(401, EST, BOC, 1, 0)], '1:0', EST, BOC);
chequear($r && $r->id === 401, 'lo saltea y elige el que sí tiene resultado');

echo "\n== 6) Sin rival atado sólo vale el orden que TM da\n";
chequear(DesempatePartido::porResultado([partido(500, BOC, EST, 0, 1)], '1:0', EST, 0) === null,
    'no se da vuelta el resultado si no se sabe quién es el rival');

echo "\n" . ($fallos ? "$fallos CHEQUEO(S) FALLIDO(S)\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);
