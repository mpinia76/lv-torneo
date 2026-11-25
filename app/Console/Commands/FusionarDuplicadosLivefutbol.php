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
 * Segunda tanda (25/11/2026, tarde): apodos de una palabra («Manel», «Juanma»,
 * «Gurpegi») con la misma fecha Y un club en común con la ficha vieja. Los 61
 * de la primera tanda ya están fusionados; si se vuelve a correr, los saltea.
 * Quedaron AFUERA por nombre distinto: Abel/Georgiev, Sérvulo/Barkero,
 * Salvat/Leyenda, Armada/Vacas, Manu/Fernández Cotrofe; y Negral/Vega Díez
 * va a mano.
 *
 * Tercera tanda (25/11/2026, noche): misma fecha sin club en común, con el
 * apodo claramente sacado del nombre (Juanra = Juan Ramón Cabrero, Maxi =
 * Máximo Ovejero, Javi = Francisco Javier Álvarez Peláez).
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
        141266 => 85560,  141295 => 102771, 141307 => 102722, 140876 => 88405,  141302 => 102831,
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
