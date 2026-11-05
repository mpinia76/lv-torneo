<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El goleador de cada torneo completo. La llena `php artisan torneos:goleadores`
 * (App\Services\GoleadoresTorneo); no se edita a mano.
 *
 * En producción se crea a mano con database/sql/torneo_goleadores.sql; el guard
 * evita el choque.
 */
class CreateTorneoGoleadoresTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('torneo_goleadores')) return;

        Schema::create('torneo_goleadores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('torneo_id');
            $table->unsignedBigInteger('jugador_id');
            $table->unsignedSmallInteger('goles');
            $table->boolean('compartido')->default(0); // empate arriba, sin goles faltantes
            $table->boolean('inconcluso')->default(0); // torneo suspendido: goleador al momento del corte
            $table->timestamps();

            $table->unique(['torneo_id', 'jugador_id'], 'torneo_goleadores_torneo_jugador');
            $table->index('jugador_id', 'torneo_goleadores_jugador');
        });
    }

    public function down()
    {
        Schema::dropIfExists('torneo_goleadores');
    }
}
