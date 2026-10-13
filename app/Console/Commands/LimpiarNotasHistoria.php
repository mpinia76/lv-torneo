<?php

namespace App\Console\Commands;

use App\Equipo;
use App\Services\NotasWiki;
use Illuminate\Console\Command;

/**
 * Barre la historia de los clubes (historia e historia_en) y saca las notas
 * de Wikipedia que quedaron al copiar: [1], [12]:34, [a], [cita requerida]...
 * La regla de qué se saca está en App\Services\NotasWiki.
 *
 * Al guardar desde el formulario ya se limpia sola; esto es para lo que entra
 * por otro lado (el .sql de las traducciones, SQL a mano en la consola) y para
 * lo cargado antes. Va en el crontab de 'deploy', una vez por semana:
 *
 *   30 5 * * 1 /usr/bin/php /var/www/lv-torneo/artisan equipos:limpiar-notas >> /dev/null 2>&1
 *
 * Con --probar no escribe nada: muestra club por club el antes y el después.
 */
class LimpiarNotasHistoria extends Command
{
    protected $signature = 'equipos:limpiar-notas {--probar : muestra qué cambiaría sin guardar}';

    protected $description = 'Saca las notas de Wikipedia ([1], [cita requerida]...) de la historia de los clubes';

    public function handle()
    {
        $probar = (bool) $this->option('probar');
        $campos = ['historia', 'historia_en'];
        $tocados = 0;

        Equipo::query()
            ->select(array_merge(['id', 'nombre'], $campos))
            ->where(function ($q) use ($campos) {
                foreach ($campos as $c) {
                    $q->orWhere($c, 'like', '%[%');
                }
            })
            ->orderBy('id')
            ->chunkById(200, function ($equipos) use ($campos, $probar, &$tocados) {
                foreach ($equipos as $equipo) {
                    $cambios = [];
                    foreach ($campos as $c) {
                        $limpio = NotasWiki::limpiar($equipo->$c);
                        if ($limpio !== $equipo->$c) {
                            $cambios[$c] = $limpio;
                        }
                    }
                    if (!$cambios) {
                        continue;
                    }
                    $tocados++;
                    $this->line("<info>#{$equipo->id} {$equipo->nombre}</info>: " . implode(', ', array_keys($cambios)));

                    if ($probar) {
                        foreach ($cambios as $c => $limpio) {
                            preg_match_all(NotasWiki::patron(), $equipo->$c, $m);
                            $this->line("  {$c}, saca: " . implode(' ', array_map('trim', $m[0])));
                            $this->line("  queda: " . $this->recorte($limpio));
                        }
                        continue;
                    }
                    // Por query y no por el modelo: mismo criterio que crearEquipo().
                    Equipo::where('id', $equipo->id)->update($cambios);
                }
            });

        $this->info(($probar ? 'Cambiarían ' : 'Limpiados ') . $tocados . ' club(es).');
        return 0;
    }

    /** Un renglón para ver la diferencia sin volcar la historia entera. */
    protected function recorte($texto)
    {
        $texto = preg_replace('/\s+/u', ' ', (string) $texto);
        return mb_strlen($texto) > 160 ? mb_substr($texto, 0, 160) . '…' : $texto;
    }
}
