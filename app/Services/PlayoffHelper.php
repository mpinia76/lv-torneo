<?php
namespace App\Services;

use App\Grupo;
use App\Fecha;
use App\Partido;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PlayoffHelper
{
    public static function actualizarCruces($grupo_id)
    {
        $grupo = Grupo::with('torneo')->findOrFail($grupo_id);
        $torneo_id = $grupo->torneo->id;

        // Buscamos todos los grupos de este torneo y sus posiciones
        $tabla = [];

        $grupos = Grupo::where('torneo_id', $torneo_id)->get();
        foreach ($grupos as $g) {
            if ($g->posiciones) {
                $tabla[$g->nombre] = self::posiciones($g->id);
            }
        }
        //Log::info('Tabla:', $tabla);
        // Obtenemos los cruces predefinidos (ej: 1A vs 2B)
        $cruces = DB::table('cruces')
            ->where('torneo_id', $torneo_id)
            ->get();

        $grupoPlayoffs = Grupo::where('torneo_id', $torneo_id)->where('nombre', 'Playoffs')->first();
        if (!$grupoPlayoffs) return;

        foreach ($cruces as $cruce) {
            $equipo1 = self::resolverEquipo($cruce->clasificado_1, $tabla, $torneo_id,$cruce->fase);
            $equipo2 = self::resolverEquipo($cruce->clasificado_2, $tabla, $torneo_id,$cruce->fase);

            //Log::info($equipo1.' vs. '.$equipo2);
            if (!$equipo1 || !$equipo2) continue; // aún no hay suficientes resultados

            // Crear o buscar la fecha correspondiente a la fase
            $fecha = Fecha::firstOrCreate([
                'grupo_id' => $grupoPlayoffs->id,
                'numero' => $cruce->fase,
            ], [
                'url_nombre' => strtolower(str_replace(' ', '-', $cruce->fase))
            ]);
            $partidoExistente = Partido::where('fecha_id', $fecha->id)
                ->where('orden', $cruce->orden)
                ->first();

            if (!$partidoExistente || !$partidoExistente->bloquear) {
                // Eliminar partidos previos que involucren a estos equipos en esta fecha
                Partido::where('fecha_id', $fecha->id)
                    ->where(function ($query) use ($equipo1, $equipo2) {
                        $query->where('equipol_id', $equipo1)
                            ->orWhere('equipov_id', $equipo1)
                            ->orWhere('equipol_id', $equipo2)
                            ->orWhere('equipov_id', $equipo2);
                    })
                    ->delete();

                // Crear o actualizar partido
                Partido::updateOrCreate([
                    'fecha_id' => $fecha->id,
                    'orden' => $cruce->orden
                ], [
                    'equipol_id' => $equipo1,
                    'equipov_id' => $equipo2,
                    'dia' => $cruce->dia,
                    'neutral' => $cruce->neutral,
                ]);
            } else {
                // Ya hay resultados, no modificar ese partido para no perder datos
                Log::info("Resultados cargados, no se recalcula ni borra para fecha $fecha->id");
            }
        }




    }

    private static function posiciones($grupo_id)
    {
        $grupo = \App\Grupo::findOrFail($grupo_id);

        $sql = "
        SELECT
            e.id AS equipo_id,
            SUM(
                CASE
                    WHEN p.equipol_id = e.id AND p.golesl > p.golesv THEN 3
                    WHEN p.equipol_id = e.id AND p.golesl = p.golesv THEN 1
                    WHEN p.equipov_id = e.id AND p.golesv > p.golesl THEN 3
                    WHEN p.equipov_id = e.id AND p.golesv = p.golesl THEN 1
                    ELSE 0
                END
            ) AS puntos,
            SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl WHEN p.equipov_id = e.id THEN p.golesv ELSE 0 END) AS gf,
            SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv WHEN p.equipov_id = e.id THEN p.golesl ELSE 0 END) AS gc,
            SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl WHEN p.equipov_id = e.id THEN p.golesv ELSE 0 END) -
            SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv WHEN p.equipov_id = e.id THEN p.golesl ELSE 0 END) AS diferencia
        FROM equipos e
        INNER JOIN plantillas pl ON pl.equipo_id = e.id AND pl.grupo_id = :grupo_id
        LEFT JOIN partidos p ON (p.equipol_id = e.id OR p.equipov_id = e.id)
        LEFT JOIN fechas f ON p.fecha_id = f.id
        LEFT JOIN grupos g ON f.grupo_id = g.id
        WHERE g.torneo_id = :torneo_id
          AND g.agrupacion = :agrupacion
          AND p.golesl IS NOT NULL AND p.golesv IS NOT NULL
        GROUP BY e.id
        ORDER BY puntos DESC, diferencia DESC, gf DESC
    ";

        $result = \DB::select($sql, [
            'grupo_id' => $grupo_id,
            'torneo_id' => $grupo->torneo_id,
            'agrupacion' => $grupo->agrupacion,
        ]);

        return array_map(function ($row) {
            return $row->equipo_id;
        }, $result);
    }


    private static function resolverEquipo($referencia, $tabla, $torneo_id, $fase_actual)
    {
        if (preg_match('/^(G|P)(\d+)$/', $referencia, $matches)) {
            $tipo = $matches[1];
            $orden = intval($matches[2]);
            //Log::info('Tipo: ' . $tipo . ' Orden: ' . $orden . ' Fase actual: ' . $fase_actual);

            // Buscar cruce anterior por siguiente_fase
            $cruceAnterior = DB::table('cruces')
                ->where('torneo_id', $torneo_id)
                ->where('orden', $orden)
                ->where('siguiente_fase', $fase_actual)
                ->first();

            if (!$cruceAnterior) return null;

            $resultado = self::calcularResultadoDelCruce($cruceAnterior, $tabla, $torneo_id);

            if (!$resultado) return null;

            return $tipo === 'G' ? $resultado['ganador'] : $resultado['perdedor'];
        }

        if (preg_match('/^(\d+)([A-Z])$/', $referencia, $matches)) {
            $pos = intval($matches[1]);
            $grupo = $matches[2];
            return $tabla[$grupo][$pos - 1] ?? null;
        }

        return null;
    }

    private static function calcularResultadoDelCruce($cruce, $tabla, $torneo_id)
    {
        // Buscar fecha de playoffs
        $fechaPlayoffs = Grupo::where('torneo_id', $torneo_id)
            ->where('nombre', 'Playoffs')
            ->first();

        if (!$fechaPlayoffs) return null;

        // Buscar la fecha correspondiente a esta fase
        $fecha = Fecha::where('grupo_id', $fechaPlayoffs->id)
            ->where('numero', $cruce->fase)
            ->first();

        if (!$fecha) return null;

        // Buscar todos los partidos de ida y vuelta de este cruce (puede haber 1 o 2)
        $partidos = Partido::where('fecha_id', $fecha->id)
            ->where(function ($q) use ($cruce) {
                $q->where(function ($q2) use ($cruce) {
                    $q2->where('equipol_id', $cruce->equipo1)
                        ->where('equipov_id', $cruce->equipo2);
                })->orWhere(function ($q2) use ($cruce) {
                    $q2->where('equipol_id', $cruce->equipo2)
                        ->where('equipov_id', $cruce->equipo1);
                });
            })->get();

        if ($partidos->isEmpty()) return null;

        // Inicializar goles y penales
        $goles = ['equipo1' => 0, 'equipo2' => 0];
        $penales = ['equipo1' => 0, 'equipo2' => 0];

        foreach ($partidos as $partido) {
            if ($partido->equipol_id == $cruce->equipo1) {
                $goles['equipo1'] += $partido->golesl;
                $goles['equipo2'] += $partido->golesv;
                $penales['equipo1'] += $partido->penalesl ?? 0;
                $penales['equipo2'] += $partido->penalesv ?? 0;
            } else {
                $goles['equipo1'] += $partido->golesv;
                $goles['equipo2'] += $partido->golesl;
                $penales['equipo1'] += $partido->penalesv ?? 0;
                $penales['equipo2'] += $partido->penalesl ?? 0;
            }
        }

        // Determinar ganador global
        if ($goles['equipo1'] > $goles['equipo2']) {
            return ['ganador' => $cruce->equipo1, 'perdedor' => $cruce->equipo2];
        } elseif ($goles['equipo2'] > $goles['equipo1']) {
            return ['ganador' => $cruce->equipo2, 'perdedor' => $cruce->equipo1];
        }

        // En empate global, decidir por penales si existen
        if ($penales['equipo1'] > 0 || $penales['equipo2'] > 0) {
            if ($penales['equipo1'] > $penales['equipo2']) {
                return ['ganador' => $cruce->equipo1, 'perdedor' => $cruce->equipo2];
            } elseif ($penales['equipo2'] > $penales['equipo1']) {
                return ['ganador' => $cruce->equipo2, 'perdedor' => $cruce->equipo1];
            }
        }

        // Si sigue empate y no hay penales, devolver null
        return null;
    }

}


