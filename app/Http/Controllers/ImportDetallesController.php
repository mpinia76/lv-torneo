<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TmDetallePartido;

/**
 * Segunda etapa de la carga: el DETALLE de cada partido.
 *
 * `ImportPartidosController` crea el partido (torneo → grupo → fecha → partido).
 * Acá se le agrega lo que pasó adentro: alineaciones, goles, tarjetas, cambios
 * y árbitros, bajándolo de Transfermarkt.
 *
 *   index()   -> partidos ya cargados que todavía no tienen alineación
 *   ver()     -> vista previa de UN partido, sin escribir nada
 *   bajar()   -> baja y guarda UN partido
 *   tanda()   -> baja y guarda los N primeros de la lista
 *   sembrar() -> llena jugador_tm con los jugadores que ya tienen transfermarkt_url
 *   revisar() -> jugadores creados automáticamente que falta repasar
 *
 * Cada partido cuesta 1 llamada a la API, más 1 cada 50 jugadores nuevos.
 * Los jugadores se resuelven una sola vez en la vida.
 */
class ImportDetallesController extends Controller
{
    // ═══════════════════════════════ LISTA ═══════════════════════════════

    public function index(Request $request)
    {
        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $conDetalle = (string) $request->get('con_detalle', '0') === '1';

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')
            ->whereNotNull('external_id')
            ->where('estado', 'aplicado');
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);

        $filas = $q->orderBy('dia', 'desc')->limit(2000)->get();

        // ¿Cuáles ya tienen alineación? Una sola consulta para todos.
        $ids = [];
        foreach ($filas as $f) $ids[] = (int) $f->partido_id;
        $conAlineacion = [];
        if (!empty($ids)) {
            foreach (DB::table('alineacions')->whereIn('partido_id', $ids)
                         ->select('partido_id', DB::raw('COUNT(*) AS n'))
                         ->groupBy('partido_id')->get() as $r) {
                $conAlineacion[(int) $r->partido_id] = (int) $r->n;
            }
        }

        $pendientes = [];
        $listos = 0;
        foreach ($filas as $f) {
            if (isset($conAlineacion[(int) $f->partido_id])) { $listos++; if (!$conDetalle) continue; }
            $pendientes[] = $f;
        }

        $tecnicos = $this->tecnicosConPartidos();

        $mapeados = DB::table('jugador_tm')->count();
        $porRevisar = DB::table('jugador_tm')->where('revisar', 1)->count();

        // Jugadores que ya tenés cargados con URL de Transfermarkt y todavía no
        // están atados. Mientras queden, conviene sembrar antes de bajar nada:
        // si no, el importador los vuelve a crear como personas nuevas.
        $conUrl = DB::table('jugadors')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->where('transfermarkt_url', 'like', '%/spieler/%')->count();
        $sembrados = DB::table('jugador_tm')->where('origen', 'url')->count();
        $porSembrar = max(0, $conUrl - $sembrados);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Detalle de los partidos</h1>'
            . '<p class="sub">Alineaciones, goles, tarjetas, cambios y árbitros. Cada partido es <b>una</b> llamada a la API, '
            . 'más una cada 50 jugadores que aparezcan por primera vez.</p>'

            . ($porSembrar
                ? '<div class="err-box"><b>Antes de bajar nada, sembrá el mapeo.</b><br>'
                . 'Tenés <b>' . $porSembrar . '</b> jugador(es) con URL de Transfermarkt que todavía no están atados a su id. '
                . 'Si arrancás sin esto, el importador los va a crear de nuevo como personas duplicadas.<br>'
                . '<a class="boton" style="margin-top:8px" href="' . e(route('import_detalles.sembrar')) . '">Sembrar jugador_tm ahora</a>'
                . '</div>'
                : ($conUrl === 0
                    ? '<div class="diag"><b>No hay nada que sembrar.</b> Ningún jugador de la base tiene guardada su URL de '
                    . 'Transfermarkt (<code>jugadors.transfermarkt_url</code>): esa columna se llena sola cuando usás el '
                    . 'scraper de Transfermarkt en la ficha de un jugador, y hasta ahora sólo la tienen los DTs.<br>'
                    . 'No es un problema para arrancar: al no encontrar el mapeo, el importador igual busca al jugador en '
                    . 'la base por <b>apellido + fecha de nacimiento</b> antes de crearlo, y a partir de ahí lo deja atado '
                    . 'a su id de Transfermarkt para siempre. El riesgo que queda es un jugador que ya tengas cargado '
                    . 'con el apellido escrito distinto o sin fecha de nacimiento: ese se va a duplicar, y por eso las '
                    . 'altas quedan en <a href="' . e(route('import_detalles.revisar')) . '">jugadores por revisar</a>.</div>'
                    : '<div class="ok-box">Mapeo sembrado: los ' . $sembrados . ' jugadores que ya tenías con URL de '
                    . 'Transfermarkt están atados a su id.</div>'))

