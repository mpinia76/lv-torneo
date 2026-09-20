<?php

namespace App\Console\Commands;

use App\Services\DuplicadosPersonas;
use Illuminate\Console\Command;

/**
 * Dry-run de la regla de "contención cruzada" del bloque D.
 *
 * Lista los pares que esa regla AGREGA (una ficha contenida en la otra, misma
 * fecha de nacimiento exacta, ningún apellido en común) sin escribir una sola
 * fila. Es para medir el ruido antes de reindexar y recalcular de verdad.
 *
 *   php artisan personas:contencion
 *   php artisan personas:contencion --todos      (incluye los que no llegan al umbral)
 *   php artisan personas:contencion --umbral=60
 *   php artisan personas:contencion --csv=storage/contencion.csv
 */
class SimularContencionPersonas extends Command
{
    protected $signature = 'personas:contencion
                            {--umbral= : Puntaje mínimo 1-100 (por defecto 70)}
                            {--todos : Mostrar también los pares que no llegan al umbral}
                            {--csv= : Guardar el listado completo en un archivo CSV}';

    protected $description = 'Simula (sin escribir) los pares nuevos que aporta la contención cruzada nombre/apellido';

    public function handle()
    {
        set_time_limit(0);

        $umbral = (int) ($this->option('umbral') ?: DuplicadosPersonas::UMBRAL);
        $inicio = microtime(true);

        $this->info("Simulando (umbral {$umbral}). No se escribe nada en la base.");

        $r     = DuplicadosPersonas::simularContencion($umbral);
        $pares = $r['pares'];

        if (!$pares) {
            $this->warn('La regla nueva no agrega ningún par.');
            return 0;
        }

        $filas = [];
        foreach ($pares as $par) {
            if (!$this->option('todos') && $par['puntaje'] < $umbral) {
                continue;
            }

            $tm = 'sin TM';
            if ($par['tm_a'] && $par['tm_b']) {
                $tm = $par['tm_a'] === $par['tm_b'] ? 'misma ficha TM' : 'TM distintas';
            } elseif ($par['tm_a'] || $par['tm_b']) {
                $tm = 'solo una tiene TM';
            }

            $filas[] = [
                $par['puntaje'],
                $par['a'] . ' ' . $par['nombre_a'] . ' (' . $par['roles_a'] . ')',
                $par['b'] . ' ' . $par['nombre_b'] . ' (' . $par['roles_b'] . ')',
                $par['fecha'],
                $tm,
                $par['estado_actual'] ?: '-',
            ];
        }

        if ($filas) {
            $this->table(
                ['Pts', 'Ficha A', 'Ficha B', 'Nacimiento', 'Transfermarkt', 'Estado actual'],
                $filas
            );
        } else {
            $this->warn('Ningún par llega al umbral. Probá con --todos para ver los que quedan abajo.');
        }

        if ($destino = $this->option('csv')) {
            $fh = fopen($destino, 'w');
            fputcsv($fh, ['puntaje', 'id_a', 'nombre_a', 'roles_a', 'tm_a', 'id_b', 'nombre_b', 'roles_b', 'tm_b', 'nacimiento', 'estado_actual', 'motivo']);
            foreach ($pares as $par) {
                fputcsv($fh, [
                    $par['puntaje'],
                    $par['a'], $par['nombre_a'], $par['roles_a'], $par['tm_a'],
                    $par['b'], $par['nombre_b'], $par['roles_b'], $par['tm_b'],
                    $par['fecha'], $par['estado_actual'], $par['motivo'],
                ]);
            }
            fclose($fh);
            $this->info("CSV escrito en {$destino}");
        }

        $segundos = round(microtime(true) - $inicio, 1);
        $this->line('');
        $this->info('Pares que agrega la regla: ' . count($pares)
            . ' — de esos, ' . $r['sobre_umbral'] . " llegan al umbral {$umbral}. ({$segundos}s)");
        $this->line('Nada de esto se guardó. Para aplicarlo: php artisan personas:duplicados');

        return 0;
    }
}
