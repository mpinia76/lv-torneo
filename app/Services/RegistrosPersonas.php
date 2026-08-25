<?php

namespace App\Services;

use App\Persona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto deportivo de una persona para la pantalla de repetidos.
 *
 * Por qué existe: mirando dos homónimos, el nombre y la fecha de nacimiento casi
 * nunca alcanzan para decidir. Apellidos como Da Silva, Rodrigues, Rodríguez o
 * Romero producen coincidencias exactas todo el tiempo, incluso con el mismo
 * puesto. Lo que sí decide es el CLUB y la TEMPORADA: dos fichas de la misma
 * persona comparten sí o sí algún plantel; dos homónimos casi nunca.
 *
 * Además resuelve el otro extremo: las fichas que no tienen ningún registro
 * asociado. Esas no son candidatas a fusión (no le aportan nada a la que queda),
 * son basura de importación y lo que corresponde es borrarlas.
 */
class RegistrosPersonas
{
    /** Cuántos clubes se listan por ficha antes de cortar con "y N más". */
    const MAX_CLUBES = 6;

    /** Caché de Schema::hasTable/hasColumn: son consultas a information_schema. */
    private static $esquema = [];

    // ------------------------------------------------------------------
    // Clubes y temporadas
    // ------------------------------------------------------------------

    /**
     * Clubes (y años) en los que figura cada persona.
     *
     * Se resuelve con unas pocas consultas para TODA la página, nunca una por
     * ficha: la pantalla vieja se murió justamente por hacer N+1 acá.
     *
     * Una misma persona puede aparecer en el mismo club con dos roles (jugó y
     * después dirigió): son dos entradas distintas, con su propio período.
     *
     * @param  int[] $ids
     * @return array [persona_id => [['equipo'=>string,'desde'=>int,'hasta'=>int,'rol'=>string], ...]]
     */
    public static function clubes(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        // Primero se juntan TODOS los años vistos por (persona, rol, club) y
        // recién al final se calcula el período. Si se fuera armando el mínimo
        // sobre la marcha, un torneo sin año cargado (year = 0) dejaría el
        // "desde" clavado en 0 para siempre y saldrían períodos tipo "0-2015".
        $crudo = [];
        foreach (self::fuentes() as $fuente) {
            foreach (self::traer($fuente, $ids) as $f) {
                $pid    = (int) $f->persona_id;
                $equipo = trim((string) $f->equipo);
                if ($equipo === '') {
                    continue;
                }
                $clave = $fuente['rol'] . '|' . $equipo;

                if (!isset($crudo[$pid][$clave])) {
                    $crudo[$pid][$clave] = ['equipo' => $equipo, 'rol' => $fuente['rol'], 'years' => []];
                }
                $year = (int) $f->year;
                if ($year > 0) {
                    $crudo[$pid][$clave]['years'][$year] = true;
                }
            }
        }

        $salida = [];
        foreach ($ids as $id) {
            $lista = [];
            foreach ($crudo[$id] ?? [] as $entrada) {
                $years = array_keys($entrada['years']);
                $lista[] = [
                    'equipo' => $entrada['equipo'],
                    'rol'    => $entrada['rol'],
                    'desde'  => $years ? min($years) : 0,
                    'hasta'  => $years ? max($years) : 0,
                ];
            }

            usort($lista, function ($a, $b) {
                if ($a['hasta'] === $b['hasta']) {
                    return strcmp($a['equipo'], $b['equipo']);
                }
                return $b['hasta'] <=> $a['hasta']; // el más reciente primero
            });

            $salida[$id] = $lista;
        }

        return $salida;
    }