            . '<div class="cards">'
            . $this->card(count($filas), 'Partidos importados')
            . $this->card($listos, 'Con detalle', 'ok')
            . $this->card(count($filas) - $listos, 'Sin detalle', (count($filas) - $listos) ? 'warn' : '')
            . $this->card($mapeados, 'Jugadores mapeados')
            . $this->card($porRevisar, 'Por revisar', $porRevisar ? 'warn' : '')
            . '</div>'

            . '<form method="get" style="margin:12px 0">'
            . '<select name="tecnico_id" class="s2" data-placeholder="todos los DT"><option value="">— todos los DT —</option>';
        foreach ($tecnicos as $t) {
            $cuerpo .= '<option value="' . (int) $t->id . '"' . ($tecnicoId === (int) $t->id ? ' selected' : '') . '>'
                . e($t->nombre) . ' (' . (int) $t->n . ')</option>';
        }
        $cuerpo .= '</select> '
            . '<label><input type="checkbox" name="con_detalle" value="1"' . ($conDetalle ? ' checked' : '') . '> mostrar también los que ya tienen detalle</label> '
            . '<button>Filtrar</button></form>'

            . '<p class="acciones">'
            . '<a class="boton-sec" href="' . e(route('import_detalles.sembrar')) . '">Sembrar jugador_tm desde las URLs</a>'
            . '<a class="boton-sec" href="' . e(route('import_detalles.revisar')) . '">Jugadores por revisar (' . $porRevisar . ')</a>'
            . '</p>';

        if (empty($pendientes)) {
            $cuerpo .= '<div class="ok-box">No queda ningún partido sin detalle' . ($tecnicoId ? ' para este DT' : '') . '.</div>';
            return $this->pagina('Detalle de partidos', $cuerpo);
        }

