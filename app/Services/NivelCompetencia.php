<?php

namespace App\Services;

use App\CompetenciaExcluida;
use Illuminate\Support\Str;

/**
 * ¿Esta competencia es de primera división?
 *
 * El importador de partidos sondea TODO lo que Transfermarkt tiene del DT:
 * reserva, juveniles, Proyección, ascenso. Nada de eso va a la base, y encima
 * ensucia la pantalla (los clubes "II" llenan «Clubes sin resolver»).
 *
 * La decisión se toma en tres pasos, en este orden:
 *
 *   1. Regla ACTIVA de `competencias_excluidas` (la tabla que ya existía para
 *      el scraper viejo, con su ABM en /admin/competencias-excluidas).
 *   2. Regla INACTIVA que matchea: es un "incluir igual" explícito del usuario.
 *      Una regla apagada quiere decir "ya la miré y SÍ va", así que gana sobre
 *      la lista automática de abajo.
 *   3. Lista automática de palabras (reserva/juveniles + ligas de ascenso).
 *
 * Así el default anda sin cargar nada, y cualquier error se corrige con un
 * clic desde el sondeo, sin tocar código.
 */
class NivelCompetencia
{
    /** Reserva, juveniles, proyección: no es el primer equipo del club. */
    protected static $reserva = [
        'proyeccion', 'reserva', 'reservas', 'juvenil', 'juveniles', 'inferiores', 'formativas',
        'sub 15', 'sub 16', 'sub 17', 'sub 18', 'sub 19', 'sub 20', 'sub 21', 'sub 23',
        'u15', 'u16', 'u17', 'u18', 'u19', 'u20', 'u21', 'u23',
        'youth', 'primavera', 'academy', 'development', 'futuro',
    ];

    /** Ligas que no son la máxima categoría del país. */
    protected static $ascenso = [
        'primera nacional', 'nacional b', 'b nacional', 'primera b', 'primera c', 'primera d',
        'torneo federal', 'federal a', 'federal b', 'federal c', 'regional amateur',
        'torneo argentino', 'argentino a', 'argentino b', 'argentino c',
        'segunda division', 'segunda b', 'segunda federacion', 'tercera division', 'cuarta division',
        'liga smartbank', 'laliga2', 'hypermotion', 'ascenso mx', 'liga de expansion',
        '2 bundesliga', '3 liga', 'regionalliga', 'championship', 'league one', 'league two',
        'national league', 'serie b', 'serie c', 'serie d', 'ligue 2', 'national 1',
        'eerste divisie', 'challenge league', 'superettan', 'segunda liga', 'liga portugal 2',
        '2 liga', 'ii liga',
    ];

    /** Marcas de equipo alternativo en el nombre del club de Transfermarkt. */
    protected static $equipoAlternativo = [
        'u15', 'u16', 'u17', 'u18', 'u19', 'u20', 'u21', 'u23',
        'sub 15', 'sub 16', 'sub 17', 'sub 18', 'sub 19', 'sub 20', 'sub 21', 'sub 23',
        'juvenil', 'youth', 'reserva', 'primavera', 'academy',
    ];

    /** Reglas inactivas, cacheadas por request. */
    protected static $inactivas = null;

    /**
     * ['excluida' => bool, 'motivo' => string, 'origen' => 'regla'|'auto'|'manual'|'']
     *
     * `origen = manual` es el "incluir igual": el usuario ya decidió que esta
     * competencia va, así que ningún chequeo automático la puede voltear.
     */
    public static function decidir($nombreCompetencia)
    {
        $nombre = trim((string) $nombreCompetencia);
        if ($nombre === '') {
            return ['excluida' => false, 'motivo' => '', 'origen' => ''];
        }

        if (CompetenciaExcluida::debeExcluir($nombre)) {
            return ['excluida' => true, 'motivo' => 'regla guardada', 'origen' => 'regla'];
        }

        if (self::matcheaInactiva($nombre)) {
            return ['excluida' => false, 'motivo' => 'marcada como de 1ra', 'origen' => 'manual'];
        }

        $n = self::normalizarAuto($nombre);
        foreach (self::$reserva as $k) {
            if (self::contiene($n, $k)) {
                return ['excluida' => true, 'motivo' => 'reserva / juveniles', 'origen' => 'auto'];
            }
        }
        foreach (self::$ascenso as $k) {
            if (self::contiene($n, $k)) {
                return ['excluida' => true, 'motivo' => 'no es primera división', 'origen' => 'auto'];
            }
        }

        return ['excluida' => false, 'motivo' => '', 'origen' => ''];
    }

    /**
     * El club de Transfermarkt es un equipo alternativo: "CA Independiente II",
     * "River Plate U20". Red de seguridad para cuando el nombre de la
     * competencia no lo delata.
     */
    public static function esEquipoAlternativo($nombreClub)
    {
        $n = self::normalizarAuto($nombreClub);
        if ($n === '') return false;

        // El sufijo "II" de TM es el segundo equipo. Ojo: solo al final y como
        // palabra suelta ("Independiente II" sí, "Ilha II" no existe, pero
        // "Estudiantes" nunca matchea porque no es palabra aparte).
        if (preg_match('/\bii$/', $n)) return true;

        foreach (self::$equipoAlternativo as $k) {
            if (self::contiene($n, $k)) return true;
        }
        return false;
    }

