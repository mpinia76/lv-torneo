<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use DB;
use App\Routing\UrlIdioma;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        ini_set('memory_limit', '256M');

        // route() con idioma: en las páginas en inglés los links públicos salen
        // con /en adelante (ver App\Routing\UrlIdioma). Es el mismo armado que
        // hace Laravel en RoutingServiceProvider, cambiando solo la clase; los
        // extend() que Laravel le cuelga a 'url' se siguen aplicando.
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            return new UrlIdioma(
                $routes,
                $app->rebinding('request', function ($app, $request) {
                    $app['url']->setRequest($request);
                }),
                $app['config']['app.asset_url']
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Laravel 8 cambio la plantilla de paginacion por defecto a Tailwind.
        // Este sitio usa Bootstrap 5, asi que sin esto los SVG de las flechas
        // salen a tamano natural (sus clases w-5 h-5 no existen) y los textos
        // aparecen en ingles.
        Paginator::useBootstrapFive();

        Schema::defaultStringLength(191);
        setlocale(LC_TIME, 'es_ES.utf8');

            /*DB::listen(function ($query) {
                Log::debug("DB: " . $query->sql . "[".  implode(",",$query->bindings). "]");
            });*/
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

        // Toda escritura en la base (Eloquent, SQL crudo, importadores, comandos)
        // deja vieja la caché del sitio público: ver App\Services\CachePaginas.
        DB::listen(function ($query) {
            \App\Services\CachePaginas::consulta($query->sql);
        });

    }
}
