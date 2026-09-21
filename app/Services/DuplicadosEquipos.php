<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Equipos que parecen ser el mismo club.
 *
 * El caso lo destapa siempre la carga: Transfermarkt nombra al mismo club de
 * dos maneras —«Olympiacos Piraeus» y «Olympiakos Piraeus», «Olympique Lyon» y
 * «Olympique Lyonnais»— y el importador, que aparea por nombre, crea un equipo
 * nuevo. A partir de ahí el club queda partido en dos: la mitad de los partidos
 * cuelga de una ficha y la mitad de la otra, y ninguno de los dos lados se ve
 * entero en el sitio.
 *
 * Esta clase NO escribe nada: arma la lista de pares sospechosos para que la
 * mire una persona. La corrección son dos caminos distintos y la pantalla los
 * distingue, porque la diferencia importa:
 *  - una de las dos fichas está VACÍA (0 registros): no hay nada que unificar,
 *    se borra la ficha de más;
 *  - las dos tienen historia: se unifican con `fusionarEquipos`.
 *
 * Por qué se calcula al vuelo y no hay tabla de candidatos (a diferencia de
 * `persona_duplicados`): `equipos` tiene tres órdenes de magnitud menos filas
 * que `personas`, el par se genera sólo dentro del mismo país, y la comparación
 * cara (levenshtein) se hace únicamente entre los que ya comparten un token
 * reducido. Sin tabla no hay migración que correr en el hosting.
 *
 * Lo que sí falta y se decide cuando se vea la lista real: **descartar un par**
 * («no son el mismo club») necesitaría guardarlo en algún lado. Por ahora el
 * único descarte es automático y no hace falta guardarlo: si los dos equipos
 * jugaron ENTRE SÍ, no son el mismo club y el par no se lista (es el mismo
 * freno que ya tiene la pantalla de unificar, y es un dato, no una opinión).
 */
class DuplicadosEquipos
{
    /** Puntaje mínimo para listar un par. */
    const UMBRAL = 78;

    /** Tope de pares a mostrar, por si el umbral se baja demasiado. */
    const TOPE = 400;

    /**
     * Palabras que no distinguen a un club de otro.
     *
     * Sólo se usan en UN escalón del puntaje («iguales salvo palabras de
     * relleno», 88): sacarlas siempre equipararía «Atlético Nacional» con
     * «Nacional», que en el mismo país pueden ser dos clubes distintos. Se
     * comparan ya reducidas: acá van escritas como se leen y `preparar()` les
     * aplica la misma reducción que a los nombres.
     */
    const RUIDO = [
        'club', 'clube', 'de', 'del', 'la', 'el', 'los', 'las', 'y', 'do', 'da',
        'fc', 'cf', 'ca', 'ac', 'sc', 'cd', 'afc', 'fbc', 'ec', 'sad', 'sa', 'cs',
        'aa', 'ad', 'cp', 'cr', 'se', 'futbol', 'football', 'fussball', 'calcio',
        'sporting', 'sportivo', 'deportivo', 'deportiva', 'atletico', 'atletica',
        'asociacion', 'association', 'sociedad', 'society',
    ];

    /**
     * Todas las columnas que apuntan a `equipos.id`.
     *
     * Vive acá y no en el controlador porque la usan los dos: la pantalla de
     * unificar (para mudar las filas) y ésta (para contar cuánto tiene cada
     * ficha). Si cada una tuviera su lista, un día contaríamos una tabla que la
     * fusión no mueve, o al revés: diríamos «ficha vacía» de una ficha que
     * todavía tiene filas colgando y el borrado fallaría por foreign key.
     *
     * La lista sale del esquema (`information_schema`), no de la memoria. Las
     * tres que no tienen foreign key están verificadas contra el código que las
     * escribe.
     */
    public static function columnasDeEquipo(): array
    {
        $base = DB::getDatabaseName();

        $cols = [];
        $filas = DB::select(
            'SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE '
            . 'WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ? '
            . 'ORDER BY TABLE_NAME, COLUMN_NAME', [$base, 'equipos', 'id']);
        foreach ($filas as $f) {
            $cols[$f->t . '.' . $f->c] = ['tabla' => $f->t, 'columna' => $f->c, 'de' => 'fk'];
        }

        foreach ([['equipo_tm', 'equipo_id'], ['import_partidos', 'equipo_id'],
                     ['import_partidos', 'rival_id']] as $par) {
            list($t, $c) = $par;
            if (isset($cols[$t . '.' . $c])) continue;
            if (!Schema::hasTable($t) || !Schema::hasColumn($t, $c)) continue;
            $cols[$t . '.' . $c] = ['tabla' => $t, 'columna' => $c, 'de' => 'lista'];
        }

        return array_values($cols);
    }

