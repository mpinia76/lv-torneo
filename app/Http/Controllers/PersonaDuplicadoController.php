<?php

namespace App\Http\Controllers;

use App\Persona;
use App\PersonaDuplicado;
use App\Services\DuplicadosPersonas;
use App\Services\FusionPersonas;
use App\Services\RegistrosPersonas;
use App\Services\TmFechas;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pantalla "Verificar personas".
 *
 * Antes: por cada una de las 1000 personas de la página se disparaba un SELECT
 * con LIKE '%...%' sobre un CONCAT (imposible de indexar) más un NOT EXISTS
 * correlacionado, y la vista además cargaba jugador/técnico/árbitro fila por fila.
 *
 * Ahora: los candidatos están precalculados en `persona_duplicados` y la pantalla
 * resuelve todo con un puñado de consultas indexadas, siempre acotadas a la página.
 */
class PersonaDuplicadoController extends Controller
{
    /** Cuántos pares por página. */
    private const POR_PAGINA = 50;

    /** Clave de caché de la lista de personas sin registros. */
    private const CACHE_SIN_REGISTROS = 'personas.sin_registros_ids';

    /** Clave de caché de las personas sin fecha de nacimiento (con su id de TM). */
    private const CACHE_SIN_FECHA = 'personas.sin_fecha';

