<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTecnicoEstadisticaManualsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tecnico_estadistica_manuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jugador_id')->constrained()->onDelete('cascade');
            $table->foreignId('equipo_id')->constrained()->onDelete('cascade');

            // torneo flexible
            $table->string('torneo_nombre');
            $table->string('torneo_logo')->nullable();
            $table->enum('tipo', ['Liga', 'Copa']);
            $table->enum('ambito', ['Nacional', 'Internacional'])->default('Nacional');

            // estadísticas
            $table->integer('partidos')->default(0);
            $table->integer('ganados')->default(0);
            $table->integer('empatados')->default(0);
            $table->integer('perdidos')->default(0);

            $table->integer('goles_favor')->default(0);
            $table->integer('goles_en_contra')->default(0);

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
        Schema::dropIfExists('tecnico_estadistica_manuals');
    }
}
