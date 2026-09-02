<?php
/**
 * Banco de pruebas de App\Services\TmBuscarGameId, sin Laravel y sin API.
 *
 *     php tools/transfermarkt/probar_buscar_gameid.php
 *
 * Corre la clase real contra stubs de DB / Cache / HttpHelper, así que no toca
 * la base, no gasta un solo crédito y se puede correr en cualquier lado. Está
 * acá porque el buscador decide con qué gameId se escribe la alineación de un
 * partido: equivocarse ahí escribe el detalle de OTRO partido encima del que
 * estabas mirando, y eso después no se nota.
 *
 * Lo que fija cada caso:
 *   1. el fixture del club sólo trae la temporada en curso → un partido de un
 *      campeonato terminado tiene que salir por la competencia del torneo;
 *   2. si el staging ya tenía el gameId, no se gasta ninguna llamada;
 *   3. si TM ignora el `seasonId`, se avisa y NO se paga la ruta redundante;
 *   4. si el `gameDay` nuestro no es el de TM, se sigue con la temporada entera;
 *   5. sin datos de partida no se gastan créditos;
 *   6. con dos partidos posibles no se elige ninguno.
 */

namespace {
    class Escenario {
        public static $partido;
        public static $equipoTm = [];        // equipo_id => tm_club_id
        public static $coaches  = [];        // urls de tecnicos
        public static $torneo   = null;      // fila del torneo, o null
        public static $staging  = [];        // filas de import_partidos
        public static $respuestas = [];      // url => json que devuelve TM
        public static $pedidas   = [];       // urls efectivamente pedidas
        public static function reset() {
            self::$equipoTm = []; self::$coaches = []; self::$torneo = null;
            self::$staging = []; self::$respuestas = []; self::$pedidas = [];
        }
    }

    class FakeQuery {
        public $tabla; public $joins = 0;
        public function __construct($t) { $this->tabla = $t; }
        public function join() { $this->joins++; return $this; }
        public function where() { return $this; }
        public function whereIn() { return $this; }
        public function whereNotNull() { return $this; }
        public function whereDate() { return $this; }
        public function orWhere() { return $this; }
        public function first($cols = null) {
            // partidos con joins = la consulta del torneo; sin joins = el partido.
            if ($this->tabla === 'partidos') return $this->joins ? Escenario::$torneo : Escenario::$partido;
            if ($this->tabla === 'equipos') return (object) ['nombre' => 'Equipo'];
            return null;
        }
        public function get() { return Escenario::$staging; }
        public function pluck($a, $b = null) {
            if ($this->tabla === 'equipo_tm') return new FakeColeccion(Escenario::$equipoTm);
            if ($this->tabla === 'partido_tecnicos') return new FakeColeccion(Escenario::$coaches);
            return new FakeColeccion([]);
        }
    }

    class FakeColeccion {
        private $items;
        public function __construct($i) { $this->items = $i; }
        public function all() { return $this->items; }
    }
}

namespace Illuminate\Support\Facades {
    class DB { public static function table($t) { return new \FakeQuery($t); } }
    class Cache {
        public static function has($k) { return false; }
        public static function get($k) { return null; }
        public static function put($k, $v, $t) { return true; }
    }
    class Schema {
        public static function hasTable($t) { return true; }
        public static function hasColumn($t, $c) { return true; }
    }
}

namespace App\Services {
    class HttpHelper {
        public static function getJson($url, $extra = []) {
            \Escenario::$pedidas[] = $url;
            return isset(\Escenario::$respuestas[$url]) ? \Escenario::$respuestas[$url] : null;
        }
    }
}

