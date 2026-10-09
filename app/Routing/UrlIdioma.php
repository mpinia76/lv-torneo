<?php

namespace App\Routing;

use App\Services\UrlAmigable;
use Illuminate\Routing\UrlGenerator;

/**
 * route() que respeta el idioma: si la página se está viendo en inglés, los
 * links a otras páginas públicas salen con /en adelante.
 *
 * Las rutas en inglés tienen los MISMOS nombres que las de español (se
 * registran después, así el nombre queda apuntando a la de español: desde
 * Laravel 11 gana la primera registrada; ver routes/web.php). Por eso request()->routeIs('fechas.fixture') anda igual en
 * los dos idiomas y ninguna vista tuvo que cambiar sus route().
 *
 * Solo se toca lo que tiene el middleware 'idioma' (el sitio público): el
 * admin, el login y las rutas internas quedan siempre sin prefijo.
 */
class UrlIdioma extends UrlGenerator
{
    public function toRoute($route, $parameters, $absolute)
    {
        [$route, $parameters] = $this->amigable($route, (array) $parameters);

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

    /**
     * URLs amigables (ver App\Services\UrlAmigable): route('jugadores.ver',
     * ['jugadorId' => 250, 'pestActiva' => 'x']) -> /jugador/250-gervasio-nunez?pestActiva=x.
     * Sin id (route('fechas.ver') a secas) sale la URL vieja, que sigue
     * andando sin parámetro. Un 'ref' explícito pasa tal cual (moldes del
     * sitemap y del menú).
     */
    protected function amigable($route, array $parameters)
    {
        $nombre = $route->getName();
        $cfg = $nombre ? UrlAmigable::config($nombre) : null;
        if (!$cfg || array_key_exists('ref', $parameters)) {
            return [$route, $parameters];
        }

        $id = $parameters[$cfg[1]] ?? null;
        if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
            unset($parameters[$cfg[1]]);
            return [$route, ['ref' => UrlAmigable::ref($nombre, (int) $id)] + $parameters];
        }

        $viejo = $this->routes->getByName($nombre . '.viejo');
        return [$viejo ?: $route, $parameters];
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
