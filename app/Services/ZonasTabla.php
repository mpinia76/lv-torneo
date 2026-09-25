<?php

namespace App\Services;

use App\EquipoClasificado;
use App\Grupo;
use App\Torneo;

/**
 * Zonas de una tabla de liga de una sola rueda de posiciones (Premier, LaLiga,
 * Brasileirão...): quién va a cada copa y quién desciende, con los mismos datos
 * que usa el Acumulado de Argentina:
 *   - torneo_clasificacions: "Libertadores 4", "Sudamericana 6"... (en orden de id)
 *   - equipo_clasificados: clasificados a mano (campeón de copa, etc.). Van a su
 *     zona y NO ocupan cupo por posición: el cupo baja al siguiente, igual que
 *     en el Acumulado.
 *   - torneos.descenso: los últimos N (sin contar los clasificados a mano).
 *
 * Se prende sólo si el torneo tiene cargada al menos una clasificación a copa:
 * `descenso` viene en 2 por defecto en todos los torneos, y sin ese opt-in
 * cualquier tabla vieja aparecería con los dos últimos en rojo.
 * No se aplica cuando el torneo ya se marca en el Acumulado (algún grupo con
 * acumulado), en copas, en tablas partidas en grupos ni en torneos inconclusos.
 * Si desciende por promedios, la tabla marca las copas pero no el descenso
 * (eso lo dice la pantalla de promedios).
 */
class ZonasTabla
{
    /** Clases CSS de las zonas de copa, en el orden de las clasificaciones. */
    const CLASES = ['t-zona-1', 't-zona-2', 't-zona-3', 't-zona-4'];

    /**
     * @param Torneo $torneo
     * @return bool
     */
    public static function aplica(Torneo $torneo)
    {
        if ($torneo->tipo === 'Copa' || !empty($torneo->inconcluso)) {
            return false;
        }
        if ($torneo->clasificaciones->isEmpty()) {
            return false;
        }
        $conPosiciones = 0;
        foreach (Grupo::where('torneo_id', $torneo->id)->get() as $g) {
            if ($g->acumulado) {
                return false;   // lo marca el Acumulado
            }
            if ($g->posiciones) {
                $conPosiciones++;
            }
        }
        return $conPosiciones === 1;
    }

    /**
     * Marca $equipo->zona (nombre o null) y $equipo->zonaClase en cada fila.
     * Devuelve la leyenda: [nombre => clase], sólo con las zonas que aparecen.
     *
     * @param Torneo $torneo
     * @param array $posiciones filas ordenadas (equipo_id)
     * @return array
     */
    public static function marcar(Torneo $torneo, array $posiciones)
    {
        $clasificaciones = $torneo->clasificaciones->sortBy('id')->values();

        // Una clasificación llamada "Descenso" (cantidad 0) sirve para los
        // descensos administrativos marcados a mano (Elche 2014/15): va en rojo
        // y no consume color de copa.
        $claseDe = [];
        $n = 0;
        foreach ($clasificaciones as $c) {
            if (mb_strtolower(trim($c->nombre)) === 'descenso') {
                $claseDe[$c->nombre] = 't-desciende';
                continue;
            }
            $claseDe[$c->nombre] = isset(self::CLASES[$n]) ? self::CLASES[$n] : 't-zona-4';
            $n++;
        }

        $manuales = EquipoClasificado::where('torneo_id', $torneo->id)
            ->with('clasificacion')
            ->get()
            ->keyBy('equipo_id');

        // Descenso por posición; si baja por promedios no se marca acá.
        $descenso = ((int) ($torneo->descenso_promedio ?? 0)) > 0 ? 0 : (int) ($torneo->descenso ?? 0);

        $manualesEnTabla = 0;
        foreach ($posiciones as $fila) {
            if (isset($manuales[$fila->equipo_id]) && $manuales[$fila->equipo_id]->clasificacion) {
                $manualesEnTabla++;
            }
        }
        $totalPorPuntos = count($posiciones) - $manualesEnTabla;

        $usadas = [];
        // Primera pasada: los clasificados a mano van a su zona.
        foreach ($posiciones as $fila) {
            $fila->zona = null;
            $fila->zonaClase = '';
            $fila->zonaManual = false;

            $manual = isset($manuales[$fila->equipo_id]) ? $manuales[$fila->equipo_id] : null;
            if ($manual && $manual->clasificacion) {
                $nombre = $manual->clasificacion->nombre;
                $fila->zona = $nombre;
                $fila->zonaClase = isset($claseDe[$nombre]) ? $claseDe[$nombre] : 't-zona-4';
                $fila->zonaManual = true;
                $usadas[$nombre] = $fila->zonaClase;
            }
        }

        // Segunda pasada con la posición efectiva (ignora los manuales ya vistos).
        $vistos = 0;
        $idx = 0;
        foreach ($posiciones as $fila) {
            $idx++;
            if ($fila->zonaManual) {
                $vistos++;
                continue;
            }
            $pos = $idx - $vistos;

            $inicio = 1;
            foreach ($clasificaciones as $c) {
                $fin = $inicio + (int) $c->cantidad - 1;
                if ($pos >= $inicio && $pos <= $fin) {
                    $fila->zona = $c->nombre;
                    $fila->zonaClase = $claseDe[$c->nombre];
                    $usadas[$c->nombre] = $fila->zonaClase;
                    break;
                }
                $inicio = $fin + 1;
            }

            if ($descenso > 0 && $pos > $totalPorPuntos - $descenso) {
                $fila->zona = 'Descenso';
                $fila->zonaClase = 't-desciende';
                $usadas['Descenso'] = 't-desciende';
            }
        }

        // Leyenda en el orden de la tabla: copas por id, descenso al final.
        $leyenda = [];
        $hayDescenso = isset($usadas['Descenso']);
        foreach ($claseDe as $nombre => $clase) {
            if (!isset($usadas[$nombre])) {
                continue;
            }
            if ($clase === 't-desciende') {
                $hayDescenso = true;   // se lista una sola vez, al final
                continue;
            }
            $leyenda[$nombre] = $clase;
        }
        if ($hayDescenso) {
            $leyenda['Descenso'] = 't-desciende';
        }
        return $leyenda;
    }
}
