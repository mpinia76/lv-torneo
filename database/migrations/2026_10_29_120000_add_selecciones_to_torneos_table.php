<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneo de selecciones (Mundial, Copa América, Eliminatorias, Eurocopa…).
 * La marca va en el torneo y no en el equipo: un club nunca juega uno de
 * estos, así que todo lo jugado en un torneo marcado es "con la selección".
 * En producción se corre a mano (database/sql/selecciones.sql); el guard
 * evita el choque.
 */
class AddSeleccionesToTorneosTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('torneos', 'selecciones')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->boolean('selecciones')->default(0)->after('ambito');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('torneos', 'selecciones')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->dropColumn('selecciones');
            });
        }
    }
}