    /**
     * De dónde sale "estuvo en tal club tal año", por rol.
     *
     * Los planteles son la fuente principal. Las alineaciones y los partidos del
     * DT se suman aparte porque hay fichas que tienen partidos sin figurar en
     * ningún plantel cargado, y son justo las fichas chicas —las de 2 registros—
     * las que hay que poder resolver de un vistazo.
     */
    private static function fuentes(): array
    {
        return [
            // Jugador en un plantel
            [
                'rol'    => 'jugador',
                'tabla'  => 'jugadors',
                'pivote' => ['tabla' => 'plantilla_jugadors', 'fk' => 'jugador_id'],
                'via'    => 'plantilla',
                'campo'  => 'plantilla_id',
            ],
            // Jugador en la alineación de un partido
            [
                'rol'    => 'jugador',
                'tabla'  => 'jugadors',
                'pivote' => ['tabla' => 'alineacions', 'fk' => 'jugador_id'],
                'via'    => 'partido',
                'campo'  => 'partido_id',
            ],
            // DT en un plantel
            [
                'rol'    => 'tecnico',
                'tabla'  => 'tecnicos',
                'pivote' => ['tabla' => 'plantilla_tecnicos', 'fk' => 'tecnico_id'],
                'via'    => 'plantilla',
                'campo'  => 'plantilla_id',
            ],
            // DT dirigiendo un partido
            [
                'rol'    => 'tecnico',
                'tabla'  => 'tecnicos',
                'pivote' => ['tabla' => 'partido_tecnicos', 'fk' => 'tecnico_id'],
                'via'    => 'partido',
                'campo'  => 'partido_id',
            ],
            // Árbitro: no tiene club, así que se muestra el torneo.
            [
                'rol'    => 'arbitro',
                'tabla'  => 'arbitros',
                'pivote' => ['tabla' => 'partido_arbitros', 'fk' => 'arbitro_id'],
                'via'    => 'torneo',
                'campo'  => 'partido_id',
            ],
        ];
    }

    private static function traer(array $f, array $ids)
    {
        $pivote = $f['pivote']['tabla'];
        $fk     = $f['pivote']['fk'];

        if (!self::hayTabla($f['tabla']) || !self::hayColumna($f['tabla'], 'persona_id')) {
            return [];
        }
        if (!self::hayTabla($pivote) || !self::hayColumna($pivote, $fk) || !self::hayColumna($pivote, $f['campo'])) {
            return [];
        }
        if (!self::hayTabla('torneos') || !self::hayTabla('grupos')) {
            return [];
        }

        $q = DB::table($f['tabla'] . ' as rol')
            ->join($pivote . ' as pv', 'pv.' . $fk, '=', 'rol.id')
            ->whereIn('rol.persona_id', $ids);

        if ($f['via'] === 'plantilla') {
            if (!self::hayTabla('plantillas') || !self::hayColumna('plantillas', 'grupo_id') || !self::hayTabla('equipos')) {
                return [];
            }
            $q->join('plantillas as pl', 'pl.id', '=', 'pv.plantilla_id')
              ->join('equipos as e', 'e.id', '=', 'pl.equipo_id')
              ->join('grupos as g', 'g.id', '=', 'pl.grupo_id')
              ->join('torneos as t', 't.id', '=', 'g.torneo_id')
              ->select('rol.persona_id', 'e.nombre as equipo', 't.year');

        } elseif ($f['via'] === 'partido') {
            // El equipo sale del propio registro (alineacions / partido_tecnicos
            // tienen equipo_id), y el año, del torneo del partido.
            if (!self::hayColumna($pivote, 'equipo_id') || !self::hayTabla('equipos')
                || !self::hayTabla('partidos') || !self::hayTabla('fechas')) {
                return [];
            }
            $q->join('equipos as e', 'e.id', '=', 'pv.equipo_id')
              ->join('partidos as pa', 'pa.id', '=', 'pv.partido_id')
              ->join('fechas as fe', 'fe.id', '=', 'pa.fecha_id')
              ->join('grupos as g', 'g.id', '=', 'fe.grupo_id')
              ->join('torneos as t', 't.id', '=', 'g.torneo_id')
              ->select('rol.persona_id', 'e.nombre as equipo', 't.year');

        } else { // torneo
            if (!self::hayTabla('partidos') || !self::hayTabla('fechas')) {
                return [];
            }
            $q->join('partidos as pa', 'pa.id', '=', 'pv.partido_id')
              ->join('fechas as fe', 'fe.id', '=', 'pa.fecha_id')
              ->join('grupos as g', 'g.id', '=', 'fe.grupo_id')
              ->join('torneos as t', 't.id', '=', 'g.torneo_id')
              ->select('rol.persona_id', 't.nombre as equipo', 't.year');
        }

        return $q->distinct()->get();
    }