    /**
     * El grupo `admin` de routes/web.php no tiene middleware: la autenticación
     * la pone cada controller en su constructor (igual que JugadorController).
     * Sin esto, fusionar() y lote() —que BORRAN personas— quedarían públicos.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $tab    = $request->query('tab', 'repetidos');
        $estado = $request->query('estado', PersonaDuplicado::PENDIENTE);
        $umbral = max(0, min(100, (int) $request->query('umbral', DuplicadosPersonas::UMBRAL)));
        $buscar = trim((string) $request->query('q', ''));

        $datos = [
            'tab'          => $tab,
            'estado'       => $estado,
            'umbral'       => $umbral,
            'buscar'       => $buscar,
            'conteos'      => $this->conteos($umbral),
            'pares'        => null,
            'personas'     => collect(),
            'peso'         => [],
            'clubes'       => [],
            'sinNombre'    => null,
            'sinBandera'   => null,
            'sinRegistros' => null,
            'sinFecha'     => null,
            'tmDe'         => [],
            'indexado'     => DB::table('personas')->whereNull('clave_orden')->count() === 0,
        ];

        if ($tab === 'sin-nombre') {
            $datos['sinNombre'] = $this->sinNombreApellido($request);
        } elseif ($tab === 'nacionalidad') {
            $datos['sinBandera'] = $this->sinBandera($request);
        } elseif ($tab === 'sin-registros') {
            $datos['sinRegistros'] = $this->sinRegistros($request);
        } elseif ($tab === 'sin-fecha') {
            list($datos['sinFecha'], $datos['tmDe']) = $this->sinFecha($request);
        } else {
            list($pares, $personas, $peso, $clubes) = $this->repetidos($request, $estado, $umbral, $buscar);
            $datos['pares']    = $pares;
            $datos['personas'] = $personas;
            $datos['peso']     = $peso;
            $datos['clubes']   = $clubes;
        }

        return view('jugadores.verificarPersona', $datos);
    }

    // ------------------------------------------------------------------
    // Pestaña 1: pares repetidos
    // ------------------------------------------------------------------

    private function repetidos(Request $request, string $estado, int $umbral, string $buscar): array
    {
        $query = DB::table('persona_duplicados as d')
            ->join('personas as p1', 'p1.id', '=', 'd.persona_id')
            ->join('personas as p2', 'p2.id', '=', 'd.simil_id')
            ->select('d.id', 'd.persona_id', 'd.simil_id', 'd.puntaje', 'd.motivo', 'd.estado')
            ->where('d.puntaje', '>=', $umbral);

        if ($estado !== 'todos') {
            $query->where('d.estado', $estado);
        }

        if ($buscar !== '') {
            $termino = '%' . DuplicadosPersonas::normalizar($buscar) . '%';
            $query->where(function ($q) use ($termino) {
                $q->where('p1.clave_norm', 'like', $termino)
                  ->orWhere('p2.clave_norm', 'like', $termino);
            });
        }

        $pares = $query
            ->orderByDesc('d.puntaje')
            ->orderBy('p1.apellido')
            ->orderBy('d.id')
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());

        // Una sola consulta para las personas de la página, con los roles cargados
        // de una (esto es lo que mataba a la vista vieja: un SELECT por fila).
        $ids = [];
        foreach ($pares->items() as $par) {
            $ids[] = (int) $par->persona_id;
            $ids[] = (int) $par->simil_id;
        }
        $ids = array_values(array_unique($ids));

        $personas = $ids
            ? Persona::with(['jugador', 'tecnico', 'arbitro'])->whereIn('id', $ids)->get()->keyBy('id')
            : collect();

        $peso = $ids ? FusionPersonas::peso($ids) : [];

        // Los clubes de las dos fichas son lo que realmente resuelve el par: si
        // comparten club y temporada es la misma persona, si no, son homónimos.
        // Sale en unas pocas consultas para toda la página, igual que el peso.
        $clubes = $ids ? RegistrosPersonas::clubes($ids) : [];

        return [$pares, $personas, $peso, $clubes];
    }

    private function conteos(int $umbral): array
    {
        $porEstado = DB::table('persona_duplicados')
            ->select('estado', DB::raw('COUNT(*) as n'))
            ->where('puntaje', '>=', $umbral)
            ->groupBy('estado')
            ->pluck('n', 'estado')
            ->all();

        return [
            'pendiente'    => (int) ($porEstado[PersonaDuplicado::PENDIENTE] ?? 0),
            'descartado'   => (int) ($porEstado[PersonaDuplicado::DESCARTADO] ?? 0),
            'sinNombre'    => (int) Persona::where(function ($q) {
                $q->whereNull('nombre')->orWhere('nombre', '')
                  ->orWhereNull('apellido')->orWhere('apellido', '');
            })->count(),
            'sinBandera'   => (int) $this->contarSinBandera(),
            'sinRegistros' => (int) $this->contarSinRegistros(),
            'sinFecha'     => count($this->pendientesSinFecha()),
            'sinFechaTm'   => $this->contarSinFechaConTm(),
            'sinFechaDet'  => TmFechas::detalle($this->pendientesSinFecha()),
            'fusiones'     => (int) DB::table('persona_fusiones')->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Pestaña 5: personas sin fecha de nacimiento
    // ------------------------------------------------------------------

    /**
     * Las personas sin fecha, con el id de Transfermarkt de cada una.
     *
     * Se cachea igual que la lista de huérfanas: el conteo lo pide TODA carga
     * de la pantalla (esté en la pestaña que esté), y son tres joins sobre
     * `personas` enteras.
     */
    private function pendientesSinFecha(): array
    {
        return Cache::remember(self::CACHE_SIN_FECHA, 600, function () {
            return TmFechas::pendientes();
        });
    }

    /** Cuántas de esas se pueden resolver solas (tienen id de TM). */
    private function contarSinFechaConTm(): int
    {
        $n = 0;
        foreach ($this->pendientesSinFecha() as $d) {
            if (!empty($d['tm'])) $n++;
        }
        return $n;
    }

