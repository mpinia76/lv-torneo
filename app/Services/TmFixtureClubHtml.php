<?php

namespace App\Services;

/**
 * El calendario de un club en UNA temporada, leído del HTML de Transfermarkt.
 *
 * Existe porque **la API no puede traer temporadas pasadas** — once formas
 * probadas en sep-2026, incluidas `/club/{id}/fixtures?seasonId=` y
 * `?saison_id=`, que reciben el parámetro y devuelven igual la temporada en
 * curso (ver la memoria [[buscar-gameid]]). El sitio sí la tiene:
 * `spielplandatum/verein/{id}/saison_id/{s}` lista todos los partidos del club
 * en esa temporada, cada uno con su `gameId` en el link del resultado.
 *
 * Una llamada = un crédito = todos los partidos de ese club en esa temporada.
 * Contra un crédito por partido de la búsqueda automática, y contra pegar la
 * URL a mano de a uno.
 *
 * **Es HTML, no JSON: se rompe si TM cambia el maquetado.** Por eso:
 *  - se lee por lo que significa cada cosa y no por posición de columna (se
 *    arranca de los links `/spielbericht/`, que son lo único que no se puede
 *    confundir, y desde ahí se sube a la fila);
 *  - se guarda el HTML crudo cuando quien llama lo pide;
 *  - y `avisos` cuenta lo que se descartó, así que una página que cambió se
 *    nota como "leí 0 partidos" y no como "el club no jugó".
 */
class TmFixtureClubHtml
{
    /**
     * El sitio en español, a propósito: ahí la fecha es dd/mm/aaaa.
     *
     * En el .com es mm/dd/aaaa, y confundirlas cambia partidos de mes en
     * silencio. Igual no se confía en el idioma: el formato se detecta solo
     * (ver `detectarFormato()`).
     *
     * El primer segmento es el nombre del club y TM lo ignora: con un guion
     * alcanza (verificado sep-2026).
     */
    const BASE = 'https://www.transfermarkt.es/-/spielplandatum/verein/';

    /** @var array */
    public $avisos = [];

    /** Cuántas filas se descartaron y por qué. */
    public $descartadas = 0;

    /** @var string */
    public $crudo = '';

    public static function url($clubTm, $season)
    {
        return self::BASE . rawurlencode((string) $clubTm) . '/saison_id/' . rawurlencode((string) $season);
    }

    /**
     * Los partidos del club en esa temporada.
     *
     * Devuelve una lista de:
     *   ['game_id', 'dia' => 'Y-m-d', 'dia_crudo', 'local' => bool|null,
     *    'rival_tm', 'rival_nombre', 'competencia', 'resultado']
     * o null si no se pudo traer la página.
     */
    public function leer($clubTm, $season, $guardarCrudo = false)
    {
        $this->avisos      = [];
        $this->descartadas = 0;
        $this->crudo       = '';

        $url  = self::url($clubTm, $season);
        $html = HttpHelper::getHtmlContent($url);

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
            $this->avisos[] = 'La página no tiene ningún link a una ficha de partido. '
                . 'O el club no jugó esa temporada, o Transfermarkt cambió el maquetado.';
            return [];
        }

        $filas = [];

        foreach ($links as $a) {
            if (!preg_match('#/spielbericht/(?:index/spielbericht/)?(\d{4,})#', $a->getAttribute('href'), $m)) {
                continue;
            }

            $gameId = $m[1];

            if (isset($filas[$gameId])) {
                continue;   // el mismo partido linkeado dos veces en la fila
            }

            $tr = $a;
            while ($tr !== null && strtolower($tr->nodeName) !== 'tr') {
                $tr = $tr->parentNode;
            }

            if ($tr === null) {
                $this->descartadas++;
                continue;
            }

            $fila = $this->leerFila($xp, $tr, (string) $clubTm);

            if ($fila !== null) {
                $fila['resultado'] = trim(preg_replace('/\s+/u', ' ', $a->textContent));
            }

            if ($fila === null) {
                $this->descartadas++;
                continue;
            }

            $filas[$gameId] = ['game_id' => $gameId] + $fila;
        }

