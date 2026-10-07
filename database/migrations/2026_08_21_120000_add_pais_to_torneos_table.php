<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * País del torneo. SOLO tiene sentido en los de ámbito Nacional: una liga
 * chilena o una copa mexicana se distinguen de las argentinas por acá.
 *
 * Los internacionales quedan con país NULL: no son de ningún país. A esos los
 * ordena `region` (Conmebol, FIFA, UEFA, Concacaf…), que ya existía en la
 * tabla y no lo usaba nadie.
 */
class AddPaisToTorneosTable extends Migration
{
    public function up()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->string('pais', 100)->nullable();
        });

        // Lo ya cargado: los nacionales son todos argentinos. Los internacionales
        // se quedan en NULL.
        DB::table('torneos')->where('ambito', 'Nacional')->update(['pais' => 'Argentina']);
    }

    public function down()
    {
        Schema::table('torneos', function (Blueprint $table) {
            $table->dropColumn('pais');
        });
    }
}
