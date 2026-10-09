<?php

namespace App\Http\Middleware;

use App\Services\TorneoEnSesion;
use App\Torneo;
use Closure;

/**
 * La barra del torneo (debajo del menú general) sale de la sesión: muestra el
 * último torneo que se miró. Eso confundía con dos menús en pantalla: se
 * entraba a "Goleadores históricos" o a un partido de otro torneo y arriba
 * seguía, por ejemplo, LaLiga 2014/15.
 *
 *  - Páginas del menú general (GENERALES): se saca la barra.
 *  - Páginas de un torneo (DE_TORNEO, con ?torneoId=): la barra pasa a ese
 *    torneo, aunque se llegue por un link directo o desde un buscador.
 *  - El partido la fija en FechaController::detalle (hace falta el partido).
 *  - El resto (fichas de jugador, equipo, técnico, árbitro…) no la toca.
 *
 * Va en el grupo público ANTES de 'pagina.cache', así corre también cuando la
 * página sale de la caché. Solo consulta la base cuando cambia de torneo.
 */
class BarraTorneo
{
    const GENERALES = [
        'home', 'fechas.fixture', 'buscar', 'torneos.explorar', 'partidos.arbitros',
        'torneos.historiales', 'torneos.historial', 'torneos.goleadores', 'torneos.jugadores', 'torneos.tarjetas',
        'torneos.posiciones', 'torneos.estadisticasOtras', 'torneos.tecnicos',
        'torneos.arqueros', 'torneos.titulos',
    ];

    const DE_TORNEO = [
        'fechas.ver', 'torneos.ver',
        'grupos.posiciones', 'grupos.goleadores', 'grupos.jugadores', 'grupos.tarjetas',
        'grupos.posicionesPublic', 'grupos.goleadoresPublic', 'grupos.tarjetasPublic',
        'grupos.arqueros', 'grupos.tecnicos',
        'torneos.plantillas', 'torneos.promedios', 'torneos.promediosPublic',
        'torneos.acumulado', 'torneos.estadisticasTorneo',
    ];

    public function handle($request, Closure $next)
    {
        $ruta = optional($request->route())->getName();

        if (in_array($ruta, self::GENERALES, true)) {
            if ($request->session()->has('codigoTorneo')) {
                TorneoEnSesion::olvidar($request);
            }
        } elseif (in_array($ruta, self::DE_TORNEO, true)) {
            $id = $request->query('torneoId');
            if (is_string($id) && ctype_digit($id) && (int) $id > 0
                && (int) $request->session()->get('codigoTorneo') !== (int) $id
                && ($torneo = Torneo::find((int) $id))) {
                TorneoEnSesion::fijar($request, $torneo);
            }
        }

        return $next($request);
    }
}
