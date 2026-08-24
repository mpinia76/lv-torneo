<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Controles de carga de partidos.
 *
 * Antes había siete pantallas (alineaciones, goles, tarjetas, cambios,
 * árbitros, técnicos, penales) y cada una repetía el mismo SELECT con los
 * mismos cinco JOIN, entre dos y cuatro veces. Al abrir una pantalla se
 * ejecutaban TODAS las consultas de sus pestañas aunque solo se mirara una, y
 * como los paginadores compartían el parámetro `page`, pasar a la página 2 de
 * una pestaña movía también a las otras.
 *
 * Acá está todo eso en un solo lugar:
 *
 *   - `base()` arma el FROM + JOIN + filtros una sola vez;
 *   - cada chequeo es un método que solo agrega su condición;
 *   - la pantalla ejecuta ÚNICAMENTE la consulta del chequeo que se está
 *     mirando (el resto son links);
 *   - los totales de cada chequeo se calculan aparte, por AJAX, y quedan
 *     cacheados 15 minutos.
 *
 * Para agregar un control nuevo alcanza con sumar una entrada en
 * `definiciones()` y el método que arma su condición.
 */
class Controles
{
    /** Filas por página en todas las pantallas de control. */
    public const POR_PAGINA = 25;

    /** Cuánto vive el total cacheado de un chequeo, en segundos. */
    private const TTL_CONTEO = 900;

    /**
     * Los totales se invalidan subiendo este número, no borrando clave por
     * clave: el driver de cache es `file` y no soporta tags.
     */
    private const CLAVE_VERSION = 'controles.version';

    /** Los tres roles que tiene que tener sí o sí una terna arbitral. */
    public const ROLES_TERNA = [
        'Principal' => 'Principal',
        'Linea 1'   => 'Línea 1',
        'Linea 2'   => 'Línea 2',
    ];