    /**
     * Para cada club de $clubesA, si la otra ficha también pasó por ahí y si los
     * períodos se solapan. Es la señal fuerte del par: mismo club + mismos años
     * es, casi siempre, la misma persona.
     *
     * Se compara contra TODAS las entradas de B con ese nombre, no contra una:
     * una persona puede figurar en el mismo club como jugador y como DT, con dos
     * períodos distintos, y alcanza con que UNO se solape.
     *
     * @return array [nombreEquipo => bool $seSolapanLosAnios]
     */
    public static function enComun(array $clubesA, array $clubesB): array
    {
        $porNombre = [];
        foreach ($clubesB as $c) {
            $porNombre[self::normalizarEquipo($c['equipo'])][] = $c;
        }

        $comunes = [];
        foreach ($clubesA as $c) {
            $k = self::normalizarEquipo($c['equipo']);
            if (!isset($porNombre[$k])) {
                continue;
            }

            $solapa = false;
            foreach ($porNombre[$k] as $otro) {
                // Sin años cargados no se puede afirmar que se solapen.
                if (!$c['desde'] || !$otro['desde']) {
                    continue;
                }
                if ($c['desde'] <= $otro['hasta'] && $otro['desde'] <= $c['hasta']) {
                    $solapa = true;
                    break;
                }
            }

            // Si el mismo club aparece dos veces en A (jugador y DT), alcanza con
            // que una de las dos se solape para marcarlo.
            $comunes[$c['equipo']] = ($comunes[$c['equipo']] ?? false) || $solapa;
        }

        return $comunes;
    }

    private static function normalizarEquipo(string $nombre): string
    {
        $n = mb_strtolower(trim($nombre));
        $n = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $n
        );

