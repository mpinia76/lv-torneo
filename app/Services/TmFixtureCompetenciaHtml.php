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
     *    'visita_tm', 'visita_nombre', 'resultado', 'ronda']
     * o null si no se pudo traer la página.
     *
     * `ronda` es el nombre de la jornada («Segunda ronda», «Octavos de final»,
     * «1ª jornada»). En el calendario va como una fila de encabezado sola, y
     * hace falta: sin ella el importador de fixture no puede agrupar por fecha
     * ni aplicar de a una. Queda null si TM cambió el maquetado — mejor null
     * que un número inventado.
     */
    public function leerComp($compId, $season, $copa = false, $guardarCrudo = false, $pais = null)
    {
        $this->avisos      = [];
        $this->descartadas = 0;
        $this->crudo       = '';

        $url = self::urlComp($compId, $season, $copa);

            // `getHtmlTm` es nuevo. Si el deploy subió los servicios pero no
            // HttpHelper, llamarlo tira «Call to undefined method» y la pantalla
            // se cae con un 500 pelado. Se cae con red: se usa el método viejo y
            // se avisa, que es mucho más fácil de diagnosticar que una pantalla
            // en blanco.
        if (method_exists(HttpHelper::class, 'getHtmlTm')) {
            $html = HttpHelper::getHtmlTm($url, $pais);
        } else {
            $this->avisos[] = 'Este servidor todavía tiene la versión vieja de HttpHelper: no puedo elegir el '
                . 'país de salida. Falta subir app/Services/HttpHelper.php.';
            $html = HttpHelper::getHtmlContent($url);
        }

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

        // A qué ronda pertenece cada fila de partido. Se resuelve en una pasada
        // por el documento ANTES de leer los partidos, porque la ronda es una
        // fila aparte que precede al grupo y desde el link del partido no hay
        // forma de mirar "hacia arriba" sin recorrer hermanos a mano.
        $rondaDe = $this->rondasPorFila($xp);

        if (!$links || $links->length === 0) {
            $this->avisos[] = 'La página no tiene ningún link a una ficha de partido. Puede ser que la '
                . 'competencia sea del otro tipo —las copas van por otra ruta que las ligas—, que el id o la '
                . 'temporada estén mal, o que Transfermarkt haya cambiado el maquetado.';
            return [];
        }

        $filas = [];

        // LA FECHA SE ESCRIBE UNA SOLA VEZ POR DÍA. En el calendario de TM, el
        // primer partido de una fecha trae la celda con el día y los que siguen
        // ese mismo día la traen VACÍA. Leyendo cada fila por separado se
        // descartaban dos de cada tres partidos: 74 leídos y 166 descartados de
        // 240. Se arrastra el último día visto, que es lo que hace el ojo al
        // mirar la tabla.
        $ultimoDia = null;

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

            $fila = $this->leerFilaComp($xp, $tr, $ultimoDia);

            if ($fila === null) {
                $this->descartadas++;
                continue;
            }

            $ultimoDia = $fila['dia_crudo'];

            $clave = spl_object_hash($tr);
            $fila['ronda']      = isset($rondaDe[$clave]) ? $rondaDe[$clave] : null;
            $fila['resultado']  = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            $filas[$gameId]     = ['game_id' => $gameId] + $fila;
        }

        return $this->convertirFechas(array_values($filas));
    }

    /**
     * A qué ronda pertenece cada `<tr>` de partido.
     *
     * En el calendario la jornada es una fila SOLA, sin clubes y sin link a
     * ninguna ficha, que encabeza al grupo: «Segunda ronda», «Octavos de
     * final», «1ª jornada». Se recorren todas las filas en orden y se arrastra
     * la última vista, igual que con el día.
     *
     * El reconocimiento va por descarte —una fila sin link a partido y sin link
     * a club, con texto corto— y no por clase de CSS a propósito: el maquetado
     * de TM cambia y las clases se renombran, pero "la fila que no tiene ni
     * partido ni clubes" se sigue cumpliendo.
     *
     * Devuelve spl_object_hash($tr) => nombre de la ronda.
     */
    private function rondasPorFila(\DOMXPath $xp)
    {
        $mapa  = [];
        $filas = $xp->query('//tr');
        if (!$filas) return $mapa;

        $actual = null;

        foreach ($filas as $tr) {
            $tienePartido = $xp->query('.//a[contains(@href, "/spielbericht/")]', $tr)->length > 0;

            if ($tienePartido) {
                if ($actual !== null) $mapa[spl_object_hash($tr)] = $actual;
                continue;
            }

            if ($xp->query('.//a[contains(@href, "/verein/")]', $tr)->length > 0) continue;

            $txt = trim(preg_replace('/\s+/u', ' ', $tr->textContent));

            // Ni vacía (separadores), ni larga (cabeceras de tabla con todos los
            // títulos de columna juntos), ni una fecha suelta.
            if ($txt === '' || mb_strlen($txt) > 60) continue;
            if (preg_match('#\d{1,2}/\d{1,2}/\d{2,4}#', $txt)) continue;

            $actual = $txt;
        }

        return $mapa;
    }

    /**
     * Una fila del calendario del torneo.
     *
     * Acá no hay "nuestro club" ni columna de localía: los dos clubes salen del
     * orden en que aparecen, que es local y después visitante. El escudo linkea
     * al mismo club con el texto vacío, así que se junta por id y se le pone el
     * primer nombre no vacío.
     */
    private function leerFilaComp(\DOMXPath $xp, \DOMNode $tr, $ultimoDia = null)
    {
        $diaCrudo = null;

        foreach ($xp->query('.//td', $tr) as $td) {
            $txt = trim(preg_replace('/\s+/u', ' ', $td->textContent));

            if (preg_match('#\b(\d{1,2})/(\d{1,2})/(\d{2,4})\b#', $txt, $m)) {
                $diaCrudo = $m[0];
                break;
            }
        }

        // Sin celda de fecha, es un partido del mismo día que el anterior.
        if ($diaCrudo === null) {
            $diaCrudo = $ultimoDia;
        }

        if ($diaCrudo === null) {
            $this->sinFecha++;
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
            $this->sinClubes++;
            return null;
        }

        return [
            'dia_crudo'     => $diaCrudo,
            'dia'           => null,
            'ronda'         => null,
            'local_tm'      => $orden[0],
            'local_nombre'  => $clubes[$orden[0]],
            'visita_tm'     => $orden[1],
            'visita_nombre' => $clubes[$orden[1]],
            'resultado'     => null,
        ];
    }
}
