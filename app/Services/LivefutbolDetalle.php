<?php

namespace App\Services;

use App\Arbitro;
use App\Partido;
use App\Persona;
use App\Tecnico;
use App\Jugador;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Detalle de un partido desde livefutbol.com: alineaciones, goles, tarjetas,
 * cambios, árbitros y DTs. Es el hermano de TmDetallePartido para cuando
 * Transfermarkt trae el partido incompleto.
 *
 * livefutbol está detrás de Cloudflare y el servidor no lo puede bajar: el
 * usuario corre el bookmarklet (`public/js/lf-a-laplanilla.js`) en la pestaña
 * «Alineación» del partido y pega el JSON en el admin. Formato: ver el doc
 * `claude/livefutbol-importador-traspaso.md` del proyecto.
 *
 * Mismo contrato que TmDetallePartido::importar():
 *   · con escribir=false NO toca la base: devuelve el plan (los que se
 *     crearían llevan id negativo);
 *   · si el partido ya tiene alineación, sólo se reescribe con forzar
 *     («Rehacer y guardar»), y rehacer no puede vaciar un equipo que la base
 *     tiene cargado;
 *   · se guarda con TmDetallePartido::grabarPlanAjeno(), o sea exactamente igual
 *     que el de TM (plantillas, filas de a una, terna, DT, penales convertidos).
 *
 * Lo que cambia es CÓMO se reconoce a cada persona. livefutbol no da fecha de
 * nacimiento, así que el criterio de TM (fecha + palabras) no aplica. En orden:
 *
 *   1. `livefutbol_personas`: el id de livefutbol ya atado → exacto.
 *   2. Lo que eligió el usuario en la vista previa.
 *   3. Jugadores: la PLANTILLA del equipo en el torneo. Es un universo chico
 *      (25-40 personas) y el dorsal desempata. DTs y árbitros: los que ya
 *      estén cargados en ESTE partido.
 *   4. Toda la base, estricto: todas las palabras del nombre de livefutbol
 *      tienen que estar en la ficha y tiene que coincidir un apellido (regla
 *      del usuario: nunca sólo por nombre de pila). Uno solo → se usa y queda
 *      para revisar. Más de uno → no se adivina: se crea, salvo que el usuario
 *      elija uno en la vista previa.
 *   5. Nada → se crea, para revisar.
 *
 * «Un apareo equivocado es peor que un duplicado»: en la duda se crea, y el
 * duplicado lo levanta /admin/verificarPersonas.
 *
 * FICHA DE LIVEFUTBOL (LivefutbolPersona): para todo el que no esté atado ni
 * en la plantilla se baja su ficha (una vez; queda en caché), que trae fecha de
 * nacimiento, nacionalidad, lugar, puesto, altura y foto. Con fecha se aplica
 * el criterio de Transfermarkt en vez del paso 4:
 *   · misma fecha + 2 o más palabras (con un apellido) → es él
 *   · misma fecha + 1 palabra → no se une, se avisa
 *   · fecha distinta → descartado
 *   · la base sin fecha + nombre completo → se usa, para revisar
 * y lo que se crea sale con esos datos (y la foto) en vez de vacío.
 */
class LivefutbolDetalle
{
    /** @var TmDetallePartido */
    private $tm;

    private $avisos = [];
    private $proximoPreview = -1;

    /** Lo que se resolvió de cada persona, para la vista previa. */
    private $personas = [];

    /** clave "rol:lf_id" => 'nuevo' | id elegido en la vista previa. */
    private $elegidos = [];

    private $escribir = false;

    /** Personas sin ficha (el JSON no la trajo ni estaba en caché). */
    private $sinFicha = 0;

    /** lf_id => ficha cruda del JSON / ficha normalizada ya usada. */
    private $fichasJson = [];
    private $fichas = [];

    /**
     * Fotos que se repiten en más de una persona del partido: es la silueta
     * de «sin foto» de livefutbol (el favorito no la distingue de una foto de
     * verdad), y no se baja.
     */
    private $fotosDeRelleno = [];

    /** Mapeos a grabar al final: [rol, lf_id, ref_id, nombre, origen, revisar]. */
    private $mapeos = [];

    /** Personas que se crearon (o se crearían). */
    private $creados = ['jugadores' => [], 'tecnicos' => [], 'arbitros' => []];

    /** Palabras que no dicen nada del club ("FC", "Club"...). */
    private static $relleno = ['fc', 'cf', 'club', 'cd', 'sc', 'ac', 'us', 'as', 'sv', 'fk', 'sk', 'cs',
        'ca', 'ud', 'rc', 'sd', 'afc', 'bk', 'if', 'ik', 'tsv', 'vfb', 'vfl', 'fsv', 'ssc', 'sporting',
        'deportivo', 'atletico', 'asociacion', 'futbol', 'football', 'calcio', 'olympique', 'stade', 'real'];

    public function __construct()
    {
        $this->tm = new TmDetallePartido();
    }

    public static function hayTabla(): bool
    {
        return Schema::hasTable('livefutbol_personas');
    }

    /**
     * Lee lo que se pegó. Acepta el JSON tal cual o con basura alrededor (un
     * «Copiá esto:» de más, comillas).
     *
     * @return array|null
     */
    public static function leerJson($texto)
    {
        $texto = trim((string) $texto);
        if ($texto === '') return null;

        $ini = strpos($texto, '{');
        $fin = strrpos($texto, '}');
        if ($ini === false || $fin === false || $fin <= $ini) return null;

        $data = json_decode(substr($texto, $ini, $fin - $ini + 1), true);
        if (!is_array($data) || ($data['fuente'] ?? '') !== 'livefutbol' || empty($data['equipos'])) {
            return null;
        }
        return $data;
    }

    /**
     * Partidos de la base que pueden ser éste: misma fecha (±1 día, por la hora
     * UTC) y los dos clubes parecidos. Para cuando se pega sin partido_id.
     *
     * @return array [['partido' => Partido, 'invertido' => bool, 'puntos' => float], ...]
     */
    public function candidatos(array $lf)
    {
        $out = [];

        // Primero, si el match_id ya está atado a un partido, es ése.
        if (!empty($lf['lf_match_id']) && LivefutbolPartido::hayTablas()) {
            $pid = DB::table('partido_livefutbol')->where('match_id', (int) $lf['lf_match_id'])->value('partido_id');
            if ($pid && ($p = Partido::find($pid))) {
                return [['partido' => $p, 'invertido' => false, 'puntos' => 999, 'por' => 'match_id ya atado']];
            }
        }

        $dia = $this->diaLf($lf);
        if (!$dia) return $out;

        $partidos = Partido::with(['equipol', 'equipov'])
            ->whereBetween('dia', [$dia->copy()->subDay()->startOfDay(), $dia->copy()->addDay()->endOfDay()])
            ->limit(400)->get();

        foreach ($partidos as $p) {
            $o = $this->orientacion($p, $lf);
            if ($o['ok']) {
                $out[] = ['partido' => $p, 'invertido' => $o['invertido'], 'puntos' => $o['puntos'], 'por' => 'fecha y clubes'];
            }
        }

        usort($out, function ($a, $b) { return $b['puntos'] <=> $a['puntos']; });
        return $out;
    }

