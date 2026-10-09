<?php

namespace App\Http\Middleware;

use App\Services\UrlAmigable as Urls;
use Closure;

/**
 * Entrada de las URLs amigables (ver App\Services\UrlAmigable).
 *
 * Va en el grupo del sitio público ANTES de 'pagina.cache': las redirecciones
 * se deciden antes de servir una copia guardada.
 *
 *  - /jugador/{ref}: si el slug no es el actual → 301 a la URL correcta. Si
 *    está bien, pone jugadorId en la query (los controladores no cambiaron:
 *    siguen leyendo $request->query('jugadorId')). Si el id no existe → 404.
 *  - /verJugador?jugadorId=N (ruta '<nombre>.viejo'): 301 a /jugador/N-slug,
 *    con el resto de la query. Sin id (o un id que no existe) sigue al
 *    controlador como siempre: /verFechas sin torneoId muestra el último.
 */
class UrlAmigable
{
    public function handle($request, Closure $next)
    {
        $route  = $request->route();
        $nombre = $route ? $route->getName() : null;
        if (!$nombre) {
            return $next($request);
        }

        // ── URL nueva
        if (($cfg = Urls::config($nombre)) && $route->hasParameter('ref')) {
            $param = $cfg[1];
            if (!preg_match('/^(\d+)(?:-(.*))?$/', (string) $route->parameter('ref'), $m)) {
                abort(404);
            }
            $id   = (int) $m[1];
            $slug = Urls::slug($nombre, $id);
            if ($slug === null) {
                abort(404);
            }

            $pedido = isset($m[2]) ? $m[2] : '';
            if ($pedido !== $slug || $m[1] !== (string) $id) {
                return $this->a($nombre, [$param => $id] + $request->query());
            }

            $request->query->set($param, (string) $id);
            // Para que url_idioma()/url_canonica() no lo agreguen a la query.
            $request->attributes->set('url_amigable_param', $param);
            // Los métodos ver(Request $request) no esperan un parámetro de ruta.
            $route->forgetParameter('ref');

            return $next($request);
        }

        // ── URL vieja
        if (substr($nombre, -6) === '.viejo' && ($cfg = Urls::config(substr($nombre, 0, -6)))) {
            $id = $request->query($cfg[1]);
            if (is_string($id) && ctype_digit($id) && (int) $id > 0
                && Urls::slug(substr($nombre, 0, -6), (int) $id) !== null) {
                return $this->a(substr($nombre, 0, -6), $request->query());
            }
        }

        return $next($request);
    }

    protected function a($nombre, array $query)
    {
        return redirect()->to(route($nombre, $query), 301);
    }
}
