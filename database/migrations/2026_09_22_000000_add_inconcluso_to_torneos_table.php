<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torneo suspendido que nunca terminó (Copa de la Superliga 2020, pandemia).
 * El Control de torneos lo saca de "faltan partidos" y "sin posiciones".
 * En producción se corrió a mano en phpMyAdmin; el guard evita el choque.
 */
class AddInconclusoToTorneosTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('torneos', 'inconcluso')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->boolean('inconcluso')->default(0)->after('parcial');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('torneos', 'inconcluso')) {
            Schema::table('torneos', function (Blueprint $table) {
                $table->dropColumn('inconcluso');
            });
        }
    }
}
