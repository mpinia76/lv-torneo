<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `equipos.desaparicion` — cuándo dejó de existir el club.
 *
 * Transfermarkt no renombra la ficha de un club que desaparece: le cuelga el
 * año de cierre al nombre, "Al-Ahli Dubai Club (- 2017)" o "Sarayköy 1926 FK
 * (1981-2019)". Ese paréntesis entraba tal cual a `equipos.nombre`. Ahora el
 * nombre queda limpio y el año va acá (ver `App\Services\ClubDesaparecido`).
 *
 * Es fecha completa, igual que la fundación. De TM sale sólo el año, así que
 * lo que se carga desde el paréntesis es el 1º de enero: el día real se
 * corrige a mano en la edición del equipo.
 *
 * No cambia ningún dato existente: la columna arranca en NULL. Lo ya cargado
 * se repasa en /admin/import-detalles/clubes-desaparecidos.
 */
class AddDesaparicionAEquipos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('equipos')) return;
        if (Schema::hasColumn('equipos', 'desaparicion')) return;

        Schema::table('equipos', function (Blueprint $table) {
            $table->date('desaparicion')->nullable()->after('fundacion');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('equipos')) return;
        if (!Schema::hasColumn('equipos', 'desaparicion')) return;

        Schema::table('equipos', function (Blueprint $table) {
            $table->dropColumn('desaparicion');
        });
    }
}
