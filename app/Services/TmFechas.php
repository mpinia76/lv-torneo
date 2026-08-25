<?php

namespace App\Services;

use App\Http\Controllers\JugadorController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa las fichas que no tienen fecha de nacimiento con el perfil de
 * Transfermarkt.
 *
 * Por qué existe: la fecha es el desempate de `DuplicadosPersonas::puntuar()`.
 * Una persona sin fecha no suma el +12 de "misma fecha" ni se lleva la resta de
 * "fechas distintas", así que queda flotando en el medio y ensucia la pantalla
 * de repetidos (los árbitros, que casi nunca traen fecha, son el caso típico).
 * Cada fecha que se completa es un par menos que hay que mirar a mano.
 *
 * Cuesta una llamada cada 50 personas: es la misma API y el mismo formato que
 * usa `TmDetallePartido` para resolver los jugadores de un partido
 * (`/players?ids[]=`, `/coaches?ids[]=`, `/referees?ids[]=`).
 *
 * De dónde sale el id de TM de cada quien:
 *   jugadores → `jugador_tm.tm_player_id`, y si no está, la URL de la ficha
 *               (`jugadors.transfermarkt_url`, .../profil/spieler/{id})
 *   técnicos  → `tecnicos.transfermarkt_url` (.../profil/trainer/{id})
 *   árbitros  → `arbitro_tm.tm_referee_id` (+ la URL, si la tabla la tiene)
 *
 * OJO con `jugador_tm`: las fusiones de personas no repuntan esa tabla, así que
 * puede tener filas apuntando a jugadores que ya no existen. Por eso todas las
 * consultas de acá salen DESDE `personas`/`jugadors` y usan `jugador_tm` como
 * LEFT JOIN: un id muerto no aparece nunca.
 *
 * Nunca pisa un dato cargado: escribe solo donde el campo está vacío. Es a
 * propósito — Transfermarkt manda fechas mal seguido, y lo que ya revisaste a
 * mano vale más que lo que diga la API.
 */
