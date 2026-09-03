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
        public static $updates   = [];       // updates que hizo anotar()
        public static $inserts   = [];       // inserts que hizo anotar()
        public static function reset() {
            self::$equipoTm = []; self::$coaches = []; self::$torneo = null;
            self::$staging = []; self::$respuestas = []; self::$pedidas = [];
            self::$updates = []; self::$inserts = [];
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
        public function update($datos) { Escenario::$updates[] = $datos; return count(Escenario::$staging); }
        public function insert($datos) { Escenario::$inserts[] = $datos; return true; }
        public function pluck($a, $b = null) {
            if ($this->tabla === 'equipo_tm') return new FakeColeccion(Escenario::$equipoTm);
            if ($this->tabla === 'partido_tecnicos') return new FakeColeccion(Escenario::$coaches);
            return new FakeColeccion([]);
        }
    }

    // `now()` es un helper de Laravel y acá no hay Laravel. Sin esto, anotar()
    // llegaba al update, tiraba «Call to undefined function now()», el catch se
    // lo tragaba y devolvía false: el mismo síntoma que estamos arreglando.
    if (!function_exists('now')) {
        function now() { return date('Y-m-d H:i:s'); }
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

echo "\n== 1) TM está en otra temporada: no se gasta un crédito en la competencia\n";
// El fixture del club sólo devuelve la temporada en curso, y las rutas de
// competencia también: preguntarle a la competencia sería pagar por la misma
// respuesta. Verificado en producción (sep-2026) con ARGC.
escenarioBase();
Escenario::$respuestas[TM . '/club/1029/fixtures'] = ['data' => ['games' => [
    juego('9001', '1029', '3333', '2026-02-13', '2025'),
    juego('9002', '4444', '1029', '2026-10-16', '2025'),
]]];
Escenario::$respuestas[TM . '/club/2222/fixtures'] = ['data' => ['games' => [
    juego('9003', '2222', '5555', '2026-01-25', '2025'),
]]];
Escenario::$respuestas[TM . '/competition/ARGC/fixtures'] = ['data' => ['games' => [
    juego('9500', '1029', '2222', '2026-08-30', '2025'),
]]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === null, 'no inventa nada');
chequear(!in_array(TM . '/competition/ARGC/fixtures', Escenario::$pedidas, true),
    'NO pidió la competencia: ya sabía que TM está en otro año');
chequear(strpos(implode(' ', array_column($r['rastro'], 'nota')), 'No la consulté') !== false,
    'y deja dicho por qué no la consultó');
chequear(!empty($r['contexto']['temporada_ajena']),
    'marca temporada_ajena, que es lo que apaga el "cargá los ids" en la pantalla');
chequear(strpos($r['partida'], 'club de TM 1029') !== false, 'el punto de partida va aparte de la tabla');

echo "\n== 2) El staging ya lo tenía: cero llamadas\n";
escenarioBase();
Escenario::$staging = [(object) ['external_id' => '4750000', 'partido_id' => null, 'dia' => '2025-11-15 21:30:00',
    'club_external_id' => '1029', 'rival_external_id' => '2222']];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === '4750000', 'sale del staging');
chequear($r['llamadas'] === 0, 'no gastó ninguna llamada (' . $r['llamadas'] . ')');
chequear(count(Escenario::$pedidas) === 0, 'no tocó la API');

echo "\n== 3) La competencia contesta otra temporada: lo dice con todas las letras\n";
// Sin fixture de club que avise antes, la competencia se consulta igual y es
// ella la que muestra que TM ignora el seasonId.
escenarioBase();
Escenario::$respuestas[TM . '/competition/ARGC/fixtures'] = ['data' => ['games' => [
    juego('9500', '6666', '7777', '2026-07-23', '2025'),
]]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === null, 'no inventa nada');
chequear(strpos(implode(' ', $r['avisos']), 'siempre la temporada en curso') !== false,
    'avisa que la API no sabe traer temporadas cerradas');
chequear(!empty($r['contexto']['temporada_ajena']), 'y lo marca en el contexto');

echo "\n== 4) Partido de la temporada en curso con los clubes sin atar: lo encuentra por la competencia\n";
// Es para lo que sirve el camino 3: los otros no llegan porque no hay mapeos.
escenarioBase();
Escenario::$partido->dia = '2026-08-30 21:30:00';
Escenario::$equipoTm = [];
Escenario::$torneo->year = 2026;
Escenario::$torneo->tm_season_id = '2025';
// Forma anidada del fixture de competencia: fixtures[].games[]
Escenario::$respuestas[TM . '/competition/ARGC/fixtures'] = ['data' => ['fixtures' => [
    ['games' => [juego('4750000', '1029', '2222', '2026-08-30', '2025')]],
    ['games' => [juego('4750001', '3333', '4444', '2026-09-06', '2025')]],
]]];
$r = (new TmBuscarGameId)->buscar(22437);
chequear($r['game_id'] === '4750000', 'lo encuentra: ' . var_export($r['game_id'], true));
chequear($r['llamadas'] === 1, 'con una sola llamada (' . $r['llamadas'] . ')');

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

