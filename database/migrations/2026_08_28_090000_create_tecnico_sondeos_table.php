<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Constancia del último sondeo de cada DT.
 *
 * El staging no alcanza como registro: un DT de Reserva o de juveniles guarda
 * CERO filas en `import_partidos` —sus competencias quedan fuera de 1ra a
 * propósito— y la lista de DTs deducía "sondeado" de que hubiera filas. Ese DT
 * decía "sin sondear" para siempre y se le gastaba una llamada a la API cada
 * vez que se lo intentaba.
 *
 * Acá queda cuándo se lo sondeó y qué dio, incluso cuando el resultado fue
 * "nada para cargar".
 */
class CreateTecnicoSondeosTable extends Migration
{
    public function up()
    {
        Schema::create('tecnico_sondeos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tecnico_id')->unique();
            $table->dateTime('sondeado_at')->nullable();

            $table->integer('partidos')->default(0);        // lo que trajo TM, antes de filtrar
            $table->integer('fuera_1ra')->default(0);       // Reserva / Proyección / juveniles / ascenso
            $table->integer('fuera_alcance')->default(0);   // pre-2000
            $table->integer('duplicados')->default(0);
            $table->integer('nuevos')->default(0);
            $table->integer('conflictos')->default(0);
            $table->integer('guardadas')->default(0);       // filas que quedaron en import_partidos

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('tecnico_sondeos');
    }
}
