<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;

/**
 * Idioma del sitio público. Las rutas públicas se registran dos veces en
 * routes/web.php: sin prefijo (español, 'idioma:es') y con /en adelante
 * ('idioma:en'). Este middleware pone el idioma de la app según cuál de las
 * dos atendió el pedido; route() después arma los links en ese mismo idioma
 * (ver App\Routing\UrlIdioma).
 *
 * Los textos salen de resources/lang/<idioma>.json, con el texto en español
 * como clave: __('Posiciones'). En español no hace falta archivo.
 */
class Idioma
{
    public function handle($request, Closure $next, $idioma = 'es')
    {
        if (!array_key_exists($idioma, idiomas_sitio())) {
            $idioma = 'es';
        }

        app()->setLocale($idioma);
        Carbon::setLocale($idioma);

        return $next($request);
    }
}
