<?php
namespace App\Helpers;

use App\Grupo;
use App\Fecha;
use App\Partido;
use Illuminate\Support\Facades\DB;

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
            $tabla[$g->nombre] = self::posiciones($g->id);
        }

        // Obtenemos los cruces predefinidos (ej: 1A vs 2B)
        $cruces = DB::table('cruces')
            ->where('torneo_id', $torneo_id)
            ->get();

        $grupoPlayoffs = Grupo::where('torneo_id', $torneo_id)->where('nombre', 'Playoffs')->first();
        if (!$grupoPlayoffs) return;

        foreach ($cruces as $cruce) {
            // Ej: clasificado_1 = '1A', clasificado_2 = '2B'
            $pos1 = intval($cruce->clasificado_1[0]);
            $grupo1 = substr($cruce->clasificado_1, 1);

            $pos2 = intval($cruce->clasificado_2[0]);
            $grupo2 = substr($cruce->clasificado_2, 1);

            $equipo1 = $tabla[$grupo1][$pos1 - 1] ?? null;
            $equipo2 = $tabla[$grupo2][$pos2 - 1] ?? null;

            if (!$equipo1 || !$equipo2) continue; // aún no hay suficientes resultados

            // Crear o buscar la fecha correspondiente a la fase
            $fecha = Fecha::firstOrCreate([
                'grupo_id' => $grupoPlayoffs->id,
                'numero' => $cruce->fase,
            ], [
                'url_nombre' => strtolower(str_replace(' ', '-', $cruce->fase))
            ]);

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
}