    /**
     * @param int   $partidoId
     * @param array $lf    el JSON del bookmarklet, ya decodificado
     * @param array $opts  escribir, forzar, elegir (array), confirmar_fecha
     */
    public function procesar($partidoId, array $lf, array $opts = [])
    {
        $this->escribir = !empty($opts['escribir']);
        $forzar         = !empty($opts['forzar']);
        $this->elegidos = isset($opts['elegir']) && is_array($opts['elegir']) ? $opts['elegir'] : [];
        $this->avisos = [];
        $this->personas = [];
        $this->mapeos = [];
        $this->creados = ['jugadores' => [], 'tecnicos' => [], 'arbitros' => []];
        $this->proximoPreview = -1;
        $this->sinFicha = 0;
        $this->fichas = [];
        $this->fichasJson = [];
        foreach (['local', 'visitante'] as $l) {
            foreach (['titulares', 'suplentes', 'cuerpo_tecnico'] as $k) {
                foreach (($lf['equipos'][$l][$k] ?? []) as $x) {
                    if (!empty($x['lf_id']) && !empty($x['ficha'])) $this->fichasJson[(int) $x['lf_id']] = $x['ficha'];
                }
            }
        }
        foreach (($lf['arbitros'] ?? []) as $x) {
            if (!empty($x['lf_id']) && !empty($x['ficha'])) $this->fichasJson[(int) $x['lf_id']] = $x['ficha'];
        }
        $cuenta = [];
        foreach ($this->fichasJson as $f) {
            if (!empty($f['foto'])) $cuenta[$f['foto']] = ($cuenta[$f['foto']] ?? 0) + 1;
        }
        $this->fotosDeRelleno = array_keys(array_filter($cuenta, function ($n) { return $n > 1; }));

        $informe = [
            'ok' => false, 'escrito' => false, 'error' => null, 'partido_id' => (int) $partidoId,
            'avisos' => [], 'fallidas' => 0, 'fecha_distinta' => null,
            'plan' => ['alineacions' => [], 'gols' => [], 'tarjetas' => [], 'cambios' => [],
                'penals' => [], 'arbitros' => [], 'tecnicos' => [], 'plantillas' => []],
            'creados' => $this->creados, 'personas' => [], 'marcador' => null,
        ];

        if (!self::hayTabla()) {
            $informe['error'] = 'Falta correr la migración de livefutbol_personas (php artisan migrate).';
            return $informe;
        }

        $partido = Partido::with(['equipol', 'equipov', 'fecha.grupo'])->find($partidoId);
        if (!$partido) {
            $informe['error'] = 'No existe el partido #' . (int) $partidoId . '.';
            return $informe;
        }

        // ── El match_id de livefutbol no puede ser de otro partido ─────────
        $matchId = !empty($lf['lf_match_id']) ? (int) $lf['lf_match_id'] : null;
        if ($matchId && LivefutbolPartido::hayTablas()) {
            $otro = DB::table('partido_livefutbol')->where('match_id', $matchId)
                ->where('partido_id', '!=', $partido->id)->value('partido_id');
            if ($otro) {
                $informe['error'] = 'Ese partido de livefutbol (ma' . $matchId . ') ya está atado al partido #'
                    . $otro . ', no a éste. Si el atado está mal, corregilo desde el botón LF de los controles.';
                return $informe;
            }
        }

        // ── Clubes ─────────────────────────────────────────────────────────
        $o = $this->orientacion($partido, $lf);
        if (!$o['ok']) {
            $informe['error'] = 'Los clubes no coinciden. Base: ' . $this->nombreEquipo($partido->equipol_id)
                . ' vs ' . $this->nombreEquipo($partido->equipov_id) . ' · livefutbol: '
                . ($lf['local']['nombre'] ?? '?') . ' vs ' . ($lf['visitante']['nombre'] ?? '?')
                . '. ¿Es el partido correcto?';
            return $informe;
        }
        if ($o['invertido']) {
            $this->aviso('Ojo: en la base el local es ' . $this->nombreEquipo($partido->equipol_id)
                . ' y en livefutbol es ' . $this->nombreEquipo($partido->equipov_id)
                . '. Uso cada jugador con su club, no por localía.');
        }
        $lados = [
            ['clave' => 'local',     'equipo_id' => (int) ($o['invertido'] ? $partido->equipov_id : $partido->equipol_id)],
            ['clave' => 'visitante', 'equipo_id' => (int) ($o['invertido'] ? $partido->equipol_id : $partido->equipov_id)],
        ];
        foreach ($lados as &$l) {
            $l['equipo_nombre'] = $this->nombreEquipo($l['equipo_id']);
            $l['lf_team'] = $lf[$l['clave']]['lf_id'] ?? null;
        }
        unset($l);

        // ── Fecha ──────────────────────────────────────────────────────────
        $diaLf = $this->diaLf($lf);
        if ($diaLf && $partido->dia) {
            $nuestra = Carbon::parse($partido->dia)->startOfDay();
            $dif = (int) round(abs($diaLf->copy()->startOfDay()->diffInDays($nuestra, false)));
            if ($dif > 1) {
                $informe['fecha_distinta'] = ['lf' => $diaLf->format('d/m/Y'), 'base' => $nuestra->format('d/m/Y')];
                if (empty($opts['confirmar_fecha'])) {
                    $msg = 'Las fechas no coinciden: livefutbol dice ' . $diaLf->format('d/m/Y') . ' y el partido es del '
                        . $nuestra->format('d/m/Y') . '. Los mismos dos clubes pueden haberse cruzado más de una vez; '
                        . 'confirmá que es éste antes de guardar.';
                    if ($this->escribir) {
                        $informe['error'] = $msg;
                        return $informe;
                    }
                    $this->aviso($msg);
                }
            }
        }

        // ── Ya tiene detalle ───────────────────────────────────────────────
        $yaCargado = DB::table('alineacions')->where('partido_id', $partido->id)->count();
        if ($yaCargado && !$forzar) {
            if ($this->escribir) {
                $informe['error'] = 'El partido #' . $partido->id . ' ya tiene alineación cargada (' . $yaCargado
                    . ' jugadores). Para reemplazarla usá "Rehacer y guardar".';
                return $informe;
            }
            $this->aviso('Este partido ya tiene detalle cargado (' . $yaCargado . ' en la alineación). '
                . 'Lo de abajo es lo que quedaría si lo rehacés: se borra lo actual y se escribe esto. '
                . 'Los técnicos del partido no se borran nunca.');
        }

        // ── Plan ───────────────────────────────────────────────────────────
        $grupoId  = optional($partido->fecha)->grupo_id;
        $torneoId = optional(optional($partido->fecha)->grupo)->torneo_id;
        $mapaDorsales = $this->tm->dorsalesPlantilla($partido, $lados);
        $plan = $informe['plan'];

        try {
            foreach ($lados as $iLado => $lado) {
                $equipoId = $lado['equipo_id'];
                $otroNombre = $lados[$iLado === 0 ? 1 : 0]['equipo_nombre'];
                $eq = $lf['equipos'][$lado['clave']] ?? [];
                $plantilla = $this->plantillaDe($torneoId, $equipoId);

                $titulares = []; $entraron = []; $salieron = [];
                $filas = [];
                foreach (['titulares' => 'Titular', 'suplentes' => 'Suplente'] as $k => $tipo) {
                    foreach (($eq[$k] ?? []) as $j) {
                        $j['_tipo'] = $tipo;
                        $filas[] = $j;
                    }
                }

                foreach ($filas as $j) {
                    $nombreLf = trim((string) ($j['nombre'] ?? ''));
                    if ($nombreLf === '') continue;
                    $dorsalLf = isset($j['dorsal']) && is_numeric($j['dorsal']) ? (int) $j['dorsal'] : null;

                    $jugadorId = $this->resolverJugador($j, $dorsalLf, $plantilla, $lado);
                    if (!$jugadorId) {
                        $this->aviso('Alineación: no pude resolver a "' . $nombreLf . '" (' . $lado['equipo_nombre'] . '), lo salteo.');
                        continue;
                    }
                    $nombre = $this->nombreJugador($jugadorId, $nombreLf);
                    if ($j['_tipo'] === 'Titular') $titulares[$jugadorId] = true;

                    // Dorsal: el de livefutbol es el de ESE partido; si no lo trae
                    // (amateurs), el de la plantilla.
                    $dePlanti = isset($mapaDorsales[$equipoId . '-' . $jugadorId]) ? (int) $mapaDorsales[$equipoId . '-' . $jugadorId] : null;
                    $dorsal = $dorsalLf; $fuente = 'livefutbol';
                    if ($dorsal === null && $dePlanti !== null) { $dorsal = $dePlanti; $fuente = 'plantilla (LF no lo trajo)'; }
                    elseif ($dorsal !== null && $dePlanti !== null && $dePlanti !== $dorsal) {
                        $fuente = 'LF ' . $dorsal . ' ≠ plantilla ' . $dePlanti;
                    }

                    $plan['alineacions'][] = [
                        'partido_id' => $partido->id, 'jugador_id' => $jugadorId, 'equipo_id' => $equipoId,
                        'dorsal' => $dorsal, 'tipo' => $j['_tipo'], 'orden' => $this->tm->puestoDe($jugadorId),
                        '_nombre' => $nombre, '_equipo' => $lado['equipo_nombre'], '_fuente' => $fuente,
                        '_dudoso' => $fuente !== 'livefutbol',
                    ];
                    if ($grupoId) {
                        $plan['plantillas'][] = [
                            'torneo_id' => (int) $torneoId, 'grupo_id' => (int) $grupoId, 'equipo_id' => $equipoId,
                            'jugador_id' => $jugadorId, 'dorsal' => $dorsal,
                            '_nombre' => $nombre, '_equipo' => $lado['equipo_nombre'],
                        ];
                    }

                    // Goles
                    foreach (($j['goles'] ?? []) as $g) {
                        $t = $this->tipoGol($g['tipo'] ?? '');
                        if ($t['tipo'] === null) {
                            $this->aviso($nombre . ', ' . ($g['txt'] ?? '') . ': livefutbol lo marca como «' . ($g['tipo'] ?? '')
                                . '» y no es un gol. No lo cargo; si fue un penal errado o atajado, cargalo a mano en penales.');
                            continue;
                        }
                        $m = MinutoHelper::normalizar($g['min'] ?? null, $g['extra'] ?? null);
                        $plan['gols'][] = [
                            'partido_id' => $partido->id, 'jugador_id' => $jugadorId,
                            'minuto' => $m['minuto'], 'adicionado' => $m['adicionado'], 'tipo' => $t['tipo'],
                            '_nombre' => $nombre,
                            '_equipo' => $t['tipo'] === TmDetallePartido::GOL_ENCONTRA
                                ? $lado['equipo_nombre'] . ' → ' . $otroNombre : $lado['equipo_nombre'],
                            '_fuente' => (string) ($g['tipo'] ?? ''), '_dudoso' => $t['dudoso'],
                        ];
                    }

                    // Tarjetas
                    foreach (($j['tarjetas'] ?? []) as $c) {
                        $tipo = $this->tipoTarjeta($c['tipo'] ?? '');
                        if ($tipo === null) {
                            $this->aviso('Tarjeta de ' . $nombre . ' con tipo «' . ($c['tipo'] ?? '') . '» que no reconozco: la salteo.');
                            continue;
                        }
                        $m = MinutoHelper::normalizar($c['min'] ?? null, $c['extra'] ?? null);
                        $plan['tarjetas'][] = [
                            'partido_id' => $partido->id, 'jugador_id' => $jugadorId,
                            'minuto' => $m['minuto'], 'adicionado' => $m['adicionado'], 'tipo' => $tipo,
                            '_nombre' => $nombre, '_equipo' => $lado['equipo_nombre'], '_fuente' => (string) ($c['tipo'] ?? ''),
                        ];
                    }

                    // Cambios: livefutbol dice en la fila de cada uno si entró o
                    // salió, así que no hay que deducir la dirección como en TM.
                    foreach (['entra' => 'Entra', 'sale' => 'Sale'] as $k => $tipo) {
                        if (empty($j[$k])) continue;
                        $m = MinutoHelper::normalizar($j[$k]['min'] ?? null, $j[$k]['extra'] ?? null);
                        $dudoso = false;
                        if ($tipo === 'Entra' && $j['_tipo'] === 'Titular') {
                            $this->aviso($nombre . ' figura como titular y como que entró: revisá ese cambio.');
                            $dudoso = true;
                        }
                        $plan['cambios'][] = [
                            'partido_id' => $partido->id, 'jugador_id' => $jugadorId,
                            'minuto' => $m['minuto'], 'adicionado' => $m['adicionado'], 'tipo' => $tipo,
                            '_nombre' => $nombre, '_equipo' => $lado['equipo_nombre'],
                            '_fuente' => 'fila del jugador en livefutbol', '_dudoso' => $dudoso,
                        ];
                        if ($tipo === 'Entra') $entraron[$jugadorId] = true; else $salieron[$jugadorId] = true;
                    }
                }

                // Coherencia: un suplente que sale tiene que haber entrado.
                foreach (array_keys($salieron) as $jid) {
                    if (!isset($titulares[$jid]) && !isset($entraron[$jid])) {
                        $this->aviso($this->nombreJugador($jid) . ' (' . $lado['equipo_nombre'] . ') sale sin haber sido titular ni haber entrado.');
                    }
                }
                if (count($entraron) !== count($salieron)) {
                    $this->aviso($lado['equipo_nombre'] . ': ' . count($entraron) . ' entran y ' . count($salieron)
                        . ' salen. Puede ser una expulsión o una lesión sin cambios; si no, falta un cambio.');
                }

                // DT
                $dt = $this->planTecnico($partido, $lado, $eq['cuerpo_tecnico'] ?? []);
                if ($dt) $plan['tecnicos'][] = $dt;
            }

            $plan['arbitros'] = $this->planArbitros($partido, $lf['arbitros'] ?? []);
        } catch (\Exception $e) {
            Log::error('LivefutbolDetalle partido ' . $partido->id . ': ' . $e->getMessage() . ' ' . $e->getFile() . ':' . $e->getLine());
            $informe['error'] = 'Error armando el detalle: ' . $e->getMessage();
            $informe['avisos'] = $this->avisos;
            return $informe;
        }

        if ($this->sinFicha) {
            $this->aviso($this->sinFicha . ' persona(s) sin ficha de livefutbol en lo que pegaste: para esas se apareó '
                . 'sólo por nombre, y si se crean salen sin fecha de nacimiento ni nacionalidad.'
                . (empty($lf['fichas_leidas']) && !isset($lf['fichas_leidas'])
                    ? ' Ese JSON es del favorito viejo: borralo de la barra, arrastrá el nuevo desde esta pantalla y volvé a copiar.' : ''));
        }

        // Los goles del resumen tienen que ser los mismos que los de las filas.
        $resumen = count($lf['resumen_goles'] ?? []);
        if ($resumen && $resumen !== count($plan['gols'])) {
            $this->aviso('El resumen de livefutbol trae ' . $resumen . ' gol(es) y en las alineaciones encontré '
                . count($plan['gols']) . '. Revisá los goles antes de guardar.');
        }

        // Marcador
        $marcador = $this->marcador($lf, $o['invertido']);
        $informe['marcador'] = $marcador;
        if ($marcador && $partido->golesl !== null && $partido->golesv !== null
            && ((int) $partido->golesl !== $marcador['golesl'] || (int) $partido->golesv !== $marcador['golesv'])) {
            $this->aviso('El resultado no coincide: en la base es ' . $partido->golesl . '-' . $partido->golesv
                . ' y livefutbol dice ' . $marcador['golesl'] . '-' . $marcador['golesv'] . '. No lo toco.');
        }

        // Las mismas depuraciones que el de TM (un jugador dos veces, dorsal repetido…).
        $plan = $this->tm->depurarPlanAjeno($plan);

        $informe['plan'] = $plan;
        $informe['personas'] = $this->personas;
        $informe['creados'] = $this->creados;
        $informe['ok'] = true;

        // El freno de rehacer: no vaciar un equipo que la base tiene cargado.
        $vacios = $forzar ? $this->tm->ladosVacios($plan, $partido, $lados) : [];
        if ($vacios) {
            $queja = 'livefutbol no trae la alineación de ' . implode(' ni de ', $vacios) . ', y en la base este '
                . 'partido la tiene cargada. Rehacer la borraría y no se puede recuperar, así que no toqué nada.';
            if ($this->escribir) {
                $informe['ok'] = false;
                $informe['error'] = $queja;
                $informe['avisos'] = $this->avisos;
                return $informe;
            }
            $this->aviso($queja);
        }

        if ($this->escribir) {
            $r = $this->tm->grabarPlanAjeno($partido, $plan, $lados, $forzar, function ($p) use ($marcador) {
                if (!$marcador || ($p->golesl !== null && $p->golesv !== null)) return;
                $p->golesl = $marcador['golesl'];
                $p->golesv = $marcador['golesv'];
                if ($marcador['penalesl'] !== null) {
                    $p->penalesl = $marcador['penalesl'];
                    $p->penalesv = $marcador['penalesv'];
                }
                $p->save();
            });
            foreach ($r['avisos'] as $a) $this->aviso($a);
            $informe['fallidas'] = $r['fallidas'];
            if ($r['error']) {
                $informe['ok'] = false;
                $informe['error'] = $r['error'];
            } else {
                $informe['escrito'] = true;
                $this->grabarMapeos();
                if ($matchId && LivefutbolPartido::hayTablas()) {
                    LivefutbolPartido::anotar($partido->id, $matchId, 'detalle');
                }
                $this->aprenderClubes($lados);
            }
        }

        $informe['avisos'] = $this->avisos;
        return $informe;
    }

