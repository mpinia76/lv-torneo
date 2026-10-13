<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traduce texto libre entre castellano e inglés (hoy: la historia de los clubes).
 *
 * Dos motores, en este orden:
 *  - DeepL, si hay DEEPL_API_KEY en el .env. Mejor calidad; el plan gratis da
 *    500.000 caracteres por mes (una historia ronda los 1.500-4.000).
 *  - Si no, el endpoint público de Google Translate (translate.googleapis.com,
 *    client=gtx). Anda sin clave, pero NO es una API oficial: Google puede
 *    limitarlo o cortarlo sin aviso. Sirve para el uso de a un club por vez.
 *
 * Nunca tira excepción: si algo falla devuelve null y deja el motivo en
 * ultimoError(), para que quien llama guarde igual y avise.
 */
class Traductor
{
    /** Google corta los pedidos largos; se manda por tandas de párrafos. */
    const MAX_TANDA = 4500;

    protected static $ultimoError = null;

    public static function ultimoError()
    {
        return self::$ultimoError;
    }

    /** Qué motor se va a usar, para mostrarlo en los avisos. */
    public static function motor()
    {
        return config('services.deepl.key') ? 'DeepL' : 'Google';
    }

    /**
     * @param string $texto
     * @param string $de  'es' | 'en'
     * @param string $a   'es' | 'en'
     * @return string|null  null si no se pudo
     */
    public static function traducir($texto, $de, $a)
    {
        self::$ultimoError = null;
        $texto = (string) $texto;
        if (trim($texto) === '') {
            return $texto;
        }

        try {
            $salida = config('services.deepl.key')
                ? self::deepl($texto, $de, $a)
                : self::google($texto, $de, $a);
        } catch (\Throwable $e) {
            $salida = null;
            self::$ultimoError = $e->getMessage();
        }

        if ($salida === null || trim($salida) === '') {
            self::$ultimoError = self::$ultimoError ?: 'respuesta vacía';
            Log::warning('Traductor ' . self::motor() . " {$de}->{$a}: " . self::$ultimoError);
            return null;
        }
        return $salida;
    }

    protected static function deepl($texto, $de, $a)
    {
        $clave = config('services.deepl.key');
        // Las claves del plan gratis terminan en ":fx" y van a otro host.
        $host = str_ends_with($clave, ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com';

        $r = Http::timeout(30)
            ->withHeaders(['Authorization' => 'DeepL-Auth-Key ' . $clave])
            ->asForm()
            ->post($host . '/v2/translate', [
                'text' => $texto,
                'source_lang' => strtoupper($de),
                // DeepL pide variante para el inglés de destino.
                'target_lang' => $a === 'en' ? 'EN-GB' : strtoupper($a),
                'preserve_formatting' => '1',
            ]);

        if (!$r->successful()) {
            self::$ultimoError = 'DeepL respondió ' . $r->status();
            return null;
        }
        return $r->json('translations.0.text');
    }

    protected static function google($texto, $de, $a)
    {
        $salida = '';
        foreach (self::tandas($texto) as $tanda) {
            // Los saltos entre tandas se conservan tal cual: no se traducen.
            if (trim($tanda) === '') {
                $salida .= $tanda;
                continue;
            }
            $r = Http::timeout(30)
                ->asForm()
                ->post('https://translate.googleapis.com/translate_a/single?client=gtx&dt=t'
                    . '&sl=' . $de . '&tl=' . $a, ['q' => $tanda]);

            if (!$r->successful()) {
                self::$ultimoError = 'Google respondió ' . $r->status();
                return null;
            }
            // Formato: [[["Hello","Hola",...], ["world","mundo",...]], null, "es", ...]
            $partes = $r->json('0');
            if (!is_array($partes)) {
                self::$ultimoError = 'Google devolvió un formato inesperado';
                return null;
            }
            foreach ($partes as $p) {
                $salida .= is_array($p) && isset($p[0]) ? $p[0] : '';
            }
        }
        return $salida;
    }

    /**
     * Parte el texto en tandas de hasta MAX_TANDA caracteres sin cortar
     * párrafos (salvo uno solo más largo que eso, que va por oraciones).
     * Los separadores de párrafo van como tandas propias para no perderlos.
     */
    protected static function tandas($texto)
    {
        $piezas = preg_split('/(\R\s*\R?)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $tandas = [];
        $actual = '';
        foreach ($piezas as $pieza) {
            if (mb_strlen($actual . $pieza) > self::MAX_TANDA && $actual !== '') {
                $tandas[] = $actual;
                $actual = '';
            }
            if (mb_strlen($pieza) > self::MAX_TANDA) {
                foreach (preg_split('/(?<=[.!?])\s+/u', $pieza) as $oracion) {
                    if (mb_strlen($actual . ' ' . $oracion) > self::MAX_TANDA && $actual !== '') {
                        $tandas[] = $actual;
                        $actual = '';
                    }
                    $actual .= ($actual === '' ? '' : ' ') . $oracion;
                }
                continue;
            }
            $actual .= $pieza;
        }
        if ($actual !== '') {
            $tandas[] = $actual;
        }
        return $tandas;
    }

    /**
     * Si de un par (castellano, inglés) hay uno solo, completa el otro.
     * Modifica $datos en el lugar y devuelve un aviso para el usuario
     * (o null si no hizo falta traducir).
     */
    public static function completarPar(array &$datos, $campoEs, $campoEn)
    {
        $es = trim((string) ($datos[$campoEs] ?? ''));
        $en = trim((string) ($datos[$campoEn] ?? ''));

        if ($es !== '' && $en === '') {
            [$origen, $destino, $de, $a, $nombre] = [$campoEs, $campoEn, 'es', 'en', 'al inglés'];
        } elseif ($en !== '' && $es === '') {
            [$origen, $destino, $de, $a, $nombre] = [$campoEn, $campoEs, 'en', 'es', 'al castellano'];
        } else {
            return null;
        }

        $traducido = self::traducir($datos[$origen], $de, $a);
        if ($traducido === null) {
            return "No pude traducir la historia {$nombre} (" . self::ultimoError() . '): quedó vacía.';
        }
        $datos[$destino] = NotasWiki::limpiar($traducido);
        return "Traduje la historia {$nombre} con " . self::motor() . ': conviene revisarla.';
    }
}
