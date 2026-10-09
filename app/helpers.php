<?php
if (! function_exists('myFetchContents')) {

    function myFetchContents($file)
    {
        if(!$xml = file_get_contents($file))
        {
            throw new Exception('Load Failed');
        }
    }

}

if (!function_exists('removeAccents')) {
    function removeAccents($string) {
        $search = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'];
        return str_replace($search, $replace, $string);
    }
}


if (!function_exists('partirEscudo')) {
    /**
     * Separa el escudo del resto en una cadena "escudo_id_...".
     * El archivo puede traer guiones bajos (escudo_tm_683.png), así que se
     * corta en la extensión de imagen y no en el primer "_".
     * Devuelve [escudo, resto], con resto = "id_...".
     */
    function partirEscudo($cadena)
    {
        $cadena = (string) $cadena;

        if (preg_match('/^(.*?\.(?:png|jpe?g|gif|svg|webp))_(.*)$/is', $cadena, $m)) {
            return [$m[1], $m[2]];
        }

        // Sin escudo ("_769_...") o una extensión desconocida: como antes
        $partes = explode('_', $cadena, 2);
        return [$partes[0], isset($partes[1]) ? $partes[1] : ''];
    }
}

if (!function_exists('partesEscudo')) {
    /**
     * Lo mismo que explode('_', $cadena), pero sin partir el nombre del
     * archivo del escudo (escudo_tm_683.png_733_5_Nombre ->
     * ['escudo_tm_683.png', '733', '5', 'Nombre']).
     * Para las cadenas "escudo_id_..." que arman los listados.
     */
    function partesEscudo($cadena)
    {
        [$escudo, $resto] = partirEscudo($cadena);
        $partes = $resto === '' ? [] : explode('_', $resto);
        array_unshift($partes, $escudo);
        return $partes;
    }
}

if (!function_exists('clubesDesdeCadena')) {
    /**
     * Lee las cadenas "escudo_equipoId_[datos...]_nombre[_titulos]" que arma
     * TorneoController para las pantallas de Protagonistas.
     *
     * El nombre del club puede traer espacios (y hasta guiones bajos), así que
     * se lee por los extremos: primero escudo e id, después los campos
     * numéricos que declare quien llama, y lo que sobra en el medio es el
     * nombre. Si el último pedazo tiene forma de título ("3 (2 Ligas)") se
     * separa aparte.
     *
     * @param  string|null  $cadena
     * @param  array  $campos       nombres de los datos numéricos, en orden
     * @param  bool   $conTitulos   si la cadena puede terminar en un bloque de títulos
     * @return array
     */
    function clubesDesdeCadena($cadena, array $campos = [], $conTitulos = false)
    {
        $clubes = [];

        foreach (explode(',', (string) $cadena) as $item) {
            if (trim($item) === '') {
                continue;
            }

            [$escudo, $resto] = partirEscudo($item);
            $partes = explode('_', $resto);
            $club   = [
                'escudo'  => $escudo,
                'id'      => array_shift($partes),
                'titulos' => '',
            ];

            foreach ($campos as $campo) {
                $club[$campo] = array_shift($partes);
            }

            if ($conTitulos && count($partes) > 1 && preg_match('/^\d+\s*\(/u', (string) end($partes))) {
                $club['titulos'] = array_pop($partes);
            }

            $club['nombre'] = implode('_', $partes);

            if ($club['id'] !== null && $club['id'] !== '') {
                $clubes[] = $club;
            }
        }

        return $clubes;
    }
}

