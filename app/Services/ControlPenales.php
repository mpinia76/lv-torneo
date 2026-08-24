<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Control de penales convertidos.
 *
 * Cada gol de penal debería tener su fila en `penals` con tipo "Convirtieron"
 * y el arquero que lo recibió. Esta clase encuentra los que faltan y los que
 * quedaron con el arquero equivocado.
 *
 * Dos cosas que cambiaron respecto de la versión anterior:
 *
 * 1. ANTES LA PANTALLA ESCRIBÍA. `controlarPenales()` era un GET que creaba
 *    los penales faltantes apenas se abría la URL, sin confirmación y sin
 *    forma de ver antes qué iba a crear. Ahora la pantalla solo muestra, y la
 *    creación es un POST aparte (`aplicar()`).
 *
 * 2. NO MÁS N+1. Antes, por cada gol de penal se hacían entre cuatro y seis
 *    consultas sueltas (alineación del goleador, equipo rival, arquero
 *    titular, cambios de arquero, rojas). Ahora se resuelve de a página: se
 *    traen las alineaciones, los cambios de arqueros y las rojas de todos los
 *    partidos de la página en tres consultas, y el arquero se calcula en
 *    memoria con `arqueroEnCancha()`.
 */
class ControlPenales
{
    /** Tope de penales que crea una sola pasada de `aplicar()`. */
    public const LIMITE_APLICAR = 3000;

    /** Cuántos partidos se resuelven por tanda al buscar arqueros equivocados. */
    private const TANDA = 300;

    private const TTL = 900;

    /** @var Controles */
    private $controles;

    public function __construct(Controles $controles)
    {
        $this->controles = $controles;
    }

    // ------------------------------------------------------------------
    // Penales que faltan cargar
    // ------------------------------------------------------------------

