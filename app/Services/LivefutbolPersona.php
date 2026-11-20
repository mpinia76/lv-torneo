<?php

namespace App\Services;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * La ficha de una persona en livefutbol: nombre y apellidos por separado, fecha
 * de nacimiento (y de fallecimiento), lugar, nacionalidad, puesto, altura, peso
 * y foto.
 *
 * Lee la página igual que «Importar jugador» por URL
 * (JugadorController::importarProcess): pares <dt>/<dd> dentro de
 * div.contentitem y la foto en #previewArea. Si un día cambia el formato de la
 * página, cambia en los dos lados.
 *
 * Lo usa el importador de detalle (LivefutbolDetalle) para no crear personas
 * a ciegas: con la fecha de nacimiento se aparea con el mismo criterio que el
 * de Transfermarkt, y lo que se crea sale completo.
 *
 * La página la lee el BOOKMARKLET en el navegador (public/js/lf-a-laplanilla.js),
 * no el servidor: «Importar jugador» por URL dejó de andar porque livefutbol
 * bloquea al servidor. leer() queda por si algún día vuelve a poder bajarse.
 */
class LivefutbolPersona
{
    const BASE = 'https://www.livefutbol.com';

    /** Cuántas fichas se bajaron (no de caché) en este request. */
    public static $bajadas = 0;

    /**
     * Tope de segundos para bajar fichas en un mismo request. Cada ficha puede
     * tardar de 1 s a más de un minuto (si livefutbol da 403, HttpHelper
     * reintenta y termina en ScraperAPI con render), y la página muere a los
     * 600 s de nginx. Pasado el tope no se piden más: las que faltan se bajan
     * en el próximo «Recalcular», porque las ya bajadas quedan en caché.
     */
    const TOPE_SEGUNDOS = 150;   // ya no se baja nada desde el servidor; queda por compatibilidad

    /** Cuántas quedaron sin pedir por el tope en este request. */
    public static $postergadas = 0;

    private static $inicio = null;

    /**
     * La ficha de una persona, ya normalizada.
     *
     * NO se baja nada desde el servidor: livefutbol (Cloudflare) no lo deja, y
     * cada intento cuesta reintentos y ScraperAPI. La ficha la lee el
     * bookmarklet desde el navegador del usuario y viaja en el JSON como
     * `ficha` de cada persona. Si viene, se guarda en caché (30 días) por si
     * otro partido trae a la misma persona sin ficha.
     *
     * @param int        $lfId
     * @param array|null $ficha la que trajo el bookmarklet (crudo: dd-mm-aaaa, "178 cm"…)
     * @return array|null nombre, apellido, name, nacimiento (Y-m-d), fallecimiento,
     *                    ciudad, nacionalidad, tipo, altura, peso, foto_url
     */
    public static function datos($lfId, $ficha = null)
    {
        $lfId = (int) $lfId;
        $clave = 'lf_persona_' . $lfId;

        if (is_array($ficha) && $ficha) {
            $d = self::normalizar($ficha);
            if ($d && $lfId > 0) Cache::put($clave, $d, now()->addDays(30));
            return $d;
        }
        if ($lfId <= 0) return null;
        $guardado = Cache::get($clave);
        return is_array($guardado) ? $guardado : null;
    }

