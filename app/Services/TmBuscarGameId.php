<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encuentra el gameId de Transfermarkt de un partido que ya está en la base.
 *
 * Sin gameId no hay nada que hacer con TM: ni el link a la ficha ni rehacerle
 * el detalle. Hasta ahora el gameId sólo aparecía si el partido había pasado
 * por el importador de DTs, y para todos los demás había que ir a buscar la
 * URL a mano y pegarla. Esto lo busca solo, con lo que ya tenemos cargado:
 * los dos clubes (`equipo_tm`), la fecha y, si hace falta, los DTs.
 *
 * Dos caminos, en este orden:
 *
 *   1. El fixture del club (`/club/{id}/fixtures`). Una llamada trae todos los
 *      partidos del club en la temporada, con gameId, los dos clubes y la
 *      fecha. Es el camino barato y el que sirve para lo que se está cargando
 *      ahora.
 *   2. Los partidos del DT (`/coach/{id}/performance-game`), que es lo que usa
 *      el importador de partidos. Va hacia atrás en el tiempo, así que rescata
 *      los partidos viejos que el fixture del club ya no lista.
 *
 * Nunca inventa: si en la ventana de fechas hay más de un candidato posible, no
 * elige ninguno y lo dice. Un gameId equivocado escribe la alineación de otro
 * partido encima del que estabas mirando.
 */
