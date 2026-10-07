<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `import_partidos.sin_detalle_at` — la marca de «a este partido nunca se le
 * bajó el detalle de Transfermarkt».
 *
 * El repaso de tipos de gol lo detecta gratis: si NINGUNO de los goles de la
 * base apareó con los de TM y de los dos lados había goles, los goles son los
 * que cargaste a mano y el mapeo de jugadores nunca se creó. Hasta ahora eso
 * salía como una línea del informe de la tanda con un link «rehacele el
 * detalle» — y en el modo continuado (`seguir=1`) ese informe se lo lleva la
 * tanda siguiente ocho segundos después. Los avisos pasaban de largo y no
 * quedaba rastro de cuáles eran.
 *
 * Con la marca, la lista es una consulta: sobrevive a la cadena, al cierre de
 * la pestaña y al 504. La borra sola la bajada del detalle
 * (`TmDetallePartido::importar()`), que es exactamente lo que arregla el caso.
 *
 * No cambia ningún dato existente: la columna arranca en NULL.
 */
class AddSinDetalleAImportPartidos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if (Schema::hasColumn('import_partidos', 'sin_detalle_at')) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->timestamp('sin_detalle_at')->nullable()->after('partido_id');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if (!Schema::hasColumn('import_partidos', 'sin_detalle_at')) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->dropColumn('sin_detalle_at');
        });
    }
}
