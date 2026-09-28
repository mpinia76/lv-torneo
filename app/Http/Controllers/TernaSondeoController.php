<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\HttpHelper;

/**
 * Sondeo de la terna arbitral: ¿el dato no existe en Transfermarkt, o existe y
 * lo estamos perdiendo?
 *
 * La primera versión de esta pantalla contaba cuántos asistentes manda TM y
 * cantaba "se está perdiendo el dato" con eso solo. Estaba mal: el sondeo elige
 * partidos a los que les falta Línea 1 *o* Línea 2, así que un partido que ya
 * tiene la Línea 1 cargada y sólo le falta la Línea 2 sumaba igual. Contar lo
 * que TM tiene sin mirar lo que hay en la base no prueba nada.
 *
 * Ahora compara rol por rol, y sólo cuenta como pérdida la celda donde TM trae
 * un árbitro y `partido_arbitros` no tiene nada para ese rol. De paso dice si
 * ese árbitro está en `arbitro_tm`, que es la causa más probable de que el
 * importador lo haya salteado.
 *
 * Cuesta una llamada por partido. No escribe nada.
 *
 *   /admin/import-detalles/terna-sondeo?n=25
 */
class TernaSondeoController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Clave de tmapi -> rol nuestro. Sólo estas cinco entran en `partido_arbitros`. */
    private static $claves = [
        'refereeId'                => 'Principal',
        'firstRefereeAssistantId'  => 'Linea 1',
        'secondRefereeAssistantId' => 'Linea 2',
        'fourthOfficialId'         => 'Cuarto',
        'firstVideoAssistantId'    => 'VAR',
    ];

    /** Las que TM manda y no tienen lugar en el enum: se miran, no se cargan. */
    private static $clavesFuera = [
        'secondVideoAssistantId' => 'AVAR',
        'firstGoalJudgeId'       => 'Juez gol 1',
        'secondGoalJudgeId'      => 'Juez gol 2',
    ];

    public function index(Request $request)
    {
        set_time_limit(0);

        $n       = max(1, min(60, (int) $request->get('n', 20)));
        $soloSin = (string) $request->get('solo_sin_terna', '1') !== '0';

        $filas = $this->partidos($n, $soloSin);

        $cuerpo = '<p class="sub"><a href="' . e(url('/admin/controles?check=arbitros.terna')) . '">← Controles · Terna incompleta</a></p>'
            . '<h1>Sondeo de la terna en Transfermarkt</h1>'
            . '<p class="sub">Compara, rol por rol, lo que manda <code>/game/{gameId}</code> contra lo que hay en '
            . '<code>partido_arbitros</code>. Una llamada por partido. No escribe nada.</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<label>Partidos <input type="number" name="n" value="' . $n . '" min="1" max="60" size="4"></label> '
            . '<label><input type="checkbox" name="solo_sin_terna" value="1"' . ($soloSin ? ' checked' : '')
            . '> sólo los que hoy tienen la terna incompleta</label> '
            . '<button>Sondear</button></form>';

        if (empty($filas)) {
            return $this->pagina('Sondeo de terna',
                $cuerpo . '<div class="ok-box">No encontré partidos importados que cumplan el filtro.</div>');
        }

        $enBase   = $this->arbitrosEnBase(array_map(function ($f) { return (int) $f->id; }, $filas));
        $mapaTm   = $this->mapaArbitroTm();
        $nombres  = $this->nombresArbitros();

        $falta = []; $ok = []; $distinto = []; $soloBase = []; $sinDato = [];
        foreach (self::$claves as $rol) { $falta[$rol] = 0; $ok[$rol] = 0; $distinto[$rol] = 0; $soloBase[$rol] = 0; $sinDato[$rol] = 0; }

        $faltaSinMapear = 0;
        $faltaDuplicado = 0;
        $descartados    = [];
        $otrasClaves    = [];
        $detalle        = '';
        $primerCrudo    = null;
        $llamadas = 0; $fallaron = 0; $sondeados = 0;

        foreach ($filas as $f) {
            $json = HttpHelper::getJson(self::TMAPI . '/game/' . rawurlencode((string) $f->external_id));
            $llamadas++;

            if (!is_array($json) || empty($json)) {
                $fallaron++;
                $detalle .= '<tr><td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                    . '<td>' . e($f->partido) . '</td>'
                    . '<td colspan="' . count(self::$claves) . '" class="err">la API no devolvió el partido</td></tr>';
                continue;
            }
            $sondeados++;

            $game = isset($json['data']) ? $json['data'] : $json;
            $ids  = isset($game['refereeIds']) && is_array($game['refereeIds']) ? $game['refereeIds'] : [];
            $base = isset($enBase[(int) $f->id]) ? $enBase[(int) $f->id] : [];

            if ($primerCrudo === null) {
                $primerCrudo = ['partido' => $f->partido, 'gameId' => $f->external_id,
                    'refereeIds' => isset($game['refereeIds']) ? $game['refereeIds'] : '(no viene la clave)',
                    'en_la_base' => $base, 'claves_del_game' => array_keys($game)];
            }

            foreach (array_keys($ids) as $k) {
                if (isset(self::$claves[$k]) || isset(self::$clavesFuera[$k])) continue;
                $otrasClaves[$k] = true;
            }
            foreach (self::$clavesFuera as $k => $etiqueta) {
                if (!empty($ids[$k])) $descartados[$etiqueta] = isset($descartados[$etiqueta]) ? $descartados[$etiqueta] + 1 : 1;
            }

            $celdas = '';
            foreach (self::$claves as $clave => $rol) {
                $tm  = isset($ids[$clave]) && $ids[$clave] !== null && $ids[$clave] !== '' && $ids[$clave] !== 0 && $ids[$clave] !== '0'
                    ? (string) $ids[$clave] : null;
                $db  = isset($base[$rol]) ? (int) $base[$rol] : null;

                if ($tm === null && $db === null) {
                    $sinDato[$rol]++;
                    $celdas .= '<td class="gris">—</td>';
                } elseif ($tm === null && $db !== null) {
                    $soloBase[$rol]++;
                    $celdas .= '<td class="gris">sólo base<br><span class="id">' . e($this->nombre($nombres, $db)) . '</span></td>';
                } elseif ($tm !== null && $db === null) {
                    $falta[$rol]++;
                    $mapeado  = isset($mapaTm[$tm]);
                    $esperado = $mapeado ? (int) $mapaTm[$tm] : null;

                    // ¿Ese mismo árbitro ya está cargado en este partido con OTRO
                    // rol? Si sí, la fila no se perdió por el importador: la
                    // rechazó la base. `partido_arbitros` no admite dos veces al
                    // mismo juez en un partido, y eso pasa cuando dos ids de
                    // Transfermarkt distintos apuntan a la misma persona nuestra.
                    $yaEn = null;
                    if ($esperado !== null) {
                        foreach ($base as $rolBase => $aid) {
                            if ((int) $aid === $esperado) { $yaEn = $rolBase; break; }
                        }
                    }

                    if (!$mapeado) {
                        $faltaSinMapear++;
                        $nota = ' · <b>sin mapear</b>';
                    } elseif ($yaEn !== null) {
                        $faltaDuplicado++;
                        $nota = ' · <b>ya está en este partido como ' . e($yaEn) . '</b>';
                    } else {
                        $nota = ' · mapeado y libre';
                    }

                    $celdas .= '<td class="err"><b>FALTA</b><br><span class="id">TM ' . e($tm) . $nota . '</span></td>';
                } else {
                    $esperado = isset($mapaTm[$tm]) ? (int) $mapaTm[$tm] : null;
                    if ($esperado !== null && $esperado !== $db) {
                        $distinto[$rol]++;
                        $celdas .= '<td class="warn">distinto<br><span class="id">TM ' . e($tm) . ' → #' . $esperado
                            . ' · base #' . $db . '</span></td>';
                    } else {
                        $ok[$rol]++;
                        $celdas .= '<td class="ok">ok<br><span class="id">' . e($this->nombre($nombres, $db)) . '</span></td>';
                    }
                }
            }

            $detalle .= '<tr><td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                . '<td>' . e($f->partido) . ' <span class="id">#' . (int) $f->id . ' · game ' . e((string) $f->external_id) . '</span></td>'
                . $celdas . '</tr>';
        }

        $totalFalta = array_sum($falta);

        // ── Veredicto ───────────────────────────────────────────────────────
        if ($sondeados === 0) {
            $veredicto = '<div class="err-box">No se pudo sondear ningún partido: la API no respondió.</div>';
        } elseif ($totalFalta === 0) {
            $veredicto = '<div class="ok-box"><b>No se está perdiendo nada.</b><br>'
                . 'En los ' . $sondeados . ' partidos sondeados no hay ni un rol donde Transfermarkt traiga un árbitro '
                . 'y la base esté vacía. Lo que falta en la terna, TM no lo tiene: manda esas claves en <code>null</code>. '
                . 'Son partidos para marcar con "Terna incompleta en TM".</div>';
        } else {
            $veredicto = '<div class="err-box"><b>Se están perdiendo ' . $totalFalta . ' árbitro(s) '
                . 'en ' . $sondeados . ' partidos.</b><br>'
                . 'Son celdas donde TM trae un id y <code>partido_arbitros</code> no tiene nada para ese rol.'
                . ($faltaSinMapear > 0
                    ? '<br>· <b>' . $faltaSinMapear . '</b> con el árbitro sin mapear en <code>arbitro_tm</code>: '
                    . 'el importador saltea al juez que no puede resolver ni crear.'
                    : '')
                . ($faltaDuplicado > 0
                    ? '<br>· <b>' . $faltaDuplicado . '</b> donde ese mismo árbitro <b>ya está en el partido con otro rol</b>. '
                    . 'Ahí no falla el importador: la base rechaza la fila porque no admite dos veces al mismo juez en un '
                    . 'partido. Pasa cuando dos ids de Transfermarkt apuntan a la misma persona nuestra — o sea, un '
                    . 'árbitro duplicado en <code>arbitro_tm</code>, o TM repitiendo a alguien en dos roles.'
                    : '')
                . ($faltaSinMapear === 0 && $faltaDuplicado === 0
                    ? '<br>Todos están mapeados y libres, así que el problema está en el guardado.'
                    : '')
                . '</div>';
        }
        $cuerpo .= $veredicto;

        // ── Resumen por rol ─────────────────────────────────────────────────
        $cuerpo .= '<h2>Rol por rol</h2>'
            . '<p class="sub">Sobre ' . $sondeados . ' partido(s)' . ($fallaron ? ' (' . $fallaron . ' no respondieron)' : '') . '. '
            . '<b>Falta</b> es lo único que indica un problema nuestro.</p>'
            . '<div class="scroll"><table><thead><tr><th>Rol</th><th>Falta</th><th>Coincide</th>'
            . '<th>Distinto</th><th>Sólo en la base</th><th>Ninguno lo tiene</th></tr></thead><tbody>';
        foreach (self::$claves as $rol) {
            $cuerpo .= '<tr><td><b>' . e($rol) . '</b></td>'
                . '<td class="num">' . ($falta[$rol] ? '<b class="err">' . $falta[$rol] . '</b>' : '0') . '</td>'
                . '<td class="num ok">' . $ok[$rol] . '</td>'
                . '<td class="num">' . ($distinto[$rol] ? '<b class="warn">' . $distinto[$rol] . '</b>' : '0') . '</td>'
                . '<td class="num gris">' . $soloBase[$rol] . '</td>'
                . '<td class="num gris">' . $sinDato[$rol] . '</td></tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        if (!empty($descartados)) {
            $partes = [];
            foreach ($descartados as $k => $v) $partes[] = $k . ' (' . $v . ')';
            $cuerpo .= '<p class="sub">Transfermarkt además manda roles que no existen en tu enum y se descartan a propósito: '
                . e(implode(' · ', $partes)) . '.</p>';
        }
        if (!empty($otrasClaves)) {
            $cuerpo .= '<div class="err-box"><b>Claves de <code>refereeIds</code> sin mapear:</b> '
                . e(implode(', ', array_keys($otrasClaves))) . '</div>';
        }

        // ── Detalle ─────────────────────────────────────────────────────────
        $cuerpo .= '<h2>Partido por partido</h2><div class="scroll"><table><thead><tr><th>Fecha</th><th>Partido</th>';
        foreach (self::$claves as $rol) $cuerpo .= '<th>' . e($rol) . '</th>';
        $cuerpo .= '</tr></thead><tbody>' . $detalle . '</tbody></table></div>';

        if ($primerCrudo) {
            $cuerpo .= '<h2>El crudo del primero</h2><pre>'
                . e(json_encode($primerCrudo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        return $this->pagina('Sondeo de terna', $cuerpo);
    }

    // ═══════════════════════════ DATOS ═══════════════════════════

    private function partidos($n, $soloSin)
    {
        $existe = function ($rol) {
            return "EXISTS (SELECT 1 FROM partido_arbitros pa
                            WHERE pa.partido_id = partidos.id AND pa.tipo = '" . $rol . "')";
        };

        $q = DB::table('partidos')
            ->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->join('import_partidos as ip', 'ip.partido_id', '=', 'partidos.id')
            ->whereNotNull('ip.external_id')->where('ip.external_id', '!=', '')
            ->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id');
            })
            ->select('partidos.id', 'partidos.dia', DB::raw('MAX(ip.external_id) as external_id'),
                DB::raw("CONCAT(el.nombre, ' vs ', ev.nombre) as partido"))
            ->groupBy('partidos.id', 'partidos.dia', 'el.nombre', 'ev.nombre');

        if ($soloSin) {
            $q->where(function ($w) use ($existe) {
                $w->whereRaw('NOT ' . $existe('Linea 1'))
                  ->orWhereRaw('NOT ' . $existe('Linea 2'));
            });
        }

        return $q->orderByDesc('partidos.dia')->limit($n)->get()->all();
    }

    /** partido_id => [tipo => arbitro_id] */
    private function arbitrosEnBase(array $partidoIds)
    {
        $out = [];
        if (empty($partidoIds)) return $out;
        foreach (DB::table('partido_arbitros')->whereIn('partido_id', $partidoIds)
                     ->select('partido_id', 'tipo', 'arbitro_id')->get() as $r) {
            $out[(int) $r->partido_id][(string) $r->tipo] = (int) $r->arbitro_id;
        }
        return $out;
    }

    /** tm_referee_id => arbitro_id */
    private function mapaArbitroTm()
    {
        $out = [];
        foreach (DB::table('arbitro_tm')->select('tm_referee_id', 'arbitro_id')->get() as $r) {
            $out[(string) $r->tm_referee_id] = (int) $r->arbitro_id;
        }
        return $out;
    }

    /** arbitro_id => nombre */
    private function nombresArbitros()
    {
        $out = [];
        foreach (DB::table('arbitros')->join('personas', 'personas.id', '=', 'arbitros.persona_id')
                     ->select('arbitros.id', 'personas.name')->get() as $r) {
            $out[(int) $r->id] = (string) $r->name;
        }
        return $out;
    }

    private function nombre(array $nombres, $id)
    {
        return isset($nombres[$id]) && $nombres[$id] !== '' ? $nombres[$id] : ('#' . $id);
    }

    private function pagina($titulo, $cuerpo)
    {
        $css = '
            body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px 28px;color:#1a1f1c;background:#f7f8f6}
            h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:28px 0 8px}
            .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
            a{color:#15714e}
            .ok-box{background:#ddede4;border:1px solid #15714e;padding:10px 14px;margin:14px 0}
            .err-box{background:#f6e2de;border:1px solid #9c3529;padding:10px 14px;margin:14px 0}
            .err{color:#9c3529} .ok{color:#15714e} .warn{color:#8a5d00} .gris{color:#9aa69f}
            .scroll{overflow:auto;border:1px solid #dde2dd;background:#fff;max-height:70vh}
            table{border-collapse:collapse;width:100%;font-size:12.5px}
            th,td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap;vertical-align:top}
            thead th{position:sticky;top:0;background:#eef1ec;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
            td.num{font-variant-numeric:tabular-nums}
            .id{color:#9aa69f;font-size:11px}
            code{background:#eef1ec;padding:1px 5px;font-size:12px}
            pre{font-size:11px;max-height:420px;overflow:auto;background:#f0f3ef;padding:10px}
            input,button{font:13px inherit;padding:3px 6px;border:1px solid #c7cec7;background:#fff}
            button{cursor:pointer;background:#eef1ec}
        ';
        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title>'
            . '<style>' . $css . '</style>' . $cuerpo);
    }
}
