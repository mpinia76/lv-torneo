<?php

namespace App\Services;

use App\Equipo;
use App\Partido;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * La ficha de un partido en livefutbol.com, para el botón "LF" de los
 * controles de carga.
 *
 * Misma regla que el botón TM: **el link tiene que abrir el partido**, no el
 * calendario del club ni una búsqueda. livefutbol no tiene buscador y el id
 * del partido (`ma608664`) no se deduce de nada nuestro, así que se busca:
 *
 *   1. el id del club (`te86`): `/equipos/{slug}/` redirige a la ficha nueva y
 *      el <link rel="canonical"> trae el id. El slug es el nombre a la alemana
 *      (Hafnarfjörður -> hafnarfjoerdur), con el ascii común de respaldo;
 *   2. el calendario de esa temporada del club
 *      (`/teams/te86/x/vs2004-2005/all-matches/`; las de año calendario son
 *      `vs2004`, y una temporada que no existe da 404);
 *   3. la fila con la fecha del partido: cada <tr> trae `data-match_id`,
 *      `data-datetime` (UTC: un partido de las 21 de acá figura al día
 *      siguiente, por eso la ventana de ±1 día) y el link al rival.
 *
 * Nunca inventa: si la fecha no aparea o hay dos candidatos sin rival que
 * desempate, devuelve null y la pantalla pide la URL a mano.
 *
 * Lo encontrado queda en `partido_livefutbol` / `equipo_livefutbol`: la
 * segunda vez no hay ninguna llamada.
 */
class LivefutbolPartido
{
    const BASE = 'https://www.livefutbol.com';

    /** Lo que se fue probando, para mostrarlo si no se encuentra. */
    public $pasos = [];

    public static function url($matchId): string
    {
        return self::BASE . '/match-report/ma' . (int) $matchId . '/';
    }

    public static function hayTablas(): bool
    {
        static $hay = null;
        if ($hay === null) {
            $hay = Schema::hasTable('partido_livefutbol') && Schema::hasTable('equipo_livefutbol');
        }
        return $hay;
    }

    /** match_id ya guardado de cada partido de la página: [partido_id => match_id]. */
    public static function guardados(array $partidoIds): array
    {
        if (!$partidoIds || !self::hayTablas()) {
            return [];
        }

        return DB::table('partido_livefutbol')
            ->whereIn('partido_id', $partidoIds)
            ->pluck('match_id', 'partido_id')
            ->all();
    }

