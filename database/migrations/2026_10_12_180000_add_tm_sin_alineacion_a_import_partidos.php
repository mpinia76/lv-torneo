<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los partidos cuyo detalle TM devolvió SIN alineación (amateurs,
 * partidos viejos), para que la tanda de detalles no los vuelva a pagar.
 * Lo escribe y lo lee ImportDetallesController::tanda().
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('import_partidos', function (Blueprint $table) {
            $table->timestamp('tm_sin_alineacion_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('import_partidos', function (Blueprint $table) {
            $table->dropColumn('tm_sin_alineacion_at');
        });
    }
};
