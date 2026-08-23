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

        $fechas = $this->mapaFechas($ids);

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
            . '<a class="boton-sec" href="' . e(route('import_detalles.plantillas', array_filter(['tecnico_id' => $tecnicoId ?: null])))
            . '">Completar plantillas de lo ya cargado</a>'
            . '</p>';

        if ($listos) {
            $paraRehacer = min(10, $listos);
            $cuerpo .= '<p class="acciones"><a class="boton-sec" href="'
                . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null,
                    'n' => $paraRehacer, 'rehacer' => 1])))
                . '">Rehacer el detalle de los ' . $paraRehacer . ' más nuevos</a>'
                . ' <span class="sub">vuelve a bajarlos de Transfermarkt y pisa lo que ya tenían '
                . '(' . $paraRehacer . ' llamadas)</span></p>';
        }

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
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);
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
                . ($inc !== '' ? ' · ' . $inc : '')
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

        // &fotos=0 para no gastar una llamada por cada persona nueva.
        $fotos = (string) $request->get('fotos', '1') !== '0';

        $r = (new TmDetallePartido)->importar($partidoId, $gameId,
            ['escribir' => $escribir, 'forzar' => $forzar, 'fotos' => $fotos]);

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos</a></p>'
            . '<h1>' . ($escribir ? 'Detalle cargado' : 'Vista previa') . ' · partido #' . $partidoId . '</h1>';

        if ($fila) {
            $mapa = $this->mapaFechas([$partidoId]);
            $inc  = $this->linkIncidencias(isset($mapa[$partidoId]) ? $mapa[$partidoId] : null,
                'Incidencias del partido →');
            $cuerpo .= '<p class="sub">' . e($fila->club_nombre . ' vs ' . $fila->rival_nombre)
                . ' · ' . e(substr((string) $fila->dia, 0, 10)) . ' · ' . e((string) $fila->competencia_nombre)
                . ' · gameId ' . e($gameId) . ' · ' . (int) $r['llamadas'] . ' llamada(s) a la API'
                . ($inc !== '' ? ' · ' . $inc : '') . '</p>';
        }

        if ($r['error']) {
            $cuerpo .= '<div class="err-box">' . e($r['error']) . '</div>';
        } elseif ($escribir && $r['escrito']) {
            $fallidas = isset($r['fallidas']) ? (int) $r['fallidas'] : 0;
            $cuerpo .= $fallidas
                ? '<div class="err-box">Guardado, pero la base rechazó <b>' . $fallidas . '</b> fila(s). '
                . 'Mirá los avisos para saber cuáles.</div>'
                : '<div class="ok-box">Guardado.</div>';
        } elseif (!$escribir) {
            // Si el partido ya tiene detalle, el único guardado posible es
            // rehacerlo: el botón lo dice y lleva forzar=1.
            $yaTiene = DB::table('alineacions')->where('partido_id', $partidoId)->exists();
            $cuerpo .= '<p class="acciones"><a class="boton" href="'
                . e(route('import_detalles.bajar', ['partido_id' => $partidoId, 'forzar' => ($yaTiene || $forzar) ? 1 : null]))
                . '">' . ($yaTiene ? 'Rehacer y guardar' : 'Guardar esto') . '</a>'
                . ($yaTiene ? ' <span class="sub">reemplaza alineación, goles, tarjetas, cambios y árbitros de este partido</span>' : '')
                . '</p>';
        }

        $p = $r['plan'];
        $cuerpo .= '<div class="cards">'
            . $this->card(count($p['alineacions']), 'Alineación')
            . $this->card(count($p['gols']), 'Goles')
            . $this->card(count($p['tarjetas']), 'Tarjetas')
            . $this->card(count($p['cambios']), 'Cambios')
            . $this->card(count($p['arbitros']), 'Árbitros')
            . $this->card(count(isset($p['tecnicos']) ? $p['tecnicos'] : []), 'Técnicos')
            . $this->card(count(isset($p['plantillas']) ? $p['plantillas'] : []), 'A la plantilla')
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

        if (!empty($r['creados']['tecnicos'])) {
            $cuerpo .= '<h2>Técnicos que no estaban en la base</h2><div class="diag">'
                . '<p class="sub">Se crean con su URL de Transfermarkt, así que van a aparecer solos '
                . 'en la lista de Carga de partidos y les vas a poder sondear los suyos.</p>';
            foreach ($r['creados']['tecnicos'] as $t) $cuerpo .= '<div>• ' . e($t) . '</div>';
            $cuerpo .= '</div>';
        }

        $cuerpo .= $this->bloque('Alineación', $p['alineacions'], ['_equipo' => 'Equipo', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', 'dorsal' => 'Dorsal', '_fuente' => 'De dónde sale el dorsal',
            'orden' => 'Puesto']);
        $cuerpo .= $this->bloque('Goles', $p['gols'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Tarjetas', $p['tarjetas'], ['minuto' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Texto de Transfermarkt']);
        $cuerpo .= $this->bloque('Cambios', $p['cambios'], ['minuto' => 'Min', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', '_equipo' => 'Equipo', '_fuente' => 'Cómo lo deduje']);
        $cuerpo .= $this->bloque('Árbitros', $p['arbitros'], ['tipo' => 'Rol', '_nombre' => 'Árbitro']);
        $cuerpo .= $this->bloque('Técnicos', isset($p['tecnicos']) ? $p['tecnicos'] : [],
            ['_equipo' => 'Equipo', '_nombre' => 'DT', '_estado' => 'Estado']);

        if (!empty($p['plantillas'])) {
            $cuerpo .= '<p class="sub" style="margin-top:24px">Los ' . count($p['plantillas']) . ' jugadores de arriba '
                . 'también se suman a la <b>plantilla</b> de su equipo en este torneo. Sin eso, la pantalla de '
                . '<code>/admin/alineaciones</code> no los ofrece en los desplegables y no se puede editar el partido a mano. '
                . 'Se usa la plantilla que el equipo ya tenga en <b>cualquier grupo del torneo</b> —una por torneo, aunque '
                . 'después pase de zona a fase final— y sólo se crea una nueva si no existía ninguna. '
                . 'A los que ya estén en la plantilla no se les toca el dorsal.</p>';
        }

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
        // rehacer=1: en vez de los que faltan, vuelve a bajar los que YA tienen
        // detalle. Sirve cuando el importador aprendió algo nuevo (plantillas,
        // técnicos, un tipo de gol) después de haberlos cargado.
        $rehacer = (string) $request->get('rehacer', '0') === '1';
        $desde   = (int) $request->get('offset', 0);

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->whereNotNull('external_id')->where('estado', 'aplicado');
        if ($rehacer) {
            $q->whereIn('partido_id', function ($sub) {
                $sub->from('alineacions')->select('partido_id')->distinct();
            });
        } else {
            $q->whereNotIn('partido_id', function ($sub) {
                $sub->from('alineacions')->select('partido_id')->distinct();
            });
        }
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);

        $filas = $q->orderBy('dia', 'desc')->offset($rehacer ? $desde : 0)->limit($n)->get();

        $fechas = $this->mapaFechas($filas->pluck('partido_id')->all());

        $imp = new TmDetallePartido;
        $ok = 0; $fallaron = 0; $llamadas = 0; $nuevos = 0;
        $detalle = '';

        foreach ($filas as $f) {
            $r = $imp->importar((int) $f->partido_id, (string) $f->external_id,
                ['escribir' => true, 'forzar' => $rehacer]);
            $llamadas += (int) $r['llamadas'];
            $nuevos   += count($r['creados']['jugadores']);

            $etiqueta = e($f->club_nombre . ' vs ' . $f->rival_nombre) . ' <span class="id">'
                . e(substr((string) $f->dia, 0, 10)) . ' · partido #' . (int) $f->partido_id . '</span>';
            $inc = $this->linkIncidencias(isset($fechas[(int) $f->partido_id]) ? $fechas[(int) $f->partido_id] : null);

            if ($r['escrito']) {
                $ok++;
                $detalle .= '<div><span class="ok">✔</span> ' . $etiqueta . ' — '
                    . count($r['plan']['alineacions']) . ' en la alineación, '
                    . count($r['plan']['gols']) . ' goles, '
                    . count($r['plan']['tarjetas']) . ' tarjetas, '
                    . count($r['plan']['cambios']) . ' cambios'
                    . (count($r['plan']['arbitros']) ? ', ' . count($r['plan']['arbitros']) . ' árbitros' : '')
                    . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => (int) $f->partido_id])) . '">ver</a>'
                    . ($inc !== '' ? ' · ' . $inc : '') . '</div>';
            } else {
                $fallaron++;
                $detalle .= '<div><span class="err">✘</span> ' . $etiqueta . ' — ' . e((string) $r['error']) . '</div>';
            }
            foreach ($r['avisos'] as $a) {
                $detalle .= '<div class="sub" style="margin-left:18px">• ' . e($a) . '</div>';
            }
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null]))) . '">← Detalle de los partidos</a></p>'
            . '<h1>' . ($rehacer ? 'Rehacer detalles' : 'Tanda de detalles') . '</h1>'
            . ($rehacer ? '<p class="sub">Se vuelve a bajar el detalle de partidos que <b>ya lo tenían</b>: '
                . 'reemplaza alineación, goles, tarjetas, cambios y árbitros, y completa lo que el importador '
                . 'no sabía hacer cuando los cargaste (plantillas, técnicos). Van del ' . ($desde + 1)
                . ' al ' . ($desde + $n) . ' de la lista, del más nuevo al más viejo.</p>' : '')
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
                . e(route('import_detalles.tanda', array_filter(['tecnico_id' => $tecnicoId ?: null, 'n' => $n,
                    'rehacer' => $rehacer ? 1 : null, 'offset' => $rehacer ? ($desde + $n) : null])))
                . '">' . ($rehacer ? 'Rehacer los ' . $n . ' siguientes' : 'Otra tanda de ' . $n) . '</a>'
                . '<a class="boton-sec" href="' . e(route('import_detalles.index', array_filter(['tecnico_id' => $tecnicoId ?: null]))) . '">Volver a la lista</a></p>'
                . '<div class="diag">' . $detalle . '</div>';
        }

        return $this->pagina('Tanda de detalles', $cuerpo);
    }

    // ═══════════════════════════ DIAGNÓSTICO ═══════════════════════════

    /**
     * Qué devuelve tmapi para un árbitro, un DT o un jugador, y qué saca de ahí
     * el parser. `personaDesdePerfil()` se escribió con el JSON de jugadores;
     * si árbitros o DTs traen otras claves, se ve acá.
     *
     *   /admin/import-detalles/arbitro?tm_id=5325&tipo=arbitro
     */
    public function arbitro(Request $request)
    {
        set_time_limit(0);

        $tmId = trim((string) $request->get('tm_id', ''));
        $tipo = (string) $request->get('tipo', 'arbitro');
        if (!in_array($tipo, ['arbitro', 'tecnico', 'jugador', 'equipo'], true)) $tipo = 'arbitro';

        // Los clubes no usan /profil/: van por /startseite/.
        $perfilTm = ['arbitro' => 'profil/schiedsrichter', 'tecnico' => 'profil/trainer',
            'jugador' => 'profil/spieler', 'equipo' => 'startseite/verein'];

        $opts = '';
        foreach (['arbitro' => 'Árbitro', 'tecnico' => 'DT',
                     'jugador' => 'Jugador (referencia)', 'equipo' => 'Club'] as $k => $v) {
            $opts .= '<option value="' . $k . '"' . ($tipo === $k ? ' selected' : '') . '>' . e($v) . '</option>';
        }

        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.revisar')) . '">← Jugadores y árbitros por revisar</a></p>'
            . '<h1>Diagnóstico de perfiles de Transfermarkt</h1>'
            . '<p class="sub">Árbitros y DTs se crean con el mismo parser que los jugadores '
            . '(<code>personaDesdePerfil</code>). Si a alguno le falta la fecha de nacimiento o la nacionalidad, '
            . 'es porque su JSON usa otras claves. Mirá un jugador también: ese es el que el parser SÍ entiende.</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<select name="tipo">' . $opts . '</select> '
            . '<input name="tm_id" value="' . e($tmId) . '" placeholder="id de TM, ej 5325" size="18"> <button>Ver</button>'
            . ' <span class="sub">el id sale del link TM de la lista de revisión, o de la URL del perfil</span></form>';

        if ($tmId === '') {
            return $this->pagina('Diagnóstico de perfiles', $cuerpo
                . '<div class="diag">Pegá un id de Transfermarkt para ver el JSON crudo y qué interpreta el importador. '
                . 'Cuesta hasta 4 llamadas a la API.</div>');
        }

        $r = TmDetallePartido::diagnosticarPersonaTm($tmId, $tipo);

        $cuerpo .= '<p class="sub">' . (int) $r['llamadas'] . ' llamada(s) a la API · '
            . '<a target="_blank" href="https://www.transfermarkt.es/-/' . $perfilTm[$tipo] . '/' . e($tmId)
            . '">ver el perfil en TM ↗</a>'
            . ($tipo === 'equipo'
                ? ' · <a target="_blank" href="https://www.transfermarkt.es/-/datenfakten/verein/' . e($tmId)
                  . '">Datos y hechos ↗</a> <span class="sub">(fundación, estadio, socios: no están en la API)</span>'
                : '') . '</p>';

        if (empty($r['perfil'])) {
            $cuerpo .= '<div class="err-box">Ninguna ruta devolvió un perfil para ese id. '
                . 'Mirá abajo qué contestó cada una.</div>';
        } elseif ($tipo === 'equipo') {
            $cuerpo .= '<div class="ok-box">Perfil encontrado. Los clubes todavía no se importan: '
                . 'este crudo es para ver qué campos hay (escudo, fundación, estadio, país, socios) '
                . 'antes de escribir el importador.</div>'
                . '<h2>Perfil crudo</h2><pre>' . e(json_encode($r['perfil'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        } else {
            $d = $r['datos'];
            $fila = function ($k, $v) {
                return '<tr><td>' . e($k) . '</td><td>'
                    . ($v === null || $v === '' ? '<span class="err">— vacío —</span>' : '<b>' . e($v) . '</b>')
                    . '</td></tr>';
            };
            $per = isset($d['persona']) ? $d['persona'] : [];
            $cuerpo .= '<h2>Lo que saca el parser hoy</h2>'
                . '<div class="scroll"><table><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>'
                . $fila('name', isset($d['name']) ? $d['name'] : null)
                . $fila('nombre', isset($per['nombre']) ? $per['nombre'] : null)
                . $fila('apellido', isset($per['apellido']) ? $per['apellido'] : null)
                . $fila('nacimiento', isset($per['nacimiento']) ? $per['nacimiento'] : null)
                . $fila('nacionalidad', isset($per['nacionalidad']) ? $per['nacionalidad'] : null)
                . $fila('ciudad', isset($per['ciudad']) ? $per['ciudad'] : null)
                . '</tbody></table></div>'
                . '<p class="sub"><b>Ojo:</b> si nacionalidad sale vacía, la persona NO queda sin nacionalidad — '
                . '<code>personas.nacionalidad</code> tiene DEFAULT \'Argentina\' en la base y la rellena sola. '
                . 'Un dato faltante se convierte en un dato falso.</p>'
                . '<h2>Perfil crudo</h2><pre>' . e(json_encode($r['perfil'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
        }

        foreach ($r['rutas'] as $ruta => $json) {
            $cuerpo .= '<h2>' . e($ruta) . '</h2><pre>'
                . e($json === null || $json === false
                    ? 'sin respuesta'
                    : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                . '</pre>';
        }

        if (!empty($r['avisos'])) {
            $cuerpo .= '<h2>Avisos</h2><div class="diag">';
            foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . e($a) . '</div>';
            $cuerpo .= '</div>';
        }

        return $this->pagina('Diagnóstico de perfiles', $cuerpo);
    }

    /**
     * Explora qué rutas de competencia existen en tmapi. Es el paso previo a
     * decidir si un torneo EN CURSO (Liga Profesional, Conmebol) se puede
     * cargar por fixture en vez de fecha por fecha a mano desde livefutbol.
     *
     *   /admin/import-detalles/competencia?comp_id=ARGC&season=2025&ronda=1
     */
    public function competencia(Request $request)
    {
        set_time_limit(0);

        $compId = trim((string) $request->get('comp_id', ''));
        $season = trim((string) $request->get('season', ''));
        $ronda  = trim((string) $request->get('ronda', ''));
        $clubId = trim((string) $request->get('club_id', ''));

        $cuerpo = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Carga de partidos</a></p>'
            . '<h1>Diagnóstico de competencias</h1>'
            . '<p class="sub">Busca una ruta que devuelva la <b>lista de partidos</b> con su <code>gameId</code>. '
            . 'Es lo único que falta para cargar un torneo en curso: con el gameId, el importador de detalle '
            . 'ya trae alineaciones e incidencias. <b>Cada ruta cuesta 1 crédito.</b><br>'
            . 'Ya sabemos que <code>/games</code>, <code>/matches</code>, <code>matchday</code> y <code>round</code> '
            . 'dan 404. Acá probamos el vocabulario propio de TM (<code>gameDay</code>) y el espejo de '
            . '<code>/coach/{id}/performance-game</code> a nivel club.<br>'
            . 'El id de competencia sale de <code>primaryCompetitionId</code> de un club (ej <code>ARGC</code>) '
            . 'o de <code>import_partidos.competencia_external_id</code>. El de club, de <code>equipo_tm</code> '
            . '(Vélez es <code>1029</code>).</p>'
            . '<form method="get" style="margin:12px 0">'
            . '<input name="comp_id" value="' . e($compId) . '" placeholder="comp id, ej ARGC" size="14"> '
            . '<input name="season" value="' . e($season) . '" placeholder="temporada, ej 2025" size="12"> '
            . '<input name="ronda" value="' . e($ronda) . '" placeholder="fecha nº" size="9"> '
            . '<input name="club_id" value="' . e($clubId) . '" placeholder="club TM, ej 1029" size="12"> '
            . '<button>Probar</button></form>';

        if ($compId === '' && $clubId === '') {
            return $this->pagina('Diagnóstico de competencias', $cuerpo
                . '<div class="diag">Pegá un id de competencia y/o uno de club para arrancar.</div>');
        }

        $r = TmDetallePartido::diagnosticarCompetencia($compId ?: '', $season ?: null, $ronda ?: null, $clubId ?: null);

        $vivas = 0;
        foreach ($r['rutas'] as $info) if (!empty($info['ok'])) $vivas++;

        $cuerpo .= '<div class="cards">'
            . $this->card($r['llamadas'], 'rutas probadas')
            . $this->card($vivas, 'con datos', $vivas ? 'ok' : 'err')
            . '</div>';

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Ruta</th><th>¿Responde?</th>'
            . '<th>Items</th><th>Rama</th><th>Claves de primer nivel</th></tr></thead><tbody>';
        foreach ($r['rutas'] as $ruta => $info) {
            $cuerpo .= '<tr' . ((int) $info['items'] > 1 ? ' class="warn"' : '') . '>'
                . '<td><code>' . e($ruta) . '</code></td>'
                . '<td>' . (!empty($info['ok']) ? '<span class="ok">sí</span>' : '<span class="err">no</span>') . '</td>'
                . '<td class="num">' . ((int) $info['items'] ?: '—') . '</td>'
                . '<td>' . e($info['rama'] ?: '—') . '</td>'
                . '<td>' . e(implode(', ', $info['claves']) ?: '—') . '</td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>'
            . '<p class="sub">La candidata es la fila resaltada con muchos <b>items</b>: ahí está la lista de partidos. '
            . 'La columna <b>Rama</b> dice de qué clave cuelga.</p>';

        foreach ($r['rutas'] as $ruta => $info) {
            if (empty($info['ok'])) continue;
            $txt = json_encode($info['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $corte = '';
            if (strlen($txt) > 12000) {
                $txt = substr($txt, 0, 12000);
                $corte = '<p class="sub">(recortado: se muestran los primeros 12 KB)</p>';
            }
            $cuerpo .= '<h2>' . e($ruta) . '</h2><pre>' . e($txt) . '</pre>' . $corte;
        }

        return $this->pagina('Diagnóstico de competencias', $cuerpo);
    }

    // ═══════════════════════════ PLANTILLAS ═══════════════════════════

    /**
     * Completa las plantillas de los partidos que YA tienen la alineación
     * cargada. No toca Transfermarkt: todo sale de `alineacions`.
     *
     * Los partidos que se importaron antes de que el detalle escribiera
     * `plantilla_jugadors` quedaron con la alineación bien pero fuera de la
     * plantilla del torneo, y por eso `/admin/alineaciones` no los deja editar.
     * Esto los repara sin volver a bajar nada.
     */
    public function plantillas(Request $request)
    {
        set_time_limit(0);

        $tecnicoId = (int) $request->get('tecnico_id', 0);
        $aplicar   = (string) $request->get('aplicar', '0') === '1';

        $q = DB::table('import_partidos')
            ->whereNotNull('partido_id')->where('estado', 'aplicado');
        if ($tecnicoId) $q->where('tecnico_id', $tecnicoId);
        $ids = $q->pluck('partido_id')->all();

        $r = TmDetallePartido::plantillasDesdeAlineaciones($ids, $aplicar);

        $volver = array_filter(['tecnico_id' => $tecnicoId ?: null]);
        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index', $volver)) . '">← Detalle de los partidos</a></p>'
            . '<h1>Plantillas de lo ya cargado</h1>'
            . '<p class="sub">Los partidos que cargaste antes de que el importador escribiera plantillas quedaron con '
            . 'la alineación completa pero sus jugadores fuera de la <b>plantilla del torneo</b>. Se ven bien en la ficha, '
            . 'pero <code>/admin/alineaciones</code> no los ofrece en los desplegables y no se pueden editar a mano. '
            . 'Esto lo arregla leyendo las alineaciones que ya están en tu base: <b>no gasta ninguna llamada a la API</b>.</p>';

        $cuerpo .= '<div class="cards">'
            . $this->card($r['partidos'], 'Partidos con alineación')
            . $this->card($r['alineaciones'], 'Filas de alineación')
            . $this->card(count($r['faltantes']), 'Fuera de la plantilla', count($r['faltantes']) ? 'warn' : 'ok')
            . $this->card($r['plantillas_nuevas'], 'Plantillas a crear', $r['plantillas_nuevas'] ? 'warn' : '')
            . ($aplicar ? $this->card($r['agregados'], 'Agregados', 'ok') : '')
            . ($aplicar && $r['fallidas'] ? $this->card($r['fallidas'], 'Rechazados', 'err') : '')
            . '</div>';

        if ($aplicar) {
            $cuerpo .= $r['fallidas']
                ? '<div class="err-box">Se agregaron ' . (int) $r['agregados'] . ', pero la base rechazó '
                . (int) $r['fallidas'] . '. Mirá los avisos.</div>'
                : '<div class="ok-box">Listo: ' . (int) $r['agregados'] . ' jugador(es) sumados a su plantilla.</div>';

            if (!empty($r['avisos'])) {
                $cuerpo .= '<h2>Avisos</h2><div class="diag">';
                foreach ($r['avisos'] as $a) $cuerpo .= '<div class="warn">• ' . e($a) . '</div>';
                $cuerpo .= '</div>';
            }
            $cuerpo .= '<p class="acciones">'
                . '<a class="boton" href="' . e(route('import_detalles.index', $volver)) . '">Volver a la lista</a>'
                . '<a class="boton-sec" href="' . e(route('import_detalles.plantillas', $volver)) . '">Volver a revisar</a></p>';
            return $this->pagina('Plantillas', $cuerpo);
        }

        if (empty($r['faltantes'])) {
            $cuerpo .= '<div class="ok-box">No falta nadie: todos los jugadores de las alineaciones ya están en la '
                . 'plantilla de su equipo en ese torneo' . ($tecnicoId ? ' (para este DT)' : '') . '.</div>';
            return $this->pagina('Plantillas', $cuerpo);
        }

        $params = $volver;
        $params['aplicar'] = 1;
        $cuerpo .= '<p class="acciones"><a class="boton" href="' . e(route('import_detalles.plantillas', $params))
            . '">Sumarlos a la plantilla (' . count($r['faltantes']) . ')</a>'
            . ' <span class="sub">no se le toca el dorsal a nadie que ya esté cargado</span></p>';

        $cuerpo .= '<div class="scroll"><table><thead><tr><th>Torneo</th><th>Equipo</th><th>Jugador</th>'
            . '<th>Dorsal</th><th>Plantilla</th><th>Partido</th></tr></thead><tbody>';
        $n = 0;
        foreach ($r['faltantes'] as $f) {
            if ($n++ >= 500) break;
            $cuerpo .= '<tr>'
                . '<td>' . e($f['_torneo']) . '</td>'
                . '<td>' . e($f['_equipo']) . '</td>'
                . '<td>' . e($f['_nombre']) . '</td>'
                . '<td class="num">' . e($f['dorsal'] === null || $f['dorsal'] === '' ? '—' : $f['dorsal']) . '</td>'
                . '<td class="num">' . ($f['_plantilla'] ? '<span class="id">#' . (int) $f['_plantilla'] . '</span>' : '<span class="warn">se crea</span>') . '</td>'
                . '<td class="num"><span class="id">#' . (int) $f['partido_id'] . '</span></td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';
        if (count($r['faltantes']) > 500) {
            $cuerpo .= '<p class="sub">Se muestran 500 de ' . count($r['faltantes']) . '. Se aplican todos igual.</p>';
        }

        return $this->pagina('Plantillas', $cuerpo);
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
        if ($request->filled('ok_arb')) {
            DB::table('arbitro_tm')->where('id', (int) $request->get('ok_arb'))->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }
        if ((string) $request->get('todos', '0') === '1') {
            DB::table('jugador_tm')->where('revisar', 1)->update(['revisar' => 0, 'updated_at' => now()]);
            DB::table('arbitro_tm')->where('revisar', 1)->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.revisar');
        }

        $arbitros = DB::table('arbitro_tm')
            ->join('arbitros', 'arbitros.id', '=', 'arbitro_tm.arbitro_id')
            ->join('personas', 'personas.id', '=', 'arbitros.persona_id')
            ->where('arbitro_tm.revisar', 1)
            ->select('arbitro_tm.id', 'arbitro_tm.tm_referee_id', 'arbitro_tm.arbitro_id', 'arbitro_tm.created_at',
                'personas.name', 'personas.nombre', 'personas.apellido', 'personas.nacimiento', 'personas.nacionalidad')
            ->orderBy('arbitro_tm.created_at', 'desc')->limit(200)->get();

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

        if ($filas->isEmpty() && $arbitros->isEmpty()) {
            $cuerpo .= '<div class="ok-box">No queda ninguno por revisar.</div>';
            return $this->pagina('Jugadores por revisar', $cuerpo);
        }

        if (!$arbitros->isEmpty()) {
            $cuerpo .= '<h2>Árbitros</h2><div class="scroll"><table><thead><tr>'
                . '<th>Alta</th><th>Nombre</th><th>Apellido, nombre</th><th>Nacimiento</th>'
                . '<th>Nacionalidad</th><th>TM</th><th></th></tr></thead><tbody>';
            foreach ($arbitros as $a) {
                $cuerpo .= '<tr>'
                    . '<td class="num">' . e(substr((string) $a->created_at, 0, 10)) . '</td>'
                    . '<td>' . e($a->name) . '</td>'
                    . '<td>' . e($a->apellido . ', ' . $a->nombre) . '</td>'
                    . '<td class="num">' . e($a->nacimiento ?: '—') . '</td>'
                    . '<td>' . e($a->nacionalidad ?: '—') . '</td>'
                    . '<td><a target="_blank" href="https://www.transfermarkt.es/-/profil/schiedsrichter/' . e($a->tm_referee_id) . '">' . e($a->tm_referee_id) . '</a></td>'
                    . '<td><a href="' . e(route('arbitros.edit', $a->arbitro_id)) . '">Editar</a>'
                    . ' · <a href="' . e(route('import_detalles.arbitro', ['tm_id' => $a->tm_referee_id, 'tipo' => 'arbitro'])) . '">Diagnóstico</a>'
                    . ' · <a href="' . e(route('import_detalles.revisar', ['ok_arb' => $a->id])) . '">Visto</a></td>'
                    . '</tr>';
            }
            $cuerpo .= '</tbody></table></div><h2>Jugadores</h2>';
        }

        if ($filas->isEmpty()) {
            return $this->pagina('Jugadores por revisar', $cuerpo . '<p class="sub">No queda ningún jugador por revisar.</p>');
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
                . ' · <a href="' . e(route('import_detalles.arbitro', ['tm_id' => $f->tm_player_id, 'tipo' => 'jugador'])) . '">Diagnóstico</a>'
                . ' · <a href="' . e(route('import_detalles.revisar', ['ok' => $f->id])) . '">Visto</a></td>'
                . '</tr>';
        }
        $cuerpo .= '</tbody></table></div>';

        return $this->pagina('Jugadores por revisar', $cuerpo);
    }

    // ═══════════════════════════ AUXILIARES ═══════════════════════════

    /**
     * partido_id => fecha_id, para linkear a "Datos complementarios"
     * (`fechas.show`): la pantalla desde donde se editan alineaciones, goles,
     * tarjetas, jueces, sustituciones y penales de cada partido.
     */
    private function mapaFechas(array $partidoIds)
    {
        $mapa = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $partidoIds))));
        foreach (array_chunk($ids, 500) as $trozo) {
            foreach (DB::table('partidos')->whereIn('id', $trozo)->select('id', 'fecha_id')->get() as $p) {
                $mapa[(int) $p->id] = (int) $p->fecha_id;
            }
        }
        return $mapa;
    }

    /** Link a las incidencias del partido. Vacío si no sabemos la fecha. */
    private function linkIncidencias($fechaId, $texto = 'Incidencias')
    {
        if (!$fechaId) return '';
        return '<a href="' . e(route('fechas.show', (int) $fechaId)) . '" target="_blank">' . e($texto) . '</a>';
    }

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

    /**
     * Estas pantallas se arman como HTML acá y se muestran dentro del layout
     * de administración (`resources/views/import/pagina.blade.php`), para tener
     * el menú de siempre. El CSS vive allá, prefijado con `.import-tm`.
     */
    private function pagina($titulo, $cuerpo)
    {
        return view('import.pagina', ['titulo' => $titulo, 'cuerpo' => $cuerpo]);
    }
}
