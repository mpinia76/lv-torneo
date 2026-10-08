<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupo de una clasificación a copa que es del CAMPEÓN de otro torneo
 * (Copa de Francia → Europa League, Copa Argentina → Libertadores).
 * Ver App\Services\CuposCampeon.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('torneo_clasificacions', function (Blueprint $table) {
            $table->unsignedBigInteger('campeon_torneo_id')->nullable()->after('cantidad');
            $table->foreign('campeon_torneo_id')->references('id')->on('torneos')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('torneo_clasificacions', function (Blueprint $table) {
            $table->dropForeign(['campeon_torneo_id']);
            $table->dropColumn('campeon_torneo_id');
        });
    }
};
