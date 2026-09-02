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
 * el staging, los dos clubes (`equipo_tm`), la fecha, los DTs y —cuando el
 * torneo lo tiene cargado— la competencia de Transfermarkt.
 *
 * Cuatro caminos, en este orden:
 *
 *   0. El **staging** (`import_partidos`). No gasta ni una llamada: si alguna
 *      vez se bajó el fixture de esa competencia, el gameId ya está guardado,
 *      aunque la fila haya quedado sin `partido_id` porque el emparejado
 *      automático por fecha no cerró.
 *   1. El **fixture del club** (`/club/{id}/fixtures`). Una llamada trae los
 *      partidos del club, pero sólo de la temporada que TM considera en curso:
 *      para un partido de una temporada ya cerrada no alcanza. Es el camino
 *      barato para lo que se está cargando ahora.
 *   2. Los **partidos del DT** (`/coach/{id}/performance-game`). Va hacia atrás
 *      en el tiempo, así que rescata los viejos — pero necesita que el partido
 *      tenga DTs en `partido_tecnicos`, y a los DTs los carga justamente el
 *      detalle que se está tratando de bajar. Para un partido que nunca se
 *      importó, esta puerta suele estar cerrada.
 *   3. El **fixture de la competencia** del torneo del partido
 *      (`torneos.tm_competition_id` + `tm_season_id`). Es el único camino que
 *      puede nombrar una temporada pasada, así que es el que sirve para los
 *      partidos de campeonatos ya terminados.
 *
 * Nunca inventa: si en la ventana de fechas hay más de un candidato posible, no
 * elige ninguno y lo dice. Un gameId equivocado escribe la alineación de otro
 * partido encima del que estabas mirando.
 *
 * Y deja un RASTRO de lo que contestó cada fuente: cuántos partidos, de qué
 * temporadas y entre qué fechas. Sin eso "no lo encontré" es indistinguible de
 * "TM no contestó" y de "me contestó otra temporada", que se arreglan de
 * maneras completamente distintas.
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

    /** Las listas de partidos se reusan mientras se corrigen varias filas. */
    const TTL = 600;

    /** @var array */
    private $avisos = [];

    /**
     * Los partidos de TM que podrían ser éste pero no se pudo decidir.
     *
     * Cuando la búsqueda no puede elegir sola, esto es lo que se le muestra al
     * usuario para que elija él de un clic: mejor tres opciones concretas que
     * mandarlo a buscar la URL a Transfermarkt.
     *
     * @var array gameId => ['dia' => 'Y-m-d', 'home' => id, 'away' => id]
     */
    private $candidatos = [];

    /**
     * Qué contestó cada fuente consultada.
     *
     * @var array de ['fuente','partidos','temporadas','desde','hasta','nota']
     */
    private $rastro = [];

    /**
     * Con qué datos se arrancó a buscar, en una línea.
     *
     * Va aparte del rastro a propósito: es texto largo y en una celda de la
     * tabla queda cortado a la derecha, justo la parte que dice qué le falta
     * al torneo.
     *
     * @var string
     */
    private $partida = '';

    /**
     * Lo mismo que `$partida` pero en crudo, para poder armar links.
     *
     * La frase sirve para leer; los links necesitan los ids sueltos.
     *
     * @var array ['torneo' => array|null, 'clubes' => array, 'dia' => string]
     */
    private $contexto = [];

    /**
     * La temporada que TM está devolviendo, cuando NO es la del partido.
     *
     * Es el dato que decide si al torneo le sirve de algo cargarle los ids:
     * si TM está en otro año, no.
     *
     * @var string|null
     */
    private $ajena = null;

    /** @var int */
    private $llamadas = 0;

    /**
     * Busca el gameId de un partido.
     *
     * Devuelve ['game_id' => string|null, 'como' => string|null,
     *           'candidatos' => array, 'rastro' => array, 'avisos' => string[],
     *           'llamadas' => int].
     */
    public function buscar($partidoId): array
    {
        $this->avisos     = [];
        $this->candidatos = [];
        $this->rastro     = [];
        $this->partida    = '';
        $this->contexto   = [];
        $this->ajena      = null;
        $this->llamadas   = 0;

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
        $torneo  = $this->torneoDelPartido($partido->id);
        $comp    = $torneo ? trim((string) $torneo->tm_competition_id) : '';

        $this->rastroPuntoDePartida($partido, $tmLocal, $tmVisit, $coaches, $torneo);

        if (!$tmLocal && !$tmVisit && !$coaches && $comp === '') {
            return $this->resultado(null, null, 'No tengo por dónde empezar a buscar: ninguno de los dos equipos '
                . 'está atado a un club de Transfermarkt (tabla `equipo_tm`), el partido no tiene DTs con su URL '
                . 'de Transfermarkt cargada, y el torneo no tiene cargado su id de competencia de Transfermarkt.');
        }

        // ── 0) El staging, que no cuesta una llamada ────────────────────────
        $hallado = $this->desdeStaging($partido);

        if ($hallado) {
            return $this->resultado($hallado, 'el fixture que ya estaba guardado en el staging');
        }

        // ── 1) El fixture del club ──────────────────────────────────────────
        foreach ([$tmLocal, $tmVisit] as $club) {
            if (!$club) {
                continue;
            }

            $hallado = $this->probar(
                $this->juegosDeRuta('/club/' . rawurlencode($club) . '/fixtures'),
                $partido, $tmLocal, $tmVisit,
                'Fixture del club ' . $club
            );

            if ($hallado) {
                return $this->resultado($hallado, 'el fixture del club ' . $club);
            }
        }

        // ── 2) Los partidos de los DTs ──────────────────────────────────────
        foreach ($coaches as $coachId) {
            $hallado = $this->probar(
                $this->partidosDelDt($coachId),
                $partido, $tmLocal, $tmVisit,
                'Partidos del DT ' . $coachId
            );

            if ($hallado) {
                return $this->resultado($hallado, 'los partidos del DT ' . $coachId);
            }
        }

        // ── 3) El fixture de la competencia del torneo ──────────────────────
        $hallado = $this->porCompetencia($partido, $tmLocal, $tmVisit, $torneo);

        if ($hallado) {
            return $this->resultado($hallado, 'el fixture de la competencia ' . $comp);
        }

        return $this->resultado(null, null, 'Ninguna de las fuentes que consulté trae un partido de estos dos '
            . 'equipos alrededor del ' . substr((string) $partido->dia, 0, 10)
            . '. La tabla de abajo dice qué contestó cada una.');
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
     * Corre una lista de partidos de TM contra este partido y deja el rastro.
     *
     * `$juegos` en null significa que la fuente no contestó, que no es lo mismo
     * que haber contestado y no tenerlo: se anota distinto.
     */
    private function probar($juegos, $partido, $tmLocal, $tmVisit, $etiqueta)
    {
        if ($juegos === null) {
            $this->anotarRastro($etiqueta, null, [], null, null, 'Transfermarkt no me contestó.');
            return null;
        }

        if (empty($juegos)) {
            $this->anotarRastro($etiqueta, 0, [], null, null, 'Contestó, pero sin ningún partido.');
            return null;
        }

        $resumen = $this->resumir($juegos);
        $hallado = $this->elegir($juegos, $partido->dia, $tmLocal, $tmVisit);

        $nota = $hallado
            ? 'Acá estaba: gameId ' . $hallado . '.'
            : 'Ninguno de estos es el partido que busco.';

        if (!$hallado && $resumen['desde'] && $resumen['hasta']) {
            $dia = substr((string) $partido->dia, 0, 10);

            if ($dia < $resumen['desde'] || $dia > $resumen['hasta']) {
                $nota .= ' El ' . $dia . ' ni siquiera cae en el período que trajo: '
                    . 'esta fuente está mirando otra temporada.';
            }
        }

        $this->anotarRastro($etiqueta, $resumen['partidos'], $resumen['temporadas'],
            $resumen['desde'], $resumen['hasta'], $nota);

        return $hallado;
    }

    /** Cuántos partidos, de qué temporadas y entre qué fechas. */
    private function resumir(array $juegos): array
    {
        $temporadas = [];
        $desde      = null;
        $hasta      = null;

        foreach ($juegos as $j) {
            if (!empty($j['temporada'])) {
                $temporadas[(string) $j['temporada']] = true;
            }

            if (empty($j['dia'])) {
                continue;
            }

            if ($desde === null || $j['dia'] < $desde) $desde = $j['dia'];
            if ($hasta === null || $j['dia'] > $hasta) $hasta = $j['dia'];
        }

        ksort($temporadas);

        return ['partidos' => count($juegos), 'temporadas' => array_keys($temporadas),
            'desde' => $desde, 'hasta' => $hasta];
    }

    private function anotarRastro($fuente, $partidos, array $temporadas, $desde, $hasta, $nota)
    {
        $this->rastro[] = ['fuente' => $fuente, 'partidos' => $partidos, 'temporadas' => $temporadas,
            'desde' => $desde, 'hasta' => $hasta, 'nota' => $nota];
    }

    /** Con qué datos se arrancó a buscar. No es una fila del rastro: ver $partida. */
    private function rastroPuntoDePartida($partido, $tmLocal, $tmVisit, array $coaches, $torneo)
    {
        $partes = [];

        foreach ([[$partido->equipol_id, $tmLocal], [$partido->equipov_id, $tmVisit]] as $par) {
            $partes[] = $this->nombreEquipo($par[0]) . ': '
                . ($par[1] ? 'club de TM ' . $par[1] : 'SIN atar en equipo_tm');
        }

        $partes[] = $coaches
            ? 'DTs con URL de TM: ' . implode(', ', $coaches)
            : 'ningún DT del partido tiene cargada su URL de TM';

        if (!$torneo) {
            $partes[] = 'no pude ubicar el torneo del partido';
        } elseif (trim((string) $torneo->tm_competition_id) === '') {
            $partes[] = 'el torneo «' . $torneo->nombre . ' ' . $torneo->year
                . '» no tiene cargado su id de competencia de TM';
        } else {
            $partes[] = 'torneo «' . $torneo->nombre . ' ' . $torneo->year . '»: competencia '
                . $torneo->tm_competition_id
                . (trim((string) $torneo->tm_season_id) !== ''
                    ? ', temporada ' . $torneo->tm_season_id
                    : ', SIN seasonId cargado');
        }

        $this->partida = implode(' · ', $partes);

        $this->contexto = [
            'dia'    => substr((string) $partido->dia, 0, 10),
            'torneo' => $torneo ? [
                'id'     => (int) $torneo->torneo_id,
                'nombre' => (string) $torneo->nombre,
                'year'   => (string) $torneo->year,
                'comp'   => trim((string) $torneo->tm_competition_id),
                'season' => trim((string) $torneo->tm_season_id),
            ] : null,
            'clubes' => [
                ['nombre' => $this->nombreEquipo($partido->equipol_id), 'tm' => $tmLocal],
                ['nombre' => $this->nombreEquipo($partido->equipov_id), 'tm' => $tmVisit],
            ],
        ];
    }

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
        $datos      = [];

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

            $datos[$clave] = [
                'dia'  => $j['dia'],
                'home' => isset($j['home']) ? $j['home'] : null,
                'away' => isset($j['away']) ? $j['away'] : null,
            ];
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
            $this->anotarCandidatos($candidatos, $datos);
            $this->avisos[] = 'Hay ' . count($candidatos) . ' partidos posibles alrededor de esa fecha y no tengo '
                . 'los dos equipos atados a clubes de Transfermarkt, así que no elijo yo.';
            return null;
        }

        // Con los dos clubes identificados sí: es el mismo cruce repetido (ida
        // y vuelta muy pegadas, o una fecha corrida). Gana el más cercano.
        $mejor     = min($candidatos);
        $empatados = array_keys(array_filter($candidatos, function ($d) use ($mejor) { return $d === $mejor; }));

        if (count($empatados) > 1) {
            $this->anotarCandidatos(array_flip($empatados), $datos);
            $this->avisos[] = 'Encontré ' . count($empatados) . ' partidos de Transfermarkt el mismo día entre estos '
                . 'dos equipos. No elijo ninguno.';
            return null;
        }

        return (string) $empatados[0];
    }

    /** Guarda los que quedaron en duda para poder ofrecerlos a mano. */
    private function anotarCandidatos(array $candidatos, array $datos)
    {
        foreach (array_keys($candidatos) as $gameId) {
            $gameId = (string) $gameId;

            if (!isset($this->candidatos[$gameId]) && isset($datos[$gameId])) {
                $this->candidatos[$gameId] = $datos[$gameId];
            }
        }
    }

    /**
     * Los candidatos con el nombre de los dos clubes puestos.
     *
     * Una llamada para todos: sin los nombres la lista es una fila de números y
     * no hay forma de elegir.
     */
    private function candidatosConNombres(): array
    {
        if (empty($this->candidatos)) {
            return [];
        }

        $ids = [];

        foreach ($this->candidatos as $c) {
            foreach (['home', 'away'] as $k) {
                if (!empty($c[$k])) {
                    $ids[(string) $c[$k]] = true;
                }
            }
        }

        $nombres = $this->nombresDeClubes(array_keys($ids));
        $out     = [];

        foreach ($this->candidatos as $gameId => $c) {
            $out[] = [
                'game_id' => (string) $gameId,
                'dia'     => isset($c['dia']) ? $c['dia'] : null,
                'local'   => isset($nombres[(string) $c['home']]) ? $nombres[(string) $c['home']] : ('club ' . $c['home']),
                'visita'  => isset($nombres[(string) $c['away']]) ? $nombres[(string) $c['away']] : ('club ' . $c['away']),
            ];
        }

        usort($out, function ($a, $b) { return strcmp((string) $a['dia'], (string) $b['dia']); });

        return $out;
    }

    /** [clubId => nombre] de una sola llamada. */
    private function nombresDeClubes(array $ids): array
    {
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return [];
        }

        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, array_slice($ids, 0, 50)));

        $this->llamadas++;
        $json = HttpHelper::getJson(self::TMAPI . '/clubs?' . $qs);

        if (!is_array($json)) {
            return [];
        }

        $items = isset($json['data']) ? $json['data'] : $json;
        $map   = [];

        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }

            foreach (['name', 'fullName', 'officialName', 'shortName'] as $k) {
                if (!empty($item[$k]) && !is_array($item[$k])) {
                    $map[(string) $item['id']] = trim((string) $item[$k]);
                    break;
                }
            }
        }

        return $map;
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
     * El gameId que ya estaba guardado en el staging. GRATIS.
     *
     * `correrUno()` busca la fila del staging por `partido_id`, así que este
     * camino cubre el caso que a esa búsqueda se le escapa: la fila existe —el
     * fixture de la competencia se bajó alguna vez— pero quedó con
     * `partido_id` en null porque el emparejado automático no la reconoció.
     *
     * Se compara el par de equipos en los dos órdenes: en el flujo del fixture
     * `equipo_id` es el local, pero en el del DT es el club del DT.
     */
    private function desdeStaging($partido)
    {
        try {
            if (!Schema::hasTable('import_partidos')) {
                return null;
            }

            $dia   = substr((string) $partido->dia, 0, 10);
            $desde = date('Y-m-d', strtotime($dia . ' -' . self::DIAS . ' days'));
            $hasta = date('Y-m-d', strtotime($dia . ' +' . self::DIAS . ' days'));

            $filas = DB::table('import_partidos')
                ->whereNotNull('external_id')
                ->whereDate('dia', '>=', $desde)
                ->whereDate('dia', '<=', $hasta)
                ->where(function ($q) use ($partido) {
                    $q->where(function ($q2) use ($partido) {
                        $q2->where('equipo_id', $partido->equipol_id)
                           ->where('rival_id', $partido->equipov_id);
                    })->orWhere(function ($q2) use ($partido) {
                        $q2->where('equipo_id', $partido->equipov_id)
                           ->where('rival_id', $partido->equipol_id);
                    });
                })
                ->get();
        } catch (\Throwable $e) {
            $this->anotarRastro('Staging (import_partidos)', null, [], null, null,
                'No pude consultarlo: ' . $e->getMessage());
            return null;
        }

        $encontrados = [];
        $datos       = [];

        foreach ($filas as $f) {
            // Una fila ya atada a OTRO partido no es ésta: no se toca.
            if ($f->partido_id && (int) $f->partido_id !== (int) $partido->id) {
                continue;
            }

            $clave               = (string) $f->external_id;
            $encontrados[$clave] = true;
            $datos[$clave]       = [
                'dia'  => substr((string) $f->dia, 0, 10),
                'home' => $f->club_external_id,
                'away' => $f->rival_external_id,
            ];
        }

        if (count($encontrados) === 1) {
            $gameId = (string) key($encontrados);
            $this->anotarRastro('Staging (import_partidos)', count($filas), [], null, null,
                'Ya lo tenía guardado: gameId ' . $gameId . '. No gasté ninguna llamada.');
            return $gameId;
        }

        if (count($encontrados) > 1) {
            $this->anotarCandidatos($encontrados, $datos);
            $this->anotarRastro('Staging (import_partidos)', count($filas), [], null, null,
                'Hay ' . count($encontrados) . ' filas guardadas que podrían ser este partido. No elijo yo.');
            return null;
        }

        $this->anotarRastro('Staging (import_partidos)', count($filas), [], null, null,
            'No hay ninguna fila guardada de estos dos equipos en esa fecha.');

        return null;
    }

    /**
     * Los partidos de una ruta de TM que devuelve una lista, aplanados.
     *
     * Aguanta las tres formas que usan las rutas de partidos: `data.games[]`
     * (fixture de club), `data.fixtures[].games[]` (fixture de competencia) y
     * la lista pelada en la raíz. Devuelve null si TM no contestó, array si
     * contestó, aunque venga vacío.
     */
    private function juegosDeRuta($ruta)
    {
        $llave = 'tm.juegos.' . md5($ruta);

        if (Cache::has($llave)) {
            return Cache::get($llave);
        }

        $this->llamadas++;
        $resp = HttpHelper::getJson(self::TMAPI . $ruta);

        if (!is_array($resp)) {
            return null;
        }

        $data   = isset($resp['data']) ? $resp['data'] : $resp;
        $crudos = [];

        if (isset($data['games']) && is_array($data['games'])) {
            $crudos = $data['games'];
        } elseif (isset($data['fixtures']) && is_array($data['fixtures'])) {
            foreach ($data['fixtures'] as $bloque) {
                if (!is_array($bloque) || empty($bloque['games']) || !is_array($bloque['games'])) {
                    continue;
                }

                foreach ($bloque['games'] as $g) {
                    if (is_array($g)) $crudos[] = $g;
                }
            }
        } elseif (isset($data[0]) && is_array($data[0])) {
            $crudos = $data;
        }

        $out = $this->aplanar($crudos);

        Cache::put($llave, $out, self::TTL);

        return $out;
    }

    /**
     * Un partido del fixture, reducido a lo que hace falta para reconocerlo.
     *
     * Es la misma forma que lee `ImportPartidosController::normalizarFixture()`:
     * `gameId`, `homeClub`/`awayClub` explícitos y `baseDetails`. **No** es la
     * forma de `/coach/{id}/performance-game`, que se aplana aparte.
     */
    private function aplanar(array $crudos): array
    {
        $out = [];

        foreach ($crudos as $g) {
            if (!is_array($g)) {
                continue;
            }

            $bd = isset($g['baseDetails']) && is_array($g['baseDetails']) ? $g['baseDetails'] : [];

            $out[] = [
                'game_id'   => isset($g['gameId']) ? (string) $g['gameId'] : null,
                'home'      => isset($g['homeClub']['clubId']) ? (string) $g['homeClub']['clubId'] : null,
                'away'      => isset($g['awayClub']['clubId']) ? (string) $g['awayClub']['clubId'] : null,
                'dia'       => $this->fecha(isset($bd['date']) ? $bd['date'] : null),
                'temporada' => isset($bd['seasonId']) ? (string) $bd['seasonId'] : null,
            ];
        }

        return $out;
    }

    /**
     * El fixture de la competencia del torneo del partido.
     *
     * **tmapi NO sabe traer temporadas cerradas.** Verificado en producción
     * (sep-2026): a `/competition/ARGC/fixtures?seasonId=2024` le pedimos el
     * Clausura 2025 y devolvió 240 partidos del 2026-07-23 al 2026-11-08, que
     * es el Clausura 2026. El `seasonId` se ignora. Y
     * `/competition/{c}/games?seasonId=..&gameDay=..` directamente no contesta.
     * Por eso acá quedó **una sola ruta y sin parámetros**: pedir la misma
     * competencia de otra forma devuelve lo mismo y cuesta otro crédito.
     *
     * Entonces este camino sirve para un partido de la temporada EN CURSO al
     * que los otros no llegaron —típicamente porque los equipos no están
     * atados en `equipo_tm`—, no para rescatar campeonatos viejos. Para esos,
     * lo único que va hacia atrás es `/coach/{id}/performance-game` o pegar la
     * URL a mano.
     */
    private function porCompetencia($partido, $tmLocal, $tmVisit, $torneo)
    {
        // Un camino que no corre tiene que decirlo. Callado es indistinguible
        // de uno que corrió y no encontró nada, y se arreglan distinto.
        if (!$torneo) {
            $this->anotarRastro('Competencia del torneo', null, [], null, null,
                'No pude ubicar el torneo de este partido (partidos → fechas → grupos → torneos), '
                . 'así que no sé qué competencia pedirle a Transfermarkt.');
            return null;
        }

        $comp = trim((string) $torneo->tm_competition_id);

        if ($comp === '') {
            $this->anotarRastro('Competencia del torneo', null, [], null, null,
                'No la consulté: el torneo «' . $torneo->nombre . ' ' . $torneo->year . '» no tiene cargado '
                . 'su id de competencia de Transfermarkt. Sirve para los partidos de la temporada en curso; '
                . 'se carga en Editar torneo → transfermarkt.com.');
            return null;
        }

        $dia = substr((string) $partido->dia, 0, 10);

        // Si una fuente anterior ya mostró que TM está parado en otra
        // temporada, la competencia va a contestar esa misma. No se paga un
        // crédito para que nos diga lo que ya sabemos.
        $this->ajena = $this->temporadaAjena($dia);

        if ($this->ajena !== null) {
            $this->anotarRastro('Competencia ' . $comp, null, [], null, null,
                'No la consulté: el fixture del club ya mostró que Transfermarkt está en otra temporada ('
                . $this->ajena . '), y la competencia devuelve la misma — sus rutas ignoran el `seasonId`. '
                . 'Este partido no se puede encontrar por la API: hay que pegar la URL a mano.');
            return null;
        }

        $ruta   = '/competition/' . rawurlencode($comp) . '/fixtures';
        $juegos = $this->juegosDeRuta($ruta);

        $hallado = $this->probar($juegos, $partido, $tmLocal, $tmVisit, 'Competencia · ' . $ruta);

        if ($hallado) {
            return $hallado;
        }

        // Red de seguridad por si TM algún día empieza a respetar el seasonId:
        // se compara lo que vino contra lo que el torneo dice tener.
        $season = trim((string) $torneo->tm_season_id);

        if (!empty($juegos) && $season !== '' && !$this->tieneTemporada($juegos, $season)) {
            $this->ajena = 'temporada TM ' . implode(', ', $this->resumir($juegos)['temporadas']);
            $this->avisos[] = 'La competencia ' . $comp . ' me contestó la ' . $this->ajena . ' y no la '
                . $season . ' que tiene cargada el torneo: tmapi devuelve siempre la temporada en curso. '
                . 'Un partido de un campeonato ya terminado no se encuentra por la API — la URL hay que '
                . 'pegarla a mano.';
        }

        return null;
    }

    /**
     * ¿Alguna fuente ya mostró que TM está mirando otra temporada?
     *
     * Devuelve la descripción de esa temporada, o null si ninguna lo mostró.
     * Se apoya en el rastro: si una fuente trajo partidos y el día que
     * buscamos no cae en su período, esa fuente está en otro campeonato.
     */
    private function temporadaAjena($dia)
    {
        foreach ($this->rastro as $r) {
            if (empty($r['desde']) || empty($r['hasta'])) {
                continue;
            }

            if ($dia >= $r['desde'] && $dia <= $r['hasta']) {
                continue;
            }

            return (!empty($r['temporadas']) ? 'temporada TM ' . implode(', ', $r['temporadas']) . ': ' : '')
                . $r['desde'] . ' → ' . $r['hasta'];
        }

        return null;
    }

    private function tieneTemporada(array $juegos, $season)
    {
        foreach ($juegos as $j) {
            if (!empty($j['temporada']) && (string) $j['temporada'] === (string) $season) {
                return true;
            }
        }

        return false;
    }

    /**
     * Los partidos dirigidos por un DT.
     *
     * `/coach/{id}/performance-game` tiene forma propia: no hay `homeClub` ni
     * `awayClub` sino `club` (el del DT) y `opponent`. Como el apareo mira el
     * par completo sin orden, da igual cuál es cuál.
     */
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
            return null;
        }

        $out = [];

        foreach ($perf as $g) {
            if (!is_array($g)) {
                continue;
            }

            $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
            $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

            $temporada = null;

            foreach ([isset($gi['seasonId']) ? $gi['seasonId'] : null,
                      isset($g['seasonId']) ? $g['seasonId'] : null,
                      isset($gi['season']['id']) ? $gi['season']['id'] : null] as $cand) {
                if (!empty($cand) && !is_array($cand)) {
                    $temporada = (string) $cand;
                    break;
                }
            }

            $out[] = [
                'game_id'   => isset($gi['gameId']) ? (string) $gi['gameId'] : null,
                'home'      => isset($ci['club']['clubId']) ? (string) $ci['club']['clubId'] : null,
                'away'      => isset($ci['opponent']['clubId']) ? (string) $ci['opponent']['clubId'] : null,
                'dia'       => $this->fecha(isset($gi['date']) ? $gi['date'] : null),
                'temporada' => $temporada,
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

    /**
     * El torneo del partido, con su competencia de TM y el número de fecha.
     *
     * partidos → fechas → grupos → torneos. El `numero` de la fecha se usa como
     * `gameDay` tentativo: si no coincide con el de TM no rompe nada, porque
     * después se sigue probando con la temporada entera.
     */
    private function torneoDelPartido($partidoId)
    {
        try {
            if (!Schema::hasTable('torneos') || !Schema::hasColumn('torneos', 'tm_competition_id')) {
                return null;
            }

            return DB::table('partidos')
                ->join('fechas', 'partidos.fecha_id', '=', 'fechas.id')
                ->join('grupos', 'fechas.grupo_id', '=', 'grupos.id')
                ->join('torneos', 'grupos.torneo_id', '=', 'torneos.id')
                ->where('partidos.id', (int) $partidoId)
                ->first(['torneos.id as torneo_id', 'torneos.nombre', 'torneos.year',
                    'torneos.tm_competition_id', 'torneos.tm_season_id', 'fechas.numero as ronda']);
        } catch (\Throwable $e) {
            return null;
        }
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
            'game_id'    => $gameId,
            'como'       => $como,
            // Los nombres sólo se piden si de verdad hay que mostrar la lista.
            'candidatos' => $gameId ? [] : $this->candidatosConNombres(),
            'partida'    => $this->partida,
            'contexto'   => $this->contexto + ['temporada_ajena' => $this->ajena],
            'rastro'     => $this->rastro,
            'avisos'     => $this->avisos,
            'llamadas'   => $this->llamadas,
        ];
    }
}
