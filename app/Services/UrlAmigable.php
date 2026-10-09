<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * URLs amigables de las fichas públicas: /jugador/250-gervasio-nunez en vez de
 * /verJugador?jugadorId=250.
 *
 * El id manda y el slug es decorativo: /jugador/250, /jugador/250-cualquiera o
 * la URL vieja con ?jugadorId=250 llevan con un 301 a la URL correcta (lo hace
 * App\Http\Middleware\UrlAmigable). Si el nombre cambia, el slug viejo sigue
 * llevando a la ficha.
 *
 * Las vistas NO cambiaron: siguen llamando route('jugadores.ver', ['jugadorId'
 * => $id]) y App\Routing\UrlIdioma arma la URL nueva. El slug sale del nombre
 * en la base; para no hacer una consulta por cada link, durante una página
 * pública los links salen con una marca (~ua.j.250~) y al terminar
 * App\Http\Middleware\UrlAmigableSlugs las completa con UNA consulta por tipo.
 * Fuera de eso (admin, consola, redirecciones) se consulta en el momento, con
 * memo por request.
 *
 * El slug no se traduce: es el mismo en /jugador/... y en /en/jugador/...
 * Se arma con Str::slug (transliteración de Laravel), NUNCA con
 * iconv('ASCII//TRANSLIT'): con el locale del hosting convierte la ó en «?».
 */
class UrlAmigable
{
    /**
     * nombre de la ruta => [letra de la marca, parámetro de la URL vieja,
     * path nuevo, path viejo, (tramo final)]. Las URLs viejas se registran con el nombre
     * '<ruta>.viejo' (ver routes/web.php).
     */
    const RUTAS = [
        'jugadores.ver'  => ['j', 'jugadorId', 'jugador', 'verJugador'],
        'tecnicos.ver'   => ['t', 'tecnicoId', 'tecnico', 'verTecnico'],
        'arbitros.ver'   => ['a', 'arbitroId', 'arbitro', 'verArbitro'],
        'equipos.ver'    => ['e', 'equipoId',  'equipo',  'verEquipo'],
        'fechas.ver'     => ['o', 'torneoId',  'torneo',  'verFechas'],
        'fechas.detalle' => ['p', 'partidoId', 'partido', 'detalleFecha'],

        // Secciones del torneo: /torneo/20-superliga-2019-2020/posiciones. El
        // quinto dato es el tramo que va después del ref.
        'grupos.posicionesPublic'    => ['o', 'torneoId', 'torneo', 'tabla',              'posiciones'],
        'grupos.goleadoresPublic'    => ['o', 'torneoId', 'torneo', 'goleadores',         'goleadores'],
        'grupos.tarjetasPublic'      => ['o', 'torneoId', 'torneo', 'tarjetero',          'tarjetas'],
        'grupos.arqueros'            => ['o', 'torneoId', 'torneo', 'arqueros',           'arqueros'],
        'grupos.jugadores'           => ['o', 'torneoId', 'torneo', 'tablaJugadores',     'jugadores'],
        'grupos.tecnicos'            => ['o', 'torneoId', 'torneo', 'tecnicos',           'tecnicos'],
        'torneos.plantillas'         => ['o', 'torneoId', 'torneo', 'plantillas',         'plantillas'],
        'torneos.promediosPublic'    => ['o', 'torneoId', 'torneo', 'descensos',          'promedios'],
        'torneos.acumulado'          => ['o', 'torneoId', 'torneo', 'acumulado',          'acumulado'],
        'torneos.estadisticasTorneo' => ['o', 'torneoId', 'torneo', 'estadisticasTorneo', 'estadisticas'],
    ];

    /** Path de la ruta nueva: "jugador/{ref}" o "torneo/{ref}/posiciones". */
    public static function path($ruta)
    {
        $c = self::RUTAS[$ruta];
        return $c[2] . '/{ref}' . (isset($c[4]) ? '/' . $c[4] : '');
    }

    /**
     * Historial entre dos equipos: route('torneos.historiales', ['equipo1' =>
     * 2, 'equipo2' => 3]) -> /historial/2-racing-club/3-independiente (ruta
     * 'torneos.historial'). Sin los dos equipos sale /historiales, la página
     * para elegirlos. Las dos órdenes valen (se muestra a la izquierda el
     * primero), pero la canonical va siempre con el id menor primero.
     */
    const DUELO = ['torneos.historiales', 'torneos.historial', 'equipo1', 'equipo2'];

    /** Largo máximo del slug (se corta en un guion). */
    const LARGO = 80;

    /** letra => [id => slug]; '' = existe pero sin texto; null = no existe. */
    protected static $memo = [];

    /** letra => true cuando ya se cargó la tabla entera (torneos, equipos). */
    protected static $completos = [];

    /** Durante una página pública: los links salen con marca (ver completar()). */
    protected static $diferir = false;

    // ─────────────────────────────────────────────────────────────────────

    public static function config($ruta)
    {
        return self::RUTAS[$ruta] ?? null;
    }

    public static function diferir($si)
    {
        self::$diferir = (bool) $si;
    }

    /** Lo que va en {ref}: "250-gervasio-nunez", "250" sin nombre, o la marca. */
    public static function ref($ruta, $id)
    {
        $letra = self::RUTAS[$ruta][0];
        $id = (int) $id;

        if (self::$diferir && !array_key_exists($id, self::$memo[$letra] ?? [])) {
            return '~ua.' . $letra . '.' . $id . '~';
        }

        return self::refCon($id, self::slug($ruta, $id));
    }

    public static function refCon($id, $slug)
    {
        return $slug === null || $slug === '' ? (string) $id : $id . '-' . $slug;
    }