if (!function_exists('titulosDesdeCadena')) {
    /**
     * "3 (2 Ligas 1 Copas)" -> ['total' => 3, 'detalle' => '2 Ligas · 1 Copa'].
     * Devuelve null si no hay títulos.
     */
    function titulosDesdeCadena($cadena)
    {
        $cadena = trim((string) $cadena);

        if ($cadena === '') {
            return null;
        }

        $total   = (int) $cadena;
        $detalle = '';

        if (preg_match('/^(\d+)\s*\((.*)\)$/u', $cadena, $m)) {
            $total   = (int) $m[1];
            $detalle = $m[2];
        }

        if ($total <= 0) {
            return null;
        }

        $detalle = str_replace(
            ['1 Ligas', '1 Copas', '1 Internacionales'],
            ['1 Liga', '1 Copa', '1 Internacional'],
            $detalle
        );
        $detalle = preg_replace('/(\p{L})\s+(?=\d)/u', '$1 · ', $detalle);

        // "2 Ligas · 1 Copa" en el idioma de la página (en español no cambia).
        if (app()->getLocale() !== 'es') {
            $detalle = preg_replace_callback('/\p{L}+/u', function ($m) {
                return trad_dato($m[0]);
            }, $detalle);
        }

        return ['total' => $total, 'detalle' => $detalle];
    }
}

// ─────────────────────────────────────────────────────────────────────────
//  Idiomas del sitio público (ver App\Http\Middleware\Idioma)
// ─────────────────────────────────────────────────────────────────────────

if (! function_exists('idiomas_sitio')) {
    /**
     * Idiomas del sitio público. El primero es el de la casa: va sin prefijo
     * en la URL. Los demás van con /<codigo>/ adelante (/en/verTorneo…).
     * Para sumar uno: agregarlo acá y crear resources/lang/<codigo>.json.
     */
    function idiomas_sitio()
    {
        return ['es' => 'Español', 'en' => 'English'];
    }
}

if (! function_exists('trad_dato')) {
    /**
     * Traduce un valor fijo que viene de la base (país, posición, Liga/Copa…).
     * Si no está en el archivo del idioma, lo devuelve tal cual. Con null o
     * vacío no toca nada (__() con '' no es seguro).
     */
    function trad_dato($valor)
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }
        $clave = (string) $valor;
        $t = __($clave);
        return is_string($t) ? $t : $clave;
    }
}

if (! function_exists('es_fecha_numerada')) {
    /**
     * ¿La fecha es una jornada numerada? «4» y también «15b», la que se crea
     * a mano cuando un partido traído por DT choca con otro de la 15.
     */
    function es_fecha_numerada($numero)
    {
        return (bool) preg_match('/^\d+[a-z]?$/i', trim((string) $numero));
    }
}

if (! function_exists('nombre_fecha')) {
    /**
     * Nombre de una fecha en el idioma del sitio: «Fecha 4» / «Matchday 4»
     * (también «Fecha 15b»), y el resto por el diccionario («Final»,
     * «Cuartos de final»…) vía trad_dato(). Un nombre que no está en el
     * diccionario sale tal cual.
     */
    function nombre_fecha($numero)
    {
        if (es_fecha_numerada($numero)) {
            return __('Fecha :numero', ['numero' => trim((string) $numero)]);
        }
        return trad_dato($numero);
    }
}

if (! function_exists('nombres_fecha')) {
    /** [numero => nombre_fecha()] de una lista de filas (arrays u objetos con `numero`). */
    function nombres_fecha($filas)
    {
        $m = [];
        foreach ((array) $filas as $f) {
            $n = is_array($f) ? (isset($f['numero']) ? $f['numero'] : null)
                : (is_object($f) && isset($f->numero) ? $f->numero : null);
            if ($n !== null && $n !== '') {
                $m[(string) $n] = nombre_fecha($n);
            }
        }
        return $m;
    }
}

if (! function_exists('fecha_corta')) {
    /**
     * Fecha corta según el idioma del sitio: 03/12/2017 en castellano,
     * "3 Dec 2017" en inglés (sin la ambigüedad día/mes entre EE.UU. y el
     * resto). Acepta string, timestamp, DateTime o Carbon. torneos.js
     * (horasLocales) arma el mismo formato del lado del navegador.
     */
    function fecha_corta($fecha)
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        try {
            if ($fecha instanceof \DateTimeInterface) {
                $dt = $fecha;
            } elseif (is_int($fecha)) {
                $dt = (new \DateTime())->setTimestamp($fecha);
            } else {
                $dt = new \DateTime((string) $fecha);
            }
        } catch (\Exception $e) {
            return (string) $fecha;
        }
        // 'M' de date() es siempre el mes en inglés, no depende del locale.
        return $dt->format(app()->getLocale() === 'en' ? 'j M Y' : 'd/m/Y');
    }
}

