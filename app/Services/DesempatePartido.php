<?php

namespace App\Services;

/**
 * Entre varios partidos tuyos posibles, el que además coincide en el resultado.
 *
 * Nació de un caso que no se resuelve de otra forma: Estudiantes (LP) vs Boca
 * Juniors dos veces en el mismo mes —la fecha 11 el 12/04 (1-0) y la semifinal
 * el 30/04 (1-1, 3-1 por penales)— las dos con Estudiantes de local. Mismo
 * cruce, mismo orden, adentro de la misma ventana: el desempate por orden no
 * alcanza, y abstenerse era decir «no está en tu base» de un partido que está
 * dos veces.
 *
 * Está en una clase propia, y no como método privado del controlador, por una
 * razón práctica: así se puede probar de verdad. La lógica que decide con qué
 * gameId se escribe una alineación no puede quedar sin banco de pruebas.
 *
 * **Sólo desempata.** Nunca elige solo: si no queda exactamente uno devuelve
 * null y quien llama se sigue absteniendo. Un gameId equivocado escribe la
 * alineación de OTRO partido encima, y eso después no se nota.
 */
class DesempatePartido
{
    /**
     * @param  iterable $candidatos  filas con equipol_id, equipov_id, golesl, golesv
     * @param  string   $resultado   el de TM, tal cual: "1:0", "5:4pen.", "1:1 (3-1 p)"
     * @param  int      $equipoId    el equipo tuyo que en TM figura de local
     * @param  int      $rivalId     el otro, o 0 si no está atado
     * @return object|null
     */
    public static function porResultado($candidatos, $resultado, $equipoId, $rivalId)
    {
        // «5:4pen.» y «1:1 (3-1 p)» traen más números que el resultado del
        // partido: el primer par es el bueno, y es el único que se mira.
        if (!preg_match('/(\d+)\s*:\s*(\d+)/', (string) $resultado, $m)) return null;

        $gl = (int) $m[1];
        $gv = (int) $m[2];

        $quedan = [];

        foreach ($candidatos as $c) {
            if ($c->golesl === null || $c->golesv === null) continue;

            $suL = (int) $c->golesl;
            $suV = (int) $c->golesv;

            // Mismo orden que TM: los goles van como vienen. Orden invertido:
            // se dan vuelta.
            if ((int) $c->equipol_id === (int) $equipoId && $suL === $gl && $suV === $gv) {
                $quedan[] = $c;
                continue;
            }

            if ($rivalId && (int) $c->equipol_id === (int) $rivalId && $suL === $gv && $suV === $gl) {
                $quedan[] = $c;
            }
        }

        return count($quedan) === 1 ? $quedan[0] : null;
    }
}
