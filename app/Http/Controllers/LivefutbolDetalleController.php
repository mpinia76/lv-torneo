<?php

namespace App\Http\Controllers;

use App\Partido;
use App\Services\LivefutbolDetalle;
use App\Services\MinutoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Detalle del partido desde LiveFutbol: el mismo recorrido que
 * `/admin/import-detalles` para Transfermarkt (vista previa que no escribe →
 * «Guardar esto» / «Rehacer y guardar»), pero con el JSON que el bookmarklet
 * lee de la pestaña «Alineación» de livefutbol.com, porque el servidor no puede
 * bajar livefutbol (Cloudflare).
 *
 *   index()   -> el bookmarklet para arrastrar y el recuadro para pegar
 *   ver()     -> vista previa (POST: el JSON es largo para una URL)
 *   guardar() -> lo mismo, escribiendo
 *
 * Toda la lógica está en App\Services\LivefutbolDetalle.
 */
class LivefutbolDetalleController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('ok')) {
            DB::table('livefutbol_personas')->where('id', (int) $request->get('ok'))
                ->update(['revisar' => 0, 'updated_at' => now()]);
            return redirect()->route('import_detalles.livefutbol', array_filter(['partido_id' => $request->get('partido_id')]));
        }

        $partidoId = (int) $request->get('partido_id', 0);
        $cuerpo = '<p class="sub"><a href="' . e(route('import_detalles.index')) . '">← Detalle de los partidos (Transfermarkt)</a></p>'
            . '<h1>Detalle desde LiveFutbol</h1>'
            . '<p class="sub">Para los partidos que Transfermarkt trae incompletos. Hace lo mismo que el de TM: '
            . 'vista previa sin escribir nada, y después guardar (o rehacer si el partido ya tiene detalle).</p>';

        if (!LivefutbolDetalle::hayTabla()) {
            $cuerpo .= '<div class="err-box">Falta correr la migración: <code>php artisan migrate</code> '
                . '(crea <code>livefutbol_personas</code>).</div>';
        }

        $cuerpo .= $this->resumenPartido($partidoId);

        $cuerpo .= '<h2>1 · El botón (una sola vez)</h2>'
            . '<p>Arrastrá esto a la barra de favoritos: '
            . '<a class="boton" href="' . e($this->bookmarklet()) . '" onclick="alert(\'Arrastralo a la barra de favoritos y usalo en livefutbol.\');return false;">LF → La Planilla</a></p>'
            . '<h2>2 · En livefutbol</h2>'
            . '<p class="sub">Abrí el partido, pestaña <b>Alineación</b>, y tocá el favorito. Copia el partido al portapapeles.</p>'
            . '<h2>3 · Pegalo acá</h2>'
            . $this->formPegar($partidoId);

        $cuerpo .= $this->pendientesRevisar($partidoId);

        return $this->pagina('Detalle desde LiveFutbol', $cuerpo);
    }

    public function ver(Request $request)
    {
        return $this->correr($request, false);
    }

    public function guardar(Request $request)
    {
        return $this->correr($request, true);
    }

    private function correr(Request $request, $escribir)
    {
        set_time_limit(0);

        $texto = (string) $request->input('json', '');
        $lf = LivefutbolDetalle::leerJson($texto);
        $partidoId = (int) $request->input('partido_id', 0);

        if (!$lf) {
            return $this->pagina('Detalle desde LiveFutbol',
                '<p class="sub"><a href="' . e(route('import_detalles.livefutbol', array_filter(['partido_id' => $partidoId ?: null]))) . '">← Volver</a></p>'
                . '<div class="err-box">Eso no es lo que copia el botón de LiveFutbol. Tiene que empezar con '
                . '<code>{"fuente":"livefutbol"</code>. Volvé a tocar el favorito en la pestaña <b>Alineación</b> '
                . 'del partido y pegá de nuevo.</div>' . $this->formPegar($partidoId, $texto));
        }

        $servicio = new LivefutbolDetalle();

        // Sin partido: lo buscamos por fecha y clubes.
        if (!$partidoId) {
            $cands = $servicio->candidatos($lf);
            if (count($cands) === 1) {
                $partidoId = (int) $cands[0]['partido']->id;
            } else {
                return $this->pagina('Detalle desde LiveFutbol', $this->elegirPartido($lf, $cands, $texto));
            }
        }

        $r = $servicio->procesar($partidoId, $lf, [
            'escribir'        => $escribir,
            'forzar'          => (string) $request->input('forzar', '0') === '1',
            'elegir'          => (array) $request->input('elegir', []),
            'confirmar_fecha' => (string) $request->input('confirmar_fecha', '0') === '1',
        ]);

        return $this->pagina('Detalle desde LiveFutbol', $this->pantalla($r, $lf, $texto, $escribir, $request));
    }

    // ═══════════════════════════ PANTALLAS ═══════════════════════════

    private function pantalla(array $r, array $lf, $texto, $escribir, Request $request)
    {
        $partidoId = (int) $r['partido_id'];
        $p = Partido::with(['equipol', 'equipov'])->find($partidoId);

        $c = '<p class="sub"><a href="' . e(route('import_detalles.livefutbol', ['partido_id' => $partidoId])) . '">← Pegar otro</a>'
            . ' · <a href="' . e(route('import_detalles.ver', ['partido_id' => $partidoId])) . '">Ver lo de Transfermarkt</a></p>'
            . '<h1>' . ($escribir ? 'Detalle cargado' : 'Vista previa') . ' · partido #' . $partidoId . ' · LiveFutbol</h1>';

        if ($p) {
            $c .= '<p class="sub">Base: ' . e(optional($p->equipol)->nombre) . ' ' . e($p->golesl) . '-' . e($p->golesv) . ' '
                . e(optional($p->equipov)->nombre) . ' · ' . e(substr((string) $p->dia, 0, 10))
                . ' &nbsp;|&nbsp; livefutbol: ' . e($lf['local']['nombre'] ?? '?') . ' ' . e($lf['resultado']['final'] ?? '')
                . ' ' . e($lf['visitante']['nombre'] ?? '?')
                . (!empty($lf['resultado']['incidente']) ? ' (' . e($lf['resultado']['incidente']) . ')' : '')
                . (!empty($lf['url']) ? ' · <a href="' . e($lf['url']) . '" target="_blank" rel="noopener">ficha ↗</a>' : '') . '</p>';
        }

        $yaTiene = DB::table('alineacions')->where('partido_id', $partidoId)->exists();
        $forzar = $yaTiene || (string) $request->input('forzar', '0') === '1';

        if ($r['error']) {
            $c .= '<div class="err-box">' . e($r['error']) . '</div>';
        } elseif ($escribir && $r['escrito']) {
            $c .= $r['fallidas']
                ? '<div class="err-box">Guardado, pero la base rechazó <b>' . (int) $r['fallidas'] . '</b> fila(s). Mirá los avisos.</div>'
                : '<div class="ok-box">Guardado.</div>';
        }

        $plan = $r['plan'];
        $c .= '<div class="cards">'
            . $this->card(count($plan['alineacions']), 'Alineación')
            . $this->card(count($plan['gols']), 'Goles')
            . $this->card(count($plan['tarjetas']), 'Tarjetas')
            . $this->card(count($plan['cambios']), 'Cambios')
            . $this->card(count($plan['arbitros']), 'Árbitros')
            . $this->card(count($plan['tecnicos']), 'Técnicos')
            . $this->card(count($plan['plantillas']), 'A la plantilla')
            . $this->card(count($r['creados']['jugadores']), 'Jugadores nuevos', count($r['creados']['jugadores']) ? 'warn' : '')
            . '</div>';

        // ── El formulario envuelve todo: los desplegables de «¿quién es?» viajan
        // con el guardar y con el recalcular. ──
        $mostrarForm = !$escribir || !empty($r['error']);
        if ($mostrarForm && ($r['ok'] || !empty($r['personas']) || $r['fecha_distinta'])) {
            $c .= '<form method="POST" action="' . e(route('import_detalles.livefutbol_guardar')) . '">'
                . csrf_field()
                . '<input type="hidden" name="partido_id" value="' . $partidoId . '">'
                . '<textarea name="json" style="display:none">' . e($texto) . '</textarea>'
                . ($forzar ? '<input type="hidden" name="forzar" value="1">' : '');

            if ($r['fecha_distinta']) {
                $c .= '<div class="warn-box"><label><input type="checkbox" name="confirmar_fecha" value="1"'
                    . ((string) $request->input('confirmar_fecha', '0') === '1' ? ' checked' : '') . '> '
                    . 'Es este partido aunque livefutbol diga el ' . e($r['fecha_distinta']['lf'])
                    . ' y la base el ' . e($r['fecha_distinta']['base']) . '.</label></div>';
            }

            $c .= '<p class="acciones"><button type="submit" class="boton" style="background:#15714e;color:#fff;font-weight:600;border:0;padding:6px 14px">'
                . ($yaTiene ? 'Rehacer y guardar' : 'Guardar esto') . '</button> '
                . '<button type="submit" formaction="' . e(route('import_detalles.livefutbol_ver')) . '">Recalcular la vista previa</button>'
                . ($yaTiene ? ' <span class="sub">reemplaza alineación, goles, tarjetas, cambios y los roles de árbitro que vienen '
                    . '(los penales «Convirtieron» y los DTs no se tocan)</span>' : '')
                . '</p>';

            $c .= $this->tablaPersonas($r['personas'], $request);
            $c .= '</form>';
        } elseif (!empty($r['personas'])) {
            $c .= $this->tablaPersonas($r['personas'], $request, false);
        }

        if (!empty($r['avisos'])) {
            $c .= '<h2>Avisos</h2><div class="diag">';
            foreach ($r['avisos'] as $a) $c .= '<div class="warn">• ' . e($a) . '</div>';
            $c .= '</div>';
        }

        foreach (['jugadores' => 'Jugadores', 'tecnicos' => 'Técnicos', 'arbitros' => 'Árbitros'] as $k => $t) {
            if (!empty($r['creados'][$k])) {
                $c .= '<h2>' . $t . ' que no estaban en la base</h2><div class="diag">'
                    . '<p class="sub">Se crean con lo que dice su ficha de livefutbol (fecha, nacionalidad, lugar, puesto, '
                    . 'altura y foto). Quedan en «Pendientes de revisar», abajo de la pantalla de pegar.</p>';
                foreach ($r['creados'][$k] as $x) $c .= '<div>• ' . e($x) . '</div>';
                $c .= '</div>';
            }
        }

        $conMin = function ($filas) {
            foreach ($filas as &$f) {
                $f['_min'] = MinutoHelper::texto($f['minuto'] ?? null, $f['adicionado'] ?? null, '');
            }
            unset($f);
            return $filas;
        };

        if ($r['marcador']) {
            $m = $r['marcador'];
            $c .= '<p class="sub" style="margin-top:20px">Resultado según livefutbol (orientado a la base): <b>'
                . $m['golesl'] . '-' . $m['golesv'] . '</b>'
                . ($m['penalesl'] !== null ? ' (penales ' . $m['penalesl'] . '-' . $m['penalesv'] . ')' : '')
                . '. Sólo se escribe si el partido no tiene resultado.</p>';
        }

        $c .= $this->bloque('Alineación', $plan['alineacions'], ['_equipo' => 'Equipo', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', 'dorsal' => 'Dorsal', '_fuente' => 'De dónde sale el dorsal', 'orden' => 'Puesto']);
        $c .= $this->bloque('Goles', $conMin($plan['gols']), ['_min' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Clase en livefutbol']);
        $c .= $this->bloque('Tarjetas', $conMin($plan['tarjetas']), ['_min' => 'Min', '_nombre' => 'Jugador',
            '_equipo' => 'Equipo', 'tipo' => 'Tipo', '_fuente' => 'Clase en livefutbol']);
        $c .= $this->bloque('Cambios', $conMin($plan['cambios']), ['_min' => 'Min', 'tipo' => 'Tipo',
            '_nombre' => 'Jugador', '_equipo' => 'Equipo']);
        $c .= $this->bloque('Árbitros', $plan['arbitros'], ['tipo' => 'Rol', '_nombre' => 'Árbitro', '_fuente' => 'Rol en livefutbol']);
        $c .= $this->bloque('Técnicos', $plan['tecnicos'], ['_equipo' => 'Equipo', '_nombre' => 'DT', '_estado' => 'Estado']);

        $c .= '<p class="sub" style="margin-top:24px">Los penales <b>fallados</b> no salen de livefutbol (la alineación no '
            . 'los muestra): si hubo, cargalos a mano. Los convertidos sí: son goles de tipo Penal y su fila '
            . '«Convirtieron» se le crea al arquero al guardar. La tanda no se carga, igual que con TM.</p>';

        $c .= '<details><summary>JSON pegado</summary><pre>' . e(json_encode($lf,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';

        return $c;
    }

    /** Quién es quién, con el desplegable para corregir lo dudoso. */
    private function tablaPersonas(array $personas, Request $request, $editable = true)
    {
        if (!$personas) return '';
        $elegidos = (array) $request->input('elegir', []);
        $roles = ['jugador' => 'Jugador', 'tecnico' => 'DT', 'arbitro' => 'Árbitro'];

        // Primero lo que hay que mirar.
        usort($personas, function ($a, $b) {
            $pa = $a['id'] < 0 ? 0 : ($a['revisar'] ? 1 : 2);
            $pb = $b['id'] < 0 ? 0 : ($b['revisar'] ? 1 : 2);
            return $pa <=> $pb;
        });

        $dudosos = count(array_filter($personas, function ($x) { return $x['id'] < 0 || $x['revisar']; }));
        $out = '<h2>¿Quién es quién? <span class="sub">(' . count($personas) . ' personas · ' . $dudosos . ' para mirar)</span></h2>'
            . '<p class="sub">Lo que no está atado de antes se aparea por la plantilla del equipo (y el dorsal); si no, '
            . 'con la ficha de livefutbol (fecha de nacimiento + nombre, igual que con TM). En la duda se crea. Si alguno está mal, '
            . 'elegí el correcto (o escribí su #id) y tocá <b>Recalcular</b>. Lo que se guarda queda atado para la próxima.</p>'
            . '<div class="scroll"><table><thead><tr><th>Rol</th><th>Equipo</th><th>Dorsal</th><th>En livefutbol</th>'
            . '<th>En la base</th><th>Cómo</th>' . ($editable ? '<th>Cambiar</th>' : '') . '</tr></thead><tbody>';

        foreach ($personas as $x) {
            $clase = $x['id'] < 0 ? ' class="warn"' : ($x['revisar'] ? ' class="warn"' : '');
            $out .= '<tr' . $clase . '><td>' . e($roles[$x['rol']] ?? $x['rol']) . '</td>'
                . '<td>' . e($x['equipo']) . '</td>'
                . '<td class="num">' . e($x['dorsal'] ?? '') . '</td>'
                . '<td>' . e($x['nombre_lf']) . ($x['lf_id'] ? ' <span class="id">pe' . (int) $x['lf_id'] . '</span>' : '')
                . (!empty($x['ficha']) ? '<br><span class="sub">' . e($x['ficha']) . '</span>' : '') . '</td>'
                . '<td>' . ($x['id'] > 0 ? e($x['base'] ?? '') . ' <span class="id">#' . (int) $x['id'] . '</span>' : '<b>nuevo</b>') . '</td>'
                . '<td class="wrap">' . e($x['como']) . '</td>';

            if ($editable) {
                if ($x['como'] === 'ya atado a livefutbol') {
                    $out .= '<td class="gris">atado</td>';
                } else {
                    $actual = (string) ($elegidos[$x['clave']] ?? 'auto');
                    $sel = function ($v) use ($actual) { return (string) $v === $actual ? ' selected' : ''; };
                    $out .= '<td><select name="elegir[' . e($x['clave']) . ']">'
                        . '<option value="auto"' . $sel('auto') . '>como está</option>';
                    foreach ($x['opciones'] as $id => $etq) {
                        $out .= '<option value="' . (int) $id . '"' . $sel($id) . '>' . e($etq) . '</option>';
                    }
                    $out .= '<option value="nuevo"' . $sel('nuevo') . '>crear nuevo</option>';
                    // Un #id escrito a mano que no está en la lista.
                    if ($actual !== 'auto' && $actual !== 'nuevo' && !isset($x['opciones'][(int) $actual])) {
                        $out .= '<option value="' . e($actual) . '" selected>#' . e($actual) . '</option>';
                    }
                    $out .= '</select> <input type="text" size="6" placeholder="#id" '
                        . 'onchange="var s=this.previousElementSibling,v=this.value.replace(/\D/g,\'\');if(v){var o=document.createElement(\'option\');o.value=v;o.text=\'#\'+v;s.add(o);s.value=v;}"></td>';
                }
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function elegirPartido(array $lf, array $cands, $texto)
    {
        $c = '<p class="sub"><a href="' . e(route('import_detalles.livefutbol')) . '">← Volver</a></p>'
            . '<h1>¿Qué partido es?</h1>'
            . '<p class="sub">livefutbol: <b>' . e($lf['local']['nombre'] ?? '?') . ' – ' . e($lf['visitante']['nombre'] ?? '?')
            . '</b> · ' . e(substr((string) ($lf['fecha_utc'] ?? ''), 0, 10)) . ' (UTC) · ' . e($lf['ronda'] ?? '') . '</p>';

        if (!$cands) {
            $c .= '<div class="warn-box">No encontré un partido en la base con esos dos clubes ese día (±1). Puede que '
                . 'el club tenga otro nombre acá, o que el partido no esté cargado. Poné el número de partido a mano:</div>'
                . $this->formPegar(0, $texto);
            return $c;
        }

        $c .= '<div class="scroll"><table><thead><tr><th>#</th><th>Día</th><th>Partido</th><th>Torneo</th><th></th></tr></thead><tbody>';
        foreach ($cands as $k) {
            $p = $k['partido'];
            $torneo = DB::table('fechas')->join('grupos', 'grupos.id', '=', 'fechas.grupo_id')
                ->join('torneos', 'torneos.id', '=', 'grupos.torneo_id')->where('fechas.id', $p->fecha_id)
                ->value(DB::raw("CONCAT(torneos.nombre, ' ', torneos.year)"));
            $c .= '<tr><td class="num">' . (int) $p->id . '</td><td class="num">' . e(substr((string) $p->dia, 0, 16)) . '</td>'
                . '<td>' . e(optional($p->equipol)->nombre) . ' ' . e($p->golesl) . '-' . e($p->golesv) . ' ' . e(optional($p->equipov)->nombre)
                . ($k['invertido'] ? ' <span class="warn">(localía al revés)</span>' : '') . '</td>'
                . '<td>' . e($torneo) . '</td>'
                . '<td><form method="POST" action="' . e(route('import_detalles.livefutbol_ver')) . '" style="margin:0">' . csrf_field()
                . '<input type="hidden" name="partido_id" value="' . (int) $p->id . '">'
                . '<textarea name="json" style="display:none">' . e($texto) . '</textarea>'
                . '<button type="submit">Es éste</button></form></td></tr>';
        }
        return $c . '</tbody></table></div>';
    }

    private function formPegar($partidoId, $texto = '')
    {
        return '<form method="POST" action="' . e(route('import_detalles.livefutbol_ver')) . '">' . csrf_field()
            . '<p><label>Partido # <input type="text" name="partido_id" size="8" value="' . ($partidoId ?: '') . '"></label> '
            . '<span class="sub">vacío = lo busco por fecha y clubes</span></p>'
            . '<textarea name="json" rows="8" style="width:100%;font:12px monospace" placeholder=\'{"fuente":"livefutbol", …}\'>'
            . e($texto) . '</textarea>'
            . '<p class="acciones"><button type="submit">Vista previa</button> <span class="sub">no escribe nada</span></p></form>';
    }

    private function resumenPartido($partidoId)
    {
        if (!$partidoId) return '';
        $p = Partido::with(['equipol', 'equipov'])->find($partidoId);
        if (!$p) return '<div class="err-box">No existe el partido #' . (int) $partidoId . '.</div>';

        $lf = \App\Services\LivefutbolPartido::hayTablas()
            ? DB::table('partido_livefutbol')->where('partido_id', $p->id)->value('match_id') : null;

        return '<div class="diag">Partido <b>#' . (int) $p->id . '</b>: ' . e(optional($p->equipol)->nombre) . ' '
            . e($p->golesl) . '-' . e($p->golesv) . ' ' . e(optional($p->equipov)->nombre) . ' · '
            . e(substr((string) $p->dia, 0, 10))
            . ' · <a href="' . e(route('controles.livefutbol', $p->id)) . '" target="_blank" rel="noopener">'
            . ($lf ? 'abrirlo en livefutbol ↗' : 'buscarlo en livefutbol ↗') . '</a></div>';
    }

    private function pendientesRevisar($partidoId)
    {
        if (!LivefutbolDetalle::hayTabla()) return '';
        $filas = DB::table('livefutbol_personas')->where('revisar', 1)->orderBy('id', 'desc')->limit(200)->get();
        if ($filas->isEmpty()) return '';

        $tablas = ['jugador' => 'jugadors', 'tecnico' => 'tecnicos', 'arbitro' => 'arbitros'];
        $rutas  = ['jugador' => 'jugadores.edit', 'tecnico' => 'tecnicos.edit', 'arbitro' => 'arbitros.edit'];

        $out = '<h2>Pendientes de revisar <span class="sub">(' . $filas->count() . ')</span></h2>'
            . '<p class="sub">Altas y apareos por nombre que hizo este importador. Las altas salen sin fecha de nacimiento ni nacionalidad '
            . 'si no se pudo leer su ficha de livefutbol: completalas. Si un apareo está mal, editá la ficha y '
            . 'después corregí el partido.</p><div class="scroll"><table><thead><tr><th>Rol</th><th>En livefutbol</th>'
            . '<th>En la base</th><th>Cómo</th><th></th></tr></thead><tbody>';

        foreach ($filas as $f) {
            $t = $tablas[$f->rol] ?? null;
            if (!$t) continue;
            $p = DB::table($t)->join('personas', 'personas.id', '=', $t . '.persona_id')->where($t . '.id', $f->ref_id)
                ->select('personas.apellido', 'personas.nombre', 'personas.nacimiento', 'personas.nacionalidad')->first();
            $link = \Illuminate\Support\Facades\Route::has($rutas[$f->rol]) ? route($rutas[$f->rol], $f->ref_id) : null;
            $out .= '<tr><td>' . e($f->rol) . '</td><td>' . e($f->nombre_lf) . ' <span class="id">pe' . (int) $f->lf_id . '</span></td>'
                . '<td>' . ($p ? e(trim($p->apellido . ', ' . $p->nombre, ', ')) . ' <span class="id">#' . (int) $f->ref_id
                    . ' · ' . e($p->nacimiento ? substr($p->nacimiento, 0, 10) : 'sin fecha') . ' · ' . e($p->nacionalidad) . '</span>'
                    : '<span class="err">ficha borrada</span>') . '</td>'
                . '<td>' . e($f->origen) . '</td>'
                . '<td>' . ($link ? '<a href="' . e($link) . '" target="_blank">editar ↗</a> · ' : '')
                . '<a href="' . e(route('import_detalles.livefutbol', array_filter(['ok' => $f->id, 'partido_id' => $partidoId ?: null]))) . '">está bien</a></td></tr>';
        }
        return $out . '</tbody></table></div>';
    }

    /** El bookmarklet listo para arrastrar, desde public/js/lf-a-laplanilla.js. */
    private function bookmarklet()
    {
        $js = @file_get_contents(public_path('js/lf-a-laplanilla.js'));
        if ($js === false) return '#';
        // Los comentarios de línea romperían el código al juntar las líneas:
        // %0A los mantiene como saltos, así que alcanza con codificar.
        return 'javascript:' . rawurlencode($js);
    }

    // ═══════════════════════════ HTML ═══════════════════════════

    private function card($n, $label, $tono = '')
    {
        return '<div class="card ' . $tono . '"><b>' . (int) $n . '</b><span>' . e($label) . '</span></div>';
    }

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
            $out .= '<tr' . (!empty($f['_dudoso']) ? ' class="warn"' : '') . '>';
            foreach ($columnas as $k => $t) {
                $v = $f[$k] ?? null;
                $num = in_array($k, ['_min', 'orden', 'dorsal'], true) ? ' class="num"' : '';
                $out .= '<td' . $num . '>' . e($v === null || $v === '' ? '—' : (string) $v) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table></div>';
    }

    private function pagina($titulo, $cuerpo)
    {
        return view('import.pagina', ['titulo' => $titulo, 'cuerpo' => $cuerpo]);
    }
}
