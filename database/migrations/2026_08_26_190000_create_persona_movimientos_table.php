<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora del traspaso PARCIAL de carrera (MoverRegistros).
 *
 * Caso que la motivó: dos homónimos que sí son personas distintas —Iván Gómez
 * #291 (1997) y #3160 (1990)— donde un tramo, Platense 2021-2022, está colgado
 * de la ficha equivocada. No hay nada que fusionar: hay que mover ese tramo y
 * dejar las dos personas vivas.
 *
 * Por qué no se reusa `persona_fusiones`: ahí `absorbida_id` significa "esta
 * persona ya no existe". En un movimiento parcial las dos siguen existiendo, y
 * guardar el origen en esa columna haría que cualquier consulta sobre fusiones
 * dé por muerta a alguien que está vivo.
 *
 * Lo importante de la bitácora es `detalle`: qué plantillas y qué partidos se
 * movieron. Es lo único que permite deshacer a mano un movimiento equivocado.
 */
class CreatePersonaMovimientosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('persona_movimientos')) {
            return;
        }

        Schema::create('persona_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('persona_origen_id');
            $table->unsignedBigInteger('persona_destino_id');
            // Sin foreign keys a propósito: la bitácora tiene que sobrevivir al
            // borrado de cualquiera de las dos personas. Si una se borra después,
            // el renglón sigue explicando qué pasó con esos partidos.
            $table->unsignedBigInteger('ficha_origen_id')->nullable();
            $table->unsignedBigInteger('ficha_destino_id')->nullable();
            $table->string('rol', 20);
            // El nombre del club tal como se veía en la pantalla al mover.
            $table->string('etiqueta', 191)->nullable();
            $table->unsignedInteger('plantillas')->default(0);
            $table->unsignedInteger('partidos')->default(0);
            $table->unsignedInteger('filas')->default(0);
            $table->text('detalle')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('persona_origen_id', 'persona_movimientos_origen_idx');
            $table->index('persona_destino_id', 'persona_movimientos_destino_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('persona_movimientos');
    }
}
