<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personas de livefutbol.com -> nuestras fichas, para el importador de detalle
 * desde LiveFutbol (ver App\Services\LivefutbolDetalle).
 *
 * Mismo criterio que `jugador_tm` / `arbitro_tm`: una vez atado un id de
 * livefutbol (`/person/pe{ID}/`), la próxima vez se aparea exacto y no se
 * vuelve a buscar por nombre. Una sola tabla para los tres roles porque el id
 * de livefutbol es de la PERSONA: el mismo pe123 puede ser jugador y después DT.
 *
 * origen:
 *   plantilla = apareó contra la plantilla del equipo en el torneo
 *   nombre    = apareó por nombre en toda la base (queda para revisar)
 *   partido   = ya estaba cargado en este partido (DT o árbitro)
 *   elegido   = lo eligió el usuario en la vista previa
 *   creado    = no estaba y se creó
 *
 * `revisar` marca lo que nadie miró todavía.
 */
class CreateLivefutbolPersonasTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('livefutbol_personas')) {
            return;
        }

        Schema::create('livefutbol_personas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lf_id');
            $table->string('rol', 10);              // jugador | tecnico | arbitro
            $table->unsignedBigInteger('ref_id');   // jugadors.id / tecnicos.id / arbitros.id
            $table->string('nombre_lf', 191)->nullable();
            $table->string('origen', 20)->default('creado');
            $table->boolean('revisar')->default(false);
            $table->timestamps();

            $table->unique(['lf_id', 'rol']);
            $table->index(['rol', 'ref_id']);
            $table->index('revisar');
        });
    }

    public function down()
    {
        Schema::dropIfExists('livefutbol_personas');
    }
}
