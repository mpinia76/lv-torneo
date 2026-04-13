<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJugadorEstadisticaManualsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jugador_estadistica_manuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jugador_id')->constrained()->onDelete('cascade');
            $table->foreignId('equipo_id')->constrained()->onDelete('cascade');

            // torneo flexible
            $table->string('torneo_nombre');
            $table->string('torneo_logo')->nullable();

            // estadísticas
            $table->integer('partidos')->default(0);
            $table->integer('goles_cabeza')->default(0);
            $table->integer('goles_penal')->default(0);
            $table->integer('goles_tiro_libre')->default(0);
            $table->integer('goles_jugada')->default(0);
            $table->integer('goles_en_contra')->default(0);
            $table->integer('amarillas')->default(0);
            $table->integer('rojas')->default(0);
            $table->integer('penales_errados')->default(0);
            $table->integer('penales_atajados')->default(0);
            $table->integer('goles_recibidos')->default(0);
            $table->integer('vallas_invictas')->default(0);

            $table->timestamps();

            // evitar duplicados
            $table->unique(['jugador_id', 'equipo_id', 'torneo_nombre'], 'jugador_equipo_torneo_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jugador_estadistica_manuals');
    }
}
