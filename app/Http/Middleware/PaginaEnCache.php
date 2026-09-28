<?php

namespace App\Http\Middleware;

use App\Services\CachePaginas;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Caché de las páginas públicas (ver App\Services\CachePaginas).
 *
 * Guarda el HTML que devolvió el controlador y a la próxima lo sirve sin
 * tocar la base. Lo que depende de cada visitante NO queda congelado:
 *
 *  - La cabecera (barra del torneo elegido, que sale de la sesión) se guarda
 *    como un hueco y se arma de nuevo en cada visita. La parte cara de la
 *    cabecera (el menú) ya tiene su propia caché.
 *  - El token CSRF se guarda como marca y se pone el de cada visitante.
 *  - El botón "Volver" (url_volver() en app/helpers.php) igual: cada uno
 *    vuelve a su página anterior.
 *  - Los listados con el filtro "buscarpor" recordado en la sesión guardan una
 *    versión por filtro (FILTROS).
 *  - Las páginas que eligen torneo (fixture, ficha, estadísticas) cambian la
 *    sesión: se guarda cómo quedó la sesión y se repite al servir la copia.
 *
 * No se guarda: ?perf=1, pedidos que no son GET, páginas con mensajes flash,
 * redirecciones, errores, archivos.
 *
 * Cada respuesta lleva X-Cache-Paginas: HIT / MISS para poder mirarlo.
 */
class PaginaEnCache
{
    /** Rutas que recuerdan el filtro "buscarpor" en la sesión, y con qué clave. */
    const FILTROS = [
        'grupos.goleadoresPublic' => 'nombre_filtro_jugador',
        'grupos.tarjetas'         => 'nombre_filtro_jugador',
        'grupos.tarjetasPublic'   => 'nombre_filtro_jugador',
        'grupos.arqueros'         => 'nombre_filtro_jugador',
        'grupos.jugadores'        => 'nombre_filtro_jugador',
        'grupos.tecnicos'         => 'nombre_filtro_jugador',
        'torneos.goleadores'      => 'nombre_filtro_jugador',
        'torneos.tarjetas'        => 'nombre_filtro_jugador',
        'torneos.arqueros'        => 'nombre_filtro_jugador',
        'torneos.jugadores'       => 'nombre_filtro_jugador',
        'torneos.tecnicos'        => 'nombre_filtro_jugador',
        'torneos.posiciones'      => 'nombre_filtro_equipo',
        'torneos.titulos'         => 'nombre_filtro_equipo',
    ];

    /** La barra del torneo en la sesión (ver App\Services\TorneoEnSesion). */
    const SESION_TORNEO = [
        'codigoTorneo', 'nombreTorneo', 'escudoTorneo',
        'sessionAcumulado', 'sessionPosiciones', 'sessionPromedios', 'sessionPaenza',
    ];

    /** Parámetros que no cambian la página (campañas, redes). */
    const PARAMS_IGNORADOS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', '_'];

    const MARCA_INI    = '<!--cp:cabecera-->';
    const MARCA_FIN    = '<!--/cp:cabecera-->';
    const HUECO        = '<!--cp:hueco-cabecera-->';
    const HUECO_CSRF   = '__CP_CSRF_TOKEN__';
    const HUECO_VOLVER = '__CP_URL_VOLVER__';
    const MAX_BYTES    = 3145728;

    public function handle($request, Closure $next)
    {
        if (!$this->aplica($request)) {
            return $next($request);
        }

        CachePaginas::ignorarEscrituras(true);
        CachePaginas::cambioDeDia();

        // Parámetros de campañas y redes (fbclid…): se sacan del pedido antes
        // del controlador, así no quedan pegados en los links de la copia
        // guardada ni arman una copia por cada visitante que llega de Facebook.
        foreach (self::PARAMS_IGNORADOS as $p) {
            $request->query->remove($p);
        }

        $gen   = CachePaginas::generacion();
        $clave = $this->clave($request, $gen);

        $guardado = CachePaginas::store()->get($clave);
        if (is_array($guardado) && isset($guardado['c'])) {
            return $this->servir($request, $guardado);
        }

        $antes = $this->sesionTorneo($request);

        $request->attributes->set('cp.guardando', true);
        $response = $next($request);
        $request->attributes->set('cp.guardando', false);

        if (!$this->guardable($response)) {
            // Por si la marca del botón Volver llegó a la respuesta igual.
            if ($response instanceof Response) {
                $contenido = $response->getContent();
                if (is_string($contenido) && strpos($contenido, self::HUECO_VOLVER) !== false) {
                    $response->setContent($this->completarVolver($contenido));
                }
            }
            return $response;
        }

        $despues = $this->sesionTorneo($request);

        $contenido = $this->ahuecar($response->getContent(), $request->session()->token());

        $entrada = [
            'c' => $contenido,
            't' => $response->headers->get('Content-Type'),
            // Cómo quedó la barra del torneo, si esta página la cambia.
            's' => ($request->attributes->get('cp.fijaTorneo') || $antes !== $despues) ? $despues : null,
        ];

        if (CachePaginas::generacion() === $gen) {
            CachePaginas::guardar($clave, $entrada);
        }

        $response->setContent($this->completarVolver($response->getContent()));
        $response->headers->set('X-Cache-Paginas', 'MISS');

        return $response;
    }