    public static function anotar($partidoId, $matchId, string $origen = 'busqueda'): void
    {
        DB::table('partido_livefutbol')->updateOrInsert(
            ['partido_id' => (int) $partidoId],
            ['match_id' => (int) $matchId, 'origen' => $origen, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /** Saca el id de una URL pegada a mano (`.../ma608664/...`). */
    public static function idDeUrl(string $url): ?int
    {
        return preg_match('~/ma(\d+)(/|$)~', $url, $m) ? (int) $m[1] : null;
    }

    /**
     * Devuelve el match_id del partido (buscándolo si hace falta) o null.
     */
    public function resolver(Partido $partido): ?int
    {
        $guardado = self::guardados([$partido->id]);
        if (isset($guardado[$partido->id])) {
            return (int) $guardado[$partido->id];
        }

        if (!$partido->dia) {
            $this->pasos[] = 'El partido no tiene fecha: no hay con qué buscarlo.';
            return null;
        }

        $dia = Carbon::parse($partido->dia);
        $equipos = [
            $partido->equipol_id => Equipo::find($partido->equipol_id),
            $partido->equipov_id => Equipo::find($partido->equipov_id),
        ];

        // Ids de livefutbol de los dos clubes (los que se puedan).
        $teams = [];
        foreach ($equipos as $id => $equipo) {
            if ($equipo) {
                $teams[$id] = $this->teamId($equipo);
            }
        }

        foreach ($teams as $equipoId => $teamId) {
            if (!$teamId) {
                continue;
            }

            $rivalId   = $equipoId == $partido->equipol_id ? $partido->equipov_id : $partido->equipol_id;
            $rivalTeam = $teams[$rivalId] ?? null;
            $rival     = $equipos[$rivalId] ?? null;

            $fila = $this->buscarEnCalendario($teamId, $dia, $rivalTeam, $rival);

            if ($fila) {
                self::anotar($partido->id, $fila['match_id']);

                // De paso, el id del rival: sale de su link en la fila.
                if (!$rivalTeam && $fila['rival_team'] && $rival) {
                    $this->anotarEquipo($rival->id, $fila['rival_team'], 'partido');
                }

                return $fila['match_id'];
            }
        }

        return null;
    }

    /**
     * Id de livefutbol del club: guardado, o por `/equipos/{slug}/`.
     */
    public function teamId(Equipo $equipo): ?int
    {
        $guardado = DB::table('equipo_livefutbol')->where('equipo_id', $equipo->id)->value('team_id');
        if ($guardado) {
            return (int) $guardado;
        }

        foreach ($this->slugs($equipo->nombre) as $slug) {
            $html = HttpHelper::getHtmlContent(self::BASE . '/equipos/' . $slug . '/', false);

            if ($html && preg_match('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\'][^"\']*/teams/te(\d+)/~i', $html, $m)) {
                $this->anotarEquipo($equipo->id, (int) $m[1], 'slug');
                $this->pasos[] = "{$equipo->nombre}: club te{$m[1]} (por /equipos/{$slug}/).";
                return (int) $m[1];
            }
        }

        $this->pasos[] = "{$equipo->nombre}: no se encontró el club en livefutbol (probé "
            . implode(', ', $this->slugs($equipo->nombre)) . ').';

        return null;
    }

    private function anotarEquipo($equipoId, $teamId, string $origen): void
    {
        DB::table('equipo_livefutbol')->updateOrInsert(
            ['equipo_id' => (int) $equipoId],
            ['team_id' => (int) $teamId, 'origen' => $origen, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * Slugs posibles del nombre. livefutbol hereda los de worldfootball, que
     * transliteran a la alemana: ö -> oe, ü -> ue, ð -> d.
     */
    public function slugs(string $nombre): array
    {
        $aleman = strtr($nombre, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss', 'ð' => 'd', 'Ð' => 'D', 'þ' => 'th', 'Þ' => 'Th',
            'æ' => 'ae', 'Æ' => 'Ae', 'ø' => 'oe', 'Ø' => 'Oe', 'å' => 'aa', 'Å' => 'Aa',
        ]);

        return array_values(array_unique(array_filter([
            Str::slug($aleman),
            Str::slug($nombre),
        ])));
    }

    /**
     * Temporadas a probar para una fecha, la más probable primero.
     */
    private function temporadas(Carbon $dia): array
    {
        $y = (int) $dia->year;

        return $dia->month >= 7
            ? ["vs{$y}-" . ($y + 1), "vs{$y}", 'vs' . ($y - 1) . "-{$y}"]
            : ['vs' . ($y - 1) . "-{$y}", "vs{$y}", "vs{$y}-" . ($y + 1)];
    }

    /**
     * La fila del partido en el calendario del club, o null.
     *
     * @return array{match_id:int, rival_team:?int}|null
     */
    private function buscarEnCalendario(int $teamId, Carbon $dia, ?int $rivalTeam, ?Equipo $rival): ?array
    {
        $slugsRival = $rival ? $this->slugs($rival->nombre) : [];

        foreach ($this->temporadas($dia) as $temporada) {
            $url  = self::BASE . "/teams/te{$teamId}/x/{$temporada}/all-matches/";
            $html = HttpHelper::getHtmlContent($url, false);

            if (!$html || stripos($html, 'data-match_id') === false) {
                continue;
            }

            $candidatos = [];

            foreach ($this->filas($html) as $fila) {
                // data-datetime viene en UTC: pasado a hora de acá cae en el
                // día que tenemos cargado casi siempre; el ±1 cubre el resto.
                $enHoraLocal = Carbon::parse($fila['datetime'])->setTimezone(config('app.timezone') ?: 'America/Argentina/Buenos_Aires');
                $diff = (int) round(abs($enHoraLocal->copy()->startOfDay()->diffInDays($dia->copy()->startOfDay(), false)));
                if ($diff > 1) {
                    continue;
                }

                // Con el id del rival conocido, tiene que ser ése.
                if ($rivalTeam && $fila['rival_team'] && $fila['rival_team'] !== $rivalTeam) {
                    continue;
                }

                $puntos = 10 - $diff;
                if ($rivalTeam && $fila['rival_team'] === $rivalTeam) {
                    $puntos += 100;
                } elseif ($fila['rival_slug'] && in_array($fila['rival_slug'], $slugsRival, true)) {
                    $puntos += 50;
                }

                $candidatos[] = $fila + ['puntos' => $puntos];
            }

            if (!$candidatos) {
                $this->pasos[] = "te{$teamId} {$temporada}: ningún partido el " . $dia->format('d/m/Y') . ' (±1 día).';
                // La temporada existe y el partido no está: probar otra igual
                // (un partido de enero puede figurar en la temporada anterior).
                continue;
            }

            usort($candidatos, function ($a, $b) { return $b['puntos'] <=> $a['puntos']; });

            // Dos filas en la ventana y nada que las desempate: no adivinar.
            if (count($candidatos) > 1 && $candidatos[0]['puntos'] === $candidatos[1]['puntos']) {
                $this->pasos[] = "te{$teamId} {$temporada}: hay más de un partido en esa fecha y no se pudo saber cuál es.";
                return null;
            }

            return ['match_id' => $candidatos[0]['match_id'], 'rival_team' => $candidatos[0]['rival_team']];
        }

        return null;
    }

    /**
     * Las filas de partidos del calendario.
     */
    private function filas(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xp = new \DOMXPath($dom);

        $filas = [];

        foreach ($xp->query('//tr[@data-match_id and @data-datetime]') as $tr) {
            $rivalTeam = null;
            $rivalSlug = null;

            foreach ($xp->query('.//a[contains(@href, "/teams/te")]', $tr) as $a) {
                if (preg_match('~/teams/te(\d+)/([^/]+)/~', $a->getAttribute('href'), $m)) {
                    $rivalTeam = (int) $m[1];
                    $rivalSlug = $m[2];
                    break;
                }
            }

            $filas[] = [
                'match_id'   => (int) $tr->getAttribute('data-match_id'),
                'datetime'   => $tr->getAttribute('data-datetime'),
                'rival_team' => $rivalTeam,
                'rival_slug' => $rivalSlug,
            ];
        }

        return $filas;
    }
}