    /**
     * Excluir de acá en adelante: guarda una regla 'contiene' en la tabla de
     * siempre, sin el año final, para que sirva en todas las temporadas.
     * Devuelve el patrón guardado.
     */
    public static function marcarExcluida($nombreCompetencia)
    {
        $patron = self::patronDe($nombreCompetencia);
        if ($patron === '') return '';

        $item = CompetenciaExcluida::firstOrCreate(
            ['patron' => $patron],
            ['tipo_match' => 'contiene', 'motivo' => 'Excluida desde el sondeo: ' . $nombreCompetencia, 'activo' => true]
        );
        if (!$item->activo) {
            $item->activo = true;
            $item->save();
        }
        self::$inactivas = null;
        return $item->patron;
    }

    /**
     * Incluir igual: apaga las reglas que la estaban tapando y deja una regla
     * APAGADA con su nombre. Esa regla apagada es la marca de "esta sí es de
     * 1ra", y es la que le gana a la lista automática.
     *
     * Devuelve ['patron' => string, 'apagadas' => array]. `apagadas` importa:
     * una regla como «reserva» tapa muchas competencias, y apagarla las
     * destapa a todas. Hay que decírselo al usuario, no apagarla en silencio.
     */
    public static function marcarIncluida($nombreCompetencia)
    {
        $nombre = trim((string) $nombreCompetencia);
        $patron = self::patronDe($nombre);
        if ($patron === '') return ['patron' => '', 'apagadas' => []];

        $apagadas = [];
        foreach (CompetenciaExcluida::where('activo', true)->get() as $regla) {
            if (self::matchea($nombre, $regla->patron, $regla->tipo_match)) {
                $regla->activo = false;
                $regla->save();
                $apagadas[] = $regla->patron;
            }
        }

        $item = CompetenciaExcluida::firstOrCreate(
            ['patron' => $patron],
            ['tipo_match' => 'contiene', 'motivo' => 'Marcada como de 1ra división desde el sondeo', 'activo' => false]
        );
        if ($item->activo) {
            $item->activo = false;
            $item->save();
        }
        self::$inactivas = null;
        return ['patron' => $item->patron, 'apagadas' => $apagadas];
    }

    // ─────────────────────────────────────────────────────────────────────

    protected static function matcheaInactiva($nombre)
    {
        if (self::$inactivas === null) {
            self::$inactivas = CompetenciaExcluida::where('activo', false)
                ->get(['patron', 'tipo_match'])->toArray();
        }
        foreach (self::$inactivas as $r) {
            if (self::matchea($nombre, $r['patron'], $r['tipo_match'])) return true;
        }
        return false;
    }

    /** Mismo criterio que CompetenciaExcluida::debeExcluir(). */
    protected static function matchea($nombre, $patron, $tipo)
    {
        $n = self::normalizar($nombre);
        $p = mb_strtolower(trim((string) $patron));
        if ($p === '') return false;

        if ($tipo === 'exacto') return $n === $p;
        if ($tipo === 'regex')  return @preg_match('/' . $patron . '/i', $nombre) === 1;
        return strpos($n, $p) !== false;
    }

    /**
     * La palabra clave, entera, dentro del nombre normalizado.
     *
     * Tiene que ser por PALABRA COMPLETA, no por substring: «primera d»
     * (Primera D) estaba adentro de «primera division», que es la máxima
     * categoría de media Sudamérica. Un patrón corto que come al torneo bueno
     * es peor que uno que no atrapa nada: lo que no se lista no se nota.
     */
    protected static function contiene($nombreNormalizado, $clave)
    {
        return preg_match('/(?<![a-z0-9])' . preg_quote($clave, '/') . '(?![a-z0-9])/', $nombreNormalizado) === 1;
    }

    /** El nombre sin el año final, normalizado: sirve para todas las temporadas. */
    public static function patronDe($nombre)
    {
        $p = self::normalizar($nombre);
        return trim(preg_replace('/\s*\d{4}(\/\d{2,4})?\s*$/', '', $p));
    }

    protected static function normalizar($s)
    {
        return (string) Str::of((string) $s)->lower()->ascii()->replaceMatches('/\s+/', ' ')->trim();
    }

    /**
     * Igual que normalizar(), pero además convierte guiones y puntos en
     * espacios: así "Sub-20", "U-20" y "2. Bundesliga" matchean las listas
     * automáticas de arriba, que van escritas con espacios.
     *
     * Las reglas de la tabla NO usan esta normalización: tienen que seguir
     * comportándose igual que en el ABM de siempre.
     */
    protected static function normalizarAuto($s)
    {
        $n = self::normalizar($s);
        $n = preg_replace('/[-_.]+/', ' ', $n);
        return trim(preg_replace('/\s+/', ' ', $n));
    }
}
