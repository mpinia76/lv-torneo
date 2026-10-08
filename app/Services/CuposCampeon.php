<?php

namespace App\Services;

use App\PosicionTorneo;
use App\Torneo;
use Illuminate\Support\Facades\Schema;

/**
 * CUPOS DEL CAMPEÓN DE OTRO TORNEO (copa nacional, copa internacional).
 *
 * Una fila de `torneo_clasificacions` con `campeon_torneo_id` es el cupo que
 * da ganar ese torneo: «Europa League 1 — campeón de Coupe de France 2025/26».
 * Se carga con las cantidades OFICIALES y el sistema resuelve lo que antes se
 * hacía a mano en cada liga:
 *
 *  - Si el campeón ya entra por la tabla a esa zona o a una mejor (Lens 2°,
 *    campeón de copa, ya va a la Champions), el cupo BAJA al siguiente de la
 *    tabla: la zona conserva su cantidad y la cubre el próximo por posición.
 *  - Si no (Toulouse 13°, campeón de copa), el campeón va a esa zona como si
 *    estuviera clasificado a mano, y el cupo deja de contarse por posición.
 *  - Si el torneo de copa no tiene campeón cargado (posicion_torneos, 1°), el
 *    cupo se cuenta como uno más por posición y se avisa.
 *
 * Las zonas se comparan por el orden de las clasificaciones (por id): la
 * primera es la mejor, igual que en ZonasTabla y el Acumulado.
 *
 * Los clasificados automáticos NO salen del conteo del descenso: el campeón de
 * copa que termina en zona de descenso baja igual (Wigan 2012/13).
 */
class CuposCampeon
{
    /**
     * @param Torneo $torneo
     * @param int[]  $ordenEquipos equipo_id en el orden de la tabla
     * @param array  $yaUbicados   [equipo_id => nombre de zona] (manuales, campeones)
     * @return array [
     *   'cupos'   => [nombre => cantidad por posición, en orden de zona],
     *   'auto'    => [equipo_id => nombre de zona],
     *   'avisos'  => [string],
     *   'detalle' => [[...]] para el debug,
     * ]
     */
    public static function resolver(Torneo $torneo, array $ordenEquipos, array $yaUbicados = [])
    {
        $hayColumna = self::hayColumna();
        $filas = $torneo->clasificaciones->sortBy('id')->values();

        // Rango de cada zona por su primera aparición; cantidades sumadas por
        // nombre (dos filas «Europa League» = una zona con las dos cantidades).
        $rango = [];
        $cupos = [];
        foreach ($filas as $c) {
            $n = (string) $c->nombre;
            if (!isset($rango[$n])) {
                $rango[$n] = count($rango);
                $cupos[$n] = 0;
            }
            $cupos[$n] += (int) $c->cantidad;
        }

        $auto = [];
        $avisos = [];
        $detalle = [];

        if (!$hayColumna) {
            return compact('cupos', 'auto', 'avisos', 'detalle');
        }

        foreach ($filas as $c) {
            if (empty($c->campeon_torneo_id)) continue;

            $zona = (string) $c->nombre;
            $copa = Torneo::find($c->campeon_torneo_id);
            $nombreCopa = $copa ? trim($copa->nombre . ' ' . $copa->year) : '#' . $c->campeon_torneo_id;
            $campeon = PosicionTorneo::where('torneo_id', $c->campeon_torneo_id)
                ->where('posicion', 1)->value('equipo_id');

            if (!$campeon) {
                $avisos[] = 'El cupo de ' . $zona . ' del campeón de ' . $nombreCopa . ' se cuenta por posición: '
                    . 'ese torneo no tiene campeón cargado en posiciones finales.';
                $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => null, 'resultado' => 'sin campeón'];
                continue;
            }
            $campeon = (int) $campeon;

            // Ya ubicado (a mano, campeón de un torneo del acumulado, o por otro cupo).
            $ubicado = isset($yaUbicados[$campeon]) ? $yaUbicados[$campeon]
                : (isset($auto[$campeon]) ? $auto[$campeon] : null);
            if ($ubicado !== null) {
                $mejor = isset($rango[$ubicado]) && $rango[$ubicado] <= $rango[$zona];
                if ($mejor) {
                    $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => $campeon,
                        'resultado' => 'ya ubicado en ' . $ubicado . ': el cupo baja'];
                    continue;
                }
                // Ubicado en una zona peor (raro): lo sube a ésta.
                if (isset($auto[$campeon])) {
                    $cupos[$auto[$campeon]] += 1;   // devuelve el cupo que ocupaba
                    unset($auto[$campeon]);
                } else {
                    $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => $campeon,
                        'resultado' => 'clasificado a mano en ' . $ubicado . ': se respeta, el cupo baja'];
                    continue;
                }
            }

            // No juega este torneo (campeón de copa de otra división): el cupo
            // es suyo y no lo cubre nadie de la tabla.
            if (!in_array($campeon, array_map('intval', $ordenEquipos), true)) {
                $cupos[$zona] = max(0, $cupos[$zona] - 1);
                $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => $campeon,
                    'resultado' => 'no está en esta tabla: el cupo es suyo y no baja'];
                continue;
            }

            // ¿A qué zona llega por posición, con los cupos de ahora?
            $porPosicion = self::zonaPorPosicion($campeon, $ordenEquipos, $cupos, $yaUbicados + $auto);
            if ($porPosicion !== null && $rango[$porPosicion] <= $rango[$zona]) {
                $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => $campeon,
                    'resultado' => 'entra por la tabla a ' . $porPosicion . ': el cupo baja'];
                continue;
            }

            // Toma el cupo: sale del conteo por posición de esa zona.
            $auto[$campeon] = $zona;
            $cupos[$zona] = max(0, $cupos[$zona] - 1);
            $detalle[] = ['zona' => $zona, 'copa' => $nombreCopa, 'campeon' => $campeon,
                'resultado' => 'toma el cupo' . ($porPosicion ? ' (por la tabla iba a ' . $porPosicion . ')' : '')];
        }

        return compact('cupos', 'auto', 'avisos', 'detalle');
    }

    /** Zona por posición de un equipo, sin contar a los ya ubicados. */
    private static function zonaPorPosicion($equipoId, array $orden, array $cupos, array $ubicados)
    {
        $pos = 0;
        foreach ($orden as $e) {
            $e = (int) $e;
            if (isset($ubicados[$e])) continue;
            $pos++;
            if ($e === (int) $equipoId) break;
        }
        if (!in_array((int) $equipoId, array_map('intval', $orden), true)) return null;

        $inicio = 1;
        foreach ($cupos as $nombre => $cant) {
            $fin = $inicio + (int) $cant - 1;
            if ($pos >= $inicio && $pos <= $fin) return $nombre;
            $inicio = $fin + 1;
        }
        return null;
    }

    public static function hayColumna()
    {
        static $hay = null;
        if ($hay === null) {
            try {
                $hay = Schema::hasColumn('torneo_clasificacions', 'campeon_torneo_id');
            } catch (\Throwable $e) {
                $hay = false;
            }
        }
        return $hay;
    }
}
