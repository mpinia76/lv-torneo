<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las personas cuya fecha de nacimiento NO existe en ninguna fuente.
 *
 * La pestaña "Sin fecha de nacimiento" mezclaba dos cosas muy distintas:
 * las que todavía no se buscaron y las que ya se buscaron en todos lados y
 * no aparecen (el caso típico: árbitros viejos sin ficha en Transfermarkt,
 * que son 196 de las 294). Sin manera de separarlas, el contador queda
 * clavado para siempre y deja de servir como medida de trabajo pendiente.
 *
 * Acá se guarda la decisión manual: "de esta no hay fecha, no la busques
 * más". Se puede deshacer.
 *
 * Por qué una tabla aparte y no un estado más en `persona_fecha_tm`:
 *   1. `persona_fecha_tm` es la bitácora automática de lo que se le
 *      preguntó a Transfermarkt (con qué id, por qué vía, cuántas veces).
 *      Esto es un juicio humano; mezclarlos haría que "reintentar las ya
 *      consultadas" borre decisiones tomadas a mano.
 *   2. Las 279 sin id de TM ni siquiera tienen fila en esa bitácora,
 *      porque nunca se las consultó.
 */
class CreatePersonaSinFechaTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('persona_sin_fecha')) {
            Schema::create('persona_sin_fecha', function (Blueprint $table) {
                $table->unsignedBigInteger('persona_id')->primary();
                // Dónde se buscó y no estaba. Sirve para el que venga después:
                // sin esto, dentro de un año nadie sabe si se buscó en serio.
                $table->string('motivo', 200)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        // `persona_fecha_tm` (la bitácora de consultas a Transfermarkt) existe
        // en producción pero nunca tuvo migración: en una instalación limpia
        // los guards `Schema::hasTable()` de TmFechas la saltean sin avisar y
        // la app vuelve a pedir eternamente las fichas que ya dieron negativo.
        // Se crea acá, con la misma forma que TmFechas::registrar() escribe.
        if (!Schema::hasTable('persona_fecha_tm')) {
            Schema::create('persona_fecha_tm', function (Blueprint $table) {
                $table->unsignedBigInteger('persona_id')->primary();
                $table->string('tm_id', 32)->nullable();
                // varchar y no enum a propósito: las fuentes y los estados se
                // agregan de a poco y un enum obliga a un ALTER por cada uno.
                $table->string('fuente', 20)->nullable();   // api | html
                $table->string('estado', 20)->nullable();   // completada | sin_fecha | sin_perfil
                $table->unsignedInteger('intentos')->default(0);
                $table->timestamp('consultado_at')->nullable();
                $table->timestamps();
                $table->index('estado', 'persona_fecha_tm_estado_idx');
            });
        }
    }

    public function down()
    {
        // No se toca `persona_fecha_tm`: es anterior a esta migración y en
        // producción tiene datos que no se crearon acá.
        Schema::dropIfExists('persona_sin_fecha');
    }
}
