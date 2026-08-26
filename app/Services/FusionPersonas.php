<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fusiona dos personas repetidas en una sola, dentro de una transacción.
 *
 * Reglas que se respetan sí o sí:
 *  - Nunca se pisa un dato del que se queda: solo se completan campos vacíos
 *    (vacío = NULL o cadena vacía; un 0 es un dato, no un vacío).
 *  - Antes de mover filas hijas se resuelven los CHOQUES: si las dos personas
 *    figuran en el mismo partido o en la misma plantilla, se fusionan esas dos
 *    filas (completando la del que se queda) y recién ahí se borra la sobrante.
 *    Así no se pierde, por ejemplo, el dorsal que solo tenía el duplicado.
 *  - La fila que se borra se borra ANTES de copiarle los datos al ganador, para
 *    no chocar contra índices únicos (documento, url, etc.) mientras las dos existen.
 *  - Si quedara alguna fila de rol colgada del perdedor, se aborta: nunca se
 *    borra una persona dejando huérfanos.
 */
class FusionPersonas
{
    /** Caché de Schema::hasTable/hasColumn: son consultas a information_schema. */
    private static $esquema = [];

    /**
     * Cada rol: su tabla, la clave foránea y las tablas hijas con las columnas
     * que definen un choque (vacío = esa tabla no puede chocar).
     */
    private static function relaciones(): array
    {
        return [
            'jugador' => [
                'tabla' => 'jugadors',
                'fk'    => 'jugador_id',
                'hijos' => [
                    'alineacions'                 => ['partido_id'],
                    'plantilla_jugadors'          => ['plantilla_id'],
                    'plantilla_partidos'          => ['partido_id'],
                    'gols'                        => [],
                    'tarjetas'                    => [],
                    'cambios'                     => [],
                    'penals'                      => [],
                    'jugador_estadistica_manuals' => [],
                ],
            ],
            'tecnico' => [
                'tabla' => 'tecnicos',
                'fk'    => 'tecnico_id',
                'hijos' => [
                    'plantilla_tecnicos'          => ['plantilla_id'],
                    // partido + equipo: un mismo partido puede tener al DT de cada
                    // equipo, así que el choque real incluye el equipo.
                    'partido_tecnicos'            => ['partido_id', 'equipo_id'],
                    'tecnico_estadistica_manuals' => [],
                    'import_partidos'             => [],
                    'tecnico_ciclos'              => [],
                ],
            ],
            'arbitro' => [
                'tabla' => 'arbitros',
                'fk'    => 'arbitro_id',
                'hijos' => [
                    'partido_arbitros' => ['partido_id', 'tipo'],
                ],
            ],
        ];
    }

    /**
     * El mapa de roles y tablas hijas, expuesto para que el resto de la app mire
     * exactamente las mismas tablas que la fusión (contar registros, borrar una
     * persona huérfana). Si acá se agrega una tabla, se agrega en todos lados.
     */
    public static function mapaRoles(): array
    {
        return self::relaciones();
    }

    /**
     * Tablas que hay que mover en una fusión pero que NO son un "registro
     * deportivo": son bitácoras de importación, no partidos jugados. No se
     * cuentan en el badge de la pantalla para no inflar fichas vacías.
     */
    private static $hijosQueNoSonRegistro = ['import_partidos'];

    /**
     * Misma lista, expuesta: el criterio de "esta ficha está vacía" tiene que ser
     * EL MISMO en el badge de la pantalla, en la pestaña "sin registros" y en el
     * borrado. Si cada uno mira tablas distintas, aparece un botón de borrar que
     * el servidor después rechaza siempre.
     */
    public static function hijosQueNoSonRegistro(): array
    {
        return self::$hijosQueNoSonRegistro;
    }

    /** Campos de `personas` que se completan en el ganador si los tiene vacíos. */
    private static $camposPersona = [
        'name', 'nombre', 'apellido', 'email', 'telefono', 'ciudad', 'observaciones',
        'nacimiento', 'fallecimiento', 'peso', 'altura', 'foto', 'nacionalidad',
    ];

