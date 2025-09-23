<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTorneoClasificacionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('torneo_clasificacions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('torneo_id');
            $table->string('nombre'); // 'Libertadores', 'Sudamericana'
            $table->integer('cantidad'); // Ej: 4
            $table->timestamps();

            $table->foreign('torneo_id')->references('id')->on('torneos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('torneo_clasificacions');
    }
}
