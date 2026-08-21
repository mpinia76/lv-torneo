<?php

namespace App\Console\Commands;

use App\Services\DuplicadosPersonas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recalcula los candidatos a persona repetida fuera del navegador.
 *
 * La primera pasada sobre toda la base conviene correrla desde acá: no depende
 * del max_execution_time del hosting ni de que el navegador aguante abierto.
 *
 *   php artisan personas:duplicados
 *   php artisan personas:duplicados --umbral=60
 *   php artisan personas:duplicados --sin-reindexar
 */
class DetectarPersonasDuplicadas extends Command
{
    protected $signature = 'personas:duplicados
                            {--umbral= : Puntaje mínimo 1-100 para guardar un par (por defecto 70)}
                            {--sin-reindexar : No recalcular claves ni tokens, solo los pares}';

    protected $description = 'Detecta personas repetidas y llena la tabla persona_duplicados';

    public function handle()
    {
        set_time_limit(0);

        $umbral = (int) ($this->option('umbral') ?: DuplicadosPersonas::UMBRAL);
        $inicio = microtime(true);

        if (!$this->option('sin-reindexar')) {
            $this->info('Reconstruyendo claves normalizadas y tokens...');
            $barra = null;

            $total = DuplicadosPersonas::reconstruirIndice(function ($hechas, $total) use (&$barra) {
                if ($barra === null) {
                    $barra = $this->output->createProgressBar($total);
                    $barra->start();
                }
                $barra->setProgress(min($hechas, $total));
            });

            if ($barra) {
                $barra->finish();
                $this->line('');
            }
            $this->info("Indexadas {$total} personas.");
        }

        $this->info("Buscando pares candidatos (umbral {$umbral})...");

        $r = DuplicadosPersonas::recalcular($umbral, function ($hechos, $total, $pares) {
            $this->line("  apellidos revisados: {$hechos}/{$total} — pares hasta ahora: {$pares}");
        });

        Cache::forget('personas.nacionalidades_sin_bandera');

        $segundos = round(microtime(true) - $inicio, 1);

        $this->line('');
        $this->info("Listo en {$segundos}s");
        $this->table(
            ['Personas', 'Pares candidatos', 'Guardados', 'Pendientes dados de baja'],
            [[$r['personas'], $r['pares'], $r['guardados'], $r['borrados']]]
        );

        if (!empty($r['saltados'])) {
            $this->warn('Apellidos demasiado comunes (más de ' . DuplicadosPersonas::MAX_POR_TOKEN
                . ' personas) que no se compararon entre sí:');
            $this->line('  ' . implode(', ', $r['saltados']));
            $this->line('  Si necesitás cubrirlos, subí DuplicadosPersonas::MAX_POR_TOKEN.');
        }

        return 0;
    }
}
