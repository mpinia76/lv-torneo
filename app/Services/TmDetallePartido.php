<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Persona;
use App\Jugador;
use App\Arbitro;
use App\Partido;
use App\Alineacion;
use App\Gol;
use App\Tarjeta;
use App\Cambio;
use App\PartidoArbitro;
use App\Http\Controllers\JugadorController;

/**
 * Detalle de un partido desde Transfermarkt: alineaciones, goles, tarjetas,
 * cambios y árbitros.
 *
 * Cuesta dos llamadas por partido: una a /game/{gameId} (que trae todo) y,
 * sólo si aparecen jugadores que todavía no conocemos, una a /players?ids[]=
 * con hasta 50 por vez. Un jugador se resuelve una única vez en la vida:
 * después queda atado en `jugador_tm`.
 *
 * Uso:
 *   $r = (new TmDetallePartido)->importar($partidoId, $gameId, ['escribir' => false]);
 *
 * Con escribir=false no toca la base: devuelve el plan de lo que haría, que es
 * lo que muestra la pantalla de detalles. Con escribir=true guarda dentro de
 * una transacción propia.
 *
 * Nada de esto pisa datos cargados a mano: si el partido ya tiene alineación,
 * se sale sin hacer nada salvo que se pase 'forzar' => true.
 */
