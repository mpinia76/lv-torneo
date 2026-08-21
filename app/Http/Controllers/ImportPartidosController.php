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
            . '<p class="sub">' . $tecnicos->count() . ' DTs con URL de Transfermarkt. '
            . '«Sondear» baja sus partidos, aprende el mapeo de clubes y los deja en staging. '
            . '«Aplicar» crea los que faltan.</p>'
            . '<form method="get" style="margin:12px 0"><input name="q" value="' . e($q) . '" placeholder="buscar DT…" size="30"> <button>Buscar</button></form>'
            . '<div class="scroll"><table><thead><tr><th>DT</th><th>Ya cargados</th><th>Nuevos</th><th>Conflictos</th><th>Aplicados</th><th></th></tr></thead>'
            . '<tbody>' . $filas . '</tbody></table></div>';

        return $this->pagina('Carga de partidos', $html);
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
        if ($tecnicoId) {
            $tecnico = \App\Tecnico::with('persona')->find($tecnicoId);
            if (!$tecnico) return $this->pagina('Sondeo', '<p class="err">No existe el técnico #' . (int) $tecnicoId . '</p>');
            $nombreDT = optional($tecnico->persona)->name ?: ('DT #' . $tecnico->id);
            if ($url === '') $url = (string) $tecnico->transfermarkt_url;
        }
        if ($url === '') {
            return $this->pagina('Sondeo', '<p class="err">Falta <code>?tecnico_id=</code> o <code>?url=</code>.</p>');
        }
        if (!preg_match('#/trainer/(\d+)#', $url, $m)) {
            return $this->pagina('Sondeo', '<p class="err">La URL no tiene el formato <code>.../trainer/{id}</code>: ' . e($url) . '</p>');
        }
        $coachId = $m[1];

        $games = $this->traerPartidos($coachId);
        if (is_string($games)) return $this->pagina('Sondeo', '<p class="err">' . $games . '</p>');

        $filas = [];
        $temporadas = [];
        foreach ($games as $g) {
            $f = $this->normalizar($g, $coachId);
            if ($f['temporada'] !== null) $temporadas[] = (int) $f['temporada'];
            $filas[] = $f;
        }
        $filas = $this->completarNombres($filas);
        $filas = $this->clasificar($filas, $desde);

        $aprendidos = [];
        if ($aprender) {
            $aprendidos = $this->aprenderMapeos($filas);
            if (!empty($aprendidos)) $filas = $this->clasificar($filas, $desde);
        }

        $cont = ['total' => count($filas), 'excluido' => 0, 'duplicado' => 0,
                 'falta_dt' => 0, 'nuevo' => 0, 'conflicto' => 0];
        foreach ($filas as $f) {
            if (isset($cont[$f['estado']])) $cont[$f['estado']]++;
            if ($f['estado'] === 'duplicado' && $f['motivo'] === 'ya cargado, le falta el DT') $cont['falta_dt']++;
        }

        $guardadas = 0;
        if ($guardar) {
            foreach ($filas as $f) {
                if ($f['estado'] === 'excluido') continue;
                $guardadas += $this->persistir($f, $coachId, $tecnicoId) ? 1 : 0;
            }
        }

        sort($temporadas);
        $rango = empty($temporadas) ? '?' : (reset($temporadas) . ' – ' . end($temporadas));

        $html  = '<p class="sub"><a href="' . e(route('import_partidos.index')) . '">← Todos los DTs</a></p>';
        $html .= '<h1>Sondeo · ' . e($nombreDT ?: ('coach ' . $coachId)) . '</h1>';
        $html .= '<p class="sub">coach ' . e($coachId) . ' · ' . $cont['total'] . ' partidos · temporadas ' . e($rango) . ' · corte en ' . $desde . '</p>';

        foreach ($avisos as $a) $html .= '<p class="ok-box">' . $a . '</p>';

        $html .= '<div class="cards">'
            . $this->card($cont['total'], 'partidos')
            . $this->card($cont['excluido'], 'fuera de alcance', 'gris')
            . $this->card($cont['duplicado'], 'ya cargados', 'ok')
            . $this->card($cont['falta_dt'], 'sin el DT', 'warn')
            . $this->card($cont['nuevo'], 'nuevos a crear', 'ok')
            . $this->card($cont['conflicto'], 'conflictos', $cont['conflicto'] ? 'err' : 'ok')
            . '</div>';

        $base = $this->urlBase($request);
        $html .= '<p class="acciones">'
            . '<a href="' . e($base . '&aprender=1&guardar=1') . '">Aprender mapeo y guardar</a> · '
            . '<a href="' . e($base . '&estado=conflicto&limite=300') . '">Ver solo conflictos</a> · '
            . '<a href="' . e($base . '&estado=nuevo&limite=300') . '">Ver solo nuevos</a>';
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

        $html .= $this->bloqueClubesSinResolver($filas, $request);

        $titulo = $filtro !== '' ? ('Partidos con estado «' . e($filtro) . '»') : ('Primeros ' . $limite . ' partidos');
        $html .= '<h2>' . $titulo . '</h2>' . $this->tabla($filas, $limite, $filtro);
        $html .= '<h2>Estructura del JSON</h2>' . $this->diagnosticar($games[0]);

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
                . '<a href="' . e(route('import_partidos.sondear', ['tecnico_id' => $tecnicoId])) . '">Volver al sondeo</a></p>';

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
        $pendientes = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'nuevo')
            ->orderBy('dia')->get();

        $faltaDt = DB::table('import_partidos')
            ->where('tecnico_id', $tecnicoId)->where('estado', 'duplicado')
            ->where('motivo', 'like', '%falta el DT%')->count();

        $html = $volver . '<h1>Aplicar partidos · ' . e($nombreDT) . '</h1>';

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
                               'desde' => $r->dia, 'hasta' => $r->dia, 'equipos' => []];
            }
            $grupos[$k]['n']++;
            if ($r->dia < $grupos[$k]['desde']) $grupos[$k]['desde'] = $r->dia;
            if ($r->dia > $grupos[$k]['hasta']) $grupos[$k]['hasta'] = $r->dia;
            if ($r->club_nombre) $grupos[$k]['equipos'][$r->club_nombre] = true;
        }

        $torneos = \App\Torneo::orderBy('year', 'desc')->orderBy('nombre')->get();

        $html .= '<p class="sub">Cada competencia+temporada va a un torneo. Elegí uno existente o creá uno nuevo '
              . '(se crea marcado como <b>parcial</b>: no entra en tablas de posiciones ni promedios).</p>'
              . '<div class="scroll"><table><thead><tr><th>Competencia</th><th>Temp.</th><th>Partidos</th><th>Período</th><th>Equipo(s)</th><th>Torneo destino</th></tr></thead><tbody>';

        foreach ($grupos as $g) {
            $opts = '<option value="nuevo">— crear torneo nuevo —</option>';
            foreach ($torneos as $t) {
                $sel = ($this->normalizaTexto($t->nombre) === $this->normalizaTexto($g['nombre'])
                        && (string) $t->year === (string) $g['temp']) ? ' selected' : '';
                $opts .= '<option value="' . $t->id . '"' . $sel . '>' . e($t->nombre . ' ' . $t->year) . '</option>';
            }
            $html .= '<tr>'
                . '<td>' . e($g['nombre'] ?: ('#' . $g['comp'])) . '</td>'
                . '<td class="num">' . e($g['temp']) . '</td>'
                . '<td class="num">' . $g['n'] . '</td>'
                . '<td class="num">' . e(substr($g['desde'], 0, 10)) . ' → ' . e(substr($g['hasta'], 0, 10)) . '</td>'
                . '<td>' . e(implode(', ', array_keys($g['equipos']))) . '</td>'
                . '<td><form method="get" action="' . e(route('import_partidos.aplicar')) . '">'
                . '<input type="hidden" name="tecnico_id" value="' . $tecnicoId . '">'
                . '<input type="hidden" name="comp" value="' . e($g['comp']) . '">'
                . '<input type="hidden" name="temp" value="' . e($g['temp']) . '">'
                . '<input type="hidden" name="confirmar" value="1">'
                . '<select name="torneo_id">' . $opts . '</select> <button>Aplicar ' . $g['n'] . '</button>'
                . '</form></td></tr>';
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

        // 1. Torneo
        if ($torneoId === 'nuevo' || $torneoId === '') {
            $nombreTorneo = $primera->competencia_nombre ?: ('Competencia ' . $comp);
            list($tipo, $ambito) = $this->clasificarCompetencia($nombreTorneo);
            $torneo = new \App\Torneo();
            $torneo->forceFill([
                'nombre'     => $nombreTorneo,
                'year'       => $temp,
                'equipos'    => 0,
                'grupos'     => 1,
                'tipo'       => $tipo,
                'ambito'     => $ambito,
                'url_nombre' => Str::slug($nombreTorneo . '-' . $temp),
                'parcial'    => 1,
            ])->save();
            $grupo = new \App\Grupo();
            $grupo->forceFill(['nombre' => 'Único', 'torneo_id' => $torneo->id, 'equipos' => 0])->save();
            $grupoId = $grupo->id;
        } else {
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

                $local = (int) $r->local === 1;
                $equipolId = $local ? (int) $r->equipo_id : (int) $r->rival_id;
                $equipovId = $local ? (int) $r->rival_id : (int) $r->equipo_id;
                $golesl    = $local ? (int) $r->goles_favor : (int) $r->goles_contra;
                $golesv    = $local ? (int) $r->goles_contra : (int) $r->goles_favor;

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

                $detalle .= '<tr><td class="num">' . e(substr($r->dia, 0, 10)) . '</td><td class="num">' . e($numero) . '</td>'
                    . '<td>' . e($r->club_nombre) . '</td><td class="num">' . $golesl . ':' . $golesv . '</td>'
                    . '<td>' . e($r->rival_nombre) . '</td><td class="num">#' . $partido->id . '</td></tr>';
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
            $html .= '<div class="scroll"><table><thead><tr><th>Fecha</th><th>Fecha nº</th><th>Local</th><th>Res.</th><th>Visitante</th><th>Partido</th></tr></thead><tbody>'
                . $detalle . '</tbody></table></div>';
        }
        $html .= '<p class="acciones"><a class="boton" href="' . e(route('import_partidos.aplicar', ['tecnico_id' => $tecnicoId])) . '">Seguir con el resto →</a></p>';

        return $this->pagina('Aplicar partidos', $html);
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

            if ($partido) {
                $filas[$i]['partido_id'] = $partido->id;
                $filas[$i]['estado'] = 'duplicado';
                $tieneDt = DB::table('partido_tecnicos')
                    ->where('partido_id', $partido->id)->where('equipo_id', $equipoId)->exists();
                $filas[$i]['motivo'] = $tieneDt ? 'ya cargado' : 'ya cargado, le falta el DT';
            } elseif (!$equipoId || !$rivalId) {
                $filas[$i]['estado'] = 'conflicto';
                $faltan = [];
                if (!$equipoId) $faltan[] = 'club «' . $f['club_nombre'] . '»';
                if (!$rivalId)  $faltan[] = 'rival «' . $f['rival_nombre'] . '»';
                $filas[$i]['motivo'] = 'sin mapear: ' . implode(' / ', $faltan);
            } else {
                $filas[$i]['estado'] = 'nuevo';
            }
        }
        return $filas;
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

    private function mapaNombres()
    {
        $mapa = [];
        foreach (\App\Equipo::select('id', 'nombre')->get() as $e) {
            foreach ($this->clavesNombre($e->nombre) as $k) {
                if ($k !== '' && !isset($mapa[$k])) $mapa[$k] = $e->id;
            }
        }
        return $mapa;
    }

    private function resolverClub($tmId, $nombre, array $mapaTm, array $mapaNombres)
    {
        if ($tmId !== null && isset($mapaTm[(string) $tmId])) return $mapaTm[(string) $tmId];
        foreach ($this->clavesNombre($nombre) as $k) {
            if ($k !== '' && isset($mapaNombres[$k])) return $mapaNombres[$k];
        }
        return null;
    }

    private function clavesNombre($nombre)
    {
        $nombre = (string) $nombre;
        if (trim($nombre) === '') return [];
        $base = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        if ($base === false) $base = $nombre;
        $base = mb_strtolower($base);
        $sinParentesis = preg_replace('/\([^)]*\)/', ' ', $base);

        $claves = [];
        foreach ([$base, $sinParentesis] as $variante) {
            $claves[] = $this->soloLetras($variante);
            $claves[] = $this->soloLetras($this->quitarPrefijos($variante));
        }
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

        $club  = isset($ci['club']) ? $ci['club'] : [];
        $rival = isset($ci['opponent']) ? $ci['opponent'] : [];
        $local = true;
        if ((string) $this->valor($rival, ['coachId']) === (string) $coachId
            && (string) $this->valor($club, ['coachId']) !== (string) $coachId) {
            $club  = isset($ci['opponent']) ? $ci['opponent'] : [];
            $rival = isset($ci['club']) ? $ci['club'] : [];
            $local = false;
        }

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
            'equipo_id'               => null,
            'rival_id'                => null,
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

        // Una fila ya aplicada no se pisa.
        $yaAplicada = DB::table('import_partidos')->where($clave)->where('estado', 'aplicado')->exists();
        if ($yaAplicada) return false;

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
        unset($q['guardar'], $q['aprender'], $q['estado'], $q['limite'],
              $q['mapear_tm'], $q['mapear_equipo'], $q['mapear_nombre']);
        return $request->url() . '?' . http_build_query($q);
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

        $opciones = '';
        foreach (\App\Equipo::select('id', 'nombre')->orderBy('nombre')->get() as $e) {
            $opciones .= '<option value="' . $e->id . ' · ' . e($e->nombre) . '"></option>';
        }

        $out = '<h2>Clubes sin mapear <span class="sub">(' . count($pend) . ')</span></h2>'
             . '<p class="sub">Escribí el equipo y guardá: queda mapeado por su id de Transfermarkt y no se vuelve a preguntar nunca más. '
             . 'Si el club no existe en tu base, crealo primero desde Equipos.</p>'
             . '<datalist id="eqs">' . $opciones . '</datalist>'
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
                 . '<input name="mapear_equipo" list="eqs" placeholder="buscar equipo…" size="28">'
                 . ' <button>Mapear</button></form></td></tr>';
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
            . '<th>Fecha</th><th>Competencia</th><th>Temp.</th><th>Fecha nº</th><th>Club</th><th></th><th>Rival</th>'
            . '<th>Res.</th><th>gameId</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>';
        $n = 0;
        foreach ($filas as $f) {
            if ($filtro !== '' && $f['estado'] !== $filtro) continue;
            if ($n++ >= $limite) break;
            $clase = $f['estado'] === 'nuevo' ? 'ok' : ($f['estado'] === 'conflicto' ? 'err' : ($f['estado'] === 'excluido' ? 'gris' : ''));
            $out .= '<tr class="' . $clase . '">'
                . '<td class="num">' . e($f['dia'] ? substr($f['dia'], 0, 10) : '—') . '</td>'
                . '<td>' . e($f['competencia_nombre'] ?: ('#' . $f['competencia_external_id'])) . '</td>'
                . '<td class="num">' . e($f['temporada']) . '</td>'
                . '<td class="num">' . e($f['ronda']) . '</td>'
                . '<td>' . e($f['club_nombre']) . ($f['equipo_id'] ? ' <span class="id">#' . $f['equipo_id'] . '</span>' : '') . '</td>'
                . '<td class="num">' . ($f['local'] ? 'L' : 'V') . '</td>'
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
            .boton{display:inline-block;background:#15714e;color:#fff;padding:5px 12px;text-decoration:none}
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
        return response('<!doctype html><meta charset="utf-8"><title>' . e($titulo) . '</title><style>' . $css . '</style>' . $cuerpo);
    }
}