    /** Lo que manda el bookmarklet → el mismo formato que leer(). */
    public static function normalizar(array $f)
    {
        // Favorito nuevo: todos los pares de la ficha en `pares`. Se pasan a
        // las claves de siempre según la etiqueta. Las etiquetas cambian con
        // el tiempo («Fecha de nacimiento» pasó a ser «Cumpleaños»): agregar
        // acá las que aparezcan.
        if (!empty($f['pares']) && is_array($f['pares'])) {
            $etiquetas = [
                'nombre' => 'name', 'nombre 2' => 'nombre', 'nombre completo' => 'nombre',
                'apellidos' => 'apellido', 'apellido' => 'apellido',
                'cumpleaños' => 'nacimiento', 'fecha de nacimiento' => 'nacimiento', 'nacimiento' => 'nacimiento',
                'nacido' => 'nacimiento',
                'fecha de fallecimiento' => 'fallecimiento', 'fallecimiento' => 'fallecimiento', 'falleció' => 'fallecimiento',
                'lugar de nacimiento' => 'lugar', 'nacido en' => 'lugar',
                'nacionalidad' => 'nacionalidad',
                'posición' => 'demarcacion', 'demarcación' => 'demarcacion', 'puesto' => 'demarcacion',
                'altura' => 'altura', 'estatura' => 'altura', 'peso' => 'peso',
            ];
            foreach ($f['pares'] as $k => $v) {
                $k = mb_strtolower(trim((string) $k));
                if (isset($etiquetas[$k]) && !isset($f[$etiquetas[$k]])) $f[$etiquetas[$k]] = $v;
            }
            if (empty($f['nombre']) && !empty($f['name'])) $f['nombre'] = $f['name'];
        }

        $fecha = function ($s) {
            if (!$s) return null;
            if (preg_match('/(\d{1,2})[.\-\/](\d{1,2})[.\-\/](\d{4})/', (string) $s, $m)) {
                return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                    ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
            }
            // "14 de marzo de 1990", "14. März 1990", "14 March 1990"
            $meses = ['ene' => 1, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'mär' => 3, 'abr' => 4, 'apr' => 4,
                'may' => 5, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'aug' => 8, 'sep' => 9, 'set' => 9,
                'oct' => 10, 'okt' => 10, 'nov' => 11, 'dic' => 12, 'dec' => 12, 'dez' => 12];
            if (preg_match('/(\d{1,2})\.?\s*(?:de\s+)?([a-zA-Zäé]{3,})\.?\s*(?:de\s+)?(\d{4})/u', mb_strtolower((string) $s), $m)) {
                $mes = $meses[mb_substr($m[2], 0, 3)] ?? null;
                if ($mes && checkdate($mes, (int) $m[1], (int) $m[3])) {
                    return sprintf('%04d-%02d-%02d', $m[3], $mes, $m[1]);
                }
            }
            return null;
        };
        $d = [
            'nombre'        => isset($f['nombre']) ? trim($f['nombre']) : null,
            'apellido'      => isset($f['apellido']) ? trim($f['apellido']) : null,
            'name'          => isset($f['name']) ? trim($f['name']) : null,
            'nacimiento'    => $fecha($f['nacimiento'] ?? null),
            'fallecimiento' => $fecha($f['fallecimiento'] ?? null),
            // «nacido en» a veces es sólo el país: no lo uso de ciudad si es la nacionalidad.
            'ciudad'        => isset($f['lugar']) && trim($f['lugar']) !== trim((string) ($f['nacionalidad'] ?? ''))
                ? mb_substr(trim($f['lugar']), 0, 191) : null,
            'nacionalidad'  => isset($f['nacionalidad']) ? trim($f['nacionalidad']) : null,
            'tipo'          => self::tipoJugador($f['demarcacion'] ?? null),
            'altura'        => null,
            'peso'          => null,
            'foto_url'      => !empty($f['foto']) && preg_match('#^https?://#', $f['foto']) ? $f['foto'] : null,
        ];
        if (!empty($f['altura']) && preg_match('/\d+/', $f['altura'], $m)) {
            $d['altura'] = number_format($m[0] / 100, 2, '.', '');
        }
        if (!empty($f['peso']) && preg_match('/\d+/', $f['peso'], $m)) $d['peso'] = $m[0];

        if (!$d['nombre'] && !$d['apellido'] && !$d['nacimiento']) return null;
        return $d;
    }

