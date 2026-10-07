<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ata cada torneo a su competencia de Transfermarkt.
 *
 * Sin esto no hay forma de saber que "Clausura 2026" es `ARGC`: el importador
 * de fixture pedía el id a mano. Mismo criterio que las columnas `sofa_*`.
 *
 * `tm_season_id` casi siempre difiere del año del torneo: el Clausura 2026 es
 * seasonId 2025 en TM.
 */
class AddTmCompetitionToTorneos extends Migration
{
    public function up()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->string('tm_competition_id', 20)->nullable()->after('sofa_category_slug');
            $table->string('tm_season_id', 10)->nullable()->after('tm_competition_id');
        });
    }

    public function down()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropColumn(['tm_competition_id', 'tm_season_id']);
        });
    }
}