    /**
     * Goles de penal sin su penal convertido.
     *
     * El match con `penals` va por partido + minuto usando `<=>` (igualdad
     * segura para NULL): con `=` un gol sin minuto nunca encontraba su penal
     * y volvía a aparecer como faltante para siempre.
     */
    public function consultaFaltantes(array $filtros)
    {
        $q = $this->controles
            ->base($filtros, ['tabla' => 'gols', 'partido' => 'gols.partido_id'])
            ->select($this->controles->columnas())
            ->addSelect([
                'gols.id as gol_id',
                'gols.minuto as minuto',
                'gols.jugador_id as ejecutor_id',
            ])
            ->where('gols.tipo', 'Penal')
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))
                    ->from('penals')
                    ->whereColumn('penals.partido_id', 'gols.partido_id')
                    ->whereRaw('penals.minuto <=> gols.minuto')
                    ->where('penals.tipo', 'Convirtieron');
            });

        return $this->controles->ordenar(
            $this->controles->conJugador($q, 'gols.jugador_id')
        );
    }

    public function contarFaltantes(array $filtros): int
    {
        $q = $this->consultaFaltantes($filtros);
        $q->orders = null;

        return $q->count();
    }

    // ------------------------------------------------------------------
    // Penales cargados con el arquero equivocado
    // ------------------------------------------------------------------

    /**
     * Recorre todos los penales convertidos y devuelve los que tienen un
     * arquero distinto del que estaba en cancha en ese minuto.
     *
     * Esto no se puede filtrar por SQL (hay que reconstruir quién atajaba),
     * así que se recorre entero y el resultado queda cacheado. El botón
     * "Recalcular" del panel lo tira abajo.
     */
    public function malCargados(array $filtros)
    {
        $version = (int) Cache::get('controles.version', 1);
        $llave   = 'controles.'.$version.'.penales.mal.'.md5(json_encode($filtros));

        $filas = Cache::remember($llave, self::TTL, function () use ($filtros) {
            return $this->buscarMalCargados($filtros)->all();
        });

        return collect($filas);
    }

    private function buscarMalCargados(array $filtros)
    {
        $q = $this->controles
            ->base($filtros, ['tabla' => 'penals', 'partido' => 'penals.partido_id'])
            ->select($this->controles->columnas())
            ->addSelect([
                'penals.id as penal_id',
                'penals.minuto as minuto',
                'penals.jugador_id as arquero_cargado_id',
            ])
            ->where('penals.tipo', 'Convirtieron');

        $filas = $this->controles->ordenar($q)->get();
        $malas = collect();

        foreach ($filas->chunk(self::TANDA) as $tanda) {
            $resueltas = $this->resolver($tanda);

            foreach ($resueltas as $fila) {
                // El arquero cargado tiene que ser el que estaba en cancha.
                // Si no se pudo determinar ninguno, también es para revisar.
                if ($fila->arquero_id !== null && (int) $fila->arquero_id === (int) $fila->arquero_cargado_id) {
                    continue;
                }

                $malas->push($fila);
            }
        }

        return $malas;
    }

    // ------------------------------------------------------------------
    // Resolución del arquero
    // ------------------------------------------------------------------

    /**
     * Le agrega a cada fila quién era el arquero en ese minuto.
     *
     * Trabaja sobre un conjunto de filas (una página, una tanda) y hace tres
     * consultas para todas juntas, no una por fila.
     *
     * Deja en cada fila:
     *   arquero_id, arquero_nombre  - el que estaba en cancha (puede ser null)
     *   arquero_cargado_nombre      - solo en los penales ya cargados
     *   ejecutor_nombre             - solo en los goles de penal
     *   motivo                      - por qué no se pudo determinar el arquero
     */
    public function resolver($filas)
    {
        $filas = collect($filas);

        if ($filas->isEmpty()) {
            return $filas;
        }

        $partidos = $filas->pluck('id')->unique()->values()->all();
        $ctx      = $this->contexto($partidos);

        foreach ($filas as $fila) {
            $fila->arquero_id = null;
            $fila->motivo     = null;

            $minuto = $fila->minuto;

            if ($minuto === null || $minuto === '') {
                $fila->motivo = 'El gol no tiene minuto: no se puede saber quién atajaba.';
                continue;
            }

            // De qué equipo es el que pateó (o, en los ya cargados, el arquero).
            $referencia = isset($fila->ejecutor_id) ? $fila->ejecutor_id : $fila->arquero_cargado_id;
            $equipoRef  = $ctx['equipoDe'][$fila->id][$referencia] ?? null;

            if ($equipoRef === null) {
                $fila->motivo = 'El jugador no figura en la alineación de ese partido.';
                continue;
            }

            if (isset($fila->ejecutor_id)) {
                // El arquero es el del equipo rival al que convirtió.
                $equipo = ((int) $equipoRef === (int) $fila->equipol_id)
                    ? $fila->equipov_id
                    : $fila->equipol_id;
            } else {
                // En un penal ya cargado, el arquero es del equipo del arquero.
                $equipo = $equipoRef;
            }

            $fila->equipo_arquero_id = $equipo;
            $fila->arquero_id = $this->arqueroEnCancha($fila->id, $equipo, (int) $minuto, $ctx);

            if ($fila->arquero_id === null && $fila->motivo === null) {
                $fila->motivo = 'No se pudo determinar el arquero (sin arquero titular, expulsado o cambios incompletos).';
            }
        }

        return $this->ponerNombres($filas);
    }

    /**
     * Quién atajaba en ese minuto.
     *
     * Misma lógica que el `obtenerArqueroEnCancha()` original, pero leyendo de
     * los arreglos ya cargados: arquero titular por orden, después los cambios
     * de arqueros de ese equipo hasta el minuto, y al final la expulsión.
     */
    public function arqueroEnCancha($partidoId, $equipoId, int $minuto, array $ctx)
    {
        $actual = null;

        foreach ($ctx['alineaciones'][$partidoId] ?? [] as $a) {
            if ((int) $a->equipo_id === (int) $equipoId
                && $a->tipo === 'Titular'
                && $a->tipoJugador === 'Arquero') {
                $actual = (int) $a->jugador_id; // vienen ordenadas por `orden`
                break;
            }
        }

        if ($actual === null) {
            return null;
        }

        foreach ($ctx['cambios'][$partidoId] ?? [] as $c) {
            if ($c->minuto === null || (int) $c->minuto > $minuto) {
                continue;
            }

            // El cambio tiene que ser de un jugador de este equipo.
            $equipoJugador = $ctx['equipoDe'][$partidoId][$c->jugador_id] ?? null;
            if ($equipoJugador === null || (int) $equipoJugador !== (int) $equipoId) {
                continue;
            }

            if ($c->tipo === 'Entra') {
                $actual = (int) $c->jugador_id;
            } elseif ($c->tipo === 'Sale' && $actual === (int) $c->jugador_id) {
                $actual = null;
            }
        }

        if ($actual === null) {
            return null;
        }

        foreach ($ctx['rojas'][$partidoId] ?? [] as $r) {
            if ((int) $r->jugador_id === $actual && $r->minuto !== null && (int) $r->minuto <= $minuto) {
                return null;
            }
        }

        return $actual;
    }

    /**
     * Las tres consultas que alimentan `arqueroEnCancha()` para un conjunto
     * de partidos: alineaciones, cambios de arqueros y rojas.
     */
    public function contexto(array $partidoIds): array
    {
        $ctx = ['alineaciones' => [], 'equipoDe' => [], 'cambios' => [], 'rojas' => []];

        if (empty($partidoIds)) {
            return $ctx;
        }

        $alineaciones = DB::table('alineacions')
            ->join('jugadors', 'alineacions.jugador_id', '=', 'jugadors.id')
            ->whereIn('alineacions.partido_id', $partidoIds)
            ->orderBy('alineacions.orden')
            ->get([
                'alineacions.partido_id',
                'alineacions.equipo_id',
                'alineacions.jugador_id',
                'alineacions.tipo',
                'jugadors.tipoJugador',
            ]);

        foreach ($alineaciones as $a) {
            $ctx['alineaciones'][$a->partido_id][] = $a;
            $ctx['equipoDe'][$a->partido_id][$a->jugador_id] = $a->equipo_id;
        }

        $cambios = DB::table('cambios')
            ->join('jugadors', 'cambios.jugador_id', '=', 'jugadors.id')
            ->whereIn('cambios.partido_id', $partidoIds)
            ->where('jugadors.tipoJugador', 'Arquero')
            ->orderBy('cambios.minuto')
            ->orderBy('cambios.id')
            ->get(['cambios.partido_id', 'cambios.jugador_id', 'cambios.tipo', 'cambios.minuto']);

        foreach ($cambios as $c) {
            $ctx['cambios'][$c->partido_id][] = $c;
        }

        $rojas = DB::table('tarjetas')
            ->whereIn('partido_id', $partidoIds)
            ->where('tipo', 'Roja')
            ->get(['partido_id', 'jugador_id', 'minuto']);

        foreach ($rojas as $r) {
            $ctx['rojas'][$r->partido_id][] = $r;
        }

        return $ctx;
    }

    /** Resuelve los nombres de todos los jugadores mencionados, de una. */
    private function ponerNombres($filas)
    {
        $ids = collect();

        foreach ($filas as $fila) {
            foreach (['arquero_id', 'arquero_cargado_id', 'ejecutor_id'] as $campo) {
                if (!empty($fila->$campo)) {
                    $ids->push($fila->$campo);
                }
            }
        }

        $nombres = [];

        if ($ids->isNotEmpty()) {
            $nombres = DB::table('jugadors')
                ->join('personas', 'jugadors.persona_id', '=', 'personas.id')
                ->whereIn('jugadors.id', $ids->unique()->values()->all())
                ->get(['jugadors.id', 'personas.nombre', 'personas.apellido'])
                ->mapWithKeys(function ($j) {
                    return [$j->id => trim($j->apellido.', '.$j->nombre, ', ')];
                })
                ->all();
        }

        foreach ($filas as $fila) {
            $fila->arquero_nombre         = $nombres[$fila->arquero_id ?? 0] ?? null;
            $fila->arquero_cargado_nombre = $nombres[$fila->arquero_cargado_id ?? 0] ?? null;
            $fila->ejecutor_nombre        = $nombres[$fila->ejecutor_id ?? 0] ?? null;
        }

        return $filas;
    }

    // ------------------------------------------------------------------
    // Crear los penales faltantes (el POST del botón "Aplicar")
    // ------------------------------------------------------------------

    /**
     * Crea el penal convertido de cada gol de penal que se pueda resolver,
     * respetando los filtros de la pantalla.
     *
     * Devuelve ['creados' => n, 'sin_arquero' => n, 'restantes' => n].
     */
    public function aplicar(array $filtros): array
    {
        $filas = $this->consultaFaltantes($filtros)->limit(self::LIMITE_APLICAR + 1)->get();

        $restantes = 0;
        if ($filas->count() > self::LIMITE_APLICAR) {
            $filas     = $filas->take(self::LIMITE_APLICAR);
            $restantes = $this->contarFaltantes($filtros) - self::LIMITE_APLICAR;
        }

        $creados    = 0;
        $sinArquero = 0;
        $ahora      = now();

        foreach ($filas->chunk(self::TANDA) as $tanda) {
            $resueltas = $this->resolver($tanda);
            $insertar  = [];
            $vistos    = [];

            foreach ($resueltas as $fila) {
                if ($fila->arquero_id === null) {
                    $sinArquero++;
                    continue;
                }

                // Dos goles de penal en el mismo partido y minuto generan un
                // solo registro: el chequeo de duplicados va por (partido, minuto).
                $clave = $fila->id.'-'.$fila->minuto;
                if (isset($vistos[$clave])) {
                    continue;
                }
                $vistos[$clave] = true;

                $insertar[] = [
                    'partido_id' => $fila->id,
                    'jugador_id' => $fila->arquero_id,
                    'minuto'     => $fila->minuto,
                    'tipo'       => 'Convirtieron',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            if (!empty($insertar)) {
                DB::transaction(function () use ($insertar, &$creados) {
                    DB::table('penals')->insert($insertar);
                    $creados += count($insertar);
                });
            }
        }

        $this->controles->invalidarConteos();

        return [
            'creados'     => $creados,
            'sin_arquero' => $sinArquero,
            'restantes'   => max(0, $restantes),
        ];
    }
}