    /** Se pagina sobre la lista cacheada, igual que "sin registros". */
    private function sinFecha(Request $request): array
    {
        $pendientes = $this->pendientesSinFecha();
        $ids        = array_keys($pendientes);
        $pagina     = max(1, (int) $request->query('page', 1));
        $tramo      = array_slice($ids, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);

        $personas = collect();
        if ($tramo) {
            $orden = array_flip($tramo);
            $personas = Persona::with(['jugador', 'tecnico', 'arbitro'])
                ->whereIn('id', $tramo)
                ->get()
                ->sortBy(function ($p) use ($orden) {
                    return $orden[$p->id] ?? PHP_INT_MAX;
                })
                ->values();
        }

        $tmDe = [];
        foreach ($tramo as $id) {
            $tmDe[$id] = $pendientes[$id];
        }

        $paginador = new LengthAwarePaginator(
            $personas,
            count($ids),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return [$paginador, $tmDe];
    }

    // ------------------------------------------------------------------
    // Pestaña 4: personas sin ningún registro
    // ------------------------------------------------------------------

    /**
     * Los ids de las personas sin registros, cacheados.
     *
     * La consulta recorre toda la tabla `personas` con un NOT EXISTS por tabla
     * hija. Para una persona CON registros MySQL corta en el primer NOT EXISTS,
     * pero para las huérfanas —que son justo las que se listan— tiene que
     * evaluarlos todos. Por eso se resuelve una sola vez: el conteo de la
     * pestaña y cada página salen de la misma lista cacheada, y no de repetir
     * la consulta cara en cada carga.
     */
    private function idsSinRegistros(): array
    {
        return Cache::remember(self::CACHE_SIN_REGISTROS, 600, function () {
            return RegistrosPersonas::queryHuerfanas()
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->pluck('id')
                ->all();
        });
    }

    private function contarSinRegistros(): int
    {
        return count($this->idsSinRegistros());
    }

    /** Se pagina sobre la lista cacheada y se traen solo las 50 filas visibles. */
    private function sinRegistros(Request $request)
    {
        $ids    = $this->idsSinRegistros();
        $pagina = max(1, (int) $request->query('page', 1));
        $tramo  = array_slice($ids, ($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA);

        $personas = collect();
        if ($tramo) {
            $orden = array_flip($tramo);
            $personas = Persona::with(['jugador', 'tecnico', 'arbitro'])
                ->whereIn('id', $tramo)
                ->get()
                ->sortBy(function ($p) use ($orden) {
                    return $orden[$p->id] ?? PHP_INT_MAX;
                })
                ->values();
        }

        return new LengthAwarePaginator(
            $personas,
            count($ids),
            self::POR_PAGINA,
            $pagina,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    // ------------------------------------------------------------------
    // Pestaña 2: sin nombre / apellido
    // ------------------------------------------------------------------

    private function sinNombreApellido(Request $request)
    {
        return Persona::with(['jugador', 'tecnico', 'arbitro'])
            ->where(function ($q) {
                $q->whereNull('nombre')->orWhere('nombre', '')
                  ->orWhereNull('apellido')->orWhere('apellido', '');
            })
            ->orderBy('apellido', 'ASC')
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());
    }

    // ------------------------------------------------------------------
    // Pestaña 3: nacionalidad sin bandera
    // ------------------------------------------------------------------

    /**
     * La versión vieja hacía un file_exists() por persona. Acá se resuelve una
     * sola vez por nacionalidad distinta (son unas decenas) y se cachea.
     */
    private function nacionalidadesSinBandera(): array
    {
        return Cache::remember('personas.nacionalidades_sin_bandera', 600, function () {
            $malas   = [];
            $hayNull = false;

            $filas = DB::table('personas')
                ->select('nacionalidad')
                ->groupBy('nacionalidad')
                ->get();

            foreach ($filas as $f) {
                if ($f->nacionalidad === null || trim($f->nacionalidad) === '') {
                    $hayNull = true;
                    continue;
                }
                if (!$this->tieneBandera($f->nacionalidad)) {
                    $malas[] = $f->nacionalidad;
                }
            }

            return ['valores' => $malas, 'vacias' => $hayNull];
        });
    }

    private function tieneBandera($nacionalidad): bool
    {
        $sinAcentos = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
            $nacionalidad
        );

        return file_exists(public_path('images/' . $sinAcentos . '.gif'));
    }

    private function querySinBandera()
    {
        $malas = $this->nacionalidadesSinBandera();

        return Persona::where(function ($q) use ($malas) {
            if ($malas['valores']) {
                $q->whereIn('nacionalidad', $malas['valores']);
            }
            if ($malas['vacias']) {
                $q->orWhereNull('nacionalidad')->orWhere('nacionalidad', '');
            }
            if (!$malas['valores'] && !$malas['vacias']) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    private function contarSinBandera(): int
    {
        return (int) $this->querySinBandera()->count();
    }

    private function sinBandera(Request $request)
    {
        return $this->querySinBandera()
            ->with(['jugador', 'tecnico', 'arbitro'])
            ->orderBy('nacionalidad')
            ->orderBy('apellido')
            ->paginate(self::POR_PAGINA)
            ->appends($request->query());
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    /** Recalcula el índice y los candidatos. Es lo único caro, y se hace a pedido. */
    public function recalcular(Request $request)
    {
        set_time_limit(0);

        $umbral    = max(1, min(100, (int) $request->input('umbral', DuplicadosPersonas::UMBRAL)));
        $reindexar = $request->boolean('reindexar');

        try {
            if ($reindexar) {
                DuplicadosPersonas::reconstruirIndice();
            }
            $r = DuplicadosPersonas::recalcular($umbral);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors([
                'error' => 'No se pudo recalcular: ' . $e->getMessage()
                    . ' — si el navegador corta por tiempo, corré "php artisan personas:duplicados" desde la consola.',
            ]);
        }

        Cache::forget('personas.nacionalidades_sin_bandera');

        $mensaje = "Se revisaron {$r['personas']} personas y quedaron {$r['pares']} pares candidatos"
            . " (umbral {$r['umbral']}). Se dieron de baja {$r['borrados']} que ya no califican.";

        if (!empty($r['saltados'])) {
            $mensaje .= ' Apellidos demasiado comunes que no se compararon entre sí: '
                . implode(', ', array_slice($r['saltados'], 0, 10))
                . (count($r['saltados']) > 10 ? ' y ' . (count($r['saltados']) - 10) . ' más' : '')
                . '.';
        }

        return redirect()->back()->with('success', $mensaje);
    }

    /** Marca un par como "no son la misma persona". No vuelve a aparecer. */
    public function descartar(Request $request)
    {
        $request->validate([
            'persona_id' => 'required|integer',
            'simil_id'   => 'required|integer|different:persona_id',
        ]);

        list($a, $b) = PersonaDuplicado::ordenado($request->input('persona_id'), $request->input('simil_id'));

        PersonaDuplicado::updateOrCreate(
            ['persona_id' => $a, 'simil_id' => $b],
            ['estado' => PersonaDuplicado::DESCARTADO]
        );

        $this->espejarVerificadas($a, $b);

        return redirect()->back()->with('success', 'Marcado como personas distintas.');
    }

    /** Vuelve a poner un par descartado en la lista de pendientes. */
    public function reabrir(Request $request)
    {
        $request->validate(['id' => 'required|integer']);

        $par = PersonaDuplicado::find($request->input('id'));
        if ($par) {
            $par->estado = PersonaDuplicado::PENDIENTE;
            $par->save();

            if (Schema::hasTable('personas_verificadas')) {
                DB::table('personas_verificadas')
                    ->where('persona_id', $par->persona_id)->where('simil_id', $par->simil_id)
                    ->delete();
                DB::table('personas_verificadas')
                    ->where('persona_id', $par->simil_id)->where('simil_id', $par->persona_id)
                    ->delete();
            }
        }

        return redirect()->back()->with('success', 'El par volvió a pendientes.');
    }

    /** Fusiona las dos personas de un par en la que elija el usuario. */
    public function fusionar(Request $request)
    {
        $request->validate([
            'ganador_id'  => 'required|integer|exists:personas,id',
            'perdedor_id' => 'required|integer|different:ganador_id',
        ]);

        set_time_limit(0);

        $r = FusionPersonas::fusionar((int) $request->input('ganador_id'), (int) $request->input('perdedor_id'));

        if (!$r['ok']) {
            return redirect()->back()->withErrors(['error' => $r['mensaje']]);
        }

        Cache::forget(self::CACHE_SIN_REGISTROS);
        Cache::forget(self::CACHE_SIN_FECHA);

        return redirect()->back()->with('success', $r['mensaje'] . ' ' . implode('; ', $r['detalle']));
    }

    /**
     * Borra personas que no tienen ningún registro asociado.
     *
     * No son candidatas a fusión: no le aportan nada a la ficha que queda, y
     * fusionarlas solo mete ruido en la carrera de la otra. Lo que corresponde
     * es sacarlas del medio.
     *
     * El servicio vuelve a contar con la fila bloqueada antes de borrar: si en
     * el medio alguien le colgó partidos a esa ficha, se aborta esa persona y
     * las demás siguen.
     */
    public function eliminar(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('personas', [])));

        if (!$ids) {
            return redirect()->back()->withErrors(['error' => 'No indicaste ninguna persona para borrar.']);
        }

        set_time_limit(0);

        $ok      = 0;
        $errores = [];

        foreach ($ids as $id) {
            $r = RegistrosPersonas::eliminar($id);
            if ($r['ok']) {
                $ok++;
            } else {
                $errores[] = $r['mensaje'];
            }
        }

        Cache::forget(self::CACHE_SIN_REGISTROS);
        Cache::forget(self::CACHE_SIN_FECHA);

        $mensaje = $ok === 1 ? 'Se borró 1 persona sin registros.' : "Se borraron {$ok} personas sin registros.";

        if ($errores) {
            return redirect()->back()
                ->with('success', $mensaje)
                ->withErrors(['error' => implode(' / ', array_slice($errores, 0, 5))]);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    /**
     * Completa las fichas sin fecha de nacimiento con el perfil de Transfermarkt.
     *
     * Escribe solo donde el campo está vacío: TM manda fechas mal seguido y lo
     * que ya está cargado a mano vale más. Va de a tandas porque cada 50
     * personas es una llamada a la API y el navegador tiene su tiempo máximo.
     */
    public function completarFechas(Request $request)
    {
        set_time_limit(0);

        $limite = (int) $request->input('limite', 500);
        $limite = $limite < 0 ? 0 : min($limite, 5000);

        // La ficha web se pide aparte porque gasta un crédito de ScraperAPI por
        // página: es la única forma de sacarle la fecha a los árbitros, pero no
        // es gratis como la API.
        $opciones = [
            'html'        => $request->boolean('html'),
            'limite_html' => max(0, min((int) $request->input('limite_html', 25), 500)),
            'reintentar'  => $request->boolean('reintentar'),
        ];

        try {
            $r = TmFechas::completar($limite, $this->pendientesSinFecha(), $opciones);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'No se pudo completar: ' . $e->getMessage()]);
        }

        Cache::forget(self::CACHE_SIN_FECHA);

        $partes = [];
        foreach ($r['campos'] as $campo => $n) {
            $partes[] = $n . ' ' . $campo;
        }

        $mensaje = 'Se ' . ($r['personas'] == 1 ? 'consultó 1 persona' : "consultaron {$r['personas']} personas")
            . ' en ' . ($r['llamadas'] == 1 ? '1 llamada' : "{$r['llamadas']} llamadas") . ' a Transfermarkt.';
        $mensaje .= $partes ? ' Se completó: ' . implode(', ', $partes) . '.' : ' No se completó ningún campo.';

        if ($r['html_paginas']) {
            $mensaje .= " Se bajaron {$r['html_paginas']} fichas web (1 crédito cada una) y de ahí salieron {$r['html_fechas']} fechas.";
        }
        if ($r['sin_fecha']) {
            $mensaje .= " {$r['sin_fecha']} tienen ficha en TM pero la API no trae fecha (es lo normal en árbitros)"
                . ($r['html_paginas'] ? '.' : ': probá con el tilde de la ficha web.');
        }
        if ($r['agotadas']) {
            $mensaje .= " {$r['agotadas']} ya se habían consultado antes sin suerte y no se volvieron a pedir"
                . ' (tildá "reintentar" si querés insistir).';
        }
        if ($r['sin_perfil']) {
            $mensaje .= " {$r['sin_perfil']} no volvieron de la API.";
        }
        if ($r['sin_tm']) {
            $mensaje .= " Quedan {$r['sin_tm']} sin id de Transfermarkt: esas no se pueden resolver así.";
        }
        if ($r['quedan']) {
            $mensaje .= " Faltan {$r['quedan']} con id de TM: volvé a apretar el botón para seguir.";
        }

        if ($r['errores']) {
            return redirect()->back()
                ->with('success', $mensaje)
                ->withErrors(['error' => implode(' / ', array_slice($r['errores'], 0, 5))]);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    /** Acciones sobre varios pares tildados a la vez. */
    public function lote(Request $request)
    {
        $ids    = (array) $request->input('pares', []);
        $accion = $request->input('accion');

        if (!$ids) {
            return redirect()->back()->withErrors(['error' => 'No tildaste ningún par.']);
        }

        set_time_limit(0);
        $pares = PersonaDuplicado::whereIn('id', $ids)->get();

        if ($accion === 'descartar') {
            foreach ($pares as $par) {
                $par->estado = PersonaDuplicado::DESCARTADO;
                $par->save();
                $this->espejarVerificadas($par->persona_id, $par->simil_id);
            }

            return redirect()->back()->with('success', $pares->count() . ' pares marcados como personas distintas.');
        }

        if ($accion === 'fusionar') {
            // Una sola llamada a peso() para todos los ids: dentro del loop sería
            // un N+1 de ~10 consultas por par.
            $todos = [];
            foreach ($pares as $par) {
                $todos[] = (int) $par->persona_id;
                $todos[] = (int) $par->simil_id;
            }
            $peso = FusionPersonas::peso($todos);

            $ok       = 0;
            $salteados = 0;
            $errores  = [];

            foreach ($pares as $par) {
                // Cada fusión borra una persona y limpia pares. Hay que releer:
                // si el usuario tildó (A,B) y (B,C), al fusionar la primera el
                // par (B,C) ya no existe y fusionarlo a ciegas sería un error.
                $vigente = PersonaDuplicado::find($par->id);
                if (!$vigente || $vigente->estado !== PersonaDuplicado::PENDIENTE) {
                    $salteados++;
                    continue;
                }

                $ganador  = $this->sugerirGanador($vigente->persona_id, $vigente->simil_id, $peso);
                $perdedor = $ganador === (int) $vigente->persona_id
                    ? (int) $vigente->simil_id
                    : (int) $vigente->persona_id;

                $r = FusionPersonas::fusionar($ganador, $perdedor);
                if ($r['ok']) {
                    $ok++;
                } else {
                    $errores[] = $r['mensaje'];
                }
            }

            Cache::forget(self::CACHE_SIN_REGISTROS);
        Cache::forget(self::CACHE_SIN_FECHA);

            $mensaje = "{$ok} pares fusionados.";
            if ($salteados) {
                $mensaje .= " {$salteados} se saltearon porque ya no estaban pendientes"
                    . " (probablemente los resolvió otra fusión de esta misma tanda).";
            }

            if ($errores) {
                return redirect()->back()
                    ->with('success', $mensaje)
                    ->withErrors(['error' => implode(' / ', array_slice($errores, 0, 5))]);
            }

            return redirect()->back()->with('success', $mensaje);
        }

        return redirect()->back()->withErrors(['error' => 'Acción desconocida.']);
    }

    /** Mantiene al día la tabla vieja `personas_verificadas`, si sigue existiendo. */
    private function espejarVerificadas($a, $b): void
    {
        if (!Schema::hasTable('personas_verificadas')) {
            return;
        }

        DB::table('personas_verificadas')->updateOrInsert(
            ['persona_id' => $a, 'simil_id' => $b],
            ['updated_at' => now()]
        );
    }

    /**
     * Con cuál conviene quedarse: la que tiene más registros asociados (así se
     * mueven menos filas) y, a igualdad, la que tiene más campos cargados.
     */
    public static function sugerirGanador($a, $b, array $peso): int
    {
        $a = (int) $a;
        $b = (int) $b;

        $pa = $peso[$a] ?? ['registros' => 0, 'campos' => 0];
        $pb = $peso[$b] ?? ['registros' => 0, 'campos' => 0];

        if ($pa['registros'] !== $pb['registros']) {
            return $pa['registros'] > $pb['registros'] ? $a : $b;
        }
        if ($pa['campos'] !== $pb['campos']) {
            return $pa['campos'] > $pb['campos'] ? $a : $b;
        }

        return min($a, $b); // el más viejo
    }
}