    protected function aplica($request)
    {
        if (!CachePaginas::activa() || !$request->isMethod('GET')) {
            return false;
        }

        // Las mediciones de PerfDebug tienen que ver el controlador de verdad.
        // Solo con sesión iniciada, como PerfDebug: si no, cualquiera saltearía
        // la caché agregando &perf=1.
        if ($request->query('perf') && Auth::check()) {
            return false;
        }

        if (strlen((string) $request->getQueryString()) > 500) {
            return false;
        }

        // Un mensaje flash (o errores) de la página anterior: esta es única.
        $flash = $request->session()->get('_flash.old', []);
        if (!empty($flash)) {
            return false;
        }

        return true;
    }

    protected function clave($request, $gen)
    {
        $query = $request->query();
        $this->ordenar($query);

        $partes = [
            app()->getLocale(),
            $request->url(),
            http_build_query($query),
        ];

        // Sin "buscarpor" en la URL, el listado usa el filtro recordado.
        $ruta = optional($request->route())->getName();
        if (isset(self::FILTROS[$ruta]) && !$request->has('buscarpor')) {
            $partes[] = 'f=' . (string) $request->session()->get(self::FILTROS[$ruta]);
        }

        return 'pagina.' . md5(implode('|', $partes)) . '.g' . $gen . '.' . date('Ymd');
    }

    protected function ordenar(array &$arr)
    {
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $this->ordenar($v);
            }
        }
    }

    protected function sesionTorneo($request)
    {
        $sesion = $request->session();
        $foto = [];
        foreach (self::SESION_TORNEO as $k) {
            $foto[$k] = $sesion->has($k) ? $sesion->get($k) : null;
        }

        return $foto;
    }

    protected function guardable($response)
    {
        if (!($response instanceof Response) && !($response instanceof JsonResponse)) {
            return false;   // redirecciones, archivos, streams
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $tipo = (string) $response->headers->get('Content-Type');
        if (stripos($tipo, 'text/html') !== 0 && stripos($tipo, 'application/json') !== 0) {
            return false;
        }

        $contenido = $response->getContent();

        return is_string($contenido) && $contenido !== '' && strlen($contenido) <= self::MAX_BYTES;
    }

    /** Saca del HTML lo que es de cada visitante. */
    protected function ahuecar($html, $token)
    {
        if ($token) {
            $html = str_replace($token, self::HUECO_CSRF, $html);
        }

        $ini = strpos($html, self::MARCA_INI);
        if ($ini !== false) {
            $fin = strpos($html, self::MARCA_FIN, $ini);
            if ($fin !== false) {
                $html = substr($html, 0, $ini) . self::HUECO . substr($html, $fin + strlen(self::MARCA_FIN));
            }
        }

        return $html;
    }

    protected function completarVolver($html)
    {
        if (strpos($html, self::HUECO_VOLVER) === false) {
            return $html;
        }

        return str_replace(self::HUECO_VOLVER, e(url()->previous()), $html);
    }

    protected function servir($request, array $guardado)
    {
        $sesion = $request->session();

        // Lo que el controlador habría dejado en la sesión.
        if (is_array($guardado['s'])) {
            foreach ($guardado['s'] as $k => $v) {
                if ($v === null) {
                    $sesion->forget($k);
                } else {
                    $sesion->put($k, $v);
                }
            }
        }

        $ruta = optional($request->route())->getName();
        if (isset(self::FILTROS[$ruta]) && $request->has('buscarpor')) {
            $sesion->put(self::FILTROS[$ruta], $request->get('buscarpor'));
        }

        $html = $guardado['c'];

        if (strpos($html, self::HUECO) !== false) {
            // Con la sesión ya al día, así la barra muestra el torneo correcto.
            $html = str_replace(self::HUECO, view('layouts.partials.headerPublic')->render(), $html);
        }

        $html = str_replace(self::HUECO_CSRF, csrf_token(), $html);
        $html = $this->completarVolver($html);

        return new Response($html, 200, [
            'Content-Type'    => $guardado['t'] ?: 'text/html; charset=UTF-8',
            'X-Cache-Paginas' => 'HIT',
        ]);
    }
}
