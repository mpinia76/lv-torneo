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
        // El campo acepta la URL entera de Transfermarkt, no sólo el número:
        // copiar la barra de direcciones es lo natural.
        $pegado    = trim((string) $request->get('game_id', ''));
        $gameId    = $this->gameIdDesde($pegado);
        // Ojo con el vacío: si el campo VINO en la URL pero llegó sin nada, el
        // usuario apretó el botón sin pegar. Volver a salir a buscar ahí sería
        // gastar créditos para mostrarle exactamente la misma pantalla.
        $malPegado = $request->has('game_id') && $gameId === '';
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
        // Si pegó algo y no se pudo sacar el gameId, NO se sale a buscar: sería
        // gastar créditos para volver a la misma pantalla. Se le dice qué pasó.
        $buscado = null;
        if ($partidoId && $gameId === '' && !$malPegado) {
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
            $error = '';

            if ($malPegado && $pegado === '') {
                $error = 'El campo llegó <b>vacío</b>. El texto gris que ves en el recuadro es sólo un ejemplo, '
                    . 'no un valor cargado: hay que <b>escribir o pegar adentro</b> la URL y recién ahí apretar '
                    . 'el botón.';
            } elseif ($malPegado) {
                $error = 'No pude sacar un gameId de «' . e($pegado) . '». Tiene que ser la URL de la <b>ficha del '
                    . 'partido</b> en Transfermarkt (la que lleva <code>/spielbericht/</code>) o el número solo. '
                    . 'La URL del club, la del torneo o la de una fecha no alcanzan: no dicen cuál es el partido.';
            }

            return $this->pagina('Detalle', $this->sinGameId($partidoId, $buscado, $error));
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
    private function sinGameId($partidoId, $buscado, $error = '')
    {
        if (!$partidoId) {
            return '<p class="err">Falta <code>?partido_id=</code>.</p>';
        }

        $encabezado = $error !== '' ? '<div class="err-box">' . $error . '</div>' : '';

        $candidatos = isset($buscado['candidatos']) ? $buscado['candidatos'] : [];

        $html = '<h1>' . ($candidatos ? '¿Cuál de estos es?' : 'No encontré este partido en Transfermarkt') . '</h1>'
            . '<p class="sub">' . $this->resumenPartido($partidoId) . '</p>'
            . $encabezado;

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
                . 'Lo busqué en el staging, en el fixture de los dos clubes, en los partidos de los DTs '
                . 'y en el fixture de la competencia del torneo, y no apareció. La tabla de abajo dice '
                . 'qué contestó cada uno.</p>';
        }

        if (!empty($buscado['avisos'])) {
            $html .= '<div class="diag">';
            foreach ($buscado['avisos'] as $a) {
                $html .= '<div class="warn">• ' . e($a) . '</div>';
            }
            $html .= '</div>';
        }

        // El rastro es lo que convierte "no lo encontré" en un diagnóstico: dice si
        // cada fuente contestó, cuántos partidos trajo y de qué temporada. Sin eso,
        // "TM no lo tiene" y "TM me contestó el campeonato de este año" se ven igual.
        if (!empty($buscado['partida'])) {
            $html .= '<h2>Con qué arranqué</h2>'
                . '<p class="sub">' . e((string) $buscado['partida']) . '</p>';
        }

        if (!empty($buscado['rastro'])) {
            $html .= '<h2>Qué contestó cada fuente</h2>'
                . '<p class="sub">Mirá la columna <b>Período</b>: si el día del partido no cae adentro, '
                . 'esa fuente estaba mirando otra temporada y por eso no lo tenía.</p>'
                . '<div class="scroll"><table><thead><tr><th>Fuente</th><th>Partidos</th>'
                . '<th>Temporadas TM</th><th>Período</th><th>Qué pasó</th></tr></thead><tbody>';

            foreach ($buscado['rastro'] as $r) {
                $temps = !empty($r['temporadas']) ? implode(', ', $r['temporadas']) : '—';
                $per   = (!empty($r['desde']) && !empty($r['hasta']))
                    ? $r['desde'] . ' → ' . $r['hasta'] : '—';

                $html .= '<tr>'
                    . '<td>' . e((string) $r['fuente']) . '</td>'
                    . '<td class="num">' . ($r['partidos'] === null ? '—' : (int) $r['partidos']) . '</td>'
                    . '<td class="num gris">' . e($temps) . '</td>'
                    . '<td class="num">' . e($per) . '</td>'
                    . '<td>' . e((string) $r['nota']) . '</td>'
                    . '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= $this->comoCompletarTorneo($buscado);

        $html .= '<p class="sub"><b>Para que se rehaga solo la próxima vez</b>, alcanza con cualquiera de estas: '
            . 'que los dos equipos estén atados a su club de Transfermarkt en <code>equipo_tm</code> '
            . '(el sondeo del DT aprende esos mapeos solo); que los DTs del partido tengan cargada su '
            . 'URL de Transfermarkt; o que el torneo tenga cargado su <b>id de competencia</b> de '
            . 'Transfermarkt (Editar torneo → transfermarkt.com).</p>'
            . '<p class="sub"><b>Ojo con los campeonatos ya terminados.</b> Todas las rutas de fixture de '
            . 'la API devuelven la temporada <b>en curso</b> e ignoran el año que se les pide (verificado). '
            . 'Lo único que va hacia atrás en el tiempo son los partidos del DT, así que en un partido '
            . 'viejo la vía rápida es cargarle la URL de Transfermarkt a los DTs que lo dirigieron — '
            . 'o pegar la del partido, acá abajo.</p>';

        // Antes acá había un link al importador VIEJO (el de promiedos /
        // livefutbol, con tres campos de URL). Confundía por dos motivos: no se
        // entendía qué URL había que pegar, y ese camino usa otro escritor de
        // incidencias, sin los arreglos del importador nuevo (roles de árbitro
        // leídos y no inferidos, gol olímpico, penales, plantillas). Lo que hay
        // que pegar es la URL del partido EN TRANSFERMARKT, y el que la usa es
        // este importador.
        $html .= '<h2>Cargarlo a mano</h2>'
            . '<p class="sub">Si ya sabés cuál es el partido en Transfermarkt, abrí <b>su ficha</b> (la página del '
            . 'partido, no la del club ni la del torneo) y copiá acá la barra de direcciones. No recortes nada: de '
            . 'la URL saco el <b>gameId</b> solo. También podés pegar el número pelado. Queda así: '
            . '<code>transfermarkt.com/spielbericht/index/spielbericht/<b>4643374</b></code>.</p>'
            . '<form method="get" action="' . e(route('import_detalles.ver')) . '" class="acciones">'
            . '<input type="hidden" name="partido_id" value="' . (int) $partidoId . '">'
            // El placeholder NO puede parecer un valor ya cargado: con una URL
            // completa de ejemplo, se aprieta el botón creyendo que está puesta.
            . '<input type="text" name="game_id" size="70" required '
            . 'placeholder="pegá acá la URL del partido en Transfermarkt…"> '
            . '<button class="boton" type="submit">Ver qué trae →</button>'
            . '</form>'
            . '<p class="sub">Eso abre la <b>vista previa</b>: muestra la alineación, los goles, las tarjetas y los '
            . 'árbitros que va a escribir <b>antes</b> de tocar nada, y si los equipos no aparean con los de la base '
            . 'te avisa ahí mismo. Se guarda recién cuando lo confirmás, y ahí el gameId queda anotado para siempre: '
            . 'de ahí en más este partido se rehace solo.</p>'
            . '<p class="sub">Si el partido no está en Transfermarkt, queda el importador viejo, que carga las '
            . 'incidencias pegando la URL de <b>promiedos</b> o <b>livefutbol</b>: '
            . '<a href="' . e(route('fechas.importarPartido', ['partidoId' => (int) $partidoId])) . '">'
            . 'importar desde otra fuente</a>.</p>';

        return $html;
    }

    /**
     * Los pasos concretos para completarle al torneo lo que le falta de TM.
     *
     * El id de competencia y el seasonId no se pueden adivinar desde acá, pero
     * SÍ se puede llevar al usuario derecho a la página donde están: el fixture
     * del club en esa temporada lista los partidos agrupados por competencia, y
     * de esos links salen los dos datos. Decirle "cargalos" sin el link es
     * mandarlo a buscar a mano lo que ya sabemos dónde está.
     *
     * La temporada del link es TENTATIVA: se usa la del torneo si está cargada
     * y si no el año del partido menos uno (que es lo que vale para los
     * campeonatos de año calendario). Si no es esa, el desplegable de la propia
     * página de TM la cambia.
     */
    private function comoCompletarTorneo($buscado)
    {
        $ctx = isset($buscado['contexto']) && is_array($buscado['contexto']) ? $buscado['contexto'] : [];
        $t   = isset($ctx['torneo']) && is_array($ctx['torneo']) ? $ctx['torneo'] : null;

        if (!$t) {
            return '';
        }

        $nombre = trim($t['nombre'] . ' ' . $t['year']);
        $ajena  = isset($ctx['temporada_ajena']) ? $ctx['temporada_ajena'] : null;

        // Si TM está devolviendo otra temporada, cargarle los ids al torneo no
        // cambia nada para este partido: la API no sabe traer campeonatos
        // terminados. Mandarlo a cargarlos igual sería hacerle perder el tiempo.
        if ($ajena !== null) {
            return '<h2>Cómo completarlo</h2>'
                . '<p class="sub">Para <b>este</b> partido no hay nada que cargar. Transfermarkt está '
                . 'devolviendo otra temporada (' . e((string) $ajena) . ') y sus rutas de fixture ignoran '
                . 'el año que se les pide, así que un partido de un campeonato ya terminado no se puede '
                . 'encontrar por la API. La salida es pegar la URL a mano, acá abajo. '
                . 'Cargarle al torneo su id de competencia sigue valiendo la pena, pero para los partidos '
                . 'del campeonato <b>en curso</b>.</p>';
        }

        if ($t['comp'] !== '') {
            return '';
        }

        $anio      = (int) substr((string) (isset($ctx['dia']) ? $ctx['dia'] : ''), 0, 4);
        $tentativa = $t['season'] !== '' ? $t['season'] : (string) ($anio - 1);

        $html = '<h2>Cómo completarlo</h2>'
            . '<p class="sub">Al torneo «' . e($nombre) . '» le falta su <b>id de competencia</b> de '
            . 'Transfermarkt. Está en la URL de la competencia en TM, y la forma más corta de llegar es '
            . 'por el fixture de uno de estos dos clubes en esa temporada:</p><ol class="sub">';

        $links = '';

        foreach ((isset($ctx['clubes']) ? $ctx['clubes'] : []) as $c) {
            if (empty($c['tm'])) {
                continue;
            }

            $links .= ($links !== '' ? ' · ' : '')
                . '<a href="' . e(\App\Services\Controles::TM_CLUB_TEMPORADA . $c['tm']
                    . '/saison_id/' . $tentativa) . '" target="_blank" rel="noopener">'
                . e((string) $c['nombre']) . ' en TM →</a>';
        }

        $html .= $links !== ''
            ? '<li>' . $links . ' <span class="gris">(temporada ' . e($tentativa) . ', tentativa: '
                . 'si el partido del ' . e((string) $ctx['dia']) . ' no aparece ahí, cambiala en el '
                . 'desplegable «Season» de esa misma página)</span></li>'
            : '<li>Buscá el torneo en Transfermarkt. <span class="gris">No te puedo dar el link directo: '
                . 'ninguno de los dos equipos está atado a un club de TM en <code>equipo_tm</code>.</span></li>';

        $html .= '<li>Ubicá ahí la fila de este partido y hacé clic en el <b>nombre de la competencia</b>. '
                . 'De esa URL salen los dos datos: '
                . '<code>.../wettbewerb/<b>ARGC</b>/...?saison_id=<b>2024</b></code> — '
                . '<code>wettbewerb</code> es el <b>id de competencia</b> y <code>saison_id</code> es el '
                . '<b>seasonId</b>, tal cual, sin convertir nada.</li>'
            . '<li>Pegalos en <a href="' . e(route('torneos.edit', $t['id'])) . '">'
                . 'Editar «' . e($nombre) . '» → transfermarkt.com</a> y volvé a apretar el botón acá.</li>'
            . '</ol>'
            . '<p class="sub">Ojo con dos cosas: el <b>seasonId va uno atrás del año</b> que usás vos '
            . '(el Clausura 2026 es seasonId 2025), y en TM el <b>Apertura y el Clausura son competencias '
            . 'distintas</b>, con ids distintos, aunque sean del mismo año.</p>';

        return $html;
    }

    /**
     * El calendario de un club en una temporada, leído del HTML del sitio.
     *
     * **Una llamada trae todos los partidos de ese club en esa temporada**, con
     * su gameId. Existe porque la API no puede: once formas probadas en
     * sep-2026 —incluidas `/club/{id}/fixtures?seasonId=` y `?saison_id=`—
     * devuelven siempre la temporada en curso. El sitio sí tiene el pasado.
     *
     * Un crédito por club-temporada contra uno por partido de la búsqueda
     * automática, y contra pegar la URL a mano de a una.
     *
     * **Sólo escribe filas de partidos que YA están en la base.** Los que TM
     * tiene y nosotros no se listan, pero no se guardan: una fila «nueva» en el
     * staging la puede tomar el importador de fixture y crear un partido
     * duplicado. Acá el objetivo es resolverle el gameId a lo que ya existe.
     */
    public function clubHtml(Request $request)
    {
        set_time_limit(0);

        $clubTm  = trim((string) $request->get('club_tm', ''));
        $season  = trim((string) $request->get('season', ''));
        $guardar = (string) $request->get('guardar', '0') === '1';

        $clubes = DB::table('equipo_tm')
            ->join('equipos', 'equipos.id', '=', 'equipo_tm.equipo_id')
            ->whereNotNull('equipo_tm.tm_club_id')
            ->orderBy('equipos.nombre')
            ->get(['equipo_tm.tm_club_id', 'equipo_tm.equipo_id', 'equipos.nombre']);

        $opciones = '<option value="">— elegí un club —</option>';
        foreach ($clubes as $c) {
            $opciones .= '<option value="' . e($c->tm_club_id) . '"'
                . ((string) $c->tm_club_id === $clubTm ? ' selected' : '') . '>'
                . e($c->nombre . '  ·  TM ' . $c->tm_club_id) . '</option>';
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Calendario de un club, del HTML de Transfermarkt</h1>'
            . '<p class="sub">Trae <b>todos los partidos de un club en una temporada</b> con su gameId, en '
            . '<b>una sola llamada</b>. Es el único camino que llega a las temporadas cerradas: las rutas de la '
            . 'API devuelven siempre la temporada en curso, aunque se les pida otra (probado con once formas '
            . 'distintas). Lo que se lee acá es la misma página que ves vos en el navegador.</p>'
            . '<p class="sub"><b>La temporada va uno atrás del año</b>, igual que en el resto del importador: '
            . 'el Clausura 2025 es <code>2024</code>.</p>'
            . '<form method="get" class="acciones">'
            . '<select name="club_tm" class="s2" data-placeholder="buscá el club…">' . $opciones . '</select> '
            . '<input type="text" name="season" value="' . e($season) . '" size="8" placeholder="ej 2024" required> '
            . '<button class="boton" type="submit">Leer el calendario</button>'
            . ' <span class="sub">1 crédito</span></form>';

        if ($clubTm === '' || $season === '') {
            return $this->pagina('Calendario del club', $cuerpo
                . '<p class="sub">Elegí un club y una temporada. Los clubes de la lista son los que ya están '
                . 'atados a Transfermarkt en <code>equipo_tm</code>.</p>'
                . '<p class="sub">Si lo que querés destrabar es un <b>torneo entero</b> —sobre todo uno '
                . 'internacional, donde los clubes no están atados— conviene '
                . '<a href="' . e(route('import_detalles.competencia_html')) . '">Calendario de una '
                . 'competencia</a>: una llamada trae todos los partidos y de paso propone los mapeos que '
                . 'faltan en <code>equipo_tm</code>.</p>');
        }

        $svc   = new \App\Services\TmFixtureClubHtml;
        $filas = $svc->leer($clubTm, $season);

        $cuerpo .= '<p class="sub">Página leída: <a href="' . e(\App\Services\TmFixtureClubHtml::url($clubTm, $season))
            . '" target="_blank" rel="noopener">verla en Transfermarkt</a></p>';

        foreach ($svc->avisos as $a) {
            $cuerpo .= '<div class="err-box">' . e($a) . '</div>';
        }

        if ($filas === null) {
            return $this->pagina('Calendario del club', $cuerpo);
        }

        // Mapa de clubes de TM a equipos nuestros: sin el rival mapeado el
        // apareo se hace igual, pero exigiendo un único candidato.
        $mapa      = DB::table('equipo_tm')->whereNotNull('tm_club_id')->pluck('equipo_id', 'tm_club_id')->all();
        $equipoId  = isset($mapa[$clubTm]) ? (int) $mapa[$clubTm] : 0;

        $ids       = array_values(array_filter(array_column($filas, 'game_id')));
        $enStaging = $ids
            ? DB::table('import_partidos')->whereIn('external_id', $ids)
                ->pluck('partido_id', 'external_id')->all()
            : [];

        $cont = ['leidos' => count($filas), 'ya' => 0, 'nuevos' => 0, 'sin' => 0, 'guardados' => 0];
        $tabla = '';

        foreach ($filas as $f) {
            $rivalId = (!empty($f['rival_tm']) && isset($mapa[$f['rival_tm']])) ? (int) $mapa[$f['rival_tm']] : 0;
            $yaEsta  = array_key_exists($f['game_id'], $enStaging) && $enStaging[$f['game_id']];

            $partidoId = 0;
            $motivo    = '';

            if ($yaEsta) {
                $partidoId = (int) $enStaging[$f['game_id']];
                $cont['ya']++;
                $motivo = 'ya lo tenía';
            } else {
                list($partidoId, $motivo) = $this->partidoDeFila($f, $equipoId, $rivalId);

                if ($partidoId) {
                    $cont['nuevos']++;

                    if ($guardar) {
                        $ok = (new TmBuscarGameId)->anotar($partidoId, $f['game_id'],
                            'calendario del club ' . $clubTm . ' temporada ' . $season . ' (HTML de Transfermarkt)');
                        if ($ok) $cont['guardados']++;
                    }
                } else {
                    $cont['sin']++;
                }
            }

            $clase = $partidoId ? ($yaEsta ? '' : 'warn') : '';
            $tabla .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e((string) ($f['dia'] ?: $f['dia_crudo'])) . '</td>'
                . '<td>' . e((string) $f['competencia']) . '</td>'
                . '<td>' . ($f['local'] === null ? '—' : ($f['local'] ? 'local' : 'visitante')) . '</td>'
                . '<td>' . e((string) $f['rival_nombre'])
                    . ($rivalId ? '' : ' <span class="err">(sin atar en equipo_tm)</span>') . '</td>'
                . '<td class="num">' . e((string) $f['resultado']) . '</td>'
                . '<td class="num gris">' . e((string) $f['game_id']) . '</td>'
                . '<td>' . ($partidoId
                    ? '<a href="' . e(route('import_detalles.ver', ['partido_id' => $partidoId])) . '">partido #'
                        . $partidoId . '</a>'
                    : '<span class="sub">' . e($motivo) . '</span>') . '</td>'
                . '</tr>';
        }

        $cuerpo .= '<div class="cards">'
            . $this->card($cont['leidos'], 'partidos en la página')
            . $this->card($cont['ya'], 'ya tenían gameId', 'ok')
            . $this->card($cont['nuevos'], $guardar ? 'apareados' : 'para guardar', $cont['nuevos'] ? 'warn' : '')
            . $this->card($cont['sin'], 'sin partido en tu base')
            . ($guardar ? $this->card($cont['guardados'], 'guardados', 'ok') : '')
            . '</div>';

        if ($svc->descartadas) {
            $cuerpo .= '<p class="sub">Se descartaron <b>' . $svc->descartadas . '</b> filas sin fecha.</p>';
        }

        if ($guardar) {
            $cuerpo .= '<div class="ok-box">Guardados <b>' . $cont['guardados'] . '</b> gameId. '
                . 'Esos partidos ya se pueden bajar como cualquier otro.</div>';
        } elseif ($cont['nuevos']) {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.club_html', ['club_tm' => $clubTm, 'season' => $season, 'guardar' => 1]))
                . '">Guardar los ' . $cont['nuevos'] . ' gameId</a> '
                . '<span class="sub">vuelve a leer la página, así que cuesta 1 crédito más</span></p>';
        } else {
            $cuerpo .= '<div class="ok-box"><b>No se escribió nada.</b> No hay ningún partido nuevo para atar '
                . 'en esta temporada de este club.</div>';
        }

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Día</th><th>Competencia</th><th>Localía</th>'
            . '<th>Rival en TM</th><th>Res.</th><th>gameId</th><th>Partido tuyo</th></tr></thead><tbody>'
            . $tabla . '</tbody></table></div>'
            . '<p class="sub">Las filas resaltadas son las que se van a guardar. Las que dicen <b>ya lo tenía</b> '
            . 'no se tocan. Y las que no aparean con ningún partido tuyo <b>no se guardan</b>: acá no se crean '
            . 'partidos, sólo se le pone el gameId a los que ya existen.</p>';

        return $this->pagina('Calendario del club', $cuerpo);
    }

    /**
     * Fichas cuyo nombre no está escrito en alfabeto latino, y su reparación.
     *
     * Nació de un desastre concreto: bajando Pyramids FC – Auckland City se
     * crearon 22 fichas con el nombre en árabe y una en chino. La causa estaba
     * en `NombreHelper::separarTM()`, que tomaba el `passportName` de TM —el
     * nombre legal, que para varios países viene en el alfabeto original— con
     * prioridad sobre todo lo demás. Ya está arreglado, **pero arreglarlo no
     * repara lo que ya se cargó**: `jugador_tm` mapea esos ids de TM a esas
     * fichas, así que volver a bajar el partido las reusa tal cual.
     *
     * Esto vuelve a leer el perfil de TM de cada una —de a 50, una llamada cada
     * 50— y las renombra con la lógica corregida. Sólo escribe cuando el nombre
     * nuevo **sí** está en latino: si TM no tiene ninguna forma latina de esa
     * persona, se deja como está y se lista aparte para verla a mano.
     */
    public function nombresAlfabeto(Request $request)
    {
        set_time_limit(0);

        $aplicar  = (string) $request->get('aplicar', '0') === '1';
        $escanear = (string) $request->get('escanear', '0') === '1';

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Nombres en otro alfabeto</h1>'
            . '<p class="sub">Fichas cuyo nombre no tiene <b>ni una letra latina</b>: quedaron así porque el '
            . 'importador tomaba el <code>passportName</code> de Transfermarkt, que para varios países viene en '
            . 'el alfabeto original. En un sitio en castellano son ilegibles, no se pueden buscar ni ordenar, y '
            . 'se duplican sin que nadie lo note.</p>'
            . '<p class="sub">El importador ya está corregido —ahora elige la primera forma escrita en latino—, '
            . 'pero eso no repara las fichas que ya existen: <code>jugador_tm</code> las tiene mapeadas, así que '
            . 'volver a bajar el partido las reusa. Acá se releen los perfiles y se renombran.</p>';

        $rotas = $this->personasSinLatino($escanear);

        if (!$rotas) {
            return $this->pagina('Nombres en otro alfabeto', $cuerpo
                . '<div class="ok-box">No hay ninguna ficha con el nombre o el apellido en otro alfabeto'
                . ($escanear ? ' (mirando la tabla entera)' : '') . '.</div>'
                . ($escanear ? '' : '<p class="sub">La búsqueda usa un filtro en SQL para no recorrer toda la '
                    . 'tabla. Si sabés que hay fichas rotas y acá no aparecen, <a href="'
                    . e(route('import_detalles.nombres_alfabeto', ['escanear' => 1]))
                    . '">mirá la tabla entera</a> — es más lento pero no depende de cómo se porte el '
                    . 'REGEXP del motor.</p>'));
        }

        // El id de TM sale de jugador_tm; sin él no hay a quién releerle el perfil.
        $ids     = array_keys($rotas);
        $tmPorId = [];

        foreach (DB::table('jugador_tm')
                     ->join('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
                     ->whereIn('jugadors.persona_id', $ids)
                     ->get(['jugadors.persona_id', 'jugador_tm.tm_player_id']) as $r) {
            $tmPorId[(int) $r->persona_id] = (string) $r->tm_player_id;
        }

        $conTm = array_values(array_filter($tmPorId));
        $sinTm = array_diff($ids, array_keys($tmPorId));

        $cuerpo .= '<div class="cards">'
            . $this->card(count($rotas), 'fichas en otro alfabeto', 'warn')
            . $this->card(count($conTm), 'con ficha de TM para releer')
            . $this->card(count($sinTm), 'sin id de TM', count($sinTm) ? 'err' : '')
            . '</div>';

        $perfiles = [];
        $llamadas = 0;

        if ($conTm) {
            // Los perfiles se cachean 10 minutos: mirar y después aplicar no
            // tiene que pagar dos veces la misma tanda.
            $llave = 'tm.perfiles.reparar.' . md5(implode(',', $conTm));

            if (\Illuminate\Support\Facades\Cache::has($llave)) {
                $perfiles = \Illuminate\Support\Facades\Cache::get($llave);
            } else {
                $informe  = ['llamadas' => 0];
                $perfiles = (new TmDetallePartido)->traerPerfiles($conTm, $informe);
                $llamadas = (int) $informe['llamadas'];
                \Illuminate\Support\Facades\Cache::put($llave, $perfiles, 600);
            }
        }

        $cambiados = 0;
        $sinLatino = 0;
        $tabla     = '';

        foreach ($rotas as $personaId => $vieja) {
            $tm     = isset($tmPorId[$personaId]) ? $tmPorId[$personaId] : null;
            $perfil = ($tm !== null && isset($perfiles[$tm])) ? $perfiles[$tm] : null;

            $nuevo  = null;
            $estado = '';

            if ($tm === null) {
                $estado = 'no tiene id de Transfermarkt: hay que corregirla a mano';
            } elseif ($perfil === null) {
                $estado = 'Transfermarkt no devolvió su perfil';
            } else {
                $d = \App\Services\NombreHelper::separarTM($perfil);

                // Sólo se escribe si lo nuevo ESTÁ en latino. Si TM no tiene
                // ninguna forma latina de esa persona, cambiar un nombre en
                // árabe por otro en árabe no arregla nada y encima pisa.
                if ($this->tieneLatino($d['apellido']) || $this->tieneLatino($d['nombre'])) {
                    $nuevo = $d;
                } else {
                    $sinLatino++;
                    $estado = 'Transfermarkt tampoco lo tiene en latino';
                }
            }

            if ($nuevo && $aplicar) {
                DB::table('personas')->where('id', $personaId)->update([
                    'name'       => $nuevo['name'],
                    'nombre'     => $nuevo['nombre'],
                    'apellido'   => $nuevo['apellido'],
                    'updated_at' => now(),
                ]);
                $cambiados++;
                $estado = 'renombrada';
            } elseif ($nuevo) {
                $cambiados++;
                $estado = 'lista para renombrar';
            }

            $tabla .= '<tr class="' . ($nuevo ? 'warn' : '') . '">'
                . '<td class="num gris">#' . (int) $personaId . '</td>'
                . '<td>' . e(trim($vieja['apellido'] . ', ' . $vieja['nombre']))
                    . ($vieja['name'] !== '' ? ' <span class="sub">· name: ' . e($vieja['name']) . '</span>' : '')
                    . '</td>'
                . '<td class="num gris">' . e((string) $tm) . '</td>'
                . '<td>' . ($nuevo ? '<b>' . e(trim($nuevo['apellido'] . ', ' . $nuevo['nombre'])) . '</b>' : '—') . '</td>'
                . '<td>' . e($estado) . '</td>'
                . '</tr>';
        }

        if ($llamadas) {
            $cuerpo .= '<p class="sub">' . $llamadas . ' llamada(s) a la API para releer los perfiles.</p>';
        }

        if ($aplicar) {
            $cuerpo .= '<div class="ok-box">Renombré <b>' . $cambiados . '</b> ficha(s).</div>'
                . '<p class="sub">Ojo con una consecuencia: si alguna de estas personas <b>ya existía</b> con su '
                . 'nombre en latino, ahora hay dos fichas con el mismo nombre. Pasá por '
                . '<a href="' . e(route('jugadores.verificarPersonas')) . '">verificar personas</a> a fusionarlas.</p>';
        } elseif ($cambiados) {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.nombres_alfabeto', ['aplicar' => 1])) . '">Renombrar las '
                . $cambiados . '</a> <span class="sub">no gasta llamadas nuevas: los perfiles quedaron '
                . 'cacheados 10 minutos</span></p>';
        } else {
            $cuerpo .= '<div class="err-box">No pude conseguir una forma latina para ninguna. '
                . 'Estas hay que corregirlas a mano.</div>';
        }

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Ficha</th><th>Como está</th><th>id TM</th>'
            . '<th>Como quedaría</th><th>Estado</th></tr></thead><tbody>'
            . $tabla . '</tbody></table></div>';

        return $this->pagina('Nombres en otro alfabeto', $cuerpo);
    }

    /** ¿El texto tiene al menos una letra latina? */
    private function tieneLatino($s)
    {
        return (string) $s !== '' && preg_match('/\p{Latin}/u', (string) $s);
    }

    /**
     * Las personas con el nombre o el apellido escritos en otro alfabeto.
     *
     * **Se miran `nombre` y `apellido`, NO `name`.** Es el error que tuvo la
     * primera versión y por el que la pantalla decía «no hay ninguna» teniendo
     * 22 adelante: metía las tres columnas en una sola bolsa, y `name` guarda
     * el `shortName` de TM, que en estos casos sí venía en latino
     * («A. El Shenawy»). Con eso alcanzaba para que la ficha pareciera sana,
     * cuando lo roto es justamente lo que se muestra en todos lados —el
     * apellido y el nombre—. Que `name` esté en latino no es la prueba de que
     * está bien: es la prueba de que TM tenía la forma buena y no se usó.
     *
     * Alcanza con que **una** de las dos esté en otro alfabeto: media ficha en
     * árabe también está rota, y así se cubren las mezclas.
     *
     * El prefiltro en SQL es por letras ASCII —cualquier nombre latino de
     * verdad tiene al menos una— y después se confirma en PHP buscando
     * caracteres de otros alfabetos. Si el motor no soporta REGEXP, se barre la
     * tabla por tandas: es una pantalla de reparación, no una de todos los días.
     *
     * @return array persona_id => ['nombre' => .., 'apellido' => .., 'name' => ..]
     */
    private function personasSinLatino($escanear = false)
    {
        $otroAlfabeto = '/[\p{Arabic}\p{Hebrew}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}'
            . '\p{Cyrillic}\p{Greek}\p{Thai}\p{Devanagari}\p{Armenian}\p{Georgian}]/u';

        $out       = [];
        $confirmar = function ($p) use (&$out, $otroAlfabeto) {
            $partes = trim((string) $p->nombre . ' ' . (string) $p->apellido);

            if ($partes !== '' && preg_match($otroAlfabeto, $partes)) {
                $out[(int) $p->id] = [
                    'nombre'   => (string) $p->nombre,
                    'apellido' => (string) $p->apellido,
                    'name'     => (string) $p->name,
                ];
            }
        };

        // El escaneo completo es la red por si el REGEXP del motor no se
        // comporta como se espera: es lo que pasó una vez y dejó la pantalla
        // diciendo «no hay ninguna» con 22 adelante.
        if (!$escanear) {
            try {
                $filas = DB::table('personas')
                    ->where(function ($q) {
                        $q->whereRaw("COALESCE(nombre,'') NOT REGEXP '[a-zA-Z]'")
                          ->orWhereRaw("COALESCE(apellido,'') NOT REGEXP '[a-zA-Z]'");
                    })
                    ->limit(500)
                    ->get(['id', 'name', 'nombre', 'apellido']);

                foreach ($filas as $p) $confirmar($p);

                return $out;
            } catch (\Throwable $e) {
                // Sin REGEXP: se barre por tandas.
            }
        }

        DB::table('personas')->select('id', 'name', 'nombre', 'apellido')
            ->orderBy('id')->chunk(2000, function ($filas) use ($confirmar) {
                foreach ($filas as $p) $confirmar($p);
            });

        return $out;
    }

    /**
     * El calendario de una COMPETENCIA entera, del HTML de Transfermarkt.
     *
     * Una llamada = el torneo completo. Para el Mundial de Clubes 2025 son 63
     * partidos en una página; hacerlo club por club serían 32 llamadas y 32
     * clubes para atar a mano.
     *
     * Y como la página trae el **nombre** de cada club además del id, la misma
     * pasada propone los mapeos que faltan en `equipo_tm`. Eso es lo que
     * destraba los torneos internacionales: ahí el problema casi nunca es
     * encontrar el partido, es que los clubes no están atados.
     *
     * Las dos reglas de siempre: sólo escribe gameId de partidos que YA están
     * en la base, y con más de un candidato en la ventana de fechas no elige.
     */
    public function competenciaHtml(Request $request)
    {
        set_time_limit(0);

        $compId  = trim((string) $request->get('comp_id', ''));
        $season  = trim((string) $request->get('season', ''));
        $copa    = (string) $request->get('copa', '0') === '1';
        $guardar = (string) $request->get('guardar', '0') === '1';
        $mapear  = (string) $request->get('mapear', '0') === '1';

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Calendario de una competencia, del HTML de Transfermarkt</h1>'
            . '<p class="sub">Trae <b>el torneo entero de una temporada</b> con el gameId de cada partido, en '
            . '<b>una sola llamada</b>. Es el camino para los torneos internacionales y para cualquier campeonato '
            . 'ya terminado: las rutas de la API devuelven siempre la temporada en curso.</p>'
            . '<p class="sub"><b>Ojo con el tipo.</b> En Transfermarkt las ligas y las copas viven en rutas '
            . 'distintas, no es un parámetro. Torneo Clausura es una <b>liga</b> (<code>ARGC</code>); '
            . 'Copa Argentina (<code>ARCA</code>), Libertadores (<code>CLI</code>) y Mundial de Clubes '
            . '(<code>KLUB</code>) son <b>copas</b>. Si elegís mal, la página vuelve vacía.</p>'
            . '<p class="sub">El id de competencia lo podés sacar de la columna «Competencia» de '
            . '<a href="' . e(route('import_detalles.club_html')) . '">Calendario de un club</a>, o de '
            . '<code>import_partidos.competencia_external_id</code>. <b>La temporada va uno atrás del año</b>: '
            . 'todo lo de 2025 es <code>2024</code>.</p>'
            . '<form method="get" class="acciones">'
            . '<input type="text" name="comp_id" value="' . e($compId) . '" size="10" placeholder="ej KLUB" required> '
            . '<select name="copa" class="s2" data-placeholder="liga o copa">'
            . '<option value="0"' . (!$copa ? ' selected' : '') . '>liga</option>'
            . '<option value="1"' . ($copa ? ' selected' : '') . '>copa</option>'
            . '</select> '
            . '<input type="text" name="season" value="' . e($season) . '" size="8" placeholder="ej 2024" required> '
            . '<button class="boton" type="submit">Leer el calendario</button>'
            . ' <span class="sub">1 crédito</span></form>';

        if ($compId === '' || $season === '') {
            return $this->pagina('Calendario de la competencia', $cuerpo);
        }

        $svc   = new \App\Services\TmFixtureCompetenciaHtml;
        $filas = $svc->leerComp($compId, $season, $copa);

        $cuerpo .= '<p class="sub">Página leída: <a href="'
            . e(\App\Services\TmFixtureCompetenciaHtml::urlComp($compId, $season, $copa))
            . '" target="_blank" rel="noopener">verla en Transfermarkt</a></p>';

        foreach ($svc->avisos as $a) {
            $cuerpo .= '<div class="err-box">' . e($a) . '</div>';
        }

        if ($filas === null || !$filas) {
            return $this->pagina('Calendario de la competencia', $cuerpo);
        }

        $mapeo     = new \App\Services\MapeoClubesTm;
        $ids       = array_values(array_filter(array_column($filas, 'game_id')));
        $enStaging = DB::table('import_partidos')->whereIn('external_id', $ids)
            ->pluck('partido_id', 'external_id')->all();

        $cont      = ['leidos' => count($filas), 'ya' => 0, 'nuevos' => 0, 'sin' => 0, 'guardados' => 0];
        $porAtar   = [];   // tm_club_id => ['nombre' => .., 'equipo_id' => ..] reconocidos por NOMBRE
        $sinAtar   = [];   // tm_club_id => nombre, los que no se reconocieron
        $tabla     = '';

        foreach ($filas as $f) {
            $equipos = [];

            foreach ([['local_tm', 'local_nombre'], ['visita_tm', 'visita_nombre']] as $par) {
                $tm     = $f[$par[0]];
                $nombre = $f[$par[1]];
                $como   = null;
                $eq     = $mapeo->resolver($tm, $nombre, $como);

                if ($eq && $como === 'nombre' && !isset($porAtar[$tm])) {
                    // Reconocido por nombre pero sin fila en equipo_tm: candidato
                    // a aprender. No se guarda solo — se propone.
                    $porAtar[$tm] = ['nombre' => $nombre, 'equipo_id' => (int) $eq];
                }

                if (!$eq && !isset($sinAtar[$tm])) {
                    $sinAtar[$tm] = $nombre;
                }

                $equipos[] = (int) $eq;
            }

            $yaEsta    = array_key_exists($f['game_id'], $enStaging) && $enStaging[$f['game_id']];
            $partidoId = 0;
            $motivo    = '';

            if ($yaEsta) {
                $partidoId = (int) $enStaging[$f['game_id']];
                $cont['ya']++;
            } else {
                // Con uno solo de los dos clubes atado alcanza para intentar:
                // el apareo exige un único candidato en la ventana, así que no
                // se arriesga nada y se rescatan los partidos donde el rival
                // todavía no está mapeado.
                $eqA = $equipos[0];
                $eqB = $equipos[1];

                if (!$eqA && $eqB) {
                    $eqA = $eqB;
                    $eqB = 0;
                }

                list($partidoId, $motivo) = $this->partidoDeFila($f, $eqA, $eqB);

                if ($partidoId) {
                    $cont['nuevos']++;

                    if ($guardar) {
                        $ok = (new TmBuscarGameId)->anotar($partidoId, $f['game_id'],
                            'calendario de la competencia ' . $compId . ' temporada ' . $season
                            . ' (HTML de Transfermarkt)');
                        if ($ok) $cont['guardados']++;
                    }
                } else {
                    $cont['sin']++;
                }
            }

            $tabla .= '<tr class="' . ($partidoId && !$yaEsta ? 'warn' : '') . '">'
                . '<td class="num">' . e((string) ($f['dia'] ?: $f['dia_crudo'])) . '</td>'
                . '<td>' . e((string) $f['local_nombre']) . ' vs ' . e((string) $f['visita_nombre']) . '</td>'
                . '<td class="num">' . e((string) $f['resultado']) . '</td>'
                . '<td class="num gris">' . e((string) $f['game_id']) . '</td>'
                . '<td>' . ($partidoId
                    ? '<a href="' . e(route('import_detalles.ver', ['partido_id' => $partidoId])) . '">partido #'
                        . $partidoId . '</a>' . ($yaEsta ? ' <span class="sub">ya lo tenía</span>' : '')
                    : '<span class="sub">' . e($motivo) . '</span>') . '</td>'
                . '</tr>';
        }

        // ── Aprender los mapeos de clubes ───────────────────────────────────
        $aprendidos = 0;

        if ($mapear && $porAtar) {
            foreach ($porAtar as $tm => $d) {
                $mapeo->guardar($tm, $d['equipo_id'], $d['nombre'], 'calendario html');
                $aprendidos++;
            }

            $cuerpo .= '<div class="ok-box">Até <b>' . $aprendidos . '</b> clubes de Transfermarkt a equipos '
                . 'tuyos en <code>equipo_tm</code>. Volvé a leer el calendario: ahora deberían aparear más '
                . 'partidos.</div>';
            $porAtar = [];
        }

        $cuerpo .= '<div class="cards">'
            . $this->card($cont['leidos'], 'partidos del torneo')
            . $this->card($cont['ya'], 'ya tenían gameId', 'ok')
            . $this->card($cont['nuevos'], $guardar ? 'apareados' : 'para guardar', $cont['nuevos'] ? 'warn' : '')
            . $this->card($cont['sin'], 'sin partido en tu base')
            . $this->card(count($porAtar), 'clubes por atar', count($porAtar) ? 'warn' : '')
            . $this->card(count($sinAtar), 'clubes desconocidos', count($sinAtar) ? 'err' : '')
            . ($guardar ? $this->card($cont['guardados'], 'guardados', 'ok') : '')
            . '</div>';

        if ($svc->descartadas) {
            $cuerpo .= '<p class="sub">Se descartaron <b>' . $svc->descartadas . '</b> filas sin fecha o sin los '
                . 'dos clubes.</p>';
        }

        // ── Primero atar, después guardar ───────────────────────────────────
        // El orden importa: cada club que se ata hace aparear más partidos, así
        // que guardar antes de atar deja plata sobre la mesa.
        if ($porAtar) {
            $cuerpo .= '<h2>Clubes que reconocí por el nombre</h2>'
                . '<p class="sub">Estos clubes de Transfermarkt no están en <code>equipo_tm</code>, pero su '
                . 'nombre coincide sin ambigüedad con un equipo tuyo. <b>Revisalos antes de atarlos:</b> un club '
                . 'mal atado carga partidos con el rival cambiado. Los homónimos no aparecen acá — cuando dos '
                . 'equipos tuyos comparten nombre normalizado, el apareo por nombre se abstiene a propósito.</p>'
                . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th>'
                . '<th>Equipo tuyo</th></tr></thead><tbody>';

            foreach ($porAtar as $tm => $d) {
                $nombreEq = $this->nombreEquipo($d['equipo_id']);
                $cuerpo .= '<tr>'
                    . '<td>' . e((string) $d['nombre']) . '</td>'
                    . '<td class="num gris">' . e((string) $tm) . '</td>'
                    . '<td>' . e((string) $nombreEq) . ' <span class="sub">#' . $d['equipo_id'] . '</span></td>'
                    . '</tr>';
            }

            $cuerpo .= '</tbody></table></div>'
                . '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.competencia_html',
                    ['comp_id' => $compId, 'season' => $season, 'copa' => $copa ? 1 : 0, 'mapear' => 1]))
                . '">Atar los ' . count($porAtar) . ' clubes</a> '
                . '<span class="sub">hacelo antes de guardar los gameId: cada club atado aparea más partidos</span></p>';
        }

        if ($sinAtar) {
            $cuerpo .= '<h2>Clubes que no reconocí</h2>'
                . '<p class="sub">No están en <code>equipo_tm</code> y su nombre no coincide con ningún equipo '
                . 'tuyo (o coincide con más de uno). Si el equipo existe en tu base con otro nombre, atalo a mano '
                . 'desde la carga de partidos; si no existe, los partidos de ese club no se van a poder aparear.</p>'
                . '<div class="diag">';

            foreach ($sinAtar as $tm => $nombre) {
                $cuerpo .= '<div>• ' . e((string) $nombre) . ' <span class="sub">— id de TM '
                    . e((string) $tm) . '</span> · <a href="'
                    . e(route('import_partidos.fixture', ['mapear_tm' => $tm, 'mapear_nombre' => $nombre]))
                    . '">atarlo a un equipo tuyo</a></div>';
            }

            $cuerpo .= '</div>';
        }

        if ($guardar) {
            $cuerpo .= '<div class="ok-box">Guardados <b>' . $cont['guardados'] . '</b> gameId.</div>';
        } elseif ($cont['nuevos']) {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.competencia_html',
                    ['comp_id' => $compId, 'season' => $season, 'copa' => $copa ? 1 : 0, 'guardar' => 1]))
                . '">Guardar los ' . $cont['nuevos'] . ' gameId</a> '
                . '<span class="sub">vuelve a leer la página, así que cuesta 1 crédito más</span></p>';
        } else {
            $cuerpo .= '<div class="ok-box"><b>No se escribió nada.</b> No hay ningún partido nuevo para atar '
                . 'en este torneo.</div>';
        }

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Día</th><th>Partido en TM</th><th>Res.</th>'
            . '<th>gameId</th><th>Partido tuyo</th></tr></thead><tbody>'
            . $tabla . '</tbody></table></div>'
            . '<p class="sub">Las filas resaltadas son las que se van a guardar. Acá <b>no se crean partidos</b>: '
            . 'los que Transfermarkt tiene y vos no se listan, pero no se escriben.</p>';

        return $this->pagina('Calendario de la competencia', $cuerpo);
    }

    /**
     * A qué partido de la base corresponde una fila del calendario de TM.
     *
     * Ventana de ±3 días como en el resto del importador: TM guarda la fecha
     * original de los postergados y la hora UTC puede correr el día. **Si queda
     * más de un candidato no elige ninguno**: un gameId equivocado escribe la
     * alineación de otro partido y después no se nota.
     */
    private function partidoDeFila(array $f, $equipoId, $rivalId)
    {
        if (empty($f['dia'])) {
            return [0, 'no pude leerle la fecha'];
        }

        if (!$equipoId) {
            return [0, 'el club no está atado en equipo_tm'];
        }

        $desde = date('Y-m-d', strtotime($f['dia'] . ' -3 days'));
        $hasta = date('Y-m-d', strtotime($f['dia'] . ' +3 days'));

        $q = DB::table('partidos')->whereDate('dia', '>=', $desde)->whereDate('dia', '<=', $hasta);

        if ($rivalId) {
            $q->where(function ($w) use ($equipoId, $rivalId) {
                $w->where(function ($x) use ($equipoId, $rivalId) {
                    $x->where('equipol_id', $equipoId)->where('equipov_id', $rivalId);
                })->orWhere(function ($x) use ($equipoId, $rivalId) {
                    $x->where('equipol_id', $rivalId)->where('equipov_id', $equipoId);
                });
            });
        } else {
            $q->where(function ($w) use ($equipoId) {
                $w->where('equipol_id', $equipoId)->orWhere('equipov_id', $equipoId);
            });
        }

        $ids = $q->pluck('id')->all();

        if (count($ids) === 1) {
            return [(int) $ids[0], ''];
        }

        if (count($ids) > 1) {
            return [0, 'hay ' . count($ids) . ' partidos posibles en esa ventana: no elijo'];
        }

        return [0, $rivalId ? 'no está en tu base' : 'no está en tu base (y el rival no está atado)'];
    }

    /**
     * El gameId que hay adentro de lo que el usuario pegó.
     *
     * Acepta el número pelado o la URL de la ficha del partido. **No adivina:**
     * si no puede identificarlo con seguridad devuelve vacío, y quien llama lo
     * dice. Un gameId equivocado escribe la alineación de OTRO partido encima
     * del que se estaba mirando, y eso después no se nota.
     *
     * El caso que obliga a tener cuidado es `?saison_id=2024`: si se toma
     * cualquier número de la URL, un año de cuatro dígitos pasa por gameId.
     */
    private function gameIdDesde($texto)
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $texto)) {
            return $texto;
        }

        // La URL de la ficha lo dice explícitamente: es la lectura confiable.
        if (preg_match('#spielbericht/(\d+)#i', $texto, $m)) {
            return $m[1];
        }

        // Si no, sólo un número largo, y sólo si es el único: un gameId tiene
        // siete dígitos y un año cuatro, pero ante el empate no se elige.
        if (preg_match_all('/\d{6,}/', $texto, $m)) {
            $unicos = array_values(array_unique($m[0]));

            if (count($unicos) === 1) {
                return $unicos[0];
            }
        }

        return '';
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
        // lo encuentra con lo que ya tenemos cargado (el staging, el fixture de
        // los dos clubes, los partidos de los DTs y el fixture de la competencia
        // del torneo) y lo deja anotado: a partir de ahí el partido entra solo
        // en la lista de arriba. OJO: en un campeonato ya terminado la única
        // fuente que llega es la de los DTs — la API sólo sirve la temporada en
        // curso. Ver la memoria [[buscar-gameid]].
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
        $yaBuscados  = $this->sinGameIdPendientes()->distinct()->count('ipx.partido_id');

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
                        . ($cuantos ? 'elegir a mano' : 'ver por qué y cargarlo a mano') . '</a></div>';
                    foreach ((isset($r['avisos']) ? $r['avisos'] : []) as $a) {
                        $detalleIds .= '<div class="sub" style="margin-left:18px">• ' . e($a) . '</div>';
                    }
                }
            }

            // Los que aparecieron ya son parte de la lista de arriba.
            $porBuscar  = (clone $sinGameId())->count();
            $pendientes = (clone $base())->distinct()->count('partido_id');
            $yaBuscados = $this->sinGameIdPendientes()->distinct()->count('ipx.partido_id');
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
            . '<p class="sub">La búsqueda no adivina: cruza la fecha y los equipos que ya tenés cargados contra '
            . 'cuatro fuentes, en orden — el <b>staging</b> (gratis, por si el fixture ya se bajó alguna vez), el '
            . '<b>fixture de los dos clubes</b>, los <b>partidos de los DTs</b> y el <b>fixture de la competencia</b> '
            . 'del torneo. Si en la ventana de fechas queda más de un candidato <b>no elige ninguno</b> y te los '
            . 'ofrece para que elijas vos: un gameId equivocado escribiría el detalle de otro partido.</p>'
            . '<p class="sub"><b>Los partidos viejos son el caso difícil.</b> Todas las rutas de fixture de la API '
            . 'devuelven la temporada <b>en curso</b> e ignoran el año que se les pide (once formas probadas), así '
            . 'que de la API la única fuente que llega al pasado es la de los DTs. Para un campeonato terminado '
            . 'conviene, antes de la tanda, o bien tener cargada la URL de Transfermarkt de los DTs, o bien pasar '
            . 'por <a href="' . e(route('import_detalles.club_html')) . '">Calendario de un club</a>, que lee la '
            . 'temporada entera del sitio en una sola llamada.</p>'
            . '<p class="sub">Cuesta <b>1 a 4 llamadas por partido</b>, y bastante menos cuando son del mismo club '
            . 'o el mismo DT: cada lista se reusa 10 minutos. El que aparece queda anotado para siempre y pasa solo '
            . 'a la lista de arriba (y de paso le sirve al resto del importador: detalle, penales, resultados). '
            . 'El que no aparece queda marcado para <b>no volver a pagarlo</b> en cada tanda.</p>';

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
            $cuerpo .= $this->listaSinGameId($yaBuscados);
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
    /**
     * Los partidos marcados como "lo busqué y no apareció" que SIGUEN sin gameId.
     *
     * La marca existe para no volver a pagar la búsqueda en cada tanda, así que
     * no se borra nunca. Pero el partido se puede haber resuelto después por
     * otro camino —el calendario del club, o pegando la URL a mano— y entonces
     * seguir listándolo es mandar a trabajar sobre algo que ya está hecho. El
     * LEFT JOIN contra una fila con `external_id` saca a esos.
     */
    private function sinGameIdPendientes()
    {
        return DB::table('import_partidos as ipx')
            ->join('partidos', 'partidos.id', '=', 'ipx.partido_id')
            ->leftJoin('import_partidos as ipg', function ($j) {
                $j->on('ipg.partido_id', '=', 'ipx.partido_id')->whereNotNull('ipg.external_id');
            })
            ->whereNotNull('ipx.partido_id')
            ->whereNull('ipx.external_id')
            ->where('ipx.estado', 'excluido')
            ->where('ipx.motivo', 'like', 'sin gameId%')
            ->whereNull('ipg.id');
    }

    /**
     * Los partidos que ya se buscaron sin suerte, con su salida al lado.
     *
     * Antes esto era un número suelto y una instrucción para escribir una URL a
     * mano: el residuo de cada tanda quedaba ahí y no se trabajaba nunca. Son
     * pocos —dos de cincuenta en el Clausura 2025— y cada uno se resuelve en un
     * clic, así que lo que hace falta es la lista, no el conteo.
     *
     * El consejo también cambió: **`equipo_tm` no sirve para un campeonato ya
     * terminado.** Todas las rutas de fixture de la API devuelven la temporada
     * en curso (verificado sep-2026), así que atar los clubes no cambia nada en
     * un partido viejo. Lo que va hacia atrás en el tiempo son los partidos del
     * DT, y si no, pegar la URL.
     */
    private function listaSinGameId($total)
    {
        $filas = $this->sinGameIdPendientes()
            ->orderByDesc('partidos.dia')
            ->limit(40)
            ->get(['ipx.partido_id', 'ipx.motivo']);

        $html = '<h3>Ya buscados y sin gameId <span class="sub">(' . (int) $total . ')</span></h3>'
            . '<p class="sub">Quedaron marcados y no se vuelven a intentar solos. '
            . '<b>Si el partido es de un campeonato ya terminado, atar los clubes en <code>equipo_tm</code> '
            . 'no cambia nada</b>: todas las rutas de fixture de Transfermarkt devuelven la temporada en curso. '
            . 'Lo único que va hacia atrás en el tiempo son los partidos del DT — cargale la URL de TM a los '
            . 'que dirigieron ese partido y volvé a entrar acá — o entrá al partido y pegá ahí la URL de '
            . 'Transfermarkt, que lo resuelve de una y para siempre.</p>'
            . '<p class="sub"><b>Atajos, que leen del sitio y no de la API:</b> '
            . '<a href="' . e(route('import_detalles.club_html')) . '">Calendario de un club</a> trae todos los '
            . 'partidos de un club en una temporada con su gameId, en <b>una sola llamada</b> — sirve cuando los '
            . 'que faltan se repiten entre pocos clubes. '
            . '<a href="' . e(route('import_detalles.competencia_html')) . '">Calendario de una competencia</a> '
            . 'trae el <b>torneo entero</b>, también en una llamada, y encima propone los mapeos de '
            . '<code>equipo_tm</code> que falten: es el que hay que usar en los torneos internacionales, donde '
            . 'lo que falla no es encontrar el partido sino que los clubes no están atados.</p>'
            . '<div class="diag">';

        foreach ($filas as $f) {
            $id = (int) $f->partido_id;

            $html .= '<div><span class="warn">?</span> ' . $this->resumenPartido($id)
                . ' <span class="sub">' . e((string) $f->motivo) . '</span>'
                . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => $id]))
                . '">ver por qué y cargarlo a mano</a></div>';
        }

        $html .= '</div>';

        if ($total > count($filas)) {
            $html .= '<p class="sub">Se listan los ' . count($filas) . ' más nuevos de ' . (int) $total . '.</p>';
        }

        return $html;
    }

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
            . '<select name="tipo" class="s2" data-placeholder="qué tipo de ficha">' . $opts . '</select> '
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
            . 'dan 404, y que <code>/competition/{id}/fixtures?seasonId=</code> <b>ignora la temporada</b>: '
            . 'pedida la 2024 de ARGC devolvió el Clausura 2026 (verificado sep-2026).<br>'
            . '<b>La pregunta abierta es si hay alguna forma de pedir una temporada pasada.</b> El sitio la tiene '
            . '(<code>transfermarkt.es/cd-riestra/spielplan/verein/19775/saison_id/2024</code> lista el Clausura '
            . '2025 entero), así que acá se prueban todas las formas de la ruta del <b>club con temporada</b>, que '
            . 'nunca se probaron. Si alguna contesta la temporada pedida, los partidos viejos se pueden traer solos; '
            . 'si no, hay que cargarlos a mano de a uno.<br>'
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
            . '<th>Items</th><th>Temporadas TM</th><th>Período</th><th>Rama</th>'
            . '<th>Claves de primer nivel</th></tr></thead><tbody>';
        foreach ($r['rutas'] as $ruta => $info) {
            // Se resalta la que trae la temporada PEDIDA, no la que trae muchos
            // items: una ruta que contesta 240 partidos del año en curso no
            // sirve para un partido viejo, y es exactamente lo que venía
            // pasando sin que se notara.
            $laPedida = $season !== '' && in_array((string) $season, $info['temporadas'], true);

            $cuerpo .= '<tr' . ($laPedida ? ' class="warn"' : '') . '>'
                . '<td><code>' . e($ruta) . '</code></td>'
                . '<td>' . (!empty($info['ok']) ? '<span class="ok">sí</span>' : '<span class="err">no</span>') . '</td>'
                . '<td class="num">' . ((int) $info['items'] ?: '—') . '</td>'
                . '<td class="num">' . ($info['temporadas']
                    ? ($laPedida ? '<b class="ok">' : '<span class="gris">')
                        . e(implode(', ', $info['temporadas']))
                        . ($laPedida ? '</b>' : '</span>')
                    : '—') . '</td>'
                . '<td class="num">' . e($info['periodo'] ?: '—') . '</td>'
                . '<td>' . e($info['rama'] ?: '—') . '</td>'
                . '<td>' . e(implode(', ', $info['claves']) ?: '—') . '</td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>'
            . '<p class="sub"><b>La columna que decide es «Temporadas TM».</b> Sirve la ruta que devuelve la '
            . 'temporada que le pediste' . ($season !== '' ? ' (<b>' . e($season) . '</b>)' : '')
            . ', no la que devuelve más items: contestar 240 partidos del año en curso cuando pediste el anterior '
            . 'es contestar que no sabe. La columna <b>Período</b> lo confirma con las fechas reales, y '
            . '<b>Rama</b> dice de qué clave cuelga la lista.</p>';

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
