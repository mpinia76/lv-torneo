<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `import_partidos.clubes_mal_at` — la marca de «para este `external_id`,
 * Transfermarkt dice otros clubes que los que juegan el partido».
 *
 * Los dos pases livianos (tipos de gol y penales) chequean los clubes DESPUÉS
 * de bajar el JSON —antes no hay con qué— y, si no aparean, salen sin escribir
 * y sin marcar el partido como revisado. O sea que el partido vuelve a salir
 * primero en la tanda siguiente y vuelve a pagar la llamada: gasta **una
 * llamada por tanda, para siempre**, y encima es lo primero que se ve del
 * informe.
 *
 * Con la marca salen de la cola y quedan en una lista aparte con el link a
 * `clubes-tm?partido_id=N`, que es donde se arregla. La borra sola el primer
 * pase que consigue aparearlos, así que no hay que destildar nada.
 *
 * Las dos causas que la llenan (ver la memoria [[club-partido-en-dos-equipos]]):
 * un club nuestro partido en dos equipos —TM renombra el verein cuando el club
 * se muda— o un `external_id` que es de otro partido.
 *
 * No cambia ningún dato existente: la columna arranca en NULL.
 */
class AddClubesMalAImportPartidos extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if (Schema::hasColumn('import_partidos', 'clubes_mal_at')) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->timestamp('clubes_mal_at')->nullable()->after('partido_id');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('import_partidos')) return;
        if (!Schema::hasColumn('import_partidos', 'clubes_mal_at')) return;

        Schema::table('import_partidos', function (Blueprint $table) {
            $table->dropColumn('clubes_mal_at');
        });
    }
}