    /**
     * Campos de las tablas de rol (jugadors/tecnicos/arbitros) que se completan.
     * Es una lista explícita a propósito: copiar "todas las columnas" arrastraría
     * slugs y flags que pueden chocar con los de una tercera persona.
     */
    private static $camposRol = [
        'foto', 'observaciones', 'nacimiento', 'fallecimiento', 'email', 'telefono',
        'ciudad', 'altura', 'peso', 'tipoJugador', 'pie', 'transfermarkt_url',
    ];

    /**
     * documento y tipoDocumento son un par indivisible: no tiene sentido copiar
     * el número sin el tipo.
     */
    private static $camposPar = [
        ['documento', 'tipoDocumento'],
    ];

    /**
     * @return array ['ok' => bool, 'mensaje' => string, 'detalle' => array]
     */
    public static function fusionar(int $ganadorId, int $perdedorId): array
    {
        if ($ganadorId === $perdedorId) {
            return ['ok' => false, 'mensaje' => 'Son la misma persona.', 'detalle' => []];
        }

        $detalle    = [];
        $committeado = false;

        try {
            DB::beginTransaction();

            // Se bloquean las dos filas para que dos fusiones simultáneas del
            // mismo par no se pisen.
            $filas = DB::table('personas')
                ->whereIn('id', [$ganadorId, $perdedorId])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $ganador  = $filas->get($ganadorId);
            $perdedor = $filas->get($perdedorId);

            if (!$ganador || !$perdedor) {
                DB::rollBack();

                return ['ok' => false, 'mensaje' => 'Alguna de las dos personas ya no existe.', 'detalle' => []];
            }

            // ---------- 1) Roles ----------
            foreach (self::relaciones() as $rol => $cfg) {
                $tabla = $cfg['tabla'];
                if (!self::hayTabla($tabla) || !self::hayColumna($tabla, 'persona_id')) {
                    continue;
                }

                // OJO: puede haber MÁS de una fila por persona (persona_id no
                // tiene único). Se procesan todas, si no quedan huérfanas.
                $filasPerdedor = DB::table($tabla)->where('persona_id', $perdedorId)->orderBy('id')->get();
                if ($filasPerdedor->isEmpty()) {
                    continue;
                }

                $filaGanador = DB::table($tabla)->where('persona_id', $ganadorId)->orderBy('id')->first();

                foreach ($filasPerdedor as $filaPerdedor) {
                    if (!$filaGanador) {
                        // El ganador no tenía este rol: alcanza con repuntar la persona.
                        DB::table($tabla)->where('id', $filaPerdedor->id)->update(['persona_id' => $ganadorId]);
                        $detalle[] = "{$tabla} #{$filaPerdedor->id} pasó a la persona {$ganadorId}";
                        $filaGanador = DB::table($tabla)->where('id', $filaPerdedor->id)->first();
                        continue;
                    }

                    $detalle = array_merge(
                        $detalle,
                        self::fusionarRol($cfg, $filaGanador, $filaPerdedor)
                    );
                }
            }

            // ---------- 2) Nada puede quedar colgado del perdedor ----------
            foreach (self::relaciones() as $cfg) {
                $tabla = $cfg['tabla'];
                if (!self::hayTabla($tabla) || !self::hayColumna($tabla, 'persona_id')) {
                    continue;
                }
                $sobran = DB::table($tabla)->where('persona_id', $perdedorId)->count();
                if ($sobran > 0) {
                    throw new \RuntimeException(
                        "Quedaron {$sobran} filas en {$tabla} apuntando a la persona {$perdedorId}. Se abortó para no dejar datos huérfanos."
                    );
                }
            }

            // ---------- 3) Bitácora (antes de borrar, con los datos a la vista) ----------
            DB::table('persona_fusiones')->insert([
                'persona_id'       => $ganadorId,
                'absorbida_id'     => $perdedorId,
                'absorbida_nombre' => mb_substr(trim(($perdedor->nombre ?? '') . ' ' . ($perdedor->apellido ?? '')), 0, 191),
                'detalle'          => json_encode([
                    'perdedor' => (array) $perdedor,
                    'acciones' => $detalle,
                ], JSON_UNESCAPED_UNICODE),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // ---------- 4) Índices auxiliares ----------
            DB::table('persona_tokens')->where('persona_id', $perdedorId)->delete();
            DB::table('persona_duplicados')
                ->where('persona_id', $perdedorId)
                ->orWhere('simil_id', $perdedorId)
                ->delete();

            if (self::hayTabla('personas_verificadas')) {
                DB::table('personas_verificadas')
                    ->where('persona_id', $perdedorId)
                    ->orWhere('simil_id', $perdedorId)
                    ->delete();
            }

            // ---------- 5) Primero se borra el duplicado ----------
            // Recién después se le copian los datos al que queda: si alguna columna
            // tuviera índice único (documento, por ejemplo), con los dos vivos el
            // UPDATE fallaría y no se podría fusionar nunca.
            DB::table('personas')->where('id', $perdedorId)->delete();

            $completar = self::completar($ganador, $perdedor, self::$camposPersona, self::$camposPar);
            if ($completar) {
                DB::table('personas')->where('id', $ganadorId)->update($completar);
                $detalle[] = 'personas: se completó ' . implode(', ', array_keys($completar));
            }

            DB::commit();
            $committeado = true;
        } catch (\Exception $e) {
            if (!$committeado) {
                DB::rollBack();
            }

            Log::error('Fusión de personas fallida', [
                'ganador'  => $ganadorId,
                'perdedor' => $perdedorId,
                'error'    => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'mensaje' => 'No se pudo fusionar: ' . $e->getMessage(),
                'detalle' => $detalle,
            ];
        }

        // Fuera de la transacción: si esto falla, la fusión ya está hecha y no
        // hay que decirle al usuario que falló (reintentaría sobre datos borrados).
        try {
            DuplicadosPersonas::indexarPersona($ganadorId);
        } catch (\Exception $e) {
            Log::warning('No se pudo reindexar la persona fusionada', [
                'persona' => $ganadorId,
                'error'   => $e->getMessage(),
            ]);
        }

        return [
            'ok'      => true,
            'mensaje' => 'Se fusionó la persona ' . $perdedorId . ' dentro de la ' . $ganadorId . '.',
            'detalle' => $detalle,
        ];
    }

    /**
     * Unifica DOS FICHAS DEL MISMO ROL (dos jugadors, dos arbitros...) sin tocar
     * `personas`. Es el caso de la pantalla "Reasignar" cuando las dos fichas ya
     * cuelgan de la misma persona: ahi no hay nada que fusionar a nivel persona,
     * pero si hay dos fichas y sus registros hay que juntarlos igual.
     *
     * Usa exactamente el mismo resolvedor de choques que la fusion de personas:
     * si las dos fichas estan en la misma plantilla o en el mismo partido, la
     * fila repetida se unifica en vez de romper por indice unico.
     *
     * @return array ['ok' => bool, 'mensaje' => string, 'detalle' => array]
     */
    public static function fusionarFilasDeRol(string $rol, int $idGanador, int $idPerdedor): array
    {
        $relaciones = self::relaciones();

        if (!isset($relaciones[$rol])) {
            return ['ok' => false, 'mensaje' => "Rol desconocido: {$rol}.", 'detalle' => []];
        }

        if ($idGanador === $idPerdedor) {
            return ['ok' => false, 'mensaje' => 'Es la misma ficha.', 'detalle' => []];
        }

        $cfg     = $relaciones[$rol];
        $tabla   = $cfg['tabla'];
        $detalle = [];

        try {
            DB::beginTransaction();

            $filas = DB::table($tabla)
                ->whereIn('id', [$idGanador, $idPerdedor])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $ganador  = $filas->get($idGanador);
            $perdedor = $filas->get($idPerdedor);

            if (!$ganador || !$perdedor) {
                DB::rollBack();

                return ['ok' => false, 'mensaje' => 'Alguna de las dos fichas ya no existe.', 'detalle' => []];
            }

            $detalle = self::fusionarRol($cfg, $ganador, $perdedor);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Unificación de fichas fallida', [
                'rol'      => $rol,
                'ganador'  => $idGanador,
                'perdedor' => $idPerdedor,
                'error'    => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'mensaje' => 'No se pudo unificar: ' . $e->getMessage(),
                'detalle' => $detalle,
            ];
        }

        return [
            'ok'      => true,
            'mensaje' => "Se unificó la ficha {$idPerdedor} dentro de la {$idGanador}.",
            'detalle' => $detalle,
        ];
    }

    /**
     * Mueve todo lo que cuelga de la fila de rol del perdedor a la del ganador,
     * resolviendo los choques sin perder información, y borra la del perdedor.
     */
    private static function fusionarRol(array $cfg, $filaGanador, $filaPerdedor): array
    {
        $detalle = [];
        $tabla   = $cfg['tabla'];
        $fk      = $cfg['fk'];
        $prefijo = DB::getTablePrefix();

        foreach ($cfg['hijos'] as $hijo => $choque) {
            if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)) {
                continue;
            }

            $choque = array_values(array_filter($choque, function ($c) use ($hijo) {
                return self::hayColumna($hijo, $c);
            }));

            $fusionadas = 0;

            if ($choque) {
                // Se buscan los pares (fila del ganador, fila del perdedor) que
                // representan el mismo hecho: el mismo partido, la misma plantilla.
                $on = [];
                foreach ($choque as $col) {
                    // `=` a propósito y no `<=>`: dos NULL no son el mismo partido.
                    $on[] = "g.`{$col}` = h.`{$col}`";
                }

                $pares = DB::select(
                    "SELECT g.id AS id_ganador, h.id AS id_perdedor
                       FROM `{$prefijo}{$hijo}` h
                       JOIN `{$prefijo}{$hijo}` g
                         ON " . implode(' AND ', $on) . "
                        AND g.`{$fk}` = ?
                      WHERE h.`{$fk}` = ?",
                    [$filaGanador->id, $filaPerdedor->id]
                );

                foreach ($pares as $par) {
                    $g = DB::table($hijo)->find($par->id_ganador);
                    $h = DB::table($hijo)->find($par->id_perdedor);
                    if (!$g || !$h) {
                        continue;
                    }

                    // Primero se borra la sobrante, después se completa la que queda
                    // (mismo motivo que con las personas: índices únicos).
                    DB::table($hijo)->where('id', $h->id)->delete();

                    $campos = array_values(array_diff(
                        self::columnas($hijo),
                        ['id', $fk, 'created_at', 'updated_at']
                    ));
                    $completar = self::completar($g, $h, $campos);
                    if ($completar) {
                        DB::table($hijo)->where('id', $g->id)->update($completar);
                    }
                    $fusionadas++;
                }
            }

            $movidas = DB::table($hijo)->where($fk, $filaPerdedor->id)->update([$fk => $filaGanador->id]);

            if ($movidas || $fusionadas) {
                $detalle[] = "{$hijo}: {$movidas} movidas"
                    . ($fusionadas ? ", {$fusionadas} repetidas unificadas" : '');
            }
        }

        // La fila de rol sobrante se borra antes de completar la que queda.
        DB::table($tabla)->where('id', $filaPerdedor->id)->delete();

        $completar = self::completar($filaGanador, $filaPerdedor, self::$camposRol, self::$camposPar);
        if ($completar) {
            DB::table($tabla)->where('id', $filaGanador->id)->update($completar);
            $detalle[] = "{$tabla} #{$filaGanador->id}: se completó " . implode(', ', array_keys($completar));
        }

        $detalle[] = "{$tabla} #{$filaPerdedor->id} eliminado";

        return $detalle;
    }

