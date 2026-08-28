<?php

namespace App\Http\Controllers;

use App\Incidencia;
use App\Services\ControlPenales;
use App\Services\Controles;
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
        $partidoId = (int) $request->input('partido_id');

        $partido = DB::table('partidos')
            ->join('fechas', 'partidos.fecha_id', '=', 'fechas.id')
            ->join('grupos', 'fechas.grupo_id', '=', 'grupos.id')
            ->where('partidos.id', $partidoId)
            ->first(['partidos.id', 'grupos.torneo_id']);

        if (!$partido) {
            return back()->with('success', 'No encontré ese partido.');
        }

        // Si ya tenía una incidencia no se agrega otra: con una alcanza para
        // que el partido no aparezca en ningún control.
        $yaTiene = Incidencia::where('partido_id', $partido->id)->exists();

        if (!$yaTiene) {
            // El motivo sale del control desde el que se apretó el botón, no
            // es un texto único: en "Terna incompleta" lo que falta son los
            // asistentes, no la alineación. `motivoSinDatos()` valida la clave.
            $motivo = $this->controles->motivoSinDatos($request->input('check'));

            Incidencia::create([
                'partido_id'    => $partido->id,
                'torneo_id'     => $partido->torneo_id,
                'equipo_id'     => null,
                'puntos'        => null,
                'observaciones' => $motivo['texto']
                    .' Marcado desde Controles de carga el '.date('d/m/Y').'.',
            ]);

            $this->controles->invalidarConteos();
        }

        return back()->with('success', $yaTiene
            ? 'Ese partido ya tenía una incidencia cargada.'
            : 'Listo: el partido quedó marcado como sin datos en TM y sale de todos los controles.');
    }

    // ------------------------------------------------------------------

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