class TmDetallePartido
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Vocabulario real de la base (ver FechaController, import de incidencias). */
    const GOL_JUGADA     = 'Jugada';
    const GOL_PENAL      = 'Penal';
    const GOL_TIROLIBRE  = 'Tiro Libre';
    const GOL_CABEZA     = 'Cabeza';
    const GOL_ENCONTRA   = 'En Contra';

    /**
     * Claves reales del JSON de /game/{id} (confirmadas con partidos de verdad).
     * Cada acción viene así:
     *   {"action":"Yellow card","actionId":301,"reason":"Not reported","reasonId":300,
     *    "minute":45,"addedTime":1,"activePlayerId":30795,"passivePlayerId":…,
     *    "activePlayer":{"id":…,"name":…,"shortName":…}}
     *
     * `action` es QUÉ pasó (el tipo de gol, el color de la tarjeta) y `reason`
     * es el CÓMO (la asistencia). Para clasificar miramos `action`, nunca
     * `reason`: si no, un "Not reported" en reason ensucia el match.
     *
     * En los cambios, `activePlayerId` y `passivePlayerId` son los dos jugadores,
     * pero cuál entra y cuál sale no está dicho: se deduce del banco (ver abajo).
     */
    private static $kJugador   = ['activePlayerId', 'playerId', 'player_id', 'scorerId', 'goalScorerId', 'id'];
    private static $kOtro      = ['passivePlayerId', 'assistPlayerId', 'passivePlayer'];
    private static $kMinuto    = ['minute', 'minuto', 'min', 'time', 'gameMinute'];
    private static $kAgregado  = ['addedTime', 'injuryTime', 'extraTime'];
    private static $kDorsal    = ['shirtNumber', 'shirtNo', 'jerseyNumber', 'number', 'dorsal', 'squadNumber'];
    private static $kDescGol   = ['action', 'goalType', 'typeName', 'description', 'actionName'];
    private static $kDescCard  = ['action', 'cardType', 'typeName', 'description', 'actionName'];

    /**
     * actionId de Transfermarkt. 200 = gol común, 206 = gol en contra,
     * 301 = amarilla, 400 = cambio. Los 2xx que no conocemos igual son goles;
     * los 3xx, tarjetas. Lo que no matchee cae al texto de `action`.
     */
    private static $accionGol = [
        200 => self::GOL_JUGADA,
        201 => self::GOL_JUGADA,
        202 => self::GOL_CABEZA,
        203 => self::GOL_PENAL,
        204 => self::GOL_TIROLIBRE,
        205 => self::GOL_JUGADA,
        206 => self::GOL_ENCONTRA,
    ];

    /** Avisos y datos sin reconocer que junta la corrida (se muestran en pantalla). */
    private $avisos = [];
    /** Cache de mapeos para no consultar de más dentro de una tanda. */
    private $mapaJugadores = null;
    private $mapaArbitros  = null;
    private $mapaEquipos   = null;
    private $mapaTecnicos  = null;
    /** En la vista previa los jugadores que se crearían llevan un id negativo. */
    private $nombresPreview = [];
    private $tiposPreview   = [];
    private $cacheTipos     = [];
    private $proximoPreview = -1;
    /** tm_club_id -> equipo_id sacado de la fila de import_partidos del partido. */
    private $mapaStaging = [];
    /** Bajar la foto de perfil de cada persona nueva (una llamada más por cabeza). */
    private $conFotos = true;
    private $fotosBajadas = 0;
    /** Filas que la base rechazó (índices únicos, etc.). */
    private $fallidas = 0;

    // ═════════════════════════════════ API ═════════════════════════════════

    /**
     * @param  int    $partidoId  partido nuestro que hay que completar
     * @param  string $gameId     gameId de Transfermarkt
     * @param  array  $opts       ['escribir' => bool, 'forzar' => bool, 'crear_jugadores' => bool]
     * @return array  informe
     */
    public function importar($partidoId, $gameId, array $opts = [])
    {
        $escribir = !empty($opts['escribir']);
        $forzar   = !empty($opts['forzar']);
        $crear    = array_key_exists('crear_jugadores', $opts) ? (bool) $opts['crear_jugadores'] : true;
        $this->conFotos = array_key_exists('fotos', $opts) ? (bool) $opts['fotos'] : true;

        $this->avisos = [];
        $this->fotosBajadas = 0;
        $this->fallidas = 0;

        $informe = [
            'ok'          => false,
            'escrito'     => false,
            'error'       => null,
            'partido_id'  => (int) $partidoId,
            'game_id'     => (string) $gameId,
            'avisos'      => [],
            'fallidas'    => 0,
            'plan'        => ['alineacions' => [], 'gols' => [], 'tarjetas' => [], 'cambios' => [],
                'arbitros' => [], 'tecnicos' => [], 'plantillas' => []],
            'creados'     => ['jugadores' => [], 'arbitros' => [], 'tecnicos' => []],
            'llamadas'    => 0,
            'crudo'       => null,
        ];

        $partido = Partido::find($partidoId);
        if (!$partido) {
            $informe['error'] = 'No existe el partido #' . (int) $partidoId . '.';
            return $informe;
        }

        // El candado es para ESCRIBIR, no para mirar: la vista previa no toca la
        // base, así que siempre muestra lo que haría, avisando que ya hay datos.
        $yaCargado = Alineacion::where('partido_id', $partido->id)->count();
        if ($yaCargado && !$forzar) {
            if ($escribir) {
                $informe['error'] = 'El partido #' . $partido->id . ' ya tiene alineación cargada ('
                    . $yaCargado . ' jugadores). Para reemplazarla usá "Rehacer".';
                return $informe;
            }
            $this->aviso('Este partido ya tiene detalle cargado (' . $yaCargado . ' en la alineación). '
                . 'Lo de abajo es lo que quedaría si lo rehacés: se borra lo actual y se escribe esto. '
                . 'Los técnicos del partido no se borran nunca.');
        }

        // ── 1) El partido entero, en una sola llamada ──────────────────────
        $json = HttpHelper::getJson(self::TMAPI . '/game/' . rawurlencode($gameId));
        $informe['llamadas']++;
        if (!is_array($json) || empty($json)) {
            $err = method_exists('App\Services\HttpHelper', 'getLastJsonError') ? HttpHelper::getLastJsonError() : null;
            $informe['error'] = 'La API no devolvió el partido ' . $gameId
                . (is_array($err) ? ' — ' . json_encode($err, JSON_UNESCAPED_UNICODE) : '');
            return $informe;
        }
        $game = isset($json['data']) ? $json['data'] : $json;
        $informe['crudo'] = $game;

        // ── 2) Qué lado de Transfermarkt es nuestro local ──────────────────
        // La fila de staging que creó este partido ya trae resueltos los dos
        // clubes (a veces por nombre, sin que hayan quedado en equipo_tm).
        $this->mapaStaging = $this->mapaDesdeStaging($partido->id);
        $lados = $this->orientar($game, $partido, $escribir);
        if ($lados === null) {
            $informe['error'] = $this->ultimoAviso() ?: 'No pude aparear los clubes del partido con los de la base.';
            $informe['avisos'] = $this->avisos;
            return $informe;
        }

        // ── 3) Juntar todos los ids de jugadores que aparecen ──────────────
        $idsTm = [];
        foreach ($lados as $lado) {
            foreach ($this->jugadoresDelLado($game, $lado['clave']) as $p) {
                if ($p['tm_id'] !== null) $idsTm[(string) $p['tm_id']] = true;
            }
            foreach ($this->accionesDelLado($game, $lado['clave']) as $accion) {
                foreach ($accion['ids'] as $id) {
                    if ($id !== null) $idsTm[(string) $id] = true;
                }
            }
        }
        $idsTm = array_keys($idsTm);

        // ── 4) Resolverlos contra jugador_tm; los que faltan, del perfil ───
        $mapa = $this->mapaJugadores();
        $faltan = [];
        foreach ($idsTm as $id) {
            if (!isset($mapa[$id])) $faltan[] = $id;
        }

        $nuevos = [];
        if (!empty($faltan)) {
            if (!$crear) {
                $informe['error'] = count($faltan) . ' jugador(es) sin mapear y la creación automática está apagada: '
                    . implode(', ', array_slice($faltan, 0, 20));
                $informe['avisos'] = $this->avisos;
                return $informe;
            }
            $perfiles = $this->traerPerfiles($faltan, $informe);
            foreach ($faltan as $id) {
                if (!isset($perfiles[$id])) {
                    $this->aviso('Sin perfil para el jugador TM ' . $id . ': no puedo crearlo.');
                    continue;
                }
                $res = $this->resolverJugador($id, $perfiles[$id], $escribir);
                if ($res['jugador_id']) {
                    $mapa[$id] = (int) $res['jugador_id'];
                    if ($res['creado']) $nuevos[] = $res['descripcion'];
                }
            }
        }
        $informe['creados']['jugadores'] = $nuevos;
        $this->mapaJugadores = $mapa;

        // ── 5) Armar el plan ───────────────────────────────────────────────
        $plan = ['alineacions' => [], 'gols' => [], 'tarjetas' => [], 'cambios' => [],
            'arbitros' => [], 'tecnicos' => [], 'plantillas' => []];

        // La plantilla de un equipo se carga UNA sola vez por torneo, en un
        // grupo, aunque después el equipo pase a otro (zona -> fase final). Por
        // eso lo que manda es el torneo; el grupo sólo se usa si hay que crearla.
        $grupoId  = optional($partido->fecha)->grupo_id;
        $torneoId = optional(optional($partido->fecha)->grupo)->torneo_id;

        // Controles que valen para los dos equipos a la vez.
        //   $jugadoresPuestos: nadie puede estar dos veces en la misma alineación.
        //   $dorsalesUsados:   `alineacions` tiene un índice único
        //                      (partido_id, equipo_id, dorsal), así que un dorsal
        //                      repetido en el mismo equipo hace fallar el guardado.
        $jugadoresPuestos = [];
        $dorsalesUsados   = [];

        // Los dorsales que vos cargaste en la plantilla. Son la referencia:
        // Transfermarkt a veces manda el número cambiado o repetido.
        $mapaDorsales = $this->dorsalesDePlantilla($partido, $lados);

        foreach ($lados as $iLado => $lado) {
            $equipoId = $lado['equipo_id'];
            // El rival, para mostrar bien los goles en contra: Transfermarkt los
            // lista en el bloque del club que se beneficia, pero el jugador que
            // lo hizo es del otro equipo.
            $otroNombre = $lados[$iLado === 0 ? 1 : 0]['equipo_nombre'];

            // Quién arrancó y quién estaba en el banco. Es lo que nos deja saber,
            // en cada cambio, cuál de los dos jugadores entra y cuál sale:
            // Transfermarkt los llama "activo" y "pasivo" sin decir la dirección.
            $jugadoresLado = $this->jugadoresDelLado($game, $lado['clave']);
            $banco = []; $titulares = []; $yaEntraron = []; $yaSalieron = [];
            foreach ($jugadoresLado as $p) {
                if ($p['tm_id'] === null) continue;
                if ($p['tipo'] === 'Suplente') $banco[(string) $p['tm_id']] = true;
                else $titulares[(string) $p['tm_id']] = true;
            }

            // Alineación: titulares primero, después el banco.
            foreach ($jugadoresLado as $p) {
                $jugadorId = isset($mapa[(string) $p['tm_id']]) ? $mapa[(string) $p['tm_id']] : null;
                if (!$jugadorId) {
                    $this->aviso('Alineación: jugador TM ' . $p['tm_id'] . ' sin resolver, lo salteo.');
                    continue;
                }
                $nombre = $this->nombreJugador($jugadorId);

                // Mismo jugador dos veces: casi siempre significa que dos ids de
                // Transfermarkt distintos terminaron apuntando al mismo jugador
                // nuestro. Es un problema de mapeo, no de la alineación.
                if (isset($jugadoresPuestos[$jugadorId])) {
                    $this->aviso('"' . $nombre . '" aparece dos veces en la alineación: los jugadores TM '
                        . $jugadoresPuestos[$jugadorId] . ' y ' . $p['tm_id'] . ' están mapeados al mismo jugador #'
                        . $jugadorId . '. Salteo el segundo — revisá el mapeo en jugador_tm.');
                    continue;
                }
                $jugadoresPuestos[$jugadorId] = $p['tm_id'];

                // ── Dorsal ────────────────────────────────────────────────
                // Por defecto vale el de Transfermarkt, que es el de ESE partido.
                // Si no coincide con el de la plantilla, se avisa. Y si viene
                // repetido dentro del equipo, ahí sí manda la plantilla: es el
                // dato que cargaste vos y encima resuelve el choque.
                $dorsal   = $p['dorsal'];
                $dePlanti = isset($mapaDorsales[$equipoId . '-' . $jugadorId])
                    ? (int) $mapaDorsales[$equipoId . '-' . $jugadorId] : null;
                $fuente   = 'Transfermarkt';

                if ($dePlanti !== null && $dorsal !== null && $dePlanti !== (int) $dorsal) {
                    $this->aviso('Dorsal distinto para "' . $nombre . '" (' . $lado['equipo_nombre'] . '): '
                        . 'Transfermarkt dice ' . $dorsal . ' y la plantilla dice ' . $dePlanti
                        . '. Uso el de Transfermarkt, que es el de este partido.');
                    $fuente = 'TM ' . $dorsal . ' ≠ plantilla ' . $dePlanti;
                }

                if ($dorsal === null && $dePlanti !== null) {
                    $dorsal = $dePlanti;
                    $fuente = 'plantilla (TM no lo trajo)';
                }

                $claveDorsal = $equipoId . '-' . $dorsal;
                if ($dorsal !== null && isset($dorsalesUsados[$claveDorsal])) {
                    $otro = $dorsalesUsados[$claveDorsal];

                    if ($dePlanti !== null && !isset($dorsalesUsados[$equipoId . '-' . $dePlanti])) {
                        $this->aviso('Dorsal ' . $dorsal . ' repetido en ' . $lado['equipo_nombre']
                            . ' (ya lo tiene "' . $otro . '"): para "' . $nombre . '" uso el de la plantilla, '
                            . $dePlanti . '.');
                        $dorsal = $dePlanti;
                        $fuente = 'plantilla (TM lo repetía)';
                    } else {
                        $this->aviso('Dorsal ' . $dorsal . ' repetido en ' . $lado['equipo_nombre']
                            . ': ya lo tiene "' . $otro . '" y "' . $nombre . '" no lo tiene cargado en la plantilla, '
                            . 'así que queda sin dorsal. Cargáselo en la plantilla y rehacé el partido.');
                        $dorsal = null;
                        $fuente = 'sin dorsal (repetido)';
                    }
                }

                if ($dorsal !== null) $dorsalesUsados[$equipoId . '-' . $dorsal] = $nombre;

                $plan['alineacions'][] = [
                    'partido_id' => $partido->id,
                    'jugador_id' => $jugadorId,
                    'equipo_id'  => $equipoId,
                    'dorsal'     => $dorsal,
                    'tipo'       => $p['tipo'],
                    // `orden` no es la posición en la lista: es el PUESTO.
                    // Mismo criterio que FechaController y AlineacionController.
                    'orden'      => $this->ordenPorPuesto($jugadorId),
                    '_nombre'    => $nombre,
                    '_equipo'    => $lado['equipo_nombre'],
                    '_fuente'    => $fuente,
                    '_dudoso'    => ($fuente !== 'Transfermarkt'),
                ];

                // La pantalla de alineaciones arma sus <select> con la PLANTILLA
                // del equipo en ese torneo. Si el jugador no está ahí, el select
                // sale vacío y al guardar revienta. Así que lo sumamos.
                if ($grupoId) {
                    $plan['plantillas'][] = [
                        'torneo_id'  => (int) $torneoId,
                        'grupo_id'   => (int) $grupoId,
                        'equipo_id'  => $equipoId,
                        'jugador_id' => $jugadorId,
                        'dorsal'     => $dorsal,
                        '_nombre'    => $nombre,
                        '_equipo'    => $lado['equipo_nombre'],
                    ];
                }
            }

            // Goles / tarjetas / cambios.
            foreach ($this->accionesDelLado($game, $lado['clave']) as $accion) {
                if ($accion['clase'] === 'gol') {
                    $jugadorId = isset($mapa[(string) $accion['ids'][0]]) ? $mapa[(string) $accion['ids'][0]] : null;
                    if (!$jugadorId) { $this->aviso('Gol de un jugador sin resolver (TM ' . $accion['ids'][0] . ').'); continue; }
                    $tipo = $this->tipoGol($accion['crudo']);
                    $plan['gols'][] = [
                        'partido_id' => $partido->id,
                        'jugador_id' => $jugadorId,
                        'minuto'     => $accion['minuto'],
                        'tipo'       => $tipo['tipo'],
                        '_nombre'    => $this->nombreJugador($jugadorId),
                        // En un gol en contra, el goleador juega para el rival.
                        '_equipo'    => $tipo['tipo'] === self::GOL_ENCONTRA
                            ? $otroNombre . ' → ' . $lado['equipo_nombre']
                            : $lado['equipo_nombre'],
                        '_fuente'    => $tipo['fuente'],
                        '_dudoso'    => $tipo['dudoso'],
                    ];
                    if ($tipo['dudoso']) {
                        $this->aviso('Tipo de gol no reconocido: "' . $tipo['fuente'] . '" → lo cargo como Jugada.');
                    }
                } elseif ($accion['clase'] === 'tarjeta') {
                    $jugadorId = isset($mapa[(string) $accion['ids'][0]]) ? $mapa[(string) $accion['ids'][0]] : null;
                    if (!$jugadorId) { $this->aviso('Tarjeta de un jugador sin resolver (TM ' . $accion['ids'][0] . ').'); continue; }
                    $tipo = $this->tipoTarjeta($accion['crudo']);
                    if ($tipo['tipo'] === null) {
                        $this->aviso('Tipo de tarjeta no reconocido: "' . $tipo['fuente'] . '" → la salteo.');
                        continue;
                    }
                    $plan['tarjetas'][] = [
                        'partido_id' => $partido->id,
                        'jugador_id' => $jugadorId,
                        'minuto'     => $accion['minuto'],
                        'tipo'       => $tipo['tipo'],
                        '_nombre'    => $this->nombreJugador($jugadorId),
                        '_equipo'    => $lado['equipo_nombre'],
                        '_fuente'    => $tipo['fuente'],
                    ];
                } elseif ($accion['clase'] === 'cambio') {
                    $dir = $this->direccionCambio($accion['ids'], $banco, $titulares, $yaEntraron, $yaSalieron);

                    // Control de coherencia: nadie sale sin haber estado en cancha,
                    // nadie entra dos veces. Si algo de esto salta, la deducción se
                    // equivocó y hay que mirar ese cambio a mano.
                    if ($dir['sale'] !== null) {
                        $s = (string) $dir['sale'];
                        if (!isset($titulares[$s]) && !isset($yaEntraron[$s])) {
                            $this->aviso('Minuto ' . $accion['minuto'] . ': sale el jugador TM ' . $s
                                . ' pero no era titular ni lo vi entrar antes. Revisá ese cambio.');
                            $dir['dudoso'] = true;
                        }
                        if (isset($yaSalieron[$s])) {
                            $this->aviso('Minuto ' . $accion['minuto'] . ': el jugador TM ' . $s . ' sale dos veces.');
                            $dir['dudoso'] = true;
                        }
                        $yaSalieron[$s] = true;
                    }
                    if ($dir['entra'] !== null) {
                        $en = (string) $dir['entra'];
                        if (isset($yaEntraron[$en])) {
                            $this->aviso('Minuto ' . $accion['minuto'] . ': el jugador TM ' . $en . ' entra dos veces.');
                            $dir['dudoso'] = true;
                        }
                        if (isset($titulares[$en])) {
                            $this->aviso('Minuto ' . $accion['minuto'] . ': entra el jugador TM ' . $en
                                . ' pero figura como titular. Revisá ese cambio.');
                            $dir['dudoso'] = true;
                        }
                        $yaEntraron[$en] = true;
                    }

                    foreach ([['Sale', $dir['sale']], ['Entra', $dir['entra']]] as $par) {
                        if ($par[1] === null) continue;   // cambio con un solo jugador informado
                        $jugadorId = isset($mapa[(string) $par[1]]) ? $mapa[(string) $par[1]] : null;
                        if (!$jugadorId) {
                            $this->aviso('Cambio del minuto ' . $accion['minuto'] . ': jugador TM ' . $par[1]
                                . ' sin resolver, salteo el "' . $par[0] . '".');
                            continue;
                        }
                        $plan['cambios'][] = [
                            'partido_id' => $partido->id,
                            'jugador_id' => $jugadorId,
                            'minuto'     => $accion['minuto'],
                            'tipo'       => $par[0],
                            '_nombre'    => $this->nombreJugador($jugadorId),
                            '_equipo'    => $lado['equipo_nombre'],
                            '_fuente'    => $dir['como'],
                            '_dudoso'    => $dir['dudoso'],
                        ];
                    }
                }
            }
        }

        // Árbitros: sólo los que ya estén atados en arbitro_tm. Los que no,
        // se listan como aviso — no inventamos personas sin nombre.
        $plan['arbitros'] = $this->planArbitros($game, $partido, $escribir, $informe);
        $plan['tecnicos'] = $this->planTecnicos($game, $partido, $lados, $escribir, $informe);

        $informe['plan']   = $plan;
        $informe['avisos']   = $this->avisos;
        $informe['ok']       = true;
        $informe['llamadas'] += $this->fotosBajadas;   // cada foto es una llamada más

        // ── 6) Guardar ─────────────────────────────────────────────────────
        if ($escribir) {
            try {
                DB::transaction(function () use ($plan, $partido, $forzar) {
                    if ($forzar) {
                        Alineacion::where('partido_id', $partido->id)->delete();
                        Gol::where('partido_id', $partido->id)->delete();
                        Tarjeta::where('partido_id', $partido->id)->delete();
                        Cambio::where('partido_id', $partido->id)->delete();
                        PartidoArbitro::where('partido_id', $partido->id)->delete();
                    }
                    // Primero la plantilla: es lo que hace que el jugador exista
                    // para la pantalla de alineaciones del torneo.
                    $this->grabarPlantillas($plan['plantillas']);

                    $this->grabarFilas(Alineacion::class,    $plan['alineacions'], 'la alineación');
                    $this->grabarFilas(Gol::class,          $plan['gols'],        'un gol');
                    $this->grabarFilas(Tarjeta::class,      $plan['tarjetas'],    'una tarjeta');
                    $this->grabarFilas(Cambio::class,       $plan['cambios'],     'un cambio');
                    $this->grabarFilas(PartidoArbitro::class, $plan['arbitros'],  'un árbitro');

                    // El DT del club dirigido ya lo pudo haber cargado el
                    // importador de partidos: nunca pisamos ni duplicamos, sólo
                    // agregamos el que falte.
                    foreach ($plan['tecnicos'] as $r) {
                        $fila = $this->limpiar($r);
                        $existe = DB::table('partido_tecnicos')
                            ->where('partido_id', $fila['partido_id'])
                            ->where('equipo_id', $fila['equipo_id'])->exists();
                        if (!$existe) \App\PartidoTecnico::create($fila);
                    }
                });
                $informe['escrito'] = true;
            } catch (\Exception $e) {
                $informe['ok']    = false;
                $informe['error'] = 'Error guardando el detalle: ' . $e->getMessage();
                Log::error('TmDetallePartido partido ' . $partido->id . ': ' . $e->getMessage(), []);
            }
        }

        $informe['avisos']   = $this->avisos;
        $informe['fallidas'] = $this->fallidas;
        return $informe;
    }

    // ═══════════════════════════ ORIENTACIÓN ═══════════════════════════

    /**
     * Decide qué lado de Transfermarkt (homeClub / awayClub) corresponde a
     * cada equipo nuestro. Si no coinciden, aborta: mejor no cargar nada que
     * cargar la alineación del rival.
     */
    private function orientar(array $game, Partido $partido, $escribir = false)
    {
        $mapaEq = $this->mapaEquipos();

        $tmLocal = $this->valor(isset($game['homeClub']) ? $game['homeClub'] : [], ['id', 'clubId']);
        $tmVisit = $this->valor(isset($game['awayClub']) ? $game['awayClub'] : [], ['id', 'clubId']);

        $idLocal = ($tmLocal !== null && isset($mapaEq[(string) $tmLocal])) ? $mapaEq[(string) $tmLocal] : null;
        $idVisit = ($tmVisit !== null && isset($mapaEq[(string) $tmVisit])) ? $mapaEq[(string) $tmVisit] : null;

        // 1er rescate: la fila de staging ya sabe a qué equipo corresponde cada
        // club de este partido, aunque el mapeo nunca haya llegado a equipo_tm
        // (el importador de partidos también resuelve por nombre).
        if (!$idLocal && $tmLocal !== null && isset($this->mapaStaging[(string) $tmLocal])) {
            $idLocal = (int) $this->mapaStaging[(string) $tmLocal];
            $this->aprenderClub($tmLocal, $idLocal, 'staging', $escribir);
        }
        if (!$idVisit && $tmVisit !== null && isset($this->mapaStaging[(string) $tmVisit])) {
            $idVisit = (int) $this->mapaStaging[(string) $tmVisit];
            $this->aprenderClub($tmVisit, $idVisit, 'staging', $escribir);
        }

        // 2do rescate: si un lado quedó resuelto y coincide con uno de los dos
        // equipos del partido, el otro lado es necesariamente el que sobra.
        $pl0 = (int) $partido->equipol_id;
        $pv0 = (int) $partido->equipov_id;
        if ($idLocal && !$idVisit && ($idLocal === $pl0 || $idLocal === $pv0) && $tmVisit !== null) {
            $idVisit = ($idLocal === $pl0) ? $pv0 : $pl0;
            $this->aprenderClub($tmVisit, $idVisit, 'inferido', $escribir);
            $this->aviso('Deduje que el club de Transfermarkt #' . $tmVisit . ' es '
                . $this->nombreEquipo($idVisit) . ' (era el único que quedaba en el partido).');
        } elseif ($idVisit && !$idLocal && ($idVisit === $pl0 || $idVisit === $pv0) && $tmLocal !== null) {
            $idLocal = ($idVisit === $pl0) ? $pv0 : $pl0;
            $this->aprenderClub($tmLocal, $idLocal, 'inferido', $escribir);
            $this->aviso('Deduje que el club de Transfermarkt #' . $tmLocal . ' es '
                . $this->nombreEquipo($idLocal) . ' (era el único que quedaba en el partido).');
        }

        if (!$idLocal || !$idVisit) {
            $this->aviso('Club de Transfermarkt sin mapear en equipo_tm: '
                . ($idLocal ? '' : 'local #' . $tmLocal . ' ') . ($idVisit ? '' : 'visitante #' . $tmVisit)
                . '. Atalo desde la carga de partidos (el sondeo del DT aprende los mapeos) '
                . 'o insertá la fila a mano en equipo_tm.');
            return null;
        }

        $pl = (int) $partido->equipol_id;
        $pv = (int) $partido->equipov_id;

        if ($idLocal === $pl && $idVisit === $pv) {
            return [
                ['clave' => 'homeClub', 'equipo_id' => $pl, 'equipo_nombre' => $this->nombreEquipo($pl)],
                ['clave' => 'awayClub', 'equipo_id' => $pv, 'equipo_nombre' => $this->nombreEquipo($pv)],
            ];
        }
        if ($idLocal === $pv && $idVisit === $pl) {
            // El partido está cargado con la localía al revés que Transfermarkt
            // (pasa con las finales en cancha neutral). Igual sirve: lo que
            // importa es a qué equipo va cada jugador.
            $this->aviso('Ojo: en la base el local es ' . $this->nombreEquipo($pl)
                . ' y en Transfermarkt es ' . $this->nombreEquipo($pv) . '. Uso el mapeo por club, no por localía.');
            return [
                ['clave' => 'homeClub', 'equipo_id' => $pv, 'equipo_nombre' => $this->nombreEquipo($pv)],
                ['clave' => 'awayClub', 'equipo_id' => $pl, 'equipo_nombre' => $this->nombreEquipo($pl)],
            ];
        }

        $this->aviso('Los clubes no coinciden. Base: #' . $pl . ' vs #' . $pv
            . ' · Transfermarkt: #' . $idLocal . ' vs #' . $idVisit
            . '. Puede ser un gameId equivocado o un mapeo de equipo_tm mal aprendido.');
        return null;
    }

    // ═══════════════════════════ EXTRACCIÓN ═══════════════════════════

    /** Titulares y suplentes de un lado, ya con dorsal, tipo y orden. */
    private function jugadoresDelLado(array $game, $clave)
    {
        $out = [];
        $lineup = isset($game[$clave]['lineup']) && is_array($game[$clave]['lineup']) ? $game[$clave]['lineup'] : [];

        foreach ([['players', 'Titular'], ['substitutes', 'Suplente']] as $par) {
            list($rama, $tipo) = $par;
            if (!isset($lineup[$rama]) || !is_array($lineup[$rama])) continue;
            $orden = 0;
            foreach ($lineup[$rama] as $p) {
                if (!is_array($p)) continue;
                $orden++;
                $dorsal = $this->valor($p, self::$kDorsal);
                $out[] = [
                    'tm_id'  => $this->valor($p, self::$kJugador),
                    'dorsal' => ($dorsal === null || $dorsal === '') ? null : (int) $dorsal,
                    'tipo'   => $tipo,
                    'orden'  => $orden,
                    'crudo'  => $p,
                ];
            }
        }
        return $out;
    }

    /** Goles, tarjetas y cambios de un lado, normalizados a una lista plana. */
    private function accionesDelLado(array $game, $clave)
    {
        $out = [];
        $acc = isset($game[$clave]['actions']) && is_array($game[$clave]['actions']) ? $game[$clave]['actions'] : [];

        foreach (['goals' => 'gol', 'cards' => 'tarjeta', 'substitutes' => 'cambio'] as $rama => $clase) {
            if (!isset($acc[$rama]) || !is_array($acc[$rama])) continue;
            foreach ($acc[$rama] as $a) {
                if (!is_array($a)) continue;
                $minuto = $this->valor($a, self::$kMinuto);
                $minuto = ($minuto === null || $minuto === '') ? null : (int) $minuto;

                // El minuto que guardamos es el del reloj: 90+7 se carga como 90,
                // igual que hace el import de incidencias de promiedos.
                $activo  = $this->valor($a, self::$kJugador);
                $pasivo  = $this->valor($a, self::$kOtro);

                if ($clase === 'cambio') {
                    if ($activo === null && $pasivo === null) {
                        $this->aviso('Cambio sin ids de jugador: ' . $this->resumenCrudo($a));
                        continue;
                    }
                    $out[] = ['clase' => 'cambio', 'minuto' => $minuto, 'ids' => [$activo, $pasivo], 'crudo' => $a];
                } else {
                    if ($activo === null) {
                        $this->aviso(ucfirst($clase) . ' sin id de jugador: ' . $this->resumenCrudo($a));
                        continue;
                    }
                    $out[] = ['clase' => $clase, 'minuto' => $minuto, 'ids' => [$activo], 'crudo' => $a];
                }
            }
        }

        // Orden cronológico. Es IMPRESCINDIBLE para los cambios: para saber si un
        // suplente sale, primero hay que haber visto que entró. Los sin minuto van
        // al final. usort no es estable antes de PHP 8, así que desempatamos por
        // la posición original para no barajar acciones del mismo minuto.
        $i = 0;
        foreach ($out as &$o) { $o['_i'] = $i++; }
        unset($o);
        usort($out, function ($x, $y) {
            $mx = $x['minuto'] === null ? PHP_INT_MAX : $x['minuto'];
            $my = $y['minuto'] === null ? PHP_INT_MAX : $y['minuto'];
            if ($mx !== $my) return $mx < $my ? -1 : 1;
            return $x['_i'] < $y['_i'] ? -1 : ($x['_i'] > $y['_i'] ? 1 : 0);
        });
        return $out;
    }

    /**
     * Quién entra y quién sale en un cambio.
     *
     * Transfermarkt da `activePlayerId` y `passivePlayerId` sin decir cuál es
     * cuál, así que no nos fiamos del orden: lo resolvemos con la alineación.
     *
     * OJO: "el del banco entra" NO alcanza. Un suplente que entró a los 60 puede
     * salir a los 80, y en ese cambio los DOS son del banco. Por eso llevamos
     * cuenta de quién ya entró y quién ya salió, y por eso las acciones se
     * recorren en orden cronológico (ver el sort en accionesDelLado): sin ese
     * orden, al llegar al cambio de los 80 todavía no sabríamos que el que sale
     * había entrado a los 60.
     *
     * Prioridad:
     *   1. uno del banco y el otro titular  -> caso normal
     *   2. los dos del banco  -> sale el que ya había entrado
     *   3. uno de los dos ya salió de la cancha -> el otro es el que entra
     *   4. nada de lo anterior -> activo=entra, marcado como dudoso
     */
    private function direccionCambio(array $ids, array $banco, array $titulares, array $yaEntraron, array $yaSalieron = [])
    {
        $a = $ids[0] === null ? null : (string) $ids[0];
        $b = (!isset($ids[1]) || $ids[1] === null) ? null : (string) $ids[1];

        // Un solo jugador informado: el banco decide si entró o salió, pero si
        // ya lo habíamos visto entrar, entonces este cambio es su salida.
        if ($a === null || $b === null) {
            $uno = $a !== null ? $a : $b;
            if ($uno === null) return ['entra' => null, 'sale' => null, 'como' => 'sin datos', 'dudoso' => true];
            if (isset($yaEntraron[$uno])) return ['entra' => null, 'sale' => $uno, 'como' => 'ya había entrado', 'dudoso' => false];
            if (isset($banco[$uno]))      return ['entra' => $uno, 'sale' => null, 'como' => 'estaba en el banco', 'dudoso' => false];
            if (isset($titulares[$uno]))  return ['entra' => null, 'sale' => $uno, 'como' => 'era titular', 'dudoso' => false];
            return ['entra' => $uno, 'sale' => null, 'como' => 'un solo jugador, sin alineación', 'dudoso' => true];
        }

        $aBanco = isset($banco[$a]); $bBanco = isset($banco[$b]);
        $aTit   = isset($titulares[$a]); $bTit = isset($titulares[$b]);

        // 1) El caso normal: uno esperaba en el banco, el otro estaba jugando
        // desde el arranque. Pero si el "del banco" ya entró antes, no puede
        // volver a entrar: entonces el que entra es el otro.
        if ($aBanco && $bTit && !isset($yaEntraron[$a])) return ['entra' => $a, 'sale' => $b, 'como' => 'banco/titular', 'dudoso' => false];
        if ($bBanco && $aTit && !isset($yaEntraron[$b])) return ['entra' => $b, 'sale' => $a, 'como' => 'banco/titular', 'dudoso' => false];

        // 2) Los dos salieron del banco: el reemplazado es el que ya había entrado.
        if ($aBanco && $bBanco) {
            if (isset($yaEntraron[$a]) && !isset($yaEntraron[$b])) return ['entra' => $b, 'sale' => $a, 'como' => 'los dos del banco: A ya había entrado', 'dudoso' => false];
            if (isset($yaEntraron[$b]) && !isset($yaEntraron[$a])) return ['entra' => $a, 'sale' => $b, 'como' => 'los dos del banco: B ya había entrado', 'dudoso' => false];
        }

        // 3) Si uno ya salió de la cancha, no puede ser el que sale de nuevo.
        if (isset($yaSalieron[$a]) && !isset($yaSalieron[$b])) return ['entra' => $a, 'sale' => $b, 'como' => 'el otro ya había salido', 'dudoso' => true];
        if (isset($yaSalieron[$b]) && !isset($yaSalieron[$a])) return ['entra' => $b, 'sale' => $a, 'como' => 'el otro ya había salido', 'dudoso' => true];

        // 4) Sin nada en qué apoyarse.
        $this->aviso('Cambio entre TM ' . $a . ' y TM ' . $b . ': no pude deducir quién entra y quién sale '
            . '(ninguno de los dos aparece en la alineación de ese equipo). Asumo que entra el primero — revisalo.');
        return ['entra' => $a, 'sale' => $b, 'como' => 'sin alineación', 'dudoso' => true];
    }

    /**
     * Transfermarkt describe el gol en texto libre. Mapeamos lo que reconocemos
     * y el resto cae en 'Jugada', pero queda marcado como dudoso para que se
     * vea en pantalla y podamos ampliar esta tabla.
     */
    private function tipoGol(array $a)
    {
        $txt = mb_strtolower($this->juntarTexto($a, self::$kDescGol));

        // "Not reported" quiere decir que Transfermarkt no aclaró cómo fue el gol.
        // No es un caso a corregir: va como Jugada y listo.
        if ($txt === '' || mb_strpos($txt, 'not reported') !== false || mb_strpos($txt, 'nicht') !== false) {
            $cod = (int) $this->valor($a, ['actionId', 'typeId']);
            if ($cod && isset(self::$accionGol[$cod])) {
                return ['tipo' => self::$accionGol[$cod], 'fuente' => 'sin detallar (actionId ' . $cod . ')', 'dudoso' => false];
            }
            return ['tipo' => self::GOL_JUGADA, 'fuente' => 'sin detallar', 'dudoso' => false];
        }

        $reglas = [
            self::GOL_ENCONTRA  => ['own goal', 'own-goal', 'owngoal', 'eigentor', 'en propia', 'propia meta', 'autogol'],
            self::GOL_PENAL     => ['penalty', 'penal', 'elfmeter', 'spot kick', 'pen.'],
            self::GOL_TIROLIBRE => ['free kick', 'free-kick', 'freekick', 'freistoss', 'freistoß', 'tiro libre', 'direct free'],
            self::GOL_CABEZA    => ['header', 'head', 'kopfball', 'cabeza', 'de cabeza'],
        ];
        foreach ($reglas as $tipo => $agujas) {
            foreach ($agujas as $aguja) {
                if (mb_strpos($txt, $aguja) !== false) {
                    return ['tipo' => $tipo, 'fuente' => $txt, 'dudoso' => false];
                }
            }
        }

        // Descripciones de jugada normal que sí conocemos: no las marcamos dudosas.
        $jugada = ['right-footed shot', 'left-footed shot', 'shot', 'tap-in', 'solo run', 'counter',
            'rechter', 'linker', 'schuss', 'remate', 'derecha', 'izquierda', 'contragolpe', 'combination'];
        foreach ($jugada as $aguja) {
            if (mb_strpos($txt, $aguja) !== false) {
                return ['tipo' => self::GOL_JUGADA, 'fuente' => $txt, 'dudoso' => false];
            }
        }

        // Último recurso: el código numérico de la acción.
        $cod = (int) $this->valor($a, ['actionId', 'typeId']);
        if ($cod && isset(self::$accionGol[$cod])) {
            return ['tipo' => self::$accionGol[$cod], 'fuente' => $txt . ' (actionId ' . $cod . ')', 'dudoso' => false];
        }

        return ['tipo' => self::GOL_JUGADA, 'fuente' => $txt . ($cod ? ' (actionId ' . $cod . ')' : ''), 'dudoso' => true];
    }

    /** Amarilla / Doble Amarilla / Roja. Si no la reconozco, no la cargo. */
    private function tipoTarjeta(array $a)
    {
        $txt = mb_strtolower($this->juntarTexto($a, self::$kDescCard));

        $dobles = ['second yellow', '2nd yellow', 'yellow/red', 'yellow-red', 'gelb-rot', 'doble amarilla', 'segunda amarilla'];
        foreach ($dobles as $aguja) {
            if (mb_strpos($txt, $aguja) !== false) return ['tipo' => 'Doble Amarilla', 'fuente' => $txt];
        }
        foreach (['red', 'rot', 'roja', 'expulsion', 'expulsión', 'sent off'] as $aguja) {
            if (mb_strpos($txt, $aguja) !== false) return ['tipo' => 'Roja', 'fuente' => $txt];
        }
        foreach (['yellow', 'gelb', 'amarilla'] as $aguja) {
            if (mb_strpos($txt, $aguja) !== false) return ['tipo' => 'Amarilla', 'fuente' => $txt];
        }

        // Si el texto no alcanzó, el código de la acción.
        // 301 = amarilla, 302 = doble amarilla, 303 = roja directa.
        $cod = (int) $this->valor($a, ['actionId', 'cardTypeId', 'typeId']);
        $mapa = [301 => 'Amarilla', 302 => 'Doble Amarilla', 303 => 'Roja',
            1 => 'Amarilla', 2 => 'Doble Amarilla', 3 => 'Roja'];
        if ($cod && isset($mapa[$cod])) {
            return ['tipo' => $mapa[$cod], 'fuente' => ($txt !== '' ? $txt . ' ' : '') . '(actionId ' . $cod . ')'];
        }

        return ['tipo' => null, 'fuente' => $txt !== '' ? $txt : $this->resumenCrudo($a)];
    }

    /**
     * Árbitros del partido. Sólo se cargan los que ya estén atados en
     * `arbitro_tm`; los desconocidos quedan como aviso con su id, para que
     * los ates a mano una vez (son pocos y se repiten mucho).
     */
    private function planArbitros(array $game, Partido $partido, $escribir = false, array &$informe = null)
    {
        $out  = [];
        $mapa = $this->mapaArbitros();

        // Forma A: lista estructurada con rol.
        $lista = null;
        foreach (['referees', 'refereeDetails'] as $k) {
            if (isset($game[$k]) && is_array($game[$k])) { $lista = $game[$k]; break; }
        }

        $pares = [];
        if (is_array($lista)) {
            foreach ($lista as $r) {
                if (!is_array($r)) continue;
                $pares[] = [$this->valor($r, ['id', 'refereeId', 'personId']), $this->rolArbitro($this->juntarTexto($r, ['role', 'type', 'position', 'typeName']))];
            }
        } elseif (isset($game['refereeIds']) && is_array($game['refereeIds'])) {
            // Forma B: sólo ids. El primero es el principal; el resto, líneas.
            $i = 0;
            foreach ($game['refereeIds'] as $id) {
                $i++;
                $rol = $i === 1 ? 'Principal' : ($i === 2 ? 'Línea 1' : ($i === 3 ? 'Línea 2' : 'Desconocido'));
                $pares[] = [is_array($id) ? $this->valor($id, ['id', 'refereeId']) : $id, $rol];
            }
        }

        // Los que todavía no conocemos: los buscamos por perfil, igual que a los
        // jugadores. Si la API no los devuelve, se saltean con un aviso.
        $faltan = [];
        foreach ($pares as $par) {
            if ($par[0] === null || $par[0] === '') continue;
            if (!isset($mapa[(string) $par[0]])) $faltan[] = (string) $par[0];
        }
        if (!empty($faltan)) {
            // OJO: nada de array_merge acá. Las claves son ids numéricos y
            // array_merge los renumera (0, 1, 2…), con lo cual el árbitro que
            // acabamos de resolver deja de encontrarse por su tm_id.
            foreach ($this->resolverArbitros(array_unique($faltan), $escribir, $informe) as $k => $v) {
                $mapa[$k] = $v;
            }
        }

        foreach ($pares as $par) {
            list($tmId, $rol) = $par;
            if ($tmId === null || $tmId === '') continue;
            if (!isset($mapa[(string) $tmId])) {
                $this->aviso('Árbitro TM ' . $tmId . ' (' . $rol . '): no lo tengo mapeado y no pude traer su perfil. '
                    . 'Cargalo a mano y atalo en arbitro_tm, o dejalo pasar: el resto del partido se guarda igual.');
                continue;
            }
            $arbitroId = (int) $mapa[(string) $tmId];
            $out[] = [
                'partido_id' => $partido->id,
                'arbitro_id' => $arbitroId,
                'tipo'       => $rol,
                '_nombre'    => $this->nombreArbitro($arbitroId),
            ];
        }
        return $out;
    }

    /**
     * Los DTs de los dos equipos.
     *
     * A diferencia de jugadores y árbitros, acá no hace falta tabla de mapeo:
     * `tecnicos.transfermarkt_url` ya guarda la URL `.../trainer/{id}` y es la
     * misma que usa el importador de partidos. De ahí sale el vínculo, y a los
     * DTs que creamos les grabamos esa URL, así quedan atados para siempre y
     * además aparecen solos en la lista de "Carga de partidos".
     *
     * El importador de partidos ya carga el DT del club que estás recorriendo;
     * lo que agrega esto es el DT rival, que hasta ahora quedaba vacío.
     */
    private function planTecnicos(array $game, Partido $partido, array $lados, $escribir = false, array &$informe = null)
    {
        $out = [];
        $mapa = $this->mapaTecnicos();

        // Qué id de DT le corresponde a cada lado.
        $porLado = [];
        foreach ($lados as $lado) {
            $id = $this->coachDelLado($game, $lado['clave']);
            if ($id !== null) $porLado[$lado['clave']] = (string) $id;
        }

        if (empty($porLado)) {
            if (isset($game['coaches'])) {
                $this->aviso('No reconocí a los DTs dentro de "coaches": ' . $this->resumenCrudo($game['coaches']));
            }
            return $out;
        }

        $faltan = [];
        foreach ($porLado as $id) { if (!isset($mapa[$id])) $faltan[] = $id; }
        if (!empty($faltan)) {
            foreach ($this->resolverTecnicos(array_unique($faltan), $escribir, $informe) as $k => $v) {
                $mapa[$k] = $v;
            }
        }

        foreach ($lados as $lado) {
            if (!isset($porLado[$lado['clave']])) continue;
            $tmId = $porLado[$lado['clave']];

            if (!isset($mapa[$tmId])) {
                $this->aviso('DT de ' . $lado['equipo_nombre'] . ' (TM ' . $tmId . '): no lo tengo y no pude traer su perfil.');
                continue;
            }

            $yaEsta = DB::table('partido_tecnicos')
                ->where('partido_id', $partido->id)->where('equipo_id', $lado['equipo_id'])->exists();

            $out[] = [
                'partido_id' => $partido->id,
                'equipo_id'  => $lado['equipo_id'],
                'tecnico_id' => (int) $mapa[$tmId],
                '_nombre'    => $this->nombreTecnico($mapa[$tmId]),
                '_equipo'    => $lado['equipo_nombre'],
                '_estado'    => $yaEsta ? 'ya estaba cargado' : 'se agrega',
            ];
        }
        return $out;
    }

    /** Busca el id del DT de un lado, probando dónde puede venir en el JSON. */
    private function coachDelLado(array $game, $clave)
    {
        $club = isset($game[$clave]) && is_array($game[$clave]) ? $game[$clave] : [];

        // a) colgando del club
        $id = $this->valor($club, ['coachId', 'trainerId', 'managerId', 'coach', 'trainer']);
        if ($id !== null) return $id;
        if (isset($club['coaches']) && is_array($club['coaches']) && isset($club['coaches'][0])) {
            $id = $this->valor($club['coaches'][0], ['id', 'coachId', 'trainerId']);
            if ($id !== null) return $id;
        }

        if (!isset($game['coaches']) || !is_array($game['coaches'])) return null;
        $coaches = $game['coaches'];

        // b) mapa por lado: {"homeClub": …, "awayClub": …} o {"home": …}
        $corto = $clave === 'homeClub' ? 'home' : 'away';
        foreach ([$clave, $corto] as $k) {
            if (!isset($coaches[$k])) continue;
            if (is_array($coaches[$k])) {
                $id = $this->valor($coaches[$k], ['id', 'coachId', 'trainerId']);
                if ($id !== null) return $id;
            } elseif ($coaches[$k] !== '' && $coaches[$k] !== null) {
                return $coaches[$k];
            }
        }

        // c) lista, emparejando por el clubId
        $clubId = $this->valor($club, ['id', 'clubId']);
        foreach ($coaches as $c) {
            if (!is_array($c)) continue;
            $cid = $this->valor($c, ['clubId', 'teamId']);
            if ($clubId !== null && $cid !== null && (string) $cid === (string) $clubId) {
                $id = $this->valor($c, ['id', 'coachId', 'trainerId', 'personId']);
                if ($id !== null) return $id;
            }
        }

        return null;
    }

    /** tm_trainer_id -> tecnico_id, leído de tecnicos.transfermarkt_url. */
    private function mapaTecnicos()
    {
        if ($this->mapaTecnicos !== null) return $this->mapaTecnicos;
        $mapa = [];
        $filas = DB::table('tecnicos')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->select('id', 'transfermarkt_url')->get();
        foreach ($filas as $f) {
            if (preg_match('#/trainer/(\d+)#', (string) $f->transfermarkt_url, $m)) {
                $mapa[$m[1]] = (int) $f->id;
            }
        }
        return $this->mapaTecnicos = $mapa;
    }

    /** Trae los perfiles de los DTs que no conocemos y los ata (o los crea). */
    private function resolverTecnicos(array $ids, $escribir, array &$informe = null)
    {
        $out = [];
        $perfiles = [];

        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));
        foreach (['/coaches?' . $qs, '/trainers?' . $qs, '/managers?' . $qs] as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            if ($informe !== null) $informe['llamadas']++;
            if (!is_array($json) || empty($json)) continue;
            $data = isset($json['data']) ? $json['data'] : $json;
            foreach (['coaches', 'trainers', 'managers'] as $rama) {
                if (isset($data[$rama]) && is_array($data[$rama])) { $data = $data[$rama]; break; }
            }
            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = $this->valor($perfil, ['id', 'coachId', 'trainerId']);
                if ($id === null && !is_int($clave)) $id = $clave;
                if ($id !== null) $perfiles[(string) $id] = $perfil;
            }
            if (!empty($perfiles)) break;
        }

        if (empty($perfiles)) {
            $this->aviso('No pude traer el perfil de ' . count($ids) . ' DT(s) desde la API.');
            return $out;
        }

        foreach ($ids as $tmId) {
            if (!isset($perfiles[(string) $tmId])) continue;
            $datos = $this->personaDesdePerfil($perfiles[(string) $tmId]);
            if ($datos['apellido'] === '') continue;

            $existente = $this->buscarPersonaRol('tecnicos', $datos);
            if ($existente) {
                $out[(string) $tmId] = (int) $existente['id'];
                // Le grabamos la URL para que el vínculo quede guardado.
                if ($escribir) $this->grabarUrlTecnico($existente['id'], $tmId);
                if ($existente['revisar']) {
                    $this->aviso('Aparejé al DT ' . $datos['apellido'] . ', ' . $datos['nombre']
                        . ' con "' . $existente['base'] . '" (#' . $existente['id'] . '). Confirmalo.');
                }
                continue;
            }

            if (!$escribir) {
                $ficticio = $this->proximoPreview--;
                $this->nombresPreview[$ficticio] = $datos['name'] . ' · DT nuevo';
                $out[(string) $tmId] = $ficticio;
                if ($informe !== null) $informe['creados']['tecnicos'][] = $datos['apellido'] . ', ' . $datos['nombre']
                    . ' (TM ' . $tmId . ') — SE CREARÍA';
                continue;
            }

            try {
                $foto = $this->descargarFoto($datos['portrait'], $datos['name']);
                if ($foto) $datos['persona']['foto'] = $foto;

                $persona = Persona::create($datos['persona']);
                $tecnico = $persona->tecnico()->create([
                    'transfermarkt_url' => 'https://www.transfermarkt.es/-/profil/trainer/' . $tmId,
                ]);
                $out[(string) $tmId] = (int) $tecnico->id;
                if ($this->mapaTecnicos !== null) $this->mapaTecnicos[(string) $tmId] = (int) $tecnico->id;
                if ($informe !== null) $informe['creados']['tecnicos'][] = $datos['apellido'] . ', ' . $datos['nombre']
                    . ' (TM ' . $tmId . ') — creado #' . $tecnico->id;
            } catch (\Exception $e) {
                $this->aviso('No pude crear al DT TM ' . $tmId . ': ' . $e->getMessage());
            }
        }

        return $out;
    }

    private function grabarUrlTecnico($tecnicoId, $tmId)
    {
        $actual = DB::table('tecnicos')->where('id', $tecnicoId)->value('transfermarkt_url');
        if ($actual) return;   // ya tiene una: no la pisamos
        DB::table('tecnicos')->where('id', $tecnicoId)->update([
            'transfermarkt_url' => 'https://www.transfermarkt.es/-/profil/trainer/' . $tmId,
            'updated_at' => now(),
        ]);
        if ($this->mapaTecnicos !== null) $this->mapaTecnicos[(string) $tmId] = (int) $tecnicoId;
    }

    private function nombreTecnico($id)
    {
        if ($id < 0) return isset($this->nombresPreview[$id]) ? $this->nombresPreview[$id] : 'DT nuevo';
        $t = DB::table('tecnicos')->join('personas', 'personas.id', '=', 'tecnicos.persona_id')
            ->where('tecnicos.id', $id)->select('personas.name')->first();
        return $t ? $t->name : ('DT #' . $id);
    }

    /**
     * Trae los perfiles de los árbitros que no conocemos y los ata (o los crea).
     * Se prueban los dos endpoints posibles; si ninguno responde, devolvemos
     * vacío y el partido se guarda igual, sin árbitro.
     */
    /**
     * Diagnóstico: qué devuelve tmapi para una persona y qué saca de ahí el
     * parser. Existe porque `personaDesdePerfil()` se escribió mirando el JSON
     * de JUGADORES, y árbitros y DTs pueden traer otra forma: sin ver el crudo
     * no se arregla sin adivinar (que es lo que salió mal la primera vez).
     *
     * Conviene mirar un jugador además del árbitro/DT: ese es el JSON que el
     * parser SÍ entiende, y sirve de referencia para ver qué clave cambió.
     *
     * @param  string $tmId
     * @param  string $tipo  'arbitro' | 'tecnico' | 'jugador'
     * @return array
     */
    public static function diagnosticarPersonaTm($tmId, $tipo = 'arbitro')
    {
        $svc = new self();
        $svc->avisos = [];

        $qs = 'ids[]=' . urlencode($tmId);
        $config = [
            'arbitro' => [
                'rutas'  => ['/referees?' . $qs, '/officials?' . $qs, '/referee/' . urlencode($tmId)],
                'ramas'  => ['referees', 'officials'],
                'claves' => ['id', 'refereeId'],
            ],
            'tecnico' => [
                'rutas'  => ['/coaches?' . $qs, '/trainers?' . $qs, '/managers?' . $qs, '/coach/' . urlencode($tmId)],
                'ramas'  => ['coaches', 'trainers', 'managers'],
                'claves' => ['id', 'coachId', 'trainerId'],
            ],
            'jugador' => [
                'rutas'  => ['/players?' . $qs, '/player/' . urlencode($tmId)],
                'ramas'  => ['players'],
                'claves' => ['id', 'playerId'],
            ],
        ];
        if (!isset($config[$tipo])) $tipo = 'arbitro';
        $cfg = $config[$tipo];

        $out = ['tipo' => $tipo, 'rutas' => [], 'datos' => null, 'perfil' => null,
            'llamadas' => 0, 'avisos' => []];

        foreach ($cfg['rutas'] as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $out['llamadas']++;
            $out['rutas'][$ruta] = $json;

            if ($out['perfil'] !== null || !is_array($json) || empty($json)) continue;

            $data = isset($json['data']) ? $json['data'] : $json;
            foreach ($cfg['ramas'] as $rama) {
                if (isset($data[$rama]) && is_array($data[$rama])) { $data = $data[$rama]; break; }
            }
            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = $svc->valor($perfil, $cfg['claves']);
                if ($id === null && !is_int($clave)) $id = $clave;
                if ((string) $id === (string) $tmId) { $out['perfil'] = $perfil; break; }
            }
            // Ruta de un solo registro: el perfil puede venir suelto.
            if ($out['perfil'] === null && isset($data['name'])) $out['perfil'] = $data;
        }

        if (is_array($out['perfil'])) {
            $out['datos'] = $svc->personaDesdePerfil($out['perfil']);
        }
        $out['avisos'] = $svc->avisos;
        return $out;
    }

    private function resolverArbitros(array $ids, $escribir, array &$informe = null)
    {
        $out = [];
        $perfiles = [];

        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));
        foreach (['/referees?' . $qs, '/officials?' . $qs] as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            if ($informe !== null) $informe['llamadas']++;
            if (!is_array($json) || empty($json)) continue;
            $data = isset($json['data']) ? $json['data'] : $json;
            if (isset($data['referees']) && is_array($data['referees'])) $data = $data['referees'];
            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = $this->valor($perfil, ['id', 'refereeId']);
                if ($id === null && !is_int($clave)) $id = $clave;
                if ($id !== null) $perfiles[(string) $id] = $perfil;
            }
            if (!empty($perfiles)) break;
        }

        if (empty($perfiles)) return $out;

        foreach ($ids as $tmId) {
            if (!isset($perfiles[(string) $tmId])) continue;
            $datos = $this->personaDesdePerfil($perfiles[(string) $tmId]);
            if ($datos['apellido'] === '') continue;

            // ¿Ya lo tenemos? Los árbitros casi nunca tienen fecha de nacimiento
            // cargada, así que comparamos las palabras del nombre completo
            // (mismo criterio que con los jugadores, ver tokensNombre).
            $existente = $this->buscarPersonaRol('arbitros', $datos);
            if ($existente) {
                $out[(string) $tmId] = (int) $existente['id'];
                if ($escribir) $this->guardarMapeoArbitro($tmId, $existente['id'], $datos['name'], 'auto', $existente['revisar']);
                if ($existente['revisar']) {
                    $this->aviso('Aparejé al árbitro ' . $datos['apellido'] . ', ' . $datos['nombre']
                        . ' con "' . $existente['base'] . '" (#' . $existente['id'] . '). Confirmalo.');
                }
                continue;
            }

            if (!$escribir) {
                $ficticio = $this->proximoPreview--;
                $this->nombresPreview[$ficticio] = $datos['name'] . ' · árbitro nuevo';
                $out[(string) $tmId] = $ficticio;
                $this->aviso('Se crearía el árbitro ' . $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ').');
                continue;
            }

            try {
                $foto = $this->descargarFoto($datos['portrait'], $datos['name']);
                if ($foto) $datos['persona']['foto'] = $foto;

                $persona = Persona::create($datos['persona']);
                $arbitro = $persona->arbitro()->create([]);
                $this->guardarMapeoArbitro($tmId, $arbitro->id, $datos['name'], 'auto', true);
                $out[(string) $tmId] = (int) $arbitro->id;
                if ($informe !== null) $informe['creados']['arbitros'][] = $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ')';
            } catch (\Exception $e) {
                $this->aviso('No pude crear al árbitro TM ' . $tmId . ': ' . $e->getMessage());
            }
        }

        return $out;
    }

    /**
     * Igual que buscarJugadorExistente, pero para árbitros y DTs, que casi nunca
     * tienen la fecha de nacimiento cargada. Sirve para las dos tablas porque el
     * criterio es el mismo: comparar las palabras del nombre completo.
     *
     * @param string $tabla 'arbitros' o 'tecnicos'
     */
    private function buscarPersonaRol($tabla, array $datos)
    {
        $tokensTm = $this->tokensNombre($datos['apellido'] . ' ' . $datos['nombre']);
        if (count($tokensTm) < 2) return null;

        $cands = $this->candidatosPorNombre($tabla, $datos);

        $mejor = null; $puntaje = 0; $empatados = 0;
        foreach ($cands as $c) {
            if (!empty($datos['nacimiento']) && !empty($c->nacimiento)
                && substr((string) $c->nacimiento, 0, 10) !== $datos['nacimiento']) {
                continue;   // fechas distintas: no es él
            }
            $p = count(array_intersect($tokensTm, $this->tokensNombre($c->apellido . ' ' . $c->nombre)));
            if ($p > $puntaje) { $puntaje = $p; $mejor = $c; $empatados = 1; }
            elseif ($p === $puntaje && $p > 0) { $empatados++; }
        }

        if (!$mejor || $puntaje < 2) return null;

        return ['id' => (int) $mejor->id, 'base' => trim($mejor->apellido . ', ' . $mejor->nombre),
            'revisar' => ($empatados > 1 || $puntaje < count($tokensTm))];
    }

    private function guardarMapeoArbitro($tmId, $arbitroId, $nombre, $origen, $revisar)
    {
        DB::table('arbitro_tm')->updateOrInsert(
            ['tm_referee_id' => (string) $tmId],
            ['arbitro_id' => (int) $arbitroId, 'nombre_tm' => $nombre, 'origen' => $origen,
                'revisar' => $revisar ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]
        );
        if ($this->mapaArbitros !== null) $this->mapaArbitros[(string) $tmId] = (int) $arbitroId;
    }

    private function rolArbitro($txt)
    {
        $t = mb_strtolower(trim((string) $txt));
        if ($t === '') return 'Principal';
        if (mb_strpos($t, 'assistant') !== false || mb_strpos($t, 'linesman') !== false || mb_strpos($t, 'línea') !== false || mb_strpos($t, 'linea') !== false) {
            return mb_strpos($t, '2') !== false ? 'Línea 2' : 'Línea 1';
        }
        if (mb_strpos($t, 'fourth') !== false || mb_strpos($t, 'cuarto') !== false) return 'Desconocido';
        if (mb_strpos($t, 'var') !== false) return 'Desconocido';
        return 'Principal';
    }

    // ═══════════════════════ JUGADORES: RESOLVER Y CREAR ═══════════════════

    /** Baja los perfiles de a 50 con /players?ids[]= */
    private function traerPerfiles(array $ids, array &$informe)
    {
        $out = [];
        foreach (array_chunk(array_values($ids), 50) as $tanda) {
            $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $tanda));
            $json = HttpHelper::getJson(self::TMAPI . '/players?' . $qs);
            $informe['llamadas']++;
            if (!is_array($json) || empty($json)) {
                $this->aviso('No pude bajar los perfiles de ' . count($tanda) . ' jugador(es).');
                continue;
            }
            $data = isset($json['data']) ? $json['data'] : $json;

            // La API devuelve o bien una lista, o bien un mapa id => perfil.
            if (isset($data['players']) && is_array($data['players'])) $data = $data['players'];
            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = $this->valor($perfil, ['id', 'playerId']);
                if ($id === null && !is_int($clave)) $id = $clave;
                if ($id === null) continue;
                $out[(string) $id] = $perfil;
            }
        }
        return $out;
    }

    /**
     * Devuelve el jugador_id para un tm_player_id. Si el jugador no existe en
     * la base, lo crea (persona + jugadors) con los datos del perfil y lo deja
     * marcado para revisar.
     */
    private function resolverJugador($tmId, array $perfil, $escribir)
    {
        $datos = $this->personaDesdePerfil($perfil);
        $etiqueta = trim($datos['apellido'] . ', ' . $datos['nombre']) . ' (TM ' . $tmId . ')';

        // ¿Ya lo tenemos, aunque sin mapear?
        $existente = $this->buscarJugadorExistente($datos);
        if ($existente) {
            if ($escribir) {
                $this->guardarMapeoJugador($tmId, $existente['jugador_id'], $datos['name'], 'auto', $existente['revisar']);
            }
            if ($existente['revisar']) {
                $this->aviso('Aparejé a ' . $etiqueta . ' con el jugador #' . $existente['jugador_id']
                    . ' (' . $existente['base'] . ') por ' . $existente['como'] . '. Confirmalo en "jugadores por revisar".');
            }
            return ['jugador_id' => (int) $existente['jugador_id'], 'creado' => false,
                'descripcion' => $etiqueta . ' — ya existía como "' . $existente['base'] . '" (' . $existente['como'] . ')'];
        }

        if (!$escribir) {
            // Vista previa: no creamos nada, pero le damos un id negativo para
            // que igual aparezca en la alineación y en las incidencias.
            $ficticio = $this->proximoPreview--;
            $this->nombresPreview[$ficticio] = ($datos['name'] !== '' ? $datos['name'] : $datos['apellido']) . ' · nuevo';
            $this->tiposPreview[$ficticio] = isset($datos['jugador']['tipoJugador']) ? $datos['jugador']['tipoJugador'] : '';
            return ['jugador_id' => $ficticio, 'creado' => true, 'descripcion' => $etiqueta . ' — SE CREARÍA'
                . ($datos['nacimiento'] ? ' · ' . $datos['nacimiento'] : ' · sin fecha de nacimiento')
                . ($datos['nacionalidad'] ? ' · ' . $datos['nacionalidad'] : ' · sin nacionalidad')
                . ($datos['portrait'] ? ' · con foto' : ' · sin foto')];
        }

        if ($datos['name'] === '' || $datos['apellido'] === '') {
            $this->aviso('Perfil TM ' . $tmId . ' sin nombre utilizable: no lo creo.');
            return ['jugador_id' => null, 'creado' => false, 'descripcion' => $etiqueta];
        }

        try {
            $foto = $this->descargarFoto($datos['portrait'], $etiqueta);
            if ($foto) $datos['persona']['foto'] = $foto;

            $persona = Persona::create($datos['persona']);
            $jugador = $persona->jugador()->create($datos['jugador']);
            $this->guardarMapeoJugador($tmId, $jugador->id, $datos['name'], 'auto', true);
            return ['jugador_id' => (int) $jugador->id, 'creado' => true, 'descripcion' => $etiqueta . ' — creado #' . $jugador->id];
        } catch (\Exception $e) {
            $this->aviso('No pude crear al jugador ' . $etiqueta . ': ' . $e->getMessage());
            return ['jugador_id' => null, 'creado' => false, 'descripcion' => $etiqueta];
        }
    }

    /**
     * Busca en la base a un jugador que ya tengamos cargado, sin depender de
     * que el nombre esté partido igual que en Transfermarkt.
     *
     * El problema real: los apellidos compuestos se reparten distinto en cada
     * fuente. Marko Biskupović figura en la base como apellido
     * "Biskupovic Venturino" / nombre "Marko Andrés", y en Transfermarkt como
     * apellido "Venturino" / nombre "Marko Andrés Biskupović". Comparando el
     * apellido tal cual, no matchean, y el jugador se duplica.
     *
     * Entonces no comparamos campo contra campo: juntamos nombre y apellido,
     * los partimos en palabras, les sacamos acentos y diacríticos (Biskupović
     * -> biskupovic) y contamos cuántas palabras comparten.
     *
     *   fecha de nacimiento igual + 2 o más palabras en común -> es él, seguro
     *   fecha de nacimiento igual + 1 palabra                 -> probablemente,
     *                                                            queda para revisar
     *   sin fecha en la base + nombre completo idéntico       -> probablemente,
     *                                                            queda para revisar
     *
     * Devuelve null si no hay nada parecido: ahí sí se crea.
     */
    private function buscarJugadorExistente(array $datos)
    {
        $tokensTm = $this->tokensNombre($datos['apellido'] . ' ' . $datos['nombre']);
        if (count($tokensTm) < 2) return null;

        // ── 1) Mismo día de nacimiento ────────────────────────────────────
        if (!empty($datos['nacimiento'])) {
            $cands = DB::table('jugadors')
                ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
                ->where('personas.nacimiento', $datos['nacimiento'])
                ->select('jugadors.id', 'personas.apellido', 'personas.nombre')
                ->limit(50)->get();

            $mejor = null; $puntaje = 0; $empatados = 0;
            foreach ($cands as $c) {
                $p = count(array_intersect($tokensTm, $this->tokensNombre($c->apellido . ' ' . $c->nombre)));
                if ($p > $puntaje) { $puntaje = $p; $mejor = $c; $empatados = 1; }
                elseif ($p === $puntaje && $p > 0) { $empatados++; }
            }

            // Hacen falta DOS palabras en común. Con una sola no alcanza: dos
            // personas distintas pueden haber nacido el mismo día y llamarse
            // las dos "José". Un apareo equivocado le pega los partidos de uno
            // a la ficha del otro — es mucho peor que un duplicado, que se ve
            // en "jugadores por revisar" y se arregla.
            if ($mejor && $puntaje >= 2) {
                $base = trim($mejor->apellido . ', ' . $mejor->nombre);
                if ($empatados > 1) {
                    $this->aviso('Hay ' . $empatados . ' jugadores nacidos el ' . $datos['nacimiento']
                        . ' que se parecen a ' . $datos['apellido'] . ', ' . $datos['nombre']
                        . '. Uso el primero (#' . $mejor->id . ') pero revisalo.');
                }
                return ['jugador_id' => (int) $mejor->id, 'base' => $base,
                    'revisar' => ($empatados > 1),
                    'como' => 'misma fecha de nacimiento y ' . $puntaje . ' palabras del nombre en común'];
            }

            if ($mejor && $puntaje === 1) {
                $this->aviso('Ojo: ' . trim($mejor->apellido . ', ' . $mejor->nombre) . ' (#' . $mejor->id
                    . ') nació el mismo día que ' . $datos['apellido'] . ', ' . $datos['nombre']
                    . ' pero sólo comparten una palabra del nombre. NO los uní: si son la misma persona, '
                    . 'unificalos a mano.');
            }
        }

        // ── 2) En la base no tiene fecha cargada ──────────────────────────
        // Sólo aceptamos si el nombre completo es exactamente el mismo conjunto
        // de palabras. Y queda marcado para revisar igual.
        foreach ($this->candidatosPorNombre('jugadors', $datos) as $c) {
            if (!empty($c->nacimiento)) continue;   // si tiene fecha y no matcheó arriba, no es él
            $tokensBase = $this->tokensNombre($c->apellido . ' ' . $c->nombre);
            sort($tokensBase);
            $tm = $tokensTm; sort($tm);
            if ($tokensBase === $tm) {
                return ['jugador_id' => (int) $c->id, 'base' => trim($c->apellido . ', ' . $c->nombre),
                    'revisar' => true, 'como' => 'mismo nombre completo, sin fecha de nacimiento en la base'];
            }
        }

        return null;
    }

    /**
     * Trae de la base los candidatos que comparten alguna palabra del nombre.
     *
     * Ojo con dos cosas que ya me mordieron:
     *
     *  1. No alcanza con buscar UNA palabra (la más larga). Héctor Santiago
     *     Tapia Urdile: la más larga es "santiago", que es un nombre de pila y
     *     puede no estar donde uno lo busca. Buscamos con varias, y primero con
     *     las del APELLIDO, que es lo que de verdad identifica.
     *  2. Hay que mirar las TRES columnas —apellido, nombre y name—, porque cada
     *     fuente parte el nombre en un lugar distinto: lo que en Transfermarkt
     *     es apellido, en la base puede estar en el nombre.
     *
     * El filtro es sólo para no traer la tabla entera; quién es quién lo decide
     * después la comparación de palabras.
     */
    private function candidatosPorNombre($tabla, array $datos)
    {
        $anclas = [];
        foreach (array_merge($this->tokensNombre($datos['apellido']),
                             $this->tokensNombre($datos['nombre'])) as $t) {
            if (mb_strlen($t) >= 4 && !in_array($t, $anclas, true)) $anclas[] = $t;
        }
        $anclas = array_slice($anclas, 0, 4);
        if (empty($anclas)) return collect();

        return DB::table($tabla)
            ->join('personas', 'personas.id', '=', $tabla . '.persona_id')
            ->where(function ($q) use ($anclas) {
                foreach ($anclas as $a) {
                    $q->orWhere('personas.apellido', 'like', '%' . $a . '%')
                      ->orWhere('personas.nombre', 'like', '%' . $a . '%')
                      ->orWhere('personas.name', 'like', '%' . $a . '%');
                }
            })
            ->select($tabla . '.id', 'personas.apellido', 'personas.nombre', 'personas.nacimiento')
            ->limit(300)->get();
    }

    /**
     * Palabras de un nombre, normalizadas: minúsculas, sin acentos ni
     * diacríticos (Biskupović -> biskupovic, Müller -> muller), sin partículas
     * (de, da, van…) ni palabras de una sola letra.
     */
    private function tokensNombre($texto)
    {
        $s = mb_strtolower(trim((string) $texto), 'UTF-8');
        $s = strtr($s, [
            'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
            'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','ē'=>'e','ę'=>'e','ě'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','ī'=>'i','į'=>'i',
            'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o','ø'=>'o','ō'=>'o','ő'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ū'=>'u','ů'=>'u','ű'=>'u',
            'ñ'=>'n','ń'=>'n','ň'=>'n',
            'ç'=>'c','ć'=>'c','č'=>'c',
            'š'=>'s','ś'=>'s','ş'=>'s',
            'ž'=>'z','ź'=>'z','ż'=>'z',
            'đ'=>'d','ď'=>'d','ð'=>'d',
            'ý'=>'y','ÿ'=>'y','ř'=>'r','ł'=>'l','ť'=>'t','ğ'=>'g','ß'=>'ss','þ'=>'t',
        ]);
        $s = preg_replace('/[^a-z0-9 ]+/u', ' ', $s);

        $particulas = ['de','da','do','dos','das','del','della','di','la','las','los','el',
            'van','von','der','den','du','le','bin','al','y','e','junior','jr'];

        $out = [];
        foreach (preg_split('/\s+/', trim($s)) as $t) {
            if ($t === '' || mb_strlen($t) < 2) continue;
            if (in_array($t, $particulas, true)) continue;
            $out[$t] = true;
        }
        return array_keys($out);
    }

    /**
     * Graba las filas de a una, aguantando que alguna falle.
     *
     * La base tiene índices únicos propios —por ejemplo (partido_id, equipo_id,
     * dorsal) en `alineacions`— y Transfermarkt manda lo que manda. Si una fila
     * choca contra uno, no tiene sentido perder el partido entero: se saltea
     * esa, queda el aviso, y el resto se guarda.
     *
     * En InnoDB una sentencia fallida no aborta la transacción, sólo se deshace
     * ella, así que se puede seguir.
     */
    private function grabarFilas($clase, array $filas, $que)
    {
        foreach ($filas as $r) {
            try {
                $clase::create($this->limpiar($r));
            } catch (\Exception $e) {
                $this->fallidas++;
                $quien = isset($r['_nombre']) ? ' — ' . $r['_nombre'] : '';
                $this->aviso('No se pudo guardar ' . $que . $quien . ': ' . $this->mensajeCorto($e));
            }
        }
    }

    /**
     * Los dorsales cargados en la plantilla, para los dos equipos del partido.
     *
     * Se miran TODOS los grupos del torneo, igual que hace `AlineacionController`
     * al armar sus desplegables: la plantilla puede estar cargada en otro grupo
     * del mismo torneo (zona, fase final) y sigue valiendo.
     *
     * Devuelve "equipoId-jugadorId" => dorsal.
     */
    private function dorsalesDePlantilla(Partido $partido, array $lados)
    {
        $mapa = [];
        $torneoId = optional(optional($partido->fecha)->grupo)->torneo_id;
        if (!$torneoId) return $mapa;

        $equipos = [];
        foreach ($lados as $l) $equipos[] = $l['equipo_id'];

        $filas = DB::table('plantilla_jugadors')
            ->join('plantillas', 'plantillas.id', '=', 'plantilla_jugadors.plantilla_id')
            ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
            ->where('grupos.torneo_id', $torneoId)
            ->whereIn('plantillas.equipo_id', $equipos)
            ->whereNotNull('plantilla_jugadors.dorsal')
            ->select('plantillas.equipo_id', 'plantilla_jugadors.jugador_id', 'plantilla_jugadors.dorsal')
            ->get();

        foreach ($filas as $f) {
            $mapa[$f->equipo_id . '-' . $f->jugador_id] = $f->dorsal;
        }
        return $mapa;
    }

    /**
     * Rearma las plantillas de partidos que YA tienen la alineación cargada,
     * sin gastar una sola llamada a Transfermarkt.
     *
     * Los partidos que se importaron antes de que el detalle empezara a escribir
     * `plantilla_jugadors` quedaron con la alineación completa pero fuera de la
     * plantilla del torneo: se ven bien en la ficha, pero `/admin/alineaciones`
     * no los ofrece en los desplegables. Como la alineación ya está guardada,
     * la plantilla se reconstruye desde la base misma.
     *
     * @param  array $partidoIds
     * @param  bool  $escribir   false = sólo informa qué faltaría
     * @return array
     */
    public static function plantillasDesdeAlineaciones(array $partidoIds, $escribir = false)
    {
        $out = ['alineaciones' => 0, 'partidos' => 0, 'faltantes' => [], 'agregados' => 0,
            'plantillas_nuevas' => 0, 'fallidas' => 0, 'avisos' => []];

        $partidoIds = array_values(array_unique(array_filter(array_map('intval', $partidoIds))));
        if (empty($partidoIds)) return $out;

        // Alineaciones ya cargadas, con el torneo y el grupo de cada partido.
        $filas = [];
        foreach (array_chunk($partidoIds, 500) as $trozo) {
            $rows = DB::table('alineacions')
                ->join('partidos', 'partidos.id', '=', 'alineacions.partido_id')
                ->join('fechas', 'fechas.id', '=', 'partidos.fecha_id')
                ->join('grupos', 'grupos.id', '=', 'fechas.grupo_id')
                ->whereIn('alineacions.partido_id', $trozo)
                ->whereNotNull('alineacions.jugador_id')
                ->whereNotNull('alineacions.equipo_id')
                ->select('alineacions.partido_id', 'alineacions.jugador_id', 'alineacions.equipo_id',
                    'alineacions.dorsal', 'grupos.id AS grupo_id', 'grupos.torneo_id')
                ->get();
            foreach ($rows as $r) $filas[] = $r;
        }
        $out['alineaciones'] = count($filas);
        if (empty($filas)) return $out;

        $porPartido = [];
        $torneos = []; $equipos = [];
        foreach ($filas as $r) {
            $porPartido[(int) $r->partido_id] = true;
            $torneos[(int) $r->torneo_id] = true;
            $equipos[(int) $r->equipo_id] = true;
        }
        $out['partidos'] = count($porPartido);

        // Una plantilla por equipo y por torneo, esté en el grupo que esté
        // (mismo criterio que grabarPlantillas y que AlineacionController).
        $plantillaDe = [];
        foreach (DB::table('plantillas')
                     ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
                     ->whereIn('grupos.torneo_id', array_keys($torneos))
                     ->whereIn('plantillas.equipo_id', array_keys($equipos))
                     ->orderBy('plantillas.id')
                     ->select('grupos.torneo_id', 'plantillas.equipo_id', 'plantillas.id')
                     ->get() as $p) {
            $k = (int) $p->torneo_id . '-' . (int) $p->equipo_id;
            if (!isset($plantillaDe[$k])) $plantillaDe[$k] = (int) $p->id;
        }

        $yaEsta = [];
        if (!empty($plantillaDe)) {
            foreach (array_chunk(array_values($plantillaDe), 500) as $trozo) {
                foreach (DB::table('plantilla_jugadors')->whereIn('plantilla_id', $trozo)
                             ->select('plantilla_id', 'jugador_id')->get() as $pj) {
                    $yaEsta[(int) $pj->plantilla_id . '-' . (int) $pj->jugador_id] = true;
                }
            }
        }

        $vistos = []; $pendientes = []; $nuevas = [];
        foreach ($filas as $r) {
            $clave = (int) $r->torneo_id . '-' . (int) $r->equipo_id;
            $k = $clave . '-' . (int) $r->jugador_id;
            if (isset($vistos[$k])) continue;
            $vistos[$k] = true;

            $pl = isset($plantillaDe[$clave]) ? $plantillaDe[$clave] : null;
            if ($pl && isset($yaEsta[$pl . '-' . (int) $r->jugador_id])) continue;
            if (!$pl) $nuevas[$clave] = true;

            $pendientes[] = [
                'torneo_id'  => (int) $r->torneo_id,
                'grupo_id'   => (int) $r->grupo_id,
                'equipo_id'  => (int) $r->equipo_id,
                'jugador_id' => (int) $r->jugador_id,
                'dorsal'     => $r->dorsal,
                'partido_id' => (int) $r->partido_id,
                '_plantilla' => $pl,
            ];
        }

        if (empty($pendientes)) return $out;

        // Nombres, sólo para los que hay que mostrar.
        $ids = []; $eqs = [];
        foreach ($pendientes as $p) { $ids[$p['jugador_id']] = true; $eqs[$p['equipo_id']] = true; }
        $nombres = [];
        foreach (DB::table('jugadors')->join('personas', 'personas.id', '=', 'jugadors.persona_id')
                     ->whereIn('jugadors.id', array_keys($ids))
                     ->select('jugadors.id', 'personas.name')->get() as $j) {
            $nombres[(int) $j->id] = $j->name;
        }
        $nomEquipo = [];
        foreach (DB::table('equipos')->whereIn('id', array_keys($eqs))->select('id', 'nombre')->get() as $e) {
            $nomEquipo[(int) $e->id] = $e->nombre;
        }
        $nomTorneo = [];
        foreach (DB::table('torneos')->whereIn('id', array_keys($torneos))->select('id', 'nombre', 'year')->get() as $t) {
            $nomTorneo[(int) $t->id] = trim($t->nombre . ' ' . $t->year);
        }
        foreach ($pendientes as $i => $p) {
            $pendientes[$i]['_nombre'] = isset($nombres[$p['jugador_id']]) ? $nombres[$p['jugador_id']] : ('#' . $p['jugador_id']);
            $pendientes[$i]['_equipo'] = isset($nomEquipo[$p['equipo_id']]) ? $nomEquipo[$p['equipo_id']] : ('#' . $p['equipo_id']);
            $pendientes[$i]['_torneo'] = isset($nomTorneo[$p['torneo_id']]) ? $nomTorneo[$p['torneo_id']] : ('#' . $p['torneo_id']);
        }

        $out['faltantes'] = $pendientes;
        $out['plantillas_nuevas'] = count($nuevas);

        if ($escribir) {
            $svc = new self();
            $svc->avisos   = [];
            $svc->fallidas = 0;
            $svc->grabarPlantillas($pendientes);
            $out['avisos']    = $svc->avisos;
            $out['fallidas']  = $svc->fallidas;
            $out['agregados'] = count($pendientes) - $svc->fallidas;
        }

        return $out;
    }

    /**
     * Suma los jugadores a la plantilla del equipo en ese grupo.
     *
     * Sin esto el detalle "se ve" en la ficha del partido pero la pantalla de
     * `/admin/alineaciones` queda inservible: sus <select> se arman con
     * `plantilla_jugadors`, así que un jugador que no está en la plantilla sale
     * como opción vacía y, al guardar, manda jugador_id nulo.
     *
     * Nunca pisa un dorsal ya cargado: si el jugador ya estaba en la plantilla
     * se lo deja como está, y sólo se le completa el dorsal si estaba vacío.
     */
    private function grabarPlantillas(array $filas)
    {
        if (empty($filas)) return;

        $plantillas = [];   // "torneo-equipo" => plantilla_id
        foreach ($filas as $f) {
            $clave = $f['torneo_id'] . '-' . $f['equipo_id'];
            try {
                if (!isset($plantillas[$clave])) {
                    // Un equipo tiene UNA plantilla por torneo, esté en el grupo
                    // que esté. Si ya existe en cualquier grupo del torneo, se
                    // usa esa; recién si no hay ninguna se crea, en el grupo de
                    // este partido.
                    $existente = null;
                    if ($f['torneo_id']) {
                        $existente = DB::table('plantillas')
                            ->join('grupos', 'grupos.id', '=', 'plantillas.grupo_id')
                            ->where('grupos.torneo_id', $f['torneo_id'])
                            ->where('plantillas.equipo_id', $f['equipo_id'])
                            ->orderBy('plantillas.id')
                            ->value('plantillas.id');
                    }
                    if (!$existente) {
                        $p = \App\Plantilla::firstOrCreate(
                            ['grupo_id' => $f['grupo_id'], 'equipo_id' => $f['equipo_id']]
                        );
                        $existente = $p->id;
                    }
                    $plantillas[$clave] = $existente;
                }

                $pj = \App\PlantillaJugador::where('plantilla_id', $plantillas[$clave])
                    ->where('jugador_id', $f['jugador_id'])->first();

                if (!$pj) {
                    \App\PlantillaJugador::create([
                        'plantilla_id' => $plantillas[$clave],
                        'jugador_id'   => $f['jugador_id'],
                        'dorsal'       => $f['dorsal'],
                    ]);
                } elseif (($pj->dorsal === null || $pj->dorsal === '') && $f['dorsal'] !== null) {
                    $pj->update(['dorsal' => $f['dorsal']]);
                }
            } catch (\Exception $e) {
                $this->fallidas++;
                $this->aviso('No se pudo sumar a la plantilla — ' . $f['_nombre'] . ': ' . $this->mensajeCorto($e));
            }
        }
    }

    /**
     * `alineacions.orden` guarda el PUESTO, no la posición en la lista.
     * Es la convención de toda la base (ver FechaController y AlineacionController):
     * arquero 0, defensor 1, medio 2, delantero 3, y lo desconocido va como 3.
     */
    private function ordenPorPuesto($jugadorId)
    {
        $tipo = $jugadorId < 0
            ? (isset($this->tiposPreview[$jugadorId]) ? $this->tiposPreview[$jugadorId] : '')
            : $this->tipoJugador($jugadorId);

        switch ($tipo) {
            case 'Arquero':   return 0;
            case 'Defensor':  return 1;
            case 'Medio':     return 2;
            case 'Delantero': return 3;
            default:          return 3;
        }
    }

    private function tipoJugador($id)
    {
        if (!isset($this->cacheTipos[$id])) {
            $this->cacheTipos[$id] = (string) DB::table('jugadors')->where('id', $id)->value('tipoJugador');
        }
        return $this->cacheTipos[$id];
    }

    /** El mensaje de la excepción sin el SQL entero pegado atrás. */
    private function mensajeCorto(\Exception $e)
    {
        $m = $e->getMessage();
        $corte = strpos($m, ' (SQL:');
        if ($corte !== false) $m = substr($m, 0, $corte);
        return mb_substr(trim($m), 0, 300);
    }

    /**
     * Baja la foto de perfil y devuelve el nombre del archivo para `personas.foto`.
     *
     * Mismo camino que el importador de jugadores: los hosts de imágenes de
     * Transfermarkt están geo-bloqueados para el server (dan 502/504 aunque en
     * el navegador se vean), así que se sale por ScraperAPI con getBinary.
     *
     * Es UNA llamada más por persona nueva, y sólo la primera vez que aparece.
     * Que falle nunca aborta nada: la persona se crea igual, sin foto.
     */
    private function descargarFoto($url, $quien)
    {
        if (!$this->conFotos || empty($url)) return null;

        try {
            $img = HttpHelper::getBinary($url);
            $this->fotosBajadas++;

            if (empty($img['ok'])) {
                $this->aviso('Sin foto para ' . $quien . ' — HTTP ' . (isset($img['http']) ? $img['http'] : '?')
                    . (empty($img['error']) ? '' : ' (' . $img['error'] . ')'));
                return null;
            }

            $ruta      = parse_url($url, PHP_URL_PATH);
            $info      = pathinfo((string) $ruta);
            $archivo   = isset($info['filename']) ? $info['filename'] : '';
            $extension = isset($info['extension']) && $info['extension'] !== '' ? $info['extension'] : 'jpg';
            $archivo   = rtrim($archivo, '.');
            if ($archivo === '') return null;

            $nombre = $archivo . '.' . $extension;
            file_put_contents(public_path('images/') . $nombre, $img['body']);
            return $nombre;
        } catch (\Exception $e) {
            $this->aviso('Sin foto para ' . $quien . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Rescata el primer apellido cuando quedó del lado de los nombres de pila.
     *
     * `NombreHelper::separarTM` se ancla en el shortName para saber dónde
     * empieza el apellido. Si Transfermarkt muestra a la persona por su SEGUNDO
     * apellido, el ancla cae demasiado a la derecha y el primero se va a los
     * nombres:
     *
     *   Roberto Ariel Pereyra Legallais  ->  nombre "Roberto Ariel Pereyra"
     *                                        apellido "Legallais"          (mal)
     *                                    ->  nombre "Roberto Ariel"
     *                                        apellido "Pereyra Legallais"  (bien)
     *
     * La pista es la forma: en el mundo hispano los nombres de pila son uno o
     * dos, y los apellidos dos. Si quedaron tres nombres y un solo apellido, el
     * último "nombre" en realidad es el primer apellido.
     *
     * Sólo se aplica a nacionalidades donde rige esa costumbre. Un brasileño
     * llamado "Carlos Alberto Silva" tiene tres nombres y un apellido de verdad,
     * y ahí la regla rompería en vez de arreglar.
     *
     * Nota: esto vive acá y NO en NombreHelper a propósito. NombreHelper lo usan
     * los otros importadores y no conoce la nacionalidad; tocarlo cambiaría
     * cómo se cargan nombres en flujos que hoy andan bien.
     */
    private function rescatarPrimerApellido(array $n, $nacionalidad)
    {
        static $dosApellidos = ['Argentina', 'Bolivia', 'Chile', 'Colombia', 'Costa Rica', 'Cuba',
            'Ecuador', 'El Salvador', 'España', 'Guatemala', 'Guinea Ecuatorial', 'Honduras',
            'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Puerto Rico',
            'República Dominicana', 'Uruguay', 'Venezuela'];

        if ($nacionalidad === null || !in_array($nacionalidad, $dosApellidos, true)) return $n;

        $nombres   = preg_split('/\s+/', trim($n['nombre']));
        $apellidos = preg_split('/\s+/', trim($n['apellido']));
        $nombres   = array_values(array_filter($nombres, 'strlen'));
        $apellidos = array_values(array_filter($apellidos, 'strlen'));

        if (count($nombres) < 3 || count($apellidos) !== 1) return $n;

        // Las partículas se arrastran con el apellido: "…de la Cruz Pérez".
        $particulas = ['de','da','do','dos','das','del','della','di','la','las','los','van','von','der','den','du','le'];
        $mover = [array_pop($nombres)];
        while (!empty($nombres) && count($nombres) >= 2
            && in_array(mb_strtolower(end($nombres)), $particulas, true)) {
            array_unshift($mover, array_pop($nombres));
        }

        $n['nombre']   = implode(' ', $nombres);
        $n['apellido'] = implode(' ', $mover) . ' ' . $n['apellido'];
        return $n;
    }

    /** Traduce el perfil de la API a nuestros campos (mismo criterio que el import de jugadores). */
    private function personaDesdePerfil(array $p)
    {
        $n = NombreHelper::separarTM($p);

        // ── Dos formas conviven en tmapi ────────────────────────────────────
        // Jugadores y DTs (/players, /coaches) vienen ANIDADOS:
        //     lifeDates.dateOfBirth · birthPlaceDetails.placeOfBirth
        //     nationalityDetails.nationalities.nationalityId
        // Los árbitros (/referees) vienen PLANOS, colgando de la raíz:
        //     dateOfBirth · nationalities.nationalityId
        // Hay que leer las dos. Leyendo solo la anidada, todo árbitro quedaba
        // sin fecha y sin país — y como `personas.nacionalidad` tiene DEFAULT
        // 'Argentina' en la base, salían estampados como argentinos.
        $aFecha = function ($raw) {
            if (!$raw) return null;
            try { return Carbon::parse($raw)->format('Y-m-d'); } catch (\Exception $e) { return null; }
        };

        $nacimiento = $aFecha(isset($p['lifeDates']['dateOfBirth'])
            ? $p['lifeDates']['dateOfBirth']
            : (isset($p['dateOfBirth']) ? $p['dateOfBirth'] : null));

        $fallecimiento = $aFecha(isset($p['lifeDates']['dateOfDeath'])
            ? $p['lifeDates']['dateOfDeath']
            : (isset($p['dateOfDeath']) ? $p['dateOfDeath'] : null));

        $ciudad = trim((string) (isset($p['birthPlaceDetails']['placeOfBirth'])
            ? $p['birthPlaceDetails']['placeOfBirth']
            : (isset($p['placeOfBirth']) ? $p['placeOfBirth'] : '')));
        $ciudad = $ciudad !== '' ? $ciudad : null;

        $nacId = 0;
        $candidatos = [
            isset($p['nationalityDetails']['nationalities']['nationalityId']) ? $p['nationalityDetails']['nationalities']['nationalityId'] : null,
            isset($p['nationalityDetails']['nationalities'][0]['nationalityId']) ? $p['nationalityDetails']['nationalities'][0]['nationalityId'] : null,
            isset($p['nationalities']['nationalityId']) ? $p['nationalities']['nationalityId'] : null,
            isset($p['nationalities'][0]['nationalityId']) ? $p['nationalities'][0]['nationalityId'] : null,
            isset($p['nationalityId']) ? $p['nationalityId'] : null,
        ];
        foreach ($candidatos as $cand) {
            if ((int) $cand > 0) { $nacId = (int) $cand; break; }
        }

        $nacionalidad = null;
        if ($nacId) {
            $paises = JugadorController::paisesTM();
            $nacionalidad = isset($paises[$nacId]) ? $paises[$nacId] : null;
            if ($nacionalidad === null) {
                $this->aviso('Código de país de Transfermarkt sin mapear: ' . $nacId . ' (' . $n['name'] . ').');
            }
        } else {
            // No podemos dejarlo pasar en silencio: la base le pone Argentina.
            $this->aviso('Transfermarkt no trajo nacionalidad para "' . $n['name'] . '". '
                . 'Ojo que la base le va a poner Argentina por default: revisalo a mano.');
        }

        $altura = isset($p['attributes']['height']) ? $p['attributes']['height'] : null;
        $altura = ($altura !== null && is_numeric($altura)) ? (float) $altura : null;

        $grupo = strtoupper((string) (isset($p['attributes']['positionGroup']) ? $p['attributes']['positionGroup'] : ''));
        $tipo = null;
        if ($grupo === 'GOALKEEPER') $tipo = 'Arquero';
        elseif ($grupo === 'DEFENDER') $tipo = 'Defensor';
        elseif ($grupo === 'MIDFIELD' || $grupo === 'MIDFIELDER') $tipo = 'Medio';
        elseif ($grupo === 'FORWARD' || $grupo === 'STRIKER' || $grupo === 'ATTACK') $tipo = 'Delantero';

        $pieRaw = trim((string) (isset($p['attributes']['preferredFoot']['name']) ? $p['attributes']['preferredFoot']['name'] : ''));
        $pie = null;
        if ($pieRaw === 'Derecho') $pie = 'Derecha';
        elseif ($pieRaw === 'Izquierdo') $pie = 'Izquierda';
        elseif ($pieRaw === 'Ambidiestro') $pie = 'Ambas';

        $n = $this->rescatarPrimerApellido($n, $nacionalidad);

        $persona = ['name' => trim($n['name']), 'nombre' => trim($n['nombre']), 'apellido' => trim($n['apellido'])];
        if ($ciudad)        $persona['ciudad'] = $ciudad;
        if ($nacionalidad)  $persona['nacionalidad'] = $nacionalidad;
        if ($nacimiento)    $persona['nacimiento'] = $nacimiento;
        if ($fallecimiento) $persona['fallecimiento'] = $fallecimiento;
        if ($altura)        $persona['altura'] = $altura;

        $jugador = [];
        if ($tipo) $jugador['tipoJugador'] = $tipo;
        if ($pie)  $jugador['pie'] = $pie;
        $tmId = $this->valor($p, ['id', 'playerId']);
        if ($tmId) $jugador['transfermarkt_url'] = 'https://www.transfermarkt.es/-/profil/spieler/' . $tmId;

        // Los nombres tal cual los mandó Transfermarkt. Sirven para entender por
        // qué NombreHelper partió el nombre donde lo partió cuando el apellido
        // sale mal (pasa con los apellidos compuestos: si el shortName usa el
        // segundo apellido, el primero se va al nombre de pila).
        // Foto de perfil. La URL se guarda acá y la imagen se baja recién al
        // crear a la persona: en la vista previa no gastamos llamadas.
        $portrait = trim((string) (isset($p['portraitUrl']) ? $p['portraitUrl'] : ''));
        if ($portrait !== '' && (!filter_var($portrait, FILTER_VALIDATE_URL) || strpos($portrait, 'default.jpg') !== false)) {
            $portrait = '';
        }

        $crudos = [];
        foreach (['name' => 'name', 'shortName' => 'short', 'displayName' => 'display'] as $k => $et) {
            if (!empty($p[$k])) $crudos[] = $et . '="' . $p[$k] . '"';
        }
        if (!empty($p['nationalityDetails']['passportName'])) {
            $crudos[] = 'pasaporte="' . $p['nationalityDetails']['passportName'] . '"';
        }

        return [
            'name'         => trim($n['name']),
            'nombre'       => trim($n['nombre']),
            'apellido'     => trim($n['apellido']),
            'nacimiento'   => $nacimiento,
            'nacionalidad' => $nacionalidad,
            'tm_nombres'   => implode(' · ', $crudos),
            'portrait'     => $portrait !== '' ? $portrait : null,
            'persona'      => $persona,
            'jugador'      => $jugador,
        ];
    }

    // ═══════════════════════════ MAPEOS ═══════════════════════════

    private function mapaJugadores()
    {
        if ($this->mapaJugadores !== null) return $this->mapaJugadores;
        $mapa = [];
        foreach (DB::table('jugador_tm')->select('tm_player_id', 'jugador_id')->get() as $r) {
            $mapa[(string) $r->tm_player_id] = (int) $r->jugador_id;
        }
        return $this->mapaJugadores = $mapa;
    }

    private function mapaArbitros()
    {
        if ($this->mapaArbitros !== null) return $this->mapaArbitros;
        $mapa = [];
        foreach (DB::table('arbitro_tm')->select('tm_referee_id', 'arbitro_id')->get() as $r) {
            $mapa[(string) $r->tm_referee_id] = (int) $r->arbitro_id;
        }
        return $this->mapaArbitros = $mapa;
    }

    private function mapaEquipos()
    {
        if ($this->mapaEquipos !== null) return $this->mapaEquipos;
        $mapa = [];
        foreach (DB::table('equipo_tm')->select('tm_club_id', 'equipo_id')->get() as $r) {
            $mapa[(string) $r->tm_club_id] = (int) $r->equipo_id;
        }
        return $this->mapaEquipos = $mapa;
    }

    /**
     * tm_club_id -> equipo_id según la fila de `import_partidos` que originó
     * este partido. El importador de partidos resuelve los clubes por clubId
     * *o por nombre*, y en el segundo caso el mapeo nunca queda guardado en
     * `equipo_tm`. Acá lo recuperamos de la fila y lo aprendemos.
     */
    private function mapaDesdeStaging($partidoId)
    {
        $mapa = [];
        $filas = DB::table('import_partidos')->where('partido_id', (int) $partidoId)
            ->select('club_external_id', 'equipo_id', 'rival_external_id', 'rival_id')->get();
        foreach ($filas as $f) {
            if ($f->club_external_id && $f->equipo_id)   $mapa[(string) $f->club_external_id]  = (int) $f->equipo_id;
            if ($f->rival_external_id && $f->rival_id)   $mapa[(string) $f->rival_external_id] = (int) $f->rival_id;
        }
        return $mapa;
    }

    /** Deja el club atado en equipo_tm (sólo cuando estamos escribiendo de verdad). */
    private function aprenderClub($tmClubId, $equipoId, $origen, $escribir)
    {
        if ($this->mapaEquipos !== null) $this->mapaEquipos[(string) $tmClubId] = (int) $equipoId;
        if (!$escribir) return;
        try {
            DB::table('equipo_tm')->updateOrInsert(
                ['tm_club_id' => (string) $tmClubId],
                ['equipo_id' => (int) $equipoId, 'nombre_tm' => $this->nombreEquipo($equipoId),
                    'origen' => $origen, 'updated_at' => now(), 'created_at' => now()]
            );
        } catch (\Exception $e) {
            $this->aviso('No pude guardar el mapeo del club ' . $tmClubId . ': ' . $e->getMessage());
        }
    }

    public function guardarMapeoJugador($tmId, $jugadorId, $nombre, $origen, $revisar)
    {
        DB::table('jugador_tm')->updateOrInsert(
            ['tm_player_id' => (string) $tmId],
            ['jugador_id' => (int) $jugadorId, 'nombre_tm' => $nombre, 'origen' => $origen,
                'revisar' => $revisar ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]
        );
        if ($this->mapaJugadores !== null) $this->mapaJugadores[(string) $tmId] = (int) $jugadorId;
    }

    /**
     * Siembra `jugador_tm` con los jugadores que ya tenías cargados y tienen
     * la URL de Transfermarkt. Es lo que evita que el importador cree de nuevo
     * a alguien que ya está en la base.
     */
    public static function sembrarDesdeUrls()
    {
        $creados = 0; $yaEstaban = 0; $sinId = 0;

        $existentes = [];
        foreach (DB::table('jugador_tm')->select('tm_player_id')->get() as $r) {
            $existentes[(string) $r->tm_player_id] = true;
        }

        DB::table('jugadors')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->select('id', 'transfermarkt_url')
            ->orderBy('id')
            ->chunk(500, function ($filas) use (&$creados, &$yaEstaban, &$sinId, &$existentes) {
                $insert = [];
                foreach ($filas as $f) {
                    if (!preg_match('#/spieler/(\d+)#', (string) $f->transfermarkt_url, $m)) { $sinId++; continue; }
                    $tm = $m[1];
                    if (isset($existentes[$tm])) { $yaEstaban++; continue; }
                    $existentes[$tm] = true;
                    $insert[] = ['tm_player_id' => $tm, 'jugador_id' => (int) $f->id, 'nombre_tm' => null,
                        'origen' => 'url', 'revisar' => 0, 'created_at' => now(), 'updated_at' => now()];
                    $creados++;
                }
                if (!empty($insert)) DB::table('jugador_tm')->insert($insert);
            });

        return ['creados' => $creados, 'ya_estaban' => $yaEstaban, 'sin_id' => $sinId];
    }

    // ═══════════════════════════ UTILIDADES ═══════════════════════════

    private function valor($arr, array $claves)
    {
        if (!is_array($arr)) return null;
        foreach ($claves as $k) {
            if (array_key_exists($k, $arr) && $arr[$k] !== null && $arr[$k] !== '' && !is_array($arr[$k])) {
                return $arr[$k];
            }
        }
        // Algunas veces el valor viene anidado: {"player": {"id": 123}}
        foreach ($claves as $k) {
            if (isset($arr[$k]) && is_array($arr[$k]) && isset($arr[$k]['id'])) return $arr[$k]['id'];
        }
        return null;
    }

    /** Junta en un solo string todos los campos de texto candidatos. */
    private function juntarTexto(array $a, array $claves)
    {
        $partes = [];
        foreach ($claves as $k) {
            if (!isset($a[$k])) continue;
            if (is_string($a[$k]) || is_numeric($a[$k])) $partes[] = (string) $a[$k];
            elseif (is_array($a[$k]) && isset($a[$k]['name'])) $partes[] = (string) $a[$k]['name'];
        }
        return trim(implode(' | ', $partes));
    }

    private function resumenCrudo($a)
    {
        return mb_substr(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 200);
    }

    /** Saca las claves auxiliares (_nombre, _equipo…) antes de insertar. */
    private function limpiar(array $r)
    {
        foreach (array_keys($r) as $k) {
            if (strpos($k, '_') === 0) unset($r[$k]);
        }
        return $r;
    }

    private function nombreJugador($id)
    {
        if (!$id) return '—';
        if ($id < 0) return isset($this->nombresPreview[$id]) ? $this->nombresPreview[$id] : 'jugador nuevo';
        $j = DB::table('jugadors')->join('personas', 'personas.id', '=', 'jugadors.persona_id')
            ->where('jugadors.id', $id)->select('personas.name', 'personas.apellido')->first();
        return $j ? ($j->name ?: $j->apellido) : ('jugador #' . $id);
    }

    private function nombreArbitro($id)
    {
        if ($id < 0) return isset($this->nombresPreview[$id]) ? $this->nombresPreview[$id] : 'árbitro nuevo';
        $a = DB::table('arbitros')->join('personas', 'personas.id', '=', 'arbitros.persona_id')
            ->where('arbitros.id', $id)->select('personas.name')->first();
        return $a ? $a->name : ('árbitro #' . $id);
    }

    private function nombreEquipo($id)
    {
        $e = DB::table('equipos')->where('id', $id)->select('nombre')->first();
        return $e ? $e->nombre : ('equipo #' . $id);
    }

    private function aviso($txt)
    {
        if (!in_array($txt, $this->avisos, true)) $this->avisos[] = $txt;
    }

    private function ultimoAviso()
    {
        return empty($this->avisos) ? null : end($this->avisos);
    }
}
