<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapeo entre nuestros equipos y los clubes de Transfermarkt.
 *
 * El nombre nunca va a alcanzar ("CA River Plate" vs "River Plate",
 * "AA Argentinos Juniors", "CS Independiente Rivadavia"). El clubId de TM sí:
 * es estable y no cambia nunca. Una vez que un club está acá, no se vuelve a
 * preguntar por él jamás.
 *
 * origen:
 *   inferido = deducido de un partido que ya estaba cargado (fecha + rival + resultado)
 *   nombre   = matcheó por nombre normalizado
 *   manual   = lo cargaste vos
 */
class CreateEquipoTmTable extends Migration
{
    public function up()
    {
        Schema::create('equipo_tm', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipo_id');
            $table->string('tm_club_id', 40)->unique();
            $table->string('nombre_tm', 191)->nullable();
            $table->string('origen', 20)->default('manual');
            $table->timestamps();

            $table->index('equipo_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipo_tm');
    }
}
