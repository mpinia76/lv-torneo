<?php

namespace App\Console\Commands;

use App\Services\FusionPersonas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fusiona las personas que el importador de LiveFutbol creó de más (25/11/2026).
 *
 * Con un apodo común («Pablo», «Tomás», «Dani García») la búsqueda por nombre
 * llegaba al tope de 300 candidatos antes de llegar a la ficha buena, y se
 * creaba un duplicado aunque la fecha de nacimiento coincidiera. Ya está
 * corregido en LivefutbolDetalle::porFecha(); esto limpia lo que quedó.
 *
 * Los pares salen de la consulta «persona creada por LF + otra persona nacida
 * el mismo día con esa palabra en el nombre», revisados a mano. Quedaron
 * AFUERA: los mellizos Guillaumot, Facundo Suárez / J. C. Hernández Suárez,
 * Juan Martínez / Wálter Martínez, Manolo Pérez / Thierry Pérez y el Ángel del
 * 15/01/1982 (dos candidatos en la base: va a mano).
 *
 * Gana SIEMPRE la ficha vieja (la que tiene la carrera). Usa la misma fusión
 * que Verificar personas, y además mueve el atado de `livefutbol_personas`,
 * que la fusión no conoce.
 *
 * Sin --aplicar sólo muestra lo que haría.
 *
 *   php artisan lf:fusionar-duplicados
 *   php artisan lf:fusionar-duplicados --aplicar
 */
class FusionarDuplicadosLivefutbol extends Command
{
    protected $signature = 'lf:fusionar-duplicados {--aplicar : Fusiona de verdad}';

    protected $description = 'Fusiona en la ficha vieja las personas duplicadas que creó el importador de LiveFutbol';

    /** persona nueva (LF) => persona vieja (la que queda). */
    private const PARES = [
        140838 => 139666, 140844 => 80503,  140851 => 134778, 140854 => 92790,  140872 => 95525,
        140881 => 88305,  140883 => 88301,  140888 => 102910, 140890 => 112351, 140892 => 103034,
        140902 => 80551,  140903 => 88020,  140905 => 88274,  140907 => 87973,  140908 => 92460,
        140909 => 88243,  140911 => 112337, 140913 => 88202,  140927 => 102776, 140933 => 110060,
        140934 => 110061, 140935 => 110179, 140940 => 102833, 140942 => 110142, 140951 => 110081,
        140963 => 102606, 140964 => 112329, 140966 => 101860, 140981 => 122589, 140982 => 80676,
        141001 => 89780,  141003 => 87972,  141008 => 102867, 141012 => 98223,  141014 => 80524,
        141015 => 85625,  141017 => 109782, 141031 => 103023, 141033 => 110011, 141062 => 88093,
        141076 => 111255, 141079 => 111259, 141081 => 102874, 141089 => 110117, 141094 => 102762,
        141117 => 104767, 141137 => 88041,  141138 => 83785,  141143 => 109930, 141149 => 81106,
        141151 => 92659,  141152 => 102948, 141168 => 88765,  141286 => 103039, 141292 => 110044,
        141297 => 102779, 141301 => 102819, 141303 => 102824, 141312 => 92673,  141327 => 88408,
        141358 => 95219,
    ];

    public function handle()
    {
        $aplicar = (bool) $this->option('aplicar');
        $ok = 0; $mal = 0;

        foreach (self::PARES as $nueva => $vieja) {
            $pn = DB::table('personas')->where('id', $nueva)->first();
            $pv = DB::table('personas')->where('id', $vieja)->first();
            if (!$pn || !$pv) {
                $this->warn("#{$nueva} → #{$vieja}: alguna ya no existe (¿ya fusionada?). La salteo.");
                continue;
            }
            $etq = sprintf('#%d «%s» (%s) → #%d «%s, %s»', $nueva, $pn->name, substr((string) $pn->nacimiento, 0, 10),
                $vieja, $pv->apellido, $pv->nombre);

            if (substr((string) $pn->nacimiento, 0, 10) !== substr((string) $pv->nacimiento, 0, 10)) {
                $this->error("{$etq}: las fechas ya no coinciden. La salteo.");
                $mal++;
                continue;
            }

            // Fichas de rol, para mover después el atado de livefutbol.
            $roles = [];
            foreach (['jugador' => 'jugadors', 'tecnico' => 'tecnicos', 'arbitro' => 'arbitros'] as $rol => $tabla) {
                $roles[$rol] = [
                    'nueva' => DB::table($tabla)->where('persona_id', $nueva)->value('id'),
                    'vieja' => DB::table($tabla)->where('persona_id', $vieja)->value('id'),
                ];
            }

            if (!$aplicar) {
                $this->line("[prueba] {$etq}");
                $ok++;
                continue;
            }

            $r = FusionPersonas::fusionar($vieja, $nueva);
            if (empty($r['ok'])) {
                $this->error("{$etq}: " . ($r['mensaje'] ?? 'no se pudo'));
                $mal++;
                continue;
            }

            // La fusión deja una sola ficha por rol (la de la vieja, o la de la
            // nueva si la vieja no tenía ese rol). El atado de livefutbol tiene
            // que apuntar a la que quedó.
            foreach ($roles as $rol => $ids) {
                if (!$ids['nueva']) continue;
                $tabla = ['jugador' => 'jugadors', 'tecnico' => 'tecnicos', 'arbitro' => 'arbitros'][$rol];
                $queda = DB::table($tabla)->where('persona_id', $vieja)->value('id');
                if ($queda && (int) $queda !== (int) $ids['nueva']) {
                    DB::table('livefutbol_personas')->where('rol', $rol)->where('ref_id', $ids['nueva'])
                        ->update(['ref_id' => $queda, 'origen' => 'elegido', 'revisar' => 0, 'updated_at' => now()]);
                }
            }

            $this->info("{$etq}: fusionada.");
            $ok++;
        }

        $this->line('');
        $this->line(($aplicar ? 'Fusionadas' : 'Se fusionarían') . ": {$ok}" . ($mal ? " · con problemas: {$mal}" : ''));
        if (!$aplicar) $this->line('Para hacerlo de verdad: php artisan lf:fusionar-duplicados --aplicar');
        return 0;
    }
}