echo "\n== 7) El torneo sin id de competencia: lo dice en vez de callarse\n";
escenarioBase();
Escenario::$torneo->tm_competition_id = '';
Escenario::$respuestas[TM . '/club/1029/fixtures'] = ['data' => ['games' => []]];
Escenario::$respuestas[TM . '/club/2222/fixtures'] = ['data' => ['games' => []]];
$r = (new TmBuscarGameId)->buscar(22437);
$notas = implode(' ', array_column($r['rastro'], 'nota'));
chequear(strpos($notas, 'no tiene cargado su id de competencia') !== false,
    'deja fila de rastro explicando por qué no consultó la competencia');
// El contexto en crudo es lo que la pantalla usa para armar los links de "Cómo completarlo".
chequear($r['contexto']['torneo']['id'] === 5 && $r['contexto']['torneo']['comp'] === ''
    && $r['contexto']['clubes'][0]['tm'] === '1029' && $r['contexto']['dia'] === '2025-11-15',
    'devuelve el contexto en crudo (torneo + clubes + día) para armar los links');
chequear(empty($r['contexto']['temporada_ajena']),
    'sin temporada ajena detectada, así que la pantalla sí ofrece cargar los ids');

echo "\n== 9) anotar(): completa la fila que ya existe en vez de duplicarla\n";
// El bug que hacía parecer que el botón «Guardar» no hacía nada: el sondeo de
// un DT deja la fila con SU tecnico_id, y anotar() la buscaba exigiendo
// `tecnico_id null`. No la encontraba, insertaba una segunda fila con el mismo
// gameId, y después `pluck` se quedaba con la que tenía partido_id en null: el
// partido seguía figurando pendiente para siempre.
escenarioBase();
Escenario::$staging = [(object) ['id' => 5, 'partido_id' => null]];
$anotador = new TmBuscarGameId;
$ok = $anotador->anotar(22437, '4643244', 'prueba');
chequear($ok === true, 'devuelve true');
chequear(count(Escenario::$updates) === 1, 'completa la fila existente (' . count(Escenario::$updates) . ' update)');
chequear(count(Escenario::$inserts) === 0, 'y NO inserta una fila nueva (' . count(Escenario::$inserts) . ' insert)');
chequear(isset(Escenario::$updates[0]['partido_id']) && Escenario::$updates[0]['partido_id'] === 22437,
    'con el partido_id correcto');

echo "\n== 10) anotar(): si el gameId ya es de otro partido, lo dice\n";
escenarioBase();
Escenario::$staging = [(object) ['id' => 7, 'partido_id' => 99999]];
$anotador2 = new TmBuscarGameId;
$ok2 = $anotador2->anotar(22437, '4643244', 'prueba');
chequear($ok2 === false, 'no devuelve true');
chequear(strpos($anotador2->ultimoError, '#99999') !== false,
    'y el motivo nombra al otro partido: ' . var_export($anotador2->ultimoError, true));
chequear(count(Escenario::$updates) === 0 && count(Escenario::$inserts) === 0, 'sin tocar nada');

echo "\n== 11) anotar(): si no hay NADA en staging, inserta la fila\n";
// Este camino no lo tocaba ningún caso: los dos anteriores sembraban staging,
// así que siempre salían por el update o por el «ya es de otro partido». Al
// reescribir el lookup se fue la variable `$clave` que armaba las columnas
// clave del insert, y el banco siguió en verde mientras producción tiraba 68
// «Undefined variable: clave» seguidos. Un camino sin caso es un camino roto
// que todavía no se notó.
escenarioBase();
Escenario::$staging = [];
$anotador3 = new TmBuscarGameId;
$ok3 = $anotador3->anotar(22437, '4643244', 'prueba');
chequear($ok3 === true, 'devuelve true (motivo: ' . var_export($anotador3->ultimoError, true) . ')');
chequear(count(Escenario::$inserts) === 1, 'inserta una fila (' . count(Escenario::$inserts) . ')');
$fila = Escenario::$inserts ? Escenario::$inserts[0] : [];
chequear(isset($fila['fuente']) && $fila['fuente'] === 'transfermarkt', 'con la fuente');
chequear(isset($fila['external_id']) && (string) $fila['external_id'] === '4643244', 'con el gameId');
chequear(array_key_exists('tecnico_id', $fila) && $fila['tecnico_id'] === null,
    'con tecnico_id en null: la fila es del partido, no del sondeo de un DT');
chequear(isset($fila['partido_id']) && $fila['partido_id'] === 22437, 'atada al partido');
chequear(count(Escenario::$updates) === 0, 'y sin updates');

echo "\n" . ($fallos ? "$fallos CHEQUEO(S) FALLIDO(S)\n" : "Todo en verde.\n");
exit($fallos ? 1 : 0);

}