    /**
     * Los pares sospechosos, ordenados por puntaje.
     *
     * Devuelve ['pares' => [...], 'equipos' => N, 'jugaron' => N, 'tope' => bool].
     */
    public static function pares(int $umbral = self::UMBRAL, bool $cruzarPais = false): array
    {
        $equipos = [];
        foreach (DB::table('equipos')
                     ->select('id', 'nombre', 'pais', 'estadio', 'escudo', 'fundacion')
                     ->get() as $e) {
            $equipos[(int) $e->id] = self::preparar($e);
        }

        $candidatos = self::generar($equipos, $cruzarPais);

        if (!$candidatos) {
            return ['pares' => [], 'equipos' => count($equipos), 'jugaron' => 0, 'tope' => false];
        }

        // Los datos que necesitan consulta se piden UNA vez para todos los ids
        // que aparecen en algún par, no por fila.
        $ids = [];
        foreach ($candidatos as $c) { $ids[$c['a']] = true; $ids[$c['b']] = true; }
        $ids = array_keys($ids);

        $registros = self::registros($ids);
        $tm        = self::clubesTm($ids);
        $anios      = self::actividad($ids);
        $entreSi   = self::jugaronEntreSi($ids);
        $torneos   = self::torneosPorEquipo($ids);

        $pares = []; $jugaron = 0;
        foreach ($candidatos as $c) {
            $a = $c['a']; $b = $c['b'];
            $clave = $a . '-' . $b;

            // Freno duro: si se enfrentaron, no son el mismo club. No es una
            // conjetura ni un puntaje bajo — es un partido cargado.
            if (isset($entreSi[$clave])) { $jugaron++; continue; }

            $puntaje = $c['puntaje'];
            $motivos = [$c['motivo']];

            $ea = $equipos[$a]; $eb = $equipos[$b];

            if ($ea['estadio'] !== '' && $ea['estadio'] === $eb['estadio']) {
                $puntaje += 6; $motivos[] = 'mismo estadio';
            }
            if ($ea['fundacion'] && $ea['fundacion'] === $eb['fundacion']) {
                $puntaje += 6; $motivos[] = 'misma fundación (' . $ea['fundacion'] . ')';
            }
            if ($ea['escudo'] !== '' && $ea['escudo'] === $eb['escudo']) {
                $puntaje += 4; $motivos[] = 'mismo escudo';
            }

            $ra = isset($registros[$a]) ? $registros[$a] : 0;
            $rb = isset($registros[$b]) ? $registros[$b] : 0;
            if (!$ra || !$rb) {
                $puntaje += 5; $motivos[] = 'una de las dos fichas está vacía';
            }

            // Compartir torneo no prueba nada solo —dos clubes distintos juegan
            // el mismo campeonato todas las temporadas— pero entre dos fichas
            // que ADEMÁS se llaman casi igual es la señal de que son dos clubes
            // de verdad y no una ficha partida.
            $comunes = [];
            if (isset($torneos[$a]) && isset($torneos[$b])) {
                $comunes = array_intersect_key($torneos[$a], $torneos[$b]);
            }
            if ($comunes) {
                $puntaje -= 15;
                $motivos[] = 'jugaron ' . count($comunes) . ' torneo(s) en común sin cruzarse';
            }

            $puntaje = max(0, min(100, $puntaje));
            if ($puntaje < $umbral) continue;

            // El que se va es el de menos registros: mudar 3 filas es más barato
            // y más fácil de revisar que mudar 300.
            $origen  = $ra <= $rb ? $a : $b;
            $destino = $origen === $a ? $b : $a;

            $pares[] = [
                'a' => $a, 'b' => $b,
                'origen' => $origen, 'destino' => $destino,
                'puntaje' => $puntaje,
                'motivos' => $motivos,
                'vacia'   => (!$ra || !$rb) ? ($ra ? $b : $a) : 0,
                'equipos' => [$a => $ea, $b => $eb],
                'reg'     => [$a => $ra, $b => $rb],
                'tm'      => [$a => isset($tm[$a]) ? $tm[$a] : [], $b => isset($tm[$b]) ? $tm[$b] : []],
                'anios'   => [$a => isset($anios[$a]) ? $anios[$a] : null, $b => isset($anios[$b]) ? $anios[$b] : null],
                'comunes' => count($comunes),
            ];
        }

        usort($pares, function ($x, $y) {
            $d = $y['puntaje'] - $x['puntaje'];
            return $d ?: strcasecmp($x['equipos'][$x['a']]['nombre'], $y['equipos'][$y['a']]['nombre']);
        });

        $tope = count($pares) > self::TOPE;
        if ($tope) $pares = array_slice($pares, 0, self::TOPE);

        return ['pares' => $pares, 'equipos' => count($equipos), 'jugaron' => $jugaron, 'tope' => $tope];
    }

