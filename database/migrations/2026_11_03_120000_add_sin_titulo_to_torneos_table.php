<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneo que NO da título (Apertura / Clausura / Intermedio de Uruguay: el
 * campeón uruguayo sale de «Playoffs Liga AUF»). Se le pueden guardar las
 * posiciones —y así tiene goleador y queda «completo» en el Control—, pero
 * el primero no suma título en ninguna parte (fichas de equipo, jugador y
 * técnico, títulos, rankings). Ver Torneo::idsSinTitulo().
 * En producción se corre a mano (database/sql/sin_titulo.sql); el guard
 * evita el choque.
 */
class AddSinTituloToTorneosTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('torneos', 'sin_titulo')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->boolean('sin_titulo')->default(0)->after('inconcluso');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('torneos', 'sin_titulo')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->dropColumn('sin_titulo');
            });
        }
    }
}
