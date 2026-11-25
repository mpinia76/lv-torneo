<?php

namespace App\Console\Commands;

use App\Services\TmDetallePartido;
use App\Services\TmFechas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Personas con el nombre igual al apellido («Rubio, Rubio», «Míchel, Míchel»)
 * — sobre todo DTs que el importador de TM creó cuando su ficha traía una sola
 * palabra (25/11/2026). Vuelve a pedir el perfil a tmapi y, si ahora trae el
 * nombre completo (passportName / displayName), lo separa con las reglas del
 * alta. El `name` que se muestra queda como está (el apodo).
 *
 * Si con el nombre completo ya existe OTRA persona nacida el mismo día, no la
 * toca: la lista como DUPLICADO para fusionar.
 *
 * El id de TM sale de: jugador → `jugador_tm` o `jugadors.transfermarkt_url`;
 * DT → `tecnicos.transfermarkt_url`; árbitro → `arbitro_tm`.
 *
 * Sin --aplicar sólo muestra lo que haría (pero sí consulta tmapi).
 *
 *   php artisan personas:nombres-mononimos
 *   php artisan personas:nombres-mononimos --aplicar
 */
class NombresMononimosTm extends Command
{
    protected $signature = 'personas:nombres-mononimos {--aplicar : Graba los nombres}';

    protected $description = 'Completa desde Transfermarkt el nombre de las personas con nombre = apellido';

    public function handle()
    {
        $aplicar = (bool) $this->option('aplicar');
        $tm = new TmDetallePartido();

        $personas = DB::table('personas')
            ->whereColumn('nombre', 'apellido')->where('nombre', '<>', '')
            ->orderBy('id')->get();

        // persona → [tipo, tm_id]
        $porTipo = ['jugador' => [], 'tecnico' => [], 'arbitro' => []];
        $sinTm = [];
        foreach ($personas as $p) {
            $tmId = null; $tipo = null;

            $j = DB::table('jugadors')->where('persona_id', $p->id)->first();
            if ($j) {
                $tmId = DB::table('jugador_tm')->where('jugador_id', $j->id)->value('tm_player_id');
                if (!$tmId && !empty($j->transfermarkt_url) && preg_match('#/spieler/(\d+)#', $j->transfermarkt_url, $m)) $tmId = $m[1];
                if ($tmId) $tipo = 'jugador';
            }
            if (!$tmId) {
                $t = DB::table('tecnicos')->where('persona_id', $p->id)->first();
                if ($t && !empty($t->transfermarkt_url) && preg_match('#/trainer/(\d+)#', $t->transfermarkt_url, $m)) {
                    $tmId = $m[1]; $tipo = 'tecnico';
                }
            }
            if (!$tmId && DB::getSchemaBuilder()->hasTable('arbitro_tm')) {
                $a = DB::table('arbitros')->where('persona_id', $p->id)->value('id');
                if ($a) {
                    $tmId = DB::table('arbitro_tm')->where('arbitro_id', $a)->value('tm_referee_id');
                    if ($tmId) $tipo = 'arbitro';
                }
            }

            if (!$tmId) { $sinTm[] = "#{$p->id} {$p->name}"; continue; }
            $porTipo[$tipo][(string) $tmId] = $p;
        }

        $ok = 0; $dup = 0; $igual = [];
        foreach ($porTipo as $tipo => $mapa) {
            foreach (array_chunk(array_keys($mapa), 20) as $ids) {
                $r = ['llamadas' => 0];
                $perfiles = TmFechas::traerPerfiles($tipo, $ids, $r);
                foreach ($ids as $tmId) {
                    $p = $mapa[$tmId];
                    $etq = "#{$p->id} «{$p->name}» (" . substr((string) $p->nacimiento, 0, 10) . ", TM {$tipo} {$tmId})";
                    $perfil = $perfiles[$tmId] ?? null;
                    if (!$perfil) { $igual[] = "{$etq}: tmapi no devolvió el perfil"; continue; }

                    $n = $tm->nombreDesdePerfil($perfil, $p->nacionalidad);
                    $nom = trim((string) ($n['nombre'] ?? ''));
                    $ape = trim((string) ($n['apellido'] ?? ''));
                    if ($nom === '' || $ape === '' || mb_strtolower($nom) === mb_strtolower($ape)) {
                        $igual[] = "{$etq}: TM tampoco tiene el nombre completo";
                        continue;
                    }

                    $otra = DB::table('personas')->where('id', '<>', $p->id)
                        ->where('nombre', $nom)->where('apellido', $ape)
                        ->whereDate('nacimiento', substr((string) $p->nacimiento, 0, 10))->first();
                    if ($otra) {
                        $this->warn("DUPLICADO {$etq} = #{$otra->id} «{$ape}, {$nom}»: no la toco, hay que fusionarla.");
                        $dup++;
                        continue;
                    }

                    $this->line(($aplicar ? '' : '[prueba] ') . "{$etq} → «{$ape}, {$nom}»");
                    if ($aplicar) {
                        DB::table('personas')->where('id', $p->id)
                            ->update(['nombre' => $nom, 'apellido' => $ape, 'updated_at' => now()]);
                    }
                    $ok++;
                }
            }
        }

        $this->line('');
        $this->line(($aplicar ? 'Completadas' : 'Se completarían') . ": {$ok}" . ($dup ? " · duplicadas: {$dup}" : ''));
        if ($igual) {
            $this->line('Sin nombre completo en TM:');
            foreach ($igual as $l) $this->line('   ' . $l);
        }
        if ($sinTm) {
            $this->line('Sin id de TM (a mano):');
            foreach ($sinTm as $l) $this->line('   ' . $l);
        }
        if (!$aplicar && $ok) $this->line('Para hacerlo de verdad: php artisan personas:nombres-mononimos --aplicar');
        return 0;
    }
}