    /**
     * El catálogo de chequeos, agrupado como se muestra en el panel.
     *
     * Cada chequeo declara:
     *   titulo   - el nombre corto que va en el menú;
     *   ayuda    - qué detecta, en una línea, para no tener que leer el SQL;
     *   jugador  - si la fila es un jugador dentro del partido o el partido solo;
     *   detalle  - partial opcional de `controles/detalles/` con datos extra;
     *   acciones - botones de la fila (las claves las resuelve `_acciones`);
     *   metodo   - el método de esta clase que arma la consulta.
     */
    public function definiciones(): array
    {
        return [
            'Alineaciones' => [
                'alineaciones.faltan' => [
                    'titulo'   => 'Once incompleto',
                    'ayuda'    => 'Partidos donde algún equipo no tiene exactamente 11 titulares.',
                    'jugador'  => false,
                    'detalle'  => 'titulares',
                    'acciones' => ['alineaciones', 'incidencia'],
                    'metodo'   => 'alineacionesFaltan',
                ],
                'alineaciones.sin_jugadores' => [
                    'titulo'   => 'Sin jugadores',
                    'ayuda'    => 'Partidos con resultado cargado pero sin ningún titular y sin incidencia que lo justifique.',
                    'jugador'  => false,
                    'acciones' => ['alineaciones', 'incidencia'],
                    'metodo'   => 'alineacionesSinJugadores',
                ],
            ],

            'Goles' => [
                'goles.sin_jugar' => [
                    'titulo'   => 'Goleador que no jugó',
                    'ayuda'    => 'El goleador no figura como titular ni entró desde el banco en ese partido.',
                    'jugador'  => true,
                    'acciones' => ['alineaciones', 'goles', 'cambios', 'incidencia'],
                    'metodo'   => 'golesSinJugar',
                ],
                'goles.repetidos' => [
                    'titulo'   => 'Goles repetidos',
                    'ayuda'    => 'El mismo jugador con más de un gol en el mismo minuto: casi siempre es carga duplicada.',
                    'jugador'  => true,
                    'detalle'  => 'cantidad',
                    'acciones' => ['goles'],
                    'metodo'   => 'golesRepetidos',
                ],
                'goles.diferencia' => [
                    'titulo'   => 'No coinciden con el resultado',
                    'ayuda'    => 'La cantidad de goles cargados no da el resultado del partido. Solo mira partidos con alineación cargada.',
                    'jugador'  => false,
                    'detalle'  => 'goles',
                    'acciones' => ['goles', 'incidencia'],
                    'metodo'   => 'golesDiferencia',
                ],
            ],

            'Tarjetas' => [
                'tarjetas.sin_jugar' => [
                    'titulo'   => 'Amonestado que no jugó',
                    'ayuda'    => 'La tarjeta es de un jugador que no figura en la alineación de ese partido.',
                    'jugador'  => true,
                    'acciones' => ['alineaciones', 'tarjetas', 'incidencia'],
                    'metodo'   => 'tarjetasSinJugar',
                ],
                'tarjetas.repetidas' => [
                    'titulo'   => 'Tarjetas repetidas',
                    'ayuda'    => 'El mismo jugador con la misma tarjeta más de una vez en el partido.',
                    'jugador'  => true,
                    'detalle'  => 'cantidad',
                    'acciones' => ['tarjetas'],
                    'metodo'   => 'tarjetasRepetidas',
                ],
            ],

            'Cambios' => [
                'cambios.sin_jugar' => [
                    'titulo'   => 'Cambio de alguien que no jugó',
                    'ayuda'    => 'El cambio es de un jugador que no figura en la alineación de ese partido.',
                    'jugador'  => true,
                    'acciones' => ['alineaciones', 'cambios', 'incidencia'],
                    'metodo'   => 'cambiosSinJugar',
                ],
                'cambios.repetidos' => [
                    'titulo'   => 'Cambios repetidos',
                    'ayuda'    => 'El mismo jugador entra (o sale) más de una vez en el mismo partido.',
                    'jugador'  => true,
                    'detalle'  => 'cantidad',
                    'acciones' => ['cambios'],
                    'metodo'   => 'cambiosRepetidos',
                ],
                'cambios.impares' => [
                    'titulo'   => 'Entra sin salir',
                    'ayuda'    => 'Minutos donde la cantidad de "Entra" no coincide con la de "Sale".',
                    'jugador'  => false,
                    'detalle'  => 'minutos',
                    'acciones' => ['cambios', 'incidencia'],
                    'metodo'   => 'cambiosImpares',
                ],
                'cambios.titulares_entran' => [
                    'titulo'   => 'Titular que entra',
                    'ayuda'    => 'Un jugador figura como titular y además tiene un "Entra" en los cambios.',
                    'jugador'  => true,
                    'acciones' => ['alineaciones', 'cambios'],
                    'metodo'   => 'cambiosTitularesQueEntran',
                ],
            ],

            'Árbitros' => [
                'arbitros.terna' => [
                    'titulo'   => 'Terna incompleta',
                    'ayuda'    => 'Falta el principal, el línea 1 o el línea 2. La columna "Falta" dice cuál.',
                    'jugador'  => false,
                    'detalle'  => 'terna',
                    'acciones' => ['jueces', 'incidencia'],
                    'metodo'   => 'arbitrosTerna',
                    'roles'    => true, // habilita el filtro por rol faltante
                ],
                'arbitros.repetidos' => [
                    'titulo'   => 'Rol repetido',
                    'ayuda'    => 'El mismo rol cargado dos veces en el partido (dos principales, dos línea 1, etc.).',
                    'jugador'  => false,
                    'detalle'  => 'roles',
                    'acciones' => ['jueces'],
                    'metodo'   => 'arbitrosRepetidos',
                ],
            ],

            'Técnicos' => [
                'tecnicos.faltan' => [
                    'titulo'   => 'Sin técnico',
                    'ayuda'    => 'Falta el DT de alguno de los dos equipos en un partido que sí tiene alineación.',
                    'jugador'  => false,
                    'detalle'  => 'tecnicos',
                    'acciones' => ['alineaciones', 'incidencia'],
                    'metodo'   => 'tecnicosFaltan',
                ],
            ],

            'Penales' => [
                'penales.faltantes' => [
                    'titulo'    => 'Penales sin cargar',
                    'ayuda'     => 'Goles de penal que todavía no tienen su registro de penal convertido con el arquero que lo recibió.',
                    'jugador'   => true,
                    'columna_jugador' => 'Ejecutor',
                    'detalle'   => 'penal_faltante',
                    'acciones'  => ['penales', 'goles'],
                    'metodo'    => 'penalesFaltantes',
                    'aplicar'   => true,
                ],
                'penales.mal_cargados' => [
                    'titulo'    => 'Arquero equivocado',
                    'ayuda'     => 'Penales convertidos donde el arquero cargado no es el que estaba en cancha en ese minuto.',
                    'jugador'   => false,
                    'detalle'   => 'penal_mal',
                    'acciones'  => ['penales'],
                    'metodo'    => 'penalesMalCargados',
                ],
            ],
        ];
    }

