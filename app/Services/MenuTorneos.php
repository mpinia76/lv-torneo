<?php

namespace App\Services;

use App\Torneo;
use Illuminate\Support\Facades\Cache;

/**
 * Los torneos ordenados como los busca el visitante: por país o región,
 * después por competencia, y recién ahí por temporada.
 *
 *   Argentina                      <- zona "local", siempre primera
 *   Internacional: Sudamérica,     <- ambito Internacional, por región
 *                  Mundial, Europa…
 *   Países: España, Inglaterra…    <- el resto de los nacionales, por país
 *
 * Una "competencia" junta las ediciones que tienen el mismo nombre
 * ("Copa Argentina" 2019, 2022, 2023…). Se agrupa por nombre y no por el id de
 * la fuente externa porque ahí Apertura, Clausura y Liga Profesional son la
 * misma competencia, y en el menú tienen que verse como cosas distintas.
 *
 * Todo sale de la lista de torneos que ya estaba cacheada para los menús, y el
 * armado también se cachea: App\Torneo tira las dos claves al guardar o borrar.
 */
class MenuTorneos
{
    /** Lista cruda de torneos (la misma que usaban los menús de siempre). */
    const CACHE_TORNEOS = 'torneos.menu';

    /** Estructura ya agrupada por zona y competencia. */
    const CACHE_ZONAS = 'torneos.menu.zonas';

    const CACHE_SEGUNDOS = 3600;

    /** El país de la casa: va primero y es donde cae un torneo sin país cargado. */
    const PAIS_LOCAL = 'Argentina';

    /**
     * Regiones de los internacionales: lo que se escribe en torneos.region
     * (en minúscula y sin acentos) => [clave, nombre visible, orden].
     */
    protected static $regiones = [
        'conmebol'   => ['conmebol', 'Sudamérica', 1],
        'sudamerica' => ['conmebol', 'Sudamérica', 1],
        'fifa'       => ['fifa', 'Mundial', 2],
        'mundial'    => ['fifa', 'Mundial', 2],
        'uefa'       => ['uefa', 'Europa', 3],
        'europa'     => ['uefa', 'Europa', 3],
        'concacaf'   => ['concacaf', 'Norte y Centroamérica', 4],
        'afc'        => ['afc', 'Asia', 5],
        'asia'       => ['afc', 'Asia', 5],
        'caf'        => ['caf', 'África', 6],
        'africa'     => ['caf', 'África', 6],
        'ofc'        => ['ofc', 'Oceanía', 7],
        'oceania'    => ['ofc', 'Oceanía', 7],
    ];

    /** Memo por request. */
    protected static $zonasMemo = null;

    // ─────────────────────────────────────────────────────────────────────
    //  Datos
    // ─────────────────────────────────────────────────────────────────────

    /** Todos los torneos, más nuevos primero. */
    public static function torneos()
    {
        return Cache::remember(self::CACHE_TORNEOS, self::CACHE_SEGUNDOS, function () {
            return Torneo::orderBy('year', 'DESC')->orderBy('id', 'DESC')->get();
        });
    }

    public static function olvidar()
    {
        Cache::forget(self::CACHE_TORNEOS);
        Cache::forget(self::CACHE_ZONAS);
        self::$zonasMemo = null;
    }

    /**
     * Zonas con sus competencias, listas para pintar.
     *
     * [
     *   'version' => 'a1b2c3…',
     *   'zonas'   => [
     *      clave => [
     *        'clave' => 'p-argentina', 'nombre' => 'Argentina', 'grupo' => 'local'|'inter'|'paises',
     *        'bandera' => 'Argentina.gif'|null, 'orden' => int, 'ultimo' => 2025, 'ediciones' => 312,
     *        'competencias' => [
     *           ['clave', 'nombre', 'tipo', 'escudo', 'ultimo' => 2025, 'historica' => bool,
     *            'ediciones' => [['id' => 812, 'year' => '2025'], …]],
     *        ],
     *      ],
     *   ],
     * ]
     */
    public static function zonas()
    {
        if (self::$zonasMemo !== null) {
            return self::$zonasMemo;
        }

        self::$zonasMemo = Cache::remember(self::CACHE_ZONAS, self::CACHE_SEGUNDOS, function () {
            return self::armar(self::torneos());
        });

        return self::$zonasMemo;
    }