    // ═══════════════════════════ CLUBES ═══════════════════════════

    /**
     * ¿El partido es éste, y con la localía igual o dada vuelta?
     * Primero por el id de livefutbol ya atado (equipo_livefutbol); si no, por
     * el nombre.
     */
    private function orientacion(Partido $p, array $lf)
    {
        $lfL = $lf['local'] ?? []; $lfV = $lf['visitante'] ?? [];
        $sLL = $this->parecidoClub($p->equipol_id, $lfL);
        $sVV = $this->parecidoClub($p->equipov_id, $lfV);
        $sLV = $this->parecidoClub($p->equipol_id, $lfV);
        $sVL = $this->parecidoClub($p->equipov_id, $lfL);

        $umbral = 0.5;
        $normal = ($sLL >= $umbral && $sVV >= $umbral) ? $sLL + $sVV : -1;
        $vuelta = ($sLV >= $umbral && $sVL >= $umbral) ? $sLV + $sVL : -1;

        if ($normal < 0 && $vuelta < 0) return ['ok' => false, 'invertido' => false, 'puntos' => 0];
        return $normal >= $vuelta
            ? ['ok' => true, 'invertido' => false, 'puntos' => $normal]
            : ['ok' => true, 'invertido' => true,  'puntos' => $vuelta];
    }

