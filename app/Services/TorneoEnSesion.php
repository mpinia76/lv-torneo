<?php

namespace App\Services;

use App\Grupo;
use Illuminate\Http\Request;

/**
 * La barra del torneo de la cabecera pública sale de la sesión. Antes cada
 * página la armaba a su manera (Fixture con las secciones del torneo, la ficha
 * solo con nombre y escudo, Estadísticas solo si se venía de otro torneo).
 * Ahora las tres dejan exactamente lo mismo, y eso es lo que permite a la
 * caché de páginas (App\Http\Middleware\PaginaEnCache) repetirlo al servir
 * una copia guardada.
 */
class TorneoEnSesion
{
    /**
     * @param  \App\Torneo  $torneo
     * @param  \Illuminate\Support\Collection|null  $grupos  los grupos del torneo, si ya se tienen
     */
    public static function fijar(Request $request, $torneo, $grupos = null)
    {
        if ($grupos === null) {
            $grupos = Grupo::where('torneo_id', $torneo->id)->get();
        }

        $sesion = $request->session();
        $sesion->put('nombreTorneo', $torneo->nombre . ' ' . $torneo->year);
        $sesion->put('escudoTorneo', $torneo->escudo);
        $sesion->put('codigoTorneo', $torneo->id);

        // Qué secciones tiene el torneo (links de la barra).
        $sesion->forget(['sessionAcumulado', 'sessionPosiciones', 'sessionPromedios', 'sessionPaenza']);

        foreach ($grupos as $grupo) {
            if ($grupo->acumulado) {
                $sesion->put('sessionAcumulado', 1);
            }
            if ($grupo->posiciones) {
                $sesion->put('sessionPosiciones', 1);
                if (count($grupos) == 1) {
                    $sesion->put('sessionPaenza', 1);
                }
            }
            if ($grupo->promedios) {
                $sesion->put('sessionPromedios', 1);
            }
        }

        // Para la caché de páginas: esta página cambia la sesión.
        $request->attributes->set('cp.fijaTorneo', true);
    }
}
