<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\HttpHelper;

/**
 * Sondeo de partidos de un DT (paso 1 del motor de carga de partidos).
 *
 * NO modifica nada de lo que ya funciona. Pega al mismo endpoint de tmapi que
 * usa ScraperController@tecnicoTransfermarkt, pero en vez de agregar los
 * partidos en totales, muestra la fila cruda de cada uno.
 *
 * Los clubes se resuelven por clubId de Transfermarkt (tabla equipo_tm), no por
 * nombre: el nombre nunca alcanza. El mapeo se aprende solo desde los partidos
 * que ya tenés cargados.
 *
 * Uso:
 *   /admin/import-partidos/sondear?tecnico_id=123
 *   /admin/import-partidos/sondear?url=https://www.transfermarkt.com/x/profil/trainer/5163
 *   ...&aprender=1    -> deduce y guarda el mapeo de clubes (equipo_tm)
 *   ...&guardar=1     -> escribe las filas en import_partidos
 *   ...&estado=conflicto  -> filtra la tabla de abajo
 *   ...&desde=2000    -> corte de temporada (default 2000)
 *   ...&limite=60     -> filas a mostrar (default 60)
 */
class ImportPartidosController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    public function sondear(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = $request->get('tecnico_id');
        $url       = trim((string) $request->get('url', ''));
        $guardar   = (string) $request->get('guardar', '0') === '1';
        $aprender  = (string) $request->get('aprender', '0') === '1';
        $desde     = (int) $request->get('desde', 2000);
        $limite    = (int) $request->get('limite', 60);
        $filtro    = trim((string) $request->get('estado', ''));

        $avisos = [];

        // ── Mapeo manual de un club (viene del formulario de abajo) ─────────
        if ($request->filled('mapear_tm') && $request->filled('mapear_equipo')) {
            $tmId = trim((string) $request->get('mapear_tm'));
            $eqId = (int) preg_replace('/\D.*$/', '', trim((string) $request->get('mapear_equipo')));
            if ($tmId !== '' && $eqId > 0 && \App\Equipo::where('id', $eqId)->exists()) {
                $this->guardarMapeo($tmId, $eqId, $request->get('mapear_nombre'), 'manual');
                $avisos[] = 'Club de TM ' . e($tmId) . ' mapeado al equipo #' . $eqId . '.';
            } else {
                $avisos[] = '<span class="err">No pude mapear: revisá el id de equipo.</span>';
            }
        }

        $nombreDT = null;
        if ($tecnicoId) {
            $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
            if (!$tecnico) return $this->pagina('Sondeo', '<p class="err">No existe el técnico #' . (int) $tecnicoId . '</p>');
            $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);
            if ($url === '') $url = (string) $tecnico->transfermarkt_url;
        }
        if ($url === '') {
            return $this->pagina('Sondeo', '<p class="err">Falta <code>?tecnico_id=</code> o <code>?url=</code> con el perfil de Transfermarkt del DT.</p>');
        }
        if (!preg_match('#/trainer/(\d+)#', $url, $m)) {
            return $this->pagina('Sondeo', '<p class="err">La URL no tiene el formato <code>.../trainer/{id}</code>:<br>' . e($url) . '</p>');
        }
        $coachId = $m[1];

        // ── 1. Rendimiento partido por partido ──────────────────────────────
        $resp = HttpHelper::getJson(self::TMAPI . "/coach/{$coachId}/performance-game");
        $games = [];
        if (is_array($resp)) {
            if (isset($resp['data']['performance']) && is_array($resp['data']['performance'])) {
                $games = $resp['data']['performance'];
            } elseif (isset($resp['performance']) && is_array($resp['performance'])) {
                $games = $resp['performance'];
            }
        }
        if (empty($games)) {
            $err = HttpHelper::getLastJsonError();
            return $this->pagina('Sondeo', '<p class="err">tmapi no devolvió partidos para el coach ' . e($coachId) . '.<br>Causa: '
                . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle') . '</p>');
        }

        // ── 2. Normalizar ───────────────────────────────────────────────────
        $filas = [];
        $temporadas = [];
        foreach ($games as $g) {
            $f = $this->normalizar($g, $coachId);
            if ($f['temporada'] !== null) $temporadas[] = (int) $f['temporada'];
            $filas[] = $f;
        }

        // Nombres de competencias y clubes
        $compIds = []; $clubIds = [];
        foreach ($filas as $f) {
            if ($f['competencia_external_id']) $compIds[$f['competencia_external_id']] = true;
            if ($f['club_external_id'])        $clubIds[$f['club_external_id']] = true;
            if ($f['rival_external_id'])       $clubIds[$f['rival_external_id']] = true;
        }
        $compNames = $this->resolverNombres(self::TMAPI . '/competitions', array_keys($compIds));
        $clubNames = $this->resolverNombres(self::TMAPI . '/clubs', array_keys($clubIds));

        foreach ($filas as $i => $f) {
            if ($f['competencia_external_id'] && isset($compNames[$f['competencia_external_id']])) {
                $filas[$i]['competencia_nombre'] = $compNames[$f['competencia_external_id']];
            }
            if ($f['club_external_id'] && isset($clubNames[$f['club_external_id']])) {
                $filas[$i]['club_nombre'] = $clubNames[$f['club_external_id']];
            }
            if ($f['rival_external_id'] && isset($clubNames[$f['rival_external_id']])) {
                $filas[$i]['rival_nombre'] = $clubNames[$f['rival_external_id']];
            }
        }

        // ── 3. Clasificar (resolver clubes + dedupe en seco) ────────────────
        $filas = $this->clasificar($filas, $desde);

        // ── 4. Aprender el mapeo de clubes desde lo ya cargado ──────────────
        $aprendidos = [];
        if ($aprender) {
            $aprendidos = $this->aprenderMapeos($filas);
            if (!empty($aprendidos)) {
                $filas = $this->clasificar($filas, $desde);   // segunda pasada con el mapeo nuevo
            }
        }

        // ── 5. Contar ───────────────────────────────────────────────────────
        $cont = ['total' => count($filas), 'excluido' => 0, 'duplicado' => 0,
                 'falta_dt' => 0, 'nuevo' => 0, 'conflicto' => 0];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['estado'] === 'duplicado' && $f['motivo'] === 'ya cargado, le falta el DT') $cont['falta_dt']++;
        }

        // ── 6. Guardar en staging ───────────────────────────────────────────
        $guardadas = 0;
        if ($guardar) {
            foreach ($filas as $f) {
                if ($f['estado'] === 'excluido') continue;
                $guardadas += $this->persistir($f, $coachId, $tecnicoId) ? 1 : 0;
            }
        }

        // ── 7. Salida ───────────────────────────────────────────────────────
        sort($temporadas);
        $rango = empty($temporadas) ? '?' : (reset($temporadas) . ' – ' . end($temporadas));

        $html  = '<h1>Sondeo de partidos · ' . e($nombreDT ?: ('coach ' . $coachId)) . '</h1>';
        $html .= '<p class="sub">coach ' . e($coachId) . ' · ' . $cont['total'] . ' partidos · temporadas ' . e($rango)
              . ' · corte en ' . $desde . '</p>';

        foreach ($avisos as $a) $html .= '<p class="ok-box">' . $a . '</p>';

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos')
            . $this->card($cont['excluido'], 'fuera de alcance', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['falta_dt'], 'sin el DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . '</div>';

        // Acciones
        $base = $this->urlBase($request);
        $html .= '<p class="acciones">'
            . '<a href="' . e($base . '&aprender=1') . '">Aprender mapeo de clubes</a> · '
            . '<a href="' . e($base . '&guardar=1') . '">Guardar en import_partidos</a> · '
            . '<a href="' . e($base . '&estado=conflicto&limite=300') . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&estado=nuevo&limite=300') . '">Ver solo nuevos</a>'
            . '</p>';

        if ($aprender) {
            $html .= '<p class="ok-box">Mapeos aprendidos en esta corrida: <b>' . count($aprendidos) . '</b>'
                . (empty($aprendidos) ? '' : '<br><span class="sub">' . e(implode(' · ', array_slice($aprendidos, 0, 30))) . '</span>')
                . '</p>';
        }
        if ($guardar) {
            $html .= '<p class="ok-box">Guardadas ' . $guardadas . ' filas en <code>import_partidos</code>.</p>';
        }

        // Clubes sin resolver
        $html .= $this->bloqueClubesSinResolver($filas, $request);

        // Tabla
        $titulo = $filtro !== '' ? ('Partidos con estado «' . e($filtro) . '»') : ('Primeros ' . $limite . ' partidos');
        $html .= '<h2>' . $titulo . '</h2>' . $this->tabla($filas, $limite, $filtro);

        // Diagnóstico del JSON (al final, ya sabemos la forma)
        $html .= '<h2>Estructura del JSON</h2>' . $this->diagnosticar($games[0]);

        return $this->pagina('Sondeo de partidos', $html);
    }

    // ────────────────────── clasificación ──────────────────────

    private function clasificar(array $filas, $desde)
    {
        $mapaTm = $this->mapaTm();
        $mapaNombres = $this->mapaNombres();

        foreach ($filas as $i => $f) {
            $filas[$i]['equipo_id'] = null;
            $filas[$i]['rival_id'] = null;
            $filas[$i]['partido_id'] = null;
            $filas[$i]['motivo'] = null;

            if ($f['temporada'] !== null && (int) $f['temporada'] < $desde) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'temporada < ' . $desde;
                continue;
            }
            if ($f['goles_favor'] === null || $f['goles_contra'] === null) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'sin resultado';
                continue;
            }

            $equipoId = $this->resolverClub($f['club_external_id'], $f['club_nombre'], $mapaTm, $mapaNombres);
            $rivalId  = $this->resolverClub($f['rival_external_id'], $f['rival_nombre'], $mapaTm, $mapaNombres);
            $filas[$i]['equipo_id'] = $equipoId;
            $filas[$i]['rival_id']  = $rivalId;

            if (!$f['dia']) {
                $filas[$i]['estado'] = 'conflicto';
                $filas[$i]['motivo'] = 'sin fecha';
                continue;
            }

            $partido = ($equipoId && $rivalId) ? $this->buscarPartido($equipoId, $rivalId, $f['dia']) : null;

            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $tieneDt = DB::table('partido_tecnicos')
                    ->where('partido_id', $partido->id)->where('equipo_id', $equipoId)->exists();
                $filas[$i]['motivo'] = $tieneDt ? 'ya cargado' : 'ya cargado, le falta el DT';
            } elseif (!$equipoId || !$rivalId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$equipoId) $faltan[] = 'club «' . $f['club_nombre'] . '»';
                if (!$rivalId)  $faltan[] = 'rival «' . $f['rival_nombre'] . '»';
                $filas[$i]['motivo'] = 'sin mapear: ' . implode(' / ', $faltan);
            } else {
                $filas[$i]['estado'] = 'nuevo';
            }
        }
        return $filas;
    }

    private function buscarPartido($equipoId, $rivalId, $dia)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -1 day'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +1 day'));
        return \App\Partido::whereBetween('dia', [$d0, $d1])
            ->where(function ($q) use ($equipoId, $rivalId) {
                $q->where(function ($w) use ($equipoId, $rivalId) {
                    $w->where('equipol_id', $equipoId)->where('equipov_id', $rivalId);
                })->orWhere(function ($w) use ($equipoId, $rivalId) {
                    $w->where('equipol_id', $rivalId)->where('equipov_id', $equipoId);
                });
            })->first();
    }

    /**
     * Deduce el mapeo club TM → equipo nuestro usando los partidos ya cargados.
     *
     * Si de un partido conocemos un solo lado, buscamos partidos de ese equipo
     * en esa fecha; si hay exactamente uno y además coincide el resultado y la
     * localía, el rival de ese partido ES el club que no sabíamos.
     */
    private function aprenderMapeos(array $filas)
    {
        $mapaTm = $this->mapaTm();
        $aprendidos = [];

        foreach ($filas as $f) {
            if ($f['estado'] === 'excluido' || !$f['dia']) continue;

            // a) De los partidos ya identificados, guardamos ambos lados.
            if ($f['estado'] === 'duplicado' && $f['equipo_id'] && $f['rival_id']) {
                foreach ([[$f['club_external_id'], $f['equipo_id'], $f['club_nombre']],
                          [$f['rival_external_id'], $f['rival_id'], $f['rival_nombre']]] as $par) {
                    if ($par[0] && !isset($mapaTm[(string) $par[0]])) {
                        $this->guardarMapeo($par[0], $par[1], $par[2], 'nombre');
                        $mapaTm[(string) $par[0]] = $par[1];
                        $aprendidos[] = $par[2] . ' → #' . $par[1];
                    }
                }
                continue;
            }

            if ($f['estado'] !== 'conflicto') continue;

            $conocidoEsClub = (bool) $f['equipo_id'];
            $conocido = $f['equipo_id'] ?: $f['rival_id'];
            $tmDesconocido = $conocidoEsClub ? $f['rival_external_id'] : $f['club_external_id'];
            $nombreDesconocido = $conocidoEsClub ? $f['rival_nombre'] : $f['club_nombre'];
            if (!$conocido || !$tmDesconocido) continue;
            if (isset($mapaTm[(string) $tmDesconocido])) continue;
            if ($f['equipo_id'] && $f['rival_id']) continue;   // los dos conocidos: no hay nada que aprender

            // Localía y resultado esperados, desde el punto de vista del club dirigido.
            $clubEsLocal = (bool) $f['local'];
            $gf = (int) $f['goles_favor'];
            $gc = (int) $f['goles_contra'];

            $d0 = date('Y-m-d 00:00:00', strtotime($f['dia'] . ' -1 day'));
            $d1 = date('Y-m-d 23:59:59', strtotime($f['dia'] . ' +1 day'));
            $cands = \App\Partido::whereBetween('dia', [$d0, $d1])
                ->where(function ($q) use ($conocido) {
                    $q->where('equipol_id', $conocido)->orWhere('equipov_id', $conocido);
                })->get();

            $ok = [];
            foreach ($cands as $p) {
                if ($p->golesl === null || $p->golesv === null) continue;

                if ($conocidoEsClub) {
                    // el conocido es el club dirigido
                    if ($clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) {
                        $ok[] = $p->equipov_id;
                    } elseif (!$clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) {
                        $ok[] = $p->equipol_id;
                    }
                } else {
                    // el conocido es el rival
                    if ($clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) {
                        $ok[] = $p->equipol_id;
                    } elseif (!$clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) {
                        $ok[] = $p->equipov_id;
                    }
                }
            }

            $ok = array_values(array_unique(array_filter($ok)));
            if (count($ok) === 1) {
                $this->guardarMapeo($tmDesconocido, $ok[0], $nombreDesconocido, 'inferido');
                $mapaTm[(string) $tmDesconocido] = $ok[0];
                $aprendidos[] = $nombreDesconocido . ' → #' . $ok[0];
            }
        }

        return $aprendidos;
    }

    private function guardarMapeo($tmClubId, $equipoId, $nombre, $origen)
    {
        DB::table('equipo_tm')->updateOrInsert(
            ['tm_club_id' => (string) $tmClubId],
            ['equipo_id' => (int) $equipoId, 'nombre_tm' => $nombre, 'origen' => $origen,
             'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function mapaTm()
    {
        $mapa = [];
        foreach (DB::table('equipo_tm')->select('tm_club_id', 'equipo_id')->get() as $r) {
            $mapa[(string) $r->tm_club_id] = (int) $r->equipo_id;
        }
        return $mapa;
    }

    private function mapaNombres()
    {
        $mapa = [];
        foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
            foreach ($this->clavesNombre($e->nombre) as $k) {
                if ($k !== '' && !isset($mapa[$k])) $mapa[$k] = $e->id;
            }
        }
        return $mapa;
    }

    private function resolverClub($tmId, $nombre, array $mapaTm, array $mapaNombres)
    {
        if ($tmId !== null && isset($mapaTm[(string) $tmId])) return $mapaTm[(string) $tmId];
        foreach ($this->clavesNombre($nombre) as $k) {
            if ($k !== '' && isset($mapaNombres[$k])) return $mapaNombres[$k];
        }
        return null;
    }

    /** Varias formas normalizadas del mismo nombre, de la más estricta a la más laxa. */
    private function clavesNombre($nombre)
    {
        $nombre = (string) $nombre;
        if (trim($nombre) === '') return [];

        $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($base === false) $base = $nombre;
        $base = mb_strtolower($base);

        $sinParentesis = preg_replace('/\([^)]*\)/', ' ', $base);

        $claves = [];
        foreach ([$base, $sinParentesis] as $variante) {
            $claves[] = $this->soloLetras($variante);
            $claves[] = $this->soloLetras($this->quitarPrefijos($variante));
        }
        return array_values(array_unique(array_filter($claves)));
    }

    private function quitarPrefijos($str)
    {
        // Prefijos y palabras genéricas: CA, AA, CS, CD, CSD, CAI, AC, SC, FC, CF,
        // club, atletico, deportivo, asociacion, sportivo, social, y "de".
        $str = preg_replace('/\b(c\.?a\.?|a\.?a\.?|c\.?s\.?|c\.?d\.?|c\.?s\.?d\.?|a\.?c\.?|s\.?c\.?|f\.?c\.?|c\.?f\.?|s\.?a\.?d\.?)\b/u', ' ', $str);
        $str = preg_replace('/\b(club|atletico|atletica|deportivo|deportiva|asociacion|association|sportivo|sporting|social|futbol|football|de|del|la|el)\b/u', ' ', $str);
        return $str;
    }

    private function soloLetras($str)
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $str);
    }

    // ────────────────────── normalización de la fuente ──────────────────────

    private function normalizar(array $g, $coachId)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

        // club = local, opponent = visitante (mismo criterio que ScraperController)
        $club  = isset($ci['club']) ? $ci['club'] : [];
        $rival = isset($ci['opponent']) ? $ci['opponent'] : [];
        $local = true;
        if ((string) $this->valor($rival, ['coachId']) === (string) $coachId
            && (string) $this->valor($club, ['coachId']) !== (string) $coachId) {
            $club  = isset($ci['opponent']) ? $ci['opponent'] : [];
            $rival = isset($ci['club']) ? $ci['club'] : [];
            $local = false;
        }

        $fechaRaw = $this->valor($gi, ['date']);
        if (is_array($fechaRaw)) $fechaRaw = $this->valor($fechaRaw, ['dateTimeUTC', 'dateTime', 'date']);
        $dia = null;
        if ($fechaRaw) {
            $ts = strtotime((string) $fechaRaw);
            if ($ts) $dia = date('Y-m-d H:i:s', $ts);
        }

        $temporada = $this->valor($gi, ['seasonId']);
        if ($temporada === null && isset($gi['season']) && is_array($gi['season'])) {
            $temporada = $this->valor($gi['season'], ['id', 'seasonId']);
        }

        $gf = $this->valor($club, ['goalsTotal']);
        $gc = $this->valor($club, ['opponentGoalsTotal']);
        if ($gc === null) $gc = $this->valor($rival, ['goalsTotal']);

        return [
            'external_id'             => $this->texto($this->valor($gi, ['gameId']) ?: $this->valor($g, ['gameId', 'id'])),
            'competencia_external_id' => $this->texto($this->valor($gi, ['competitionId'])),
            'competencia_tipo_id'     => $this->texto($this->valor($gi, ['competitionTypeId'])),
            'competencia_nombre'      => $this->valor($gi, ['competitionName']),
            'temporada'               => $this->texto($temporada),
            'ronda'                   => $this->texto($this->valor($gi, ['gameDay', 'matchDay', 'round'])),
            'grupo_externo'           => $this->texto($this->valor($gi, ['competitionGroupId'])),
            'arbitro_external_id'     => $this->texto($this->valor($gi, ['refereeId'])),
            'estadio_external_id'     => $this->texto($this->valor($gi, ['stadiumId'])),
            'club_external_id'        => $this->texto($this->valor($club, ['clubId', 'id'])),
            'club_nombre'             => $this->valor($club, ['name', 'clubName']),
            'rival_external_id'       => $this->texto($this->valor($rival, ['clubId', 'id'])),
            'rival_nombre'            => $this->valor($rival, ['name', 'clubName']),
            'local'                   => $local,
            'dia'                     => $dia,
            'goles_favor'             => $gf === null ? null : (int) $gf,
            'goles_contra'            => $gc === null ? null : (int) $gc,
            'equipo_id'               => null,
            'rival_id'                => null,
            'partido_id'              => null,
            'estado'                  => 'nuevo',
            'motivo'                  => null,
            'payload'                 => json_encode($g, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function persistir(array $f, $coachId, $tecnicoId)
    {
        $clave = ['fuente' => 'transfermarkt', 'external_id' => $f['external_id'], 'tecnico_id' => $tecnicoId ?: null];
        if (!$f['external_id']) {
            $clave = ['fuente' => 'transfermarkt', 'tecnico_id' => $tecnicoId ?: null,
                      'club_nombre' => $f['club_nombre'], 'rival_nombre' => $f['rival_nombre'], 'dia' => $f['dia']];
        }
        DB::table('import_partidos')->updateOrInsert($clave, [
            'coach_external_id'       => $coachId,
            'competencia_external_id' => $f['competencia_external_id'],
            'competencia_nombre'      => $f['competencia_nombre'],
            'temporada'               => $f['temporada'],
            'ronda'                   => $f['ronda'],
            'club_external_id'        => $f['club_external_id'],
            'club_nombre'             => $f['club_nombre'],
            'rival_external_id'       => $f['rival_external_id'],
            'rival_nombre'            => $f['rival_nombre'],
            'local'                   => $f['local'],
            'dia'                     => $f['dia'],
            'goles_favor'             => $f['goles_favor'],
            'goles_contra'            => $f['goles_contra'],
            'equipo_id'               => $f['equipo_id'],
            'rival_id'                => $f['rival_id'],
            'partido_id'              => $f['partido_id'],
            'estado'                  => $f['estado'],
            'motivo'                  => $f['motivo'],
            'payload'                 => $f['payload'],
            'updated_at'              => now(),
            'created_at'              => now(),
        ]);
        return true;
    }

    private function valor($arr, array $claves)
    {
        if (!is_array($arr)) return null;
        foreach ($claves as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') return $arr[$k];
        }
        return null;
    }

    private function texto($v)
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function resolverNombres($endpoint, array $ids)
    {
        $map = [];
        if (empty($ids)) return $map;
        foreach (array_chunk($ids, 50) as $chunk) {
            $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $chunk));
            $json = HttpHelper::getJson($endpoint . '?' . $qs);
            if (!$json) continue;
            $items = isset($json['data']) ? $json['data'] : $json;
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $id = isset($item['id']) ? $item['id'] : null;
                if ($id === null) continue;
                $name = $this->valor($item, ['name', 'fullName', 'officialName', 'shortName', 'display']);
                if ($name) $map[(string) $id] = trim($name);
            }
        }
        return $map;
    }

    // ────────────────────── vistas ──────────────────────

    private function urlBase(Request $request)
    {
        $q = $request->query();
        unset($q['guardar'], $q['aprender'], $q['estado'], $q['limite'],
              $q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre']);
        return $request->url() . '?' . http_build_query($q);
    }

    private function bloqueClubesSinResolver(array $filas, Request $request)
    {
        $pend = [];
        foreach ($filas as $f) {
            if ($f['estado'] !== 'conflicto') continue;
            foreach ([[$f['club_external_id'], $f['club_nombre'], $f['equipo_id']],
                      [$f['rival_external_id'], $f['rival_nombre'], $f['rival_id']]] as $c) {
                if ($c[2] || !$c[0]) continue;
                $k = (string) $c[0];
                if (!isset($pend[$k])) $pend[$k] = ['nombre' => $c[1], 'n' => 0];
                $pend[$k]['n']++;
            }
        }
        if (empty($pend)) return '<p class="ok-box">No queda ningún club sin mapear.</p>';

        uasort($pend, function ($a, $b) { return $b['n'] <=> $a['n']; });

        $opciones = '';
        foreach (\App\Equipo::select('id', 'nombre')->orderBy('nombre')->get() as $e) {
            $opciones .= '<option value="' . $e->id . ' · ' . e($e->nombre) . '"></option>';
        }

        $base = $this->urlBase($request);
        $out = '<h2>Clubes sin mapear <span class="sub">(' . count($pend) . ')</span></h2>'
             . '<p class="sub">Escribí el equipo y guardá: el club queda mapeado por su id de Transfermarkt y no se vuelve a preguntar nunca más.</p>'
             . '<datalist id="eqs">' . $opciones . '</datalist>'
             . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Partidos</th><th>Nuestro equipo</th></tr></thead><tbody>';

        foreach ($pend as $tmId => $d) {
            $out .= '<tr><td>' . e($d['nombre']) . '</td><td class="num">' . e($tmId) . '</td><td class="num">' . $d['n'] . '</td>'
                 . '<td><form method="get" action="' . e($request->url()) . '">';
            foreach ($request->query() as $k => $v) {
                if (in_array($k, ['mapear_tm', 'mapear_equipo', 'mapear_nombre', 'guardar', 'aprender'], true)) continue;
                if (is_array($v)) continue;
                $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
            }
            $out .= '<input type="hidden" name="mapear_tm" value="' . e($tmId) . '">'
                 . '<input type="hidden" name="mapear_nombre" value="' . e($d['nombre']) . '">'
                 . '<input name="mapear_equipo" list="eqs" placeholder="buscar equipo…" size="28">'
                 . ' <button>Mapear</button></form></td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function diagnosticar(array $game)
    {
        $lineas = [];
        $lineas[] = '<strong>Claves del partido:</strong> <code>' . e(implode(', ', array_keys($game))) . '</code>';
        foreach (['gameInformation', 'clubsInformation', 'statistics'] as $sub) {
            if (isset($game[$sub]) && is_array($game[$sub])) {
                $lineas[] = '<strong>' . $sub . ':</strong> <code>' . e(implode(', ', array_keys($game[$sub]))) . '</code>';
                foreach ($game[$sub] as $k => $v) {
                    if (is_array($v)) {
                        $lineas[] = '&nbsp;&nbsp;↳ <em>' . e($k) . '</em>: <code>' . e(implode(', ', array_keys($v))) . '</code>';
                    }
                }
            }
        }
        $lineas[] = '<details><summary>JSON crudo del primer partido</summary><pre>'
            . e(json_encode($game, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';
        return '<div class="diag">' . implode('<br>', $lineas) . '</div>';
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

    private function tabla(array $filas, $limite, $filtro = '')
    {
        $out = '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Temp.</th><th>Fecha nº</th><th>Club</th><th></th><th>Rival</th>'
            . '<th>Res.</th><th>gameId</th><th>Árb.</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
        $n = 0;
        foreach ($filas as $f) {
            if ($filtro !== '' && $f['estado'] !== $filtro) continue;
            if ($n++ >= $limite) break;
            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'conflicto' ? 'err' : ($f['estado'] === 'excluido' ? 'gris' : ''));
            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id'])) . '</td>'
                . '<td class="num">' . e($f['temporada']) . '</td>'
                . '<td class="num">' . e($f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . ($f['local'] ? 'L' : 'V') . '</td>'
                . '<td>' . e($f['rival_nombre']) . ($f['rival_id'] ? ' <span class="id">#' . $f['rival_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . e($f['goles_favor']) . ':' . e($f['goles_contra']) . '</td>'
                . '<td class="num">' . e($f['external_id'] ?: '—') . '</td>'
                . '<td class="num">' . e($f['arbitro_external_id'] ?: '—') . '</td>'
                . '<td>' . e($f['estado']) . '</td>'
                . '<td>' . e($f['motivo']) . ($f['partido_id'] ? ' <span class="id">partido #' . $f['partido_id'] . '</span>' : '') . '</td>'
                . '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function pagina($titulo, $cuerpo)
    {
        $css = '
            body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px 28px;color:#1a1f1c;background:#f7f8f6}
            h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:28px 0 8px}
            .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
            .acciones{margin:12px 0} .acciones a{color:#15714e;margin-right:2px}
            .diag{background:#fff;border:1px solid #dde2dd;padding:14px 16px;font-size:13px}
            .diag code{background:#eef1ec;padding:1px 5px;font-size:12px}
            pre{font-size:11px;max-height:340px;overflow:auto;background:#f0f3ef;padding:10px}
            .cards{display:flex;flex-wrap:wrap;gap:1px;background:#dde2dd;border:1px solid #dde2dd;margin:14px 0}
            .card{background:#fff;padding:10px 16px;min-width:110px}
            .card b{display:block;font-size:20px} .card span{font-size:11px;color:#6b7a73;text-transform:uppercase;letter-spacing:.06em}
            .card.ok b{color:#15714e} .card.err b{color:#9c3529} .card.warn b{color:#8a5d00} .card.gris b{color:#9aa69f}
            .ok-box{background:#ddede4;border:1px solid #15714e;padding:10px 14px}
            .err{color:#9c3529} .warn{color:#8a5d00}
            .scroll{overflow:auto;border:1px solid #dde2dd;background:#fff;max-height:70vh}
            table{border-collapse:collapse;width:100%;font-size:12.5px}
            th,td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap}
            thead th{position:sticky;top:0;background:#eef1ec;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
            td.num{font-variant-numeric:tabular-nums}
            tr.ok td:nth-child(11){color:#15714e;font-weight:600}
            tr.err td:nth-child(11){color:#9c3529;font-weight:600}
            tr.gris{color:#9aa69f}
            .id{color:#9aa69f;font-size:11px}
            input,button{font:13px inherit;padding:3px 6px;border:1px solid #c7cec7;background:#fff}
            button{cursor:pointer;background:#eef1ec}
            details summary{cursor:pointer;color:#15714e;margin-top:6px}
        ';
        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title><style>' . $css . '</style>' . $cuerpo);
    }
}
