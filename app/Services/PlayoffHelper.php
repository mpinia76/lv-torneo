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

        // Obtenemos los cruces predefinidos (ej: 1A vs 2B)
        $cruces = DB::table('cruces')
            ->where('torneo_id', $torneo_id)
            ->get();

        $grupoPlayoffs = Grupo::where('torneo_id', $torneo_id)->where('nombre', 'Playoffs')->first();
        if (!$grupoPlayoffs) return;

        foreach ($cruces as $cruce) {
            $equipo1 = self::resolverEquipo($cruce->clasificado_1, $tabla, $torneo_id,$cruce->fase);
            $equipo2 = self::resolverEquipo($cruce->clasificado_2, $tabla, $torneo_id,$cruce->fase);

            Log::info($equipo1.' vs. '.$equipo2);
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
        // Devuelve un array de equipo_id en orden de posición
        $sql = "
            SELECT equipo_id
            FROM (
                SELECT
                    e.id AS equipo_id,
                    SUM(
                        CASE
                            WHEN p.equipol_id = e.id THEN
                                CASE
                                    WHEN p.golesl > p.golesv THEN 3
                                    WHEN p.golesl = p.golesv THEN 1
                                    ELSE 0
                                END
                            WHEN p.equipov_id = e.id THEN
                                CASE
                                    WHEN p.golesv > p.golesl THEN 3
                                    WHEN p.golesv = p.golesl THEN 1
                                    ELSE 0
                                END
                            ELSE 0
                        END
                    ) AS puntos,
                    SUM(CASE WHEN p.equipol_id = e.id THEN p.golesl WHEN p.equipov_id = e.id THEN p.golesv ELSE 0 END) AS gf,
                    SUM(CASE WHEN p.equipol_id = e.id THEN p.golesv WHEN p.equipov_id = e.id THEN p.golesl ELSE 0 END) AS gc
                FROM equipos e
                LEFT JOIN partidos p ON (p.equipol_id = e.id OR p.equipov_id = e.id)
                LEFT JOIN fechas f ON p.fecha_id = f.id
                WHERE f.grupo_id = :grupo_id
                GROUP BY e.id
            ) AS tabla
            ORDER BY puntos DESC, (gf - gc) DESC, gf DESC
        ";

        $result = DB::select($sql, ['grupo_id' => $grupo_id]);

        return array_map(function ($row) {
            return $row->equipo_id;
        }, $result);

    }

    private static function resolverEquipo($referencia, $tabla, $torneo_id, $fase_actual)
    {
        if (preg_match('/^(G|P)(\d+)$/', $referencia, $matches)) {
            $tipo = $matches[1];
            $orden = intval($matches[2]);
            Log::info('Tipo: ' . $tipo . ' Orden: ' . $orden . ' Fase actual: ' . $fase_actual);

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
            Log::info('Pos: ' . $pos . ' Grupo: ' . $grupo);
            Log::info(print_r($matches, true));
            Log::info(print_r($tabla, true));
            return $tabla[$grupo][$pos - 1] ?? null;
        }

        return null;
    }

    private static function calcularResultadoDelCruce($cruce, $tabla, $torneo_id)
    {
        // Buscar partido correspondiente a este cruce
        $fechaPlayoffs = Grupo::where('torneo_id', $torneo_id)
            ->where('nombre', 'Playoffs')
            ->first();

        if (!$fechaPlayoffs) return null;

        //Log::info(print_r($cruce,true));

        $fecha = Fecha::where('grupo_id', $fechaPlayoffs->id)
            ->where('numero', $cruce->fase)
            ->first();

        if (!$fecha) return null;

        $partido = Partido::where('fecha_id', $fecha->id)
            ->where('orden', $cruce->orden)
            ->first();

        if (!$partido) return null;

        // Determinar ganador y perdedor
        if ($partido->golesl > $partido->golesv) {
            return ['ganador' => $partido->equipol_id, 'perdedor' => $partido->equipov_id];
        } elseif ($partido->golesv > $partido->golesl) {
            return ['ganador' => $partido->equipov_id, 'perdedor' => $partido->equipol_id];
        }

        // En empate, resolver por penales si están cargados
        if (!is_null($partido->penalesl) && !is_null($partido->penalesv)) {
            if ($partido->penalesl > $partido->penalesv) {
                return ['ganador' => $partido->equipol_id, 'perdedor' => $partido->equipov_id];
            } elseif ($partido->penalesv > $partido->penalesl) {
                return ['ganador' => $partido->equipov_id, 'perdedor' => $partido->equipol_id];
            }
        }
        return null;
    }
}