if (! function_exists('texto_idioma')) {
    /**
     * Texto libre cargado a mano que tiene versión en inglés en otra columna
     * (equipos.historia_en, personas.observaciones_en, incidencias.observaciones_en).
     * En /en devuelve la versión en inglés; si está vacía, cae al castellano.
     */
    function texto_idioma($es, $en = null)
    {
        if (app()->getLocale() === 'en' && trim((string) $en) !== '') {
            return $en;
        }
        return $es;
    }
}

if (! function_exists('fecha_larga')) {
    /** "sábado 5 de octubre de 2024" / "Saturday, 5 October 2024". */
    function fecha_larga($fecha)
    {
        $c = \Carbon\Carbon::parse($fecha)->locale(app()->getLocale());
        return app()->getLocale() === 'es'
            ? $c->isoFormat('dddd D [de] MMMM [de] YYYY')
            : $c->isoFormat('dddd, D MMMM YYYY');
    }
}

if (! function_exists('url_idioma')) {
    /**
     * La página que se está viendo, en otro idioma: saca o pone el /en del
     * principio del path y conserva la query string.
     */
    function url_idioma($idioma, $canonica = false)
    {
        $req   = request();
        $raiz  = rtrim($req->root(), '/');
        $path  = trim($req->path(), '/');           // sin la base /~torneospinia/public
        $otros = array_slice(array_keys(idiomas_sitio()), 1);

        $partes = $path === '' ? [] : explode('/', $path);
        if ($partes && in_array($partes[0], $otros, true)) {
            array_shift($partes);
        }
        if ($idioma !== array_keys(idiomas_sitio())[0]) {
            array_unshift($partes, $idioma);
        }

        $query = $req->query();
        // En las URLs amigables (/jugador/250-…) el id va en el path: el
        // middleware 'url.amigable' lo puso en la query para el controlador,
        // pero no forma parte de la URL. (getQueryString() lee la query
        // original del pedido, así que no lo trae.)
        foreach ((array) $req->attributes->get('url_amigable_param', []) as $p) {
            unset($query[$p]);
        }
        // Página con una URL "oficial" distinta de la pedida (historial con los
        // equipos en el otro orden): la canonical y los hreflang van a esa.
        if ($canonica && ($otra = $req->attributes->get('url_amigable_path'))) {
            $partes = explode('/', trim(parse_url($otra, PHP_URL_PATH), '/'));
            if ($partes && in_array($partes[0], $otros, true)) {
                array_shift($partes);
            }
            if ($idioma !== array_keys(idiomas_sitio())[0]) {
                array_unshift($partes, $idioma);
            }
        }
        $url = $raiz . ($partes ? '/' . implode('/', $partes) : '');
        $qs  = $canonica ? query_canonica($query) : $req->getQueryString();
        return $qs ? $url . '?' . $qs : $url;
    }
}

if (! function_exists('query_canonica')) {
    /**
     * Query string de la versión "oficial" de una página, para los buscadores
     * (<link rel="canonical"> y los hreflang de metaPublic.blade.php).
     *
     * Se sacan los parámetros que NO cambian de qué trata la página: campañas
     * (utm_*, fbclid…), el filtro por nombre de los listados (buscarpor), el
     * orden de una tabla y la pestaña abierta. Todo lo demás (jugadorId,
     * torneoId, fechaId, tipo, page…) se conserva, y se ordena alfabéticamente
     * para que ?a=1&b=2 y ?b=2&a=1 sean la misma URL.
     *
     * Ante la duda un parámetro se conserva: juntar dos páginas distintas en
     * una es peor que dejar dos casi iguales.
     */
    function query_canonica(array $query)
    {
        $fuera = ['buscarpor', 'order', 'tipoOrder', 'pestActiva', 'perf', '_',
                  'fbclid', 'gclid', 'msclkid'];

        foreach (array_keys($query) as $k) {
            if (in_array($k, $fuera, true) || strpos($k, 'utm_') === 0
                || $query[$k] === null || $query[$k] === '') {
                unset($query[$k]);
            }
        }
        ksort($query);

        return http_build_query($query);
    }
}