class TmBuscarGameId
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /**
     * Cuántos días de diferencia se le perdonan a la fecha.
     *
     * No es capricho: TM guarda la fecha original de los partidos postergados y
     * la base tiene la real, y además la hora UTC puede correr el día. Con los
     * dos clubes identificados, tres días no alcanzan para confundirse de
     * partido (dos equipos no juegan dos veces en esa ventana).
     */
    const DIAS = 3;

    /** Los fixtures de un club se reusan mientras se corrigen varias filas. */
    const TTL = 600;

    /** @var array */
    private $avisos = [];

    /** @var int */
    private $llamadas = 0;

    /**
     * Busca el gameId de un partido.
     *
     * Devuelve ['game_id' => string|null, 'como' => string|null,
     *           'avisos' => string[], 'llamadas' => int].
     */
    public function buscar($partidoId): array
    {
        $this->avisos   = [];
        $this->llamadas = 0;

        $partido = DB::table('partidos')->where('id', (int) $partidoId)->first();

        if (!$partido) {
            return $this->resultado(null, null, 'No encontré el partido #' . (int) $partidoId . ' en la base.');
        }

        if (empty($partido->dia)) {
            return $this->resultado(null, null, 'El partido no tiene fecha cargada: sin fecha no hay forma de '
                . 'reconocerlo entre los de Transfermarkt.');
        }

        $clubes  = $this->clubesTm([$partido->equipol_id, $partido->equipov_id]);
        $tmLocal = isset($clubes[(int) $partido->equipol_id]) ? (string) $clubes[(int) $partido->equipol_id] : null;
        $tmVisit = isset($clubes[(int) $partido->equipov_id]) ? (string) $clubes[(int) $partido->equipov_id] : null;

        // Sin ningún club mapeado todavía queda el camino de los DTs: sus
        // partidos son suyos, así que ahí alcanza con la fecha exacta (nadie
        // dirige dos partidos el mismo día).
        $coaches = $this->coachesDelPartido($partido->id);

        if (!$tmLocal && !$tmVisit && !$coaches) {
            return $this->resultado(null, null, 'Ninguno de los dos equipos está atado a un club de Transfermarkt '
                . '(tabla `equipo_tm`) y el partido no tiene DTs con su URL de Transfermarkt cargada, '
                . 'así que no tengo por dónde empezar a buscar.');
        }

        // ── 1) El fixture del club ──────────────────────────────────────────
        foreach ([$tmLocal, $tmVisit] as $club) {
            if (!$club) {
                continue;
            }

            $juegos = $this->fixtureDeClub($club);

            if ($juegos === null) {
                continue;
            }

            $hallado = $this->elegir($juegos, $partido->dia, $tmLocal, $tmVisit);

            if ($hallado) {
                return $this->resultado($hallado, 'el fixture del club ' . $club);
            }
        }

        // ── 2) Los partidos de los DTs ──────────────────────────────────────
        foreach ($coaches as $coachId) {
            $juegos = $this->partidosDelDt($coachId);

            if ($juegos === null) {
                continue;
            }

            $hallado = $this->elegir($juegos, $partido->dia, $tmLocal, $tmVisit);

            if ($hallado) {
                return $this->resultado($hallado, 'los partidos del DT ' . $coachId);
            }
        }

        return $this->resultado(null, null, 'Transfermarkt no me devolvió ningún partido de estos dos equipos '
            . 'alrededor del ' . substr((string) $partido->dia, 0, 10) . '.');
    }

    /**
     * Deja anotado el gameId en el staging del importador.
     *
     * Es la misma fila que usa `ImportPartidosController::persistirFixture()`
     * (clave: fuente + external_id + tecnico_id null), así que si el importador
     * ya la tenía no se duplica ni se le pisa nada: sólo se le completa el
     * `partido_id`. A partir de ahí el partido tiene su link a TM en los
     * controles y su "Rehacer" va derecho a la vista previa.
     *
     * Es un extra: si falla, quien llama sigue igual.
     */
    public function anotar($partidoId, $gameId, $motivo = 'encontrado en Transfermarkt')
    {
        $gameId    = trim((string) $gameId);
        $partidoId = (int) $partidoId;

        if ($partidoId <= 0 || !preg_match('/^\d{1,20}$/', $gameId)) {
            return false;
        }

        try {
            if (!Schema::hasTable('import_partidos')) {
                return false;
            }

            $clave = ['fuente' => 'transfermarkt', 'external_id' => $gameId, 'tecnico_id' => null];
            $ya    = DB::table('import_partidos')->where($clave)->first();

            if ($ya) {
                if (!$ya->partido_id) {
                    DB::table('import_partidos')->where('id', $ya->id)
                        ->update(['partido_id' => $partidoId, 'updated_at' => now()]);
                }

                return true;
            }

            $partido = DB::table('partidos')->where('id', $partidoId)->first();

            if (!$partido) {
                return false;
            }

            DB::table('import_partidos')->insert($clave + [
                'club_nombre'  => $this->nombreEquipo($partido->equipol_id),
                'rival_nombre' => $this->nombreEquipo($partido->equipov_id),
                'local'        => 1,
                'dia'          => $partido->dia,
                'goles_favor'  => $partido->golesl,
                'goles_contra' => $partido->golesv,
                'equipo_id'    => $partido->equipol_id,
                'rival_id'     => $partido->equipov_id,
                'partido_id'   => $partidoId,
                // El partido ya está cargado: la fila existe para guardar el
                // gameId, no para que el importador lo cree de nuevo.
                'estado'       => 'duplicado',
                'motivo'       => $motivo,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ══════════════════════════════ búsqueda ══════════════════════════════

    /**
     * De todos los partidos que trajo TM, el que es este.
     *
     * Pide que la fecha caiga en la ventana y que los clubes coincidan con los
     * que conocemos. Si queda más de un candidato, devuelve null: mejor pedir
     * la URL a mano que escribir el detalle de otro partido.
     */
    private function elegir(array $juegos, $dia, $tmLocal, $tmVisit)
    {
        $fecha      = strtotime(substr((string) $dia, 0, 10));
        $candidatos = [];

        // Si no conocemos ninguno de los dos clubes, lo único que queda para
        // reconocer el partido es la fecha, así que se exige exacta. Esto sólo
        // pasa por el camino del DT, y ahí es seguro: nadie dirige dos partidos
        // el mismo día.
        $ventana = ($tmLocal || $tmVisit) ? self::DIAS : 0;

        foreach ($juegos as $j) {
            if (empty($j['game_id']) || empty($j['dia'])) {
                continue;
            }

            $diff = abs((strtotime($j['dia']) - $fecha) / 86400);

            if ($diff > $ventana) {
                continue;
            }

            if (!$this->clubesCoinciden($j, $tmLocal, $tmVisit)) {
                continue;
            }

            $clave = (string) $j['game_id'];

            if (!isset($candidatos[$clave]) || $diff < $candidatos[$clave]) {
                $candidatos[$clave] = $diff;
            }
        }

        if (empty($candidatos)) {
            return null;
        }

        if (count($candidatos) === 1) {
            return (string) key($candidatos);
        }

        // Con menos de dos clubes identificados, dos partidos en la misma
        // ventana pueden ser contra rivales distintos: no hay con qué
        // desempatar.
        if (!($tmLocal && $tmVisit)) {
            $this->avisos[] = 'Hay ' . count($candidatos) . ' partidos posibles alrededor de esa fecha y no tengo '
                . 'los dos equipos atados a clubes de Transfermarkt, así que no puedo saber cuál es '
                . '(gameId ' . implode(', ', array_keys($candidatos)) . ').';
            return null;
        }

        // Con los dos clubes identificados sí: es el mismo cruce repetido (ida
        // y vuelta muy pegadas, o una fecha corrida). Gana el más cercano.
        $mejor     = min($candidatos);
        $empatados = array_keys(array_filter($candidatos, function ($d) use ($mejor) { return $d === $mejor; }));

        if (count($empatados) > 1) {
            $this->avisos[] = 'Encontré ' . count($empatados) . ' partidos de Transfermarkt el mismo día entre estos '
                . 'dos equipos (gameId ' . implode(', ', $empatados) . '). No elijo ninguno: cargá la URL a mano.';
            return null;
        }

        return (string) $empatados[0];
    }

    /**
     * ¿Los clubes del partido de TM son los nuestros?
     *
     * Con los dos mapeados se exige el par completo, en cualquier orden: si TM
     * tiene la localía al revés (cancha neutral, partido mudado) igual es este
     * partido. Con uno solo mapeado alcanza con que ese esté; la ventana de
     * fechas hace el resto. Con ninguno no hay nada que comparar y decide sólo
     * la fecha exacta (ver la ventana en `elegir()`).
     */
    private function clubesCoinciden(array $j, $tmLocal, $tmVisit)
    {
        if (!$tmLocal && !$tmVisit) {
            return true;
        }

        $enJuego = [];

        foreach (['home', 'away'] as $lado) {
            if (!empty($j[$lado])) {
                $enJuego[] = (string) $j[$lado];
            }
        }

        if (empty($enJuego)) {
            return false;
        }

        if ($tmLocal && $tmVisit) {
            return in_array((string) $tmLocal, $enJuego, true)
                && in_array((string) $tmVisit, $enJuego, true);
        }

        $unico = $tmLocal ?: $tmVisit;

        return in_array((string) $unico, $enJuego, true);
    }

    // ══════════════════════════════ fuentes ══════════════════════════════

    /**
     * Los partidos del fixture de un club, aplanados a lo que nos importa.
     *
     * Devuelve null si TM no contestó (y deja el aviso), array si contestó,
     * aunque venga vacío.
     */
    private function fixtureDeClub($clubId)
    {
        $llave = 'tm.fixture.club.' . $clubId;

        if (Cache::has($llave)) {
            return Cache::get($llave);
        }

        $this->llamadas++;
        $resp = HttpHelper::getJson(self::TMAPI . '/club/' . rawurlencode($clubId) . '/fixtures');

        if (!is_array($resp)) {
            $this->avisos[] = 'No pude traer el fixture del club ' . $clubId . ' de Transfermarkt.';
            return null;
        }

        $data   = isset($resp['data']) ? $resp['data'] : $resp;
        $juegos = isset($data['games']) && is_array($data['games']) ? $data['games'] : [];
        $out    = [];

        foreach ($juegos as $g) {
            if (!is_array($g)) {
                continue;
            }

            $bd = isset($g['baseDetails']) && is_array($g['baseDetails']) ? $g['baseDetails'] : [];

            $out[] = [
                'game_id' => isset($g['gameId']) ? (string) $g['gameId'] : null,
                'home'    => isset($g['homeClub']['clubId']) ? (string) $g['homeClub']['clubId'] : null,
                'away'    => isset($g['awayClub']['clubId']) ? (string) $g['awayClub']['clubId'] : null,
                'dia'     => $this->fecha(isset($bd['date']) ? $bd['date'] : null),
            ];
        }

        Cache::put($llave, $out, self::TTL);

        return $out;
    }

    /** Lo mismo, pero de los partidos dirigidos por un DT. */
    private function partidosDelDt($coachId)
    {
        $llave = 'tm.partidos.dt.' . $coachId;

        if (Cache::has($llave)) {
            return Cache::get($llave);
        }

        $this->llamadas++;
        $resp = HttpHelper::getJson(self::TMAPI . '/coach/' . rawurlencode($coachId) . '/performance-game');

        $perf = null;

        if (is_array($resp)) {
            if (isset($resp['data']['performance']) && is_array($resp['data']['performance'])) {
                $perf = $resp['data']['performance'];
            } elseif (isset($resp['performance']) && is_array($resp['performance'])) {
                $perf = $resp['performance'];
            }
        }

        if ($perf === null) {
            $this->avisos[] = 'No pude traer los partidos del DT ' . $coachId . ' de Transfermarkt.';
            return null;
        }

        $out = [];

        foreach ($perf as $g) {
            if (!is_array($g)) {
                continue;
            }

            $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
            $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

            // Acá no hay local/visitante: son "el club del DT" y "el rival".
            // Como el apareo mira el par completo sin orden, da igual.
            $out[] = [
                'game_id' => isset($gi['gameId']) ? (string) $gi['gameId'] : null,
                'home'    => isset($ci['club']['clubId']) ? (string) $ci['club']['clubId'] : null,
                'away'    => isset($ci['opponent']['clubId']) ? (string) $ci['opponent']['clubId'] : null,
                'dia'     => $this->fecha(isset($gi['date']) ? $gi['date'] : null),
            ];
        }

        Cache::put($llave, $out, self::TTL);

        return $out;
    }

    // ══════════════════════════════ auxiliares ══════════════════════════════

    /** La fecha de TM, que a veces es string y a veces un objeto. */
    private function fecha($raw)
    {
        if (is_array($raw)) {
            foreach (['dateTimeUTC', 'dateTime', 'date'] as $k) {
                if (!empty($raw[$k]) && !is_array($raw[$k])) {
                    $raw = $raw[$k];
                    break;
                }
            }
        }

        if (!$raw || is_array($raw)) {
            return null;
        }

        $ts = strtotime((string) $raw);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    /** [equipo_id => tm_club_id] para los equipos que se le pasen. */
    private function clubesTm(array $equipoIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $equipoIds)));

        if (!$ids || !Schema::hasTable('equipo_tm')) {
            return [];
        }

        return DB::table('equipo_tm')
            ->whereIn('equipo_id', $ids)
            ->whereNotNull('tm_club_id')
            ->pluck('tm_club_id', 'equipo_id')
            ->all();
    }

    /** Los coachId de Transfermarkt de los DTs que dirigieron el partido. */
    private function coachesDelPartido($partidoId): array
    {
        if (!Schema::hasTable('partido_tecnicos')) {
            return [];
        }

        $urls = DB::table('partido_tecnicos')
            ->join('tecnicos', 'partido_tecnicos.tecnico_id', '=', 'tecnicos.id')
            ->where('partido_tecnicos.partido_id', (int) $partidoId)
            ->whereNotNull('tecnicos.transfermarkt_url')
            ->pluck('tecnicos.transfermarkt_url')
            ->all();

        $ids = [];

        foreach ($urls as $url) {
            if (preg_match('#/trainer/(\d+)#', (string) $url, $m)) {
                $ids[$m[1]] = true;
            }
        }

        return array_keys($ids);
    }

    private function nombreEquipo($equipoId)
    {
        $e = DB::table('equipos')->where('id', (int) $equipoId)->first();

        return $e ? $e->nombre : null;
    }

    private function resultado($gameId, $como, $aviso = null): array
    {
        if ($aviso !== null) {
            $this->avisos[] = $aviso;
        }

        return [
            'game_id'  => $gameId,
            'como'     => $como,
            'avisos'   => $this->avisos,
            'llamadas' => $this->llamadas,
        ];
    }
}
