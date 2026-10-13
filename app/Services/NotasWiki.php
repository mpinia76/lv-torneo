<?php

namespace App\Services;

/**
 * Saca de un texto las marcas de nota que quedan al copiar de Wikipedia:
 * "[1]", "[12]:34", "[a]", "[nota 3]", "[cita requerida]", "[editar]"...
 *
 * Se usa en la historia de los clubes (castellano e inglés): al guardar
 * desde el formulario (EquipoController) y en el barrido periódico
 * (php artisan equipos:limpiar-notas).
 *
 * Es conservador a propósito: sólo saca lo que tiene forma de nota de wiki.
 * Un corchete con texto normal ("[sic]", "[...]", "[Racing]") se queda.
 */
class NotasWiki
{
    /** Marcas de mantenimiento de Wikipedia (es / en), en minúscula. */
    const MARCAS = [
        'cita requerida', 'cita necesaria', 'citation needed', 'verificación requerida',
        'aclaración requerida', 'sin referencias', 'referencia rota', 'dudoso',
        '¿cuándo?', '¿quién?', '¿dónde?', '¿cuál?', '¿por qué?', 'when?', 'who?', 'which?',
        'clarification needed', 'dubious', 'dubious – discuss', 'dubious - discuss',
        'better source needed', 'failed verification', 'page needed', 'year needed',
        'not in citation given', 'según quién', 'editar', 'edit', 'editar código',
    ];

    public static function patron()
    {
        static $p = null;
        if ($p === null) {
            $marcas = implode('|', array_map(fn ($m) => preg_quote($m, '/'), self::MARCAS));
            $nota = '(?:'
                . '\d{1,3}'                                  // [1] [12]
                . '|[a-z]'                                   // [a] [b] (notas con letra)
                . '|(?:nota|note|n\.|nb|n)\s*\.?\s*\d{1,3}'  // [nota 1] [note 2] [n. 3]
                . '|' . $marcas                              // [cita requerida] [editar]
                . ')';
            // Espacios antes de la marca + la marca (una o varias pegadas: [1][2])
            // + la página que a veces viene atrás: [3]:45 / [3]: p. 45 / [3]:45-47
            $p = '/[ \t]*(?:\[' . $nota . '\])+(?::\s*(?:pp?\.\s*)?\d+(?:\s*[-–]\s*\d+)?)?/iu';
        }
        return $p;
    }

    /** Devuelve el texto sin las notas (igual que entró si no tenía ninguna). */
    public static function limpiar($texto)
    {
        if ($texto === null || $texto === '' || strpos($texto, '[') === false) {
            return $texto;
        }

        $limpio = preg_replace(self::patron(), '', $texto, -1, $n);
        if (!$n || $limpio === null) {
            return $texto;
        }

        // Lo que deja la marca al salir: espacios antes de la puntuación
        // ("palabra [1] ." -> "palabra."), espacios dobles y al borde del renglón.
        $limpio = preg_replace('/[ \t]+([.,;:!?)])/u', '$1', $limpio);
        $limpio = preg_replace('/[ \t]{2,}/', ' ', $limpio);
        $limpio = preg_replace('/^[ \t]+|[ \t]+(?=\r?$)/m', '', $limpio);

        return $limpio;
    }
}
