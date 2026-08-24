<?php

namespace App\Http\Controllers;

use App\Services\ControlPenales;
use App\Services\Controles;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

/**
 * Panel de controles de carga.
 *
 * Una sola pantalla para los diecisiete chequeos que antes estaban repartidos
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

            return new LengthAwarePaginator(
                $todas->forPage($pagina, Controles::POR_PAGINA)->values(),
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

        return $paginador;
    }
}