    /** 0..1 (o 2 si el id de livefutbol ya está atado a ese equipo, -1 si a otro). */
    private function parecidoClub($equipoId, array $lfEq)
    {
        static $teams = null;
        if ($teams === null) {
            $teams = LivefutbolPartido::hayTablas()
                ? DB::table('equipo_livefutbol')->pluck('team_id', 'equipo_id')->all() : [];
        }
        $lfTeam = isset($lfEq['lf_id']) ? (int) $lfEq['lf_id'] : 0;
        if ($lfTeam && isset($teams[$equipoId])) {
            return (int) $teams[$equipoId] === $lfTeam ? 2 : -1;
        }

        $base = $this->nombreEquipo($equipoId);
        $suyo = (string) ($lfEq['nombre'] ?? '');
        if ($base === '' || $suyo === '') return 0;

        if (Str::slug($base) === Str::slug($suyo)) return 1;

        $a = array_values(array_diff($this->tm->palabras($base), self::$relleno));
        $b = array_values(array_diff($this->tm->palabras($suyo), self::$relleno));
        if (!$a || !$b) {
            $a = $this->tm->palabras($base);
            $b = $this->tm->palabras($suyo);
        }
        if (!$a || !$b) return 0;

        $comunes = count(array_intersect($a, $b));
        // Abreviaturas: "Man Utd" no comparte palabras con "Manchester United".
        if ($comunes === 0) {
            foreach ($a as $x) {
                foreach ($b as $y) {
                    if (mb_strlen($x) >= 3 && mb_strlen($y) >= 3 && (strpos($x, $y) === 0 || strpos($y, $x) === 0)) {
                        $comunes += 0.5;
                        break;
                    }
                }
            }
        }
        return $comunes / min(count($a), count($b));
    }