    /**
     * Devuelve los campos que están vacíos en $ganador y con dato en $perdedor.
     * Vacío = NULL o cadena vacía. Un 0 es un dato y no se pisa.
     *
     * @param array $pares grupos de campos que viajan juntos (documento + tipoDocumento)
     */
    private static function completar($ganador, $perdedor, array $campos, array $pares = []): array
    {
        $vacio = function ($v) {
            return $v === null || (is_string($v) && trim($v) === '');
        };

        $completar = [];

        foreach ($campos as $campo) {
            if (!property_exists($ganador, $campo) || !property_exists($perdedor, $campo)) {
                continue;
            }
            if ($vacio($ganador->$campo) && !$vacio($perdedor->$campo)) {
                $completar[$campo] = $perdedor->$campo;
            }
        }

        // Los grupos se copian enteros o no se copian.
        foreach ($pares as $grupo) {
            $aplicable = true;
            $valores   = [];

            foreach ($grupo as $campo) {
                if (!property_exists($ganador, $campo) || !property_exists($perdedor, $campo)) {
                    $aplicable = false;
                    break;
                }
                $valores[$campo] = $perdedor->$campo;
            }

            // Solo si el campo principal del grupo está vacío en el ganador
            // y cargado en el perdedor.
            $principal = $grupo[0];
            if ($aplicable
                && $vacio($ganador->$principal)
                && !$vacio($perdedor->$principal)) {
                foreach ($valores as $campo => $valor) {
                    if (!$vacio($valor)) {
                        $completar[$campo] = $valor;
                    }
                }
            }
        }

        return $completar;
    }

