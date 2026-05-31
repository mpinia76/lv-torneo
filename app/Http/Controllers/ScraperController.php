<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HttpHelper;
use Illuminate\Support\Facades\Log;
use App\Equipo;
use App\Torneo;
use App\EquipoEstadisticaManual;
use Illuminate\Support\Str;
use App\CompetenciaExcluida;
class ScraperController extends Controller
{
    public function test()
    {
        $nombre = "Julio Cesar Falcioni";

        $url = "https://www.transfermarkt.com/schnellsuche/ergebnis/schnellsuche?query=" . urlencode($nombre);

        // 🔥 Fallback inteligente
        $html = HttpHelper::getHtmlContent($url, false);

        if (!$html) {
            return "Error obteniendo búsqueda";
        }

        // 🔥 PARSE HTML
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 🔍 Buscar técnico
        $nodes = $xpath->query('//a[contains(@href, "/profil/trainer/")]');

        if ($nodes->length == 0) {
            return "No se encontró el técnico";
        }

        $link = $nodes->item(0)->getAttribute('href');
        $perfilUrl = "https://www.transfermarkt.com" . $link;

        // 🔥 PERFIL
        $htmlPerfil = HttpHelper::getHtmlContent($perfilUrl, false);



        if (!$htmlPerfil) {
            return "Error perfil";
        }

        $domPerfil = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domPerfil->loadHTML($htmlPerfil);
        libxml_clear_errors();

        $xpathPerfil = new \DOMXPath($domPerfil);

        // 🔍 Buscar link historial (más robusto)
        $historialNodes = $xpathPerfil->query('//a[contains(@href,"stationen")]');

        if ($historialNodes->length == 0) {
            return "No se encontró historial";
        }

        $historialLink = $historialNodes->item(0)->getAttribute('href');
        $historialUrl = "https://www.transfermarkt.com" . $historialLink;

        // 🔥 HISTORIAL
        $htmlHistorial = HttpHelper::getHtmlContent($historialUrl, false);


        if (!$htmlHistorial) {
            return "Error historial";
        }

        $domHistorial = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domHistorial->loadHTML($htmlHistorial);
        libxml_clear_errors();

        $xpathHistorial = new \DOMXPath($domHistorial);

        $rows = $xpathHistorial->query('//table[contains(@class,"items")]/tbody/tr');

        //dd($rows);

        $data = [];

        foreach ($rows as $row) {

            $cols = $row->getElementsByTagName('td');

            if ($cols->length < 5) {
                continue;
            }

            // 🔥 EQUIPO + ROL
            $equipoRaw = trim($cols->item(1)->textContent);

            preg_match('/^(.*?)(Manager|Assistant Manager|Caretaker Manager|Sporting Director)?$/', $equipoRaw, $matches);

            $equipo = trim($matches[1]);
            $rol = isset($matches[2]) ? trim($matches[2]) : '';

            // 🔥 TEMPORADA
            $temporadaRaw = trim($cols->item(2)->textContent);

            preg_match('/^\d{2}\/\d{2}/', $temporadaRaw, $matchesTemp);
            $temporada = $matchesTemp[0] ?? null;

            // 🔥 PARTIDOS (col 4 en tu caso)
            $partidos = trim($cols->item(4)->textContent);

            // 🔥 filtrar solo técnicos
            if (!preg_match('/Manager/i', $rol)) {
                continue;
            }

            if (!$temporada || !$equipo || !$partidos) {
                continue;
            }
            $year = intval('20' . substr($temporada, 0, 2));
            $resultado = [];

            foreach ($data as $item) {

                $key = $item['temporada'] . '-' . $item['equipo'];

                if (!isset($resultado[$key])) {
                    $resultado[$key] = $item;
                } else {
                    $resultado[$key]['partidos'] += (int)$item['partidos'];
                }
            }

            $data = array_values($resultado);

            $data[] = [
                'temporada' => $temporada,
                'equipo' => $equipo,
                'partidos' => $partidos
            ];
        }

        return response()->json($data);
    }

    function normalizeTeam($str) {
        // 1️⃣ Pasar a ASCII, eliminar acentos
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);

        // 2️⃣ Convertir a minúsculas
        $str = mb_strtolower($str);

        // 3️⃣ Eliminar palabras "manager", "assistantmanager", etc. pegadas o separadas
        $str = preg_replace('/(manager|assistantmanager|caretakermanager|sportingdirector)/i', '', $str);

        // 4️⃣ Eliminar espacios, puntos, comillas, apóstrofes y caracteres no alfanuméricos
        $str = preg_replace('/[^\p{L}\p{N}]+/u', '', $str);

