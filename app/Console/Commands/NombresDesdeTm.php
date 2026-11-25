<?php

namespace App\Console\Commands;

use App\Services\FusionPersonas;
use App\Services\HttpHelper;
use App\Services\NombreHelper;
use App\Services\TmFechas;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Nombre real (o fusión) para los jugadores que el importador de LiveFutbol
 * creó sólo con el apodo — «Juanma», «Chechu», «Kaiku» (25/11/2026).
 *
 * LF no da el nombre completo. Transfermarkt sí, y sin buscar por nombre:
 *
 *   1. Del jugador se toman sus partidos → club + temporada.
 *   2. Se lee el PLANTEL de ese club en esa temporada en TM (`/kader/…/plus/1`,
 *      que trae la fecha de nacimiento). Una página = un crédito de ScraperAPI,
 *      y queda en caché 30 días.
 *   3. El jugador del plantel nacido EL MISMO DÍA es él (si hay dos, se
 *      saltea: ambiguo).
 *   4. Con ese id de TM:
 *      - si ya está atado en `jugador_tm` a otra ficha → es un DUPLICADO:
 *        se fusiona en esa ficha;
 *      - si no, se pide el perfil a tmapi y se separa el nombre igual que en
 *        el importador de TM. Si con ese nombre + apellido + fecha ya hay otra
 *        persona → DUPLICADO, se fusiona; si no, se completan nombre y
 *        apellido (el `name` que se muestra queda con el apodo) y se ata el id
 *        de TM.
 *
 * Gana siempre la ficha vieja. Sin --aplicar sólo muestra lo que haría (pero
 * SÍ lee TM, así que gasta los créditos una vez; al aplicar usa la caché).
 *
 *   php artisan lf:nombres-desde-tm
 *   php artisan lf:nombres-desde-tm --persona=140852     (probar con una)
 *   php artisan lf:nombres-desde-tm --aplicar
 */
class NombresDesdeTm extends Command
{
    protected $signature = 'lf:nombres-desde-tm {--aplicar : Graba y fusiona} {--persona= : Sólo esta persona (id)} {--ver : Muestra los planteles leídos y guarda el HTML en storage/app}';

    protected $description = 'Busca en los planteles de Transfermarkt el nombre real de los jugadores que LF creó sólo con el apodo';

    private $meses = [
        'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
        'jul' => 7, 'ago' => 8, 'sep' => 9, 'sept' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        'jan' => 1, 'apr' => 4, 'aug' => 8, 'dec' => 12,
    ];

    private $creditos = 0;

