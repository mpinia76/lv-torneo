<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTituloTorneosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('titulo_torneos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('titulo_id');
            $table->unsignedBigInteger('torneo_id');

            $table->foreign('titulo_id')->references('id')->on('titulos')->onDelete('cascade');
            $table->foreign('torneo_id')->references('id')->on('torneos')->onDelete('cascade');
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
        Schema::dropIfExists('titulo_torneos');
    }
}
