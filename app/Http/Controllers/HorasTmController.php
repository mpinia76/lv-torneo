<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /admin/import-partidos/horas-html
 *
 * Hasta el 2026-09-29 el calendario en HTML de TM (transfermarkt.es,
 * TmFixtureCompetenciaHtml) se guardaba con la hora de ESPAÑA, no la
 * argentina: Danubio–Wanderers (25/05/2014 16:00 en Montevideo) entraba a las
 * 21:00, y un partido de las 21:00 caía al día siguiente a las 02:00. El
 * camino de la API (dateTimeUTC) siempre estuvo bien.
 *
 * Esta pantalla encuentra los partidos que se crearon o se ajustaron desde ese
 * calendario y les pasa día y hora a la argentina.
 *
 * CÓMO SABE CUÁLES TOCAR. Sólo mira filas del staging con `_fuente: html` y
 * partido atado. La hora "de España" de esa fila sale de `import_partidos.dia`
 * (o, si la fila ya se volvió a guardar después del arreglo —`hora_ar` en el
 * payload—, de deshacer la conversión). Se corrige el partido SÓLO si su `dia`
 * es exactamente esa hora de España:
 *   - si ya está en la hora argentina, está bien (no se toca);
 *   - si es otra cosa, alguien la cambió después (a mano, el detalle, la
 *     API): no se toca y se muestra aparte.
 * Por eso se puede correr cuantas veces se quiera: lo corregido ya no
 * coincide con la hora de España y no se vuelve a mover.
 * Los partidos sin hora (00:00) no se tocan: no hay hora que convertir.
 */
class HorasTmController extends Controller
{
    const ZONA_TM = 'Europe/Madrid';

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        set_time_limit(0);

        $aplicar  = (string) $request->get('aplicar', '0') === '1';
        $soloTorneo = (int) $request->get('torneo_id', 0);

        $zonaAr = config('app.timezone', 'America/Argentina/Buenos_Aires');

        $filas = DB::table('import_partidos AS ip')
            ->join('partidos AS p', 'p.id', '=', 'ip.partido_id')
            ->join('fechas AS f', 'f.id', '=', 'p.fecha_id')
            ->join('grupos AS g', 'g.id', '=', 'f.grupo_id')
            ->join('torneos AS t', 't.id', '=', 'g.torneo_id')
            ->whereNotNull('ip.partido_id')
            ->where('ip.payload', 'like', '%"_fuente":"html"%')
            ->select('ip.id AS ip_id', 'ip.dia AS ip_dia', 'ip.payload', 'p.id AS partido_id', 'p.dia AS p_dia',
                'p.equipol_id', 'p.equipov_id', 't.id AS torneo_id', 't.nombre AS torneo_nombre', 't.year AS torneo_year')
            ->orderBy('t.id')
            ->orderBy('p.dia')
            ->get();

        $vistos = [];
        $porTorneo = [];   // torneo_id => ['nombre', 'corregir' => [...], 'bien' => n, 'distinto' => [...], 'sin_hora' => n]

        foreach ($filas as $r) {
            if (isset($vistos[$r->partido_id])) {
                continue;
            }
            $tid = (int) $r->torneo_id;
            if (!isset($porTorneo[$tid])) {
                $porTorneo[$tid] = ['nombre' => $r->torneo_nombre . ' ' . $r->torneo_year,
                    'corregir' => [], 'bien' => 0, 'distinto' => [], 'sin_hora' => 0];
            }

            $espana = $this->horaEspana($r, $zonaAr);
            if ($espana === null) {
                // Sin hora en el staging: no hay con qué comparar. Se deja
                // para otra fila del mismo partido, si la hay.
                continue;
            }
            $vistos[$r->partido_id] = true;

            if ($espana === 'sin_hora') {
                $porTorneo[$tid]['sin_hora']++;
                continue;
            }

            $ar = $this->convertir($espana, self::ZONA_TM, $zonaAr);
            $actual = substr((string) $r->p_dia, 0, 16);

            if ($actual === $espana) {
                $porTorneo[$tid]['corregir'][] = ['r' => $r, 'de' => $espana, 'a' => $ar];
            } elseif ($actual === $ar) {
                $porTorneo[$tid]['bien']++;
            } else {
                $porTorneo[$tid]['distinto'][] = ['r' => $r, 'de' => $espana, 'a' => $ar];
            }
        }

        // ── Aplicar ───────────────────────────────────────────────────────
        $hechos = 0;
        if ($aplicar) {
            foreach ($porTorneo as $tid => $t) {
                if ($soloTorneo && $soloTorneo !== $tid) {
                    continue;
                }
                DB::transaction(function () use ($t, &$hechos) {
                    foreach ($t['corregir'] as $c) {
                        // Condicional: si el partido cambió entre que se
                        // armó la pantalla y el clic, no se pisa.
                        $n = DB::table('partidos')
                            ->where('id', $c['r']->partido_id)
                            ->where('dia', $c['de'] . ':00')
                            ->update(['dia' => $c['a'] . ':00']);
                        $hechos += $n;
                    }
                });
            }
            return redirect()->route('import_partidos.horas_html')
                ->with('success', 'Pasé a hora argentina ' . $hechos . ' partido' . ($hechos == 1 ? '' : 's') . '.');
        }

