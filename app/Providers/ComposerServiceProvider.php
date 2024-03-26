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

            $view->with('torneos', $torneos);
        });

    }
}
