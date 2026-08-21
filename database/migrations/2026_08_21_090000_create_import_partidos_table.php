<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging de partidos importados.
 *
 * Acá cae, tal cual viene de la fuente, cada partido dirigido por un DT.
 * Nada de esto entra a `partidos` sin pasar por una revisión: esta tabla es
 * el paso intermedio que permite ver qué se trajo, qué ya estaba cargado y
 * qué necesita una decisión manual.
 *
 * No toca ninguna tabla existente.
 */
class CreateImportPartidosTable extends Migration
{
    public function up()
    {
        Schema::create('import_partidos', function (Blueprint $table) {
            $table->id();

            $table->string('fuente', 30)->default('transfermarkt');
            $table->string('external_id', 40)->nullable();   // gameId del partido en la fuente

            // De quién vino
            $table->unsignedBigInteger('tecnico_id')->nullable();
            $table->string('coach_external_id', 40)->nullable();

            // Competencia y temporada
            $table->string('competencia_external_id', 40)->nullable();
            $table->string('competencia_nombre', 191)->nullable();
            $table->string('temporada', 20)->nullable();
            $table->string('ronda', 100)->nullable();        // jornada / fase, si la fuente la da

            // Clubes (el dirigido y el rival, con el nombre crudo de la fuente)
            $table->string('club_external_id', 40)->nullable();
            $table->string('club_nombre', 191)->nullable();
            $table->string('rival_external_id', 40)->nullable();
            $table->string('rival_nombre', 191)->nullable();
            $table->boolean('local')->nullable();

            $table->dateTime('dia')->nullable();
            $table->integer('goles_favor')->nullable();
            $table->integer('goles_contra')->nullable();

            // Resolución contra nuestras tablas
            $table->unsignedBigInteger('equipo_id')->nullable();
            $table->unsignedBigInteger('rival_id')->nullable();
            $table->unsignedBigInteger('partido_id')->nullable();

            // nuevo      = no existe y se puede crear
            // duplicado  = ya está cargado (solo puede faltarle el partido_tecnico)
            // conflicto  = falta resolver algo (equipo desconocido, fecha ambigua)
            // excluido   = fuera de alcance (pre-2000, competencia no admitida)
            // aplicado   = ya se volcó a `partidos`
            $table->string('estado', 20)->default('nuevo');
            $table->string('motivo', 191)->nullable();

            $table->text('payload')->nullable();             // JSON crudo del partido

            $table->timestamps();

            $table->index(['tecnico_id', 'estado']);
            $table->index(['fuente', 'external_id']);
            $table->index('dia');
        });
    }

    public function down()
    {
        Schema::dropIfExists('import_partidos');
    }
}