        return preg_replace('/[^a-z0-9]+/', '', $n);
    }

    // ------------------------------------------------------------------
    // Personas sin ningún registro
    // ------------------------------------------------------------------

    /**
     * Las tablas hijas que cuentan como "registro". Es la MISMA lista que usa el
     * badge de la pantalla (FusionPersonas::peso): las bitácoras de importación
     * quedan afuera de los dos lados, si no aparecería un botón de borrar que el
     * servidor rechaza siempre.
     *
     * @return array [rol => ['tabla'=>..., 'fk'=>..., 'hijos'=>[...], 'logs'=>[...]]]
     */
    private static function mapaRegistros(): array
    {
        $excluidas = FusionPersonas::hijosQueNoSonRegistro();
        $mapa      = [];

        foreach (FusionPersonas::mapaRoles() as $rol => $cfg) {
            $hijos = [];
            $logs  = [];
            foreach (array_keys($cfg['hijos']) as $hijo) {
                if (in_array($hijo, $excluidas, true)) {
                    $logs[] = $hijo;
                } else {
                    $hijos[] = $hijo;
                }
            }
            $mapa[$rol] = ['tabla' => $cfg['tabla'], 'fk' => $cfg['fk'], 'hijos' => $hijos, 'logs' => $logs];
        }

        return $mapa;
    }

    /**
     * Personas que no tienen NI UNA fila hija en ninguna tabla de rol.
     *
     * Se arma con un NOT EXISTS por tabla hija en vez de contar todo: para una
     * persona que sí tiene registros, MySQL corta en el primer NOT EXISTS que
     * encuentra fila. Para las huérfanas, en cambio, se evalúan todos —por eso
     * el controller cachea el resultado en vez de repetir la consulta por página.
     */
    public static function queryHuerfanas()
    {
        $q = Persona::query();

        foreach (self::mapaRegistros() as $cfg) {
            if (!self::hayTabla($cfg['tabla']) || !self::hayColumna($cfg['tabla'], 'persona_id')) {
                continue;
            }
            foreach ($cfg['hijos'] as $hijo) {
                if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $cfg['fk'])) {
                    continue;
                }
                $q->whereNotExists(function ($s) use ($cfg, $hijo) {
                    $s->select(DB::raw(1))
                      ->from($hijo)
                      ->join($cfg['tabla'], $cfg['tabla'] . '.id', '=', $hijo . '.' . $cfg['fk'])
                      ->whereColumn($cfg['tabla'] . '.persona_id', 'personas.id');
                });
            }
        }

        return $q;
    }

    /**
     * Borra una persona que no tiene ningún registro asociado.
     *
     * Tres redes de seguridad, a propósito:
     *  1. Se vuelve a contar acá adentro, con las filas bloqueadas. Nunca se
     *     confía en el "sin registros" que vio el navegador: entre que se pintó
     *     la pantalla y se apretó el botón, un import pudo colgarle partidos.
     *  2. Las bitácoras de importación no bloquean el borrado (no son registros
     *     deportivos) pero tampoco quedan colgadas: se les suelta la referencia.
     *  3. Todo va en una transacción. Si igual quedara alguna fila hija que este
     *     código no conoce, la foreign key de MySQL hace fallar el DELETE y se
     *     revierte entero. Preferible un error a una persona borrada a medias.
     *
     * @return array ['ok' => bool, 'mensaje' => string]
     */
    public static function eliminar(int $personaId): array
    {
        try {
            DB::beginTransaction();

            $persona = DB::table('personas')->where('id', $personaId)->lockForUpdate()->first();
            if (!$persona) {
                DB::rollBack();
                return ['ok' => false, 'mensaje' => "La persona #{$personaId} ya no existe."];
            }

            $nombre = trim(($persona->apellido ?? '') . ', ' . ($persona->nombre ?? ''));
            $nombre = $nombre === ',' || $nombre === '' ? ('#' . $personaId) : $nombre;

            foreach (self::mapaRegistros() as $cfg) {
                if (!self::hayTabla($cfg['tabla']) || !self::hayColumna($cfg['tabla'], 'persona_id')) {
                    continue;
                }

                // lockForUpdate también sobre las filas de rol: si no, entre el
                // count() y el DELETE otra transacción puede colgarles un partido.
                $rolIds = DB::table($cfg['tabla'])
                    ->where('persona_id', $personaId)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all();

                if (!$rolIds) {
                    continue;
                }

                foreach ($cfg['hijos'] as $hijo) {
                    if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $cfg['fk'])) {
                        continue;
                    }
                    $n = DB::table($hijo)->whereIn($cfg['fk'], $rolIds)->count();
                    if ($n > 0) {
                        DB::rollBack();
                        return [
                            'ok'      => false,
                            'mensaje' => "No se borró {$nombre} (#{$personaId}): tiene {$n} registros en {$hijo}. "
                                . 'Refrescá la pantalla, el conteo cambió.',
                        ];
                    }
                }

                // Bitácoras de importación: no son registros, pero no pueden
                // quedar apuntando a un id que se va. Se les suelta la referencia.
                foreach ($cfg['logs'] as $log) {
                    if (!self::hayTabla($log) || !self::hayColumna($log, $cfg['fk'])) {
                        continue;
                    }
                    DB::table($log)->whereIn($cfg['fk'], $rolIds)->update([$cfg['fk'] => null]);
                }

                DB::table($cfg['tabla'])->where('persona_id', $personaId)->delete();
            }

            if (self::hayTabla('persona_tokens')) {
                DB::table('persona_tokens')->where('persona_id', $personaId)->delete();
            }
            if (self::hayTabla('persona_duplicados')) {
                DB::table('persona_duplicados')
                    ->where('persona_id', $personaId)
                    ->orWhere('simil_id', $personaId)
                    ->delete();
            }
            if (self::hayTabla('personas_verificadas')) {
                DB::table('personas_verificadas')
                    ->where('persona_id', $personaId)
                    ->orWhere('simil_id', $personaId)
                    ->delete();
            }

            DB::table('personas')->where('id', $personaId)->delete();

            DB::commit();

            Log::info('Persona sin registros eliminada', ['id' => $personaId, 'nombre' => $nombre]);

            return ['ok' => true, 'mensaje' => "Se borró {$nombre} (#{$personaId})."];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'ok'      => false,
                'mensaje' => "No se pudo borrar la persona #{$personaId}: " . $e->getMessage(),
            ];
        }
    }

    // ------------------------------------------------------------------

    private static function hayTabla(string $tabla): bool
    {
        $k = 't:' . $tabla;
        if (!array_key_exists($k, self::$esquema)) {
            self::$esquema[$k] = Schema::hasTable($tabla);
        }

        return self::$esquema[$k];
    }

    private static function hayColumna(string $tabla, string $columna): bool
    {
        $k = 'c:' . $tabla . '.' . $columna;
        if (!array_key_exists($k, self::$esquema)) {
            self::$esquema[$k] = self::hayTabla($tabla) && Schema::hasColumn($tabla, $columna);
        }

        return self::$esquema[$k];
    }
}
