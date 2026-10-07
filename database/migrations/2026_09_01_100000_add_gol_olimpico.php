<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * El gol olímpico: el que entra directo desde el saque de esquina.
 *
 * Tres cambios, todos aditivos (nada de lo cargado cambia de valor):
 *
 * 1) `gols.tipo` suma 'Olímpico' al enum. Hasta ahora ese gol caía en 'Jugada'
 *    (Transfermarkt lo manda como `actionId` 211 / "direct corner", que el
 *    importador no reconocía) o en 'Tiro Libre' cuando venía del scraper de
 *    estadísticas manuales, que metía "Gol directo de un saque de esquina" en
 *    la misma bolsa que el libre directo.
 *
 * 2) `jugador_estadistica_manuals.goles_olimpico`: sin esta columna los cuadros
 *    de goleadores mezclarían dos criterios — los goles de partidos cargados
 *    separarían el olímpico y los manuales lo seguirían escondiendo adentro de
 *    otro tipo.
 *
 * 3) `import_partidos.tipos_gol_revisado_at`: la marca del relevamiento. Qué
 *    partidos tienen un olímpico NO se puede saber desde la base —el tipo de
 *    gol sólo existe en Transfermarkt—, así que hay que preguntarlo partido por
 *    partido y eso cuesta 1 llamada. La marca hace que esa llamada se pague una
 *    sola vez, igual que `penales_revisado_at`.
 *
 * El valor va con tilde ('Olímpico') porque es el que se muestra en pantalla y
 * en todo el sistema el valor y la etiqueta son lo mismo. La comparación de
 * enums en MySQL es insensible a mayúsculas y acentos con la collation por
 * defecto, así que un 'Olimpico' escrito sin tilde igual matchea.
 */
class AddGolOlimpico extends Migration
{
    public function up()
    {
        // 1) El enum. Schema::table no sabe modificar enums sin doctrine/dbal,
        //    así que va en SQL crudo, con la lista COMPLETA de valores: un
        //    MODIFY reemplaza el enum entero, no le agrega nada.
        DB::statement("ALTER TABLE `gols` MODIFY `tipo` "
            . "ENUM('Cabeza','En Contra','Jugada','Penal','Tiro Libre','Olímpico') NOT NULL");

        // 2) La columna de las estadísticas manuales.
        if (!Schema::hasColumn('jugador_estadistica_manuals', 'goles_olimpico')) {
            Schema::table('jugador_estadistica_manuals', function (Blueprint $table) {
                $table->integer('goles_olimpico')->default(0)->after('goles_jugada');
            });
        }

        // 3) La marca del relevamiento.
        if (!Schema::hasColumn('import_partidos', 'tipos_gol_revisado_at')) {
            Schema::table('import_partidos', function (Blueprint $table) {
                $table->dateTime('tipos_gol_revisado_at')->nullable();
            });
        }
    }

    public function down()
    {
        // Ojo: si ya hay goles cargados como 'Olímpico', volver atrás el enum
        // los deja en ''. Por eso primero se los devuelve a 'Jugada', que es
        // donde estaban antes de que existiera el tipo.
        DB::table('gols')->where('tipo', 'Olímpico')->update(['tipo' => 'Jugada']);
        DB::statement("ALTER TABLE `gols` MODIFY `tipo` "
            . "ENUM('Cabeza','En Contra','Jugada','Penal','Tiro Libre') NOT NULL");

        if (Schema::hasColumn('jugador_estadistica_manuals', 'goles_olimpico')) {
            Schema::table('jugador_estadistica_manuals', function (Blueprint $table) {
                $table->dropColumn('goles_olimpico');
            });
        }
        if (Schema::hasColumn('import_partidos', 'tipos_gol_revisado_at')) {
            Schema::table('import_partidos', function (Blueprint $table) {
                $table->dropColumn('tipos_gol_revisado_at');
            });
        }
    }
}
