<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los minutos de descuento: el 90+6.
 *
 * Hasta ahora una incidencia guardaba un solo número y cada fuente lo llenaba
 * con un criterio distinto:
 *
 *   · Transfermarkt: `accionesDelLado()` leía `minute` y TIRABA `addedTime`, así
 *     que el 90+6 y el 90+9 entraban los dos como minuto 90. Dos goles del
 *     mismo jugador en el descuento quedaban indistinguibles de una carga
 *     duplicada, y el control «Goles repetidos» los marcaba como tal.
 *   · Promiedos: sumaba el añadido del segundo tiempo (90+6 → 96) y colapsaba
 *     el del primero (45+2 → 45). O sea que el mismo gol se guardaba distinto
 *     según de dónde hubiera venido.
 *
 * A partir de acá `minuto` es SIEMPRE el minuto del reloj —45, 90, 105, 120— y
 * el descuento va aparte, en `adicionado`. Un gol a los 90+6 es
 * `minuto = 90, adicionado = 6`.
 *
 * Por qué en una columna aparte y no sumado:
 *   · 45+2 deja de confundirse con 47 (un minuto del segundo tiempo).
 *   · 90+6 deja de confundirse con 96 (un minuto del primer suplementario).
 *   · Todo lo cargado hasta hoy sigue siendo válido con `adicionado` en NULL:
 *     dice «minuto 90», que es exactamente lo que se sabía de esa fila.
 *
 * El período no se guarda: se deduce del minuto del reloj (ver MinutoHelper).
 * 45 y menos es primer tiempo, hasta 90 es segundo, hasta 105 es el primer
 * suplementario y hasta 120 el segundo. Guardarlo además sería un dato que
 * puede quedar en contra del minuto la primera vez que alguien lo edita a mano.
 *
 * `unsignedTinyInteger` alcanza y sobra: el descuento más largo que registra
 * Transfermarkt no llega a 30. NULL y 0 significan lo mismo —sin descuento—,
 * y todo el sistema los trata igual.
 */
class AddAdicionadoAIncidencias extends Migration
{
    /** Las cuatro tablas de incidencias. Todas tienen `minuto` nullable. */
    private $tablas = ['gols', 'tarjetas', 'cambios', 'penals'];

    public function up()
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasColumn($tabla, 'adicionado')) continue;
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedTinyInteger('adicionado')->nullable()->after('minuto');
            });
        }

        // La marca del repaso de minutos. Va aparte de `tipos_gol_revisado_at`
        // a propósito: los partidos que ya figuran revisados de tipo lo fueron
        // ANTES de que el descuento existiera, así que su marca no dice nada
        // sobre los minutos. Compartir la columna dejaría a esos partidos como
        // «ya revisados» sin haberles mirado nunca el descuento — el error de
        // siempre, que un arreglo nuevo no repara lo viejo.
        if (!Schema::hasColumn('import_partidos', 'minutos_revisado_at')) {
            Schema::table('import_partidos', function (Blueprint $table) {
                $table->dateTime('minutos_revisado_at')->nullable();
            });
        }
    }

    public function down()
    {
        foreach ($this->tablas as $tabla) {
            if (!Schema::hasColumn($tabla, 'adicionado')) continue;
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('adicionado');
            });
        }

        if (Schema::hasColumn('import_partidos', 'minutos_revisado_at')) {
            Schema::table('import_partidos', function (Blueprint $table) {
                $table->dropColumn('minutos_revisado_at');
            });
        }
    }
}