    // ------------------------------------------------------------------
    // Esquema cacheado (information_schema es caro en hosting compartido)
    // ------------------------------------------------------------------

    private static function hayTabla(string $tabla): bool
    {
        if (!array_key_exists("t:{$tabla}", self::$esquema)) {
            self::$esquema["t:{$tabla}"] = Schema::hasTable($tabla);
        }

        return self::$esquema["t:{$tabla}"];
    }

    private static function columnas(string $tabla): array
    {
        if (!array_key_exists("c:{$tabla}", self::$esquema)) {
            self::$esquema["c:{$tabla}"] = self::hayTabla($tabla) ? Schema::getColumnListing($tabla) : [];
        }

        return self::$esquema["c:{$tabla}"];
    }

    private static function hayColumna(string $tabla, string $columna): bool
    {
        return in_array($columna, self::columnas($tabla), true);
    }

    // ------------------------------------------------------------------
    // Cuánta información tiene cada persona (para sugerir cuál conservar)
    // ------------------------------------------------------------------

    /**
     * Cuenta registros asociados y campos cargados de cada persona.
     * Recibe TODOS los ids de una vez: nunca llamar dentro de un loop.
     */
    public static function peso(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        $peso = [];
        foreach ($ids as $id) {
            $peso[$id] = ['registros' => 0, 'campos' => 0, 'roles' => []];
        }

        // Se cuentan TODAS las tablas hijas del mapa de roles, no un subconjunto.
        // Antes solo miraba alineaciones, goles y planteles: una ficha con
        // tarjetas o cambios y nada más mostraba "0 reg.", y ese cero ahora
        // habilita el botón de borrar. El número tiene que ser el completo.
        $conteos = [];
        foreach (self::relaciones() as $rol => $cfg) {
            $hijos = [];
            foreach (array_keys($cfg['hijos']) as $hijo) {
                if (in_array($hijo, self::$hijosQueNoSonRegistro, true)) {
                    continue;
                }
                $hijos[$hijo] = $cfg['fk'];
            }
            $conteos[$rol] = ['tabla' => $cfg['tabla'], 'hijos' => $hijos];
        }

        foreach ($conteos as $rol => $cfg) {
            if (!self::hayTabla($cfg['tabla']) || !self::hayColumna($cfg['tabla'], 'persona_id')) {
                continue;
            }

            $roles = DB::table($cfg['tabla'])->select('id', 'persona_id')->whereIn('persona_id', $ids)->get();
            if ($roles->isEmpty()) {
                continue;
            }

            $porRol = [];
            foreach ($roles as $r) {
                $porRol[(int) $r->id] = (int) $r->persona_id;
                $peso[(int) $r->persona_id]['roles'][$rol] = (int) $r->id;
            }

            foreach ($cfg['hijos'] as $hijo => $fk) {
                if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)) {
                    continue;
                }
                $filas = DB::table($hijo)
                    ->select($fk, DB::raw('COUNT(*) as n'))
                    ->whereIn($fk, array_keys($porRol))
                    ->groupBy($fk)
                    ->get();

                foreach ($filas as $f) {
                    $pid = $porRol[(int) $f->$fk] ?? null;
                    if ($pid !== null && isset($peso[$pid])) {
                        $peso[$pid]['registros'] += (int) $f->n;
                    }
                }
            }
        }

        $personas = DB::table('personas')->whereIn('id', $ids)->get();
        foreach ($personas as $p) {
            $n = 0;
            foreach (self::$camposPersona as $campo) {
                if (property_exists($p, $campo) && $p->$campo !== null && $p->$campo !== '') {
                    $n++;
                }
            }
            $peso[(int) $p->id]['campos'] = $n;
        }

        return $peso;
    }
}