        return $this->convertirFechas(array_values($filas));
    }

    /** Lo que hay que sacar de una fila del calendario. */
    private function leerFila(\DOMXPath $xp, \DOMNode $tr, $clubTm)
    {
        $diaCrudo = null;
        $local    = null;

        foreach ($xp->query('.//td', $tr) as $td) {
            $txt = trim(preg_replace('/\s+/u', ' ', $td->textContent));

            if ($diaCrudo === null && preg_match('#\b(\d{1,2})/(\d{1,2})/(\d{2,4})\b#', $txt, $m)) {
                $diaCrudo = $m[0];
                continue;
            }

            // La columna «Localidad»: H de local, A de visitante. Se compara la
            // celda entera para no confundirla con una A suelta de otro texto.
            if ($local === null && ($txt === 'H' || $txt === 'A')) {
                $local = $txt === 'H';
            }
        }

        if ($diaCrudo === null) {
            return null;   // sin fecha no sirve para aparear
        }

        $rivalTm     = null;
        $rivalNombre = null;

        foreach ($xp->query('.//a[contains(@href, "/verein/")]', $tr) as $link) {
            if (!preg_match('#/verein/(\d+)#', $link->getAttribute('href'), $m)) {
                continue;
            }

            if ($m[1] === $clubTm) {
                continue;   // el club cuyo calendario estamos mirando
            }

            $nombre = trim(preg_replace('/\s+/u', ' ', $link->textContent));

            // El escudo también linkea al club, con el texto vacío: se prefiere
            // el link que trae el nombre, pero el id sirve igual.
            if ($rivalTm === null) {
                $rivalTm = $m[1];
            }

            if ($nombre !== '' && $rivalNombre === null && $m[1] === $rivalTm) {
                $rivalNombre = $nombre;
            }
        }

        // El id de competencia está DENTRO de la fila: la celda de la jornada
        // linkea a la competencia. De ahí salen ARGC, ARG1, etc.
        //
        // OJO CON LAS COPAS: en TM las ligas van por `/wettbewerb/` y las copas
        // por `/pokalwettbewerb/` — Copa Argentina es `pokalwettbewerb/ARCA`,
        // Libertadores `CLI`, Mundial de Clubes `KLUB`. Buscando "/wettbewerb/"
        // con la barra adelante, las copas no matcheaban: quedaban sin id y,
        // peor, heredaban el nombre del último encabezado de liga, así que un
        // partido de la Copa Argentina figuraba como «Torneo Clausura».
        $compId = null;

        foreach ($xp->query('.//a[contains(@href, "wettbewerb/")]', $tr) as $link) {
            if (preg_match('#/(?:pokal)?wettbewerb/([A-Za-z0-9]+)#', $link->getAttribute('href'), $m)) {
                $compId = $m[1];
                break;
            }
        }

        // El NOMBRE es el encabezado de la sección, hacia atrás. Ojo: el link de
        // la jornada de las filas anteriores también apunta a la competencia y
        // su texto es el número de fecha. Sin descartar los textos numéricos,
        // cada partido se quedaba con la jornada del anterior — pasó, y se veía
        // como una competencia llamada «15».
        $competencia = null;
        $previos     = $xp->query('preceding::a[contains(@href, "wettbewerb/")]', $tr);

        if ($previos && $previos->length) {
            for ($i = $previos->length - 1; $i >= 0; $i--) {
                $txt = trim(preg_replace('/\s+/u', ' ', $previos->item($i)->textContent));

                if ($txt !== '' && !preg_match('/^\d+$/', $txt)) {
                    $competencia = $txt;
                    break;
                }
            }
        }

        return [
            'dia_crudo'    => $diaCrudo,
            'dia'          => null,
            'local'        => $local,
            'rival_tm'     => $rivalTm,
            'rival_nombre' => $rivalNombre,
            'competencia'  => $competencia,
            'comp_id'      => $compId,
            'resultado'    => null,
        ];
    }

    /**
     * dd/mm o mm/dd: se decide MIRANDO LOS DATOS, no el idioma de la página.
     *
     * Si alguna fecha tiene el primer número mayor que 12, es dd/mm; si lo
     * tiene el segundo, es mm/dd. Un calendario de una temporada entera casi
     * siempre trae las dos pruebas. Si no aparece ninguna —caso rarísimo, todas
     * las fechas del 1 al 12— se usa dd/mm, que es lo que sirve el sitio en
     * español, y se avisa.
     */
    protected function convertirFechas(array $filas)
    {
        $formato = $this->detectarFormato($filas);

        foreach ($filas as $i => $f) {
            if (!preg_match('#^(\d{1,2})/(\d{1,2})/(\d{2,4})$#', $f['dia_crudo'], $m)) {
                continue;
            }

            $a = (int) $m[3];
            if ($a < 100) $a += 2000;

            $d = $formato === 'mm/dd' ? (int) $m[2] : (int) $m[1];
            $mes = $formato === 'mm/dd' ? (int) $m[1] : (int) $m[2];

            if (!checkdate($mes, $d, $a)) {
                continue;
            }

            $filas[$i]['dia'] = sprintf('%04d-%02d-%02d', $a, $mes, $d);
        }

        return $filas;
    }

    protected function detectarFormato(array $filas)
    {
        $primeroGrande = false;
        $segundoGrande = false;

        foreach ($filas as $f) {
            if (!preg_match('#^(\d{1,2})/(\d{1,2})/#', $f['dia_crudo'], $m)) {
                continue;
            }

            if ((int) $m[1] > 12) $primeroGrande = true;
            if ((int) $m[2] > 12) $segundoGrande = true;
        }

        if ($primeroGrande && $segundoGrande) {
            $this->avisos[] = 'Las fechas de la página no son consistentes: hay días mayores que 12 en las dos '
                . 'posiciones. No me fío de ninguna: revisá la lista antes de guardar.';
            return 'dd/mm';
        }

        if ($segundoGrande) {
            $this->avisos[] = 'Esa página vino en formato mm/dd/aaaa (sitio en inglés). Lo tuve en cuenta.';
            return 'mm/dd';
        }

        if (!$primeroGrande) {
            $this->avisos[] = 'Todas las fechas tienen día y mes menores que 13, así que no pude confirmar el '
                . 'formato por los datos. Asumí dd/mm/aaaa, que es el del sitio en español.';
        }

        return 'dd/mm';
    }
}
