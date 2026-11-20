<?php

namespace App\Services;

/**
 * Etapas de una persona en cada club («Período» de las tablas Por club de las
 * fichas de jugador y técnico).
 *
 * Con MIN/MAX del día por club, el que volvió a un club después de pasar por
 * otros quedaba con un solo rango (C. Ronaldo en el United: 2003–2022). Acá se
 * recorren sus partidos en orden de fecha y cada vez que cambia el equipo
 * empieza una etapa nueva: 2003–2009, 2021–2022.
 *
 * Clubes y selecciones se recorren por separado: los partidos de la
 * selección van intercalados con los del club y no cortan una etapa.
 */
class EtapasClub
{
    /**
     * @param iterable $partidos filas con equipo_id y dia, en orden de fecha
     * @return array equipo_id => [['desde' => dia, 'hasta' => dia], ...] (de la más vieja a la más nueva)
     */
    public static function calcular($partidos)
    {
        $etapas = [];
        $anterior = null;
        foreach ($partidos as $p) {
            if (empty($p->dia)) {
                continue;
            }
            $eid = (int) $p->equipo_id;
            if ($eid !== $anterior) {
                $etapas[$eid][] = ['desde' => $p->dia, 'hasta' => $p->dia];
            } else {
                $etapas[$eid][count($etapas[$eid]) - 1]['hasta'] = $p->dia;
            }
            $anterior = $eid;
        }
        return $etapas;
    }

    /**
     * «2003–2009, 2021–2022». Dos etapas que caen en los mismos años (un
     * préstamo de meses) se juntan para no repetir el año.
     */
    public static function texto(array $etapas)
    {
        $tramos = [];
        foreach ($etapas as $e) {
            $d = (int) substr((string) $e['desde'], 0, 4);
            $h = (int) substr((string) $e['hasta'], 0, 4);
            $n = count($tramos);
            if ($n && $d <= $tramos[$n - 1][1]) {
                $tramos[$n - 1][1] = max($tramos[$n - 1][1], $h);
            } else {
                $tramos[] = [$d, $h];
            }
        }
        return implode(', ', array_map(function ($t) {
            return $t[0] === $t[1] ? (string) $t[0] : $t[0] . '–' . $t[1];
        }, $tramos));
    }

    /**
     * Le pone ->periodo a cada fila (con equipo_id, desde y hasta). Sin
     * etapas calculadas para ese equipo queda el rango de siempre.
     */
    public static function asignar(array $filas, array $etapas)
    {
        foreach ($filas as $f) {
            $eid = (int) $f->equipo_id;
            $f->periodo = !empty($etapas[$eid])
                ? self::texto($etapas[$eid])
                : self::texto([['desde' => $f->desde, 'hasta' => $f->hasta]]);
        }
        return $filas;
    }
}
