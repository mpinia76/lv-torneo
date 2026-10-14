<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ids de livefutbol.com, para el botón "LF" de /admin/controles.
 *
 * livefutbol arma la ficha de un partido con un id propio
 * (/match-report/ma608664/) que no se puede deducir de nada nuestro: hay que
 * buscarlo en el calendario de uno de los dos clubes, que a su vez tiene su
 * propio id (/teams/te86/). Lo que se encuentra queda acá, así la segunda vez
 * el botón va directo.
 *
 * Tablas aparte (y no columnas en `partidos` / `equipos`) para no tocar las
 * tablas grandes. `equipos.url_id` NO es el id de livefutbol: es de la fuente
 * vieja del importador por fecha y apunta a otros clubes.
 *
 * origen:
 *   slug    = el club se encontró por /equipos/{slug}/ (redirige a /teams/teN/)
 *   partido = salió de la fila del rival en el calendario del otro club
 *   manual  = se pegó la URL a mano
 */
class CreateLivefutbolIdsTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('partido_livefutbol')) {
            Schema::create('partido_livefutbol', function (Blueprint $table) {
                $table->unsignedBigInteger('partido_id')->primary();
                $table->unsignedBigInteger('match_id');
                $table->string('origen', 20)->default('busqueda');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('equipo_livefutbol')) {
            Schema::create('equipo_livefutbol', function (Blueprint $table) {
                $table->unsignedBigInteger('equipo_id')->primary();
                $table->unsignedBigInteger('team_id');
                $table->string('origen', 20)->default('slug');
                $table->timestamps();

                $table->index('team_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('partido_livefutbol');
        Schema::dropIfExists('equipo_livefutbol');
    }
}
