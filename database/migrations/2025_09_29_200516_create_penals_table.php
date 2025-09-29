<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePenalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('penals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partido_id');
            $table->foreign('partido_id')->references('id')->on('partidos');
            $table->unsignedBigInteger('jugador_id');
            $table->foreign('jugador_id')->references('id')->on('jugadors');
            $table->integer('minuto')->nullable();
            $table->enum('tipo', ['Errado', 'Atajado', 'Atajó']);
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
        Schema::dropIfExists('penals');
    }
}