    private static function tipoJugador($txt)
    {
        $t = mb_strtolower(trim((string) $txt));
        if ($t === '') return null;
        if (preg_match('/porter|arquer|guardameta|goalkeeper|torwart/u', $t)) return 'Arquero';
        if (preg_match('/defens|lateral|zaguer|central|defender|abwehr/u', $t)) return 'Defensor';
        if (preg_match('/centrocamp|mediocamp|medio|volante|midfield|mittelfeld/u', $t)) return 'Medio';
        if (preg_match('/delanter|atacante|extremo|punta|forward|striker|sturm/u', $t)) return 'Delantero';
        return null;
    }

    /** Los <dt>/<dd> de la ficha. null si la página no es una ficha. */
    public static function leer($html)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xp = new \DOMXPath($dom);

        $dts = $xp->query('//div[@class="contentitem"]/dl/dt');
        $dds = $xp->query('//div[@class="contentitem"]/dl/dd');
        if (!$dts || $dts->length === 0) return null;

        $d = ['nombre' => null, 'apellido' => null, 'name' => null, 'nacimiento' => null,
            'fallecimiento' => null, 'ciudad' => null, 'nacionalidad' => null, 'tipo' => null,
            'altura' => null, 'peso' => null, 'foto_url' => null];

        $fecha = function ($s) {
            try {
                return Carbon::createFromFormat('d-m-Y', trim($s))->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        for ($i = 0; $i < $dts->length && $i < $dds->length; $i++) {
            $dt = trim($dts[$i]->textContent);
            $dd = trim(preg_replace('/\s+/u', ' ', $dds[$i]->textContent));
            if ($dd === '') continue;
            switch ($dt) {
                case 'Nombre':
                    if ($d['name'] === null) $d['name'] = $dd;   // la primera es el nombre mostrado
                    $d['nombre'] = $dd;
                    break;
                case 'Apellidos':           $d['apellido'] = $dd; break;
                case 'Fecha de nacimiento': $d['nacimiento'] = $fecha($dd); break;
                case 'Fecha de fallecimiento': $d['fallecimiento'] = $fecha($dd); break;
                case 'Lugar de nacimiento': $d['ciudad'] = $dd; break;
                case 'Nacionalidad':        $d['nacionalidad'] = $dd; break;
                case 'Demarcación':
                    $d['tipo'] = ['Portero' => 'Arquero', 'Defensa' => 'Defensor',
                        'Centrocampista' => 'Medio', 'Delantero' => 'Delantero'][$dd] ?? null;
                    break;
                case 'Altura':
                    if (preg_match('/\d+/', $dd, $m)) $d['altura'] = number_format($m[0] / 100, 2, '.', '');
                    break;
                case 'Peso':
                    if (preg_match('/\d+/', $dd, $m)) $d['peso'] = $m[0];
                    break;
            }
        }

        $img = $xp->query('//div[@id="previewArea"]/img');
        if ($img && $img->length > 0) {
            $src = $img[0]->getAttribute('src');
            if ($src && strpos($src, 'avatar-player.jpg') === false) $d['foto_url'] = $src;
        }

        if (!$d['nombre'] && !$d['apellido']) return null;
        return $d;
    }

    /**
     * Baja la foto (como «Importar jugador»: directo, sin ScraperAPI) y
     * devuelve el nombre del archivo para `personas.foto`, o null.
     */
    public static function bajarFoto($url)
    {
        if (!$url) return null;
        try {
            $r = (new Client())->get($url, ['http_errors' => false, 'timeout' => 10]);
            if ($r->getStatusCode() !== 200) return null;
            $info = pathinfo((string) parse_url($url, PHP_URL_PATH));
            $archivo = rtrim($info['filename'] ?? '', '.');
            if ($archivo === '') return null;
            $nombre = $archivo . '.' . (($info['extension'] ?? '') ?: 'jpg');
            file_put_contents(public_path('images/') . $nombre, $r->getBody()->getContents());
            return $nombre;
        } catch (\Exception $e) {
            Log::info('LivefutbolPersona: sin foto ' . $url . ': ' . $e->getMessage());
            return null;
        }
    }
}
