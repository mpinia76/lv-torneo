<?php

namespace App\Http\Controllers;

use App\Incidencia;
use App\Services\CambiosPareja;
use App\Services\ControlPenales;
use App\Services\Controles;
use App\Services\TmDetallePartido;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Panel de controles de carga.
 *
 * Una sola pantalla para los dieciocho chequeos que antes estaban repartidos
 * en siete pantallas. Cada chequeo es un link, así que en cada carga se
 * ejecuta UNA consulta: la del chequeo que se está mirando. Los totales del
 * menú los pide el navegador aparte, contra `conteo()`, y quedan cacheados.
 */
class ControlController extends Controller
{
    /** @var Controles */
    private $controles;

    public function __construct(Controles $controles)
    {
        $this->controles = $controles;
    }

    public function index(Request $request)
    {
        $clave = $request->input('check');

        if (!$clave || !$this->controles->definicion($clave)) {
            $clave = $this->controles->primeraClave();
        }

        $def     = $this->controles->definicion($clave);
        $filtros = $this->controles->filtrosDesde($request);

        $filas = $this->filas($clave, $filtros, $request);

        return view('controles.index', [
            'grupos'       => $this->controles->definiciones(),
            'clave'        => $clave,
            'def'          => $def,
            'filtros'      => $filtros,
            'filas'        => $filas,
            'anios'        => $this->controles->anios(),
            // Ojo: NO se puede llamar 'torneos'. ComposerServiceProvider hace
            // View::composer('*') y mete su propio $torneos (coleccion de
            // modelos Torneo) en todas las vistas, pisando lo que mande el
            // controlador.
            'torneosFiltro' => $this->controles->torneos($filtros['year']),
            'rolesTerna'   => Controles::ROLES_TERNA,
            'resumenRoles' => $def['grupo'] === 'Árbitros' ? $this->controles->resumenRoles() : [],
            // Qué dice el botón "no se puede arreglar" en ESTE control.
            'sinDatos'     => $this->controles->motivoSinDatos($clave),
        ]);
    }

    /**
     * Total de un chequeo, para el badge del menú. Se pide por AJAX de a uno
     * para que la pantalla se vea enseguida aunque los conteos tarden.
     */
    public function conteo(Request $request)
    {
        $clave = $request->input('check');

        if (!$this->controles->definicion($clave)) {
            return response()->json(['error' => 'chequeo desconocido'], 404);
        }

        $filtros = $this->controles->filtrosDesde($request);

        return response()->json([
            'check' => $clave,
            'total' => $this->controles->contar($clave, $filtros),
        ]);
    }

    /** Tira los totales cacheados para que se vuelvan a calcular. */
    public function recalcular(Request $request)
    {
        $this->controles->invalidarConteos();

        return back()->with('success', 'Listo: los totales se van a recalcular.');
    }

