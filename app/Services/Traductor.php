<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Traduce texto libre entre castellano e inglés (hoy: la historia de los clubes).
 *
 * Prueba los motores en orden y se queda con el primero que responde:
 *  1. DeepL, sólo si hay DEEPL_API_KEY en el .env. La mejor calidad; el plan
 *     gratis da 500.000 caracteres por mes.
 *  2. Google, por el endpoint público (translate.googleapis.com, client=gtx).
 *     No es una API oficial y desde la IP de Vultr contesta 429 (lo bloquea por
 *     ser un datacenter): queda por si algún día responde.
 *  3. MyMemory (api.mymemory.translated.net). Gratis y sin clave, pero con
 *     tope: 5.000 caracteres por día anónimo, 50.000 si se le pasa un mail
 *     (MYMEMORY_EMAIL en el .env). Acepta hasta 500 bytes por pedido, así que
 *     el texto va de a oraciones.
 *
 * Nunca tira excepción: si todos fallan devuelve null y deja los motivos en
 * ultimoError(), para que quien llama guarde igual y avise.
 */
class Traductor
{
    protected static $ultimoError = null;
    protected static $ultimoMotor = null;

    public static function ultimoError()
    {
        return self::$ultimoError;
    }

    /** El motor que hizo la última traducción que salió bien. */
    public static function motor()
    {
        return self::$ultimoMotor;
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
        self::$ultimoMotor = null;
        $texto = (string) $texto;
        if (trim($texto) === '') {
            return $texto;
        }

        $motores = [];
        if (config('services.deepl.key')) {
            $motores['DeepL'] = 'deepl';
        }
        $motores['Google'] = 'google';
        $motores['MyMemory'] = 'mymemory';

        $errores = [];
        foreach ($motores as $nombre => $metodo) {
            try {
                $salida = self::$metodo($texto, $de, $a);
            } catch (\Throwable $e) {
                $errores[$nombre] = $e->getMessage();
                continue;
            }
            if (is_string($salida) && trim($salida) !== '') {
                self::$ultimoMotor = $nombre;
                return $salida;
            }
            $errores[$nombre] = is_array($salida) ? $salida['error'] : 'respuesta vacía';
        }

        self::$ultimoError = implode('; ', array_map(
            fn ($m, $e) => "{$m}: {$e}", array_keys($errores), $errores
        ));
        Log::warning("Traductor {$de}->{$a}: " . self::$ultimoError);
        return null;
    }

    /** Los motores devuelven el texto, o ['error' => motivo]. */
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
            return ['error' => 'respondió ' . $r->status()];
        }
        return $r->json('translations.0.text');
    }

    protected static function google($texto, $de, $a)
    {
        $salida = '';
        foreach (self::tandas($texto, 4500) as $tanda) {
            // Los saltos entre párrafos se conservan tal cual: no se traducen.
            if (trim($tanda) === '') {
                $salida .= $tanda;
                continue;
            }
            $r = Http::timeout(30)
                ->asForm()
                ->post('https://translate.googleapis.com/translate_a/single?client=gtx&dt=t'
                    . '&sl=' . $de . '&tl=' . $a, ['q' => $tanda]);

            if (!$r->successful()) {
                return ['error' => 'respondió ' . $r->status()];
            }
            // Formato: [[["Hello","Hola",...], ["world","mundo",...]], null, "es", ...]
            $partes = $r->json('0');
            if (!is_array($partes)) {
                return ['error' => 'formato inesperado'];
            }
            foreach ($partes as $p) {
                $salida .= is_array($p) && isset($p[0]) ? $p[0] : '';
            }
        }
        return $salida;
    }

    protected static function mymemory($texto, $de, $a)
    {
        $mail = config('services.mymemory.email');
        $salida = '';
        // 500 bytes por pedido; 450 caracteres deja margen para los acentos.
        foreach (self::tandas($texto, 450) as $tanda) {
            if (trim($tanda) === '') {
                $salida .= $tanda;
                continue;
            }
            // Los espacios del borde no viajan bien: se sacan y se reponen.
            preg_match('/^(\s*)(.*?)(\s*)$/su', $tanda, $m);

            $r = Http::timeout(30)->get('https://api.mymemory.translated.net/get', array_filter([
                'q' => $m[2],
                'langpair' => $de . '|' . $a,
                'de' => $mail,
            ]));

            if (!$r->successful()) {
                return ['error' => 'respondió ' . $r->status()];
            }
            // Ojo: cuando se agota la cuota diaria contesta 200 igual, con el
            // aviso en responseStatus/responseDetails y el aviso como "traducción".
            $estado = (int) $r->json('responseStatus');
            $traduccion = $r->json('responseData.translatedText');
            if ($estado !== 200 || !is_string($traduccion) || $traduccion === '') {
                return ['error' => trim(($estado ?: '') . ' ' . $r->json('responseDetails'))];
            }
            $salida .= $m[1] . html_entity_decode($traduccion, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $m[3];
        }
        return $salida;
    }

    /**
     * Parte el texto en tandas de hasta $max caracteres. Los saltos de párrafo
     * van como tandas propias (no se traducen, se copian). Un párrafo más largo
     * que $max se corta por oraciones; una oración más larga, por palabras.
     */
    protected static function tandas($texto, $max)
    {
        $piezas = preg_split('/(\R\s*)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $tandas = [];
        foreach ($piezas as $pieza) {
            if (trim($pieza) === '' || mb_strlen($pieza) <= $max) {
                $tandas[] = $pieza;
                continue;
            }
            $actual = '';
            foreach (preg_split('/(?<=[.!?;])(?=\s)/u', $pieza) as $oracion) {
                if ($actual !== '' && mb_strlen($actual . $oracion) > $max) {
                    $tandas[] = $actual;
                    $actual = '';
                }
                while (mb_strlen($oracion) > $max) {
                    $corte = mb_strrpos(mb_substr($oracion, 0, $max), ' ') ?: $max;
                    $tandas[] = mb_substr($oracion, 0, $corte);
                    $oracion = mb_substr($oracion, $corte);
                }
                $actual .= $oracion;
            }
            if ($actual !== '') {
                $tandas[] = $actual;
            }
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
