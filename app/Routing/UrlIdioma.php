<?php

namespace App\Routing;

use Illuminate\Routing\UrlGenerator;

/**
 * route() que respeta el idioma: si la página se está viendo en inglés, los
 * links a otras páginas públicas salen con /en adelante.
 *
 * Las rutas en inglés tienen los MISMOS nombres que las de español (se
 * registran primero, así el nombre queda apuntando a la de español; ver
 * routes/web.php). Por eso request()->routeIs('fechas.fixture') anda igual en
 * los dos idiomas y ninguna vista tuvo que cambiar sus route().
 *
 * Solo se toca lo que tiene el middleware 'idioma' (el sitio público): el
 * admin, el login y las rutas internas quedan siempre sin prefijo.
 */
class UrlIdioma extends UrlGenerator
{
    public function toRoute($route, $parameters, $absolute)
    {
        $url = parent::toRoute($route, $parameters, $absolute);

        $idioma = app()->getLocale();
        if ($idioma === array_keys(idiomas_sitio())[0] || !self::esPublica($route)) {
            return $url;
        }

        if ($absolute) {
            $raiz = $this->formatRoot($this->formatScheme());
            if (strpos($url, $raiz) === 0) {
                return $raiz . '/' . $idioma . self::resto(substr($url, strlen($raiz)));
            }
            return $url;
        }

        return '/' . $idioma . self::resto($url);
    }

    /** "" -> "", "/verTorneo?x" -> "/verTorneo?x", "?x" -> "?x". */
    protected static function resto($resto)
    {
        if ($resto === '' || $resto === '/') {
            return '';
        }
        return $resto[0] === '/' || $resto[0] === '?' ? $resto : '/' . $resto;
    }

    protected static function esPublica($route)
    {
        foreach ((array) $route->middleware() as $m) {
            if (is_string($m) && strpos($m, 'idioma') === 0) {
                return true;
            }
        }
        return false;
    }
}
