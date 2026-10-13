<?php

namespace App\Console\Commands;

use App\Equipo;
use App\Services\Traductor;
use Illuminate\Console\Command;

/**
 * Completa la historia de los clubes que la tienen en un solo idioma:
 * traduce la que está cargada (castellano -> inglés o al revés) con
 * App\Services\Traductor. Al guardar desde el formulario ya se hace sola;
 * esto es para los clubes cargados antes.
 *
 *   php artisan equipos:traducir-historias --probar      (cuántos y cuáles)
 *   php artisan equipos:traducir-historias --limite=20   (de a poco)
 *
 * Va despacio a propósito (--pausa, 2 s por defecto): sin DEEPL_API_KEY usa
 * el endpoint público de Google, que corta si se le pide mucho seguido.
 */
class TraducirHistorias extends Command
{
    protected $signature = 'equipos:traducir-historias
        {--limite=50 : cuántos clubes traducir como máximo}
        {--pausa=2 : segundos entre un club y otro}
        {--probar : lista los clubes sin traducir nada}';

    protected $description = 'Traduce la historia de los clubes que la tienen en un solo idioma';

    public function handle()
    {
        $vacio = fn ($q, $c) => $q->where(fn ($w) => $w->whereNull($c)->orWhereRaw("TRIM({$c}) = ''"));
        $lleno = fn ($q, $c) => $q->whereNotNull($c)->whereRaw("TRIM({$c}) <> ''");

        $pendientes = Equipo::query()
            ->select('id', 'nombre', 'historia', 'historia_en')
            ->where(function ($q) use ($vacio, $lleno) {
                $q->where(fn ($a) => $lleno($vacio($a, 'historia_en'), 'historia'))
                  ->orWhere(fn ($b) => $lleno($vacio($b, 'historia'), 'historia_en'));
            })
            ->orderBy('id');

        $total = (clone $pendientes)->count();
        $this->info("Clubes con historia en un solo idioma: {$total}. Motor: " . Traductor::motor() . '.');

        $limite = max(1, (int) $this->option('limite'));
        $pausa = max(0, (int) $this->option('pausa'));
        $ok = $fallas = 0;

        foreach ($pendientes->limit($limite)->get() as $i => $equipo) {
            if ($this->option('probar')) {
                $falta = trim((string) $equipo->historia_en) === '' ? 'inglés' : 'castellano';
                $this->line("#{$equipo->id} {$equipo->nombre}: falta {$falta}");
                continue;
            }

            if ($i > 0 && $pausa) {
                sleep($pausa);
            }

            $datos = $equipo->only(['historia', 'historia_en']);
            $aviso = Traductor::completarPar($datos, 'historia', 'historia_en');

            if (Traductor::ultimoError()) {
                $fallas++;
                $this->error("#{$equipo->id} {$equipo->nombre}: {$aviso}");
                // Tres seguidas suele ser que Google cortó: no tiene sentido seguir.
                if ($fallas >= 3 && $ok === 0) {
                    $this->error('Tres fallas sin ningún acierto: corto acá.');
                    return self::FAILURE;
                }
                continue;
            }

            $equipo->update($datos);
            $ok++;
            $this->line("<info>#{$equipo->id} {$equipo->nombre}</info>: {$aviso}");
        }

        if (!$this->option('probar')) {
            $this->info("Traducidos: {$ok}. Fallas: {$fallas}.");
            if ($ok) {
                $this->comment('Vaciar la caché de páginas para que se vea: rm -rf storage/framework/cache/paginas/*');
            }
        }
        return self::SUCCESS;
    }
}
