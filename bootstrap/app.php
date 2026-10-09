<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Despues de iniciar sesion se va al panel. Reemplaza a
        // RouteServiceProvider::HOME, que desaparecio en Laravel 11.
        $middleware->redirectUsersTo('/admin');

        $middleware->alias([
            // Idioma del sitio publico segun el prefijo de la URL (ver routes/web.php).
            'idioma' => \App\Http\Middleware\Idioma::class,
            // Cache del HTML de las paginas publicas (ver App\Services\CachePaginas).
            'pagina.cache' => \App\Http\Middleware\PaginaEnCache::class,
            // URLs amigables de las fichas (ver App\Services\UrlAmigable).
            'url.amigable' => \App\Http\Middleware\UrlAmigable::class,
            'url.slugs' => \App\Http\Middleware\UrlAmigableSlugs::class,
        ]);

        // Los middleware globales y los de los grupos web/api que tenia el
        // Kernel de Laravel 7 eran todos los de fabrica: el framework ya los
        // aplica. Lo unico que habia de mas era PerfDebug, temporal, que no se
        // porta. TrustProxies tampoco: el sitio no esta detras de un proxy
        // inverso. Cuando entre Cloudflare, agregar aca:
        //   $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
