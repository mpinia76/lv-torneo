<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Persona;

class ActualizarNombrePersonas extends Command
{
    protected $signature = 'personas:actualizar-nombres';
    protected $description = 'Actualiza el campo name de cada persona con el full_name o el contenido de los paréntesis si existe';

    public function handle()
    {
        set_time_limit(0);
        $total = 0;

        \App\Persona::chunk(500, function ($personas) use (&$total) {
            foreach ($personas as $p) {
                // Buscar si hay algo entre paréntesis en apellido
                if (preg_match('/\((.*?)\)/', $p->apellido, $matches)) {
                    $p->name = trim($matches[1]); // Usar lo que está dentro de los paréntesis
                    // Quitar esa parte del apellido
                    $p->apellido = trim(preg_replace('/\s*\(.*?\)\s*/', '', $p->apellido));
                } else {
                    // Si no hay paréntesis, usar el accessor
                    $p->name = $p->nombre.' '.$p->apellido;
                }

                $p->save();
                $total++;
            }
        });

        $this->info("✅ Se actualizaron {$total} personas.");
    }
}
