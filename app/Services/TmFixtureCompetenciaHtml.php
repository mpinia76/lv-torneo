<?php

namespace App\Services;

/**
 * El calendario completo de una COMPETENCIA en una temporada, del HTML de TM.
 *
 * Hermano de `TmFixtureClubHtml`: misma técnica y las mismas trampas (de ahí la
 * herencia, sobre todo por la detección del formato de fecha), pero una llamada
 * trae **el torneo entero** en vez de los partidos de un club.
 *
 * Es la diferencia entre 1 crédito y 32 cuando el torneo es un Mundial de
 * Clubes: verificado el 2026-09-02, `pokalwettbewerb/KLUB` temporada 2024
 * devuelve los **63 partidos** con su gameId y los dos clubes con id y nombre.
 * Y como trae los nombres, la misma pasada sirve para aprender los mapeos de
 * `equipo_tm` de clubes que nunca vimos, que es justamente lo que falta cuando
 * el torneo es internacional.
 *
 * **OJO CON LAS COPAS.** En TM las ligas van por `/wettbewerb/` y las copas por
 * `/pokalwettbewerb/`: Torneo Clausura es `wettbewerb/ARGC`, pero Copa
 * Argentina es `pokalwettbewerb/ARCA`, Libertadores `CLI` y Mundial de Clubes
 * `KLUB`. Es una ruta distinta, no un parámetro: hay que saber cuál es antes de
 * pedirla.
 */
class TmFixtureCompetenciaHtml extends TmFixtureClubHtml
{
    const BASE_LIGA = 'https://www.transfermarkt.es/-/gesamtspielplan/wettbewerb/';
    const BASE_COPA = 'https://www.transfermarkt.es/-/gesamtspielplan/pokalwettbewerb/';

    public static function urlComp($compId, $season, $copa = false)
    {
        return ($copa ? self::BASE_COPA : self::BASE_LIGA)
            . rawurlencode((string) $compId) . '/saison_id/' . rawurlencode((string) $season);
    }

    /**
     * Los partidos del torneo.
     *
     * Devuelve una lista de:
     *   ['game_id', 'dia' => 'Y-m-d', 'dia_crudo', 'local_tm', 'local_nombre',
     *    'visita_tm', 'visita_nombre', 'resultado']
     * o null si no se pudo traer la página.
     */
    public function leerComp($compId, $season, $copa = false, $guardarCrudo = false, $pais = null)
    {
        $this->avisos      = [];
        $this->descartadas = 0;
        $this->crudo       = '';

        $url  = self::urlComp($compId, $season, $copa);
        $html = HttpHelper::getHtmlTm($url, $pais);

        if (!$html) {
            $this->avisos[] = 'No pude traer ' . $url;
            return null;
        }

        if ($guardarCrudo) {
            $this->crudo = $html;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xp    = new \DOMXPath($dom);
        $links = $xp->query('//a[contains(@href, "/spielbericht/")]');

        if (!$links || $links->length === 0) {
            $this->avisos[] = 'La página no tiene ningún link a una ficha de partido. Puede ser que la '
                . 'competencia sea del otro tipo —las copas van por otra ruta que las ligas—, que el id o la '
                . 'temporada estén mal, o que Transfermarkt haya cambiado el maquetado.';
            return [];
        }

        $filas = [];

        foreach ($links as $a) {
            if (!preg_match('#/spielbericht/(?:index/spielbericht/)?(\d{4,})#', $a->getAttribute('href'), $m)) {
                continue;
            }

            $gameId = $m[1];

            if (isset($filas[$gameId])) {
                continue;
            }

            $tr = $a;
            while ($tr !== null && strtolower($tr->nodeName) !== 'tr') {
                $tr = $tr->parentNode;
            }

            if ($tr === null) {
                $this->descartadas++;
                continue;
            }

            $fila = $this->leerFilaComp($xp, $tr);

            if ($fila === null) {
                $this->descartadas++;
                continue;
            }

            $fila['resultado']  = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            $filas[$gameId]     = ['game_id' => $gameId] + $fila;
        }

        return $this->convertirFechas(array_values($filas));
    }

    /**
     * Una fila del calendario del torneo.
     *
     * Acá no hay "nuestro club" ni columna de localía: los dos clubes salen del
     * orden en que aparecen, que es local y después visitante. El escudo linkea
     * al mismo club con el texto vacío, así que se junta por id y se le pone el
     * primer nombre no vacío.
     */
    private function leerFilaComp(\DOMXPath $xp, \DOMNode $tr)
    {
        $diaCrudo = null;

        foreach ($xp->query('.//td', $tr) as $td) {
            $txt = trim(preg_replace('/\s+/u', ' ', $td->textContent));

            if (preg_match('#\b(\d{1,2})/(\d{1,2})/(\d{2,4})\b#', $txt, $m)) {
                $diaCrudo = $m[0];
                break;
            }
        }

        if ($diaCrudo === null) {
            return null;
        }

        $clubes = [];
        $orden  = [];

        foreach ($xp->query('.//a[contains(@href, "/verein/")]', $tr) as $link) {
            if (!preg_match('#/verein/(\d+)#', $link->getAttribute('href'), $m)) {
                continue;
            }

            $id     = $m[1];
            $nombre = trim(preg_replace('/\s+/u', ' ', $link->textContent));

            if (!array_key_exists($id, $clubes)) {
                $clubes[$id] = $nombre;
                $orden[]     = $id;
            } elseif ($clubes[$id] === '' && $nombre !== '') {
                $clubes[$id] = $nombre;
            }
        }

        // Sin los dos clubes la fila no sirve para aparear: no se inventa.
        if (count($orden) < 2) {
            return null;
        }

        return [
            'dia_crudo'     => $diaCrudo,
            'dia'           => null,
            'local_tm'      => $orden[0],
            'local_nombre'  => $clubes[$orden[0]],
            'visita_tm'     => $orden[1],
            'visita_nombre' => $clubes[$orden[1]],
            'resultado'     => null,
        ];
    }
}
