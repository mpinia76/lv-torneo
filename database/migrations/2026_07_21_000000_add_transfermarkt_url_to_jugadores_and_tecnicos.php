<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransfermarktUrlToJugadoresAndTecnicos extends Migration
{
    public function up()
    {
        Schema::table('jugadores', function (Blueprint $table) {
            if (!Schema::hasColumn('jugadores', 'transfermarkt_url')) {
                $table->string('transfermarkt_url')->nullable()->after('url_nombre');
            }
        });

        Schema::table('tecnicos', function (Blueprint $table) {
            if (!Schema::hasColumn('tecnicos', 'transfermarkt_url')) {
                $table->string('transfermarkt_url')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('jugadores', function (Blueprint $table) {
            if (Schema::hasColumn('jugadores', 'transfermarkt_url')) {
                $table->dropColumn('transfermarkt_url');
            }
        });

        Schema::table('tecnicos', function (Blueprint $table) {
            if (Schema::hasColumn('tecnicos', 'transfermarkt_url')) {
                $table->dropColumn('transfermarkt_url');
            }
        });
    }
}