    /** Las claves de comparación de un equipo, calculadas una sola vez. */
    private static function preparar($e): array
    {
        $nombre = (string) $e->nombre;

        $norm   = DuplicadosPersonas::normalizar($nombre);
        $tokens = DuplicadosPersonas::tokenizar($nombre);

        $red = [];
        foreach ($tokens as $t) $red[] = DuplicadosPersonas::reducir($t);
        $red = array_values(array_unique(array_filter($red, function ($t) { return $t !== ''; })));

        $ordenado = $red; sort($ordenado);

        // Los mismos tokens sin las palabras de relleno. Si sacarlas deja el
        // nombre vacío («Club Atlético»), se deja como estaba: un nombre que es
        // todo relleno no puede aparear con cualquier otro.
        $utiles = array_values(array_diff($red, array_map(
            function ($r) { return DuplicadosPersonas::reducir($r); }, self::RUIDO)));
        if (!$utiles) $utiles = $red;
        sort($utiles);

        $fund = '';
        if ($e->fundacion && substr($e->fundacion, 0, 4) !== '0000') $fund = substr($e->fundacion, 0, 4);

        return [
            'id'        => (int) $e->id,
            'nombre'    => $nombre,
            'pais'      => DuplicadosPersonas::normalizar((string) $e->pais),
            'pais_txt'  => (string) $e->pais,
            'estadio'   => DuplicadosPersonas::normalizar((string) $e->estadio),
            'escudo'    => trim((string) $e->escudo),
            'fundacion' => $fund,
            'norm'      => $norm,
            'red'       => $red,
            'seq'       => implode(' ', $red),
            'orden'     => implode(' ', $ordenado),
            'utiles'    => implode(' ', $utiles),
        ];
    }

