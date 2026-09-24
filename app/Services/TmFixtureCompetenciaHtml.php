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
            // gameId de CUALQUIER largo: los partidos viejos de TM tienen ids
            // de 3 dígitos (Rosenborg-Estrella Roja, Copa UEFA 03/04 = 227). Con
            // \d{4,} se perdía casi toda la 1ª ronda y la ida de la 2ª.
            if (!preg_match('#/spielbericht/(?:index/spielbericht/)?(\d+)#', $a->getAttribute('href'), $m)) {
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

            $clave = $tr->getNodePath();
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
     * EN LAS COPAS LA RONDA PUEDE NO SER UNA FILA sino el título de la caja
     * (cada ronda, una caja con su título arriba de la tabla y ninguna fila
     * de encabezado adentro). Mirando sólo `<tr>`, los 205 partidos de la
     * Copa UEFA 2000/01 (`pokalwettbewerb/UEFA`, temporada 2000) quedaron
     * todos en «—» y «Aplicar» los mandaba a una sola fecha. [2026-09-23:
     * el maquetado de las cajas se supuso, no se pudo mirar el HTML crudo;
     * si con esto sigue saliendo «—», abrir la página y ver dónde está el
     * nombre de la ronda.]
     * Por eso se recorren, en orden de documento, las filas Y los títulos
     * (h2/h3 y `.content-box-headline`); una fila de encabezado dentro de la
     * tabla, si la hay, pisa al título porque viene después.
     *
     * Un título que no sirve como nombre (largo, o vacío después de sacarle
     * la fecha) CORTA la ronda en vez de ignorarse: si no, los partidos de esa
     * caja heredarían el nombre de la caja anterior, que es peor que «—».
     *
     * Devuelve $tr->getNodePath() => nombre de la ronda. NO spl_object_hash():
     * los objetos DOM de PHP se crean y se liberan en cada consulta, así que el
     * hash de una fila en esta pasada no es el de la misma fila en leerComp()
     * —y puede ser el de otra—. El path es del nodo, no del objeto.
     */
    private function rondasPorFila(\DOMXPath $xp)
    {
        $mapa  = [];
        $filas = $xp->query('//tr | //h2 | //h3 | //*[contains(concat(" ", normalize-space(@class), " "), " content-box-headline ")]');
        if (!$filas) return $mapa;

        $actual = null;

        foreach ($filas as $tr) {
            if (strtolower($tr->nodeName) !== 'tr') {
                $txt = trim(preg_replace('/\s+/u', ' ', $tr->textContent));
                $txt = preg_replace('#\s*\d{1,2}/\d{1,2}/\d{2,4}\s*#', ' ', $txt);
                $txt = preg_replace('/^[\s\-–—·|]+|[\s\-–—·|]+$/u', '', $txt);
                if ($this->esRotuloGenerico($txt)) continue;
                $actual =($txt !== '' && mb_strlen($txt) <= 60) ? $txt : null;
                continue;
            }

            $tienePartido = $xp->query('.//a[contains(@href, "/spielbericht/")]', $tr)->length > 0;

            if ($tienePartido) {
                if ($actual !== null) $mapa[$tr->getNodePath()] = $actual;
                continue;
            }

            if ($xp->query('.//a[contains(@href, "/verein/")]', $tr)->length > 0) continue;

            // Un encabezado de ronda es UNA celda (con colspan). Una fila con
            // varias celdas con texto es la cabecera de columnas («Fecha ·
            // Local · Resultado…»): corta, no la frena el largo, y le ponía
            // «FechaLocal» de nombre a la ronda.
            $conTexto = 0;
            foreach ($xp->query('./th | ./td', $tr) as $celda) {
                if (trim($celda->textContent) !== '') $conTexto++;
            }
            if ($conTexto > 1) continue;

            $txt = trim(preg_replace('/\s+/u', ' ', $tr->textContent));

            // Ni vacía (separadores), ni larga (cabeceras de tabla con todos los
            // títulos de columna juntos), ni una fecha suelta.
            if ($txt === '' || mb_strlen($txt) > 60) continue;
            if (preg_match('#\d{1,2}/\d{1,2}/\d{2,4}#', $txt)) continue;
            if ($this->esRotuloGenerico($txt)) continue;

            $actual = $txt;
        }

        return $mapa;
    }

    /**
     * Rótulos que TM pone arriba de una lista de partidos y que NO son una
     * ronda: no cambian la ronda en curso, se saltean.
     *
     * Caso real (KNVB Beker 2000/01, `pokalwettbewerb/NLP`): cada grupo de la
     * fase de grupos es una caja titulada «Grupo 15», con la tabla de
     * posiciones y abajo una fila sola «Plan de encuentros» antes de los
     * partidos. Como esa fila viene DESPUÉS del título, lo pisaba: los 117
     * partidos de los 20 grupos quedaban en una sola ronda «Plan de
     * encuentros» y se perdía en qué grupo jugó cada uno.
     */
    private function esRotuloGenerico($txt)
    {
        return (bool) preg_match(
            '/^(plan de encuentros|calendario|partidos|resultados|spielplan|fixtures(?: & results)?|matches)$/iu',
            trim((string) $txt)
        );
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
