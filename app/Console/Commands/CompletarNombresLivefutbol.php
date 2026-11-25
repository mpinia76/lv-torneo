<?php

namespace App\Console\Commands;

use App\Services\NombreHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Completa el nombre de pila de las personas que el importador de LiveFutbol
 * creó sólo con el apodo (25/11/2026).
 *
 * Cuando se crearon, el importador no leía bien la ficha de LF (la etiqueta del
 * nombre completo, «Cumpleaños» en vez de «Fecha de nacimiento», etc.) y quedó
 * «Juanma, Juanma». Ya se les vació el nombre; esto intenta completarlo con la
 * ficha de LF que quedó en caché (30 días) cuando se importó el partido.
 *
 * Sólo toca personas con el nombre vacío atadas a LF. Si con el nombre
 * completo ya existe otra persona (mismo nombre + apellido + fecha), NO la
 * completa: la lista como duplicado para fusionar.
 *
 * Sin --aplicar sólo muestra lo que haría.
 *
 *   php artisan lf:completar-nombres
 *   php artisan lf:completar-nombres --aplicar
 */
class CompletarNombresLivefutbol extends Command
{
    protected $signature = 'lf:completar-nombres {--aplicar : Graba los nombres}';

    protected $description = 'Completa con la ficha de LiveFutbol el nombre de las personas creadas sólo con el apodo';

    public function handle()
    {
        $aplicar = (bool) $this->option('aplicar');

        $filas = DB::table('livefutbol_personas as lp')
            ->join('jugadors as j', function ($q) {
                $q->on('j.id', '=', 'lp.ref_id')->where('lp.rol', '=', 'jugador');
            })
            ->join('personas as p', 'p.id', '=', 'j.persona_id')
            ->where(function ($q) { $q->whereNull('p.nombre')->orWhere('p.nombre', ''); })
            ->select('p.id', 'p.name', 'p.apellido', 'p.nacimiento', 'lp.lf_id')
            ->union(
                DB::table('livefutbol_personas as lp')
                    ->join('tecnicos as t', function ($q) {
                        $q->on('t.id', '=', 'lp.ref_id')->where('lp.rol', '=', 'tecnico');
                    })
                    ->join('personas as p', 'p.id', '=', 't.persona_id')
                    ->where(function ($q) { $q->whereNull('p.nombre')->orWhere('p.nombre', ''); })
                    ->select('p.id', 'p.name', 'p.apellido', 'p.nacimiento', 'lp.lf_id')
            )
            ->get();

        $vistos = [];
        $completa = 0; $dup = 0; $sinCache = 0; $sinDato = 0;
        $listaSinCache = []; $listaSinDato = [];

        foreach ($filas as $f) {
            if (isset($vistos[$f->id])) continue;
            $vistos[$f->id] = true;
            $fecha = substr((string) $f->nacimiento, 0, 10);

            $d = Cache::get('lf_persona_' . (int) $f->lf_id);
            if (!is_array($d)) {
                $sinCache++;
                $listaSinCache[] = "#{$f->id} {$f->name} (lf {$f->lf_id})";
                continue;
            }

            $n = null;
            $nom = trim((string) ($d['nombre'] ?? ''));
            $ape = trim((string) ($d['apellido'] ?? ''));
            if ($nom !== '' && $ape !== '' && mb_strtolower($nom) !== mb_strtolower($ape)) {
                $n = ['nombre' => $nom, 'apellido' => $ape];
            } elseif (!empty($d['completo'])
                && count(preg_split('/\s+/u', trim($d['completo']), -1, PREG_SPLIT_NO_EMPTY)) >= 2) {
                $n = NombreHelper::separarTM(['name' => trim($d['completo'])]);
            }

            if (!$n || trim((string) $n['nombre']) === '' || trim((string) $n['apellido']) === '') {
                $sinDato++;
                $listaSinDato[] = "#{$f->id} {$f->name}";
                continue;
            }
            $n['nombre'] = trim($n['nombre']);
            $n['apellido'] = trim($n['apellido']);

            $otra = DB::table('personas')->where('id', '<>', $f->id)
                ->where('nombre', $n['nombre'])->where('apellido', $n['apellido'])
                ->whereDate('nacimiento', $fecha)->value('id');
            if ($otra) {
                $this->warn("DUPLICADO #{$f->id} «{$f->name}» ({$fecha}) = #{$otra} «{$n['apellido']}, {$n['nombre']}»: no la toco, hay que fusionarla.");
                $dup++;
                continue;
            }

            $this->line(($aplicar ? '' : '[prueba] ') . "#{$f->id} «{$f->name}» ({$fecha}) → «{$n['apellido']}, {$n['nombre']}»");
            if ($aplicar) {
                // `name` (el que se muestra) queda con el apodo.
                DB::table('personas')->where('id', $f->id)->update([
                    'nombre' => $n['nombre'], 'apellido' => $n['apellido'], 'updated_at' => now(),
                ]);
            }
            $completa++;
        }

        $this->line('');
        $this->line(($aplicar ? 'Completadas' : 'Se completarían') . ": {$completa}");
        if ($dup) $this->line("Duplicadas (para fusionar): {$dup}");
        if ($sinDato) {
            $this->line("Con ficha en caché pero sin nombre completo: {$sinDato}");
            foreach ($listaSinDato as $l) $this->line('   ' . $l);
        }
        if ($sinCache) {
            $this->line("Sin ficha en caché (hay que volver a leerla de LF): {$sinCache}");
            foreach ($listaSinCache as $l) $this->line('   ' . $l);
        }
        if (!$aplicar && $completa) $this->line('Para hacerlo de verdad: php artisan lf:completar-nombres --aplicar');
        return 0;
    }
}