    /** Todos los chequeos en un solo nivel, clave => definición (con 'grupo'). */
    public function catalogo(): array
    {
        $plano = [];
        foreach ($this->definiciones() as $grupo => $chequeos) {
            foreach ($chequeos as $clave => $def) {
                $def['grupo'] = $grupo;
                $def['clave'] = $clave;
                $plano[$clave] = $def;
            }
        }

        return $plano;
    }

    public function definicion(string $clave): ?array
    {
        return $this->catalogo()[$clave] ?? null;
    }

    /** El chequeo que se abre si la URL no pide ninguno. */
    public function primeraClave(): string
    {
        return array_key_first($this->catalogo());
    }

    /**
     * Devuelve el query builder del chequeo, ya ordenado y listo para paginar.
     * Los chequeos de penales no pasan por acá: los resuelve ControlPenales.
     */
    public function consulta(string $clave, array $filtros = [])
    {
        $def = $this->definicion($clave);

        if (!$def || !method_exists($this, $def['metodo'])) {
            return null;
        }

        return $this->{$def['metodo']}($filtros);
    }

    /**
     * Total de un chequeo. Es lo caro de la pantalla, así que va cacheado y se
     * pide por AJAX: la tabla se ve enseguida y los números van llegando.
     */
    public function contar(string $clave, array $filtros = []): int
    {
        $version = (int) Cache::get(self::CLAVE_VERSION, 1);
        $llave   = 'controles.'.$version.'.'.$clave.'.'.md5(json_encode($filtros));

        return (int) Cache::remember($llave, self::TTL_CONTEO, function () use ($clave, $filtros) {
            if ($clave === 'penales.faltantes') {
                return app(ControlPenales::class)->contarFaltantes($filtros);
            }
            if ($clave === 'penales.mal_cargados') {
                return app(ControlPenales::class)->malCargados($filtros)->count();
            }

            $consulta = $this->consulta($clave, $filtros);
            if (!$consulta) {
                return 0;
            }

            // Contar con el ORDER BY puesto obliga a MySQL a ordenar de gusto.
            $consulta->orders = null;

            return $consulta->count();
        });
    }

    /** Tira a la basura todos los totales cacheados. */
    public function invalidarConteos(): void
    {
        Cache::forever(self::CLAVE_VERSION, ((int) Cache::get(self::CLAVE_VERSION, 1)) + 1);
    }

    /** Años disponibles para el filtro. */
    public function anios(): array
    {
        return DB::table('torneos')->select('year')->distinct()->orderByDesc('year')->pluck('year')->all();
    }

