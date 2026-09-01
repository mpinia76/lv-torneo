<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\TmBuscarGameId;
use App\Services\TmDetallePartido;

/**
 * Segunda etapa de la carga: el DETALLE de cada partido.
 *
 * `ImportPartidosController` crea el partido (torneo → grupo → fecha → partido).
 * Acá se le agrega lo que pasó adentro: alineaciones, goles, tarjetas, cambios
 * y árbitros, bajándolo de Transfermarkt.
 *
 *   index()   -> partidos ya cargados que todavía no tienen alineación
 *   ver()     -> vista previa de UN partido, sin escribir nada
 *   bajar()   -> baja y guarda UN partido
 *   tanda()   -> baja y guarda los N primeros de la lista
 *   penales() -> pase liviano: sólo los penales fallados de lo ya cargado
 *   sembrar() -> llena jugador_tm con los jugadores que ya tienen transfermarkt_url
 *   revisar() -> jugadores creados automáticamente que falta repasar
 *   mapeos()  -> puentes con TM que apuntan a fichas borradas (no gasta API)
 *
 * Cada partido cuesta 1 llamada a la API, más 1 cada 50 jugadores nuevos.
 * Los jugadores se resuelven una sola vez en la vida.
 */
class ImportDetallesController extends Controller
{
    // ═══════════════════════════════ LISTA ═══════════════════════════════

