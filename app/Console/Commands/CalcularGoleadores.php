<?php

namespace App\Console\Commands;

use App\Services\GoleadoresTorneo;
use Illuminate\Console\Command;

/**
 * El goleador de cada torneo completo, en `torneo_goleadores` (la regla está en
 * App\Services\GoleadoresTorneo). Va en el crontab de 'deploy', antes del
 * sitemap:
 *
 *   20 4 * * * /usr/bin/php /var/www/lv-torneo/artisan torneos:goleadores >> /dev/null 2>&1
 *
 * Además se recalcula solo el torneo que se finaliza (TorneoController@guardarFinalizar).
 */
class CalcularGoleadores extends Command
{
    protected $signature = 'torneos:goleadores {--torneo= : sólo ese torneo}';

    protected $description = 'Calcula el goleador de cada torneo completo (torneo_goleadores)';

    public function handle(GoleadoresTorneo $servicio)
    {
        $torneo = $this->option('torneo') ? (int) $this->option('torneo') : null;
        $inicio = microtime(true);

        $r = $servicio->calcular($torneo);

        $this->info(sprintf(
            'Torneos completos: %d · con goleador: %d · sin (faltan goles o empate con goles faltantes): %d · filas: %d · %.1f s',
            $r['completos'], $r['con_goleador'], $r['sin_goleador'], $r['filas'], microtime(true) - $inicio
        ));

        return 0;
    }
}