    /** Torneos para el filtro, opcionalmente los de un año. */
    public function torneos($year = null): array
    {
        return DB::table('torneos')
            ->when($year, function ($q) use ($year) {
                return $q->where('year', $year);
            })
            ->orderByDesc('year')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'year'])
            ->mapWithKeys(function ($t) {
                return [$t->id => $t->nombre.' '.$t->year];
            })
            ->all();
    }

    /**
     * Normaliza los filtros de la pantalla.
     *
     * Va por `input()` y no por `query()` a proposito: los mismos filtros
     * viajan por querystring cuando se navega y por POST cuando se aprieta
     * "Crear los penales faltantes" o "Recalcular totales".
     */
    public function filtrosDesde($request): array
    {
        return [
            'year'   => $request->input('year') ?: null,
            'torneo' => $request->input('torneo') ?: null,
            'q'      => trim((string) $request->input('q')) ?: null,
            'rol'    => in_array($request->input('rol'), array_keys(self::ROLES_TERNA), true)
                ? $request->input('rol')
                : null,
        ];
    }

    /** Cuántos árbitros hay cargados de cada rol; sirve para leer la terna. */
    public function resumenRoles(): array
    {
        return DB::table('partido_arbitros')
            ->select('tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->pluck('total', 'tipo')
            ->all();
    }

    // ------------------------------------------------------------------
    // Armado de la consulta base
    // ------------------------------------------------------------------

    /**
     * El FROM + los cinco JOIN + los filtros de la barra superior.
     *
     * $origen permite arrancar desde otra tabla (gols, tarjetas, cambios) o
     * desde una derivada, en vez de arrancar desde partidos:
     *   ['tabla' => 'gols', 'partido' => 'gols.partido_id']
     *   ['raw' => 'SELECT ...', 'alias' => 't1', 'partido' => 't1.partido_id']
     */
    public function base(array $filtros, array $origen = null)
    {
        if ($origen === null) {
            $q = DB::table('partidos');
        } elseif (isset($origen['raw'])) {
            $alias = $origen['alias'];
            $q = DB::table(DB::raw('('.$origen['raw'].') as '.$alias))
                ->join('partidos', $origen['partido'], '=', 'partidos.id');
        } else {
            $q = DB::table($origen['tabla'])
                ->join('partidos', $origen['partido'], '=', 'partidos.id');
        }

        $q->join('equipos as el', 'partidos.equipol_id', '=', 'el.id')
            ->join('equipos as ev', 'partidos.equipov_id', '=', 'ev.id')
            ->join('fechas as fecha', 'partidos.fecha_id', '=', 'fecha.id')
            ->join('grupos as grupo', 'fecha.grupo_id', '=', 'grupo.id')
            ->join('torneos as torneo', 'grupo.torneo_id', '=', 'torneo.id')
            ->whereNotNull('partidos.golesl')
            ->whereNotNull('partidos.golesv');

        if (!empty($filtros['year'])) {
            $q->where('torneo.year', $filtros['year']);
        }

        if (!empty($filtros['torneo'])) {
            $q->where('torneo.id', $filtros['torneo']);
        }

        if (!empty($filtros['q'])) {
            $texto = '%'.$filtros['q'].'%';
            $q->where(function ($w) use ($texto) {
                $w->where('el.nombre', 'like', $texto)
                    ->orWhere('ev.nombre', 'like', $texto)
                    ->orWhere('torneo.nombre', 'like', $texto);
            });
        }

        return $q;
    }

    /** Las columnas que muestra la tabla, iguales para todos los chequeos. */
    public function columnas(): array
    {
        return [
            'partidos.id',
            'partidos.dia',
            'partidos.golesl',
            'partidos.golesv',
            'partidos.penalesl',
            'partidos.penalesv',
            'partidos.equipol_id',
            'partidos.equipov_id',
            'fecha.id as fecha_id',
            'fecha.numero as fecha',
            'torneo.id as torneo_id',
            'torneo.nombre as torneo',
            'torneo.year as year',
            'el.nombre as equipo_local_nombre',
            'ev.nombre as equipo_visitante_nombre',
            'el.escudo as equipo_local_escudo',
            'ev.escudo as equipo_visitante_escudo',
        ];
    }

    /** Agrega el jugador de la fila a partir de la tabla que la origina. */
    public function conJugador($q, string $columnaJugador)
    {
        return $q->join('jugadors', $columnaJugador, '=', 'jugadors.id')
            ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
            ->addSelect([
                'personas.nombre as jugador_nombre',
                'personas.apellido as jugador_apellido',
                'personas.foto as jugador_foto',
            ]);
    }

    /** Orden común: lo más nuevo primero. */
    public function ordenar($q)
    {
        return $q->orderByDesc('torneo.year')
            ->orderBy('torneo.nombre')
            ->orderBy('fecha.numero')
            ->orderBy('partidos.dia');
    }

    /**
     * Una incidencia es la forma de marcar "este partido es una excepción".
     * Un partido con incidencia no tiene que aparecer más en los controles.
     */
    private function sinIncidencia($q)
    {
        return $q->whereNotExists(function ($s) {
            $s->select(DB::raw(1))
                ->from('incidencias')
                ->whereColumn('incidencias.partido_id', 'partidos.id');
        });
    }

    /**
     * "El partido tiene el detalle cargado": hay alineación de alguno de los
     * dos equipos. Sin esto, los controles marcarían como error los miles de
     * partidos viejos que solo tienen resultado.
     */
    private function conAlineacion($q)
    {
        return $q->whereExists(function ($s) {
            $s->select(DB::raw(1))
                ->from('alineacions')
                ->whereColumn('alineacions.partido_id', 'partidos.id')
                ->whereRaw('(alineacions.equipo_id = partidos.equipol_id OR alineacions.equipo_id = partidos.equipov_id)');
        });
    }

    // ------------------------------------------------------------------
    // Alineaciones
    // ------------------------------------------------------------------

    private function alineacionesFaltan(array $filtros)
    {
        $q = $this->base($filtros)
            ->select($this->columnas())
            ->addSelect([
                DB::raw('(SELECT COUNT(*) FROM alineacions a WHERE a.partido_id = partidos.id AND a.equipo_id = partidos.equipol_id AND a.tipo = \'Titular\') as titulares_local'),
                DB::raw('(SELECT COUNT(*) FROM alineacions a WHERE a.partido_id = partidos.id AND a.equipo_id = partidos.equipov_id AND a.tipo = \'Titular\') as titulares_visitante'),
            ])
            ->whereIn('partidos.id', function ($s) {
                $s->select('partido_id')
                    ->from('alineacions')
                    ->where('tipo', 'Titular')
                    ->groupBy('partido_id', 'equipo_id')
                    ->havingRaw('COUNT(*) != 11');
            });

        return $this->ordenar($this->sinIncidencia($q));
    }

    private function alineacionesSinJugadores(array $filtros)
    {
        $q = $this->base($filtros)
            ->select($this->columnas())
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'partidos.id')
                    ->where('alineacions.tipo', 'Titular');
            });

        return $this->ordenar($this->sinIncidencia($q));
    }

    // ------------------------------------------------------------------
    // Goles
    // ------------------------------------------------------------------

    /**
     * Goleadores que no figuran en la cancha.
     *
     * La versión anterior cruzaba dos subconsultas independientes (partidos con
     * algún gol huérfano X jugadores con algún gol huérfano), así que mostraba
     * combinaciones que no existían: un jugador que estaba bien cargado en ese
     * partido aparecía igual si tenía un problema en OTRO partido. Acá la fila
     * es directamente el gol que no cierra.
     */
    private function golesSinJugar(array $filtros)
    {
        $q = $this->base($filtros, ['tabla' => 'gols', 'partido' => 'gols.partido_id'])
            ->select($this->columnas())
            ->addSelect(['gols.minuto as minuto'])
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))
                    ->from('alineacions as a')
                    ->leftJoin('cambios as c', function ($j) {
                        $j->on('a.partido_id', '=', 'c.partido_id')
                            ->on('a.jugador_id', '=', 'c.jugador_id')
                            ->where('c.tipo', '=', 'Entra');
                    })
                    ->whereColumn('a.partido_id', 'gols.partido_id')
                    ->whereColumn('a.jugador_id', 'gols.jugador_id')
                    ->where(function ($w) {
                        $w->where('a.tipo', 'Titular')->orWhereNotNull('c.id');
                    });
            });

        return $this->ordenar($this->conJugador($this->sinIncidencia($q), 'gols.jugador_id'));
    }

    private function golesRepetidos(array $filtros)
    {
        $derivada = 'SELECT partido_id, jugador_id, minuto, COUNT(*) AS cantidad
                     FROM gols
                     GROUP BY partido_id, jugador_id, minuto
                     HAVING COUNT(*) > 1';

        $q = $this->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select($this->columnas())
            ->addSelect(['t1.cantidad as cantidad', 't1.minuto as minuto']);

        return $this->ordenar($this->conJugador($q, 't1.jugador_id'));
    }

    /**
     * El resultado del partido no coincide con los goles cargados.
     *
     * La consulta vieja comparaba contra un `COUNT(...) GROUP BY partido_id`:
     * cuando un partido no tenía NINGÚN gol cargado la subconsulta devolvía
     * NULL, la comparación daba NULL y el partido no aparecía. O sea que el
     * caso más grave (2-0 sin ningún gol) era justo el que se escapaba.
     * Acá el COUNT sin GROUP BY devuelve 0 y el partido cae en la lista, pero
     * se exige alineación cargada para no arrastrar los partidos viejos que
     * solo tienen resultado.
     */
    private function golesDiferencia(array $filtros)
    {
        $q = $this->base($filtros)
            ->select($this->columnas())
            ->addSelect([
                DB::raw('(SELECT COUNT(*) FROM gols WHERE gols.partido_id = partidos.id) as goles_cargados'),
            ])
            ->whereRaw('(partidos.golesl + partidos.golesv) != (SELECT COUNT(*) FROM gols WHERE gols.partido_id = partidos.id)');

        return $this->ordenar($this->conAlineacion($this->sinIncidencia($q)));
    }

    // ------------------------------------------------------------------
    // Tarjetas
    // ------------------------------------------------------------------

    private function tarjetasSinJugar(array $filtros)
    {
        $q = $this->base($filtros, ['tabla' => 'tarjetas', 'partido' => 'tarjetas.partido_id'])
            ->select($this->columnas())
            ->addSelect(['tarjetas.minuto as minuto', 'tarjetas.tipo as tipo'])
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'tarjetas.partido_id')
                    ->whereColumn('alineacions.jugador_id', 'tarjetas.jugador_id');
            });

        return $this->ordenar($this->conJugador($this->sinIncidencia($q), 'tarjetas.jugador_id'));
    }

    private function tarjetasRepetidas(array $filtros)
    {
        $derivada = 'SELECT partido_id, jugador_id, tipo, COUNT(*) AS cantidad
                     FROM tarjetas
                     GROUP BY partido_id, jugador_id, tipo
                     HAVING COUNT(*) > 1';

        $q = $this->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select($this->columnas())
            ->addSelect(['t1.cantidad as cantidad', 't1.tipo as tipo']);

        return $this->ordenar($this->conJugador($q, 't1.jugador_id'));
    }

    // ------------------------------------------------------------------
    // Cambios
    // ------------------------------------------------------------------

    private function cambiosSinJugar(array $filtros)
    {
        $q = $this->base($filtros, ['tabla' => 'cambios', 'partido' => 'cambios.partido_id'])
            ->select($this->columnas())
            ->addSelect(['cambios.minuto as minuto', 'cambios.tipo as tipo'])
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))
                    ->from('alineacions')
                    ->whereColumn('alineacions.partido_id', 'cambios.partido_id')
                    ->whereColumn('alineacions.jugador_id', 'cambios.jugador_id');
            });

        return $this->ordenar($this->conJugador($this->sinIncidencia($q), 'cambios.jugador_id'));
    }

    private function cambiosRepetidos(array $filtros)
    {
        $derivada = 'SELECT partido_id, jugador_id, tipo, COUNT(*) AS cantidad
                     FROM cambios
                     GROUP BY partido_id, jugador_id, tipo
                     HAVING COUNT(*) > 1';

        $q = $this->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select($this->columnas())
            ->addSelect(['t1.cantidad as cantidad', 't1.tipo as tipo']);

        return $this->ordenar($this->conJugador($q, 't1.jugador_id'));
    }

    /**
     * Minutos donde entran y salen distinta cantidad de jugadores.
     * Se agrupa por partido (antes el mismo partido aparecía una vez por cada
     * minuto descalzado) y se listan los minutos en una columna.
     */
    private function cambiosImpares(array $filtros)
    {
        $derivada = "SELECT partido_id, GROUP_CONCAT(minuto ORDER BY minuto SEPARATOR ', ') AS minutos
                     FROM (
                         SELECT partido_id, minuto
                         FROM cambios
                         GROUP BY partido_id, minuto
                         HAVING SUM(CASE WHEN tipo = 'Entra' THEN 1 ELSE 0 END)
                              <> SUM(CASE WHEN tipo = 'Sale'  THEN 1 ELSE 0 END)
                     ) AS x
                     GROUP BY partido_id";

        $q = $this->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select($this->columnas())
            ->addSelect(['t1.minutos as minutos']);

        return $this->ordenar($this->sinIncidencia($q));
    }

    private function cambiosTitularesQueEntran(array $filtros)
    {
        $q = $this->base($filtros, ['tabla' => 'alineacions', 'partido' => 'alineacions.partido_id'])
            ->join('cambios', function ($j) {
                $j->on('alineacions.partido_id', '=', 'cambios.partido_id')
                    ->on('alineacions.jugador_id', '=', 'cambios.jugador_id')
                    ->where('cambios.tipo', '=', 'Entra');
            })
            ->select($this->columnas())
            ->addSelect(['cambios.minuto as minuto'])
            ->where('alineacions.tipo', 'Titular');

        return $this->ordenar($this->conJugador($this->sinIncidencia($q), 'alineacions.jugador_id'));
    }

    // ------------------------------------------------------------------
    // Árbitros
    // ------------------------------------------------------------------

    /**
     * Terna incompleta.
     *
     * Reemplaza a los dos chequeos viejos ("sin árbitro" y "distinto de 3").
     * El de "distinto de 3" solo contaba filas: un partido con principal y dos
     * cuartos árbitros daba 3 y pasaba, y un partido con principal, dos líneas
     * y un VAR daba 4 y aparecía como error. Lo que importa es qué roles hay,
     * no cuántas filas: tiene que existir Principal, Linea 1 y Linea 2.
     */
    private function arbitrosTerna(array $filtros)
    {
        $existe = function (string $rol) {
            return "EXISTS (SELECT 1 FROM partido_arbitros pa
                            WHERE pa.partido_id = partidos.id AND pa.tipo = '".$rol."')";
        };

        $q = $this->base($filtros)
            ->select($this->columnas())
            ->addSelect([
                DB::raw($existe('Principal').' as tiene_principal'),
                DB::raw($existe('Linea 1').' as tiene_linea1'),
                DB::raw($existe('Linea 2').' as tiene_linea2'),
            ]);

        if (!empty($filtros['rol'])) {
            // Filtrado por un rol puntual: solo los partidos a los que les falta ESE.
            $q->whereRaw('NOT '.$existe($filtros['rol']));
        } else {
            $q->where(function ($w) use ($existe) {
                foreach (array_keys(self::ROLES_TERNA) as $rol) {
                    $w->orWhereRaw('NOT '.$existe($rol));
                }
            });
        }

        return $this->ordenar($this->conAlineacion($this->sinIncidencia($q)));
    }

    private function arbitrosRepetidos(array $filtros)
    {
        $derivada = "SELECT partido_id, GROUP_CONCAT(CONCAT(tipo, ' x', c) ORDER BY tipo SEPARATOR ', ') AS roles
                     FROM (
                         SELECT partido_id, tipo, COUNT(*) AS c
                         FROM partido_arbitros
                         GROUP BY partido_id, tipo
                         HAVING COUNT(*) > 1
                     ) AS x
                     GROUP BY partido_id";

        $q = $this->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select($this->columnas())
            ->addSelect(['t1.roles as roles']);

        return $this->ordenar($this->sinIncidencia($q));
    }

    // ------------------------------------------------------------------
    // Técnicos
    // ------------------------------------------------------------------

    private function tecnicosFaltan(array $filtros)
    {
        $existe = function (string $columnaEquipo) {
            return "EXISTS (SELECT 1 FROM partido_tecnicos pt
                            WHERE pt.partido_id = partidos.id AND pt.equipo_id = partidos.".$columnaEquipo.")";
        };

        $q = $this->base($filtros)
            ->select($this->columnas())
            ->addSelect([
                DB::raw($existe('equipol_id').' as tiene_dt_local'),
                DB::raw($existe('equipov_id').' as tiene_dt_visitante'),
            ])
            ->whereRaw('(NOT '.$existe('equipol_id').' OR NOT '.$existe('equipov_id').')');

        return $this->ordenar($this->conAlineacion($this->sinIncidencia($q)));
    }

    // ------------------------------------------------------------------
    // Penales (los arma ControlPenales, que necesita resolver el arquero)
    // ------------------------------------------------------------------

    private function penalesFaltantes(array $filtros)
    {
        return app(ControlPenales::class)->consultaFaltantes($filtros);
    }

    private function penalesMalCargados(array $filtros)
    {
        return null; // no es SQL: se resuelve en memoria, ver ControlPenales
    }
}
