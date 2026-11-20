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
 * Una llamada por persona la primera vez; queda en caché 30 días (la vista
 * previa y el guardar no la piden dos veces). Una ficha que no se pudo leer
 * también se guarda —1 día— para no reintentar en cada recálculo.
 */
class LivefutbolPersona
{
    const BASE = 'https://www.livefutbol.com';

    /** Cuántas fichas se bajaron (no de caché) en este request. */
    public static $bajadas = 0;

    /**
     * @param int         $lfId  el número de /pe{ID}/
     * @param string|null $url   la del link de la alineación, si el bookmarklet la trajo
     * @return array|null  nombre, apellido, name, nacimiento (Y-m-d), fallecimiento,
     *                     ciudad, nacionalidad, tipo (Arquero/Defensor/Medio/Delantero),
     *                     altura, peso, foto_url, url
     */
    public static function datos($lfId, $url = null)
    {
        $lfId = (int) $lfId;
        if ($lfId <= 0) return null;

        $clave = 'lf_persona_' . $lfId;
        $guardado = Cache::get($clave);
        if ($guardado !== null) {
            return $guardado === 'nada' ? null : $guardado;
        }

        $urls = [];
        if ($url) $urls[] = (strpos($url, 'http') === 0 ? '' : self::BASE) . $url;
        // Sin el link a mano: el mismo esquema que /match-report/ma{ID}/.
        $urls[] = self::BASE . '/person/pe' . $lfId . '/';
        $urls[] = self::BASE . '/players/pe' . $lfId . '/';

        $datos = null;
        foreach (array_unique($urls) as $u) {
            try {
                self::$bajadas++;
                $html = HttpHelper::getHtmlContent($u);
            } catch (\Exception $e) {
                $html = false;
            }
            if (!$html) continue;
            $datos = self::leer($html);
            if ($datos) {
                $datos['url'] = $u;
                break;
            }
        }

        if ($datos) {
            Cache::put($clave, $datos, now()->addDays(30));
        } else {
            Cache::put($clave, 'nada', now()->addDay());
            Log::info('LivefutbolPersona: no pude leer la ficha de pe' . $lfId);
        }
        return $datos;
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
