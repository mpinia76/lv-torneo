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

            $partes = explode('_', $item);
            $club   = [
                'escudo'  => array_shift($partes),
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

        return ['total' => $total, 'detalle' => $detalle];
    }
}