    public function handle()
    {
        $aplicar = (bool) $this->option('aplicar');

        $q = DB::table('livefutbol_personas as lp')
            ->join('jugadors as j', function ($q) {
                $q->on('j.id', '=', 'lp.ref_id')->where('lp.rol', '=', 'jugador');
            })
            ->join('personas as p', 'p.id', '=', 'j.persona_id')
            ->where(function ($q) { $q->whereNull('p.nombre')->orWhere('p.nombre', ''); })
            ->select('p.id as persona_id', 'j.id as jugador_id', 'p.name', 'p.apellido', 'p.nacimiento')
            ->distinct();
        if ($this->option('persona')) $q->where('p.id', (int) $this->option('persona'));
        $personas = $q->orderBy('p.id')->get();

        $cuenta = ['nombre' => 0, 'fusion' => 0, 'ambiguo' => 0, 'nada' => 0, 'sin_fecha' => 0];
        $sinResolver = [];

        foreach ($personas as $per) {
            $fecha = substr((string) $per->nacimiento, 0, 10);
            $etq = "#{$per->persona_id} «{$per->name}» ({$fecha})";
            if (!$fecha || $fecha < '1900-01-01') {
                $cuenta['sin_fecha']++;
                $sinResolver[] = "{$etq}: sin fecha de nacimiento";
                continue;
            }

            // Club + temporada de cada partido que jugó.
            $pares = DB::table('alineacions as a')
                ->join('partidos as pa', 'pa.id', '=', 'a.partido_id')
                ->join('equipo_tm as et', 'et.equipo_id', '=', 'a.equipo_id')
                ->where('a.jugador_id', $per->jugador_id)
                ->whereNotNull('pa.dia')
                ->select('et.tm_club_id', 'pa.dia')
                ->get();
            $buscar = [];
            foreach ($pares as $p) {
                $d = Carbon::parse($p->dia);
                $season = $d->month >= 7 ? $d->year : $d->year - 1;
                $buscar[$p->tm_club_id . '|' . $season] = [$p->tm_club_id, $season];
            }

            $hallados = [];
            foreach ($buscar as [$club, $season]) {
                $plantel = $this->plantel($club, $season);
                if ($this->option('ver')) {
                    $this->line("   plantel TM {$club} temporada {$season}: " . count($plantel) . ' jugadores');
                    foreach ($plantel as $id => $j) $this->line("      {$j['nac']}  {$j['nombre']} (TM {$id})");
                }
                foreach ($plantel as $tmId => $j) {
                    if ($j['nac'] === $fecha) $hallados[$tmId] = $j['nombre'];
                }
                if ($hallados) break;   // con un plantel alcanza
            }

            if (!$buscar) {
                $cuenta['nada']++;
                $sinResolver[] = "{$etq}: su club no está atado a TM";
                continue;
            }
            if (!$hallados) {
                $cuenta['nada']++;
                $sinResolver[] = "{$etq}: nadie nacido ese día en el plantel de TM";
                continue;
            }
            if (count($hallados) > 1) {
                $cuenta['ambiguo']++;
                $sinResolver[] = "{$etq}: dos en el plantel nacidos ese día (" . implode(', ', $hallados) . ')';
                continue;
            }

            $tmId = (string) array_key_first($hallados);
            $nombreTm = $hallados[$tmId];

            // ¿Ese id de TM ya es de otra ficha?
            $atado = DB::table('jugador_tm')->where('tm_player_id', $tmId)->value('jugador_id');
            $otraPersona = $atado ? DB::table('jugadors')->where('id', $atado)->value('persona_id') : null;
            if ($otraPersona && (int) $otraPersona !== (int) $per->persona_id) {
                $vieja = DB::table('personas')->where('id', $otraPersona)->first();
                $this->fusion($aplicar, $etq, $per->persona_id, $vieja, "TM {$tmId} «{$nombreTm}» ya atado");
                $cuenta['fusion']++;
                continue;
            }

            // Perfil de tmapi → nombre y apellido.
            $r = ['llamadas' => 0];
            $perfiles = TmFechas::traerPerfiles('jugador', [$tmId], $r);
            $perfil = $perfiles[$tmId] ?? null;
            if (!$perfil) {
                $cuenta['nada']++;
                $sinResolver[] = "{$etq}: TM {$tmId} «{$nombreTm}» — tmapi no devolvió el perfil";
                continue;
            }
            $n = NombreHelper::separarTM($perfil);
            $nom = trim((string) ($n['nombre'] ?? ''));
            $ape = trim((string) ($n['apellido'] ?? ''));
            if ($nom === '' || $ape === '' || mb_strtolower($nom) === mb_strtolower($ape)) {
                $cuenta['nada']++;
                $sinResolver[] = "{$etq}: TM {$tmId} «{$nombreTm}» tampoco tiene nombre completo";
                continue;
            }

            $otra = DB::table('personas')->where('id', '<>', $per->persona_id)
                ->where('nombre', $nom)->where('apellido', $ape)->whereDate('nacimiento', $fecha)->first();
            if ($otra) {
                $this->fusion($aplicar, $etq, $per->persona_id, $otra, "TM {$tmId} «{$ape}, {$nom}»");
                $cuenta['fusion']++;
                continue;
            }

            $this->line(($aplicar ? '' : '[prueba] ') . "NOMBRE   {$etq} → «{$ape}, {$nom}» (TM {$tmId})");
            if ($aplicar) {
                DB::table('personas')->where('id', $per->persona_id)
                    ->update(['nombre' => $nom, 'apellido' => $ape, 'updated_at' => now()]);
                if (!$atado) {
                    DB::table('jugador_tm')->insert([
                        'tm_player_id' => $tmId, 'jugador_id' => $per->jugador_id, 'nombre_tm' => $nombreTm,
                        'origen' => 'lf-plantel', 'revisar' => 0, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
            $cuenta['nombre']++;
        }

        $this->line('');
        $this->line(($aplicar ? '' : 'Prueba · ') . "Con nombre: {$cuenta['nombre']} · fusiones: {$cuenta['fusion']}"
            . " · ambiguos: {$cuenta['ambiguo']} · sin resolver: " . ($cuenta['nada'] + $cuenta['sin_fecha'])
            . " · páginas de TM bajadas: {$this->creditos}");
        if ($sinResolver) {
            $this->line('Quedan a mano:');
            foreach ($sinResolver as $l) $this->line('   ' . $l);
        }
        if (!$aplicar) $this->line('Para hacerlo de verdad: php artisan lf:nombres-desde-tm --aplicar');
        return 0;
    }

    private function fusion($aplicar, $etq, $nuevaId, $vieja, $por)
    {
        $txt = "FUSIÓN   {$etq} → #{$vieja->id} «{$vieja->apellido}, {$vieja->nombre}» ({$por})";
        if (!$aplicar) { $this->line('[prueba] ' . $txt); return; }
        $r = FusionPersonas::fusionar((int) $vieja->id, (int) $nuevaId);
        if (empty($r['ok'])) $this->error($txt . ': ' . ($r['mensaje'] ?? 'no se pudo'));
        else $this->info($txt);
    }

    /**
     * [tm_id => ['nombre' => ..., 'nac' => 'Y-m-d']] del plantel de un club en
     * una temporada. Cacheado 30 días (también el vacío, para no repagarlo).
     */
    private function plantel($club, $season): array
    {
        $clave = "tm_plantel_{$club}_{$season}";
        $c = Cache::get($clave);
        if (is_array($c) && $c) return $c;

        $url = 'https://www.transfermarkt.es/-/kader/verein/' . rawurlencode($club)
            . '/saison_id/' . (int) $season . '/plus/1';
        $html = HttpHelper::getHtmlTm($url);
        $this->creditos++;
        if ($html && $this->option('ver')) {
            file_put_contents(storage_path("app/tm_plantel_{$club}_{$season}.html"), $html);
            $this->line("   HTML guardado en storage/app/tm_plantel_{$club}_{$season}.html");
        }
        if (!$html) {
            $this->warn("No pude leer {$url}");
            return [];
        }

        $out = [];
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xp = new \DOMXPath($dom);
        foreach ($xp->query('//table[contains(@class,"items")]/tbody/tr') as $tr) {
            $a = $xp->query('.//td[contains(@class,"hauptlink")]//a[contains(@href,"/spieler/")]', $tr)->item(0);
            if (!$a || !preg_match('#/spieler/(\d+)#', $a->getAttribute('href'), $m)) continue;
            $tmId = $m[1];
            $nac = null;
            foreach ($xp->query('./td', $tr) as $td) {
                $nac = $this->fecha($td->textContent);
                if ($nac) break;
            }
            if ($nac) $out[$tmId] = ['nombre' => trim($a->textContent), 'nac' => $nac];
        }

        if (!$out) {
            // Ni un muro de consentimiento ni un HTML que no supe leer se
            // guardan: si se cachea vacío, la próxima vez ni se intenta.
            $this->warn("No saqué ningún jugador con fecha de: {$url}");
            return [];
        }
        Cache::put($clave, $out, now()->addDays(30));
        return $out;
    }

    /** «19/08/1980 (24)», «19 ago 1980 (24)», «Aug 19, 1980 (24)» → Y-m-d. */
    private function fecha($txt)
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $txt));
        if (preg_match('#^(\d{1,2})[./](\d{1,2})[./](\d{4})#', $t, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }
        if (preg_match('#^(\d{1,2}) (\p{L}+)\.? (\d{4})#u', $t, $m)) {
            $mes = $this->meses[mb_strtolower(mb_substr($m[2], 0, 4))] ?? $this->meses[mb_strtolower(mb_substr($m[2], 0, 3))] ?? null;
            return $mes && checkdate($mes, (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $mes, $m[1]) : null;
        }
        if (preg_match('#^(\p{L}+)\.? (\d{1,2}), (\d{4})#u', $t, $m)) {
            $mes = $this->meses[mb_strtolower(mb_substr($m[1], 0, 3))] ?? null;
            return $mes && checkdate($mes, (int) $m[2], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $mes, $m[2]) : null;
        }
        return null;
    }
}
