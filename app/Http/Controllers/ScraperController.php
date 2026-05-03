<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HttpHelper;
use Illuminate\Support\Facades\Log;
use App\Equipo;
use App\Torneo;
use App\EquipoEstadisticaManual;
use Illuminate\Support\Str;

class ScraperController extends Controller
{
    public function test()
    {
        $nombre = "Julio Cesar Falcioni";

        $url = "https://www.transfermarkt.com/schnellsuche/ergebnis/schnellsuche?query=" . urlencode($nombre);

        // 🔥 Fallback inteligente
        $html = HttpHelper::getHtmlContent($url, false);

        if (!$html) {
            $html = HttpHelper::getHtmlContent($url, true);
        }

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
            $htmlPerfil = HttpHelper::getHtmlContent($perfilUrl, true);
        }

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
            $htmlHistorial = HttpHelper::getHtmlContent($historialUrl, true);
        }

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

        if (!$html) {
            $html = HttpHelper::getHtmlContent($url, true);
        }
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
                if (!str_contains($resultStr, ':')) continue; // ignorar si no hay resultado
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
                    Log::info($teamNameNorm.' -> '.$homeTeamNorm.' -> '.$awayTeamNorm.' -> '.$homeGoals.' -> '.$awayGoals);
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
                if (!isset($existentes[$key])) {

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

        $existentes = collect()
            ->merge(\App\TecnicoEstadisticaManual::pluck('torneo_nombre'))
            ->merge(
                \App\Torneo::all()->map(function ($t) {
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
            ->unique()
            ->flip()
            ->toArray();

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

        $existentes = collect()
            ->merge(\App\TecnicoEstadisticaManual::pluck('torneo_nombre'))
            ->merge(\App\Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        $rows = $xpath->query('//tr[contains(@class,"line") and not(contains(@class,"total"))]');
        $data = [];

        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            if ($cols->length < 4) continue;

            $season = trim($cols->item(0)->textContent);
            preg_match('/(\d{4})/', $season, $mYear);
            $year = $mYear[1] ?? null;
            if (!$year || (int)$year < 2000) continue;

            // Club + country
            $clubCell = $cols->item(1);
            $flagSpan = $xpath->query('.//span[@class="real_flag"]', $clubCell)->item(0);
            $country = $flagSpan ? trim($flagSpan->getAttribute('title')) : '';
            if (strtolower($country) === 'argentina') continue;

            $clubLink = $xpath->query('.//a', $clubCell)->item(0);
            $club = $clubLink ? trim($clubLink->textContent) : trim($clubCell->textContent);

            // Tipos de competencia: champ=Liga, cont=Internacional, cup=Copa
            $competencias = [
                'champ' => ['tipo' => 'Liga',  'ambito' => 'Nacional'],
                'cont'  => ['tipo' => 'Copa',  'ambito' => 'Internacional'],
                'cup'   => ['tipo' => 'Copa',  'ambito' => 'Nacional'],
            ];

            foreach ($competencias as $suffix => $meta) {

                $pj = 0; $v = 0; $e = 0; $d = 0; $gf = 0; $gc = 0;
                $compName = null;

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
                    // Competition name from lastrounds
                    if (str_contains($class, 'pc_lastrounds1') && str_contains($class, $suffix) && !$compName) {
                        $spans = $xpath->query('.//span[@class="competition"]', $col);
                        if ($spans->length > 0) {
                            $compName = trim($spans->item(0)->textContent);
                        }
                    }
                }

                if ($pj === 0) continue;

                // Fallback: get name from champ link for liga
                if (!$compName && $suffix === 'champ') {
                    $compLink = $xpath->query('.//td[@class="champ"]/a', $row)->item(0);
                    if ($compLink) {
                        $href = $compLink->getAttribute('href');
                        preg_match('/\/\d+-([^\/]+)\//', $href, $mComp);
                        if (isset($mComp[1])) {
                            $compName = ucwords(str_replace('_', ' ', $mComp[1]));
                        }
                    }
                }

                if (!$compName) $compName = $meta['tipo'];

                $competition = $compName . ' ' . $year;

                $key = (string) \Str::of($competition)->lower()->ascii()
                    ->replaceMatches('/\s+/', ' ')->trim();

                if (isset($existentes[$key])) continue;

                $data[] = [
                    'competition' => $competition,
                    'equipo'      => $club,
                    'posicion'    => 0,
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

        $existentes = collect()
            ->merge(\App\JugadorEstadisticaManual::pluck('torneo_nombre'))
            ->merge(\App\Torneo::all()->map(function ($t) {
                return ($t->nombre ?? '') . ' ' . ($t->year ?? '');
            }))
            ->filter()
            ->map(function ($v) {
                return (string) \Str::of($v)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
            })
            ->unique()->flip()->toArray();

        // Each season row has a corresponding morecareer detail row
        $seasonRows = $xpath->query('//tr[contains(@class,"line") and not(contains(@class,"total"))]');
        $data = [];

        foreach ($seasonRows as $seasonRow) {
            $cols = $seasonRow->getElementsByTagName('td');
            if ($cols->length < 4) continue;

            // Get year
            $season = trim($cols->item(0)->textContent);
            preg_match('/(\d{4})/', $season, $mYear);
            $year = $mYear[1] ?? null;
            if (!$year || (int)$year < 2000) continue;

            // Skip Argentine clubs
            $clubCell = $cols->item(1);
            $flagSpan = $xpath->query('.//span[@class="real_flag"]', $clubCell)->item(0);
            $country = $flagSpan ? trim($flagSpan->getAttribute('title')) : '';
            if (strtolower($country) === 'argentina') continue;

            // Find the morecareer row that follows this season row
            // It has id="morecareer_2_N" — find via nextSibling
            $detailRow = null;
            $next = $seasonRow->nextSibling;
            while ($next) {
                if ($next->nodeType === XML_ELEMENT_NODE) {
                    $id = $next->getAttribute('id');
                    if (strpos($id, 'morecareer_2_') === 0) {
                        $detailRow = $next;
                    }
                    break;
                }
                $next = $next->nextSibling;
            }

            if (!$detailRow) continue;

            // Parse detail table rows - each is one competition
            $detailDataRows = $xpath->query('.//table[contains(@class,"moreinformations")]/tbody/tr[not(th)]', $detailRow);

            foreach ($detailDataRows as $dRow) {
                $dCols = $dRow->getElementsByTagName('td');
                if ($dCols->length < 3) continue;

                $clubName  = trim($dCols->item(0)->textContent);
                $compName  = trim($dCols->item(1)->textContent);
                $pj        = (int) trim($dCols->item(2)->textContent);

                if ($pj === 0 || !$compName) continue;

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
                $goles = 0;
                $amarillas = 0;
                $rojas = 0;
                $golesRecibidos = 0;
                $vallasInvictas = 0;
                $propios = 0;

                $offenseCols = $xpath->query('.//td[contains(@class,"pc_offense2")]', $dRow);
                if ($offenseCols->length > 0) {
                    $goles = (int) trim($offenseCols->item(0)->textContent);
                }

                $defenseCols = $xpath->query('.//td[contains(@class,"pc_defense2")]', $dRow);
                // order: own_goals, goals_conceded, cleansheets
                if ($defenseCols->length > 0) $propios        = (int) trim($defenseCols->item(0)->textContent);
                if ($defenseCols->length > 1) $golesRecibidos = (int) trim($defenseCols->item(1)->textContent);
                if ($defenseCols->length > 2) $vallasInvictas = (int) trim($defenseCols->item(2)->textContent);

                $disciplineCols = $xpath->query('.//td[contains(@class,"pc_discipline2")]', $dRow);
                if ($disciplineCols->length > 0) $amarillas = (int) trim($disciplineCols->item(0)->textContent);
                if ($disciplineCols->length > 1) $rojas     = (int) trim($disciplineCols->item(1)->textContent);

                $competition = $compName . ' ' . $year;
                $key = (string) \Str::of($competition)->lower()->ascii()
                    ->replaceMatches('/\s+/', ' ')->trim();

                if (isset($existentes[$key])) continue;

                // Determine tipo/ambito from competition name
                $compLower = strtolower($competition);
                $internacional = ['champions', 'libertadores', 'sudamericana', 'europa', 'concacaf',
                    'mundial', 'nations', 'copa america', 'eurocopa', 'amistosos'];
                $ambito = 'Nacional';
                foreach ($internacional as $kw) {
                    if (strpos($compLower, $kw) !== false) {
                        $ambito = 'Internacional';
                        break;
                    }
                }
                $tipo = str_contains($compLower, 'liga') || str_contains($compLower, 'mls')
                || str_contains($compLower, 'premier') || str_contains($compLower, 'bundesliga')
                || str_contains($compLower, 'ligue') || str_contains($compLower, 'serie')
                    ? 'Liga' : 'Copa';

                $data[] = [
                    'competition'     => $competition,
                    'equipo'          => $clubName,
                    'posicion'        => 0,
                    'partidos'        => $pj,
                    'goles_jugada'    => $goles,
                    'goles_en_contra' => $propios,
                    'goles_recibidos' => $golesRecibidos,
                    'vallas_invictas' => $vallasInvictas,
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

}
