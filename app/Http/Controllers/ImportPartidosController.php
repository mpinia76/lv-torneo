<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\HttpHelper;
use App\Services\NivelCompetencia;

/**
 * Motor de carga de partidos, DT por DT.
 *
 *   index()    -> lista de DTs con URL de Transfermarkt y su estado
 *   sondear()  -> baja los partidos del DT, los clasifica y (opcional) los guarda en staging
 *   aplicar()  -> crea de verdad los partidos nuevos: torneo -> grupo -> fecha -> partido -> partido_tecnico
 *
 * No toca nada de lo que ya funciona. Los clubes se resuelven por clubId de
 * Transfermarkt (tabla equipo_tm), nunca por nombre.
 */
class ImportPartidosController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    // ═══════════════════════════════ ÍNDICE ═══════════════════════════════

    public function index(Request $request)
    {
        $q      = trim((string) $request->get('q', ''));
        $filtro = trim((string) $request->get('estado', ''));
        $limite = max(50, min(2000, (int) $request->get('limite', 300)));

        // TODOS los DTs, tengan o no el slug de Transfermarkt. Los que no lo
        // tienen son justamente los que hay que descubrir: sin slug no hay sondeo.
        $tecnicos = \App\Tecnico::with('persona')->get()
            ->map(function ($t) {
                return (object) [
                    'id'     => $t->id,
                    'nombre' => optional($t->persona)->name ?: ('DT #' . $t->id),
                    'url'    => trim((string) $t->transfermarkt_url),
                ];
            })
            ->sortBy('nombre')
            ->values();

        // Contadores del staging, una consulta para todos.
        $stats = [];
        foreach (DB::table('import_partidos')
                     ->select('tecnico_id', 'estado', DB::raw('COUNT(*) AS n'))
                     ->groupBy('tecnico_id', 'estado')->get() as $r) {
            $stats[(int) $r->tecnico_id][$r->estado] = (int) $r->n;
        }

        // Cuántos de los aplicados ya tienen alineación cargada.
        $conDetalle = [];
        foreach (DB::table('import_partidos')
                     ->whereNotNull('partido_id')->where('estado', 'aplicado')
                     ->whereIn('partido_id', function ($sub) {
                         $sub->from('alineacions')->select('partido_id')->distinct();
                     })
                     ->select('tecnico_id', DB::raw('COUNT(DISTINCT partido_id) AS n'))
                     ->groupBy('tecnico_id')->get() as $r) {
            $conDetalle[(int) $r->tecnico_id] = (int) $r->n;
        }

        // El sondeo que NO deja rastro en staging. Un DT cuyos partidos son todos
        // de Proyección/juveniles guarda 0 filas: sin este registro la lista lo
        // muestra "sin sondear" para siempre y se le vuelve a gastar una llamada
        // a la API cada vez. La tabla puede no existir todavía: se degrada sola.
        $sondeos = [];
        if (Schema::hasTable('tecnico_sondeos')) {
            foreach (DB::table('tecnico_sondeos')->get() as $r) $sondeos[(int) $r->tecnico_id] = $r;
        }

        // ── Estado de cada DT ───────────────────────────────────────────────
        $c = ['total' => 0, 'sin_url' => 0, 'sin_sondear' => 0, 'sondeados' => 0,
            'pendientes' => 0, 'conflictos' => 0, 'sin_detalle' => 0, 'listos' => 0,
            'partidos' => 0, 'detalle' => 0];
        $todos = [];

        foreach ($tecnicos as $t) {
            $s = isset($stats[$t->id]) ? $stats[$t->id] : [];
            $nuevo     = isset($s['nuevo'])     ? $s['nuevo']     : 0;
            $conflicto = isset($s['conflicto']) ? $s['conflicto'] : 0;
            $aplicado  = isset($s['aplicado'])  ? $s['aplicado']  : 0;
            $duplicado = isset($s['duplicado']) ? $s['duplicado'] : 0;
            $detalle   = isset($conDetalle[$t->id]) ? $conDetalle[$t->id] : 0;

            $sinUrl   = ($t->url === '');
            // Cualquier fila en staging cuenta, incluidas las 'excluido'
            // (pre-2000): esas no son ninguno de los cuatro estados de arriba y
            // hacían que un DT ya sondeado figurara como sin sondear.
            $enStaging = array_sum($s) > 0;
            $sd = isset($sondeos[$t->id]) ? $sondeos[$t->id] : null;
            $sondeado = $enStaging || $sd !== null;

            $c['total']++;
            $c['partidos'] += $aplicado;
            $c['detalle']  += $detalle;
            if ($sinUrl)                 $c['sin_url']++;
            elseif (!$sondeado)          $c['sin_sondear']++;
            if ($sondeado)               $c['sondeados']++;
            if ($nuevo)                  $c['pendientes']++;
            if ($conflicto)              $c['conflictos']++;
            if ($aplicado > $detalle)    $c['sin_detalle']++;
            if ($sondeado && !$nuevo && !$conflicto) $c['listos']++;

            $todos[] = (object) compact('t', 'nuevo', 'conflicto', 'aplicado',
                'duplicado', 'detalle', 'sinUrl', 'sondeado', 'enStaging', 'sd');
        }

        // ── Filtros ─────────────────────────────────────────────────────────
        $pasa = function ($f) use ($filtro) {
            switch ($filtro) {
                case 'sin_url':     return $f->sinUrl;
                case 'sin_sondear': return !$f->sinUrl && !$f->sondeado;
                case 'pendientes':  return $f->nuevo > 0;
                case 'conflictos':  return $f->conflicto > 0;
                case 'sin_detalle': return $f->aplicado > $f->detalle;
                case 'listos':      return $f->sondeado && !$f->nuevo && !$f->conflicto;
                default:            return true;
            }
        };

        $visibles = [];
        foreach ($todos as $f) {
            if (!$pasa($f)) continue;
            if ($q !== '' && mb_stripos($f->t->nombre, $q) === false) continue;
            $visibles[] = $f;
        }

        // ── Pantalla ────────────────────────────────────────────────────────
        $html = '<h1>Carga de partidos · DT por DT</h1>'
            . '<p class="sub">Todos los DTs de la base y en qué punto está cada uno. Sin el slug de Transfermarkt '
            . 'no se puede sondear: esos son los primeros a resolver.</p>'
            . '<p class="acciones"><a class="boton-sec" href="' . e(route('import_partidos.fixture')) . '">'
            . 'Fixture por competencia (torneos en curso)</a>'
            . '<a class="boton-sec" href="' . e(route('import_detalles.index')) . '">'
            . 'Detalle de los partidos (alineaciones, goles, tarjetas, cambios)</a></p>';

        $html .= '<div class="cards">'
            . $this->card($c['total'], 'DTs en la base')
            . $this->card($c['sin_url'], 'sin slug de TM', $c['sin_url'] ? 'err' : 'ok')
            . $this->card($c['sin_sondear'], 'con slug, sin sondear', $c['sin_sondear'] ? 'warn' : 'ok')
            . $this->card($c['sondeados'], 'sondeados', 'ok')
            . $this->card($c['pendientes'], 'con nuevos por aplicar', $c['pendientes'] ? 'warn' : '')
            . $this->card($c['conflictos'], 'con conflictos', $c['conflictos'] ? 'err' : '')
            . $this->card($c['partidos'], 'partidos aplicados', 'ok')
            . $this->card($c['detalle'], 'con detalle', 'ok')
            . '</div>';

        // Solapas de filtro
        $solapas = [
            ''            => 'Todos (' . $c['total'] . ')',
            'sin_url'     => 'Sin slug (' . $c['sin_url'] . ')',
            'sin_sondear' => 'Sin sondear (' . $c['sin_sondear'] . ')',
            'pendientes'  => 'Por aplicar (' . $c['pendientes'] . ')',
            'conflictos'  => 'Con conflictos (' . $c['conflictos'] . ')',
            'sin_detalle' => 'Sin detalle (' . $c['sin_detalle'] . ')',
            'listos'      => 'Listos (' . $c['listos'] . ')',
        ];
        $html .= '<p class="acciones">';
        foreach ($solapas as $clave => $texto) {
            $params = array_filter(['estado' => $clave ?: null, 'q' => $q ?: null]);
            $html .= '<a class="' . ($filtro === $clave ? 'boton' : 'boton-sec') . '" href="'
                . e(route('import_partidos.index', $params)) . '">' . e($texto) . '</a> ';
        }
        $html .= '</p>';

        $html .= '<form method="get" style="margin:12px 0">'
            . '<input type="hidden" name="estado" value="' . e($filtro) . '">'
            . '<input name="q" value="' . e($q) . '" placeholder="buscar DT…" size="30"> <button>Buscar</button>'
            . ($q !== '' ? ' <a href="' . e(route('import_partidos.index', array_filter(['estado' => $filtro ?: null]))) . '">limpiar</a>' : '')
            . '</form>';

        if (empty($visibles)) {
            return $this->pagina('Carga de partidos', $html . '<div class="ok-box">No hay ningún DT en este filtro.</div>');
        }

        $filas = ''; $n = 0;
        foreach ($visibles as $f) {
            if ($n++ >= $limite) break;
            $t = $f->t;

            if ($f->sinUrl) {
                $estado = '<span class="err">sin slug</span>';
                $acciones = '<a href="' . e(route('tecnico-estadisticas.createPorTecnico', $t->id)) . '" target="_blank">Cargar URL ▸</a>';
            } else {
                $cuando = ($f->sd && $f->sd->sondeado_at)
                    ? ' title="Último sondeo: ' . e(substr((string) $f->sd->sondeado_at, 0, 16)) . '"' : '';

                if (!$f->sondeado)          $estado = '<span class="warn">sin sondear</span>';
                elseif (!$f->enStaging && $f->sd)
                                            $estado = '<span class="gris"' . $cuando . '>'
                                                . (((int) $f->sd->partidos === 0)
                                                    ? 'sondeado · TM no le da partidos'
                                                    : 'sondeado · nada de 1ra (' . (int) $f->sd->fuera_1ra . ' afuera)')
                                                . '</span>';
                elseif ($f->conflicto)      $estado = '<span class="err">' . $f->conflicto . ' conflicto(s)</span>';
                elseif ($f->nuevo)          $estado = '<span class="warn">' . $f->nuevo . ' por aplicar</span>';
                elseif ($f->aplicado > $f->detalle) $estado = '<span class="warn">falta detalle</span>';
                else                        $estado = '<span class="ok">listo</span>';

                $acciones = '<a href="' . e(route('import_partidos.sondear',
                        ['tecnico_id' => $t->id, 'aprender' => 1, 'guardar' => 1])) . '">Sondear</a>'
                    . ($f->nuevo ? ' · <a href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $t->id]))
                        . '"><b>Aplicar ' . $f->nuevo . '</b></a>' : '')
                    . ($f->aplicado ? ' · <a href="' . e(route('import_detalles.index', ['tecnico_id' => $t->id]))
                        . '">Detalle</a>' : '');
            }

            $filas .= '<tr>'
                . '<td>' . e($t->nombre) . ' <span class="id">#' . (int) $t->id . '</span></td>'
                . '<td>' . $estado . '</td>'
                . '<td class="num">' . ($f->sondeado ? $f->duplicado : '—') . '</td>'
                . '<td class="num">' . ($f->nuevo ? '<b class="warn">' . $f->nuevo . '</b>' : ($f->sondeado ? '0' : '—')) . '</td>'
                . '<td class="num">' . ($f->conflicto ? '<b class="err">' . $f->conflicto . '</b>' : ($f->sondeado ? '0' : '—')) . '</td>'
                . '<td class="num">' . ($f->aplicado ? '<b class="ok">' . $f->aplicado . '</b>' : ($f->sondeado ? '0' : '—')) . '</td>'
                . '<td class="num">' . ($f->aplicado
                    ? ($f->detalle . '/' . $f->aplicado) : '—') . '</td>'
                . '<td>' . $acciones . '</td></tr>';
        }

        $html .= '<div class="scroll"><table><thead><tr><th>DT</th><th>Estado</th><th>Ya cargados</th>'
            . '<th>Nuevos</th><th>Conflictos</th><th>Aplicados</th><th>Con detalle</th><th></th>'
            . '</tr></thead><tbody>' . $filas . '</tbody></table></div>';

        if (count($visibles) > $limite) {
            $params = array_filter(['estado' => $filtro ?: null, 'q' => $q ?: null, 'limite' => $limite + 500]);
            $html .= '<p class="sub">Se muestran ' . $limite . ' de ' . count($visibles) . '. '
                . '<a href="' . e(route('import_partidos.index', $params)) . '">Mostrar 500 más</a></p>';
        }

        return $this->pagina('Carga de partidos', $html);
    }

    /**
     * Deja constancia del último sondeo de un DT en `tecnico_sondeos`.
     *
     * Hace falta porque el staging no alcanza como registro: un DT de Reserva o
     * de juveniles guarda CERO filas —sus competencias quedan fuera de 1ra a
     * propósito—, y la lista de DTs deducía "sondeado" de que hubiera filas. El
     * resultado era un DT que decía "sin sondear" para siempre y al que se le
     * gastaba una llamada a la API cada vez que se lo intentaba.
     *
     * Si la tabla todavía no está creada, no pasa nada: se sigue como antes.
     */
    private function registrarSondeo($tecnicoId, array $datos)
    {
        if (!Schema::hasTable('tecnico_sondeos')) return;

        $fila = $datos + ['sondeado_at' => now(), 'updated_at' => now()];

        $afectadas = DB::table('tecnico_sondeos')->where('tecnico_id', (int) $tecnicoId)->update($fila);
        if (!$afectadas && !DB::table('tecnico_sondeos')->where('tecnico_id', (int) $tecnicoId)->exists()) {
            DB::table('tecnico_sondeos')->insert(
                $fila + ['tecnico_id' => (int) $tecnicoId, 'created_at' => now()]
            );
        }
    }

    // ═══════════════════ FIXTURE POR COMPETENCIA ═══════════════════
    //
    // Para un torneo EN CURSO el motor DT por DT no sirve: junta la carrera de
    // un técnico, no la fecha de un campeonato. Acá se baja el fixture entero de
    // la competencia con UNA llamada a /competition/{id}/fixtures.
    //
    // Ventajas sobre el JSON del DT:
    //   · `homeClub` / `awayClub` vienen explícitos → no hay que deducir localía.
    //   · `isTimeDefined` dice si el partido ya tiene día y hora confirmados.
    //   · `isFinished` dice si ya se jugó → mientras sea false la fecha se puede
    //     seguir actualizando; una vez true, se congela.
    //
    // OJO con las fechas: `dateTimeUTC` viene en UTC y los partidos nocturnos
    // argentinos caen al día siguiente. La conversión la hace `date()` porque
    // config/app.php fija el timezone en America/Argentina/Buenos_Aires. No
    // reemplazar por un parseo manual del string.

    public function fixture(Request $request)
    {
        set_time_limit(0);

        $comp    = trim((string) $request->get('comp', ''));
        // Mirar nunca escribe. Guardar toca solo el staging. Y pisar el horario
        // de partidos YA cargados es una tercera acción, explícita: si el
        // emparejado estuviera mal, movería fechas de partidos equivocados.
        $guardar  = (string) $request->get('guardar', '0') === '1';
        $refrescar = (string) $request->get('refrescar', '0') === '1';
        $filtro  = trim((string) $request->get('estado', ''));
        $gameday = trim((string) $request->get('gameday', ''));
        // Temporada de la competencia. Vacío = la que TM dé por defecto, que es
        // la que está en curso. Ver `traerFixture()`.
        $season  = trim((string) $request->get('season', ''));

        $avisos = [];

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

        // Si viene un torneo tuyo, la competencia sale de ahí.
        $torneoElegido = null;
        if ((int) $request->get('torneo_id')) {
            $torneoElegido = \App\Torneo::find((int) $request->get('torneo_id'));
            if ($torneoElegido && trim((string) $torneoElegido->tm_competition_id) !== '') {
                $comp = trim((string) $torneoElegido->tm_competition_id);
            }
        }

        $conTm = \App\Torneo::whereNotNull('tm_competition_id')->where('tm_competition_id', '!=', '')
            ->orderBy('year', 'desc')->orderBy('nombre')->get();

        $opts = '<option value="">— elegí un torneo tuyo —</option>';
        foreach ($conTm as $t) {
            $sel = ($torneoElegido && $torneoElegido->id === $t->id) ? ' selected' : '';
            $opts .= '<option value="' . $t->id . '"' . $sel . '>'
                . e($t->nombre . ' ' . $t->year . '  ·  ' . $t->tm_competition_id) . '</option>';
        }

        $html = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Fixture por competencia</h1>'
            . '<p class="sub">Baja el fixture completo del torneo con <b>una</b> llamada y lo deja listo para '
            . 'aplicar fecha por fecha. Reemplaza la carga del Excel. El detalle de cada partido '
            . '(alineaciones, goles, tarjetas) lo trae después la pantalla de siempre.</p>';

        if ($conTm->isEmpty()) {
            $html .= '<div class="err-box">Ningún torneo tuyo tiene cargado el id de competencia de Transfermarkt. '
                . 'Averigualo con el buscador de abajo y guardalo en <b>Editar torneo → transfermarkt.com</b>. '
                . 'Se hace una sola vez por torneo.</div>';
        } else {
            $html .= '<form method="get" style="margin:12px 0">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí un torneo tuyo…">' . $opts . '</select> '
                . '<input name="season" value="' . e($season) . '" placeholder="temporada, ej 2021" size="10"> '
                . '<button>Ver fixture</button> '
                . '<span class="sub">1 crédito + 1 por los nombres de los clubes</span></form>'
                . '<p class="sub">El id de competencia es de la <b>copa</b>, no de la edición: tus cinco Copas '
                . 'Argentina comparten <code>ARCA</code>. Sin temporada, Transfermarkt manda la que está en curso, '
                . 'así que elegir un torneo viejo igual baja el fixture de este año. La temporada es el <b>año de '
                . 'arranque</b>, uno menos que el nombre del torneo en Argentina: la Copa Argentina 2022 es '
                . '<code>2021</code>. Pedir temporada siempre baja de TM —el staging no distingue ediciones—.</p>';
        }

        $html .= '<details' . ($comp === '' ? ' open' : '') . '><summary>No sé el id de competencia de un torneo</summary>'
            . '<div class="diag" style="margin-top:8px">'
            . '<p class="sub">El fixture de un club lista <b>todas</b> las competencias que juega, con sus ids. '
            . 'Poné un club de Transfermarkt que participe del torneo que buscás y te las muestro. '
            . 'Después copiá el id en <b>Editar torneo → transfermarkt.com</b>.</p>'
            . '<form method="get">'
            . '<input name="descubrir" value="' . e((string) $request->get('descubrir', '')) . '" placeholder="club TM, ej 1029 (Vélez)" size="22"> '
            . '<button>Buscar competencias</button> <span class="sub">2 créditos</span></form>'
            . $this->bloqueDescubrirCompetencias($request)
            . '<p class="sub" style="margin-top:10px">También podés escribir el id a mano: '
            . '<form method="get" style="display:inline">'
            . '<input name="comp" value="' . e($comp) . '" placeholder="ej ARGC" size="12"> '
            . '<button>Ver</button></form></p>'
            . '</div></details>'
            . $this->bloqueProbarTemporada($request, $comp);

        foreach ($avisos as $a) $html .= '<p class="ok-box">' . $a . '</p>';

        if ($comp === '') return $this->pagina('Fixture', $html);

        // Pedir una temporada obliga a bajar: el staging no distingue ediciones
        // (guarda por `competencia_external_id` y nada más), así que releerlo
        // devolvería las cinco Copas Argentina mezcladas.
        $usarCache = (string) $request->get('cache', '0') === '1' && $season === '';
        $filas = [];

        if ($usarCache) {
            $filas = $this->fixtureDesdeStaging($comp);
            if (empty($filas)) $usarCache = false;
        }

        $saltados = 0;
        if (!$usarCache) {
            $crudo = $this->traerFixture($comp, $season);
            if (is_string($crudo)) return $this->pagina('Fixture', $html . '<p class="err-box">' . $crudo . '</p>');

            $compNombre = $this->nombreCompetencia($comp);
            foreach ($crudo as $g) {
                $f = $this->normalizarFixture($g, $comp, $compNombre);
                if (!$f['hora_definida'] || !$f['dia']) { $saltados++; continue; }
                $filas[] = $f;
            }
            $filas = $this->completarNombresClubes($filas);
        }

        if (empty($filas)) {
            return $this->pagina('Fixture', $html
                . '<p class="err-box">No vino ningún partido con día y hora confirmados para <code>'
                . e($comp) . '</code>.</p>');
        }

        $filas = $this->clasificarFixture($filas);

        // Qué temporada vino DE VERDAD. Sin esto no hay forma de saber si TM
        // respetó el `seasonId` o te devolvió la edición en curso igual: los
        // partidos se ven bien y son de otro año.
        $temporadas = [];   // seasonId => ['n' => partidos, 'anio' => nombre lindo]
        foreach ($filas as $f) {
            $t = trim((string) (isset($f['temporada']) ? $f['temporada'] : ''));
            if ($t === '') continue;
            if (!isset($temporadas[$t])) $temporadas[$t] = ['n' => 0, 'anio' => ''];
            $temporadas[$t]['n']++;
            if ($temporadas[$t]['anio'] === '' && !empty($f['anio'])) $temporadas[$t]['anio'] = (string) $f['anio'];
        }
        if (!$usarCache) {
            $lista = [];
            foreach ($temporadas as $t => $d) {
                $lista[] = e($t) . ($d['anio'] !== '' ? ' (' . e($d['anio']) . ')' : '') . ' · ' . $d['n'] . ' partidos';
            }
            $vino = implode(' — ', $lista) ?: 'no vino ninguna';

            if ($season === '') {
                $html .= '<p class="sub">Temporada que devolvió Transfermarkt: <b>' . $vino . '</b>. '
                    . 'No se pidió ninguna, así que es la que está en curso.</p>';
            } elseif (count($temporadas) === 1 && array_key_exists($season, $temporadas)) {
                $html .= '<p class="ok-box">Pediste la temporada <b>' . e($season) . '</b> y eso vino: <b>'
                    . $vino . '</b>.</p>';
            } else {
                $html .= '<p class="err-box">Pediste la temporada <b>' . e($season) . '</b> y vino <b>' . $vino
                    . '</b>. Transfermarkt <b>ignoró</b> el parámetro (o esa edición no existe con ese id): '
                    . 'lo que estás mirando no es el fixture que pediste.</p>';
            }
        }

        $cont = ['total' => count($filas), 'nuevo' => 0, 'duplicado' => 0, 'conflicto' => 0,
            'jugados' => 0, 'pendientes' => 0];
        $porFecha = [];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['terminado']) $cont['jugados']++; else $cont['pendientes']++;
            $r = (string) $f['ronda'];
            if (!isset($porFecha[$r])) {
                $porFecha[$r] = ['n' => 0, 'nuevo' => 0, 'conflicto' => 0, 'duplicado' => 0,
                    'desde' => $f['dia'], 'hasta' => $f['dia']];
            }
            $porFecha[$r]['n']++;
            if (isset($porFecha[$r][$f['estado']])) $porFecha[$r][$f['estado']]++;
            if ($f['dia'] < $porFecha[$r]['desde']) $porFecha[$r]['desde'] = $f['dia'];
            if ($f['dia'] > $porFecha[$r]['hasta']) $porFecha[$r]['hasta'] = $f['dia'];
        }
        ksort($porFecha, SORT_NATURAL);

        $guardadas = 0; $refrescadas = 0;
        if ($guardar || $refrescar) {
            foreach ($filas as $f) $guardadas += $this->persistirFixture($f) ? 1 : 0;
        }
        $resultados = ['cargados' => 0, 'detalle' => ''];
        if ($refrescar) {
            $refrescadas = $this->refrescarHorarios($filas);
            $resultados  = $this->completarResultados($filas);
        }

        // La auditoría no escribe nada: se muestra siempre.
        $problemas = $this->auditarResultados($filas);

        $porTipo = [];
        foreach ($problemas as $pr) {
            $t = isset($pr['tipo']) ? $pr['tipo'] : 'otro';
            $porTipo[$t] = (isset($porTipo[$t]) ? $porTipo[$t] : 0) + 1;
        }
        $sinResultado = isset($porTipo['sin_resultado']) ? $porTipo['sin_resultado'] : 0;

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos con fecha')
            . $this->card($saltados, 'sin programar', $saltados ? 'gris' : '')
            . $this->card($cont['jugados'], 'ya jugados')
            . $this->card($cont['pendientes'], 'por jugarse')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($sinResultado, 'cargados sin resultado', $sinResultado ? 'warn' : 'ok')
            . $this->card($cont['nuevo'], 'nuevos a crear', $cont['nuevo'] ? 'warn' : 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . '</div>';

        if ($usarCache) {
            $html .= '<p class="sub">Datos del <b>staging</b>: no se bajó nada de Transfermarkt. Es la foto de la '
                . 'última bajada, así que un partido que se jugó después de esa bajada acá sigue figurando sin '
                . 'resultado. Para cargar resultados nuevos, usá «Volver a bajar de TM» primero.</p>';
        }
        if ($saltados) {
            $html .= '<p class="sub">Se ignoraron <b>' . $saltados . '</b> partidos sin día y hora confirmados '
                . '(<code>isTimeDefined: false</code>). Entran solos cuando TM los programe y vuelvas a bajar.</p>';
        }
        if ($guardar || $refrescar) {
            $html .= '<p class="ok-box">Guardadas <b>' . $guardadas . '</b> filas en staging.'
                . ($refrescar
                    ? ($refrescadas
                        ? ' Actualicé el horario de <b>' . $refrescadas . '</b> partidos que todavía no se jugaron.'
                        : ' Ningún horario necesitaba corrección.')
                      . ($resultados['cargados']
                        ? ' Cargué el resultado de <b>' . $resultados['cargados'] . '</b> partidos que estaban sin marcador.'
                        : ' Ningún partido estaba sin resultado.')
                    : '')
                . '</p>';
            if ($resultados['detalle']) {
                $html .= '<h2>Resultados cargados</h2><div class="scroll"><table><thead><tr><th>Día</th>'
                    . '<th>Local</th><th>Res.</th><th>Visitante</th><th>Partido</th></tr></thead><tbody>'
                    . $resultados['detalle'] . '</tbody></table></div>';
            }
        } else {
            $html .= '<p class="ok-box"><b>No se escribió nada.</b> Esto es solo una vista: '
                . 'no se creó, borró ni modificó ningún partido tuyo. '
                . 'Revisá los números de arriba —sobre todo que «ya cargados» sea alto si este torneo ya lo tenías— '
                . 'y recién después guardá.</p>';
        }

        $base = route('import_partidos.fixture', ['comp' => $comp]);

        // LOS BOTONES QUE ESCRIBEN TRABAJAN SOBRE LO QUE SE ESTÁ VIENDO.
        // Antes forzaban `cache=1` siempre, así que si venías de una bajada
        // fresca de TM el botón descartaba esos datos y releía el staging: una
        // foto vieja, tomada antes de que se jugaran los partidos, donde
        // `isFinished` es false y el `score` viene vacío. Resultado: la pantalla
        // te prometía cargar N resultados y el botón no escribía nada, porque
        // para el staging esos partidos todavía no se habían jugado.
        // Si la vista salió del staging, el botón sigue usando el staging; si
        // salió de TM, el botón vuelve a bajar de TM.
        $fuente = $usarCache ? '&cache=1' : '';
        $costo  = $usarCache ? '' : ' <span class="sub">(vuelve a bajar de TM)</span>';

        $html .= '<p class="acciones">'
            . '<a class="boton" href="' . e($base . $fuente . '&guardar=1') . '">Guardar en staging</a>' . $costo
            . ' <span class="sub">no toca tus partidos; solo habilita el botón «Aplicar» de cada fecha</span>'
            . '</p>'
            . '<p class="acciones">'
            . '<a class="boton-sec" href="' . e($base . $fuente . '&refrescar=1') . '">Guardar, corregir horarios y cargar resultados</a>' . $costo
            . ' <span class="sub"><b>esto sí escribe en tus partidos</b>: pisa día y hora de los que todavía no se '
            . 'jugaron, y carga el marcador en los que estén <b>sin resultado</b>. Nunca pisa un resultado que ya '
            . 'tengas cargado. Usalo cuando hayas comprobado que el emparejado es correcto.</span>'
            . '</p>'
            . '<p class="acciones">'
            . '<a href="' . e($base) . '">Volver a bajar de TM</a> · '
            . '<a href="' . e($base . '&cache=1') . '">Releer sin bajar</a> · '
            . '<a href="' . e($base . '&cache=1&estado=conflicto') . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&cache=1&estado=nuevo') . '">Ver solo nuevos</a>'
            . '</p>';

        $html .= $this->bloqueClubesSinResolver($filas, $request);
        $html .= $this->bloqueClubesMapeados($filas, $request);

        if (!empty($problemas)) {
            $revisar = trim((string) $request->get('revisar', ''));
            $visibles = $problemas;
            if ($revisar !== '') {
                $visibles = array_values(array_filter($problemas, function ($x) use ($revisar) {
                    return (isset($x['tipo']) ? $x['tipo'] : 'otro') === $revisar;
                }));
            }

            $etiquetas = ['sin_resultado' => 'sin resultado', 'distinto' => 'resultado distinto',
                'penales' => 'penales', 'goles' => 'goles cargados', 'localia' => 'localía',
                'otro' => 'otros'];
            $chips = ($revisar === '' ? '<b>Todos (' . count($problemas) . ')</b>'
                : '<a href="' . e($base . '&cache=1') . '">Todos (' . count($problemas) . ')</a>');
            foreach ($etiquetas as $k => $lab) {
                if (empty($porTipo[$k])) continue;
                $chips .= ' · ' . ($revisar === $k
                    ? '<b>' . e($lab) . ' (' . $porTipo[$k] . ')</b>'
                    : '<a href="' . e($base . '&cache=1&revisar=' . $k) . '">' . e($lab)
                      . ' (' . $porTipo[$k] . ')</a>');
            }

            $html .= '<h2>Revisar <span class="sub">(' . count($problemas) . ')</span></h2>'
                . '<p class="sub">Diferencias entre lo que tenés cargado y lo que dice Transfermarkt, más chequeos '
                . 'internos de tu base. <b>No se corrige nada de esto solo</b>: puede estar mal TM o podés tenerlo '
                . 'bien vos.</p>'
                . ($sinResultado
                    ? '<p class="ok-box"><b>' . $sinResultado . '</b> «sin resultado» son la excepción: el partido '
                      . 'ya lo tenés creado —por eso no figura en NUEVOS— pero está sin marcador y TM ya lo jugó. '
                      . 'Esos los carga solos <b>«Guardar, corregir horarios y cargar resultados»</b>. '
                      . 'Las vueltas de llave no entran acá: van al bloque «Llaves de ida y vuelta» y se cargan a mano.</p>'
                    : '')
                . '<p class="acciones">' . $chips . '</p>'
                . '<div class="scroll"><table><thead><tr><th>Día</th><th>Partido</th><th>Qué pasa</th>'
                . '<th>Tenés</th><th>TM / contado</th><th></th></tr></thead><tbody>';
            $mapaF = $this->mapaFechas(array_map(function ($x) { return $x['partido_id']; }, $visibles));
            $n = 0;
            foreach ($visibles as $pr) {
                if ($n++ >= 200) break;
                $html .= '<tr class="warn">'
                    . '<td class="num">' . e(substr((string) $pr['dia'], 0, 10)) . '</td>'
                    . '<td>' . e($pr['local'] . ' vs ' . $pr['visitante']) . '</td>'
                    . '<td>' . e($pr['problema']) . '</td>'
                    . '<td class="num">' . e((string) $pr['tuyo']) . '</td>'
                    . '<td class="num">' . e((string) $pr['tm']) . '</td>'
                    . '<td><span class="id">#' . (int) $pr['partido_id'] . '</span> '
                    . $this->linkIncidencias(isset($mapaF[$pr['partido_id']]) ? $mapaF[$pr['partido_id']] : null)
                    . (empty($pr['external_id']) ? ''
                        : ' · <a href="' . e(route('import_partidos.partido',
                                ['game_id' => $pr['external_id']]))
                            . '" title="Abre el JSON de TM de ESTE partido. Gasta 1 crédito.">Sondear</a>')
                    . '</td></tr>';
            }
            $html .= '</tbody></table></div>';
            if (count($visibles) > 200) {
                $html .= '<p class="sub">Se muestran 200 de ' . count($visibles) . '.</p>';
            }
        } else {
            $html .= '<p class="ok-box">Nada para revisar: los resultados que tenés coinciden con TM, la localía '
                . 'también, y los goles cargados dan el marcador.</p>';
        }

        $html .= $this->bloqueLlaves($filas);

        $html .= '<h2>Fechas</h2><div class="scroll"><table><thead><tr><th>Fecha nº</th><th>Partidos</th>'
            . '<th>Período</th><th>Ya cargados</th><th>Nuevos</th><th>Conflictos</th><th></th></tr></thead><tbody>';
        foreach ($porFecha as $r => $d) {
            $html .= '<tr>'
                . '<td class="num"><b>' . e($r) . '</b></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td class="num">' . e(substr($d['desde'], 0, 10))
                . ($d['desde'] !== $d['hasta'] ? ' → ' . e(substr($d['hasta'], 0, 10)) : '') . '</td>'
                . '<td class="num">' . $d['duplicado'] . '</td>'
                . '<td class="num">' . ($d['nuevo'] ? '<b class="warn">' . $d['nuevo'] . '</b>' : '0') . '</td>'
                . '<td class="num">' . ($d['conflicto'] ? '<b class="err">' . $d['conflicto'] . '</b>' : '0') . '</td>'
                . '<td>' . ($d['nuevo']
                    ? '<a class="boton-sec" href="' . e(route('import_partidos.fixture_aplicar',
                        ['comp' => $comp, 'gameday' => $r])) . '">Aplicar ' . $d['nuevo'] . ' →</a>'
                    : '<span class="sub">nada por crear</span>')
                . ' <a class="boton-sec" href="' . e(route('import_detalles.index',
                    ['comp' => $comp, 'ronda' => $r])) . '">Detalles →</a>'
                . '</td></tr>';
        }
        $html .= '</tbody></table></div>';

        $titulo = $filtro !== '' ? ('Partidos con estado «' . e($filtro) . '»')
            : ($gameday !== '' ? ('Fecha ' . e($gameday)) : 'Partidos');
        $html .= '<h2>' . $titulo . '</h2>' . $this->tablaFixture($filas, $filtro, $gameday);

        return $this->pagina('Fixture por competencia', $html);
    }

    /**
     * Buscador de ids de competencia.
     *
     * No hay forma de adivinar que "Clausura 2026" es `ARGC`. Pero el fixture de
     * un club lista todas las competencias que juega, con su id y su temporada:
     * con un club por país se descubren la liga, la copa nacional y las de
     * Conmebol de una sola vez.
     *
     * Cuesta 2 llamadas: el fixture del club y los nombres de las competencias.
     */
    private function bloqueDescubrirCompetencias(Request $request)
    {
        $clubId = trim((string) $request->get('descubrir', ''));
        if ($clubId === '') return '';

        $resp = HttpHelper::getJson(self::TMAPI . '/club/' . rawurlencode($clubId) . '/fixtures');
        if (!is_array($resp)) {
            return '<p class="err-box">No pude traer el fixture del club ' . e($clubId) . '.</p>';
        }
        $data = isset($resp['data']) ? $resp['data'] : $resp;
        $juegos = isset($data['games']) && is_array($data['games']) ? $data['games'] : [];
        if (empty($juegos)) {
            return '<p class="err-box">El club ' . e($clubId) . ' no devolvió partidos.</p>';
        }

        // Agrupar por competencia + temporada.
        $comps = [];
        foreach ($juegos as $g) {
            $bd = isset($g['baseDetails']) && is_array($g['baseDetails']) ? $g['baseDetails'] : [];
            $cid = isset($bd['competitionId']) ? (string) $bd['competitionId'] : '';
            if ($cid === '') continue;
            $temp = isset($bd['seasonId']) ? (string) $bd['seasonId'] : '';
            // OJO: `seasonId` es el año de ARRANQUE de la temporada; para
            // Argentina va uno atrás del año real. El año que usa el usuario es
            // `cyclicalName` (= seasonId + 1). Nunca mostrar el seasonId solo.
            $anio = isset($bd['season']['cyclicalName']) ? (string) $bd['season']['cyclicalName'] : '';
            $k = $cid . '|' . $temp;
            if (!isset($comps[$k])) $comps[$k] = ['id' => $cid, 'temporada' => $temp, 'anio' => $anio,
                'n' => 0, 'desde' => null, 'hasta' => null];
            if ($anio !== '' && $comps[$k]['anio'] === '') $comps[$k]['anio'] = $anio;
            $comps[$k]['n']++;
            $raw = isset($bd['date']['dateTimeUTC']) ? $bd['date']['dateTimeUTC'] : null;
            if ($raw && ($ts = strtotime($raw))) {
                $d = date('Y-m-d', $ts);
                if (!$comps[$k]['desde'] || $d < $comps[$k]['desde']) $comps[$k]['desde'] = $d;
                if (!$comps[$k]['hasta'] || $d > $comps[$k]['hasta']) $comps[$k]['hasta'] = $d;
            }
        }
        if (empty($comps)) return '<p class="err-box">No reconocí ninguna competencia en ese club.</p>';

        $ids = [];
        foreach ($comps as $c) $ids[$c['id']] = true;
        $nombres = $this->resolverNombres(self::TMAPI . '/competitions', array_keys($ids));

        uasort($comps, function ($a, $b) { return strcmp((string) $b['desde'], (string) $a['desde']); });

        $out = '<div class="scroll" style="margin-top:8px"><table><thead><tr><th>Competencia</th><th>Id</th>'
            . '<th>Es tu año…</th><th>seasonId TM</th><th>Partidos</th><th>Período</th><th></th></tr></thead><tbody>';
        foreach ($comps as $c) {
            $nom = isset($nombres[$c['id']]) ? $nombres[$c['id']] : $c['id'];
            $anio = $c['anio'] !== '' ? $c['anio'] : substr((string) $c['desde'], 0, 4);
            $out .= '<tr>'
                . '<td>' . e($nom) . '</td>'
                . '<td class="num"><b>' . e($c['id']) . '</b></td>'
                . '<td class="num"><b class="ok">' . e($anio) . '</b></td>'
                . '<td class="num gris">' . e($c['temporada']) . '</td>'
                . '<td class="num">' . $c['n'] . '</td>'
                . '<td class="num">' . e((string) $c['desde']) . ' → ' . e((string) $c['hasta']) . '</td>'
                . '<td><a href="' . e(route('import_partidos.fixture', ['comp' => $c['id']]))
                . '">Ver este fixture</a></td>'
                . '</tr>';
        }
        $out .= '</tbody></table></div>'
            . '<p class="sub"><b>Ojo con las dos columnas de año.</b> «Es tu año» es el que usás vos '
            . '(el <code>cyclicalName</code> de TM, que coincide con las fechas de los partidos). '
            . '«seasonId TM» va <b>uno atrás</b> y es el que hay que guardar en el torneo, porque es el que '
            . 'entiende la API. El Clausura 2026 es seasonId 2025.<br>'
            . 'Copiá el <b>Id</b> y el <b>seasonId</b> en «Editar torneo → transfermarkt.com» del torneo '
            . 'cuyo año coincida con la columna «Es tu año». A partir de ahí aparece en el desplegable de arriba.</p>';

        return $out;
    }

    /** Trae el fixture completo y lo aplana: fixtures[].games[] -> lista. */
    /**
     * ¿Hay alguna forma de pedirle a la API el fixture de una edición vieja?
     *
     * `/competition/{id}/fixtures` devuelve la temporada en curso y `?seasonId=`
     * lo ignora (probado con ARCA y 2021: vino 2025). Antes de dar por perdida
     * la idea conviene agotar las variantes de una sola vez, porque cada intento
     * suelto cuesta un crédito igual y a ciegas se van de a uno.
     *
     * Mide el resultado por el `seasonId` que traen los partidos, no por el HTTP
     * status: la API contesta 200 y un fixture perfecto aunque ignore lo que le
     * pediste. Ese es justamente el modo en que engaña.
     */
    private function bloqueProbarTemporada(Request $request, $comp)
    {
        $pedida = trim((string) $request->get('probar_temporada', ''));
        $base   = route('import_partidos.fixture', array_filter([
            'comp' => $comp ?: null, 'probar_temporada' => '2021']));

        $out = '<details' . ($pedida !== '' ? ' open' : '') . '>'
            . '<summary>Probar si la API acepta una temporada</summary><div class="diag" style="margin-top:8px">'
            . '<p class="sub">El id de competencia no distingue ediciones, así que sin temporada siempre viene la '
            . 'que está en curso. Esto prueba <b>cinco formas distintas</b> de pedirle una vieja y muestra qué '
            . 'temporada devolvió cada una. <b>Cuesta 1 crédito por variante</b>.</p>'
            . '<form method="get">'
            . '<input type="hidden" name="comp" value="' . e($comp) . '">'
            . '<input name="probar_temporada" value="' . e($pedida !== '' ? $pedida : '2021') . '" size="8"> '
            . '<button>Probar</button> <span class="sub">5 créditos</span></form>';

        if ($pedida === '' || $comp === '') {
            return $out . '</div></details>';
        }

        // Las cinco formas plausibles: cuatro nombres de parámetro y el estilo
        // REST con la temporada en el path.
        $variantes = [
            '?seasonId='  => self::TMAPI . '/competition/' . rawurlencode($comp) . '/fixtures?seasonId=' . rawurlencode($pedida),
            '?season_id=' => self::TMAPI . '/competition/' . rawurlencode($comp) . '/fixtures?season_id=' . rawurlencode($pedida),
            '?season='    => self::TMAPI . '/competition/' . rawurlencode($comp) . '/fixtures?season=' . rawurlencode($pedida),
            '?saison_id=' => self::TMAPI . '/competition/' . rawurlencode($comp) . '/fixtures?saison_id=' . rawurlencode($pedida),
            '/fixtures/{temporada}' => self::TMAPI . '/competition/' . rawurlencode($comp) . '/fixtures/' . rawurlencode($pedida),
        ];

        $out .= '<div class="scroll" style="margin-top:10px"><table><thead><tr><th>Variante</th>'
            . '<th>Partidos</th><th>Temporada que vino</th><th>Período</th><th></th></tr></thead><tbody>';

        $gano = null;
        foreach ($variantes as $etiqueta => $url) {
            $json = HttpHelper::getJson($url);

            if (!is_array($json) || empty($json)) {
                $err = HttpHelper::getLastJsonError();
                $out .= '<tr><td><code>' . e($etiqueta) . '</code></td><td colspan="4" class="sub">'
                    . 'sin datos' . (is_array($err) ? ' — ' . e(json_encode($err, JSON_UNESCAPED_UNICODE)) : '')
                    . '</td></tr>';
                continue;
            }

            $data = isset($json['data']) ? $json['data'] : $json;
            $bloques = isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [];
            $temps = []; $n = 0; $desde = null; $hasta = null;
            foreach ($bloques as $b) {
                if (!is_array($b) || empty($b['games']) || !is_array($b['games'])) continue;
                foreach ($b['games'] as $g) {
                    if (!is_array($g)) continue;
                    $n++;
                    $bd = isset($g['baseDetails']) && is_array($g['baseDetails']) ? $g['baseDetails'] : [];
                    $t = isset($bd['seasonId']) ? (string) $bd['seasonId'] : '';
                    if ($t !== '') $temps[$t] = true;
                    $d = isset($bd['date']['dateTimeUTC']) ? substr((string) $bd['date']['dateTimeUTC'], 0, 10) : null;
                    if ($d) {
                        if ($desde === null || $d < $desde) $desde = $d;
                        if ($hasta === null || $d > $hasta) $hasta = $d;
                    }
                }
            }

            $vinieron = array_keys($temps);
            $acerto = (count($vinieron) === 1 && (string) $vinieron[0] === $pedida);
            if ($acerto && $gano === null) $gano = $etiqueta;

            $out .= '<tr class="' . ($acerto ? 'ok' : 'warn') . '">'
                . '<td><code>' . e($etiqueta) . '</code></td>'
                . '<td class="num">' . $n . '</td>'
                . '<td class="num">' . ($vinieron ? e(implode(', ', $vinieron)) : '—') . '</td>'
                . '<td class="num">' . e((string) $desde) . ' → ' . e((string) $hasta) . '</td>'
                . '<td>' . ($acerto ? '<b class="ok">✔ la respeta</b>' : '<span class="sub">la ignora</span>') . '</td>'
                . '</tr>';
        }

        $out .= '</tbody></table></div>';

        $out .= $gano !== null
            ? '<p class="ok-box">La API <b>sí</b> acepta la temporada con <code>' . e($gano) . '</code>. '
              . 'Con eso se puede guardar el seasonId en cada torneo y que elegirlo baje la edición correcta.</p>'
            : '<p class="err-box">Ninguna variante sirvió: <code>/competition/{id}/fixtures</code> devuelve '
              . '<b>siempre la temporada en curso</b>. Para las ediciones viejas hay que seguir yendo por el '
              . 'sondeo del DT (<code>/coach/{id}/performance-game</code>), que sí va hacia atrás en el tiempo. '
              . 'Ojo: contestan 200 y un fixture entero igual, así que sin mirar el <code>seasonId</code> parece '
              . 'que anduvo.</p>';

        return $out . '</div></details>';
    }

    private function traerFixture($compId, $season = '')
    {
        // OJO: el id de competencia identifica la COPA, no la edición. `ARCA` es
        // la Copa Argentina de todos los años, así que sin temporada TM devuelve
        // la que está en curso: elegir "Copa Argentina 2022" bajaba el fixture
        // 2026. `seasonId` es el año de ARRANQUE (la edición 2022 es la 2021,
        // igual que el `saison_id` de la web).
        $url = self::TMAPI . '/competition/' . rawurlencode($compId) . '/fixtures';
        $season = trim((string) $season);
        if ($season !== '') $url .= '?seasonId=' . rawurlencode($season);

        $resp = HttpHelper::getJson($url);
        if (!is_array($resp)) {
            $err = HttpHelper::getLastJsonError();
            return 'No pude bajar el fixture de ' . e($compId) . '. '
                . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle');
        }
        $data = isset($resp['data']) ? $resp['data'] : $resp;
        $bloques = isset($data['fixtures']) && is_array($data['fixtures']) ? $data['fixtures'] : [];
        if (empty($bloques)) return 'El fixture de ' . e($compId) . ' vino vacío.';

        $juegos = [];
        foreach ($bloques as $b) {
            if (!is_array($b) || empty($b['games']) || !is_array($b['games'])) continue;
            foreach ($b['games'] as $g) if (is_array($g)) $juegos[] = $g;
        }
        return $juegos ?: 'El fixture de ' . e($compId) . ' no trajo partidos.';
    }

    /** Un partido del fixture -> la forma que usa el staging. */
    /**
     * Vueltas de llave: se listan, no se auditan.
     *
     * En la vuelta de una eliminatoria el `score` de TM no es el marcador de
     * ese partido —puede ser el global de la llave, la tanda, o una mezcla— y
     * no hay forma confiable de separarlo. Compararlo contra tu resultado solo
     * genera ruido, así que va aparte y con el número crudo a la vista.
     */
    private function bloqueLlaves(array $filas)
    {
        $rows = [];
        foreach ($filas as $f) {
            if (empty($f['ida_vuelta']) || empty($f['terminado'])) continue;
            if (empty($f['partido_id']) || empty($f['marcador_tm'])) continue;
            $rows[] = $f;
        }
        if (empty($rows)) return '';

        $ids = array_map(function ($x) { return (int) $x['partido_id']; }, $rows);
        $partidos = [];
        foreach (\App\Partido::whereIn('id', $ids)->get() as $p) $partidos[(int) $p->id] = $p;

        // Cuántas te faltan cargar: es la única tarea pendiente de este bloque,
        // y sin el número queda escondida entre las que ya están.
        $faltan = 0;
        foreach ($ids as $pid) {
            if (isset($partidos[$pid]) && ($partidos[$pid]->golesl === null || $partidos[$pid]->golesv === null)) $faltan++;
        }

        $html = '<h2>Llaves de ida y vuelta <span class="sub">(' . count($rows) . ')</span></h2>'
            . ($faltan ? '<p class="ok-box"><b>' . $faltan . '</b> de estas vueltas las tenés <b>sin resultado</b>. '
                . 'Son las que no puede cargar el importador: hay que ponerles el marcador a mano.</p>' : '')
            . '<p class="sub">Estos son <b>vueltas</b> de una eliminatoria (TM los manda con '
            . '<code>firstLegScore</code>). Ahí el número que publica TM <b>no es el marcador de esos 90\'</b>: '
            . 'según el caso es el global de la llave, la tanda de penales, o una mezcla. '
            . '<b>No se comparan ni se cargan solos</b> — se listan para que los mires vos.</p>'
            . '<div class="scroll"><table><thead><tr><th>Día</th><th>Partido</th><th>Tenés</th>'
            . '<th>TM publica</th><th>Ida</th><th></th></tr></thead><tbody>';

        $mapaF = $this->mapaFechas($ids);
        foreach ($rows as $f) {
            $pid = (int) $f['partido_id'];
            $p = isset($partidos[$pid]) ? $partidos[$pid] : null;
            if (!$p) continue;

            $tuyo = ($p->golesl === null || $p->golesv === null)
                ? '<span class="sub">sin resultado</span>'
                : e($p->golesl . ':' . $p->golesv)
                  . ($p->penalesl !== null && $p->penalesv !== null
                      ? ' <span class="sub">y ' . e($p->penalesl . '-' . $p->penalesv) . ' p</span>' : '');

            $html .= '<tr>'
                . '<td class="num">' . e(substr((string) $f['dia'], 0, 10)) . '</td>'
                . '<td>' . e($this->nombreEquipo($p->equipol_id) . ' vs ' . $this->nombreEquipo($p->equipov_id)) . '</td>'
                . '<td class="num">' . $tuyo . '</td>'
                . '<td class="num">' . e((string) $f['marcador_tm'])
                . (!empty($f['por_penales']) ? ' <span class="sub">(hubo penales)</span>' : '') . '</td>'
                . '<td class="num">' . e((string) (isset($f['ida_marcador']) ? $f['ida_marcador'] : '—')) . '</td>'
                . '<td><span class="id">#' . $pid . '</span> '
                . $this->linkIncidencias(isset($mapaF[$pid]) ? $mapaF[$pid] : null)
                . (empty($f['external_id']) ? ''
                    : ' · <a href="' . e(route('import_partidos.partido',
                            ['game_id' => $f['external_id']]))
                        . '" title="Abre el JSON de TM de ESTE partido. Gasta 1 crédito.">Sondear</a>')
                . '</td></tr>';
        }
        return $html . '</tbody></table></div>';
    }

    private function normalizarFixture(array $g, $compId, $compNombre)
    {
        $bd = isset($g['baseDetails']) && is_array($g['baseDetails']) ? $g['baseDetails'] : [];
        $fecha = isset($bd['date']) && is_array($bd['date']) ? $bd['date'] : [];

        $dia = null;
        $raw = isset($fecha['dateTimeUTC']) ? $fecha['dateTimeUTC'] : null;
        if ($raw) {
            $ts = strtotime($raw);
            // date() convierte a America/Argentina/Buenos_Aires (config/app.php).
            if ($ts) $dia = date('Y-m-d H:i:s', $ts);
        }

        $terminado = !empty($g['isFinished']);
        $sc = isset($g['score']) && is_array($g['score']) ? $g['score'] : [];

        // OJO CON LOS PENALES: cuando el partido se define por tanda, el
        // `score` de TM NO es el marcador de los 90'. Es 90' + los penales
        // convertidos, todo sumado: 1:1 con tanda 4:2 lo publica como 5:3, y lo
        // marca con `additionType: "after_shootout"` (en un partido normal, con
        // "none"). Guardar eso como resultado seria cargar un marcador que
        // nunca existio, asi que se separa la tanda o no se carga nada.
        $porPenales = isset($sc['additionType']) && $sc['additionType'] === 'after_shootout';

        $gf = ($terminado && isset($sc['home'])) ? (int) $sc['home'] : null;
        $gc = ($terminado && isset($sc['away'])) ? (int) $sc['away'] : null;
        $brutoTm = ($gf === null || $gc === null) ? null : $gf . ':' . $gc;

        $penF = $porPenales ? $this->penalesConvertidos($g, 'homeClub') : null;
        $penC = $porPenales ? $this->penalesConvertidos($g, 'awayClub') : null;

        // IDA Y VUELTA: si el `score` trae `firstLegScore`, este partido es la
        // VUELTA de una llave y su `score` NO es el marcador de estos 90'.
        // Comprobado con O'Higgins–Boca (gameId 4891294): 1:0 a los 90' y tanda
        // 3-4, y TM publica 3:4 — que no es el partido, ni 90'+tanda, ni el
        // global. Segun el caso puede ser una cosa u otra y no hay forma
        // confiable de separarlo, asi que no se compara ni se carga: se lista
        // aparte para mirarlo a mano.
        $idaVuelta = isset($sc['firstLegScore']) && is_array($sc['firstLegScore']);

        if ($idaVuelta) {
            $gf = null; $gc = null;
        } elseif ($porPenales) {
            // Partido unico definido por penales: ahi si vale 90' + tanda
            // (Riestra–Gimnasia, 1:1 con tanda 4:2, TM lo publica 5:3).
            // El detalle del partido trae la tanda y entonces se puede restar.
            // El listado del fixture no la trae: ahi el marcador queda en null
            // —mejor sin resultado que con uno inventado— y la auditoria avisa.
            if ($gf !== null && $gc !== null && $penF !== null && $penC !== null) {
                $gf -= $penF; $gc -= $penC;
            } else {
                $gf = null; $gc = null;
            }
        }

        return [
            'external_id'             => isset($g['gameId']) ? (string) $g['gameId'] : null,
            'competencia_external_id' => isset($bd['competitionId']) && $bd['competitionId'] !== '' ? $bd['competitionId'] : $compId,
            'competencia_nombre'      => $compNombre,
            // `seasonId` es el año de arranque: para Argentina va uno atrás del
            // año real. El año que se le muestra al usuario es `cyclicalName`.
            'temporada'               => isset($bd['seasonId']) ? (string) $bd['seasonId'] : null,
            'anio'                    => isset($bd['season']['cyclicalName']) ? (string) $bd['season']['cyclicalName'] : null,
            'ronda'                   => isset($bd['gameDay']) ? (string) $bd['gameDay'] : null,
            // En este flujo el "club" es SIEMPRE el local: local = 1 fijo.
            'club_external_id'        => isset($g['homeClub']['clubId']) ? (string) $g['homeClub']['clubId'] : null,
            'club_nombre'             => null,
            'rival_external_id'       => isset($g['awayClub']['clubId']) ? (string) $g['awayClub']['clubId'] : null,
            'rival_nombre'            => null,
            'local'                   => 1,
            'dia'                     => $dia,
            'goles_favor'             => $gf,
            'goles_contra'            => $gc,
            'equipo_id' => null, 'rival_id' => null, 'partido_id' => null,
            'estado' => 'nuevo', 'motivo' => null,
            'payload' => json_encode($g, JSON_UNESCAPED_UNICODE),
            'terminado'     => $terminado,
            'por_penales'    => $porPenales,
            'ida_vuelta'     => $idaVuelta,
            'ida_marcador'   => $idaVuelta && isset($sc['firstLegScore']['home'])
                                    ? $sc['firstLegScore']['home'] . ':' . $sc['firstLegScore']['away'] : null,
            'penales_favor'  => $penF,
            'penales_contra' => $penC,
            'marcador_tm'    => $brutoTm,
            'hora_definida' => !empty($fecha['isTimeDefined']),
            'reprogramado'  => !empty($g['extendedDetails']['isRescheduled']),
        ];
    }

    /**
     * Penales convertidos por un lado en la tanda.
     *
     * `homeClub.actions.shootout` solo viene en el detalle del partido, no en el
     * listado del fixture. Si no esta devuelve **null**, que no es lo mismo que
     * cero: null significa "no se puede separar la tanda del marcador".
     */
    private function penalesConvertidos(array $g, $lado)
    {
        if (!isset($g[$lado]['actions']['shootout'])
            || !is_array($g[$lado]['actions']['shootout'])) return null;

        $n = 0;
        foreach ($g[$lado]['actions']['shootout'] as $t) {
            if (isset($t['action']) && $t['action'] === 'Scored') $n++;
        }
        return $n;
    }

    /** El fixture trae solo clubIds: los nombres se piden aparte (1 llamada / 50). */
    private function completarNombresClubes(array $filas)
    {
        $ids = [];
        foreach ($filas as $f) {
            if ($f['club_external_id'])  $ids[$f['club_external_id']] = true;
            if ($f['rival_external_id']) $ids[$f['rival_external_id']] = true;
        }
        $nombres = $this->resolverNombres(self::TMAPI . '/clubs', array_keys($ids));
        foreach ($filas as $i => $f) {
            if ($f['club_external_id'] && isset($nombres[$f['club_external_id']])) {
                $filas[$i]['club_nombre'] = $nombres[$f['club_external_id']];
            }
            if ($f['rival_external_id'] && isset($nombres[$f['rival_external_id']])) {
                $filas[$i]['rival_nombre'] = $nombres[$f['rival_external_id']];
            }
        }
        return $filas;
    }

    private function nombreCompetencia($compId)
    {
        $m = $this->resolverNombres(self::TMAPI . '/competitions', [$compId]);
        return isset($m[(string) $compId]) ? $m[(string) $compId] : $compId;
    }

    /**
     * Nuevo / duplicado / conflicto. Más simple que la del DT: acá la localía
     * viene dada por homeClub/awayClub, así que no existe el caso
     * "no se pudo determinar si fue local o visitante".
     */
    private function clasificarFixture(array $filas)
    {
        $mapaTm = $this->mapaTm();
        $mapaNombres = $this->mapaNombres();

        foreach ($filas as $i => $f) {
            $localId = $this->resolverClub($f['club_external_id'], $f['club_nombre'], $mapaTm, $mapaNombres);
            $visiId  = $this->resolverClub($f['rival_external_id'], $f['rival_nombre'], $mapaTm, $mapaNombres);
            $filas[$i]['equipo_id'] = $localId;
            $filas[$i]['rival_id']  = $visiId;

            if (!$localId || !$visiId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$localId) $faltan[] = 'local «' . $f['club_nombre'] . '»';
                if (!$visiId)  $faltan[] = 'visitante «' . $f['rival_nombre'] . '»';
                $filas[$i]['motivo'] = 'sin mapear: ' . implode(' / ', $faltan);
                continue;
            }

            $partido = $this->buscarPartido($localId, $visiId, $f['dia']);
            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $filas[$i]['motivo'] = 'ya cargado';
            } else {
                $filas[$i]['estado'] = 'nuevo';
            }
        }
        return $filas;
    }

    /** Guarda en staging. La clave es el gameId: reimportar no duplica. */
    private function persistirFixture(array $f)
    {
        if (!$f['external_id']) return false;
        $clave = ['fuente' => 'transfermarkt', 'external_id' => $f['external_id'], 'tecnico_id' => null];

        $ya = DB::table('import_partidos')->where($clave)->first();
        if ($ya && $ya->estado === 'aplicado') {
            $sigue = $ya->partido_id && \App\Partido::where('id', $ya->partido_id)->exists();
            if ($sigue) return false;   // aplicada y viva: no se pisa
            DB::table('import_partidos')->where('id', $ya->id)
                ->update(['estado' => 'nuevo', 'partido_id' => null, 'motivo' => null, 'updated_at' => now()]);
        }

        DB::table('import_partidos')->updateOrInsert($clave, [
            'competencia_external_id' => $f['competencia_external_id'],
            'competencia_nombre'      => $f['competencia_nombre'],
            'temporada'               => $f['temporada'],
            'ronda'                   => $f['ronda'],
            'club_external_id'        => $f['club_external_id'],
            'club_nombre'             => $f['club_nombre'],
            'rival_external_id'       => $f['rival_external_id'],
            'rival_nombre'            => $f['rival_nombre'],
            'local'                   => 1,
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

    /**
     * Corrige día y hora de los partidos YA cargados que todavía no se jugaron.
     *
     * Es lo contrario de la regla del motor DT (donde nunca se pisa la fecha,
     * porque TM guarda la original de los postergados y la base tiene la real).
     * Acá TM es la fuente de la programación: mientras `isFinished` sea false su
     * horario manda. Con el partido jugado, la fecha queda congelada.
     */
    private function refrescarHorarios(array $filas)
    {
        $n = 0;
        foreach ($filas as $f) {
            if (!empty($f['terminado']) || empty($f['partido_id']) || empty($f['dia'])) continue;
            $p = \App\Partido::find($f['partido_id']);
            if (!$p) continue;
            if (substr((string) $p->dia, 0, 16) === substr($f['dia'], 0, 16)) continue;
            $p->forceFill(['dia' => $f['dia']])->save();
            $n++;
        }
        return $n;
    }

    /**
     * Carga el resultado en los partidos que YA tenés pero que están sin
     * marcador. Nunca pisa un resultado cargado: si el tuyo difiere del de TM,
     * se avisa y se deja como está — puede ser un error de TM o tuyo, pero lo
     * decidís vos.
     *
     * OJO CON LA LOCALÍA: `buscarPartido()` empareja en los dos órdenes, así que
     * tu partido puede tener local y visitante al revés de como los tiene TM.
     * Antes de copiar los goles hay que mirar la orientación, o se carga el
     * resultado dado vuelta.
     */
    private function completarResultados(array $filas)
    {
        $out = ['cargados' => 0, 'detalle' => ''];

        foreach ($filas as $f) {
            if (empty($f['terminado']) || empty($f['partido_id'])) continue;
            if ($f['goles_favor'] === null || $f['goles_contra'] === null) continue;

            $p = \App\Partido::find($f['partido_id']);
            if (!$p) continue;
            if ($p->golesl !== null && $p->golesv !== null) continue;   // ya tiene resultado

            // ¿Está en el mismo orden que TM?
            if ((int) $p->equipol_id === (int) $f['equipo_id']) {
                $mismoOrden = true;
                $gl = (int) $f['goles_favor']; $gv = (int) $f['goles_contra'];
            } elseif ((int) $p->equipol_id === (int) $f['rival_id']) {
                $mismoOrden = false;
                $gl = (int) $f['goles_contra']; $gv = (int) $f['goles_favor'];
            } else {
                continue;   // no reconozco la orientación: no toco nada
            }

            $datos = ['golesl' => $gl, 'golesv' => $gv];

            // Si el partido se definió por penales, el marcador que se carga es
            // el de los 90' (`normalizarFixture` ya le restó la tanda) y la
            // tanda va donde corresponde. Sin tanda separable no se llega acá:
            // `goles_favor` viene en null y el partido se saltea más arriba.
            if (!empty($f['por_penales'])
                && isset($f['penales_favor']) && isset($f['penales_contra'])) {
                $datos['penalesl'] = $mismoOrden ? (int) $f['penales_favor'] : (int) $f['penales_contra'];
                $datos['penalesv'] = $mismoOrden ? (int) $f['penales_contra'] : (int) $f['penales_favor'];
            }

            $p->forceFill($datos)->save();
            $out['detalle'] .= '<tr><td class="num">' . e(substr((string) $f['dia'], 0, 10)) . '</td>'
                . '<td>' . e($this->nombreEquipo($p->equipol_id)) . '</td>'
                . '<td class="num"><b>' . $gl . ':' . $gv . '</b>'
                . (isset($datos['penalesl'])
                    ? ' <span class="sub">(' . $datos['penalesl'] . '-' . $datos['penalesv'] . ' p)</span>' : '')
                . '</td>'
                . '<td>' . e($this->nombreEquipo($p->equipov_id)) . '</td>'
                . '<td class="num">#' . (int) $p->id . '</td></tr>';
            $out['cargados']++;
        }
        return $out;
    }

    /**
     * Audita sin escribir nada. Tres cosas:
     *   · tu resultado vs el de Transfermarkt
     *   · la localía: si tu partido tiene local y visitante al revés que TM
     *   · el marcador vs los goles cargados en `gols`
     *
     * `gols` no guarda el equipo: sale de la alineación del jugador. Y un gol
     * "En Contra" suma para el RIVAL del que lo hizo.
     */
    private function auditarResultados(array $filas)
    {
        $ids = [];
        foreach ($filas as $f) if (!empty($f['partido_id'])) $ids[] = (int) $f['partido_id'];
        $ids = array_values(array_unique($ids));
        if (empty($ids)) return [];

        $partidos = [];
        foreach (array_chunk($ids, 500) as $t) {
            foreach (\App\Partido::whereIn('id', $t)->get() as $p) $partidos[(int) $p->id] = $p;
        }

        // Goles cargados, atribuidos al equipo que corresponde.
        $anotados = []; $sinAtribuir = [];
        foreach (array_chunk($ids, 500) as $t) {
            $rows = DB::table('gols')
                ->leftJoin('alineacions', function ($j) {
                    $j->on('alineacions.partido_id', '=', 'gols.partido_id')
                      ->on('alineacions.jugador_id', '=', 'gols.jugador_id');
                })
                ->whereIn('gols.partido_id', $t)
                ->select('gols.partido_id', 'gols.tipo', 'alineacions.equipo_id',
                    DB::raw('COUNT(*) AS n'))
                ->groupBy('gols.partido_id', 'gols.tipo', 'alineacions.equipo_id')
                ->get();

            foreach ($rows as $r) {
                $pid = (int) $r->partido_id;
                if (!isset($partidos[$pid])) continue;
                if ($r->equipo_id === null) { $sinAtribuir[$pid] = (isset($sinAtribuir[$pid]) ? $sinAtribuir[$pid] : 0) + (int) $r->n; continue; }

                $p = $partidos[$pid];
                $deQuien = (int) $r->equipo_id;
                // En contra: el gol es del rival del que lo hizo.
                if ($r->tipo === 'En Contra') {
                    $deQuien = ((int) $p->equipol_id === $deQuien) ? (int) $p->equipov_id : (int) $p->equipol_id;
                }
                if (!isset($anotados[$pid])) $anotados[$pid] = ['l' => 0, 'v' => 0];
                if ($deQuien === (int) $p->equipol_id) $anotados[$pid]['l'] += (int) $r->n;
                else                                    $anotados[$pid]['v'] += (int) $r->n;
            }
        }

        $problemas = [];
        foreach ($filas as $f) {
            $pid = (int) (isset($f['partido_id']) ? $f['partido_id'] : 0);
            if (!$pid || !isset($partidos[$pid])) continue;
            $p = $partidos[$pid];

            $invertido = ((int) $p->equipol_id === (int) $f['rival_id']);
            $problema = null; $tuyo = null; $deTm = null; $tipo = 'otro';

            // Partido definido por penales: el `score` de TM viene con la
            // tanda sumada, así que lo comparable es 90' + penales. Comparar
            // contra `golesl/golesv` pelados marcaba como error TODOS los
            // partidos por penales (1:1 tuyo contra 6:7 de TM).
            if (!empty($f['ida_vuelta'])) {
                $problema = null;   // no comparable: va al bloque de llaves
            } elseif (!empty($f['terminado']) && !empty($f['por_penales'])
                && !empty($f['marcador_tm'])
                && $p->golesl !== null && $p->golesv !== null) {
                list($brutoA, $brutoB) = array_map('intval', explode(':', $f['marcador_tm']));
                $tmL = $invertido ? $brutoB : $brutoA;
                $tmV = $invertido ? $brutoA : $brutoB;

                // TM publica DOS cosas distintas y desde el listado del fixture
                // no hay con qué distinguirlas (el `score` de acá viene sin
                // `firstLegScore`, que es lo único que marca una vuelta):
                //   · partido único  → 90' + tanda   (Riestra–Gimnasia: 1:1 y
                //     4-2 lo publica 5:3)
                //   · vuelta de llave → la tanda sola (O'Higgins–Boca: 1:0 y
                //     3-4 lo publica 3:4)
                // Las dos verificadas contra el JSON. Se acepta cualquiera de
                // las dos: solo se marca cuando no coincide con ninguna.
                if ($p->penalesl === null || $p->penalesv === null) {
                    $problema = 'TM lo da definido por penales y no tenés la tanda cargada';
                    $tipo = 'penales';
                    $tuyo = $p->golesl . ':' . $p->golesv . ' sin penales';
                    $deTm = $tmL . ':' . $tmV;
                } else {
                    $conGoles = ((int) $p->golesl + (int) $p->penalesl === $tmL
                              && (int) $p->golesv + (int) $p->penalesv === $tmV);
                    $soloTanda = ((int) $p->penalesl === $tmL && (int) $p->penalesv === $tmV);

                    if (!$conGoles && !$soloTanda) {
                        $problema = 'no coincide con TM ni sumando la tanda ni tomando solo la tanda';
                        $tipo = 'penales';
                        $tuyo = $p->golesl . ':' . $p->golesv . ' y ' . $p->penalesl . '-' . $p->penalesv . ' p';
                        $deTm = $tmL . ':' . $tmV . ' (sería ' . ((int) $p->golesl + (int) $p->penalesl)
                              . ':' . ((int) $p->golesv + (int) $p->penalesv) . ' o '
                              . $p->penalesl . ':' . $p->penalesv . ')';
                    }
                }
            } elseif (!empty($f['terminado']) && $f['goles_favor'] !== null
                && $p->golesl !== null && $p->golesv !== null) {
                $tmL = $invertido ? (int) $f['goles_contra'] : (int) $f['goles_favor'];
                $tmV = $invertido ? (int) $f['goles_favor']  : (int) $f['goles_contra'];
                if ((int) $p->golesl !== $tmL || (int) $p->golesv !== $tmV) {
                    $problema = 'resultado distinto al de TM';
                    $tipo = 'distinto';
                    $tuyo = $p->golesl . ':' . $p->golesv;
                    $deTm = $tmL . ':' . $tmV;
                }

            // TM ya lo jugó y vos lo tenés SIN marcador. No es un conflicto —el
            // partido está bien cargado, por eso la columna NUEVOS no lo ve— pero
            // tampoco es "nada para revisar": es trabajo pendiente. Sin esta rama
            // la pantalla se quedaba muda con los partidos cargados a medias.
            // Este es el único caso de la lista que se arregla solo, con
            // «Guardar, corregir horarios y cargar resultados».
            } elseif (!empty($f['terminado']) && $f['goles_favor'] !== null
                && ($p->golesl === null || $p->golesv === null)) {
                $tmL = $invertido ? (int) $f['goles_contra'] : (int) $f['goles_favor'];
                $tmV = $invertido ? (int) $f['goles_favor']  : (int) $f['goles_contra'];
                $problema = 'lo tenés sin resultado y TM ya lo tiene'
                    . ($invertido ? ' (y además la localía te quedó invertida respecto de TM)' : '');
                $tipo = 'sin_resultado';
                $tuyo = 'sin resultado';
                $deTm = $tmL . ':' . $tmV;
            }

            // Los goles cargados tienen que dar el marcador.
            if ($problema === null && isset($anotados[$pid])
                && $p->golesl !== null && $p->golesv !== null) {
                $a = $anotados[$pid];
                if ($a['l'] !== (int) $p->golesl || $a['v'] !== (int) $p->golesv) {
                    $problema = 'los goles cargados no dan el marcador';
                    $tipo = 'goles';
                    $tuyo = $p->golesl . ':' . $p->golesv;
                    $deTm = $a['l'] . ':' . $a['v'] . ' (contados en gols)';
                }
            }

            if ($problema === null && !empty($sinAtribuir[$pid])) {
                $problema = $sinAtribuir[$pid] . ' gol(es) de jugadores que no están en la alineación';
                $tipo = 'goles';
            }

            if ($problema === null && $invertido) {
                $problema = 'localía invertida respecto de TM';
                $tipo = 'localia';
                $tuyo = $this->nombreEquipo($p->equipol_id) . ' de local';
                $deTm = $this->nombreEquipo($f['equipo_id']) . ' de local';
            }

            if ($problema !== null) {
                $problemas[] = ['partido_id' => $pid, 'dia' => $f['dia'],
                    'external_id' => isset($f['external_id']) ? $f['external_id'] : null,
                    'local' => $this->nombreEquipo($p->equipol_id),
                    'visitante' => $this->nombreEquipo($p->equipov_id),
                    'problema' => $problema, 'tuyo' => $tuyo, 'tm' => $deTm,
                    'tipo' => $tipo];
            }
        }
        return $problemas;
    }

    /** Relee el fixture desde el staging, sin tocar Transfermarkt. */
    private function fixtureDesdeStaging($compId)
    {
        $filas = [];
        $rows = DB::table('import_partidos')
            ->whereNull('tecnico_id')->where('competencia_external_id', $compId)
            ->orderBy('dia')->get();

        foreach ($rows as $r) {
            $g = $r->payload ? json_decode($r->payload, true) : null;
            if (is_array($g) && !empty($g)) {
                $f = $this->normalizarFixture($g, $compId, $r->competencia_nombre);
                if ($r->club_nombre)  $f['club_nombre']  = $r->club_nombre;
                if ($r->rival_nombre) $f['rival_nombre'] = $r->rival_nombre;
            } else {
                $f = [
                    'external_id' => $r->external_id,
                    'competencia_external_id' => $r->competencia_external_id,
                    'competencia_nombre' => $r->competencia_nombre,
                    'temporada' => $r->temporada, 'ronda' => $r->ronda,
                    'club_external_id' => $r->club_external_id, 'club_nombre' => $r->club_nombre,
                    'rival_external_id' => $r->rival_external_id, 'rival_nombre' => $r->rival_nombre,
                    'local' => 1, 'dia' => $r->dia,
                    'goles_favor' => $r->goles_favor, 'goles_contra' => $r->goles_contra,
                    'payload' => $r->payload,
                    'terminado' => $r->goles_favor !== null,
                    'por_penales' => false, 'ida_vuelta' => false, 'ida_marcador' => null,
                    'penales_favor' => null,
                    'penales_contra' => null, 'marcador_tm' => null,
                    'hora_definida' => true, 'reprogramado' => false,
                ];
            }
            $f['equipo_id'] = null; $f['rival_id'] = null; $f['partido_id'] = null;
            $f['estado'] = 'nuevo'; $f['motivo'] = null;
            $filas[] = $f;
        }
        return $filas;
    }

    private function tablaFixture(array $filas, $filtro = '', $gameday = '')
    {
        $ids = [];
        foreach ($filas as $f) if (!empty($f['partido_id'])) $ids[] = $f['partido_id'];
        $fechas = $this->mapaFechas($ids);

        $out = '<div class="scroll"><table><thead><tr><th>Fecha nº</th><th>Día</th><th>Local</th><th>Res.</th>'
            . '<th>Visitante</th><th>gameId</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
        $n = 0;
        foreach ($filas as $f) {
            if ($filtro !== '' && $f['estado'] !== $filtro) continue;
            if ($gameday !== '' && (string) $f['ronda'] !== (string) $gameday) continue;
            if ($n++ >= 400) break;

            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'conflicto' ? 'err' : '');
            $res = ($f['goles_favor'] === null) ? '<span class="sub">—</span>'
                : (e($f['goles_favor']) . ':' . e($f['goles_contra']));

            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['ronda']) . '</td>'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 16) : '—')
                . (!empty($f['reprogramado']) ? ' <span class="warn" title="reprogramado">↻</span>' : '') . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . $res . '</td>'
                . '<td>' . e($f['rival_nombre']) . ($f['rival_id'] ? ' <span class="id">#' . $f['rival_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . e($f['external_id']) . '</td>'
                . '<td>' . e($f['estado']) . '</td>'
                . '<td>' . e($f['motivo'])
                . ($f['partido_id']
                    ? ' <span class="id">#' . $f['partido_id'] . '</span> '
                      . $this->linkIncidencias(isset($fechas[(int) $f['partido_id']]) ? $fechas[(int) $f['partido_id']] : null)
                    : '')
                . '</td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    /**
     * Crea los partidos de UNA fecha del fixture.
     *
     * De a una fecha a propósito: estos torneos NO son `parcial` —tienen tabla,
     * promedios y acumulados— así que un error acá ensucia datos de verdad.
     *
     * OJO CON LOS GRUPOS: Transfermarkt no conoce tus zonas. El Clausura son 30
     * equipos en dos grupos de 15, y TM manda los 15 partidos de la fecha
     * mezclados. Volcarlos todos a un grupo rompería el torneo, así que cada
     * partido se rutea al grupo donde está la PLANTILLA de su equipo local.
     * Los interzonales y los equipos sin plantilla se avisan y no se crean solos.
     */
    public function fixtureAplicar(Request $request)
    {
        set_time_limit(0);

        $comp     = trim((string) $request->get('comp', ''));
        $gameday  = trim((string) $request->get('gameday', ''));
        $torneoId = (int) $request->get('torneo_id');
        $confirmar = (string) $request->get('confirmar', '0') === '1';
        $interzonales = (string) $request->get('interzonales', '0') === '1';

        $volver = '<p class="sub"><a href="' . e(route('import_partidos.fixture', ['comp' => $comp, 'cache' => 1]))
            . '">← Volver al fixture</a></p>';

        if ($comp === '' || $gameday === '') {
            return $this->pagina('Aplicar fecha', $volver . '<p class="err">Faltan <code>comp</code> y <code>gameday</code>.</p>');
        }

        $filas = DB::table('import_partidos')
            ->whereNull('tecnico_id')
            ->where('competencia_external_id', $comp)
            ->where('ronda', $gameday)
            ->where('estado', 'nuevo')
            ->orderBy('dia')->get();

        if ($filas->isEmpty()) {
            return $this->pagina('Aplicar fecha', $volver
                . '<p class="ok-box">La fecha ' . e($gameday) . ' no tiene partidos nuevos por crear.</p>');
        }

        $html = $volver . '<h1>Fecha ' . e($gameday) . ' · ' . e($comp) . '</h1>';

        // ── Elegir torneo ───────────────────────────────────────────────────
        if (!$torneoId) {
            $porPais = [];
            foreach (\App\Torneo::orderBy('year', 'desc')->orderBy('nombre')->get() as $t) {
                $etiqueta = $t->ambito === 'Internacional'
                    ? (trim((string) $t->region) ?: 'Internacional')
                    : (trim((string) $t->pais) ?: 'Argentina');
                $porPais[$etiqueta][] = $t;
            }
            ksort($porPais);

            $opts = '<option value="">— elegí el torneo —</option>';
            foreach ($porPais as $etiqueta => $lista) {
                $opts .= '<optgroup label="' . e($etiqueta) . '">';
                foreach ($lista as $t) {
                    $opts .= '<option value="' . $t->id . '">' . e($t->nombre . ' ' . $t->year) . '</option>';
                }
                $opts .= '</optgroup>';
            }

            return $this->pagina('Aplicar fecha', $html
                . '<p class="sub">Son <b>' . $filas->count() . '</b> partidos. Elegí a qué torneo tuyo van. '
                . 'El grupo de cada partido lo deduzco de la plantilla del equipo local.</p>'
                . '<form method="get" action="' . e(route('import_partidos.fixture_aplicar')) . '">'
                . '<input type="hidden" name="comp" value="' . e($comp) . '">'
                . '<input type="hidden" name="gameday" value="' . e($gameday) . '">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí el torneo…">' . $opts . '</select> '
                . '<button>Continuar</button></form>');
        }

        $torneo = \App\Torneo::find($torneoId);
        if (!$torneo) return $this->pagina('Aplicar fecha', $volver . '<p class="err">No existe ese torneo.</p>');

        $grupos = \App\Grupo::where('torneo_id', $torneo->id)->orderBy('id')->get()->keyBy('id');
        if ($grupos->isEmpty()) {
            return $this->pagina('Aplicar fecha', $html
                . '<p class="err-box">' . e($torneo->nombre . ' ' . $torneo->year) . ' no tiene grupos cargados.</p>');
        }

        // ── equipo -> grupo, según las plantillas del torneo ─────────────────
        $grupoDe = [];
        foreach (DB::table('plantillas')
                     ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
                     ->where('grupos.torneo_id', $torneo->id)
                     ->select('plantillas.equipo_id', 'plantillas.grupo_id')->get() as $pl) {
            $grupoDe[(int) $pl->equipo_id] = (int) $pl->grupo_id;
        }

        $unico = $grupos->count() === 1 ? (int) $grupos->keys()->first() : null;

        $plan = []; $sinPlantilla = []; $inter = [];
        foreach ($filas as $r) {
            $lId = (int) $r->equipo_id; $vId = (int) $r->rival_id;
            $gl = isset($grupoDe[$lId]) ? $grupoDe[$lId] : null;
            $gv = isset($grupoDe[$vId]) ? $grupoDe[$vId] : null;

            if ($unico !== null) { $gl = $gl ?: $unico; $gv = $gv ?: $unico; }

            $destino = $gl; $nota = '';
            if (!$gl && !$gv) {
                $sinPlantilla[] = $r; continue;
            } elseif (!$gl) {
                $destino = $gv; $nota = 'el local no tiene plantilla; va al grupo del visitante';
            } elseif ($gv && $gl !== $gv) {
                $nota = 'interzonal: ' . e($grupos[$gl]->nombre) . ' vs ' . e($grupos[$gv]->nombre);
                $inter[] = $r;
                if (!$interzonales) { continue; }
            }
            $plan[] = ['fila' => $r, 'grupo_id' => $destino, 'nota' => $nota];
        }

        // ── Previsualización ────────────────────────────────────────────────
        if (!$confirmar) {
            $porGrupo = [];
            foreach ($plan as $x) {
                $g = $x['grupo_id'];
                if (!isset($porGrupo[$g])) $porGrupo[$g] = 0;
                $porGrupo[$g]++;
            }

            $html .= '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . '</p>';

            $html .= '<div class="cards">'
                . $this->card(count($plan), 'a crear', count($plan) ? 'ok' : '')
                . $this->card(count($inter), 'interzonales', count($inter) ? 'warn' : '')
                . $this->card(count($sinPlantilla), 'sin plantilla', count($sinPlantilla) ? 'err' : '')
                . '</div>';

            if (!empty($porGrupo)) {
                $html .= '<h2>A qué grupo va cada uno</h2><div class="scroll"><table><thead><tr>'
                    . '<th>Grupo</th><th>Partidos</th></tr></thead><tbody>';
                foreach ($porGrupo as $g => $n) {
                    $html .= '<tr><td>' . e($grupos[$g]->nombre) . ' <span class="id">#' . (int) $g . '</span></td>'
                        . '<td class="num">' . $n . '</td></tr>';
                }
                $html .= '</tbody></table></div>';
            }

            if (!empty($sinPlantilla)) {
                $html .= '<p class="err-box"><b>' . count($sinPlantilla) . ' partidos sin grupo:</b> ni el local ni el '
                    . 'visitante tienen plantilla en este torneo. Puede que hayas elegido el torneo equivocado, o que '
                    . 'falte cargarles la plantilla. Esos no se crean.<br><span class="sub">';
                foreach (array_slice($sinPlantilla, 0, 10) as $r) {
                    $html .= e($r->club_nombre . ' vs ' . $r->rival_nombre) . ' · ';
                }
                $html .= '</span></p>';
            }

            if (!empty($inter)) {
                $html .= '<p class="ok-box"><b>' . count($inter) . ' interzonales</b> (los dos equipos están en grupos '
                    . 'distintos). Por defecto <b>no</b> se crean, porque hay que decidir en qué zona van.<br>'
                    . '<a class="boton-sec" href="' . e(route('import_partidos.fixture_aplicar', ['comp' => $comp,
                        'gameday' => $gameday, 'torneo_id' => $torneo->id, 'interzonales' => 1]))
                    . '">Incluirlos, en el grupo del local</a></p>';
            }

            $html .= '<h2>Detalle</h2><div class="scroll"><table><thead><tr><th>Día</th><th>Local</th>'
                . '<th>Res.</th><th>Visitante</th><th>Grupo destino</th><th></th></tr></thead><tbody>';
            foreach ($plan as $x) {
                $r = $x['fila'];
                $html .= '<tr>'
                    . '<td class="num">' . e(substr((string) $r->dia, 0, 16)) . '</td>'
                    . '<td>' . e($r->club_nombre) . '</td>'
                    . '<td class="num">' . ($r->goles_favor === null ? '—' : e($r->goles_favor) . ':' . e($r->goles_contra)) . '</td>'
                    . '<td>' . e($r->rival_nombre) . '</td>'
                    . '<td>' . e($grupos[$x['grupo_id']]->nombre) . '</td>'
                    . '<td class="sub">' . $x['nota'] . '</td></tr>';
            }
            $html .= '</tbody></table></div>';

            if (empty($plan)) {
                return $this->pagina('Aplicar fecha', $html . '<p class="err-box">No hay nada que crear.</p>');
            }

            $html .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_partidos.fixture_aplicar', array_filter(['comp' => $comp, 'gameday' => $gameday,
                    'torneo_id' => $torneo->id, 'interzonales' => $interzonales ? 1 : null, 'confirmar' => 1])))
                . '">Crear estos ' . count($plan) . ' partidos</a>'
                . ' <span class="sub">recién acá se escribe</span></p>';

            return $this->pagina('Aplicar fecha', $html);
        }

        // ── Crear ───────────────────────────────────────────────────────────
        $creados = 0; $errores = []; $detalle = ''; $fechasTocadas = [];
        foreach ($plan as $x) {
            $r = $x['fila']; $gId = (int) $x['grupo_id'];
            try {
                $fecha = \App\Fecha::where('grupo_id', $gId)->where('numero', $gameday)->first();
                if (!$fecha) {
                    $fecha = new \App\Fecha();
                    $fecha->forceFill([
                        'numero'     => $gameday,
                        'grupo_id'   => $gId,
                        'orden'      => is_numeric($gameday) ? (int) $gameday : 999,
                        'url_nombre' => Str::slug('fecha-' . $gameday),
                    ])->save();
                }
                $fechasTocadas[$gId] = true;

                $lId = (int) $r->equipo_id; $vId = (int) $r->rival_id;

                $ya = \App\Partido::where('fecha_id', $fecha->id)
                    ->where(function ($q) use ($lId, $vId) {
                        $q->where('equipol_id', $lId)->orWhere('equipov_id', $lId)
                            ->orWhere('equipol_id', $vId)->orWhere('equipov_id', $vId);
                    })->first();
                if ($ya) {
                    $errores[] = 'Ya hay un partido de ' . $this->nombreEquipo($lId) . ' en la fecha ' . $gameday
                        . ' del grupo ' . $grupos[$gId]->nombre . ' (#' . $ya->id . ').';
                    continue;
                }

                $partido = new \App\Partido();
                $partido->forceFill([
                    'fecha_id'   => $fecha->id,
                    'dia'        => $r->dia,
                    'equipol_id' => $lId,
                    'equipov_id' => $vId,
                    'golesl'     => $r->goles_favor,
                    'golesv'     => $r->goles_contra,
                ])->save();

                DB::table('import_partidos')->where('id', $r->id)
                    ->update(['estado' => 'aplicado', 'partido_id' => $partido->id,
                        'motivo' => null, 'updated_at' => now()]);

                $detalle .= '<tr><td class="num">' . e(substr((string) $r->dia, 0, 16)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($lId)) . '</td>'
                    . '<td class="num">' . ($r->goles_favor === null ? '—' : e($r->goles_favor) . ':' . e($r->goles_contra)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($vId)) . '</td>'
                    . '<td>' . e($grupos[$gId]->nombre) . '</td>'
                    . '<td class="num">#' . $partido->id . '</td></tr>';
                $creados++;
            } catch (\Throwable $ex) {
                $errores[] = 'Error en ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ': ' . $ex->getMessage();
                Log::error('fixtureAplicar: ' . $ex->getMessage());
            }
        }

        foreach (array_keys($fechasTocadas) as $gId) $this->recontarEquipos($gId);

        $html .= '<h1>Creados ' . $creados . ' partidos</h1>'
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · fecha ' . e($gameday) . '</p>';

        if (!empty($errores)) {
            $html .= '<p class="err-box"><b>' . count($errores) . ' quedaron sin crear:</b><br>' . e(implode(' — ', $errores)) . '</p>';
        }
        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Día</th><th>Local</th><th>Res.</th>'
                . '<th>Visitante</th><th>Grupo</th><th>Partido</th></tr></thead><tbody>' . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones">'
            . '<a class="boton" href="' . e(route('import_partidos.fixture', ['comp' => $comp, 'cache' => 1])) . '">Seguir con otra fecha →</a>'
            . '<a class="boton-sec" href="' . e(route('import_detalles.index')) . '">Bajar el detalle de estos partidos</a></p>';

        return $this->pagina('Aplicar fecha', $html);
    }

    // ═══════════════════════════ CREAR EQUIPO DESDE TM ═══════════════════════════

    /**
     * Crea un equipo que NO existe en la base, con lo que da tmapi, y lo deja
     * mapeado en `equipo_tm`. Después redirige a la edición para completar lo
     * que Transfermarkt no tiene.
     *
     * Sólo corre sobre clubes nuevos: si el id de TM ya está mapeado, no toca
     * nada y te lleva al equipo existente. Nunca pisa datos cargados a mano.
     *
     * De `/clubs?ids[]=` salen: nombre, siglas (clubCode), país (countryId) y
     * escudo (crestUrl). NO vienen fundación, estadio, socios ni historia:
     * ésos se completan a mano en la pantalla a la que caés.
     *
     * Cuesta 2 llamadas: una por los datos y otra por el escudo.
     */
    public function crearEquipo(Request $request)
    {
        set_time_limit(0);

        $tmId = trim((string) $request->get('tm_id', ''));
        $volverA = $request->get('volver');

        if ($tmId === '') {
            return redirect()->route('import_partidos.index')->with('error', 'Falta el id de Transfermarkt.');
        }

        // ¿Ya está mapeado? Entonces no hay nada que crear.
        $yaMapeado = DB::table('equipo_tm')->where('tm_club_id', $tmId)->value('equipo_id');
        if ($yaMapeado && \App\Equipo::where('id', $yaMapeado)->exists()) {
            $links = $this->urlsClubTm($tmId, null);
            return redirect()->route('equipos.edit', $yaMapeado)
                ->with('error', 'El club de TM ' . e($tmId) . ' ya estaba mapeado a este equipo, así que no creé nada '
                    . 'ni toqué ningún dato. Si querés completarlo a mano: '
                    . '<a href="' . e($links['datos']) . '" target="_blank"><b>Datos y hechos ↗</b></a> · '
                    . '<a href="' . e($links['perfil']) . '" target="_blank">Perfil del club ↗</a>');
        }

        $json = HttpHelper::getJson(self::TMAPI . '/clubs?ids[]=' . urlencode($tmId));
        $club = null;
        if (is_array($json)) {
            $data = isset($json['data']) ? $json['data'] : $json;
            if (isset($data['clubs']) && is_array($data['clubs'])) $data = $data['clubs'];
            foreach ((array) $data as $item) {
                if (!is_array($item)) continue;
                if ((string) (isset($item['id']) ? $item['id'] : '') === (string) $tmId) { $club = $item; break; }
            }
            if ($club === null && isset($data['id']) && (string) $data['id'] === (string) $tmId) $club = $data;
        }

        if (!is_array($club)) {
            $err = HttpHelper::getLastJsonError();
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'No pude traer el club ' . e($tmId) . ' de Transfermarkt. '
                    . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle'));
        }

        $base = isset($club['baseDetails']) && is_array($club['baseDetails']) ? $club['baseDetails'] : [];

        // Nombre: el largo oficial del club "superior" suele ser el bueno
        // ("Club Atlético Vélez Sársfield"); si no está, el corto de la ficha.
        $nombre = trim((string) (isset($base['superiorClub']['name']) ? $base['superiorClub']['name'] : ''));
        if ($nombre === '') $nombre = trim((string) (isset($club['name']) ? $club['name'] : ''));
        if ($nombre === '') {
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'El club ' . e($tmId) . ' vino sin nombre. No lo creé.');
        }

        $siglas = trim((string) (isset($club['preferences']['clubCode']) ? $club['preferences']['clubCode'] : ''));
        if ($siglas === '') $siglas = trim((string) (isset($base['abbreviation']) ? $base['abbreviation'] : ''));

        $pais = null;
        $paisId = (int) (isset($base['countryId']) ? $base['countryId'] : 0);
        if ($paisId) {
            $paises = \App\Http\Controllers\JugadorController::paisesTM();
            $pais = isset($paises[$paisId]) ? $paises[$paisId] : null;
        }

        $escudo = $this->bajarEscudo(isset($club['crestUrl']) ? $club['crestUrl'] : null);

        try {
            $equipo = \App\Equipo::create([
                'nombre'     => $nombre,
                'siglas'     => $siglas !== '' ? $siglas : null,
                'pais'       => $pais,
                'escudo'     => $escudo,
                'socios'     => 0,        // la columna es NOT NULL; se completa a mano
                'url_nombre' => Str::slug($nombre),
            ]);
        } catch (\Exception $e) {
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'No pude crear el equipo: ' . e($e->getMessage()));
        }

        $this->guardarMapeo($tmId, $equipo->id, $nombre, 'club_tm');

        $falta = [];
        if (!$pais)   $falta[] = 'país';
        if (!$escudo) $falta[] = 'escudo';
        $falta[] = 'fundación';
        $falta[] = 'estadio';
        $falta[] = 'socios';
        $falta[] = 'historia';

        $links = $this->urlsClubTm($tmId, isset($club['relativeUrl']) ? $club['relativeUrl'] : null);

        $msg = 'Creé <b>' . e($nombre) . '</b> desde Transfermarkt y lo dejé mapeado al club ' . e($tmId)
            . ', así que en el sondeo ya no va a figurar como conflicto (refrescá esa pestaña).<br>'
            . 'Transfermarkt <b>no</b> trae estos datos por la API: <b>' . e(implode(', ', $falta)) . '</b>.<br>'
            . 'Los tenés en la página del club — abrila al lado y completá a mano:<br>'
            . '<a href="' . e($links['datos']) . '" target="_blank"><b>Datos y hechos ↗</b></a> '
            . '<span style="opacity:.7">(fundación, estadio, socios)</span> · '
            . '<a href="' . e($links['perfil']) . '" target="_blank">Perfil del club ↗</a>';

        return redirect()->route('equipos.edit', $equipo->id)->with('success', $msg);
    }

    /**
     * URLs del club en la web de Transfermarkt. La API no trae fundación,
     * estadio ni socios: eso está en la pestaña "Datos y hechos" del club,
     * así que siempre damos el link para completarlo a mano.
     *
     * `relativeUrl` viene como "/ca-velez-sarsfield/startseite/verein/1029".
     * Si no la tenemos, el guión anda igual: TM resuelve por el id.
     */
    private function urlsClubTm($tmId, $relativeUrl)
    {
        $base = 'https://www.transfermarkt.es';
        $rel  = trim((string) $relativeUrl);

        if ($rel === '' || strpos($rel, '/startseite/') === false) {
            return [
                'perfil' => $base . '/-/startseite/verein/' . rawurlencode($tmId),
                'datos'  => $base . '/-/datenfakten/verein/' . rawurlencode($tmId),
            ];
        }
        return [
            'perfil' => $base . $rel,
            'datos'  => $base . str_replace('/startseite/', '/datenfakten/', $rel),
        ];
    }

    /** Baja el escudo del club y devuelve el nombre de archivo, o null. */
    private function bajarEscudo($url)
    {
        if (empty($url)) return null;
        try {
            $img = HttpHelper::getBinary($url);
            if (empty($img['ok']) || empty($img['body'])) return null;

            $info = pathinfo((string) parse_url($url, PHP_URL_PATH));
            $archivo = isset($info['filename']) ? rtrim($info['filename'], '.') : '';
            if ($archivo === '') return null;
            $ext = (isset($info['extension']) && $info['extension'] !== '') ? $info['extension'] : 'png';

            $nombre = 'escudo_tm_' . $archivo . '.' . $ext;
            file_put_contents(public_path('images/') . $nombre, $img['body']);
            return $nombre;
        } catch (\Exception $e) {
            Log::error('bajarEscudo: ' . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════ SONDEO DE UN PARTIDO (descubrimiento) ═══════════════════

    /**
     * Prueba los endpoints de tmapi para un partido y muestra qué devuelve cada uno.
     * No escribe nada: sirve para saber de dónde salen alineaciones, goles,
     * tarjetas, cambios y árbitro antes de escribir el importador.
     *
     *   /admin/import-partidos/partido?game_id=2480728
     *   /admin/import-partidos/partido?partido_id=24803   (busca el gameId en el staging)
     */
    public function partido(Request $request)
    {
        set_time_limit(0);

        $gameId = trim((string) $request->get('game_id', ''));
        $fila = null;

        if ($gameId === '' && $request->filled('partido_id')) {
            $fila = DB::table('import_partidos')->where('partido_id', (int) $request->get('partido_id'))->first();
            // Si el partido_id no está en staging NO se cae al fallback de más
            // abajo: mostraría el JSON de otro partido como si fuera este, que
            // es peor que no contestar (y encima gasta un crédito).
            if (!$fila) {
                return $this->pagina('Sondeo de partido',
                    '<p class="err-box">No hay ninguna fila de staging con <code>partido_id='
                    . e((string) $request->get('partido_id')) . '</code>, así que no sé qué '
                    . '<code>gameId</code> tiene ese partido. Pasá <code>?game_id=</code> a mano, o entrá '
                    . 'desde el link «Sondear» de la pantalla del fixture, que ya lo sabe.</p>');
            }
            $gameId = (string) $fila->external_id;
        }
        if ($gameId === '') {
            // Si no dijo nada, agarramos el primer partido aplicado que tengamos.
            $fila = DB::table('import_partidos')->whereNotNull('external_id')
                ->where('estado', 'aplicado')->orderBy('dia', 'desc')->first();
            if ($fila) $gameId = (string) $fila->external_id;
        }
        if ($gameId === '') {
            return $this->pagina('Sondeo de partido',
                '<p class="err">Pasá <code>?game_id=</code> o <code>?partido_id=</code>.</p>');
        }

        // /game/{id} trae todo (lineup, actions, referees). Los demás dan 404:
        // solo se prueban si se los pide expresamente con &todos=1.
        $candidatos = ["/game/{$gameId}"];
        if ((string) $request->get('todos', '0') === '1') {
            $candidatos = array_merge($candidatos, [
                "/game/{$gameId}/lineup", "/game/{$gameId}/lineups", "/game/{$gameId}/events",
                "/game/{$gameId}/incidents", "/game/{$gameId}/statistics", "/game/{$gameId}/report",
                "/match/{$gameId}",
            ]);
        }

        $html = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a></p>'
            . '<h1>Sondeo de partido · gameId ' . e($gameId) . '</h1>';

        if ($fila) {
            $html .= '<p class="sub">' . e($fila->club_nombre . ' vs ' . $fila->rival_nombre)
                . ' · ' . e(substr((string) $fila->dia, 0, 10))
                . ' · ' . e((string) $fila->competencia_nombre)
                . ($fila->partido_id ? ' · partido #' . (int) $fila->partido_id : '') . '</p>';
        }
        $html .= '<p class="sub">Cada endpoint es una llamada a ScraperAPI. Los que respondan con datos son los '
            . 'que vamos a usar para alineaciones e incidencias.</p>';

        $rama = trim((string) $request->get('rama', ''));
        $cuantos = max(1, (int) $request->get('n', 3));

        foreach ($candidatos as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);

            $html .= '<h2><code>' . e($ruta) . '</code></h2>';

            if (!is_array($json) || empty($json)) {
                $err = HttpHelper::getLastJsonError();
                $html .= '<p class="sub">Sin datos' . (is_array($err) ? ' — ' . e(json_encode($err, JSON_UNESCAPED_UNICODE)) : '') . '</p>';
                continue;
            }

            $data = isset($json['data']) ? $json['data'] : $json;

            // Atajos para mirar una rama concreta sin abrir el JSON entero.
            if ($ruta === "/game/{$gameId}") {
                $ramas = ['homeClub.lineup.players', 'homeClub.lineup.substitutes', 'homeClub.actions.goals',
                    'homeClub.actions.cards', 'homeClub.actions.substitutes', 'homeClub.tactic',
                    'actions', 'refereeIds', 'playerIds', 'coaches', 'score', 'baseDetails'];
                $links = [];
                foreach ($ramas as $r) {
                    $links[] = '<a href="' . e($request->url() . '?' . http_build_query(
                                array_merge($request->query(), ['rama' => $r, 'n' => $cuantos]))) . '">' . e($r) . '</a>';
                }
                $html .= '<p class="acciones">Ver rama: ' . implode(' · ', $links) . '</p>';

                if ($rama !== '') {
                    $html .= '<h3><code>' . e($rama) . '</code></h3>' . $this->verRama($data, $rama, $cuantos);
                }
            }

            // Los nombres de los jugadores de la alineación no vienen en el partido:
            // probamos cómo resolverlos.
            if ($ruta === "/game/{$gameId}" && (string) $request->get('jugadores', '0') === '1') {
                $html .= $this->probarJugadores($data);
            }

            $html .= '<div class="diag">' . $this->arbolClaves($data) . '</div>';
        }

        return $this->pagina('Sondeo de partido', $html);
    }

    /** Prueba cómo resolver el nombre de los jugadores de la alineación. */
    private function probarJugadores(array $data)
    {
        $ids = [];
        foreach (['homeClub', 'awayClub'] as $lado) {
            if (!isset($data[$lado]['lineup']['players'])) continue;
            foreach ($data[$lado]['lineup']['players'] as $p) {
                if (!empty($p['id'])) $ids[] = (string) $p['id'];
                if (count($ids) >= 3) break 2;
            }
        }
        if (empty($ids)) return '<p class="err">No encontré ids de jugadores en la alineación.</p>';

        $html = '<h3>Resolver nombres de jugadores</h3><p class="sub">Ids de prueba: <code>'
            . e(implode(', ', $ids)) . '</code></p>';

        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));
        $pruebas = [
            '/players?' . $qs,
            '/player/' . $ids[0],
        ];

        foreach ($pruebas as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $html .= '<h4><code>' . e($ruta) . '</code></h4>';
            if (!is_array($json) || empty($json)) {
                $err = HttpHelper::getLastJsonError();
                $html .= '<p class="sub">Sin datos' . (is_array($err) ? ' — ' . e(json_encode($err, JSON_UNESCAPED_UNICODE)) : '') . '</p>';
                continue;
            }
            $d = isset($json['data']) ? $json['data'] : $json;
            $html .= '<pre>' . e(mb_substr(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000)) . '</pre>';
        }

        return $html;
    }

    /** Imprime una rama del JSON (ruta con puntos), mostrando los primeros N elementos. */
    private function verRama($data, $rama, $cuantos)
    {
        $actual = $data;
        foreach (explode('.', $rama) as $paso) {
            if (!is_array($actual) || !array_key_exists($paso, $actual)) {
                return '<p class="err">No existe esa rama.</p>';
            }
            $actual = $actual[$paso];
        }

        $esLista = is_array($actual) && array_keys($actual) === range(0, max(0, count($actual) - 1));
        $muestra = $esLista ? array_slice($actual, 0, $cuantos) : $actual;

        return '<pre>' . e(json_encode($muestra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            . '</pre>' . ($esLista ? '<p class="sub">' . count($actual) . ' elementos en total.</p>' : '');
    }

    /** Muestra las claves de un JSON hasta cierta profundidad, con el JSON crudo al final. */
    private function arbolClaves($data, $prof = 0)
    {
        if (!is_array($data)) return '<code>' . e(mb_substr((string) $data, 0, 120)) . '</code>';

        $lineas = [];
        $esLista = array_keys($data) === range(0, count($data) - 1);

        if ($esLista) {
            $lineas[] = '<strong>lista de ' . count($data) . ' elementos</strong>';
            if (isset($data[0]) && is_array($data[0])) {
                $lineas[] = '&nbsp;&nbsp;claves: <code>' . e(implode(', ', array_keys($data[0]))) . '</code>';
            }
        } else {
            $lineas[] = '<strong>claves:</strong> <code>' . e(implode(', ', array_keys($data))) . '</code>';
            if ($prof < 2) {
                foreach ($data as $k => $v) {
                    if (!is_array($v)) continue;
                    $lineas[] = '&nbsp;&nbsp;↳ <em>' . e($k) . '</em>: ' . $this->arbolClaves($v, $prof + 1);
                }
            }
        }

        if ($prof === 0) {
            $lineas[] = '<details><summary>JSON crudo</summary><pre>'
                . e(mb_substr(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 20000))
                . '</pre></details>';
        }

        return implode('<br>', $lineas);
    }

    // ═══════════════════════════════ SONDEO ═══════════════════════════════

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

        // ── Marcar una competencia como fuera / dentro de 1ra división ──────
        // «Excluir» guarda una regla en `competencias_excluidas`: la misma tabla
        // del ABM de siempre, así que vale para todos los DTs y para el scraper.
        $excluirComp = trim((string) $request->get('excluir_comp', ''));
        if ($excluirComp !== '') {
            $patron = NivelCompetencia::marcarExcluida($excluirComp);
            $avisos[] = $patron === ''
                ? '<span class="err">No pude armar el patrón de «' . e($excluirComp) . '».</span>'
                : 'Competencia <b>' . e($excluirComp) . '</b> excluida (patrón <code>' . e($patron) . '</code>). '
                  . 'No se sondea más, en ningún DT. Se maneja en '
                  . '<a href="' . e(route('competencias_excluidas.index')) . '" target="_blank">Competencias excluidas ↗</a>.';
        }
        $incluirComp = trim((string) $request->get('incluir_comp', ''));
        if ($incluirComp !== '') {
            $r = NivelCompetencia::marcarIncluida($incluirComp);
            if ($r['patron'] === '') {
                $avisos[] = '<span class="err">No pude armar el patrón de «' . e($incluirComp) . '».</span>';
            } else {
                $avisos[] = 'Competencia <b>' . e($incluirComp) . '</b> marcada como de <b>1ra división</b>: sus partidos vuelven al sondeo.'
                    . (empty($r['apagadas']) ? ''
                        : '<br><span class="err">Ojo:</span> para eso apagué la(s) regla(s) <code>'
                          . implode('</code>, <code>', array_map('e', $r['apagadas'])) . '</code>, que también tapaban otras competencias. '
                          . 'Se prenden de nuevo en <a href="' . e(route('competencias_excluidas.index')) . '" target="_blank">Competencias excluidas ↗</a>.');
            }
        }

        $nombreDT = null;
        $tecnico = null;
        if ($tecnicoId) {
            $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
            if (!$tecnico) return $this->pagina('Sondeo', '<p class="err">No existe el técnico #' . (int) $tecnicoId . '</p>');
            $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);
            if ($url === '') $url = trim((string) $tecnico->transfermarkt_url);

            if ($url !== '') {
                $avisos[] = 'Slug de Transfermarkt <b>ya cargado</b>: <code>' . e($url) . '</code> '
                    . '<a href="' . e($url) . '" target="_blank">ver perfil ↗</a>';
            }

            // Sin slug no hay nada que hacer: lo cargás vos desde la pantalla de estadísticas.
            if ($url === '') {
                return $this->pagina('Sondeo',
                    '<h1>' . e($nombreDT) . '</h1>'
                    . '<p class="err-box">Este DT no tiene cargado el slug de Transfermarkt.<br>'
                    . 'Buscalo y pegalo en <b>Importar desde Transfermarkt</b> '
                    . '(<a href="' . e(route('tecnico-estadisticas.createPorTecnico', $tecnico->id)) . '">abrir la pantalla del DT</a>), '
                    . 'y volvé a apretar <b>Partidos</b>.</p>');
            }
        }
        if ($url === '') {
            return $this->pagina('Sondeo', '<p class="err">Falta <code>?tecnico_id=</code> o <code>?url=</code>.</p>');
        }
        if (!preg_match('#/trainer/(\d+)#', $url, $m)) {
            return $this->pagina('Sondeo', '<p class="err">La URL no tiene el formato <code>.../trainer/{id}</code>: ' . e($url) . '</p>');
        }
        $coachId = $m[1];

        // ── Datos: del staging si ya los bajamos, o de Transfermarkt ────────
        // Después de mapear un club no hace falta volver a scrapear: las filas
        // ya están guardadas con todo lo necesario.
        $usarCache = (string) $request->get('cache', '0') === '1';
        $games = null;
        $filas = [];

        if ($usarCache && $tecnicoId) {
            $filas = $this->filasDesdeStaging($tecnicoId);
            if (empty($filas)) $usarCache = false;
        } else {
            $usarCache = false;
        }

        if (!$usarCache) {
            $games = $this->traerPartidos($coachId);
            if (is_string($games)) return $this->pagina('Sondeo', '<p class="err">' . $games . '</p>');
            $filas = [];
            foreach ($games as $g) {
                $filas[] = $this->normalizar($g, $coachId);
            }
            $filas = $this->completarNombres($filas);
        }

        // ── Solo torneos de primera división ───────────────────────────────
        // Reserva, Proyección, juveniles y ascenso no se cargan. No se muestran,
        // no se guardan en staging y —sobre todo— sus clubes («... II») no
        // aparecen pidiendo mapeo.
        list($filas, $fuera) = $this->separarPorNivel($filas);
        $fueraTotal = 0;
        foreach ($fuera as $g) $fueraTotal += $g['n'];

        // Lo que quedó afuera y ya estaba guardado de un sondeo anterior se
        // borra del staging, salvo lo que ya se aplicó (esos partidos existen).
        if ($tecnicoId && !empty($fuera)) {
            $compsDentro = [];
            foreach ($filas as $f) $compsDentro[(string) $f['competencia_external_id']] = true;
            $borrar = array_values(array_diff(array_keys($fuera), array_keys($compsDentro)));
            if (!empty($borrar)) {
                $borradas = DB::table('import_partidos')
                    ->where('tecnico_id', $tecnicoId)
                    ->whereIn('competencia_external_id', $borrar)
                    ->where('estado', '!=', 'aplicado')
                    ->delete();
                if ($borradas) {
                    $avisos[] = 'Saqué <b>' . $borradas . '</b> filas del staging que eran de competencias fuera de 1ra.';
                }
            }
        }

        $temporadas = [];
        foreach ($filas as $f) {
            if ($f['temporada'] !== null) $temporadas[] = (int) $f['temporada'];
        }
        $filas = $this->clasificar($filas, $desde);

        $aprendidos = [];
        if ($aprender) {
            $aprendidos = $this->aprenderMapeos($filas);
            if (!empty($aprendidos)) $filas = $this->clasificar($filas, $desde);
        }

        $cont = ['total' => count($filas), 'excluido' => 0, 'duplicado' => 0,
            'falta_dt' => 0, 'nuevo' => 0, 'conflicto' => 0, 'corridos' => 0];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['estado'] === 'duplicado' && strpos((string) $f['motivo'], 'falta el DT') !== false) $cont['falta_dt']++;
            if (isset($f['corrido'])) $cont['corridos']++;
        }

        $guardadas = 0;
        if ($guardar) {
            foreach ($filas as $f) {
                $guardadas += $this->persistir($f, $coachId, $tecnicoId) ? 1 : 0;
            }
        }

        // Queda constancia de que a este DT ya se lo sondeó, aunque no haya
        // dejado ni una fila en staging.
        if ($tecnicoId && $guardar) {
            $this->registrarSondeo($tecnicoId, [
                'partidos'      => $cont['total'] + $fueraTotal,
                'fuera_1ra'     => $fueraTotal,
                'fuera_alcance' => $cont['excluido'],
                'duplicados'    => $cont['duplicado'],
                'nuevos'        => $cont['nuevo'],
                'conflictos'    => $cont['conflicto'],
                'guardadas'     => $guardadas,
            ]);
        }

        sort($temporadas);
        $rango = empty($temporadas) ? '?' : (reset($temporadas) . ' – ' . end($temporadas));

        $html  = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a></p>';
        $html .= '<h1>Sondeo · ' . e($nombreDT ?: ('coach ' . $coachId)) . '</h1>';
        $html .= '<p class="sub">coach ' . e($coachId) . ' · ' . $cont['total'] . ' partidos · temporadas ' . e($rango) . ' · corte en ' . $desde . '</p>';

        foreach ($avisos as $a) $html .= '<p class="ok-box">' . $a . '</p>';

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos')
            . $this->card($cont['excluido'], 'fuera de alcance', 'gris')
            . $this->card($fueraTotal, 'fuera de 1ra', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['corridos'], 'con fecha corrida', $cont['corridos'] ? 'warn' : '')
            . $this->card($cont['falta_dt'], 'sin el DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . '</div>';

        if ($usarCache) {
            $html .= '<p class="sub">Datos tomados de <code>import_partidos</code>: no se volvió a bajar nada de Transfermarkt.</p>';
        }

        $base = $this->urlBase($request);
        $cache = $usarCache ? '&cache=1' : '';
        $html .= '<p class="acciones">'
            . '<a href="' . e($base . '&aprender=1&guardar=1' . $cache) . '">Aprender mapeo y guardar</a> · '
            . '<a href="' . e($base . '&estado=conflicto&limite=300' . $cache) . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&estado=nuevo&limite=300' . $cache) . '">Ver solo nuevos</a> · '
            . '<a href="' . e($base . '&cache=1&aprender=1&guardar=1') . '">Refrescar sin bajar</a> · '
            . '<a href="' . e($base . '&aprender=1&guardar=1') . '">Volver a bajar de Transfermarkt</a>';
        if ($tecnicoId) {
            $html .= ' · <a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Aplicar los nuevos →</a>';
            $html .= ' · <a class="boton-sec" href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId]))
                . '" title="Alineaciones, goles, tarjetas, cambios y árbitros de los partidos ya cargados">Detalles de los partidos →</a>';
        } else {
            $html .= ' <span class="sub">(para aplicar hace falta entrar con <code>?tecnico_id=</code>)</span>';
        }
        $html .= '</p>';

        if ($aprender) {
            $html .= '<p class="ok-box">Mapeos aprendidos: <b>' . count($aprendidos) . '</b>'
                . (empty($aprendidos) ? '' : '<br><span class="sub">' . e(implode(' · ', array_slice($aprendidos, 0, 40))) . '</span>') . '</p>';
        }
        if ($guardar) $html .= '<p class="ok-box">Guardadas ' . $guardadas . ' filas en <code>import_partidos</code>.</p>';

        if ($cont['total'] === 0 && $fueraTotal > 0) {
            $html .= '<div class="ok-box"><b>Este DT no tiene nada para cargar.</b><br>'
                . 'Los ' . $fueraTotal . ' partidos que trae Transfermarkt son de competencias que no son de primera '
                . 'división (las de abajo). No es un sondeo fallido: no hay nada que guardar. En la lista de DTs '
                . 'queda como <b>sondeado · nada de 1ra</b>, así no se le vuelve a gastar una llamada.</div>';
        }

        $html .= $this->bloqueCompetencias($filas, $fuera, $request);
        $html .= $this->bloqueMapeosSospechosos($filas, $request);
        $html .= $this->bloqueClubesSinResolver($filas, $request);
        $html .= $this->bloqueClubesMapeados($filas, $request);

        $titulo = $filtro !== '' ? ('Partidos con estado «' . e($filtro) . '»') : ('Primeros ' . $limite . ' partidos');
        $html .= '<h2>' . $titulo . '</h2>' . $this->tabla($filas, $limite, $filtro);
        if (!empty($games)) {
            $html .= '<h2>Estructura del JSON</h2>' . $this->diagnosticar($games[0]);
        }

        return $this->pagina('Sondeo de partidos', $html);
    }

    // ═══════════════════════════════ APLICAR ═══════════════════════════════

    public function aplicar(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id');
        if (!$tecnicoId) return $this->pagina('Aplicar', '<p class="err">Falta <code>?tecnico_id=</code>. Los partidos se crean con su DT, así que hace falta saber quién es.</p>');

        $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
        if (!$tecnico) return $this->pagina('Aplicar', '<p class="err">No existe el técnico #' . $tecnicoId . '</p>');
        $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);

        $volver = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a> · '
            . '<a href="' . e(route('import_partidos.sondear', ['tecnico_id' => $tecnicoId])) . '">Volver al sondeo</a> · '
            . '<a href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId])) . '">Detalle de los partidos →</a></p>';

        // ── Revisar/corregir la localía de lo ya aplicado ───────────────────
        if ((string) $request->get('arreglar_localia', '0') === '1') {
            return $this->pagina('Aplicar', $volver . $this->arreglarLocalia($tecnicoId));
        }

        // ── Completar el DT en partidos que ya estaban cargados ─────────────
        if ((string) $request->get('completar_dt', '0') === '1') {
            $n = $this->completarTecnicos($tecnicoId);
            return $this->pagina('Aplicar', $volver . '<h1>Listo</h1><p class="ok-box">Agregué el DT en ' . $n . ' partidos que ya estaban cargados.</p>');
        }

        // ── Confirmación: crear los partidos de un grupo ────────────────────
        if ((string) $request->get('confirmar', '0') === '1') {
            return $this->aplicarGrupo($request, $tecnicoId, $nombreDT, $volver);
        }

        // ── Pantalla: grupos pendientes ─────────────────────────────────────
        // Filas marcadas como aplicadas cuyo partido ya no existe (borrado a mano):
        // vuelven a estar pendientes.
        DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')
            ->whereNotNull('partido_id')
            ->whereNotIn('partido_id', function ($q) {
                $q->select('id')->from('partidos');
            })
            ->update(['estado' => 'nuevo', 'partido_id' => null, 'motivo' => null]);

        $pendientes = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'nuevo')
            ->orderBy('dia')->get();

        // Staging viejo: filas de reserva/juveniles/ascenso guardadas antes del
        // filtro de 1ra. No se aplican.
        $fueraDe1ra = 0;
        $pendientes = $pendientes->filter(function ($r) use (&$fueraDe1ra) {
            $d = NivelCompetencia::decidir((string) $r->competencia_nombre);
            if ($d['excluida']) { $fueraDe1ra++; return false; }
            return true;
        })->values();

        $faltaDt = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'duplicado')
            ->where('motivo', 'like', '%falta el DT%')->count();

        $html = $volver . '<h1>Aplicar partidos · ' . e($nombreDT) . '</h1>';

        $yaAplicados = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')->count();
        if ($yaAplicados) {
            $html .= '<p class="sub">Ya aplicaste <b>' . $yaAplicados . '</b> partidos de este DT. '
                . '<a class="boton-sec" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId, 'arreglar_localia' => 1])) . '">Revisar la localía de lo aplicado</a> '
                . '<a class="boton-sec" href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId])) . '">Bajar el detalle de esos partidos</a></p>';
        }

        if ($faltaDt) {
            $html .= '<p class="ok-box">Hay <b>' . $faltaDt . '</b> partidos ya cargados donde falta este DT. '
                . '<a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId, 'completar_dt' => 1])) . '">Agregar el DT en esos partidos</a></p>';
        }

        if ($fueraDe1ra) {
            $html .= '<p class="sub">Dejo afuera <b>' . $fueraDe1ra . '</b> partidos de competencias que no son de '
                . '1ra división (reserva, juveniles, ascenso). Se limpian del staging la próxima vez que sondees.</p>';
        }

        if ($pendientes->isEmpty()) {
            // "Corré el sondeo primero" es un mal consejo si el sondeo ya se
            // corrió y no dejó nada: al DT de Reserva se le gastaría una llamada
            // a la API para volver a no guardar nada.
            $sd = Schema::hasTable('tecnico_sondeos')
                ? DB::table('tecnico_sondeos')->where('tecnico_id', (int) $tecnicoId)->first() : null;

            $motivo = ($sd && (int) $sd->guardadas === 0 && (int) $sd->fuera_1ra > 0)
                ? '<div class="ok-box"><b>No hay nada para aplicar, y está bien.</b><br>'
                    . 'Este DT ya se sondeó el ' . e(substr((string) $sd->sondeado_at, 0, 16)) . ': sus '
                    . (int) $sd->fuera_1ra . ' partidos son de competencias que no son de primera división. '
                    . 'No hace falta volver a sondearlo.</div>'
                : '<p class="sub">No hay partidos nuevos en staging. Corré el sondeo con <code>&guardar=1</code> primero.</p>';

            return $this->pagina('Aplicar', $html . $motivo);
        }

        // Agrupar por competencia + temporada
        $grupos = [];
        foreach ($pendientes as $r) {
            $k = $r->competencia_external_id . '|' . $r->temporada;
            if (!isset($grupos[$k])) {
                $grupos[$k] = ['comp' => $r->competencia_external_id, 'temp' => $r->temporada,
                    'nombre' => $r->competencia_nombre, 'n' => 0,
                    'desde' => $r->dia, 'hasta' => $r->dia, 'equipos' => [],
                    'equipo_id' => $r->equipo_id];
            }
            $grupos[$k]['n']++;
            if ($r->dia < $grupos[$k]['desde']) $grupos[$k]['desde'] = $r->dia;
            if ($r->dia > $grupos[$k]['hasta']) $grupos[$k]['hasta'] = $r->dia;
            if ($r->club_nombre) $grupos[$k]['equipos'][$r->club_nombre] = true;
        }

        $torneos = \App\Torneo::orderBy('year', 'desc')->orderBy('nombre')->get();

        $html .= '<p class="sub">Cada competencia+temporada va a un torneo. Elegí uno de los tuyos; si no existe, '
            . '«Crear torneo» abre el alta de siempre con el nombre, el año, el tipo y el ámbito ya cargados. '
            . 'Lo guardás, volvés acá, refrescás y ya aparece en la lista.</p>'
            . '<div class="scroll"><table><thead><tr><th>Competencia</th><th>Temp. TM</th><th>Partidos</th><th>Período</th><th>Equipo(s)</th><th>Torneo destino</th></tr></thead><tbody>';

        foreach ($grupos as $g) {
            // Ojo: la temporada de Transfermarkt no es el año del torneo. El Clausura 2026
            // sale como seasonId 2025. Preseleccionamos mirando los años reales de los partidos.
            $anios = [];
            $anios[substr($g['desde'], 0, 4)] = true;
            $anios[substr($g['hasta'], 0, 4)] = true;
            $anios[(string) $g['temp']] = true;
            $anios[(string) ((int) $g['temp'] + 1)] = true;
            $anios[$g['temp'] . '/' . substr((string) ((int) $g['temp'] + 1), -2)] = true;
            $anios[$g['temp'] . '/' . ((int) $g['temp'] + 1)] = true;

            // Agrupados por país (nacionales) o confederación (internacionales),
            // para no confundir un Apertura argentino con uno chileno.
            $porGrupo = [];
            foreach ($torneos as $t) {
                $etiqueta = $t->ambito === 'Internacional'
                    ? (trim((string) $t->region) ?: 'Internacional')
                    : (trim((string) $t->pais) ?: 'Argentina');
                $porGrupo[$etiqueta][] = $t;
            }
            ksort($porGrupo);

            $opts = '<option value="">— elegí el torneo —</option>';
            $yaSel = false;
            foreach ($porGrupo as $etiqueta => $lista) {
                $opts .= '<optgroup label="' . e($etiqueta) . '">';
                foreach ($lista as $t) {
                    $sel = '';
                    if (!$yaSel
                        && $this->normalizaTexto($t->nombre) === $this->normalizaTexto($g['nombre'])
                        && isset($anios[(string) $t->year])) {
                        $sel = ' selected';
                        $yaSel = true;
                    }
                    $opts .= '<option value="' . $t->id . '"' . $sel . '>'
                        . e($t->nombre . ' ' . $t->year . ' · ' . $etiqueta) . '</option>';
                }
                $opts .= '</optgroup>';
            }
            $html .= '<tr>'
                . '<td>' . e($g['nombre'] ?: ('#' . $g['comp'])) . '</td>'
                . '<td class="num">' . e($g['temp']) . '</td>'
                . '<td class="num">' . $g['n'] . '</td>'
                . '<td class="num">' . e(substr($g['desde'], 0, 10)) . ' → ' . e(substr($g['hasta'], 0, 10)) . '</td>'
                . '<td>' . e(implode(', ', array_keys($g['equipos'])))
                . ($this->paisEquipo($g) ? ' <span class="id">(' . e($this->paisEquipo($g)) . ')</span>' : '') . '</td>'
                . '<td><form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
                . '<input type="hidden" name="tecnico_id" value="' . $tecnicoId . '">'
                . '<input type="hidden" name="comp" value="' . e($g['comp']) . '">'
                . '<input type="hidden" name="temp" value="' . e($g['temp']) . '">'
                . '<input type="hidden" name="confirmar" value="1">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí el torneo…">' . $opts . '</select> <button>Aplicar ' . $g['n'] . '</button>'
                . '</form>'
                . '<a class="boton-sec" target="_blank" href="' . e($this->urlCrearTorneo($g)) . '">Crear torneo ↗</a>'
                . '</td></tr>';
        }

        return $this->pagina('Aplicar partidos', $html . '</tbody></table></div>');
    }

    private function aplicarGrupo(Request $request, $tecnicoId, $nombreDT, $volver)
    {
        $comp = (string) $request->get('comp');
        $temp = (string) $request->get('temp');
        $torneoId = (string) $request->get('torneo_id');
        $grupoId = (int) $request->get('grupo_id');

        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'nuevo')
            ->where('competencia_external_id', $comp)->where('temporada', $temp)
            ->orderBy('dia')->get();

        if ($filas->isEmpty()) {
            return $this->pagina('Aplicar', $volver . '<p class="sub">Ese grupo ya no tiene partidos pendientes.</p>');
        }

        $primera = $filas->first();

        // 1. Torneo: tiene que existir. Acá no se crea nada.
        {
            if ($torneoId === '' || $torneoId === 'nuevo') {
                $g = ['comp' => $comp, 'temp' => $temp, 'nombre' => $primera->competencia_nombre,
                    'desde' => $primera->dia, 'hasta' => $filas->last()->dia,
                    'equipo_id' => $primera->equipo_id];
                return $this->pagina('Aplicar', $volver
                    . '<h1>Falta elegir el torneo</h1>'
                    . '<p class="sub">No se crea ningún torneo automáticamente. Creá el torneo con el alta de siempre '
                    . '—se abre con los datos ya cargados— y después volvé, refrescá y elegilo en la lista.</p>'
                    . '<p><a class="boton" target="_blank" href="' . e($this->urlCrearTorneo($g)) . '">Crear torneo ↗</a> '
                    . '<a class="boton-sec" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Volver a elegir</a></p>');
            }

            $torneo = \App\Torneo::find((int) $torneoId);
            if (!$torneo) return $this->pagina('Aplicar', $volver . '<p class="err">No existe ese torneo.</p>');

            $grupos = \App\Grupo::where('torneo_id', $torneo->id)->orderBy('id')->get();
            if ($grupos->isEmpty()) {
                $grupo = new \App\Grupo();
                $grupo->forceFill(['nombre' => 'Único', 'torneo_id' => $torneo->id, 'equipos' => 0])->save();
                $grupoId = $grupo->id;
            } elseif (!$grupoId) {
                if ($grupos->count() === 1) {
                    $grupoId = $grupos->first()->id;
                } else {
                    // Pedir el grupo
                    $opts = '';
                    foreach ($grupos as $gr) $opts .= '<option value="' . $gr->id . '">' . e($gr->nombre) . '</option>';
                    return $this->pagina('Aplicar', $volver
                        . '<h1>¿En qué grupo?</h1><p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' tiene ' . $grupos->count() . ' grupos.</p>'
                        . '<form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
                        . '<input type="hidden" name="tecnico_id" value="' . (int) $tecnicoId . '">'
                        . '<input type="hidden" name="comp" value="' . e($comp) . '">'
                        . '<input type="hidden" name="temp" value="' . e($temp) . '">'
                        . '<input type="hidden" name="torneo_id" value="' . (int) $torneo->id . '">'
                        . '<input type="hidden" name="confirmar" value="1">'
                        . '<select name="grupo_id">' . $opts . '</select> <button>Aplicar ' . $filas->count() . '</button></form>');
                }
            }
        }

        // 2. Partidos
        $creados = 0; $errores = []; $detalle = '';
        foreach ($filas as $r) {
            try {
                $numero = $r->ronda !== null && $r->ronda !== '' ? $r->ronda : 'Importado';
                $fecha = \App\Fecha::where('grupo_id', $grupoId)->where('numero', $numero)->first();
                if (!$fecha) {
                    $fecha = new \App\Fecha();
                    $fecha->forceFill([
                        'numero'     => $numero,
                        'grupo_id'   => $grupoId,
                        'orden'      => is_numeric($numero) ? (int) $numero : 999,
                        'url_nombre' => Str::slug('fecha-' . $numero),
                    ])->save();
                }

                // La localía y el resultado se recalculan SIEMPRE desde el JSON crudo:
                // las columnas pueden haberse guardado con una lógica vieja.
                $datos = $this->datosPartido($r);
                if ($datos['local'] === null) {
                    $errores[] = 'Sin localía: ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ' (' . substr($r->dia, 0, 10) . ')';
                    continue;
                }
                $local     = $datos['local'];
                $equipolId = $local ? (int) $r->equipo_id : (int) $r->rival_id;
                $equipovId = $local ? (int) $r->rival_id : (int) $r->equipo_id;
                $golesl    = $local ? $datos['gf'] : $datos['gc'];
                $golesv    = $local ? $datos['gc'] : $datos['gf'];

                if (!$equipolId || !$equipovId) {
                    $errores[] = 'Sin equipos resueltos: ' . $r->club_nombre . ' vs ' . $r->rival_nombre;
                    continue;
                }

                // ¿Ya hay un partido de esos equipos en esa fecha? (índice único de partidos)
                $ya = \App\Partido::where('fecha_id', $fecha->id)
                    ->where(function ($q) use ($equipolId, $equipovId) {
                        $q->where('equipol_id', $equipolId)->orWhere('equipov_id', $equipolId)
                            ->orWhere('equipol_id', $equipovId)->orWhere('equipov_id', $equipovId);
                    })->first();

                if ($ya) {
                    $errores[] = 'Choque en la fecha «' . $numero . '»: ya hay un partido de ' . $r->club_nombre
                        . ' ahí (partido #' . $ya->id . '). Ese quedó sin crear.';
                    continue;
                }

                $partido = new \App\Partido();
                $partido->forceFill([
                    'fecha_id'   => $fecha->id,
                    'dia'        => $r->dia,
                    'equipol_id' => $equipolId,
                    'equipov_id' => $equipovId,
                    'golesl'     => $golesl,
                    'golesv'     => $golesv,
                ])->save();

                DB::table('partido_tecnicos')->insert([
                    'partido_id' => $partido->id,
                    'equipo_id'  => (int) $r->equipo_id,
                    'tecnico_id' => (int) $tecnicoId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                DB::table('import_partidos')->where('id', $r->id)
                    ->update(['estado' => 'aplicado', 'partido_id' => $partido->id, 'motivo' => null, 'updated_at' => now()]);

                // Ojo: local y visitante son los que corresponden por venue, NO
                // "club dirigido vs rival". Imprimir el club siempre a la izquierda
                // hacía parecer que todos los partidos eran de local.
                $detalle .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td><td class="num">' . e($numero) . '</td>'
                    . '<td>' . e($this->nombreEquipo($equipolId)) . '</td>'
                    . '<td class="num">' . $golesl . ':' . $golesv . '</td>'
                    . '<td>' . e($this->nombreEquipo($equipovId)) . '</td>'
                    . '<td class="num">' . ($local ? 'L' : 'V') . '</td>'
                    . '<td class="num"><span class="id">#' . $partido->id . '</span> '
                    . $this->linkIncidencias($fecha->id) . '</td></tr>';
                $creados++;
            } catch (\Throwable $ex) {
                $errores[] = 'Error en ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ': ' . $ex->getMessage();
                Log::error('aplicarGrupo: ' . $ex->getMessage());
            }
        }

        // Contar equipos del grupo, para que el torneo no quede en 0
        $this->recontarEquipos($grupoId);

        $html = $volver . '<h1>Aplicados ' . $creados . ' partidos</h1>'
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · grupo #' . (int) $grupoId
            . ($torneo->parcial ? ' · <b>torneo parcial</b>' : '') . '</p>';

        if (!empty($errores)) {
            $html .= '<p class="err-box"><b>' . count($errores) . ' quedaron sin crear:</b><br>' . e(implode(' — ', $errores)) . '</p>';
        }
        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Fecha nº</th><th>Local</th><th>Res.</th><th>Visitante</th><th>El DT jugó de</th><th>Partido</th></tr></thead><tbody>'
                . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Seguir con el resto →</a></p>';

        return $this->pagina('Aplicar partidos', $html);
    }

    /** País del equipo dirigido en ese grupo (para saber de dónde es el torneo). */
    private function paisEquipo(array $g)
    {
        if (empty($g['equipo_id'])) return null;
        $e = \App\Equipo::select('pais')->find($g['equipo_id']);
        return $e ? trim((string) $e->pais) : null;
    }

    /** URL del alta de torneos con los datos del grupo ya cargados en los inputs. */
    private function urlCrearTorneo(array $g)
    {
        $nombre = $g['nombre'] ?: ('Competencia ' . $g['comp']);
        list($tipo, $ambito) = $this->clasificarCompetencia($nombre);

        // El año del torneo es el de los partidos, no la temporada de Transfermarkt.
        $anio = !empty($g['desde']) ? substr($g['desde'], 0, 4) : (string) $g['temp'];
        $anioFin = !empty($g['hasta']) ? substr($g['hasta'], 0, 4) : $anio;
        if ($anioFin !== $anio) $anio = $anio . '/' . substr($anioFin, -2);

        $params = [
            'nombre'     => $nombre,
            'year'       => $anio,
            'tipo'       => $tipo,
            'ambito'     => $ambito,
            'grupos'     => 1,
            'url_nombre' => Str::slug($nombre . '-' . $anio),
        ];

        if ($ambito === 'Internacional') {
            $params['region'] = $this->confederacion($nombre);
        } else {
            // Nacional: el país sale del equipo dirigido.
            $pais = 'Argentina';
            if (!empty($g['equipo_id'])) {
                $e = \App\Equipo::select('pais')->find($g['equipo_id']);
                if ($e && trim((string) $e->pais) !== '') $pais = $e->pais;
            }
            $params['pais'] = $pais;
        }

        return route('torneos.create', $params);
    }

    /** Confederación probable a partir del nombre de la competencia. */
    private function confederacion($nombre)
    {
        $n = $this->normalizaTexto($nombre);
        $mapa = [
            'Conmebol' => ['libertadores', 'sudamericana', 'recopa', 'merconorte', 'mercosur', 'conmebol'],
            'FIFA'     => ['intercontinental', 'mundial de clubes', 'club world', 'fifa'],
            'UEFA'     => ['champions', 'europa league', 'uefa', 'conference', 'supercopa de europa'],
            'Concacaf' => ['concacaf', 'concachampions'],
        ];
        foreach ($mapa as $conf => $claves) {
            foreach ($claves as $k) {
                if (strpos($n, $k) !== false) return $conf;
            }
        }
        return '';
    }

    /**
     * Localía y goles de una fila del staging, recalculados desde el JSON crudo.
     * Si no hay payload, cae a las columnas guardadas.
     */
    private function datosPartido($r)
    {
        $g = $r->payload ? json_decode($r->payload, true) : null;
        if (is_array($g) && !empty($g)) {
            $f = $this->normalizar($g, $r->coach_external_id);
            return ['local' => $f['local'], 'gf' => (int) $f['goles_favor'], 'gc' => (int) $f['goles_contra']];
        }
        return [
            'local' => $r->local === null ? null : ((int) $r->local === 1),
            'gf'    => (int) $r->goles_favor,
            'gc'    => (int) $r->goles_contra,
        ];
    }

    /**
     * Repara partidos ya aplicados cuya localía se guardó al revés.
     * Recalcula desde el JSON y da vuelta local/visitante y el resultado.
     */
    private function arreglarLocalia($tecnicoId)
    {
        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')
            ->whereNotNull('partido_id')->get();

        $corregidos = 0; $revisados = 0; $detalle = '';
        $sinPayload = 0; $payloadRoto = 0; $sinLocalia = 0; $diag = '';

        foreach ($filas as $r) {
            $revisados++;

            if (empty($r->payload)) {
                $sinPayload++;
            } elseif (!is_array(json_decode($r->payload, true))) {
                $payloadRoto++;
            }

            $datos = $this->datosPartido($r);

            if (count(explode('<tr>', $diag)) <= 6) {
                $p = \App\Partido::find($r->partido_id);
                $diag .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td>'
                    . '<td>' . e($r->club_nombre) . ' vs ' . e($r->rival_nombre) . '</td>'
                    . '<td class="num">' . ($datos['local'] === null ? '?' : ($datos['local'] ? 'L' : 'V'))
                    . ' ' . (int) $datos['gf'] . ':' . (int) $datos['gc'] . '</td>'
                    . '<td class="num">' . (empty($r->payload) ? 'sin payload' : (is_array(json_decode($r->payload, true)) ? 'ok' : 'roto')) . '</td>'
                    . '<td class="num">' . ($r->local === null ? 'null' : (int) $r->local) . '</td>'
                    . '<td>' . ($p ? e($this->nombreEquipo($p->equipol_id) . ' ' . $p->golesl . ':' . $p->golesv . ' ' . $this->nombreEquipo($p->equipov_id)) : 'sin partido') . '</td>'
                    . '</tr>';
            }

            if ($datos['local'] === null) { $sinLocalia++; continue; }
            if (!$r->equipo_id || !$r->rival_id) continue;

            $partido = \App\Partido::find($r->partido_id);
            if (!$partido) continue;

            $equipolId = $datos['local'] ? (int) $r->equipo_id : (int) $r->rival_id;
            $equipovId = $datos['local'] ? (int) $r->rival_id : (int) $r->equipo_id;
            $golesl    = $datos['local'] ? $datos['gf'] : $datos['gc'];
            $golesv    = $datos['local'] ? $datos['gc'] : $datos['gf'];

            if ((int) $partido->equipol_id === $equipolId && (int) $partido->equipov_id === $equipovId
                && (int) $partido->golesl === $golesl && (int) $partido->golesv === $golesv) {
                continue;   // ya estaba bien
            }

            $antes = $this->nombreEquipo($partido->equipol_id) . ' ' . $partido->golesl . ':' . $partido->golesv
                . ' ' . $this->nombreEquipo($partido->equipov_id);

            $partido->forceFill([
                'equipol_id' => $equipolId,
                'equipov_id' => $equipovId,
                'golesl'     => $golesl,
                'golesv'     => $golesv,
            ])->save();

            DB::table('import_partidos')->where('id', $r->id)
                ->update(['local' => $datos['local'] ? 1 : 0, 'updated_at' => now()]);

            $detalle .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td><td>' . e($antes) . '</td>'
                . '<td><b>' . e($this->nombreEquipo($equipolId) . ' ' . $golesl . ':' . $golesv . ' ' . $this->nombreEquipo($equipovId)) . '</b></td>'
                . '<td class="num">#' . $partido->id . '</td></tr>';
            $corregidos++;
        }

        $html = '<h1>Localía revisada</h1>'
            . '<p class="ok-box">Revisé ' . $revisados . ' partidos ya aplicados de este DT. '
            . ($corregidos ? ('Corregí <b>' . $corregidos . '</b>.') : 'No hubo nada que corregir.') . '</p>';

        if ($sinPayload || $payloadRoto || $sinLocalia) {
            $html .= '<p class="err-box">Ojo: ' . $sinPayload . ' sin payload · ' . $payloadRoto
                . ' con payload ilegible · ' . $sinLocalia . ' sin localía. '
                . 'En esos casos no puedo recalcular nada y por eso quedan como están.</p>';
        }

        if ($diag) {
            $html .= '<h2>Qué está comparando</h2>'
                . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Partido en TM</th>'
                . '<th>Recalculado</th><th>Payload</th><th>Columna local</th><th>Partido en tu base</th>'
                . '</tr></thead><tbody>' . $diag . '</tbody></table></div>';
        }

        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Estaba</th><th>Quedó</th><th>Partido</th></tr></thead><tbody>'
                . $detalle . '</tbody></table></div>';
        }
        return $html;
    }

    /** Agrega el partido_tecnico en partidos que ya estaban cargados sin este DT. */
    private function completarTecnicos($tecnicoId)
    {
        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'duplicado')
            ->where('motivo', 'like', '%falta el DT%')->get();

        $n = 0;
        foreach ($filas as $r) {
            if (!$r->partido_id || !$r->equipo_id) continue;
            $existe = DB::table('partido_tecnicos')
                ->where('partido_id', $r->partido_id)->where('equipo_id', $r->equipo_id)->exists();
            if ($existe) continue;
            DB::table('partido_tecnicos')->insert([
                'partido_id' => (int) $r->partido_id,
                'equipo_id'  => (int) $r->equipo_id,
                'tecnico_id' => (int) $tecnicoId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('import_partidos')->where('id', $r->id)->update(['motivo' => 'ya cargado', 'updated_at' => now()]);
            $n++;
        }
        return $n;
    }

    private function recontarEquipos($grupoId)
    {
        $ids = DB::table('partidos')
            ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->where('fechas.grupo_id', $grupoId)
            ->select('partidos.equipol_id AS a', 'partidos.equipov_id AS b')->get();
        $set = [];
        foreach ($ids as $r) { $set[$r->a] = true; $set[$r->b] = true; }
        unset($set[null]);
        DB::table('grupos')->where('id', $grupoId)->update(['equipos' => count($set)]);
    }

    private function clasificarCompetencia($nombre)
    {
        $n = mb_strtolower($this->normalizaTexto($nombre));
        $inter = ['libertadores', 'sudamericana', 'recopa', 'champions', 'mundial', 'intercontinental',
            'concacaf', 'club world', 'europa league', 'conference', 'merconorte', 'mercosur'];
        $ambito = 'Nacional';
        foreach ($inter as $k) if (strpos($n, $k) !== false) { $ambito = 'Internacional'; break; }
        $tipo = (strpos($n, 'copa') !== false || strpos($n, 'cup') !== false || $ambito === 'Internacional') ? 'Copa' : 'Liga';
        return [$tipo, $ambito];
    }

    private function normalizaTexto($s)
    {
        $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $s);
        return trim(mb_strtolower($c === false ? (string) $s : $c));
    }

    // ═══════════════════════════ FUENTE / CLASIFICACIÓN ═══════════════════════════

    /**
     * Reconstruye las filas desde import_partidos, sin tocar Transfermarkt.
     * Se usa después de mapear un club o de crear un equipo: los datos del DT
     * ya los bajamos una vez, no hace falta gastar otra llamada.
     */
    private function filasDesdeStaging($tecnicoId)
    {
        $filas = [];
        $rows = DB::table('import_partidos')->where('tecnico_id', $tecnicoId)->orderBy('dia', 'desc')->get();
        foreach ($rows as $r) {
            // Si tenemos el JSON crudo, lo volvemos a interpretar: así los arreglos
            // de lógica (localía, fechas, etc.) valen también para lo ya guardado,
            // sin tener que bajar todo de nuevo.
            $g = $r->payload ? json_decode($r->payload, true) : null;
            if (is_array($g) && !empty($g)) {
                $f = $this->normalizar($g, $r->coach_external_id);
                // Los nombres ya resueltos se conservan: no volvemos a pedirlos a tmapi.
                if ($r->competencia_nombre) $f['competencia_nombre'] = $r->competencia_nombre;
                if ($r->club_nombre)        $f['club_nombre']        = $r->club_nombre;
                if ($r->rival_nombre)       $f['rival_nombre']       = $r->rival_nombre;
                $f['aplicado'] = $r->estado === 'aplicado';
                $f['partido_aplicado'] = $r->partido_id;
                $filas[] = $f;
                continue;
            }

            $filas[] = [
                'external_id'             => $r->external_id,
                'competencia_external_id' => $r->competencia_external_id,
                'competencia_nombre'      => $r->competencia_nombre,
                'temporada'               => $r->temporada,
                'ronda'                   => $r->ronda,
                'arbitro_external_id'     => null,
                'club_external_id'        => $r->club_external_id,
                'club_nombre'             => $r->club_nombre,
                'rival_external_id'       => $r->rival_external_id,
                'rival_nombre'            => $r->rival_nombre,
                'local'                   => $r->local === null ? null : ((int) $r->local === 1),
                'dia'                     => $r->dia,
                'goles_favor'             => $r->goles_favor === null ? null : (int) $r->goles_favor,
                'goles_contra'            => $r->goles_contra === null ? null : (int) $r->goles_contra,
                'anio'                    => $r->dia ? substr($r->dia, 0, 4) : null,
                'equipo_id'               => null,
                'rival_id'                => null,
                'rival_real_id'           => null,
                'partido_id'              => null,
                'estado'                  => $r->estado === 'aplicado' ? 'aplicado' : 'nuevo',
                'motivo'                  => null,
                'payload'                 => $r->payload,
                'aplicado'                => $r->estado === 'aplicado',
                'partido_aplicado'        => $r->partido_id,
            ];
        }
        return $filas;
    }

    private function traerPartidos($coachId)
    {
        $resp = HttpHelper::getJson(self::TMAPI . "/coach/{$coachId}/performance-game");
        if (is_array($resp)) {
            if (isset($resp['data']['performance']) && is_array($resp['data']['performance']) && !empty($resp['data']['performance'])) {
                return $resp['data']['performance'];
            }
            if (isset($resp['performance']) && is_array($resp['performance']) && !empty($resp['performance'])) {
                return $resp['performance'];
            }
        }
        $err = HttpHelper::getLastJsonError();
        return 'tmapi no devolvió partidos para el coach ' . e($coachId) . '. Causa: '
            . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle');
    }

    private function completarNombres(array $filas)
    {
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
        return $filas;
    }

    private function clasificar(array $filas, $desde)
    {
        $mapaTm = $this->mapaTm();
        $mapaNombres = $this->mapaNombres();

        foreach ($filas as $i => $f) {
            $filas[$i]['equipo_id'] = null;
            $filas[$i]['rival_id'] = null;
            $filas[$i]['rival_real_id'] = null;
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
            $corrido = null;

            // Partidos postergados: TM guarda la fecha original y vos la fecha real.
            // Se buscan por par de equipos + localía + resultado exacto en una ventana amplia.
            if (!$partido && $equipoId && $rivalId) {
                $partido = $this->buscarPartidoAplazado($equipoId, $rivalId, $f['dia'], $f['local'],
                    (int) $f['goles_favor'], (int) $f['goles_contra'], $f['ronda']);
                if ($partido) {
                    $corrido = (int) round((strtotime(substr($partido->dia, 0, 10)) - strtotime(substr($f['dia'], 0, 10))) / 86400);
                }
            }

            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $tieneDt = DB::table('partido_tecnicos')
                    ->where('partido_id', $partido->id)->where('equipo_id', $equipoId)->exists();
                $filas[$i]['motivo'] = $tieneDt ? 'ya cargado' : 'ya cargado, le falta el DT';
                if ($corrido !== null) {
                    $filas[$i]['motivo'] .= ' · fecha corrida ' . ($corrido > 0 ? '+' : '') . $corrido
                        . ' días (tu base: ' . substr($partido->dia, 0, 10) . ')';
                    $filas[$i]['corrido'] = $corrido;
                }
            } elseif (!$equipoId || !$rivalId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$equipoId) $faltan[] = 'club «' . $f['club_nombre'] . '»';
                if (!$rivalId)  $faltan[] = 'rival «' . $f['rival_nombre'] . '»';
                $filas[$i]['motivo'] = 'sin mapear: ' . implode(' / ', $faltan);
            } else {
                // Antes de darlo por nuevo: ¿ese día el club ya jugó contra OTRO equipo?
                // Si sí, el partido existe y el que está mal es el mapeo del rival.
                $otro = $this->partidoDelDia($equipoId, $f['dia']);
                if ($otro) {
                    $rivalReal = ((int) $otro->equipol_id === (int) $equipoId) ? $otro->equipov_id : $otro->equipol_id;
                    $filas[$i]['estado'] = 'conflicto';
                    $filas[$i]['partido_id'] = $otro->id;
                    $filas[$i]['rival_real_id'] = $rivalReal;
                    $filas[$i]['motivo'] = 'ese día ya tenés el partido #' . $otro->id . ' contra '
                        . $this->nombreEquipo($rivalReal) . ' (#' . $rivalReal . '), no contra «' . $f['rival_nombre']
                        . '» (#' . $rivalId . '): el mapeo del rival está mal';
                } elseif ($f['local'] === null) {
                    // Sin localía no se puede crear: quedaría el resultado dado vuelta.
                    $filas[$i]['estado'] = 'conflicto';
                    $filas[$i]['motivo'] = 'no se pudo determinar si fue local o visitante';
                } else {
                    $filas[$i]['estado'] = 'nuevo';
                }
            }
        }
        return $filas;
    }

    /**
     * Busca un partido postergado: mismo par de equipos, misma localía y el
     * MISMO resultado, dentro de una ventana amplia (±150 días).
     *
     * Si hay más de un candidato, desempata por el número de fecha. Si sigue
     * habiendo empate, no devuelve nada: mejor que quede como conflicto.
     */
    private function buscarPartidoAplazado($equipoId, $rivalId, $dia, $local, $gf, $gc, $ronda)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -150 days'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +150 days'));

        $q = \App\Partido::whereBetween('dia', [$d0, $d1]);
        if ($local === true) {
            $q->where('equipol_id', $equipoId)->where('equipov_id', $rivalId)
                ->where('golesl', $gf)->where('golesv', $gc);
        } elseif ($local === false) {
            $q->where('equipol_id', $rivalId)->where('equipov_id', $equipoId)
                ->where('golesl', $gc)->where('golesv', $gf);
        } else {
            // Sin localía conocida: cualquiera de los dos órdenes, con el resultado que corresponda.
            $q->where(function ($w) use ($equipoId, $rivalId, $gf, $gc) {
                $w->where(function ($x) use ($equipoId, $rivalId, $gf, $gc) {
                    $x->where('equipol_id', $equipoId)->where('equipov_id', $rivalId)
                        ->where('golesl', $gf)->where('golesv', $gc);
                })->orWhere(function ($x) use ($equipoId, $rivalId, $gf, $gc) {
                    $x->where('equipol_id', $rivalId)->where('equipov_id', $equipoId)
                        ->where('golesl', $gc)->where('golesv', $gf);
                });
            });
        }
        $cands = $q->get();

        if ($cands->count() === 1) return $cands->first();
        if ($cands->isEmpty()) return null;

        // Desempate por número de fecha
        if ($ronda !== null && $ronda !== '') {
            $porRonda = $cands->filter(function ($p) use ($ronda) {
                $fecha = \App\Fecha::find($p->fecha_id);
                if (!$fecha) return false;
                return (int) preg_replace('/\D/', '', (string) $fecha->numero) === (int) $ronda;
            });
            if ($porRonda->count() === 1) return $porRonda->first();
        }
        return null;
    }

    /** Cualquier partido de ese equipo ese día, sin importar el rival. */
    private function partidoDelDia($equipoId, $dia)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -1 day'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +1 day'));
        return \App\Partido::whereBetween('dia', [$d0, $d1])
            ->where(function ($q) use ($equipoId) {
                $q->where('equipol_id', $equipoId)->orWhere('equipov_id', $equipoId);
            })->first();
    }

    private function nombreEquipo($id)
    {
        static $cache = [];
        if (!$id) return '?';
        if (!isset($cache[$id])) {
            $e = \App\Equipo::select('nombre')->find($id);
            $cache[$id] = $e ? $e->nombre : ('#' . $id);
        }
        return $cache[$id];
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

    private function aprenderMapeos(array $filas)
    {
        $mapaTm = $this->mapaTm();
        $aprendidos = [];

        foreach ($filas as $f) {
            if ($f['estado'] === 'excluido' || !$f['dia']) continue;

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
            if ($f['equipo_id'] && $f['rival_id']) continue;

            $conocidoEsClub = (bool) $f['equipo_id'];
            $conocido = $f['equipo_id'] ?: $f['rival_id'];
            $tmDesconocido = $conocidoEsClub ? $f['rival_external_id'] : $f['club_external_id'];
            $nombreDesconocido = $conocidoEsClub ? $f['rival_nombre'] : $f['club_nombre'];
            if (!$conocido || !$tmDesconocido) continue;
            if (isset($mapaTm[(string) $tmDesconocido])) continue;

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
                    if ($clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) $ok[] = $p->equipov_id;
                    elseif (!$clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) $ok[] = $p->equipol_id;
                } else {
                    if ($clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) $ok[] = $p->equipol_id;
                    elseif (!$clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) $ok[] = $p->equipov_id;
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

    /**
     * Nombre normalizado -> equipo_id. Si dos equipos comparten la misma clave
     * (pasa con los homónimos), la clave se marca ambigua y no matchea con nadie:
     * mejor un conflicto para resolver a mano que un partido con el rival cambiado.
     */
    private function mapaNombres()
    {
        $mapa = [];
        foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
            foreach ($this->clavesNombre($e->nombre) as $k) {
                if ($k === '') continue;
                if (isset($mapa[$k]) && $mapa[$k] !== $e->id) {
                    $mapa[$k] = null;      // ambigua
                } elseif (!array_key_exists($k, $mapa)) {
                    $mapa[$k] = $e->id;
                }
            }
        }
        return $mapa;
    }

    private function resolverClub($tmId, $nombre, array $mapaTm, array $mapaNombres)
    {
        if ($tmId !== null && isset($mapaTm[(string) $tmId])) return $mapaTm[(string) $tmId];
        foreach ($this->clavesNombre($nombre) as $k) {
            if ($k !== '' && isset($mapaNombres[$k]) && $mapaNombres[$k] !== null) return $mapaNombres[$k];
        }
        return null;
    }

    /**
     * Claves normalizadas de un nombre de club.
     *
     * REGLA IMPORTANTE: si el nombre trae un paréntesis aclaratorio —"Sarmiento (Junín)",
     * "Central Córdoba (SdE)"— ese paréntesis es parte del nombre y NO se descarta.
     * Sin esta regla, "CA Sarmiento (Junín)" matchea contra un "Sarmiento" cualquiera
     * y termina creando partidos con el rival equivocado.
     */
    private function clavesNombre($nombre)
    {
        $nombre = (string) $nombre;
        if (trim($nombre) === '') return [];

        $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($base === false) $base = $nombre;
        $base = mb_strtolower($base);

        // Los paréntesis se aplanan (pasan a ser texto), no se borran.
        $base = str_replace(['(', ')', '.', ','], ' ', $base);

        $claves = [
            $this->soloLetras($base),
            $this->soloLetras($this->quitarPrefijos($base)),
        ];
        return array_values(array_unique(array_filter($claves)));
    }

    private function quitarPrefijos($str)
    {
        $str = preg_replace('/\b(c\.?a\.?|a\.?a\.?|c\.?s\.?|c\.?d\.?|c\.?s\.?d\.?|a\.?c\.?|s\.?c\.?|f\.?c\.?|c\.?f\.?|c\.?b\.?|s\.?a\.?d\.?)\b/u', ' ', $str);
        $str = preg_replace('/\b(club|atletico|atletica|deportivo|deportiva|deportes|asociacion|association|sportivo|sporting|social|futbol|football|de|del|la|el)\b/u', ' ', $str);
        return $str;
    }

    private function soloLetras($str)
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $str);
    }

    private function normalizar(array $g, $coachId)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

        // En performance-game, `club` es SIEMPRE el equipo del DT y `opponent` el rival.
        // La localía no sale del orden: sale del campo `venue` ("home" / "away").
        $club  = isset($ci['club']) ? $ci['club'] : [];
        $rival = isset($ci['opponent']) ? $ci['opponent'] : [];

        // Por las dudas, si el coachId apareciera del lado del rival, damos vuelta.
        if ((string) $this->valor($rival, ['coachId']) === (string) $coachId
            && (string) $this->valor($club, ['coachId']) !== (string) $coachId) {
            $club  = isset($ci['opponent']) ? $ci['opponent'] : [];
            $rival = isset($ci['club']) ? $ci['club'] : [];
        }

        $venue = mb_strtolower(trim((string) $this->valor($club, ['venue'])));
        if ($venue === '') {
            // Si el rival dice dónde jugó, alcanza: es al revés del nuestro.
            $venueRival = mb_strtolower(trim((string) $this->valor($rival, ['venue'])));
            $venue = $venueRival === '' ? '' : ($this->esLocal($venueRival) ? 'away' : 'home');
        }
        $local = $venue === '' ? null : $this->esLocal($venue);

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
            'competencia_nombre'      => $this->valor($gi, ['competitionName']),
            'temporada'               => $this->texto($temporada),
            'ronda'                   => $this->texto($this->valor($gi, ['gameDay', 'matchDay', 'round'])),
            'arbitro_external_id'     => $this->texto($this->valor($gi, ['refereeId'])),
            'club_external_id'        => $this->texto($this->valor($club, ['clubId', 'id'])),
            'club_nombre'             => $this->valor($club, ['name', 'clubName']),
            'rival_external_id'       => $this->texto($this->valor($rival, ['clubId', 'id'])),
            'rival_nombre'            => $this->valor($rival, ['name', 'clubName']),
            'local'                   => $local,
            'dia'                     => $dia,
            'goles_favor'             => $gf === null ? null : (int) $gf,
            'goles_contra'            => $gc === null ? null : (int) $gc,
            'anio'                    => $dia ? substr($dia, 0, 4) : null,
            'equipo_id'               => null,
            'rival_id'                => null,
            'rival_real_id'           => null,
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

        // Una fila ya aplicada no se pisa… salvo que el partido que había creado
        // ya no exista (lo borraste a mano). En ese caso vuelve a estar disponible.
        $aplicada = DB::table('import_partidos')->where($clave)->where('estado', 'aplicado')->first();
        if ($aplicada) {
            $sigue = $aplicada->partido_id && \App\Partido::where('id', $aplicada->partido_id)->exists();
            if ($sigue) return false;
            DB::table('import_partidos')->where('id', $aplicada->id)
                ->update(['estado' => 'nuevo', 'partido_id' => null, 'motivo' => null, 'updated_at' => now()]);
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

    /** Interpreta el campo venue de Transfermarkt: home / away (y variantes). */
    private function esLocal($venue)
    {
        $v = mb_strtolower(trim((string) $venue));
        if ($v === '') return null;
        if (strpos($v, 'home') !== false || strpos($v, 'local') !== false || $v === 'h' || $v === '1') return true;
        if (strpos($v, 'away') !== false || strpos($v, 'guest') !== false || strpos($v, 'visit') !== false
            || $v === 'a' || $v === '2') return false;
        if (strpos($v, 'neutral') !== false) return true;   // cancha neutral: lo dejamos como local
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

    // ═══════════════════════════════ VISTAS ═══════════════════════════════

    private function urlBase(Request $request)
    {
        $q = $request->query();
        unset($q['guardar'], $q['aprender'], $q['estado'], $q['limite'], $q['cache'],
            $q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre'], $q['remapear'],
            $q['excluir_comp'], $q['incluir_comp']);
        return $request->url() . '?' . http_build_query($q);
    }

    /**
     * Clubes que SÍ están mapeados pero cuyo mapeo contradice un partido ya cargado.
     * Un clic corrige el mapeo apuntándolo al rival que realmente jugó ese día.
     */
    private function bloqueMapeosSospechosos(array $filas, Request $request)
    {
        $mal = [];
        foreach ($filas as $f) {
            if ($f['estado'] !== 'conflicto' || empty($f['rival_real_id'])) continue;
            $k = (string) $f['rival_external_id'];
            if ($k === '') continue;
            if (!isset($mal[$k])) {
                $mal[$k] = ['nombre' => $f['rival_nombre'], 'actual' => $f['rival_id'],
                    'real' => $f['rival_real_id'], 'n' => 0];
            }
            $mal[$k]['n']++;
        }
        if (empty($mal)) return '';

        $out = '<h2 class="err">Mapeos que no cierran <span class="sub">(' . count($mal) . ')</span></h2>'
            . '<p class="sub">Estos clubes están mapeados a un equipo tuyo, pero el partido de esa fecha en tu base '
            . 'es contra otro rival. Casi siempre es un homónimo mal enganchado. Corregilo y el partido pasa a «ya cargado».</p>'
            . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Mapeado hoy a</th>'
            . '<th>Debería ser</th><th>Partidos</th><th></th></tr></thead><tbody>';

        foreach ($mal as $tmId => $d) {
            $q = $request->query();
            unset($q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre']);
            $q['mapear_tm'] = $tmId;
            $q['mapear_nombre'] = $d['nombre'];
            $q['mapear_equipo'] = $d['real'];
            $q['cache'] = 1;
            $href = $request->url() . '?' . http_build_query($q);

            $out .= '<tr class="err"><td>' . e($d['nombre']) . '</td><td class="num">' . e($tmId) . '</td>'
                . '<td>' . e($this->nombreEquipo($d['actual'])) . ' <span class="id">#' . (int) $d['actual'] . '</span></td>'
                . '<td><b>' . e($this->nombreEquipo($d['real'])) . '</b> <span class="id">#' . (int) $d['real'] . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td><a class="boton" href="' . e($href) . '">Corregir</a></td></tr>';
        }
        return $out . '</tbody></table></div>';
    }


    /**
     * Clubes de este fixture que YA están resueltos: a qué equipo tuyo apuntan,
     * de dónde sale el enganche y cómo reapuntarlo.
     *
     * Es lo único que «Clubes sin mapear» no deja hacer —ahí sólo aparecen los
     * que faltan—, y sin esto un mapeo equivocado sólo se corrige metiendo mano
     * en la base.
     *
     * Dos señales para mirar antes de aplicar una fecha:
     *   · «por nombre»: el club NO está en equipo_tm, matcheó por el nombre
     *     normalizado. Anda hasta que aparece un homónimo. «Fijar» lo clava.
     *   · el mismo equipo tuyo apuntado por dos clubes de TM distintos: uno de
     *     los dos está mal, y los partidos de ambos van a caer sobre el mismo.
     *
     * El select de equipos se arma sólo para la fila que se está editando
     * (?remapear=<idTM>): son cientos de equipos y una lista por fila hincharía
     * la página al pedo.
     */
    private function bloqueClubesMapeados(array $filas, Request $request)
    {
        $mapaTm = $this->mapaTm();

        $clubes = [];
        foreach ($filas as $f) {
            foreach ([[$f['club_external_id'], $f['club_nombre'], $f['equipo_id']],
                         [$f['rival_external_id'], $f['rival_nombre'], $f['rival_id']]] as $c) {
                if (empty($c[0]) || empty($c[2])) continue;
                $k = (string) $c[0];
                if (!isset($clubes[$k])) {
                    $clubes[$k] = ['nombre' => $c[1], 'equipo_id' => (int) $c[2],
                        'fijo' => isset($mapaTm[$k]), 'n' => 0];
                }
                $clubes[$k]['n']++;
            }
        }
        if (empty($clubes)) return '';

        $porEquipo = [];
        foreach ($clubes as $k => $d) $porEquipo[$d['equipo_id']][] = $k;

        $duplicados = 0;
        foreach ($porEquipo as $lista) if (count($lista) > 1) $duplicados++;
        $porNombre = 0;
        foreach ($clubes as $d) if (!$d['fijo']) $porNombre++;

        uasort($clubes, function ($a, $b) { return strcmp((string) $a['nombre'], (string) $b['nombre']); });

        $editando = trim((string) $request->get('remapear', ''));

        // Query base de links y formularios: sin las claves de acción, para no
        // repetir un guardado ni volver a pisar horarios al navegar.
        $limpia = $request->query();
        foreach (['mapear_tm', 'mapear_equipo', 'mapear_nombre', 'guardar', 'aprender', 'refrescar', 'remapear'] as $k) {
            unset($limpia[$k]);
        }
        $limpia['cache'] = 1;
        foreach ($limpia as $k => $v) if (is_array($v)) unset($limpia[$k]);

        $opciones = '';
        if ($editando !== '') {
            $actual = isset($clubes[$editando]) ? $clubes[$editando]['equipo_id'] : 0;
            foreach (\App\Equipo::select('id', 'nombre', 'pais')->orderBy('nombre')->get() as $e) {
                $opciones .= '<option value="' . $e->id . '"' . ((int) $e->id === (int) $actual ? ' selected' : '') . '>'
                    . e($e->nombre) . ($e->pais ? ' (' . e($e->pais) . ')' : '') . '</option>';
            }
        }

        $out = '<details' . ($editando !== '' || $duplicados ? ' open' : '') . '>'
            . '<summary>Clubes ya mapeados <span class="sub">(' . count($clubes) . ')</span>'
            . ($duplicados ? ' <span class="err">· ' . $duplicados . ' equipo(s) con dos clubes apuntando</span>' : '')
            . '</summary>'
            . '<p class="sub">A qué equipo tuyo apunta cada club de Transfermarkt de este fixture. '
            . '<b>Cambiar</b> reapunta el mapeo y relee el staging: no toca ningún partido tuyo y, si ya guardaste '
            . 'en staging, tampoco vuelve a bajar de TM. Ojo: los partidos <b>ya creados</b> con el mapeo viejo '
            . 'no se arreglan solos, hay que borrarlos a mano.</p>';

        if ($porNombre) {
            $out .= '<p class="sub"><b>' . $porNombre . '</b> enganchados «por nombre»: no están en '
                . '<code>equipo_tm</code>, matchean por el nombre normalizado. Andan, pero el día que aparezca '
                . 'un homónimo dejan de andar. «Fijar» los clava al equipo que ya tienen.</p>';
        }

        $out .= '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Partidos</th>'
            . '<th>Apunta a</th><th>Enganche</th><th></th></tr></thead><tbody>';

        foreach ($clubes as $tmId => $d) {
            $rep = count($porEquipo[$d['equipo_id']]) > 1;

            $out .= '<tr' . ($rep ? ' class="warn"' : '') . '>'
                . '<td>' . e($d['nombre']) . '</td>'
                . '<td class="num">' . $this->linkClubTm($tmId) . '</td>'
                . '<td class="num">' . $d['n'] . '</td>';

            if ((string) $editando === (string) $tmId) {
                $out .= '<td colspan="3"><form method="get" action="' . e($request->url()) . '">';
                foreach ($limpia as $k => $v) {
                    $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
                }
                $out .= '<input type="hidden" name="mapear_tm" value="' . e($tmId) . '">'
                    . '<input type="hidden" name="mapear_nombre" value="' . e($d['nombre']) . '">'
                    . '<select name="mapear_equipo" class="s2" data-placeholder="buscar equipo…">' . $opciones . '</select> '
                    . '<button>Guardar mapeo</button>'
                    . '<a class="boton-sec" href="' . e($request->url() . '?' . http_build_query($limpia)) . '">cancelar</a>'
                    . '</form></td>';
            } else {
                $q = $limpia; $q['remapear'] = $tmId;

                $out .= '<td>' . e($this->nombreEquipo($d['equipo_id']))
                    . ' <span class="id">#' . $d['equipo_id'] . '</span>'
                    . ($rep ? ' <span class="err" title="otro club de TM apunta al mismo equipo">· repetido</span>' : '')
                    . '</td>'
                    . '<td>' . ($d['fijo'] ? '<span class="sub">id TM</span>' : '<span class="warn">por nombre</span>') . '</td>'
                    . '<td><a class="boton-sec" href="' . e($request->url() . '?' . http_build_query($q)) . '">Cambiar</a>';

                if (!$d['fijo']) {
                    $q2 = $limpia;
                    $q2['mapear_tm'] = $tmId;
                    $q2['mapear_nombre'] = $d['nombre'];
                    $q2['mapear_equipo'] = $d['equipo_id'];
                    $out .= '<a class="boton-sec" href="' . e($request->url() . '?' . http_build_query($q2)) . '">Fijar</a>';
                }
                $out .= '</td>';
            }
            $out .= '</tr>';
        }

        return $out . '</tbody></table></div></details>';
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

        $opciones = '<option value=""></option>';
        foreach (\App\Equipo::select('id', 'nombre', 'pais')->orderBy('nombre')->get() as $e) {
            $opciones .= '<option value="' . $e->id . '">' . e($e->nombre)
                . ($e->pais ? ' (' . e($e->pais) . ')' : '') . '</option>';
        }

        $out = '<h2>Clubes sin mapear <span class="sub">(' . count($pend) . ')</span></h2>'
            . '<p class="sub">Elegí el equipo y guardá: queda mapeado por su id de Transfermarkt y no se vuelve a preguntar nunca más. '
            . 'Si el club <b>no existe</b> en tu base, «Crear desde TM» lo da de alta con nombre, siglas, país y escudo, '
            . 'lo mapea solo y te deja en la edición para completar fundación, estadio, socios e historia '
            . '(eso Transfermarkt no lo tiene). Cuesta 2 llamadas. «En blanco» abre el alta de siempre. '
            . 'Cuando volvés acá y refrescás, el club ya aparece resuelto '
            . '<b>sin volver a bajar nada de Transfermarkt</b>.</p>'
            . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Partidos</th><th>Nuestro equipo</th></tr></thead><tbody>';

        foreach ($pend as $tmId => $d) {
            $out .= '<tr><td>' . e($d['nombre']) . '</td>'
                . '<td class="num">' . $this->linkClubTm($tmId) . '</td><td class="num">' . $d['n'] . '</td>'
                . '<td><form method="get" action="' . e($request->url()) . '">';
            foreach ($request->query() as $k => $v) {
                if (in_array($k, ['mapear_tm', 'mapear_equipo', 'mapear_nombre', 'guardar', 'aprender'], true)) continue;
                if (is_array($v)) continue;
                $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
            }
            $out .= '<input type="hidden" name="mapear_tm" value="' . e($tmId) . '">'
                . '<input type="hidden" name="mapear_nombre" value="' . e($d['nombre']) . '">'
                . '<input type="hidden" name="cache" value="1">'
                . '<select name="mapear_equipo" class="s2" data-placeholder="buscar equipo…">' . $opciones . '</select>'
                . ' <button>Mapear</button></form>'
                . '<a class="boton-sec" target="_blank" href="' . e(route('import_partidos.crear_equipo',
                    ['tm_id' => $tmId, 'volver' => $request->fullUrl()])) . '">Crear desde TM ↗</a>'
                . '<a class="boton-sec" href="' . e(route('equipos.create')) . '" target="_blank">En blanco ↗</a>'
                . '</td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    /**
     * El id de Transfermarkt del club, linkeado a su perfil.
     *
     * El slug no hace falta: TM acepta cualquier cosa antes de
     * `/startseite/verein/{id}` y redirige al club que corresponde.
     */
    private function linkClubTm($tmId)
    {
        $tmId = trim((string) $tmId);
        if ($tmId === '') return '—';

        $u = $this->urlsClubTm($tmId, null);
        return '<a href="' . e($u['perfil']) . '" target="_blank" rel="noopener" '
            . 'title="Ver este club en Transfermarkt">' . e($tmId) . ' ↗</a>';
    }

    private function diagnosticar(array $game)
    {
        $lineas = [];
        $lineas[] = '<strong>Claves del partido:</strong> <code>' . e(implode(', ', array_keys($game))) . '</code>';
        foreach (['gameInformation', 'clubsInformation', 'statistics'] as $sub) {
            if (isset($game[$sub]) && is_array($game[$sub])) {
                $lineas[] = '<strong>' . $sub . ':</strong> <code>' . e(implode(', ', array_keys($game[$sub]))) . '</code>';
            }
        }
        $ci = isset($game['clubsInformation']) ? $game['clubsInformation'] : [];
        $lineas[] = '<strong>venue del primer partido:</strong> club = <code>'
            . e((string) $this->valor(isset($ci['club']) ? $ci['club'] : [], ['venue'])) . '</code> · opponent = <code>'
            . e((string) $this->valor(isset($ci['opponent']) ? $ci['opponent'] : [], ['venue'])) . '</code>'
            . ' <span class="sub">(de acá sale local/visitante)</span>';

        $lineas[] = '<details><summary>JSON crudo del primer partido</summary><pre>'
            . e(json_encode($game, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';
        return '<div class="diag">' . implode('<br>', $lineas) . '</div>';
    }

    /**
     * Parte las filas en las que van (1ra división) y las que no.
     *
     * Devuelve [filas, fuera], donde `fuera` viene agrupado por competencia:
     * comp => ['nombre', 'n', 'motivo', 'origen'].
     *
     * El chequeo del equipo («... II», «U20») es una red de seguridad para
     * cuando el nombre de la competencia no delata que es reserva. No corre si
     * el usuario marcó la competencia a mano como de 1ra: su decisión manda.
     */
    private function separarPorNivel(array $filas)
    {
        $dentro = [];
        $fuera  = [];
        $cache  = [];

        foreach ($filas as $f) {
            $nombre = (string) ($f['competencia_nombre'] ?: '');
            if (!isset($cache[$nombre])) $cache[$nombre] = NivelCompetencia::decidir($nombre);
            $d = $cache[$nombre];

            if (!$d['excluida'] && $d['origen'] !== 'manual'
                && (NivelCompetencia::esEquipoAlternativo($f['club_nombre'])
                    || NivelCompetencia::esEquipoAlternativo($f['rival_nombre']))) {
                $d = ['excluida' => true, 'motivo' => 'equipo alternativo (reserva / juveniles)', 'origen' => 'auto'];
            }

            if (!$d['excluida']) { $dentro[] = $f; continue; }

            $k = (string) $f['competencia_external_id'];
            if (!isset($fuera[$k])) {
                $fuera[$k] = ['nombre' => $nombre ?: ('#' . $k), 'n' => 0,
                    'motivo' => $d['motivo'], 'origen' => $d['origen']];
            }
            $fuera[$k]['n']++;
        }

        return [$dentro, $fuera];
    }

    /**
     * Qué competencias entraron y cuáles quedaron afuera por no ser de 1ra.
     *
     * «Excluir» guarda una regla `contiene` en `competencias_excluidas` (sin el
     * año, así sirve para todas las temporadas) y vale para todo el sistema.
     * «Sí es de 1ra» apaga las reglas que la tapaban y deja una regla APAGADA
     * con su nombre: esa marca le gana a la lista automática del servicio.
     */
    private function bloqueCompetencias(array $filas, array $fuera, Request $request)
    {
        $dentro = [];
        foreach ($filas as $f) {
            $k = (string) $f['competencia_external_id'];
            if (!isset($dentro[$k])) {
                $dentro[$k] = ['nombre' => (string) ($f['competencia_nombre'] ?: ('#' . $k)), 'n' => 0];
            }
            $dentro[$k]['n']++;
        }
        if (empty($dentro) && empty($fuera)) return '';

        uasort($dentro, function ($a, $b) { return $b['n'] - $a['n']; });
        uasort($fuera,  function ($a, $b) { return $b['n'] - $a['n']; });

        $limpia = $request->query();
        foreach (['mapear_tm', 'mapear_equipo', 'mapear_nombre', 'guardar', 'aprender',
                     'remapear', 'excluir_comp', 'incluir_comp', 'estado'] as $k) {
            unset($limpia[$k]);
        }
        foreach ($limpia as $k => $v) if (is_array($v)) unset($limpia[$k]);

        // Excluir puede leerse del staging: los partidos que quedan ya los tenemos.
        // Incluir NO: los de esa competencia se borraron del staging, hay que
        // volver a pedirlos a Transfermarkt.
        $urlExcluir = function ($nombre) use ($request, $limpia) {
            $q = array_merge($limpia, ['cache' => 1, 'excluir_comp' => $nombre]);
            return $request->url() . '?' . http_build_query($q);
        };
        $urlIncluir = function ($nombre) use ($request, $limpia) {
            $q = $limpia;
            unset($q['cache']);
            $q = array_merge($q, ['aprender' => 1, 'guardar' => 1, 'incluir_comp' => $nombre]);
            return $request->url() . '?' . http_build_query($q);
        };

        $nFuera = 0;
        foreach ($fuera as $g) $nFuera += $g['n'];

        $out = '<details' . (empty($fuera) ? '' : ' open') . '>'
            . '<summary>Competencias del sondeo <span class="sub">(' . count($dentro) . ' de 1ra'
            . (empty($fuera) ? '' : ' · ' . count($fuera) . ' afuera, ' . $nFuera . ' partidos') . ')</span></summary>'
            . '<p class="sub">Solo se cargan los torneos de <b>primera división</b>. Reserva, Proyección, juveniles y '
            . 'ascenso quedan afuera: no se listan abajo, no se guardan en staging y sus clubes no piden mapeo. '
            . 'Si me equivoqué con alguna, dale al botón: la decisión queda guardada en '
            . '<a href="' . e(route('competencias_excluidas.index')) . '" target="_blank">Competencias excluidas ↗</a> '
            . 'y vale para todos los DTs.</p>'
            . '<div class="scroll"><table><thead><tr><th>Competencia</th><th>Partidos</th><th>Estado</th>'
            . '<th></th></tr></thead><tbody>';

        foreach ($dentro as $k => $d) {
            $out .= '<tr>'
                . '<td>' . e($d['nombre']) . ' <span class="id">' . e($k) . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td class="ok">se carga</td>'
                . '<td><a class="boton-sec" href="' . e($urlExcluir($d['nombre'])) . '">No es de 1ra ✕</a></td>'
                . '</tr>';
        }
        foreach ($fuera as $k => $d) {
            $origen = $d['origen'] === 'regla' ? 'regla guardada' : 'automático';
            $out .= '<tr class="gris">'
                . '<td>' . e($d['nombre']) . ' <span class="id">' . e($k) . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td>fuera: ' . e($d['motivo']) . ' <span class="id">(' . e($origen) . ')</span></td>'
                . '<td><a class="boton-sec" href="' . e($urlIncluir($d['nombre'])) . '">Sí es de 1ra ✓</a></td>'
                . '</tr>';
        }

        return $out . '</tbody></table></div>'
            . '<p class="sub"><b>«Sí es de 1ra» vuelve a bajar los partidos de Transfermarkt</b> (1 llamada): '
            . 'los de esa competencia ya no están en el staging.</p></details>';
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

    /**
     * partido_id => fecha_id, para poder linkear a "Datos complementarios"
     * (`fechas.show`), que es la pantalla desde donde se cargan alineaciones,
     * goles, tarjetas, jueces, sustituciones y penales de cada partido.
     */
    private function mapaFechas(array $partidoIds)
    {
        $mapa = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $partidoIds))));
        foreach (array_chunk($ids, 500) as $trozo) {
            foreach (DB::table('partidos')->whereIn('id', $trozo)->select('id', 'fecha_id')->get() as $p) {
                $mapa[(int) $p->id] = (int) $p->fecha_id;
            }
        }
        return $mapa;
    }

    /** Link a las incidencias del partido. Vacío si no sabemos la fecha. */
    private function linkIncidencias($fechaId, $texto = 'Incidencias')
    {
        if (!$fechaId) return '';
        return '<a href="' . e(route('fechas.show', (int) $fechaId)) . '" target="_blank">' . e($texto) . '</a>';
    }

    private function tabla(array $filas, $limite, $filtro = '')
    {
        $out = '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Año</th><th>Temp. TM</th><th>Fecha nº</th><th>Club</th><th></th><th>Rival</th>'
            . '<th>Res.</th><th>gameId</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';

        // Para los que ya están en la base, link directo a sus incidencias.
        $idsPartido = [];
        foreach ($filas as $f) if (!empty($f['partido_id'])) $idsPartido[] = $f['partido_id'];
        $fechas = $this->mapaFechas($idsPartido);

        $n = 0;
        foreach ($filas as $f) {
            if ($filtro !== '' && $f['estado'] !== $filtro) continue;
            if ($n++ >= $limite) break;
            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'conflicto' ? 'err' : ($f['estado'] === 'excluido' ? 'gris' : ''));
            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id'])) . '</td>'
                . '<td class="num"><b>' . e(isset($f['anio']) ? $f['anio'] : '') . '</b></td>'
                . '<td class="num gris">' . e($f['temporada']) . '</td>'
                . '<td class="num">' . e($f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . ($f['local'] === null ? '<span class="err">?</span>' : ($f['local'] ? 'L' : 'V')) . '</td>'
                . '<td>' . e($f['rival_nombre']) . ($f['rival_id'] ? ' <span class="id">#' . $f['rival_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . e($f['goles_favor']) . ':' . e($f['goles_contra']) . '</td>'
                . '<td class="num">' . e($f['external_id'] ?: '—') . '</td>'
                . '<td>' . e($f['estado']) . '</td>'
                . '<td>' . e($f['motivo'])
                . ($f['partido_id']
                    ? ' <span class="id">partido #' . $f['partido_id'] . '</span> '
                    . $this->linkIncidencias(isset($fechas[(int) $f['partido_id']]) ? $fechas[(int) $f['partido_id']] : null)
                    : '') . '</td>'
                . '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    /**
     * Estas pantallas se arman como HTML acá y se muestran dentro del layout
     * de administración (`resources/views/import/pagina.blade.php`), para tener
     * el menú de siempre. El CSS vive allá, prefijado con `.import-tm`.
     */
    private function pagina($titulo, $cuerpo)
    {
        return view('import.pagina', ['titulo' => $titulo, 'cuerpo' => $cuerpo]);
    }
}
