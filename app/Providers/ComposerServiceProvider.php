<?php

namespace App\Providers;

use App\Services\MenuTorneos;
use Illuminate\Support\ServiceProvider;
use View;

class ComposerServiceProvider  extends ServiceProvider
{
    /** Clave del caché con la lista de torneos (ahora vive en MenuTorneos). */
    const CACHE_KEY = MenuTorneos::CACHE_TORNEOS;

    /** Se conserva por si algo externo la lee. */
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
                $torneos = MenuTorneos::torneos();

                // $torneosMenu: la lista vieja, solo lo argentino y los internacionales
                // propios. El menú superior ya no la usa (ahora arma todo el mundo por
                // país con MenuTorneos); queda por si alguna vista vieja la pide.
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
