<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Infraestructura para detectar personas repetidas SIN escanear la tabla entera
 * en cada carga de pantalla.
 *
 * La pantalla vieja hacía, por cada una de las 1000 personas de la pagina, un
 * SELECT con LOWER(CONCAT(nombre,' ',apellido)) LIKE '%...%'. Eso es un escaneo
 * completo de `personas` por fila (un LIKE con comodin al principio, y encima
 * sobre una expresion, no puede usar indice) mas un NOT EXISTS correlacionado.
 *
 * Acá se invierte el problema: las claves normalizadas y el indice invertido de
 * tokens se calculan UNA vez, y la pantalla solo lee `persona_duplicados`.
 */
class CreatePersonaDuplicadosTable extends Migration
{
    public function up()
    {
        // 1) Claves normalizadas sobre personas. Acá está la velocidad: son
        //    columnas indexadas, comparables con `=` en vez de LIKE '%..%'.
        Schema::table('personas', function (Blueprint $table) {
            if (!Schema::hasColumn('personas', 'clave_norm')) {
                // "juan carlos perez" (nombre + apellido, sin acentos ni simbolos)
                $table->string('clave_norm', 191)->nullable()->index('personas_clave_norm_idx');
            }
            if (!Schema::hasColumn('personas', 'clave_orden')) {
                // "carlos juan perez" (los mismos tokens, ordenados alfabeticamente).
                // Al ordenar, "Juan Perez" y "Perez Juan" quedan idénticos: el caso
                // de nombre y apellido invertidos sale gratis, sin doble consulta.
                $table->string('clave_orden', 191)->nullable()->index('personas_clave_orden_idx');
            }
        });

        // 2) Indice invertido de tokens. Es el "bloqueo" (blocking) que evita
        //    comparar todos contra todos: solo se comparan las personas que
        //    comparten al menos una palabra de apellido.
        if (!Schema::hasTable('persona_tokens')) {
            Schema::create('persona_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('persona_id');
                $table->string('token', 64);
                $table->char('campo', 1)->default('a'); // a = apellido, n = nombre
                $table->primary(['persona_id', 'token', 'campo'], 'persona_tokens_pk');
                $table->index(['token', 'campo'], 'persona_tokens_token_idx');
            });
        }

        // 3) Los pares candidatos ya resueltos. La pantalla lee de acá y nada mas.
        //    persona_id guarda SIEMPRE el id menor y simil_id el mayor, asi el par
        //    (A,B) y el (B,A) son la misma fila y no se puede duplicar la info.
        if (!Schema::hasTable('persona_duplicados')) {
            Schema::create('persona_duplicados', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('persona_id');
                $table->unsignedBigInteger('simil_id');
                $table->unsignedTinyInteger('puntaje')->default(0);
                $table->string('motivo', 150)->nullable();
                // pendiente | descartado | fusionado
                $table->string('estado', 20)->default('pendiente');
                $table->timestamps();

                $table->unique(['persona_id', 'simil_id'], 'persona_duplicados_par_uk');
                $table->index(['estado', 'puntaje'], 'persona_duplicados_estado_idx');
                $table->index('simil_id', 'persona_duplicados_simil_idx');
            });
        }

        // 4) Bitacora de fusiones: si una fusion sale mal, al menos queda el rastro
        //    de qué se absorbió y con qué datos.
        if (!Schema::hasTable('persona_fusiones')) {
            Schema::create('persona_fusiones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('persona_id');   // la que quedó
                $table->unsignedBigInteger('absorbida_id'); // la que se borró
                $table->string('absorbida_nombre', 191)->nullable();
                $table->text('detalle')->nullable();        // JSON con lo que se movió
                $table->timestamps();
                $table->index('persona_id', 'persona_fusiones_persona_idx');
            });
        }

        // 5) Las decisiones ya tomadas en la pantalla vieja no se pierden:
        //    personas_verificadas pasa a ser el estado "descartado".
        if (Schema::hasTable('personas_verificadas')) {
            DB::statement("
                INSERT IGNORE INTO persona_duplicados
                    (persona_id, simil_id, puntaje, motivo, estado, created_at, updated_at)
                SELECT LEAST(persona_id, simil_id),
                       GREATEST(persona_id, simil_id),
                       0,
                       'descartado en la pantalla anterior',
                       'descartado',
                       NOW(), NOW()
                FROM personas_verificadas
                WHERE persona_id IS NOT NULL
                  AND simil_id IS NOT NULL
                  AND persona_id <> simil_id
                GROUP BY LEAST(persona_id, simil_id), GREATEST(persona_id, simil_id)
            ");
        }

        // 6) La pestaña de banderas agrupa por nacionalidad: con indice es instantanea.
        if (Schema::hasColumn('personas', 'nacionalidad')) {
            try {
                Schema::table('personas', function (Blueprint $table) {
                    $table->index('nacionalidad', 'personas_nacionalidad_idx');
                });
            } catch (\Exception $e) {
                // el indice ya existía; no es motivo para abortar la migracion
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('persona_fusiones');
        Schema::dropIfExists('persona_duplicados');
        Schema::dropIfExists('persona_tokens');

        Schema::table('personas', function (Blueprint $table) {
            foreach (['clave_norm', 'clave_orden'] as $col) {
                if (Schema::hasColumn('personas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
