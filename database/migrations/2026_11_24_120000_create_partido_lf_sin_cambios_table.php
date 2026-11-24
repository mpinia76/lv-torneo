<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partidos rehechos con livefutbol que quedaron sin cambios y están bien así
 * (nadie entró desde el banco). Los marca el botón «Está bien sin cambios» de
 * /admin/import-detalles/livefutbol/sin-cambios, para que no vuelvan a
 * aparecer ahí.
 *
 * Esa lista existe porque hasta el 24/11/2026 el favorito «LF → La Planilla»
 * descartaba los cambios que livefutbol publica sin minuto (sólo la flecha),
 * y los partidos rehechos así quedaron sin ningún cambio.
 */
class CreatePartidoLfSinCambiosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('partido_lf_sin_cambios')) {
            return;
        }
        Schema::create('partido_lf_sin_cambios', function (Blueprint $table) {
            $table->unsignedBigInteger('partido_id')->primary();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('partido_lf_sin_cambios');
    }
}
