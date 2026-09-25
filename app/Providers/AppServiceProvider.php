<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use DB;
use App\Routing\UrlIdioma;

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
        Schema::defaultStringLength(191);
        setlocale(LC_TIME, 'es_ES.utf8');

            /*DB::listen(function ($query) {
                Log::debug("DB: " . $query->sql . "[".  implode(",",$query->bindings). "]");
            });*/
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    }
}