        return view('import.pagina', [
            'titulo' => 'Horas del calendario HTML',
            'cuerpo' => $this->armar($porTorneo),
        ]);
    }

    /**
     * La hora "de España" con la que el calendario dejó a esta fila, 'Y-m-d H:i'.
     * 'sin_hora' si quedó a las 00:00; null si no se puede saber.
     */
    private function horaEspana($r, $zonaAr)
    {
        if (empty($r->ip_dia)) {
            return null;
        }
        $g = json_decode((string) $r->payload, true);
        $dia = substr((string) $r->ip_dia, 0, 16);

        if (substr($dia, 11) === '00:00' && (!is_array($g) || empty($g['hora']))) {
            return 'sin_hora';
        }
        if (is_array($g) && !empty($g['hora_ar'])) {
            // Fila guardada después del arreglo: está en hora argentina.
            // Se deshace para saber qué había escrito el calendario viejo.
            return $this->convertir($dia, $zonaAr, self::ZONA_TM);
        }
        return $dia;
    }

    private function convertir($ymdHi, $de, $a)
    {
        $dt = new \DateTime($ymdHi . ':00', new \DateTimeZone($de));
        $dt->setTimezone(new \DateTimeZone($a));
        return $dt->format('Y-m-d H:i');
    }

    private function armar(array $porTorneo)
    {
        $url = route('import_partidos.horas_html');
        $totCorregir = 0; $totBien = 0; $totDistinto = 0;
        foreach ($porTorneo as $t) {
            $totCorregir += count($t['corregir']);
            $totBien += $t['bien'];
            $totDistinto += count($t['distinto']);
        }

        $nombres = [];
        $ids = [];
        foreach ($porTorneo as $t) {
            foreach (array_merge($t['corregir'], $t['distinto']) as $c) {
                $ids[(int) $c['r']->equipol_id] = true;
                $ids[(int) $c['r']->equipov_id] = true;
            }
        }
        if ($ids) {
            $nombres = DB::table('equipos')->whereIn('id', array_keys($ids))->pluck('nombre', 'id')->all();
        }
        $eq = function ($id) use ($nombres) {
            return e(isset($nombres[$id]) ? $nombres[$id] : '#' . $id);
        };

        $h = '<h1>Horas del calendario HTML</h1>'
            . '<p class="sub">Partidos importados desde el calendario en HTML de Transfermarkt antes del 29/09/2026, '
            . 'que se guardaron con la hora de España en vez de la argentina. Sólo se corrige un partido si todavía '
            . 'tiene exactamente la hora que le puso el calendario; si alguien la cambió después, se muestra aparte y '
            . 'no se toca.</p>';

        $h .= '<div class="cards">'
            . '<div class="card warn"><b>' . $totCorregir . '</b><span>a corregir</span></div>'
            . '<div class="card ok"><b>' . $totBien . '</b><span>ya en hora argentina</span></div>'
            . '<div class="card gris"><b>' . $totDistinto . '</b><span>cambiados después, no se tocan</span></div>'
            . '</div>';

        if ($totCorregir > 0) {
            $h .= '<div class="acciones"><a class="boton" href="' . e($url . '?aplicar=1')
                . '">Corregir los ' . $totCorregir . '</a></div>';
        } else {
            $h .= '<div class="ok-box">No queda ningún partido con la hora de España.</div>';
        }

        foreach ($porTorneo as $tid => $t) {
            if (empty($t['corregir']) && empty($t['distinto'])) {
                continue;
            }
            $h .= '<h2>' . e($t['nombre']) . ' <span class="id">#' . $tid . '</span></h2>'
                . '<p class="sub">' . count($t['corregir']) . ' a corregir · ' . $t['bien'] . ' bien · '
                . count($t['distinto']) . ' cambiados después · ' . $t['sin_hora'] . ' sin hora';
            if ($t['corregir']) {
                $h .= ' · <a href="' . e($url . '?aplicar=1&torneo_id=' . $tid) . '">corregir sólo este torneo</a>';
            }
            $h .= '</p><div class="scroll"><table><thead><tr><th>Partido</th><th>Ahora</th><th>Pasa a</th><th></th></tr></thead><tbody>';
            foreach ($t['corregir'] as $c) {
                $r = $c['r'];
                $h .= '<tr><td>' . $eq($r->equipol_id) . ' – ' . $eq($r->equipov_id) . ' <span class="id">#' . (int) $r->partido_id . '</span></td>'
                    . '<td class="num">' . e($c['de']) . '</td><td class="num ok">' . e($c['a']) . '</td><td></td></tr>';
            }
            foreach ($t['distinto'] as $c) {
                $r = $c['r'];
                $h .= '<tr class="gris"><td>' . $eq($r->equipol_id) . ' – ' . $eq($r->equipov_id) . ' <span class="id">#' . (int) $r->partido_id . '</span></td>'
                    . '<td class="num">' . e(substr((string) $r->p_dia, 0, 16)) . '</td><td class="num">—</td>'
                    . '<td class="wrap">el calendario decía ' . e($c['de']) . ' (España) = ' . e($c['a']) . ' (Argentina); no coincide con ninguna, no se toca</td></tr>';
            }
            $h .= '</tbody></table></div>';
        }

        return $h;
    }
}
