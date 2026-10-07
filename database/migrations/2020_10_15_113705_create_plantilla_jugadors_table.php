<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlantillaJugadorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plantilla_jugadors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plantilla_id');
            $table->foreign('plantilla_id')->references('id')->on('plantillas');
            $table->unsignedBigInteger('jugador_id');
            $table->foreign('jugador_id')->references('id')->on('jugadors');
            $table->integer('dorsal')->nullable();
            $table->text('foto')->nullable();
            $table->text('observaciones')->nullable();


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
        Schema::dropIfExists('plantilla_jugadors');
    }
}
