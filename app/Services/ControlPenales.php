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
 *    traen las alineaciones, los cambios y las expulsiones de todos los
 *    partidos de la página en tres consultas, y el arquero se calcula en
 *    memoria con `arqueroEnCancha()`.
 *
 * 3. NO SE PUDO DETERMINAR EL ARQUERO ≠ ESTÁ MAL CARGADO. Cuando echan al
 *    arquero y no quedan cambios, ataja un jugador de campo: el penal está
 *    bien cargado aunque el que figura no sea arquero. Esos casos se dan por
 *    buenos si el jugador cargado estaba en cancha en ese minuto
 *    (`estabaEnCancha()`), y solo se muestran los que ni siquiera estaban.
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

        // Invariante del panel: un partido con incidencia no aparece en
        // NINGUN control. Los dos chequeos de penales se lo estaban salteando.
        return $this->controles->ordenar(
            $this->controles->conJugador($this->controles->sinIncidencia($q), 'gols.jugador_id')
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

        $filas = $this->controles->ordenar($this->controles->sinIncidencia($q))->get();
        $malas = collect();

        foreach ($filas->chunk(self::TANDA) as $tanda) {
            $resueltas = $this->resolver($tanda);

            foreach ($resueltas as $fila) {
                // El arquero cargado tiene que ser el que estaba en cancha.
                if ($fila->arquero_id !== null) {
                    if ((int) $fila->arquero_id === (int) $fila->arquero_cargado_id) {
                        continue;
                    }

                    $malas->push($fila);
                    continue;
                }

                // No se pudo determinar el arquero (lo echaron, no quedaban
                // cambios, la alineación no tiene arquero titular). Eso NO es
                // un error de carga: alguien tuvo que atajar igual, y muchas
                // veces es un jugador de campo. Si el que está cargado estaba
                // en cancha en ese minuto, damos el penal por bien cargado.
                if (!empty($fila->cargado_en_cancha)) {
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
     *   cargado_en_cancha           - solo en los penales ya cargados y solo
     *                                 cuando no se pudo determinar el arquero:
     *                                 si el jugador cargado estaba en cancha en
     *                                 ese minuto. Es lo que separa "atajó un
     *                                 jugador de campo" (está bien) de "está
     *                                 cargado cualquiera" (hay que corregirlo).
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
            $fila->arquero_id        = null;
            $fila->motivo            = null;
            $fila->cargado_en_cancha = false;
            $fila->traza             = [];

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

            $pasos = [];
            $fila->arquero_id = $this->arqueroEnCancha($fila->id, $equipo, (int) $minuto, $ctx, $pasos);
            $fila->traza = $pasos;

            if ($fila->arquero_id !== null) {
                continue;
            }

            // Sin arquero determinable. En un penal ya cargado, todavía se
            // puede decir algo útil: si el jugador cargado estaba en cancha en
            // ese minuto, es el caso de siempre —echaron al arquero, no
            // quedaban cambios y se puso los guantes un jugador de campo— y no
            // hay nada que corregir.
            if (!isset($fila->ejecutor_id) && isset($fila->arquero_cargado_id)) {
                $fila->cargado_en_cancha = $this->estabaEnCancha(
                    $fila->id, $fila->arquero_cargado_id, $equipo, (int) $minuto, $ctx
                );

                if ($fila->cargado_en_cancha) {
                    $fila->motivo = 'No hay arquero determinable en ese minuto (expulsado o sin cambios), '
                        .'pero el jugador cargado estaba en cancha: atajó él.';
                    continue;
                }

                $fila->motivo = 'El jugador cargado no estaba en cancha en ese minuto.';
                continue;
            }

            if ($fila->motivo === null) {
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
     *
     * `$pasos` es opcional y sirve para explicar el resultado. Cuando se pasa
     * un arreglo, la función va anotando qué miró y qué descartó — sobre todo
     * los datos que NO puede usar (una roja sin minuto, un cambio sin minuto),
     * que son la causa habitual de que el control marque un penal que en
     * realidad está bien cargado. Sin eso hay que adivinar leyendo la base.
     */
    public function arqueroEnCancha($partidoId, $equipoId, int $minuto, array $ctx, &$pasos = null)
    {
        $anotar = function ($tipo, $jugador = null, $min = null) use (&$pasos) {
            if ($pasos === null) return;
            $pasos[] = ['t' => $tipo, 'j' => $jugador, 'm' => $min];
        };

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
            $anotar('sin_titular');
            return null;
        }
        $anotar('titular', $actual);

        foreach ($ctx['cambios'][$partidoId] ?? [] as $c) {
            $equipoJugador = $ctx['equipoDe'][$partidoId][$c->jugador_id] ?? null;
            $delEquipo = ($equipoJugador !== null && (int) $equipoJugador === (int) $equipoId);

            if ($c->minuto === null) {
                if ($delEquipo) $anotar('cambio_sin_minuto', (int) $c->jugador_id);
                continue;
            }
            if ((int) $c->minuto > $minuto) {
                continue;
            }

            // El cambio tiene que ser de un jugador de este equipo.
            if (!$delEquipo) {
                continue;
            }

            if ($c->tipo === 'Entra') {
                $actual = (int) $c->jugador_id;
                $anotar('entra', $actual, (int) $c->minuto);
            } elseif ($c->tipo === 'Sale' && $actual === (int) $c->jugador_id) {
                $anotar('sale', $actual, (int) $c->minuto);
                $actual = null;
            }
        }

        if ($actual === null) {
            $anotar('nadie');
            return null;
        }

        foreach ($ctx['rojas'][$partidoId] ?? [] as $r) {
            if ((int) $r->jugador_id !== $actual) {
                continue;
            }
            if ($r->minuto === null) {
                // Es EL caso a mirar: la expulsión existe pero sin minuto no se
                // puede aplicar, y el arquero sigue figurando en cancha.
                $anotar('roja_sin_minuto', $actual);
                continue;
            }
            if ((int) $r->minuto <= $minuto) {
                $anotar('roja', $actual, (int) $r->minuto);
                return null;
            }
            $anotar('roja_tarde', $actual, (int) $r->minuto);
        }

        $anotar('queda', $actual);
        return $actual;
    }

    /**
     * ¿Este jugador estaba en cancha en ese minuto, en ese equipo?
     *
     * Es la versión suave de `arqueroEnCancha()`: no pregunta si es el arquero,
     * solo si estaba adentro. Sirve para el caso en que no hay arquero
     * determinable y atajó un jugador de campo.
     *
     * Titular hasta que lo cambien, suplente desde que entra, y afuera si vio
     * la roja (o la doble amarilla) antes del minuto.
     */
    public function estabaEnCancha($partidoId, $jugadorId, $equipoId, int $minuto, array $ctx): bool
    {
        $jugadorId = (int) $jugadorId;
        $adentro   = null;

        foreach ($ctx['alineaciones'][$partidoId] ?? [] as $a) {
            if ((int) $a->jugador_id !== $jugadorId || (int) $a->equipo_id !== (int) $equipoId) {
                continue;
            }

            $adentro = ($a->tipo === 'Titular');
            break;
        }

        // Ni siquiera figura en la alineación de ese equipo.
        if ($adentro === null) {
            return false;
        }

        // Acá van TODOS los cambios, no solo los de arqueros: el que nos
        // interesa puede ser un jugador de campo.
        foreach ($ctx['cambiosTodos'][$partidoId] ?? [] as $c) {
            if ((int) $c->jugador_id !== $jugadorId) {
                continue;
            }

            if ($c->minuto === null || (int) $c->minuto > $minuto) {
                continue;
            }

            if ($c->tipo === 'Entra') {
                $adentro = true;
            } elseif ($c->tipo === 'Sale') {
                $adentro = false;
            }
        }

        if (!$adentro) {
            return false;
        }

        foreach ($ctx['rojas'][$partidoId] ?? [] as $r) {
            if ((int) $r->jugador_id === $jugadorId && $r->minuto !== null && (int) $r->minuto <= $minuto) {
                return false;
            }
        }

        return true;
    }

    /**
     * Las tres consultas que alimentan `arqueroEnCancha()` para un conjunto
     * de partidos: alineaciones, cambios y expulsiones.
     *
     * Los cambios quedan en dos listas: `cambios` son solo los de arqueros (es
     * lo que mira `arqueroEnCancha()`, donde "entra" significa "este es el
     * arquero nuevo") y `cambiosTodos` son todos, para `estabaEnCancha()`. Una
     * sola consulta alimenta las dos.
     */
    public function contexto(array $partidoIds): array
    {
        $ctx = [
            'alineaciones' => [],
            'equipoDe'     => [],
            'cambios'      => [], // solo arqueros
            'cambiosTodos' => [],
            'rojas'        => [],
        ];

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
            ->orderBy('cambios.minuto')
            ->orderBy('cambios.id')
            ->get([
                'cambios.partido_id',
                'cambios.jugador_id',
                'cambios.tipo',
                'cambios.minuto',
                'jugadors.tipoJugador',
            ]);

        foreach ($cambios as $c) {
            $ctx['cambiosTodos'][$c->partido_id][] = $c;

            if ($c->tipoJugador === 'Arquero') {
                $ctx['cambios'][$c->partido_id][] = $c;
            }
        }

        // La doble amarilla también deja al equipo con uno menos: si no se
        // cuenta, el arquero expulsado por dos amarillas sigue "atajando".
        $rojas = DB::table('tarjetas')
            ->whereIn('partido_id', $partidoIds)
            ->whereIn('tipo', ['Roja', 'Doble Amarilla'])
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

            foreach ($fila->traza ?? [] as $paso) {
                if (!empty($paso['j'])) {
                    $ids->push($paso['j']);
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
            $fila->traza_txt              = $this->trazaLegible($fila->traza ?? [], $nombres);
        }

        return $filas;
    }

    /**
     * La traza de `arqueroEnCancha()` en castellano, para mostrarla en la fila.
     *
     * Cada entrada es un paso del razonamiento. Los que importan son los que
     * avisan de un dato inservible (`roja_sin_minuto`, `cambio_sin_minuto`):
     * ahi el control no se equivoca, le falta el minuto en la base.
     */
    private function trazaLegible(array $pasos, array $nombres): array
    {
        $quien = function ($id) use ($nombres) {
            if (empty($id)) return '?';
            return $nombres[$id] ?? ('#'.$id);
        };

        $txt = [];
        foreach ($pasos as $paso) {
            $j = $quien($paso['j'] ?? null);
            $m = isset($paso['m']) ? $paso['m']."'" : '';

            switch ($paso['t']) {
                case 'sin_titular':
                    $txt[] = 'la alineación no tiene arquero titular'; break;
                case 'titular':
                    $txt[] = 'titular: '.$j; break;
                case 'entra':
                    $txt[] = 'entra '.$j.' al '.$m; break;
                case 'sale':
                    $txt[] = 'sale '.$j.' al '.$m; break;
                case 'cambio_sin_minuto':
                    $txt[] = '⚠ cambio de '.$j.' SIN MINUTO: no se aplica'; break;
                case 'nadie':
                    $txt[] = 'no queda arquero en cancha'; break;
                case 'roja':
                    $txt[] = 'roja de '.$j.' al '.$m; break;
                case 'roja_sin_minuto':
                    $txt[] = '⚠ '.$j.' tiene roja SIN MINUTO: no se descuenta'; break;
                case 'roja_tarde':
                    $txt[] = 'roja de '.$j.' al '.$m.', después del penal'; break;
                case 'queda':
                    $txt[] = 'queda '.$j; break;
            }
        }
        return $txt;
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
    /**
     * Los penales que le faltan a UN partido.
     *
     * Es lo que llama el importador de detalle después de rehacer: el importador
     * escribe `gols` con tipo Penal pero nunca escribió `penals`, así que cada
     * "Rehacer" dejaba el partido en el control "Penales sin cargar" esperando
     * un click a mano. Se llama después de la transacción, no adentro: el
     * arquero se calcula leyendo la alineación, los cambios y las rojas que
     * acaba de guardar.
     *
     * No borra ni pisa nada. Un penal ya cargado —incluso uno con el arquero
     * corregido a mano— se queda como está: `consultaFaltantes()` lo saltea.
     */
    public function aplicarPartido($partidoId): array
    {
        return $this->aplicar(['partido' => (int) $partidoId]);
    }

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