if (! function_exists('url_canonica')) {
    /** URL canónica de la página actual, en su idioma (ver query_canonica()). */
    function url_canonica()
    {
        return url_idioma(app()->getLocale(), true);
    }
}

if (! function_exists('url_imagen')) {
    /**
     * URL absoluta de un archivo de public/images, con el nombre codificado:
     * hay escudos y banderas con espacios y acentos ("Nueva Zelanda.gif"), y los
     * lectores de Open Graph (WhatsApp, Facebook) no siempre los toleran crudos.
     */
    function url_imagen($archivo)
    {
        $partes = array_map('rawurlencode', explode('/', ltrim((string) $archivo, '/')));

        return url('images/' . implode('/', $partes));
    }
}

if (! function_exists('cantidad')) {
    /**
     * "1 partido" / "312 partidos" / "1.204 goles" en el idioma del sitio.
     * Las palabras pasan por __(), así que van en lang/en.json.
     */
    function cantidad($n, $singular, $plural)
    {
        $n = (int) $n;
        $num = app()->getLocale() === 'en'
            ? number_format($n, 0, '.', ',')
            : number_format($n, 0, ',', '.');

        return $num . ' ' . __($n === 1 ? $singular : $plural);
    }
}

if (! function_exists('lista_y')) {
    /** ['a', 'b', 'c'] -> "a, b y c" ("a, b and c" en inglés). Ignora vacíos. */
    function lista_y(array $partes)
    {
        $partes = array_values(array_filter($partes, function ($p) { return $p !== null && $p !== ''; }));
        if (count($partes) <= 1) {
            return $partes[0] ?? '';
        }
        $ultima = array_pop($partes);

        return implode(', ', $partes) . ' ' . __('y') . ' ' . $ultima;
    }
}

if (! function_exists('textos_js')) {
    /**
     * Textos de public/js/torneos.js traducidos al idioma actual (los usa su
     * función t()). Al agregar un t('…') nuevo en el JS, sumarlo acá y al .json.
     */
    function textos_js()
    {
        $claves = [
            'Cambiar a tema claro',
            'Cambiar a tema oscuro',
            'Filas cómodas',
            'Filas compactas',
            'No se pudo cargar el menú. ',
            'Ver todas las competiciones',
            'Ver todas las temporadas',
            'Volver a la lista de países',
            'Ver página',
            'Ligas',
            'Copas',
            'Torneos que ya no se juegan (:n)',
            'Primeros :n resultados',
            '1 resultado',
            ':n resultados',
            'Sin resultados',
            'No hay torneos que coincidan con «:q».',
            'Horario de tu zona (:zona). En Argentina: :orig',
        ];
        $salida = [];
        foreach ($claves as $c) {
            $salida[$c] = __($c);
        }
        return $salida;
    }
}

