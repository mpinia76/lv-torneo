<?php

namespace App\Providers;

use App\Torneo;
use View;
use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider  extends ServiceProvider
{
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
        View::composer('*', function($view){
            //any code to set $val variable
            $torneos=Torneo::orderBy('year','DESC')->orderBy('id','DESC')->get();

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

            $view->with('torneos', $torneos)->with('torneosMenu', $torneosMenu);
        });

    }
}