    public function index(Request $request)
    {
        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $conDetalle = (string) $request->get('con_detalle', '0') === '1';
        $comp  = trim((string) $request->get('comp', ''));
        $ronda = trim((string) $request->get('ronda', ''));

        // `aplicado` son los que creó el importador; `duplicado` los que ya
        // tenías cargados y el fixture emparejó. A los dos se les puede bajar el
        // detalle: lo único que hace falta es el partido_id y el gameId.
        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')
            ->whereNotNull('external_id')
            ->whereIn('estado', ['aplicado', 'duplicado']);
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
        if ($comp !== '')  $q->where('competencia_external_id', $comp);
        if ($ronda !== '') $q->where('ronda', $ronda);

        $filas = $q->orderBy('dia', 'desc')->limit(2000)->get();

        // ¿Cuáles ya tienen alineación? Una sola consulta para todos.
        $ids = [];
        foreach ($filas as $f) $ids[] = (int) $f->partido_id;
        $conAlineacion = [];
        if (!empty($ids)) {
            foreach (DB::table('alineacions')->whereIn('partido_id', $ids)
                         ->select('partido_id', DB::raw('COUNT(*) AS n'))
                         ->groupBy('partido_id')->get() as $r) {
                $conAlineacion[(int) $r->partido_id] = (int) $r->n;
            }
        }

        // El resultado sale de `partidos`, NO del staging: cuando se baja el
        // detalle el marcador se escribe en `partidos.golesl/golesv`, mientras
        // que la fila de `import_partidos` conserva lo que traía Transfermarkt
        // el día del fixture — vacío si el partido todavía no se había jugado.
        $marcadores = [];
        if (!empty($ids)) {
            foreach (DB::table('partidos')->whereIn('id', $ids)
                         ->select('id', 'golesl', 'golesv')->get() as $r) {
                $marcadores[(int) $r->id] = $r;
            }
        }

        $sinResultado = 0;
        foreach (array_unique($ids) as $pid) {
            if (!isset($marcadores[$pid])) continue;
            if ($marcadores[$pid]->golesl === null || $marcadores[$pid]->golesv === null) $sinResultado++;
        }

        $fechas = $this->mapaFechas($ids);

        $pendientes = [];
        $listos = 0;
        foreach ($filas as $f) {
            if (isset($conAlineacion[(int) $f->partido_id])) { $listos++; if (!$conDetalle) continue; }
            $pendientes[] = $f;
        }

        $tecnicos = $this->tecnicosConPartidos();

        $mapeados = DB::table('jugador_tm')->count();
        $porRevisar = DB::table('jugador_tm')->where('revisar', 1)->count();

        // Jugadores que ya tenés cargados con URL de Transfermarkt y todavía no
        // están atados. Mientras queden, conviene sembrar antes de bajar nada:
        // si no, el importador los vuelve a crear como personas nuevas.
        $conUrl = DB::table('jugadors')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->where('transfermarkt_url', 'like', '%/spieler/%')->count();
        // Pendiente = la URL no tiene fila en jugador_tm. NO se cuenta por
        // origen: el mapeo lo crea también el importador (origen='api'), así que
        // restar los origen='url' dejaba el cartel rojo puesto para siempre.
        $siembra = TmDetallePartido::estadoSiembra();
        $porSembrar = $siembra['pendientes'];
        $chocan = $siembra['conflictos'];
        $sembrados = max(0, $conUrl - $porSembrar);

        // Mapeos que apuntan a una ficha que ya no existe. Mientras estén, cada
        // partido donde aparezca ese jugador escribe un id fantasma: la fila se
        // rechaza por foreign key, pero antes le saca el dorsal al que sí está.
        $nRotos = TmDetallePartido::contarMapeosRotos();

        // Partidos con detalle cargado a los que todavía no se les preguntó por
        // los penales fallados (el importador no los leía hasta el 31/08/2026).
        // Ver `penales()`. Si la migración todavía no corrió, el cartel no sale.
        $sinPenalesRevisar = 0;
        if (Schema::hasColumn('import_partidos', 'penales_revisado_at')) {
            $sinPenalesRevisar = DB::table('import_partidos')
                ->whereNotNull('partido_id')->whereNotNull('external_id')
                ->whereIn('estado', ['aplicado', 'duplicado'])
                ->whereNull('penales_revisado_at')
                ->whereIn('partido_id', function ($sub) {
                    $sub->from('alineacions')->select('partido_id')->distinct();
                })
                ->distinct()->count('partido_id');
        }

        // Lo mismo para los tipos de gol: el olímpico no existía hasta el
        // 01/09/2026 y caía en «Jugada». Ver `tiposGol()`. Sólo cuentan los
        // partidos que tienen algún gol cargado: los otros no tienen nada que
        // corregir y no vale gastarles una llamada.
        $sinTiposGolRevisar = 0;
        if (Schema::hasColumn('import_partidos', 'tipos_gol_revisado_at')) {
            $sinTiposGolRevisar = DB::table('import_partidos')
                ->whereNotNull('partido_id')->whereNotNull('external_id')
                ->whereIn('estado', ['aplicado', 'duplicado'])
                ->whereNull('tipos_gol_revisado_at')
                ->whereIn('partido_id', function ($sub) {
                    $sub->from('gols')->select('partido_id')->distinct();
                })
                ->distinct()->count('partido_id');
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Detalle de los partidos</h1>'
            . '<p class="sub">Alineaciones, goles, tarjetas, cambios y árbitros. Cada partido es <b>una</b> llamada a la API, '
            . 'más una cada 50 jugadores que aparezcan por primera vez.</p>'

            . ($nRotos
                ? '<div class="err-box"><b>Hay ' . $nRotos . ' mapeo(s) roto(s).</b><br>'
                . 'Apuntan a fichas que ya no existen: se las llevó una fusión de personas o el borrado de '
                . 'huérfanas, y <code>jugador_tm</code> no tiene foreign key que lo impida. El importador ya no '
                . 'los usa, pero conviene limpiarlos.<br>'
                . '<a class="boton" style="margin-top:8px" href="' . e(route('import_detalles.mapeos')) . '">Ver los mapeos rotos</a>'
                . '</div>'
                : '')

            . ($porSembrar
                ? '<div class="err-box"><b>Antes de bajar nada, sembrá el mapeo.</b><br>'
                . 'Tenés <b>' . $porSembrar . '</b> jugador(es) con URL de Transfermarkt que todavía no están atados a su id. '
                . 'Si arrancás sin esto, el importador los va a crear de nuevo como personas duplicadas.<br>'
                . '<a class="boton" style="margin-top:8px" href="' . e(route('import_detalles.sembrar')) . '">Sembrar jugador_tm ahora</a>'
                . '</div>'
                : ($conUrl === 0
                    ? '<div class="diag"><b>No hay nada que sembrar.</b> Ningún jugador de la base tiene guardada su URL de '
                    . 'Transfermarkt (<code>jugadors.transfermarkt_url</code>): esa columna se llena sola cuando usás el '
                    . 'scraper de Transfermarkt en la ficha de un jugador, y hasta ahora sólo la tienen los DTs.<br>'
                    . 'No es un problema para arrancar: al no encontrar el mapeo, el importador igual busca al jugador en '
                    . 'la base por <b>apellido + fecha de nacimiento</b> antes de crearlo, y a partir de ahí lo deja atado '
                    . 'a su id de Transfermarkt para siempre. El riesgo que queda es un jugador que ya tengas cargado '
                    . 'con el apellido escrito distinto o sin fecha de nacimiento: ese se va a duplicar, y por eso las '
                    . 'altas quedan en <a href="' . e(route('import_detalles.revisar')) . '">jugadores por revisar</a>.</div>'
                    : '<div class="ok-box">Mapeo sembrado: los ' . $sembrados . ' jugadores que ya tenías con URL de '
                    . 'Transfermarkt están atados a su id.</div>'))

            . ($chocan
                ? '<div class="err-box"><b>Hay ' . $chocan . ' URL(s) que chocan con el mapeo.</b><br>'
                . 'Ese id de Transfermarkt ya está atado a <b>otra</b> ficha que también existe. La siembra no los '
                . 'pisa —los partidos ya cargados con ese id cuelgan de la otra ficha—, casi siempre es la misma '
                . 'persona cargada dos veces.<br>'
                . '<a class="boton" style="margin-top:8px" href="' . e(route('import_detalles.sembrar')) . '">Ver cuáles son</a>'
                . '</div>'
                : '')

            . '<div class="cards">'
            . $this->card(count($filas), 'Partidos importados')
            . $this->card($listos, 'Con detalle', 'ok')
            . $this->card(count($filas) - $listos, 'Sin detalle', (count($filas) - $listos) ? 'warn' : '')
            . $this->card($mapeados, 'Jugadores mapeados')
            . $this->card($porRevisar, 'Por revisar', $porRevisar ? 'warn' : '')
            . $this->card($nRotos, 'Mapeos rotos', $nRotos ? 'warn' : '')
            . $this->card($sinResultado, 'Sin resultado', $sinResultado ? 'warn' : '')
            . '</div>'

            . '<form method="get" style="margin:12px 0">'
            . '<select name="tecnico_id" class="s2" data-placeholder="todos los DT"><option value="">— todos los DT —</option>';
        foreach ($tecnicos as $t) {
            $cuerpo .= '<option value="' . (int) $t->id . '"' . ($tecnicoId === (int) $t->id ? ' selected' : '') . '>'
                . e($t->nombre) . ' (' . (int) $t->n . ')</option>';
        }
        $cuerpo .= '</select> '
            . '<input name="comp" value="' . e($comp) . '" placeholder="competencia, ej ARGC" size="14"> '
            . '<input name="ronda" value="' . e($ronda) . '" placeholder="fecha nº" size="8"> '
            . '<label><input type="checkbox" name="con_detalle" value="1"' . ($conDetalle ? ' checked' : '') . '> mostrar también los que ya tienen detalle</label> '
            . '<button>Filtrar</button>'
            . (($comp !== '' || $ronda !== '' || $tecnicoId)
                ? ' <a href="' . e(route('import_detalles.index')) . '">limpiar filtros</a>' : '')
            . '</form>'
            . (($comp !== '' || $ronda !== '')
                ? '<p class="sub">Filtrando por <b>' . e($comp !== '' ? $comp : 'todas') . '</b>'
                  . ($ronda !== '' ? ' · fecha <b>' . e($ronda) . '</b>' : '') . '.</p>'
                : '')

            . '<p class="acciones">'
            . '<a class="boton-sec" href="' . e(route('import_detalles.sembrar')) . '">Sembrar jugador_tm desde las URLs</a>'
            . '<a class="boton-sec" href="' . e(route('import_detalles.revisar')) . '">Jugadores por revisar (' . $porRevisar . ')</a>'
            . ($nRotos
                ? '<a class="boton-sec" href="' . e(route('import_detalles.mapeos')) . '">Mapeos rotos (' . $nRotos . ')</a>'
                : '')
            . '<a class="boton-sec" href="' . e(route('import_detalles.plantillas', array_filter(['tecnico_id' => $tecnicoId ?: null])))
            . '">Completar plantillas de lo ya cargado</a>'
            . ($sinResultado
                ? '<a class="boton-sec" href="' . e(route('import_detalles.resultados', array_filter(['tecnico_id' => $tecnicoId ?: null,
                    'comp' => $comp ?: null, 'ronda' => $ronda ?: null])))
                . '">Completar resultados (' . $sinResultado . ')</a>'
                : '')
            . ($sinPenalesRevisar
                ? '<a class="boton-sec" href="' . e(route('import_detalles.penales', array_filter(['tecnico_id' => $tecnicoId ?: null,
                    'comp' => $comp ?: null, 'ronda' => $ronda ?: null])))
                . '">Penales fallados sin revisar (' . $sinPenalesRevisar . ')</a>'
                : '')
            . ($sinTiposGolRevisar
                ? '<a class="boton-sec" href="' . e(route('import_detalles.tipos_gol', array_filter(['tecnico_id' => $tecnicoId ?: null,
                    'comp' => $comp ?: null, 'ronda' => $ronda ?: null])))
                . '">Tipos de gol sin revisar (' . $sinTiposGolRevisar . ')</a>'
                : '')
            . '</p>';

        if ($listos) {
            $paraRehacer = min(10, $listos);
            $cuerpo .= '<p class="acciones"><a class="boton-sec" href="'
                . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null,
                    'n' => $paraRehacer, 'comp' => $comp ?: null, 'ronda' => $ronda ?: null, 'rehacer' => 1])))
                . '">Rehacer el detalle de los ' . $paraRehacer . ' más nuevos</a>'
                . ' <span class="sub">vuelve a bajarlos de Transfermarkt y pisa lo que ya tenían '
                . '(' . $paraRehacer . ' llamadas)</span></p>';
        }

        if (empty($pendientes)) {
            $cuerpo .= '<div class="ok-box">No queda ningún partido sin detalle' . ($tecnicoId ? ' para este DT' : '') . '.</div>';
            return $this->pagina('Detalle de partidos', $cuerpo);
        }

        $paraTanda = min(10, count($pendientes));
        $cuerpo .= '<p class="acciones">'
            . '<a class="boton" href="' . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null,
                'n' => $paraTanda, 'comp' => $comp ?: null, 'ronda' => $ronda ?: null])))
            . '">Bajar los primeros ' . $paraTanda . '</a>'
            . ' <span class="sub">≈ ' . $paraTanda . ' llamadas + las de jugadores nuevos</span></p>';

        $cuerpo .= '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Local</th><th></th><th>Visitante</th><th>Res.</th>'
            . '<th>gameId</th><th>Partido</th><th>Detalle</th><th></th></tr></thead><tbody>';

        $n = 0;
        foreach ($pendientes as $f) {
            if ($n++ >= 400) break;
            $tiene = isset($conAlineacion[(int) $f->partido_id]);
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);
            $cuerpo .= '<tr>'
                . '<td class="num">' . e($f->dia ? substr($f->dia, 0, 10) : '—') . '</td>'
                . '<td>' . e($f->competencia_nombre) . '</td>'
                . '<td>' . e($f->local ? $f->club_nombre : $f->rival_nombre) . '</td>'
                . '<td class="num">vs</td>'
                . '<td>' . e($f->local ? $f->rival_nombre : $f->club_nombre) . '</td>'
                . '<td class="num">' . $this->resultado($f, isset($marcadores[(int) $f->partido_id]) ? $marcadores[(int) $f->partido_id] : null) . '</td>'
                . '<td class="num">' . e($f->external_id) . '</td>'
                . '<td class="num"><span class="id">#' . (int) $f->partido_id . '</span></td>'
                . '<td>' . ($tiene ? '<span class="ok">' . $conAlineacion[(int) $f->partido_id] . ' jugadores</span>' : '<span class="warn">—</span>') . '</td>'
                . '<td><a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $f->partido_id])) . '">Ver</a>'
                . ' · <a href="' . e(route('import_detalles.bajar', ['partido_id' => (int) $f->partido_id])) . '"><b>Bajar</b></a>'
                . ($tiene ? ' · <a href="' . e(route('import_detalles.bajar', ['partido_id' => (int) $f->partido_id, 'forzar' => 1])) . '" class="err">Rehacer</a>' : '')
                . ($inc !== '' ? ' · ' . $inc : '')
                . '</td></tr>';
        }
        $cuerpo .= '</tbody></table></div>';
        if (count($pendientes) > 400) {
            $cuerpo .= '<p class="sub">Se muestran 400 de ' . count($pendientes) . '. Filtrá por DT para ver el resto.</p>';
        }

        return $this->pagina('Detalle de partidos', $cuerpo);
    }

    /**
     * El resultado que muestra la tabla.
     *
     * Sale de `partidos`, no de la fila de staging. El staging guarda el
     * marcador que tenía Transfermarkt el día que se bajó el fixture: si el
     * partido todavía no se había jugado, ahí quedó vacío para siempre. Al
     * bajar el detalle el marcador se escribe en `partidos.golesl/golesv`, y
     * esa es la única fuente que se actualiza.
     *
     * Recién si el partido no tiene marcador se cae al staging, y ahí hay que
     * darlo vuelta cuando el DT era visitante: el staging guarda goles a favor
     * y en contra DEL DT, y estas columnas muestran local y visitante.
     */
    private function resultado($fila, $partido)
    {
        if ($partido && $partido->golesl !== null && $partido->golesv !== null) {
            return (int) $partido->golesl . ':' . (int) $partido->golesv;
        }
        if ($fila->goles_favor === null || $fila->goles_contra === null) return ':';
        return $fila->local
            ? e($fila->goles_favor) . ':' . e($fila->goles_contra)
            : e($fila->goles_contra) . ':' . e($fila->goles_favor);
    }

    // ═══════════════════════════ UN PARTIDO ═══════════════════════════

    /** Vista previa: baja el partido y muestra qué cargaría, sin tocar la base. */
    public function ver(Request $request)
    {
        return $this->correrUno($request, false);
    }

    /** Baja y guarda. */
    public function bajar(Request $request)
    {
        return $this->correrUno($request, true);
    }

    private function correrUno(Request $request, $escribir)
    {
        set_time_limit(0);

        $partidoId = (int) $request->get('partido_id', 0);
        $gameId    = trim((string) $request->get('game_id', ''));
        $forzar    = (string) $request->get('forzar', '0') === '1';

        // Si el gameId viene en la URL es porque lo eligió el usuario entre los
        // candidatos: si el partido baja bien, se anota para no volver a preguntar.
        $elegido = $gameId !== '';

        $fila = null;
        if ($partidoId) {
            $fila = DB::table('import_partidos')->where('partido_id', $partidoId)
                ->whereNotNull('external_id')->orderBy('id', 'desc')->first();
            if ($fila && $gameId === '') $gameId = (string) $fila->external_id;
        }

        // Sin gameId en el staging lo buscamos en Transfermarkt: con los dos
        // clubes y la fecha alcanza. Así "Rehacer" anda en cualquier partido y
        // no sólo en los que pasaron por el importador de DTs.
        $buscado = null;
        if ($partidoId && $gameId === '') {
            $buscador = new TmBuscarGameId;
            $buscado  = $buscador->buscar($partidoId);

            if (!empty($buscado['game_id'])) {
                $gameId = (string) $buscado['game_id'];
                $buscador->anotar($partidoId, $gameId, 'encontrado solo por ' . $buscado['como']);

                $fila = DB::table('import_partidos')->where('partido_id', $partidoId)
                    ->whereNotNull('external_id')->orderBy('id', 'desc')->first();
            }
        }

        if (!$partidoId || $gameId === '') {
            return $this->pagina('Detalle', $this->sinGameId($partidoId, $buscado));
        }

        // &fotos=0 para no gastar una llamada por cada persona nueva.
        $fotos = (string) $request->get('fotos', '1') !== '0';

        $r = (new TmDetallePartido)->importar($partidoId, $gameId,
            ['escribir' => $escribir, 'forzar' => $forzar, 'fotos' => $fotos]);

        // El importador aborta si los clubes no aparean, así que llegar hasta acá
        // sin error es la confirmación de que el gameId elegido era el correcto.
        if ($elegido && empty($r['error'])) {
            (new TmBuscarGameId)->anotar($partidoId, $gameId, 'elegido entre los candidatos de Transfermarkt');

            if (!$fila) {
                $fila = DB::table('import_partidos')->where('partido_id', $partidoId)
                    ->whereNotNull('external_id')->orderBy('id', 'desc')->first();
            }
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>' . ($escribir ? 'Detalle cargado' : 'Vista previa') . ' · partido #' . $partidoId . '</h1>';

        if ($fila) {
            $mapa = $this->mapaFechas([$partidoId]);
            $inc  = $this->linkIncidencias(isset($mapa[$partidoId]) ? $mapa[$partidoId] : null,
                'Incidencias del partido →');
            $cuerpo .= '<p class="sub">' . e($fila->club_nombre . ' vs ' . $fila->rival_nombre)
                . ' · ' . e(substr((string) $fila->dia, 0, 10)) . ' · ' . e((string) $fila->competencia_nombre)
                . ' · gameId ' . e($gameId) . ' · ' . (int) $r['llamadas'] . ' llamada(s) a la API'
                . ($inc !== '' ? ' · ' . $inc : '') . '</p>';
        }

        if ($r['error']) {
            $cuerpo .= '<div class="err-box">' . e($r['error']) . '</div>';
        } elseif ($escribir && $r['escrito']) {
            $fallidas = isset($r['fallidas']) ? (int) $r['fallidas'] : 0;
            $cuerpo .= $fallidas
                ? '<div class="err-box">Guardado, pero la base rechazó <b>' . $fallidas . '</b> fila(s). '
                . 'Mirá los avisos para saber cuáles.</div>'
                : '<div class="ok-box">Guardado.</div>';
        } elseif (!$escribir) {
            // Si el partido ya tiene detalle, el único guardado posible es
            // rehacerlo: el botón lo dice y lleva forzar=1.
            $yaTiene = DB::table('alineacions')->where('partido_id', $partidoId)->exists();
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.bajar', ['partido_id' => $partidoId, 'forzar' => ($yaTiene || $forzar) ? 1 : null]))
                . '">' . ($yaTiene ? 'Rehacer y guardar' : 'Guardar esto') . '</a>'
                . ($yaTiene ? ' <span class="sub">reemplaza alineación, goles, tarjetas, cambios, penales fallados '
                    . 'y árbitros de este partido (los penales «Convirtieron» no se tocan)</span>' : '')
                . '</p>';
        }

        $p = $r['plan'];
        $cuerpo .= '<div class="cards">'
            . $this->card(count($p['alineacions']), 'Alineación')
            . $this->card(count($p['gols']), 'Goles')
            . $this->card(count($p['tarjetas']), 'Tarjetas')
            . $this->card(count($p['cambios']), 'Cambios')
            . $this->card(count(isset($p['penals']) ? $p['penals'] : []), 'Penales fallados')
            . $this->card(count($p['arbitros']), 'Árbitros')
            . $this->card(count(isset($p['tecnicos']) ? $p['tecnicos'] : []), 'Técnicos')
            . $this->card(count(isset($p['plantillas']) ? $p['plantillas'] : []), 'A la plantilla')
            . $this->card(count($r['creados']['jugadores']), 'Jugadores nuevos', count($r['creados']['jugadores']) ? 'warn' : '')
            . '</div>';

        if (!empty($r['avisos'])) {
            $cuerpo .= '<h2>Avisos</h2><div class="diag">';
            foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . $this->avisoHtml($a) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!empty($r['creados']['jugadores'])) {
            $cuerpo .= '<h2>Jugadores que no estaban en la base</h2><div class="diag">';
            foreach ($r['creados']['jugadores'] as $j) $cuerpo .= '<div>• ' . e($j) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!empty($r['creados']['arbitros'])) {
            $cuerpo .= '<h2>Árbitros que no estaban en la base</h2><div class="diag">';
            foreach ($r['creados']['arbitros'] as $a) $cuerpo .= '<div>• ' . e($a) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!empty($r['creados']['tecnicos'])) {
            $cuerpo .= '<h2>Técnicos que no estaban en la base</h2><div class="diag">'
                . '<p class="sub">Se crean con su URL de Transfermarkt, así que van a aparecer solos '
                . 'en la lista de Carga de partidos y les vas a poder sondear los suyos.</p>';
            foreach ($r['creados']['tecnicos'] as $t) $cuerpo .= '<div>• ' . e($t) . '</div>';
            $cuerpo .= '</div>';
        }

        $cuerpo .= $this->bloque('Alineación', $p['alineacions'], ['_equipo' => 'Equipo', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', 'dorsal' => 'Dorsal', '_fuente' => 'De dónde sale el dorsal',
            'orden' => 'Puesto']);
        $cuerpo .= $this->bloque('Goles', $p['gols'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Tarjetas', $p['tarjetas'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Cambios', $p['cambios'], ['minuto' => 'Min', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', '_equipo' => 'Equipo', '_fuente' => 'Cómo lo deduje']);
        if (!empty($p['penals'])) {
            $cuerpo .= '<p class="sub" style="margin-top:24px">Los <b>penales fallados</b> se cargan con una fila '
                . 'por protagonista, igual que cuando los cargás a mano: el que lo tiró afuera va como '
                . '<b>Errado</b>, y en uno atajado van los dos — el pateador como <b>Atajado</b> y el arquero '
                . 'como <b>Atajó</b>. Los penales <b>convertidos</b> no salen en esta tabla: son un gol de tipo '
                . 'Penal y su fila «Convirtieron» se le crea al arquero al final.</p>';
        }
        $cuerpo .= $this->bloque('Penales fallados', isset($p['penals']) ? $p['penals'] : [],
            ['minuto' => 'Min', '_nombre' => 'Jugador', '_equipo' => 'Equipo', 'tipo' => 'Tipo',
             '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Árbitros', $p['arbitros'],
            ['tipo' => 'Rol', '_nombre' => 'Árbitro', '_fuente' => 'Texto de Transfermarkt']);

        if (!empty($r['crudo_arbitros'])) {
            $cuerpo .= '<h2>Árbitros · crudo de Transfermarkt</h2>'
                . '<p class="sub">Tal como viene en el JSON. Es lo que hace falta para leer el rol de verdad '
                . 'en vez de inferirlo por la posición.</p>'
                . '<pre>' . e(json_encode($r['crudo_arbitros'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }
        $cuerpo .= $this->bloque('Técnicos', isset($p['tecnicos']) ? $p['tecnicos'] : [],
            ['_equipo' => 'Equipo', '_nombre' => 'DT', '_estado' => 'Estado']);

        if (!empty($p['plantillas'])) {
            $cuerpo .= '<p class="sub" style="margin-top:24px">Los ' . count($p['plantillas']) . ' jugadores de arriba '
                . 'también se suman a la <b>plantilla</b> de su equipo en este torneo. Sin eso, la pantalla de '
                . '<code>/admin/alineaciones</code> no los ofrece en los desplegables y no se puede editar el partido a mano. '
                . 'Se usa la plantilla que el equipo ya tenga en <b>cualquier grupo del torneo</b> —una por torneo, aunque '
                . 'después pase de zona a fase final— y sólo se crea una nueva si no existía ninguna. '
                . 'A los que ya estén en la plantilla no se les toca el dorsal.</p>';
        }

        if ((string) $request->get('crudo', '0') === '1' && $r['crudo']) {
            $cuerpo .= '<h2>JSON crudo</h2><pre>' . e(json_encode($r['crudo'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
            $cuerpo .= '<p class="sub"><a href="' . e($request->fullUrl() . (strpos($request->fullUrl(), '?') === false ? '?' : '&') . 'crudo=1')
                . '">Ver el JSON crudo del partido</a> — útil cuando algo no se reconoce.</p>';
        }

        return $this->pagina('Detalle del partido', $cuerpo);
    }

    /**
     * Qué mostrar cuando la búsqueda automática no pudo decidir sola.
     *
     * Casi nunca es que TM no tenga el partido: es que hay dos o tres que
     * podrían serlo y no hay con qué desempatar (los clubes no están atados en
     * `equipo_tm`). Entonces no se manda al usuario a buscar la URL: se le
     * muestran los candidatos con fecha y nombres, y elige de un clic. Lo que
     * elige queda anotado, así que el partido no vuelve a preguntar.
     */
    private function sinGameId($partidoId, $buscado)
    {
        if (!$partidoId) {
            return '<p class="err">Falta <code>?partido_id=</code>.</p>';
        }

        $candidatos = isset($buscado['candidatos']) ? $buscado['candidatos'] : [];

        $html = '<h1>' . ($candidatos ? '¿Cuál de estos es?' : 'No encontré este partido en Transfermarkt') . '</h1>'
            . '<p class="sub">' . $this->resumenPartido($partidoId) . '</p>';

        if ($candidatos) {
            $html .= '<p class="sub">Estos son los partidos de Transfermarkt que podrían ser éste. '
                . 'No elijo yo porque los equipos no están atados a sus clubes de Transfermarkt y un gameId '
                . 'equivocado escribe la alineación de otro partido. Elegí vos y queda anotado para siempre.</p>'
                . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Partido en Transfermarkt</th>'
                . '<th>gameId</th><th></th><th></th></tr></thead><tbody>';

            foreach ($candidatos as $c) {
                $html .= '<tr>'
                    . '<td class="num">' . e((string) $c['dia']) . '</td>'
                    . '<td>' . e($c['local']) . ' vs ' . e($c['visita']) . '</td>'
                    . '<td class="num gris">' . e($c['game_id']) . '</td>'
                    . '<td><a href="' . e(\App\Services\Controles::TM_PARTIDO . $c['game_id'])
                    . '" target="_blank" rel="noopener">ver en TM</a></td>'
                    . '<td><a class="boton" href="'
                    . e(route('import_detalles.ver', ['partido_id' => (int) $partidoId, 'game_id' => $c['game_id']]))
                    . '">Es éste →</a></td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div>'
                . '<p class="sub">«Es éste» abre la vista previa: muestra qué va a escribir <b>antes</b> de tocar '
                . 'nada, y si los equipos no aparean con los de la base te avisa ahí mismo.</p>';
        } else {
            $html .= '<p class="sub">Para bajarle el detalle hace falta el <b>gameId</b> de Transfermarkt. '
                . 'Lo busqué por el fixture de los dos clubes y por los partidos de los DTs, y no apareció.</p>';
        }

        if (!empty($buscado['avisos'])) {
            $html .= '<div class="diag">';
            foreach ($buscado['avisos'] as $a) {
                $html .= '<div class="warn">• ' . e($a) . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '<p class="sub"><b>Para que se rehaga solo la próxima vez</b>, lo que falta es una de estas dos: '
            . 'que los dos equipos estén atados a su club de Transfermarkt en <code>equipo_tm</code> '
            . '(el sondeo del DT aprende esos mapeos solo), o que los DTs del partido tengan cargada su '
            . 'URL de Transfermarkt. Con cualquiera de las dos, este mismo botón lo encuentra sin ayuda.</p>'
            . '<p class="sub">Última salida, sólo para este partido: '
            . '<a href="' . e(route('fechas.importarPartido', ['partidoId' => (int) $partidoId])) . '">'
            . 'pegar la URL de Transfermarkt a mano</a>. Pegándola una vez queda anotado el gameId y de ahí en más '
            . 'este partido se rehace solo.</p>';

        return $html;
    }

    /** "Equipo A vs Equipo B · 2026-08-19", para saber de qué partido hablamos. */
    private function resumenPartido($partidoId)
    {
        $p = DB::table('partidos')
            ->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->where('partidos.id', (int) $partidoId)
            ->first(['partidos.dia', 'el.nombre as local', 'ev.nombre as visita']);

        if (!$p) {
            return 'Partido #' . (int) $partidoId;
        }

        return e($p->local) . ' vs ' . e($p->visita)
            . ($p->dia ? ' · ' . e(substr((string) $p->dia, 0, 10)) : '')
            . ' · partido #' . (int) $partidoId;
    }

    // ═══════════════════════════════ TANDA ═══════════════════════════════

    public function tanda(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $n = max(1, min(50, (int) $request->get('n', 10)));
        // rehacer=1: en vez de los que faltan, vuelve a bajar los que YA tienen
        // detalle. Sirve cuando el importador aprendió algo nuevo (plantillas,
        // técnicos, un tipo de gol) después de haberlos cargado.
        $rehacer = (string) $request->get('rehacer', '0') === '1';
        $desde   = (int) $request->get('offset', 0);

        $comp  = trim((string) $request->get('comp', ''));
        $ronda = trim((string) $request->get('ronda', ''));

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('external_id')
            ->whereIn('estado', ['aplicado', 'duplicado']);
        if ($comp !== '')  $q->where('competencia_external_id', $comp);
        if ($ronda !== '') $q->where('ronda', $ronda);
        if ($rehacer) {
            $q->whereIn('partido_id', function ($sub) {
                $sub->from('alineacions')->select('partido_id')->distinct();
            });
        } else {
            $q->whereNotIn('partido_id', function ($sub) {
                $sub->from('alineacions')->select('partido_id')->distinct();
            });
        }
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);

        $filas = $q->orderBy('dia', 'desc')->offset($rehacer ? $desde : 0)->limit($n)->get();

        $fechas = $this->mapaFechas($filas->pluck('partido_id')->all());

        $imp = new TmDetallePartido;
        $ok = 0; $fallaron = 0; $llamadas = 0; $nuevos = 0;
        $detalle = '';

        foreach ($filas as $f) {
            $r = $imp->importar((int) $f->partido_id, (string) $f->external_id,
                ['escribir' => true, 'forzar' => $rehacer]);
            $llamadas += (int) $r['llamadas'];
            $nuevos   += count($r['creados']['jugadores']);

            $etiqueta = e($f->club_nombre . ' vs ' . $f->rival_nombre) . ' <span class="id">'
                . e(substr((string) $f->dia, 0, 10)) . ' · partido #' . (int) $f->partido_id . '</span>';
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);

            if ($r['escrito']) {
                $ok++;
                $detalle .= '<div><span class="ok">✔</span> ' . $etiqueta . ' — '
                    . count($r['plan']['alineacions']) . ' en la alineación, '
                    . count($r['plan']['gols']) . ' goles, '
                    . count($r['plan']['tarjetas']) . ' tarjetas, '
                    . count($r['plan']['cambios']) . ' cambios'
                    . (count(isset($r['plan']['penals']) ? $r['plan']['penals'] : [])
                        ? ', ' . count($r['plan']['penals']) . ' de penales fallados' : '')
                    . (count($r['plan']['arbitros']) ? ', ' . count($r['plan']['arbitros']) . ' árbitros' : '')
                    . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $f->partido_id])) . '">ver</a>'
                    . ($inc !== '' ? ' · ' . $inc : '') . '</div>';
            } else {
                $fallaron++;
                $detalle .= '<div><span class="err">✘</span> ' . $etiqueta . ' — ' . e((string) $r['error']) . '</div>';
            }
            foreach ($r['avisos'] as $a) {
                $detalle .= '<div class="sub" style="margin-left:18px">• ' . $this->avisoHtml($a) . '</div>';
            }
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null,
                'comp' => $comp ?: null, 'ronda' => $ronda ?: null]))) . '">← Detalle de los partidos</a></p>'
            . '<h1>' . ($rehacer ? 'Rehacer detalles' : 'Tanda de detalles') . '</h1>'
            . ($rehacer ? '<p class="sub">Se vuelve a bajar el detalle de partidos que <b>ya lo tenían</b>: '
                . 'reemplaza alineación, goles, tarjetas, cambios y árbitros, y completa lo que el importador '
                . 'no sabía hacer cuando los cargaste (plantillas, técnicos). Van del ' . ($desde + 1)
                . ' al ' . ($desde + $n) . ' de la lista, del más nuevo al más viejo.</p>' : '')
            . '<div class="cards">'
            . $this->card($ok, 'Cargados', 'ok')
            . $this->card($fallaron, 'Con problema', $fallaron ? 'err' : '')
            . $this->card($nuevos, 'Jugadores nuevos', $nuevos ? 'warn' : '')
            . $this->card($llamadas, 'Llamadas a la API')
            . '</div>';

        if ($filas->isEmpty()) {
            $cuerpo .= '<div class="ok-box">No quedaban partidos sin detalle.</div>';
        } else {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null, 'n' => $n,
                    'comp' => $comp ?: null, 'ronda' => $ronda ?: null,
                    'rehacer' => $rehacer ? 1 : null, 'offset' => $rehacer ? ($desde + $n) : null])))
                . '">' . ($rehacer ? 'Rehacer los ' . $n . ' siguientes' : 'Otra tanda de ' . $n) . '</a>'
                . '<a class="boton-sec" href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null,
                'comp' => $comp ?: null, 'ronda' => $ronda ?: null]))) . '">Volver a la lista</a></p>'
                . '<div class="diag">' . $detalle . '</div>';
        }

        return $this->pagina('Tanda de detalles', $cuerpo);
    }

    // ═══════════════════════════ PENALES FALLADOS ═══════════════════════════

    /**
     * Los partidos a los que todavía no se les preguntó por los penales fallados.
     *
     * Hasta el 31/08/2026 el importador no leía `actions.missedPenalties`, así
     * que a todo lo cargado antes le puede faltar un penal errado o atajado.
     * **Cuáles, no se puede saber desde la base**: el dato sólo existe en
     * Transfermarkt y hay que preguntarlo partido por partido. Por eso la lista
     * son TODOS los partidos con detalle cargado sin revisar, y por eso el
     * resultado queda anotado en `import_partidos.penales_revisado_at`: la
     * llamada se paga una sola vez y la lista se achica sola.
     *
     * El pase escribe SÓLO `penals`. No toca alineación, goles, tarjetas,
     * cambios ni árbitros, no crea jugadores y no baja fotos.
     */
    public function penales(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $comp      = trim((string) $request->get('comp', ''));
        $ronda     = trim((string) $request->get('ronda', ''));
        $n         = max(1, min(50, (int) $request->get('n', 10)));
        $correr    = (string) $request->get('correr', '0') === '1';
        // Un partido puntual, aunque ya esté marcado como revisado. Sirve para
        // volver sobre uno que quedó con un aviso (por ejemplo un penal que no
        // se pudo cargar porque faltaba el jugador) sin desmarcar nada a mano.
        $unPartido = (int) $request->get('partido_id', 0);

        $filtros = array_filter(['tecnico_id' => $tecnicoId ?: null,
            'comp' => $comp ?: null, 'ronda' => $ronda ?: null]);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', $filtros)) . '">← Detalle de los partidos</a></p>'
            . '<h1>Penales fallados de lo ya cargado</h1>';

        if (!Schema::hasColumn('import_partidos', 'penales_revisado_at')) {
            return $this->pagina('Penales fallados', $cuerpo
                . '<div class="err-box">Falta la columna <code>import_partidos.penales_revisado_at</code>. '
                . 'Corré la migración <code>2026_08_31_120000_add_penales_revisado_to_import_partidos</code> '
                . '(o el SQL suelto <code>database/penales_revisado.sql</code>) y volvé. Sin esa columna no hay '
                . 'dónde anotar qué partidos ya se revisaron, y el pase se repetiría entero cada vez.</div>');
        }

        $cuerpo .= '<p class="sub">Hasta el 31/08/2026 el importador no leía los penales que <b>no fueron gol</b>, '
            . 'así que a los partidos cargados antes les puede faltar un <b>Errado</b>, un <b>Atajado</b> o un '
            . '<b>Atajó</b>. Cuáles lo tienen no se puede saber sin preguntarle a Transfermarkt: es '
            . '<b>1 llamada por partido</b>. Este pase pregunta y escribe <b>sólo</b> las filas de <code>penals</code> '
            . '—no toca alineación, goles, tarjetas ni cambios, no crea jugadores y no baja fotos— y deja el partido '
            . 'marcado como revisado, así la llamada se paga una sola vez.</p>';

        // Base: partidos con detalle cargado (tienen alineación) que todavía no
        // se revisaron. Los que no tienen detalle no van: cuando se lo bajes,
        // el importador ya trae los penales.
        $base = function () use ($tecnicoId, $comp, $ronda) {
            $q = DB::table('import_partidos')
                ->whereNotNull('partido_id')->whereNotNull('external_id')
                ->whereIn('estado', ['aplicado', 'duplicado'])
                ->whereNull('penales_revisado_at')
                ->whereIn('partido_id', function ($sub) {
                    $sub->from('alineacions')->select('partido_id')->distinct();
                });
            if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
            if ($comp !== '')  $q->where('competencia_external_id', $comp);
            if ($ronda !== '') $q->where('ronda', $ronda);
            return $q;
        };

        $pendientes = (clone $base())->distinct()->count('partido_id');
        $revisados  = DB::table('import_partidos')->whereNotNull('penales_revisado_at')
            ->distinct()->count('partido_id');

        $hechos = 0; $fallaron = 0; $llamadas = 0; $conPenales = 0; $filasNuevas = 0;
        $detalle = ''; $jugadoresNuevos = [];

        if ($correr && ($pendientes || $unPartido)) {
            // De a uno y en orden: el más nuevo primero, que es lo que más
            // mirás. Si algo se cae, los demás siguen.
            $lote = $unPartido
                ? DB::table('import_partidos')->where('partido_id', $unPartido)
                    ->whereNotNull('external_id')->orderByDesc('id')->limit(1)->get()
                : (clone $base())->orderByDesc('dia')->limit($n)->get();
            $imp  = new TmDetallePartido;
            $fechasLote = $this->mapaFechas($lote->pluck('partido_id')->all());

            foreach ($lote as $f) {
                $r = $imp->soloPenales((int) $f->partido_id, (string) $f->external_id);
                $llamadas += (int) $r['llamadas'];
                foreach ((isset($r['creados']) ? $r['creados'] : []) as $c) $jugadoresNuevos[] = $c;

                $etiqueta = e($f->club_nombre . ' vs ' . $f->rival_nombre) . ' <span class="id">'
                    . e(substr((string) $f->dia, 0, 10)) . ' · partido #' . (int) $f->partido_id . '</span>';
                $inc = $this->linkIncidencias(isset($fechasLote[(int) $f->partido_id])
                    ? $fechasLote[(int) $f->partido_id] : null);

                if (!$r['escrito']) {
                    $fallaron++;
                    $detalle .= '<div><span class="err">✘</span> ' . $etiqueta . ' — ' . e((string) $r['error']) . '</div>';
                } else {
                    $hechos++;
                    $cuantas = count($r['penals']);
                    if ($cuantas) {
                        $conPenales++;
                        $filasNuevas += $cuantas;
                        $quienes = [];
                        foreach ($r['penals'] as $p) {
                            $quienes[] = e($p['_nombre']) . ' <b>' . e($p['tipo']) . '</b>'
                                . ($p['minuto'] === null ? '' : ' (' . (int) $p['minuto'] . '\')');
                        }
                        $detalle .= '<div><span class="ok">✔</span> ' . $etiqueta . ' — '
                            . implode(' · ', $quienes)
                            . ' · <a href="' . e(route('penales.index', ['partidoId' => (int) $f->partido_id])) . '">penales</a>'
                            . ($inc !== '' ? ' · ' . $inc : '') . '</div>';
                    } else {
                        $detalle .= '<div class="sub">· ' . $etiqueta . ' — sin penales fallados</div>';
                    }
                }
                foreach ($r['avisos'] as $a) {
                    $detalle .= '<div class="sub" style="margin-left:18px">• ' . $this->avisoHtml($a) . '</div>';
                }
            }

            // Los recién revisados ya no están pendientes.
            $pendientes = (clone $base())->distinct()->count('partido_id');
        }

        $cuerpo .= '<div class="cards">'
            . $this->card($pendientes, 'Sin revisar', $pendientes ? 'warn' : 'ok')
            . $this->card($revisados + $hechos, 'Ya revisados', 'ok')
            . ($correr ? $this->card($hechos, 'Revisados ahora', 'ok') : '')
            . ($correr ? $this->card($conPenales, 'Con penales', $conPenales ? 'warn' : '') : '')
            . ($correr ? $this->card($filasNuevas, 'Filas creadas', $filasNuevas ? 'warn' : '') : '')
            . ($correr && $fallaron ? $this->card($fallaron, 'Con problema', 'err') : '')
            . ($correr && $jugadoresNuevos ? $this->card(count($jugadoresNuevos), 'Jugadores nuevos', 'warn') : '')
            . ($correr ? $this->card($llamadas, 'Llamadas a la API') : '')
            . '</div>';

        if (!empty($jugadoresNuevos)) {
            $cuerpo .= '<h2>Jugadores que no estaban en la base</h2>'
                . '<p class="sub">Eran el pateador o el arquero de un penal y no estaban mapeados, así que los creé '
                . 'para no perder el penal. Quedan con <b>revisar</b> puesto y sin foto: repasalos en '
                . '<a href="' . e(route('import_detalles.revisar')) . '">Jugadores por revisar</a>.</p>'
                . '<div class="diag">';
            foreach ($jugadoresNuevos as $j) $cuerpo .= '<div>• ' . e($j) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!$pendientes) {
            $cuerpo .= '<div class="ok-box">No queda ningún partido sin revisar'
                . (!empty($filtros) ? ' con este filtro' : '') . '.</div>';
        } else {
            $params = $filtros; $params['n'] = $n; $params['correr'] = 1;
            $cuerpo .= '<p class="acciones">'
                . '<a class="boton" href="' . e(route('import_detalles.penales', $params)) . '">'
                . 'Revisar los ' . min($n, $pendientes) . ' más nuevos</a>'
                . ' <span class="sub">' . min($n, $pendientes) . ' llamadas · quedan <b>' . $pendientes . '</b></span>';

            foreach ([25, 50] as $otro) {
                if ($otro === $n || $otro > $pendientes) continue;
                $p2 = $filtros; $p2['n'] = $otro; $p2['correr'] = 1;
                $cuerpo .= ' <a class="boton-sec" href="' . e(route('import_detalles.penales', $p2)) . '">'
                    . 'de a ' . $otro . '</a>';
            }
            $cuerpo .= '</p>'
                . '<p class="sub">Conviene filtrar por competencia y hacer primero los torneos que te importan: '
                . 'con <b>' . $pendientes . '</b> partidos pendientes, revisarlos todos son ' . $pendientes
                . ' llamadas. Un partido sin penales también queda marcado, así que nunca se pregunta dos veces.</p>';
        }

        if ($detalle !== '') {
            $cuerpo .= '<h2>Lo que hizo esta tanda</h2><div class="diag">' . $detalle . '</div>'
                . '<p class="sub">Si alguna línea de acá arriba tiene un aviso, arreglá lo que haga falta y volvé a '
                . 'pasar <b>ese</b> partido con <code>?partido_id=NNN&amp;correr=1</code>: eso lo rehace aunque ya '
                . 'esté marcado como revisado, y no toca a los demás.</p>';
        }

        $cuerpo .= $this->bloquePendientesPenales($base());

        return $this->pagina('Penales fallados', $cuerpo);
    }

    /** Los próximos que se van a revisar, para saber por dónde va la cosa. */
    private function bloquePendientesPenales($consulta)
    {
        $filas = (clone $consulta)->orderByDesc('dia')->limit(40)->get();
        if ($filas->isEmpty()) return '';

        $fechas = $this->mapaFechas($filas->pluck('partido_id')->all());

        $out = '<h2>Los próximos <span class="sub">(' . count($filas) . ' de los más nuevos)</span></h2>'
            . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Competencia</th><th>Partido</th>'
            . '<th>gameId</th><th></th></tr></thead><tbody>';

        foreach ($filas as $f) {
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);
            $out .= '<tr>'
                . '<td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                . '<td>' . e((string) $f->competencia_nombre) . '</td>'
                . '<td>' . e($f->club_nombre . ' vs ' . $f->rival_nombre)
                . ' <span class="id">#' . (int) $f->partido_id . '</span></td>'
                . '<td class="num"><span class="id">' . e((string) $f->external_id) . '</span></td>'
                . '<td><a href="' . e(route('penales.index', ['partidoId' => (int) $f->partido_id])) . '">Penales</a>'
                . ($inc !== '' ? ' · ' . $inc : '') . '</td>'
                . '</tr>';
        }

        return $out . '</tbody></table></div>';
    }

    // ═══════════════════════════ TIPOS DE GOL ═══════════════════════════

    /**
     * Relevamiento de tipos de gol sobre lo YA cargado.
     *
     * El caso que lo motivó: el gol olímpico. Transfermarkt lo manda como
     * `actionId` 211 ("direct corner") y hasta el 01/09/2026 el importador no
     * lo conocía, así que caía en «Jugada» con un aviso de "tipo de gol no
     * reconocido". El tipo ya existe, pero un arreglo nuevo no repara lo viejo:
     * los partidos cargados antes lo siguen teniendo mal.
     *
     * Cuáles son NO se puede saber desde la base —«Jugada» y «Olímpico» son la
     * misma fila, mismo jugador y mismo minuto—, así que hay que preguntarle a
     * Transfermarkt partido por partido: 1 llamada cada uno. Por eso el
     * resultado queda anotado en `import_partidos.tipos_gol_revisado_at` y la
     * lista se achica sola.
     *
     * De paso corrige cualquier otro tipo que esté distinto (una cabeza cargada
     * como jugada, un tiro libre como penal). Lo único que escribe es
     * `gols.tipo` de filas que ya existían: no agrega ni borra goles, no crea
     * jugadores y no toca nada más del partido.
     */
    public function tiposGol(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $comp      = trim((string) $request->get('comp', ''));
        $ronda     = trim((string) $request->get('ronda', ''));
        $n         = max(1, min(50, (int) $request->get('n', 10)));
        $correr    = (string) $request->get('correr', '0') === '1';
        // Pasada barata: sólo los partidos que tienen algún gol cargado como
        // «Jugada», que es donde cayeron los olímpicos. Deja afuera los que sólo
        // pueden tener otro tipo de error, más raro.
        $soloJugada = (string) $request->get('solo_jugada', '0') === '1';
        // Un partido puntual, aunque ya esté marcado. Para volver sobre uno que
        // quedó con un aviso, sin desmarcar nada a mano.
        $unPartido = (int) $request->get('partido_id', 0);

        $filtros = array_filter(['tecnico_id' => $tecnicoId ?: null,
            'comp' => $comp ?: null, 'ronda' => $ronda ?: null]);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', $filtros)) . '">← Detalle de los partidos</a></p>'
            . '<h1>Tipos de gol de lo ya cargado</h1>';

        if (!Schema::hasColumn('import_partidos', 'tipos_gol_revisado_at')) {
            return $this->pagina('Tipos de gol', $cuerpo
                . '<div class="err-box">Falta la columna <code>import_partidos.tipos_gol_revisado_at</code>. '
                . 'Corré la migración <code>2026_09_01_100000_add_gol_olimpico</code> '
                . '(o el SQL suelto <code>database/sql/gol_olimpico.sql</code>) y volvé. Esa migración es la que '
                . 'agrega el tipo <b>Olímpico</b> al enum de <code>gols</code>: sin ella no hay dónde guardar la '
                . 'corrección, y sin la columna no hay dónde anotar qué partidos ya se preguntaron.</div>');
        }

        $cuerpo .= '<p class="sub">Hasta el 01/09/2026 el <b>gol olímpico</b> no existía como tipo: Transfermarkt lo '
            . 'manda como <code>direct corner</code> (actionId 211) y el importador lo cargaba como <b>Jugada</b>. '
            . 'Cuáles son no se puede saber desde la base —«Jugada» y «Olímpico» son la misma fila—, así que hay que '
            . 'preguntarlo: es <b>1 llamada por partido</b>. Este pase pregunta y escribe <b>sólo</b> el '
            . '<code>tipo</code> de goles que ya están cargados —no agrega ni borra goles, no toca la alineación, '
            . 'los cambios ni las tarjetas, y no crea jugadores— y deja el partido marcado, así la llamada se paga '
            . 'una sola vez.</p>'
            . '<p class="sub">Ya que se paga la llamada, se controlan <b>todos</b> los tipos, no sólo el olímpico: '
            . 'cualquier gol que la base tenga distinto de lo que dice Transfermarkt se corrige, y al final de la '
            . 'tanda queda el cruce completo —lo que decía la base contra lo que dice TM— para ver si hay algún '
            . 'error sistemático.</p>'
            . '<p class="sub">Un gol que la base tiene como <b>En Contra</b> y Transfermarkt no (o al revés) '
            . '<b>no se toca</b>: eso cambia de equipo el gol y, si el apareo estuviera mal, no se notaría. Esos '
            . 'casos salen como aviso para mirarlos a mano. Los partidos sin ningún gol cargado no entran en la '
            . 'lista: no hay nada que corregir y no vale la pena gastarles una llamada.</p>';

        $base = function () use ($tecnicoId, $comp, $ronda, $soloJugada) {
            $q = DB::table('import_partidos')
                ->whereNotNull('partido_id')->whereNotNull('external_id')
                ->whereIn('estado', ['aplicado', 'duplicado'])
                ->whereNull('tipos_gol_revisado_at')
                ->whereIn('partido_id', function ($sub) {
                    $sub->from('gols')->select('partido_id')->distinct();
                });
            if ($soloJugada) {
                $q->whereIn('partido_id', function ($sub) {
                    $sub->from('gols')->select('partido_id')->where('tipo', 'Jugada')->distinct();
                });
            }
            if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
            if ($comp !== '')  $q->where('competencia_external_id', $comp);
            if ($ronda !== '') $q->where('ronda', $ronda);
            return $q;
        };

        // ── Volver a preguntar lo ya revisado ─────────────────────────────
        // Un arreglo nuevo no repara lo viejo. Cuando el mapeo de tipos aprende
        // algo —el 01/09/2026 aprendió que "penalty rebound" es Jugada y no
        // Penal—, los partidos que ya se revisaron siguen con lo que se les
        // escribió antes. Esto les saca la marca para que vuelvan a la cola.
        // No gasta ni una llamada por sí solo: sólo repone la lista.
        $desmarcar = trim((string) $request->get('desmarcar', ''));

        $revisadosQ = function ($soloPenal = false) use ($tecnicoId, $comp, $ronda) {
            $q = DB::table('import_partidos')
                ->whereNotNull('partido_id')->whereNotNull('external_id')
                ->whereIn('estado', ['aplicado', 'duplicado'])
                ->whereNotNull('tipos_gol_revisado_at');
            if ($soloPenal) {
                $q->whereIn('partido_id', function ($s) {
                    $s->from('gols')->select('partido_id')->where('tipo', 'Penal')->distinct();
                });
            }
            if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
            if ($comp !== '')  $q->where('competencia_external_id', $comp);
            if ($ronda !== '') $q->where('ronda', $ronda);
            return $q;
        };

        $desmarcados = 0;
        if ($desmarcar === 'penal' || $desmarcar === 'todos') {
            $desmarcados = (clone $revisadosQ($desmarcar === 'penal'))->distinct()->count('partido_id');
            $revisadosQ($desmarcar === 'penal')->update(['tipos_gol_revisado_at' => null]);
        }

        $pendientes = (clone $base())->distinct()->count('partido_id');
        $revisados  = DB::table('import_partidos')->whereNotNull('tipos_gol_revisado_at')
            ->distinct()->count('partido_id');

        $hechos = 0; $fallaron = 0; $llamadas = 0; $corregidos = 0; $conCambios = 0; $olimpicos = 0;
        $detalle = '';
        // Control de TODOS los tipos de la tanda: qué decía la base y qué dice
        // Transfermarkt, gol por gol. Se acumula acá y se muestra abajo.
        $matriz = []; $sueltosTm = 0; $sueltosBase = 0; $sinDetalle = 0;

        if ($correr && ($pendientes || $unPartido)) {
            $lote = $unPartido
                ? DB::table('import_partidos')->where('partido_id', $unPartido)
                    ->whereNotNull('external_id')->orderByDesc('id')->limit(1)->get()
                : (clone $base())->orderByDesc('dia')->limit($n)->get();
            $imp = new TmDetallePartido;
            $fechasLote = $this->mapaFechas($lote->pluck('partido_id')->all());

            // ¿Cuáles ya tienen alineación? Decide si el link de abajo tiene que
            // ir con `forzar` (rehacer) o alcanza con bajar. Una sola consulta.
            $conAlineacion = [];
            foreach (DB::table('alineacions')->whereIn('partido_id', $lote->pluck('partido_id')->all())
                         ->select('partido_id')->distinct()->get() as $a) {
                $conAlineacion[(int) $a->partido_id] = true;
            }

            foreach ($lote as $f) {
                $r = $imp->soloTiposDeGol((int) $f->partido_id, (string) $f->external_id);
                $llamadas += (int) $r['llamadas'];
                $sinDetalleTm = false;

                $etiqueta = e($f->club_nombre . ' vs ' . $f->rival_nombre) . ' <span class="id">'
                    . e(substr((string) $f->dia, 0, 10)) . ' · partido #' . (int) $f->partido_id . '</span>';
                $inc = $this->linkIncidencias(isset($fechasLote[(int) $f->partido_id])
                    ? $fechasLote[(int) $f->partido_id] : null);

                if (!$r['escrito']) {
                    $fallaron++;
                    $detalle .= '<div><span class="err">✘</span> ' . $etiqueta . ' — ' . e((string) $r['error']) . '</div>';
                } else {
                    $hechos++;
                    $olimpicos += (int) $r['olimpicos'];
                    foreach ((isset($r['matriz']) ? $r['matriz'] : []) as $clave => $cuantos) {
                        $matriz[$clave] = (isset($matriz[$clave]) ? $matriz[$clave] : 0) + $cuantos;
                    }
                    $sueltosTm   += (int) (isset($r['sueltos_tm']) ? $r['sueltos_tm'] : 0);
                    $sueltosBase += (int) (isset($r['sueltos_base']) ? $r['sueltos_base'] : 0);

                    // Ni un gol apareó, y de los dos lados había goles: eso no es
                    // un problema de tipos, es que a este partido NUNCA se le bajó
                    // el detalle. Los goles son los que cargó el usuario y los
                    // goleadores de TM no están mapeados — el mapeo lo crea la
                    // bajada del detalle, no el emparejamiento del fixture.
                    $sinDetalleTm = ((int) (isset($r['apareados']) ? $r['apareados'] : 0)) === 0
                        && (int) $r['goles_tm'] > 0 && (int) $r['goles_base'] > 0;

                    if (!empty($r['cambios'])) {
                        $conCambios++;
                        $corregidos += count($r['cambios']);
                        $qué = [];
                        foreach ($r['cambios'] as $c) {
                            $qué[] = e($c['nombre'])
                                . ($c['minuto'] === null ? '' : ' ' . $c['minuto'] . '\'')
                                . ' <span class="sub">' . e($c['de']) . ' →</span> <b>' . e($c['a']) . '</b>'
                                . ($c['fuente'] !== '' ? ' <span class="id">(' . e($c['fuente']) . ')</span>' : '');
                        }
                        $detalle .= '<div><span class="ok">✔</span> ' . $etiqueta . ' — '
                            . implode(' · ', $qué)
                            . ($inc !== '' ? ' · ' . $inc : '') . '</div>';
                    } elseif ($sinDetalleTm) {
                        $detalle .= '<div class="sub"><span class="warn">·</span> ' . $etiqueta
                            . ' — no pude comparar ninguno de sus ' . (int) $r['goles_base'] . ' gol(es)</div>';
                    } else {
                        $detalle .= '<div class="sub">· ' . $etiqueta . ' — los ' . (int) $r['goles_base']
                            . ' gol(es) ya estaban bien'
                            . ((int) $r['olimpicos'] ? ' (' . (int) $r['olimpicos'] . ' olímpico/s)' : '') . '</div>';
                    }
                }
                // Con el diagnóstico de arriba, una línea sirve más que un aviso
                // por cada gol suelto.
                if ($sinDetalleTm) {
                    $sinDetalle++;
                    $rehacer = isset($conAlineacion[(int) $f->partido_id]);
                    $linkBajar = route('import_detalles.bajar', array_filter([
                        'partido_id' => (int) $f->partido_id, 'forzar' => $rehacer ? 1 : null]));
                    $sinMapear = (int) (isset($r['sueltos_sin_mapear']) ? $r['sueltos_sin_mapear'] : 0);
                    $detalle .= '<div class="sub" style="margin-left:18px">• <b>A este partido nunca se le bajó el '
                        . 'detalle de Transfermarkt.</b> Ninguno de sus ' . (int) $r['goles_base'] . ' gol(es) apareó '
                        . 'con los ' . (int) $r['goles_tm'] . ' que tiene TM'
                        . ($sinMapear ? ', y ' . $sinMapear . ' de los goleadores de TM ni siquiera están mapeados '
                            . '(el mapeo lo crea la bajada del detalle)' : '')
                        . ': los goles son los que cargaste vos. Corregirles el tipo acá no alcanza — '
                        . '<a href="' . e($linkBajar) . '"><b>' . ($rehacer ? 'rehacele' : 'bajale') . ' el detalle</b></a>'
                        . ', que por la misma llamada deja bien la alineación, los cambios, las tarjetas, los árbitros '
                        . 'y los tipos de gol, y encima mapea a los jugadores para siempre.</div>';
                } else {
                    foreach ((isset($r['avisos_apareo']) ? $r['avisos_apareo'] : []) as $a) {
                        $detalle .= '<div class="sub" style="margin-left:18px">• ' . $this->avisoHtml($a) . '</div>';
                    }
                }

                foreach ($r['avisos'] as $a) {
                    $detalle .= '<div class="sub" style="margin-left:18px">• ' . $this->avisoHtml($a) . '</div>';
                }
            }

            $pendientes = (clone $base())->distinct()->count('partido_id');
        }

        // ── Segunda etapa: los partidos que ni siquiera tienen gameId ──────
        // Sin gameId no hay a quién preguntarle el tipo de gol. `TmBuscarGameId`
        // lo encuentra con lo que ya tenemos cargado (el fixture de los dos
        // clubes y, si hace falta, los partidos de los DTs) y lo deja anotado:
        // a partir de ahí el partido entra solo en la lista de arriba.
        $buscarIds = (string) $request->get('buscar_ids', '0') === '1';
        $nIds      = max(1, min(50, (int) $request->get('n_ids', 10)));

        // Va con LEFT JOIN y no con NOT EXISTS a propósito: `import_partidos`
        // no tiene índice por `partido_id` (ver `Controles::agregarTransfermarkt`),
        // y un NOT EXISTS correlacionado sería un scan de la tabla entera por
        // cada partido. La migración 2026_09_01_110000 agrega ese índice; sin
        // ella esto igual anda, pero lento.
        $sinGameId = function () {
            return DB::table('partidos')
                ->whereIn('partidos.id', function ($s) {
                    $s->from('gols')->select('partido_id')->distinct();
                })
                // No tiene ninguna fila de staging con gameId...
                ->leftJoin('import_partidos as ipg', function ($j) {
                    $j->on('ipg.partido_id', '=', 'partidos.id')->whereNotNull('ipg.external_id');
                })
                // ...ni una marca de "ya lo busqué y no apareció".
                ->leftJoin('import_partidos as ipx', function ($j) {
                    $j->on('ipx.partido_id', '=', 'partidos.id')
                        ->whereNull('ipx.external_id')
                        ->where('ipx.estado', 'excluido')
                        ->where('ipx.motivo', 'like', 'sin gameId%');
                })
                ->whereNull('ipg.id')->whereNull('ipx.id');
        };

        $porBuscar   = (clone $sinGameId())->count();
        $yaBuscados  = DB::table('import_partidos')->whereNotNull('partido_id')
            ->whereNull('external_id')->where('estado', 'excluido')
            ->where('motivo', 'like', 'sin gameId%')->distinct()->count('partido_id');

        $idsHallados = 0; $idsFallados = 0; $idsLlamadas = 0; $detalleIds = '';

        if ($buscarIds && $porBuscar) {
            $lote = (clone $sinGameId())->orderByDesc('partidos.dia')
                ->limit($nIds)->get(['partidos.id', 'partidos.dia']);
            $buscador = new TmBuscarGameId;

            foreach ($lote as $p) {
                $r = $buscador->buscar((int) $p->id);
                $idsLlamadas += (int) (isset($r['llamadas']) ? $r['llamadas'] : 0);
                $resumen = $this->resumenPartido((int) $p->id);

                if (!empty($r['game_id'])) {
                    $buscador->anotar((int) $p->id, $r['game_id'], 'gameId encontrado desde el control de tipos de gol');
                    $idsHallados++;
                    $detalleIds .= '<div><span class="ok">✔</span> ' . $resumen . ' — gameId <b>'
                        . e((string) $r['game_id']) . '</b>'
                        . (!empty($r['como']) ? ' <span class="id">(' . e((string) $r['como']) . ')</span>' : '')
                        . '</div>';
                } else {
                    $idsFallados++;
                    $this->marcarSinGameId((int) $p->id, count(isset($r['candidatos']) ? $r['candidatos'] : []));
                    $cuantos = count(isset($r['candidatos']) ? $r['candidatos'] : []);
                    $detalleIds .= '<div><span class="warn">?</span> ' . $resumen . ' — '
                        . ($cuantos
                            ? 'hay ' . $cuantos . ' partido(s) posible(s) y no elijo yo'
                            : 'no lo encontré en Transfermarkt')
                        . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $p->id])) . '">'
                        . ($cuantos ? 'elegir a mano' : 'ver por qué') . '</a></div>';
                    foreach ((isset($r['avisos']) ? $r['avisos'] : []) as $a) {
                        $detalleIds .= '<div class="sub" style="margin-left:18px">• ' . e($a) . '</div>';
                    }
                }
            }

            // Los que aparecieron ya son parte de la lista de arriba.
            $porBuscar  = (clone $sinGameId())->count();
            $pendientes = (clone $base())->distinct()->count('partido_id');
            $yaBuscados = DB::table('import_partidos')->whereNotNull('partido_id')
                ->whereNull('external_id')->where('estado', 'excluido')
                ->where('motivo', 'like', 'sin gameId%')->distinct()->count('partido_id');
        }

        $cuerpo .= '<div class="cards">'
            . $this->card($pendientes, 'Sin revisar', $pendientes ? 'warn' : 'ok')
            . $this->card($revisados + $hechos, 'Ya revisados', 'ok')
            . ($correr ? $this->card($hechos, 'Revisados ahora', 'ok') : '')
            . ($correr ? $this->card($corregidos, 'Goles corregidos', $corregidos ? 'warn' : '') : '')
            . ($correr ? $this->card($olimpicos, 'Olímpicos', $olimpicos ? 'warn' : '') : '')
            . ($correr && $sinDetalle ? $this->card($sinDetalle, 'Sin detalle de TM', 'warn') : '')
            . ($correr && $fallaron ? $this->card($fallaron, 'Con problema', 'err') : '')
            . ($correr ? $this->card($llamadas, 'Llamadas a la API') : '')
            . ($buscarIds ? $this->card($idsHallados, 'gameId encontrados', $idsHallados ? 'ok' : '') : '')
            . ($buscarIds ? $this->card($idsFallados, 'Sin gameId', $idsFallados ? 'warn' : '') : '')
            . ($buscarIds ? $this->card($idsLlamadas, 'Llamadas de la búsqueda') : '')
            . '</div>';

        // ── De qué universo estamos hablando ──────────────────────────────
        // Dos cosas que la pantalla no decía y hacían que «sin revisar» pareciera
        // un número demasiado chico: qué filtros están puestos (el de la URL se
        // arrastra y no se ve), y que el control sólo alcanza a los partidos que
        // tienen gameId de Transfermarkt. A un partido cargado a mano no hay a
        // quién preguntarle el tipo de gol.
        $enLaBase = function ($sub) {
            $sub->from('gols')->select('partido_id')->distinct();
        };

        $pendientesSinFiltro = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('external_id')
            ->whereIn('estado', ['aplicado', 'duplicado'])
            ->whereNull('tipos_gol_revisado_at')
            ->whereIn('partido_id', $enLaBase)
            ->distinct()->count('partido_id');

        $conGoles = DB::table('gols')->distinct()->count('partido_id');
        $alcanzables = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('external_id')
            ->whereIn('estado', ['aplicado', 'duplicado'])
            ->whereIn('partido_id', $enLaBase)
            ->distinct()->count('partido_id');
        $fueraDeAlcance = max(0, $conGoles - $alcanzables);

        $puestos = [];
        if ($tecnicoId) {
            $nombreDt = (string) DB::table('tecnicos')
                ->join('personas', 'personas.id', '=', 'tecnicos.persona_id')
                ->where('tecnicos.id', $tecnicoId)->value('personas.name');
            $puestos[] = 'el DT <b>' . e($nombreDt !== '' ? $nombreDt : '#' . $tecnicoId) . '</b>';
        }
        if ($comp !== '')  $puestos[] = 'la competencia <b>' . e($comp) . '</b>';
        if ($ronda !== '') $puestos[] = 'la fecha <b>' . e($ronda) . '</b>';
        if ($soloJugada)   $puestos[] = 'sólo los partidos con algún gol de <b>Jugada</b>';

        if (!empty($puestos)) {
            $cuerpo .= '<div class="diag"><b>Ojo: esos ' . $pendientes . ' son con filtro.</b> '
                . 'Estás mirando ' . implode(' · ', $puestos) . '.<br>'
                . 'Sin ningún filtro quedan <b>' . $pendientesSinFiltro . '</b> partidos sin revisar. '
                . '<a href="' . e(route('import_detalles.tipos_gol')) . '">Ver todos, sin filtros</a></div>';
        }

        $cuerpo .= '<p class="sub"><b>Hasta dónde llega esto:</b> en la base hay <b>' . $conGoles
            . '</b> partidos con goles cargados, y <b>' . $alcanzables . '</b> de ellos tienen gameId de '
            . 'Transfermarkt. El gameId es lo único que hace falta: sin él no hay a quién preguntarle de qué fue '
            . 'cada gol. Los otros <b>' . $fueraDeAlcance . '</b> se cargaron a mano o por planilla y nunca pasaron '
            . 'por el importador — pero el gameId se puede <b>buscar</b>, y eso los mete en el relevamiento.</p>';

        // ── El bloque de la búsqueda de gameId ────────────────────────────
        $cuerpo .= '<h2>Partidos sin gameId <span class="sub">(' . $porBuscar . ' por buscar)</span></h2>'
            . '<p class="sub">La búsqueda no adivina: cruza el <b>fixture de los dos clubes</b> de Transfermarkt '
            . '(y, si hace falta, los partidos de los DTs) con la fecha y los equipos que ya tenés cargados. '
            . 'Si en la ventana de fechas queda más de un candidato <b>no elige ninguno</b> y te los ofrece para '
            . 'que elijas vos: un gameId equivocado escribiría el detalle de otro partido.</p>'
            . '<p class="sub">Cuesta <b>1 a 3 llamadas por partido</b>, y bastante menos cuando son del mismo club: '
            . 'el fixture se reusa 10 minutos. El que aparece queda anotado para siempre y pasa solo a la lista de '
            . 'arriba (y de paso le sirve al resto del importador: detalle, penales, resultados). El que no aparece '
            . 'queda marcado para <b>no volver a pagarlo</b> en cada tanda.</p>';

        if ($porBuscar) {
            $cuerpo .= '<p class="acciones">'
                . '<a class="boton" href="' . e(route('import_detalles.tipos_gol',
                    ['buscar_ids' => 1, 'n_ids' => $nIds])) . '">Buscar el gameId de ' . min($nIds, $porBuscar)
                . ' partidos</a>';
            foreach ([25, 50] as $otro) {
                if ($otro === $nIds || $otro > $porBuscar) continue;
                $cuerpo .= ' <a class="boton-sec" href="' . e(route('import_detalles.tipos_gol',
                    ['buscar_ids' => 1, 'n_ids' => $otro])) . '">de a ' . $otro . '</a>';
            }
            $cuerpo .= ' <span class="sub">van del más nuevo al más viejo</span></p>';
        } else {
            $cuerpo .= '<div class="ok-box">No queda ningún partido con goles al que le falte el gameId '
                . '(sin contar los que ya se buscaron sin suerte).</div>';
        }

        if ($yaBuscados) {
            $cuerpo .= '<p class="sub"><b>' . $yaBuscados . '</b> partido(s) ya se buscaron y no salieron: quedaron '
                . 'marcados y no se vuelven a intentar solos. Casi siempre falta que los dos equipos estén atados a '
                . 'su club de Transfermarkt en <code>equipo_tm</code>, o que el DT tenga cargada su URL. Con eso, la '
                . 'búsqueda los encuentra: para reintentar uno, entrá por '
                . '<code>import-detalles/ver?partido_id=NNN</code>.</p>';
        }

        if ($detalleIds !== '') {
            $cuerpo .= '<h3>Lo que encontró la búsqueda</h3><div class="diag">' . $detalleIds . '</div>';
        }

        if (!$pendientes) {
            $cuerpo .= '<div class="ok-box">No queda ningún partido sin revisar'
                . (!empty($filtros) ? ' con este filtro' : '') . ($soloJugada ? ' entre los que tienen goles de Jugada' : '')
                . '.</div>';
        } else {
            $params = $filtros; $params['n'] = $n; $params['correr'] = 1;
            if ($soloJugada) $params['solo_jugada'] = 1;
            $cuerpo .= '<p class="acciones">'
                . '<a class="boton" href="' . e(route('import_detalles.tipos_gol', $params)) . '">'
                . 'Revisar los ' . min($n, $pendientes) . ' más nuevos</a>'
                . ' <span class="sub">' . min($n, $pendientes) . ' llamadas · quedan <b>' . $pendientes . '</b></span>';

            foreach ([25, 50] as $otro) {
                if ($otro === $n || $otro > $pendientes) continue;
                $p2 = $params; $p2['n'] = $otro;
                $cuerpo .= ' <a class="boton-sec" href="' . e(route('import_detalles.tipos_gol', $p2)) . '">'
                    . 'de a ' . $otro . '</a>';
            }
            $cuerpo .= '</p>';

            $p3 = $filtros; $p3['n'] = $n;
            if (!$soloJugada) $p3['solo_jugada'] = 1;
            $cuerpo .= '<p class="acciones"><a class="boton-sec" href="' . e(route('import_detalles.tipos_gol', $p3)) . '">'
                . ($soloJugada ? 'Mirar todos los partidos con goles' : 'Sólo los que tienen goles de «Jugada»')
                . '</a> <span class="sub">'
                . ($soloJugada
                    ? 'ahora estás viendo sólo los que pueden tener un olímpico escondido'
                    : 'es la pasada barata: el olímpico viejo siempre quedó cargado como Jugada')
                . '</span></p>'
                . '<p class="sub">Conviene filtrar por competencia y hacer primero los torneos que te importan. '
                . 'Un partido que estaba todo bien también queda marcado, así que nunca se pregunta dos veces.</p>';
        }

        // ── Volver a preguntar: los botones ───────────────────────────────
        if ($desmarcados) {
            $cuerpo .= '<div class="ok-box"><b>' . $desmarcados . ' partido(s) volvieron a la cola.</b> '
                . 'Se les borró la marca de revisado, así que la próxima tanda los vuelve a preguntar con el '
                . 'mapeo de tipos como está hoy. Eso son ' . $desmarcados . ' llamadas: hacelas cuando quieras.</div>';
        }

        $reRevisables    = (clone $revisadosQ(false))->distinct()->count('partido_id');
        $reRevisablesPen = (clone $revisadosQ(true))->distinct()->count('partido_id');

        if ($reRevisables) {
            $cuerpo .= '<h2>Volver a preguntar lo ya revisado</h2>'
                . '<p class="sub">Un arreglo nuevo no repara lo viejo: si el mapeo aprendió algo después de que '
                . 'pasaste un partido, ese partido quedó con lo de antes. El <b>01/09/2026</b> aprendió que '
                . '<code>penalty rebound</code> (rebote de penal) es <b>Jugada</b> y no Penal — antes la regla '
                . 'del penal se lo llevaba puesto porque el texto dice "penalty". Sacarles la marca no gasta '
                . 'nada; volver a preguntarlos, 1 llamada cada uno.</p>'
                . '<p class="acciones">'
                . ($reRevisablesPen
                    ? '<a class="boton" href="' . e(route('import_detalles.tipos_gol',
                        $filtros + ['desmarcar' => 'penal'])) . '">Los que tienen algún gol de Penal ('
                        . $reRevisablesPen . ')</a> <span class="sub">es donde puede haber quedado mal el rebote</span>'
                    : '')
                . ' <a class="boton-sec" href="' . e(route('import_detalles.tipos_gol',
                    $filtros + ['desmarcar' => 'todos'])) . '">Todos los revisados (' . $reRevisables . ')</a>'
                . '</p>'
                . (!empty($filtros) ? '<p class="sub">Ojo: respeta los filtros que tenés puestos.</p>' : '');
        }

        $cuerpo .= $this->bloqueMatrizTipos($matriz, $sueltosTm, $sueltosBase);

        if ($detalle !== '') {
            $cuerpo .= '<h2>Lo que hizo esta tanda</h2><div class="diag">' . $detalle . '</div>'
                . '<p class="sub">Si alguna línea tiene un aviso, arreglá lo que haga falta y volvé a pasar '
                . '<b>ese</b> partido con <code>?partido_id=NNN&amp;correr=1</code>: eso lo rehace aunque ya esté '
                . 'marcado, y no toca a los demás.</p>';
        }

        $cuerpo .= $this->bloqueOlimpicos();
        $cuerpo .= $this->bloquePendientesTiposGol($base());

        return $this->pagina('Tipos de gol', $cuerpo);
    }

    /**
     * Deja anotado que a este partido ya se le buscó el gameId y no salió.
     *
     * Sin esta marca, cada tanda volvería a pagar la búsqueda de los mismos
     * partidos irresolubles. La fila va con `external_id` en NULL —así queda
     * afuera de todas las consultas que piden gameId, incluida la de esta misma
     * pantalla— y con `tecnico_id` en NULL, igual que las que escribe
     * `TmBuscarGameId::anotar()`: el tablero de cobertura agrupa por DT, así que
     * no la muestra.
     *
     * Si más adelante el gameId aparece (elegido a mano entre los candidatos),
     * `anotar()` inserta SU propia fila con el gameId y el partido vuelve solo a
     * la lista.
     */
    private function marcarSinGameId($partidoId, $candidatos = 0)
    {
        try {
            $partido = DB::table('partidos')->where('id', (int) $partidoId)->first();
            if (!$partido) return;

            $ya = DB::table('import_partidos')->where('partido_id', (int) $partidoId)
                ->whereNull('external_id')->where('estado', 'excluido')
                ->where('motivo', 'like', 'sin gameId%')->first();
            if ($ya) return;

            DB::table('import_partidos')->insert([
                'fuente'       => 'transfermarkt',
                'external_id'  => null,
                'tecnico_id'   => null,
                'partido_id'   => (int) $partidoId,
                'equipo_id'    => $partido->equipol_id,
                'rival_id'     => $partido->equipov_id,
                'club_nombre'  => $this->nombreEquipo($partido->equipol_id),
                'rival_nombre' => $this->nombreEquipo($partido->equipov_id),
                'local'        => 1,
                'dia'          => $partido->dia,
                'estado'       => 'excluido',
                'motivo'       => $candidatos
                    ? 'sin gameId: quedaron ' . (int) $candidatos . ' candidatos y hay que elegir a mano'
                    : 'sin gameId: no lo encontré con el fixture de los clubes ni con los partidos de los DTs',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } catch (\Exception $e) {
            \Log::error('marcarSinGameId partido ' . (int) $partidoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Control de TODOS los tipos de gol de la tanda, no sólo del olímpico.
     *
     * Filas = lo que decía la base, columnas = lo que dice Transfermarkt. La
     * diagonal es lo que ya estaba bien; todo lo de afuera es una diferencia.
     * Sirve para ver de un vistazo si hay un error sistemático —cabezas
     * cargadas como jugada, tiros libres como penal— y no sólo el gol que
     * fuimos a buscar.
     *
     * Las diferencias que tocan «En Contra» se pintan distinto: esas NO se
     * escriben (cambian de equipo el gol), quedan para revisar a mano.
     */
    private function bloqueMatrizTipos(array $matriz, $sueltosTm, $sueltosBase)
    {
        if (empty($matriz) && !$sueltosTm && !$sueltosBase) return '';

        // SIEMPRE los seis tipos, aparezcan o no en esta tanda. Mostrar sólo los
        // que salieron hacía que «Olímpico» —que es raro y es justo el que se
        // fue a buscar— desapareciera de la tabla, y no se distinguía «no hubo
        // ninguno» de «esto no lo estoy mirando». Con la grilla fija, un cero es
        // un dato. Si algún día TM trae un tipo que no conocemos, se agrega al
        // final en lugar de tapar nada.
        $tipos = ['Jugada', 'Cabeza', 'Penal', 'Tiro Libre', 'Olímpico', 'En Contra'];
        foreach ($matriz as $clave => $n) {
            foreach (explode('||', $clave) as $t) {
                if ($t !== '' && !in_array($t, $tipos, true)) $tipos[] = $t;
            }
        }

        $valor = function ($de, $a) use ($matriz) {
            $k = $de . '||' . $a;
            return isset($matriz[$k]) ? (int) $matriz[$k] : 0;
        };

        $iguales = 0; $distintos = 0; $sinTocar = 0;
        foreach ($matriz as $clave => $n) {
            $partes = explode('||', $clave);
            $de = $partes[0]; $a = isset($partes[1]) ? $partes[1] : '';
            if ($de === $a) { $iguales += (int) $n; continue; }
            $distintos += (int) $n;
            if ($de === 'En Contra' || $a === 'En Contra') $sinTocar += (int) $n;
        }
        $total = $iguales + $distintos;

        $out = '<h2>Control de tipos de esta tanda</h2>'
            . '<p class="sub">Los <b>' . $total . '</b> goles que se pudieron aparear, cruzados: '
            . 'la fila es lo que tenía la base y la columna lo que dice Transfermarkt. '
            . '<b>' . $iguales . '</b> coincidían y <b>' . $distintos . '</b> no'
            . ($sinTocar ? ', de los cuales ' . $sinTocar . ' toca(n) «En Contra» y quedaron sin corregir '
                . '(cambia de equipo el gol: van a mano)' : '')
            . '. Lo de la diagonal ya estaba bien; lo de afuera es lo que este pase corrigió. '
            . 'Los seis tipos están siempre, salieran o no en esta tanda: un cero también dice algo.</p>';

        $out .= '<div class="scroll"><table><thead><tr>'
            . '<th>Base ╲ Transfermarkt</th>';
        foreach ($tipos as $t) $out .= '<th>' . e($t) . '</th>';
        $out .= '<th>Total</th></tr></thead><tbody>';

        foreach ($tipos as $de) {
            $fila = ''; $totalFila = 0;
            foreach ($tipos as $a) {
                $n = $valor($de, $a);
                $totalFila += $n;
                if ($n === 0) {
                    $fila .= '<td class="num gris">·</td>';
                } elseif ($de === $a) {
                    $fila .= '<td class="num gris">' . $n . '</td>';
                } elseif ($de === 'En Contra' || $a === 'En Contra') {
                    $fila .= '<td class="num err"><b>' . $n . '</b></td>';
                } else {
                    $fila .= '<td class="num warn"><b>' . $n . '</b></td>';
                }
            }
            // La fila vacía se muestra igual, en gris: «no hubo ninguno» es
            // información, y la tabla queda igual de tanda a tanda.
            $out .= '<tr' . ($totalFila === 0 ? ' class="gris"' : '') . '><th>' . e($de) . '</th>' . $fila
                . '<td class="num">' . ($totalFila ?: '·') . '</td></tr>';
        }

        $out .= '<tr><th>Total</th>';
        foreach ($tipos as $a) {
            $n = 0;
            foreach ($tipos as $de) $n += $valor($de, $a);
            $out .= '<td class="num">' . $n . '</td>';
        }
        $out .= '<td class="num"><b>' . $total . '</b></td></tr>';
        $out .= '</tbody></table></div>';

        $out .= '<p class="sub">Referencia: <span class="gris">gris</span> = coincidían · '
            . '<span class="warn"><b>ámbar</b></span> = se corrigió · '
            . '<span class="err"><b>rojo</b></span> = toca «En Contra», no se tocó.'
            . (($sueltosTm || $sueltosBase)
                ? ' Además quedaron afuera del cruce <b>' . (int) $sueltosTm . '</b> gol(es) que Transfermarkt '
                . 'tiene y no encontré cargados, y <b>' . (int) $sueltosBase . '</b> cargado(s) que Transfermarkt '
                . 'no tiene: los detalla el listado de abajo, uno por uno.'
                : '')
            . '</p>';

        return $out;
    }

    /**
     * Los olímpicos que hay en la base hoy. Es el resultado del relevamiento:
     * la lista que antes no se podía hacer porque el tipo no existía.
     */
    private function bloqueOlimpicos()
    {
        $filas = DB::table('gols')
            ->join('jugadors', 'gols.jugador_id', '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->join('partidos', 'gols.partido_id', '=', 'partidos.id')
            ->leftJoin('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->leftJoin('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->where('gols.tipo', 'Olímpico')
            ->select('gols.id', 'gols.minuto', 'gols.partido_id', 'partidos.dia', 'partidos.fecha_id',
                'personas.name as jugador', 'jugadors.id as jugador_id',
                'el.nombre as local', 'ev.nombre as visitante')
            ->orderByDesc('partidos.dia')->limit(100)->get();

        $total = DB::table('gols')->where('tipo', 'Olímpico')->count();

        if ($filas->isEmpty()) {
            return '<h2>Olímpicos cargados</h2><div class="diag">Todavía no hay ninguno. '
                . 'Aparecen acá a medida que el relevamiento los encuentra.</div>';
        }

        $out = '<h2>Olímpicos cargados <span class="sub">(' . $total . ')</span></h2>'
            . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Partido</th><th>Jugador</th>'
            . '<th>Min.</th><th></th></tr></thead><tbody>';

        foreach ($filas as $g) {
            $inc = $this->linkIncidencias($g->fecha_id);
            $out .= '<tr>'
                . '<td class="num">' . e(substr((string) $g->dia, 0, 10)) . '</td>'
                . '<td>' . e($g->local . ' vs ' . $g->visitante)
                . ' <span class="id">#' . (int) $g->partido_id . '</span></td>'
                . '<td><a href="' . e(route('jugadores.ver', ['jugadorId' => (int) $g->jugador_id])) . '">'
                . e((string) $g->jugador) . '</a></td>'
                . '<td class="num">' . ($g->minuto === null ? '—' : (int) $g->minuto . '\'') . '</td>'
                . '<td>' . $inc . '</td>'
                . '</tr>';
        }

        return $out . '</tbody></table></div>'
            . ($total > count($filas) ? '<p class="sub">Se muestran los ' . count($filas) . ' más nuevos.</p>' : '');
    }

    /** Los próximos que se van a revisar, para saber por dónde va la cosa. */
    private function bloquePendientesTiposGol($consulta)
    {
        $filas = (clone $consulta)->orderByDesc('dia')->limit(40)->get();
        if ($filas->isEmpty()) return '';

        $fechas = $this->mapaFechas($filas->pluck('partido_id')->all());

        $out = '<h2>Los próximos <span class="sub">(' . count($filas) . ' de los más nuevos)</span></h2>'
            . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Competencia</th><th>Partido</th>'
            . '<th>gameId</th><th></th></tr></thead><tbody>';

        foreach ($filas as $f) {
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);
            $out .= '<tr>'
                . '<td class="num">' . e(substr((string) $f->dia, 0, 10)) . '</td>'
                . '<td>' . e((string) $f->competencia_nombre) . '</td>'
                . '<td>' . e($f->club_nombre . ' vs ' . $f->rival_nombre)
                . ' <span class="id">#' . (int) $f->partido_id . '</span></td>'
                . '<td class="num"><span class="id">' . e((string) $f->external_id) . '</span></td>'
                . '<td>' . $inc . '</td>'
                . '</tr>';
        }

        return $out . '</tbody></table></div>';
    }

    // ═══════════════════════════ DIAGNÓSTICO ═══════════════════════════

    /**
     * Qué devuelve tmapi para un árbitro, un DT o un jugador, y qué saca de ahí
     * el parser. `personaDesdePerfil()` se escribió con el JSON de jugadores;
     * si árbitros o DTs traen otras claves, se ve acá.
     *
     *   /admin/import-detalles/arbitro?tm_id=5325&tipo=arbitro
     */
    public function arbitro(Request $request)
    {
        set_time_limit(0);

        $tmId = trim((string) $request->get('tm_id', ''));
        $tipo = (string) $request->get('tipo', 'arbitro');
        if (!in_array($tipo, ['arbitro', 'tecnico', 'jugador', 'equipo'], true)) $tipo = 'arbitro';

        // Los clubes no usan /profil/: van por /startseite/.
        $perfilTm = ['arbitro' => 'profil/schiedsrichter', 'tecnico' => 'profil/trainer',
            'jugador' => 'profil/spieler', 'equipo' => 'startseite/verein'];

        $opts = '';
        foreach (['arbitro' => 'Árbitro', 'tecnico' => 'DT',
                     'jugador' => 'Jugador (referencia)', 'equipo' => 'Club'] as $k => $v) {
            $opts .= '<option value="' . $k . '"' . ($tipo === $k ? ' selected' : '') . '>' . e($v) . '</option>';
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.revisar')) . '">← Jugadores y árbitros por revisar</a></p>'
            . '<h1>Diagnóstico de perfiles de Transfermarkt</h1>'
            . '<p class="sub">Árbitros y DTs se crean con el mismo parser que los jugadores '
            . '(<code>personaDesdePerfil</code>). Si a alguno le falta la fecha de nacimiento o la nacionalidad, '
            . 'es porque su JSON usa otras claves. Mirá un jugador también: ese es el que el parser SÍ entiende.</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<select name="tipo">' . $opts . '</select> '
            . '<input name="tm_id" value="' . e($tmId) . '" placeholder="id de TM, ej 5325" size="18"> <button>Ver</button>'
            . ' <span class="sub">el id sale del link TM de la lista de revisión, o de la URL del perfil</span></form>';

        if ($tmId === '') {
            return $this->pagina('Diagnóstico de perfiles', $cuerpo
                . '<div class="diag">Pegá un id de Transfermarkt para ver el JSON crudo y qué interpreta el importador. '
                . 'Cuesta hasta 4 llamadas a la API.</div>');
        }

        $r = TmDetallePartido::diagnosticarPersonaTm($tmId, $tipo);

        $cuerpo .= '<p class="sub">' . (int) $r['llamadas'] . ' llamada(s) a la API · '
            . '<a target="_blank" href="https://www.transfermarkt.es/-/' . $perfilTm[$tipo] . '/' . e($tmId)
            . '">ver el perfil en TM ↗</a>'
            . ($tipo === 'equipo'
                ? ' · <a target="_blank" href="https://www.transfermarkt.es/-/datenfakten/verein/' . e($tmId)
                  . '">Datos y hechos ↗</a> <span class="sub">(fundación, estadio, socios: no están en la API)</span>'
                : '') . '</p>';

        if (empty($r['perfil'])) {
            $cuerpo .= '<div class="err-box">Ninguna ruta devolvió un perfil para ese id. '
                . 'Mirá abajo qué contestó cada una.</div>';
        } elseif ($tipo === 'equipo') {
            $cuerpo .= '<div class="ok-box">Perfil encontrado. Los clubes todavía no se importan: '
                . 'este crudo es para ver qué campos hay (escudo, fundación, estadio, país, socios) '
                . 'antes de escribir el importador.</div>'
                . '<h2>Perfil crudo</h2><pre>' . e(json_encode($r['perfil'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
            $d = $r['datos'];
            $fila = function ($k, $v) {
                return '<tr><td>' . e($k) . '</td><td>'
                    . ($v === null || $v === '' ? '<span class="err">— vacío —</span>' : '<b>' . e($v) . '</b>')
                    . '</td></tr>';
            };
            $per = isset($d['persona']) ? $d['persona'] : [];
            $cuerpo .= '<h2>Lo que saca el parser hoy</h2>'
                . '<div class="scroll"><table><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>'
                . $fila('name', isset($d['name']) ? $d['name'] : null)
                . $fila('nombre', isset($per['nombre']) ? $per['nombre'] : null)
                . $fila('apellido', isset($per['apellido']) ? $per['apellido'] : null)
                . $fila('nacimiento', isset($per['nacimiento']) ? $per['nacimiento'] : null)
                . $fila('nacionalidad', isset($per['nacionalidad']) ? $per['nacionalidad'] : null)
                . $fila('ciudad', isset($per['ciudad']) ? $per['ciudad'] : null)
                . '</tbody></table></div>'
                . '<p class="sub"><b>Ojo:</b> si nacionalidad sale vacía, la persona NO queda sin nacionalidad — '
                . '<code>personas.nacionalidad</code> tiene DEFAULT \'Argentina\' en la base y la rellena sola. '
                . 'Un dato faltante se convierte en un dato falso.</p>'
                . '<h2>Perfil crudo</h2><pre>' . e(json_encode($r['perfil'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        foreach ($r['rutas'] as $ruta => $json) {
            $cuerpo .= '<h2>' . e($ruta) . '</h2><pre>'
                . e($json === null || $json === false
                    ? 'sin respuesta'
                    : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . '</pre>';
        }

        if (!empty($r['avisos'])) {
            $cuerpo .= '<h2>Avisos</h2><div class="diag">';
            foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . $this->avisoHtml($a) . '</div>';
            $cuerpo .= '</div>';
        }

        return $this->pagina('Diagnóstico de perfiles', $cuerpo);
    }

    /**
     * Explora qué rutas de competencia existen en tmapi. Es el paso previo a
     * decidir si un torneo EN CURSO (Liga Profesional, Conmebol) se puede
     * cargar por fixture en vez de fecha por fecha a mano desde livefutbol.
     *
     *   /admin/import-detalles/competencia?comp_id=ARGC&season=2025&ronda=1
     */
    public function competencia(Request $request)
    {
        set_time_limit(0);

        $compId = trim((string) $request->get('comp_id', ''));
        $season = trim((string) $request->get('season', ''));
        $ronda  = trim((string) $request->get('ronda', ''));
        $clubId = trim((string) $request->get('club_id', ''));

        $cuerpo = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Diagnóstico de competencias</h1>'
            . '<p class="sub">Busca una ruta que devuelva la <b>lista de partidos</b> con su <code>gameId</code>. '
            . 'Es lo único que falta para cargar un torneo en curso: con el gameId, el importador de detalle '
            . 'ya trae alineaciones e incidencias. <b>Cada ruta cuesta 1 crédito.</b><br>'
            . 'Ya sabemos que <code>/games</code>, <code>/matches</code>, <code>matchday</code> y <code>round</code> '
            . 'dan 404. Acá probamos el vocabulario propio de TM (<code>gameDay</code>) y el espejo de '
            . '<code>/coach/{id}/performance-game</code> a nivel club.<br>'
            . 'El id de competencia sale de <code>primaryCompetitionId</code> de un club (ej <code>ARGC</code>) '
            . 'o de <code>import_partidos.competencia_external_id</code>. El de club, de <code>equipo_tm</code> '
            . '(Vélez es <code>1029</code>).</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<input name="comp_id" value="' . e($compId) . '" placeholder="comp id, ej ARGC" size="14"> '
            . '<input name="season" value="' . e($season) . '" placeholder="temporada, ej 2025" size="12"> '
            . '<input name="ronda" value="' . e($ronda) . '" placeholder="fecha nº" size="9"> '
            . '<input name="club_id" value="' . e($clubId) . '" placeholder="club TM, ej 1029" size="12"> '
            . '<button>Probar</button></form>';

        if ($compId === '' && $clubId === '') {
            return $this->pagina('Diagnóstico de competencias', $cuerpo
                . '<div class="diag">Pegá un id de competencia y/o uno de club para arrancar.</div>');
        }

        $r = TmDetallePartido::diagnosticarCompetencia($compId ?: '', $season ?: null, $ronda ?: null, $clubId ?: null);

        $vivas = 0;
        foreach ($r['rutas'] as $info) if (!empty($info['ok'])) $vivas++;

        $cuerpo .= '<div class="cards">'
            . $this->card($r['llamadas'], 'rutas probadas')
            . $this->card($vivas, 'con datos', $vivas ? 'ok' : 'err')
            . '</div>';

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Ruta</th><th>¿Responde?</th>'
            . '<th>Items</th><th>Rama</th><th>Claves de primer nivel</th></tr></thead><tbody>';
        foreach ($r['rutas'] as $ruta => $info) {
            $cuerpo .= '<tr' . ((int) $info['items'] > 1 ? ' class="warn"' : '') . '>'
                . '<td><code>' . e($ruta) . '</code></td>'
                . '<td>' . (!empty($info['ok']) ? '<span class="ok">sí</span>' : '<span class="err">no</span>') . '</td>'
                . '<td class="num">' . ((int) $info['items'] ?: '—') . '</td>'
                . '<td>' . e($info['rama'] ?: '—') . '</td>'
                . '<td>' . e(implode(', ', $info['claves']) ?: '—') . '</td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>'
            . '<p class="sub">La candidata es la fila resaltada con muchos <b>items</b>: ahí está la lista de partidos. '
            . 'La columna <b>Rama</b> dice de qué clave cuelga.</p>';

        foreach ($r['rutas'] as $ruta => $info) {
            if (empty($info['ok'])) continue;
            $txt = json_encode($info['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $corte = '';
            if (strlen($txt) > 12000) {
                $txt = substr($txt, 0, 12000);
                $corte = '<p class="sub">(recortado: se muestran los primeros 12 KB)</p>';
            }
            $cuerpo .= '<h2>' . e($ruta) . '</h2><pre>' . e($txt) . '</pre>' . $corte;
        }

        return $this->pagina('Diagnóstico de competencias', $cuerpo);
    }

    // ═══════════════════════════ PLANTILLAS ═══════════════════════════

    /**
     * Completa las plantillas de los partidos que YA tienen la alineación
     * cargada. No toca Transfermarkt: todo sale de `alineacions`.
     *
     * Los partidos que se importaron antes de que el detalle escribiera
     * `plantilla_jugadors` quedaron con la alineación bien pero fuera de la
     * plantilla del torneo, y por eso `/admin/alineaciones` no los deja editar.
     * Esto los repara sin volver a bajar nada.
     */
    /**
     * Completa el marcador de los partidos que quedaron sin resultado, leyendo
     * el fixture que ya está guardado en `import_partidos`. **No gasta ninguna
     * llamada a la API.**
     *
     * Hace falta porque el detalle escribe el marcador recién desde que se
     * agregó `completarMarcador()`: todo lo que se bajó antes quedó sin
     * resultado, y como esos partidos ya tienen la alineación, volver a darles
     * "Bajar" corta antes de tocar nada. Nunca pisa un resultado cargado.
     */
    public function resultados(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $comp      = trim((string) $request->get('comp', ''));
        $ronda     = trim((string) $request->get('ronda', ''));
        $aplicar   = (string) $request->get('aplicar', '0') === '1';

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('payload')
            ->whereIn('estado', ['aplicado', 'duplicado']);
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
        if ($comp !== '')  $q->where('competencia_external_id', $comp);
        if ($ronda !== '') $q->where('ronda', $ronda);

        $ids = array_values(array_unique(array_map('intval', $q->limit(5000)->pluck('partido_id')->all())));

        $r = TmDetallePartido::marcadoresDesdeStaging($ids, $aplicar);

        $filtros = array_filter(['tecnico_id' => $tecnicoId ?: null, 'comp' => $comp ?: null, 'ronda' => $ronda ?: null]);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', $filtros)) . '">← Detalle de los partidos</a></p>'
            . '<h1>Resultados de lo ya cargado</h1>'
            . '<p class="sub">Los partidos que bajaste antes de que el detalle escribiera el marcador quedaron con las '
            . 'incidencias completas pero <b>sin resultado</b>. Volver a darles "Bajar" no los arregla: como ya tienen '
            . 'alineación, el importador corta antes. Esto les carga el marcador desde el fixture que ya está guardado: '
            . '<b>no gasta ninguna llamada a la API</b> y nunca pisa un resultado que ya esté cargado.</p>';

        $cuerpo .= '<div class="cards">'
            . $this->card(count($ids), 'Partidos mirados')
            . $this->card($r['mirados'], 'Sin resultado', $r['mirados'] ? 'warn' : 'ok')
            . $this->card($r['con_marcador'], 'Con marcador en el fixture', 'ok')
            . $this->card($r['mirados'] - $r['con_marcador'], 'Sin dato', ($r['mirados'] - $r['con_marcador']) ? 'warn' : '')
            . ($aplicar ? $this->card($r['escritos'], 'Cargados', 'ok') : '')
            . '</div>';

        if (!$r['mirados']) {
            $cuerpo .= '<div class="ok-box">No hay ningún partido sin resultado' . ($comp !== '' || $ronda !== '' || $tecnicoId ? ' con este filtro' : '') . '.</div>';
            return $this->pagina('Resultados', $cuerpo);
        }

        if ($aplicar) {
            $cuerpo .= '<div class="ok-box">Listo: ' . (int) $r['escritos'] . ' partido(s) con el resultado cargado.</div>';
        } else {
            $params = $filtros; $params['aplicar'] = 1;
            $cuerpo .= '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.resultados', $params))
                . '">Cargar estos ' . (int) $r['con_marcador'] . ' resultados</a>'
                . ' <span class="sub">recién acá se escribe</span></p>';
        }

        $fechas = $this->mapaFechas(array_map(function ($f) { return $f['partido_id']; }, $r['filas']));

        $cuerpo .= '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Local</th><th>Res.</th><th>Visitante</th><th>Partido</th><th></th><th></th>'
            . '</tr></thead><tbody>';
        foreach ($r['filas'] as $f) {
            $inc = $this->linkIncidencias(isset($fechas[$f['partido_id']]) ? $fechas[$f['partido_id']] : null);
            $cuerpo .= '<tr>'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($this->nombreEquipo($f['equipol_id'])) . '</td>'
                . '<td class="num">' . ($f['gl'] === null ? '—' : '<b>' . (int) $f['gl'] . ':' . (int) $f['gv'] . '</b>') . '</td>'
                . '<td>' . e($this->nombreEquipo($f['equipov_id'])) . '</td>'
                . '<td class="num"><span class="id">#' . (int) $f['partido_id'] . '</span></td>'
                . '<td class="sub">' . ($f['motivo'] ? '<span class="warn">' . e($f['motivo']) . '</span>'
                    : ($aplicar ? '<span class="ok">cargado</span>' : 'listo para cargar')) . '</td>'
                . '<td>' . ($inc !== '' ? $inc : '') . '</td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        return $this->pagina('Resultados', $cuerpo);
    }

    /** Nombre de un equipo, cacheado: estas tablas repiten los mismos. */
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

    public function plantillas(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $aplicar   = (string) $request->get('aplicar', '0') === '1';

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereIn('estado', ['aplicado', 'duplicado']);
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
        $ids = $q->pluck('partido_id')->all();

        $r = TmDetallePartido::plantillasDesdeAlineaciones($ids, $aplicar);

        $volver = array_filter(['tecnico_id' => $tecnicoId ?: null]);
        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', $volver)) . '">← Detalle de los partidos</a></p>'
            . '<h1>Plantillas de lo ya cargado</h1>'
            . '<p class="sub">Los partidos que cargaste antes de que el importador escribiera plantillas quedaron con '
            . 'la alineación completa pero sus jugadores fuera de la <b>plantilla del torneo</b>. Se ven bien en la ficha, '
            . 'pero <code>/admin/alineaciones</code> no los ofrece en los desplegables y no se pueden editar a mano. '
            . 'Esto lo arregla leyendo las alineaciones que ya están en tu base: <b>no gasta ninguna llamada a la API</b>.</p>';

        $cuerpo .= '<div class="cards">'
            . $this->card($r['partidos'], 'Partidos con alineación')
            . $this->card($r['alineaciones'], 'Filas de alineación')
            . $this->card(count($r['faltantes']), 'Fuera de la plantilla', count($r['faltantes']) ? 'warn' : 'ok')
            . $this->card($r['plantillas_nuevas'], 'Plantillas a crear', $r['plantillas_nuevas'] ? 'warn' : '')
            . ($aplicar ? $this->card($r['agregados'], 'Agregados', 'ok') : '')
            . ($aplicar && $r['fallidas'] ? $this->card($r['fallidas'], 'Rechazados', 'err') : '')
            . '</div>';

        if ($aplicar) {
            $cuerpo .= $r['fallidas']
                ? '<div class="err-box">Se agregaron ' . (int) $r['agregados'] . ', pero la base rechazó '
                . (int) $r['fallidas'] . '. Mirá los avisos.</div>'
                : '<div class="ok-box">Listo: ' . (int) $r['agregados'] . ' jugador(es) sumados a su plantilla.</div>';

            if (!empty($r['avisos'])) {
                $cuerpo .= '<h2>Avisos</h2><div class="diag">';
                foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . $this->avisoHtml($a) . '</div>';
                $cuerpo .= '</div>';
            }
            $cuerpo .= '<p class="acciones">'
                . '<a class="boton" href="' . e(route('import_detalles.index', $volver)) . '">Volver a la lista</a>'
                . '<a class="boton-sec" href="' . e(route('import_detalles.plantillas', $volver)) . '">Volver a revisar</a></p>';
            return $this->pagina('Plantillas', $cuerpo);
        }

        if (empty($r['faltantes'])) {
            $cuerpo .= '<div class="ok-box">No falta nadie: todos los jugadores de las alineaciones ya están en la '
                . 'plantilla de su equipo en ese torneo' . ($tecnicoId ? ' (para este DT)' : '') . '.</div>';
            return $this->pagina('Plantillas', $cuerpo);
        }

        $params = $volver;
        $params['aplicar'] = 1;
        $cuerpo .= '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.plantillas', $params))
            . '">Sumarlos a la plantilla (' . count($r['faltantes']) . ')</a>'
            . ' <span class="sub">no se le toca el dorsal a nadie que ya esté cargado</span></p>';

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Torneo</th><th>Equipo</th><th>Jugador</th>'
            . '<th>Dorsal</th><th>Plantilla</th><th>Partido</th></tr></thead><tbody>';
        $n = 0;
        foreach ($r['faltantes'] as $f) {
            if ($n++ >= 500) break;
            $cuerpo .= '<tr>'
                . '<td>' . e($f['_torneo']) . '</td>'
                . '<td>' . e($f['_equipo']) . '</td>'
                . '<td>' . e($f['_nombre']) . '</td>'
                . '<td class="num">' . e($f['dorsal'] === null || $f['dorsal'] === '' ? '—' : $f['dorsal']) . '</td>'
                . '<td class="num">' . ($f['_plantilla'] ? '<span class="id">#' . (int) $f['_plantilla'] . '</span>' : '<span class="warn">se crea</span>') . '</td>'
                . '<td class="num"><span class="id">#' . (int) $f['partido_id'] . '</span></td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';
        if (count($r['faltantes']) > 500) {
            $cuerpo .= '<p class="sub">Se muestran 500 de ' . count($r['faltantes']) . '. Se aplican todos igual.</p>';
        }

        return $this->pagina('Plantillas', $cuerpo);
    }

    // ═══════════════════════════ MAPEO Y REVISIÓN ═══════════════════════════

    /** Llena jugador_tm con los jugadores que ya tenés y tienen transfermarkt_url. */
    public function sembrar(Request $request)
    {
        set_time_limit(0);
        $r = TmDetallePartido::sembrarDesdeUrls();

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Siembra de jugador_tm</h1>'
            . '<p class="sub">Se lee el <code>/spieler/NNN</code> de <code>jugadors.transfermarkt_url</code> y se ata cada '
            . 'jugador a su id de Transfermarkt. Es lo que evita que el importador cree de nuevo a alguien que ya tenés.</p>'
            . '<div class="cards">'
            . $this->card($r['creados'], 'Mapeos nuevos', $r['creados'] ? 'ok' : 'gris')
            . $this->card($r['ya_estaban'], 'Ya estaban')
            . $this->card($r['repuntados'], 'Repuntados', $r['repuntados'] ? 'ok' : 'gris')
            . $this->card($r['n_conflictos'], 'Chocan con otra ficha', $r['n_conflictos'] ? 'err' : 'gris')
            . $this->card($r['sin_id'], 'URL sin id', $r['sin_id'] ? 'warn' : 'gris')
            . '</div>'

            . (($r['creados'] === 0 && $r['repuntados'] === 0 && $r['ya_estaban'] > 0 && $r['n_conflictos'] === 0)
                ? '<div class="ok-box"><b>No había nada que sembrar: los ' . $r['ya_estaban'] . ' ya estaban atados.</b><br>'
                . '"Ya estaban" no es un error. El mapeo lo crea también el importador cuando baja una alineación '
                . '(<code>origen=api</code>), así que la siembra sólo tiene trabajo con jugadores nuevos.</div>'
                : '')

            . ($r['repuntados']
                ? '<div class="ok-box">Se repuntaron ' . $r['repuntados'] . ' mapeo(s) que apuntaban a una ficha borrada.</div>'
                : '');

        if ($r['n_conflictos']) {
            $ids = [];
            foreach ($r['conflictos'] as $c) { $ids[] = $c['ficha_url']; $ids[] = $c['ficha_mapeo']; }
            $nombres = [];
            foreach (DB::table('jugadors')
                ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
                ->whereIn('jugadors.id', array_unique($ids))
                ->select('jugadors.id', 'personas.apellido', 'personas.nombre', 'personas.nacimiento')
                ->get() as $p) {
                $nombres[(int) $p->id] = trim($p->apellido . ', ' . $p->nombre)
                    . ($p->nacimiento ? ' (' . substr((string) $p->nacimiento, 0, 4) . ')' : '');
            }

            $cuerpo .= '<h2>Chocan con el mapeo (' . $r['n_conflictos'] . ')</h2>'
                . '<p class="sub">El id de Transfermarkt de la izquierda ya está atado a la ficha de la derecha, que '
                . 'también existe. <b>No se tocó nada</b>: pisar el mapeo dejaría los partidos ya cargados colgados de '
                . 'la ficha equivocada. Si las dos fichas son la misma persona, fusionalas; si no, sacale la URL a la '
                . 'que no corresponde.</p>'
                . '<div class="scroll"><table><thead><tr>'
                . '<th>TM</th><th>La URL la tiene</th><th>El mapeo apunta a</th><th></th></tr></thead><tbody>';
            foreach ($r['conflictos'] as $c) {
                $a = isset($nombres[$c['ficha_url']]) ? $nombres[$c['ficha_url']] : ('ficha ' . $c['ficha_url']);
                $b = isset($nombres[$c['ficha_mapeo']]) ? $nombres[$c['ficha_mapeo']] : ('ficha ' . $c['ficha_mapeo']);
                $cuerpo .= '<tr>'
                    . '<td><a target="_blank" href="https://www.transfermarkt.es/-/profil/spieler/' . e($c['tm']) . '">' . e($c['tm']) . '</a></td>'
                    . '<td>' . e($a) . ' <span class="sub">#' . (int) $c['ficha_url'] . '</span></td>'
                    . '<td>' . e($b) . ' <span class="sub">#' . (int) $c['ficha_mapeo'] . '</span></td>'
                    . '<td><a href="' . e(route('jugadores.edit', $c['ficha_url'])) . '">Editar la de la URL</a>'
                    . ' · <a href="' . e(route('jugadores.edit', $c['ficha_mapeo'])) . '">Editar la del mapeo</a></td>'
                    . '</tr>';
            }
            $cuerpo .= '</tbody></table></div>';
            if ($r['n_conflictos'] > count($r['conflictos'])) {
                $cuerpo .= '<p class="sub">Se muestran los primeros ' . count($r['conflictos']) . '.</p>';
            }
        }

        $cuerpo .= '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.index')) . '">Volver</a></p>';

        return $this->pagina('Siembra de jugador_tm', $cuerpo);
    }

    /** Jugadores que creó el importador y todavía no repasó nadie. */
    public function revisar(Request $request)
    {
        if ($request->filled('ok')) {
            DB::table('jugador_tm')->where('id', (int) $request->get('ok'))->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }
        if ($request->filled('ok_arb')) {
            DB::table('arbitro_tm')->where('id', (int) $request->get('ok_arb'))->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }
        if ((string) $request->get('todos', '0') === '1') {
            DB::table('jugador_tm')->where('revisar', 1)->update(['revisar' => 0, 'updated_at' => now()]);
            DB::table('arbitro_tm')->where('revisar', 1)->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }

        // "Está mal": el apareo de TM con un árbitro nuestro no es esa persona.
        // Se corta el puente en `arbitro_tm` y nada más: el árbitro de la base
        // casi siempre es alguien real y bien cargado —el error es haberlo
        // atado a otro id de Transfermarkt—, así que borrarlo sería peor.
        // Los partidos que ya se cargaron con el árbitro equivocado se
        // arreglan con "Rehacer": ese borra los árbitros del partido y los
        // vuelve a escribir, y esta vez el id de TM ya no está atado a nadie,
        // con lo cual se crea el árbitro que corresponde.
        if ($request->filled('mal_arb')) {
            $fila = DB::table('arbitro_tm')->where('id', (int) $request->get('mal_arb'))->first();
            if (!$fila) return redirect()->route('import_detalles.revisar');

            $nombre = DB::table('arbitros')
                ->join('personas', 'personas.id', '=', 'arbitros.persona_id')
                ->where('arbitros.id', $fila->arbitro_id)->value('personas.name');

            $partidos = DB::table('partido_arbitros')
                ->join('partidos', 'partidos.id', '=', 'partido_arbitros.partido_id')
                ->where('partido_arbitros.arbitro_id', (int) $fila->arbitro_id)
                ->select('partidos.id', 'partidos.dia', 'partido_arbitros.tipo')
                ->orderBy('partidos.dia', 'desc')->limit(100)->get();

            DB::table('arbitro_tm')->where('id', (int) $fila->id)->delete();

            $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.revisar')) . '">← Jugadores y árbitros por revisar</a></p>'
                . '<h1>Apareo deshecho</h1>'
                . '<div class="ok-box">El árbitro de Transfermarkt <b>' . e($fila->tm_referee_id) . '</b>'
                . (isset($fila->nombre_tm) && $fila->nombre_tm !== '' ? ' (' . e($fila->nombre_tm) . ')' : '')
                . ' ya no está atado a <b>' . e($nombre ?: ('#' . $fila->arbitro_id)) . '</b>. '
                . 'Al árbitro de la base no lo toqué.</div>'
                . '<p class="sub">Ahora hay que rehacer los partidos donde se haya cargado mal. '
                . '<b>Rehacer</b> borra los árbitros del partido y los vuelve a bajar: como el id de TM ya no apunta '
                . 'a nadie, esta vez se va a crear el árbitro que corresponde. Ojo que gasta llamadas a la API.</p>';

            if ($partidos->isEmpty()) {
                $cuerpo .= '<div class="ok-box">Ese árbitro no figura en ningún partido: no hay nada que rehacer.</div>';
            } else {
                $cuerpo .= '<p class="sub">Estos son <b>todos</b> los partidos donde figura ' . e($nombre ?: 'ese árbitro')
                    . ' — no todos vienen del importador. Rehacé sólo los que sepas que cargó él.</p>'
                    . '<div class="scroll"><table><thead><tr><th>Partido</th><th>Día</th><th>Rol</th><th></th></tr></thead><tbody>';
                foreach ($partidos as $p) {
                    $cuerpo .= '<tr>'
                        . '<td class="num">#' . (int) $p->id . '</td>'
                        . '<td class="num">' . e(substr((string) $p->dia, 0, 10)) . '</td>'
                        . '<td>' . e($p->tipo) . '</td>'
                        . '<td><a class="err" href="' . e(route('import_detalles.bajar',
                            ['partido_id' => (int) $p->id, 'forzar' => 1])) . '">Rehacer</a></td>'
                        . '</tr>';
                }
                $cuerpo .= '</tbody></table></div>';
            }

            $cuerpo .= '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.revisar')) . '">Volver</a></p>';
            return $this->pagina('Apareo deshecho', $cuerpo);
        }

        $arbitros = DB::table('arbitro_tm')
            ->join('arbitros', 'arbitros.id', '=', 'arbitro_tm.arbitro_id')
            ->join('personas', 'personas.id', '=', 'arbitros.persona_id')
            ->where('arbitro_tm.revisar', 1)
            ->select('arbitro_tm.id', 'arbitro_tm.tm_referee_id', 'arbitro_tm.arbitro_id', 'arbitro_tm.created_at',
                'arbitro_tm.nombre_tm',
                'personas.name', 'personas.nombre', 'personas.apellido', 'personas.nacimiento', 'personas.nacionalidad')
            ->orderBy('arbitro_tm.created_at', 'desc')->limit(200)->get();

        $filas = DB::table('jugador_tm')
            ->join('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
            ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
            ->where('jugador_tm.revisar', 1)
            ->select('jugador_tm.id', 'jugador_tm.tm_player_id', 'jugador_tm.jugador_id', 'jugador_tm.created_at',
                'personas.name', 'personas.nombre', 'personas.apellido', 'personas.nacimiento',
                'personas.ciudad', 'personas.nacionalidad', 'jugadors.tipoJugador')
            ->orderBy('jugador_tm.created_at', 'desc')
            ->limit(500)->get();

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Jugadores creados por el importador</h1>'
            . '<p class="sub">Se dieron de alta solos al cargar una alineación. Repasá que el nombre, la fecha de '
            . 'nacimiento y la nacionalidad estén bien, y marcalos como vistos.</p>';

        if ($filas->isEmpty() && $arbitros->isEmpty()) {
            $cuerpo .= '<div class="ok-box">No queda ninguno por revisar.</div>';
            return $this->pagina('Jugadores por revisar', $cuerpo);
        }

        if (!$arbitros->isEmpty()) {
            $cuerpo .= '<h2>Árbitros</h2>'
                . '<p class="sub">Compará las dos columnas de nombre: <b>en Transfermarkt</b> es lo que dice la fuente '
                . 'y <b>en la base</b> es a quién quedó atado. Si no son la misma persona, <b>Está mal</b> corta el apareo.</p>'
                . '<div class="scroll"><table><thead><tr>'
                . '<th>Alta</th><th>En Transfermarkt</th><th>En la base</th><th>Apellido, nombre</th><th>Nacimiento</th>'
                . '<th>Nacionalidad</th><th>TM</th><th></th></tr></thead><tbody>';
            foreach ($arbitros as $a) {
                $distinto = $this->apellidoDistinto(isset($a->nombre_tm) ? $a->nombre_tm : '', $a->apellido);
                $cuerpo .= '<tr>'
                    . '<td class="num">' . e(substr((string) $a->created_at, 0, 10)) . '</td>'
                    . '<td>' . e((isset($a->nombre_tm) && $a->nombre_tm !== '') ? $a->nombre_tm : '—') . '</td>'
                    . '<td>' . e($a->name) . ($distinto ? ' <span class="err">¿otro apellido?</span>' : '') . '</td>'
                    . '<td>' . e($a->apellido . ', ' . $a->nombre) . '</td>'
                    . '<td class="num">' . e($a->nacimiento ?: '—') . '</td>'
                    . '<td>' . e($a->nacionalidad ?: '—') . '</td>'
                    . '<td><a target="_blank" href="https://www.transfermarkt.es/-/profil/schiedsrichter/' . e($a->tm_referee_id) . '">' . e($a->tm_referee_id) . '</a></td>'
                    . '<td><a href="' . e(route('arbitros.edit', $a->arbitro_id)) . '">Editar</a>'
                    . ' · <a href="' . e(route('import_detalles.arbitro', ['tm_id' => $a->tm_referee_id, 'tipo' => 'arbitro'])) . '">Diagnóstico</a>'
                    . ' · <a href="' . e(route('import_detalles.revisar', ['ok_arb' => $a->id])) . '">Visto</a>'
                    . ' · <a class="err" href="' . e(route('import_detalles.revisar', ['mal_arb' => $a->id]))
                    . '" title="No es esa persona: corta el apareo con Transfermarkt">Está mal</a></td>'
                    . '</tr>';
            }
            $cuerpo .= '</tbody></table></div><h2>Jugadores</h2>';
        }

        if ($filas->isEmpty()) {
            return $this->pagina('Jugadores por revisar', $cuerpo . '<p class="sub">No queda ningún jugador por revisar.</p>');
        }

        $cuerpo .= '<p class="acciones"><a class="boton-sec" href="' . e(route('import_detalles.revisar', ['todos' => 1]))
            . '">Marcar todos como vistos (' . count($filas) . ')</a></p>'
            . '<div class="scroll"><table><thead><tr>'
            . '<th>Alta</th><th>Nombre</th><th>Apellido, nombre</th><th>Nacimiento</th><th>Ciudad</th>'
            . '<th>Nacionalidad</th><th>Puesto</th><th>TM</th><th></th></tr></thead><tbody>';

        foreach ($filas as $f) {
            $falta = (!$f->nacimiento ? ' <span class="err">sin fecha</span>' : '')
                . (!$f->nacionalidad ? ' <span class="err">sin país</span>' : '');
            $cuerpo .= '<tr>'
                . '<td class="num">' . e(substr((string) $f->created_at, 0, 10)) . '</td>'
                . '<td>' . e($f->name) . '</td>'
                . '<td>' . e($f->apellido . ', ' . $f->nombre) . $falta . '</td>'
                . '<td class="num">' . e($f->nacimiento ?: '—') . '</td>'
                . '<td>' . e($f->ciudad ?: '—') . '</td>'
                . '<td>' . e($f->nacionalidad ?: '—') . '</td>'
                . '<td>' . e($f->tipoJugador ?: '—') . '</td>'
                . '<td><a target="_blank" href="https://www.transfermarkt.es/-/profil/spieler/' . e($f->tm_player_id) . '">' . e($f->tm_player_id) . '</a></td>'
                . '<td><a href="' . e(route('jugadores.edit', $f->jugador_id)) . '">Editar</a>'
                . ' · <a href="' . e(route('import_detalles.arbitro', ['tm_id' => $f->tm_player_id, 'tipo' => 'jugador'])) . '">Diagnóstico</a>'
                . ' · <a href="' . e(route('import_detalles.revisar', ['ok' => $f->id])) . '">Visto</a></td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        return $this->pagina('Jugadores por revisar', $cuerpo);
    }

    // ═══════════════════════════ AUXILIARES ═══════════════════════════

    /**
     * Los avisos se escapan siempre (traen datos crudos de Transfermarkt).
     * Después de escapar, los tokens [[plantilla:N]] se convierten en un link
     * a la edición de esa plantilla, para poder arreglar el dorsal de una.
     */
    private function avisoHtml($texto)
    {
        $html = e($texto);
        $html = preg_replace_callback('/\[\[plantilla:(\d+)\]\]/', function ($m) {
            return '<a href="' . e(route('plantillas.edit', (int) $m[1])) . '" target="_blank">abrir la plantilla ↗</a>';
        }, $html);
        // Dos fichas con el mismo nombre peleándose un dorsal: eso se arregla
        // fusionando, no en la plantilla.
        return str_replace('[[repetidos]]',
            '<a href="' . e(route('jugadores.verificarPersonas')) . '" target="_blank">verificar personas ↗</a>',
            $html);
    }

    /**
     * partido_id => fecha_id, para linkear a "Datos complementarios"
     * (`fechas.show`): la pantalla desde donde se editan alineaciones, goles,
     * tarjetas, jueces, sustituciones y penales de cada partido.
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

    private function tecnicosConPartidos()
    {
        return DB::table('import_partidos')
            ->join('tecnicos', 'tecnicos.id', '=', 'import_partidos.tecnico_id')
            ->join('personas', 'personas.id', '=', 'tecnicos.persona_id')
            ->where('import_partidos.estado', 'aplicado')
            ->whereNotNull('import_partidos.partido_id')
            ->select('tecnicos.id', 'personas.name AS nombre', DB::raw('COUNT(*) AS n'))
            ->groupBy('tecnicos.id', 'personas.name')
            ->orderBy('personas.name')
            ->get();
    }

    /** Tabla genérica del plan: columnas => títulos. */
    private function bloque($titulo, array $filas, array $columnas)
    {
        if (empty($filas)) {
            return '<h2>' . e($titulo) . '</h2><p class="sub">Nada.</p>';
        }
        $out = '<h2>' . e($titulo) . ' <span class="sub">(' . count($filas) . ')</span></h2>'
            . '<div class="scroll"><table><thead><tr>';
        foreach ($columnas as $t) $out .= '<th>' . e($t) . '</th>';
        $out .= '</tr></thead><tbody>';
        foreach ($filas as $f) {
            $clase = !empty($f['_dudoso']) ? ' class="warn"' : '';
            $out .= '<tr' . $clase . '>';
            foreach ($columnas as $k => $t) {
                $v = isset($f[$k]) ? $f[$k] : null;
                $num = in_array($k, ['minuto', 'orden', 'dorsal'], true) ? ' class="num"' : '';
                $out .= '<td' . $num . '>' . e($v === null || $v === '' ? '—' : (string) $v) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    /**
     * ¿El apellido que tenemos en la base no aparece en el nombre que mandó
     * Transfermarkt? Es la señal de un apareo equivocado: dos personas que
     * comparten los nombres de pila (Juan Pablo Belatti y Juan Pablo González)
     * y nada más. Sólo pinta la fila; no decide nada.
     */
    private function apellidoDistinto($nombreTm, $apellidoBase)
    {
        $limpiar = function ($t) {
            $t = mb_strtolower(trim((string) $t), 'UTF-8');
            $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u','ç'=>'c']);
            return preg_replace('/[^a-z0-9 ]+/u', ' ', $t);
        };

        $tm  = $limpiar($nombreTm);
        $ape = $limpiar($apellidoBase);
        if (trim($tm) === '' || trim($ape) === '') return false;

        foreach (preg_split('/\s+/', trim($ape)) as $t) {
            if (mb_strlen($t) < 3) continue;
            if (mb_strpos($tm, $t) !== false) return false;   // algo del apellido está: bien
        }
        return true;
    }

    /**
     * Mapeos con Transfermarkt que apuntan a una ficha que ya no existe.
     *
     * `jugador_tm` y `arbitro_tm` no tienen foreign key contra `jugadors` /
     * `arbitros`. Hasta ago-2026 ni la fusión de personas ni el borrado de
     * huérfanas las repuntaban, así que unificar dos fichas dejaba el mapeo
     * apuntando a la que se borró. Después, en el próximo partido de ese
     * jugador, el importador escribía ese id fantasma: MySQL lo rechazaba con
     * un 1452 —pero recién después de haberle sacado el dorsal al jugador de
     * verdad, que es lo que pasó con el 16 de Atenas de San Carlos—.
     *
     * Las dos puntas ya están tapadas (el importador ignora los mapeos muertos,
     * y la fusión y el borrado los repuntan o los borran). Esto es para los que
     * quedaron de antes: **un arreglo nuevo no repara lo viejo**.
     *
     * Borrarlos no pierde nada y NO gasta llamadas a la API: la próxima vez que
     * ese jugador aparezca en un partido, el importador lo aparea de nuevo por
     * apellido + fecha de nacimiento y vuelve a atar la fila, esta vez a la
     * ficha que sobrevivió.
     */
    public function mapeos(Request $request)
    {
        if ((string) $request->get('limpiar', '0') === '1') {
            $borradas = TmDetallePartido::limpiarMapeosRotos();
            return redirect()->route('import_detalles.mapeos')
                ->with('ok_mapeos', $borradas['jugador'] + $borradas['arbitro']);
        }

        $rotos = TmDetallePartido::mapeosRotos();
        $total = count($rotos['jugador']) + count($rotos['arbitro']);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Mapeos rotos</h1>'
            . '<p class="sub">Filas de <code>jugador_tm</code> y <code>arbitro_tm</code> que apuntan a una ficha '
            . 'que ya no existe: se la llevó una fusión de personas o el borrado de huérfanas. '
            . 'Borrarlas no pierde nada y no gasta ni una llamada a la API — la próxima vez que ese jugador '
            . 'aparezca en un partido se vuelve a aparear solo, contra la ficha que quedó.</p>';

        if (session('ok_mapeos') !== null) {
            $cuerpo .= '<div class="ok-box">Limpié ' . (int) session('ok_mapeos') . ' fila(s).</div>';
        }

        if (!$total) {
            $cuerpo .= '<div class="ok-box">No hay ninguno: todos los mapeos apuntan a una ficha que existe.</div>';
            return $this->pagina('Mapeos rotos', $cuerpo);
        }

        $cuerpo .= '<p class="acciones"><a class="boton" href="'
            . e(route('import_detalles.mapeos', ['limpiar' => 1])) . '">Limpiar los ' . $total . '</a></p>';

        foreach (['jugador' => ['Jugadores', 'jugador'], 'arbitro' => ['Árbitros', 'árbitro']] as $rol => $textos) {
            if (empty($rotos[$rol])) continue;
            $cuerpo .= '<h2>' . e($textos[0]) . ' (' . count($rotos[$rol]) . ')</h2>'
                . '<table><thead><tr><th>id TM</th><th>nombre que trajo TM</th>'
                . '<th>' . e($textos[1]) . ' que ya no existe</th><th>origen</th><th>atado el</th></tr></thead><tbody>';
            foreach ($rotos[$rol] as $r) {
                $cuerpo .= '<tr>'
                    . '<td>' . e((string) $r->tm_id) . '</td>'
                    . '<td>' . e($r->nombre_tm !== null && $r->nombre_tm !== '' ? $r->nombre_tm : '—') . '</td>'
                    . '<td>#' . (int) $r->ficha_id . '</td>'
                    . '<td>' . e($r->origen !== null ? $r->origen : '—') . '</td>'
                    . '<td>' . e($r->created_at !== null ? substr((string) $r->created_at, 0, 10) : '—') . '</td>'
                    . '</tr>';
            }
            $cuerpo .= '</tbody></table>';
        }

        return $this->pagina('Mapeos rotos', $cuerpo);
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
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
