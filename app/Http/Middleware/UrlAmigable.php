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

        // ── Historial entre dos equipos (ver Urls::DUELO)
        [$suelto, $bonita, $p1, $p2] = Urls::DUELO;
        if ($nombre === $bonita) {
            $a = $this->partir($route->parameter('ref1'));
            $b = $this->partir($route->parameter('ref2'));
            if (!$a || !$b || $a[0] === $b[0]) {
                abort(404);
            }
            $sa = Urls::slug('equipos.ver', $a[0]);
            $sb = Urls::slug('equipos.ver', $b[0]);
            if ($sa === null || $sb === null) {
                abort(404);
            }
            if ($a[1] !== $sa || $b[1] !== $sb || $a[2] || $b[2]) {
                return $this->a($suelto, [$p1 => $a[0], $p2 => $b[0]] + $request->query());
            }

            $request->query->set($p1, (string) $a[0]);
            $request->query->set($p2, (string) $b[0]);
            $request->attributes->set('url_amigable_param', [$p1, $p2]);
            // Las dos órdenes son la misma página para Google: la canonical (y
            // los hreflang) van con el id menor primero.
            if ($a[0] > $b[0]) {
                $request->attributes->set('url_amigable_path',
                    route($suelto, [$p1 => $b[0], $p2 => $a[0]], false));
            }
            $route->forgetParameter('ref1');
            $route->forgetParameter('ref2');

            return $next($request);
        }
        if ($nombre === $suelto) {
            $a = $request->query($p1);
            $b = $request->query($p2);
            if (is_string($a) && ctype_digit($a) && is_string($b) && ctype_digit($b)
                && (int) $a > 0 && (int) $b > 0 && $a !== $b
                && Urls::slug('equipos.ver', (int) $a) !== null
                && Urls::slug('equipos.ver', (int) $b) !== null) {
                return $this->a($suelto, $request->query());
            }
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

    /** "250-slug" -> [250, 'slug', ceros a la izquierda?]; null si no es un ref. */
    protected function partir($ref)
    {
        if (!preg_match('/^(\d+)(?:-(.*))?$/', (string) $ref, $m)) {
            return null;
        }
        return [(int) $m[1], isset($m[2]) ? $m[2] : '', $m[1] !== (string) (int) $m[1]];
    }

    protected function a($nombre, array $query)
    {
        return redirect()->to(route($nombre, $query), 301);
    }
}
