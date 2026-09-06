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

        // ── EL ALFABETO IMPORTA ─────────────────────────────────────────────
        // `passportName` es el nombre legal y para varios países TM lo guarda
        // en el alfabeto original. Bajando un Pyramids FC – Auckland City se
        // crearon 22 fichas con el nombre en árabe («الشناوي, احمد») y una en
        // chino: ilegibles, imposibles de buscar y de ordenar en un sitio en
        // castellano, y encima duplicables sin que nadie lo note.
        //
        // Se elige el primer candidato ESCRITO EN ALFABETO LATINO, respetando
        // el orden de siempre. Para un nombre latino —o sea, para todo lo que
        // ya estaba— no cambia absolutamente nada.
        $enLatino = function ($s) {
            return $s !== ''
                && preg_match('/\p{Latin}/u', $s)
                && !preg_match('/[\p{Arabic}\p{Hebrew}\p{Han}\p{Hiragana}\p{Katakana}'
                    . '\p{Hangul}\p{Cyrillic}\p{Greek}\p{Thai}\p{Devanagari}\p{Armenian}'
                    . '\p{Georgian}]/u', $s);
        };

        // Nombre completo: passportName (legal); si viene vacío, displayName
        // (ej. brasileños: "Nadson Juan Maia da Silva de Souza"); y como último
        // recurso, el name corto.
        $completo = '';

        foreach ([$passport, $displayName, $nameField, $shortName] as $cand) {
            if ($enLatino($cand)) {
                $completo = $cand;
                break;
            }
        }

        // Si TM no tiene NINGUNA forma latina de ese nombre, se vuelve al orden
        // de siempre: mejor el nombre en su alfabeto que ninguno.
        if ($completo === '') {
            $completo = $passport !== '' ? $passport
                : ($displayName !== '' ? $displayName : $nameField);
        }

        // El campo mostrado se elige aparte, y también en latino cuando se puede.
        $paraMostrar = [$shortName, $nameField, $displayName, $passport];

        // Un ancla en otro alfabeto no puede coincidir con un nombre latino, y
        // buscarla sólo gasta el intento: se descarta y se va al fallback.
        if ($shortName !== '' && !$enLatino($shortName) && $enLatino($completo)) {
            $shortName = '';
        }

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
                // Mononimo (ej: "Marcão", "Tite", "Ronaldinho"): el único token va
                // a los dos campos, igual que hacía el importador viejo. Antes
                // quedaba sólo en $nombre y el apellido salía vacío, con dos
                // consecuencias: el import se abortaba con "No se pudieron extraer
                // los datos..." y, como `nombre` quedaba NULL, el índice único de
                // personas no saltaba (MySQL trata dos NULL como distintos) y se
                // cargaba un duplicado en cada intento.
                $nombre   = trim($baseSplit);
                $apellido = trim($baseSplit);
            }
        }

        // Campo "name" mostrado: shortName; si no hay, name; si no, nombre+apellido.
        // Con la misma regla del alfabeto: es el que se ve en las listas.
        $name = '';

        foreach ($paraMostrar as $cand) {
            if ($enLatino($cand)) {
                $name = $cand;
                break;
            }
        }

        if ($name === '') { $name = $shortName !== '' ? $shortName : $nameField; }
        if ($name === '') { $name = trim($nombre . ' ' . $apellido); }

        // El shortName de TM puede haberse comido un apellido de adelante.
        $name = self::completarApellido($name, $apellido);

        return [
            'name'     => $name,
            'nombre'   => $nombre,
            'apellido' => $apellido,
        ];
    }

    /**
     * Repone en el campo MOSTRADO los apellidos que Transfermarkt salteó.
     *
     * TM arma el shortName con UN solo apellido, y no siempre es el primero:
     *
     *   Nicolás Barros Schelotto  ->  shortName "N. Schelotto"   ("Barros" desaparece)
     *   Roberto Pereyra Legallais ->  shortName "R. Legallais"   ("Pereyra" desaparece)
     *
     * En las listas del sitio eso se lee como otra persona. Acá se completa el
     * apellido tal como quedó separado en la ficha, conservando intacta la parte
     * del nombre de pila que eligió TM (inicial o nombre de uso).
     *
     * NO toca el caso normal —"F. Calvo" con apellido "Calvo Quesada"—: ahí TM
     * ya arranca por el PRIMER apellido, que es como se conoce a la persona, y
     * alargarlo a "F. Calvo Quesada" empeoraría miles de fichas que hoy están
     * bien. Sólo se repone lo que falta ADELANTE.
     *
     * Se llama dos veces a propósito: una acá, para los importadores que usan
     * `separarTM()` directo, y otra en `TmDetallePartido::personaDesdePerfil()`
     * DESPUÉS de `rescatarPrimerApellido()` / `apellidoDobleSinAncla()`, que son
     * las que descubren el apellido doble cuando TM no lo manda separado. Sin esa
     * segunda pasada el name se queda con el apellido viejo: es exactamente por
     * eso que "Barros Schelotto" quedaba bien en la ficha y mal en el mostrado.
     *
     * @param  string $name      campo mostrado tal como viene de TM
     * @param  string $apellido  apellido ya separado (el de la ficha)
     * @return string            el mostrado, con el apellido completo
     */
    public static function completarApellido($name, $apellido)
    {
        $name     = trim((string) $name);
        $apellido = trim((string) $apellido);
        if ($name === '' || $apellido === '') return $name;

        $tokensApe = preg_split('/\s+/', $apellido);
        // Apellido simple: no hay nada que reponer.
        if (count($tokensApe) < 2) return $name;

        $tokensName = preg_split('/\s+/', $name);

        $norm = function ($s) {
            $s = mb_strtolower(trim((string) $s), 'UTF-8');
            return strtr($s, [
                'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a',
                'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e',
                'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i',
                'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o',
                'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u',
                'ñ'=>'n','ç'=>'c','ý'=>'y','ÿ'=>'y',
            ]);
        };

        // ¿Por qué palabra del apellido arranca lo que muestra TM?
        $idxName = null;
        $idxApe  = null;
        foreach ($tokensName as $i => $t) {
            foreach ($tokensApe as $j => $a) {
                if ($norm($t) === $norm($a)) { $idxName = $i; $idxApe = $j; break 2; }
            }
        }

        // El mostrado no contiene ninguna palabra del apellido (apodos tipo
        // "Marcão", nombres artísticos): no es asunto nuestro.
        if ($idxName === null) return $name;

        // TM ya arranca por el primer apellido: está bien como está.
        if ($idxApe === 0) return $name;

        // De ahí al final el mostrado tiene que ser EXACTAMENTE la cola del
        // apellido. Si no, es una forma que no entendemos y no la pisamos.
        $cola     = array_slice($tokensName, $idxName);
        $esperado = array_slice($tokensApe, $idxApe);
        if (count($cola) !== count($esperado)) return $name;
        foreach ($cola as $k => $t) {
            if ($norm($t) !== $norm($esperado[$k])) return $name;
        }

        $pila   = array_slice($tokensName, 0, $idxName);
        $faltan = array_slice($tokensApe, 0, $idxApe);

        return trim(implode(' ', array_merge($pila, $faltan, $cola)));
    }
}
