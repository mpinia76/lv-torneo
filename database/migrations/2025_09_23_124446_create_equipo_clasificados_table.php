<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEquipoClasificadosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('equipo_clasificados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('torneo_id'); // torneo actual
            $table->unsignedBigInteger('equipo_id');
            $table->unsignedBigInteger('torneo_clasificacion_id'); // referencia a TorneoClasificacion
            $table->timestamps();

            $table->foreign('torneo_id')->references('id')->on('torneos');
            $table->foreign('equipo_id')->references('id')->on('equipos');
            $table->foreign('torneo_clasificacion_id')->references('id')->on('torneo_clasificacions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipo_clasificados');
    }
}
