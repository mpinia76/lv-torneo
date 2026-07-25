<?php

namespace App\Services;

class NombreHelper
{
    /**
     * Separa el nombre de una persona (jugador, DT o árbitro) a partir del
     * perfil JSON de la API interna de Transfermarkt (tmapi).
     *
     * Devuelve un array con:
     *   'name'     => nombre mostrado (shortName o name)
     *   'nombre'   => nombres de pila
     *   'apellido' => apellido(s)
     *
     * Estrategia: se toma el nombre COMPLETO (passportName legal; si no,
     * displayName; si no, name) y se usa el shortName como ancla para saber
     * dónde termina el nombre y empiezan los apellidos.
     *
     * El shortName puede venir en dos formatos:
     *   "F. Calvo"     (inicial + apellido)  -> el apellido arranca en "Calvo"
     *   "Tomás Uribe"  (nombre + apellido)   -> el apellido arranca en "Uribe"
     */
    public static function separarTM(array $datos): array
    {
        $nameField   = trim($datos['name'] ?? '');
        $shortName   = trim($datos['shortName'] ?? '');
        $passport    = trim($datos['nationalityDetails']['passportName'] ?? '');
        $displayName = trim($datos['displayName'] ?? '');

        // Nombre completo: passportName (legal); si viene vacío, displayName
        // (ej. brasileños: "Nadson Juan Maia da Silva de Souza"); y como último
        // recurso, el name corto.
        $completo = $passport !== '' ? $passport
                  : ($displayName !== '' ? $displayName : $nameField);

        // Ancla de apellido: con qué palabra ARRANCA el apellido dentro del shortName.
        $primerApellido = '';
        if ($shortName !== '') {
            // Quitamos iniciales tipo "F." del comienzo.
            $sinIniciales = trim(preg_replace('/^(\p{Lu}\p{Ll}?\.\s*)+/u', '', $shortName));
            if ($sinIniciales !== '' && $sinIniciales !== trim($shortName)) {
                // Había iniciales (ej. "F. Calvo" -> "Calvo"): apellido = 1ra palabra restante.
                $primerApellido = preg_split('/\s+/', $sinIniciales)[0];
            } else {
                // Sin iniciales (ej. "Tomás Uribe"): 1ra palabra = nombre; apellido = 2da.
                $tokensShort    = preg_split('/\s+/', trim($shortName));
                $primerApellido = $tokensShort[count($tokensShort) >= 2 ? 1 : 0];
            }
        }

        $nombre   = '';
        $apellido = '';

        if ($primerApellido !== '' && $completo !== '') {
            $palabras = preg_split('/\s+/', $completo);

            // Ubicamos la palabra dentro del nombre completo (sin distinguir may/min
            // ni acentos). Quitamos acentos con una tabla portable: iconv con
            // 'ASCII//TRANSLIT' es poco fiable (según el locale convierte "í" en
            // "'i" y la comparación falla, ej. "García" vs "Garcia").
            $norm = function ($s) {
                $s = mb_strtolower(trim($s ?? ''), 'UTF-8');
                return strtr($s, [
                    'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
                    'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
                    'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
                    'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
                    'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
                    'ñ'=>'n','ç'=>'c','ý'=>'y','ÿ'=>'y',
                ]);
            };
            $idx = null;
            foreach ($palabras as $i => $p) {
                if ($norm($p) === $norm($primerApellido)) { $idx = $i; break; }
            }

            if ($idx !== null && $idx > 0) {
                $nombre   = trim(implode(' ', array_slice($palabras, 0, $idx)));
                $apellido = trim(implode(' ', array_slice($palabras, $idx)));
            }
        }

        // Fallback: si no pudimos anclar, separamos por la última palabra del
        // nombre completo, arrastrando las partículas (de, da, van, etc.) al apellido.
        if ($apellido === '') {
            $baseSplit = $completo !== '' ? $completo : $nameField;
            $palabras  = preg_split('/\s+/', trim($baseSplit));
            if (count($palabras) >= 2) {
                $particulas = ['de','da','do','dos','das','del','della','di','la','las','los',
                               'van','von','der','den','du','le','bin','al'];
                $apellido = array_pop($palabras);
                while (!empty($palabras) && in_array(mb_strtolower(end($palabras)), $particulas, true)) {
                    $apellido = array_pop($palabras) . ' ' . $apellido;
                }
                $nombre   = implode(' ', $palabras);
            } else {
                $nombre   = $baseSplit;
                $apellido = '';
            }
        }

        // Campo "name" mostrado: shortName; si no hay, name; si no, nombre+apellido.
        $name = $shortName !== '' ? $shortName : $nameField;
        if ($name === '') { $name = trim($nombre . ' ' . $apellido); }

        return [
            'name'     => $name,
            'nombre'   => $nombre,
            'apellido' => $apellido,
        ];
    }
}