    /**
     * Genera los pares candidatos con su puntaje de nombre.
     *
     * Dos recorridas:
     *  1. dentro de cada país, sólo entre los que comparten un token reducido o
     *     el arranque del nombre (sin ese filtro serían todos contra todos y la
     *     comparación cara —levenshtein— se haría un millón de veces);
     *  2. por clave exacta en TODA la tabla, para el par que se llama igual y
     *     tiene el país distinto (o vacío). Ahí el sospechoso no es el club:
     *     es el país mal cargado. Con `$cruzarPais` en false sólo se listan los
     *     que tienen un país vacío, que es error seguro; el resto necesita el
     *     tilde, porque dos clubes homónimos en países distintos son lo normal.
     */
    private static function generar(array $equipos, bool $cruzarPais): array
    {
        $out = [];
        $vistos = [];

        $porPais = [];
        foreach ($equipos as $id => $e) $porPais[$e['pais']][] = $id;

        foreach ($porPais as $pais => $ids) {
            if (count($ids) < 2) continue;

            // Índice invertido: token reducido -> ids, y prefijo de 4 -> ids.
            $index = [];
            foreach ($ids as $id) {
                foreach ($equipos[$id]['red'] as $t) $index['t:' . $t][] = $id;
                $index['p:' . substr($equipos[$id]['seq'], 0, 4)][] = $id;
            }

            foreach ($index as $grupo) {
                $n = count($grupo);
                if ($n < 2 || $n > 400) continue; // un grupo enorme es una palabra vacía de información
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $a = min($grupo[$i], $grupo[$j]);
                        $b = max($grupo[$i], $grupo[$j]);
                        if ($a === $b || isset($vistos[$a . '-' . $b])) continue;
                        $vistos[$a . '-' . $b] = true;

                        $p = self::puntuarNombres($equipos[$a], $equipos[$b]);
                        if ($p) $out[] = $p + ['a' => $a, 'b' => $b];
                    }
                }
            }
        }

        // Segunda recorrida: mismo nombre, país distinto.
        $porClave = [];
        foreach ($equipos as $id => $e) $porClave[$e['seq']][] = $id;

        foreach ($porClave as $ids) {
            $n = count($ids);
            if ($n < 2 || $n > 50) continue;
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a = min($ids[$i], $ids[$j]);
                    $b = max($ids[$i], $ids[$j]);
                    if (isset($vistos[$a . '-' . $b])) continue;

                    $pa = $equipos[$a]['pais']; $pb = $equipos[$b]['pais'];
                    if ($pa === $pb) continue; // ya salió en la primera recorrida
                    $sinPais = ($pa === '' || $pb === '');
                    if (!$sinPais && !$cruzarPais) continue;

                    $vistos[$a . '-' . $b] = true;
                    $p = self::puntuarNombres($equipos[$a], $equipos[$b]);
                    if (!$p) continue;

                    $p['motivo'] .= $sinPais
                        ? ', y uno de los dos no tiene país cargado'
                        : ', pero el país es distinto (' . ($equipos[$a]['pais_txt'] ?: '—') . ' / '
                          . ($equipos[$b]['pais_txt'] ?: '—') . ')';
                    if (!$sinPais) $p['puntaje'] -= 20;

                    $out[] = $p + ['a' => $a, 'b' => $b];
                }
            }
        }

        return $out;
    }

    /**
     * Cuánto se parecen dos nombres. `null` = ni candidato.
     *
     * Los escalones van de la coincidencia más segura a la más conjetural, y
     * cada uno dice POR QUÉ entró: el puntaje solo no se puede discutir, el
     * motivo sí.
     */
    private static function puntuarNombres(array $x, array $y): ?array
    {
        if ($x['norm'] === $y['norm']) {
            return ['puntaje' => 100, 'motivo' => 'el nombre es idéntico'];
        }
        if ($x['seq'] !== '' && $x['seq'] === $y['seq']) {
            return ['puntaje' => 95, 'motivo' => 'se escriben distinto pero suenan igual'];
        }
        if ($x['orden'] !== '' && $x['orden'] === $y['orden']) {
            return ['puntaje' => 92, 'motivo' => 'las mismas palabras en otro orden'];
        }
        if ($x['utiles'] !== '' && $x['utiles'] === $y['utiles']) {
            return ['puntaje' => 88, 'motivo' => 'iguales salvo palabras de relleno (Club, Deportivo, FC…)'];
        }

        $rx = $x['red']; $ry = $y['red'];
        $faltaEnY = array_values(array_diff($rx, $ry));
        $faltaEnX = array_values(array_diff($ry, $rx));

        // Uno contenido en el otro: «Sport Boys» dentro de «Sport Boys Warnes».
        if (!$faltaEnY && $rx) {
            return ['puntaje' => 86, 'motivo' => '«' . $x['nombre'] . '» está entero adentro del otro nombre'];
        }
        if (!$faltaEnX && $ry) {
            return ['puntaje' => 86, 'motivo' => '«' . $y['nombre'] . '» está entero adentro del otro nombre'];
        }

        // Todo igual menos una palabra, y esa palabra es casi la misma:
        // «Olympique Lyon» / «Olympique Lyonnais».
        if (count($faltaEnY) === 1 && count($faltaEnX) === 1 && count($rx) === count($ry)) {
            $u = $faltaEnY[0]; $v = $faltaEnX[0];
            $corto = strlen($u) <= strlen($v) ? $u : $v;
            $largo = $corto === $u ? $v : $u;
            if (strlen($corto) >= 4 && strpos($largo, $corto) === 0) {
                return ['puntaje' => 82, 'motivo' => 'cambia una sola palabra, y una es el comienzo de la otra ('
                    . $u . ' / ' . $v . ')'];
            }
            if (strlen($corto) >= 4 && levenshtein($u, $v) <= 2) {
                return ['puntaje' => 80, 'motivo' => 'cambia una sola palabra, por una o dos letras ('
                    . $u . ' / ' . $v . ')'];
            }
        }

        // Último recurso: el nombre entero a una o dos letras de distancia.
        if (strlen($x['seq']) >= 8 && abs(strlen($x['seq']) - strlen($y['seq'])) <= 2
            && levenshtein($x['seq'], $y['seq']) <= 2) {
            return ['puntaje' => 78, 'motivo' => 'el nombre entero está a una o dos letras de distancia'];
        }

        return null;
    }

    /**
     * Cuántas filas cuelgan de cada equipo, sumando TODAS las tablas que lo
     * referencian. Es el mismo criterio que usa la fusión para mudar, así que
     * «0 registros» acá significa que la ficha se puede borrar.
     */
    public static function registros(array $ids): array
    {
        $out = [];
        foreach (self::columnasDeEquipo() as $c) {
            $filas = DB::table($c['tabla'])
                ->select($c['columna'] . ' as eq', DB::raw('COUNT(*) as n'))
                ->whereIn($c['columna'], $ids)
                ->groupBy($c['columna'])->get();
            foreach ($filas as $f) {
                $id = (int) $f->eq;
                $out[$id] = (isset($out[$id]) ? $out[$id] : 0) + (int) $f->n;
            }
        }
        return $out;
    }

    /** Clubes de Transfermarkt atados a cada equipo. */
    private static function clubesTm(array $ids): array
    {
        $out = [];
        if (!Schema::hasTable('equipo_tm')) return $out;
        foreach (DB::table('equipo_tm')->select('equipo_id', 'tm_club_id', 'nombre_tm')
                     ->whereIn('equipo_id', $ids)->get() as $t) {
            $out[(int) $t->equipo_id][] = $t;
        }
        return $out;
    }

    /** Primer y último año con partido cargado. */
    private static function actividad(array $ids): array
    {
        $sub = DB::table('partidos')->select('equipol_id as eq', 'dia')->whereIn('equipol_id', $ids)
            ->unionAll(DB::table('partidos')->select('equipov_id as eq', 'dia')->whereIn('equipov_id', $ids));

        $out = [];
        foreach (DB::query()->fromSub($sub, 'x')
                     ->select('eq', DB::raw('MIN(dia) as desde'), DB::raw('MAX(dia) as hasta'))
                     ->groupBy('eq')->get() as $f) {
            if (!$f->desde) continue;
            $out[(int) $f->eq] = ['desde' => substr($f->desde, 0, 4), 'hasta' => substr($f->hasta, 0, 4)];
        }
        return $out;
    }

    /** Los pares que se enfrentaron alguna vez, como mapa "menor-mayor". */
    private static function jugaronEntreSi(array $ids): array
    {
        $out = [];
        foreach (DB::table('partidos')->select('equipol_id', 'equipov_id')
                     ->whereIn('equipol_id', $ids)->whereIn('equipov_id', $ids)->get() as $p) {
            $a = (int) $p->equipol_id; $b = (int) $p->equipov_id;
            if (!$a || !$b || $a === $b) continue;
            $out[min($a, $b) . '-' . max($a, $b)] = true;
        }
        return $out;
    }

    /** Torneos en los que cada equipo tiene algún partido. */
    private static function torneosPorEquipo(array $ids): array
    {
        $sel = function ($col) use ($ids) {
            return DB::table('partidos')
                ->join('fechas', 'partidos.fecha_id', '=', 'fechas.id')
                ->join('grupos', 'fechas.grupo_id', '=', 'grupos.id')
                ->select('partidos.' . $col . ' as eq', 'grupos.torneo_id as torneo')
                ->whereIn('partidos.' . $col, $ids);
        };

        $out = [];
        foreach (DB::query()->fromSub($sel('equipol_id')->unionAll($sel('equipov_id')), 'x')
                     ->select('eq', 'torneo')->distinct()->get() as $f) {
            $out[(int) $f->eq][(int) $f->torneo] = true;
        }
        return $out;
    }
}
