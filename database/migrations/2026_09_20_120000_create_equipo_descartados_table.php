<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pares de equipos que YA se miraron y no son el mismo club.
 *
 * La pantalla «Equipos que parecen el mismo club» aparea por nombre, y hay
 * clubes distintos que se llaman casi igual y se van a llamar casi igual para
 * siempre: Ferro (Caballito) y Ferro de General Pico, Huracán y Huracán de
 * Tres Arroyos, Liniers y Liniers de La Matanza. Sin esta tabla esos cuatro
 * vuelven a salir en cada carga y la pantalla deja de mirarse.
 *
 * Es deliberadamente mínima —el par, el motivo y quién lo marcó—, nada de
 * puntajes ni estados: los candidatos se calculan al vuelo, acá sólo vive la
 * decisión humana, que es lo único que no se puede recalcular.
 *
 * Invariante, igual que en `persona_duplicados`: `equipo_id` es SIEMPRE el id
 * menor y `equipo2_id` el mayor. Sin eso el mismo par entra dos veces y el
 * índice único no sirve de nada.
 *
 * Sin foreign key a propósito: si después se borra uno de los dos equipos, la
 * fila queda colgada y no molesta a nadie, mientras que una FK haría fallar el
 * borrado justo en la pantalla que lo ofrece.
 */
class CreateEquipoDescartadosTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('equipo_descartados')) return;

        Schema::create('equipo_descartados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('equipo_id');   // el menor de los dos
            $table->unsignedBigInteger('equipo2_id');  // el mayor
            $table->string('motivo', 255)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['equipo_id', 'equipo2_id'], 'equipo_descartados_par_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('equipo_descartados');
    }
}
