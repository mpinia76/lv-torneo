<?php
/**
 * Banco de pruebas de App\Services\TmFixtureClubHtml, sin red y sin créditos.
 *
 *     php tools/transfermarkt/probar_fixture_club_html.php
 *
 * El HTML de acá abajo copia la forma real de
 * `transfermarkt.es/-/spielplandatum/verein/19775/saison_id/2024` (una sola
 * tabla cronológica, con filas de encabezado por competencia), verificada
 * contra la página el 2026-09-02. Incluye a propósito las tres trampas:
 *
 *   1. la celda de la JORNADA linkea a la competencia y su texto es un número,
 *      así que sin descartar los textos numéricos cada partido se queda con la
 *      jornada del anterior y la competencia sale llamándose «15»;
 *   2. el mismo partido aparece linkeado dos veces en su fila;
 *   3. el escudo del rival linkea al club con el texto vacío.
 */

namespace App\Services {
    class HttpHelper {
        public static $html = '';
        public static function getHtmlContent(string $url, bool $r = false) { return self::$html; }
        // Los lectores piden el HTML de TM por acá: el país de salida es un
        // parámetro porque el sitio y la API no se comportan igual desde Europa.
        public static function getHtmlTm(string $url, $pais = null) { return self::$html; }
    }
}

namespace {

require __DIR__ . '/../../app/Services/TmFixtureClubHtml.php';
require __DIR__ . '/../../app/Services/TmFixtureCompetenciaHtml.php';

use App\Services\TmFixtureClubHtml;
use App\Services\HttpHelper;

$fallos = 0;

function chequear($ok, $texto) {
    global $fallos;
    if (!$ok) { $fallos++; echo "  FALLA: $texto\n"; } else { echo "  ok: $texto\n"; }
}

function encabezado($compId, $nombre, $copa = false) {
    $ruta = $copa ? 'pokalwettbewerb' : 'wettbewerb';
    return '<tr><td colspan="9"><a href="/x/startseite/' . $ruta . '/' . $compId . '">' . $nombre . '</a></td></tr>';
}

function fila($jornada, $compId, $dia, $ha, $rivalId, $rival, $gameId, $res, $doble = false, $copa = false) {
    $link = '<a href="/x/index/spielbericht/' . $gameId . '">' . $res . '</a>';
    $ruta = $copa ? 'pokalwettbewerb' : 'wettbewerb';
    return '<tr>'
        . '<td><a href="/x/gesamtspielplan/' . $ruta . '/' . $compId . '">' . $jornada . '</a></td>'
        . '<td>vie ' . $dia . '</td><td>21:00</td><td>' . $ha . '</td><td>(5.)</td>'
        . '<td><a href="/x/startseite/verein/' . $rivalId . '"></a>'
        . '<a href="/x/startseite/verein/' . $rivalId . '">' . $rival . '</a></td>'
        . '<td>4-4-2</td><td>1.500</td>'
        . '<td>' . $link . ($doble ? $link : '') . '</td>'
        . '</tr>';
}

$paginaES = '<html><body><table>'
    . encabezado('ARG1', 'Torneo Apertura')
    . fila('1',  'ARG1', '24/01/2025', 'A', '333',   'Lanús',      '4529322', '0:2')
    . fila('2',  'ARG1', '28/01/2025', 'H', '10511', 'San Martín', '4529115', '0:0')
    . encabezado('ARGC', 'Torneo Clausura')
    . fila('15', 'ARGC', '10/11/2025', 'H', '7631',  'Independiente', '4643362', '0:1')
    . fila('16', 'ARGC', '15/11/2025', 'A', '12574', 'Godoy Cruz', '4643374', '1:1', true)
    // Una copa: en TM va por `pokalwettbewerb`, no por `wettbewerb`.
    . encabezado('ARCA', 'Copa Argentina', true)
    . fila('Octavos', 'ARCA', '20/08/2025', 'H', '1029', 'Vélez', '4600001', '2:0', false, true)
    . '</table></body></html>';

echo "\n== 1) La página real (una tabla, encabezados por competencia)\n";
HttpHelper::$html = $paginaES;
$svc   = new TmFixtureClubHtml;
$filas = $svc->leer('19775', 2024);

chequear(is_array($filas) && count($filas) === 5, 'lee las 5 filas y no duplica la que tiene el link dos veces ('
    . (is_array($filas) ? count($filas) : 'null') . ')');

$porId = [];
foreach ((array) $filas as $f) $porId[$f['game_id']] = $f;
$t = isset($porId['4643374']) ? $porId['4643374'] : null;

chequear($t !== null, 'está el partido testigo 4643374');
chequear($t && $t['dia'] === '2025-11-15', 'la fecha sale bien: ' . ($t ? var_export($t['dia'], true) : '—'));
chequear($t && $t['local'] === false, 'lo lee como visitante (columna A)');
chequear($t && $t['rival_tm'] === '12574', 'saca el club rival de Transfermarkt (12574 = Godoy Cruz)');
chequear($t && $t['rival_nombre'] === 'Godoy Cruz', 'y su nombre, ignorando el link del escudo que viene vacío');
chequear($t && $t['resultado'] === '1:1', 'y el resultado del texto del link');
chequear($t && $t['comp_id'] === 'ARGC', 'el id de competencia sale de la celda de la jornada');
chequear($t && $t['competencia'] === 'Torneo Clausura',
    'y el NOMBRE del encabezado, no la jornada del partido anterior: ' . ($t ? var_export($t['competencia'], true) : '—'));
chequear(isset($porId['4529115']) && $porId['4529115']['competencia'] === 'Torneo Apertura',
    'la segunda fila de una sección también toma el encabezado, no el «1» de la anterior');
chequear(isset($porId['4529115']) && $porId['4529115']['local'] === true, 'y la localía H la lee como local');

chequear(isset($porId['4600001']) && $porId['4600001']['comp_id'] === 'ARCA'
    && $porId['4600001']['competencia'] === 'Copa Argentina',
    'una COPA (pokalwettbewerb) no se confunde con la liga anterior: '
    . (isset($porId['4600001']) ? var_export($porId['4600001']['competencia'], true) : '—'));

echo "\n== 2) Formato de fecha: se decide por los datos, no por el idioma\n";
HttpHelper::$html = str_replace(['24/01/2025', '28/01/2025', '10/11/2025', '15/11/2025', '20/08/2025'],
                                ['01/24/2025', '01/28/2025', '11/10/2025', '11/15/2025', '08/20/2025'], $paginaES);
$svc2 = new TmFixtureClubHtml;
$f2   = $svc2->leer('19775', 2024);
$p2   = [];
foreach ((array) $f2 as $f) $p2[$f['game_id']] = $f;
chequear(isset($p2['4643374']) && $p2['4643374']['dia'] === '2025-11-15',
    'una página en mm/dd se lee igual de bien: ' . (isset($p2['4643374']) ? var_export($p2['4643374']['dia'], true) : '—'));
chequear(strpos(implode(' ', $svc2->avisos), 'mm/dd') !== false, 'y avisa que la detectó');

echo "\n== 3) Si TM cambia el maquetado, se nota como error y no como «no jugó»\n";
HttpHelper::$html = '<html><body><p>otra cosa</p></body></html>';
$svc3 = new TmFixtureClubHtml;
$f3   = $svc3->leer('19775', 2024);
chequear(is_array($f3) && count($f3) === 0, 'devuelve una lista vacía');
chequear(strpos(implode(' ', $svc3->avisos), 'maquetado') !== false, 'y lo dice en un aviso');

echo "\n== 4) Si la página no viene, no se inventa nada\n";
HttpHelper::$html = '';
$svc4 = new TmFixtureClubHtml;
chequear($svc4->leer('19775', 2024) === null, 'devuelve null, que no es lo mismo que «no hay partidos»');

echo "\n== 5) El calendario de una COMPETENCIA entera\n";
// Otra forma de página: no hay "nuestro club" ni columna de localía; los dos
// clubes salen del orden en que aparecen. Verificado contra
// `gesamtspielplan/pokalwettbewerb/KLUB/saison_id/2024` el 2026-09-02, que
// devuelve los 63 partidos del Mundial de Clubes 2025.
function filaComp($dia, $localId, $local, $visitaId, $visita, $gameId, $res) {
    return '<tr><td>' . $dia . '</td><td>21:00</td>'
        . '<td><a href="/x/startseite/verein/' . $localId . '">' . $local . '</a>'
        . '<a href="/x/startseite/verein/' . $localId . '"></a></td>'
        . '<td><a href="/x/startseite/verein/' . $visitaId . '">' . $visita . '</a></td>'
        . '<td><a href="/x/index/spielbericht/' . $gameId . '">' . $res . '</a></td></tr>';
}

HttpHelper::$html = '<html><body><table>'
    . filaComp('15/06/2025', '7', 'Al Ahly', '69261', 'Inter Miami', '4504579', '0:0')
    . filaComp('01/07/2025', '281', 'Man. City', '1114', 'Al-Hilal', '4506865', '3:4')
    // Una fila con un solo club no sirve para aparear: se descarta.
    . '<tr><td>02/07/2025</td><td><a href="/x/startseite/verein/281">Man. City</a></td>'
    . '<td><a href="/x/index/spielbericht/9999999">?</a></td></tr>'
    . '</table></body></html>';

$comp = new App\Services\TmFixtureCompetenciaHtml;
$fc   = $comp->leerComp('KLUB', 2024, true);
$pc   = [];
foreach ((array) $fc as $f) $pc[$f['game_id']] = $f;

chequear(is_array($fc) && count($fc) === 2, 'lee las 2 filas completas y descarta la que tiene un solo club ('
    . (is_array($fc) ? count($fc) : 'null') . ')');
chequear(isset($pc['4506865']) && $pc['4506865']['dia'] === '2025-07-01', 'la fecha sale bien');
chequear(isset($pc['4506865']) && $pc['4506865']['local_tm'] === '281'
    && $pc['4506865']['visita_tm'] === '1114',
    'local y visitante salen del orden, no de una columna H/A');
chequear(isset($pc['4506865']) && $pc['4506865']['local_nombre'] === 'Man. City'
    && $pc['4506865']['visita_nombre'] === 'Al-Hilal', 'y con sus nombres, que son los que atan equipo_tm');
chequear(strpos(App\Services\TmFixtureCompetenciaHtml::urlComp('KLUB', 2024, true), 'pokalwettbewerb') !== false
    && strpos(App\Services\TmFixtureCompetenciaHtml::urlComp('ARGC', 2024, false), '/-/gesamtspielplan/wettbewerb/') !== false,
    'la copa y la liga van a rutas distintas');

echo "\n" . ($fallos ? "$fallos CHEQUEO(S) FALLIDO(S)\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);

}