    /** Separado de zonas() para poder probarlo con cualquier colección. */
    public static function armar($torneos)
    {
        $zonas = [];
        $firma = [];

        foreach ($torneos as $t) {
            // Parciales: solo tienen los partidos del ciclo de un DT. A esos se
            // llega desde las fichas, no desde el menú.
            if (!empty($t->parcial)) {
                continue;
            }

            $zona  = self::zonaDe($t);
            $clave = $zona['clave'];

            if (!isset($zonas[$clave])) {
                $zonas[$clave] = $zona + ['ultimo' => 0, 'ediciones' => 0, 'competencias' => []];
            }

            $cClave = self::claveCompetencia($t->nombre);
            if (!isset($zonas[$clave]['competencias'][$cClave])) {
                // La colección viene de más nueva a más vieja: la primera edición
                // que aparece es la que da nombre, tipo y escudo.
                $zonas[$clave]['competencias'][$cClave] = [
                    'clave'     => $cClave,
                    'nombre'    => trim((string) $t->nombre),
                    'tipo'      => $t->tipo === 'Copa' ? 'Copa' : 'Liga',
                    'escudo'    => $t->escudo ?: null,
                    'ultimo'    => self::anio($t->year),
                    'historica' => false,
                    'ediciones' => [],
                ];
            }

            $comp = &$zonas[$clave]['competencias'][$cClave];
            if (!$comp['escudo'] && $t->escudo) {
                $comp['escudo'] = $t->escudo;
            }
            $comp['ediciones'][] = ['id' => (int) $t->id, 'year' => (string) $t->year];
            unset($comp);

            $zonas[$clave]['ediciones']++;
            $zonas[$clave]['ultimo'] = max($zonas[$clave]['ultimo'], self::anio($t->year));

            $firma[] = $t->id . ':' . $t->updated_at;
        }

        foreach ($zonas as &$z) {
            // Histórica: no se juega hace más de un año respecto de lo más nuevo
            // de su zona (Metropolitano, Apertura…). Van plegadas al final.
            foreach ($z['competencias'] as &$c) {
                $c['historica'] = $c['ultimo'] < $z['ultimo'] - 1;
            }
            unset($c);

            $comps = array_values($z['competencias']);
            usort($comps, function ($a, $b) {
                if ($a['historica'] !== $b['historica']) return $a['historica'] ? 1 : -1;
                if ($a['tipo'] !== $b['tipo'])           return $a['tipo'] === 'Liga' ? -1 : 1;
                if ($a['ultimo'] !== $b['ultimo'])       return $b['ultimo'] - $a['ultimo'];
                $n = count($b['ediciones']) - count($a['ediciones']);
                return $n !== 0 ? $n : strcmp(self::normalizar($a['nombre']), self::normalizar($b['nombre']));
            });
            $z['competencias'] = $comps;
        }
        unset($z);

        uasort($zonas, function ($a, $b) {
            $g = ['local' => 0, 'inter' => 1, 'paises' => 2];
            if ($g[$a['grupo']] !== $g[$b['grupo']]) return $g[$a['grupo']] - $g[$b['grupo']];
            if ($a['orden'] !== $b['orden'])         return $a['orden'] - $b['orden'];
            return strcmp(self::normalizar($a['nombre']), self::normalizar($b['nombre']));
        });

        return [
            'version' => substr(md5(implode('|', $firma)), 0, 10),
            'zonas'   => $zonas,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Consultas para las vistas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Para la barra del torneo: su zona y las otras temporadas de la misma
     * competencia. Anda también con un torneo parcial (sin temporadas).
     */
    public static function contexto($torneoId)
    {
        $torneo = self::torneos()->firstWhere('id', (int) $torneoId);
        if (!$torneo) {
            return null;
        }

        $zona = self::zonaDe($torneo);
        $comp = null;

        $datos = self::zonas();
        if (!empty($datos['zonas'][$zona['clave']])) {
            $cClave = self::claveCompetencia($torneo->nombre);
            foreach ($datos['zonas'][$zona['clave']]['competencias'] as $c) {
                if ($c['clave'] === $cClave) {
                    $comp = $c;
                    break;
                }
            }
        }

        return [
            'torneo'      => $torneo,
            'zona'        => $zona,
            'competencia' => $comp,
        ];
    }

    /**
     * Lo que baja el menú desplegable, en el formato más corto posible: en el
     * mundo entero pueden ser miles de ediciones y esto se pide una sola vez.
     */
    public static function paraJson()
    {
        $datos = self::zonas();
        $zonas = [];

        foreach ($datos['zonas'] as $z) {
            $comps = [];
            foreach ($z['competencias'] as $c) {
                $ed = [];
                foreach ($c['ediciones'] as $e) {
                    $ed[] = [$e['id'], $e['year']];
                }
                $comps[] = [
                    'n'  => $c['nombre'],
                    't'  => $c['tipo'] === 'Copa' ? 'C' : 'L',
                    'e'  => $c['escudo'] ? url('images/' . $c['escudo']) : '',
                    'h'  => $c['historica'] ? 1 : 0,
                    'ed' => $ed,
                ];
            }

            $zonas[] = [
                'c'  => $z['clave'],
                'n'  => $z['nombre'],
                'g'  => $z['grupo'],
                'b'  => $z['bandera'] ? url('images/' . $z['bandera']) : '',
                'cs' => $comps,
            ];
        }

        return [
            'v'     => $datos['version'],
            'url'   => route('fechas.ver') . '?torneoId=',
            'zonas' => $zonas,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Reglas
    // ─────────────────────────────────────────────────────────────────────

    /** ['clave', 'nombre', 'grupo', 'bandera', 'orden'] de un torneo. */
    public static function zonaDe($t)
    {
        if ($t->ambito === 'Internacional') {
            $region = self::normalizar($t->region);

            if ($region === '') {
                // Sin región: lo de siempre, los internacionales propios. Los
                // de FIFA se reconocen por el nombre.
                $region = preg_match('/mundial|intercontinental|fifa/', self::normalizar($t->nombre)) ? 'fifa' : 'conmebol';
            }

            if (isset(self::$regiones[$region])) {
                list($clave, $nombre, $orden) = self::$regiones[$region];
            } else {
                $clave  = self::slug($region);
                $nombre = self::capitalizar($t->region);
                $orden  = 50;
            }

            return ['clave' => 'r-' . $clave, 'nombre' => $nombre, 'grupo' => 'inter', 'bandera' => null, 'orden' => $orden];
        }

        $pais = trim((string) $t->pais);
        if ($pais === '') {
            $pais = self::PAIS_LOCAL;
        }
        $pais  = self::capitalizar($pais);
        $local = self::normalizar($pais) === self::normalizar(self::PAIS_LOCAL);

        return [
            'clave'   => 'p-' . self::slug($pais),
            'nombre'  => $pais,
            'grupo'   => $local ? 'local' : 'paises',
            // Mismo criterio que las banderitas de nacionalidad de las fichas.
            'bandera' => removeAccents($pais) . '.gif',
            'orden'   => 0,
        ];
    }

    public static function claveCompetencia($nombre)
    {
        return self::slug($nombre);
    }

    /** "2023/24" -> 2023. */
    protected static function anio($year)
    {
        return preg_match('/\d{4}/', (string) $year, $m) ? (int) $m[0] : 0;
    }

    /** Minúsculas, sin acentos, espacios simples. Sin iconv (ver acentos_y_locale). */
    protected static function normalizar($s)
    {
        $s = mb_strtolower(trim((string) $s), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e',
            'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o',
            'ç' => 'c', 'ã' => 'a', 'õ' => 'o',
        ]);
        return preg_replace('/\s+/', ' ', $s);
    }

    protected static function slug($s)
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', self::normalizar($s)), '-');
    }

    /**
     * "españa" / "ESPAÑA" -> "España". Lo que ya viene con mayúsculas y
     * minúsculas se respeta ("Bosnia y Herzegovina" no pasa a "Y").
     */
    protected static function capitalizar($s)
    {
        $s = trim((string) $s);
        if ($s === mb_strtolower($s, 'UTF-8') || $s === mb_strtoupper($s, 'UTF-8')) {
            return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
        }
        return $s;
    }
}