        return $str;
    }
    private function scrapearTecnico($nombre)
    {
        set_time_limit(0);
        $url = "https://www.transfermarkt.com/schnellsuche/ergebnis/schnellsuche?query=" . urlencode($nombre);
        //dd($url);
        $html = HttpHelper::getHtmlContent($url, false);


        //dd($html);
        if (!$html) {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query('//a[contains(@href, "/profil/trainer/")]');

        if ($nodes->length == 0) {
            return [];
        }

        $link = $nodes->item(0)->getAttribute('href');
        $perfilUrl = "https://www.transfermarkt.com" . $link;

        $htmlPerfil = HttpHelper::getHtmlContent($perfilUrl, false);
        //dd($htmlPerfil);
        if (!$htmlPerfil) {
            return [];
        }

        $domPerfil = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domPerfil->loadHTML($htmlPerfil);
        libxml_clear_errors();

        $xpathPerfil = new \DOMXPath($domPerfil);

        $historialNodes = $xpathPerfil->query('//a[contains(@href,"stationen")]');

        if ($historialNodes->length == 0) {
            return [];
        }

        $historialUrl = "https://www.transfermarkt.com" . $historialNodes->item(0)->getAttribute('href') . "/plus/1";
        //dd($historialUrl);
        $htmlHistorial = HttpHelper::getHtmlContent($historialUrl, false);

        if (!$htmlHistorial) {
            return [];
        }

        $domHistorial = new \DOMDocument();
        libxml_use_internal_errors(true);
        $domHistorial->loadHTML($htmlHistorial);
        libxml_clear_errors();

        $xpathHistorial = new \DOMXPath($domHistorial);

        $rows = $xpathHistorial->query('//table[contains(@class,"items")]/tbody/tr');

        $stats = [];

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            //if ($cols->length < 13) continue;

            // 🔹 Filtrar solo Manager
            $equipoRaw = trim($cols->item(1)->textContent);
            preg_match('/^(.*?)(Manager|Assistant Manager|Caretaker Manager|Sporting Director)?$/', $equipoRaw, $matches);
            $equipo = trim($matches[1]); // 👈 ESTE es el bueno
            //Log::info('Equipo: '.$equipo.' -> '.$equipoRaw);
            $rol = $matches[2] ?? '';
            if (!preg_match('/Manager/i', $rol)) continue;


            // 🔹 Link a matches
            $matchLink = $cols->item(6)->getElementsByTagName('a')->item(0)->getAttribute('href'). "/plus/1";
            //Log::info('Link: '.$matchLink);
// ✅ Check si ya es absoluto
            if (str_starts_with($matchLink, 'http')) {
                $matchUrl = $matchLink;
            } else {
                $matchUrl = "https://www.transfermarkt.com" . $matchLink;
            }

            //dd($matchUrl);
            $htmlMatches = HttpHelper::getHtmlContent($matchUrl, false);

            if (!$htmlMatches) $htmlMatches = HttpHelper::getHtmlContent($matchUrl, true);

            if (!$htmlMatches) continue;

            $domMatches = new \DOMDocument();
            libxml_use_internal_errors(true);
            $domMatches->loadHTML($htmlMatches);
            libxml_clear_errors();
            $xpathMatches = new \DOMXPath($domMatches);

            $matchRows = $xpathMatches->query('//table[contains(@class,"items")]/tbody/tr');
            //Log::info(print_r($matchRows, true));
            foreach ($matchRows as $matchRow) {

                $cols = $matchRow->getElementsByTagName('td');

                //if ($cols->length < 6) continue;

                // 🔹 Datos principales de la fila
                $competitionName = trim($cols->item(1)->textContent); // columna 1 -> nombre de la competición

                $temporada = trim($cols->item(2)->textContent);       // columna 2 -> año/temporada
                $homeTeam = trim($cols->item(4)->textContent);        // columna 4 -> local

                $resultStr = trim($cols->item(6)->textContent);      // columna 5 -> resultado "2:1"
                $awayTeam = trim($cols->item(8)->textContent);        // columna 6 -> visitante

                //dd($competitionName,$temporada,$equipo);
                //Log::info($equipo.' -> '.$homeTeam.' -> '.$awayTeam.' -> '.$resultStr);
                if (strpos($resultStr, ':') === false) continue; // ignorar si no hay resultado
                list($homeGoals, $awayGoals) = array_map('intval', explode(':', $resultStr));



                $teamNameNorm = $this->normalizeTeam($equipo); // equipo dirigido
                $homeTeamNorm = $this->normalizeTeam($homeTeam);
                $awayTeamNorm = $this->normalizeTeam($awayTeam);
                //dd($teamNameNorm, $homeTeamNorm,$awayTeamNorm,$homeGoals, $awayGoals);

                // 🔹 Determinar goles a favor, en contra y resultado
                if ($teamNameNorm === $homeTeamNorm) {
                    $gf = $homeGoals;
                    $ge = $awayGoals;
                    $resultado = $gf > $ge ? 'W' : ($gf < $ge ? 'L' : 'D');
                } elseif ($teamNameNorm === $awayTeamNorm) {
                    $gf = $awayGoals;
                    $ge = $homeGoals;
                    $resultado = $gf > $ge ? 'W' : ($gf < $ge ? 'L' : 'D');
                } else {
                    //$teamNameNorm.' -> '.$homeTeamNorm.' -> '.$awayTeamNorm.' -> '.$homeGoals.' -> '.$awayGoals);
                    continue; // partido no corresponde al equipo dirigido
                }

                // 🔹 Stats del partido
                $partidos = 1;
                $ganados = $resultado === 'W' ? 1 : 0;
                $empatados = $resultado === 'D' ? 1 : 0;
                $perdidos = $resultado === 'L' ? 1 : 0;

                // 🔹 Usamos el nombre de la competición como identificador
                $competitionId = $competitionName;

                $key = trim($competitionId) . '|' . trim($equipo);

                if (!isset($stats[$key])) {
                    //Log::info('1era '.$equipo.' -> '.$partidos.' -> '.$ganados.' -> '.$empatados.' -> '.$perdidos);
                    $stats[$key] = [
                        'competition_id' => $competitionId,
                        'competition' => trim($competitionName).' '.trim($temporada),
                        'equipo' => $equipo,
                        'partidos' => $partidos,
                        'ganados' => $ganados,
                        'empatados' => $empatados,
                        'perdidos' => $perdidos,
                        'gf' => $gf,
                        'ge' => $ge
                    ];
                } else {
                    //Log::info($equipo.' -> '.$partidos.' -> '.$ganados.' -> '.$empatados.' -> '.$perdidos);
                    $stats[$key]['partidos'] += $partidos;
                    $stats[$key]['ganados'] += $ganados;
                    $stats[$key]['empatados'] += $empatados;
                    $stats[$key]['perdidos'] += $perdidos;
                    $stats[$key]['gf'] += $gf;
                    $stats[$key]['ge'] += $ge;


                }
            }
        }

        return response()->json(array_values($stats));
    }

    public function autocompletar(Request $request)
    {
        $tecnico = \App\Tecnico::findOrFail($request->tecnico_id);

        $variantes = [
            $tecnico->persona->nombre . ' ' . $tecnico->persona->apellido,
            $tecnico->persona->apellido,
            $tecnico->persona->nombre,
            $tecnico->persona->name,
        ];

        foreach ($variantes as $nombre) {
            $data = $this->scrapearTecnico($nombre);

            if (!empty($data)) {
                return response()->json($data);
            }
        }

        return response()->json([]);
    }

    private function debeExcluirCompetencia($nombre)
    {
        return CompetenciaExcluida::debeExcluir($nombre);
    }

    private function debeExcluirEquipo($nombre)
    {
        return \App\EquipoExcluido::debeExcluir($nombre);
    }

    private function normalizeKey($name, $year)
    {
        $name = \Str::of($name)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim();

        return $name . ' ' . $year;
    }


    public function equipo(Request $request)
    {
        $equipo = Equipo::find($request->equipo_id);

        if (!$equipo) {
            return response()->json([]);
        }

        $teamId = $equipo->url_id;

        /*
        |---------------------------------------
        | SLUG DEL EQUIPO
        |---------------------------------------
        */
        $slug = \Str::of($equipo->nombre)
            ->lower()
            ->ascii()
            ->replace(' ', '-');

        $slug = (string) $slug;

        /*
        |---------------------------------------
        | URL FINAL
        |---------------------------------------
        */
        $baseUrl = "https://www.livefutbol.com/teams/te{$teamId}/{$slug}/all-matches/";

        $html = HttpHelper::getHtmlContent($baseUrl, false);

        if (!$html) {
            return response()->json([]);
        }

        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        @$dom->loadHTML($html);

        $xp = new \DOMXPath($dom);

        /*
        |---------------------------------------
        | TEMPORADAS
        |---------------------------------------
        */
        $seasonNodes = $xp->query("//select[contains(@class,'season-navigation')]/option");

        $seasons = [];

        foreach ($seasonNodes as $node) {
            $url = trim($node->getAttribute('value'));
            if ($url) {
                $seasons[] = "https://www.livefutbol.com" . $url;
            }
        }
        //dd($seasons);
        /*
        |---------------------------------------
        | EXISTENTES (MANUALES + AUTOMÁTICOS)
        |---------------------------------------
        */
        $existentes = collect()
            ->merge(
                EquipoEstadisticaManual::where('equipo_id', $equipo->id)
                    ->pluck('torneo_nombre')
            )
            ->merge(
                Torneo::all()->map(function ($t) {
                    return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
                })
            )
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)
                    ->lower()
                    ->ascii()
                    ->replaceMatches('/\s+/', ' ')
                    ->trim();
            })
            ->filter()
            ->unique()
            ->flip()   // 👈 ESTE ES EL FIX CLAVE
            ->toArray();

        //dd($existentes);

       // Log::info(print_r($existentes, true));
        $torneos = [];

        /*
        |---------------------------------------
        | POR TEMPORADA
        |---------------------------------------
        */
        foreach ($seasons as $seasonUrl) {

            $seasonYear = null;

            if (preg_match('/vs(\d{4})/', $seasonUrl, $m)) {
                $seasonYear = $m[1];
            }

            $htmlSeason = HttpHelper::getHtmlContent($seasonUrl, false);
            if (!$htmlSeason) continue;

            $dom2 = new \DOMDocument();
            @$dom2->loadHTML($htmlSeason);

            $xp2 = new \DOMXPath($dom2);

            /*
            |---------------------------------------
            | COMPETITIONS
            |---------------------------------------
            */

            $compNodes = $xp2->query("//select[contains(@class,'hs-filter-competition_id')]/option");
            $hasSelect = $compNodes->length > 0;

            /*
            |---------------------------------------
            | CASO 1: HAY SELECT DE COMPETENCIAS
            |---------------------------------------
            */
            if ($hasSelect) {

                foreach ($compNodes as $compNode) {

                    $compId = trim($compNode->getAttribute('value'));
                    $compName = trim($compNode->nodeValue);

                    if (!$compId || $compId == "0") continue;

                    $key = $this->normalizeKey($compName, $seasonYear);

                    if (isset($existentes[$key])) {
                        continue;
                    }

                    if ($this->debeExcluirCompetencia($compName)) {
                        continue;
                    }

                    $dom3 = new \DOMDocument();
                    @$dom3->loadHTML($htmlSeason);

                    $xp3 = new \DOMXPath($dom3);

                    $rows = $xp3->query("//tr[contains(@class,'match') and @data-competition_id='{$compId}']");

                    $played = $won = $draw = $lost = $gf = $ga = 0;

                    foreach ($rows as $row) {

                        $spans = $row->getElementsByTagName('span');

                        foreach ($spans as $span) {

                            $class = $span->getAttribute('class');

                            if (str_contains($class, 'match-result')) {

                                $text = trim($span->nodeValue);

                                if (preg_match('/\d+\s*:\s*\d+/', $text, $m)) {
                                    $resultText = $m[0];

                                    if (preg_match('/(\d+)\s*:\s*(\d+)/', $resultText, $m2)) {

                                        $teamGoals = (int) $m2[1];
                                        $opponentGoals = (int) $m2[2];

                                        $gf += $teamGoals;
                                        $ga += $opponentGoals;

                                        if ($teamGoals > $opponentGoals) $won++;
                                        elseif ($teamGoals < $opponentGoals) $lost++;
                                        else $draw++;

                                        $played++;
                                    }
                                }

                                break;
                            }
                        }
                    }

                    if ($played === 0) continue;

                    $torneos[$key] = [
                        'liga' => $compName,
                        'year' => $seasonYear,
                        'competition' => $key,
                        'partidos' => $played,
                        'ganados' => $won,
                        'empatados' => $draw,
                        'perdidos' => $lost,
                        'gf' => $gf,
                        'ge' => $ga
                    ];
                }

                /*
                |---------------------------------------
                | CASO 2: NO HAY SELECT (FALLBACK)
                |---------------------------------------
                */
            } else {

                $compName = 'Todos los partidos';
                /*
     |---------------------------------------
     | INTENTAR OBTENER NOMBRE REAL
     |---------------------------------------
     */
                $header = $xp2->query("//tr[contains(@class,'competition-head')]")->item(0);

                if ($header) {
                    $link = $header->getElementsByTagName('a')->item(1);
                    $compName = $link ? trim($link->nodeValue) : 'Sin nombre';
                } else {
                    $compName = 'Sin competencia';
                }

                $key = $this->normalizeKey($compName, $seasonYear);
                //log::info($key);
                if (!isset($existentes[$key]) && !$this->debeExcluirCompetencia($compName)) {

                    $rows = $xp2->query("//tr[contains(@class,'match')]");

                    $played = $won = $draw = $lost = $gf = $ga = 0;

                    foreach ($rows as $row) {

                        $rowText = trim($row->textContent);

                        if (preg_match('/(\d+)\s*:\s*(\d+)/', $rowText, $m)) {

                            $home = (int) $m[1];
                            $away = (int) $m[2];

                            $gf += $home;
                            $ga += $away;

                            if ($home > $away) $won++;
                            elseif ($home < $away) $lost++;
                            else $draw++;

                            $played++;
                        }
                    }

                    if ($played > 0) {

                        $torneos[$key] = [
                            'liga' => $compName,
                            'year' => $seasonYear,
                            'competition' => $key,
                            'partidos' => $played,
                            'ganados' => $won,
                            'empatados' => $draw,
                            'perdidos' => $lost,
                            'gf' => $gf,
                            'ge' => $ga
                        ];
                    }
                }
            }
        }
        //dd($torneos);
        return response()->json(array_values($torneos));
    }

    public function csvTecnico(Request $request)
    {
        set_time_limit(0);

        $file = $request->file('file');

        if (!$file) {
            return response()->json([]);
        }

        $extension = $file->getClientOriginalExtension();

        if (strtolower($extension) != 'csv') {
            return response()->json([]);
        }

        $filepath = $file->getRealPath();
        $handle = fopen($filepath, "r");

        $data = [];
        $header = [];
        $i = 0;

        $tecnicoId = $request->tecnico_id;

        $existentes = collect()
            ->merge(
                $tecnicoId
                    ? \App\TecnicoEstadisticaManual::where('tecnico_id', $tecnicoId)->pluck('torneo_nombre')
                    : collect()
            )
            ->merge(\App\Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

            if ($i == 0) {
                $header = array_map('strtolower', $row);
                $i++;
                continue;
            }



            $row = array_combine($header, $row);

            $equipo   = trim($row['equipo'] ?? '');
            $torneo   = trim($row['torneo'] ?? '');
            $year     = trim($row['año'] ?? '');
            $logoUrl  = trim($row['logo_url'] ?? '');

            if (!$equipo || !$torneo) continue;

            $key = (string) \Str::of($torneo . ' ' . $year)
                ->lower()
                ->ascii()
                ->replaceMatches('/\s+/', ' ')
                ->trim();

            if (isset($existentes[$key])) {
                continue;
            }

            if ($this->debeExcluirCompetencia($torneo)) {
                continue;
            }

            $logoNombre = $logoUrl ?: null;

            $data[] = [
                'competition' => trim($torneo . ' ' . $year),
                'equipo'      => $equipo,
                'posicion'    => (int) ($row['posicion'] ?? 0),
                'partidos'    =>
                    ((int) ($row['ganados'] ?? 0)) +
                    ((int) ($row['empatados'] ?? 0)) +
                    ((int) ($row['perdidos'] ?? 0)),
                'ganados'     => (int) ($row['ganados'] ?? 0),
                'empatados'   => (int) ($row['empatados'] ?? 0),
                'perdidos'    => (int) ($row['perdidos'] ?? 0),
                'gf'          => (int) ($row['gf'] ?? 0),
                'ge'          => (int) ($row['gc'] ?? 0),
                'torneo_logo' => $logoNombre,
                'tipo'        => trim($row['tipo'] ?? ''),
                'ambito'      => trim($row['ambito'] ?? ''),
            ];
        }

        fclose($handle);

        return response()->json($data);
    }



    public function tecnicoFootballDatabase(Request $request)
    {
        set_time_limit(0);

        $url = trim($request->url);
        if (!$url) return response()->json([]);

        $html = HttpHelper::getHtmlContent($url, false);
        if (!$html) $html = HttpHelper::getHtmlContent($url, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $tecnicoId = $request->tecnico_id;

        $existentes = collect()
            ->merge(
                $tecnicoId
                    ? \App\TecnicoEstadisticaManual::where('tecnico_id', $tecnicoId)->pluck('torneo_nombre')
                    : collect()
            )
            ->merge(\App\Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        //\Log::info("[FBDB EXISTENTES] tecnicoId={$tecnicoId} | total=" . count($existentes));   // 🆕


        $rows = $xpath->query('//tr[contains(@class,"line") and not(contains(@class,"total"))]');
        $data = [];

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            if ($cols->length < 4) continue;

            $season = trim($cols->item(0)->textContent);
            preg_match('/(\d{4})/', $season, $mYear);
            $year = $mYear[1] ?? null;
            if (!$year || (int)$year < 2000) continue;

            $clubCell = $cols->item(1);
            /*$flagSpan = $xpath->query('.//span[@class="real_flag"]', $clubCell)->item(0);
            $country  = $flagSpan ? trim($flagSpan->getAttribute('title')) : '';
            if (strtolower($country) === 'argentina') continue;*/

            $clubLink = $xpath->query('.//a', $clubCell)->item(0);
            $club = $clubLink ? trim($clubLink->textContent) : trim($clubCell->textContent);

            if ($this->debeExcluirEquipo($club)) continue;

            $competencias = [
                'champ' => ['tipo' => 'Liga', 'ambito' => 'Nacional'],
                'cont'  => ['tipo' => 'Copa', 'ambito' => 'Internacional'],
                'cup'   => ['tipo' => 'Copa', 'ambito' => 'Nacional'],
            ];

            foreach ($competencias as $suffix => $meta) {

                $pj = 0; $v = 0; $e = 0; $d = 0; $gf = 0; $gc = 0;
                $compName = null;
                $posicion = null;
                $lastRoundsRaw = '';

                foreach ($cols as $col) {
                    $class = $col->getAttribute('class');

                    if (str_contains($class, 'matchsplayed') && str_contains($class, $suffix)) {
                        $pj = (int) trim($col->textContent);
                    }
                    if (str_contains($class, 'pc_v1') && str_contains($class, $suffix)) {
                        $slip = $xpath->query('.//span[@class="slip"]', $col)->item(0);
                        $v = $slip ? (int) trim($slip->textContent) : 0;
                    }
                    if (str_contains($class, 'pc_d1') && str_contains($class, $suffix)) {
                        $slip = $xpath->query('.//span[@class="slip"]', $col)->item(0);
                        $e = $slip ? (int) trim($slip->textContent) : 0;
                    }
                    if (str_contains($class, 'pc_l1') && str_contains($class, $suffix)) {
                        $slip = $xpath->query('.//span[@class="slip"]', $col)->item(0);
                        $d = $slip ? (int) trim($slip->textContent) : 0;
                    }
                    if (str_contains($class, 'pc_goalsfor1') && str_contains($class, $suffix)) {
                        $a = $xpath->query('.//a', $col)->item(0);
                        $gf = $a ? (int) trim($a->textContent) : 0;
                    }
                    if (str_contains($class, 'pc_goalsagainst1') && str_contains($class, $suffix)) {
                        $a = $xpath->query('.//a', $col)->item(0);
                        $gc = $a ? (int) trim($a->textContent) : 0;
                    }
                    if (str_contains($class, 'pc_lastrounds1') && str_contains($class, $suffix)) {
                        // Try span.competition first
                        $spans = $xpath->query('.//span[@class="competition"]', $col);
                        if ($spans->length > 0) {
                            $compName = trim($spans->item(0)->textContent);
                        }
                        // Always grab raw text for multi-competition parsing
                        $lastRoundsRaw = trim($col->textContent);
                    }
                    if (str_contains($class, 'pc_club_ranking1') && str_contains($class, $suffix)) {
                        $a = $xpath->query('.//a', $col)->item(0);
                        $text = $a ? trim($a->textContent) : trim($col->textContent);
                        $num = (int) preg_replace('/\D/', '', $text);
                        $posicion = ($num > 0 && $num <= 50) ? $num : null;
                    }
                }

                if ($pj === 0) continue;

                // Fallback name for champ from href
                if (!$compName && $suffix === 'champ') {
                    $compLink = $xpath->query('.//td[contains(@class,"champ")]//a', $row)->item(0);
                    if ($compLink) {
                        $href = $compLink->getAttribute('href');
                        preg_match('/\/\d+-([^\/]+)\//', $href, $mComp);
                        if (isset($mComp[1])) {
                            $compName = ucwords(str_replace('_', ' ', $mComp[1]));
                        }
                    }
                }

                // 🆕 Fallback for cont/cup when lastRoundsRaw is empty
                // 🆕 Fallback for cont/cup when lastRoundsRaw is empty
                if (!$compName && ($suffix === 'cont' || $suffix === 'cup') && empty($lastRoundsRaw)) {
                    $clubLink = $xpath->query('.//a', $clubCell)->item(0);
                    if ($clubLink) {
                        $clubHref = $clubLink->getAttribute('href');
                        $clubUrl = "https://www.footballdatabase.eu" . $clubHref;
                        //\Log::info("[FALLBACK] Resolving for {$club} {$year} suffix={$suffix} url={$clubUrl}");
                        $compName = $this->resolveCompetitionFromClubPage($clubUrl, $suffix);
                        //\Log::info("[FALLBACK] Result: " . ($compName ?? 'NULL'));
                    }
                }

                // For cont/cup: parse multiple competitions from lastRoundsRaw
                // Format: "- Copa Libertadores2e t2e tour - Copa Sudamericana1er 1er tour"
                if (($suffix === 'cont' || $suffix === 'cup') && $lastRoundsRaw) {
                    // Split on " - " keeping each competition block
                    $parts = preg_split('/\s+-\s+/', $lastRoundsRaw, -1, PREG_SPLIT_NO_EMPTY);

                    $competitionsFound = [];
                    foreach ($parts as $part) {
                        $part = trim($part);
                        if (!$part) continue;

                        // Strip round suffixes — everything after the comp name
                        // Patterns: "1/2 finales", "1/4 de finale", "FinaFinale", "Grou Groupe X",
                        //           "2e t2e tour", "1er 1er tour", "Tour préliminaire", etc.
                        $name = preg_replace(
                            '/\s*(Fina\s*Finale?|Final|1\/2.*|1\/4.*|1\/8.*|1\/16.*|1\/32.*|\d+[a-z]*\s*[a-z]*\s*tour.*|Grou.*|Tour.*|Group.*|Phase.*|Round.*|\d+\s*de\s*finale.*|Journee.*)/i',
                            '',
                            $part
                        );
                        $name = trim($name);

                        if (strlen($name) > 2) {
                            $competitionsFound[] = $name;
                        }
                    }

                    if (empty($competitionsFound) && $compName) {
                        $competitionsFound[] = $compName;
                    }

                    if (empty($competitionsFound)) {
                        $competitionsFound[] = $meta['tipo'];
                    }

                    // Determine ambito per competition name
                    $internacionalKw = ['champions', 'libertadores', 'sudamericana', 'europa league',
                        'concacaf', 'mundial', 'intercontinental', 'club world', 'recopa'];

                    foreach ($competitionsFound as $cName) {

                        if ($this->debeExcluirCompetencia($cName)) {
                            continue;
                        }

                        $competition = $cName . ' ' . $year;
                        $key = (string) \Str::of($competition)->lower()->ascii()
                            ->replaceMatches('/\s+/', ' ')->trim();

                        if (isset($existentes[$key])) continue;

                        $ambitoComp = 'Nacional';
                        foreach ($internacionalKw as $kw) {
                            if (stripos($cName, $kw) !== false) { $ambitoComp = 'Internacional'; break; }
                        }

                        $data[] = [
                            'competition' => $competition,
                            'equipo'      => $club,
                            'posicion'    => null,
                            'partidos'    => $pj,
                            'ganados'     => $v,
                            'empatados'   => $e,
                            'perdidos'    => $d,
                            'gf'          => $gf,
                            'ge'          => $gc,
                            'torneo_logo' => null,
                            'tipo'        => 'Copa',
                            'ambito'      => $ambitoComp,
                        ];
                    }

                    // Skip the generic single-entry below for cont/cup
                    continue;
                }

                // For champ: single entry
                if (!$compName) $compName = $meta['tipo'];



                if ($this->debeExcluirCompetencia($compName)) {
                    continue;
                }

                $competition = $compName . ' ' . $year;
                $key = (string) \Str::of($competition)->lower()->ascii()
                    ->replaceMatches('/\s+/', ' ')->trim();

                if (isset($existentes[$key])) continue;

                if ($this->debeExcluirCompetencia($compName)) {
                    continue;
                }

                $data[] = [
                    'competition' => $competition,
                    'equipo'      => $club,
                    'posicion'    => $posicion,
                    'partidos'    => $pj,
                    'ganados'     => $v,
                    'empatados'   => $e,
                    'perdidos'    => $d,
                    'gf'          => $gf,
                    'ge'          => $gc,
                    'torneo_logo' => null,
                    'tipo'        => $meta['tipo'],
                    'ambito'      => $meta['ambito'],
                ];
            }
        }

        return response()->json($data);
    }

    public function jugadorFootballDatabase(Request $request)
    {
        set_time_limit(0);

        $url = trim($request->url);
        if (!$url) return response()->json([]);

        $html = HttpHelper::getHtmlContent($url, false);
        if (!$html) $html = HttpHelper::getHtmlContent($url, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $jugadorId = $request->jugador_id;

        $existentes = collect()
            ->merge(
                $jugadorId
                    ? \App\JugadorEstadisticaManual::where('jugador_id', $jugadorId)->pluck('torneo_nombre')
                    : collect()
            )
            ->merge(\App\Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        // Each season row has a corresponding morecareer detail row
        $seasonRows = $xpath->query('//table[contains(@class,"firstblock")]//tr[contains(@class,"line") and not(contains(@class,"total"))]');
        $data = [];

        foreach ($seasonRows as $seasonRow) {
            $cols = $seasonRow->getElementsByTagName('td');
            if ($cols->length < 4) continue;

            $season = trim($cols->item(0)->textContent);
            //\Log::info('Season row: ' . $season . ' cols: ' . $cols->length);

            preg_match('/(\d{4})/', $season, $mYear);
            $year = $mYear[1] ?? null;
            if (!$year || (int)$year < 2000) continue;

            $clubCell = $cols->item(1);
            $flagSpan = $xpath->query('.//span[@class="real_flag"]', $clubCell)->item(0);
            $country = $flagSpan ? trim($flagSpan->getAttribute('title')) : '';
            //\Log::info('Country: ' . $country);
            //if (strtolower($country) === 'argentina') continue;

            // Find morecareer row
            $next = $seasonRow->nextSibling;
            $detailRow = null;
            while ($next) {
                if ($next->nodeType === XML_ELEMENT_NODE) {
                    $id = $next->getAttribute('id');
                    //\Log::info('Next sibling id: ' . $id);
                    if (strpos($id, 'morecareer_2_') === 0) {
                        $detailRow = $next;
                    }
                    break;
                }
                $next = $next->nextSibling;
            }
            //\Log::info('DetailRow found: ' . ($detailRow ? 'yes' : 'no'));

            if (!$detailRow) continue;



            // Parse detail table rows - each is one competition
            $detailDataRows = $xpath->query('.//table[contains(@class,"moreinformations")]//tr[td]', $detailRow);
            //\Log::info('Detail data rows count: ' . $detailDataRows->length);

            foreach ($detailDataRows as $dRow) {
                $dCols = $dRow->getElementsByTagName('td');
                //\Log::info('dRow cols: ' . $dCols->length . ' | ' . trim($dCols->item(0)->textContent ?? '') . ' | ' . trim($dCols->item(1)->textContent ?? ''));
                if ($dCols->length < 3) continue;

                $clubName  = trim($dCols->item(0)->textContent);
                $compName  = trim($dCols->item(1)->textContent);
                $pj        = (int) trim($dCols->item(2)->textContent);

                if ($pj === 0 || !$compName) continue;

                // Skip excluded competitions (segunda b, sub 20, juvenil, etc.)
                if ($this->debeExcluirCompetencia($compName)) continue;

                // NUEVO
                if ($this->debeExcluirEquipo($clubName)) continue;

                // Skip Argentina national team
                // Skip national teams - club name matches a country name
// Get country from the season row flag
                $flagSpans = $xpath->query('.//span[@class="real_flag"]', $detailRow);
// Simple approach: if club name has no spaces typical of team names
// or matches known country patterns, skip
// Better: check if clubName matches the country of any flag in the detail
                $isNationalTeam = false;
                $detailFlags = $xpath->query('.//span[@class="real_flag"]', $dRow);
// In detail rows there are no flags - use parent season row country
// Skip if clubName == country of season row
                if (strtolower(trim($clubName)) === strtolower(trim($country))) {
                    $isNationalTeam = true;
                }
                if ($isNationalTeam) continue;

                // Goles: first pc_offense2 td after PJ
                // Inicializar
                $goles = 0;
                $amarillas = 0;
                $rojas = 0;
                $golesRecibidos = 0;
                $vallasInvictas = 0;
                $propios = 0;

// pc_offense2: [0]=goles, [1]=asistencias, [2]=goles en contra propia
                $offenseCols = $xpath->query('.//td[contains(@class,"pc_offense2")]', $dRow);
                if ($offenseCols->length > 0) $goles   = (int) trim($offenseCols->item(0)->textContent);
                //if ($offenseCols->length > 2) $propios = (int) trim($offenseCols->item(2)->textContent);

// pc_defense2: [0]=(arquero, vacío para campo), [1]=goles recibidos, [2]=vallas invictas
                $defenseCols = $xpath->query('.//td[contains(@class,"pc_defense2")]', $dRow);
                if ($defenseCols->length > 0) {
                    $propios = (int) trim($defenseCols->item(0)->textContent);
                }
                if ($defenseCols->length > 1) {
                    $golesRecibidos = (int) trim($defenseCols->item(1)->textContent);
                }
                if ($defenseCols->length > 2) {
                    $vallasText = trim($defenseCols->item(2)->textContent);
                    preg_match('/(\d+)/', $vallasText, $mVI);
                    $vallasInvictas = isset($mVI[1]) ? (int) $mVI[1] : 0;
                }

// pc_discipline2: [0]=amarillas, [1]=rojas
                $disciplineCols = $xpath->query('.//td[contains(@class,"pc_discipline2")]', $dRow);
                if ($disciplineCols->length > 0) $amarillas = (int) trim($disciplineCols->item(0)->textContent);
                if ($disciplineCols->length > 1) $rojas     = (int) trim($disciplineCols->item(1)->textContent);

                $competition = $compName . ' ' . $year;
                $key = (string) \Str::of($competition)->lower()->ascii()
                    ->replaceMatches('/\s+/', ' ')->trim();

                if (isset($existentes[$key])) continue;

                // Determine tipo/ambito from competition name
                $compLower = strtolower($competition);
                // Fix 1 y 2: chequear ámbito ANTES que tipo
                $internacional = ['champions', 'libertadores', 'sudamericana', 'europa', 'concacaf',
                    'mundial', 'nations', 'copa america', 'eurocopa', 'amistosos',
                    'campeones', 'intercontinental', 'sudamericano', 'olimpico', 'olimpicos'];
                $ambito = 'Nacional';
                foreach ($internacional as $kw) {
                    if (strpos($compLower, $kw) !== false) {
                        $ambito = 'Internacional';
                        break;
                    }
                }

// Tipo depende del ámbito también
                $esLiga = (strpos($compLower, 'liga') !== false && $ambito === 'Nacional')
                    || strpos($compLower, 'mls') !== false
                    || strpos($compLower, 'premier') !== false
                    || strpos($compLower, 'bundesliga') !== false
                    || strpos($compLower, 'ligue') !== false
                    || strpos($compLower, 'serie') !== false
                    || strpos($compLower, 'primera') !== false
                    || strpos($compLower, 'division') !== false
                    || strpos($compLower, 'segunda') !== false;
                $tipo = $esLiga ? 'Liga' : 'Copa';

// Fix 3: filtrar selecciones por palabras clave en clubName
                $selecciones = ['argentina', 'brasil', 'españa', 'france', 'italia', 'alemania',
                    'germany', 'england', 'portugal', 'colombia', 'uruguay', 'chile',
                    'mexico', 'peru', 'venezuela', 'ecuador', 'paraguay', 'bolivia',
                    'estados unidos', 'usa', 'netherlands', 'holanda', 'belgica',
                    'croacia', 'suiza', 'suecia', 'dinamarca', 'noruega', 'japon',
                    'corea', 'marruecos', 'senegal', 'ghana', 'nigeria', 'camerun'];
                $clubLower = strtolower(trim($clubName));
                $esSeleccion = false;
                foreach ($selecciones as $pais) {
                    if (strpos($clubLower, $pais) !== false) { $esSeleccion = true; break; }
                }
                if ($esSeleccion) continue;

                $data[] = [
                    'competition'     => $competition,
                    'equipo'          => $clubName,
                    'posicion'        => 0,
                    'partidos'        => $pj,
                    'goles_jugada'    => $goles,
                    'goles_en_contra' => $propios,
                    'goles_recibidos' => $golesRecibidos,   // ← antes era 0
                    'vallas_invictas' => $vallasInvictas,   // ← antes era 0
                    'amarillas'       => $amarillas,
                    'rojas'           => $rojas,
                    'torneo_logo'     => null,
                    'tipo'            => $tipo,
                    'ambito'          => $ambito,
                ];
            }
        }

        return response()->json($data);
    }

    private function resolveCompetitionFromClubPage($clubUrl, $suffix)
    {
        $html = HttpHelper::getHtmlContent($clubUrl, false);
        if (!$html) $html = HttpHelper::getHtmlContent($clubUrl, true);
        if (!$html) return null;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // Keywords for international competitions (suffix=cont)
        $internacionalKw = ['champions', 'libertadores', 'sudamericana', 'europa league',
            'concacaf', 'mundial', 'intercontinental', 'club world', 'recopa',
            'copa america', 'supercopa sudamericana'];

        // Look for competition links in the season's competition list
        $compLinks = $xpath->query('//a[contains(@href,"/competicion/general/")]');

        $candidates = [];
        foreach ($compLinks as $link) {
            $name = trim($link->textContent);
            if (strlen($name) < 3) continue;

            // Normalize: strip trailing 2-3 letter codes glued to the name
            // (e.g. "Copa LibertadoresCL" -> "Copa Libertadores")
            $nameClean = preg_replace('/[A-Z]{2,4}$/', '', $name);
            $nameClean = trim($nameClean);
            if (strlen($nameClean) < 3) $nameClean = $name;

            $isInternational = false;
            $nameLower = strtolower($nameClean);
            foreach ($internacionalKw as $kw) {
                if (strpos($nameLower, $kw) !== false) {
                    $isInternational = true;
                    break;
                }
            }

            if ($suffix === 'cont' && $isInternational) {
                // Use lowercase version as key for dedup, store original casing as value
                $candidates[strtolower($nameClean)] = $nameClean;
            } elseif ($suffix === 'cup' && !$isInternational
                && stripos($nameLower, 'liga') === false
                && stripos($nameLower, 'primera') === false
                && stripos($nameLower, 'division') === false
                && stripos($nameLower, 'serie') === false
                && stripos($nameLower, 'premier') === false
                && stripos($nameLower, 'bundesliga') === false) {
                $candidates[strtolower($nameClean)] = $nameClean;
            }
        }

        //\Log::info("[RESOLVE] Suffix={$suffix} candidates=" . json_encode(array_values($candidates)));

        if (count($candidates) === 1) {
            return array_values($candidates)[0];
        }

        return null;
    }

    public function jugadorTransfermarktGoles(Request $request)
    {
        set_time_limit(0);

        $competicionBuscada = trim($request->competicion ?? '');
        $clubBuscado        = trim($request->club ?? '');
        $temporadaBuscada   = trim($request->temporada ?? '');

        $normalizar = function ($txt) {

            $txt = mb_strtolower($txt);

            $txt = iconv('UTF-8', 'ASCII//TRANSLIT', $txt);

            $txt = preg_replace('/\b(fc|cf|club|de|la|el)\b/', ' ', $txt);

            $txt = preg_replace('/[^a-z0-9 ]/', ' ', $txt);

            $txt = preg_replace('/\s+/', ' ', $txt);

            return trim($txt);
        };

        $normalizarCompeticion = function ($txt) {

            $txt = mb_strtolower($txt);

            // sacar años
            $txt = preg_replace('/\b(19|20)\d{2}\b/', '', $txt);

            $txt = trim($txt);

            $map = [

                'mls' => 'major league soccer',

                'liga bbva' => 'laliga',
                'primera division' => 'laliga',

                'UEFA champions league' => 'liga de campeones',

                'europa league' => 'uefa europa league',

                'mundial de clubes' => 'fifa club world cup',
            ];

            foreach ($map as $from => $to) {

                if (str_contains($txt, $from)) {

                    return $to;
                }
            }

            return $txt;
        };

        $url = trim($request->url);
        if (!$url) return response()->json([]);

        // Extraer slug e ID desde cualquier URL del jugador en Transfermarkt
        if (!preg_match('#transfermarkt\.[^/]+/([^/]+)/[^/]+/spieler/(\d+)#', $url, $m)) {
            return response()->json(['error' => 'URL inválida de Transfermarkt']);
        }
        $slug = $m[1];
        $id   = $m[2];

        $alleToreUrl = "https://www.transfermarkt.com.ar/{$slug}/alletore/spieler/{$id}";

        //\Log::info($alleToreUrl);

        $html = HttpHelper::getHtmlContent($alleToreUrl, false);
        if (!$html) $html = HttpHelper::getHtmlContent($alleToreUrl, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        //\Log::info($dom->saveHTML());
        // Map Transfermarkt "Tipo de gol" text -> bucket field
        $tipoMap = [
            'Remate de cabeza'                   => 'cabeza',
            'Pecho'                              => 'cabeza',
            'Penalti'                            => 'penal',
            'Rebote de penalti'                  => 'jugada',
            'Libre directo'                      => 'tiro_libre',
            'Gol directo de un saque de esquina' => 'tiro_libre',
            // El resto cae en 'jugada' por default
        ];

        // Argentina + CONMEBOL exclusion keywords (case-insensitive)
        $excluir = [
            'argentin', 'torneo apertura', 'torneo clausura', 'primera división argentina',
            'copa argentina', 'libertadores', 'sudamericana', 'recopa', 'conmebol',
            'suruga', 'copa américa', 'copa america', 'eliminatorias',
        ];

        // The goals table is the one that has rows with "Temporada XX/YY" header rows.
        // Pick all rows from that table by matching the header-row pattern.
        $allRows = $xpath->query('//div[contains(@class,"responsive-table")]//table//tbody//tr');

        $temporadaActual = null;
        $stats = [];
        //\Log::info('Rows: '.$allRows->length);
        foreach ($allRows as $row) {
            // Check if this is a "Temporada XX/YY" header row
            $tdHeader = $xpath->query('./td[@colspan and contains(@class,"hauptlink")]', $row);
            if ($tdHeader->length > 0) {
                $headerText = trim($tdHeader->item(0)->textContent);
                if (preg_match('/Temporada\s+(\d{2}\/\d{2})/i', $headerText, $mT)) {
                    $temporadaActual = $mT[1];
                    //\Log::info("[TM Goles] Header temporada: {$temporadaActual}");
                }
                continue;
            }

            if (!$temporadaActual) continue;

            $cols = $row->getElementsByTagName('td');
            if ($cols->length < 11) {
                //\Log::info("[TM Goles] Skip: cols={$cols->length} temp={$temporadaActual}");
                continue;
            }

            $yy = (int) substr($temporadaActual, 0, 2);
            $yearStart = ($yy >= 50) ? 1900 + $yy : 2000 + $yy;
            if ($yearStart < 2000) {
                //\Log::info("[TM Goles] Skip < 2000: temp={$temporadaActual} year={$yearStart}");
                continue;
            }

            $compImg = $xpath->query('.//td[1]//img', $row)->item(0);
            $competicion = $compImg ? trim($compImg->getAttribute('title')) : '';
            $competicion = preg_replace('/\s*\(-?\d{2}\/\d{2}\)\s*$/', '', $competicion);
            $competicion = trim($competicion);

            //\Log::info("[TM Goles] Fila temp={$temporadaActual} comp='{$competicion}'");

            if (!$competicion) continue;

            $compLower = mb_strtolower($competicion);
            $skip = false;
            foreach ($excluir as $kw) {
                if (mb_strpos($compLower, $kw) !== false) {
                    //\Log::info("[TM Goles] EXCLUIDO por '{$kw}': {$competicion}");
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // "Tipo de gol" is the LAST <td> in the row
            $tipoText = trim($cols->item($cols->length - 1)->textContent);
            $bucket   = $tipoMap[$tipoText] ?? 'jugada';

            // Club "Para" is the team this goal was scored for. It's the <a title="...">
            // inside the td after "Localia" (H/A). Use the first <a title> with /spielplan/verein/.
            $clubLink = $xpath->query('.//a[contains(@href,"/spielplan/verein/")]', $row)->item(0);
            $club = $clubLink ? trim($clubLink->getAttribute('title')) : '';

            // ======================
// FILTROS OPCIONALES
// ======================



            $compNorm =
                $normalizarCompeticion($normalizar($competicion));

            $compBuscadaNorm =
                $normalizarCompeticion($normalizar($competicionBuscada));

                $clubNorm = $normalizar($club);
                $clubBuscadoNorm = $normalizar($clubBuscado);


            if ($competicionBuscada) {

                $okComp =
                    str_contains($compNorm, $compBuscadaNorm)
                    || str_contains($compBuscadaNorm, $compNorm);

                if (!$okComp) {

                    //\Log::info("[TM] Skip comp '{$competicion}' vs '{$competicionBuscada}'");

                    continue;
                }
            }

            if ($clubBuscado) {

                $okClub =
                    str_contains($clubNorm, $clubBuscadoNorm)
                    || str_contains($clubBuscadoNorm, $clubNorm);

                if (!$okClub) {

                    similar_text($clubNorm, $clubBuscadoNorm, $scoreClub);

                    if ($scoreClub < 45) {

                        //\Log::info("[TM] Skip club '{$club}' vs '{$clubBuscado}'");

                        continue;
                    }
                }
            }

            if ($temporadaBuscada && $temporadaActual !== $temporadaBuscada) {

                continue;
            }


            $key = $temporadaActual . '|' . $competicion . '|' . $club;

            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'temporada'   => $temporadaActual,
                    'competicion' => $competicion,
                    'club'        => $club,
                    'total'       => 0,
                    'cabeza'      => 0,
                    'jugada'      => 0,
                    'penal'       => 0,
                    'tiro_libre'  => 0,
                ];
            }

            $stats[$key]['total']++;
            $stats[$key][$bucket]++;
        }

        // Sort by temporada desc, then competition
        $result = array_values($stats);
        usort($result, function ($a, $b) {
            $cmp = strcmp($b['temporada'], $a['temporada']);
            return $cmp !== 0 ? $cmp : strcmp($a['competicion'], $b['competicion']);
        });
        //\Log::info('[TM Goles] Stats finales: ' . json_encode($stats));
        return response()->json($result);
    }

    public function equipoTransfermarkt(Request $request)
    {
        set_time_limit(0);

        $url = trim($request->url);
        if (!$url) {
            return response()->json(['error' => 'Falta la URL de Transfermarkt']);
        }

        // Extract verein id + season from the spielplan URL.
        // e.g. .../fc-barcelona/spielplan/verein/131/plus/0?saison_id=2025
        if (!preg_match('#/verein/(\d+)#', $url, $mId)) {
            return response()->json(['error' => 'URL inválida (falta /verein/{id})']);
        }
        $vereinId = $mId[1];



        $html = HttpHelper::getHtmlContent($url, false);
        if (!$html) $html = HttpHelper::getHtmlContent($url, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // Season label from the "Elegir temporada" select (source of truth).
// TM shows two formats depending on the competition calendar:
//   - Cross-year (Europe):     value="2025", text="25/26"  -> "2025/26"
//   - Calendar-year (S. Amer): value="2000", text="2000"   -> "2000"
        $year = null;

        $optSel = $xpath->query("//select[contains(@name,'saison_id')]/option[@selected]")->item(0);
        if ($optSel) {
            $val  = (int) preg_replace('/\D/', '', $optSel->getAttribute('value'));
            $text = trim($optSel->textContent);

            if (str_contains($text, '/')) {
                // Cross-year season (European leagues): text like "25/26".
                // value is the start year (saison_id) -> "2025/26".
                if ($val >= 1900) $year = $val . '/' . substr((string) ($val + 1), -2);
            } else {
                // Calendar-year season (South American leagues): the VISIBLE text is the
                // real year ("2024"), while value/saison_id is one less ("2023"). Trust text.
                if (preg_match('/(\d{4})/', $text, $m) && (int) $m[1] >= 1900) {
                    $year = $m[1];
                }
            }
        }

// Fallback: saison_id in URL (start year only).
        if (!$year && preg_match('#saison_id[=/](\d{4})#', $url, $mY)) {
            $year = $mY[1];
        }

// Last resort: current TM season start year.
        if (!$year) {
            $year = (string) ((int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1);
        }

        // ----------------------------------------------------------------
        // 1) Locate the "Estadísticas" summary table by its "Balance total" row.
        // ----------------------------------------------------------------
        $statsTable = null;
        foreach ($xpath->query('//table') as $t) {
            if (mb_stripos($t->textContent, 'Balance total') !== false) {
                $statsTable = $t;
                break;
            }
        }

        // Debug: ?debug=1 dumps the stats-box HTML so selectors can be verified.
        if ($request->debug) {
            return response($statsTable ? $dom->saveHTML($statsTable) : 'NO STATS TABLE');
        }

        if (!$statsTable) {
            return response()->json(['error' => 'No se encontró la tabla de estadísticas']);
        }

        // ----------------------------------------------------------------
        // 2) League position from the "Clasificación" box.
        //    Match the row whose club links to /verein/{vereinId}/ and whose
        //    first cell is a plain ranking number.
        // ----------------------------------------------------------------
        $posicionLiga = null;
        foreach ($xpath->query("//a[contains(@href,'/verein/{$vereinId}/')]") as $a) {
            $tr = $a;
            while ($tr && $tr->nodeName !== 'tr') $tr = $tr->parentNode;
            if (!$tr) continue;

            $firstCell = $xpath->query('./td[1]', $tr)->item(0);
            if ($firstCell) {
                $n = (int) preg_replace('/\D/', '', $firstCell->textContent);
                if ($n > 0 && $n <= 30) { $posicionLiga = $n; break; }
            }
        }

        // ----------------------------------------------------------------
        // 3) Existing tournaments for dedup (manual + automatic Torneo rows).
        // ----------------------------------------------------------------
        $equipoId = $request->equipo_id;
        $existentes = collect()
            ->merge(
                $equipoId
                    ? EquipoEstadisticaManual::where('equipo_id', $equipoId)->pluck('torneo_nombre')
                    : collect()
            )
            ->merge(Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        // ----------------------------------------------------------------
        // 4) Parse rows. Competition headers set $currentComp; data rows
        //    (anchored on the "NN:NN" goals cell) accumulate into it.
        //    LaLiga has two rows (casa/fuera) -> summed under the same comp.
        // ----------------------------------------------------------------
        $torneos = [];
        $currentComp = null;

        $num = function ($td) {
            $t = trim($td->textContent);
            if ($t === '' || $t === '-') return 0;
            return (int) preg_replace('/\D/', '', $t);
        };

        foreach ($xpath->query('.//tr', $statsTable) as $row) {

            $rowText = trim($row->textContent);
            if ($rowText === '') continue;

            // Total section is last -> stop here.
            if (mb_stripos($rowText, 'Balance total') !== false) break;

            $cells = [];
            foreach ($row->getElementsByTagName('td') as $td) $cells[] = $td;

            // Competition link (covers /wettbewerb/ and /pokalwettbewerb/).
            $compLink = $xpath->query(".//a[contains(@href,'wettbewerb')]", $row)->item(0);

            // Find goals cell "NN:NN".
            $idxGoals = -1;
            foreach ($cells as $i => $td) {
                if (preg_match('/^\s*\d+\s*:\s*\d+\s*$/', trim($td->textContent))) {
                    $idxGoals = $i;
                    break;
                }
            }

            // No stats in this row -> it's a competition header.
            if ($idxGoals === -1) {
                if ($compLink) $currentComp = trim($compLink->textContent);
                continue;
            }

            // Data row but no comp set yet -> try same-row comp name.
            if (!$currentComp) {
                if ($compLink) $currentComp = trim($compLink->textContent);
                if (!$currentComp) continue;
            }

            $partidos  = isset($cells[$idxGoals - 5]) ? $num($cells[$idxGoals - 5]) : 0;
            $ganados   = isset($cells[$idxGoals - 4]) ? $num($cells[$idxGoals - 4]) : 0;
            $empatados = isset($cells[$idxGoals - 3]) ? $num($cells[$idxGoals - 3]) : 0;
            $perdidos  = isset($cells[$idxGoals - 2]) ? $num($cells[$idxGoals - 2]) : 0;

            list($gf, $ge) = array_map('intval', explode(':', trim($cells[$idxGoals]->textContent)));

            if (!isset($torneos[$currentComp])) {
                $torneos[$currentComp] = [
                    'partidos' => 0, 'ganados' => 0, 'empatados' => 0,
                    'perdidos' => 0, 'gf' => 0, 'ge' => 0,
                ];
            }
            $torneos[$currentComp]['partidos']  += $partidos;
            $torneos[$currentComp]['ganados']   += $ganados;
            $torneos[$currentComp]['empatados'] += $empatados;
            $torneos[$currentComp]['perdidos']  += $perdidos;
            $torneos[$currentComp]['gf']        += $gf;
            $torneos[$currentComp]['ge']        += $ge;
        }

        // ----------------------------------------------------------------
        // 5) Build payload (same shape the front-end cards expect).
        // ----------------------------------------------------------------
        $data = [];
        foreach ($torneos as $compName => $s) {

            if ($this->debeExcluirCompetencia($compName)) continue;

            list($tipo, $ambito) = $this->clasificarCompetencia($compName);

            $competition = trim($compName) . ' ' . $year;
            $key = (string) \Str::of($competition)->lower()->ascii()
                ->replaceMatches('/\s+/', ' ')->trim();

            if (isset($existentes[$key])) continue;

            $data[] = [
                'competition' => $competition,
                'posicion'    => $tipo === 'Liga' ? $posicionLiga : null,
                'partidos'    => $s['partidos'],
                'ganados'     => $s['ganados'],
                'empatados'   => $s['empatados'],
                'perdidos'    => $s['perdidos'],
                'gf'          => $s['gf'],
                'ge'          => $s['ge'],
                'torneo_logo' => null,
                'tipo'        => $tipo,
                'ambito'      => $ambito,
            ];
        }

        return response()->json($data);
    }

// Guess tipo/ambito from the competition name (same spirit as the dts scraper).
    private function clasificarCompetencia($nombre)
    {
        $n = (string) \Str::of($nombre)->lower()->ascii();

        // International cups first.
        $intl = ['champions', 'libertadores', 'sudamericana', 'europa', 'concacaf',
            'mundial', 'intercontinental', 'recopa', 'club world', 'supercopa de europa',
            'afc', 'caf', 'asian'];
        foreach ($intl as $kw) {
            if (str_contains($n, $kw)) return ['Copa', 'Internacional'];
        }

        // Cups (explicit) -> Copa, before the generic league check.
        $copaKw = ['copa', 'cup', 'pokal', 'coupe', 'supercopa', 'super cup', 'trophy', 'shield', 'playoff'];
        foreach ($copaKw as $kw) {
            if (str_contains($n, $kw)) return ['Copa', 'Nacional'];
        }

        // Leagues: explicit names + generic "league"/"liga"/"division".
        $ligaKw = ['laliga', 'la liga', 'liga', 'league', 'primera', 'segunda', 'serie a', 'serie b',
            'premier', 'bundesliga', 'ligue', 'eredivisie', 'mls', 'primeira',
            'brasileiro', 'brasileir', 'division', 'división', 'championship', 'ekstraklasa'];
        foreach ($ligaKw as $kw) {
            if (str_contains($n, $kw)) return ['Liga', 'Nacional'];
        }

        return ['Copa', 'Nacional']; // default
    }

    public function tecnicoTransfermarkt(Request $request)
    {
        set_time_limit(0);

        $url = trim($request->url);
        if (!$url) return response()->json(['error' => 'Falta la URL de Transfermarkt']);
        if (!preg_match('#/trainer/(\d+)#', $url, $mId)) {
            return response()->json(['error' => 'URL inválida (falta /trainer/{id})']);
        }

        $html = HttpHelper::getHtmlContent($url, false);
        if (!$html) $html = HttpHelper::getHtmlContent($url, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // ---- Season from the "Elegir temporada" select (same as equipos) ----
        $year = null;
        $optSel = $xpath->query("//select[contains(@name,'saison_id')]/option[@selected]")->item(0);
        if ($optSel) {
            $val  = (int) preg_replace('/\D/', '', $optSel->getAttribute('value'));
            $text = trim($optSel->textContent);
            if (str_contains($text, '/')) {
                if ($val >= 1900) $year = $val . '/' . substr((string) ($val + 1), -2);
            } else {
                if (preg_match('/(\d{4})/', $text, $m2) && (int) $m2[1] >= 1900) $year = $m2[1];
            }
        }
        if (!$year && preg_match('#saison_id[=/](\d{4})#', $url, $mY)) $year = $mY[1];
        if (!$year) $year = (string) ((int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1);

        // ---- Matches table (date + NN:NN result rows) ----
        $statsTable = null; $bestGoals = 0;
        foreach ($xpath->query('//table[contains(@class,"items")]') as $t) {
            $g = 0;
            foreach ($xpath->query('.//td', $t) as $td) {
                if (preg_match('/^\s*\d+\s*:\s*\d+\s*$/', trim($td->textContent))) $g++;
            }
            if ($g > $bestGoals) { $bestGoals = $g; $statsTable = $t; }
        }

        if ($request->debug) {
            $rows = $xpath->query('.//tbody/tr', $statsTable);
            $out = ''; $c = 0;
            foreach ($rows as $r) { $out .= $dom->saveHTML($r) . "\n\n----\n\n"; if (++$c >= 2) break; }
            return response('<pre>' . htmlspecialchars($out) . '</pre>');
        }

        if (!$statsTable) return response()->json(['error' => 'No se encontró la tabla de partidos']);

        // ---- Pass 1: collect raw matches + count team frequency to find the DT's club ----
        $partidosRaw = [];
        $freq = [];
        $nombres = []; // norm-key -> display name

        foreach ($xpath->query('.//tbody/tr', $statsTable) as $row) {

            // Result cell "NN:NN" (inside the ergebnis-link span).
            $resStr = null;
            foreach ($row->getElementsByTagName('td') as $td) {
                $tt = trim($td->textContent);
                if (preg_match('/^\s*\d+\s*:\s*\d+\s*$/', $tt)) { $resStr = $tt; break; }
            }
            if (!$resStr) continue;

            // Competition: prefer the icon with class "wappen-position-grid-view"
            // (the competition crest); team crests use "tiny_wappen". This is
            // robust for continental cups whose <a> doesn't carry /wettbewerb/.
            $comp = '';
            $compImg = $xpath->query('.//img[contains(@class,"wappen-position-grid-view")]', $row)->item(0);
            if (!$compImg) {
                $compImg = $xpath->query('.//img[@title]', $row)->item(0); // fallback: first titled img
            }
            if ($compImg) $comp = trim($compImg->getAttribute('title'));
            $comp = preg_replace('/\s*\(-?\d{2}\/\d{2}\)\s*$/', '', $comp);
            $comp = trim($comp);

            // Teams: the two <a> that link to /verein/ AND have text (not the crest-only anchors).
            $home = $away = '';
            foreach ($xpath->query(".//a[contains(@href,'/verein/')]", $row) as $a) {
                $txt = trim($a->textContent);
                if ($txt === '') continue;
                if ($home === '') { $home = $txt; }
                elseif ($away === '') { $away = $txt; break; }
            }
            if (!$home || !$away) continue;

            list($hg, $ag) = array_map('intval', explode(':', $resStr));
            $partidosRaw[] = compact('comp', 'home', 'away', 'hg', 'ag');

            $hk = $this->normalizeTeam($home);
            $ak = $this->normalizeTeam($away);
            $freq[$hk] = ($freq[$hk] ?? 0) + 1;
            $freq[$ak] = ($freq[$ak] ?? 0) + 1;
            $nombres[$hk] = $home;
            $nombres[$ak] = $away;
        }

        if (empty($partidosRaw)) return response()->json([]);

        // The directed club = most frequent team across all matches this season.
        $bestKey = null; $bestCount = 0;
        foreach ($freq as $k => $v) {
            if ($v > $bestCount) { $bestCount = $v; $bestKey = $k; }
        }
        $equipoDir = $nombres[$bestKey] ?? '';
        $dirNorm = $bestKey;

        // ---- Pass 2: aggregate by competition from the DT's perspective ----
        $stats = [];
        foreach ($partidosRaw as $p) {
            $homeNorm = $this->normalizeTeam($p['home']);
            $awayNorm = $this->normalizeTeam($p['away']);

            if ($homeNorm === $dirNorm)      { $gf = $p['hg']; $ge = $p['ag']; }
            elseif ($awayNorm === $dirNorm)  { $gf = $p['ag']; $ge = $p['hg']; }
            else continue; // match where the directed club didn't play (shouldn't happen)

            $res = $gf > $ge ? 'W' : ($gf < $ge ? 'L' : 'D');
            $comp = $p['comp'] ?: 'Sin competencia';
            $key = $comp;

            if (!isset($stats[$key])) {
                $stats[$key] = [
                    'competition' => $comp, 'equipo' => $equipoDir,
                    'partidos' => 0, 'ganados' => 0, 'empatados' => 0,
                    'perdidos' => 0, 'gf' => 0, 'ge' => 0,
                ];
            }
            $stats[$key]['partidos']++;
            $stats[$key]['ganados']   += $res === 'W' ? 1 : 0;
            $stats[$key]['empatados'] += $res === 'D' ? 1 : 0;
            $stats[$key]['perdidos']  += $res === 'L' ? 1 : 0;
            $stats[$key]['gf'] += $gf;
            $stats[$key]['ge'] += $ge;
        }

        // ---- Dedup + classify + payload (footballdb shape) ----
        $tecnicoId = $request->tecnico_id;
        $existentes = collect()
            ->merge($tecnicoId ? \App\TecnicoEstadisticaManual::where('tecnico_id', $tecnicoId)->pluck('torneo_nombre') : collect())
            ->merge(\App\Torneo::all()->map(function ($t) { return ($t->nombre ?? '') . ' ' . ($t->year ?? ''); }))
            ->filter()
            ->map(function ($v) { return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim(); })
            ->unique()->flip()->toArray();

        $data = [];
        foreach ($stats as $s) {
            if ($this->debeExcluirCompetencia($s['competition'])) continue;

            list($tipo, $ambito) = $this->clasificarCompetencia($s['competition']);

            $competition = trim($s['competition']) . ' ' . $year;
            $key = (string) \Str::of($competition)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            if (isset($existentes[$key])) continue;

            $data[] = [
                'competition' => $competition,
                'equipo'      => $s['equipo'],
                'posicion'    => null,
                'partidos'    => $s['partidos'],
                'ganados'     => $s['ganados'],
                'empatados'   => $s['empatados'],
                'perdidos'    => $s['perdidos'],
                'gf'          => $s['gf'],
                'ge'          => $s['ge'],
                'torneo_logo' => null,
                'tipo'        => $tipo,
                'ambito'      => $ambito,
            ];
        }

        return response()->json($data);
    }

// "23/24" -> "2023/24" (cross-year) ; "2024" -> "2024" (calendar-year)
    private function normalizarTemporadaTM($raw)
    {
        $raw = trim($raw);
        if (preg_match('#(\d{2})/(\d{2})#', $raw, $m)) {
            $start = ((int) $m[1] >= 50 ? 1900 : 2000) + (int) $m[1];
            return $start . '/' . $m[2];
        }
        if (preg_match('/(\d{4})/', $raw, $m)) return $m[1];
        return $raw;
    }

    public function jugadorTransfermarkt(Request $request)
    {
        set_time_limit(0);

        $url = trim($request->url);
        if (!$url) return response()->json(['error' => 'Falta la URL de Transfermarkt']);
        if (!preg_match('#/spieler/(\d+)#', $url, $mId)) {
            return response()->json(['error' => 'URL inválida (falta /spieler/{id})']);
        }

        $html = HttpHelper::getHtmlContent($url, false);
        if (!$html) $html = HttpHelper::getHtmlContent($url, true);
        if (!$html) return response()->json(['error' => 'No se pudo obtener la página']);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        // ---- Season from the "Elegir temporada" select (same logic as dts/equipos) ----
        $year = null;
        $optSel = $xpath->query("//select[contains(@name,'saison')]/option[@selected]")->item(0);
        if ($optSel) {
            $val  = (int) preg_replace('/\D/', '', $optSel->getAttribute('value'));
            $text = trim($optSel->textContent);
            if (str_contains($text, '/')) {
                if ($val >= 1900) $year = $val . '/' . substr((string) ($val + 1), -2);
            } else {
                if (preg_match('/(\d{4})/', $text, $m2) && (int) $m2[1] >= 1900) $year = $m2[1];
            }
        }
        if (!$year && preg_match('#saison[_=/]+(\d{4})#', $url, $mY)) $year = $mY[1];
        if (!$year) $year = (string) ((int) date('n') >= 7 ? (int) date('Y') : (int) date('Y') - 1);

        // ---- Stats table (the "items" one with most data rows) ----
        $statsTable = $xpath->query('//table[contains(@class,"items")]')->item(0);

        // Debug: dump the season + the first 2 data rows (escaped) to inspect columns.
        if ($request->debug) {
            $selVal  = $optSel ? $optSel->getAttribute('value') : '(sin selected)';
            $selText = $optSel ? trim($optSel->textContent) : '';
            $out = "AÑO: {$year}\nOPTION value='{$selVal}' text='{$selText}'\n\n";
            if ($statsTable) {
                $rows = $xpath->query('.//tbody/tr', $statsTable);
                $c = 0;
                foreach ($rows as $r) { $out .= $dom->saveHTML($r) . "\n\n----\n\n"; if (++$c >= 3) break; }
            } else {
                $out .= 'NO STATS TABLE';
            }
            return response('<pre>' . htmlspecialchars($out) . '</pre>');
        }

        return response()->json(['todo' => 'parser pendiente del debug']);
    }

}
