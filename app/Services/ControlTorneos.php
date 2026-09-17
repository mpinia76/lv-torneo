<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Control de torneos: cuántos equipos tiene cargados cada torneo contra los
 * que dice que tiene (`torneos.equipos`), y si ya se guardaron las posiciones
 * finales (`posicion_torneos`, la pantalla "Finalizar").
 *
 * Tres listas:
 *   - sobran     → más equipos con plantilla que los declarados.
 *   - faltan     → menos equipos con plantilla que los declarados.
 *   - sin_posiciones → la cantidad coincide y no hay ninguna fila en
 *                  posicion_torneos.
 *
 * "Equipo del torneo" = equipo con plantilla en algún grupo del torneo
 * (COUNT DISTINCT: un club que está en la fase de grupos y en los playoffs
 * cuenta una vez). Es el mismo criterio de `TorneoController@finalizar`, que
 * arma el desplegable de equipos desde las plantillas.
 *
 * Todo se cuenta desde `torneos` con subconsultas correlacionadas, nunca con
 * un GROUP BY sobre la tabla de detalle: un torneo sin ninguna plantilla o sin
 * ninguna posición tiene que dar 0, no desaparecer (ver controles_de_carga).
 */
class ControlTorneos
{
    const LISTAS = [
        'sobran'         => 'Le sobran equipos',
        'faltan'         => 'Le faltan equipos',
        'sin_posiciones' => 'Completos sin posiciones',
    ];

    /**
     * Una fila por torneo con los contadores. Filtros: year, tipo, q,
     * parciales (1 = incluirlos).
     */
    public function torneos(array $f): array
    {
        $q = DB::table('torneos as t')
            ->select('t.id', 't.nombre', 't.year', 't.tipo', 't.ambito', 't.equipos as esperados', 't.grupos')
            ->selectRaw('COALESCE(t.parcial, 0) AS parcial')
            ->selectRaw('(SELECT COUNT(DISTINCT p.equipo_id) FROM plantillas p
                            INNER JOIN grupos g ON g.id = p.grupo_id
                           WHERE g.torneo_id = t.id) AS cargados')
            ->selectRaw('(SELECT COUNT(*) FROM posicion_torneos pt WHERE pt.torneo_id = t.id) AS posiciones')
            ->selectRaw('(SELECT COUNT(*) FROM partidos pa
                            INNER JOIN fechas fe ON fe.id = pa.fecha_id
                            INNER JOIN grupos g ON g.id = fe.grupo_id
                           WHERE g.torneo_id = t.id) AS partidos')
            ->selectRaw('(SELECT COUNT(*) FROM partidos pa
                            INNER JOIN fechas fe ON fe.id = pa.fecha_id
                            INNER JOIN grupos g ON g.id = fe.grupo_id
                           WHERE g.torneo_id = t.id
                             AND (pa.golesl IS NULL OR pa.golesv IS NULL)) AS sin_resultado');

        if (!empty($f['year'])) {
            $q->where('t.year', $f['year']);
        }
        if (!empty($f['tipo'])) {
            $q->where('t.tipo', $f['tipo']);
        }
        if (!empty($f['q'])) {
            $q->where('t.nombre', 'like', '%' . $f['q'] . '%');
        }
        if (empty($f['parciales'])) {
            $q->whereRaw('COALESCE(t.parcial, 0) = 0');
        }

        return $q->orderByDesc('t.year')->orderBy('t.nombre')->get()->all();
    }

    /** Reparte las filas en las tres listas. */
    public function clasificar(array $filas): array
    {
        $listas = array_fill_keys(array_keys(self::LISTAS), []);

        foreach ($filas as $fila) {
            $esperados = (int) $fila->esperados;
            $cargados  = (int) $fila->cargados;
            $fila->diferencia = $cargados - $esperados;

            if ($cargados > $esperados) {
                $listas['sobran'][] = $fila;
            } elseif ($cargados < $esperados) {
                $listas['faltan'][] = $fila;
            } elseif ((int) $fila->posiciones === 0) {
                $listas['sin_posiciones'][] = $fila;
            }
        }

        return $listas;
    }

    /**
     * Para los torneos que se muestran: qué equipo explica la diferencia.
     *   - con plantilla y sin ningún partido  → sospechoso de sobrar
     *   - con partidos y sin plantilla        → explica un faltante
     * Dos consultas para toda la página.
     *
     * @return array [torneo_id => ['sin_partidos' => [...], 'sin_plantilla' => [...]]]
     */
    public function detalle(array $ids): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }
        foreach ($ids as $id) {
            $out[$id] = ['sin_partidos' => [], 'sin_plantilla' => []];
        }

        $conPlantilla = DB::table('plantillas as p')
            ->join('grupos as g', 'g.id', '=', 'p.grupo_id')
            ->join('equipos as e', 'e.id', '=', 'p.equipo_id')
            ->whereIn('g.torneo_id', $ids)
            ->select('g.torneo_id', 'e.id', 'e.nombre', 'e.escudo')
            ->distinct()
            ->get();

        $jugados = DB::select(
            'SELECT x.torneo_id, x.equipo_id, e.nombre, e.escudo, COUNT(*) AS partidos
               FROM (
                     SELECT g.torneo_id, pa.equipol_id AS equipo_id
                       FROM partidos pa
                       INNER JOIN fechas fe ON fe.id = pa.fecha_id
                       INNER JOIN grupos g ON g.id = fe.grupo_id
                      WHERE g.torneo_id IN (' . implode(',', array_map('intval', $ids)) . ')
                     UNION ALL
                     SELECT g.torneo_id, pa.equipov_id
                       FROM partidos pa
                       INNER JOIN fechas fe ON fe.id = pa.fecha_id
                       INNER JOIN grupos g ON g.id = fe.grupo_id
                      WHERE g.torneo_id IN (' . implode(',', array_map('intval', $ids)) . ')
                    ) x
               INNER JOIN equipos e ON e.id = x.equipo_id
              GROUP BY x.torneo_id, x.equipo_id, e.nombre, e.escudo'
        );

        $juega = [];
        foreach ($jugados as $j) {
            $juega[$j->torneo_id][$j->equipo_id] = $j;
        }
        $tiene = [];
        foreach ($conPlantilla as $c) {
            $tiene[$c->torneo_id][$c->id] = true;
            if (empty($juega[$c->torneo_id][$c->id])) {
                $out[$c->torneo_id]['sin_partidos'][] = $c;
            }
        }
        foreach ($jugados as $j) {
            if (empty($tiene[$j->torneo_id][$j->equipo_id])) {
                $out[$j->torneo_id]['sin_plantilla'][] = $j;
            }
        }

        return $out;
    }

    public function anios(): array
    {
        return DB::table('torneos')->select('year')->distinct()->orderByDesc('year')->pluck('year')->all();
    }

    public function tipos(): array
    {
        return DB::table('torneos')->whereNotNull('tipo')->select('tipo')->distinct()->orderBy('tipo')->pluck('tipo')->all();
    }
}
