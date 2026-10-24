<?php

namespace App\Services;

use App\Incidencia;
use Illuminate\Support\Facades\DB;

/**
 * El goleador de cada torneo, guardado en `torneo_goleadores`.
 *
 * Se calcula aparte (comando `torneos:goleadores`, en el cron, y al finalizar
 * un torneo) y la ficha del jugador sólo lee la tabla: es una consulta sobre
 * todos los goles de la base y no tiene sentido repetirla en cada visita.
 *
 * Regla, decidida el 24/10/2026:
 *
 *  1. Sólo torneos COMPLETOS: con posiciones guardadas (`posicion_torneos`) o
 *     marcados `inconcluso`. Un torneo en curso no tiene goleador todavía.
 *  2. Los goles se cuentan como en la ficha (`gols.tipo <> 'En contra'`),
 *     SIN los partidos con incidencia de escritorio o no jugado
 *     (`Incidencia::ANULAN_GOLES`): el resultado oficial no es el que se jugó.
 *  3. k = goles que faltan en el detalle del resto de los partidos del torneo
 *     (marcador menos filas de `gols`). No se sabe de quién son.
 *  4. Hay goleador si el primero le saca al segundo MÁS de k: aunque todos los
 *     que faltan fueran del segundo, no lo alcanza. Con k = 0 y empate arriba,
 *     son goleadores todos los empatados (`compartido`). Si no, no hay.
 *
 * Por eso un torneo de copa vieja con medio fixture sin detalle (KNVB Beker
 * 2000/01: 515 goles sin goleador) no muestra goleador, y uno con un gol
 * suelto perdido casi siempre sí.
 */
class GoleadoresTorneo
{
    /**
     * Recalcula y guarda. Con $torneoId, sólo ese torneo.
     *
     * @return array resumen: completos, con_goleador, sin_goleador (k alcanza), filas
     */
    public function calcular(?int $torneoId = null): array
    {
        $tipos = "'" . implode("','", Incidencia::ANULAN_GOLES) . "'";
        $anulados = "SELECT i.partido_id FROM incidencias i
                      WHERE i.partido_id IS NOT NULL AND i.tipo IN ($tipos)";
        $soloTorneo = $torneoId ? ' AND g.torneo_id = ' . (int) $torneoId : '';

        // 1) Torneos completos
        $completos = [];
        $q = 'SELECT t.id, COALESCE(t.inconcluso, 0) AS inconcluso FROM torneos t
               WHERE (COALESCE(t.inconcluso, 0) = 1
                      OR EXISTS (SELECT 1 FROM posicion_torneos pt WHERE pt.torneo_id = t.id))'
            . ($torneoId ? ' AND t.id = ' . (int) $torneoId : '');
        foreach (DB::select($q) as $t) {
            $completos[(int) $t->id] = (int) $t->inconcluso;
        }

        // 2) Goles por torneo y jugador, sin los partidos anulados
        $goles = [];
        foreach (DB::select("SELECT g.torneo_id, gols.jugador_id, COUNT(*) AS goles
                               FROM gols
                               JOIN partidos p ON p.id = gols.partido_id
                               JOIN fechas f   ON f.id = p.fecha_id
                               JOIN grupos g   ON g.id = f.grupo_id
                              WHERE gols.tipo <> 'En contra' AND gols.jugador_id IS NOT NULL
                                AND p.id NOT IN ($anulados)$soloTorneo
                              GROUP BY g.torneo_id, gols.jugador_id") as $f) {
            $tid = (int) $f->torneo_id;
            if (!isset($completos[$tid])) continue;
            $goles[$tid][(int) $f->jugador_id] = (int) $f->goles;
        }

        // 3) k: goles del marcador que no están en el detalle
        $faltan = [];
        foreach (DB::select("SELECT g.torneo_id,
                                    SUM(GREATEST(p.golesl + p.golesv - COALESCE(x.n, 0), 0)) AS k
                               FROM partidos p
                               JOIN fechas f ON f.id = p.fecha_id
                               JOIN grupos g ON g.id = f.grupo_id
                               LEFT JOIN (SELECT partido_id, COUNT(*) AS n FROM gols GROUP BY partido_id) x
                                      ON x.partido_id = p.id
                              WHERE p.golesl IS NOT NULL AND p.golesv IS NOT NULL
                                AND p.id NOT IN ($anulados)$soloTorneo
                              GROUP BY g.torneo_id") as $f) {
            $faltan[(int) $f->torneo_id] = (int) $f->k;
        }

        // 4) La regla
        $filas = [];
        $conGoleador = 0;
        $ahora = now();
        foreach ($completos as $tid => $inconcluso) {
            if (empty($goles[$tid])) continue;
            $lista = $goles[$tid];
            arsort($lista);
            $valores = array_values($lista);
            $g1 = $valores[0];
            if ($g1 < 1) continue;

            $arriba = array_keys(array_filter($lista, function ($n) use ($g1) { return $n === $g1; }));
            $k = $faltan[$tid] ?? 0;

            if (count($arriba) === 1) {
                $g2 = $valores[1] ?? 0;
                if ($g1 <= $g2 + $k) continue;
            } elseif ($k > 0) {
                continue;
            }

            $conGoleador++;
            foreach ($arriba as $jid) {
                $filas[] = [
                    'torneo_id'  => $tid,
                    'jugador_id' => $jid,
                    'goles'      => $g1,
                    'compartido' => count($arriba) > 1 ? 1 : 0,
                    'inconcluso' => $inconcluso ? 1 : 0,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }
        }

        DB::transaction(function () use ($torneoId, $filas) {
            if ($torneoId) {
                DB::table('torneo_goleadores')->where('torneo_id', $torneoId)->delete();
            } else {
                DB::table('torneo_goleadores')->delete();
            }
            foreach (array_chunk($filas, 500) as $tanda) {
                DB::table('torneo_goleadores')->insert($tanda);
            }
        });

        return [
            'completos'    => count($completos),
            'con_goleador' => $conGoleador,
            'sin_goleador' => count($completos) - $conGoleador,
            'filas'        => count($filas),
        ];
    }
}
