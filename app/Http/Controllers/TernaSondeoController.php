<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\HttpHelper;

/**
 * Sondeo de la terna arbitral.
 *
 * Contesta con datos una pregunta que hasta ahora se contestaba de memoria:
 * ¿Transfermarkt manda los asistentes y nosotros los perdemos, o directamente
 * no los tiene?
 *
 * Agarra N partidos ya importados a los que el control "Terna incompleta" les
 * marca un faltante, vuelve a pedir `/game/{gameId}` y anota, clave por clave,
 * si el dato vino con valor, vino en null, o la clave ni siquiera existe. Esa
 * distinción es la que resuelve la discusión:
 *
 *   - clave presente en null  -> TM conoce el campo y no tiene el dato
 *   - clave ausente           -> puede ser que el dato viva en otro lado
 *   - clave con valor         -> el dato estaba y lo estábamos perdiendo
 *
 * Además lista TODAS las claves de primer nivel del JSON del partido y marca
 * las que huelan a árbitro, por si la terna viene en una rama que no miramos.
 *
 * Cuesta una llamada por partido sondeado. No escribe nada en la base.
 *
 *   /admin/import-detalles/terna-sondeo?n=25
 *   /admin/import-detalles/terna-sondeo?n=25&torneo_id=123
 */
class TernaSondeoController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Las claves de `refereeIds` que conocemos, en el orden en que importan. */
    private static $claves = [
        'refereeId'                => 'Principal',
        'firstRefereeAssistantId'  => 'Línea 1',
        'secondRefereeAssistantId' => 'Línea 2',
        'fourthOfficialId'         => 'Cuarto',
        'firstVideoAssistantId'    => 'VAR',
        'secondVideoAssistantId'   => 'AVAR',
        'firstGoalJudgeId'         => 'Juez gol 1',
        'secondGoalJudgeId'        => 'Juez gol 2',
    ];

    public function index(Request $request)
    {
        set_time_limit(0);

        $n        = max(1, min(60, (int) $request->get('n', 20)));
        $torneoId = (int) $request->get('torneo_id', 0);
        $soloSin  = (string) $request->get('solo_sin_terna', '1') !== '0';

        $filas = $this->partidos($n, $torneoId, $soloSin);

        $cuerpo = '<p class="sub"><a href="' . e(url('/admin/controles?check=arbitros.terna')) . '">← Controles · Terna incompleta</a></p>'
            . '<h1>Sondeo de la terna en Transfermarkt</h1>'
            . '<p class="sub">Vuelve a pedir <code>/game/{gameId}</code> de ' . count($filas) . ' partido(s) ya importados '
            . 'y mira qué manda TM en <code>refereeIds</code>. Una llamada por partido. No escribe nada.</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<label>Partidos a sondear <input type="number" name="n" value="' . $n . '" min="1" max="60" size="4"></label> '
            . '<label><input type="checkbox" name="solo_sin_terna" value="1"' . ($soloSin ? ' checked' : '')
            . '> sólo los que hoy tienen la terna incompleta</label> '
            . '<input type="hidden" name="torneo_id" value="' . ($torneoId ?: '') . '"> '
            . '<button>Sondear</button></form>';

        if (empty($filas)) {
            return $this->pagina('Sondeo de terna',
                $cuerpo . '<div class="ok-box">No encontré partidos importados que cumplan el filtro.</div>');
        }

        // ── El sondeo ───────────────────────────────────────────────────────
        $conteo = [];   // clave => ['valor'=>n, 'null'=>n, 'ausente'=>n]
        foreach (array_keys(self::$claves) as $k) $conteo[$k] = ['valor' => 0, 'null' => 0, 'ausente' => 0];

        $otrasClaves = [];   // claves de refereeIds que no conocemos
        $ramasRaras  = [];   // claves del game que huelen a árbitro
        $detalle     = '';
        $primerCrudo = null;
        $llamadas    = 0;
        $fallaron    = 0;

        foreach ($filas as $f) {
            $json = HttpHelper::getJson(self::TMAPI . '/game/' . rawurlencode((string) $f->external_id));
            $llamadas++;

            if (!is_array($json) || empty($json)) {
                $fallaron++;
                $detalle .= '<tr class="err"><td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                    . '<td>' . e($f->partido) . '</td>'
                    . '<td class="num">' . e((string) $f->external_id) . '</td>'
                    . '<td colspan="' . count(self::$claves) . '">la API no devolvió el partido</td></tr>';
                continue;
            }

            $game = isset($json['data']) ? $json['data'] : $json;
            $ids  = isset($game['refereeIds']) && is_array($game['refereeIds']) ? $game['refereeIds'] : [];

            if ($primerCrudo === null) {
                $primerCrudo = ['partido' => $f->partido, 'gameId' => $f->external_id,
                    'refereeIds' => isset($game['refereeIds']) ? $game['refereeIds'] : '(no viene la clave)',
                    'claves_del_game' => array_keys($game)];
            }

            // Ramas de primer nivel que puedan contener árbitros y no miramos.
            foreach (array_keys($game) as $k) {
                $bajo = mb_strtolower($k);
                if ($k === 'refereeIds') continue;
                if (mb_strpos($bajo, 'refer') !== false || mb_strpos($bajo, 'official') !== false
                    || mb_strpos($bajo, 'schieds') !== false || mb_strpos($bajo, 'umpire') !== false) {
                    $ramasRaras[$k] = isset($ramasRaras[$k]) ? $ramasRaras[$k] + 1 : 1;
                }
            }

            foreach (array_keys($ids) as $k) {
                if (!isset(self::$claves[$k])) {
                    $otrasClaves[$k] = isset($otrasClaves[$k]) ? $otrasClaves[$k] + 1 : 1;
                }
            }

            $celdas = '';
            foreach (self::$claves as $clave => $rol) {
                if (!array_key_exists($clave, $ids)) {
                    $conteo[$clave]['ausente']++;
                    $celdas .= '<td class="num gris">—</td>';
                } elseif ($ids[$clave] === null || $ids[$clave] === '' || $ids[$clave] === 0 || $ids[$clave] === '0') {
                    $conteo[$clave]['null']++;
                    $celdas .= '<td class="num warn">null</td>';
                } else {
                    $conteo[$clave]['valor']++;
                    $celdas .= '<td class="num ok"><b>' . e(is_array($ids[$clave])
                            ? json_encode($ids[$clave]) : (string) $ids[$clave]) . '</b></td>';
                }
            }

            $detalle .= '<tr><td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                . '<td>' . e($f->partido) . '</td>'
                . '<td class="num"><span class="id">' . e((string) $f->external_id) . '</span></td>'
                . $celdas . '</tr>';
        }

        $sondeados = $llamadas - $fallaron;

        // ── El veredicto ────────────────────────────────────────────────────
        $conAsistente = $conteo['firstRefereeAssistantId']['valor'] + $conteo['secondRefereeAssistantId']['valor'];
        $nullAsist    = $conteo['firstRefereeAssistantId']['null'] + $conteo['secondRefereeAssistantId']['null'];
        $ausAsist     = $conteo['firstRefereeAssistantId']['ausente'] + $conteo['secondRefereeAssistantId']['ausente'];

        if ($sondeados === 0) {
            $veredicto = '<div class="err-box">No se pudo sondear ningún partido: la API no respondió.</div>';
        } elseif ($conAsistente > 0) {
            $veredicto = '<div class="err-box"><b>Transfermarkt SÍ manda asistentes en algunos de estos partidos '
                . '(' . $conAsistente . ' valor(es) en ' . $sondeados . ' partidos) y en la base no están.</b><br>'
                . 'O sea que el dato se está perdiendo en el camino: hay que mirar por qué el importador no lo guardó. '
                . 'Empezá por un partido de los que abajo tienen número en Línea 1 o Línea 2 y rehacelo mirando los avisos.</div>';
        } elseif ($nullAsist > 0 && $ausAsist === 0) {
            $veredicto = '<div class="ok-box"><b>Transfermarkt no tiene los asistentes de estos partidos.</b><br>'
                . 'Manda las claves <code>firstRefereeAssistantId</code> y <code>secondRefereeAssistantId</code> '
                . 'explícitamente en <code>null</code> en los ' . $sondeados . ' partidos sondeados. '
                . 'No es un problema del importador: el dato no existe en la fuente. '
                . 'Estos partidos son para marcar con "Terna incompleta en TM".</div>';
        } elseif ($ausAsist > 0 && $conAsistente === 0) {
            $veredicto = '<div class="err-box"><b>Ojo: las claves de los asistentes ni siquiera vienen en el JSON.</b><br>'
                . 'Que no aparezcan (en vez de venir en null) deja abierta la posibilidad de que la terna viaje en otra '
                . 'rama o en otro endpoint. Mirá abajo las claves del partido y el crudo antes de dar por cerrado que '
                . 'el dato no existe.</div>';
        } else {
            $veredicto = '<div class="ok-box">Sondeo terminado. Mirá la tabla.</div>';
        }

        $cuerpo .= $veredicto;

        // ── Resumen por clave ───────────────────────────────────────────────
        $cuerpo .= '<h2>Qué manda Transfermarkt, clave por clave</h2>'
            . '<p class="sub">Sobre ' . $sondeados . ' partido(s) sondeados con éxito'
            . ($fallaron ? ' (' . $fallaron . ' no respondieron)' : '') . '.</p>'
            . '<div class="scroll"><table><thead><tr><th>Clave de Transfermarkt</th><th>Rol</th>'
            . '<th>Con dato</th><th>En null</th><th>Clave ausente</th></tr></thead><tbody>';
        foreach (self::$claves as $clave => $rol) {
            $c = $conteo[$clave];
            $cuerpo .= '<tr>'
                . '<td><code>' . e($clave) . '</code></td>'
                . '<td>' . e($rol) . '</td>'
                . '<td class="num">' . ($c['valor'] ? '<b class="ok">' . $c['valor'] . '</b>' : '0') . '</td>'
                . '<td class="num">' . ($c['null'] ? '<b class="warn">' . $c['null'] . '</b>' : '0') . '</td>'
                . '<td class="num gris">' . $c['ausente'] . '</td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        if (!empty($otrasClaves)) {
            $cuerpo .= '<div class="err-box" style="margin-top:14px"><b>Claves de <code>refereeIds</code> que no conocemos:</b> '
                . e(implode(', ', array_keys($otrasClaves))) . '. Estas hay que mapearlas.</div>';
        }
        if (!empty($ramasRaras)) {
            $cuerpo .= '<div class="err-box" style="margin-top:14px"><b>Ramas del partido que podrían traer árbitros y no miramos:</b> '
                . e(implode(', ', array_keys($ramasRaras))) . '.</div>';
        }

        // ── El detalle ──────────────────────────────────────────────────────
        $cuerpo .= '<h2>Partido por partido</h2><div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Partido</th><th>gameId</th>';
        foreach (self::$claves as $rol) $cuerpo .= '<th>' . e($rol) . '</th>';
        $cuerpo .= '</tr></thead><tbody>' . $detalle . '</tbody></table></div>';

        if ($primerCrudo) {
            $cuerpo .= '<h2>El crudo del primero</h2>'
                . '<p class="sub">' . e($primerCrudo['partido']) . ' · gameId ' . e($primerCrudo['gameId']) . '</p>'
                . '<pre>' . e(json_encode($primerCrudo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        return $this->pagina('Sondeo de terna', $cuerpo);
    }

    /**
     * Los partidos a sondear: importados (tienen gameId), con alineación
     * cargada, y por defecto sólo los que hoy tienen la terna incompleta —
     * que son exactamente los que lista el control.
     */
    private function partidos($n, $torneoId, $soloSin)
    {
        $existe = function ($rol) {
            return "EXISTS (SELECT 1 FROM partido_arbitros pa
                            WHERE pa.partido_id = partidos.id AND pa.tipo = '" . $rol . "')";
        };

        $q = DB::table('partidos')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->join('import_partidos as ip', 'ip.partido_id', '=', 'partidos.id')
            ->whereNotNull('ip.external_id')->where('ip.external_id', '!=', '')
            ->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id');
            })
            ->select('partidos.id', 'partidos.dia', 'ip.external_id',
                DB::raw("CONCAT(el.nombre, ' vs ', ev.nombre) as partido"))
            ->groupBy('partidos.id', 'partidos.dia', 'ip.external_id', 'el.nombre', 'ev.nombre');

        if ($torneoId) $q->where('grupo.torneo_id', $torneoId);

        if ($soloSin) {
            $q->where(function ($w) use ($existe) {
                $w->whereRaw('NOT ' . $existe('Linea 1'))
                  ->orWhereRaw('NOT ' . $existe('Linea 2'));
            });
        }

        return $q->orderByDesc('partidos.dia')->limit($n)->get()->all();
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
            th,td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap}
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
