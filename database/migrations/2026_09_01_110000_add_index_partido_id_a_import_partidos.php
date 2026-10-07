<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Índice por `partido_id` en `import_partidos`.
 *
 * La tabla se creó sin él y ya hay tres lugares que la consultan por partido:
 * `Controles::agregarTransfermarkt()` (que por eso hace UNA consulta por página
 * en vez de una por fila), los pases de penales y tipos de gol, y la búsqueda de
 * partidos sin gameId del control de tipos, que la cruza contra `partidos`
 * entera. Sin índice, ese cruce es un scan completo por cada partido.
 *
 * No cambia ningún dato: es sólo velocidad.
 */
class AddIndexPartidoIdAImportPartidos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if ($this->existe()) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->index('partido_id', 'import_partidos_partido_id_index');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if (!$this->existe()) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->dropIndex('import_partidos_partido_id_index');
        });
    }

    /** MySQL no tiene CREATE INDEX IF NOT EXISTS: se pregunta a mano. */
    private function existe()
    {
        $filas = DB::select("SHOW INDEX FROM `import_partidos` WHERE Key_name = 'import_partidos_partido_id_index'");
        return !empty($filas);
    }
}