class TmFechas
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Cuántos ids entran en una llamada. Mismo tope que usa el importador. */
    const POR_LLAMADA = 50;

    /**
     * Todas las personas sin fecha de nacimiento, ordenadas por apellido.
     *
     * Devuelve [persona_id => ['tipo', 'rol_id', 'tm', 'apellido', 'nombre']],
     * con `tm` en null cuando no tenemos con qué ir a buscarla.
     */
    public static function pendientes(): array
    {
        $out = [];

        // ── Jugadores ──────────────────────────────────────────────────────
        $q = DB::table('personas as p')
            ->join('jugadors as j', 'j.persona_id', '=', 'p.id')
            ->whereNull('p.nacimiento');

        $cols = ['p.id as persona_id', 'p.apellido', 'p.nombre', 'j.id as rol_id'];
        if (Schema::hasTable('jugador_tm')) {
            $q->leftJoin('jugador_tm as t', 't.jugador_id', '=', 'j.id');
            $cols[] = 't.tm_player_id as tm';
        }
        $tieneUrl = self::tieneCol('jugadors', 'transfermarkt_url');
        if ($tieneUrl) $cols[] = 'j.transfermarkt_url as url';

        foreach ($q->select($cols)->get() as $f) {
            $tm = self::limpiar(isset($f->tm) ? $f->tm : null);
            if ($tm === null && $tieneUrl) $tm = self::idDeUrl(isset($f->url) ? $f->url : null, 'spieler');
            self::sumar($out, $f, 'jugador', $tm);
        }

        // ── Técnicos ───────────────────────────────────────────────────────
        if (self::tieneCol('tecnicos', 'transfermarkt_url')) {
            $filas = DB::table('personas as p')
                ->join('tecnicos as tc', 'tc.persona_id', '=', 'p.id')
                ->whereNull('p.nacimiento')
                ->select('p.id as persona_id', 'p.apellido', 'p.nombre', 'tc.id as rol_id', 'tc.transfermarkt_url as url')
                ->get();

            foreach ($filas as $f) {
                self::sumar($out, $f, 'tecnico', self::idDeUrl($f->url, 'trainer'));
            }
        }

        // ── Árbitros ───────────────────────────────────────────────────────
        $q = DB::table('personas as p')
            ->join('arbitros as a', 'a.persona_id', '=', 'p.id')
            ->whereNull('p.nacimiento');

        $cols = ['p.id as persona_id', 'p.apellido', 'p.nombre', 'a.id as rol_id'];
        $hayMapa = Schema::hasTable('arbitro_tm');
        if ($hayMapa) {
            $q->leftJoin('arbitro_tm as at', 'at.arbitro_id', '=', 'a.id');
            $cols[] = 'at.tm_referee_id as tm';
        }
        $tieneUrl = self::tieneCol('arbitros', 'transfermarkt_url');
        if ($tieneUrl) $cols[] = 'a.transfermarkt_url as url';

        foreach ($q->select($cols)->get() as $f) {
            $tm = self::limpiar(isset($f->tm) ? $f->tm : null);
            if ($tm === null && $tieneUrl) $tm = self::idDeUrl(isset($f->url) ? $f->url : null, 'schiedsrichter');
            self::sumar($out, $f, 'arbitro', $tm);
        }

        uasort($out, function ($a, $b) {
            $cmp = strcasecmp((string) $a['apellido'], (string) $b['apellido']);
            return $cmp !== 0 ? $cmp : strcasecmp((string) $a['nombre'], (string) $b['nombre']);
        });

        return $out;
    }

    /**
     * Baja los perfiles y completa lo que esté vacío.
     *
     * $limite acota cuántas personas se procesan en esta pasada (0 = todas).
     * Con 500 son 10 llamadas, que es lo que entra cómodo en un request.
     */
    public static function completar(int $limite = 500, array $pendientes = null): array
    {
        $pendientes = $pendientes !== null ? $pendientes : self::pendientes();

        $r = [
            'personas'   => 0,   // cuántas se consultaron
            'llamadas'   => 0,
            'sin_tm'     => 0,   // no hay id de TM: no se puede ni intentar
            'sin_perfil' => 0,   // la API no devolvió esa ficha
            'sin_fecha'  => 0,   // vino el perfil pero sin fecha de nacimiento
            'campos'     => [],  // campo => cuántas veces se completó
            'quedan'     => 0,
            'errores'    => [],
        ];

        $porTipo = [];
        foreach ($pendientes as $personaId => $d) {
            if (empty($d['tm'])) { $r['sin_tm']++; continue; }
            $porTipo[$d['tipo']][] = [
                'persona' => (int) $personaId,
                'rol_id'  => (int) $d['rol_id'],
                'tm'      => (string) $d['tm'],
            ];
        }

        $paises = JugadorController::paisesTM();
        $cortado = false;

        foreach (['jugador', 'tecnico', 'arbitro'] as $tipo) {
            if (empty($porTipo[$tipo])) continue;

            foreach (array_chunk($porTipo[$tipo], self::POR_LLAMADA) as $tanda) {
                if ($limite > 0 && $r['personas'] >= $limite) { $cortado = true; break 2; }

                $ids = [];
                foreach ($tanda as $fila) $ids[] = $fila['tm'];

                $perfiles = self::traerPerfiles($tipo, $ids, $r);

                foreach ($tanda as $fila) {
                    $r['personas']++;
                    if (!isset($perfiles[$fila['tm']])) { $r['sin_perfil']++; continue; }

                    $datos = self::datosDePerfil($perfiles[$fila['tm']], $paises);
                    if (empty($datos['nacimiento'])) $r['sin_fecha']++;

                    try {
                        self::aplicar($tipo, $fila, $datos, $r);
                    } catch (\Exception $e) {
                        $r['errores'][] = 'Persona #' . $fila['persona'] . ': ' . $e->getMessage();
                    }
                }
            }
        }

        if ($cortado) {
            $r['quedan'] = 0;
            foreach ($porTipo as $filas) $r['quedan'] += count($filas);
            $r['quedan'] = max(0, $r['quedan'] - $r['personas']);
        }

        return $r;
    }

    // ------------------------------------------------------------------
    // Internas
    // ------------------------------------------------------------------

    private static function sumar(array &$out, $fila, string $tipo, $tm): void
    {
        $id = (int) $fila->persona_id;

        // Una persona puede tener dos roles (jugador que después fue DT). Nos
        // quedamos con el primero que traiga id de TM.
        if (isset($out[$id]) && empty($out[$id]['tm'])) {
            if (!empty($tm)) {
                $out[$id]['tipo']   = $tipo;
                $out[$id]['rol_id'] = (int) $fila->rol_id;
                $out[$id]['tm']     = $tm;
            }
            return;
        }
        if (isset($out[$id])) return;

        $out[$id] = [
            'tipo'     => $tipo,
            'rol_id'   => (int) $fila->rol_id,
            'tm'       => $tm,
            'apellido' => $fila->apellido,
            'nombre'   => $fila->nombre,
        ];
    }

    private static function limpiar($valor): ?string
    {
        $v = trim((string) $valor);
        return ($v === '' || $v === '0') ? null : $v;
    }

    /** Saca el id numérico de una URL de Transfermarkt. */
    private static function idDeUrl($url, string $tramo): ?string
    {
        if (!$url) return null;
        return preg_match('#/' . $tramo . '/(\d+)#', (string) $url, $m) ? $m[1] : null;
    }

    /**
     * Una llamada por tanda. Las rutas alternativas son las mismas que probaba
     * el importador: la API cambió de nombre alguna vez y conviene no atarse.
     */
    private static function traerPerfiles(string $tipo, array $ids, array &$r): array
    {
        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));

        if ($tipo === 'jugador') {
            $rutas = ['/players?' . $qs];
            $ramas = ['players'];
            $claves = ['id', 'playerId'];
        } elseif ($tipo === 'tecnico') {
            $rutas = ['/coaches?' . $qs, '/trainers?' . $qs, '/managers?' . $qs];
            $ramas = ['coaches', 'trainers', 'managers'];
            $claves = ['id', 'coachId', 'trainerId'];
        } else {
            $rutas = ['/referees?' . $qs, '/officials?' . $qs];
            $ramas = ['referees', 'officials'];
            $claves = ['id', 'refereeId'];
        }

        $out = [];

        foreach ($rutas as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $r['llamadas']++;
            if (!is_array($json) || empty($json)) continue;

            $data = isset($json['data']) ? $json['data'] : $json;
            foreach ($ramas as $rama) {
                if (isset($data[$rama]) && is_array($data[$rama])) { $data = $data[$rama]; break; }
            }

            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = null;
                foreach ($claves as $k) {
                    if (isset($perfil[$k]) && $perfil[$k] !== '') { $id = $perfil[$k]; break; }
                }
                if ($id === null && !is_int($clave)) $id = $clave;
                if ($id === null) continue;
                $out[(string) $id] = $perfil;
            }

            if ($out) break;
        }

        return $out;
    }

    /**
     * Traduce el perfil a nuestros campos.
     *
     * Igual que en `TmDetallePartido::personaDesdePerfil()`, hay que leer las
     * dos formas que conviven en tmapi: jugadores y DTs vienen anidados
     * (`lifeDates.dateOfBirth`) y los árbitros vienen planos (`dateOfBirth`).
     * Leyendo solo la anidada, todo árbitro queda sin fecha.
     */
    private static function datosDePerfil(array $p, array $paises): array
    {
        $aFecha = function ($raw) {
            if (!$raw) return null;
            try { return Carbon::parse($raw)->format('Y-m-d'); } catch (\Exception $e) { return null; }
        };

        $nacimiento = $aFecha(isset($p['lifeDates']['dateOfBirth'])
            ? $p['lifeDates']['dateOfBirth']
            : (isset($p['dateOfBirth']) ? $p['dateOfBirth'] : null));

        $fallecimiento = $aFecha(isset($p['lifeDates']['dateOfDeath'])
            ? $p['lifeDates']['dateOfDeath']
            : (isset($p['dateOfDeath']) ? $p['dateOfDeath'] : null));

        $ciudad = trim((string) (isset($p['birthPlaceDetails']['placeOfBirth'])
            ? $p['birthPlaceDetails']['placeOfBirth']
            : (isset($p['placeOfBirth']) ? $p['placeOfBirth'] : '')));

        $nacId = 0;
        $candidatos = [
            isset($p['nationalityDetails']['nationalities']['nationalityId']) ? $p['nationalityDetails']['nationalities']['nationalityId'] : null,
            isset($p['nationalityDetails']['nationalities'][0]['nationalityId']) ? $p['nationalityDetails']['nationalities'][0]['nationalityId'] : null,
            isset($p['nationalities']['nationalityId']) ? $p['nationalities']['nationalityId'] : null,
            isset($p['nationalities'][0]['nationalityId']) ? $p['nationalities'][0]['nationalityId'] : null,
            isset($p['nationalityId']) ? $p['nationalityId'] : null,
        ];
        foreach ($candidatos as $cand) {
            if ((int) $cand > 0) { $nacId = (int) $cand; break; }
        }
        $nacionalidad = ($nacId && isset($paises[$nacId])) ? $paises[$nacId] : null;

        $altura = isset($p['attributes']['height']) ? $p['attributes']['height'] : null;
        $altura = ($altura !== null && is_numeric($altura)) ? (float) $altura : null;

        $grupo = strtoupper((string) (isset($p['attributes']['positionGroup']) ? $p['attributes']['positionGroup'] : ''));
        $tipoJugador = null;
        if ($grupo === 'GOALKEEPER') $tipoJugador = 'Arquero';
        elseif ($grupo === 'DEFENDER') $tipoJugador = 'Defensor';
        elseif ($grupo === 'MIDFIELD' || $grupo === 'MIDFIELDER') $tipoJugador = 'Medio';
        elseif ($grupo === 'FORWARD' || $grupo === 'STRIKER' || $grupo === 'ATTACK') $tipoJugador = 'Delantero';

        $pieRaw = trim((string) (isset($p['attributes']['preferredFoot']['name']) ? $p['attributes']['preferredFoot']['name'] : ''));
        $pie = null;
        if ($pieRaw === 'Derecho') $pie = 'Derecha';
        elseif ($pieRaw === 'Izquierdo') $pie = 'Izquierda';
        elseif ($pieRaw === 'Ambidiestro') $pie = 'Ambas';

        return [
            'nacimiento'    => $nacimiento,
            'fallecimiento' => $fallecimiento,
            'ciudad'        => $ciudad !== '' ? $ciudad : null,
            'nacionalidad'  => $nacionalidad,
            'altura'        => $altura,
            'tipoJugador'   => $tipoJugador,
            'pie'           => $pie,
        ];
    }

    /**
     * Escribe solo los campos vacíos.
     *
     * Va con `DB::table()` a propósito: el `saved()` del modelo Persona
     * reindexa duplicados, y acá no se toca ni el nombre ni el apellido, así
     * que no hay nada que reindexar.
     */
    private static function aplicar(string $tipo, array $fila, array $datos, array &$r): void
    {
        $persona = DB::table('personas')->where('id', $fila['persona'])->first();
        if (!$persona) return;

        $set = [];
        foreach (['nacimiento', 'fallecimiento', 'ciudad', 'altura', 'nacionalidad'] as $campo) {
            if (empty($datos[$campo])) continue;
            if (!self::vacio(isset($persona->$campo) ? $persona->$campo : null)) continue;
            $set[$campo] = $datos[$campo];
        }

        if ($set) {
            if (self::tieneCol('personas', 'updated_at')) $set['updated_at'] = now();
            DB::table('personas')->where('id', $fila['persona'])->update($set);
            foreach ($set as $campo => $v) {
                if ($campo === 'updated_at') continue;
                $r['campos'][$campo] = (isset($r['campos'][$campo]) ? $r['campos'][$campo] : 0) + 1;
            }
        }

        if ($tipo !== 'jugador') return;

        $jugador = DB::table('jugadors')->where('id', $fila['rol_id'])->first();
        if (!$jugador) return;

        $setJ = [];
        foreach (['tipoJugador', 'pie'] as $campo) {
            if (empty($datos[$campo])) continue;
            if (!self::tieneCol('jugadors', $campo)) continue;
            if (!self::vacio(isset($jugador->$campo) ? $jugador->$campo : null)) continue;
            $setJ[$campo] = $datos[$campo];
        }

        if (self::tieneCol('jugadors', 'transfermarkt_url')
            && self::vacio(isset($jugador->transfermarkt_url) ? $jugador->transfermarkt_url : null)) {
            $setJ['transfermarkt_url'] = 'https://www.transfermarkt.es/-/profil/spieler/' . $fila['tm'];
        }

        if ($setJ) {
            if (self::tieneCol('jugadors', 'updated_at')) $setJ['updated_at'] = now();
            DB::table('jugadors')->where('id', $fila['rol_id'])->update($setJ);
            foreach ($setJ as $campo => $v) {
                if ($campo === 'updated_at') continue;
                $r['campos'][$campo] = (isset($r['campos'][$campo]) ? $r['campos'][$campo] : 0) + 1;
            }
        }
    }

    /**
     * `self::tieneCol()` pega contra information_schema cada vez que se
     * llama. Acá se llama una vez por persona y por campo, así que sin este
     * cache serían miles de consultas de más por tanda.
     */
    private static function tieneCol(string $tabla, string $columna): bool
    {
        static $cache = [];
        $k = $tabla . '.' . $columna;
        if (!array_key_exists($k, $cache)) {
            try {
                $cache[$k] = Schema::hasColumn($tabla, $columna);
            } catch (\Exception $e) {
                $cache[$k] = false;
            }
        }
        return $cache[$k];
    }

    /** Vacío de verdad: null, string vacío, 0 o la fecha cero de MySQL. */
    private static function vacio($valor): bool
    {
        if ($valor === null) return true;
        $v = trim((string) $valor);
        return $v === '' || $v === '0' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00';
    }
}