    private function aprenderClubes(array $lados)
    {
        if (!LivefutbolPartido::hayTablas()) return;
        foreach ($lados as $l) {
            if (empty($l['lf_team'])) continue;
            $ya = DB::table('equipo_livefutbol')->where('equipo_id', $l['equipo_id'])->exists();
            $deOtro = DB::table('equipo_livefutbol')->where('team_id', (int) $l['lf_team'])
                ->where('equipo_id', '!=', $l['equipo_id'])->exists();
            if (!$ya && !$deOtro) {
                DB::table('equipo_livefutbol')->insert([
                    'equipo_id' => $l['equipo_id'], 'team_id' => (int) $l['lf_team'], 'origen' => 'detalle',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    // ═══════════════════════════ PERSONAS ═══════════════════════════

    /** Jugadores de la plantilla del equipo en cualquier grupo del torneo. */
    private function plantillaDe($torneoId, $equipoId)
    {
        if (!$torneoId) return [];
        return DB::table('plantilla_jugadors')
            ->join('plantillas', 'plantillas.id', '=', 'plantilla_jugadors.plantilla_id')
            ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
            ->join('jugadors', 'jugadors.id', '=', 'plantilla_jugadors.jugador_id')
            ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
            ->where('grupos.torneo_id', $torneoId)
            ->where('plantillas.equipo_id', $equipoId)
            ->select('jugadors.id', 'plantilla_jugadors.dorsal', 'personas.apellido', 'personas.nombre', 'personas.name', 'personas.nacimiento')
            ->get()->unique('id')->values()->all();
    }

    /**
     * Puntaje de una ficha contra el nombre de livefutbol, o null si no puede
     * ser él: tiene que coincidir al menos un apellido (nunca sólo el nombre de
     * pila). Con un nombre de una sola palabra ("Casemiro") alcanza con que esa
     * palabra esté en la ficha.
     */
    private function puntaje($nombreLf, $c, $estricto)
    {
        $tLf   = $this->tm->palabras($nombreLf);
        if (!$tLf) return null;
        $tBase = array_values(array_unique(array_merge(
            $this->tm->palabras($c->apellido . ' ' . $c->nombre),
            $this->tm->palabras((string) $c->name))));
        $apeBase = $this->tm->palabras($c->apellido);

        $comunes = array_values(array_intersect($tLf, $tBase));
        if (!$comunes) return null;

        if (count($tLf) === 1) {
            // Un apodo tiene que ser exactamente el nombre mostrado o el apellido.
            $mostrado = $this->tm->palabras((string) $c->name);
            if ($mostrado !== $tLf && $apeBase !== $tLf) return null;
            return 1;
        }

        if ($apeBase && !array_intersect($comunes, $apeBase)) return null;

        if ($estricto && array_diff($tLf, $tBase)) return null;

        return count($comunes);
    }

    private function resolverJugador(array $j, $dorsalLf, array $plantilla, array $lado)
    {
        $lfId = !empty($j['lf_id']) ? (int) $j['lf_id'] : null;
        $nombreLf = trim((string) $j['nombre']);
        $clave = 'jugador:' . ($lfId ?: Str::slug($nombreLf));
        $info = ['rol' => 'jugador', 'clave' => $clave, 'lf_id' => $lfId, 'nombre_lf' => $nombreLf,
            'equipo' => $lado['equipo_nombre'], 'dorsal' => $dorsalLf, 'id' => null, 'como' => '',
            'revisar' => false, 'opciones' => [], 'ficha' => null];

        // 1) Atado
        if ($lfId && ($id = $this->atado('jugador', $lfId, 'jugadors'))) {
            $info['id'] = $id; $info['como'] = 'ya atado a livefutbol';
            return $this->registrar($info);
        }

        // 2) Elegido en la vista previa
        $elegido = $this->elegidoPara($clave, 'jugadors');
        if ($elegido === 'nuevo') {
            return $this->registrar($this->crear('jugador', $info, 'elegido: crear nuevo'));
        }
        if ($elegido) {
            $info['id'] = $elegido; $info['como'] = 'elegido a mano';
            $this->anotarMapeo('jugador', $lfId, $elegido, $nombreLf, 'elegido', false);
            return $this->registrar($info);
        }

        // 3) Plantilla del equipo
        $mejor = null; $mejorP = 0; $empates = 0; $opciones = [];
        foreach ($plantilla as $c) {
            $p = $this->puntaje($nombreLf, $c, false);
            if ($p === null) continue;
            if ($dorsalLf !== null && (int) $c->dorsal === $dorsalLf) $p += 0.5;
            $opciones[(int) $c->id] = $this->etiqueta($c, 'plantilla' . ($c->dorsal ? ', ' . $c->dorsal : ''));
            if ($p > $mejorP) { $mejor = $c; $mejorP = $p; $empates = 1; }
            elseif ($p === $mejorP) { $empates++; }
        }
        if ($mejor && $empates === 1) {
            $info['id'] = (int) $mejor->id;
            $conDorsal = $dorsalLf !== null && (int) $mejor->dorsal === $dorsalLf;
            $info['como'] = 'plantilla del equipo' . ($conDorsal ? ' + dorsal' : '');
            $info['revisar'] = !$conDorsal && $mejorP < 2;
            $info['opciones'] = $opciones;
            $this->anotarMapeo('jugador', $lfId, $info['id'], $nombreLf, 'plantilla', $info['revisar']);
            return $this->registrar($info);
        }

        // 4) Con la ficha de livefutbol: criterio de TM por fecha de nacimiento.
        $ficha = $lfId ? $this->ficha($lfId, $j['ficha'] ?? null, $info) : null;
        if ($ficha && $ficha['nacimiento']) {
            $porFecha = $this->porFecha('jugadors', $nombreLf, $ficha, $info, $opciones);
            if ($porFecha !== null) return $porFecha;
            $motivo = count($info['opciones']) > 1
                ? 'hay ' . count($info['opciones']) . ' parecidos y ninguno con su fecha: elegí uno o se crea'
                : 'no está en la base';
            return $this->registrar($this->crear('jugador', $info, $motivo, $ficha));
        }

        // 5) Sin ficha: toda la base, estricto
        $cands = $this->candidatosBase('jugadors', $nombreLf);
        $validos = [];
        foreach ($cands as $c) {
            if ($this->puntaje($nombreLf, $c, true) !== null) $validos[] = $c;
        }
        foreach ($validos as $c) $opciones[(int) $c->id] = $this->etiqueta($c, 'base');
        $info['opciones'] = $opciones;

        if (count($validos) === 1 && !($mejor && $empates > 1)) {
            $info['id'] = (int) $validos[0]->id;
            $info['como'] = 'mismo nombre en la base (sin fecha para confirmar)';
            $info['revisar'] = true;
            $this->anotarMapeo('jugador', $lfId, $info['id'], $nombreLf, 'nombre', true);
            return $this->registrar($info);
        }

        // 6) Crear
        $motivo = count($opciones) > 1 ? 'hay ' . count($opciones) . ' parecidos y no adivino: elegí uno o se crea' : 'no está en la base';
        return $this->registrar($this->crear('jugador', $info, $motivo, $ficha));
    }

    /** DT del lado: el «Director técnico»/«Entrenador» del cuerpo técnico. */
    private function planTecnico(Partido $partido, array $lado, array $cuerpo)
    {
        $dts = array_values(array_filter($cuerpo, function ($c) {
            $r = mb_strtolower((string) ($c['rol'] ?? ''));
            return $r === '' || preg_match('/t[eé]cnico|entrenador|trainer|coach|manager|dt\b/u', $r)
                && !preg_match('/asist|ayud|assist|adjunt|segundo|\bco-/u', $r);
        }));
        if (!$dts) return null;

        $ya = DB::table('partido_tecnicos')
            ->join('tecnicos', 'tecnicos.id', '=', 'partido_tecnicos.tecnico_id')
            ->join('personas', 'personas.id', '=', 'tecnicos.persona_id')
            ->where('partido_tecnicos.partido_id', $partido->id)
            ->where('partido_tecnicos.equipo_id', $lado['equipo_id'])
            ->select('tecnicos.id', 'personas.apellido', 'personas.nombre', 'personas.name', 'personas.nacimiento')
            ->get()->all();

        // Dos «Director técnico» (pasó con Dijon): si uno es el que ya está
        // cargado, ése; si no, el primero, avisando.
        $dt = $dts[0];
        if (count($dts) > 1) {
            foreach ($dts as $cand) {
                foreach ($ya as $c) {
                    if ($this->puntaje($cand['nombre'] ?? '', $c, false) !== null) { $dt = $cand; break 2; }
                }
            }
            $this->aviso($lado['equipo_nombre'] . ': livefutbol lista ' . count($dts) . ' DTs ('
                . implode(', ', array_map(function ($d) { return $d['nombre'] ?? '?'; }, $dts))
                . '). Uso a ' . ($dt['nombre'] ?? '?') . '; si era el otro, cargalo a mano.');
        }

        $id = $this->resolverRol('tecnico', 'tecnicos', $dt, $ya, $lado['equipo_nombre']);
        if (!$id) return null;

        $estado = $ya ? (in_array($id, array_map(function ($c) { return (int) $c->id; }, $ya), true)
            ? 'ya estaba en el partido' : 'el partido ya tiene otro DT para este equipo: no se agrega')
            : 'se agrega';

        return ['partido_id' => $partido->id, 'tecnico_id' => $id, 'equipo_id' => $lado['equipo_id'],
            '_equipo' => $lado['equipo_nombre'], '_nombre' => $this->nombrePersona('tecnicos', $id, $dt['nombre'] ?? ''),
            '_estado' => $estado];
    }

    private function planArbitros(Partido $partido, array $arbitros)
    {
        $ya = DB::table('partido_arbitros')
            ->join('arbitros', 'arbitros.id', '=', 'partido_arbitros.arbitro_id')
            ->join('personas', 'personas.id', '=', 'arbitros.persona_id')
            ->where('partido_arbitros.partido_id', $partido->id)
            ->select('arbitros.id', 'personas.apellido', 'personas.nombre', 'personas.name', 'personas.nacimiento')
            ->get()->all();

        $out = []; $usados = [];
        foreach ($arbitros as $i => $a) {
            $rolTxt = trim((string) ($a['rol'] ?? ''), " :\t");
            $rol = $this->tm->rolArbitroDe($rolTxt);
            $porPosicion = false;
            if ($rol === null) {
                // Sin rol legible: por el orden en que los lista livefutbol
                // (principal primero). Es inferencia, así que va marcada.
                foreach (['Principal', 'Linea 1', 'Linea 2', 'Cuarto'] as $r) {
                    if (!isset($usados[$r])) { $rol = $r; break; }
                }
                if ($rol === null) continue;
                $porPosicion = true;
                $this->aviso('Árbitro «' . ($a['nombre'] ?? '?') . '»: livefutbol no dice el rol de forma que la '
                    . 'entienda («' . $rolTxt . '»), lo pongo como ' . $rol . ' por el orden de la lista. Revisalo.');
            }
            // "Asistentes" sin número: el primero Línea 1, el segundo Línea 2.
            if ($rol === 'Linea 1' && isset($usados['Linea 1'])) $rol = 'Linea 2';
            if (isset($usados[$rol])) {
                $this->aviso('Livefutbol trae más de un ' . $rol . ' (' . ($a['nombre'] ?? '?') . '): lo salteo.');
                continue;
            }
            $usados[$rol] = true;

            $id = $this->resolverRol('arbitro', 'arbitros', $a, $ya, 'terna');
            if (!$id) continue;
            $out[] = ['partido_id' => $partido->id, 'arbitro_id' => $id, 'tipo' => $rol,
                '_nombre' => $this->nombrePersona('arbitros', $id, $a['nombre'] ?? ''),
                '_fuente' => $rolTxt . ($porPosicion ? ' (rol por posición)' : ''), '_dudoso' => $porPosicion];
        }
        return $out;
    }

    /** DTs y árbitros: atado → elegido → ya en el partido → base estricta → crear. */
    private function resolverRol($rol, $tabla, array $p, array $enPartido, $donde)
    {
        $lfId = !empty($p['lf_id']) ? (int) $p['lf_id'] : null;
        $nombreLf = trim((string) ($p['nombre'] ?? ''));
        if ($nombreLf === '') return null;
        $clave = $rol . ':' . ($lfId ?: Str::slug($nombreLf));
        $info = ['rol' => $rol, 'clave' => $clave, 'lf_id' => $lfId, 'nombre_lf' => $nombreLf,
            'equipo' => $donde, 'dorsal' => null, 'id' => null, 'como' => '', 'revisar' => false, 'opciones' => [],
            'ficha' => null];

        if ($lfId && ($id = $this->atado($rol, $lfId, $tabla))) {
            $info['id'] = $id; $info['como'] = 'ya atado a livefutbol';
            return $this->registrar($info);
        }

        $elegido = $this->elegidoPara($clave, $tabla);
        if ($elegido === 'nuevo') return $this->registrar($this->crear($rol, $info, 'elegido: crear nuevo'));
        if ($elegido) {
            $info['id'] = $elegido; $info['como'] = 'elegido a mano';
            $this->anotarMapeo($rol, $lfId, $elegido, $nombreLf, 'elegido', false);
            return $this->registrar($info);
        }

        $opciones = [];
        foreach ($enPartido as $c) {
            if ($this->puntaje($nombreLf, $c, false) !== null) {
                $info['id'] = (int) $c->id; $info['como'] = 'ya estaba cargado en este partido';
                $this->anotarMapeo($rol, $lfId, $info['id'], $nombreLf, 'partido', false);
                return $this->registrar($info);
            }
        }

        $ficha = $lfId ? $this->ficha($lfId, $p['ficha'] ?? null, $info) : null;
        if ($ficha && $ficha['nacimiento']) {
            $porFecha = $this->porFecha($tabla, $nombreLf, $ficha, $info, $opciones);
            if ($porFecha !== null) return $porFecha;
            $motivo = count($info['opciones']) > 1
                ? 'hay ' . count($info['opciones']) . ' parecidos y ninguno con su fecha: elegí uno o se crea'
                : 'no está en la base';
            return $this->registrar($this->crear($rol, $info, $motivo, $ficha));
        }

        $validos = [];
        foreach ($this->candidatosBase($tabla, $nombreLf) as $c) {
            if ($this->puntaje($nombreLf, $c, true) !== null) {
                $validos[] = $c;
                $opciones[(int) $c->id] = $this->etiqueta($c, 'base');
            }
        }
        $info['opciones'] = $opciones;
        if (count($validos) === 1) {
            $info['id'] = (int) $validos[0]->id;
            $info['como'] = 'mismo nombre en la base (sin fecha para confirmar)';
            $info['revisar'] = true;
            $this->anotarMapeo($rol, $lfId, $info['id'], $nombreLf, 'nombre', true);
            return $this->registrar($info);
        }

        $motivo = count($validos) > 1 ? 'hay ' . count($validos) . ' parecidos y no adivino: elegí uno o se crea' : 'no está en la base';
        return $this->registrar($this->crear($rol, $info, $motivo, $ficha));
    }

    /** Fichas de la tabla del rol que comparten alguna palabra larga del nombre. */
    private function candidatosBase($tabla, $nombreLf)
    {
        $anclas = [];
        foreach ($this->tm->palabras($nombreLf) as $t) {
            if (mb_strlen($t) >= 4) $anclas[] = $t;
        }
        // Las del final primero: suelen ser el apellido.
        $anclas = array_slice(array_reverse($anclas), 0, 4);
        if (!$anclas) {
            $anclas = $this->tm->palabras($nombreLf);
            if (!$anclas) return [];
        }

        return DB::table($tabla)
            ->join('personas', 'personas.id', '=', $tabla . '.persona_id')
            ->where(function ($q) use ($anclas) {
                foreach ($anclas as $a) {
                    $q->orWhere('personas.apellido', 'like', '%' . $a . '%')
                      ->orWhere('personas.nombre', 'like', '%' . $a . '%')
                      ->orWhere('personas.name', 'like', '%' . $a . '%');
                }
            })
            ->select($tabla . '.id', 'personas.apellido', 'personas.nombre', 'personas.name', 'personas.nacimiento')
            ->limit(300)->get()->all();
    }

    private function atado($rol, $lfId, $tabla)
    {
        $id = DB::table('livefutbol_personas')->where('rol', $rol)->where('lf_id', $lfId)->value('ref_id');
        // Un atado a una ficha que ya no existe (fusión, borrado) no vale.
        return ($id && DB::table($tabla)->where('id', $id)->exists()) ? (int) $id : null;
    }

    /** 'nuevo', un id que existe, o null. */
    private function elegidoPara($clave, $tabla)
    {
        if (!isset($this->elegidos[$clave])) return null;
        $v = trim((string) $this->elegidos[$clave]);
        if ($v === '' || $v === 'auto') return null;
        if ($v === 'nuevo') return 'nuevo';
        $id = (int) ltrim($v, '#');
        if ($id > 0 && DB::table($tabla)->where('id', $id)->exists()) return $id;
        $this->aviso('El id «' . $v . '» que elegiste para ' . $clave . ' no existe en ' . $tabla . ': lo resuelvo solo.');
        return null;
    }

    /**
     * Crea la persona con el rol (o le asigna un id ficticio en la vista previa).
     * Sin fecha ni nacionalidad: livefutbol no las da en la alineación.
     */
    private function crear($rol, array $info, $motivo, $ficha = null)
    {
        $nombreLf = $info['nombre_lf'];
        if (!$ficha && !empty($info['lf_id'])) {
            $ficha = $this->fichas[(int) $info['lf_id']] ?? LivefutbolPersona::datos($info['lf_id'], $this->fichasJson[(int) $info['lf_id']] ?? null);
        }
        $n = NombreHelper::separarTM(['name' => $nombreLf]);
        // La ficha trae nombre y apellidos separados: mejor que adivinar el corte.
        if ($ficha && $ficha['apellido']) {
            $n = ['nombre' => (string) $ficha['nombre'], 'apellido' => (string) $ficha['apellido'],
                'name' => $nombreLf];
        }
        $grupo = ['jugador' => 'jugadores', 'tecnico' => 'tecnicos', 'arbitro' => 'arbitros'][$rol];
        $info['como'] = 'NUEVO — ' . $motivo;
        $info['revisar'] = true;

        if (!$this->escribir) {
            $info['id'] = $this->proximoPreview--;
            $this->creados[$grupo][] = $nombreLf . ' (' . $info['equipo'] . ') — SE CREARÍA · ' . $this->resumenFicha($ficha);
            return $info;
        }

        try {
            $alta = ['name' => trim($n['name']), 'nombre' => trim($n['nombre']), 'apellido' => trim($n['apellido'])];
            if ($ficha) {
                foreach (['nacimiento', 'fallecimiento', 'ciudad', 'nacionalidad', 'altura', 'peso'] as $k) {
                    if (!empty($ficha[$k])) $alta[$k] = $ficha[$k];
                }
                if (!empty($ficha['foto_url']) && !in_array($ficha['foto_url'], $this->fotosDeRelleno, true)
                    && ($foto = LivefutbolPersona::bajarFoto($ficha['foto_url']))) {
                    $alta['foto'] = $foto;
                }
            }
            $persona = Persona::create($alta);
            if ($rol === 'jugador')      $ref = $persona->jugador()->create(!empty($ficha['tipo']) ? ['tipoJugador' => $ficha['tipo']] : []);
            elseif ($rol === 'tecnico')  $ref = $persona->tecnico()->create([]);
            else                         $ref = $persona->arbitro()->create([]);

            $info['id'] = (int) $ref->id;
            $this->anotarMapeo($rol, $info['lf_id'], $info['id'], $nombreLf, 'creado', true);
            $this->creados[$grupo][] = $nombreLf . ' (' . $info['equipo'] . ') — creado #' . $info['id'] . ' · ' . $this->resumenFicha($ficha);
            foreach ($this->tm->avisosApellidoParecido(['apellido' => $n['apellido'], 'nombre' => $n['nombre'],
                         'nacimiento' => $ficha['nacimiento'] ?? null],
                         $rol, (int) $persona->id) as $a) {
                $this->aviso($a);
            }
        } catch (\Exception $e) {
            $this->aviso('No pude crear a ' . $nombreLf . ': ' . $e->getMessage());
            $info['id'] = null;
        }
        return $info;
    }

    /** Baja (o saca de caché) la ficha de livefutbol y la deja a la vista en $info. */
    private function ficha($lfId, $cruda, array &$info)
    {
        $f = LivefutbolPersona::datos($lfId, $cruda);
        if ($f && $f['foto_url'] && in_array($f['foto_url'], $this->fotosDeRelleno, true)) $f['foto_url'] = null;
        if ($f) $this->fichas[(int) $lfId] = $f;
        $info['ficha'] = $f ? $this->resumenFicha($f) : 'sin ficha en el JSON';
        if (!$f) $this->sinFicha++;
        return $f;
    }

    private function resumenFicha($f)
    {
        if (!$f) return 'sin ficha de livefutbol: sin fecha de nacimiento ni nacionalidad';
        $p = [];
        $p[] = $f['nacimiento'] ? 'nac. ' . Carbon::parse($f['nacimiento'])->format('d/m/Y') : 'sin fecha de nacimiento';
        $p[] = $f['nacionalidad'] ?: 'sin nacionalidad';
        if (!empty($f['tipo'])) $p[] = $f['tipo'];
        // Si tiene foto o sólo la silueta recién se sabe al bajarla (ver
        // LivefutbolPersona::bajarFoto): la vista previa no lo promete.
        return implode(' · ', $p);
    }

    /**
     * El criterio de Transfermarkt, con la fecha de la ficha de livefutbol.
     * Devuelve el id si lo encontró (ya registrado), o null para que se cree.
     * Deja los parecidos en $info['opciones'] para el desplegable.
     */
    private function porFecha($tabla, $nombreLf, array $ficha, array &$info, array $opciones)
    {
        $rol = $info['rol'];
        $completo = trim($ficha['nombre'] . ' ' . $ficha['apellido']);
        $nombres = array_values(array_unique(array_filter([$nombreLf, $completo])));
        $fecha = substr($ficha['nacimiento'], 0, 10);

        $cands = [];
        foreach ($nombres as $n) {
            foreach ($this->candidatosBase($tabla, $n) as $c) $cands[(int) $c->id] = $c;
        }

        $mismaFecha = []; $sinFecha = [];
        foreach ($cands as $c) {
            // El mejor puntaje con cualquiera de las dos formas del nombre.
            $p = null; $estricto = null;
            foreach ($nombres as $n) {
                $x = $this->puntaje($n, $c, false);
                if ($x !== null && ($p === null || $x > $p)) $p = $x;
                if ($this->puntaje($n, $c, true) !== null) $estricto = true;
            }
            if ($p === null) continue;

            if ($c->nacimiento) {
                if (substr((string) $c->nacimiento, 0, 10) !== $fecha) continue;   // fecha distinta: no es
                if ($p >= 2 || count($this->tm->palabras($nombreLf)) === 1) {
                    $mismaFecha[] = $c;
                } else {
                    $this->aviso($nombreLf . ': ' . $this->etiqueta($c, 'base') . ' nació el mismo día pero coincide '
                        . 'una sola palabra del nombre. No los uno; si es él, elegilo.');
                }
                $opciones[(int) $c->id] = $this->etiqueta($c, 'misma fecha');
            } elseif ($estricto) {
                $sinFecha[] = $c;
                $opciones[(int) $c->id] = $this->etiqueta($c, 'base, sin fecha');
            }
        }
        $info['opciones'] = $opciones;
        $lfId = $info['lf_id'];

        if (count($mismaFecha) === 1) {
            $info['id'] = (int) $mismaFecha[0]->id;
            $info['como'] = 'misma fecha de nacimiento y nombre';
            $info['revisar'] = false;
            $this->anotarMapeo($rol, $lfId, $info['id'], $nombreLf, 'nombre', false);
            return $this->registrar($info);
        }
        if (count($mismaFecha) > 1) {
            $this->aviso($nombreLf . ': hay ' . count($mismaFecha) . ' fichas con su nombre y su fecha de nacimiento '
                . '(¿duplicados?). No elijo: se crea, salvo que elijas una.');
            return null;
        }
        if (count($sinFecha) === 1) {
            $info['id'] = (int) $sinFecha[0]->id;
            $info['como'] = 'mismo nombre; la base no tiene su fecha (livefutbol: '
                . Carbon::parse($fecha)->format('d/m/Y') . ')';
            $info['revisar'] = true;
            $this->anotarMapeo($rol, $lfId, $info['id'], $nombreLf, 'nombre', true);
            return $this->registrar($info);
        }
        return null;
    }

    private function anotarMapeo($rol, $lfId, $refId, $nombre, $origen, $revisar)
    {
        if (!$lfId || !$refId || $refId < 0) return;
        $this->mapeos[$rol . ':' . $lfId] = [$rol, (int) $lfId, (int) $refId, $nombre, $origen, (bool) $revisar];
    }

    private function grabarMapeos()
    {
        foreach ($this->mapeos as $m) {
            list($rol, $lfId, $refId, $nombre, $origen, $revisar) = $m;
            try {
                DB::table('livefutbol_personas')->updateOrInsert(
                    ['lf_id' => $lfId, 'rol' => $rol],
                    ['ref_id' => $refId, 'nombre_lf' => mb_substr((string) $nombre, 0, 191), 'origen' => $origen,
                        'revisar' => $revisar ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]);
            } catch (\Exception $e) {
                $this->aviso('No pude guardar el atado de ' . $nombre . ' con livefutbol: ' . $e->getMessage());
            }
        }
    }

    private function registrar(array $info)
    {
        if ($info['id'] && $info['id'] > 0) {
            $tabla = ['jugador' => 'jugadors', 'tecnico' => 'tecnicos', 'arbitro' => 'arbitros'][$info['rol']];
            $info['base'] = $this->nombrePersona($tabla, $info['id'], '');
        }
        $this->personas[] = $info;
        return $info['id'];
    }

    private function etiqueta($c, $de)
    {
        return trim($c->apellido . ', ' . $c->nombre, ', ') . ' #' . $c->id
            . ($c->nacimiento ? ' · ' . substr((string) $c->nacimiento, 0, 10) : '') . ' (' . $de . ')';
    }

    // ═══════════════════════════ EVENTOS ═══════════════════════════

    /** Clase de livefutbol → `gols.tipo`. null = no es un gol (penal errado). */
    private function tipoGol($clase)
    {
        $c = mb_strtolower(trim((string) $clase));
        if (preg_match('/miss|fall|errad|saved|atajad/', $c)) return ['tipo' => null, 'dudoso' => false];
        if (strpos($c, 'own') !== false || strpos($c, 'contra') !== false || strpos($c, 'eigen') !== false) {
            return ['tipo' => TmDetallePartido::GOL_ENCONTRA, 'dudoso' => false];
        }
        if (strpos($c, 'pen') !== false)  return ['tipo' => TmDetallePartido::GOL_PENAL, 'dudoso' => false];
        if (strpos($c, 'free') !== false || strpos($c, 'libre') !== false) return ['tipo' => TmDetallePartido::GOL_TIROLIBRE, 'dudoso' => false];
        if (strpos($c, 'head') !== false || strpos($c, 'cabez') !== false) return ['tipo' => TmDetallePartido::GOL_CABEZA, 'dudoso' => false];
        if ($c === '' || $c === 'goal' || strpos($c, 'goal') !== false || strpos($c, 'gol') !== false) {
            return ['tipo' => TmDetallePartido::GOL_JUGADA, 'dudoso' => false];
        }
        $this->aviso('Tipo de gol de livefutbol no reconocido: «' . $clase . '» → lo cargo como Jugada.');
        return ['tipo' => TmDetallePartido::GOL_JUGADA, 'dudoso' => true];
    }

    private function tipoTarjeta($clase)
    {
        $c = mb_strtolower(trim((string) $clase));
        if (strpos($c, 'yellow-red') !== false || strpos($c, 'yellowred') !== false || strpos($c, 'second') !== false) return 'Doble Amarilla';
        if (strpos($c, 'red') !== false || strpos($c, 'roja') !== false) return 'Roja';
        if (strpos($c, 'yellow') !== false || strpos($c, 'amarilla') !== false) return 'Amarilla';
        return null;
    }

    /**
     * El resultado, ya orientado a la base. Con penales, `final` es la tanda y
     * el marcador del partido es el del alargue (o el de los 90).
     */
    private function marcador(array $lf, $invertido)
    {
        $r = $lf['resultado'] ?? [];
        $par = function ($s) {
            return preg_match('/(\d+)\s*[:\-]\s*(\d+)/', (string) $s, $m) ? [(int) $m[1], (int) $m[2]] : null;
        };
        $incidente = mb_strtolower((string) ($r['incidente'] ?? ''));
        $porPenales = strpos($incidente, 'pen') !== false || strpos($incidente, 'p.') === 0;

        $goles = null; $pen = null;
        if ($porPenales) {
            $goles = $par($r['alargue'] ?? null) ?: $par($r['noventa'] ?? null);
            $pen = $par($r['final'] ?? null);
            if (!$goles) {
                $this->aviso('Partido por penales y livefutbol no separa el resultado del juego: no cargo el marcador.');
                return null;
            }
        } else {
            $goles = $par($r['final'] ?? null);
        }
        if (!$goles) return null;

        if ($invertido) {
            $goles = array_reverse($goles);
            if ($pen) $pen = array_reverse($pen);
        }
        return ['golesl' => $goles[0], 'golesv' => $goles[1],
            'penalesl' => $pen ? $pen[0] : null, 'penalesv' => $pen ? $pen[1] : null];
    }

    // ═══════════════════════════ VARIOS ═══════════════════════════

    private function diaLf(array $lf)
    {
        if (empty($lf['fecha_utc'])) return null;
        try {
            return Carbon::parse($lf['fecha_utc'])->setTimezone(config('app.timezone') ?: 'America/Argentina/Buenos_Aires');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function nombreEquipo($id)
    {
        static $cache = [];
        if (!isset($cache[$id])) $cache[$id] = (string) DB::table('equipos')->where('id', $id)->value('nombre');
        return $cache[$id];
    }

    private function nombreJugador($id, $lf = '')
    {
        return $this->nombrePersona('jugadors', $id, $lf);
    }

    private function nombrePersona($tabla, $id, $lf)
    {
        if ($id < 0) return $lf . ' · nuevo';
        $p = DB::table($tabla)->join('personas', 'personas.id', '=', $tabla . '.persona_id')
            ->where($tabla . '.id', $id)->select('personas.name', 'personas.apellido', 'personas.nombre')->first();
        return $p ? ($p->name ?: trim($p->nombre . ' ' . $p->apellido)) : ($lf ?: '#' . $id);
    }

    private function aviso($txt)
    {
        if (!in_array($txt, $this->avisos, true)) $this->avisos[] = $txt;
    }
}
