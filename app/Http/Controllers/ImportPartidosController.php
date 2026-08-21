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
 * partidos en totales, muestra la fila cruda de cada uno y —si se lo pedís—
 * la guarda en `import_partidos`.
 *
 * Uso:
 *   /admin/import-partidos/sondear?tecnico_id=123
 *   /admin/import-partidos/sondear?url=https://www.transfermarkt.com/x/profil/trainer/5163
 *   ...&guardar=1     -> además escribe en import_partidos
 *   ...&desde=2000    -> corte de temporada (default 2000)
 *   ...&limite=60     -> cuántas filas mostrar en la tabla (default 60)
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
        $desde     = (int) $request->get('desde', 2000);
        $limite    = (int) $request->get('limite', 60);

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

        // ── 1. Traer el rendimiento partido por partido ─────────────────────
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
            $detalle = is_array($err) ? e(json_encode($err, JSON_UNESCAPED_UNICODE)) : 'sin detalle';
            return $this->pagina('Sondeo', '<p class="err">tmapi no devolvió partidos para el coach ' . e($coachId) . '.<br>'
                . 'Causa: ' . $detalle . '</p>');
        }

        // ── 2. Diagnóstico de estructura (para saber qué campos hay de verdad) ─
        $diag = $this->diagnosticar($games[0], $coachId);

        // ── 3. Normalizar todas las filas ───────────────────────────────────
        $filas = [];
        $temporadas = [];
        foreach ($games as $g) {
            $f = $this->normalizar($g, $coachId);
            if ($f['temporada'] !== null) $temporadas[] = (int) $f['temporada'];
            $filas[] = $f;
        }

        // Nombres de competencias y clubes (mismo mecanismo que ya usás)
        $compIds = []; $clubIds = [];
        foreach ($filas as $f) {
            if ($f['competencia_external_id']) $compIds[$f['competencia_external_id']] = true;
            if ($f['club_external_id'])        $clubIds[$f['club_external_id']] = true;
            if ($f['rival_external_id'])       $clubIds[$f['rival_external_id']] = true;
        }
        $compNames = $this->resolverNombres(self::TMAPI . '/competitions', array_keys($compIds));
        $clubNames = $this->resolverNombres(self::TMAPI . '/clubs', array_keys($clubIds));

        // ── 4. Mapa de equipos nuestros, por nombre normalizado ─────────────
        $mapaEquipos = [];
        foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
            $k = $this->normalizarNombre($e->nombre);
            if ($k !== '' && !isset($mapaEquipos[$k])) $mapaEquipos[$k] = $e->id;
        }

        // ── 5. Clasificar cada fila (dedupe en seco, sin escribir en partidos) ─
        $cont = [
            'total' => count($filas), 'previos' => 0, 'sin_resultado' => 0,
            'nuevo' => 0, 'duplicado' => 0, 'conflicto' => 0,
            'falta_dt' => 0, 'con_game_id' => 0, 'con_ronda' => 0,
        ];

        foreach ($filas as $i => $f) {
            if ($f['external_id']) $cont['con_game_id']++;
            if ($f['ronda'])       $cont['con_ronda']++;

            $filas[$i]['competencia_nombre'] = $f['competencia_external_id'] && isset($compNames[$f['competencia_external_id']])
                ? $compNames[$f['competencia_external_id']] : $f['competencia_nombre'];
            $filas[$i]['club_nombre'] = $f['club_external_id'] && isset($clubNames[$f['club_external_id']])
                ? $clubNames[$f['club_external_id']] : $f['club_nombre'];
            $filas[$i]['rival_nombre'] = $f['rival_external_id'] && isset($clubNames[$f['rival_external_id']])
                ? $clubNames[$f['rival_external_id']] : $f['rival_nombre'];

            // Fuera de alcance por temporada
            if ($f['temporada'] !== null && (int) $f['temporada'] < $desde) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'temporada < ' . $desde;
                $cont['previos']++;
                continue;
            }
            if ($f['goles_favor'] === null || $f['goles_contra'] === null) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'sin resultado';
                $cont['sin_resultado']++;
                continue;
            }

            // Resolver equipos contra los nuestros
            $equipoId = $this->buscarEquipo($mapaEquipos, $filas[$i]['club_nombre']);
            $rivalId  = $this->buscarEquipo($mapaEquipos, $filas[$i]['rival_nombre']);
            $filas[$i]['equipo_id'] = $equipoId;
            $filas[$i]['rival_id']  = $rivalId;

            if (!$f['dia']) {
                $filas[$i]['estado'] = 'conflicto';
                $filas[$i]['motivo'] = 'sin fecha';
                $cont['conflicto']++;
                continue;
            }

            // ¿Ya está cargado? mismo par de equipos, fecha ±1 día
            $partido = null;
            if ($equipoId && $rivalId) {
                $d0 = date('Y-m-d 00:00:00', strtotime($f['dia'] . ' -1 day'));
                $d1 = date('Y-m-d 23:59:59', strtotime($f['dia'] . ' +1 day'));
                $partido = \App\Partido::whereBetween('dia', [$d0, $d1])
                    ->where(function ($q) use ($equipoId, $rivalId) {
                        $q->where(function ($w) use ($equipoId, $rivalId) {
                            $w->where('equipol_id', $equipoId)->where('equipov_id', $rivalId);
                        })->orWhere(function ($w) use ($equipoId, $rivalId) {
                            $w->where('equipol_id', $rivalId)->where('equipov_id', $equipoId);
                        });
                    })
                    ->first();
            }

            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $tieneDt = DB::table('partido_tecnicos')
                    ->where('partido_id', $partido->id)
                    ->where('equipo_id', $equipoId)
                    ->exists();
                if (!$tieneDt) {
                    $filas[$i]['motivo'] = 'ya cargado, le falta el DT';
                    $cont['falta_dt']++;
                } else {
                    $filas[$i]['motivo'] = 'ya cargado';
                }
                $cont['duplicado']++;
            } elseif (!$equipoId || !$rivalId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$equipoId) $faltan[] = 'club "' . $filas[$i]['club_nombre'] . '"';
                if (!$rivalId)  $faltan[] = 'rival "' . $filas[$i]['rival_nombre'] . '"';
                $filas[$i]['motivo'] = 'no existe en equipos: ' . implode(' / ', $faltan);
                $cont['conflicto']++;
            } else {
                $filas[$i]['estado'] = 'nuevo';
                $filas[$i]['motivo'] = null;
                $cont['nuevo']++;
            }
        }

        // ── 6. Guardar en staging (opcional) ────────────────────────────────
        $guardadas = 0;
        if ($guardar) {
            foreach ($filas as $f) {
                if ($f['estado'] === 'excluido') continue;
                $clave = [
                    'fuente'      => 'transfermarkt',
                    'external_id' => $f['external_id'],
                    'tecnico_id'  => $tecnicoId ?: null,
                ];
                // Sin gameId no hay clave única confiable: se distingue por fecha + rival.
                if (!$f['external_id']) {
                    $clave['external_id'] = null;
                    $clave['club_nombre'] = $f['club_nombre'];
                    $clave['rival_nombre'] = $f['rival_nombre'];
                    $clave['dia'] = $f['dia'];
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
                $guardadas++;
            }
        }

        // ── 7. Salida ───────────────────────────────────────────────────────
        sort($temporadas);
        $rango = empty($temporadas) ? '?' : (reset($temporadas) . ' – ' . end($temporadas));

        $html  = '<h1>Sondeo de partidos · ' . e($nombreDT ?: ('coach ' . $coachId)) . '</h1>';
        $html .= '<p class="sub">coach ' . e($coachId) . ' · ' . $cont['total'] . ' partidos en tmapi · temporadas ' . e($rango)
              . ' · corte en ' . $desde . '</p>';

        $html .= '<h2>1. ¿Qué trae realmente el JSON?</h2>' . $diag;

        $html .= '<h2>2. Resumen</h2><div class="cards">'
            . $this->card($cont['total'], 'partidos')
            . $this->card($cont['previos'], 'anteriores a ' . $desde, 'gris')
            . $this->card($cont['sin_resultado'], 'sin resultado', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['falta_dt'], 'cargados sin DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', 'err')
            . $this->card($cont['con_game_id'], 'con gameId', $cont['con_game_id'] ? 'ok' : 'err')
            . $this->card($cont['con_ronda'], 'con jornada', $cont['con_ronda'] ? 'ok' : 'warn')
            . '</div>';

        if ($guardar) {
            $html .= '<p class="ok-box">Guardadas ' . $guardadas . ' filas en <code>import_partidos</code>.</p>';
        } else {
            $sep = strpos($request->fullUrl(), '?') === false ? '?' : '&';
            $html .= '<p class="sub">Modo lectura: no se escribió nada. Para guardar en staging, agregá '
                  . '<a href="' . e($request->fullUrl() . $sep . 'guardar=1') . '"><code>&guardar=1</code></a>.</p>';
        }

        $html .= '<h2>3. Primeros ' . $limite . ' partidos</h2>' . $this->tabla($filas, $limite);

        return $this->pagina('Sondeo de partidos', $html);
    }

    // ───────────────────────── helpers ─────────────────────────

    /** Muestra la forma real del primer partido, para no adivinar nombres de campos. */
    private function diagnosticar(array $game, $coachId)
    {
        $lineas = [];
        $lineas[] = '<strong>Claves del partido:</strong> <code>' . e(implode(', ', array_keys($game))) . '</code>';

        foreach (['gameInformation', 'clubsInformation'] as $sub) {
            if (isset($game[$sub]) && is_array($game[$sub])) {
                $lineas[] = '<strong>' . $sub . ':</strong> <code>' . e(implode(', ', array_keys($game[$sub]))) . '</code>';
                foreach ($game[$sub] as $k => $v) {
                    if (is_array($v)) {
                        $lineas[] = '&nbsp;&nbsp;↳ <em>' . e($k) . '</em>: <code>' . e(implode(', ', array_keys($v))) . '</code>';
                    }
                }
            }
        }

        $gameId = $this->buscarGameId($game);
        $lineas[] = '<strong>gameId detectado:</strong> ' . ($gameId ? '<code>' . e($gameId) . '</code>' : '<span class="err">no se encontró</span>');

        $ronda = $this->buscarRonda($game);
        $lineas[] = '<strong>jornada / fase detectada:</strong> ' . ($ronda ? '<code>' . e($ronda) . '</code>' : '<span class="warn">no se encontró</span>');

        $lineas[] = '<details><summary>JSON crudo del primer partido</summary><pre>'
            . e(json_encode($game, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';

        return '<div class="diag">' . implode('<br>', $lineas) . '</div>';
    }

    private function normalizar(array $g, $coachId)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

        // El lado del DT: club = local, opponent = visitante (mismo criterio que ScraperController).
        $club = isset($ci['club']) ? $ci['club'] : [];
        $rival = isset($ci['opponent']) ? $ci['opponent'] : [];
        $local = true;
        if ((string) $this->valor($rival, ['coachId']) === (string) $coachId
            && (string) $this->valor($club, ['coachId']) !== (string) $coachId) {
            $club = isset($ci['opponent']) ? $ci['opponent'] : [];
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
            'external_id'             => $this->buscarGameId($g),
            'competencia_external_id' => $this->valor($gi, ['competitionId']),
            'competencia_nombre'      => $this->valor($gi, ['competitionName']),
            'temporada'               => $temporada === null ? null : (string) $temporada,
            'ronda'                   => $this->buscarRonda($g),
            'club_external_id'        => $this->valor($club, ['clubId', 'id']),
            'club_nombre'             => $this->valor($club, ['name', 'clubName']),
            'rival_external_id'       => $this->valor($rival, ['clubId', 'id']),
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

    private function buscarGameId(array $g)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $v = $this->valor($g, ['gameId', 'matchId']);
        if ($v === null) $v = $this->valor($gi, ['gameId', 'matchId', 'id']);
        if ($v === null) $v = $this->valor($g, ['id']);
        return $v === null ? null : (string) $v;
    }

    private function buscarRonda(array $g)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $v = $this->valor($gi, ['matchDay', 'matchday', 'round', 'gameDay', 'competitionGroupName', 'competitionGroupId']);
        if ($v === null) $v = $this->valor($g, ['matchDay', 'round']);
        if (is_array($v)) $v = $this->valor($v, ['name', 'id']);
        return $v === null ? null : (string) $v;
    }

    private function valor($arr, array $claves)
    {
        if (!is_array($arr)) return null;
        foreach ($claves as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') return $arr[$k];
        }
        return null;
    }

    /** Igual que tmResolveNames de ScraperController, para no depender de un método privado. */
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

    private function normalizarNombre($str)
    {
        $str = (string) $str;
        $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($conv !== false) $str = $conv;
        $str = mb_strtolower($str);
        $str = preg_replace('/\b(club|atletico|atlético|deportivo|asociacion|asociación|futbol|fútbol|fc|cf|ac|sc|cd|ca)\b/u', '', $str);
        $str = preg_replace('/[^\p{L}\p{N}]+/u', '', $str);
        return (string) $str;
    }

    private function buscarEquipo(array $mapa, $nombre)
    {
        if (!$nombre) return null;
        $k = $this->normalizarNombre($nombre);
        return isset($mapa[$k]) ? $mapa[$k] : null;
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

    private function tabla(array $filas, $limite)
    {
        $out = '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Temp.</th><th>Club</th><th></th><th>Rival</th>'
            . '<th>Res.</th><th>gameId</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';

        $n = 0;
        foreach ($filas as $f) {
            if ($n++ >= $limite) break;
            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'duplicado' ? '' : ($f['estado'] === 'conflicto' ? 'err' : 'gris'));
            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id'])) . '</td>'
                . '<td class="num">' . e($f['temporada']) . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . ($f['local'] ? 'L' : 'V') . '</td>'
                . '<td>' . e($f['rival_nombre']) . ($f['rival_id'] ? ' <span class="id">#' . $f['rival_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . e($f['goles_favor']) . ':' . e($f['goles_contra']) . '</td>'
                . '<td class="num">' . e($f['external_id'] ?: '—') . '</td>'
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
            .sub{color:#6b7a73;margin:0 0 8px}
            .diag{background:#fff;border:1px solid #dde2dd;padding:14px 16px;font-size:13px}
            .diag code{background:#eef1ec;padding:1px 5px;font-size:12px}
            pre{background:#11181500;font-size:11px;max-height:340px;overflow:auto;background:#f0f3ef;padding:10px}
            .cards{display:flex;flex-wrap:wrap;gap:1px;background:#dde2dd;border:1px solid #dde2dd}
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
            tr.ok td:nth-child(9){color:#15714e;font-weight:600}
            tr.err td:nth-child(9){color:#9c3529;font-weight:600}
            tr.gris{color:#9aa69f}
            .id{color:#9aa69f;font-size:11px}
            details summary{cursor:pointer;color:#15714e;margin-top:6px}
        ';
        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title><style>' . $css . '</style>' . $cuerpo);
    }
}
