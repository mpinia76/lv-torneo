<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePartidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partidos', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha')->nullable();
            $table->unsignedBigInteger('fecha_id');
            $table->foreign('fecha_id')->references('id')->on('fechas');
            $table->unsignedBigInteger('equipol_id')->nullable();
            $table->foreign('equipol_id')->references('id')->on('equipos');
            $table->unsignedBigInteger('equipov_id')->nullable();
            $table->foreign('equipov_id')->references('id')->on('equipos');
            $table->integer('golesl')->nullable();
            $table->integer('golesv')->nullable();
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
        Schema::dropIfExists('partidos');
    }
}