    /**
     * Crea los penales convertidos que faltan.
     *
     * Antes esto pasaba solo, al abrir la pantalla de control. Ahora es un
     * POST explícito y respeta los filtros que estén puestos.
     */
    public function aplicarPenales(Request $request)
    {
        set_time_limit(0);

        $filtros  = $this->controles->filtrosDesde($request);
        $resumen  = app(ControlPenales::class)->aplicar($filtros);

        $mensaje = 'Penales creados: '.$resumen['creados'].'.';

        if ($resumen['sin_arquero']) {
            $mensaje .= ' Salteados por no poder determinar el arquero: '.$resumen['sin_arquero'].'.';
        }

        if ($resumen['restantes']) {
            $mensaje .= ' Quedan '.$resumen['restantes'].' para una próxima pasada.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Junta las parejas de cambios que quedaron partidas por el descuento.
     *
     * Un cambio son dos filas sin vínculo entre sí: si a una le corrigieron el
     * minuto y a la otra no, el control las marca. Esta pasada las vuelve a
     * juntar SIN llamar a Transfermarkt, y sólo donde no hay nada que adivinar:
     * una fila con el descuento (90+4) y su pareja en una de las formas viejas
     * de esa misma jugada (90, porque TM tiraba el descuento; o 94, porque
     * promiedos lo sumaba). Las que están separadas por un minuto de verdad
     * —un 63 contra un 64— no las toca: eso hay que preguntárselo a TM.
     */
    public function unirCambios(Request $request)
    {
        set_time_limit(0);

        $filtros = $this->controles->filtrosDesde($request);
        $r       = app(CambiosPareja::class)->aplicar($filtros);

        if ($r['movidas'] === 0) {
            $mensaje = 'Miré '.$r['mirados'].' partido(s) y no encontré ninguna pareja que se pueda '
                .'juntar sin preguntarle a Transfermarkt.';
        } else {
            $mensaje = 'Listo: '.$r['movidas'].' fila(s) movidas en '.$r['partidos'].' partido(s), '
                .'de los '.$r['mirados'].' que tenían un minuto con descuento descalzado.';
        }

        if ($r['dudosos']) {
            $mensaje .= ' En '.$r['dudosos'].' partido(s) había más de una candidata y no toqué nada.';
        }

        if ($r['restantes'] !== 0) {
            $mensaje .= ' Quedan más para otra pasada: apretá de nuevo.';
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Marca un partido como "Transfermarkt no tiene los datos".
     *
     * Hay partidos que no se pueden arreglar: la ficha de TM dice "no data
     * available" para uno de los dos equipos, o le falta un gol. Rehacer no
     * cambia nada —el importador trae lo mismo— y el partido se queda para
     * siempre en los controles tapando los errores que sí se pueden corregir.
     *
     * La salida es la de siempre, la incidencia, pero de un click. Va con
     * `equipo_id` y `puntos` en NULL a propósito: así no se publica en el
     * front ni toca la tabla de posiciones (ver `posicionesPublic` y
     * `GrupoController`, que filtran por `whereNotNull('equipo_id')`), y el
     * partido desaparece de los dieciocho controles.
     */
    public function marcarSinDatos(Request $request)
    {
        // El motivo sale del control desde el que se apretó el botón, no es un
        // texto único: en "Terna incompleta" lo que falta son los asistentes,
        // no la alineación. `motivoSinDatos()` valida la clave.
        $motivo = $this->controles->motivoSinDatos($request->input('check'));
        $r      = $this->incidenciaSinDatos((int) $request->input('partido_id'), $motivo['texto']);

        if ($r['creada']) {
            $this->controles->invalidarConteos();
        }

        return back()->with('success', $r['texto']);
    }

    /**
     * Lo mismo, pero en los partidos tildados en la página.
     *
     * Es el hermano gratis de "Rehacer seleccionados": no le pregunta nada a
     * Transfermarkt, sólo escribe una incidencia por partido. Sirve para lo que
     * viene mal DEL ORIGEN y en tandas —el caso que lo pidió es "Terna
     * incompleta", donde TM publica sólo el árbitro principal y hay temporadas
     * enteras así: de a un click son cientos, y el error no es nuestro.
     *
     * El motivo lo pone el control, igual que en el botón de la fila. Los que
     * ya tenían una incidencia se informan y no se duplican.
     */
    public function marcarSinDatosSeleccionados(Request $request)
    {
        $ids = $this->idsDesde($request->input('ids'));

        if (empty($ids)) {
            return back()->with('error', 'No llegó ningún partido tildado.');
        }

        $tope      = Controles::POR_PAGINA;
        $sobrantes = 0;
        if (count($ids) > $tope) {
            $sobrantes = count($ids) - $tope;
            $ids = array_slice($ids, 0, $tope);
        }

        $motivo    = $this->controles->motivoSinDatos($request->input('check'));
        $etiquetas = $this->etiquetasDe($ids);

        $informe  = [];
        $creadas  = 0;
        $repetidas = 0;

        foreach ($ids as $id) {
            $r = $this->incidenciaSinDatos($id, $motivo['texto']);

            if ($r['creada']) {
                $creadas++;
            } elseif ($r['ya_tenia']) {
                $repetidas++;
            }

            $informe[] = [
                'id'       => $id,
                'partido'  => isset($etiquetas[$id]['texto']) ? $etiquetas[$id]['texto'] : 'Partido #'.$id,
                'fecha_id' => isset($etiquetas[$id]['fecha_id']) ? $etiquetas[$id]['fecha_id'] : null,
                'ok'       => $r['creada'],
                'texto'    => $r['texto'],
                'avisos'   => [],
                // El link a la vista previa del importador no va acá: esto no
                // falló por Transfermarkt, y bajar el partido cuesta plata.
                'previa'   => false,
            ];
        }

        if ($creadas) {
            $this->controles->invalidarConteos();
        }

        $mensaje = 'Incidencias cargadas: '.$creadas.'.'
            .($repetidas ? ' '.$repetidas.' ya tenía(n) una y no las dupliqué.' : '')
            .($sobrantes ? ' Dejé '.$sobrantes.' afuera: por vez entran hasta '.$tope.'.' : '')
            .($creadas ? ' Esos partidos salen de todos los controles.' : '');

        return back()->with('success', $mensaje)->with('lote_informe', [
            'titulo' => $motivo['boton'].' en los seleccionados',
            'filas'  => $informe,
        ]);
    }

    /**
     * La incidencia de "no se puede arreglar" en un partido.
     *
     * Va con `equipo_id` y `puntos` en NULL a propósito: así no se publica en
     * el front ni toca la tabla de posiciones (ver `posicionesPublic` y
     * `GrupoController`, que filtran por `whereNotNull('equipo_id')`), y el
     * partido desaparece de los dieciocho controles.
     *
     * Devuelve `['creada' => bool, 'ya_tenia' => bool, 'texto' => string]`. No
     * invalida los conteos: eso lo hace quien llama, una sola vez, aunque haya
     * escrito veinte.
     */
    private function incidenciaSinDatos($partidoId, $motivo)
    {
        $partido = DB::table('partidos')
            ->join('fechas', 'partidos.fecha_id', '=', 'fechas.id')
            ->join('grupos', 'fechas.grupo_id', '=', 'grupos.id')
            ->where('partidos.id', (int) $partidoId)
            ->first(['partidos.id', 'grupos.torneo_id']);

        if (!$partido) {
            return ['creada' => false, 'ya_tenia' => false, 'texto' => 'No encontré ese partido.'];
        }

        // Si ya tenía una incidencia no se agrega otra: con una alcanza para
        // que el partido no aparezca en ningún control.
        if (Incidencia::where('partido_id', $partido->id)->exists()) {
            return ['creada' => false, 'ya_tenia' => true, 'texto' => 'Ya tenía una incidencia cargada.'];
        }

        Incidencia::create([
            'partido_id'    => $partido->id,
            'torneo_id'     => $partido->torneo_id,
            'equipo_id'     => null,
            'puntos'        => null,
            'observaciones' => $motivo.' Marcado desde Controles de carga el '.date('d/m/Y').'.',
        ]);

        return ['creada' => true, 'ya_tenia' => false,
            'texto' => 'Incidencia cargada: sale de todos los controles.'];
    }

    /**
     * Rehace el detalle de los partidos tildados en la página.
     *
     * Es el botón "Rehacer" de la fila, pero de a varios: para cada partido
     * vuelve a bajar el detalle de Transfermarkt con `forzar`, o sea que
     * reemplaza alineación, goles, tarjetas, cambios y árbitros por lo que diga
     * TM. Cuesta UNA llamada por partido (más las fotos de los jugadores que
     * haya que crear), así que la pantalla lo dice antes de largarlo.
     *
     * Tres decisiones que valen la pena explicar:
     *
     * - **Escribe sin vista previa.** El "Rehacer" de a uno abre la previa
     *   porque ahí se puede mirar; veinticinco previas no se miran. Lo que
     *   sustituye a la previa es la selección: los partidos los elige el
     *   usuario de a uno con el tilde, no los adivina el sistema.
     * - **Sólo los que YA tienen gameId anotado.** Sin gameId, "Rehacer" sale
     *   a buscarlo a TM y puede terminar ofreciendo candidatos para que elijas:
     *   eso se decide de a uno. Esas filas SÍ se pueden tildar —el otro botón
     *   del lote, el de la incidencia, no necesita gameId— así que acá se
     *   saltean con el motivo, y la pantalla ya avisó cuántas eran.
     * - **Un partido, una llamada.** En los controles por jugador el mismo
     *   partido aparece en varias filas; los ids se deduplican para no pagar
     *   dos veces el mismo dato.
     *
     * El informe va por sesión y se muestra ARRIBA DE LA LISTA, no en una
     * pantalla propia: la vuelta es a la misma página del mismo control (los
     * que se arreglaron ya no están) y así no se pierde el lugar.
     */
    public function rehacerSeleccionados(Request $request)
    {
        set_time_limit(0);

        $ids = $this->idsDesde($request->input('ids'));

        if (empty($ids)) {
            return back()->with('error', 'No llegó ningún partido tildado.');
        }

        // Tope: lo que entra en una página. Es el techo natural de la
        // selección y de paso acota cuánto puede tardar el request — con una
        // llamada por partido, una tanda más larga se come el tiempo del
        // navegador y el informe se pierde en un 504.
        $tope      = Controles::POR_PAGINA;
        $sobrantes = 0;
        if (count($ids) > $tope) {
            $sobrantes = count($ids) - $tope;
            $ids = array_slice($ids, 0, $tope);
        }

        $gameIds   = $this->gameIdsDe($ids);
        $etiquetas = $this->etiquetasDe($ids);

        $imp      = new TmDetallePartido;
        $informe  = [];
        $ok       = 0;
        $fallaron = 0;
        $llamadas = 0;
        $nuevos   = 0;

        foreach ($ids as $id) {
            $fila = [
                'id'      => $id,
                'partido' => isset($etiquetas[$id]['texto']) ? $etiquetas[$id]['texto'] : 'Partido #'.$id,
                'fecha_id' => isset($etiquetas[$id]['fecha_id']) ? $etiquetas[$id]['fecha_id'] : null,
                'ok'      => false,
                'texto'   => '',
                'avisos'  => [],
                // El que falló se puede mirar en la vista previa del
                // importador, avisando que cuesta otra llamada.
                'previa'  => true,
            ];

            if (!isset($gameIds[$id])) {
                $fallaron++;
                $fila['texto'] = 'No tiene gameId anotado. Usá el botón "Rehacer" de la fila: '
                    .'esa pantalla lo busca en Transfermarkt y, si hay más de un candidato, te los ofrece.';
                $informe[] = $fila;
                continue;
            }

            $r = $imp->importar($id, $gameIds[$id], ['escribir' => true, 'forzar' => true]);

            $llamadas += (int) $r['llamadas'];
            $nuevos   += count($r['creados']['jugadores']);

            if (!empty($r['escrito'])) {
                $ok++;
                $fila['ok']    = true;
                $fila['texto'] = count($r['plan']['alineacions']).' en la alineación, '
                    .count($r['plan']['gols']).' goles, '
                    .count($r['plan']['tarjetas']).' tarjetas, '
                    .count($r['plan']['cambios']).' cambios'
                    .(count($r['plan']['arbitros']) ? ', '.count($r['plan']['arbitros']).' árbitros' : '');
            } else {
                $fallaron++;
                $fila['texto'] = (string) $r['error'] !== ''
                    ? (string) $r['error']
                    : 'No se escribió nada y el importador no dijo por qué.';
            }

            foreach ($r['avisos'] as $aviso) {
                $fila['avisos'][] = $this->avisoTexto($aviso);
            }

            $informe[] = $fila;
        }

        if ($ok) {
            $this->controles->invalidarConteos();
        }

        $mensaje = 'Rehice el detalle de '.$ok.' partido(s)'
            .($fallaron ? ', '.$fallaron.' con problema' : '').'. '
            .'Llamadas a la API: '.$llamadas.'.'
            .($nuevos ? ' Jugadores nuevos: '.$nuevos.'.' : '')
            .($sobrantes ? ' Dejé '.$sobrantes.' afuera: por vez entran hasta '.$tope.'.' : '');

        return back()->with('success', $mensaje)->with('lote_informe', [
            'titulo' => 'Rehacer seleccionados',
            'filas'  => $informe,
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Los ids del campo oculto del lote: "12,40,7".
     *
     * Deduplica —en los controles por jugador el mismo partido puede venir dos
     * veces— y descarta cualquier cosa que no sea un id positivo.
     */
    private function idsDesde($crudo)
    {
        $ids = [];

        foreach (explode(',', (string) $crudo) as $pedazo) {
            $id = (int) trim($pedazo);
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }

        return $ids;
    }

    /**
     * partido_id => gameId, del staging.
     *
     * Mismo criterio que la pantalla de un partido
     * (`ImportDetallesController::correrUno`): la fila más nueva que tenga
     * `external_id`. Se ordena ascendente y se sobreescribe, así la última que
     * queda es la de id más alto.
     */
    private function gameIdsDe(array $ids)
    {
        $mapa = [];

        foreach (DB::table('import_partidos')
                     ->whereIn('partido_id', $ids)
                     ->whereNotNull('external_id')
                     ->orderBy('id')
                     ->get(['partido_id', 'external_id']) as $f) {
            $mapa[(int) $f->partido_id] = (string) $f->external_id;
        }

        return $mapa;
    }

    /** partido_id => ['texto' => 'Local vs Visita · 19/02/2015', 'fecha_id' => N]. */
    private function etiquetasDe(array $ids)
    {
        $mapa = [];

        foreach (DB::table('partidos')
                     ->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
                     ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
                     ->whereIn('partidos.id', $ids)
                     ->get(['partidos.id', 'partidos.dia', 'partidos.fecha_id',
                         'el.nombre as local', 'ev.nombre as visita']) as $p) {
            $mapa[(int) $p->id] = [
                'texto'    => $p->local.' vs '.$p->visita
                    .($p->dia ? ' · '.date('d/m/Y', strtotime($p->dia)) : ''),
                'fecha_id' => (int) $p->fecha_id,
            ];
        }

        return $mapa;
    }

    /**
     * El aviso del importador, en texto plano.
     *
     * `TmDetallePartido` mete tokens para que la pantalla del importador los
     * convierta en links (`[[plantilla:12]]`, `[[repetidos]]`). Acá el informe
     * es una lista corta arriba de la tabla: los tokens se reemplazan por su
     * nombre y listo, así no aparecen corchetes crudos.
     */
    private function avisoTexto($texto)
    {
        $limpio = preg_replace('/\[\[plantilla:(\d+)\]\]/', 'plantilla #$1', (string) $texto);

        return str_replace('[[repetidos]]', 'verificar personas', $limpio);
    }

    /**
     * Las filas del chequeo activo, ya paginadas.
     *
     * Los penales son el caso raro: "arquero equivocado" no se puede filtrar
     * por SQL (hay que reconstruir quién atajaba en ese minuto), así que se
     * resuelve en memoria sobre el resultado cacheado y se pagina a mano.
     */
    private function filas(string $clave, array $filtros, Request $request)
    {
        $penales = app(ControlPenales::class);

        if ($clave === 'penales.mal_cargados') {
            $todas  = $penales->malCargados($filtros);
            $pagina = max(1, (int) $request->query('page', 1));

            $deLaPagina = $todas->forPage($pagina, Controles::POR_PAGINA)->values();
            $this->controles->agregarTransfermarkt($deLaPagina);

            return new LengthAwarePaginator(
                $deLaPagina,
                $todas->count(),
                Controles::POR_PAGINA,
                $pagina,
                // Sin `page`: lo pone el propio paginador y si no se duplicaria.
                ['path' => $request->url(), 'query' => Arr::except($request->query(), 'page')]
            );
        }

        $consulta = $this->controles->consulta($clave, $filtros);

        if (!$consulta) {
            return new LengthAwarePaginator([], 0, Controles::POR_PAGINA, 1, ['path' => $request->url()]);
        }

        $paginador = $consulta->paginate(Controles::POR_PAGINA)->withQueryString();

        // Los goles de penal sin cargar muestran qué arquero se les va a
        // asignar: se resuelve solo para las filas de esta página.
        if ($clave === 'penales.faltantes') {
            $penales->resolver($paginador->getCollection());
        }

        // El link a Transfermarkt y el botón de rehacer valen para cualquier
        // chequeo: la fila siempre es un partido.
        $this->controles->agregarTransfermarkt($paginador->getCollection());

        return $paginador;
    }
}
