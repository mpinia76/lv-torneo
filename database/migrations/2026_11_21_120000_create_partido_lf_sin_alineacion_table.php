<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partidos que livefutbol TAMPOCO tiene con alineación (la ficha sólo muestra
 * «Historial»). Los marca el botón «LF tampoco tiene» de la lista
 * /admin/import-detalles/livefutbol/sin-datos, para que no vuelvan a aparecer
 * ahí. No toca la incidencia «sin datos en TM» del partido, que sigue siendo
 * cierta.
 */
class CreatePartidoLfSinAlineacionTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('partido_lf_sin_alineacion')) {
            return;
        }
        Schema::create('partido_lf_sin_alineacion', function (Blueprint $table) {
            $table->unsignedBigInteger('partido_id')->primary();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('partido_lf_sin_alineacion');
    }
}
