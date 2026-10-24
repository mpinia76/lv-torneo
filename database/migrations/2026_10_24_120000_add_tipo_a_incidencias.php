<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo de la incidencia (ver `App\Incidencia::TIPOS` y `TAPA`).
 *
 * Hasta acá la incidencia era sólo texto y cualquiera sacaba al partido de
 * todos los controles de carga. Con el tipo, cada una tapa sólo los controles
 * que explica, y las de escritorio / no jugado anulan los goles del partido
 * para las estadísticas.
 *
 * NULL = incidencia vieja sin clasificar: se sigue tratando como antes.
 * La clasificación de las que ya existen está en
 * `database/sql/incidencias_tipo.sql`.
 *
 * En producción se corre a mano (el ALTER del principio de ese .sql); el
 * guard evita el choque.
 */
class AddTipoAIncidencias extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('incidencias', 'tipo')) {
            Schema::table('incidencias', function (Blueprint $table) {
                $table->string('tipo', 40)->nullable()->after('partido_id');
                $table->index(['partido_id', 'tipo'], 'incidencias_partido_tipo');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('incidencias', 'tipo')) {
            Schema::table('incidencias', function (Blueprint $table) {
                $table->dropIndex('incidencias_partido_tipo');
                $table->dropColumn('tipo');
            });
        }
    }
}
