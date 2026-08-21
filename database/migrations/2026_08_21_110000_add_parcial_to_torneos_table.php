<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los torneos que se cargaron incompletos (solo los partidos que dirigió
 * un DT, no el fixture entero).
 *
 * Sirve para excluirlos de tablas de posiciones, promedios y acumulados: los
 * partidos son reales y valen para la ficha del DT, del equipo y del jugador,
 * pero la tabla de ese torneo no significa nada.
 */
class AddParcialToTorneosTable extends Migration
{
    public function up()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->boolean('parcial')->default(0);
        });
    }

    public function down()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropColumn('parcial');
        });
    }
}
