<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orden cronológico de los torneos en las fichas (técnico, jugador, equipo,
 * títulos).
 *
 * Antes se ordenaba por `torneos.year`, que es texto ("2011", "2011/12",
 * "2012") o por el primer año de 4 cifras del nombre. Con torneos del mismo
 * año quedaban empatados y el orden salía al azar: "Apertura 2011" (dic 2011)
 * arriba de "LaLiga 2011/12" (mayo 2012), porque para el regex los dos son 2011.
 *
 * Ahora la clave es una FECHA, de más a menos precisa:
 *   1. la propia: último partido de la persona/equipo en ese torneo
 *      (el que arma la ficha la pasa en $propias);
 *   2. el fin del torneo: su último partido;
 *   3. el año escrito (manuales y títulos extra): "2011/12" -> 2012-06-30,
 *      "2011" -> 2011-12-31.
 * Desempata por fin del torneo y después por id, así nunca queda al azar
 * (usort no es estable antes de PHP 8).
 */
class OrdenTorneos
{
    /** [torneo_id => fecha del último partido del torneo] */
    public static function fines(array $ids)
    {
        $ids = array_values(array_unique(array_filter(array_map(function ($v) {
            return is_numeric($v) ? (int) $v : 0;
        }, $ids))));
        if (empty($ids)) {
            return [];
        }
        $out = [];
        foreach (DB::select('SELECT grupos.torneo_id, MAX(partidos.dia) AS fin
FROM partidos
INNER JOIN fechas ON fechas.id = partidos.fecha_id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE grupos.torneo_id IN ('.implode(',', $ids).')
GROUP BY grupos.torneo_id') as $f) {
            if ($f->fin) {
                $out[(int) $f->torneo_id] = (string) $f->fin;
            }
        }
        return $out;
    }

    /** [torneo_id => último partido que dirigió la persona] */
    public static function ultimosComoTecnico($personaId)
    {
        return self::ultimos('SELECT grupos.torneo_id, MAX(partidos.dia) AS dia
FROM partido_tecnicos
INNER JOIN tecnicos ON tecnicos.id = partido_tecnicos.tecnico_id
INNER JOIN partidos ON partidos.id = partido_tecnicos.partido_id
INNER JOIN fechas ON fechas.id = partidos.fecha_id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE tecnicos.persona_id = ?
GROUP BY grupos.torneo_id', (int) $personaId);
    }

    /** [torneo_id => último partido que jugó la persona] */
    public static function ultimosComoJugador($personaId)
    {
        return self::ultimos('SELECT grupos.torneo_id, MAX(partidos.dia) AS dia
FROM alineacions
INNER JOIN jugadors ON jugadors.id = alineacions.jugador_id
INNER JOIN partidos ON partidos.id = alineacions.partido_id
INNER JOIN fechas ON fechas.id = partidos.fecha_id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE jugadors.persona_id = ?
GROUP BY grupos.torneo_id', (int) $personaId);
    }

    /** [torneo_id => último partido del equipo] */
    public static function ultimosDeEquipo($equipoId)
    {
        $id = (int) $equipoId;
        return self::ultimos('SELECT grupos.torneo_id, MAX(partidos.dia) AS dia
FROM partidos
INNER JOIN fechas ON fechas.id = partidos.fecha_id
INNER JOIN grupos ON grupos.id = fechas.grupo_id
WHERE partidos.equipol_id = ? OR partidos.equipov_id = ?
GROUP BY grupos.torneo_id', $id, $id);
    }

    private static function ultimos($sql, ...$params)
    {
        $out = [];
        foreach (DB::select($sql, $params) as $f) {
            if ($f->dia) {
                $out[(int) $f->torneo_id] = (string) $f->dia;
            }
        }
        return $out;
    }

    /**
     * Ordena de más nuevo a más viejo. Acepta array o Collection y devuelve
     * lo mismo que recibió (reindexado).
     *
     * @param array|Collection $filas   objetos con idTorneo (o id) y year/nombreTorneo/nombre
     * @param array            $propias [torneo_id => fecha] de la persona o el equipo
     */
    public static function ordenar($filas, array $propias = [])
    {
        $esColeccion = $filas instanceof Collection;
        $lista = $esColeccion ? $filas->values()->all() : array_values((array) $filas);

        $ids = [];
        foreach ($lista as $t) {
            $ids[] = self::idDe($t);
        }
        $fines = self::fines($ids);

        $claves = [];
        foreach ($lista as $i => $t) {
            $id = self::idDe($t);
            $fin = ($id && isset($fines[$id])) ? $fines[$id] : self::fechaDelAnio($t);
            $propia = ($id && isset($propias[$id])) ? $propias[$id] : $fin;
            $claves[$i] = [$propia, $fin, $id, $i];
        }

        uksort($claves, function ($a, $b) use ($claves) {
            $x = $claves[$a];
            $y = $claves[$b];
            // fechas y id DESC; el índice original ASC como último desempate
            return [$y[0], $y[1], $y[2], $x[3]] <=> [$x[0], $x[1], $x[2], $y[3]];
        });

        $out = [];
        foreach (array_keys($claves) as $i) {
            $out[] = $lista[$i];
        }
        return $esColeccion ? collect($out) : $out;
    }

    private static function idDe($t)
    {
        foreach (['idTorneo', 'torneo_id'] as $campo) {
            if (isset($t->$campo) && is_numeric($t->$campo)) {
                return (int) $t->$campo;
            }
        }
        return 0;
    }

    /** "2011/12" -> 2012-06-30 · "2011" -> 2011-12-31 · nada -> 0000-00-00 */
    public static function fechaDelAnio($t)
    {
        $textos = [];
        foreach (['year', 'nombreTorneo', 'nombre'] as $campo) {
            if (isset($t->$campo) && $t->$campo !== '') {
                $textos[] = (string) $t->$campo;
            }
        }
        foreach ($textos as $s) {
            if (preg_match('/\b((?:19|20)\d{2})\s*[\/-]\s*(\d{2}|\d{4})\b/', $s, $m)) {
                $fin = strlen($m[2]) == 2 ? (int) (substr($m[1], 0, 2).$m[2]) : (int) $m[2];
                if ($fin <= (int) $m[1]) {
                    $fin = (int) $m[1] + 1;
                }
                return sprintf('%04d-06-30', $fin);
            }
            if (preg_match('/\b((?:19|20)\d{2})\b/', $s, $m)) {
                return $m[1].'-12-31';
            }
        }
        return '0000-00-00';
    }
}
