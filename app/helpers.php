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
    function url_idioma($idioma)
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

        $url = $raiz . ($partes ? '/' . implode('/', $partes) : '');
        $qs  = $req->getQueryString();
        return $qs ? $url . '?' . $qs : $url;
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
        ];
        $salida = [];
        foreach ($claves as $c) {
            $salida[$c] = __($c);
        }
        return $salida;
    }
}
