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
 *   - partidos_faltan → la cantidad coincide pero hay fechas de tabla con
 *                  menos partidos de los que corresponden (o ningún partido).
 *   - sin_posiciones → equipos y fechas completos, y no hay ninguna fila en
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
        'partidos_faltan' => 'Equipos bien, faltan partidos',
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
            ->selectRaw($this->hayInconcluso() ? 'COALESCE(t.inconcluso, 0) AS inconcluso' : '0 AS inconcluso')
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
        // Inconcluso = torneo sin campeón: suspendido que nunca terminó (Copa
        // de la Superliga 2020, pandemia) o fase previa que TM lista aparte
        // (UEFA Champions League Qualifying). No entra en NINGUNA lista.
        if ($this->hayInconcluso()) {
            $q->whereRaw('COALESCE(t.inconcluso, 0) = 0');
        }

        $filas = $q->orderByDesc('t.year')->orderBy('t.nombre')->get()->all();

        $fechas = $this->fechasPorTorneo(array_map(function ($t) { return $t->id; }, $filas));
        foreach ($filas as $fila) {
            $fila->fechas_tabla       = $fechas[$fila->id]['total'] ?? 0;
            $fila->fechas_incompletas = $fechas[$fila->id]['incompletas'] ?? 0;
        }

        return $filas;
    }

    /**
     * Fechas "de tabla" (número puro, fuera del grupo Playoffs) de cada torneo,
     * contadas por NÚMERO de fecha en todo el torneo, no por grupo.
     *
     * La fecha N está a medias si la suma de sus partidos en todas las zonas
     * es menor que la suma de la mitad de los equipos con plantilla de cada
     * zona que tiene esa fecha. En una liga de 20 lleva 10: LaLiga cargada DT
     * por DT tiene 1 y cae.
     *
     * Por qué sumando zonas: en el Transición 2016 (dos zonas de 15) el
     * interzonal de cada fecha está cargado en una sola zona, así que la
     * fecha de la otra zona queda con menos (o ninguno) y por grupo parecía
     * incompleta. Sumando: 15 partidos contra 7 + 7 esperados, completa.
     *
     * Las fechas de playoffs ('Final', 'Cuartos de final'...) no se miran:
     * ahí la cantidad de partidos no sale de los equipos del grupo.
     * Se resuelve en PHP y con una sola consulta (sin derivadas correlacionadas,
     * que MySQL viejo no acepta).
     *
     * @return array [torneo_id => ['total' => n, 'incompletas' => n]]
     */
    private function fechasPorTorneo(array $ids): array
    {
        $out = [];
        if (!$ids) {
            return $out;
        }

        $filas = DB::select(
            "SELECT g.torneo_id, fe.numero,
                    SUM(COALESCE(pc.cant, 0))             AS jugados,
                    SUM(FLOOR(COALESCE(ec.cant, 0) / 2))  AS esperados
               FROM fechas fe
               INNER JOIN grupos g ON g.id = fe.grupo_id
               LEFT JOIN (SELECT fecha_id, COUNT(*) AS cant FROM partidos GROUP BY fecha_id) pc
                      ON pc.fecha_id = fe.id
               LEFT JOIN (SELECT grupo_id, COUNT(DISTINCT equipo_id) AS cant FROM plantillas GROUP BY grupo_id) ec
                      ON ec.grupo_id = g.id
              WHERE g.torneo_id IN (" . implode(',', array_map('intval', $ids)) . ")
                AND fe.numero REGEXP '^[0-9]+$'
                AND g.nombre <> 'Playoffs'
              GROUP BY g.torneo_id, fe.numero"
        );

        foreach ($filas as $f) {
            if (!isset($out[$f->torneo_id])) {
                $out[$f->torneo_id] = ['total' => 0, 'incompletas' => 0];
            }
            $out[$f->torneo_id]['total']++;
            if ((int) $f->jugados < (int) $f->esperados) {
                $out[$f->torneo_id]['incompletas']++;
            }
        }

        // FECHAS CORTAS A PROPÓSITO. Si todos los equipos del torneo jugaron la
        // MISMA cantidad de partidos de tabla, no falta ninguno: una fecha con
        // menos partidos es una fecha extra o una jornada que TM repartió mal
        // (Eredivisie 2000/01: 306 partidos, 34 por equipo, repartidos en 37
        // fechas). Cargado DT por DT, en cambio, los equipos quedan
        // desparejos y la fecha sigue contando como a medias.
        $revisar = [];
        foreach ($out as $tid => $x) if ($x['incompletas'] > 0) $revisar[] = (int) $tid;
        if ($revisar) {
            $porEquipo = DB::select(
                "SELECT x.torneo_id, x.equipo_id, COUNT(*) AS n FROM (
                     SELECT g.torneo_id, pa.equipol_id AS equipo_id
                       FROM partidos pa
                       INNER JOIN fechas fe ON fe.id = pa.fecha_id
                       INNER JOIN grupos g ON g.id = fe.grupo_id
                      WHERE g.torneo_id IN (" . implode(',', $revisar) . ")
                        AND fe.numero REGEXP '^[0-9]+$' AND g.nombre <> 'Playoffs'
                     UNION ALL
                     SELECT g.torneo_id, pa.equipov_id
                       FROM partidos pa
                       INNER JOIN fechas fe ON fe.id = pa.fecha_id
                       INNER JOIN grupos g ON g.id = fe.grupo_id
                      WHERE g.torneo_id IN (" . implode(',', $revisar) . ")
                        AND fe.numero REGEXP '^[0-9]+$' AND g.nombre <> 'Playoffs'
                 ) x GROUP BY x.torneo_id, x.equipo_id"
            );
            $cuentas = [];
            foreach ($porEquipo as $r) $cuentas[(int) $r->torneo_id][] = (int) $r->n;
            foreach ($cuentas as $tid => $ns) {
                if (count($ns) >= 2 && min($ns) > 0 && min($ns) === max($ns)) {
                    $out[$tid]['incompletas'] = 0;
                }
            }
        }

        return $out;
    }

    /**
     * La columna `torneos.inconcluso` se agrega a mano en phpMyAdmin (el deploy
     * es solo git pull). Hasta que exista, la pantalla sigue andando igual.
     */
    private function hayInconcluso(): bool
    {
        static $hay = null;
        if ($hay === null) {
            $hay = \Illuminate\Support\Facades\Schema::hasColumn('torneos', 'inconcluso');
        }
        return $hay;
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
            } elseif ((int) $fila->partidos === 0 || (int) $fila->fechas_incompletas > 0) {
                // La cantidad de equipos da, pero los partidos no: no está
                // completo, así que no va a la lista de posiciones.
                $listas['partidos_faltan'][] = $fila;
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