        $paraTanda = min(10, count($pendientes));
        $cuerpo .= '<p class="acciones">'
            . '<a class="boton" href="' . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null, 'n' => $paraTanda])))
            . '">Bajar los primeros ' . $paraTanda . '</a>'
            . ' <span class="sub">≈ ' . $paraTanda . ' llamadas + las de jugadores nuevos</span></p>';

        $cuerpo .= '<div class="scroll"><table><thead><tr>'
            . '<th>Fecha</th><th>Competencia</th><th>Local</th><th></th><th>Visitante</th><th>Res.</th>'
            . '<th>gameId</th><th>Partido</th><th>Detalle</th><th></th></tr></thead><tbody>';

        $n = 0;
        foreach ($pendientes as $f) {
            if ($n++ >= 400) break;
            $tiene = isset($conAlineacion[(int) $f->partido_id]);
            $cuerpo .= '<tr>'
                . '<td class="num">' . e($f->dia ? substr($f->dia, 0, 10) : '—') . '</td>'
                . '<td>' . e($f->competencia_nombre) . '</td>'
                . '<td>' . e($f->local ? $f->club_nombre : $f->rival_nombre) . '</td>'
                . '<td class="num">vs</td>'
                . '<td>' . e($f->local ? $f->rival_nombre : $f->club_nombre) . '</td>'
                . '<td class="num">' . e($f->goles_favor) . ':' . e($f->goles_contra) . '</td>'
                . '<td class="num">' . e($f->external_id) . '</td>'
                . '<td class="num"><span class="id">#' . (int) $f->partido_id . '</span></td>'
                . '<td>' . ($tiene ? '<span class="ok">' . $conAlineacion[(int) $f->partido_id] . ' jugadores</span>' : '<span class="warn">—</span>') . '</td>'
                . '<td><a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $f->partido_id])) . '">Ver</a>'
                . ' · <a href="' . e(route('import_detalles.bajar', ['partido_id' => (int) $f->partido_id])) . '"><b>Bajar</b></a>'
                . ($tiene ? ' · <a href="' . e(route('import_detalles.bajar', ['partido_id' => (int) $f->partido_id, 'forzar' => 1])) . '" class="err">Rehacer</a>' : '')
                . '</td></tr>';
        }
        $cuerpo .= '</tbody></table></div>';
        if (count($pendientes) > 400) {
            $cuerpo .= '<p class="sub">Se muestran 400 de ' . count($pendientes) . '. Filtrá por DT para ver el resto.</p>';
        }

        return $this->pagina('Detalle de partidos', $cuerpo);
    }

    // ═══════════════════════════ UN PARTIDO ═══════════════════════════

    /** Vista previa: baja el partido y muestra qué cargaría, sin tocar la base. */
    public function ver(Request $request)
    {
        return $this->correrUno($request, false);
    }

    /** Baja y guarda. */
    public function bajar(Request $request)
    {
        return $this->correrUno($request, true);
    }

    private function correrUno(Request $request, $escribir)
    {
        set_time_limit(0);

        $partidoId = (int) $request->get('partido_id', 0);
        $gameId    = trim((string) $request->get('game_id', ''));
        $forzar    = (string) $request->get('forzar', '0') === '1';

        $fila = null;
        if ($partidoId) {
            $fila = DB::table('import_partidos')->where('partido_id', $partidoId)
                ->whereNotNull('external_id')->orderBy('id', 'desc')->first();
            if ($fila && $gameId === '') $gameId = (string) $fila->external_id;
        }
        if (!$partidoId || $gameId === '') {
            return $this->pagina('Detalle', '<p class="err">Falta <code>?partido_id=</code> (y su gameId en el staging).</p>');
        }

        $r = (new TmDetallePartido)->importar($partidoId, $gameId, ['escribir' => $escribir, 'forzar' => $forzar]);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>' . ($escribir ? 'Detalle cargado' : 'Vista previa') . ' · partido #' . $partidoId . '</h1>';

        if ($fila) {
            $cuerpo .= '<p class="sub">' . e($fila->club_nombre . ' vs ' . $fila->rival_nombre)
                . ' · ' . e(substr((string) $fila->dia, 0, 10)) . ' · ' . e((string) $fila->competencia_nombre)
                . ' · gameId ' . e($gameId) . ' · ' . (int) $r['llamadas'] . ' llamada(s) a la API</p>';
        }

        if ($r['error']) {
            $cuerpo .= '<div class="err-box">' . e($r['error']) . '</div>';
        } elseif ($escribir && $r['escrito']) {
            $cuerpo .= '<div class="ok-box">Guardado.</div>';
        } elseif (!$escribir) {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.bajar', array_filter(['partido_id' => $partidoId, 'forzar' => $forzar ? 1 : null])))
                . '">Guardar esto</a></p>';
        }

        $p = $r['plan'];
        $cuerpo .= '<div class="cards">'
            . $this->card(count($p['alineacions']), 'Alineación')
            . $this->card(count($p['gols']), 'Goles')
            . $this->card(count($p['tarjetas']), 'Tarjetas')
            . $this->card(count($p['cambios']), 'Cambios')
            . $this->card(count($p['arbitros']), 'Árbitros')
            . $this->card(count($r['creados']['jugadores']), 'Jugadores nuevos', count($r['creados']['jugadores']) ? 'warn' : '')
            . '</div>';

        if (!empty($r['avisos'])) {
            $cuerpo .= '<h2>Avisos</h2><div class="diag">';
            foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . e($a) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!empty($r['creados']['jugadores'])) {
            $cuerpo .= '<h2>Jugadores que no estaban en la base</h2><div class="diag">';
            foreach ($r['creados']['jugadores'] as $j) $cuerpo .= '<div>• ' . e($j) . '</div>';
            $cuerpo .= '</div>';
        }

        if (!empty($r['creados']['arbitros'])) {
            $cuerpo .= '<h2>Árbitros que no estaban en la base</h2><div class="diag">';
            foreach ($r['creados']['arbitros'] as $a) $cuerpo .= '<div>• ' . e($a) . '</div>';
            $cuerpo .= '</div>';
        }

        $cuerpo .= $this->bloque('Alineación', $p['alineacions'], ['_equipo' => 'Equipo', 'tipo' => 'Tipo',
            'orden' => 'Orden', 'dorsal' => 'Dorsal', '_nombre' => 'Jugador']);
        $cuerpo .= $this->bloque('Goles', $p['gols'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Tarjetas', $p['tarjetas'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Cambios', $p['cambios'], ['minuto' => 'Min', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', '_equipo' => 'Equipo', '_fuente' => 'Cómo lo deduje']);
        $cuerpo .= $this->bloque('Árbitros', $p['arbitros'], ['tipo' => 'Rol', '_nombre' => 'Árbitro']);

        if ((string) $request->get('crudo', '0') === '1' && $r['crudo']) {
            $cuerpo .= '<h2>JSON crudo</h2><pre>' . e(json_encode($r['crudo'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
            $cuerpo .= '<p class="sub"><a href="' . e($request->fullUrl() . (strpos($request->fullUrl(), '?') === false ? '?' : '&') . 'crudo=1')
                . '">Ver el JSON crudo del partido</a> — útil cuando algo no se reconoce.</p>';
        }

        return $this->pagina('Detalle del partido', $cuerpo);
    }

    // ═══════════════════════════════ TANDA ═══════════════════════════════

    public function tanda(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $n = max(1, min(50, (int) $request->get('n', 10)));

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('external_id')->where('estado', 'aplicado')
            ->whereNotIn('partido_id', function ($sub) {
                $sub->from('alineacions')->select('partido_id')->distinct();
            });
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);

        $filas = $q->orderBy('dia', 'desc')->limit($n)->get();

        $imp = new TmDetallePartido;
        $ok = 0; $fallaron = 0; $llamadas = 0; $nuevos = 0;
        $detalle = '';

        foreach ($filas as $f) {
            $r = $imp->importar((int) $f->partido_id, (string) $f->external_id, ['escribir' => true]);
            $llamadas += (int) $r['llamadas'];
            $nuevos   += count($r['creados']['jugadores']);

            $etiqueta = e($f->club_nombre . ' vs ' . $f->rival_nombre) . ' <span class="id">'
                . e(substr((string) $f->dia, 0, 10)) . ' · partido #' . (int) $f->partido_id . '</span>';

            if ($r['escrito']) {
                $ok++;
                $detalle .= '<div><span class="ok">✔</span> ' . $etiqueta . ' — '
                    . count($r['plan']['alineacions']) . ' en la alineación, '
                    . count($r['plan']['gols']) . ' goles, '
                    . count($r['plan']['tarjetas']) . ' tarjetas, '
                    . count($r['plan']['cambios']) . ' cambios'
                    . (count($r['plan']['arbitros']) ? ', ' . count($r['plan']['arbitros']) . ' árbitros' : '')
                    . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $f->partido_id])) . '">ver</a></div>';
            } else {
                $fallaron++;
                $detalle .= '<div><span class="err">✘</span> ' . $etiqueta . ' — ' . e((string) $r['error']) . '</div>';
            }
            foreach ($r['avisos'] as $a) {
                $detalle .= '<div class="sub" style="margin-left:18px">• ' . e($a) . '</div>';
            }
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null]))) . '">← Detalle de los partidos</a></p>'
            . '<h1>Tanda de detalles</h1>'
            . '<div class="cards">'
            . $this->card($ok, 'Cargados', 'ok')
            . $this->card($fallaron, 'Con problema', $fallaron ? 'err' : '')
            . $this->card($nuevos, 'Jugadores nuevos', $nuevos ? 'warn' : '')
            . $this->card($llamadas, 'Llamadas a la API')
            . '</div>';

        if ($filas->isEmpty()) {
            $cuerpo .= '<div class="ok-box">No quedaban partidos sin detalle.</div>';
        } else {
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null, 'n' => $n])))
                . '">Otra tanda de ' . $n . '</a>'
                . '<a class="boton-sec" href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null]))) . '">Volver a la lista</a></p>'
                . '<div class="diag">' . $detalle . '</div>';
        }

        return $this->pagina('Tanda de detalles', $cuerpo);
    }

    // ═══════════════════════════ MAPEO Y REVISIÓN ═══════════════════════════

    /** Llena jugador_tm con los jugadores que ya tenés y tienen transfermarkt_url. */
    public function sembrar(Request $request)
    {
        set_time_limit(0);
        $r = TmDetallePartido::sembrarDesdeUrls();

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Siembra de jugador_tm</h1>'
            . '<p class="sub">Se lee el <code>/spieler/NNN</code> de <code>jugadors.transfermarkt_url</code> y se ata cada '
            . 'jugador a su id de Transfermarkt. Es lo que evita que el importador cree de nuevo a alguien que ya tenés.</p>'
            . '<div class="cards">'
            . $this->card($r['creados'], 'Mapeos nuevos', 'ok')
            . $this->card($r['ya_estaban'], 'Ya estaban')
            . $this->card($r['sin_id'], 'URL sin id', $r['sin_id'] ? 'warn' : '')
            . '</div>'
            . '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.index')) . '">Volver</a></p>';

        return $this->pagina('Siembra de jugador_tm', $cuerpo);
    }

    /** Jugadores que creó el importador y todavía no repasó nadie. */
    public function revisar(Request $request)
    {
        if ($request->filled('ok')) {
            DB::table('jugador_tm')->where('id', (int) $request->get('ok'))->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }
        if ((string) $request->get('todos', '0') === '1') {
            DB::table('jugador_tm')->where('revisar', 1)->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }

        $filas = DB::table('jugador_tm')
            ->join('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
            ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
            ->where('jugador_tm.revisar', 1)
            ->select('jugador_tm.id', 'jugador_tm.tm_player_id', 'jugador_tm.jugador_id', 'jugador_tm.created_at',
                'personas.name', 'personas.nombre', 'personas.apellido', 'personas.nacimiento',
                'personas.ciudad', 'personas.nacionalidad', 'jugadors.tipoJugador')
            ->orderBy('jugador_tm.created_at', 'desc')
            ->limit(500)->get();

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>Jugadores creados por el importador</h1>'
            . '<p class="sub">Se dieron de alta solos al cargar una alineación. Repasá que el nombre, la fecha de '
            . 'nacimiento y la nacionalidad estén bien, y marcalos como vistos.</p>';

        if ($filas->isEmpty()) {
            $cuerpo .= '<div class="ok-box">No queda ninguno por revisar.</div>';
            return $this->pagina('Jugadores por revisar', $cuerpo);
        }

        $cuerpo .= '<p class="acciones"><a class="boton-sec" href="' . e(route('import_detalles.revisar', ['todos' => 1]))
            . '">Marcar todos como vistos (' . count($filas) . ')</a></p>'
            . '<div class="scroll"><table><thead><tr>'
            . '<th>Alta</th><th>Nombre</th><th>Apellido, nombre</th><th>Nacimiento</th><th>Ciudad</th>'
            . '<th>Nacionalidad</th><th>Puesto</th><th>TM</th><th></th></tr></thead><tbody>';

        foreach ($filas as $f) {
            $falta = (!$f->nacimiento ? ' <span class="err">sin fecha</span>' : '')
                . (!$f->nacionalidad ? ' <span class="err">sin país</span>' : '');
            $cuerpo .= '<tr>'
                . '<td class="num">' . e(substr((string) $f->created_at, 0, 10)) . '</td>'
                . '<td>' . e($f->name) . '</td>'
                . '<td>' . e($f->apellido . ', ' . $f->nombre) . $falta . '</td>'
                . '<td class="num">' . e($f->nacimiento ?: '—') . '</td>'
                . '<td>' . e($f->ciudad ?: '—') . '</td>'
                . '<td>' . e($f->nacionalidad ?: '—') . '</td>'
                . '<td>' . e($f->tipoJugador ?: '—') . '</td>'
                . '<td><a target="_blank" href="https://www.transfermarkt.es/-/profil/spieler/' . e($f->tm_player_id) . '">' . e($f->tm_player_id) . '</a></td>'
                . '<td><a href="' . e(route('jugadores.edit', $f->jugador_id)) . '">Editar</a>'
                . ' · <a href="' . e(route('import_detalles.revisar', ['ok' => $f->id])) . '">Visto</a></td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        return $this->pagina('Jugadores por revisar', $cuerpo);
    }

    // ═══════════════════════════ AUXILIARES ═══════════════════════════

    private function tecnicosConPartidos()
    {
        return DB::table('import_partidos')
            ->join('tecnicos', 'tecnicos.id', '=', 'import_partidos.tecnico_id')
            ->join('personas', 'personas.id', '=', 'tecnicos.persona_id')
            ->where('import_partidos.estado', 'aplicado')
            ->whereNotNull('import_partidos.partido_id')
            ->select('tecnicos.id', 'personas.name AS nombre', DB::raw('COUNT(*) AS n'))
            ->groupBy('tecnicos.id', 'personas.name')
            ->orderBy('personas.name')
            ->get();
    }

    /** Tabla genérica del plan: columnas => títulos. */
    private function bloque($titulo, array $filas, array $columnas)
    {
        if (empty($filas)) {
            return '<h2>' . e($titulo) . '</h2><p class="sub">Nada.</p>';
        }
        $out = '<h2>' . e($titulo) . ' <span class="sub">(' . count($filas) . ')</span></h2>'
            . '<div class="scroll"><table><thead><tr>';
        foreach ($columnas as $t) $out .= '<th>' . e($t) . '</th>';
        $out .= '</tr></thead><tbody>';
        foreach ($filas as $f) {
            $clase = !empty($f['_dudoso']) ? ' class="warn"' : '';
            $out .= '<tr' . $clase . '>';
            foreach ($columnas as $k => $t) {
                $v = isset($f[$k]) ? $f[$k] : null;
                $num = in_array($k, ['minuto', 'orden', 'dorsal'], true) ? ' class="num"' : '';
                $out .= '<td' . $num . '>' . e($v === null || $v === '' ? '—' : (string) $v) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

    private function pagina($titulo, $cuerpo)
    {
        $css = '
            body{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;margin:0;padding:24px 28px;color:#1a1f1c;background:#f7f8f6}
            h1{font-size:22px;margin:0 0 4px} h2{font-size:16px;margin:28px 0 8px}
            .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
            .acciones{margin:12px 0} .acciones a{margin-right:6px}
            a{color:#15714e}
            a.boton{display:inline-block;background:#15714e;color:#fff;padding:5px 12px;text-decoration:none;font-weight:600}
            a.boton:hover{background:#0f5a3d}
            a.boton-sec{display:inline-block;padding:4px 10px;border:1px solid #c7cec7;background:#eef1ec;color:#15714e;text-decoration:none;font-size:12px}
            a.boton-sec:hover{background:#e2e8e1}
            .diag{background:#fff;border:1px solid #dde2dd;padding:14px 16px;font-size:13px}
            .diag code{background:#eef1ec;padding:1px 5px;font-size:12px}
            pre{font-size:11px;max-height:420px;overflow:auto;background:#f0f3ef;padding:10px}
            .cards{display:flex;flex-wrap:wrap;gap:1px;background:#dde2dd;border:1px solid #dde2dd;margin:14px 0}
            .card{background:#fff;padding:10px 16px;min-width:110px}
            .card b{display:block;font-size:20px} .card span{font-size:11px;color:#6b7a73;text-transform:uppercase;letter-spacing:.06em}
            .card.ok b{color:#15714e} .card.err b{color:#9c3529} .card.warn b{color:#8a5d00} .card.gris b{color:#9aa69f}
            .ok-box{background:#ddede4;border:1px solid #15714e;padding:10px 14px}
            .err-box{background:#f6e2de;border:1px solid #9c3529;padding:10px 14px}
            .err{color:#9c3529} .ok{color:#15714e} .warn{color:#8a5d00}
            tr.warn td{background:#fdf6e6}
            .scroll{overflow:auto;border:1px solid #dde2dd;background:#fff;max-height:70vh}
            table{border-collapse:collapse;width:100%;font-size:12.5px}
            th,td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap}
            thead th{position:sticky;top:0;background:#eef1ec;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
            td.num{font-variant-numeric:tabular-nums}
            .id{color:#9aa69f;font-size:11px}
            input,button,select{font:13px inherit;padding:3px 6px;border:1px solid #c7cec7;background:#fff}
            button{cursor:pointer;background:#eef1ec}
            details summary{cursor:pointer;color:#15714e;margin-top:6px}
            .select2-container{vertical-align:middle}
            .select2-container--default .select2-selection--single{border-color:#c7cec7;border-radius:0;height:28px}
            .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:26px;font-size:13px}
            .select2-container--default .select2-selection--single .select2-selection__arrow{height:26px}
        ';
        $assets = '<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet">'
            . '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js"></script>'
            . '<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>';
        $init = '<script>$(function(){$(".s2").each(function(){'
            . '$(this).select2({width:"320px",placeholder:$(this).data("placeholder")||"",allowClear:true});});});</script>';

        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title>'
            . $assets . '<style>' . $css . '</style>' . $cuerpo . $init);
    }
}
