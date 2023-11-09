<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePosicionTorneosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posicion_torneos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('torneo_id');
            $table->foreign('torneo_id')->references('id')->on('torneos');
            $table->unsignedBigInteger('equipo_id');
            $table->foreign('equipo_id')->references('id')->on('equipos');
            $table->integer('posicion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('posicion_torneos');
    }
}
