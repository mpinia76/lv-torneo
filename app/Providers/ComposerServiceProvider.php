<?php

namespace App\Providers;

use App\Torneo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use View;

class ComposerServiceProvider  extends ServiceProvider
{
    /** Clave del caché con la lista de torneos que alimenta los menús. */
    const CACHE_KEY = 'torneos.menu';

    /** Minutos que vive el caché (igual se limpia solo al guardar un torneo). */
    const CACHE_MINUTOS = 60;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Este composer corre en CADA vista y partial que se renderiza, así que la
        // consulta se resuelve una sola vez por request (memoria) y se guarda en
        // caché entre requests. App\Torneo limpia el caché al guardar o borrar.
        $torneos = null;
        $torneosMenu = null;

        View::composer('*', function ($view) use (&$torneos, &$torneosMenu) {

            if ($torneos === null) {
                $torneos = Cache::remember(self::CACHE_KEY, self::CACHE_MINUTOS * 60, function () {
                    return Torneo::orderBy('year', 'DESC')->orderBy('id', 'DESC')->get();
                });

                // $torneosMenu: lo que se navega desde los menús públicos.
                // Queda afuera lo parcial (torneos cargados solo con los partidos del
                // ciclo de un DT) y lo del exterior: a eso se llega desde las fichas.
                $torneosMenu = $torneos->filter(function ($t) {
                    if (!empty($t->parcial)) return false;

                    if ($t->ambito === 'Internacional') {
                        $region = trim((string) $t->region);
                        // Sin región cargada se asume propio (Libertadores, Sudamericana,
                        // Recopa, Intercontinental, Mundial de Clubes).
                        return $region === ''
                            || in_array(mb_strtolower($region), ['conmebol', 'fifa', 'mundial', 'sudamerica', 'sudamérica'], true);
                    }

                    $pais = trim((string) $t->pais);
                    return $pais === '' || mb_strtolower($pais) === 'argentina';
                })->values();
            }

            $view->with('torneos', $torneos)->with('torneosMenu', $torneosMenu);
        });
    }
}
