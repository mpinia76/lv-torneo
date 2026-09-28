<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\HttpHelper;
use App\Services\Controles;
use App\Incidencia;

/**
 * La terna arbitral de Transfermarkt: qué manda, qué no, y qué hacer con los
 * cientos de partidos que quedan con la terna incompleta.
 *
 * Dos pantallas:
 *
 *   index()   - sondeo fino. Compara rol por rol lo que trae `/game/{gameId}`
 *               contra `partido_arbitros`. Sirve para entender un caso.
 *   barrido() - el recorrido en serio. Va torneo por torneo: sondea una muestra
 *               y, si en esa muestra no se pierde nada, marca la incidencia en
 *               TODOS los que quedan de ese torneo sin gastar una llamada más.
 *
 * Por qué muestra y no uno por uno: verificar los ~578 partidos cuesta una
 * llamada cada uno. El comportamiento de TM no es por partido sino por
 * competencia y temporada —o la cargaron o no la cargaron—, así que 15 partidos
 * alcanzan para saber cómo viene ese torneo. Si la muestra ensucia, ese torneo
 * NO se marca: se avisa y se revisa aparte.
 *
 * Nada de esto borra datos. Lo único que escribe son incidencias, con el mismo
 * texto y la misma forma que el botón "Terna incompleta en TM" de Controles.
 */
