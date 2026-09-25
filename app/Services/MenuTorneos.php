<?php

namespace App\Services;

use App\Torneo;
use Illuminate\Support\Facades\Cache;

/**
 * Los torneos ordenados como los busca el visitante: por país o región,
 * después por competencia, y recién ahí por temporada.
 *
 *   Argentina                      <- zona "local", siempre primera
 *   Mundial:    Torneos FIFA
 *   Sudamérica: Torneos Conmebol,  <- por confederación: primero sus torneos
 *               Brasil, Chile…        internacionales (ambito Internacional,
 *   Europa:     Torneos UEFA,         por región) y después los países
 *               Alemania, España…     (nacionales, por país)
 *   …
 *   Otros países                   <- país que no está en $confederaciones
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
     * Secciones del menú, en orden. 'local' no lleva título (es Argentina sola).
     * Clave => título de la sección.
     */
    protected static $grupos = [
        'local'    => '',
        'fifa'     => 'Mundial',
        'conmebol' => 'Sudamérica · Conmebol',
        'uefa'     => 'Europa · UEFA',
        'concacaf' => 'Norte y Centroamérica · Concacaf',
        'afc'      => 'Asia · AFC',
        'caf'      => 'África · CAF',
        'ofc'      => 'Oceanía · OFC',
        'otros'    => 'Otros países',
    ];

    /** Nombre de la zona con los torneos internacionales de cada confederación. */
    protected static $nombresRegion = [
        'fifa'     => 'Torneos FIFA',
        'conmebol' => 'Torneos Conmebol',
        'uefa'     => 'Torneos UEFA',
        'concacaf' => 'Torneos Concacaf',
        'afc'      => 'Torneos AFC',
        'caf'      => 'Torneos CAF',
        'ofc'      => 'Torneos OFC',
    ];

    /**
     * Lo que se escribe en torneos.region (en minúscula y sin acentos) =>
     * confederación.
     */
    protected static $regiones = [
        'conmebol' => 'conmebol', 'sudamerica' => 'conmebol',
        'fifa'     => 'fifa',     'mundial'    => 'fifa',
        'uefa'     => 'uefa',     'europa'     => 'uefa',
        'concacaf' => 'concacaf',
        'afc'      => 'afc',      'asia'       => 'afc',
        'caf'      => 'caf',      'africa'     => 'caf',
        'ofc'      => 'ofc',      'oceania'    => 'ofc',
    ];

    /**
     * País (como en torneos.pais, en minúscula y sin acentos) => confederación.
     * El que no esté acá cae en «Otros países»: se agrega una línea y listo.
     */
    protected static $confederaciones = [
        // Conmebol
        'argentina' => 'conmebol', 'bolivia' => 'conmebol', 'brasil' => 'conmebol', 'chile' => 'conmebol',
        'colombia' => 'conmebol', 'ecuador' => 'conmebol', 'paraguay' => 'conmebol', 'peru' => 'conmebol',
        'uruguay' => 'conmebol', 'venezuela' => 'conmebol',
        // UEFA
        'albania' => 'uefa', 'alemania' => 'uefa', 'andorra' => 'uefa', 'armenia' => 'uefa', 'austria' => 'uefa',
        'azerbaiyan' => 'uefa', 'belgica' => 'uefa', 'bielorrusia' => 'uefa', 'bosnia y herzegovina' => 'uefa',
        'bulgaria' => 'uefa', 'chipre' => 'uefa', 'croacia' => 'uefa', 'dinamarca' => 'uefa', 'escocia' => 'uefa',
        'eslovaquia' => 'uefa', 'eslovenia' => 'uefa', 'espana' => 'uefa', 'estonia' => 'uefa', 'finlandia' => 'uefa',
        'francia' => 'uefa', 'gales' => 'uefa', 'georgia' => 'uefa', 'gibraltar' => 'uefa', 'grecia' => 'uefa',
        'holanda' => 'uefa', 'hungria' => 'uefa', 'inglaterra' => 'uefa', 'irlanda' => 'uefa',
        'irlanda del norte' => 'uefa', 'islandia' => 'uefa', 'islas feroe' => 'uefa', 'israel' => 'uefa',
        'italia' => 'uefa', 'kazajistan' => 'uefa', 'kosovo' => 'uefa', 'letonia' => 'uefa',
        'liechtenstein' => 'uefa', 'lituania' => 'uefa', 'luxemburgo' => 'uefa', 'macedonia' => 'uefa',
        'macedonia del norte' => 'uefa', 'malta' => 'uefa', 'moldavia' => 'uefa', 'montenegro' => 'uefa',
        'noruega' => 'uefa', 'paises bajos' => 'uefa', 'polonia' => 'uefa', 'portugal' => 'uefa',
        'republica checa' => 'uefa', 'chequia' => 'uefa', 'rumania' => 'uefa', 'rusia' => 'uefa',
        'san marino' => 'uefa', 'serbia' => 'uefa', 'suecia' => 'uefa', 'suiza' => 'uefa', 'turquia' => 'uefa',
        'ucrania' => 'uefa',
        // Concacaf
        'belice' => 'concacaf', 'canada' => 'concacaf', 'costa rica' => 'concacaf', 'cuba' => 'concacaf',
        'curazao' => 'concacaf', 'el salvador' => 'concacaf', 'estados unidos' => 'concacaf', 'eeuu' => 'concacaf',
        'guatemala' => 'concacaf', 'guyana' => 'concacaf', 'haiti' => 'concacaf', 'honduras' => 'concacaf',
        'jamaica' => 'concacaf', 'mexico' => 'concacaf', 'nicaragua' => 'concacaf', 'panama' => 'concacaf',
        'puerto rico' => 'concacaf', 'republica dominicana' => 'concacaf', 'surinam' => 'concacaf',
        'trinidad y tobago' => 'concacaf',
        // AFC (Australia juega en Asia desde 2006)
        'arabia saudita' => 'afc', 'arabia saudi' => 'afc', 'australia' => 'afc', 'barein' => 'afc',
        'catar' => 'afc', 'qatar' => 'afc', 'china' => 'afc', 'corea del sur' => 'afc', 'emiratos arabes unidos' => 'afc',
        'emiratos arabes' => 'afc', 'filipinas' => 'afc', 'hong kong' => 'afc', 'india' => 'afc',
        'indonesia' => 'afc', 'irak' => 'afc', 'iran' => 'afc', 'japon' => 'afc', 'jordania' => 'afc',
        'kuwait' => 'afc', 'libano' => 'afc', 'malasia' => 'afc', 'oman' => 'afc', 'singapur' => 'afc',
        'siria' => 'afc', 'tailandia' => 'afc', 'uzbekistan' => 'afc', 'vietnam' => 'afc',
        // CAF
        'angola' => 'caf', 'argelia' => 'caf', 'camerun' => 'caf', 'costa de marfil' => 'caf', 'egipto' => 'caf',
        'ghana' => 'caf', 'kenia' => 'caf', 'libia' => 'caf', 'mali' => 'caf', 'marruecos' => 'caf',
        'nigeria' => 'caf', 'rd congo' => 'caf', 'senegal' => 'caf', 'sudafrica' => 'caf', 'tunez' => 'caf',
        'zambia' => 'caf',
        // OFC
        'fiyi' => 'ofc', 'nueva zelanda' => 'ofc', 'tahiti' => 'ofc',
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
     *        'clave' => 'p-argentina', 'nombre' => 'Argentina', 'grupo' => 'local'|'conmebol'|'uefa'|…,
     *        'bandera' => 'Argentina.gif'|null, 'escudo' => 'confederaciones/uefa.png'|null, 'orden' => int, 'ultimo' => 2025, 'ediciones' => 312,
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

        $ordenGrupos = array_flip(array_keys(self::$grupos));
        uasort($zonas, function ($a, $b) use ($ordenGrupos) {
            $ga = $ordenGrupos[$a['grupo']];
            $gb = $ordenGrupos[$b['grupo']];
            if ($ga !== $gb)                         return $ga - $gb;
            if ($a['orden'] !== $b['orden'])         return $a['orden'] - $b['orden'];
            return strcmp(self::normalizar($a['nombre']), self::normalizar($b['nombre']));
        });

        // Los escudos de confederación también entran en la versión: al subir
        // uno nuevo cambia la URL del JSON y el navegador no usa la vieja.
        foreach ($zonas as $z) {
            if (!empty($z['escudo'])) {
                $firma[] = $z['escudo'];
            }
        }

        return [
            'version' => substr(md5(implode('|', $firma)), 0, 10),
            'zonas'   => $zonas,
        ];
    }

    /** Títulos de las secciones del menú (clave del grupo => título). */
    public static function titulosGrupos()
    {
        return self::$grupos;
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
                'es' => !empty($z['escudo']) ? url('images/' . $z['escudo']) : '',
                'cs' => $comps,
            ];
        }

        return [
            'v'      => $datos['version'],
            'url'    => route('fechas.ver') . '?torneoId=',
            'grupos' => self::$grupos,
            'zonas'  => $zonas,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Reglas
    // ─────────────────────────────────────────────────────────────────────

    /**
     * ['clave', 'nombre', 'grupo', 'bandera', 'orden'] de un torneo.
     * 'grupo' es la sección del menú (ver $grupos); 'orden' pone los torneos
     * internacionales de la confederación (0) antes que sus países (1).
     */
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
                $conf   = self::$regiones[$region];
                $clave  = $conf;
                $nombre = self::$nombresRegion[$conf];
            } else {
                // Región escrita de otra forma: queda en «Otros», con su nombre.
                $conf   = 'otros';
                $clave  = self::slug($region);
                $nombre = self::capitalizar($t->region);
            }

            return [
                'clave'   => 'r-' . $clave,
                'nombre'  => $nombre,
                'grupo'   => $conf,
                'bandera' => null,
                'escudo'  => self::escudoConfederacion($conf),
                'orden'   => 0,
            ];
        }

        $pais = trim((string) $t->pais);
        if ($pais === '') {
            $pais = self::PAIS_LOCAL;
        }
        $pais  = self::capitalizar($pais);
        $norm  = self::normalizar($pais);
        $local = $norm === self::normalizar(self::PAIS_LOCAL);

        if ($local) {
            $grupo = 'local';
        } else {
            $grupo = isset(self::$confederaciones[$norm]) ? self::$confederaciones[$norm] : 'otros';
        }

        return [
            'clave'   => 'p-' . self::slug($pais),
            'nombre'  => $pais,
            'grupo'   => $grupo,
            // Mismo criterio que las banderitas de nacionalidad de las fichas.
            'bandera' => removeAccents($pais) . '.gif',
            'escudo'  => null,
            'orden'   => 1,
        ];
    }

    /** Carpeta (dentro de public/images) con los escudos de las confederaciones. */
    const CARPETA_CONFEDERACIONES = 'confederaciones';

    /**
     * Escudo de la confederación: public/images/confederaciones/<clave>.svg|png|webp
     * (fifa, conmebol, uefa, concacaf, afc, caf, ofc). Si no está el archivo
     * devuelve null y la zona sigue con el globito, así no se piden imágenes
     * que no existen. Ojo: el menú se cachea una hora (MenuTorneos::olvidar()).
     */
    public static function escudoConfederacion($conf)
    {
        if (!isset(self::$nombresRegion[$conf])) {
            return null;
        }
        foreach (['svg', 'png', 'webp'] as $ext) {
            $archivo = self::CARPETA_CONFEDERACIONES . '/' . $conf . '.' . $ext;
            if (is_file(public_path('images/' . $archivo))) {
                return $archivo;
            }
        }
        return null;
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
