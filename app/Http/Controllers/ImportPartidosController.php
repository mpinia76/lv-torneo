<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\HttpHelper;

/**
 * Motor de carga de partidos, DT por DT.
 *
 *   index()    -> lista de DTs con URL de Transfermarkt y su estado
 *   sondear()  -> baja los partidos del DT, los clasifica y (opcional) los guarda en staging
 *   aplicar()  -> crea de verdad los partidos nuevos: torneo -> grupo -> fecha -> partido -> partido_tecnico
 *
 * No toca nada de lo que ya funciona. Los clubes se resuelven por clubId de
 * Transfermarkt (tabla equipo_tm), nunca por nombre.
 */
class ImportPartidosController extends Controller
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    // ═══════════════════════════════ ÍNDICE ═══════════════════════════════

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $tecnicos = \App\Tecnico::with('persona')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->get()
            ->map(function ($t) {
                return (object) [
                    'id'     => $t->id,
                    'nombre' => optional($t->persona)->name ?: ('DT #' . $t->id),
                    'url'    => $t->transfermarkt_url,
                ];
            })
            ->filter(function ($t) use ($q) {
                return $q === '' || mb_stripos($t->nombre, $q) !== false;
            })
            ->sortBy('nombre')
            ->values();

        // Contadores del staging
        $stats = [];
        foreach (DB::table('import_partidos')
                     ->select('tecnico_id', 'estado', DB::raw('COUNT(*) AS n'))
                     ->groupBy('tecnico_id', 'estado')->get() as $r) {
            $stats[(int) $r->tecnico_id][$r->estado] = (int) $r->n;
        }

        $filas = '';
        foreach ($tecnicos as $t) {
            $s = isset($stats[$t->id]) ? $stats[$t->id] : [];
            $nuevo     = isset($s['nuevo']) ? $s['nuevo'] : 0;
            $conflicto = isset($s['conflicto']) ? $s['conflicto'] : 0;
            $aplicado  = isset($s['aplicado']) ? $s['aplicado'] : 0;
            $duplicado = isset($s['duplicado']) ? $s['duplicado'] : 0;
            $sondeado  = ($nuevo + $conflicto + $aplicado + $duplicado) > 0;

            $filas .= '<tr>'
                . '<td>' . e($t->nombre) . '</td>'
                . '<td class="num">' . ($sondeado ? $duplicado : '—') . '</td>'
                . '<td class="num">' . ($nuevo ? '<b class="ok">' . $nuevo . '</b>' : ($sondeado ? '0' : '—')) . '</td>'
                . '<td class="num">' . ($conflicto ? '<b class="err">' . $conflicto . '</b>' : ($sondeado ? '0' : '—')) . '</td>'
                . '<td class="num">' . ($aplicado ? '<b class="ok">' . $aplicado . '</b>' : ($sondeado ? '0' : '—')) . '</td>'
                . '<td><a href="' . e(route('import_partidos.sondear', ['tecnico_id' => $t->id, 'aprender' => 1, 'guardar' => 1])) . '">Sondear</a>'
                . ($nuevo ? ' · <a href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $t->id])) . '"><b>Aplicar ' . $nuevo . '</b></a>' : '')
                . '</td></tr>';
        }

        $html = '<h1>Carga de partidos · DT por DT</h1>'
            . '<p class="sub">Estos son los DTs que ya pasaron por el sondeo. El botón <b>Partidos</b> de la lista de técnicos '
            . 'baja los partidos del DT y los deja en staging; necesita que el DT tenga cargado el slug de Transfermarkt.</p>'
            . '<p class="acciones"><a class="boton-sec" href="' . e(route('import_detalles.index')) . '">'
            . 'Detalle de los partidos (alineaciones, goles, tarjetas, cambios)</a></p>'
            . '<form method="get" style="margin:12px 0"><input name="q" value="' . e($q) . '" placeholder="buscar DT…" size="30"> <button>Buscar</button></form>'
            . '<div class="scroll"><table><thead><tr><th>DT</th><th>Ya cargados</th><th>Nuevos</th><th>Conflictos</th><th>Aplicados</th><th></th></tr></thead>'
            . '<tbody>' . $filas . '</tbody></table></div>';

        return $this->pagina('Carga de partidos', $html);
    }

    // ═══════════════════ SONDEO DE UN PARTIDO (descubrimiento) ═══════════════════

    /**
     * Prueba los endpoints de tmapi para un partido y muestra qué devuelve cada uno.
     * No escribe nada: sirve para saber de dónde salen alineaciones, goles,
     * tarjetas, cambios y árbitro antes de escribir el importador.
     *
     *   /admin/import-partidos/partido?game_id=2480728
     *   /admin/import-partidos/partido?partido_id=24803   (busca el gameId en el staging)
     */
    public function partido(Request $request)
    {
        set_time_limit(0);

        $gameId = trim((string) $request->get('game_id', ''));
        $fila = null;

        if ($gameId === '' && $request->filled('partido_id')) {
            $fila = DB::table('import_partidos')->where('partido_id', (int) $request->get('partido_id'))->first();
            if ($fila) $gameId = (string) $fila->external_id;
        }
        if ($gameId === '') {
            // Si no dijo nada, agarramos el primer partido aplicado que tengamos.
            $fila = DB::table('import_partidos')->whereNotNull('external_id')
                ->where('estado', 'aplicado')->orderBy('dia', 'desc')->first();
            if ($fila) $gameId = (string) $fila->external_id;
        }
        if ($gameId === '') {
            return $this->pagina('Sondeo de partido',
                '<p class="err">Pasá <code>?game_id=</code> o <code>?partido_id=</code>.</p>');
        }

        // /game/{id} trae todo (lineup, actions, referees). Los demás dan 404:
        // solo se prueban si se los pide expresamente con &todos=1.
        $candidatos = ["/game/{$gameId}"];
        if ((string) $request->get('todos', '0') === '1') {
            $candidatos = array_merge($candidatos, [
                "/game/{$gameId}/lineup", "/game/{$gameId}/lineups", "/game/{$gameId}/events",
                "/game/{$gameId}/incidents", "/game/{$gameId}/statistics", "/game/{$gameId}/report",
                "/match/{$gameId}",
            ]);
        }

        $html = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a></p>'
            . '<h1>Sondeo de partido · gameId ' . e($gameId) . '</h1>';

        if ($fila) {
            $html .= '<p class="sub">' . e($fila->club_nombre . ' vs ' . $fila->rival_nombre)
                . ' · ' . e(substr((string) $fila->dia, 0, 10))
                . ' · ' . e((string) $fila->competencia_nombre)
                . ($fila->partido_id ? ' · partido #' . (int) $fila->partido_id : '') . '</p>';
        }
        $html .= '<p class="sub">Cada endpoint es una llamada a ScraperAPI. Los que respondan con datos son los '
            . 'que vamos a usar para alineaciones e incidencias.</p>';

        $rama = trim((string) $request->get('rama', ''));
        $cuantos = max(1, (int) $request->get('n', 3));

        foreach ($candidatos as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);

            $html .= '<h2><code>' . e($ruta) . '</code></h2>';

            if (!is_array($json) || empty($json)) {
                $err = HttpHelper::getLastJsonError();
                $html .= '<p class="sub">Sin datos' . (is_array($err) ? ' — ' . e(json_encode($err, JSON_UNESCAPED_UNICODE)) : '') . '</p>';
                continue;
            }

            $data = isset($json['data']) ? $json['data'] : $json;

            // Atajos para mirar una rama concreta sin abrir el JSON entero.
            if ($ruta === "/game/{$gameId}") {
                $ramas = ['homeClub.lineup.players', 'homeClub.lineup.substitutes', 'homeClub.actions.goals',
                    'homeClub.actions.cards', 'homeClub.actions.substitutes', 'homeClub.tactic',
                    'actions', 'refereeIds', 'playerIds', 'coaches', 'score', 'baseDetails'];
                $links = [];
                foreach ($ramas as $r) {
                    $links[] = '<a href="' . e($request->url() . '?' . http_build_query(
                                array_merge($request->query(), ['rama' => $r, 'n' => $cuantos]))) . '">' . e($r) . '</a>';
                }
                $html .= '<p class="acciones">Ver rama: ' . implode(' · ', $links) . '</p>';

                if ($rama !== '') {
                    $html .= '<h3><code>' . e($rama) . '</code></h3>' . $this->verRama($data, $rama, $cuantos);
                }
            }

            // Los nombres de los jugadores de la alineación no vienen en el partido:
            // probamos cómo resolverlos.
            if ($ruta === "/game/{$gameId}" && (string) $request->get('jugadores', '0') === '1') {
                $html .= $this->probarJugadores($data);
            }

            $html .= '<div class="diag">' . $this->arbolClaves($data) . '</div>';
        }

        return $this->pagina('Sondeo de partido', $html);
    }

    /** Prueba cómo resolver el nombre de los jugadores de la alineación. */
    private function probarJugadores(array $data)
    {
        $ids = [];
        foreach (['homeClub', 'awayClub'] as $lado) {
            if (!isset($data[$lado]['lineup']['players'])) continue;
            foreach ($data[$lado]['lineup']['players'] as $p) {
                if (!empty($p['id'])) $ids[] = (string) $p['id'];
                if (count($ids) >= 3) break 2;
            }
        }
        if (empty($ids)) return '<p class="err">No encontré ids de jugadores en la alineación.</p>';

        $html = '<h3>Resolver nombres de jugadores</h3><p class="sub">Ids de prueba: <code>'
            . e(implode(', ', $ids)) . '</code></p>';

        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));
        $pruebas = [
            '/players?' . $qs,
            '/player/' . $ids[0],
        ];

        foreach ($pruebas as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $html .= '<h4><code>' . e($ruta) . '</code></h4>';
            if (!is_array($json) || empty($json)) {
                $err = HttpHelper::getLastJsonError();
                $html .= '<p class="sub">Sin datos' . (is_array($err) ? ' — ' . e(json_encode($err, JSON_UNESCAPED_UNICODE)) : '') . '</p>';
                continue;
            }
            $d = isset($json['data']) ? $json['data'] : $json;
            $html .= '<pre>' . e(mb_substr(json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 4000)) . '</pre>';
        }

        return $html;
    }

    /** Imprime una rama del JSON (ruta con puntos), mostrando los primeros N elementos. */
    private function verRama($data, $rama, $cuantos)
    {
        $actual = $data;
        foreach (explode('.', $rama) as $paso) {
            if (!is_array($actual) || !array_key_exists($paso, $actual)) {
                return '<p class="err">No existe esa rama.</p>';
            }
            $actual = $actual[$paso];
        }

        $esLista = is_array($actual) && array_keys($actual) === range(0, max(0, count($actual) - 1));
        $muestra = $esLista ? array_slice($actual, 0, $cuantos) : $actual;

        return '<pre>' . e(json_encode($muestra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            . '</pre>' . ($esLista ? '<p class="sub">' . count($actual) . ' elementos en total.</p>' : '');
    }

    /** Muestra las claves de un JSON hasta cierta profundidad, con el JSON crudo al final. */
    private function arbolClaves($data, $prof = 0)
    {
        if (!is_array($data)) return '<code>' . e(mb_substr((string) $data, 0, 120)) . '</code>';

        $lineas = [];
        $esLista = array_keys($data) === range(0, count($data) - 1);

        if ($esLista) {
            $lineas[] = '<strong>lista de ' . count($data) . ' elementos</strong>';
            if (isset($data[0]) && is_array($data[0])) {
                $lineas[] = '&nbsp;&nbsp;claves: <code>' . e(implode(', ', array_keys($data[0]))) . '</code>';
            }
        } else {
            $lineas[] = '<strong>claves:</strong> <code>' . e(implode(', ', array_keys($data))) . '</code>';
            if ($prof < 2) {
                foreach ($data as $k => $v) {
                    if (!is_array($v)) continue;
                    $lineas[] = '&nbsp;&nbsp;↳ <em>' . e($k) . '</em>: ' . $this->arbolClaves($v, $prof + 1);
                }
            }
        }

        if ($prof === 0) {
            $lineas[] = '<details><summary>JSON crudo</summary><pre>'
                . e(mb_substr(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 20000))
                . '</pre></details>';
        }

        return implode('<br>', $lineas);
    }

    // ═══════════════════════════════ SONDEO ═══════════════════════════════

    public function sondear(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = $request->get('tecnico_id');
        $url       = trim((string) $request->get('url', ''));
        $guardar   = (string) $request->get('guardar', '0') === '1';
        $aprender  = (string) $request->get('aprender', '0') === '1';
        $desde     = (int) $request->get('desde', 2000);
        $limite    = (int) $request->get('limite', 60);
        $filtro    = trim((string) $request->get('estado', ''));

        $avisos = [];

        if ($request->filled('mapear_tm') && $request->filled('mapear_equipo')) {
            $tmId = trim((string) $request->get('mapear_tm'));
            $eqId = (int) preg_replace('/\D.*$/', '', trim((string) $request->get('mapear_equipo')));
            if ($tmId !== '' && $eqId > 0 && \App\Equipo::where('id', $eqId)->exists()) {
                $this->guardarMapeo($tmId, $eqId, $request->get('mapear_nombre'), 'manual');
                $avisos[] = 'Club de TM ' . e($tmId) . ' mapeado al equipo #' . $eqId . '.';
            } else {
                $avisos[] = '<span class="err">No pude mapear: revisá el id de equipo.</span>';
            }
        }

        $nombreDT = null;
        $tecnico = null;
        if ($tecnicoId) {
            $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
            if (!$tecnico) return $this->pagina('Sondeo', '<p class="err">No existe el técnico #' . (int) $tecnicoId . '</p>');
            $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);
            if ($url === '') $url = trim((string) $tecnico->transfermarkt_url);

            if ($url !== '') {
                $avisos[] = 'Slug de Transfermarkt <b>ya cargado</b>: <code>' . e($url) . '</code> '
                    . '<a href="' . e($url) . '" target="_blank">ver perfil ↗</a>';
            }

            // Sin slug no hay nada que hacer: lo cargás vos desde la pantalla de estadísticas.
            if ($url === '') {
                return $this->pagina('Sondeo',
                    '<h1>' . e($nombreDT) . '</h1>'
                    . '<p class="err-box">Este DT no tiene cargado el slug de Transfermarkt.<br>'
                    . 'Buscalo y pegalo en <b>Importar desde Transfermarkt</b> '
                    . '(<a href="' . e(route('tecnico-estadisticas.createPorTecnico', $tecnico->id)) . '">abrir la pantalla del DT</a>), '
                    . 'y volvé a apretar <b>Partidos</b>.</p>');
            }
        }
        if ($url === '') {
            return $this->pagina('Sondeo', '<p class="err">Falta <code>?tecnico_id=</code> o <code>?url=</code>.</p>');
        }
        if (!preg_match('#/trainer/(\d+)#', $url, $m)) {
            return $this->pagina('Sondeo', '<p class="err">La URL no tiene el formato <code>.../trainer/{id}</code>: ' . e($url) . '</p>');
        }
        $coachId = $m[1];

        // ── Datos: del staging si ya los bajamos, o de Transfermarkt ────────
        // Después de mapear un club no hace falta volver a scrapear: las filas
        // ya están guardadas con todo lo necesario.
        $usarCache = (string) $request->get('cache', '0') === '1';
        $games = null;
        $filas = [];

        if ($usarCache && $tecnicoId) {
            $filas = $this->filasDesdeStaging($tecnicoId);
            if (empty($filas)) $usarCache = false;
        } else {
            $usarCache = false;
        }

        if (!$usarCache) {
            $games = $this->traerPartidos($coachId);
            if (is_string($games)) return $this->pagina('Sondeo', '<p class="err">' . $games . '</p>');
            $filas = [];
            foreach ($games as $g) {
                $filas[] = $this->normalizar($g, $coachId);
            }
            $filas = $this->completarNombres($filas);
        }

        $temporadas = [];
        foreach ($filas as $f) {
            if ($f['temporada'] !== null) $temporadas[] = (int) $f['temporada'];
        }
        $filas = $this->clasificar($filas, $desde);

        $aprendidos = [];
        if ($aprender) {
            $aprendidos = $this->aprenderMapeos($filas);
            if (!empty($aprendidos)) $filas = $this->clasificar($filas, $desde);
        }

        $cont = ['total' => count($filas), 'excluido' => 0, 'duplicado' => 0,
            'falta_dt' => 0, 'nuevo' => 0, 'conflicto' => 0, 'corridos' => 0];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['estado'] === 'duplicado' && strpos((string) $f['motivo'], 'falta el DT') !== false) $cont['falta_dt']++;
            if (isset($f['corrido'])) $cont['corridos']++;
        }

        $guardadas = 0;
        if ($guardar) {
            foreach ($filas as $f) {
                $guardadas += $this->persistir($f, $coachId, $tecnicoId) ? 1 : 0;
            }
        }

        sort($temporadas);
        $rango = empty($temporadas) ? '?' : (reset($temporadas) . ' – ' . end($temporadas));

        $html  = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a>'
            . ($tecnicoId ? ' · <a href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId]))
                . '">Detalle de los partidos →</a>' : '') . '</p>';
        $html .= '<h1>Sondeo · ' . e($nombreDT ?: ('coach ' . $coachId)) . '</h1>';
        $html .= '<p class="sub">coach ' . e($coachId) . ' · ' . $cont['total'] . ' partidos · temporadas ' . e($rango) . ' · corte en ' . $desde . '</p>';

        foreach ($avisos as $a) $html .= '<p class="ok-box">' . $a . '</p>';

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos')
            . $this->card($cont['excluido'], 'fuera de alcance', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['corridos'], 'con fecha corrida', $cont['corridos'] ? 'warn' : '')
            . $this->card($cont['falta_dt'], 'sin el DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . '</div>';

        if ($usarCache) {
            $html .= '<p class="sub">Datos tomados de <code>import_partidos</code>: no se volvió a bajar nada de Transfermarkt.</p>';
        }

        $base = $this->urlBase($request);
        $cache = $usarCache ? '&cache=1' : '';
        $html .= '<p class="acciones">'
            . '<a href="' . e($base . '&aprender=1&guardar=1' . $cache) . '">Aprender mapeo y guardar</a> · '
            . '<a href="' . e($base . '&estado=conflicto&limite=300' . $cache) . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&estado=nuevo&limite=300' . $cache) . '">Ver solo nuevos</a> · '
            . '<a href="' . e($base . '&cache=1&aprender=1&guardar=1') . '">Refrescar sin bajar</a> · '
            . '<a href="' . e($base . '&aprender=1&guardar=1') . '">Volver a bajar de Transfermarkt</a>';
        if ($tecnicoId) {
            $html .= ' · <a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Aplicar los nuevos →</a>';
        } else {
            $html .= ' <span class="sub">(para aplicar hace falta entrar con <code>?tecnico_id=</code>)</span>';
        }
        $html .= '</p>';

        if ($aprender) {
            $html .= '<p class="ok-box">Mapeos aprendidos: <b>' . count($aprendidos) . '</b>'
                . (empty($aprendidos) ? '' : '<br><span class="sub">' . e(implode(' · ', array_slice($aprendidos, 0, 40))) . '</span>') . '</p>';
        }
        if ($guardar) $html .= '<p class="ok-box">Guardadas ' . $guardadas . ' filas en <code>import_partidos</code>.</p>';

        $html .= $this->bloqueMapeosSospechosos($filas, $request);
        $html .= $this->bloqueClubesSinResolver($filas, $request);

        $titulo = $filtro !== '' ? ('Partidos con estado «' . e($filtro) . '»') : ('Primeros ' . $limite . ' partidos');
        $html .= '<h2>' . $titulo . '</h2>' . $this->tabla($filas, $limite, $filtro);
        if (!empty($games)) {
            $html .= '<h2>Estructura del JSON</h2>' . $this->diagnosticar($games[0]);
        }

        return $this->pagina('Sondeo de partidos', $html);
    }

    // ═══════════════════════════════ APLICAR ═══════════════════════════════

    public function aplicar(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id');
        if (!$tecnicoId) return $this->pagina('Aplicar', '<p class="err">Falta <code>?tecnico_id=</code>. Los partidos se crean con su DT, así que hace falta saber quién es.</p>');

        $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
        if (!$tecnico) return $this->pagina('Aplicar', '<p class="err">No existe el técnico #' . $tecnicoId . '</p>');
        $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);

        $volver = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a> · '
            . '<a href="' . e(route('import_partidos.sondear', ['tecnico_id' => $tecnicoId])) . '">Volver al sondeo</a> · '
            . '<a href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId])) . '">Detalle de los partidos →</a></p>';

        // ── Revisar/corregir la localía de lo ya aplicado ───────────────────
        if ((string) $request->get('arreglar_localia', '0') === '1') {
            return $this->pagina('Aplicar', $volver . $this->arreglarLocalia($tecnicoId));
        }

        // ── Completar el DT en partidos que ya estaban cargados ─────────────
        if ((string) $request->get('completar_dt', '0') === '1') {
            $n = $this->completarTecnicos($tecnicoId);
            return $this->pagina('Aplicar', $volver . '<h1>Listo</h1><p class="ok-box">Agregué el DT en ' . $n . ' partidos que ya estaban cargados.</p>');
        }

        // ── Confirmación: crear los partidos de un grupo ────────────────────
        if ((string) $request->get('confirmar', '0') === '1') {
            return $this->aplicarGrupo($request, $tecnicoId, $nombreDT, $volver);
        }

        // ── Pantalla: grupos pendientes ─────────────────────────────────────
        // Filas marcadas como aplicadas cuyo partido ya no existe (borrado a mano):
        // vuelven a estar pendientes.
        DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')
            ->whereNotNull('partido_id')
            ->whereNotIn('partido_id', function ($q) {
                $q->select('id')->from('partidos');
            })
            ->update(['estado' => 'nuevo', 'partido_id' => null, 'motivo' => null]);

        $pendientes = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'nuevo')
            ->orderBy('dia')->get();

        $faltaDt = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'duplicado')
            ->where('motivo', 'like', '%falta el DT%')->count();

        $html = $volver . '<h1>Aplicar partidos · ' . e($nombreDT) . '</h1>';

        $yaAplicados = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')->count();
        if ($yaAplicados) {
            $html .= '<p class="sub">Ya aplicaste <b>' . $yaAplicados . '</b> partidos de este DT. '
                . '<a class="boton-sec" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId, 'arreglar_localia' => 1])) . '">Revisar la localía de lo aplicado</a> '
                . '<a class="boton-sec" href="' . e(route('import_detalles.index', ['tecnico_id' => $tecnicoId])) . '">Bajar el detalle de esos partidos</a></p>';
        }

        if ($faltaDt) {
            $html .= '<p class="ok-box">Hay <b>' . $faltaDt . '</b> partidos ya cargados donde falta este DT. '
                . '<a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId, 'completar_dt' => 1])) . '">Agregar el DT en esos partidos</a></p>';
        }

        if ($pendientes->isEmpty()) {
            return $this->pagina('Aplicar', $html . '<p class="sub">No hay partidos nuevos en staging. Corré el sondeo con <code>&guardar=1</code> primero.</p>');
        }

        // Agrupar por competencia + temporada
        $grupos = [];
        foreach ($pendientes as $r) {
            $k = $r->competencia_external_id . '|' . $r->temporada;
            if (!isset($grupos[$k])) {
                $grupos[$k] = ['comp' => $r->competencia_external_id, 'temp' => $r->temporada,
                    'nombre' => $r->competencia_nombre, 'n' => 0,
                    'desde' => $r->dia, 'hasta' => $r->dia, 'equipos' => [],
                    'equipo_id' => $r->equipo_id];
            }
            $grupos[$k]['n']++;
            if ($r->dia < $grupos[$k]['desde']) $grupos[$k]['desde'] = $r->dia;
            if ($r->dia > $grupos[$k]['hasta']) $grupos[$k]['hasta'] = $r->dia;
            if ($r->club_nombre) $grupos[$k]['equipos'][$r->club_nombre] = true;
        }

        $torneos = \App\Torneo::orderBy('year', 'desc')->orderBy('nombre')->get();

        $html .= '<p class="sub">Cada competencia+temporada va a un torneo. Elegí uno de los tuyos; si no existe, '
            . '«Crear torneo» abre el alta de siempre con el nombre, el año, el tipo y el ámbito ya cargados. '
            . 'Lo guardás, volvés acá, refrescás y ya aparece en la lista.</p>'
            . '<div class="scroll"><table><thead><tr><th>Competencia</th><th>Temp. TM</th><th>Partidos</th><th>Período</th><th>Equipo(s)</th><th>Torneo destino</th></tr></thead><tbody>';

        foreach ($grupos as $g) {
            // Ojo: la temporada de Transfermarkt no es el año del torneo. El Clausura 2026
            // sale como seasonId 2025. Preseleccionamos mirando los años reales de los partidos.
            $anios = [];
            $anios[substr($g['desde'], 0, 4)] = true;
            $anios[substr($g['hasta'], 0, 4)] = true;
            $anios[(string) $g['temp']] = true;
            $anios[(string) ((int) $g['temp'] + 1)] = true;
            $anios[$g['temp'] . '/' . substr((string) ((int) $g['temp'] + 1), -2)] = true;
            $anios[$g['temp'] . '/' . ((int) $g['temp'] + 1)] = true;

            // Agrupados por país (nacionales) o confederación (internacionales),
            // para no confundir un Apertura argentino con uno chileno.
            $porGrupo = [];
            foreach ($torneos as $t) {
                $etiqueta = $t->ambito === 'Internacional'
                    ? (trim((string) $t->region) ?: 'Internacional')
                    : (trim((string) $t->pais) ?: 'Argentina');
                $porGrupo[$etiqueta][] = $t;
            }
            ksort($porGrupo);

            $opts = '<option value="">— elegí el torneo —</option>';
            $yaSel = false;
            foreach ($porGrupo as $etiqueta => $lista) {
                $opts .= '<optgroup label="' . e($etiqueta) . '">';
                foreach ($lista as $t) {
                    $sel = '';
                    if (!$yaSel
                        && $this->normalizaTexto($t->nombre) === $this->normalizaTexto($g['nombre'])
                        && isset($anios[(string) $t->year])) {
                        $sel = ' selected';
                        $yaSel = true;
                    }
                    $opts .= '<option value="' . $t->id . '"' . $sel . '>'
                        . e($t->nombre . ' ' . $t->year . ' · ' . $etiqueta) . '</option>';
                }
                $opts .= '</optgroup>';
            }
            $html .= '<tr>'
                . '<td>' . e($g['nombre'] ?: ('#' . $g['comp'])) . '</td>'
                . '<td class="num">' . e($g['temp']) . '</td>'
                . '<td class="num">' . $g['n'] . '</td>'
                . '<td class="num">' . e(substr($g['desde'], 0, 10)) . ' → ' . e(substr($g['hasta'], 0, 10)) . '</td>'
                . '<td>' . e(implode(', ', array_keys($g['equipos'])))
                . ($this->paisEquipo($g) ? ' <span class="id">(' . e($this->paisEquipo($g)) . ')</span>' : '') . '</td>'
                . '<td><form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
                . '<input type="hidden" name="tecnico_id" value="' . $tecnicoId . '">'
                . '<input type="hidden" name="comp" value="' . e($g['comp']) . '">'
                . '<input type="hidden" name="temp" value="' . e($g['temp']) . '">'
                . '<input type="hidden" name="confirmar" value="1">'
                . '<select name="torneo_id" class="s2" data-placeholder="elegí el torneo…">' . $opts . '</select> <button>Aplicar ' . $g['n'] . '</button>'
                . '</form>'
                . '<a class="boton-sec" target="_blank" href="' . e($this->urlCrearTorneo($g)) . '">Crear torneo ↗</a>'
                . '</td></tr>';
        }

        return $this->pagina('Aplicar partidos', $html . '</tbody></table></div>');
    }

    private function aplicarGrupo(Request $request, $tecnicoId, $nombreDT, $volver)
    {
        $comp = (string) $request->get('comp');
        $temp = (string) $request->get('temp');
        $torneoId = (string) $request->get('torneo_id');
        $grupoId = (int) $request->get('grupo_id');

        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'nuevo')
            ->where('competencia_external_id', $comp)->where('temporada', $temp)
            ->orderBy('dia')->get();

        if ($filas->isEmpty()) {
            return $this->pagina('Aplicar', $volver . '<p class="sub">Ese grupo ya no tiene partidos pendientes.</p>');
        }

        $primera = $filas->first();

        // 1. Torneo: tiene que existir. Acá no se crea nada.
        {
            if ($torneoId === '' || $torneoId === 'nuevo') {
                $g = ['comp' => $comp, 'temp' => $temp, 'nombre' => $primera->competencia_nombre,
                    'desde' => $primera->dia, 'hasta' => $filas->last()->dia,
                    'equipo_id' => $primera->equipo_id];
                return $this->pagina('Aplicar', $volver
                    . '<h1>Falta elegir el torneo</h1>'
                    . '<p class="sub">No se crea ningún torneo automáticamente. Creá el torneo con el alta de siempre '
                    . '—se abre con los datos ya cargados— y después volvé, refrescá y elegilo en la lista.</p>'
                    . '<p><a class="boton" target="_blank" href="' . e($this->urlCrearTorneo($g)) . '">Crear torneo ↗</a> '
                    . '<a class="boton-sec" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Volver a elegir</a></p>');
            }

            $torneo = \App\Torneo::find((int) $torneoId);
            if (!$torneo) return $this->pagina('Aplicar', $volver . '<p class="err">No existe ese torneo.</p>');

            $grupos = \App\Grupo::where('torneo_id', $torneo->id)->orderBy('id')->get();
            if ($grupos->isEmpty()) {
                $grupo = new \App\Grupo();
                $grupo->forceFill(['nombre' => 'Único', 'torneo_id' => $torneo->id, 'equipos' => 0])->save();
                $grupoId = $grupo->id;
            } elseif (!$grupoId) {
                if ($grupos->count() === 1) {
                    $grupoId = $grupos->first()->id;
                } else {
                    // Pedir el grupo
                    $opts = '';
                    foreach ($grupos as $gr) $opts .= '<option value="' . $gr->id . '">' . e($gr->nombre) . '</option>';
                    return $this->pagina('Aplicar', $volver
                        . '<h1>¿En qué grupo?</h1><p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' tiene ' . $grupos->count() . ' grupos.</p>'
                        . '<form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
                        . '<input type="hidden" name="tecnico_id" value="' . (int) $tecnicoId . '">'
                        . '<input type="hidden" name="comp" value="' . e($comp) . '">'
                        . '<input type="hidden" name="temp" value="' . e($temp) . '">'
                        . '<input type="hidden" name="torneo_id" value="' . (int) $torneo->id . '">'
                        . '<input type="hidden" name="confirmar" value="1">'
                        . '<select name="grupo_id">' . $opts . '</select> <button>Aplicar ' . $filas->count() . '</button></form>');
                }
            }
        }

        // 2. Partidos
        $creados = 0; $errores = []; $detalle = '';
        foreach ($filas as $r) {
            try {
                $numero = $r->ronda !== null && $r->ronda !== '' ? $r->ronda : 'Importado';
                $fecha = \App\Fecha::where('grupo_id', $grupoId)->where('numero', $numero)->first();
                if (!$fecha) {
                    $fecha = new \App\Fecha();
                    $fecha->forceFill([
                        'numero'     => $numero,
                        'grupo_id'   => $grupoId,
                        'orden'      => is_numeric($numero) ? (int) $numero : 999,
                        'url_nombre' => Str::slug('fecha-' . $numero),
                    ])->save();
                }

                // La localía y el resultado se recalculan SIEMPRE desde el JSON crudo:
                // las columnas pueden haberse guardado con una lógica vieja.
                $datos = $this->datosPartido($r);
                if ($datos['local'] === null) {
                    $errores[] = 'Sin localía: ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ' (' . substr($r->dia, 0, 10) . ')';
                    continue;
                }
                $local     = $datos['local'];
                $equipolId = $local ? (int) $r->equipo_id : (int) $r->rival_id;
                $equipovId = $local ? (int) $r->rival_id : (int) $r->equipo_id;
                $golesl    = $local ? $datos['gf'] : $datos['gc'];
                $golesv    = $local ? $datos['gc'] : $datos['gf'];

                if (!$equipolId || !$equipovId) {
                    $errores[] = 'Sin equipos resueltos: ' . $r->club_nombre . ' vs ' . $r->rival_nombre;
                    continue;
                }

                // ¿Ya hay un partido de esos equipos en esa fecha? (índice único de partidos)
                $ya = \App\Partido::where('fecha_id', $fecha->id)
                    ->where(function ($q) use ($equipolId, $equipovId) {
                        $q->where('equipol_id', $equipolId)->orWhere('equipov_id', $equipolId)
                            ->orWhere('equipol_id', $equipovId)->orWhere('equipov_id', $equipovId);
                    })->first();

                if ($ya) {
                    $errores[] = 'Choque en la fecha «' . $numero . '»: ya hay un partido de ' . $r->club_nombre
                        . ' ahí (partido #' . $ya->id . '). Ese quedó sin crear.';
                    continue;
                }

                $partido = new \App\Partido();
                $partido->forceFill([
                    'fecha_id'   => $fecha->id,
                    'dia'        => $r->dia,
                    'equipol_id' => $equipolId,
                    'equipov_id' => $equipovId,
                    'golesl'     => $golesl,
                    'golesv'     => $golesv,
                ])->save();

                DB::table('partido_tecnicos')->insert([
                    'partido_id' => $partido->id,
                    'equipo_id'  => (int) $r->equipo_id,
                    'tecnico_id' => (int) $tecnicoId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                DB::table('import_partidos')->where('id', $r->id)
                    ->update(['estado' => 'aplicado', 'partido_id' => $partido->id, 'motivo' => null, 'updated_at' => now()]);

                // Ojo: local y visitante son los que corresponden por venue, NO
                // "club dirigido vs rival". Imprimir el club siempre a la izquierda
                // hacía parecer que todos los partidos eran de local.
                $detalle .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td><td class="num">' . e($numero) . '</td>'
                    . '<td>' . e($this->nombreEquipo($equipolId)) . '</td>'
                    . '<td class="num">' . $golesl . ':' . $golesv . '</td>'
                    . '<td>' . e($this->nombreEquipo($equipovId)) . '</td>'
                    . '<td class="num">' . ($local ? 'L' : 'V') . '</td>'
                    . '<td class="num">#' . $partido->id . '</td></tr>';
                $creados++;
            } catch (\Throwable $ex) {
                $errores[] = 'Error en ' . $r->club_nombre . ' vs ' . $r->rival_nombre . ': ' . $ex->getMessage();
                Log::error('aplicarGrupo: ' . $ex->getMessage());
            }
        }

        // Contar equipos del grupo, para que el torneo no quede en 0
        $this->recontarEquipos($grupoId);

        $html = $volver . '<h1>Aplicados ' . $creados . ' partidos</h1>'
            . '<p class="sub">' . e($torneo->nombre . ' ' . $torneo->year) . ' · grupo #' . (int) $grupoId
            . ($torneo->parcial ? ' · <b>torneo parcial</b>' : '') . '</p>';

        if (!empty($errores)) {
            $html .= '<p class="err-box"><b>' . count($errores) . ' quedaron sin crear:</b><br>' . e(implode(' — ', $errores)) . '</p>';
        }
        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Fecha nº</th><th>Local</th><th>Res.</th><th>Visitante</th><th>El DT jugó de</th><th>Partido</th></tr></thead><tbody>'
                . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Seguir con el resto →</a></p>';

        return $this->pagina('Aplicar partidos', $html);
    }

    /** País del equipo dirigido en ese grupo (para saber de dónde es el torneo). */
    private function paisEquipo(array $g)
    {
        if (empty($g['equipo_id'])) return null;
        $e = \App\Equipo::select('pais')->find($g['equipo_id']);
        return $e ? trim((string) $e->pais) : null;
    }

    /** URL del alta de torneos con los datos del grupo ya cargados en los inputs. */
    private function urlCrearTorneo(array $g)
    {
        $nombre = $g['nombre'] ?: ('Competencia ' . $g['comp']);
        list($tipo, $ambito) = $this->clasificarCompetencia($nombre);

        // El año del torneo es el de los partidos, no la temporada de Transfermarkt.
        $anio = !empty($g['desde']) ? substr($g['desde'], 0, 4) : (string) $g['temp'];
        $anioFin = !empty($g['hasta']) ? substr($g['hasta'], 0, 4) : $anio;
        if ($anioFin !== $anio) $anio = $anio . '/' . substr($anioFin, -2);

        $params = [
            'nombre'     => $nombre,
            'year'       => $anio,
            'tipo'       => $tipo,
            'ambito'     => $ambito,
            'grupos'     => 1,
            'url_nombre' => Str::slug($nombre . '-' . $anio),
        ];

        if ($ambito === 'Internacional') {
            $params['region'] = $this->confederacion($nombre);
        } else {
            // Nacional: el país sale del equipo dirigido.
            $pais = 'Argentina';
            if (!empty($g['equipo_id'])) {
                $e = \App\Equipo::select('pais')->find($g['equipo_id']);
                if ($e && trim((string) $e->pais) !== '') $pais = $e->pais;
            }
            $params['pais'] = $pais;
        }

        return route('torneos.create', $params);
    }

    /** Confederación probable a partir del nombre de la competencia. */
    private function confederacion($nombre)
    {
        $n = $this->normalizaTexto($nombre);
        $mapa = [
            'Conmebol' => ['libertadores', 'sudamericana', 'recopa', 'merconorte', 'mercosur', 'conmebol'],
            'FIFA'     => ['intercontinental', 'mundial de clubes', 'club world', 'fifa'],
            'UEFA'     => ['champions', 'europa league', 'uefa', 'conference', 'supercopa de europa'],
            'Concacaf' => ['concacaf', 'concachampions'],
        ];
        foreach ($mapa as $conf => $claves) {
            foreach ($claves as $k) {
                if (strpos($n, $k) !== false) return $conf;
            }
        }
        return '';
    }

    /**
     * Localía y goles de una fila del staging, recalculados desde el JSON crudo.
     * Si no hay payload, cae a las columnas guardadas.
     */
    private function datosPartido($r)
    {
        $g = $r->payload ? json_decode($r->payload, true) : null;
        if (is_array($g) && !empty($g)) {
            $f = $this->normalizar($g, $r->coach_external_id);
            return ['local' => $f['local'], 'gf' => (int) $f['goles_favor'], 'gc' => (int) $f['goles_contra']];
        }
        return [
            'local' => $r->local === null ? null : ((int) $r->local === 1),
            'gf'    => (int) $r->goles_favor,
            'gc'    => (int) $r->goles_contra,
        ];
    }

    /**
     * Repara partidos ya aplicados cuya localía se guardó al revés.
     * Recalcula desde el JSON y da vuelta local/visitante y el resultado.
     */
    private function arreglarLocalia($tecnicoId)
    {
        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'aplicado')
            ->whereNotNull('partido_id')->get();

        $corregidos = 0; $revisados = 0; $detalle = '';
        $sinPayload = 0; $payloadRoto = 0; $sinLocalia = 0; $diag = '';

        foreach ($filas as $r) {
            $revisados++;

            if (empty($r->payload)) {
                $sinPayload++;
            } elseif (!is_array(json_decode($r->payload, true))) {
                $payloadRoto++;
            }

            $datos = $this->datosPartido($r);

            if (count(explode('<tr>', $diag)) <= 6) {
                $p = \App\Partido::find($r->partido_id);
                $diag .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td>'
                    . '<td>' . e($r->club_nombre) . ' vs ' . e($r->rival_nombre) . '</td>'
                    . '<td class="num">' . ($datos['local'] === null ? '?' : ($datos['local'] ? 'L' : 'V'))
                    . ' ' . (int) $datos['gf'] . ':' . (int) $datos['gc'] . '</td>'
                    . '<td class="num">' . (empty($r->payload) ? 'sin payload' : (is_array(json_decode($r->payload, true)) ? 'ok' : 'roto')) . '</td>'
                    . '<td class="num">' . ($r->local === null ? 'null' : (int) $r->local) . '</td>'
                    . '<td>' . ($p ? e($this->nombreEquipo($p->equipol_id) . ' ' . $p->golesl . ':' . $p->golesv . ' ' . $this->nombreEquipo($p->equipov_id)) : 'sin partido') . '</td>'
                    . '</tr>';
            }

            if ($datos['local'] === null) { $sinLocalia++; continue; }
            if (!$r->equipo_id || !$r->rival_id) continue;

            $partido = \App\Partido::find($r->partido_id);
            if (!$partido) continue;

            $equipolId = $datos['local'] ? (int) $r->equipo_id : (int) $r->rival_id;
            $equipovId = $datos['local'] ? (int) $r->rival_id : (int) $r->equipo_id;
            $golesl    = $datos['local'] ? $datos['gf'] : $datos['gc'];
            $golesv    = $datos['local'] ? $datos['gc'] : $datos['gf'];

            if ((int) $partido->equipol_id === $equipolId && (int) $partido->equipov_id === $equipovId
                && (int) $partido->golesl === $golesl && (int) $partido->golesv === $golesv) {
                continue;   // ya estaba bien
            }

            $antes = $this->nombreEquipo($partido->equipol_id) . ' ' . $partido->golesl . ':' . $partido->golesv
                . ' ' . $this->nombreEquipo($partido->equipov_id);

            $partido->forceFill([
                'equipol_id' => $equipolId,
                'equipov_id' => $equipovId,
                'golesl'     => $golesl,
                'golesv'     => $golesv,
            ])->save();

            DB::table('import_partidos')->where('id', $r->id)
                ->update(['local' => $datos['local'] ? 1 : 0, 'updated_at' => now()]);

            $detalle .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td><td>' . e($antes) . '</td>'
                . '<td><b>' . e($this->nombreEquipo($equipolId) . ' ' . $golesl . ':' . $golesv . ' ' . $this->nombreEquipo($equipovId)) . '</b></td>'
                . '<td class="num">#' . $partido->id . '</td></tr>';
            $corregidos++;
        }

        $html = '<h1>Localía revisada</h1>'
            . '<p class="ok-box">Revisé ' . $revisados . ' partidos ya aplicados de este DT. '
            . ($corregidos ? ('Corregí <b>' . $corregidos . '</b>.') : 'No hubo nada que corregir.') . '</p>';

        if ($sinPayload || $payloadRoto || $sinLocalia) {
            $html .= '<p class="err-box">Ojo: ' . $sinPayload . ' sin payload · ' . $payloadRoto
                . ' con payload ilegible · ' . $sinLocalia . ' sin localía. '
                . 'En esos casos no puedo recalcular nada y por eso quedan como están.</p>';
        }

        if ($diag) {
            $html .= '<h2>Qué está comparando</h2>'
                . '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Partido en TM</th>'
                . '<th>Recalculado</th><th>Payload</th><th>Columna local</th><th>Partido en tu base</th>'
                . '</tr></thead><tbody>' . $diag . '</tbody></table></div>';
        }

        if ($detalle) {
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Estaba</th><th>Quedó</th><th>Partido</th></tr></thead><tbody>'
                . $detalle . '</tbody></table></div>';
        }
        return $html;
    }

    /** Agrega el partido_tecnico en partidos que ya estaban cargados sin este DT. */
    private function completarTecnicos($tecnicoId)
    {
        $filas = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'duplicado')
            ->where('motivo', 'like', '%falta el DT%')->get();

        $n = 0;
        foreach ($filas as $r) {
            if (!$r->partido_id || !$r->equipo_id) continue;
            $existe = DB::table('partido_tecnicos')
                ->where('partido_id', $r->partido_id)->where('equipo_id', $r->equipo_id)->exists();
            if ($existe) continue;
            DB::table('partido_tecnicos')->insert([
                'partido_id' => (int) $r->partido_id,
                'equipo_id'  => (int) $r->equipo_id,
                'tecnico_id' => (int) $tecnicoId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('import_partidos')->where('id', $r->id)->update(['motivo' => 'ya cargado', 'updated_at' => now()]);
            $n++;
        }
        return $n;
    }

    private function recontarEquipos($grupoId)
    {
        $ids = DB::table('partidos')
            ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
            ->where('fechas.grupo_id', $grupoId)
            ->select('partidos.equipol_id AS a', 'partidos.equipov_id AS b')->get();
        $set = [];
        foreach ($ids as $r) { $set[$r->a] = true; $set[$r->b] = true; }
        unset($set[null]);
        DB::table('grupos')->where('id', $grupoId)->update(['equipos' => count($set)]);
    }

    private function clasificarCompetencia($nombre)
    {
        $n = mb_strtolower($this->normalizaTexto($nombre));
        $inter = ['libertadores', 'sudamericana', 'recopa', 'champions', 'mundial', 'intercontinental',
            'concacaf', 'club world', 'europa league', 'conference', 'merconorte', 'mercosur'];
        $ambito = 'Nacional';
        foreach ($inter as $k) if (strpos($n, $k) !== false) { $ambito = 'Internacional'; break; }
        $tipo = (strpos($n, 'copa') !== false || strpos($n, 'cup') !== false || $ambito === 'Internacional') ? 'Copa' : 'Liga';
        return [$tipo, $ambito];
    }

    private function normalizaTexto($s)
    {
        $c = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $s);
        return trim(mb_strtolower($c === false ? (string) $s : $c));
    }

    // ═══════════════════════════ FUENTE / CLASIFICACIÓN ═══════════════════════════

    /**
     * Reconstruye las filas desde import_partidos, sin tocar Transfermarkt.
     * Se usa después de mapear un club o de crear un equipo: los datos del DT
     * ya los bajamos una vez, no hace falta gastar otra llamada.
     */
    private function filasDesdeStaging($tecnicoId)
    {
        $filas = [];
        $rows = DB::table('import_partidos')->where('tecnico_id', $tecnicoId)->orderBy('dia', 'desc')->get();
        foreach ($rows as $r) {
            // Si tenemos el JSON crudo, lo volvemos a interpretar: así los arreglos
            // de lógica (localía, fechas, etc.) valen también para lo ya guardado,
            // sin tener que bajar todo de nuevo.
            $g = $r->payload ? json_decode($r->payload, true) : null;
            if (is_array($g) && !empty($g)) {
                $f = $this->normalizar($g, $r->coach_external_id);
                // Los nombres ya resueltos se conservan: no volvemos a pedirlos a tmapi.
                if ($r->competencia_nombre) $f['competencia_nombre'] = $r->competencia_nombre;
                if ($r->club_nombre)        $f['club_nombre']        = $r->club_nombre;
                if ($r->rival_nombre)       $f['rival_nombre']       = $r->rival_nombre;
                $f['aplicado'] = $r->estado === 'aplicado';
                $f['partido_aplicado'] = $r->partido_id;
                $filas[] = $f;
                continue;
            }

            $filas[] = [
                'external_id'             => $r->external_id,
                'competencia_external_id' => $r->competencia_external_id,
                'competencia_nombre'      => $r->competencia_nombre,
                'temporada'               => $r->temporada,
                'ronda'                   => $r->ronda,
                'arbitro_external_id'     => null,
                'club_external_id'        => $r->club_external_id,
                'club_nombre'             => $r->club_nombre,
                'rival_external_id'       => $r->rival_external_id,
                'rival_nombre'            => $r->rival_nombre,
                'local'                   => $r->local === null ? null : ((int) $r->local === 1),
                'dia'                     => $r->dia,
                'goles_favor'             => $r->goles_favor === null ? null : (int) $r->goles_favor,
                'goles_contra'            => $r->goles_contra === null ? null : (int) $r->goles_contra,
                'anio'                    => $r->dia ? substr($r->dia, 0, 4) : null,
                'equipo_id'               => null,
                'rival_id'                => null,
                'rival_real_id'           => null,
                'partido_id'              => null,
                'estado'                  => $r->estado === 'aplicado' ? 'aplicado' : 'nuevo',
                'motivo'                  => null,
                'payload'                 => $r->payload,
                'aplicado'                => $r->estado === 'aplicado',
                'partido_aplicado'        => $r->partido_id,
            ];
        }
        return $filas;
    }

    private function traerPartidos($coachId)
    {
        $resp = HttpHelper::getJson(self::TMAPI . "/coach/{$coachId}/performance-game");
        if (is_array($resp)) {
            if (isset($resp['data']['performance']) && is_array($resp['data']['performance']) && !empty($resp['data']['performance'])) {
                return $resp['data']['performance'];
            }
            if (isset($resp['performance']) && is_array($resp['performance']) && !empty($resp['performance'])) {
                return $resp['performance'];
            }
        }
        $err = HttpHelper::getLastJsonError();
        return 'tmapi no devolvió partidos para el coach ' . e($coachId) . '. Causa: '
            . e(is_array($err) ? json_encode($err, JSON_UNESCAPED_UNICODE) : 'sin detalle');
    }

    private function completarNombres(array $filas)
    {
        $compIds = []; $clubIds = [];
        foreach ($filas as $f) {
            if ($f['competencia_external_id']) $compIds[$f['competencia_external_id']] = true;
            if ($f['club_external_id'])        $clubIds[$f['club_external_id']] = true;
            if ($f['rival_external_id'])       $clubIds[$f['rival_external_id']] = true;
        }
        $compNames = $this->resolverNombres(self::TMAPI . '/competitions', array_keys($compIds));
        $clubNames = $this->resolverNombres(self::TMAPI . '/clubs', array_keys($clubIds));

        foreach ($filas as $i => $f) {
            if ($f['competencia_external_id'] && isset($compNames[$f['competencia_external_id']])) {
                $filas[$i]['competencia_nombre'] = $compNames[$f['competencia_external_id']];
            }
            if ($f['club_external_id'] && isset($clubNames[$f['club_external_id']])) {
                $filas[$i]['club_nombre'] = $clubNames[$f['club_external_id']];
            }
            if ($f['rival_external_id'] && isset($clubNames[$f['rival_external_id']])) {
                $filas[$i]['rival_nombre'] = $clubNames[$f['rival_external_id']];
            }
        }
        return $filas;
    }

    private function clasificar(array $filas, $desde)
    {
        $mapaTm = $this->mapaTm();
        $mapaNombres = $this->mapaNombres();

        foreach ($filas as $i => $f) {
            $filas[$i]['equipo_id'] = null;
            $filas[$i]['rival_id'] = null;
            $filas[$i]['rival_real_id'] = null;
            $filas[$i]['partido_id'] = null;
            $filas[$i]['motivo'] = null;

            if ($f['temporada'] !== null && (int) $f['temporada'] < $desde) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'temporada < ' . $desde;
                continue;
            }
            if ($f['goles_favor'] === null || $f['goles_contra'] === null) {
                $filas[$i]['estado'] = 'excluido';
                $filas[$i]['motivo'] = 'sin resultado';
                continue;
            }

            $equipoId = $this->resolverClub($f['club_external_id'], $f['club_nombre'], $mapaTm, $mapaNombres);
            $rivalId  = $this->resolverClub($f['rival_external_id'], $f['rival_nombre'], $mapaTm, $mapaNombres);
            $filas[$i]['equipo_id'] = $equipoId;
            $filas[$i]['rival_id']  = $rivalId;

            if (!$f['dia']) {
                $filas[$i]['estado'] = 'conflicto';
                $filas[$i]['motivo'] = 'sin fecha';
                continue;
            }

            $partido = ($equipoId && $rivalId) ? $this->buscarPartido($equipoId, $rivalId, $f['dia']) : null;
            $corrido = null;

            // Partidos postergados: TM guarda la fecha original y vos la fecha real.
            // Se buscan por par de equipos + localía + resultado exacto en una ventana amplia.
            if (!$partido && $equipoId && $rivalId) {
                $partido = $this->buscarPartidoAplazado($equipoId, $rivalId, $f['dia'], $f['local'],
                    (int) $f['goles_favor'], (int) $f['goles_contra'], $f['ronda']);
                if ($partido) {
                    $corrido = (int) round((strtotime(substr($partido->dia, 0, 10)) - strtotime(substr($f['dia'], 0, 10))) / 86400);
                }
            }

            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $tieneDt = DB::table('partido_tecnicos')
                    ->where('partido_id', $partido->id)->where('equipo_id', $equipoId)->exists();
                $filas[$i]['motivo'] = $tieneDt ? 'ya cargado' : 'ya cargado, le falta el DT';
                if ($corrido !== null) {
                    $filas[$i]['motivo'] .= ' · fecha corrida ' . ($corrido > 0 ? '+' : '') . $corrido
                        . ' días (tu base: ' . substr($partido->dia, 0, 10) . ')';
                    $filas[$i]['corrido'] = $corrido;
                }
            } elseif (!$equipoId || !$rivalId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$equipoId) $faltan[] = 'club «' . $f['club_nombre'] . '»';
                if (!$rivalId)  $faltan[] = 'rival «' . $f['rival_nombre'] . '»';
                $filas[$i]['motivo'] = 'sin mapear: ' . implode(' / ', $faltan);
            } else {
                // Antes de darlo por nuevo: ¿ese día el club ya jugó contra OTRO equipo?
                // Si sí, el partido existe y el que está mal es el mapeo del rival.
                $otro = $this->partidoDelDia($equipoId, $f['dia']);
                if ($otro) {
                    $rivalReal = ((int) $otro->equipol_id === (int) $equipoId) ? $otro->equipov_id : $otro->equipol_id;
                    $filas[$i]['estado'] = 'conflicto';
                    $filas[$i]['partido_id'] = $otro->id;
                    $filas[$i]['rival_real_id'] = $rivalReal;
                    $filas[$i]['motivo'] = 'ese día ya tenés el partido #' . $otro->id . ' contra '
                        . $this->nombreEquipo($rivalReal) . ' (#' . $rivalReal . '), no contra «' . $f['rival_nombre']
                        . '» (#' . $rivalId . '): el mapeo del rival está mal';
                } elseif ($f['local'] === null) {
                    // Sin localía no se puede crear: quedaría el resultado dado vuelta.
                    $filas[$i]['estado'] = 'conflicto';
                    $filas[$i]['motivo'] = 'no se pudo determinar si fue local o visitante';
                } else {
                    $filas[$i]['estado'] = 'nuevo';
                }
            }
        }
        return $filas;
    }

    /**
     * Busca un partido postergado: mismo par de equipos, misma localía y el
     * MISMO resultado, dentro de una ventana amplia (±150 días).
     *
     * Si hay más de un candidato, desempata por el número de fecha. Si sigue
     * habiendo empate, no devuelve nada: mejor que quede como conflicto.
     */
    private function buscarPartidoAplazado($equipoId, $rivalId, $dia, $local, $gf, $gc, $ronda)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -150 days'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +150 days'));

        $q = \App\Partido::whereBetween('dia', [$d0, $d1]);
        if ($local === true) {
            $q->where('equipol_id', $equipoId)->where('equipov_id', $rivalId)
                ->where('golesl', $gf)->where('golesv', $gc);
        } elseif ($local === false) {
            $q->where('equipol_id', $rivalId)->where('equipov_id', $equipoId)
                ->where('golesl', $gc)->where('golesv', $gf);
        } else {
            // Sin localía conocida: cualquiera de los dos órdenes, con el resultado que corresponda.
            $q->where(function ($w) use ($equipoId, $rivalId, $gf, $gc) {
                $w->where(function ($x) use ($equipoId, $rivalId, $gf, $gc) {
                    $x->where('equipol_id', $equipoId)->where('equipov_id', $rivalId)
                        ->where('golesl', $gf)->where('golesv', $gc);
                })->orWhere(function ($x) use ($equipoId, $rivalId, $gf, $gc) {
                    $x->where('equipol_id', $rivalId)->where('equipov_id', $equipoId)
                        ->where('golesl', $gc)->where('golesv', $gf);
                });
            });
        }
        $cands = $q->get();

        if ($cands->count() === 1) return $cands->first();
        if ($cands->isEmpty()) return null;

        // Desempate por número de fecha
        if ($ronda !== null && $ronda !== '') {
            $porRonda = $cands->filter(function ($p) use ($ronda) {
                $fecha = \App\Fecha::find($p->fecha_id);
                if (!$fecha) return false;
                return (int) preg_replace('/\D/', '', (string) $fecha->numero) === (int) $ronda;
            });
            if ($porRonda->count() === 1) return $porRonda->first();
        }
        return null;
    }

    /** Cualquier partido de ese equipo ese día, sin importar el rival. */
    private function partidoDelDia($equipoId, $dia)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -1 day'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +1 day'));
        return \App\Partido::whereBetween('dia', [$d0, $d1])
            ->where(function ($q) use ($equipoId) {
                $q->where('equipol_id', $equipoId)->orWhere('equipov_id', $equipoId);
            })->first();
    }

    private function nombreEquipo($id)
    {
        static $cache = [];
        if (!$id) return '?';
        if (!isset($cache[$id])) {
            $e = \App\Equipo::select('nombre')->find($id);
            $cache[$id] = $e ? $e->nombre : ('#' . $id);
        }
        return $cache[$id];
    }

    private function buscarPartido($equipoId, $rivalId, $dia)
    {
        $d0 = date('Y-m-d 00:00:00', strtotime($dia . ' -1 day'));
        $d1 = date('Y-m-d 23:59:59', strtotime($dia . ' +1 day'));
        return \App\Partido::whereBetween('dia', [$d0, $d1])
            ->where(function ($q) use ($equipoId, $rivalId) {
                $q->where(function ($w) use ($equipoId, $rivalId) {
                    $w->where('equipol_id', $equipoId)->where('equipov_id', $rivalId);
                })->orWhere(function ($w) use ($equipoId, $rivalId) {
                    $w->where('equipol_id', $rivalId)->where('equipov_id', $equipoId);
                });
            })->first();
    }

    private function aprenderMapeos(array $filas)
    {
        $mapaTm = $this->mapaTm();
        $aprendidos = [];

        foreach ($filas as $f) {
            if ($f['estado'] === 'excluido' || !$f['dia']) continue;

            if ($f['estado'] === 'duplicado' && $f['equipo_id'] && $f['rival_id']) {
                foreach ([[$f['club_external_id'], $f['equipo_id'], $f['club_nombre']],
                             [$f['rival_external_id'], $f['rival_id'], $f['rival_nombre']]] as $par) {
                    if ($par[0] && !isset($mapaTm[(string) $par[0]])) {
                        $this->guardarMapeo($par[0], $par[1], $par[2], 'nombre');
                        $mapaTm[(string) $par[0]] = $par[1];
                        $aprendidos[] = $par[2] . ' → #' . $par[1];
                    }
                }
                continue;
            }

            if ($f['estado'] !== 'conflicto') continue;
            if ($f['equipo_id'] && $f['rival_id']) continue;

            $conocidoEsClub = (bool) $f['equipo_id'];
            $conocido = $f['equipo_id'] ?: $f['rival_id'];
            $tmDesconocido = $conocidoEsClub ? $f['rival_external_id'] : $f['club_external_id'];
            $nombreDesconocido = $conocidoEsClub ? $f['rival_nombre'] : $f['club_nombre'];
            if (!$conocido || !$tmDesconocido) continue;
            if (isset($mapaTm[(string) $tmDesconocido])) continue;

            $clubEsLocal = (bool) $f['local'];
            $gf = (int) $f['goles_favor'];
            $gc = (int) $f['goles_contra'];

            $d0 = date('Y-m-d 00:00:00', strtotime($f['dia'] . ' -1 day'));
            $d1 = date('Y-m-d 23:59:59', strtotime($f['dia'] . ' +1 day'));
            $cands = \App\Partido::whereBetween('dia', [$d0, $d1])
                ->where(function ($q) use ($conocido) {
                    $q->where('equipol_id', $conocido)->orWhere('equipov_id', $conocido);
                })->get();

            $ok = [];
            foreach ($cands as $p) {
                if ($p->golesl === null || $p->golesv === null) continue;
                if ($conocidoEsClub) {
                    if ($clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) $ok[] = $p->equipov_id;
                    elseif (!$clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) $ok[] = $p->equipol_id;
                } else {
                    if ($clubEsLocal && (int) $p->equipov_id === (int) $conocido
                        && (int) $p->golesl === $gf && (int) $p->golesv === $gc) $ok[] = $p->equipol_id;
                    elseif (!$clubEsLocal && (int) $p->equipol_id === (int) $conocido
                        && (int) $p->golesv === $gf && (int) $p->golesl === $gc) $ok[] = $p->equipov_id;
                }
            }

            $ok = array_values(array_unique(array_filter($ok)));
            if (count($ok) === 1) {
                $this->guardarMapeo($tmDesconocido, $ok[0], $nombreDesconocido, 'inferido');
                $mapaTm[(string) $tmDesconocido] = $ok[0];
                $aprendidos[] = $nombreDesconocido . ' → #' . $ok[0];
            }
        }
        return $aprendidos;
    }

    private function guardarMapeo($tmClubId, $equipoId, $nombre, $origen)
    {
        DB::table('equipo_tm')->updateOrInsert(
            ['tm_club_id' => (string) $tmClubId],
            ['equipo_id' => (int) $equipoId, 'nombre_tm' => $nombre, 'origen' => $origen,
                'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function mapaTm()
    {
        $mapa = [];
        foreach (DB::table('equipo_tm')->select('tm_club_id', 'equipo_id')->get() as $r) {
            $mapa[(string) $r->tm_club_id] = (int) $r->equipo_id;
        }
        return $mapa;
    }

    /**
     * Nombre normalizado -> equipo_id. Si dos equipos comparten la misma clave
     * (pasa con los homónimos), la clave se marca ambigua y no matchea con nadie:
     * mejor un conflicto para resolver a mano que un partido con el rival cambiado.
     */
    private function mapaNombres()
    {
        $mapa = [];
        foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
            foreach ($this->clavesNombre($e->nombre) as $k) {
                if ($k === '') continue;
                if (isset($mapa[$k]) && $mapa[$k] !== $e->id) {
                    $mapa[$k] = null;      // ambigua
                } elseif (!array_key_exists($k, $mapa)) {
                    $mapa[$k] = $e->id;
                }
            }
        }
        return $mapa;
    }

    private function resolverClub($tmId, $nombre, array $mapaTm, array $mapaNombres)
    {
        if ($tmId !== null && isset($mapaTm[(string) $tmId])) return $mapaTm[(string) $tmId];
        foreach ($this->clavesNombre($nombre) as $k) {
            if ($k !== '' && isset($mapaNombres[$k]) && $mapaNombres[$k] !== null) return $mapaNombres[$k];
        }
        return null;
    }

    /**
     * Claves normalizadas de un nombre de club.
     *
     * REGLA IMPORTANTE: si el nombre trae un paréntesis aclaratorio —"Sarmiento (Junín)",
     * "Central Córdoba (SdE)"— ese paréntesis es parte del nombre y NO se descarta.
     * Sin esta regla, "CA Sarmiento (Junín)" matchea contra un "Sarmiento" cualquiera
     * y termina creando partidos con el rival equivocado.
     */
    private function clavesNombre($nombre)
    {
        $nombre = (string) $nombre;
        if (trim($nombre) === '') return [];

        $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($base === false) $base = $nombre;
        $base = mb_strtolower($base);

        // Los paréntesis se aplanan (pasan a ser texto), no se borran.
        $base = str_replace(['(', ')', '.', ','], ' ', $base);

        $claves = [
            $this->soloLetras($base),
            $this->soloLetras($this->quitarPrefijos($base)),
        ];
        return array_values(array_unique(array_filter($claves)));
    }

    private function quitarPrefijos($str)
    {
        $str = preg_replace('/\b(c\.?a\.?|a\.?a\.?|c\.?s\.?|c\.?d\.?|c\.?s\.?d\.?|a\.?c\.?|s\.?c\.?|f\.?c\.?|c\.?f\.?|c\.?b\.?|s\.?a\.?d\.?)\b/u', ' ', $str);
        $str = preg_replace('/\b(club|atletico|atletica|deportivo|deportiva|deportes|asociacion|association|sportivo|sporting|social|futbol|football|de|del|la|el)\b/u', ' ', $str);
        return $str;
    }

    private function soloLetras($str)
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $str);
    }

    private function normalizar(array $g, $coachId)
    {
        $gi = isset($g['gameInformation']) && is_array($g['gameInformation']) ? $g['gameInformation'] : [];
        $ci = isset($g['clubsInformation']) && is_array($g['clubsInformation']) ? $g['clubsInformation'] : [];

        // En performance-game, `club` es SIEMPRE el equipo del DT y `opponent` el rival.
        // La localía no sale del orden: sale del campo `venue` ("home" / "away").
        $club  = isset($ci['club']) ? $ci['club'] : [];
        $rival = isset($ci['opponent']) ? $ci['opponent'] : [];

        // Por las dudas, si el coachId apareciera del lado del rival, damos vuelta.
        if ((string) $this->valor($rival, ['coachId']) === (string) $coachId
            && (string) $this->valor($club, ['coachId']) !== (string) $coachId) {
            $club  = isset($ci['opponent']) ? $ci['opponent'] : [];
            $rival = isset($ci['club']) ? $ci['club'] : [];
        }

        $venue = mb_strtolower(trim((string) $this->valor($club, ['venue'])));
        if ($venue === '') {
            // Si el rival dice dónde jugó, alcanza: es al revés del nuestro.
            $venueRival = mb_strtolower(trim((string) $this->valor($rival, ['venue'])));
            $venue = $venueRival === '' ? '' : ($this->esLocal($venueRival) ? 'away' : 'home');
        }
        $local = $venue === '' ? null : $this->esLocal($venue);

        $fechaRaw = $this->valor($gi, ['date']);
        if (is_array($fechaRaw)) $fechaRaw = $this->valor($fechaRaw, ['dateTimeUTC', 'dateTime', 'date']);
        $dia = null;
        if ($fechaRaw) {
            $ts = strtotime((string) $fechaRaw);
            if ($ts) $dia = date('Y-m-d H:i:s', $ts);
        }

        $temporada = $this->valor($gi, ['seasonId']);
        if ($temporada === null && isset($gi['season']) && is_array($gi['season'])) {
            $temporada = $this->valor($gi['season'], ['id', 'seasonId']);
        }

        $gf = $this->valor($club, ['goalsTotal']);
        $gc = $this->valor($club, ['opponentGoalsTotal']);
        if ($gc === null) $gc = $this->valor($rival, ['goalsTotal']);

        return [
            'external_id'             => $this->texto($this->valor($gi, ['gameId']) ?: $this->valor($g, ['gameId', 'id'])),
            'competencia_external_id' => $this->texto($this->valor($gi, ['competitionId'])),
            'competencia_nombre'      => $this->valor($gi, ['competitionName']),
            'temporada'               => $this->texto($temporada),
            'ronda'                   => $this->texto($this->valor($gi, ['gameDay', 'matchDay', 'round'])),
            'arbitro_external_id'     => $this->texto($this->valor($gi, ['refereeId'])),
            'club_external_id'        => $this->texto($this->valor($club, ['clubId', 'id'])),
            'club_nombre'             => $this->valor($club, ['name', 'clubName']),
            'rival_external_id'       => $this->texto($this->valor($rival, ['clubId', 'id'])),
            'rival_nombre'            => $this->valor($rival, ['name', 'clubName']),
            'local'                   => $local,
            'dia'                     => $dia,
            'goles_favor'             => $gf === null ? null : (int) $gf,
            'goles_contra'            => $gc === null ? null : (int) $gc,
            'anio'                    => $dia ? substr($dia, 0, 4) : null,
            'equipo_id'               => null,
            'rival_id'                => null,
            'rival_real_id'           => null,
            'partido_id'              => null,
            'estado'                  => 'nuevo',
            'motivo'                  => null,
            'payload'                 => json_encode($g, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function persistir(array $f, $coachId, $tecnicoId)
    {
        $clave = ['fuente' => 'transfermarkt', 'external_id' => $f['external_id'], 'tecnico_id' => $tecnicoId ?: null];
        if (!$f['external_id']) {
            $clave = ['fuente' => 'transfermarkt', 'tecnico_id' => $tecnicoId ?: null,
                'club_nombre' => $f['club_nombre'], 'rival_nombre' => $f['rival_nombre'], 'dia' => $f['dia']];
        }

        // Una fila ya aplicada no se pisa… salvo que el partido que había creado
        // ya no exista (lo borraste a mano). En ese caso vuelve a estar disponible.
        $aplicada = DB::table('import_partidos')->where($clave)->where('estado', 'aplicado')->first();
        if ($aplicada) {
            $sigue = $aplicada->partido_id && \App\Partido::where('id', $aplicada->partido_id)->exists();
            if ($sigue) return false;
            DB::table('import_partidos')->where('id', $aplicada->id)
                ->update(['estado' => 'nuevo', 'partido_id' => null, 'motivo' => null, 'updated_at' => now()]);
        }

        DB::table('import_partidos')->updateOrInsert($clave, [
            'coach_external_id'       => $coachId,
            'competencia_external_id' => $f['competencia_external_id'],
            'competencia_nombre'      => $f['competencia_nombre'],
            'temporada'               => $f['temporada'],
            'ronda'                   => $f['ronda'],
            'club_external_id'        => $f['club_external_id'],
            'club_nombre'             => $f['club_nombre'],
            'rival_external_id'       => $f['rival_external_id'],
            'rival_nombre'            => $f['rival_nombre'],
            'local'                   => $f['local'],
            'dia'                     => $f['dia'],
            'goles_favor'             => $f['goles_favor'],
            'goles_contra'            => $f['goles_contra'],
            'equipo_id'               => $f['equipo_id'],
            'rival_id'                => $f['rival_id'],
            'partido_id'              => $f['partido_id'],
            'estado'                  => $f['estado'],
            'motivo'                  => $f['motivo'],
            'payload'                 => $f['payload'],
            'updated_at'              => now(),
            'created_at'              => now(),
        ]);
        return true;
    }

    private function valor($arr, array $claves)
    {
        if (!is_array($arr)) return null;
        foreach ($claves as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '') return $arr[$k];
        }
        return null;
    }

    /** Interpreta el campo venue de Transfermarkt: home / away (y variantes). */
    private function esLocal($venue)
    {
        $v = mb_strtolower(trim((string) $venue));
        if ($v === '') return null;
        if (strpos($v, 'home') !== false || strpos($v, 'local') !== false || $v === 'h' || $v === '1') return true;
        if (strpos($v, 'away') !== false || strpos($v, 'guest') !== false || strpos($v, 'visit') !== false
            || $v === 'a' || $v === '2') return false;
        if (strpos($v, 'neutral') !== false) return true;   // cancha neutral: lo dejamos como local
        return null;
    }

    private function texto($v)
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function resolverNombres($endpoint, array $ids)
    {
        $map = [];
        if (empty($ids)) return $map;
        foreach (array_chunk($ids, 50) as $chunk) {
            $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $chunk));
            $json = HttpHelper::getJson($endpoint . '?' . $qs);
            if (!$json) continue;
            $items = isset($json['data']) ? $json['data'] : $json;
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $id = isset($item['id']) ? $item['id'] : null;
                if ($id === null) continue;
                $name = $this->valor($item, ['name', 'fullName', 'officialName', 'shortName', 'display']);
                if ($name) $map[(string) $id] = trim($name);
            }
        }
        return $map;
    }

    // ═══════════════════════════════ VISTAS ═══════════════════════════════

    private function urlBase(Request $request)
    {
        $q = $request->query();
        unset($q['guardar'], $q['aprender'], $q['estado'], $q['limite'], $q['cache'],
            $q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre']);
        return $request->url() . '?' . http_build_query($q);
    }

    /**
     * Clubes que SÍ están mapeados pero cuyo mapeo contradice un partido ya cargado.
     * Un clic corrige el mapeo apuntándolo al rival que realmente jugó ese día.
     */
    private function bloqueMapeosSospechosos(array $filas, Request $request)
    {
        $mal = [];
        foreach ($filas as $f) {
            if ($f['estado'] !== 'conflicto' || empty($f['rival_real_id'])) continue;
            $k = (string) $f['rival_external_id'];
            if ($k === '') continue;
            if (!isset($mal[$k])) {
                $mal[$k] = ['nombre' => $f['rival_nombre'], 'actual' => $f['rival_id'],
                    'real' => $f['rival_real_id'], 'n' => 0];
            }
            $mal[$k]['n']++;
        }
        if (empty($mal)) return '';

        $out = '<h2 class="err">Mapeos que no cierran <span class="sub">(' . count($mal) . ')</span></h2>'
            . '<p class="sub">Estos clubes están mapeados a un equipo tuyo, pero el partido de esa fecha en tu base '
            . 'es contra otro rival. Casi siempre es un homónimo mal enganchado. Corregilo y el partido pasa a «ya cargado».</p>'
            . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Mapeado hoy a</th>'
            . '<th>Debería ser</th><th>Partidos</th><th></th></tr></thead><tbody>';

        foreach ($mal as $tmId => $d) {
            $q = $request->query();
            unset($q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre']);
            $q['mapear_tm'] = $tmId;
            $q['mapear_nombre'] = $d['nombre'];
            $q['mapear_equipo'] = $d['real'];
            $q['cache'] = 1;
            $href = $request->url() . '?' . http_build_query($q);

            $out .= '<tr class="err"><td>' . e($d['nombre']) . '</td><td class="num">' . e($tmId) . '</td>'
                . '<td>' . e($this->nombreEquipo($d['actual'])) . ' <span class="id">#' . (int) $d['actual'] . '</span></td>'
                . '<td><b>' . e($this->nombreEquipo($d['real'])) . '</b> <span class="id">#' . (int) $d['real'] . '</span></td>'
                . '<td class="num">' . $d['n'] . '</td>'
                . '<td><a class="boton" href="' . e($href) . '">Corregir</a></td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function bloqueClubesSinResolver(array $filas, Request $request)
    {
        $pend = [];
        foreach ($filas as $f) {
            if ($f['estado'] !== 'conflicto') continue;
            foreach ([[$f['club_external_id'], $f['club_nombre'], $f['equipo_id']],
                         [$f['rival_external_id'], $f['rival_nombre'], $f['rival_id']]] as $c) {
                if ($c[2] || !$c[0]) continue;
                $k = (string) $c[0];
                if (!isset($pend[$k])) $pend[$k] = ['nombre' => $c[1], 'n' => 0];
                $pend[$k]['n']++;
            }
        }
        if (empty($pend)) return '<p class="ok-box">No queda ningún club sin mapear.</p>';

        uasort($pend, function ($a, $b) { return $b['n'] <=> $a['n']; });

        $opciones = '<option value=""></option>';
        foreach (\App\Equipo::select('id', 'nombre', 'pais')->orderBy('nombre')->get() as $e) {
            $opciones .= '<option value="' . $e->id . '">' . e($e->nombre)
                . ($e->pais ? ' (' . e($e->pais) . ')' : '') . '</option>';
        }

        $out = '<h2>Clubes sin mapear <span class="sub">(' . count($pend) . ')</span></h2>'
            . '<p class="sub">Elegí el equipo y guardá: queda mapeado por su id de Transfermarkt y no se vuelve a preguntar nunca más. '
            . 'Si el club no existe en tu base, «Crear equipo» lo abre en otra pestaña; cuando volvés acá y refrescás, '
            . 'aparece en la lista <b>sin volver a bajar nada de Transfermarkt</b>.</p>'
            . '<div class="scroll"><table><thead><tr><th>Club en TM</th><th>id TM</th><th>Partidos</th><th>Nuestro equipo</th></tr></thead><tbody>';

        foreach ($pend as $tmId => $d) {
            $out .= '<tr><td>' . e($d['nombre']) . '</td><td class="num">' . e($tmId) . '</td><td class="num">' . $d['n'] . '</td>'
                . '<td><form method="get" action="' . e($request->url()) . '">';
            foreach ($request->query() as $k => $v) {
                if (in_array($k, ['mapear_tm', 'mapear_equipo', 'mapear_nombre', 'guardar', 'aprender'], true)) continue;
                if (is_array($v)) continue;
                $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
            }
            $out .= '<input type="hidden" name="mapear_tm" value="' . e($tmId) . '">'
                . '<input type="hidden" name="mapear_nombre" value="' . e($d['nombre']) . '">'
                . '<input type="hidden" name="cache" value="1">'
                . '<select name="mapear_equipo" class="s2" data-placeholder="buscar equipo…">' . $opciones . '</select>'
                . ' <button>Mapear</button></form>'
                . '<a class="boton-sec" href="' . e(route('equipos.create')) . '" target="_blank">Crear equipo ↗</a>'
                . '</td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function diagnosticar(array $game)
    {
        $lineas = [];
        $lineas[] = '<strong>Claves del partido:</strong> <code>' . e(implode(', ', array_keys($game))) . '</code>';
        foreach (['gameInformation', 'clubsInformation', 'statistics'] as $sub) {
            if (isset($game[$sub]) && is_array($game[$sub])) {
                $lineas[] = '<strong>' . $sub . ':</strong> <code>' . e(implode(', ', array_keys($game[$sub]))) . '</code>';
            }
        }
        $ci = isset($game['clubsInformation']) ? $game['clubsInformation'] : [];
        $lineas[] = '<strong>venue del primer partido:</strong> club = <code>'
            . e((string) $this->valor(isset($ci['club']) ? $ci['club'] : [], ['venue'])) . '</code> · opponent = <code>'
            . e((string) $this->valor(isset($ci['opponent']) ? $ci['opponent'] : [], ['venue'])) . '</code>'
            . ' <span class="sub">(de acá sale local/visitante)</span>';

        $lineas[] = '<details><summary>JSON crudo del primer partido</summary><pre>'
            . e(json_encode($game, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';
        return '<div class="diag">' . implode('<br>', $lineas) . '</div>';
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

    private function tabla(array $filas, $limite, $filtro = '')
    {
        $out = '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Año</th><th>Temp. TM</th><th>Fecha nº</th><th>Club</th><th></th><th>Rival</th>'
            . '<th>Res.</th><th>gameId</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
        $n = 0;
        foreach ($filas as $f) {
            if ($filtro !== '' && $f['estado'] !== $filtro) continue;
            if ($n++ >= $limite) break;
            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'conflicto' ? 'err' : ($f['estado'] === 'excluido' ? 'gris' : ''));
            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id'])) . '</td>'
                . '<td class="num"><b>' . e(isset($f['anio']) ? $f['anio'] : '') . '</b></td>'
                . '<td class="num gris">' . e($f['temporada']) . '</td>'
                . '<td class="num">' . e($f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . ($f['local'] === null ? '<span class="err">?</span>' : ($f['local'] ? 'L' : 'V')) . '</td>'
                . '<td>' . e($f['rival_nombre']) . ($f['rival_id'] ? ' <span class="id">#' . $f['rival_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . e($f['goles_favor']) . ':' . e($f['goles_contra']) . '</td>'
                . '<td class="num">' . e($f['external_id'] ?: '—') . '</td>'
                . '<td>' . e($f['estado']) . '</td>'
                . '<td>' . e($f['motivo']) . ($f['partido_id'] ? ' <span class="id">partido #' . $f['partido_id'] . '</span>' : '') . '</td>'
                . '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function pagina($titulo, $cuerpo)
    {
        $css = '
            body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px 28px;color:#1a1f1c;background:#f7f8f6}
            h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:28px 0 8px}
            .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
            .acciones{margin:12px 0} .acciones a{color:#15714e;margin-right:2px}
            a{color:#15714e}
            a.boton,.acciones a.boton{display:inline-block;background:#15714e;color:#fff;padding:5px 12px;text-decoration:none;font-weight:600}
            a.boton:hover{background:#0f5a3d}
            a.boton-sec{display:inline-block;margin-left:8px;padding:4px 10px;border:1px solid #c7cec7;background:#eef1ec;color:#15714e;text-decoration:none;font-size:12px}
            a.boton-sec:hover{background:#e2e8e1}
            .diag{background:#fff;border:1px solid #dde2dd;padding:14px 16px;font-size:13px}
            .diag code{background:#eef1ec;padding:1px 5px;font-size:12px}
            pre{font-size:11px;max-height:340px;overflow:auto;background:#f0f3ef;padding:10px}
            .cards{display:flex;flex-wrap:wrap;gap:1px;background:#dde2dd;border:1px solid #dde2dd;margin:14px 0}
            .card{background:#fff;padding:10px 16px;min-width:110px}
            .card b{display:block;font-size:20px} .card span{font-size:11px;color:#6b7a73;text-transform:uppercase;letter-spacing:.06em}
            .card.ok b{color:#15714e} .card.err b{color:#9c3529} .card.warn b{color:#8a5d00} .card.gris b{color:#9aa69f}
            .ok-box{background:#ddede4;border:1px solid #15714e;padding:10px 14px}
            .err-box{background:#f6e2de;border:1px solid #9c3529;padding:10px 14px}
            .err{color:#9c3529} .ok{color:#15714e} .warn{color:#8a5d00}
            .scroll{overflow:auto;border:1px solid #dde2dd;background:#fff;max-height:70vh}
            table{border-collapse:collapse;width:100%;font-size:12.5px}
            th,td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap}
            thead th{position:sticky;top:0;background:#eef1ec;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
            td.num{font-variant-numeric:tabular-nums}
            tr.gris{color:#9aa69f}
            .id{color:#9aa69f;font-size:11px}
            input,button,select{font:13px inherit;padding:3px 6px;border:1px solid #c7cec7;background:#fff}
            button{cursor:pointer;background:#eef1ec}
            details summary{cursor:pointer;color:#15714e;margin-top:6px}
        ';
        $assets = '<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet">'
            . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script>'
            . '<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>';

        $init = '<script>$(function(){$(".s2").each(function(){'
            . '$(this).select2({width:"260px",placeholder:$(this).data("placeholder")||"",allowClear:true});'
            . '});});</script>';

        $cssExtra = '
            .select2-container{vertical-align:middle}
            .select2-container--default .select2-selection--single{border-color:#c7cec7;border-radius:0;height:28px}
            .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:26px;font-size:13px}
            .select2-container--default .select2-selection--single .select2-selection__arrow{height:26px}
        ';

        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title>'
            . $assets . '<style>' . $css . $cssExtra . '</style>' . $cuerpo . $init);
    }
}