namespace {

require __DIR__ . '/../../app/Services/TmBuscarGameId.php';

use App\Services\TmBuscarGameId;

const TM = 'https://tmapi.transfermarkt.technology';

$fallos = 0;

function juego($gameId, $home, $away, $dia, $season) {
    return ['gameId' => $gameId, 'homeClub' => ['clubId' => $home], 'awayClub' => ['clubId' => $away],
        'baseDetails' => ['seasonId' => $season, 'date' => ['dateTimeUTC' => $dia . 'T23:00:00Z']]];
}

function chequear($ok, $texto) {
    global $fallos;
    if (!$ok) { $fallos++; echo "  FALLA: $texto\n"; } else { echo "  ok: $texto\n"; }
}

/** Godoy Cruz (77) vs Riestra (88), 2025-11-15: el partido #22437 real. */
function escenarioBase() {
    Escenario::reset();
    Escenario::$partido = (object) ['id' => 22437, 'dia' => '2025-11-15 21:30:00',
        'equipol_id' => 77, 'equipov_id' => 88, 'golesl' => null, 'golesv' => null];
    Escenario::$equipoTm = [77 => '1029', 88 => '2222'];
    Escenario::$torneo = (object) ['torneo_id' => 5, 'nombre' => 'Clausura', 'year' => 2025,
        'tm_competition_id' => 'ARGC', 'tm_season_id' => '2024', 'ronda' => '15'];
}

echo "\n== 1) El fixture del club sólo trae la temporada en curso\n";
escenarioBase();
Escenario::$respuestas[TM . '/club/1029/fixtures'] = ['data' => ['games' => [
    juego('9001', '1029', '3333', '2026-02-10', '2025'),
    juego('9002', '4444', '1029', '2026-08-30', '2025'),
]]];
Escenario::$respuestas[TM . '/club/2222/fixtures'] = ['data' => ['games' => [
    juego('9003', '2222', '5555', '2026-03-01', '2025'),
]]];
Escenario::$respuestas[TM . '/competition/ARGC/games?seasonId=2024&gameDay=15'] = ['data' => ['games' => [
    juego('4750000', '1029', '2222', '2025-11-15', '2024'),
]]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === '4750000', 'lo encuentra por la competencia');
chequear(strpos((string) $r['como'], 'competencia') !== false, 'dice de dónde salió: ' . $r['como']);
chequear(count($r['rastro']) === 5, 'una fila de rastro por fuente (' . count($r['rastro']) . ')');
chequear(strpos(implode(' ', array_column($r['rastro'], 'nota')), 'otra temporada') !== false,
    'avisa que el club estaba mirando otra temporada');

echo "\n== 2) El staging ya lo tenía: cero llamadas\n";
escenarioBase();
Escenario::$staging = [(object) ['external_id' => '4750000', 'partido_id' => null, 'dia' => '2025-11-15 21:30:00',
    'club_external_id' => '1029', 'rival_external_id' => '2222']];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === '4750000', 'sale del staging');
chequear($r['llamadas'] === 0, 'no gastó ninguna llamada (' . $r['llamadas'] . ')');
chequear(count(Escenario::$pedidas) === 0, 'no tocó la API');

echo "\n== 3) TM ignora el seasonId: lo dice y no paga la ruta redundante\n";
escenarioBase();
$soloEnCurso = ['data' => ['games' => [juego('9001', '1029', '3333', '2026-02-10', '2025')]]];
Escenario::$respuestas[TM . '/club/1029/fixtures'] = $soloEnCurso;
Escenario::$respuestas[TM . '/competition/ARGC/games?seasonId=2024&gameDay=15'] = ['data' => ['games' => []]];
Escenario::$respuestas[TM . '/competition/ARGC/fixtures?seasonId=2024'] = $soloEnCurso;
Escenario::$respuestas[TM . '/competition/ARGC/fixtures'] = $soloEnCurso;
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === null, 'no inventa nada');
chequear(!in_array(TM . '/competition/ARGC/fixtures', Escenario::$pedidas, true),
    'no pide la ruta sin seasonId, que devolvería lo mismo');
chequear(strpos(implode(' ', $r['avisos']), 'ignora el `seasonId`') !== false,
    'avisa que esa ruta de la API ignora el seasonId');

echo "\n== 4) El gameDay nuestro no es el de TM: sigue con la temporada entera\n";
escenarioBase();
Escenario::$respuestas[TM . '/club/1029/fixtures'] = ['data' => ['games' => []]];
Escenario::$respuestas[TM . '/club/2222/fixtures'] = ['data' => ['games' => []]];
Escenario::$respuestas[TM . '/competition/ARGC/games?seasonId=2024&gameDay=15'] = ['data' => ['games' => [
    juego('9500', '6666', '7777', '2025-10-01', '2024'),
]]];
// Forma anidada del fixture de competencia, localía al revés y un día corrido.
Escenario::$respuestas[TM . '/competition/ARGC/fixtures?seasonId=2024'] = ['data' => ['fixtures' => [
    ['games' => [juego('4750000', '2222', '1029', '2025-11-16', '2024')]],
]]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === '4750000', 'lo encuentra igual');

echo "\n== 5) Sin nada cargado: no arranca y lo explica\n";
escenarioBase();
Escenario::$equipoTm = []; Escenario::$torneo = null;
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === null && $r['llamadas'] === 0, 'no gasta créditos si no tiene por dónde');
chequear(strpos(implode(' ', $r['avisos']), 'No tengo por dónde empezar') !== false, 'lo dice');

echo "\n== 6) Dos partidos posibles el mismo día: no elige\n";
escenarioBase();
Escenario::$respuestas[TM . '/club/1029/fixtures'] = ['data' => ['games' => [
    juego('8001', '1029', '2222', '2025-11-15', '2024'),
    juego('8002', '2222', '1029', '2025-11-15', '2024'),
]]];
Escenario::$respuestas[TM . '/clubs?ids[]=1029&ids[]=2222'] = ['data' => [
    ['id' => '1029', 'name' => 'Godoy Cruz'], ['id' => '2222', 'name' => 'Deportivo Riestra']]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === null, 'no elige ninguno');
chequear(count($r['candidatos']) === 2, 'ofrece los dos para elegir a mano');

echo "\n" . ($fallos ? "$fallos CHEQUEO(S) FALLIDO(S)\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);

}
