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

    /**
     * Corrimiento de fecha que se acepta a ciegas como "el mismo partido, otro
     * día": la misma ventana que usa el buscador de gameId (TmBuscarGameId::DIAS).
     * Más que esto ya no alcanza el resultado para reconocerlo — hace falta
     * confirmar el torneo o el número de fecha. Ver el caso de los tres
     * partidos que quedaron con dos gameId (15-sep-2026).
     */
    const CORRIMIENTO_SEGURO = 3;

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
            . 'Detalle de los partidos (alineaciones, goles, tarjetas, cambios)</a>'
            . '<a class="boton-sec" href="' . e(route('import_partidos.fechas')) . '">'
            . 'Reagrupar fechas de un grupo</a></p>';

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
                                                    : 'sondeado · nada para cargar (' . (int) $f->sd->fuera_1ra . ' excluidos)')
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
    private function registrarSondeo($tecnicoId, array $datos, $esSondeo = true)
    {
        if (!Schema::hasTable('tecnico_sondeos')) return;

        // $esSondeo = false: solo se actualizan columnas sueltas (la lista de
        // excluidas), sin marcar al DT como sondeado.
        $fila = $datos + ['updated_at' => now()];
        if ($esSondeo) $fila['sondeado_at'] = now();
        if (!$esSondeo && !DB::table('tecnico_sondeos')->where('tecnico_id', (int) $tecnicoId)->exists()) {
            // Sin fila previa no hay sondeo registrado: no se inventa uno.
            // (Si la columna sondeado_at es nullable se podría insertar, pero
            // no hace falta: la próxima bajada con guardar=1 la crea.)
            return;
        }

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
        // Las fechas de los partidos YA JUGADOS son un botón aparte, nunca parte
        // de `refrescar`: ver `corregirFechasJugadas()`.
        $corregirJugados = (string) $request->get('fechas_jugados', '0') === '1';
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

        // Si viene un torneo tuyo, la competencia Y LA TEMPORADA salen de ahí.
        // El torneo ya guarda las dos cosas (`tm_competition_id` y
        // `tm_season_id`, que se cargan en Editar torneo): no hay que tipear
        // nada acá ni volver a mirar en qué año era cada edición.
        $torneoElegido = null;
        if ((int) $request->get('torneo_id')) {
            $torneoElegido = \App\Torneo::find((int) $request->get('torneo_id'));
            if ($torneoElegido && trim((string) $torneoElegido->tm_competition_id) !== '') {
                $comp = trim((string) $torneoElegido->tm_competition_id);
            }
            if ($torneoElegido && $season === '' && trim((string) $torneoElegido->tm_season_id) !== '') {
                $season = trim((string) $torneoElegido->tm_season_id);
            }
        }

        // ── LAS CUATRO PERILLAS DEL CALENDARIO EN HTML ──────────────────────
        // `import_detalles.competencia_html` funciona porque deja tipear las
        // cuatro cosas que pueden estar mal: id de competencia, tipo (liga o
        // copa), temporada y país de salida. Acá salían del torneo y no había
        // forma de moverlas sin editar el torneo, así que un intento fallido
        // era un callejón sin salida. Ahora se pisan por query string y
        // `solo_html=1` va derecho al HTML sin pasar por la API.
        $compForzado = trim((string) $request->get('comp_forzado', ''));
        if ($compForzado !== '') $comp = $compForzado;
        $tipoHtml = trim((string) $request->get('tipo', ''));
        if ($tipoHtml !== 'liga' && $tipoHtml !== 'copa') $tipoHtml = '';
        $pais     = trim((string) $request->get('pais', '')) ?: null;
        $soloHtml = (string) $request->get('solo_html', '0') === '1';

        // Los torneos COMPLETOS (con posiciones finales guardadas en
        // `posicion_torneos`) no aparecen: ya no hay fixture que bajarles.
        // El que viene elegido por URL se deja igual, para no romper un link.
        $conTm = \App\Torneo::whereNotNull('tm_competition_id')->where('tm_competition_id', '!=', '')
            ->where(function ($q) use ($torneoElegido) {
                $q->whereNotExists(function ($s) {
                    $s->select(DB::raw(1))->from('posicion_torneos')
                        ->whereColumn('posicion_torneos.torneo_id', 'torneos.id');
                });
                if ($torneoElegido) $q->orWhere('torneos.id', $torneoElegido->id);
            })
            ->orderBy('year', 'desc')->orderBy('nombre')->get();

        $opts = '<option value="">— elegí un torneo tuyo —</option>';
        foreach ($conTm as $t) {
            $sel = ($torneoElegido && $torneoElegido->id === $t->id) ? ' selected' : '';
            $temp = trim((string) $t->tm_season_id);
            $opts .= '<option value="' . $t->id . '"' . $sel . '>'
                . e($t->nombre . ' ' . $t->year . '  ·  ' . $t->tm_competition_id
                    . ($temp !== '' ? ' · temporada ' . $temp : ' · SIN TEMPORADA')) . '</option>';
        }

        $html = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Fixture por competencia</h1>'
            . '<p class="sub">Baja el fixture completo del torneo con <b>una</b> llamada y lo deja listo para '
            . 'aplicar fecha por fecha. Reemplaza la carga del Excel. El detalle de cada partido '
            . '(alineaciones, goles, tarjetas) lo trae después la pantalla de siempre.</p>';

        if ($conTm->isEmpty()) {
            $html .= '<div class="err-box">Ningún torneo tuyo tiene cargado el id de competencia de Transfermarkt (o todos los que lo tienen ya están completos, con posiciones guardadas). '
                . 'Averigualo con el buscador de abajo y guardalo en <b>Editar torneo → transfermarkt.com</b>. '
                . 'Se hace una sola vez por torneo.</div>';
        } else {
            $html .= '<form method="get" style="margin:12px 0">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí un torneo tuyo…">' . $opts . '</select> '
                . '<button>Ver fixture</button> '
                . '<span class="sub">1 crédito + 1 por los nombres de los clubes</span></form>'
                . '<p class="sub">La temporada sale del torneo (<b>Editar torneo → Id Temporada</b>), no se tipea '
                . 'acá. Hace falta porque el id de competencia es de la <b>copa</b>, no de la edición: tus cinco '
                . 'Copas Argentina comparten <code>ARCA</code>, y sin temporada Transfermarkt manda la que está en '
                . 'curso. Si un torneo del desplegable dice <b>SIN TEMPORADA</b>, cargásela primero o vas a bajar '
                . 'el fixture de este año creyendo que bajás el suyo.</p>'
                . ($torneoElegido ? '<p class="acciones"><a class="boton-sec" href="'
                    . e(route('import_partidos.fixture', array_filter([
                        'torneo_id' => $torneoElegido->id, 'season' => $season, 'solo_html' => 1])))
                    . '">Leer el calendario en HTML</a> <span class="sub">para las temporadas cerradas: '
                    . 'la API siempre contesta la edición en curso. 1 crédito</span></p>' : '');
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
            ;

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
        $fuenteHtml = false;
        $avisosHtml = [];

        if (!$usarCache && $soloHtml) {
            // Derecho al calendario en HTML. El nombre de la competencia sale
            // del torneo: pedírselo a la API costaría un crédito para nada.
            $compNombre = $torneoElegido ? (string) $torneoElegido->nombre : $comp;
            $porHtml = $this->fixtureDesdeHtml($comp, $season, $torneoElegido, $compNombre, $avisosHtml, $pais, $tipoHtml);

            if (is_array($porHtml) && !empty($porHtml)) {
                $filas      = $porHtml;
                $fuenteHtml = true;
            } else {
                return $this->pagina('Fixture', $html . $this->cajaFixtureHtml(
                    $comp, $season, $torneoElegido, $avisosHtml, $tipoHtml, $pais,
                    'El calendario en HTML no trajo partidos.'));
            }
        }

        if (!$usarCache && !$soloHtml) {
            $crudo = $this->traerFixture($comp, $season);
            if (is_string($crudo)) return $this->pagina('Fixture', $html . '<p class="err-box">' . $crudo . '</p>');

            $compNombre = $this->nombreCompetencia($comp);
            foreach ($crudo as $g) {
                $f = $this->normalizarFixture($g, $comp, $compNombre);
                if (!$f['hora_definida'] || !$f['dia']) { $saltados++; continue; }
                $filas[] = $f;
            }

            // ¿La API contestó la edición que pedimos? No sabe de temporadas:
            // devuelve 200 y la que está en curso. Si no es la del torneo, el
            // fixture correcto se saca del CALENDARIO EN HTML, que sí la
            // respeta. Cuesta una llamada más y sólo pasa en ediciones viejas.
            if ($season !== '' && !$this->esTemporada($filas, $season)) {
                // `pais` es por dónde sale la petición (ScraperAPI). El sitio no
                // es la API: saliendo de Europa TM puede contestar el muro de
                // consentimiento en vez de la página. Con `&pais=us` se prueba
                // desde otro lado sin tocar el .env.
                $porHtml = $this->fixtureDesdeHtml($comp, $season, $torneoElegido, $compNombre,
                    $avisosHtml, $pais, $tipoHtml);

                if (is_array($porHtml) && !empty($porHtml)) {
                    $filas      = $porHtml;
                    $fuenteHtml = true;
                    $saltados   = 0;
                } else {
                    return $this->pagina('Fixture', $html . $this->cajaFixtureHtml(
                        $comp, $season, $torneoElegido, $avisosHtml, $tipoHtml, $pais,
                        'No pude traer la temporada ' . e($season) . '. La API devolvió la edición en curso '
                        . '(no sabe de temporadas) y el calendario en HTML tampoco trajo partidos.'));
                }
            }

            // Los nombres de los clubes cuestan una llamada aparte en la API;
            // el calendario en HTML ya los trae.
            if (!$fuenteHtml) $filas = $this->completarNombresClubes($filas);
        }

        if (empty($filas)) {
            return $this->pagina('Fixture', $html
                . '<p class="err-box">No vino ningún partido con día y hora confirmados para <code>'
                . e($comp) . '</code>.</p>');
        }

        $filas = $this->clasificarFixture($filas, $torneoElegido ? (int) $torneoElegido->id : null);

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

            if ($fuenteHtml) {
                $sinRonda = 0;
                foreach ($filas as $f) if ((string) $f['ronda'] === '—') $sinRonda++;

                $html .= '<p class="ok-box"><b>Temporada ' . e($season) . '</b>, la del torneo, '
                    . 'leída del <b>calendario en HTML</b> de Transfermarkt. '
                    . 'La API no sabe de temporadas —contesta la edición en curso le pidas la que le pidas—, '
                    . 'así que para las ediciones viejas se lee la página del torneo. '
                    . 'Vino: <b>' . $vino . '</b>.</p>'
                    . '<p class="warn-box">De esta fuente <b>no viene la hora</b>, sólo el día: por eso los '
                    . 'partidos figuran a las 00:00 y el botón que corrige horarios está apagado —escribiría esa '
                    . 'hora falsa—. Los partidos definidos <b>por penales</b> quedan sin marcador: el calendario '
                    . 'publica la tanda sumada a los 90\' y no hay con qué separarla.'
                    . ($sinRonda ? ' Además, <b>' . $sinRonda . '</b> partido(s) quedaron sin número de fecha '
                        . '(agrupados en «—»): TM no los tenía bajo ningún encabezado de ronda.' : '')
                    . '</p>';
            } elseif ($season === '') {
                $html .= '<p class="warn-box">Temporada que devolvió Transfermarkt: <b>' . $vino . '</b>. '
                    . 'Este torneo <b>no tiene cargada la temporada</b>, así que vino la que está en curso. '
                    . 'Si el torneo no es el de este año, lo que estás mirando no es el suyo: cargale el '
                    . '<b>Id Temporada</b> en Editar torneo.</p>';
            } else {
                $html .= '<p class="ok-box">Temporada <b>' . e($season) . '</b>, que es la del torneo. '
                    . 'Vino: <b>' . $vino . '</b>.</p>';
            }

            foreach ($avisosHtml as $a) {
                $html .= '<p class="warn-box">' . e($a) . '</p>';
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
            // Del calendario en HTML no viene la hora: las filas traen 00:00.
            // Pisar con eso el horario de un partido sería romperlo. Los
            // resultados sí se cargan: el día y el marcador son buenos.
            if (!$fuenteHtml) $refrescadas = $this->refrescarHorarios($filas);
            $resultados = $this->completarResultados($filas);
        }

        $jugadas = ['cargadas' => 0, 'salteadas' => 0, 'detalle' => ''];
        if ($corregirJugados) $jugadas = $this->corregirFechasJugadas($filas);

        // La auditoría no escribe nada: se muestra siempre.
        $problemas = $this->auditarResultados($filas);

        $porTipo = [];
        foreach ($problemas as $pr) {
            $t = isset($pr['tipo']) ? $pr['tipo'] : 'otro';
            $porTipo[$t] = (isset($porTipo[$t]) ? $porTipo[$t] : 0) + 1;
        }
        $sinResultado = isset($porTipo['sin_resultado']) ? $porTipo['sin_resultado'] : 0;
        // Los que están sin resultado y el fixture no puede cargar solo (penales).
        $sinMarcador  = isset($porTipo['sin_marcador'])  ? $porTipo['sin_marcador']  : 0;

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos con fecha')
            . $this->card($saltados, 'sin programar', $saltados ? 'gris' : '')
            . $this->card($cont['jugados'], 'ya jugados')
            . $this->card($cont['pendientes'], 'por jugarse')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($sinResultado, 'cargados sin resultado', $sinResultado ? 'warn' : 'ok')
            . $this->card($sinMarcador, 'por penales, a mano', $sinMarcador ? 'warn' : 'ok')
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
                    ? ($fuenteHtml
                        ? ' <b>No toqué ningún horario</b>: el calendario en HTML no trae la hora.'
                        : ($refrescadas
                            ? ' Actualicé el horario de <b>' . $refrescadas . '</b> partidos que todavía no se jugaron.'
                            : ' Ningún horario necesitaba corrección.'))
                      . ($resultados['cargados']
                        ? ' Cargué el resultado de <b>' . $resultados['cargados'] . '</b> partidos que estaban sin marcador.'
                        // «Ningún partido estaba sin resultado» era mentira cuando los
                        // que faltaban eran los definidos por penales: el botón no los
                        // puede cargar y decía que no había nada. Se cuentan aparte.
                        : ($sinMarcador ? '' : ' Ningún partido estaba sin resultado.'))
                      . ($sinMarcador
                        ? ' <b>' . $sinMarcador . '</b> partidos siguen sin resultado y este botón no los puede'
                          . ' cargar: TM los dio por penales y el marcador del fixture viene con la tanda sumada.'
                          . ' Están abajo, en «Revisar».'
                        : '')
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

        // EL TORNEO VIAJA EN TODOS LOS BOTONES. Antes `$base` llevaba sólo la
        // competencia, así que elegías el torneo del desplegable, veías la
        // temporada correcta, apretabas cualquier botón y el link salía con
        // `comp=` pelado: sin temporada, Transfermarkt manda la EDICIÓN EN
        // CURSO. Con `ARCA` —tus cinco Copas Argentina comparten ese id— eso
        // significa escribir en los partidos de un año mirando el fixture de
        // otro. El cartel amarillo avisaba, pero lo disparaba el propio botón.
        // De paso, con el torneo la segunda pasada del emparejador puede filtrar
        // por `grupos.torneo_id`.
        $base = route('import_partidos.fixture', array_filter([
            'comp' => $comp,
            'torneo_id' => $torneoElegido ? (int) $torneoElegido->id : null,
        ]));

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
            . 'tengas cargado. Usalo cuando hayas comprobado que el emparejado es correcto. '
            // El botón prometía "carga el marcador en los que estén sin resultado"
            // a secas, y con los de penales no puede: el listado del fixture no
            // trae la tanda separada. Decirlo acá, que es donde se lee.
            . '<b>Excepción:</b> los definidos por <b>penales</b> no los puede cargar —el marcador del listado '
            . 'del fixture viene con la tanda sumada—; ésos van con «Traer solo el marcador», en Revisar.</span>'
            . '</p>'
            . '<p class="acciones">'
            . '<a href="' . e($base) . '">Volver a bajar de TM</a> · '
            . '<a href="' . e($base . '&cache=1') . '">Releer sin bajar</a> · '
            . '<a href="' . e($base . '&cache=1&estado=conflicto') . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&cache=1&estado=nuevo') . '">Ver solo nuevos</a>'
            . '</p>';

        if ($corregirJugados) {
            $html .= '<p class="ok-box">' . ($jugadas['cargadas']
                    ? 'Corregí la fecha de <b>' . $jugadas['cargadas'] . '</b> partidos ya jugados.'
                    : 'No corregí ninguna fecha.')
                . ($jugadas['salteadas']
                    ? ' Dejé <b>' . $jugadas['salteadas'] . '</b> sin tocar porque el corrimiento pasa los 10 días: '
                      . 'ésos van de a uno, mirándolos.' : '')
                // El resultado NO se toca acá: este botón escribe la fecha y
                // nada más, como dice su nombre. Pero un partido al que recién
                // le arreglaste la fecha es, casi siempre, uno que hasta hoy no
                // aparejaba y por eso también está sin marcador. Dejar el link
                // a mano evita la vuelta por la pantalla anterior.
                . ($jugadas['cargadas']
                    ? '<br>Les falta el marcador: hasta recién no aparejaban, así que ninguna pasada se lo pudo '
                      . 'cargar. <a href="' . e($base . $fuente . '&refrescar=1') . '"><b>Cargar los resultados '
                      . 'ahora →</b></a>' : '')
                . '</p>'
                . ($jugadas['detalle']
                    ? '<div class="scroll"><table><thead><tr><th>Fecha nº</th><th>Partido</th><th>Antes</th>'
                      . '<th>Ahora</th><th>Partido</th></tr></thead><tbody>'
                      . $jugadas['detalle'] . '</tbody></table></div>'
                    : '');
        }

        $html .= $this->bloqueFechasCorridas($filas, $base . $fuente);
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

            $etiquetas = ['sin_resultado' => 'sin resultado', 'sin_marcador' => 'sin resultado (por penales)',
                'distinto' => 'resultado distinto',
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
                . ($sinMarcador
                    ? '<p class="warn-box"><b>' . $sinMarcador . '</b> partidos <b>jugados y sin resultado</b> que el '
                      . 'botón de arriba NO puede cargar: TM los dio por penales y el marcador del listado del '
                      . 'fixture viene con la tanda sumada (1:1 con tanda 4:2 lo publica 5:3). Los 90\' reales salen '
                      . 'del <b>detalle</b> del partido, que trae la tanda y la puede restar. En cada fila: '
                      . '<b>«Traer solo el marcador»</b> escribe el resultado y la tanda y nada más —no toca '
                      . 'alineación ni incidencias, así que sirve también en los partidos que cargaste a mano—, y '
                      . '«Bajar el detalle» trae además alineación e incidencias, pero se planta si el partido ya '
                      . 'las tiene. <b>Cualquiera de los dos gasta 1 crédito por partido.</b></p>'
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
                    . $this->linkTm($pr['external_id'] ?? null)
                    . (empty($pr['external_id']) ? ''
                        : ' · <a href="' . e(route('import_partidos.partido',
                                ['game_id' => $pr['external_id']]))
                            . '" title="Abre el JSON de TM de ESTE partido. Gasta 1 crédito.">Sondear</a>')
                    // El detalle es el ÚNICO lugar donde está la tanda separada del
                    // marcador, así que para estos partidos es el arreglo, no un extra.
                    // Se ofrecen los dos caminos, y primero el que no puede romper
                    // nada: «solo el marcador» escribe goles y tanda y nada más.
                    // «Bajar el detalle» trae además alineación e incidencias, pero
                    // se planta si el partido ya las tiene cargadas a mano.
                    . ((isset($pr['tipo']) && $pr['tipo'] === 'sin_marcador')
                        ? ' · <a href="' . e(route('import_detalles.marcador',
                                array_filter(['partido_id' => (int) $pr['partido_id'],
                                    'game_id' => $pr['external_id'],
                                    'comp' => $comp,
                                    'torneo_id' => $torneoElegido ? (int) $torneoElegido->id : null])))
                            . '" title="Baja el detalle, le resta la tanda al marcador de TM y escribe SOLO el '
                            . 'resultado y la tanda. No toca alineación ni incidencias. Gasta 1 crédito.">'
                            . '<b>Traer solo el marcador →</b></a>'
                          . ' · <a href="' . e(route('import_detalles.bajar',
                                ['partido_id' => (int) $pr['partido_id']]))
                            . '" title="Además del marcador trae alineación e incidencias. Se planta si el '
                            . 'partido ya tiene alineación cargada. Gasta 1 crédito.">Bajar el detalle</a>'
                        : '')
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
                        array_filter(['comp' => $comp, 'gameday' => $r,
                            'torneo_id' => $torneoElegido ? (int) $torneoElegido->id : null]))) . '">Aplicar ' . $d['nuevo'] . ' →</a>'
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

    /** ¿Todo lo que vino es de la temporada que pedimos? */
    private function esTemporada(array $filas, $season)
    {
        if (empty($filas)) return false;

        foreach ($filas as $f) {
            $t = trim((string) (isset($f['temporada']) ? $f['temporada'] : ''));
            if ($t !== '' && $t !== (string) $season) return false;
        }
        return true;
    }

    /**
     * El fixture de una edición vieja, leído del calendario en HTML.
     *
     * La API no sabe de temporadas: `/competition/{id}/fixtures` contesta 200 y
     * te manda la edición en curso, le pases el seasonId o no (comprobado con
     * ARCA y 2021). El calendario del sitio sí la respeta, y trae lo esencial:
     * gameId, día, y los DOS clubes con su id de TM — que es lo único que hace
     * falta para aparear.
     *
     * Lo que NO trae, y por qué no importa acá:
     *   · la HORA. Sólo el día. Por eso estas filas se marcan con
     *     `sin_hora`, y la pantalla apaga el botón que corrige horarios: sin
     *     hora real escribiría 00:00 en partidos que no se jugaron.
     *   · el marcador estructurado. Viene el texto del link («5:0», «4:5pen.»).
     *     Los definidos por penales quedan SIN marcador, igual que en el
     *     importador de la API: el texto trae la tanda sumada y no hay con qué
     *     separarla, y un marcador inventado es peor que ninguno.
     */
    /**
     * Qué contestó Transfermarkt cuando la página no trajo ningún partido.
     *
     * Sin esto, "0 partidos" es indistinguible entre el muro de consentimiento,
     * un 404 con el maquetado de TM, una temporada que no existe y un id de
     * competencia equivocado — y las cuatro se arreglan distinto. El HTML ya
     * está en la mano, así que mirarlo no cuesta otra llamada.
     */
    private function queVino($html)
    {
        $html = (string) $html;
        if ($html === '') return '';

        $titulo = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $titulo = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        }

        $texto = mb_strtolower($titulo . ' ' . mb_substr(strip_tags($html), 0, 3000));
        $pista = '';
        $pistas = ['consent' => 'el muro de consentimiento', 'zustimmung' => 'el muro de consentimiento',
            'cookie' => 'el muro de cookies', 'captcha' => 'un captcha',
            'attention required' => 'un bloqueo de Cloudflare', 'access denied' => 'un acceso denegado',
            'no se encontr' => 'un 404 de TM', 'not found' => 'un 404'];
        foreach ($pistas as $aguja => $que) {
            if (mb_strpos($texto, $aguja) !== false) { $pista = ', parece ' . $que; break; }
        }

        return ' [vino una página de ' . strlen($html) . ' bytes'
            . ($titulo !== '' ? ', título «' . mb_substr($titulo, 0, 90) . '»' : ', sin título')
            . $pista . ']';
    }

    /**
     * La caja de "el calendario no trajo partidos", CON las perillas a la vista.
     *
     * Cuatro cosas pueden estar mal y cada una se arregla distinto: el id de
     * competencia, el tipo (las ligas van por `/wettbewerb/` y las copas por
     * `/pokalwettbewerb/`), la temporada (el año de arranque, que en los
     * torneos que cruzan años va uno atrás) y el país de salida (desde Europa
     * TM contesta el muro de consentimiento en vez de la página). Antes había
     * que adivinar cuál y editar el torneo para probar; ahora se cambia acá y
     * se reintenta, que es lo que hace la pantalla del calendario suelto.
     */
    private function cajaFixtureHtml($comp, $season, $torneo, array $avisosHtml, $tipoHtml, $pais, $titulo)
    {
        $esCopa = $tipoHtml === 'copa'
            || ($tipoHtml === '' && $torneo && strcasecmp((string) $torneo->tipo, 'Copa') === 0);

        $detalle = '';
        foreach ($avisosHtml as $a) $detalle .= '<div>• ' . e($a) . '</div>';

        $base = ['solo_html' => 1];
        if ($torneo) $base['torneo_id'] = (int) $torneo->id;

        $ocultos = '';
        foreach ($base as $k => $v) $ocultos .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';

        $opts = '';
        foreach (['' => 'tipo: como diga el torneo', 'copa' => 'copa (pokalwettbewerb)',
                  'liga' => 'liga (wettbewerb)'] as $v => $t) {
            $opts .= '<option value="' . e($v) . '"' . ($tipoHtml === $v ? ' selected' : '') . '>' . e($t) . '</option>';
        }

        $aEEUU = route('import_partidos.fixture', array_filter(array_merge($base, [
            'comp_forzado' => $comp, 'season' => $season, 'tipo' => $tipoHtml, 'pais' => 'us'])));

        $suelto = route('import_detalles.competencia_html', [
            'comp_id' => $comp, 'season' => $season, 'copa' => $esCopa ? 1 : 0]);

        // `?crudo=1` de la pantalla suelta es el diagnóstico bueno: bytes,
        // marcas de consentimiento/captcha/créditos, los primeros 4 KB y qué ve
        // el parser. Un muro de cookies, un bloqueo por IP, una respuesta
        // recortada y un cambio de maquetado se ven todos iguales sin eso.
        $crudo = route('import_detalles.competencia_html', array_filter([
            'comp_id' => $comp, 'season' => $season, 'copa' => $esCopa ? 1 : 0,
            'pais' => $pais, 'crudo' => 1]));

        return '<p class="err-box"><b>' . $titulo . '</b></p>'
            . ($detalle ? '<div class="diag">' . $detalle . '</div>' : '')
            . '<p class="sub">Cambiá la perilla que sospeches y reintentá — cada intento es 1 crédito. '
            . 'El <b>país</b> es por dónde sale la petición: saliendo de Europa, Transfermarkt contesta el muro '
            . 'de consentimiento en vez de la página, y con <code>us</code> suele venir bien. La <b>temporada</b> '
            . 'de TM es el año de arranque: en los torneos que cruzan años va uno atrás del que usás vos. '
            . 'Y si el <b>tipo</b> está mal, la página existe pero viene sin un solo partido.</p>'
            . '<form method="get" action="' . e(route('import_partidos.fixture')) . '">' . $ocultos
            . '<input name="comp_forzado" value="' . e((string) $comp) . '" size="10" placeholder="id competencia"> '
            . '<select name="tipo" class="s2" data-placeholder="tipo…">' . $opts . '</select> '
            . '<input name="season" value="' . e((string) $season) . '" size="7" placeholder="temporada"> '
            . '<input name="pais" value="' . e((string) $pais) . '" size="5" placeholder="país"> '
            . '<button class="boton">Leer el calendario</button> <span class="sub">1 crédito</span></form>'
            . '<p class="acciones">'
            . '<a class="boton-sec" href="' . e($aEEUU) . '">Reintentar desde EE.UU.</a> '
            . '<a class="boton-sec" href="' . e($suelto) . '">Abrir el calendario suelto</a> '
            . '<a class="boton-sec" href="' . e($crudo) . '">Ver qué contestó Transfermarkt</a></p>';
    }

    private function fixtureDesdeHtml($comp, $season, $torneo, $compNombre, array &$avisos = [], $pais = null, $tipo = '')
    {
        // En TM las ligas van por `/wettbewerb/` y las copas por
        // `/pokalwettbewerb/`: es otra ruta, no un parámetro, y pedir la que no
        // es devuelve una página sin un solo partido.
        //
        // El tipo del torneo dice cuál debería ser, pero NO se le cree del todo:
        // es un campo que se carga a mano y un torneo mal tipeado dejaba la
        // pantalla diciendo "no hay ningún link a una ficha de partido", que no
        // le explica nada a nadie. Se prueba la que corresponde y, si vuelve
        // vacía, la otra. La segunda llamada sólo ocurre cuando la primera
        // falló, que es exactamente cuando vale la pena gastarla.
        // `$tipo` ('liga'/'copa') lo pisa a mano desde la pantalla: el campo
        // `torneos.tipo` se carga a mano y puede estar mal.
        $esCopa = $tipo === 'copa' ? true
            : ($tipo === 'liga' ? false
                : ($torneo ? (strcasecmp((string) $torneo->tipo, 'Copa') === 0) : true));

        $svc = new \App\Services\TmFixtureCompetenciaHtml;
        $leido = null;
        $intentos = [];

        foreach ([$esCopa, !$esCopa] as $copa) {
            $url = \App\Services\TmFixtureCompetenciaHtml::urlComp($comp, $season, $copa);
            // Se pide el crudo (no cuesta otra llamada: el HTML ya viaja) para
            // poder decir QUÉ contestó TM cuando no hay partidos.
            $r   = $svc->leerComp($comp, $season, $copa, true, $pais);

            $intentos[] = ($copa ? 'copa' : 'liga') . ': ' . $url
                . ' → ' . (is_array($r) ? count($r) . ' partidos' : 'no vino la página')
                . ((is_array($r) && !empty($r)) ? '' : $this->queVino($svc->crudo));

            if (is_array($r) && !empty($r)) { $leido = $r; break; }
        }

        if (!empty($svc->avisos)) $avisos = array_merge($avisos, (array) $svc->avisos);

        if (!is_array($leido) || empty($leido)) {
            // Sin las URLs probadas esto es imposible de diagnosticar: abrís la
            // que corresponda en el navegador y en un segundo sabés si el
            // problema es el id, la temporada o que TM contestó el muro de
            // consentimiento.
            $avisos[] = 'Probé estas dos rutas y ninguna trajo partidos — ' . implode(' · ', $intentos);
            return null;
        }

        $anio = $torneo ? (string) $torneo->year : '';
        $filas = [];

        foreach ($leido as $r) {
            if (empty($r['game_id']) || empty($r['dia'])) continue;
            if (empty($r['local_tm']) || empty($r['visita_tm'])) continue;

            $res = $this->marcadorDeTexto(isset($r['resultado']) ? $r['resultado'] : '');

            $filas[] = [
                'external_id'             => (string) $r['game_id'],
                'competencia_external_id' => $comp,
                'competencia_nombre'      => $compNombre,
                'temporada'               => (string) $season,
                'anio'                    => $anio,
                'ronda'                   => isset($r['ronda']) && $r['ronda'] !== null ? (string) $r['ronda'] : '—',
                'club_external_id'        => (string) $r['local_tm'],
                'club_nombre'             => isset($r['local_nombre']) ? $r['local_nombre'] : null,
                'rival_external_id'       => (string) $r['visita_tm'],
                'rival_nombre'            => isset($r['visita_nombre']) ? $r['visita_nombre'] : null,
                'local'                   => 1,
                // Sin hora: se guarda el día a las 00:00 y se marca la fila.
                'dia'                     => substr((string) $r['dia'], 0, 10) . ' 00:00:00',
                'goles_favor'             => $res['gf'],
                'goles_contra'            => $res['gc'],
                'equipo_id' => null, 'rival_id' => null, 'partido_id' => null,
                'estado' => 'nuevo', 'motivo' => null,
                // El payload NO es un game de la API. Se marca para que
                // `fixtureDesdeStaging()` no intente parsearlo como tal.
                'payload' => json_encode(['_fuente' => 'html'] + $r, JSON_UNESCAPED_UNICODE),
                'terminado'      => $res['terminado'],
                'por_penales'    => $res['por_penales'],
                'ida_vuelta'     => false,
                'ida_marcador'   => null,
                'penales_favor'  => null,
                'penales_contra' => null,
                'marcador_tm'    => $res['crudo'],
                'hora_definida'  => true,
                'sin_hora'       => true,
                'reprogramado'   => false,
            ];
        }

        return $filas;
    }

    /**
     * El marcador del calendario en HTML, que es texto: «5:0», «4:5pen.», «-:-».
     *
     * Un partido definido por penales se deja SIN marcador a propósito: TM
     * publica la tanda sumada a los 90' y desde el calendario no hay con qué
     * separarla. Mismo criterio que `normalizarFixture()`.
     */
    private function marcadorDeTexto($txt)
    {
        $txt = trim((string) $txt);
        $out = ['gf' => null, 'gc' => null, 'terminado' => false,
            'por_penales' => false, 'crudo' => ($txt !== '' ? $txt : null)];

        if ($txt === '' || !preg_match('/(\d+)\s*:\s*(\d+)/', $txt, $m)) return $out;

        $out['terminado'] = true;

        if (stripos($txt, 'pen') !== false || stripos($txt, 'e.t.') !== false) {
            $out['por_penales'] = (stripos($txt, 'pen') !== false);
            return $out;   // con tanda sumada, mejor sin marcador
        }

        $out['gf'] = (int) $m[1];
        $out['gc'] = (int) $m[2];
        return $out;
    }

    /** Trae el fixture completo y lo aplana: fixtures[].games[] -> lista. */

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
                . $this->linkTm($f['external_id'] ?? null)
                . (empty($f['external_id']) ? ''
                    : ' · <a href="' . e(route('import_partidos.partido',
                            ['game_id' => $f['external_id']]))
                        . '" title="Abre el JSON de TM de ESTE partido. Gasta 1 crédito.">Sondear</a>')
                . '</td></tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /**
     * « · Ver en TM ↗» a la ficha del partido en Transfermarkt. Es un link
     * común (no gasta crédito); vacío si la fila no tiene gameId.
     */
    private function linkTm($gameId)
    {
        $gameId = trim((string) $gameId);
        if ($gameId === '') return '';
        return ' · <a href="' . e(\App\Services\Controles::TM_PARTIDO . rawurlencode($gameId))
            . '" target="_blank" rel="noopener" title="Ficha del partido en Transfermarkt (gameId '
            . e($gameId) . '). No gasta crédito.">Ver en TM ↗</a>';
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
     *
     * DOS PASADAS, y la segunda no es un lujo. `buscarPartido()` mira una
     * ventana de ±1 día alrededor de la fecha de TM: a un partido que movieron
     * más que eso NO lo encuentra, lo marca «nuevo» y el botón «Aplicar» te
     * crea un DUPLICADO del que ya tenías, con su alineación y su resultado
     * aparte. Pasó con la fecha 8 del Clausura 2026: los tres del viernes
     * estaban guardados con la fecha del domingo y figuraban como nuevos.
     * La segunda pasada los reconoce por par de equipos + número de fecha.
     */
    private function clasificarFixture(array $filas, $torneoId = null)
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

            $filas[$i]['dia_base'] = null;
            $filas[$i]['corrido']  = 0;

            $partido = $this->buscarPartido($localId, $visiId, $f['dia']);
            $porRonda = false;
            if (!$partido) {
                $partido = $this->buscarPartidoPorRonda($localId, $visiId, $f['dia'],
                    isset($f['ronda']) ? $f['ronda'] : null, $torneoId);
                $porRonda = (bool) $partido;
            }

            if ($partido) {
                $corrido = (int) round((strtotime(substr((string) $partido->dia, 0, 10))
                    - strtotime(substr((string) $f['dia'], 0, 10))) / 86400);

                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $filas[$i]['dia_base'] = substr((string) $partido->dia, 0, 19);
                $filas[$i]['corrido'] = $corrido;
                $filas[$i]['motivo'] = $porRonda
                    ? 'ya cargado, con la fecha corrida ' . abs($corrido) . ' día(s)'
                    : 'ya cargado';
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

        // EL PAR (partido, gameId) YA ES DE UNA FILA DE DT. `uq_partido_gameid`
        // no deja repetirlo (ver `persistir()`), y el insert reventaba con un
        // 500 al refrescar el fixture. La fila del fixture se guarda igual —la
        // pantalla la relee del staging— pero SIN partido_id: queda «duplicado»,
        // así «Aplicar» (que sólo toma «nuevo») no la crea otra vez, y al
        // releerla `clasificarFixture()` la vuelve a emparejar sola.
        if ($f['partido_id']) {
            $duena = DB::table('import_partidos')
                ->where('partido_id', (int) $f['partido_id'])
                ->where('external_id', (string) $f['external_id'])
                ->whereNotNull('tecnico_id')
                ->first(['id']);
            if ($duena) {
                $f['partido_id'] = null;
                $f['estado'] = 'duplicado';
                $f['motivo'] = mb_substr(trim($f['motivo'] . ' · gameId en la fila #' . $duena->id, ' ·'), 0, 191);
            }
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
     * Corrige la fecha de los partidos YA JUGADOS que quedaron guardados otro día.
     *
     * Es lo que `refrescarHorarios()` no hace y a propósito: con el partido
     * jugado, TM deja de ser la autoridad —a los postergados les conserva la
     * fecha ORIGINAL— así que pisar todo sería cambiar un dato bueno por uno
     * viejo. Por eso esto es un botón aparte, con la lista a la vista antes de
     * apretarlo, y **sólo mueve corrimientos de hasta 10 días**: un partido que
     * se corrió de un viernes a un domingo es una reprogramación de la misma
     * ronda; uno que se corrió tres meses es el caso postergado, y ése se mira
     * de a uno.
     *
     * Sin hora definida (viene así del calendario en HTML) se cambia sólo el
     * día y se conserva la hora cargada: un 00:00 inventado es peor.
     */
    private function corregirFechasJugadas(array &$filas, $limiteDias = 10)
    {
        $out = ['cargadas' => 0, 'salteadas' => 0, 'detalle' => ''];

        foreach ($filas as $i => $f) {
            if (empty($f['terminado']) || empty($f['partido_id']) || empty($f['dia'])) continue;
            if (empty($f['dia_base'])) continue;
            if (substr((string) $f['dia_base'], 0, 10) === substr((string) $f['dia'], 0, 10)) continue;

            $dias = abs((strtotime(substr((string) $f['dia'], 0, 10))
                - strtotime(substr((string) $f['dia_base'], 0, 10))) / 86400);
            if ($dias > $limiteDias) { $out['salteadas']++; continue; }

            $p = \App\Partido::find($f['partido_id']);
            if (!$p) continue;

            $vieja = (string) $p->dia;
            $nueva = !empty($f['hora_definida'])
                ? substr((string) $f['dia'], 0, 19)
                : substr((string) $f['dia'], 0, 10) . ' '
                  . (strlen($vieja) >= 19 ? substr($vieja, 11, 8) : '00:00:00');

            $p->forceFill(['dia' => $nueva])->save();

            // `$filas` VIENE POR REFERENCIA A PROPÓSITO. El array se clasificó al
            // principio del request, así que `dia_base` es la fecha que el partido
            // tenía ANTES de esta corrección. Sin actualizarlo acá, el bloque
            // "Ya jugados con la fecha corrida" —que se arma después— los volvía
            // a listar como pendientes, con la fecha vieja, justo debajo del
            // cartel que decía que los había corregido.
            $filas[$i]['dia_base'] = substr($nueva, 0, 19);
            $filas[$i]['corrido']  = 0;

            $out['detalle'] .= '<tr><td class="num">' . e((string) $f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre'] . ' vs ' . $f['rival_nombre']) . '</td>'
                . '<td class="num">' . e(substr($vieja, 0, 16)) . '</td>'
                . '<td class="num"><b>' . e(substr($nueva, 0, 16)) . '</b></td>'
                . '<td class="num">#' . (int) $f['partido_id'] . '</td></tr>';
            $out['cargadas']++;
        }
        return $out;
    }

    /**
     * Los ya jugados cuya fecha no coincide con la de TM. No escribe nada: es
     * la lista que hay que mirar ANTES de apretar el botón de arriba.
     *
     * Cada fila linkea a `import_detalles.fecha`, que corrige ese partido solo.
     */
    private function bloqueFechasCorridas(array $filas, $urlBase, $limiteDias = 10)
    {
        $corridos = [];
        foreach ($filas as $f) {
            if (empty($f['terminado']) || empty($f['partido_id']) || empty($f['dia'])) continue;
            if (empty($f['dia_base'])) continue;
            if (substr((string) $f['dia_base'], 0, 10) === substr((string) $f['dia'], 0, 10)) continue;
            $corridos[] = $f;
        }
        if (empty($corridos)) return '';

        $lejos = 0;
        $filasHtml = '';
        foreach ($corridos as $f) {
            $dias = (int) round((strtotime(substr((string) $f['dia'], 0, 10))
                - strtotime(substr((string) $f['dia_base'], 0, 10))) / 86400);
            if (abs($dias) > $limiteDias) $lejos++;

            $nueva = !empty($f['hora_definida'])
                ? substr((string) $f['dia'], 0, 19)
                : substr((string) $f['dia'], 0, 10) . ' ' . substr((string) $f['dia_base'], 11, 8);

            $filasHtml .= '<tr' . (abs($dias) > $limiteDias ? ' class="warn"' : '') . '>'
                . '<td class="num">' . e((string) $f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre'] . ' vs ' . $f['rival_nombre']) . '</td>'
                . '<td class="num">' . e(substr((string) $f['dia_base'], 0, 16)) . '</td>'
                . '<td class="num"><b>' . e(substr((string) $f['dia'], 0, 16)) . '</b></td>'
                . '<td class="num">' . ($dias > 0 ? '+' : '') . $dias . '</td>'
                . '<td class="num">#' . (int) $f['partido_id'] . '</td>'
                . '<td><a href="' . e(route('import_detalles.fecha',
                    ['partido_id' => (int) $f['partido_id'], 'dia' => $nueva]))
                . '">Poner el ' . e(substr((string) $f['dia'], 0, 10)) . '</a></td></tr>';
        }

        return '<h2>Ya jugados con la fecha corrida <span class="sub">(' . count($corridos) . ')</span></h2>'
            . '<p class="sub">Partidos que ya se jugaron y que tenés guardados <b>otro día</b> que el que dice '
            . 'Transfermarkt. Los encontró la segunda pasada del emparejador (par de equipos + número de fecha), '
            . 'así que <b>no</b> figuran como «nuevos» y no hay riesgo de duplicarlos. '
            . '<b>Ojo antes de corregir</b>: a los partidos postergados TM les conserva la fecha original, y en '
            . 'ésos el que está bien sos vos. Un corrimiento de pocos días es una reprogramación; uno de meses, '
            . 'casi seguro un postergado.</p>'
            . '<div class="scroll"><table><thead><tr><th>Fecha nº</th><th>Partido</th><th>La tuya</th>'
            . '<th>La de TM</th><th>Días</th><th>Partido</th><th></th></tr></thead><tbody>'
            . $filasHtml . '</tbody></table></div>'
            . '<p class="acciones"><a class="boton-sec" href="' . e($urlBase . '&fechas_jugados=1')
            . '">Corregir las de hasta 10 días</a> <span class="sub">escribe <code>partidos.dia</code> de esos '
            . 'partidos y nada más'
            . ($lejos ? '; deja afuera ' . $lejos . ' que se corrieron más de 10 días, ésos de a uno' : '')
            . '</span></p>';
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

            // TM LO JUGÓ, VOS LO TENÉS VACÍO Y EL FIXTURE NO TRAE UN MARCADOR
            // USABLE. Es el agujero que dejaba la pantalla muda: el caso de
            // arriba pide `goles_favor !== null`, y `normalizarFixture()` lo
            // pone en null a propósito cuando el partido se definió por penales
            // (el `score` del listado viene con la tanda sumada: 1:1 con tanda
            // 4:2 lo publica 5:3). Resultado: el partido no entraba en ninguna
            // rama, `completarResultados()` lo salteaba y la pantalla decía
            // «nada para revisar» y «ningún partido estaba sin resultado» con
            // varios partidos vacíos. En una copa —donde media ronda se define
            // por penales— eso es la mitad de la fecha.
            //
            // Esto NO lo arregla «Guardar, corregir horarios y cargar
            // resultados»: el marcador de los 90' sale del DETALLE del partido,
            // que sí trae `actions.shootout` y lo puede restar.
            // Las vueltas de llave no llegan acá: las corta la primera rama.
            } elseif (!empty($f['terminado']) && $f['goles_favor'] === null
                && !empty($f['marcador_tm'])
                && ($p->golesl === null || $p->golesv === null)) {
                // Corto A PROPÓSITO: el texto largo empujaba la columna de
                // acciones fuera del scroll horizontal y el link para arreglarlo
                // quedaba invisible. La explicación va en la caja de arriba.
                $problema = !empty($f['por_penales'])
                    ? 'sin resultado · TM lo dio por penales'
                    : 'sin resultado · el fixture no trae marcador usable';
                $tipo = 'sin_marcador';
                $tuyo = 'sin resultado';
                $deTm = $f['marcador_tm'] . (!empty($f['por_penales']) ? ' (con la tanda sumada)' : '');
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

            // El payload es un `game` de la API sólo si lo parece. Las filas que
            // vinieron del calendario en HTML guardan otra cosa (y van marcadas
            // con `_fuente`): pasarlas por `normalizarFixture()` devolvía una
            // fila de puros nulls, sin ruido ni error.
            $esDeLaApi = is_array($g) && !empty($g)
                && empty($g['_fuente'])
                && (isset($g['baseDetails']) || isset($g['gameId']));

            if ($esDeLaApi) {
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
            // Con «—» pelado, un partido definido por penales parecía no jugado.
            // Se muestra el marcador crudo de TM en gris, avisando que trae la
            // tanda sumada y que por eso no se carga solo.
            $res = ($f['goles_favor'] === null)
                ? (!empty($f['terminado']) && !empty($f['marcador_tm'])
                    ? '<span class="sub" title="Marcador de TM con la tanda sumada: el de los 90\' sale del '
                      . 'detalle del partido">' . e((string) $f['marcador_tm'])
                      . (!empty($f['por_penales']) && stripos((string) $f['marcador_tm'], 'pen') === false
                            ? ' pen.' : '') . '</span>'
                    : '<span class="sub">—</span>')
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

        // El torneo vuelve con vos. Sin él, la pantalla de fixture pierde la
        // temporada y Transfermarkt manda la edición en curso: ver el comentario
        // de `$base` en fixture().
        $alFixture = route('import_partidos.fixture',
            array_filter(['comp' => $comp, 'cache' => 1, 'torneo_id' => $torneoId ?: null]));

        $volver = '<p class="sub"><a href="' . e($alFixture) . '">← Volver al fixture</a></p>';

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
            // ¿VACÍO PORQUE NO HAY NADA O PORQUE EL STAGING ESTÁ VIEJO? La
            // pantalla del fixture cuenta los «nuevos» sobre lo que ACABA DE
            // BAJAR de TM; este botón trabaja contra `import_partidos`. Las dos
            // cosas se separan solas: si no guardaste, o si borraste los
            // partidos que se habían creado, allá dice «Aplicar 2» y acá no hay
            // ninguno. Contestar «no hay nada que crear» manda a buscar el
            // problema donde no está.
            $enStaging = DB::table('import_partidos')
                ->whereNull('tecnico_id')
                ->where('competencia_external_id', $comp)
                ->where('ronda', $gameday)
                ->select('estado', DB::raw('count(*) as n'))
                ->groupBy('estado')->get();

            $porEstado = [];
            foreach ($enStaging as $e) $porEstado[] = $e->n . ' ' . $e->estado;

            return $this->pagina('Aplicar fecha', $volver
                . '<p class="' . (empty($porEstado) ? 'err-box' : 'ok-box') . '">'
                . 'La fecha ' . e($gameday) . ' no tiene partidos nuevos <b>en el staging</b>'
                . (empty($porEstado)
                    ? ', que para esta competencia está vacío. Si la pantalla del fixture te muestra partidos '
                      . 'nuevos, son los que acaba de bajar de TM y todavía no están guardados: apretá '
                      . '<b>«Guardar en staging»</b> y volvé a intentar.'
                    : ': ahí hay ' . e(implode(', ', $porEstado)) . '. Si la pantalla del fixture te muestra '
                      . 'nuevos que acá no aparecen, el staging quedó viejo —pasa cuando borraste los partidos '
                      . 'que se habían creado—. <b>«Guardar en staging»</b> los devuelve a «nuevo».')
                . '</p>');
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

        // ── LA RONDA DE TM ES UNA ZONA: «Grupo 15» ──────────────────────────
        // Fase de grupos de una copa leída del calendario en HTML (KNVB Beker
        // 2000/01: 20 grupos, 117 partidos). Si el torneo tiene una zona que
        // se llama igual, esos partidos van ahí, repartidos en fechas: el
        // camino normal los mandaba a UNA sola fecha y chocaba con el índice
        // único (fecha, visitante) apenas un equipo era visitante dos veces.
        $zona = $this->zonaDeLaRonda($gameday, $grupos, $filas);
        if ($zona) {
            return $this->aplicarZona($filas, $torneo, $zona, $comp, $gameday, $confirmar, $html, $alFixture);
        }

        // ── equipo -> grupo, según las plantillas del torneo ─────────────────
        // UN EQUIPO PUEDE TENER PLANTILLA EN DOS GRUPOS: su zona y el grupo de
        // llaves, cuando ya se le cargó el plantel de los playoffs. Antes se
        // quedaba con la última fila que leía, así que el mismo equipo caía a
        // veces en la zona y a veces en Playoffs según el orden de la consulta.
        // Ahora: $grupoDe es la ZONA (el grupo sin penales, si tiene), y
        // $enLlaves marca a los que ya tienen plantilla en el grupo de llaves.
        $plantillasTorneo = DB::table('plantillas')
            ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
            ->where('grupos.torneo_id', $torneo->id)
            ->select('plantillas.equipo_id', 'plantillas.grupo_id', 'grupos.penales')->get();

        $grupoDe = []; $enLlaves = [];
        foreach ($plantillasTorneo as $pl) {
            $eq = (int) $pl->equipo_id;
            if (!empty($pl->penales)) {
                $enLlaves[$eq][(int) $pl->grupo_id] = true;
                if (!isset($grupoDe[$eq])) $grupoDe[$eq] = (int) $pl->grupo_id;
            } else {
                // La zona le gana al grupo de llaves.
                if (!isset($grupoDe[$eq]) || isset($enLlaves[$eq][$grupoDe[$eq]])) {
                    $grupoDe[$eq] = (int) $pl->grupo_id;
                }
            }
        }

        $unico = $grupos->count() === 1 ? (int) $grupos->keys()->first() : null;

        // ── ¿ESTE TORNEO TIENE LLAVES? ──────────────────────────────────────
        // El grupo de playoffs es el que tiene `penales` prendido: sus fechas
        // no son números sino «Octavos de final», y sus partidos cruzan zonas
        // POR DEFINICIÓN. Rutear por la plantilla del local ahí no sirve de
        // nada: los marca a todos «interzonal» y no crea ninguno.
        //
        // El interzonal de verdad —dos zonas del mismo torneo que se cruzan en
        // una fecha común— es cosa de Argentina, y sigue funcionando igual en
        // los torneos que no tienen grupo de llaves.
        $conPenales = $grupos->filter(function ($g) { return !empty($g->penales); });
        $playoffId  = $conPenales->count() === 1 ? (int) $conPenales->keys()->first() : null;

        // Cuántos partidos de ESTA fecha cruzan zonas, y cuántos son entre dos
        // equipos que ya tienen plantilla en el grupo de llaves.
        $cruzan = 0; $conDosGrupos = 0; $ambosEnLlaves = 0;
        foreach ($filas as $r) {
            $a = isset($grupoDe[(int) $r->equipo_id]) ? $grupoDe[(int) $r->equipo_id] : null;
            $b = isset($grupoDe[(int) $r->rival_id]) ? $grupoDe[(int) $r->rival_id] : null;
            if ($a && $b) { $conDosGrupos++; if ($a !== $b) $cruzan++; }
            if ($playoffId && isset($enLlaves[(int) $r->equipo_id][$playoffId])
                && isset($enLlaves[(int) $r->rival_id][$playoffId])) $ambosEnLlaves++;
        }

        // LA DECISIÓN ES POR FECHA ENTERA, NO PARTIDO POR PARTIDO. De cuartos
        // en adelante pueden cruzarse dos equipos del mismo grupo: mirando
        // partido por partido, ése sería el único que caería en la zona A
        // mientras sus hermanos van a Playoffs.
        //
        // Y LA PLANTILLA DEL GRUPO DE LLAVES MANDA: si los dos equipos de cada
        // partido ya están en el plantel de Playoffs, la fecha es de playoffs
        // aunque la cuenta de cruces no dé mayoría. Caso real: semifinales de
        // la Libertadores 2026 (fecha 17) — 2 partidos, uno entre dos equipos
        // del mismo grupo de la fase de grupos: 1 cruce sobre 2 no es mayoría,
        // y la semifinal Estudiantes–Flamengo se proponía para la zona A.
        $proponeLlaves = $playoffId && (
            ($conDosGrupos > 0 && $cruzan * 2 > $conDosGrupos)
            || $ambosEnLlaves === $filas->count()
        );

        // `grupo_destino` = toda la fecha va a ese grupo. Sin él se rutea por
        // plantilla, que es lo que corresponde en una liga con zonas.
        $grupoDestino = (int) $request->get('grupo_destino', 0);
        if ($grupoDestino && !$grupos->has($grupoDestino)) $grupoDestino = 0;
        if (!$grupoDestino && $proponeLlaves && (string) $request->get('modo', '') !== 'plantilla') {
            $grupoDestino = $playoffId;
        }

        // ¿EL GAMEDAY SIRVE COMO NOMBRE DE FECHA? Cuando el fixture sale del
        // calendario en HTML, TM puede no traer encabezado de ronda y
        // `normalizarFixture()` guarda «—». Ese valor termina siendo el
        // `numero` de la fecha que se crea: una fecha llamada «—» no es lo que
        // quiso nadie, así que hay que preguntar cómo se llama.
        $sinRonda = ($gameday === '' || $gameday === '—');

        // UN SOLO GRUPO: SE PREGUNTA IGUAL POR LA FECHA. `$proponeLlaves` exige
        // partidos CRUZANDO zonas, y en una copa internacional todas las rondas
        // viven en el mismo grupo de llaves: no cruza nada, así que este bloque
        // era inalcanzable y la fecha se creaba con el gameday de TM («—» en la
        // Sudamericana 2015, con las fechas de verdad —Primera etapa, Octavos—
        // ya cargadas al lado). Con un solo grupo, rutear por plantilla y
        // mandar todo a ese grupo son lo mismo, así que `modo=plantilla` no lo
        // apaga: lo único que cambia es que ahora se puede elegir la fecha.
        if (!$grupoDestino && $unico !== null && ($playoffId === $unico || $sinRonda)) {
            $grupoDestino = $unico;
        }

        // En un grupo de llaves la plantilla no decide nada, así que un equipo
        // sin plantilla no es motivo para no crear el partido: los que se van
        // en la primera ronda nunca la tienen. Se avisa igual, porque que no
        // exista NINGUNO de los dos suele significar torneo equivocado.
        $sinPlantillaIgual = (string) $request->get('sin_plantilla', '0') === '1';

        $plan = []; $sinPlantilla = []; $inter = [];
        foreach ($filas as $r) {
            $lId = (int) $r->equipo_id; $vId = (int) $r->rival_id;
            $gl = isset($grupoDe[$lId]) ? $grupoDe[$lId] : null;
            $gv = isset($grupoDe[$vId]) ? $grupoDe[$vId] : null;

            if ($unico !== null) { $gl = $gl ?: $unico; $gv = $gv ?: $unico; }

            if ($grupoDestino) {
                if (!$gl && !$gv) {
                    $sinPlantilla[] = $r;
                    if (!$sinPlantillaIgual) continue;
                }
                $plan[] = ['fila' => $r, 'grupo_id' => $grupoDestino,
                    'nota' => (!$gl && !$gv) ? 'ninguno de los dos tiene plantilla en el torneo' : ''];
                continue;
            }

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

        // ── UN EQUIPO CON DOS RIVALES EN LA MISMA RONDA ─────────────────────
        // En una ronda de TM cada equipo juega contra UN rival (una vez, o dos
        // si la ronda trae ida y vuelta juntas). Si aparece contra dos rivales
        // distintos, uno de esos partidos tiene un club mal: un mapeo de TM
        // equivocado o un error del propio calendario de TM. Caso real, Copa
        // del Rey 2001/02, octavos de ida: «Córdoba – Mallorca» y «Figueres –
        // Córdoba», y la vuelta decía «Novelda – Figueres». En un grupo de
        // llaves no corre el control de «otro partido del equipo en la fecha»,
        // así que los dos se crearon y el error recién saltó en la vuelta.
        //
        // Esos partidos se muestran y NO se crean: el resto de la ronda sí.
        $rivalesDe = [];
        foreach ($plan as $x) {
            $l = (int) $x['fila']->equipo_id; $v = (int) $x['fila']->rival_id;
            if (!$l || !$v) continue;
            $rivalesDe[$l][$v] = true;
            $rivalesDe[$v][$l] = true;
        }
        $repetidos = [];
        foreach ($rivalesDe as $eq => $rs) {
            if (count($rs) > 1) $repetidos[$eq] = true;
        }
        $dudosos = [];
        if ($repetidos) {
            $limpio = [];
            foreach ($plan as $x) {
                $l = (int) $x['fila']->equipo_id; $v = (int) $x['fila']->rival_id;
                if (isset($repetidos[$l]) || isset($repetidos[$v])) $dudosos[] = $x['fila'];
                else $limpio[] = $x;
            }
            $plan = $limpio;
            $nombres = [];
            foreach (array_keys($repetidos) as $eq) $nombres[] = $this->nombreEquipo($eq);
            $html .= '<div class="err-box"><b>' . count($dudosos) . ' partidos NO se crean:</b> '
                . e(implode(', ', $nombres)) . ' ' . (count($nombres) > 1 ? 'aparecen' : 'aparece')
                . ' contra dos rivales distintos en esta ronda, así que uno de estos partidos tiene un club mal '
                . '(el mapeo de TM, o el calendario de TM). Abrí la ficha en TM, corregí el mapeo o el partido, '
                . '«Guardar en staging» y volvé a aplicar: el resto de la ronda se crea igual.<br>';
            foreach ($dudosos as $r) {
                $html .= e(substr((string) $r->dia, 0, 10) . ' · ' . $r->club_nombre . ' (' . $this->nombreEquipo($r->equipo_id)
                    . ') vs ' . $r->rival_nombre . ' (' . $this->nombreEquipo($r->rival_id) . ')')
                    . $this->linkTm($r->external_id) . '<br>';
            }
            $html .= '</div>';
            if (empty($plan)) {
                return $this->pagina('Aplicar fecha', $html . '<p class="err-box">No queda nada que crear.</p>');
            }
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
                . ($grupoDestino ? '' : $this->card(count($inter), 'interzonales', count($inter) ? 'warn' : ''))
                . $this->card(count($sinPlantilla), 'sin plantilla',
                    count($sinPlantilla) ? ($grupoDestino ? 'warn' : 'err') : '')
                . '</div>';

            // Cuando la fecha entera va a un grupo, este cuadro dice una sola
            // cosa y la dice el formulario de abajo: sobra.
            if (!$grupoDestino && !empty($porGrupo)) {
                $html .= '<h2>A qué grupo va cada uno</h2><div class="scroll"><table><thead><tr>'
                    . '<th>Grupo</th><th>Partidos</th></tr></thead><tbody>';
                foreach ($porGrupo as $g => $n) {
                    $html .= '<tr><td>' . e($grupos[$g]->nombre) . ' <span class="id">#' . (int) $g . '</span></td>'
                        . '<td class="num">' . $n . '</td></tr>';
                }
                $html .= '</tbody></table></div>';
            }

            // ── A qué grupo y a qué FECHA va, cuando va entera ──────────
            $fechaDestino = 0; $fechaNombre = '';
            if ($grupoDestino) {
                $fechaDestino = (int) $request->get('fecha_destino', 0);
                $fechaNombre  = trim((string) $request->get('fecha_nombre', ''));

                if (!$request->has('fecha_destino') && $fechaNombre === '') {
                    // La ida ya cargada es el mejor dato que hay: si estas
                    // mismas llaves ya tienen partido en una fecha de este
                    // torneo, la vuelta va ahí. Sirve igual para reaplicar una
                    // fecha que quedó a medias.
                    $fechaDestino = (int) $this->fechaDeLasLlaves($filas, $torneo->id);
                    // `nombreDeRonda()` adivina por la CANTIDAD de partidos, y
                    // sin ronda de TM eso miente: una llave sola —ida y
                    // vuelta— son 2 partidos y propondría «Semifinal». Mejor
                    // vacío: la pantalla lo pide y no crea nada hasta tenerlo.
                    if (!$fechaDestino && !$sinRonda) $fechaNombre = $this->nombreDeRonda(count($filas), $gameday);
                }

                $optsG = '';
                foreach ($grupos as $gid => $g) {
                    $optsG .= '<option value="' . (int) $gid . '"' . ((int) $gid === $grupoDestino ? ' selected' : '')
                        . '>' . e($g->nombre) . (!empty($g->penales) ? ' · llaves' : '') . '</option>';
                }

                $optsF = '<option value="0">— fecha nueva —</option>';
                foreach (\App\Fecha::where('grupo_id', $grupoDestino)->orderBy('orden')->orderBy('id')->get() as $fx) {
                    $optsF .= '<option value="' . (int) $fx->id . '"' . ($fechaDestino === (int) $fx->id ? ' selected' : '')
                        . '>' . e($fx->numero) . '</option>';
                }

                $html .= '<div class="ok-box"><div><b>Toda la fecha va a un solo grupo.</b> '
                    . ($unico !== null
                        ? 'Este torneo tiene un solo grupo, así que no hay nada que rutear: lo único que falta '
                          . 'decidir es a qué fecha van.'
                          . ($sinRonda ? ' Transfermarkt no trajo el nombre de la ronda —quedó «—»—, '
                              . 'así que tampoco se puede deducir.' : '')
                        : ($grupoDestino === $playoffId
                            ? 'Los dos equipos de cada partido están en zonas distintas: esto es una ronda de playoffs, '
                              . 'no una fecha con interzonales. En un grupo de llaves la fecha se llama por su ronda '
                              . '(«Octavos de final») y la ida y la vuelta van juntas en la misma.'
                            : 'Elegido a mano.'))
                    . '</div>'
                    . '<form method="get" action="' . e(route('import_partidos.fixture_aplicar')) . '" style="margin-top:10px">'
                    . '<input type="hidden" name="comp" value="' . e($comp) . '">'
                    . '<input type="hidden" name="gameday" value="' . e($gameday) . '">'
                    . '<input type="hidden" name="torneo_id" value="' . (int) $torneo->id . '">'
                    . '<div>Grupo: <select name="grupo_destino" class="s2" data-placeholder="grupo destino…">'
                    . $optsG . '</select></div>'
                    . '<div style="margin-top:6px">Fecha: <select name="fecha_destino" class="s2" data-placeholder="fecha del grupo…">'
                    . $optsF . '</select> o una nueva llamada '
                    . '<input type="text" name="fecha_nombre" value="' . e($fechaDestino ? '' : $fechaNombre) . '" '
                    . 'placeholder="Cuartos de final"></div>'
                    . (!empty($sinPlantilla) ? '<div style="margin-top:6px"><label><input type="checkbox" '
                        . 'name="sin_plantilla" value="1"' . ($sinPlantillaIgual ? ' checked' : '') . '> crear igual los '
                        . count($sinPlantilla) . ' partidos sin plantilla</label></div>' : '')
                    . '<p class="acciones"><button>Ver de nuevo con esto</button> '
                    // Con un solo grupo, rutear por plantilla lleva EXACTAMENTE
                    // al mismo lugar: el botón sólo serviría para perder el
                    // selector de fecha.
                    . ($unico !== null ? '' : '<a class="boton-sec" href="'
                        . e(route('import_partidos.fixture_aplicar', ['comp' => $comp,
                            'gameday' => $gameday, 'torneo_id' => $torneo->id, 'modo' => 'plantilla']))
                        . '">Mejor rutear por plantilla</a>')
                    . '</p></form></div>';

                if ($fechaDestino === 0 && $fechaNombre === '') {
                    $html .= '<p class="err-box">Falta decir a qué fecha del grupo van: elegí una de la lista o '
                        . 'escribí el nombre de una nueva.</p>';
                }
            }

            if (!empty($sinPlantilla)) {
                $html .= '<p class="' . ($grupoDestino ? 'warn-box' : 'err-box') . '"><b>' . count($sinPlantilla)
                    . ' partidos sin plantilla:</b> ni el local ni el visitante tienen plantilla en este torneo. '
                    . ($grupoDestino
                        ? 'En un grupo de llaves eso es normal en las primeras rondas —el que queda eliminado nunca '
                          . 'llega a tener plantilla—, pero que pase en TODOS suele significar que el torneo elegido '
                          . 'no es éste. ' . ($sinPlantillaIgual ? 'Los estás creando igual.' : 'Por ahora no se crean.')
                        : 'Puede que hayas elegido el torneo equivocado, o que falte cargarles la plantilla. '
                          . 'Esos no se crean.')
                    . '<br><span class="sub">';
                foreach (array_slice($sinPlantilla, 0, 10) as $r) {
                    $html .= e($r->club_nombre . ' vs ' . $r->rival_nombre) . ' · ';
                }
                $html .= '</span></p>';
            }

            if (!$grupoDestino && !empty($inter)) {
                $html .= '<p class="ok-box"><b>' . count($inter) . ' interzonales</b> (los dos equipos están en grupos '
                    . 'distintos). Por defecto <b>no</b> se crean, porque hay que decidir en qué zona van.<br>'
                    . '<a class="boton-sec" href="' . e(route('import_partidos.fixture_aplicar', ['comp' => $comp,
                        'gameday' => $gameday, 'torneo_id' => $torneo->id, 'modo' => 'plantilla', 'interzonales' => 1]))
                    . '">Incluirlos, en el grupo del local</a>'
                    . ($playoffId ? ' <a class="boton" href="' . e(route('import_partidos.fixture_aplicar',
                        ['comp' => $comp, 'gameday' => $gameday, 'torneo_id' => $torneo->id,
                         'grupo_destino' => $playoffId]))
                        . '">Mandar toda la fecha a ' . e($grupos[$playoffId]->nombre) . '</a>' : '')
                    . '</p>';
            }

            // ── RUTEO POR PLANTILLA Y TM SIN NOMBRE DE RONDA ────────────────
            // Acá la fecha se crea en CADA grupo con el gameday de TM como
            // `numero`. Si TM no trajo ronda ese numero sería «—», que no es
            // nombre de nada: se pide una vez y vale para todos los grupos.
            $fechaLibre = trim((string) $request->get('fecha_nombre', ''));
            if (!$grupoDestino && $sinRonda) {
                $html .= '<div class="' . ($fechaLibre === '' ? 'err-box' : 'ok-box') . '">'
                    . '<div><b>Transfermarkt no trajo el nombre de la ronda</b> (quedó «—»). '
                    . 'La fecha se crea con ese nombre en cada grupo, así que decime cómo se llama.</div>'
                    . '<form method="get" action="' . e(route('import_partidos.fixture_aplicar')) . '" style="margin-top:10px">'
                    . '<input type="hidden" name="comp" value="' . e($comp) . '">'
                    . '<input type="hidden" name="gameday" value="' . e($gameday) . '">'
                    . '<input type="hidden" name="torneo_id" value="' . (int) $torneo->id . '">'
                    . '<input type="hidden" name="modo" value="plantilla">'
                    . ($interzonales ? '<input type="hidden" name="interzonales" value="1">' : '')
                    . 'Fecha: <input type="text" name="fecha_nombre" value="' . e($fechaLibre) . '" '
                    . 'placeholder="Cuartos de final"> <button>Ver de nuevo con esto</button>'
                    . '</form></div>';
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

            if ($grupoDestino && !$fechaDestino && $fechaNombre === '') {
                return $this->pagina('Aplicar fecha', $html);
            }
            if (!$grupoDestino && $sinRonda && $fechaLibre === '') {
                return $this->pagina('Aplicar fecha', $html);
            }

            $html .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_partidos.fixture_aplicar', array_filter([
                    'comp' => $comp, 'gameday' => $gameday, 'torneo_id' => $torneo->id,
                    'interzonales'  => (!$grupoDestino && $interzonales) ? 1 : null,
                    'modo'          => (!$grupoDestino && $playoffId) ? 'plantilla' : null,
                    'grupo_destino' => $grupoDestino ?: null,
                    'fecha_destino' => ($grupoDestino && $fechaDestino) ? $fechaDestino : null,
                    'fecha_nombre'  => $grupoDestino
                        ? (!$fechaDestino ? $fechaNombre : null)
                        : ($sinRonda ? $fechaLibre : null),
                    'sin_plantilla' => $sinPlantillaIgual ? 1 : null,
                    'confirmar' => 1])))
                . '">Crear estos ' . count($plan) . ' partidos'
                . ($grupoDestino ? ' en ' . e($grupos[$grupoDestino]->nombre) . ' · '
                    . e($fechaDestino ? \App\Fecha::where('id', $fechaDestino)->value('numero') : $fechaNombre) : '')
                . '</a> <span class="sub">recién acá se escribe</span></p>';

            return $this->pagina('Aplicar fecha', $html);
        }

        // ── Crear ───────────────────────────────────────────────────────────
        // LA FECHA DESTINO, cuando la fecha entera va a un grupo. En un grupo de
        // llaves el `numero` no es el gameday de TM sino el nombre de la ronda,
        // y dos gamedays —ida y vuelta— caen en la MISMA fecha, así que se
        // resuelve una sola vez y fuera del loop.
        $fechaFijada = null;
        if ($grupoDestino) {
            $fechaDestino = (int) $request->get('fecha_destino', 0);
            $fechaNombre  = trim((string) $request->get('fecha_nombre', ''));

            if ($fechaDestino) {
                $fechaFijada = \App\Fecha::where('grupo_id', $grupoDestino)->where('id', $fechaDestino)->first();
            }
            if (!$fechaFijada && $fechaNombre !== '') {
                $fechaFijada = \App\Fecha::where('grupo_id', $grupoDestino)->where('numero', $fechaNombre)->first();
            }
            if (!$fechaFijada) {
                if ($fechaNombre === '') {
                    return $this->pagina('Aplicar fecha', $html
                        . '<p class="err-box">No creé nada: falta decir a qué fecha del grupo van. Volvé a la '
                        . 'previsualización y elegí una fecha existente o escribí el nombre de una nueva.</p>');
                }
                $fechaFijada = new \App\Fecha();
                $fechaFijada->forceFill([
                    'numero'     => $fechaNombre,
                    'grupo_id'   => $grupoDestino,
                    'orden'      => ((int) \App\Fecha::where('grupo_id', $grupoDestino)->max('orden')) + 1,
                    'url_nombre' => Str::slug($fechaNombre),
                ])->save();
            }
        }

        // EL `numero` DE LA FECHA cuando se rutea por plantilla: el gameday de
        // TM, salvo que TM no haya traído ronda y lo hayas escrito vos.
        $numeroFecha = $gameday;
        if (!$grupoDestino && $sinRonda) {
            $numeroFecha = trim((string) $request->get('fecha_nombre', ''));
            if ($numeroFecha === '') {
                return $this->pagina('Aplicar fecha', $html
                    . '<p class="err-box">No creé nada: Transfermarkt no trajo el nombre de la ronda y hace '
                    . 'falta uno para crear la fecha. Volvé a la previsualización y escribilo.</p>');
            }
        }

        $creados = 0; $errores = []; $detalle = ''; $fechasTocadas = [];
        foreach ($plan as $x) {
            $r = $x['fila']; $gId = (int) $x['grupo_id'];
            try {
                $fecha = $fechaFijada;
                if (!$fecha) {
                    $fecha = \App\Fecha::where('grupo_id', $gId)->where('numero', $numeroFecha)->first();
                }
                if (!$fecha) {
                    $fecha = new \App\Fecha();
                    $fecha->forceFill([
                        'numero'     => $numeroFecha,
                        'grupo_id'   => $gId,
                        'orden'      => is_numeric($numeroFecha) ? (int) $numeroFecha : 999,
                        'url_nombre' => Str::slug('fecha-' . $numeroFecha),
                    ])->save();
                }
                $fechasTocadas[$gId] = true;

                $lId = (int) $r->equipo_id; $vId = (int) $r->rival_id;

                // EN UNA LLAVE EL MISMO PAR JUEGA DOS VECES y las dos van en la
                // misma fecha: «Octavos de final» tiene la ida y la vuelta.
                // El control de siempre —«ya hay un partido de este equipo en
                // esta fecha»— dejaría afuera la vuelta SIEMPRE, así que en un
                // grupo con penales se bloquea sólo el mismo partido: mismo par
                // y misma localía. En un grupo normal el control queda como
                // estaba, que es la red que atrapa un mapeo de club equivocado.
                if (!empty($grupos[$gId]->penales)) {
                    $ya = \App\Partido::where('fecha_id', $fecha->id)
                        ->where('equipol_id', $lId)->where('equipov_id', $vId)->first();
                    if ($ya) {
                        $errores[] = 'Ya está cargado ' . $this->nombreEquipo($lId) . ' vs '
                            . $this->nombreEquipo($vId) . ' en ' . $fecha->numero . ' (#' . $ya->id . ').';
                        continue;
                    }
                    $choque = $this->choqueLocalia($fecha, $lId, $vId, $r->dia);
                    if ($choque !== null) { $errores[] = $choque; continue; }
                } else {
                    $ya = \App\Partido::where('fecha_id', $fecha->id)
                        ->where(function ($q) use ($lId, $vId) {
                            $q->where('equipol_id', $lId)->orWhere('equipov_id', $lId)
                                ->orWhere('equipol_id', $vId)->orWhere('equipov_id', $vId);
                        })->first();
                    if ($ya) {
                        $errores[] = 'Ya hay un partido de ' . $this->nombreEquipo($lId) . ' en la fecha ' . $numeroFecha
                            . ' del grupo ' . $grupos[$gId]->nombre . ' (#' . $ya->id . ').';
                        continue;
                    }
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
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · fecha ' . e($gameday)
            . ($fechaFijada
                ? ' de TM → <b>' . e($fechaFijada->numero) . '</b> del grupo ' . e($grupos[$grupoDestino]->nombre)
                : ($numeroFecha !== $gameday ? ' de TM → <b>' . e($numeroFecha) . '</b>' : '')) . '</p>';

        if (!empty($errores)) {
            $html .= '<p class="err-box"><b>' . count($errores) . ' quedaron sin crear:</b><br>' . e(implode(' — ', $errores)) . '</p>';
        }
        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Día</th><th>Local</th><th>Res.</th>'
                . '<th>Visitante</th><th>Grupo</th><th>Partido</th></tr></thead><tbody>' . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones">'
            . '<a class="boton" href="' . e($alFixture) . '">Seguir con otra fecha →</a>'
            . '<a class="boton-sec" href="' . e(route('import_detalles.index')) . '">Bajar el detalle de estos partidos</a></p>';

        return $this->pagina('Aplicar fecha', $html);
    }

    /**
     * La zona del torneo que corresponde a una ronda de TM «Grupo 15».
     *
     * Acepta que la zona se llame «15», «Grupo 15» o «G15», sin importar
     * mayúsculas. Nunca un grupo de llaves (`penales`), y sólo si hay UNA que
     * coincide: con dos, se sigue por el camino de siempre.
     */
    private function zonaDeLaRonda($gameday, $grupos, $filas = null)
    {
        if (!preg_match('/^(?:grupo|group|gruppe|groep)\s+(\S+)$/iu', trim((string) $gameday), $m)) return null;
        $clave = mb_strtolower($m[1]);

        // OJO con la «g» suelta: se saca sólo si le sigue un número («G15»).
        // Antes la regla era `(grupo|group|zona|g)\s*` y a una zona llamada
        // «G» le comía el nombre entero: quedaba '' y nunca coincidía con la
        // ronda «Grupo G» (Champions 2002/03: los 12 partidos del G fueron a
        // UNA sola fecha y 10 chocaron con «Ya hay un partido de...»).
        $hallados = $grupos->filter(function ($g) use ($clave) {
            if (!empty($g->penales)) return false;
            $n = mb_strtolower(trim((string) $g->nombre));
            $n = preg_replace('/^(?:(?:grupo|group|zona)\s*|g(?=\d))/u', '', $n);
            return $n === $clave;
        });

        // DOS FASES DE GRUPOS con los mismos nombres (1ra fase A..H, 2da A..D):
        // desempata la plantilla — la zona donde tienen plantilla los equipos
        // de esta ronda. Si sigue sin quedar una sola, camino de siempre.
        if ($hallados->count() > 1 && $filas !== null) {
            $equipos = [];
            foreach ($filas as $r) {
                if ($r->equipo_id) $equipos[(int) $r->equipo_id] = true;
                if ($r->rival_id)  $equipos[(int) $r->rival_id]  = true;
            }
            $cuenta = [];
            foreach ($hallados as $g) {
                $cuenta[$g->id] = \App\Plantilla::where('grupo_id', $g->id)
                    ->whereIn('equipo_id', array_keys($equipos))->count();
            }
            arsort($cuenta);
            $ids = array_keys($cuenta);
            if ($cuenta[$ids[0]] > 0 && (count($ids) < 2 || $cuenta[$ids[0]] > $cuenta[$ids[1]])) {
                return $hallados->get($ids[0]);
            }
            return null;
        }

        return $hallados->count() === 1 ? $hallados->first() : null;
    }

    /**
     * Número de fecha (1, 2, 3...) de cada partido de una zona.
     *
     * TM no dice en qué fecha del grupo va cada partido, sólo el día. Se
     * recorren por día y cada uno va a la PRIMERA fecha donde todavía no jugó
     * ninguno de sus dos equipos: así ningún equipo juega dos veces en la misma
     * fecha, que es lo que exige el índice único de `partidos`. En un grupo de
     * 4 a una rueda salen 3 fechas de 2 partidos; en uno de 3, 3 fechas de 1
     * (uno libre por fecha). Comprobado a mano con los grupos 15, 16 y 18 de
     * la KNVB Beker 2000/01.
     *
     * Lo que ya está cargado en la zona cuenta: si un equipo ya tiene partido
     * en la fecha 1, el nuevo no va ahí.
     *
     * Devuelve [import_partidos.id => número].
     */
    private function fechasDeZona($filas, $zona)
    {
        $ocupado = [];   // número => [equipo_id => true]
        foreach (\App\Fecha::where('grupo_id', $zona->id)->get() as $f) {
            $k = (int) preg_replace('/\D/', '', (string) $f->numero);
            if ($k <= 0) continue;
            foreach (DB::table('partidos')->where('fecha_id', $f->id)->get(['equipol_id', 'equipov_id']) as $p) {
                $ocupado[$k][(int) $p->equipol_id] = true;
                $ocupado[$k][(int) $p->equipov_id] = true;
            }
        }

        $orden = $filas->sortBy(function ($r) { return (string) $r->dia . sprintf('%012d', (int) $r->id); });

        $asignado = [];
        foreach ($orden as $r) {
            $l = (int) $r->equipo_id; $v = (int) $r->rival_id;
            for ($k = 1; ; $k++) {
                if (empty($ocupado[$k][$l]) && empty($ocupado[$k][$v])) break;
            }
            $ocupado[$k][$l] = true;
            $ocupado[$k][$v] = true;
            $asignado[(int) $r->id] = $k;
        }
        return $asignado;
    }

    /** La fecha de la zona con ese número: «1» o «Fecha 1» sirven. */
    private function fechaDeZonaPorNumero($zonaId, $k)
    {
        foreach (\App\Fecha::where('grupo_id', $zonaId)->orderBy('id')->get() as $f) {
            if ((int) preg_replace('/\D/', '', (string) $f->numero) === (int) $k) return $f;
        }
        return null;
    }

    /**
     * Aplica una ronda de TM que es una zona entera («Grupo 15»).
     *
     * Muestra el reparto en fechas y recién con `confirmar=1` escribe. Las
     * fechas que faltan se crean con el número pelado («1», «2», «3») y ese
     * mismo orden; las que existen se reusan.
     */
    private function aplicarZona($filas, $torneo, $zona, $comp, $gameday, $confirmar, $html, $alFixture)
    {
        $numeros = $this->fechasDeZona($filas, $zona);

        $sinMapear = $filas->filter(function ($r) { return !$r->equipo_id || !$r->rival_id; });
        if ($sinMapear->count()) {
            return $this->pagina('Aplicar fecha', $html
                . '<p class="err-box">No creé nada: ' . $sinMapear->count() . ' partido(s) de esta zona tienen un '
                . 'club sin mapear. Mapealos en la pantalla del fixture y volvé.</p>');
        }

        if (!$confirmar) {
            $html .= '<p class="ok-box"><b>Es una zona:</b> los ' . $filas->count() . ' partidos van al grupo <b>'
                . e($zona->nombre) . '</b> de ' . e($torneo->nombre . ' ' . $torneo->year) . ', repartidos en fechas '
                . 'por día: cada partido va a la primera fecha donde no jugó ninguno de sus dos equipos. '
                . 'Las fechas que no existan se crean.</p>'
                . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Día</th><th>Local</th><th>Res.</th>'
                . '<th>Visitante</th><th></th></tr></thead><tbody>';

            $lista = $filas->sortBy(function ($r) use ($numeros) {
                return sprintf('%04d', $numeros[(int) $r->id]) . (string) $r->dia;
            });
            foreach ($lista as $r) {
                $k = $numeros[(int) $r->id];
                $existe = $this->fechaDeZonaPorNumero($zona->id, $k);
                $html .= '<tr><td class="num">' . $k . '</td>'
                    . '<td class="num">' . e(substr((string) $r->dia, 0, 10)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($r->equipo_id)) . '</td>'
                    . '<td class="num">' . ($r->goles_favor === null ? '—' : e($r->goles_favor) . ':' . e($r->goles_contra)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($r->rival_id)) . '</td>'
                    . '<td class="sub">' . ($existe ? 'fecha «' . e($existe->numero) . '» ya existe' : 'se crea la fecha') . '</td></tr>';
            }
            $html .= '</tbody></table></div>'
                . '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.fixture_aplicar', [
                    'comp' => $comp, 'gameday' => $gameday, 'torneo_id' => $torneo->id, 'confirmar' => 1]))
                . '">Crear estos ' . $filas->count() . ' partidos en el grupo ' . e($zona->nombre) . '</a> '
                . '<span class="sub">recién acá se escribe</span></p>';

            return $this->pagina('Aplicar fecha', $html);
        }

        $creados = 0; $errores = []; $detalle = '';
        foreach ($filas->sortBy('dia') as $r) {
            try {
                $k = $numeros[(int) $r->id];
                $fecha = $this->fechaDeZonaPorNumero($zona->id, $k);
                if (!$fecha) {
                    $fecha = new \App\Fecha();
                    $fecha->forceFill([
                        'numero'     => (string) $k,
                        'grupo_id'   => $zona->id,
                        'orden'      => $k,
                        'url_nombre' => Str::slug('fecha-' . $k),
                    ])->save();
                }

                $lId = (int) $r->equipo_id; $vId = (int) $r->rival_id;
                $ya = \App\Partido::where('fecha_id', $fecha->id)
                    ->where(function ($q) use ($lId, $vId) {
                        $q->where('equipol_id', $lId)->orWhere('equipov_id', $lId)
                            ->orWhere('equipol_id', $vId)->orWhere('equipov_id', $vId);
                    })->first();
                if ($ya) {
                    $errores[] = 'Ya hay un partido de ' . $this->nombreEquipo($lId) . ' o ' . $this->nombreEquipo($vId)
                        . ' en la fecha ' . $fecha->numero . ' del grupo ' . $zona->nombre . ' (#' . $ya->id . ').';
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

                $detalle .= '<tr><td class="num">' . e($fecha->numero) . '</td>'
                    . '<td class="num">' . e(substr((string) $r->dia, 0, 10)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($lId)) . '</td>'
                    . '<td class="num">' . ($r->goles_favor === null ? '—' : e($r->goles_favor) . ':' . e($r->goles_contra)) . '</td>'
                    . '<td>' . e($this->nombreEquipo($vId)) . '</td>'
                    . '<td class="num">#' . $partido->id . '</td></tr>';
                $creados++;
            } catch (\Throwable $ex) {
                $errores[] = 'Error en ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ': ' . $ex->getMessage();
                Log::error('aplicarZona: ' . $ex->getMessage());
            }
        }

        $this->recontarEquipos($zona->id);

        $html .= '<h1>Creados ' . $creados . ' partidos</h1>'
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · ' . e($gameday) . ' de TM → grupo <b>'
            . e($zona->nombre) . '</b></p>';
        if (!empty($errores)) {
            $html .= '<p class="err-box"><b>' . count($errores) . ' quedaron sin crear:</b><br>' . e(implode(' — ', $errores)) . '</p>';
        }
        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Día</th><th>Local</th><th>Res.</th>'
                . '<th>Visitante</th><th>Partido</th></tr></thead><tbody>' . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones"><a class="boton" href="' . e($alFixture) . '">Seguir con otra fecha →</a></p>';

        return $this->pagina('Aplicar fecha', $html);
    }

    /**
     * A qué fecha de llaves pertenecen estos partidos, según lo YA cargado.
     *
     * La vuelta de una llave es el mismo par de equipos con la localía dada
     * vuelta, y va en la misma fecha que la ida. Así que si estos pares ya
     * tienen partido en una fecha de un grupo con penales de este torneo, ésa
     * es la fecha: no hay que adivinar el nombre de la ronda ni preguntarlo dos
     * veces. Sirve igual para reaplicar una fecha que quedó a medias.
     *
     * Devuelve el id de la fecha donde cayeron MÁS de estos pares, o null.
     */
    private function fechaDeLasLlaves($filas, $torneoId)
    {
        $pares = [];
        foreach ($filas as $r) {
            $a = (int) $r->equipo_id; $b = (int) $r->rival_id;
            if ($a && $b) $pares[] = [$a, $b];
        }
        if (empty($pares)) return null;

        $rows = DB::table('partidos')
            ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->join('grupos', 'grupos.id', '=', 'fechas.grupo_id')
            ->where('grupos.torneo_id', $torneoId)
            ->where('grupos.penales', 1)
            ->where(function ($w) use ($pares) {
                foreach ($pares as $p) {
                    $w->orWhere(function ($x) use ($p) {
                        $x->where('partidos.equipol_id', $p[0])->where('partidos.equipov_id', $p[1]);
                    });
                    $w->orWhere(function ($x) use ($p) {
                        $x->where('partidos.equipol_id', $p[1])->where('partidos.equipov_id', $p[0]);
                    });
                }
            })
            ->select('fechas.id AS fecha_id')->get();

        $cuenta = [];
        foreach ($rows as $f) {
            $id = (int) $f->fecha_id;
            $cuenta[$id] = isset($cuenta[$id]) ? $cuenta[$id] + 1 : 1;
        }
        if (empty($cuenta)) return null;
        arsort($cuenta);
        return (int) key($cuenta);
    }

    /**
     * Nombre sugerido para una ronda de llaves, por cuántos partidos trae.
     *
     * Es una SUGERENCIA editable, no una deducción: cuatro partidos pueden ser
     * los cuartos o una tercera ronda previa. TM manda un número de gameday y
     * nada más, así que el nombre lo termina de decidir el usuario.
     */
    private function nombreDeRonda($cuantos, $gameday)
    {
        $mapa = [1 => 'Final', 2 => 'Semifinal', 4 => 'Cuartos de final',
            8 => 'Octavos de final', 16 => 'Dieciseisavos de final'];
        return isset($mapa[$cuantos]) ? $mapa[$cuantos] : 'Ronda ' . $gameday;
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
     * escudo (crestUrl). La API NO trae fundación, estadio ni socios, pero el
     * SITIO sí los tiene en «Datos y hechos», así que se leen de ahí — ver
     * `datosClubDelSitio()`. La historia no existe en Transfermarkt en ninguna
     * parte: ésa es la única que queda siempre a mano.
     *
     * Cuesta 3 llamadas: datos, escudo y la página de «Datos y hechos»
     * (esta última se puede saltear con `&sitio=0`).
     */
    public function crearEquipo(Request $request)
    {
        set_time_limit(0);

        $tmId = trim((string) $request->get('tm_id', ''));
        $volverA = $request->get('volver');

        if ($tmId === '') {
            return redirect()->route('import_partidos.index')->with('error', 'Falta el id de Transfermarkt.');
        }

        // Diagnóstico: mirar qué se lee de «Datos y hechos» SIN crear nada.
        // Esto es HTML, así que el día que TM cambie el maquetado el síntoma va
        // a ser «me creó el club con el estadio vacío»; sin esta pantalla no hay
        // forma de saber si falló la bajada o el parseo.
        if ((string) $request->get('ver_datos', '0') === '1') {
            return $this->verDatosClubTm($tmId);
        }

        // ¿Ya está mapeado? Entonces no hay nada que crear.
        $yaMapeado = DB::table('equipo_tm')->where('tm_club_id', $tmId)->value('equipo_id');
        if ($yaMapeado && \App\Equipo::where('id', $yaMapeado)->exists()) {
            $links = $this->urlsClubTm($tmId, null);
            return redirect()->route('equipos.edit', $yaMapeado)
                ->with('error', 'El club de TM ' . e($tmId) . ' ya estaba mapeado a este equipo, así que no creé nada '
                    . 'ni toqué ningún dato. Si querés completarlo a mano: '
                    . '<a href="' . e($links['datos']) . '" target="_blank"><b>Datos y hechos ↗</b></a> · '
                    . '<a href="' . e($links['perfil']) . '" target="_blank">Perfil del club ↗</a> · '
                    . '<a href="' . e($this->urlWikipediaBusqueda((string) \App\Equipo::where('id', $yaMapeado)->value('nombre')))
                    . '" target="_blank">Buscar en Wikipedia ↗</a>');
        }

        $club = $this->clubDeTm($tmId);

        if (!is_array($club)) {
            $err = HttpHelper::getLastJsonError();
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'No pude traer el club ' . e($tmId) . ' de Transfermarkt. '
                    . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle'));
        }

        $base = isset($club['baseDetails']) && is_array($club['baseDetails']) ? $club['baseDetails'] : [];

        // Nombre: el MISMO que se ve en el sondeo ("Real Jaén CF"), que es el
        // `name` de la ficha —`resolverNombres()` toma ese campo—. Antes mandaba
        // el oficial largo del club superior ("Real Jaén Club De Fútbol S.A.D.")
        // y había que renombrar a mano cada club recién creado: es más completo,
        // pero no es el nombre con el que el usuario reconoce al club ni el que
        // figura en la pantalla desde la que apretó el botón. El largo queda de
        // respaldo para cuando la ficha venga sin `name`.
        $nombre = trim((string) (isset($club['name']) ? $club['name'] : ''));
        if ($nombre === '') $nombre = trim((string) (isset($base['superiorClub']['name']) ? $base['superiorClub']['name'] : ''));
        if ($nombre === '') $nombre = trim((string) (isset($base['officialName']) ? $base['officialName'] : ''));
        if ($nombre === '') {
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'El club ' . e($tmId) . ' vino sin nombre. No lo creé.');
        }

        // Club desaparecido: TM le cuelga el año de cierre al nombre, "(- 2019)"
        // o "(1981-2019)". El nombre del equipo va limpio y el año pasa a
        // `desaparicion` (1º de enero: TM no da más que el año). El mapeo en
        // `equipo_tm` guarda el nombre TAL CUAL lo manda TM, que es con lo que
        // se lo reconoce en el sondeo. Ver App\Services\ClubDesaparecido.
        $nombreTm = $nombre;
        $cierre = \App\Services\ClubDesaparecido::partir($nombre);
        if ($cierre) $nombre = $cierre['nombre'];

        // Siglas: SÓLO `abbreviation`, que es una abreviatura de verdad.
        //
        // Antes se usaba primero `preferences.clubCode`, que es un código
        // interno de Transfermarkt y no las siglas del club: de ahí salieron
        // "JAE" para Real Jaén y "96" para Hannover 96, que después hay que
        // borrar a mano. Si el club no tiene siglas, el campo va en blanco: una
        // sigla inventada es peor que ninguna, porque no se nota.
        $siglas = trim((string) (isset($base['abbreviation']) ? $base['abbreviation'] : ''));

        $pais = null;
        $paisId = (int) (isset($base['countryId']) ? $base['countryId'] : 0);
        if ($paisId) {
            $paises = \App\Http\Controllers\JugadorController::paisesTM();
            $pais = isset($paises[$paisId]) ? $paises[$paisId] : null;
        }

        $escudo = $this->bajarEscudo(isset($club['crestUrl']) ? $club['crestUrl'] : null);

        // ── 1. El alta, con lo que trajo la API y nada más ──────────────────
        //
        // PRIMERO se crea el club y recién DESPUÉS se va a buscar lo que falta.
        // Al revés —que es como estaba— cualquier problema del paso opcional
        // (la página del club, una columna que no acepta vacío) se llevaba
        // puesta el alta entera y el botón terminaba volviendo al sondeo sin
        // crear nada. Lo que se puede conseguir de más nunca puede costar lo
        // que ya se tenía.
        $alta = [
            'nombre'     => $nombre,
            'siglas'     => $siglas !== '' ? $siglas : null,
            'pais'       => $pais,
            'escudo'     => $escudo,
            'socios'     => 0,
            'url_nombre' => Str::slug($nombre),
        ];
        $conColumnaCierre = Schema::hasColumn('equipos', 'desaparicion');
        if ($cierre && $conColumnaCierre) {
            $alta['desaparicion'] = \App\Services\ClubDesaparecido::fechaDeCierre($cierre);
        }

        try {
            $equipo = \App\Equipo::create($alta);
        } catch (\Exception $e) {
            return redirect()->to($volverA ?: route('import_partidos.index'))
                ->with('error', 'No pude crear el equipo: ' . e($e->getMessage()));
        }

        $this->guardarMapeo($tmId, $equipo->id, $nombreTm, 'club_tm');

        // ── 2. Fundación, estadio y socios, que la API no tiene ─────────────
        //
        // Salen de «Datos y hechos» del sitio. De acá para abajo el club YA
        // está creado y mapeado: todo lo que falle se cuenta en el cartel y
        // se completa a mano, pero nadie vuelve al sondeo con las manos vacías.
        $sitio = ((string) $request->get('sitio', '1') === '0')
            ? $this->sitioVacio()
            : $this->datosClubDelSitio($tmId, isset($club['relativeUrl']) ? $club['relativeUrl'] : null);

        // Lo que no se consiguió queda VACÍO, no en cero ni en una fecha
        // inventada: un campo en blanco se ve y se completa, un 0 parece un
        // dato cargado y nadie lo vuelve a mirar.
        $completar = ['socios' => $sitio['socios']];
        if ($sitio['fundacion'] !== null) $completar['fundacion'] = $sitio['fundacion'];

        // TM a veces tiene en «Datos y hechos» del verein viejo una fundación
        // POSTERIOR a su propio cierre (Benidorm CF "(-2011)" con 13/10/2020,
        // Ciudad Murcia "(- 2007)" con 25/10/2010): el dato está mal en TM, no
        // en la lectura. Una de las dos fechas es falsa y no hay cómo saber
        // cuál, así que la fundación no se guarda y se avisa.
        $fundContradice = null;
        if ($cierre && isset($completar['fundacion'])
            && $completar['fundacion'] > \App\Services\ClubDesaparecido::fechaDeCierre($cierre)) {
            $fundContradice = $completar['fundacion'];
            unset($completar['fundacion']);
        }
        if ($sitio['estadio']   !== null) $completar['estadio']   = $sitio['estadio'];

        $avisoNulos = '';

        // Se escribe por query builder y NO con `$equipo->update()`: el modelo
        // se queda con los atributos sucios aunque el guardado falle, así que
        // el reintento volvía a mandar el mismo valor que lo había volteado y
        // se perdían también los campos que sí estaban bien.
        $guardar = function (array $campos) use ($equipo) {
            if (!$campos) return;
            \App\Equipo::where('id', $equipo->id)->update($campos);
        };

        try {
            $guardar($completar);
        } catch (\Exception $e) {
            // Los socios son el campo que puede traer un valor raro del sitio,
            // y también el único que va en NULL. Se los saca y se guarda el
            // resto: el club ya está creado, no se pierde nada más por esto.
            $avisoNulos = 'Los socios quedaron en <b>0</b>: la base rechazó lo que leí del sitio — '
                . e($e->getMessage()) . '<br>';

            unset($completar['socios']);
            $sitio['socios'] = null;

            try {
                $guardar($completar);
            } catch (\Exception $e2) {
                Log::error('crearEquipo: no pude completar el club ' . $equipo->id . ': ' . $e2->getMessage());
                $avisoNulos .= 'Tampoco pude guardar fundación y estadio: ' . e($e2->getMessage()) . '<br>';
                $completar = [];
            }
        }

        // Lo que sí se consiguió se dice tal cual quedó, para que se pueda
        // desconfiar de un dato raro sin tener que abrir TM.
        $trajo = [];
        if (isset($completar['fundacion'])) $trajo[] = 'fundación ' . $completar['fundacion'];
        if (isset($completar['estadio']))   $trajo[] = 'estadio «' . $completar['estadio'] . '»';
        if ($sitio['socios'] !== null)      $trajo[] = 'socios ' . number_format($sitio['socios'], 0, ',', '.');

        $falta = [];
        if (!$pais)                          $falta[] = 'país';
        if (!$escudo)                        $falta[] = 'escudo';
        if (!isset($completar['fundacion'])) $falta[] = 'fundación';
        if (!isset($completar['estadio']))   $falta[] = 'estadio';
        if ($sitio['socios'] === null)       $falta[] = 'socios';
        $falta[] = 'historia';   // ésta no está en Transfermarkt en ningún lado

        $links = $this->urlsClubTm($tmId, isset($club['relativeUrl']) ? $club['relativeUrl'] : null);

        $msg = 'Creé <b>' . e($nombre) . '</b> desde Transfermarkt y lo dejé mapeado al club ' . e($tmId)
            . ', así que en el sondeo ya no va a figurar como conflicto (refrescá esa pestaña).<br>';

        if ($trajo) {
            $msg .= 'De «Datos y hechos» saqué: <b>' . e(implode(' · ', $trajo)) . '</b>.<br>';
        }

        if ($fundContradice) {
            $msg .= '<b>No guardé la fundación:</b> TM dice <b>' . e($fundContradice) . '</b>, que es posterior a su '
                . 'propio año de cierre (' . (int) $cierre['hasta'] . '). El dato está mal en Transfermarkt; '
                . 'cargala a mano si la sabés.<br>';
        }

        if ($cierre) {
            $msg .= 'En TM figura como <b>' . e($nombreTm) . '</b>: es un club desaparecido. '
                . ($conColumnaCierre
                    ? 'Le saqué el paréntesis al nombre y cargué la desaparición en <b>01/01/' . (int) $cierre['hasta']
                        . '</b> (TM sólo da el año: corregí el día si lo sabés).'
                    : 'Le saqué el paréntesis al nombre, pero <b>no guardé el año de cierre</b>: falta correr '
                        . '<code>database/sql/desaparicion_equipos.sql</code>.')
                . '<br>';

            // Un nombre limpio puede ser el mismo de otro equipo nuestro —el club
            // actual, si éste es el verein viejo de una fusión o refundación—.
            // Eso deja el apareo por nombre ambiguo (que es lo seguro), pero
            // puede ser un duplicado para unificar: se avisa y no se decide.
            $homonimo = \App\Equipo::where('nombre', $nombre)->where('id', '<>', $equipo->id)->value('id');
            if ($homonimo) {
                $msg .= '<b>Ojo:</b> ya tenés otro equipo con ese nombre, '
                    . '<a href="' . e(route('equipos.edit', $homonimo)) . '" target="_blank">#' . (int) $homonimo . ' ↗</a>. '
                    . 'Si es el mismo club (fusión o refundación), unificalos en '
                    . '<a href="' . e(route('import_detalles.fusionar_equipos')) . '" target="_blank">Unificar equipos ↗</a>.<br>';
            }
        }

        // Si el sitio trajo la fundación pero sin día y mes, se avisa y NO se
        // guarda: un 1º de enero inventado no se distingue de uno real.
        if (!isset($completar['fundacion']) && $sitio['fundacion_crudo'] !== '') {
            $msg .= 'La fundación no la pude guardar porque el sitio la trae como <b>'
                . e($sitio['fundacion_crudo']) . '</b> y de ahí no sale una fecha completa.<br>';
        }

        $msg .= $avisoNulos
            . 'Queda para completar a mano: <b>' . e(implode(', ', $falta)) . '</b>'
            . ($sitio['leido'] ? '' : ' <span style="opacity:.7">(no pude leer la página del club)</span>') . '.<br>'
            . '<a href="' . e($links['datos']) . '" target="_blank"><b>Datos y hechos ↗</b></a> '
            . '<span style="opacity:.7">(fundación, estadio, socios)</span> · '
            . '<a href="' . e($links['perfil']) . '" target="_blank">Perfil del club ↗</a> · '
            . '<a href="' . e(route('import_partidos.crear_equipo', ['tm_id' => $tmId, 'ver_datos' => 1]))
            . '" target="_blank">Ver qué leí del sitio ↗</a> · '
            . '<a href="' . e($this->urlWikipediaBusqueda($nombre)) . '" target="_blank">Buscar en Wikipedia ↗</a> '
            . '<span style="opacity:.7">(historia)</span>';

        return redirect()->route('equipos.edit', $equipo->id)->with('success', $msg);
    }

    /**
     * Búsqueda en Wikipedia en español. Si el nombre coincide con un artículo
     * (o con una redirección, p.ej. «Ferencvárosi TC»), Wikipedia salta
     * directo al artículo; si no, muestra la lista de resultados.
     */
    private function urlWikipediaBusqueda(string $nombre): string
    {
        return 'https://es.wikipedia.org/w/index.php?' . http_build_query(['search' => trim($nombre)]);
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

    // ═══════════════ FUNDACIÓN / ESTADIO / SOCIOS DESDE EL SITIO ═══════════════

    /**
     * La ficha de un club en tmapi, o null. Cuesta 1 llamada.
     *
     * La respuesta viene de varias formas según el endpoint (`data`, `clubs`,
     * o el objeto pelado), así que se busca el id en todas y no se confía en
     * el orden: pedir un id y quedarse con "el primero que venga" es cómo se
     * carga un club con los datos de otro.
     */
    private function clubDeTm($tmId)
    {
        $json = HttpHelper::getJson(self::TMAPI . '/clubs?ids[]=' . urlencode($tmId));
        if (!is_array($json)) return null;

        $data = isset($json['data']) ? $json['data'] : $json;
        if (isset($data['clubs']) && is_array($data['clubs'])) $data = $data['clubs'];

        foreach ((array) $data as $item) {
            if (!is_array($item)) continue;
            if ((string) (isset($item['id']) ? $item['id'] : '') === (string) $tmId) return $item;
        }

        if (isset($data['id']) && (string) $data['id'] === (string) $tmId) return $data;

        return null;
    }

    /** La forma del resultado de `datosClubDelSitio()`, sin nada adentro. */
    private function sitioVacio()
    {
        return ['fundacion' => null, 'fundacion_crudo' => '', 'estadio' => null,
            'socios' => null, 'leido' => false, 'url' => null, 'texto' => ''];
    }

    /**
     * Fundación, estadio y socios leídos de «Datos y hechos» del club.
     *
     * La API de clubes no los trae (confirmado: por eso el mensaje viejo decía
     * "completá a mano"). El sitio sí, en `datenfakten/verein/{id}`, y ya
     * sabemos bajar HTML de TM por ScraperAPI — el mismo camino del calendario
     * por club. Una llamada = un crédito = los tres datos.
     *
     * **Es HTML, no JSON: se rompe si TM cambia el maquetado.** Por eso no se
     * lee por posición sino por la etiqueta que tiene al lado ("Estadio:",
     * "Fecha de fundación:"), se usa el sitio en español —que es de donde salen
     * esas etiquetas— y todo lo que no se entiende vuelve en `null` en vez de
     * inventarse. Lo que queda vacío se ve en la pantalla de edición; un dato
     * mal leído no se nota nunca.
     */
    private function datosClubDelSitio($tmId, $relativeUrl)
    {
        $out = $this->sitioVacio();
        $urls = $this->urlsClubTm($tmId, $relativeUrl);
        $out['url'] = $urls['datos'];

        try {
            // `getHtmlTm` es el camino nuevo; si el deploy todavía no lo subió,
            // el viejo enruta igual los hosts de transfermarkt por ScraperAPI.
            $html = method_exists(HttpHelper::class, 'getHtmlTm')
                ? HttpHelper::getHtmlTm($urls['datos'])
                : HttpHelper::getHtmlContent($urls['datos']);
        } catch (\Exception $e) {
            Log::error('datosClubDelSitio: ' . $e->getMessage());
            return $out;
        }

        if (!is_string($html) || trim($html) === '') return $out;

        $out['texto'] = $this->htmlATexto($html);
        $out['leido'] = true;

        // Fundación. Se guarda sólo si sale una fecha COMPLETA; el texto crudo
        // vuelve igual para poder avisar qué fue lo que no se pudo interpretar.
        // Las variantes van de la más específica a la más suelta: la etiqueta
        // tiene que terminar donde termina el nombre, así que "Fundación" NO
        // matchea "Fundación del club:" y hay que listar las dos.
        $crudo = $this->valorDeEtiqueta($out['texto'], ['fecha de fundacion', 'fundacion del club',
            'ano de fundacion', 'fundacion', 'fundado el', 'fundado en', 'fundado']);
        if ($crudo !== null) {
            $out['fundacion_crudo'] = mb_substr($crudo, 0, 60);
            $out['fundacion'] = $this->fechaDelTexto($crudo);
        }

        // Estadio. El nombre puede venir seguido de la capacidad en la misma
        // línea ("La Victoria 12.569 espectadores"): se corta ahí.
        $estadio = $this->valorDeEtiqueta($out['texto'], ['estadio', 'nombre del estadio']);
        if ($estadio !== null) {
            // "Parken - connected by 3 38.065 Aforo" → "Parken - connected by 3".
            $estadio = trim(preg_replace(
                '/\s*\d[\d.,]*\s*(aforo|espectadores|asientos|plazas|butacas|capacidad).*$/iu', '', $estadio));
            // Un "estadio" que es sólo un número es la capacidad, no el nombre.
            if ($estadio !== '' && !preg_match('/^[\d.,\s]+$/u', $estadio)) {
                $out['estadio'] = mb_substr($estadio, 0, 150);
            }
        }

        // Socios. TM los llama "Miembros" en la versión en español.
        //
        // Se toma SÓLO el primer número del renglón. Sacarle los no-dígitos a
        // todo lo que venga pega números que no tienen nada que ver: "18.200
        // 26.03.2009" salía como 1820026032009, que además de ser falso no
        // entra en la columna y voltea el guardado entero.
        $socios = $this->valorDeEtiqueta($out['texto'], ['miembros', 'socios',
            'numero de socios', 'cantidad de socios', 'numero de miembros']);
        if ($socios !== null && preg_match('/\d[\d.,]*/u', $socios, $m)) {
            $n = (int) preg_replace('/[^\d]/', '', $m[0]);
            // 0 es "no lo sé", no "no tiene". Y ningún club del mundo pasa el
            // millón de socios: arriba de eso leí cualquier cosa, no un socio.
            if ($n > 0 && $n <= 1000000) $out['socios'] = $n;
        }

        return $out;
    }

    /**
     * El HTML como texto, un renglón por celda o bloque.
     *
     * Trabajar sobre el texto y no sobre el DOM es a propósito: las etiquetas
     * ("Estadio:") sobreviven a los rediseños mucho más que las clases CSS.
     */
    private function htmlATexto($html)
    {
        $html = preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is', ' ', (string) $html);
        $html = preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(td|th|tr|li|p|div|h1|h2|h3|h4|span|dt|dd|a)\s*>#i', "\n", $html);

        $txt = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $txt = str_replace(["\xc2\xa0", "\xe2\x80\x8b"], ' ', $txt);   // &nbsp; y el ancho cero

        $lineas = [];
        foreach (preg_split('/\R+/u', $txt) as $l) {
            $l = trim(preg_replace('/[ \t]+/u', ' ', $l));
            if ($l !== '') $lineas[] = $l;
        }

        return implode("\n", $lineas);
    }

    /**
     * El valor que va con una etiqueta: lo que sigue a los dos puntos, o el
     * renglón de abajo si la etiqueta quedó sola (el caso de `<th>` / `<td>`).
     *
     * La etiqueta tiene que ARRANCAR el renglón: si no, "Estadio del rival"
     * matchea con "Estadio" y devuelve cualquier cosa.
     */
    private function valorDeEtiqueta($texto, array $etiquetas)
    {
        $lineas = explode("\n", (string) $texto);
        $planas = [];
        foreach ($lineas as $i => $l) $planas[$i] = $this->aplanar($l);

        foreach ($etiquetas as $etiqueta) {
            $et = $this->aplanar($etiqueta);
            if ($et === '') continue;

            foreach ($planas as $i => $plana) {
                if (strpos($plana, $et) !== 0) continue;

                // Arrancar con la etiqueta no alcanza: después tiene que venir
                // el ":" o terminarse el renglón. Sin esto, "Estadio del rival:
                // Monumental" entra por "estadio" y devuelve el estadio de otro.
                $cola = ltrim(mb_substr($plana, mb_strlen($et)));
                if ($cola !== '' && strpos($cola, ':') !== 0) continue;

                // Lo que sobra del renglón después del ":" — se corta por el
                // separador y no por la longitud de la etiqueta, así los
                // acentos no corren el índice.
                $pos = mb_strpos($lineas[$i], ':');
                if ($pos !== false) {
                    $resto = trim(mb_substr($lineas[$i], $pos + 1));
                    if ($resto !== '') return $resto;
                }

                if (isset($lineas[$i + 1])) {
                    $sig = trim($lineas[$i + 1]);
                    // Si abajo hay otra etiqueta, esta venía vacía.
                    if ($sig !== '' && mb_substr($sig, -1) !== ':') return $sig;
                }
            }
        }

        return null;
    }

    /**
     * Minúsculas y sin acentos, para comparar etiquetas.
     *
     * **NO se usa `iconv('ASCII//TRANSLIT')`**, que es lo que parecía obvio:
     * depende del locale, y con el locale `C` —el que suele tener PHP en el
     * hosting— la ó no se convierte en o sino en `?`. "Fundación:" quedaba
     * como "fundaci?n:" y la etiqueta no matcheaba nunca, mientras que
     * "Estadio:" y "Socios:", que no llevan acento, andaban perfecto. Un bug
     * que sólo aparece en el servidor y sólo en las palabras acentuadas.
     *
     * El reemplazo es explícito, y después se tira TODO lo que no sea letra
     * ASCII, número, espacio o dos puntos. Eso cubre de paso el caso en que el
     * HTML llegue con la codificación cambiada: sea `?`, `'o` o `Ã³`, lo que
     * queda es "fundacion".
     */
    private function aplanar($str)
    {
        $s = mb_strtolower((string) $str);

        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', 'ý' => 'y',
        ]);

        $s = preg_replace('/[^a-z0-9 :]+/', '', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Una fecha `Y-m-d` de un texto de TM, o null.
     *
     * Acepta 15/01/1922 y "15 ene 1922". **Un año suelto NO alcanza**: poner
     * 1 de enero para completar es inventar un dato que después nadie puede
     * distinguir de uno real. En ese caso vuelve null y el mensaje lo avisa.
     */
    private function fechaDelTexto($str)
    {
        $str = trim((string) $str);

        if (preg_match('/\b(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})\b/', $str, $m)) {
            return $this->armarFecha($m[3], $m[2], $m[1]);
        }
        if (preg_match('/\b(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})\b/', $str, $m)) {
            return $this->armarFecha($m[1], $m[2], $m[3]);
        }

        $meses = ['ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12];

        if (preg_match('/\b(\d{1,2})\s*(?:de\s+)?([a-zA-ZñÑáéíóúÁÉÍÓÚ]{3,12})\.?\s*(?:de\s+)?(\d{4})\b/u', $str, $m)) {
            $k = mb_substr($this->aplanar($m[2]), 0, 3);
            if (isset($meses[$k])) return $this->armarFecha($m[3], $meses[$k], $m[1]);
        }

        return null;
    }

    /** `Y-m-d` si la fecha existe de verdad; null si no (30 de febrero y demás). */
    private function armarFecha($anio, $mes, $dia)
    {
        $anio = (int) $anio; $mes = (int) $mes; $dia = (int) $dia;
        if ($anio < 1800 || $anio > (int) date('Y') || !checkdate($mes, $dia, $anio)) return null;
        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    /** Qué se lee de «Datos y hechos», sin crear ni tocar nada. */
    private function verDatosClubTm($tmId)
    {
        $d = $this->datosClubDelSitio($tmId, null);

        $fila = function ($k, $v) {
            return '<tr><th style="text-align:left;padding-right:1em">' . e($k) . '</th><td>'
                . ($v === null || $v === '' ? '<span class="sub">— no lo encontré —</span>' : '<b>' . e($v) . '</b>')
                . '</td></tr>';
        };

        $html = '<h1>Datos y hechos · club ' . e($tmId) . '</h1>'
            . '<p class="sub">Esto es lo que leería «Crear desde TM». No crea ni modifica nada. '
            . 'Gasta 2 créditos por carga (la API y la página).</p>'
            . '<p class="acciones"><a href="' . e($d['url']) . '" target="_blank">Abrir la página en TM ↗</a></p>'
            . $this->bloqueClubApi($tmId, $fila);

        if (!$d['leido']) {
            $html .= '<p class="err-box">No pude bajar la página. Si el resto de las pantallas de TM andan, '
                . 'es la página: miralá en el navegador con el link de arriba.</p>';
            return $this->pagina('Datos y hechos', $html);
        }

        $html .= '<table>'
            . $fila('Fundación', $d['fundacion'])
            . $fila('Fundación (como vino)', $d['fundacion_crudo'])
            . $fila('Estadio', $d['estadio'])
            . $fila('Socios', $d['socios'] === null ? null : (string) $d['socios'])
            . '</table>'
            . '<h2>Texto de la página</h2>'
            . '<p class="sub">Si arriba falta algo, la etiqueta que hay que agregar en '
            . '<code>datosClubDelSitio()</code> está acá abajo.</p>'
            . '<pre>' . e(mb_substr($d['texto'], 0, 12000)) . '</pre>';

        return $this->pagina('Datos y hechos', $html);
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

        // ── Excluir / incluir una competencia en el sondeo ──────────────────
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
                $avisos[] = 'Competencia <b>' . e($incluirComp) . '</b> <b>incluida</b>: sus partidos vuelven al sondeo.'
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

        // Con cache=1 las filas salen del staging, y lo excluido en sondeos
        // anteriores ya se borró de ahí: sin esto, la tabla de competencias solo
        // mostraba la recién excluida y las demás no se podían volver a incluir.
        if ($tecnicoId) {
            $compsDentro = [];
            foreach ($filas as $f) $compsDentro[(string) $f['competencia_external_id']] = true;
            $fuera = $this->fueraRecordado($tecnicoId, $fuera, $compsDentro, !$usarCache);
        }

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
                    $avisos[] = 'Saqué <b>' . $borradas . '</b> filas del staging que eran de competencias excluidas.';
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
            'falta_dt' => 0, 'nuevo' => 0, 'conflicto' => 0, 'corridos' => 0, 'cerca' => 0];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['estado'] === 'duplicado' && strpos((string) $f['motivo'], 'falta el DT') !== false) $cont['falta_dt']++;
            if (isset($f['corrido'])) $cont['corridos']++;
            if (!empty($f['cerca'])) $cont['cerca']++;
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
            ] + (Schema::hasColumn('tecnico_sondeos', 'fuera_detalle')
                ? ['fuera_detalle' => json_encode(array_map(function ($g) {
                        return ['nombre' => $g['nombre'], 'n' => (int) $g['n'], 'motivo' => $g['motivo'], 'origen' => $g['origen']];
                    }, $fuera), JSON_UNESCAPED_UNICODE)]
                : []));
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
            . $this->card($fueraTotal, 'excluidos', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['corridos'], 'con fecha corrida', $cont['corridos'] ? 'warn' : '')
            . $this->card($cont['falta_dt'], 'sin el DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . $this->card($cont['cerca'], 'parecidos no atados', $cont['cerca'] ? 'warn' : '')
            . '</div>';

        // PARECIDOS NO ATADOS — mirar antes de apretar «Aplicar».
        // Son filas con los mismos equipos y el mismo resultado que un partido
        // que ya tenés, descartado porque es de otra competencia o porque la
        // fecha está lejos y no se pudo confirmar el torneo. Se van a CREAR:
        // si alguno era en realidad el mismo partido, se arregla acá y no
        // después, cuando ya haya dos partidos con la misma alineación.
        $html .= $this->avisoParecidos($filas);

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
                . 'Los ' . $fueraTotal . ' partidos que trae Transfermarkt son de competencias excluidas '
                . '(las de abajo: no son de 1ra división o tienen una regla guardada). No es un sondeo fallido: no hay nada que guardar. En la lista de DTs '
                . 'queda como <b>sondeado · nada para cargar</b>, así no se le vuelve a gastar una llamada.</div>';
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
            $html .= '<p class="sub">Dejo afuera <b>' . $fueraDe1ra . '</b> partidos de competencias excluidas '
                . '(reserva, juveniles, ascenso o regla guardada). Se limpian del staging la próxima vez que sondees.</p>';
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
                    . (int) $sd->fuera_1ra . ' partidos son de competencias excluidas. '
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
            $anioDesde = substr((string) $g['desde'], 0, 4);
            $anioHasta = substr((string) $g['hasta'], 0, 4);
            if ($anioDesde !== '' && $anioDesde === $anioHasta) {
                // Todos los partidos en el mismo año: el torneo es ESE año y
                // ningún otro. Aceptar también temp/temp+1 mandaba la CONCACAF
                // Champions Cup jugada en 2025 (TM temp 2024) al torneo 2024
                // cuando el 2025 todavía no existía (22-sep-2026).
                $anios[$anioDesde] = true;
            } else {
                $anios[$anioDesde] = true;
                $anios[$anioHasta] = true;
                $anios[(string) $g['temp']] = true;
                $anios[(string) ((int) $g['temp'] + 1)] = true;
                $anios[$g['temp'] . '/' . substr((string) ((int) $g['temp'] + 1), -2)] = true;
                $anios[$g['temp'] . '/' . ((int) $g['temp'] + 1)] = true;
            }

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

    /**
     * REAGRUPAR LAS FECHAS DE UN GRUPO — reparación de lo ya cargado.
     *
     * Hasta sep-2026 el motor DT escribía `fechas.numero` con el `gameDay` de
     * TM, así que un grupo de copa quedaba con fechas 6, 7, 9, 11... Acá se les
     * pone el nombre que corresponde y se fusionan las que son la misma ronda
     * (la ida y la vuelta de una llave).
     *
     * Toca `fechas.numero/url_nombre/orden` y `partidos.fecha_id`, nada más:
     * `partidos.fecha_id` es la ÚNICA columna de la base que apunta a una
     * fecha —goles, tarjetas, cambios y alineaciones cuelgan del partido—, así
     * que mover un partido de fecha no arrastra ni pierde nada.
     *
     * Una fecha se borra sólo si se pidió fusionarla Y quedó sin partidos.
     */
    public function fechas(Request $request)
    {
        set_time_limit(0);

        $volver = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a></p>';

        $grupoId  = (int) $request->get('grupo_id');
        $torneoId = (int) $request->get('torneo_id');

        // ── Elegir el grupo ─────────────────────────────────────────────────
        if (!$grupoId) {
            $html = $volver . '<h1>Reagrupar fechas</h1>'
                . '<p class="sub">Para los grupos donde las fechas quedaron con el número de Transfermarkt '
                . '(6, 7, 9, 11...): les ponés el nombre que va y fusionás las que son la misma ronda. '
                . 'Se muestra todo antes de escribir.</p>';

            $opts = '<option value=""></option>';
            foreach (\App\Torneo::orderBy('year', 'desc')->orderBy('nombre')->get() as $t) {
                $opts .= '<option value="' . (int) $t->id . '"' . ($torneoId === (int) $t->id ? ' selected' : '') . '>'
                    . e($t->nombre . ' ' . $t->year) . '</option>';
            }

            $html .= '<form method="get" action="' . e(route('import_partidos.fechas')) . '">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí el torneo…">' . $opts . '</select> '
                . '<button class="boton">Ver los grupos</button></form>';

            if ($torneoId) {
                $grupos = \App\Grupo::where('torneo_id', $torneoId)->orderBy('id')->get();
                if ($grupos->isEmpty()) {
                    $html .= '<p class="sub">Ese torneo no tiene grupos.</p>';
                } else {
                    $html .= '<div class="scroll"><table><thead><tr><th>Grupo</th><th>Llaves</th>'
                        . '<th>Fechas</th><th>Partidos</th><th></th></tr></thead><tbody>';
                    foreach ($grupos as $g) {
                        $fs = \App\Fecha::where('grupo_id', $g->id)->pluck('id')->all();
                        $np = $fs ? DB::table('partidos')->whereIn('fecha_id', $fs)->count() : 0;
                        $html .= '<tr><td>' . e((string) $g->nombre) . ' <span class="id">#' . (int) $g->id . '</span></td>'
                            . '<td class="num">' . ((int) $g->penales === 1 ? 'sí' : '—') . '</td>'
                            . '<td class="num">' . count($fs) . '</td><td class="num">' . $np . '</td>'
                            . '<td><a class="boton-sec" href="' . e(route('import_partidos.fechas', ['grupo_id' => $g->id])) . '">Reagrupar</a></td></tr>';
                    }
                    $html .= '</tbody></table></div>';
                }
            }

            return $this->pagina('Reagrupar fechas', $html);
        }

        $grupo = \App\Grupo::find($grupoId);
        if (!$grupo) return $this->pagina('Reagrupar fechas', $volver . '<p class="err">No existe el grupo #' . $grupoId . '</p>');
        $torneo = \App\Torneo::find($grupo->torneo_id);

        $fechas = \App\Fecha::where('grupo_id', $grupoId)->orderBy('orden')->orderBy('id')->get();
        if ($fechas->isEmpty()) return $this->pagina('Reagrupar fechas', $volver . '<p class="sub">Ese grupo no tiene fechas.</p>');

        $porId = $fechas->keyBy('id');
        $ids   = $fechas->pluck('id')->all();

        $porFecha = [];
        foreach (DB::table('partidos')->whereIn('fecha_id', $ids)->orderBy('dia')->orderBy('id')->get() as $pt) {
            $porFecha[(int) $pt->fecha_id][] = $pt;
        }

        $nom       = (array) $request->get('nom', []);
        $mov       = (array) $request->get('mov', []);
        $reordenar = (string) $request->get('reordenar', '0') === '1';
        $paso      = (string) $request->get('paso', '');
        $aplicar   = (string) $request->get('aplicar', '0') === '1';

        $encabezado = $volver . '<h1>Reagrupar fechas</h1>'
            . '<p class="sub">' . e(($torneo ? $torneo->nombre . ' ' . $torneo->year . ' · ' : '') . 'grupo ' . $grupo->nombre)
            . ' <span class="id">#' . (int) $grupoId . '</span>'
            . ((int) $grupo->penales === 1 ? ' · <b>grupo de llaves</b>' : '') . '</p>';

        // ── Plan (previsualización y escritura comparten el armado) ─────────
        if ($paso === 'ver' || $aplicar) {
            $plan = []; $errores = [];

            foreach ($fechas as $f) {
                $destino = isset($mov[$f->id]) ? (int) $mov[$f->id] : 0;
                $nombre  = isset($nom[$f->id]) ? trim((string) $nom[$f->id]) : (string) $f->numero;

                if ($destino) {
                    if ($destino === (int) $f->id || !in_array($destino, $ids)) {
                        $errores[] = 'La fecha «' . $f->numero . '» apunta a una fecha que no es de este grupo.';
                        continue;
                    }
                    if (!empty($mov[$destino])) {
                        $errores[] = 'La fecha «' . $f->numero . '» se fusiona con «' . $porId[$destino]->numero
                            . '», que a su vez se fusiona con otra. Hacelo en dos pasos.';
                        continue;
                    }
                } elseif ($nombre === '') {
                    $errores[] = 'La fecha «' . $f->numero . '» quedó sin nombre.';
                    continue;
                }

                $plan[] = ['fecha' => $f, 'nombre' => $nombre, 'destino' => $destino];
            }

            // Dos fechas que se quedan y terminan con el mismo nombre: casi
            // seguro se quiso fusionarlas. Se avisa en vez de dejar el grupo
            // con dos fechas iguales.
            $vistos = [];
            foreach ($plan as $x) {
                if ($x['destino']) continue;
                $k = mb_strtolower($x['nombre']);
                if (isset($vistos[$k])) {
                    $errores[] = 'Dos fechas quedarían con el nombre «' . $x['nombre']
                        . '». Si son la misma ronda, fusionalas con el select en vez de repetir el nombre.';
                }
                $vistos[$k] = true;
            }

            if (!empty($errores)) {
                return $this->pagina('Reagrupar fechas', $encabezado
                    . '<p class="err-box"><b>No cambié nada:</b><br>' . e(implode(' — ', $errores)) . '</p>'
                    . '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.fechas', ['grupo_id' => $grupoId])) . '">Volver</a></p>');
            }

            // ── Escribir ────────────────────────────────────────────────────
            if ($aplicar) {
                $renombradas = 0; $movidos = 0; $borradas = 0;

                DB::transaction(function () use ($plan, &$renombradas, &$movidos, &$borradas) {
                    // 0. Nombres temporales para TODAS las que cambian.
                    //
                    // En producción `fechas` tiene un índice único que la
                    // migración no muestra (la tabla se tocó a mano), así que
                    // dos fechas del mismo grupo no pueden llamarse igual ni
                    // por un instante. Al correr las rondas de lugar —«Octavos»
                    // pasa a «Cuartos» y «Cuartos» a «Semifinal»— el primer
                    // UPDATE chocaba contra el nombre que la segunda todavía no
                    // había soltado, y salía un **500 en blanco**: el chequeo de
                    // arriba mira los nombres FINALES, que no se repiten, así
                    // que la pantalla dejaba pasar algo que la base rechazaba.
                    // Pasó de verdad con la Champions 2012/13, grupo Playoffs
                    // #968, el 20/09/2026; se resolvió a mano en dos pasadas.
                    //
                    // Con el nombre temporal, el intercambio entra de una. Va
                    // adentro de la misma transacción: si algo falla después,
                    // ninguna fecha queda llamándose «~tmp».
                    foreach ($plan as $x) {
                        if ($x['destino']) continue;
                        if ((string) $x['fecha']->numero === $x['nombre']) continue;

                        $tmp = ['numero' => '~tmp' . (int) $x['fecha']->id];
                        if (Schema::hasColumn('fechas', 'url_nombre')) {
                            $tmp['url_nombre'] = 'tmp-' . (int) $x['fecha']->id;
                        }
                        DB::table('fechas')->where('id', $x['fecha']->id)->update($tmp);
                    }

                    foreach ($plan as $x) {                       // 1. renombrar las que se quedan
                        if ($x['destino']) continue;
                        if ((string) $x['fecha']->numero === $x['nombre']) continue;
                        $f = $x['fecha'];
                        $f->numero     = $x['nombre'];
                        $f->url_nombre = Str::slug('fecha-' . $x['nombre']);
                        $f->save();
                        $renombradas++;
                    }
                    foreach ($plan as $x) {                       // 2. mover y borrar la vacía
                        if (!$x['destino']) continue;
                        $movidos += \App\Partido::where('fecha_id', $x['fecha']->id)
                            ->update(['fecha_id' => (int) $x['destino']]);
                        if (!\App\Partido::where('fecha_id', $x['fecha']->id)->exists()) {
                            \App\Fecha::where('id', $x['fecha']->id)->delete();
                            $borradas++;
                        }
                    }
                });

                if ($reordenar) {                                  // 3. ordenar por el primer partido
                    $clave = [];
                    foreach (\App\Fecha::where('grupo_id', $grupoId)->get() as $f) {
                        $min = DB::table('partidos')->where('fecha_id', $f->id)->min('dia');
                        $clave[(int) $f->id] = $min ? (string) $min : '9999-12-31';
                    }
                    asort($clave);
                    $i = 1;
                    foreach (array_keys($clave) as $fid) {
                        \App\Fecha::where('id', $fid)->update(['orden' => $i]);
                        $i++;
                    }
                }

                $this->recontarEquipos($grupoId);

                return $this->pagina('Reagrupar fechas', $encabezado
                    . '<p class="ok-box"><b>Listo.</b> Renombré ' . $renombradas . ' fechas, moví ' . $movidos
                    . ' partidos y borré ' . $borradas . ' fechas que quedaron vacías'
                    . ($reordenar ? ', y reordené el grupo por el día del primer partido' : '') . '.</p>'
                    . '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.fechas', ['grupo_id' => $grupoId])) . '">Ver cómo quedó</a></p>');
            }

            // ── Previsualización ────────────────────────────────────────────
            $lineas = '';
            foreach ($plan as $x) {
                $f = $x['fecha'];
                $n = isset($porFecha[(int) $f->id]) ? count($porFecha[(int) $f->id]) : 0;

                if ($x['destino']) {
                    $lineas .= '<li><b>«' . e((string) $f->numero) . '»</b> → se fusiona con <b>«'
                        . e((string) $porId[$x['destino']]->numero) . '»</b>: se mueven ' . $n . ' partidos'
                        . ($n ? ' y la fecha vacía se borra' : ' y la fecha se borra') . '.</li>';
                } elseif ((string) $f->numero !== $x['nombre']) {
                    $lineas .= '<li><b>«' . e((string) $f->numero) . '»</b> pasa a llamarse <b>«'
                        . e($x['nombre']) . '»</b> (' . $n . ' partidos, no se mueven).</li>';
                }
            }

            if ($lineas === '') {
                return $this->pagina('Reagrupar fechas', $encabezado
                    . '<p class="sub">No pediste ningún cambio.</p>'
                    . '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.fechas', ['grupo_id' => $grupoId])) . '">Volver</a></p>');
            }

            $params = ['grupo_id' => $grupoId, 'nom' => $nom, 'mov' => $mov, 'aplicar' => 1];
            if ($reordenar) $params['reordenar'] = 1;

            return $this->pagina('Reagrupar fechas', $encabezado
                . '<h2>Esto es lo que voy a hacer</h2><ul>' . $lineas . '</ul>'
                . ($reordenar ? '<p class="sub">Y después reordeno las fechas del grupo por el día de su primer partido.</p>' : '')
                . '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.fechas', $params)) . '">Hacer los cambios</a> '
                . '<a class="boton-sec" href="' . e(route('import_partidos.fechas', ['grupo_id' => $grupoId])) . '">Volver</a> '
                . '<span class="sub">recién acá se escribe</span></p>');
        }

        // ── Pantalla ────────────────────────────────────────────────────────
        $html = $encabezado
            . '<p class="sub">Cambiale el nombre a la fecha, o fusionala con otra si son la misma ronda '
            . '(la ida y la vuelta de una llave van juntas). Los partidos se mueven; nada se pierde, '
            . 'porque los goles, las tarjetas y las alineaciones cuelgan del partido, no de la fecha.</p>'
            . '<form method="get" action="' . e(route('import_partidos.fechas')) . '">'
            . '<input type="hidden" name="grupo_id" value="' . (int) $grupoId . '">'
            . '<input type="hidden" name="paso" value="ver">'
            . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Partidos</th>'
            . '<th>Se llama</th><th>…o se fusiona con</th></tr></thead><tbody>';

        foreach ($fechas as $f) {
            $lista = '';
            foreach (isset($porFecha[(int) $f->id]) ? $porFecha[(int) $f->id] : [] as $pt) {
                $lista .= e(substr((string) $pt->dia, 0, 10)) . ' · ' . e($this->nombreEquipo($pt->equipol_id))
                    . ' ' . (($pt->golesl === null || $pt->golesv === null) ? '-' : ((int) $pt->golesl . ':' . (int) $pt->golesv))
                    . ' ' . e($this->nombreEquipo($pt->equipov_id))
                    . ' <span class="id">#' . (int) $pt->id . '</span><br>';
            }
            if ($lista === '') $lista = '<i>sin partidos</i>';

            $opts = '<option value="">(queda como está)</option>';
            foreach ($fechas as $o) {
                if ((int) $o->id === (int) $f->id) continue;
                $opts .= '<option value="' . (int) $o->id . '">' . e((string) $o->numero) . '</option>';
            }

            $html .= '<tr><td class="num">' . e((string) $f->numero) . '<br><span class="id">#' . (int) $f->id . '</span></td>'
                . '<td><span class="sub">' . $lista . '</span></td>'
                . '<td><input type="text" name="nom[' . (int) $f->id . ']" value="' . e((string) $f->numero) . '"></td>'
                . '<td><select name="mov[' . (int) $f->id . ']" class="s2" data-placeholder="no se fusiona">' . $opts . '</select></td></tr>';
        }

        $html .= '</tbody></table></div>'
            . '<p><label><input type="checkbox" name="reordenar" value="1" checked> '
            . 'Reordenar las fechas del grupo por el día de su primer partido</label></p>'
            . '<p class="acciones"><button class="boton">Previsualizar</button> '
            . '<span class="sub">no se escribe nada hasta confirmar</span></p></form>';

        return $this->pagina('Reagrupar fechas', $html);
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

        // ── 2. LAS FECHAS ───────────────────────────────────────────────────
        // `import_partidos.ronda` es el `gameDay` de la API de TM, y NO es el
        // nombre de la fecha. En una liga suele coincidir; en una copa es un
        // contador de la competencia (los octavos pueden ser el "12") y encima
        // el motor DT trae SOLO los partidos de ese DT, así que llegan
        // salteados: 6, 7, 9, 11, 12... Crear `fechas.numero = gameDay` sin
        // preguntar dejaba fechas con nombres que no significan nada. Ahora la
        // fecha destino se elige una vez por ronda y, sin decisión, no se crea.
        $esLlave = (int) DB::table('grupos')->where('id', $grupoId)->value('penales') === 1;

        // La clave de cada fila: el gameDay, salvo que TM le haya puesto el
        // MISMO gameDay a dos partidos de este DT (ver claveRondaPorFila).
        $claveFila = $this->claveRondaPorFila($filas, $esLlave);

        $rondas = [];
        foreach ($filas as $r) {
            $k = $claveFila[$r->id];
            if (!isset($rondas[$k])) $rondas[$k] = [];
            $rondas[$k][] = $r;
        }

        $destino = $this->fechasDestino($request, $rondas, $torneo, $grupoId, $tecnicoId, $comp, $temp, $volver);
        if (!is_array($destino)) return $destino;   // falta elegir: se muestra la pantalla

        // 3. Partidos
        $creados = 0; $enganchados = 0; $saltados = 0; $errores = []; $avisos = []; $detalle = '';
        foreach ($filas as $r) {
            try {
                $k = $claveFila[$r->id];
                if (empty($destino[$k])) { $saltados++; continue; }   // ronda dejada para después
                $fecha  = $destino[$k];
                $numero = $fecha->numero;

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

                // ¿ESTE partido ya está cargado? Pasa siempre que se haya
                // aplicado primero el DT rival: es el MISMO partido, no un
                // choque. Se engancha —queda listo para bajarle el detalle— en
                // vez de contarlo como error.
                $mismos = \App\Partido::where('fecha_id', $fecha->id)
                    ->where(function ($q) use ($equipolId, $equipovId) {
                        $q->where(function ($x) use ($equipolId, $equipovId) {
                            $x->where('equipol_id', $equipolId)->where('equipov_id', $equipovId);
                        })->orWhere(function ($x) use ($equipolId, $equipovId) {
                            $x->where('equipol_id', $equipovId)->where('equipov_id', $equipolId);
                        });
                    })->orderBy('id')->get();

                $exacto = null; $invertido = null;
                foreach ($mismos as $p) {
                    if ((int) $p->equipol_id === $equipolId) { $exacto = $p; break; }
                    if ($invertido === null) $invertido = $p;
                }

                // En un grupo de llaves la ida y la vuelta van en la MISMA fecha
                // con la localía dada vuelta: ahí el invertido es el otro
                // partido de la llave, no éste. Fuera de las llaves, el mismo
                // par en la misma fecha es el mismo partido con la localía al
                // revés (se avisa; el partido no se toca).
                //
                // Y el invertido es el mismo partido sólo si es el MISMO DÍA. Un
                // partido cargado con local y visitante al revés sigue siendo el
                // de esa fecha; la vuelta de una llave es el mismo par, la
                // localía dada vuelta y una semana después. Sin esta condición,
                // un grupo de llaves al que le falta el flag `penales` engancha
                // la vuelta al partido de la ida: la fila del staging queda
                // apuntando ahí (con `motivo = 'ya estaba cargado'`) y después el
                // detalle de la vuelta se escribe adentro de la ida. Es lo que
                // pasó con Atlético–Valencia 2012, partido #25379 — y volvía a
                // pasar cada vez que se apretaba Aplicar, aunque se desatara la
                // fila a mano. Acá no se engancha ni se crea: el que decide es
                // el flag del grupo, y eso lo pone una persona.
                $ya = $exacto;
                if (!$ya && $invertido && !$esLlave) {
                    $dias = abs((int) round((strtotime(substr((string) $invertido->dia, 0, 10))
                        - strtotime(substr((string) $r->dia, 0, 10))) / 86400));

                    if ($dias <= 1) {
                        $ya = $invertido;
                        $avisos[] = $this->nombreEquipo($equipolId) . ' vs ' . $this->nombreEquipo($equipovId)
                            . ' ya estaba cargado con la localía al revés (partido #' . $invertido->id . ').';
                    } else {
                        $errores[] = $this->nombreEquipo($equipolId) . ' vs ' . $this->nombreEquipo($equipovId)
                            . ' (' . substr((string) $r->dia, 0, 10) . '): el mismo par ya está cargado con la '
                            . 'localía al revés, pero ' . $dias . ' días ' . ($invertido->dia < $r->dia ? 'antes' : 'después')
                            . ' (partido #' . (int) $invertido->id . '). Eso no es una localía mal cargada: es la '
                            . 'OTRA mitad de una llave. No lo enganché ni lo creé. Si este grupo es de ida y '
                            . 'vuelta, marcalo como llave y volvé a aplicar — ahí se crea el partido que falta.';
                        continue;
                    }
                }

                if ($ya) {
                    $this->engancharPartido($r, $ya->id, $tecnicoId);
                    $enganchados++;
                    continue;
                }

                // Un partido de uno de los dos equipos en la misma fecha, pero
                // contra otro rival, es la red que atrapa un mapeo de club
                // equivocado. En un grupo de llaves no aplica: ahí conviven la
                // ida y la vuelta.
                if (!$esLlave) {
                    $otro = \App\Partido::where('fecha_id', $fecha->id)
                        ->where(function ($q) use ($equipolId, $equipovId) {
                            $q->where('equipol_id', $equipolId)->orWhere('equipov_id', $equipolId)
                                ->orWhere('equipol_id', $equipovId)->orWhere('equipov_id', $equipovId);
                        })->first();

                    if ($otro) {
                        $errores[] = 'Choque en la fecha «' . $numero . '»: ya hay otro partido de ' . $r->club_nombre
                            . ' ahí contra otro rival (partido #' . $otro->id . '). Ese quedó sin crear.';
                        continue;
                    }
                }

                // En los grupos de llaves el control de arriba no corre, y el
                // choque contra los índices únicos llegaba como SQLSTATE crudo.
                $choque = $this->choqueLocalia($fecha, $equipolId, $equipovId, $r->dia);
                if ($choque !== null) { $errores[] = $choque; continue; }

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
                    . '<td class="num">' . ($golesl === null ? '<span class="sub">sin resultado</span>' : ($golesl . ':' . $golesv)) . '</td>'
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

        if ($enganchados) {
            $html .= '<p class="ok-box"><b>' . $enganchados . ' ya estaban cargados</b> (los creó el DT rival, o una corrida anterior). '
                . 'Los enganché con este DT: quedan listos para bajarles el detalle, y no se tocó ni el resultado ni la localía.</p>';
        }
        if ($saltados) {
            $html .= '<p class="sub">Dejé <b>' . $saltados . '</b> partidos sin crear porque su ronda quedó sin fecha elegida. '
                . 'Volvé a aplicar el grupo cuando sepas a qué fecha van.</p>';
        }
        if (!empty($avisos)) {
            $html .= '<p class="sub">' . e(implode(' — ', $avisos)) . '</p>';
        }
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

    /**
     * La clave de ronda de cada fila del staging: [import_partidos.id => clave].
     *
     * Normalmente es el gameDay de TM. Pero TM a veces le pone el MISMO gameDay
     * a dos partidos del mismo equipo —un partido reprogramado o jugado entre
     * semana hereda el número de otra ronda—. Pasó con Vitesse en la Eredivisie
     * 2000/01: el 11-nov vs Groningen y el 17-nov vs RBC vinieron los dos como
     * «12», y el 12-dic vs AZ y el 15-dic vs NAC como «17». Agrupados por ronda,
     * los dos iban a la misma fecha y el segundo reventaba en «Choque en la
     * fecha». En una liga un equipo juega UNA vez por fecha, así que el
     * segundo (y siguientes, por día) sale en su propia fila, «12 · 2º», sin
     * sugerencia: la fecha la elige una persona.
     *
     * En un grupo de llaves no se parte: ahí la ida y la vuelta pueden
     * compartir número y van juntas a propósito.
     */
    private function claveRondaPorFila($filas, $esLlave)
    {
        $claves = []; $vistos = [];
        foreach ($filas as $r) {           // $filas viene ordenado por día
            $k = ($r->ronda !== null && $r->ronda !== '') ? (string) $r->ronda : '—';
            if (!$esLlave && $k !== '—') {
                $vistos[$k] = isset($vistos[$k]) ? $vistos[$k] + 1 : 1;
                if ($vistos[$k] > 1) $k = $k . ' · ' . $vistos[$k] . 'º';
            }
            $claves[$r->id] = $k;
        }
        return $claves;
    }

    /** ¿Es una ronda partida por claveRondaPorFila()? ("12 · 2º") */
    private function esRondaRepetida($k)
    {
        return strpos((string) $k, ' · ') !== false;
    }

    /**
     * A qué fecha del grupo va cada ronda de TM.
     *
     * Devuelve [ronda => \App\Fecha] cuando está todo decidido, o la pantalla
     * para decidirlo. NUNCA inventa el nombre de una fecha: el `gameDay` se usa
     * como número sólo si el usuario tilda "usar los números de TM", que está
     * bien en una liga y es un disparate en una copa.
     *
     * Lo que se deja sin elegir no se crea. Es a propósito: una fecha con un
     * nombre equivocado ensucia el torneo y hay que ir a borrarla a mano.
     */
    private function fechasDestino(Request $request, array $rondas, $torneo, $grupoId, $tecnicoId, $comp, $temp, $volver)
    {
        $claves  = array_keys($rondas);
        $fechas  = \App\Fecha::where('grupo_id', $grupoId)->orderBy('orden')->orderBy('id')->get();
        $usarTm  = (string) $request->get('usar_tm', '0') === '1';
        $enviado = (string) $request->get('fechas_ok', '0') === '1';
        $elegido = (array) $request->get('f', []);
        $nuevos  = (array) $request->get('n', []);
        $rondaOk = (array) $request->get('r', []);

        // Si el staging cambió entre la pantalla y el envío, los índices ya no
        // apuntan a la misma ronda. Antes que crear cualquier cosa, se vuelve
        // a preguntar.
        if ($enviado) {
            foreach ($claves as $i => $k) {
                if (isset($rondaOk[$i]) && (string) $rondaOk[$i] !== (string) $k) { $enviado = false; break; }
            }
        }

        $mismaOk = (string) $request->get('misma_ok', '0') === '1';

        // Primero se decide TODO sin crear nada: `plan` guarda la fecha
        // existente o el nombre de la nueva. Las fechas nuevas se crean recién
        // cuando no falta nada y no hay rondas repetidas sin confirmar.
        $plan = []; $faltan = []; $sinNombre = [];

        foreach ($claves as $i => $k) {
            if (!$enviado) { $faltan[] = $k; continue; }

            $sel = isset($elegido[$i]) ? trim((string) $elegido[$i]) : '';
            $nom = isset($nuevos[$i]) ? trim((string) $nuevos[$i]) : '';

            if ($sel === '0') continue;   // "por ahora no"

            if ($sel !== '' && $sel !== 'nueva') {
                $f = $fechas->first(function ($x) use ($sel) { return (int) $x->id === (int) $sel; });
                if ($f) { $plan[(string) $k] = ['fecha' => $f, 'nombre' => null]; continue; }
            }
            if ($nom === '' && $sel === 'nueva') { $sinNombre[] = $k; $faltan[] = $k; continue; }
            if ($nom === '' && $sel === '' && $usarTm && (string) $k !== '—' && !$this->esRondaRepetida($k)) $nom = (string) $k;

            if ($nom !== '') {
                $f = $fechas->first(function ($x) use ($nom) { return (string) $x->numero === $nom; });
                $plan[(string) $k] = $f ? ['fecha' => $f, 'nombre' => null] : ['fecha' => null, 'nombre' => $nom];
                continue;
            }
            $faltan[] = $k;
        }

        // DOS RONDAS DE TM EN LA MISMA FECHA. En una liga no pasa nunca (cada
        // gameDay es su fecha), y en una copa casi siempre es un error: en la
        // Supercopa de España 2020 las semis (ronda 1) y la final (ronda 2)
        // fueron las dos a «Final», la semi se creó ahí y la final reventó
        // contra el índice único. No se prohíbe —puede ser a propósito—, pero
        // se pide confirmación.
        $repetidas = [];
        if (empty($faltan) && !$mismaOk) {
            $porFecha = [];
            foreach ($plan as $k => $p) {
                $clave = $p['fecha'] ? ('id:' . (int) $p['fecha']->id) : ('n:' . mb_strtolower($p['nombre']));
                $porFecha[$clave][] = (string) $k;
            }
            foreach ($porFecha as $ks) {
                if (count($ks) > 1) $repetidas[] = $ks;
            }
        }

        if (empty($faltan) && empty($repetidas)) {
            $destino = [];
            foreach ($plan as $k => $p) {
                $destino[(string) $k] = $p['fecha'] ?: $this->fechaPorNombre($grupoId, $p['nombre']);
            }
            return $destino;
        }

        // ── La pantalla ─────────────────────────────────────────────────────
        $grupoNombre = (string) DB::table('grupos')->where('id', $grupoId)->value('nombre');
        $esLlaveG = (int) DB::table('grupos')->where('id', $grupoId)->value('penales') === 1;

        $html = $volver . '<h1>¿A qué fecha va cada ronda?</h1>'
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · grupo '
            . e($grupoNombre !== '' ? $grupoNombre : ('#' . (int) $grupoId)) . '</p>'
            . '<p class="sub">Transfermarkt no manda el nombre de la ronda: manda un número de <code>gameDay</code>. '
            . 'En una liga suele ser la fecha; en una copa no significa nada (los octavos pueden ser el «12»). '
            . 'Y como acá vienen sólo los partidos de este DT, los números llegan salteados. '
            . '<b>Lo que dejes sin elegir no se crea</b>: no se inventa ninguna fecha.</p>';

        if (!empty($sinNombre)) {
            $html .= '<p class="err-box">No creé nada: dijiste «crear una fecha nueva» y quedó sin nombre en la ronda '
                . e(implode(', ', array_map('strval', $sinNombre))) . '.</p>';
        }
        if (!empty($repetidas)) {
            $txt = [];
            foreach ($repetidas as $ks) {
                $p = $plan[$ks[0]];
                $txt[] = 'las rondas ' . implode(', ', $ks) . ' van todas a «'
                    . ($p['fecha'] ? (string) $p['fecha']->numero : $p['nombre']) . '»';
            }
            $html .= '<p class="err-box"><b>No creé nada:</b> ' . e(implode('; ', $txt)) . '. '
                . 'Cada ronda de TM suele ser una fecha distinta (semis y final, por ejemplo). '
                . 'Revisalo abajo; si de verdad van juntas, tildá la confirmación.</p>';
        }

        $html .= '<form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
            . '<input type="hidden" name="tecnico_id" value="' . (int) $tecnicoId . '">'
            . '<input type="hidden" name="comp" value="' . e($comp) . '">'
            . '<input type="hidden" name="temp" value="' . e($temp) . '">'
            . '<input type="hidden" name="torneo_id" value="' . (int) $torneo->id . '">'
            . '<input type="hidden" name="grupo_id" value="' . (int) $grupoId . '">'
            . '<input type="hidden" name="confirmar" value="1">'
            . '<input type="hidden" name="fechas_ok" value="1">'
            . '<div class="scroll"><table><thead><tr><th>TM</th><th>Partidos</th>'
            . '<th>¿A qué fecha del grupo van?</th></tr></thead><tbody>';

        foreach ($claves as $i => $k) {
            $rs = $rondas[$k];

            // Sugerencia, en orden: dónde cayó esta misma ronda cuando se
            // aplicó otro DT, y si no, dónde cayeron estos mismos pares en un
            // grupo de llaves. Las dos salen de lo ya cargado, no de adivinar.
            $repetida = $this->esRondaRepetida($k);
            $sug = $repetida ? null : $this->fechaDeLaRonda($comp, $temp, (string) $k, $torneo->id);
            if (!$sug && !$repetida) {
                $idLlave = $this->fechaDeLasLlaves($rs, $torneo->id);
                if ($idLlave) $sug = \App\Fecha::find($idLlave);
            }

            $selId = ($sug && (int) $sug->grupo_id === (int) $grupoId) ? (int) $sug->id : 0;
            if (!$selId && !$repetida) {
                $igual = $fechas->first(function ($f) use ($k) { return (string) $f->numero === (string) $k; });
                if ($igual) $selId = (int) $igual->id;
            }

            // La sugerencia no vale si en esa fecha el equipo del DT YA juega
            // (fuera de las llaves): es el caso de un gameDay que TM repitió y
            // cuyo primer partido ya se aplicó. Preseleccionarla lleva derecho
            // al «Choque en la fecha».
            $ocupada = null;
            if ($selId && !$esLlaveG) {
                foreach ($rs as $r) {
                    $eq = (int) $r->equipo_id;
                    if (!$eq) continue;
                    $p = \App\Partido::where('fecha_id', $selId)
                        ->where(function ($q) use ($eq) { $q->where('equipol_id', $eq)->orWhere('equipov_id', $eq); })
                        ->first();
                    if ($p) { $ocupada = $p; break; }
                }
                if ($ocupada) $selId = 0;
            }

            // Si esa ronda ya tiene nombre en OTRO grupo del torneo, se ofrece
            // ese nombre en vez de un número que no dice nada.
            $nomSug = ($sug && (int) $sug->grupo_id !== (int) $grupoId) ? (string) $sug->numero : '';

            // Si la pantalla vuelve después de un envío, se muestra lo que se
            // eligió y no la sugerencia: si no, habría que elegir todo de nuevo.
            $selTxt = '';
            if ($enviado) {
                $selTxt = isset($elegido[$i]) ? trim((string) $elegido[$i]) : '';
                $selId  = ctype_digit($selTxt) ? (int) $selTxt : 0;
                $nomSug = isset($nuevos[$i]) ? trim((string) $nuevos[$i]) : '';
            }

            $lista = '';
            foreach ($rs as $r) {
                $lista .= e(substr((string) $r->dia, 0, 10)) . ' · ' . e((string) $r->club_nombre)
                    . ' vs ' . e((string) $r->rival_nombre) . '<br>';
            }

            $opts = '<option value=""></option>';
            foreach ($fechas as $f) {
                $opts .= '<option value="' . (int) $f->id . '"' . ($selId === (int) $f->id ? ' selected' : '') . '>'
                    . e((string) $f->numero) . '</option>';
            }
            $opts .= '<option value="nueva"' . ($selTxt === 'nueva' ? ' selected' : '') . '>crear una fecha nueva…</option>'
                . '<option value="0"' . ($selTxt === '0' ? ' selected' : '') . '>por ahora no</option>';

            $html .= '<tr><td class="num">' . e((string) $k)
                . ($repetida ? '<br><span class="err">TM repite el número: este DT ya tiene otro partido en esa ronda. Elegí a mano la fecha que le corresponde.</span>' : '')
                . ($ocupada ? '<br><span class="err">En la fecha que correspondería ya está '
                    . e($this->nombreEquipo($ocupada->equipol_id) . ' vs ' . $this->nombreEquipo($ocupada->equipov_id))
                    . ' (' . e(substr((string) $ocupada->dia, 0, 10)) . ', #' . (int) $ocupada->id . '). TM le puso el mismo número a los dos: elegí a mano.</span>' : '')
                . '<input type="hidden" name="r[' . (int) $i . ']" value="' . e((string) $k) . '"></td>'
                . '<td><span class="sub">' . $lista . '</span></td>'
                . '<td><select name="f[' . (int) $i . ']" class="s2" data-placeholder="elegí la fecha…">' . $opts . '</select> '
                . '<input type="text" name="n[' . (int) $i . ']" value="' . e($nomSug) . '" placeholder="…o el nombre de una fecha nueva">'
                . '</td></tr>';
        }

        $html .= '</tbody></table></div>'
            . '<p><label><input type="checkbox" name="usar_tm" value="1"' . ($usarTm ? ' checked' : '') . '> '
            . 'Para las que deje sin elegir, usar el número de TM como número de fecha '
            . '<span class="sub">(sirve en una liga; en una copa, no)</span></label></p>'
            . (!empty($repetidas)
                ? '<p><label><input type="checkbox" name="misma_ok" value="1"> '
                    . '<b>Sí, esas rondas van a la misma fecha</b> <span class="sub">(crear igual)</span></label></p>'
                : '')
            . '<p class="acciones"><button class="boton">Crear los partidos</button> '
            . '<span class="sub">las fechas nuevas se crean recién acá</span></p>'
            . '</form>';

        return $this->pagina('Aplicar partidos', $html);
    }

    /**
     * ¿Chocaría este partido contra los índices únicos de `partidos`?
     *
     * La tabla no deja que un equipo sea local —ni visitante— dos veces en la
     * misma fecha (`fecha_id_equipov_id` y su par del local). En un grupo común
     * eso ya lo frena el control de «otro partido del equipo en la fecha», pero
     * en un grupo de llaves ese control no corre (la ida y la vuelta conviven)
     * y el insert reventaba con un SQLSTATE 23000. Pasó con la Supercopa de
     * España 2020: las semis y la final habían ido a la misma fecha «Final».
     *
     * Devuelve el mensaje para la pantalla, o null si se puede crear.
     */
    private function choqueLocalia($fecha, $equipolId, $equipovId, $dia)
    {
        $equipolId = (int) $equipolId; $equipovId = (int) $equipovId;

        $ocupado = \App\Partido::where('fecha_id', $fecha->id)
            ->where(function ($q) use ($equipolId, $equipovId) {
                $q->where('equipol_id', $equipolId)->orWhere('equipov_id', $equipovId);
            })->orderBy('id')->first();

        if (!$ocupado) return null;

        $quien = (int) $ocupado->equipol_id === $equipolId
            ? $this->nombreEquipo($equipolId) . ' ya juega de local'
            : $this->nombreEquipo($equipovId) . ' ya juega de visitante';

        return $this->nombreEquipo($equipolId) . ' vs ' . $this->nombreEquipo($equipovId)
            . ' (' . substr((string) $dia, 0, 10) . '): en la fecha «' . $fecha->numero . '» ' . $quien
            . ' (partido #' . (int) $ocupado->id . ', ' . $this->nombreEquipo($ocupado->equipol_id)
            . ' vs ' . $this->nombreEquipo($ocupado->equipov_id) . ' del ' . substr((string) $ocupado->dia, 0, 10) . '). '
            . 'Un equipo no puede repetir localía en la misma fecha: casi seguro esta ronda va a otra fecha. No lo creé.';
    }

    /** La fecha del grupo que se llama así, o una nueva con ese nombre. */
    private function fechaPorNombre($grupoId, $nombre)
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') return null;

        $f = \App\Fecha::where('grupo_id', $grupoId)->where('numero', $nombre)->first();
        if ($f) return $f;

        $f = new \App\Fecha();
        $f->forceFill([
            'numero'     => $nombre,
            'grupo_id'   => $grupoId,
            'orden'      => is_numeric($nombre) ? (int) $nombre
                : ((int) \App\Fecha::where('grupo_id', $grupoId)->max('orden')) + 1,
            'url_nombre' => Str::slug('fecha-' . $nombre),
        ])->save();

        return $f;
    }

    /**
     * Dónde cayó esta misma ronda de TM la última vez que se aplicó.
     *
     * `import_partidos` guarda `ronda` y `partido_id`, así que lo ya aplicado
     * —por este DT o por el rival— dice a qué fecha corresponde ese gameday sin
     * tener que adivinar ningún nombre.
     */
    private function fechaDeLaRonda($comp, $temp, $ronda, $torneoId)
    {
        $rows = DB::table('import_partidos')
            ->join('partidos', 'partidos.id', '=', 'import_partidos.partido_id')
            ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->join('grupos', 'grupos.id', '=', 'fechas.grupo_id')
            ->where('grupos.torneo_id', (int) $torneoId)
            ->where('import_partidos.competencia_external_id', (string) $comp)
            ->where('import_partidos.temporada', (string) $temp)
            ->where('import_partidos.ronda', (string) $ronda)
            ->whereNotNull('import_partidos.partido_id')
            ->select('fechas.id AS fecha_id')->get();

        $cuenta = [];
        foreach ($rows as $f) {
            $id = (int) $f->fecha_id;
            $cuenta[$id] = isset($cuenta[$id]) ? $cuenta[$id] + 1 : 1;
        }
        if (empty($cuenta)) return null;

        arsort($cuenta);
        return \App\Fecha::find((int) key($cuenta));
    }

    /**
     * El partido ya existía: se engancha la fila del staging y se le agrega el
     * DT si le falta. NO se toca el partido — el resultado y la localía de lo
     * ya cargado mandan sobre lo que traiga TM.
     */
    private function engancharPartido($r, $partidoId, $tecnicoId)
    {
        if ((int) $r->equipo_id) {
            $existe = DB::table('partido_tecnicos')
                ->where('partido_id', (int) $partidoId)
                ->where('equipo_id', (int) $r->equipo_id)->exists();

            if (!$existe) {
                DB::table('partido_tecnicos')->insert([
                    'partido_id' => (int) $partidoId,
                    'equipo_id'  => (int) $r->equipo_id,
                    'tecnico_id' => (int) $tecnicoId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        DB::table('import_partidos')->where('id', $r->id)->update([
            'estado'     => 'duplicado',
            'partido_id' => (int) $partidoId,
            'motivo'     => 'ya estaba cargado',
            'updated_at' => now(),
        ]);
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
            // Sin casteo a int: `goles_favor` en null significa "TM no da un
            // marcador que se pueda cargar" (partido por penales), y `(int) null`
            // lo convertia en un 0:0 falso.
            return [
                'local' => $f['local'],
                'gf'    => $f['goles_favor'] === null ? null : (int) $f['goles_favor'],
                'gc'    => $f['goles_contra'] === null ? null : (int) $f['goles_contra'],
            ];
        }
        return [
            'local' => $r->local === null ? null : ((int) $r->local === 1),
            'gf'    => $r->goles_favor === null ? null : (int) $r->goles_favor,
            'gc'    => $r->goles_contra === null ? null : (int) $r->goles_contra,
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

            // Un partido por penales viene sin marcador de TM (ver `normalizar()`).
            // Esta pantalla arregla la LOCALIA: no tiene por que borrar un
            // resultado ya cargado. Si hay que dar vuelta los equipos, se dan
            // vuelta tambien los goles y la tanda que ya estaban.
            $conMarcador   = $golesl !== null && $golesv !== null;
            $mismosEquipos = (int) $partido->equipol_id === $equipolId
                && (int) $partido->equipov_id === $equipovId;
            $mismoMarcador = !$conMarcador
                || ((int) $partido->golesl === (int) $golesl && (int) $partido->golesv === (int) $golesv);

            if ($mismosEquipos && $mismoMarcador) {
                continue;   // ya estaba bien
            }

            $antes = $this->nombreEquipo($partido->equipol_id) . ' ' . $partido->golesl . ':' . $partido->golesv
                . ' ' . $this->nombreEquipo($partido->equipov_id);

            $campos = ['equipol_id' => $equipolId, 'equipov_id' => $equipovId];
            if ($conMarcador) {
                $campos['golesl'] = $golesl;
                $campos['golesv'] = $golesv;
            } elseif (!$mismosEquipos) {
                $campos['golesl']   = $partido->golesv;
                $campos['golesv']   = $partido->golesl;
                $campos['penalesl'] = $partido->penalesv;
                $campos['penalesv'] = $partido->penalesl;
            }
            $golesl = array_key_exists('golesl', $campos) ? $campos['golesl'] : $partido->golesl;
            $golesv = array_key_exists('golesv', $campos) ? $campos['golesv'] : $partido->golesv;

            $partido->forceFill($campos)->save();

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
            // Sin marcador por penales NO es «sin resultado»: el partido se
            // jugó y hay que crearlo (sin goles; el marcador se completa con
            // «Solo el marcador», que baja /game/{id} y separa la tanda).
            // Excluirlo dejaba la llave sin la vuelta: Atlético–Inter, octavos
            // de la Champions 2023/24 (13/03/2024, 2:1 y 3:2 por penales), no
            // aparecía en «¿A qué fecha va cada ronda?».
            $porPenales = !empty($f['por_penales']);
            $sinGoles = $f['goles_favor'] === null || $f['goles_contra'] === null;
            if ($sinGoles && !$porPenales) {
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
            $cerca   = [];

            // Partidos postergados: TM guarda la fecha original y vos la fecha real.
            // Se buscan por par de equipos + localía + resultado exacto, pero con
            // la competencia y el número de fecha como segunda llave: equipos +
            // resultado SOLOS no alcanzan (ver buscarPartidoAplazado()).
            // El aplazado se reconoce por el resultado: sin marcador no hay con qué.
            if (!$partido && $equipoId && $rivalId && !$sinGoles) {
                $r = $this->buscarPartidoAplazado($equipoId, $rivalId, $f['dia'], $f['local'],
                    (int) $f['goles_favor'], (int) $f['goles_contra'], $f['ronda'],
                    $f['competencia_external_id']);
                $partido = $r['partido'];
                $corrido = $r['corrido'];
                $cerca   = $r['cerca'];
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
                // Si sí, el partido PUEDE ser el mismo y el que está mal ser el mapeo
                // del rival. Pero esa deducción sólo vale si los dos pueden ser el
                // mismo partido, y dos partidos de competencias distintas no lo son
                // nunca. La ventana de `partidoDelDia()` es de ±1 día, y liga el
                // domingo + copa el jueves es la semana normal de cualquier equipo:
                // así se perdió la vuelta de Copa del Rey Athletic 6:0 UD Lanzarote
                // (17/01/2005), acusada de ser el Athletic–Espanyol del 16/01.
                // Mismo criterio de competencia que ya usa `buscarPartidoAplazado()`.
                $otro = $this->partidoDelDia($equipoId, $f['dia']);
                $ctxOtro  = $otro ? $this->contextoPartido($otro->id) : null;
                $compTm   = trim((string) $f['competencia_external_id']);
                $otraComp = $ctxOtro && $compTm !== '' && $ctxOtro['comp'] !== ''
                    && $ctxOtro['comp'] !== $compTm;
                $mismoDia = $otro && substr((string) $otro->dia, 0, 10) === substr((string) $f['dia'], 0, 10);

                if ($otro && $otraComp) {
                    // No puede ser el mismo partido. NO se toca el mapeo del rival y
                    // NO se escribe `partido_id`: la fila no es de ese partido, sólo
                    // chocó con él.
                    $rivalOtro = ((int) $otro->equipol_id === (int) $equipoId) ? $otro->equipov_id : $otro->equipol_id;
                    $donde = 'el partido #' . $otro->id . ' contra ' . $this->nombreEquipo($rivalOtro)
                        . ' (' . $ctxOtro['comp'] . ' ≠ ' . $compTm . ')';

                    if ($mismoDia) {
                        // Un club no juega dos veces el mismo día: una de las dos
                        // fechas está mal, casi siempre la de Transfermarkt. Hay que
                        // mirarlo antes de crear nada. Caso real: Athletic–Austria
                        // Viena (UEFA) fechado el mismo día que Athletic–Getafe (ES1).
                        $filas[$i]['estado'] = 'conflicto';
                        $filas[$i]['motivo'] = 'ese día ya tenés ' . $donde . ': no es este partido, es de '
                            . 'otra competencia. Un club no juega dos veces el mismo día, así que una de las dos '
                            . 'fechas está mal — revisá la de TM antes de crearlo';
                    } else {
                        // Días distintos y competencias distintas: fútbol normal.
                        $filas[$i]['estado'] = 'nuevo';
                        $cerca[] = 'el ' . substr((string) $otro->dia, 0, 10) . ' tenés ' . $donde
                            . ', de otra competencia: por eso este se crea igual';
                    }
                } elseif ($otro && !$mismoDia) {
                    // Misma competencia —o, lo más común, sin poder saberlo porque al
                    // torneo le falta `tm_competition_id`— pero otro día. Queda
                    // frenado para que lo mire una persona, pero SIN `rival_real_id`:
                    // esa columna es la que arma el botón «Corregir» de «Mapeos que no
                    // cierran», y ofrecer un remapeo de club exige estar seguro de que
                    // los dos son el mismo partido. A un día de distancia y sin la
                    // competencia, eso es una corazonada, y un remapeo equivocado no se
                    // nota nunca más.
                    $rivalOtro = ((int) $otro->equipol_id === (int) $equipoId) ? $otro->equipov_id : $otro->equipol_id;
                    $filas[$i]['estado'] = 'conflicto';
                    $filas[$i]['partido_id'] = $otro->id;
                    $filas[$i]['motivo'] = 'el ' . substr((string) $otro->dia, 0, 10) . ' tenés el partido #'
                        . $otro->id . ' contra ' . $this->nombreEquipo($rivalOtro) . ' (#' . $rivalOtro . '), '
                        . 'a un día de éste. No puedo confirmar si son de la misma competencia'
                        . ($ctxOtro && $ctxOtro['comp'] === '' ? ' (a ese torneo le falta el id de competencia de TM)' : '')
                        . ': si son dos partidos distintos, cargá ese id y volvé a sondear';
                } elseif ($otro) {
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

            // Había un partido parecido y NO se lo colgó: que se vea por qué.
            // Antes esto no existía porque nunca se descartaba un candidato.
            $filas[$i]['cerca'] = $cerca;
            if ($filas[$i]['estado'] === 'nuevo' && $cerca) {
                $filas[$i]['motivo'] = 'se crea nuevo · OJO: ' . implode(' · ', $cerca);
            }
            if ($filas[$i]['estado'] === 'nuevo' && $sinGoles) {
                $filas[$i]['motivo'] = trim('se crea SIN resultado (se definió por penales): completalo con «Solo el marcador»'
                    . ($filas[$i]['motivo'] ? ' · ' . $filas[$i]['motivo'] : ''));
            }
        }
        return $filas;
    }

    /**
     * Busca un partido postergado: mismo par de equipos, misma localía y el
     * MISMO resultado, en una ventana de ±150 días.
     *
     * EQUIPOS + RESULTADO NO ALCANZAN. Los mismos dos equipos, con el mismo
     * resultado, a un par de meses de distancia, suelen ser DOS partidos
     * distintos de dos competencias distintas: el Clausura y los Playoffs
     * uruguayos, la liga y la copa nacional. Sin más llave que ésa, este método
     * colgaba el gameId del segundo partido del primero: el partido de la copa
     * no se creaba nunca (el torneo queda con un partido de menos) y el partido
     * de la liga terminaba con DOS gameId. Pasó tres veces (Nacional 3-2
     * Torque, Boston River 0-1 Atenas, Atlético 0-0 Getafe) y se limpió a mano
     * el 15-sep-2026.
     *
     * Ahora, para aceptar un candidato, hace falta una de estas tres:
     *   1. que la fecha caiga dentro de ±CORRIMIENTO_SEGURO días (una
     *      reprogramación de la misma ronda: ahí el resultado sí identifica);
     *   2. que el torneo del candidato apunte a la MISMA competencia de TM
     *      (`torneos.tm_competition_id`);
     *   3. que el número de fecha del candidato sea el mismo que la ronda de TM.
     * Y se descarta de entrada el candidato cuyo torneo apunta a OTRA
     * competencia de TM, esté donde esté la fecha.
     *
     * Si no queda ninguno, no se empareja: la fila cae en «nuevo» —el partido
     * se crea, que es lo que corresponde— con el aviso de qué partido parecido
     * había y por qué no se usó. Nunca se cuelga del existente.
     *
     * Devuelve ['partido' => Partido|null, 'corrido' => int|null, 'cerca' => [avisos]].
     */
    private function buscarPartidoAplazado($equipoId, $rivalId, $dia, $local, $gf, $gc, $ronda, $compTm = null)
    {
        $vacio = ['partido' => null, 'corrido' => null, 'cerca' => []];
        if (!$dia) return $vacio;

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
        if ($cands->isEmpty()) return $vacio;

        $nRonda = preg_replace('/\D/', '', trim((string) $ronda));
        $comp   = trim((string) $compTm);

        $ok = [];
        $cerca = [];
        foreach ($cands as $p) {
            $ctx = $this->contextoPartido($p->id);
            $corrido = (int) round((strtotime(substr((string) $p->dia, 0, 10))
                - strtotime(substr((string) $dia, 0, 10))) / 86400);
            $donde = 'partido #' . $p->id . ' del ' . substr((string) $p->dia, 0, 10)
                . ($ctx['torneo'] !== '' ? ' (' . $ctx['torneo'] . ')' : '');

            $mismaComp  = ($comp !== '' && $ctx['comp'] !== '' && $ctx['comp'] === $comp);
            $otraComp   = ($comp !== '' && $ctx['comp'] !== '' && $ctx['comp'] !== $comp);
            $mismaRonda = ($nRonda !== '' && $ctx['ronda'] !== '' && (int) $ctx['ronda'] === (int) $nRonda);

            if ($otraComp) {
                $cerca[] = 'el ' . $donde . ' tiene el mismo resultado pero es de otra competencia de TM ('
                    . $ctx['comp'] . ' ≠ ' . $comp . ')';
                continue;
            }
            if (abs($corrido) <= self::CORRIMIENTO_SEGURO || $mismaComp || $mismaRonda) {
                $ok[] = ['p' => $p, 'corrido' => $corrido, 'ronda' => $mismaRonda];
                continue;
            }
            $cerca[] = 'el ' . $donde . ' tiene el mismo resultado pero está a ' . abs($corrido)
                . ' días y no puedo confirmar que sea del mismo torneo'
                . ($ctx['torneo'] === '' ? ' (no tiene torneo)' : ($ctx['comp'] === '' ? ' (a ese torneo le falta el id de competencia de TM)' : ''));
        }

        if (count($ok) === 1) {
            return ['partido' => $ok[0]['p'], 'corrido' => $ok[0]['corrido'], 'cerca' => $cerca];
        }

        // Varios candidatos: desempata el número de fecha, como antes. Si sigue
        // el empate no se elige ninguno — atarlo al equivocado no se nota nunca más.
        if (count($ok) > 1) {
            $porRonda = [];
            foreach ($ok as $o) if ($o['ronda']) $porRonda[] = $o;
            if (count($porRonda) === 1) {
                return ['partido' => $porRonda[0]['p'], 'corrido' => $porRonda[0]['corrido'], 'cerca' => $cerca];
            }
            $ids = [];
            foreach ($ok as $o) $ids[] = '#' . $o['p']->id;
            $cerca[] = 'hay ' . count($ok) . ' partidos que calzan (' . implode(', ', $ids)
                . ') y ninguno se distingue por número de fecha: no elijo yo';
        }

        return ['partido' => null, 'corrido' => null, 'cerca' => $cerca];
    }

    /**
     * Torneo, competencia de TM y número de fecha de un partido ya cargado.
     * El torneo no cuelga del partido: partidos → fechas → grupos → torneos.
     *
     * `tm_competition_id` se chequea con Schema porque es una columna nueva
     * (ago-2026): si la migración todavía no corrió en el servidor, esto
     * devuelve comp vacía y el emparejador se queda en el criterio de la fecha,
     * en lugar de tirar un 500 en blanco.
     */
    private function contextoPartido($partidoId)
    {
        static $cache = [];
        static $hayComp = null;

        $id = (int) $partidoId;
        if (isset($cache[$id])) return $cache[$id];
        if ($hayComp === null) {
            $hayComp = Schema::hasTable('torneos') && Schema::hasColumn('torneos', 'tm_competition_id');
        }

        $cols = ['torneos.id as torneo_id', 'torneos.nombre as torneo', 'torneos.year as anio',
            'fechas.numero as ronda'];
        $cols[] = $hayComp ? 'torneos.tm_competition_id as comp' : DB::raw("'' as comp");

        $r = DB::table('partidos')
            ->leftJoin('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->leftJoin('grupos', 'grupos.id', '=', 'fechas.grupo_id')
            ->leftJoin('torneos', 'torneos.id', '=', 'grupos.torneo_id')
            ->where('partidos.id', $id)
            ->select($cols)->first();

        $cache[$id] = [
            'torneo_id' => $r && $r->torneo_id ? (int) $r->torneo_id : 0,
            'torneo'    => $r && $r->torneo ? trim($r->torneo . ' ' . $r->anio) : '',
            'comp'      => $r ? trim((string) $r->comp) : '',
            'ronda'     => $r ? preg_replace('/\D/', '', (string) $r->ronda) : '',
        ];
        return $cache[$id];
    }

    /**
     * Cualquier partido de ese equipo ese día, sin importar el rival.
     *
     * La ventana sigue siendo de ±1 día, pero gana el del MISMO día cuando hay
     * más de uno: quien llama distingue «mismo día» (un club no juega dos veces)
     * de «a un día» (liga el domingo, copa el jueves), y con `first()` a secas se
     * comía el del día exacto por puro orden de tabla.
     */
    private function partidoDelDia($equipoId, $dia)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -1 day'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +1 day'));
        return \App\Partido::whereBetween('dia', [$d0, $d1])
            ->where(function ($q) use ($equipoId) {
                $q->where('equipol_id', $equipoId)->orWhere('equipov_id', $equipoId);
            })
            ->orderByRaw('ABS(DATEDIFF(dia, ?))', [substr((string) $dia, 0, 10)])
            ->first();
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

    /**
     * Segunda pasada del fixture: el mismo par de equipos en el MISMO número de
     * fecha, aunque el día no coincida.
     *
     * Por qué no sirve `buscarPartidoAplazado()` acá: ése exige que el
     * resultado coincida, y el caso típico es justamente un partido que todavía
     * no tiene marcador cargado. Y por qué el número de fecha alcanza como
     * llave: en una liga, dos equipos se cruzan una sola vez por ronda. La
     * ventana igual se acota a ±120 días para no llegar a la otra rueda ni a
     * otra edición del torneo.
     *
     * Devuelve algo sólo si queda UN candidato. Con dos o más se prefiere que
     * caiga en «nuevo» y lo mire una persona: un emparejado equivocado le pega
     * los datos de un partido a otro y no se nota nunca más.
     */
    private function buscarPartidoPorRonda($equipoId, $rivalId, $dia, $ronda, $torneoId = null)
    {
        if (!$dia) return null;
        $nRonda = preg_replace('/\D/', '', trim((string) $ronda));

        // EN LAS COPAS NO HAY NÚMERO DE FECHA. Transfermarkt no manda `gameDay`
        // y tus fechas se llaman «8VOS», «CUARTOS»: de los dos lados queda una
        // llave vacía. Ahí el reemplazo es el TORNEO: en una copa dos equipos se
        // cruzan una sola vez por edición, así que par + torneo alcanza. Sin
        // torneo (se entró por `comp=` a mano) no hay con qué, y se devuelve null.
        if ($nRonda === '' && !$torneoId) return null;

        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -120 days'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +120 days'));

        $q = DB::table('partidos')
            ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->join('grupos', 'grupos.id', '=', 'fechas.grupo_id')
            ->whereBetween('partidos.dia', [$d0, $d1])
            ->where(function ($w) use ($equipoId, $rivalId) {
                $w->where(function ($x) use ($equipoId, $rivalId) {
                    $x->where('partidos.equipol_id', $equipoId)->where('partidos.equipov_id', $rivalId);
                })->orWhere(function ($x) use ($equipoId, $rivalId) {
                    $x->where('partidos.equipol_id', $rivalId)->where('partidos.equipov_id', $equipoId);
                });
            })
            ->select('partidos.id', 'fechas.numero', 'partidos.equipol_id', 'grupos.penales');

        // IDA Y VUELTA: EL PAR NO ALCANZA, HACE FALTA LA LOCALÍA. En una llave
        // los mismos dos equipos se cruzan DOS veces en la misma ronda, con la
        // localía invertida. Con sólo la ida cargada, la vuelta de TM
        // encontraba la ida como candidato único y quedaba «duplicado» de ella:
        // no se creaba nunca, y «Ya jugados con la fecha corrida» ofrecía mover
        // la ida al día de la vuelta. Pasó con la Copa UEFA 2000/01: 23
        // vueltas de «2ª Ronda - Vuelta» (07 y 09/11) atadas a sus idas del
        // 24 y 26/10 — el número de fecha no separa nada, «2ª Ronda - Ida»,
        // «2ª Ronda - Vuelta» y «2ª Ronda» dan todos 2.
        //
        // En contexto de copa —grupo de llaves, ronda que dice ida/vuelta, o
        // sin número de fecha— un candidato con la localía al revés es el
        // OTRO partido de la llave, no éste. En una liga se sigue aceptando:
        // ahí el número de fecha ya separa la ida de la vuelta, y la localía
        // invertida es un error de carga que la auditoría muestra aparte.
        $esIdaVuelta = (bool) preg_match('/\b(ida|vuelta)\b/iu', (string) $ronda);

        // Con el torneo elegido la llave es redonda. Sin él (se entró por
        // `comp=` a mano) queda el número de fecha solo, que puede repetirse
        // entre torneos: por eso se exige un único candidato.
        if ($torneoId) $q->where('grupos.torneo_id', (int) $torneoId);

        // COMPARAR COMO NÚMERO, NO COMO TEXTO: tus fechas se llaman «08» o
        // «Fecha 08» y la ronda de TM viene «8». Como cadenas no coinciden
        // nunca, y la segunda pasada no encontraría jamás un partido.
        $todos = [];
        $cands = [];
        foreach ($q->get() as $r) {
            $alReves = (int) $r->equipol_id !== (int) $equipoId;
            if ($alReves && ($esIdaVuelta || $nRonda === '' || (int) $r->penales === 1)) continue;
            $todos[] = (int) $r->id;
            $suyo = preg_replace('/\D/', '', (string) $r->numero);
            if ($suyo === '' || $nRonda === '') continue;
            if ((int) $suyo === (int) $nRonda) $cands[] = (int) $r->id;
        }
        $todos = array_values(array_unique($todos));
        $cands = array_values(array_unique($cands));

        if (count($cands) === 1) return \App\Partido::find($cands[0]);

        // Sin número de fecha de algún lado: queda el par dentro del torneo, y
        // sólo si hay UN candidato. Con dos (una llave de ida y vuelta, un
        // torneo que se juega dos veces) no se elige: mejor que caiga en
        // «nuevo» y lo mire una persona que atarlo al partido equivocado.
        if ($nRonda === '' && $torneoId && count($todos) === 1) {
            return \App\Partido::find($todos[0]);
        }

        return null;
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

        // Club desaparecido: TM manda "Sarayköy 1926 FK (1981-2019)" y nuestro
        // equipo se llama sin el paréntesis (el año de cierre vive en
        // `equipos.desaparicion`). Sin estas claves el club dejaría de
        // reconocerse por nombre. Si tenemos DOS equipos con el nombre limpio
        // —el desaparecido y otro homónimo—, la clave queda ambigua en
        // mapaNombres() y sale como conflicto, que es lo seguro.
        $cierre = \App\Services\ClubDesaparecido::partir($nombre);
        if ($cierre) {
            foreach ($this->clavesNombre($cierre['nombre']) as $k) $claves[] = $k;
        }

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

        // OJO CON LOS PENALES. Con `gameInformation.gameState = penalty_shootout`
        // el `goalsTotal` de TM NO es el marcador del partido: viene con la tanda
        // sumada. Verificado en el JSON de `/coach/2868/performance-game`
        // (Simeone): la final de la Champions 2015/16 --1:1 y tanda 3-5-- llega
        // como `goalsTotal: 4 / opponentGoalsTotal: 6`, que es lo que entraba a
        // `partidos.golesl/golesv` como si fuera el resultado.
        //
        // Y de este payload NO se puede separar: `clubsInformation.club` trae
        // solo `venue, clubId, coachId, goalsTotal, opponentGoalsTotal,
        // clubRank, tacticId, points`. Sin el desglose de la tanda, cargar el
        // 6:4 seria escribir un partido que no existio, asi que se deja SIN
        // marcador y se arregla de a uno con "Solo el marcador", que baja el
        // detalle (`/game/{id}`, ahi si viene `actions.shootout`) y lo separa.
        //
        // `extra_time` es otra cosa y su `goalsTotal` SI vale: es el de los 120'.
        // En la carrera de Simeone (1011 partidos): 994 `regularly_terminated`,
        // 8 `extra_time`, 9 `penalty_shootout`.
        $porPenales = (string) $this->valor($gi, ['gameState']) === 'penalty_shootout';
        if ($porPenales) {
            $gf = null;
            $gc = null;
        }

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
            // No es columna de import_partidos: sólo lo usa clasificar().
            'por_penales'             => $porPenales,
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

        // EL PARTIDO YA ES DE OTRA FILA (fixture u otro DT con el mismo gameId).
        // `uq_partido_gameid` (partido_id, external_id) no deja repetir el par, y
        // está bien que no: una segunda fila con gameId haría que la tanda de
        // detalles (que exige external_id) bajara el mismo partido otra vez.
        // Lo único que este DT le aporta al partido es su nombre en
        // partido_tecnicos, así que:
        //   · si el DT ya está → no se guarda nada;
        //   · si falta → se guarda la fila SIN gameId: sirve para «Agregar el
        //     DT» (completarTecnicos) y los conteos, y el detalle no la ve.
        if ($f['external_id'] && $f['partido_id']) {
            $duena = DB::table('import_partidos')
                ->where('partido_id', (int) $f['partido_id'])
                ->where('external_id', (string) $f['external_id'])
                ->where(function ($q) use ($tecnicoId) {
                    $tecnicoId ? $q->whereNull('tecnico_id')->orWhere('tecnico_id', '<>', (int) $tecnicoId)
                               : $q->whereNotNull('tecnico_id');
                })
                ->first(['id']);

            if ($duena) {
                $clave = ['fuente' => 'transfermarkt', 'tecnico_id' => $tecnicoId ?: null,
                    'partido_id' => (int) $f['partido_id'], 'external_id' => null];

                if (strpos((string) $f['motivo'], 'falta el DT') === false) {
                    // Nada que hacer. Si quedó una fila vieja sin gameId de este DT, se la deja.
                    return false;
                }

                $f['external_id'] = null;
                $f['motivo'] = mb_substr($f['motivo'] . ' · gameId en la fila #' . $duena->id, 0, 191);
            }
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
            . 'Si el club <b>no existe</b> en tu base, «Crear desde TM» lo da de alta con el nombre que ves acá, '
            . 'siglas, país, escudo y —leídos de «Datos y hechos»— fundación, estadio y socios; '
            . 'lo mapea solo y te deja en la edición para completar la historia, que Transfermarkt no tiene. '
            . 'Cuesta 3 llamadas. «En blanco» abre el alta de siempre. '
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
     * Las competencias excluidas de un DT, recordadas en
     * `tecnico_sondeos.fuera_detalle` (JSON: id TM => nombre y cantidad).
     *
     * - Bajada fresca de TM: lo que dio `separarPorNivel()` es la verdad y
     *   reemplaza lo guardado.
     * - Leyendo del staging: a lo recién excluido se le suma lo recordado, con
     *   la decisión recalculada (si una se incluyó mientras tanto, no se lista:
     *   «Incluir» vuelve a bajar de TM y ahí entra sola).
     *
     * Sin la columna, devuelve `$fuera` tal cual: todo sigue como antes.
     */
    private function fueraRecordado($tecnicoId, array $fuera, array $compsDentro, $bajadaFresca)
    {
        if (!Schema::hasTable('tecnico_sondeos') || !Schema::hasColumn('tecnico_sondeos', 'fuera_detalle')) {
            return $fuera;
        }

        if (!$bajadaFresca) {
            $json = DB::table('tecnico_sondeos')->where('tecnico_id', (int) $tecnicoId)->value('fuera_detalle');
            $previo = $json ? json_decode($json, true) : [];
            foreach ((array) $previo as $k => $g) {
                $k = (string) $k;
                if (isset($fuera[$k]) || isset($compsDentro[$k]) || !is_array($g)) continue;
                $nombre = (string) ($g['nombre'] ?? '');
                $d = NivelCompetencia::decidir($nombre);
                // Las que salieron por "equipo alternativo" no tienen regla por
                // nombre: se conservan con el motivo con que se guardaron.
                if (!$d['excluida'] && $d['origen'] !== 'manual' && ($g['origen'] ?? '') === 'auto'
                    && strpos((string) ($g['motivo'] ?? ''), 'alternativo') !== false) {
                    $d = ['excluida' => true, 'motivo' => $g['motivo'], 'origen' => 'auto'];
                }
                if (!$d['excluida']) continue;
                $fuera[$k] = ['nombre' => $nombre ?: ('#' . $k), 'n' => (int) ($g['n'] ?? 0),
                    'motivo' => $d['motivo'], 'origen' => $d['origen']];
            }
        }

        $guardar = [];
        foreach ($fuera as $k => $g) {
            $guardar[(string) $k] = ['nombre' => $g['nombre'], 'n' => (int) $g['n'],
                'motivo' => $g['motivo'], 'origen' => $g['origen']];
        }
        $this->registrarSondeo($tecnicoId, ['fuera_detalle' => json_encode($guardar, JSON_UNESCAPED_UNICODE)], false);

        return $fuera;
    }

    /**
     * Qué competencias entraron y cuáles quedaron afuera (automático por no ser
     * de 1ra, o por una regla guardada en competencias_excluidas).
     *
     * «Excluir» guarda una regla `contiene` en `competencias_excluidas` (sin el
     * año, así sirve para todas las temporadas) y vale para todo el sistema.
     * «Incluir» apaga las reglas que la tapaban y deja una regla APAGADA
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
            . '<summary>Competencias del sondeo <span class="sub">(' . count($dentro) . ' se cargan'
            . (empty($fuera) ? '' : ' · ' . count($fuera) . ' excluidas, ' . $nFuera . ' partidos') . ')</span></summary>'
            . '<p class="sub">Solo se cargan los torneos de <b>primera división</b>. Reserva, Proyección, juveniles y '
            . 'ascenso quedan afuera: no se listan abajo, no se guardan en staging y sus clubes no piden mapeo. '
            . 'Además quedan afuera las competencias con una regla guardada. Para cambiar cualquiera, dale al botón: '
            . 'la decisión queda guardada en '
            . '<a href="' . e(route('competencias_excluidas.index')) . '" target="_blank">Competencias excluidas ↗</a> '
            . 'y vale para todos los DTs.</p>'
            . '<div class="scroll"><table><thead><tr><th>Competencia</th><th>Partidos</th><th>Estado</th>'
            . '<th></th></tr></thead><tbody>';

        foreach ($dentro as $k => $d) {
            $out .= '<tr>'
                . '<td>' . e($d['nombre']) . ' <span class="id">' . e($k) . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td class="ok">se carga</td>'
                . '<td><a class="boton-sec" href="' . e($urlExcluir($d['nombre'])) . '">Excluir ✕</a></td>'
                . '</tr>';
        }
        foreach ($fuera as $k => $d) {
            $origen = $d['origen'] === 'regla' ? 'regla guardada' : 'automático';
            $out .= '<tr class="gris">'
                . '<td>' . e($d['nombre']) . ' <span class="id">' . e($k) . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td>excluida: ' . e($d['motivo']) . ' <span class="id">(' . e($origen) . ')</span></td>'
                . '<td><a class="boton-sec" href="' . e($urlIncluir($d['nombre'])) . '">Incluir ✓</a></td>'
                . '</tr>';
        }

        return $out . '</tbody></table></div>'
            . '<p class="sub"><b>«Incluir» vuelve a bajar los partidos de Transfermarkt</b> (1 llamada): '
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

    /**
     * El bloque de «parecidos no atados» del sondeo. Vacío si no hay ninguno.
     *
     * Existe porque el emparejador ahora DESCARTA candidatos (antes se colgaba
     * del primero que calzaba equipos + resultado). Un descarte silencioso es
     * tan malo como un emparejado silencioso: si no se muestra, el duplicado
     * aparece meses después y ya no se sabe de dónde salió.
     */
    private function avisoParecidos(array $filas)
    {
        $items = [];
        foreach ($filas as $f) {
            if (empty($f['cerca'])) continue;
            $items[] = '<li><b>' . e(substr((string) $f['dia'], 0, 10)) . '</b> · '
                . e($f['club_nombre'] . ' ' . $f['goles_favor'] . ':' . $f['goles_contra'] . ' ' . $f['rival_nombre'])
                . ' · ' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id']))
                . ' <span class="sub">' . e(implode(' · ', $f['cerca'])) . '</span>'
                . ' <span class="id">' . e($f['estado']) . '</span></li>';
        }
        if (empty($items)) return '';

        return '<div class="warn-box"><b>' . count($items) . ' partido(s) parecido(s) que NO se ataron.</b><br>'
            . 'Mismos equipos y mismo resultado que uno que ya tenés, pero de otra competencia o con la fecha '
            . 'lejos y sin poder confirmar el torneo. Se van a crear como partidos nuevos — que es lo correcto '
            . 'cuando son dos partidos distintos. Si alguno es el mismo partido, arreglale la fecha o el torneo '
            . 'antes de aplicar.'
            . '<ul>' . implode('', $items) . '</ul></div>';
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