    /** Slug de una ficha: '' si no tiene nombre, null si el id no existe. */
    public static function slug($ruta, $id)
    {
        $letra = self::RUTAS[$ruta][0];
        $id = (int) $id;

        if (!array_key_exists($id, self::$memo[$letra] ?? [])) {
            self::cargar($letra, [$id]);
        }

        return self::$memo[$letra][$id];
    }

    /** Carga de una vez los slugs de muchos ids (para listados largos). */
    public static function precargar($ruta, $ids)
    {
        $letra = self::RUTAS[$ruta][0];
        $faltan = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, self::$memo[$letra] ?? [])) {
                $faltan[$id] = $id;
            }
        }
        if ($faltan) {
            self::cargar($letra, array_values($faltan));
        }
    }

    /**
     * Reemplaza las marcas ~ua.x.N~ de un texto por "N-slug". Una consulta
     * por tipo, sea cual sea la cantidad de links.
     */
    public static function completarTexto($texto)
    {
        if (!is_string($texto) || strpos($texto, '~ua.') === false) {
            return $texto;
        }
        if (!preg_match_all('/~ua\.([a-z])\.(\d+)~/', $texto, $m, PREG_SET_ORDER)) {
            return $texto;
        }

        $porLetra = [];
        foreach ($m as $x) {
            $porLetra[$x[1]][(int) $x[2]] = (int) $x[2];
        }
        foreach ($porLetra as $letra => $ids) {
            $faltan = array_values(array_diff_key($ids, self::$memo[$letra] ?? []));
            if ($faltan) {
                self::cargar($letra, $faltan);
            }
        }

        $cambios = [];
        foreach ($m as $x) {
            $id = (int) $x[2];
            $cambios[$x[0]] = self::refCon($id, self::$memo[$x[1]][$id] ?? null);
        }

        return strtr($texto, $cambios);
    }

    /** Texto -> slug: "Gervasio Nuñez" -> "gervasio-nunez". */
    public static function aSlug($texto)
    {
        // "2019/2020" -> "2019-2020" (Str::slug borra la barra sin dejar separador).
        $s = Str::slug(str_replace(['/', '\\'], ' ', (string) $texto));
        if (strlen($s) > self::LARGO) {
            $s = substr($s, 0, self::LARGO);
            $corte = strrpos($s, '-');
            if ($corte !== false && $corte > 20) {
                $s = substr($s, 0, $corte);
            }
            $s = rtrim($s, '-');
        }
        return $s;
    }

    /**
     * SELECT que devuelve id + las columnas del slug (ver slugDeFila()), para
     * una letra. Lo usa también el sitemap. $where se agrega tal cual.
     */
    public static function consulta($letra)
    {
        switch ($letra) {
            case 'j': return 'SELECT x.id, p.nombre, p.apellido FROM jugadors x JOIN personas p ON p.id = x.persona_id';
            case 't': return 'SELECT x.id, p.nombre, p.apellido FROM tecnicos x JOIN personas p ON p.id = x.persona_id';
            case 'a': return 'SELECT x.id, p.nombre, p.apellido FROM arbitros x JOIN personas p ON p.id = x.persona_id';
            case 'e': return 'SELECT x.id, x.nombre FROM equipos x';
            case 'o': return 'SELECT x.id, x.nombre, x.year FROM torneos x';
            case 'p': return 'SELECT x.id, el.nombre AS local, ev.nombre AS visitante FROM partidos x'
                           . ' LEFT JOIN equipos el ON el.id = x.equipol_id'
                           . ' LEFT JOIN equipos ev ON ev.id = x.equipov_id';
        }
        throw new \InvalidArgumentException('Tipo de URL amigable desconocido: ' . $letra);
    }

    public static function slugDeFila($letra, $f)
    {
        switch ($letra) {
            case 'j':
            case 't':
            case 'a':
                return self::aSlug(trim($f->nombre . ' ' . $f->apellido));
            case 'e':
                return self::aSlug($f->nombre);
            case 'o':
                return self::aSlug($f->nombre . ' ' . $f->year);
            case 'p':
                return ($f->local || $f->visitante) ? self::aSlug($f->local . ' vs ' . $f->visitante) : '';
        }
        return '';
    }

    public static function letra($ruta)
    {
        return self::RUTAS[$ruta][0];
    }

    // ─────────────────────────────────────────────────────────────────────

    protected static function cargar($letra, array $ids)
    {
        if (!empty(self::$completos[$letra])) {
            foreach ($ids as $id) {
                self::$memo[$letra][$id] = self::$memo[$letra][$id] ?? null;
            }
            return;
        }

        // Torneos y equipos son pocos (cientos / un par de miles): se cargan
        // enteros la primera vez y ya no se consulta más en esta request.
        if ($letra === 'o' || $letra === 'e') {
            foreach (DB::select(self::consulta($letra)) as $f) {
                self::$memo[$letra][(int) $f->id] = self::slugDeFila($letra, $f);
            }
            self::$completos[$letra] = true;
        } else {
            foreach (array_chunk($ids, 1000) as $bloque) {
                $sql = self::consulta($letra) . ' WHERE x.id IN (' . implode(',', array_map('intval', $bloque)) . ')';
                foreach (DB::select($sql) as $f) {
                    self::$memo[$letra][(int) $f->id] = self::slugDeFila($letra, $f);
                }
            }
        }

        foreach ($ids as $id) {
            if (!array_key_exists($id, self::$memo[$letra] ?? [])) {
                self::$memo[$letra][$id] = null;
            }
        }
    }
}