class TernaSondeoController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Cuántos partidos se sondean por torneo antes de decidir. */
    const MUESTRA = 15;

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

    private $controles;

    public function __construct(Controles $controles)
    {
        $this->controles = $controles;
    }

    // ═══════════════════════════ BARRIDO ═══════════════════════════

    public function barrido(Request $request)
    {
        set_time_limit(0);

        $muestra = max(5, min(40, (int) $request->get('muestra', self::MUESTRA)));
        $torneoId = (int) $request->get('torneo_id', 0);

        // ── Marcar un torneo entero y saltar al siguiente ────────────────────
        if ((string) $request->get('accion') === 'marcar' && $torneoId) {
            $n = $this->marcarTorneo($torneoId);
            $this->controles->invalidarConteo('arbitros.terna');

            $acumulado = (int) $request->session()->get('terna_marcados', 0) + $n;
            $torneos   = (int) $request->session()->get('terna_torneos', 0) + 1;
            $request->session()->put('terna_marcados', $acumulado);
            $request->session()->put('terna_torneos', $torneos);

            $proximo = $this->proximoTorneo();
            return redirect()->route('import_detalles.terna_barrido',
                array_filter(['torneo_id' => $proximo, 'muestra' => $muestra]));
        }

        $pendientes = $this->torneosPendientes();
        $marcados   = (int) $request->session()->get('terna_marcados', 0);
        $torneosOk  = (int) $request->session()->get('terna_torneos', 0);

        $cuerpo = '<p class="sub"><a href="' . e(url('/admin/controles?check=arbitros.terna')) . '">← Controles · Terna incompleta</a>'
            . ' · <a href="' . e(route('import_detalles.terna_sondeo')) . '">Sondeo fino</a></p>'
            . '<h1>Barrido de la terna incompleta</h1>';

        if ($marcados) {
            $cuerpo .= '<div class="ok-box">En esta sesión llevás <b>' . $marcados . '</b> partido(s) marcados '
                . 'en ' . $torneosOk . ' torneo(s). '
                . '<a href="' . e(route('import_detalles.terna_barrido', ['reset' => 1])) . '">Empezar de nuevo el conteo</a></div>';
        }
        if ($request->filled('reset')) {
            $request->session()->forget(['terna_marcados', 'terna_torneos']);
            return redirect()->route('import_detalles.terna_barrido');
        }

        $total = 0;
        foreach ($pendientes as $p) $total += (int) $p->n;

        if (empty($pendientes)) {
            return $this->pagina('Barrido de terna', $cuerpo
                . '<div class="ok-box"><b>No queda ningún partido con la terna incompleta.</b></div>');
        }

        $cuerpo .= '<p class="sub">Quedan <b>' . $total . '</b> partido(s) en <b>' . count($pendientes) . '</b> torneo(s). '
            . 'El barrido sondea ' . $muestra . ' por torneo (una llamada cada uno) y, si en esa muestra no se pierde '
            . 'nada, marca el resto del torneo sin gastar más llamadas.</p>';

        // ── Sondeo de un torneo ─────────────────────────────────────────────
        if ($torneoId) {
            return $this->pagina('Barrido de terna',
                $cuerpo . $this->sondearTorneo($torneoId, $muestra, $pendientes));
        }

        // ── Lista de torneos pendientes ─────────────────────────────────────
        $cuerpo .= '<p class="acciones"><a class="boton" href="'
            . e(route('import_detalles.terna_barrido', ['torneo_id' => $pendientes[0]->torneo_id, 'muestra' => $muestra]))
            . '">Empezar por ' . e($pendientes[0]->torneo) . ' →</a></p>';

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Año</th><th>Torneo</th><th>Sin terna</th><th></th></tr></thead><tbody>';
        foreach ($pendientes as $p) {
            $cuerpo .= '<tr><td class="num">' . e((string) $p->year) . '</td>'
                . '<td>' . e($p->torneo) . '</td>'
                . '<td class="num"><b>' . (int) $p->n . '</b></td>'
                . '<td><a href="' . e(route('import_detalles.terna_barrido',
                    ['torneo_id' => $p->torneo_id, 'muestra' => $muestra])) . '">Sondear</a></td></tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        return $this->pagina('Barrido de terna', $cuerpo);
    }

    /** Sondea la muestra de un torneo y decide si se puede marcar el resto. */
    private function sondearTorneo($torneoId, $muestra, array $pendientes)
    {
        $nombre = 'torneo #' . $torneoId;
        $quedan = 0;
        foreach ($pendientes as $p) {
            if ((int) $p->torneo_id === $torneoId) { $nombre = $p->torneo . ' (' . $p->year . ')'; $quedan = (int) $p->n; }
        }

        $filas   = $this->partidos($muestra, true, $torneoId);
        $enBase  = $this->arbitrosEnBase(array_map(function ($f) { return (int) $f->id; }, $filas));
        $mapaTm  = $this->mapaArbitroTm();

        $html = '<h2>' . e($nombre) . ' <span class="sub">· ' . $quedan . ' sin terna</span></h2>';

        if (empty($filas)) {
            return $html . '<div class="ok-box">Este torneo ya no tiene partidos pendientes.</div>';
        }

        $faltas = []; $sondeados = 0; $fallaron = 0; $detalle = '';

        foreach ($filas as $f) {
            $json = HttpHelper::getJson(self::TMAPI . '/game/' . rawurlencode((string) $f->external_id));
            if (!is_array($json) || empty($json)) { $fallaron++; continue; }
            $sondeados++;

            $game = isset($json['data']) ? $json['data'] : $json;
            $ids  = isset($game['refereeIds']) && is_array($game['refereeIds']) ? $game['refereeIds'] : [];
            $base = isset($enBase[(int) $f->id]) ? $enBase[(int) $f->id] : [];

            foreach ($this->comparar($ids, $base, $mapaTm) as $falta) {
                $falta['partido'] = $f->partido;
                $falta['partido_id'] = (int) $f->id;
                $faltas[] = $falta;
            }

            $detalle .= '<tr><td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                . '<td>' . e($f->partido) . '</td>'
                . '<td>' . $this->resumenRoles($ids, $base) . '</td></tr>';
        }

        if ($sondeados === 0) {
            return $html . '<div class="err-box">La API no respondió en ninguno de los ' . count($filas)
                . ' partidos. Probá de nuevo más tarde.</div>';
        }

        // ── Veredicto del torneo ────────────────────────────────────────────
        if (empty($faltas)) {
            $html .= '<div class="ok-box"><b>Muestra limpia.</b> En los ' . $sondeados . ' partidos sondeados no hay '
                . 'ni un rol donde Transfermarkt traiga un árbitro y la base esté vacía. Lo que falta, TM no lo tiene.</div>'
                . '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.terna_barrido', ['accion' => 'marcar', 'torneo_id' => $torneoId, 'muestra' => $muestra]))
                . '">Marcar los ' . $quedan . ' de este torneo y seguir con el próximo →</a></p>'
                . '<p class="sub">Escribe una incidencia por partido, igual que el botón "Terna incompleta en TM". '
                . 'Esos partidos salen de todos los controles. No gasta ninguna llamada.</p>';
        } else {
            $html .= '<div class="err-box"><b>Acá sí se está perdiendo algo: ' . count($faltas) . ' árbitro(s) '
                . 'en ' . $sondeados . ' partidos sondeados.</b><br>'
                . 'Este torneo NO se marca. Revisalo antes de taparlo.</div>'
                . '<div class="scroll" style="max-height:300px"><table><thead><tr><th>Partido</th><th>Rol</th>'
                . '<th>TM</th><th>Por qué no entró</th></tr></thead><tbody>';
            foreach ($faltas as $x) {
                $html .= '<tr><td>' . e($x['partido']) . ' <span class="id">#' . $x['partido_id'] . '</span></td>'
                    . '<td>' . e($x['rol']) . '</td>'
                    . '<td class="num">' . e($x['tm']) . '</td>'
                    . '<td class="err">' . e($x['motivo']) . '</td></tr>';
            }
            $html .= '</tbody></table></div>'
                . '<p class="acciones"><a class="boton-sec" href="'
                . e(route('import_detalles.terna_barrido', ['muestra' => $muestra])) . '">Volver a la lista y seguir con otro</a></p>';
        }

        $html .= '<h3>La muestra</h3><p class="sub">' . $sondeados . ' sondeados'
            . ($fallaron ? ', ' . $fallaron . ' sin respuesta' : '') . '.</p>'
            . '<div class="scroll" style="max-height:340px"><table><thead><tr><th>Fecha</th><th>Partido</th>'
            . '<th>Roles: TM vs base</th></tr></thead><tbody>' . $detalle . '</tbody></table></div>';

        return $html;
    }

    /**
     * Los roles donde TM trae un árbitro y la base no tiene nada.
     *
     * Es lo ÚNICO que cuenta como pérdida. Que TM mande `null` y la base esté
     * vacía no es un problema nuestro, y que la base tenga algo que TM no trae
     * tampoco: vino de otra fuente.
     */
    private function comparar(array $ids, array $base, array $mapaTm)
    {
        $out = [];
        foreach (self::$claves as $clave => $rol) {
            $tm = isset($ids[$clave]) && $ids[$clave] !== null && $ids[$clave] !== ''
                && $ids[$clave] !== 0 && $ids[$clave] !== '0' ? (string) $ids[$clave] : null;
            if ($tm === null || isset($base[$rol])) continue;

            $esperado = isset($mapaTm[$tm]) ? (int) $mapaTm[$tm] : null;
            $motivo   = 'mapeado y libre: habría que poder cargarlo';

            if ($esperado === null) {
                $motivo = 'el árbitro no está en arbitro_tm';
            } else {
                foreach ($base as $rolBase => $aid) {
                    if ((int) $aid === $esperado) {
                        $motivo = 'ese árbitro ya está en el partido como ' . $rolBase;
                        break;
                    }
                }
            }
            $out[] = ['rol' => $rol, 'tm' => $tm, 'motivo' => $motivo];
        }
        return $out;
    }

    /** Una línea compacta con qué rol tiene cada lado. */
    private function resumenRoles(array $ids, array $base)
    {
        $partes = [];
        foreach (self::$claves as $clave => $rol) {
            $tm = isset($ids[$clave]) && $ids[$clave] !== null && $ids[$clave] !== '' ? 'TM' : '';
            $db = isset($base[$rol]) ? 'base' : '';
            if ($tm === '' && $db === '') continue;
            $clase = ($tm && $db) ? 'ok' : ($tm ? 'err' : 'gris');
            $partes[] = '<span class="' . $clase . '">' . e($rol) . ($tm && !$db ? ' ← sólo TM' : '') . '</span>';
        }
        return empty($partes) ? '<span class="gris">nadie tiene nada</span>' : implode(' · ', $partes);
    }

    /** Escribe la incidencia en todos los pendientes del torneo. Devuelve cuántas. */
    private function marcarTorneo($torneoId)
    {
        $motivo = $this->controles->motivoSinDatos('arbitros.terna');
        $filas  = $this->partidos(5000, true, $torneoId);
        $n = 0;

        foreach ($filas as $f) {
            // `partidos()` ya excluye los que tienen incidencia, pero lo
            // re-chequeamos: entre la consulta y el insert puede haber pasado
            // otra cosa, y duplicar incidencias ensucia el detalle público.
            if (Incidencia::where('partido_id', (int) $f->id)->exists()) continue;

            Incidencia::create([
                'partido_id'    => (int) $f->id,
                'torneo_id'     => (int) $f->torneo_id,
                'equipo_id'     => null,
                'puntos'        => null,
                'observaciones' => $motivo['texto'],
            ]);
            $n++;
        }
        return $n;
    }

    private function proximoTorneo()
    {
        $p = $this->torneosPendientes();
        return empty($p) ? null : (int) $p[0]->torneo_id;
    }

    /** Torneos que todavía tienen partidos con la terna incompleta. */
    private function torneosPendientes()
    {
        $existe = function ($rol) {
            return "EXISTS (SELECT 1 FROM partido_arbitros pa
                            WHERE pa.partido_id = partidos.id AND pa.tipo = '" . $rol . "')";
        };

        return DB::table('partidos')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->join('import_partidos as ip', 'ip.partido_id', '=', 'partidos.id')
            ->whereNotNull('ip.external_id')->where('ip.external_id', '!=', '')
            ->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id');
            })
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('incidencias')
                    ->whereColumn('incidencias.partido_id', 'partidos.id');
            })
            ->where(function ($w) use ($existe) {
                $w->whereRaw('NOT ' . $existe('Linea 1'))
                  ->orWhereRaw('NOT ' . $existe('Linea 2'));
            })
            ->select('torneo.id as torneo_id', 'torneo.nombre as torneo', 'torneo.year as year',
                DB::raw('COUNT(DISTINCT partidos.id) as n'))
            ->groupBy('torneo.id', 'torneo.nombre', 'torneo.year')
            ->orderByDesc(DB::raw('COUNT(DISTINCT partidos.id)'))
            ->get()->all();
    }

    // ═══════════════════════════ SONDEO FINO ═══════════════════════════

    public function index(Request $request)
    {
        set_time_limit(0);

        $n        = max(1, min(60, (int) $request->get('n', 20)));
        $soloSin  = (string) $request->get('solo_sin_terna', '1') !== '0';
        $torneoId = (int) $request->get('torneo_id', 0);

        $filas = $this->partidos($n, $soloSin, $torneoId);

        $cuerpo = '<p class="sub"><a href="' . e(url('/admin/controles?check=arbitros.terna')) . '">← Controles · Terna incompleta</a>'
            . ' · <a href="' . e(route('import_detalles.terna_barrido')) . '">Barrido por torneo</a></p>'
            . '<h1>Sondeo de la terna en Transfermarkt</h1>'
            . '<p class="sub">Compara, rol por rol, lo que manda <code>/game/{gameId}</code> contra '
            . '<code>partido_arbitros</code>. Una llamada por partido. No escribe nada.</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<label>Partidos <input type="number" name="n" value="' . $n . '" min="1" max="60" size="4"></label> '
            . '<label><input type="checkbox" name="solo_sin_terna" value="1"' . ($soloSin ? ' checked' : '')
            . '> sólo los que hoy tienen la terna incompleta</label> '
            . ($torneoId ? '<input type="hidden" name="torneo_id" value="' . $torneoId . '"> ' : '')
            . '<button>Sondear</button></form>';

        if (empty($filas)) {
            return $this->pagina('Sondeo de terna',
                $cuerpo . '<div class="ok-box">No encontré partidos importados que cumplan el filtro.</div>');
        }

        $enBase  = $this->arbitrosEnBase(array_map(function ($f) { return (int) $f->id; }, $filas));
        $mapaTm  = $this->mapaArbitroTm();
        $nombres = $this->nombresArbitros();

        $falta = []; $ok = []; $distinto = []; $soloBase = []; $sinDato = [];
        foreach (self::$claves as $rol) { $falta[$rol] = 0; $ok[$rol] = 0; $distinto[$rol] = 0; $soloBase[$rol] = 0; $sinDato[$rol] = 0; }

        $faltaSinMapear = 0; $faltaDuplicado = 0;
        $descartados = []; $otrasClaves = []; $detalle = ''; $primerCrudo = null;
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
                $tm = isset($ids[$clave]) && $ids[$clave] !== null && $ids[$clave] !== ''
                    && $ids[$clave] !== 0 && $ids[$clave] !== '0' ? (string) $ids[$clave] : null;
                $db = isset($base[$rol]) ? (int) $base[$rol] : null;

                if ($tm === null && $db === null) {
                    $sinDato[$rol]++;
                    $celdas .= '<td class="gris">—</td>';
                } elseif ($tm === null && $db !== null) {
                    $soloBase[$rol]++;
                    $celdas .= '<td class="gris">sólo base<br><span class="id">' . e($this->nombre($nombres, $db)) . '</span></td>';
                } elseif ($tm !== null && $db === null) {
                    $falta[$rol]++;
                    $esperado = isset($mapaTm[$tm]) ? (int) $mapaTm[$tm] : null;
                    $yaEn = null;
                    if ($esperado !== null) {
                        foreach ($base as $rolBase => $aid) {
                            if ((int) $aid === $esperado) { $yaEn = $rolBase; break; }
                        }
                    }
                    if ($esperado === null) { $faltaSinMapear++; $nota = ' · <b>sin mapear</b>'; }
                    elseif ($yaEn !== null) { $faltaDuplicado++; $nota = ' · <b>ya está como ' . e($yaEn) . '</b>'; }
                    else { $nota = ' · mapeado y libre'; }
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

        if ($sondeados === 0) {
            $veredicto = '<div class="err-box">No se pudo sondear ningún partido: la API no respondió.</div>';
        } elseif ($totalFalta === 0) {
            $veredicto = '<div class="ok-box"><b>No se está perdiendo nada.</b><br>'
                . 'En los ' . $sondeados . ' partidos sondeados no hay ni un rol donde Transfermarkt traiga un árbitro '
                . 'y la base esté vacía. Lo que falta, TM no lo tiene. '
                . '<a href="' . e(route('import_detalles.terna_barrido')) . '">Barrer y marcar por torneo →</a></div>';
        } else {
            $veredicto = '<div class="err-box"><b>Se están perdiendo ' . $totalFalta . ' árbitro(s) en '
                . $sondeados . ' partidos.</b>'
                . ($faltaSinMapear ? '<br>· <b>' . $faltaSinMapear . '</b> con el árbitro sin mapear en <code>arbitro_tm</code>.' : '')
                . ($faltaDuplicado ? '<br>· <b>' . $faltaDuplicado . '</b> donde ese árbitro ya está en el partido con otro rol: '
                    . 'la fila la rechaza la base, que no admite dos veces al mismo juez.' : '')
                . '</div>';
        }
        $cuerpo .= $veredicto;

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
            $cuerpo .= '<p class="sub">TM además manda roles que no existen en tu enum y se descartan a propósito: '
                . e(implode(' · ', $partes)) . '.</p>';
        }
        if (!empty($otrasClaves)) {
            $cuerpo .= '<div class="err-box"><b>Claves de <code>refereeIds</code> sin mapear:</b> '
                . e(implode(', ', array_keys($otrasClaves))) . '</div>';
        }

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

    private function partidos($n, $soloSin, $torneoId = 0)
    {
        $existe = function ($rol) {
            return "EXISTS (SELECT 1 FROM partido_arbitros pa
                            WHERE pa.partido_id = partidos.id AND pa.tipo = '" . $rol . "')";
        };

        $q = DB::table('partidos')
            ->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('import_partidos as ip', 'ip.partido_id', '=', 'partidos.id')
            ->whereNotNull('ip.external_id')->where('ip.external_id', '!=', '')
            ->whereExists(function ($s) {
                $s->select(DB::raw(1))->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id');
            })
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('incidencias')
                    ->whereColumn('incidencias.partido_id', 'partidos.id');
            })
            ->select('partidos.id', 'partidos.dia', 'grupo.torneo_id',
                DB::raw('MAX(ip.external_id) as external_id'),
                DB::raw("CONCAT(el.nombre, ' vs ', ev.nombre) as partido"))
            ->groupBy('partidos.id', 'partidos.dia', 'grupo.torneo_id', 'el.nombre', 'ev.nombre');

        if ($torneoId) $q->where('grupo.torneo_id', $torneoId);

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

    private function mapaArbitroTm()
    {
        $out = [];
        foreach (DB::table('arbitro_tm')->select('tm_referee_id', 'arbitro_id')->get() as $r) {
            $out[(string) $r->tm_referee_id] = (int) $r->arbitro_id;
        }
        return $out;
    }

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
            h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:28px 0 8px} h3{font-size:14px;margin:22px 0 6px}
            .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
            a{color:#15714e}
            .acciones{margin:14px 0}
            a.boton{display:inline-block;background:#15714e;color:#fff;padding:7px 14px;text-decoration:none;font-weight:600}
            a.boton:hover{background:#0f5a3d}
            a.boton-sec{display:inline-block;padding:5px 12px;border:1px solid #c7cec7;background:#eef1ec;color:#15714e;text-decoration:none;font-size:12px}
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
