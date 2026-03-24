<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\HttpHelper;
use Illuminate\Support\Facades\Log;

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
            $tecnico->nombre . ' ' . $tecnico->apellido,
            $tecnico->apellido,
            $tecnico->nombre,
            $tecnico->name,
        ];

        foreach ($variantes as $nombre) {
            $data = $this->scrapearTecnico($nombre);

            if (!empty($data)) {
                return response()->json($data);
            }
        }

        return response()->json([]);
    }
}