if (!function_exists('hora_partido')) {
    /**
     * Día u hora de un partido, para que el visitante la vea en SU zona.
     *
     * La base guarda todo en hora argentina (config/app.php). Acá se escribe
     * esa hora —es lo que ve quien no tiene JavaScript y lo que queda en la
     * caché del sitio— dentro de un <time> con el instante exacto (ISO con el
     * offset histórico de Argentina, que calcula DateTimeZone). torneos.js
     * (horasLocales) lo reescribe con la zona del navegador.
     *
     * $que: 'fecha' (fecha_corta(): d/m/Y o "3 Dec 2017") · 'hora' (H:i, cuando al lado va la fecha también
     * convertida) · 'hora_dia' (H:i en listas agrupadas por día argentino: si
     * en la zona del visitante cae otro día, el JS le agrega +1 / −1).
     *
     * Un partido a las 00:00 es "sin hora" en este sitio: no se convierte,
     * porque moverlo cambiaría el día por una hora que nunca existió.
     */
    function hora_partido($dia, $que = 'hora')
    {
        if (!$dia) {
            return '';
        }
        try {
            $dt = new \DateTime((string) $dia, new \DateTimeZone(config('app.timezone', 'America/Argentina/Buenos_Aires')));
        } catch (\Exception $e) {
            return e((string) $dia);
        }
        $txt = $que === 'fecha' ? fecha_corta($dt) : $dt->format('H:i');
        if ($dt->format('H:i:s') === '00:00:00') {
            return e($txt);
        }
        return '<time class="t-local" datetime="' . $dt->format('c') . '" data-que="' . e($que) . '" data-dia="'
            . $dt->format('Y-m-d') . '">' . e($txt) . '</time>';
    }
}

if (!function_exists('url_volver')) {
    /**
     * Link del botón "Volver": la página anterior del visitante.
     *
     * Es lo mismo que url()->previous(), salvo cuando la página se está por
     * guardar en la caché del sitio público: ahí devuelve una marca que
     * App\Http\Middleware\PaginaEnCache cambia por la página anterior de cada
     * visitante (si no, todos volverían a donde venía el primero).
     */
    function url_volver()
    {
        if (request()->attributes->get('cp.guardando')) {
            return \App\Http\Middleware\PaginaEnCache::HUECO_VOLVER;
        }

        return url()->previous();
    }
}

if (! function_exists('datos_estructurados')) {
    /**
     * Datos estructurados (JSON-LD de schema.org) de la página.
     *
     * La vista agrega nodos con datos_estructurados([...]) en un @php y
     * metaPublic los imprime en el <head> con datos_estructurados_html(). Anda
     * porque Blade arma la vista hija antes que el layout. No se usa una
     * @section: lo que entra por @section('x', $valor) pasa por e() y rompería
     * el JSON.
     *
     * Las URLs se arman con route(), así que salen con la marca de las URLs
     * amigables y UrlAmigableSlugs las completa como al resto de la página.
     */
    function datos_estructurados(?array $nodo = null)
    {
        static $nodos = [];
        if ($nodo === null) {
            $todos = $nodos;
            $nodos = [];
            return $todos;
        }
        $nodos[] = $nodo;
        return $nodos;
    }

    function datos_estructurados_html()
    {
        $nodos = datos_estructurados();
        if (!$nodos) {
            return '';
        }
        // Sin nulos ni textos vacíos (un "logo": null es un error para el validador).
        $limpiar = function ($v) use (&$limpiar) {
            if (!is_array($v)) {
                return $v;
            }
            $r = [];
            foreach ($v as $k => $x) {
                $x = $limpiar($x);
                if ($x === null || $x === '' || $x === []) {
                    continue;
                }
                $r[$k] = $x;
            }
            return $r;
        };
        $datos = count($nodos) === 1
            ? ['@context' => 'https://schema.org'] + $nodos[0]
            : ['@context' => 'https://schema.org', '@graph' => $nodos];

        // JSON_HEX_TAG: un "</script>" dentro de un nombre no puede cerrar el bloque.
        return '<script type="application/ld+json">'
            . json_encode($limpiar($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
            . '</script>';
    }
}

if (! function_exists('migas_ld')) {
    /** BreadcrumbList de schema.org: [[nombre, url], …] (la última puede ir sin url). */
    function migas_ld(array $migas)
    {
        $items = [];
        foreach (array_values($migas) as $i => $m) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $m[0], 'item' => $m[1] ?? null];
        }
        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
}
