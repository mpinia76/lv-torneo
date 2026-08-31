<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
use App\Penal;
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
     * `penals` describe UNA acción por protagonista, no un penal por fila.
     * Es el criterio con el que se vienen cargando los partidos a mano:
     *
     *   · lo tiró afuera / al palo  → una fila:  el PATEADOR con «Errado»
     *   · se lo atajaron            → dos filas: el PATEADOR con «Atajado»
     *                                            y el ARQUERO con «Atajó»
     *   · lo convirtió              → el gol va en `gols` (tipo «Penal») y en
     *     `penals` va el ARQUERO con «Convirtieron» (lo hace ControlPenales).
     */
    const PEN_ERRADO      = 'Errado';
    const PEN_ATAJADO     = 'Atajado';
    const PEN_ATAJO       = 'Atajó';
    const PEN_CONVIRTIERON = 'Convirtieron';

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
     * En `missedPenalties` el QUÉ pasó está en `reason`, no en `action`:
     * `action` viene "Not reported" y `reason` dice "Saved". Es la excepción a
     * la regla de arriba.
     */
    private static $kMotivoPen = ['reason', 'reasonName', 'description'];

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
    /**
     * tm_id => id fantasma, de los mapeos que apuntan a una ficha que ya no
     * existe. Se llenan al leer el mapa y se avisan cuando el partido los usa.
     */
    private $mapeosRotosJugador = [];
    private $mapeosRotosArbitro = [];
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
    /** Crudo de los árbitros del último partido, para mostrarlo en la vista previa. */
    private $crudoArbitros = null;
    /** Por qué no se pudo sacar el marcador del fixture guardado. */
    private $motivoMarcador = null;

    // ═════════════════════════════════ API ═════════════════════════════════

    /**
     * @param  int    $partidoId  partido nuestro que hay que completar
     * @param  string $gameId     gameId de Transfermarkt
     * @param  array  $opts       ['escribir' => bool, 'forzar' => bool, 'crear_jugadores' => bool]
     * @return array  informe
     */
    /**
     * Crea las filas de `penals` de los penales CONVERTIDOS: el arquero que lo
     * recibió, con tipo «Convirtieron».
     *
     * Es sólo una parte de `penals`. Los penales que no fueron gol —el
     * «Errado» / «Atajado» del pateador y el «Atajó» del arquero— salen de
     * `missedPenalties` y los arma el plan, más arriba. Acá se resuelven los
     * convertidos, que en el JSON figuran como un gol y no dicen quién era el
     * arquero: hay que deducirlo de la alineación, los cambios y las rojas.
     *
     * Nunca pisa un penal ya cargado, ni siquiera cuando se rehace con
     * `forzar`: si le corregiste el arquero a mano, tu corrección sobrevive.
     */
    private function crearPenales(Partido $partido): array
    {
        try {
            $r = app(\App\Services\ControlPenales::class)->aplicarPartido($partido->id);

            if (!empty($r['creados'])) {
                $this->aviso('Le creé ' . $r['creados'] . ' penal(es) convertido(s) con el arquero que '
                    . 'estaba en cancha en ese minuto.');
            }
            if (!empty($r['sin_arquero'])) {
                $this->aviso($r['sin_arquero'] . ' gol(es) de penal quedaron sin su fila en `penals` porque '
                    . 'no se pudo determinar el arquero. Miralos en Controles → Penales sin cargar.');
            }
            return $r;
        } catch (\Exception $e) {
            // Que esto falle no puede tirar abajo un detalle bien importado.
            Log::error('crearPenales partido ' . $partido->id . ': ' . $e->getMessage());
            $this->aviso('No pude crear los penales de este partido: ' . $e->getMessage());
            return ['creados' => 0, 'sin_arquero' => 0, 'restantes' => 0];
        }
    }

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
                'penals' => [], 'arbitros' => [], 'tecnicos' => [], 'plantillas' => []],
            'creados'     => ['jugadores' => [], 'arbitros' => [], 'tecnicos' => []],
            'llamadas'    => 0,
            'crudo'       => null,
            'crudo_arbitros' => null,
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
                // Aunque no se rehaga el detalle, si el partido está sin
                // marcador se lo completamos: el resultado está en el payload
                // que ya guardó el fixture, así que no cuesta ninguna llamada.
                $puesto = $this->marcadorDesdeStaging($partido);

                $informe['error'] = 'El partido #' . $partido->id . ' ya tiene alineación cargada ('
                    . $yaCargado . ' jugadores). Para reemplazarla usá "Rehacer".'
                    . ($puesto ? ' (Igual le cargué el resultado, que estaba vacío.)' : '');
                $informe['avisos'] = $this->avisos;
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

        // Los que faltan porque su mapeo apuntaba a una ficha borrada no son
        // jugadores nuevos: ya habían estado atados. Se avisa para que no
        // parezca que Transfermarkt trajo gente desconocida.
        foreach (array_intersect_key($this->mapeosRotosJugador, array_flip($faltan)) as $tmId => $fantasma) {
            $this->aviso('El mapeo del jugador TM ' . $tmId . ' apuntaba al jugador #' . $fantasma
                . ', que ya no existe: se lo llevó una fusión de personas o el borrado de huérfanas. '
                . 'Lo apareo de nuevo desde el perfil y dejo la fila apuntando a la ficha que quedó. '
                . 'Los mapeos rotos se limpian todos juntos en "Mapeos rotos".');
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
            'penals' => [], 'arbitros' => [], 'tecnicos' => [], 'plantillas' => []];

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

        // Penales que NO fueron gol. Van en su propio método porque el pase
        // liviano (`soloPenales`) usa exactamente esto y nada más.
        $plan['penals'] = $this->planPenales($game, $partido, $lados, $mapa);

        // Árbitros: sólo los que ya estén atados en arbitro_tm. Los que no,
        // se listan como aviso — no inventamos personas sin nombre.
        $plan['arbitros'] = $this->planArbitros($game, $partido, $escribir, $informe);
        if ($informe !== null) $informe['crudo_arbitros'] = $this->crudoArbitros;
        $plan['tecnicos'] = $this->planTecnicos($game, $partido, $lados, $escribir, $informe);

        $informe['plan']   = $plan;
        $informe['avisos']   = $this->avisos;
        $informe['ok']       = true;
        $informe['llamadas'] += $this->fotosBajadas;   // cada foto es una llamada más

        // ── 6) Guardar ─────────────────────────────────────────────────────
        if ($escribir) {
            try {
                DB::transaction(function () use ($plan, $partido, $forzar, $game, $lados) {
                    if ($forzar) {
                        Alineacion::where('partido_id', $partido->id)->delete();
                        Gol::where('partido_id', $partido->id)->delete();
                        Tarjeta::where('partido_id', $partido->id)->delete();
                        Cambio::where('partido_id', $partido->id)->delete();
                        PartidoArbitro::where('partido_id', $partido->id)->delete();
                        // De `penals` se borra SOLO lo que escribe este
                        // importador. Los «Convirtieron» quedan: los crea
                        // ControlPenales deduciendo el arquero, y ese arquero se
                        // puede haber corregido a mano. Rehacer no debe pisar eso.
                        Penal::where('partido_id', $partido->id)
                            ->whereIn('tipo', [self::PEN_ERRADO, self::PEN_ATAJADO, self::PEN_ATAJO])
                            ->delete();
                    }
                    // Primero la plantilla: es lo que hace que el jugador exista
                    // para la pantalla de alineaciones del torneo.
                    $this->grabarPlantillas($plan['plantillas']);

                    $this->grabarFilas(Alineacion::class,    $plan['alineacions'], 'la alineación');
                    $this->grabarFilas(Gol::class,          $plan['gols'],        'un gol');
                    $this->grabarFilas(Tarjeta::class,      $plan['tarjetas'],    'una tarjeta');
                    $this->grabarFilas(Cambio::class,       $plan['cambios'],     'un cambio');
                    $this->grabarFilas(PartidoArbitro::class, $plan['arbitros'],  'un árbitro');
                    $this->grabarPenales($plan['penals']);

                    // Si el partido no tenía marcador, se lo ponemos. Primero
                    // con lo que acaba de bajar; si de ahí no sale, con el
                    // fixture que ya estaba guardado. Si igual queda sin
                    // resultado, se avisa: callado no se entiende nunca.
                    if (!$this->completarMarcador($game, $partido, $lados)
                        && !$this->marcadorDesdeStaging($partido)
                        && ($partido->golesl === null || $partido->golesv === null)) {
                        $this->aviso('El partido quedó sin resultado: '
                            . ($this->motivoMarcador ?: 'Transfermarkt todavía no lo da por jugado') . '.');
                    }

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

                // Este importador ya lee `missedPenalties`, así que el partido
                // queda revisado y no tiene que salir en el pase de penales.
                self::marcarPenalesRevisados([(int) $partido->id]);

                // Los penales van FUERA de la transacción de arriba a propósito:
                // el arquero se resuelve leyendo la alineación, los cambios y
                // las rojas recién guardados.
                $informe['penales'] = $this->crearPenales($partido);
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

    /**
     * Pase liviano: baja el partido y escribe SOLO los penales fallados.
     *
     * Existe por una razón concreta. Hasta el 31/08/2026 el importador no leía
     * `actions.missedPenalties`, así que hay ~1900 partidos ya cargados a los
     * que les puede faltar un penal errado o atajado. Cuáles, no se puede saber
     * desde la base: el dato sólo está en Transfermarkt, una llamada por
     * partido.
     *
     * Rehacer el detalle completo también los traería, pero cuesta lo mismo y
     * hace mucho más: pisa alineación, goles, tarjetas y cambios —incluidas las
     * correcciones a mano— y puede crear jugadores y bajar fotos. Esto no toca
     * nada de eso: una llamada, las filas de `penals`, y la marca de revisado
     * para no volver a pagarla.
     *
     * Lo único que sí crea son las fichas del pateador y del arquero cuando no
     * están mapeados (1 llamada extra, sólo en ese caso): sin eso el penal se
     * perdería en silencio y el partido quedaría marcado como revisado igual.
     * Las fotos van apagadas — el jugador nuevo sale en "Jugadores por revisar"
     * y ahí se completa.
     */
    public function soloPenales($partidoId, $gameId, array $opts = [])
    {
        $escribir = array_key_exists('escribir', $opts) ? (bool) $opts['escribir'] : true;

        $this->avisos = [];
        $this->fotosBajadas = 0;
        $this->fallidas = 0;
        $this->conFotos = array_key_exists('fotos', $opts) ? (bool) $opts['fotos'] : false;

        $informe = [
            'ok'         => false,
            'escrito'    => false,
            'error'      => null,
            'partido_id' => (int) $partidoId,
            'game_id'    => (string) $gameId,
            'avisos'     => [],
            'fallidas'   => 0,
            'llamadas'   => 0,
            'penals'     => [],
            'creados'    => [],
        ];

        $partido = Partido::find($partidoId);
        if (!$partido) {
            $informe['error'] = 'No existe el partido #' . (int) $partidoId . '.';
            return $informe;
        }

        $json = HttpHelper::getJson(self::TMAPI . '/game/' . rawurlencode($gameId));
        $informe['llamadas']++;
        if (!is_array($json) || empty($json)) {
            $err = method_exists('App\Services\HttpHelper', 'getLastJsonError') ? HttpHelper::getLastJsonError() : null;
            $informe['error'] = 'La API no devolvió el partido ' . $gameId
                . (is_array($err) ? ' — ' . json_encode($err, JSON_UNESCAPED_UNICODE) : '');
            return $informe;
        }
        $game = isset($json['data']) ? $json['data'] : $json;

        // Mismo control que el importador completo: si los clubes no aparean, el
        // gameId no es de este partido y no se escribe nada. Sin esto, un gameId
        // equivocado le metería penales de otro partido.
        $this->mapaStaging = $this->mapaDesdeStaging($partido->id);
        $lados = $this->orientar($game, $partido, false);
        if ($lados === null) {
            $informe['error'] = $this->ultimoAviso() ?: 'No pude aparear los clubes del partido con los de la base.';
            $informe['avisos'] = $this->avisos;
            return $informe;
        }

        // El pateador o el arquero pueden no estar en `jugador_tm` aunque el
        // partido ya tenga la alineación cargada: pasa cuando el importador
        // viejo no pudo resolverlo, o cuando TM lo lista en las acciones pero
        // no en el once. Si no se los resuelve acá, el penal se pierde en
        // silencio y encima el partido queda marcado como revisado.
        $mapa = $this->resolverProtagonistas($game, $lados, $escribir, $informe);

        $filas = $this->planPenales($game, $partido, $lados, $mapa);
        $informe['penals'] = $filas;
        $informe['ok'] = true;

        if ($escribir) {
            try {
                DB::transaction(function () use ($filas, $partido) {
                    // Se rehacen sólo los tipos que escribe este pase. Los
                    // «Convirtieron» son de ControlPenales y su arquero puede
                    // estar corregido a mano: no se tocan nunca.
                    Penal::where('partido_id', $partido->id)
                        ->whereIn('tipo', [self::PEN_ERRADO, self::PEN_ATAJADO, self::PEN_ATAJO])
                        ->delete();
                    $this->grabarPenales($filas);
                });
                $informe['escrito'] = true;

                // La marca va aunque no haya habido ningún penal: "revisado" es
                // "ya le pregunté a TM", no "tenía penales". Sin esto el partido
                // sin penales volvería a salir en la lista para siempre.
                self::marcarPenalesRevisados([(int) $partido->id]);
            } catch (\Exception $e) {
                $informe['ok']    = false;
                $informe['error'] = 'Error guardando los penales: ' . $e->getMessage();
                Log::error('TmDetallePartido soloPenales partido ' . $partido->id . ': ' . $e->getMessage());
            }
        }

        $informe['avisos']   = $this->avisos;
        $informe['fallidas'] = $this->fallidas;
        $informe['llamadas'] += $this->fotosBajadas;
        return $informe;
    }

    /**
     * Resuelve al pateador y al arquero de cada penal fallado, creándolos si no
     * están en la base.
     *
     * Sólo mira los ids que aparecen en `missedPenalties`, no toda la
     * alineación: el pase de penales no está para completar fichas, está para
     * que ningún penal se pierda porque su protagonista no estaba mapeado.
     * Cuesta 1 llamada extra y sólo cuando falta alguien, que es raro.
     *
     * Las fotos van apagadas: el jugador se crea con `revisar = 1` y aparece en
     * "Jugadores por revisar", así que la foto se resuelve ahí y no gastamos una
     * llamada más en un pase que se corre 1900 veces.
     */
    private function resolverProtagonistas(array $game, array $lados, $escribir, array &$informe)
    {
        $mapa = $this->mapaJugadores();

        $faltan = [];
        foreach ($lados as $lado) {
            foreach ($this->accionesDelLado($game, $lado['clave']) as $accion) {
                if ($accion['clase'] !== 'penal') continue;
                foreach ($accion['ids'] as $id) {
                    if ($id === null) continue;
                    if (!isset($mapa[(string) $id])) $faltan[(string) $id] = true;
                }
            }
        }
        $faltan = array_keys($faltan);
        if (empty($faltan)) return $mapa;

        foreach (array_intersect_key($this->mapeosRotosJugador, array_flip($faltan)) as $tmId => $fantasma) {
            $this->aviso('El mapeo del jugador TM ' . $tmId . ' apuntaba al jugador #' . $fantasma
                . ', que ya no existe. Lo apareo de nuevo desde el perfil.');
        }

        $perfiles = $this->traerPerfiles($faltan, $informe);
        foreach ($faltan as $id) {
            if (!isset($perfiles[$id])) {
                $this->aviso('Sin perfil para el jugador TM ' . $id . ': no puedo crearlo, así que ese penal '
                    . 'queda sin cargar. Cargalo a mano en la pantalla de penales del partido.');
                continue;
            }
            $res = $this->resolverJugador($id, $perfiles[$id], $escribir);
            if (!$res['jugador_id']) continue;

            $mapa[$id] = (int) $res['jugador_id'];
            if ($res['creado']) {
                $informe['creados'][] = $res['descripcion'];
                $this->aviso('El protagonista de un penal no estaba en la base: creé a '
                    . $res['descripcion'] . '. Queda en "Jugadores por revisar".');
            }
        }

        $this->mapaJugadores = $mapa;
        return $mapa;
    }

    /**
     * Deja anotado que a estos partidos ya se les preguntaron los penales.
     *
     * Silencioso si la columna todavía no existe: la migración puede no haber
     * corrido y eso no es motivo para que falle una importación entera.
     */
    public static function marcarPenalesRevisados(array $partidoIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $partidoIds))));
        if (empty($ids)) return;
        if (!Schema::hasColumn('import_partidos', 'penales_revisado_at')) return;

        try {
            DB::table('import_partidos')->whereIn('partido_id', $ids)
                ->update(['penales_revisado_at' => now()]);
        } catch (\Exception $e) {
            Log::error('marcarPenalesRevisados: ' . $e->getMessage());
        }
    }

    // ═══════════════════════════ ORIENTACIÓN ═══════════════════════════

    /**
     * Decide qué lado de Transfermarkt (homeClub / awayClub) corresponde a
     * cada equipo nuestro. Si no coinciden, aborta: mejor no cargar nada que
     * cargar la alineación del rival.
     */
    /**
     * Completa el marcador usando el payload que ya está en `import_partidos`,
     * sin llamar a la API. Sirve cuando el partido ya tiene el detalle cargado
     * y por eso `importar()` corta antes de bajar nada.
     */
    private function marcadorDesdeStaging(Partido $partido, $escribir = true)
    {
        $this->motivoMarcador = null;

        if ($partido->golesl !== null && $partido->golesv !== null) {
            $this->motivoMarcador = 'ya tenía resultado';
            return false;
        }

        $fila = DB::table('import_partidos')
            ->where('partido_id', $partido->id)->whereNotNull('payload')
            ->orderBy('id', 'desc')->first();
        if (!$fila) {
            $this->motivoMarcador = 'no hay fixture guardado para este partido';
            return false;
        }

        $g = json_decode($fila->payload, true);
        if (!is_array($g)) {
            $this->motivoMarcador = 'el fixture guardado no se puede leer';
            return false;
        }
        if (empty($g['isFinished'])) {
            $this->motivoMarcador = 'el fixture guardado lo tiene como no jugado: hay que bajar el detalle';
            return false;
        }
        if (!isset($g['score']['home']) || !isset($g['score']['away'])) {
            $this->motivoMarcador = 'el fixture guardado no trae el marcador';
            return false;
        }
        // El `score` del fixture de un partido por penales viene con la tanda
        // sumada (1:1 con penales 4:2 lo publica como 5:3) y el listado no trae
        // los penales pateados, asi que no hay con que separarlo. Se baja el
        // detalle o se carga a mano: lo que no se hace es inventar el marcador.
        if (isset($g['score']['firstLegScore'])) {
            $this->motivoMarcador = 'es la vuelta de una llave: el numero que publica TM no es el '
                . 'marcador de esos 90 minutos (puede ser el global o la tanda). Cargalo a mano';
            return false;
        }
        if (isset($g['score']['additionType']) && $g['score']['additionType'] === 'after_shootout') {
            $this->motivoMarcador = 'se definio por penales: el marcador del fixture viene con la '
                . 'tanda sumada y no se puede separar sin bajar el detalle';
            return false;
        }

        $tmLocal = isset($g['homeClub']['clubId']) ? (string) $g['homeClub']['clubId'] : null;
        if ($tmLocal === null) {
            $this->motivoMarcador = 'el fixture no dice qué club era el local';
            return false;
        }

        $mapaEq = $this->mapaEquipos();
        $equipoTmLocal = isset($mapaEq[$tmLocal]) ? (int) $mapaEq[$tmLocal] : null;
        // Rescate: la fila de staging sabe a qué equipo nuestro corresponde el
        // club, pero `equipo_id` es el club del DT — sólo sirve como local si la
        // fila dice que jugaba de local.
        if (!$equipoTmLocal && $fila->equipo_id && !empty($fila->local)) {
            $equipoTmLocal = (int) $fila->equipo_id;
        }
        if (!$equipoTmLocal) {
            $this->motivoMarcador = 'el club local del fixture no está mapeado a ningún equipo';
            return false;
        }

        $home = (int) $g['score']['home']; $away = (int) $g['score']['away'];
        if ($equipoTmLocal === (int) $partido->equipol_id) {
            $gl = $home; $gv = $away;
        } elseif ($equipoTmLocal === (int) $partido->equipov_id) {
            $gl = $away; $gv = $home;
        } else {
            $this->motivoMarcador = 'el local del fixture no es ninguno de los dos equipos del partido';
            return false;
        }

        if ($escribir) {
            $partido->forceFill(['golesl' => $gl, 'golesv' => $gv])->save();
            $this->aviso('El partido estaba sin resultado: le cargué ' . $gl . ':' . $gv
                . ' desde el fixture que ya estaba guardado (sin gastar llamadas).');
        }
        return ['gl' => $gl, 'gv' => $gv];
    }

    /**
     * Completa el marcador de una tanda de partidos con el payload que ya está
     * guardado en `import_partidos`. **No gasta ninguna llamada a la API.**
     *
     * Es el arreglo para todo lo que se bajó antes de que el detalle escribiera
     * el resultado: esos partidos tienen la alineación cargada, así que
     * `importar()` corta antes de tocar nada.
     */
    public static function marcadoresDesdeStaging(array $partidoIds, $escribir = false)
    {
        $svc = new self();
        $out = ['mirados' => 0, 'con_marcador' => 0, 'escritos' => 0, 'filas' => []];

        foreach (array_unique(array_map('intval', $partidoIds)) as $id) {
            if (!$id) continue;
            $p = Partido::find($id);
            if (!$p) continue;
            if ($p->golesl !== null && $p->golesv !== null) continue;   // ya tiene resultado

            $out['mirados']++;
            $m = $svc->marcadorDesdeStaging($p, $escribir);

            $out['filas'][] = [
                'partido_id' => $id,
                'dia'        => (string) $p->dia,
                'equipol_id' => (int) $p->equipol_id,
                'equipov_id' => (int) $p->equipov_id,
                'gl'         => $m ? $m['gl'] : null,
                'gv'         => $m ? $m['gv'] : null,
                'motivo'     => $m ? null : $svc->motivoMarcador,
            ];
            if ($m) {
                $out['con_marcador']++;
                if ($escribir) $out['escritos']++;
            }
        }

        return $out;
    }

    /**
     * Carga el marcador si el partido está SIN resultado.
     *
     * El detalle traía goles, tarjetas y cambios pero nunca tocaba
     * `partidos.golesl/golesv`: eso sólo lo hacía el fixture al crear el
     * partido. Si el partido lo cargaste vos (Excel) y bajás las incidencias,
     * el resultado tiene que venir con ellas.
     *
     * NUNCA pisa un resultado ya cargado. Y respeta la orientación: `$lados[0]`
     * es el local SEGÚN TM, que no siempre es el local de tu partido (finales
     * en cancha neutral, ver `orientar()`).
     */
    /**
     * ¿El partido ya se jugó, según el JSON de `/game/{id}`?
     *
     * OJO: ese JSON **NO trae `isFinished`** — esa clave es del fixture por
     * competencia. Por pedirla acá, `completarMarcador()` cortaba siempre y el
     * detalle nunca cargaba el resultado la primera vez (costó dos rondas).
     * Lo que sí trae es `baseDetails.isGameReport`: Transfermarkt arma el
     * informe del partido recién cuando terminó.
     *
     * `isWithinLiveTimeframe` marca los que están en curso: ahí el marcador es
     * parcial y no se toca.
     */
    private function jugado(array $game)
    {
        if (!empty($game['isFinished'])) return true;               // forma del fixture
        if (!empty($game['isWithinLiveTimeframe'])) return false;    // en curso
        if (empty($game['baseDetails']['isGameReport'])) return false;

        // Cinturón y tiradores: hay informe y la hora del partido ya pasó.
        $cuando = isset($game['baseDetails']['date']['dateTimeUTC'])
            ? strtotime((string) $game['baseDetails']['date']['dateTimeUTC']) : null;
        return !$cuando || $cuando < time();
    }

    private function completarMarcador(array $game, Partido $partido, array $lados)
    {
        if ($partido->golesl !== null && $partido->golesv !== null) return false;

        $sc = isset($game['score']) && is_array($game['score']) ? $game['score'] : [];
        if (!isset($sc['home']) || !isset($sc['away'])) return false;
        if (!$this->jugado($game)) return false;   // sin jugar o en curso: no hay marcador

        $golesTmLocal = (int) $sc['home'];
        $golesTmVisit = (int) $sc['away'];

        // OJO CON LOS PENALES: si el partido se definio por tanda, el `score`
        // de TM no es el marcador de los 90' sino 90' + penales convertidos
        // (1:1 con tanda 4:2 lo publica como 5:3). Se separan los penales o no
        // se carga nada: un 5:3 en `golesl/golesv` es un partido que no existio.
        // Vuelta de una llave: el `score` no es el marcador de este partido
        // (comprobado con O'Higgins-Boca 4891294: 1:0 y tanda 3-4, TM publica
        // 3:4). No hay como separarlo, asi que no se carga nada.
        if (isset($sc['firstLegScore'])) {
            $this->aviso('Es la vuelta de una llave: no le cargue el marcador, porque el numero de TM '
                . 'no es el resultado de estos 90 minutos.');
            return false;
        }

        $penTmLocal = null; $penTmVisit = null;
        if (isset($sc['additionType']) && $sc['additionType'] === 'after_shootout') {
            $penTmLocal = $this->penalesConvertidos($game, 'homeClub');
            $penTmVisit = $this->penalesConvertidos($game, 'awayClub');
            if ($penTmLocal === null || $penTmVisit === null) {
                $this->aviso('El partido se definio por penales y el JSON no trae la tanda: no le '
                    . 'cargue el marcador, porque el de TM viene con los penales sumados.');
                return false;
            }
            $golesTmLocal -= $penTmLocal;
            $golesTmVisit -= $penTmVisit;
        }

        // $lados[0] es el equipo que TM pone de local.
        $equipoTmLocal = (int) $lados[0]['equipo_id'];

        if ($equipoTmLocal === (int) $partido->equipol_id) {
            $gl = $golesTmLocal; $gv = $golesTmVisit;
            $pl = $penTmLocal;   $pv = $penTmVisit;
        } elseif ($equipoTmLocal === (int) $partido->equipov_id) {
            $gl = $golesTmVisit; $gv = $golesTmLocal;
            $pl = $penTmVisit;   $pv = $penTmLocal;
        } else {
            return false;
        }

        $datos = ['golesl' => $gl, 'golesv' => $gv];
        if ($pl !== null && $pv !== null) { $datos['penalesl'] = $pl; $datos['penalesv'] = $pv; }

        $partido->forceFill($datos)->save();
        $this->aviso('El partido estaba sin resultado: le cargue ' . $gl . ':' . $gv
            . ($pl !== null ? ' y la tanda ' . $pl . '-' . $pv : '') . ' segun Transfermarkt.');
        return true;
    }

    /**
     * Penales convertidos por un lado en la tanda. Devuelve **null** si el JSON
     * no trae `actions.shootout` (el listado del fixture no lo trae), que no es
     * lo mismo que cero: null significa "no se puede separar la tanda".
     */
    private function penalesConvertidos(array $game, $lado)
    {
        if (!isset($game[$lado]['actions']['shootout'])
            || !is_array($game[$lado]['actions']['shootout'])) return null;

        $n = 0;
        foreach ($game[$lado]['actions']['shootout'] as $t) {
            if (isset($t['action']) && $t['action'] === 'Scored') $n++;
        }
        return $n;
    }

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
                // OJO: Transfermarkt manda `0` cuando NO sabe el dorsal (pasa
                // seguido en el ascenso brasileño). Un 0 no es un dorsal: si se
                // lo toma como bueno, el primero de la lista se queda con el 0 y
                // todos los demás chocan contra él y caen en "repetido".
                $dorsal = $this->valor($p, self::$kDorsal);
                $out[] = [
                    'tm_id'  => $this->valor($p, self::$kJugador),
                    'dorsal' => ($dorsal === null || $dorsal === '' || (int) $dorsal === 0) ? null : (int) $dorsal,
                    'tipo'   => $tipo,
                    'orden'  => $orden,
                    'crudo'  => $p,
                ];
            }
        }
        return $out;
    }

    /**
     * Goles, tarjetas, cambios y penales fallados de un lado, normalizados a
     * una lista plana.
     *
     * `missedPenalties` está en el bloque del club que PATEÓ: `activePlayerId`
     * es el pateador y `passivePlayerId` el arquero del otro equipo. La rama
     * sólo aparece cuando hubo alguno, por eso el `awayClub` de un partido sin
     * penales fallados no la trae.
     */
    private function accionesDelLado(array $game, $clave)
    {
        $out = [];
        $acc = isset($game[$clave]['actions']) && is_array($game[$clave]['actions']) ? $game[$clave]['actions'] : [];

        foreach (['goals' => 'gol', 'cards' => 'tarjeta', 'substitutes' => 'cambio',
                  'missedPenalties' => 'penal'] as $rama => $clase) {
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
                } elseif ($clase === 'penal') {
                    // Los dos ids importan: el pateador y el arquero. El arquero
                    // puede faltar (una pelota afuera no la ataja nadie).
                    if ($activo === null) {
                        $this->aviso('Penal fallado sin id del pateador: ' . $this->resumenCrudo($a));
                        continue;
                    }
                    $out[] = ['clase' => 'penal', 'minuto' => $minuto, 'ids' => [$activo, $pasivo], 'crudo' => $a];
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
    /**
     * Las filas de `penals` de los penales que NO fueron gol.
     *
     * Los convertidos no pasan por acá: vienen como un gol de tipo Penal y su
     * fila —el arquero con «Convirtieron»— la crea `ControlPenales`, que es
     * quien sabe deducir el arquero. Acá TM nos dice quién fue: el pateador en
     * `activePlayerId` y el arquero en `passivePlayerId`.
     *
     * Una fila por protagonista, como se cargan a mano:
     *   · la tiró afuera → pateador «Errado»
     *   · se la atajaron → pateador «Atajado» + arquero «Atajó»
     */
    private function planPenales(array $game, Partido $partido, array $lados, array $mapa)
    {
        $filas = [];

        foreach ($lados as $i => $lado) {
            // El arquero es del otro equipo: `missedPenalties` está en el bloque
            // del club que pateó.
            $otroNombre = $lados[$i === 0 ? 1 : 0]['equipo_nombre'];

            foreach ($this->accionesDelLado($game, $lado['clave']) as $accion) {
                if ($accion['clase'] !== 'penal') continue;

                $pateadorTm = $accion['ids'][0];
                $arqueroTm  = isset($accion['ids'][1]) ? $accion['ids'][1] : null;

                $pateadorId = isset($mapa[(string) $pateadorTm]) ? $mapa[(string) $pateadorTm] : null;
                if (!$pateadorId) {
                    $this->aviso('Penal fallado de un jugador sin resolver (TM ' . $pateadorTm . ').');
                    continue;
                }

                $motivo = $this->motivoPenal($accion['crudo']);
                if ($motivo['dudoso']) {
                    $this->aviso('Penal del minuto ' . $accion['minuto'] . ': no reconozco «'
                        . $motivo['fuente'] . '». Lo cargo como Errado; si el arquero lo atajó, '
                        . 'corregilo a mano y avisá para sumar esa palabra al importador.');
                }

                // El pateador siempre lleva fila: Errado si la tiró afuera,
                // Atajado si el arquero la contuvo.
                $filas[] = [
                    'partido_id' => $partido->id,
                    'jugador_id' => $pateadorId,
                    'minuto'     => $accion['minuto'],
                    'tipo'       => $motivo['atajado'] ? self::PEN_ATAJADO : self::PEN_ERRADO,
                    '_nombre'    => $this->nombreJugador($pateadorId),
                    '_equipo'    => $lado['equipo_nombre'],
                    '_fuente'    => $motivo['fuente'],
                    '_dudoso'    => $motivo['dudoso'],
                ];

                // Y el arquero sólo cuando lo atajó: esa es SU acción. Si la
                // tiró afuera, el arquero no hizo nada y no lleva fila.
                if (!$motivo['atajado']) continue;

                $arqueroId = ($arqueroTm !== null && isset($mapa[(string) $arqueroTm]))
                    ? $mapa[(string) $arqueroTm] : null;
                if (!$arqueroId) {
                    $this->aviso('Penal atajado en el minuto ' . $accion['minuto'] . ': Transfermarkt no '
                        . 'dice qué arquero lo atajó (o no lo pude resolver), así que cargo sólo el '
                        . '«Atajado» del pateador. El «Atajó» del arquero ponelo a mano.');
                    continue;
                }

                $filas[] = [
                    'partido_id' => $partido->id,
                    'jugador_id' => $arqueroId,
                    'minuto'     => $accion['minuto'],
                    'tipo'       => self::PEN_ATAJO,
                    '_nombre'    => $this->nombreJugador($arqueroId),
                    '_equipo'    => $otroNombre,
                    '_fuente'    => $motivo['fuente'],
                    '_dudoso'    => false,
                ];
            }
        }

        return $filas;
    }

    /**
     * ¿El penal fallado se lo atajaron o lo tiró afuera?
     *
     * La diferencia importa porque cambia cuántas filas se cargan: un penal
     * atajado tiene dos protagonistas (el que pateó y el que atajó) y uno
     * tirado afuera tiene uno solo.
     *
     * Acá se mira `reason`, no `action`: en `missedPenalties` el `action` viene
     * "Not reported" y el dato real está en `reason` ("Saved", reasonId 501,
     * confirmado con Independiente Rivadavia–Racing, gameId 4889704).
     */
    private function motivoPenal(array $a)
    {
        $txt = mb_strtolower(trim($this->juntarTexto($a, self::$kMotivoPen)));

        foreach (['saved', 'gehalten', 'atajad', 'goalkeeper', 'keeper'] as $k) {
            if ($txt !== '' && mb_strpos($txt, $k) !== false) {
                return ['atajado' => true, 'fuente' => $txt, 'dudoso' => false];
            }
        }

        // Vocabulario conocido de "la tiró afuera": no hace falta avisar nada.
        foreach (['post', 'crossbar', 'bar', 'wide', 'over', 'missed', 'miss', 'out',
                  'blocked', 'pfosten', 'latte', 'vorbei', 'afuera', 'palo', 'travesa'] as $k) {
            if ($txt !== '' && mb_strpos($txt, $k) !== false) {
                return ['atajado' => false, 'fuente' => $txt, 'dudoso' => false];
            }
        }

        // Ni una cosa ni la otra: va como Errado —es lo más probable— pero
        // marcado, así el vocabulario nuevo se ve en pantalla y se amplía la
        // lista de arriba en vez de quedar cargado mal en silencio.
        return ['atajado' => false, 'fuente' => $txt !== '' ? $txt : 'sin detallar', 'dudoso' => true];
    }

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
        $lista = null; $claveLista = null;
        foreach (['referees', 'refereeDetails'] as $k) {
            if (isset($game[$k]) && is_array($game[$k])) { $lista = $game[$k]; $claveLista = $k; break; }
        }

        // Si algo no cierra —entradas sin id, sin rol, o de más— hay que ver el
        // crudo: la estructura de TM puede no ser la lista plana que asumimos.
        // Se guarda tal cual para poder mirarlo en la vista previa: el rol de
        // los árbitros ya nos hizo equivocar tres veces por inferir en vez de leer.
        $this->crudoArbitros = ['clave' => $claveLista, 'lista' => $lista,
            'refereeIds' => isset($game['refereeIds']) ? $game['refereeIds'] : null,
            'refereeId'  => isset($game['refereeId']) ? $game['refereeId'] : null];

        $rarezas = 0;
        if (is_array($lista)) {
            foreach ($lista as $r) {
                if (!is_array($r)) { $rarezas++; continue; }
                if ($this->valor($r, ['id', 'refereeId', 'personId']) === null) $rarezas++;
            }
            if ($rarezas > 0 || count($lista) > count(self::$rolesArbitro)) {
                $this->aviso('Estructura de árbitros rara en `' . $claveLista . '`: ' . count($lista)
                    . ' entradas, ' . $rarezas . ' sin id reconocible. Crudo: '
                    . $this->resumenCrudo($lista, 1500));
            }
        }

        $pares = [];
        if (is_array($lista)) {
            foreach ($lista as $r) {
                if (!is_array($r)) continue;
                $crudo = $this->juntarTexto($r, ['role', 'type', 'position', 'typeName', 'roleName', 'refereeType']);
                $pares[] = [$this->valor($r, ['id', 'refereeId', 'personId']), $this->rolArbitro($crudo), $crudo];
            }
        } elseif (isset($game['refereeIds']) && is_array($game['refereeIds'])) {
            // Forma B: objeto con una clave por rol. La clave ES el rol.
            $fuera = [];
            foreach (self::$clavesArbitroTm as $clave => $rol) {
                if (!array_key_exists($clave, $game['refereeIds'])) continue;
                $id = $game['refereeIds'][$clave];
                if ($id === null || $id === '' || $id === 0 || $id === '0') continue;
                if (is_array($id)) $id = $this->valor($id, ['id', 'refereeId']);
                if ($id === null || $id === '') continue;

                if (!in_array($rol, self::$rolesArbitro, true)) {
                    // Los ignorados a propósito no generan aviso.
                    if (!in_array($rol, self::$rolesArbitroIgnorados, true)) {
                        $fuera[] = $rol . ' (TM ' . $id . ')';
                    }
                    continue;
                }
                $pares[] = [$id, $rol, $clave];
            }

            // Claves que TM mandó y no conocemos: hay que mirarlas, no ignorarlas.
            foreach ($game['refereeIds'] as $clave => $id) {
                if (isset(self::$clavesArbitroTm[$clave])) continue;
                if ($id === null || $id === '' || $id === 0 || $id === '0') continue;
                $this->aviso('Transfermarkt mandó un árbitro en una clave que no conozco: `' . $clave
                    . '` = ' . (is_array($id) ? $this->resumenCrudo($id) : $id) . '. Pasámela y la mapeo.');
            }

            if (!empty($fuera)) {
                $this->aviso('No cargué ' . count($fuera) . ' juez(ces) porque el rol no existe en tu tabla '
                    . '`partido_arbitros` (que admite ' . implode(', ', self::$rolesArbitro) . '): '
                    . implode(' · ', $fuera) . '. Si los querés, hay que agregar ese valor al enum.');
            }
        }

        // A los que no se les reconoció el rol se les da el primer lugar libre
        // (Principal, Linea 1, Linea 2, Cuarto, VAR), que es el orden en que
        // Transfermarkt los lista. Mejor eso que meter cuatro 'Principal'.
        // Los roles son un enum de 5 y no se pueden repetir dentro del partido.
        // Se reparten en dos pasadas: primero los reconocidos (y si dos caen en
        // el mismo rol, el segundo se corre al siguiente libre), después los que
        // no se reconocieron, por orden de aparición.
        $usados = [];
        foreach ($pares as $i => $par) {
            if ($par[1] === null) continue;
            if (!isset($usados[$par[1]])) { $usados[$par[1]] = true; continue; }

            $original = $par[1];
            $pares[$i][1] = null;
            foreach (self::$rolesArbitro as $rol) {
                if (!isset($usados[$rol])) { $pares[$i][1] = $rol; $usados[$rol] = true; break; }
            }
            $this->aviso('Dos árbitros con el rol ' . $original . ' en este partido'
                . ($par[2] !== '' ? ' (TM dice "' . $par[2] . '")' : '') . '. '
                . ($pares[$i][1] !== null
                    ? 'Al segundo lo puse como ' . $pares[$i][1] . '.'
                    : 'No quedaba ningún rol libre, así que lo dejé afuera.'));
        }

        // Sin rol en el texto: se asigna por la POSICIÓN en la lista de TM,
        // usando $ordenArbitroTm. Sólo cuentan las entradas con id: las que no
        // lo tienen no son árbitros y correrían la numeración.
        $pos = 0; $porOrden = [];
        foreach ($pares as $i => $par) {
            if ($par[0] === null || $par[0] === '') continue;
            $esteLugar = $pos++;
            if ($par[1] !== null) continue;

            $rol = isset(self::$ordenArbitroTm[$esteLugar]) ? self::$ordenArbitroTm[$esteLugar] : null;
            if ($rol === null || isset($usados[$rol])) {
                foreach (self::$rolesArbitro as $libre) {
                    if (!isset($usados[$libre])) { $rol = $libre; break; }
                }
            }
            if ($rol === null || isset($usados[$rol])) continue;   // no quedan roles

            $pares[$i][1] = $rol;
            $pares[$i][3] = true;              // marcado: rol inferido, no informado
            $usados[$rol] = true;
            $porOrden[] = 'TM ' . $par[0] . ' → ' . $rol;
        }

        if (!empty($porOrden)) {
            $this->aviso('Transfermarkt no informó el rol de ' . count($porOrden) . ' árbitro(s). '
                . 'Los asigné por el orden en que vienen (' . implode(', ', self::$ordenArbitroTm) . '): '
                . implode(' · ', $porOrden) . '. Es una inferencia por posición: verificalos.');
        }

        $sinLugar = [];
        foreach ($pares as $par) {
            if ($par[1] === null) {
                $sinLugar[] = ($par[0] !== null && $par[0] !== '' ? 'TM ' . $par[0] : 'sin id');
            }
        }
        if (!empty($sinLugar)) {
            $this->aviso('Quedaron afuera ' . count($sinLugar) . ' árbitro(s), sin rol libre donde ponerlos: '
                . implode(' · ', $sinLugar) . '.');
        }

        $pares = array_values(array_filter($pares, function ($x) { return $x[1] !== null; }));

        // Los que todavía no conocemos: los buscamos por perfil, igual que a los
        // jugadores. Si la API no los devuelve, se saltean con un aviso.
        $faltan = [];
        foreach ($pares as $par) {
            if ($par[0] === null || $par[0] === '') continue;
            if (!isset($mapa[(string) $par[0]])) $faltan[] = (string) $par[0];
        }
        foreach (array_intersect_key($this->mapeosRotosArbitro, array_flip($faltan)) as $tmId => $fantasma) {
            $this->aviso('El mapeo del árbitro TM ' . $tmId . ' apuntaba al árbitro #' . $fantasma
                . ', que ya no existe: se lo llevó una fusión o un borrado. Lo apareo de nuevo desde el perfil.');
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
            $tmId = $par[0]; $rol = $par[1]; $crudoRol = $par[2];
            $inferido = !empty($par[3]);
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
                '_fuente'    => $crudoRol !== '' ? $crudoRol : ($inferido ? '(por orden en la lista)' : '(TM no mandó rol)'),
                '_dudoso'    => $inferido || $crudoRol === '',
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

            // No está como DT, pero la PERSONA puede existir ya con otro rol:
            // el DT que antes fue jugador es la misma fila de `personas`.
            // Ver personaExistenteSinRol(); sin esto Persona::create() choca
            // contra el índice único (nombre, apellido, nacimiento) y el
            // partido se queda sin DT.
            $libre = $this->personaExistenteSinRol($datos);

            if (!$escribir) {
                $ficticio = $this->proximoPreview--;
                $this->nombresPreview[$ficticio] = $datos['name'] . ($libre ? ' · DT (rol nuevo)' : ' · DT nuevo');
                $out[(string) $tmId] = $ficticio;
                if ($informe !== null) $informe['creados']['tecnicos'][] = $datos['apellido'] . ', ' . $datos['nombre']
                    . ' (TM ' . $tmId . ')' . ($libre
                        ? ' — YA EXISTE como persona #' . $libre['id'] . ' (' . $libre['base'] . '): se le AGREGARÍA el rol de DT'
                        : ' — SE CREARÍA');
                continue;
            }

            try {
                if ($libre) {
                    $persona = Persona::findOrFail($libre['id']);
                } else {
                    $foto = $this->descargarFoto($datos['portrait'], $datos['name']);
                    if ($foto) $datos['persona']['foto'] = $foto;
                    $persona = Persona::create($datos['persona']);
                }

                $tecnico = $persona->tecnico;
                if ($tecnico) {
                    $this->grabarUrlTecnico($tecnico->id, $tmId);
                } else {
                    $tecnico = $persona->tecnico()->create([
                        'transfermarkt_url' => 'https://www.transfermarkt.es/-/profil/trainer/' . $tmId,
                    ]);
                }
                $out[(string) $tmId] = (int) $tecnico->id;
                if ($this->mapaTecnicos !== null) $this->mapaTecnicos[(string) $tmId] = (int) $tecnico->id;
                if ($libre) {
                    $this->aviso('El DT ' . $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ') ya estaba '
                        . 'cargado como persona #' . $libre['id'] . ' (' . $libre['base'] . ', ' . $libre['como'] . '): '
                        . 'le agregué el rol de DT (#' . $tecnico->id . ') en vez de duplicarlo. Confirmalo.');
                }
                if ($informe !== null) $informe['creados']['tecnicos'][] = $datos['apellido'] . ', ' . $datos['nombre']
                    . ' (TM ' . $tmId . ')' . ($libre ? ' — rol de DT agregado a la persona #' . $libre['id'] : ' — creado')
                    . ' #' . $tecnico->id;
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
            // Los clubes no pasan por personaDesdePerfil: acá sólo interesa
            // ver el crudo, para saber qué campos hay (escudo, fundación,
            // estadio, país, socios) antes de escribir el importador.
            'equipo' => [
                'rutas'  => ['/clubs?' . $qs, '/club/' . urlencode($tmId)],
                'ramas'  => ['clubs'],
                'claves' => ['id', 'clubId'],
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

        // Un club no es una persona: el parser de personas no aplica.
        if (is_array($out['perfil']) && $tipo !== 'equipo') {
            $out['datos'] = $svc->personaDesdePerfil($out['perfil']);
        }
        $out['avisos'] = $svc->avisos;
        return $out;
    }

    /**
     * Diagnóstico exploratorio: buscar una ruta que devuelva la LISTA DE
     * PARTIDOS de una competencia. Es lo único que falta para cargar un torneo
     * en curso: con el `gameId` de cada partido, `importar()` hace el resto.
     *
     * No interesan tablas ni resultados: las tablas las arma el usuario con sus
     * propios partidos, y los resultados vienen con las incidencias.
     *
     * Dos familias de candidatas:
     *  a) TM llama `gameDay` a la fecha en su propio JSON (`gameDayCount`,
     *     `closestGameDay`), así que probamos ESA palabra — no "round" ni
     *     "matchday", que ya dieron 404.
     *  b) El único endpoint que sabemos que devuelve partidos es
     *     `/coach/{id}/performance-game`. Si el club tiene su equivalente,
     *     el fixture se arma club por club.
     *
     * Cada ruta es 1 crédito.
     */
    public static function diagnosticarCompetencia($compId, $seasonId = null, $ronda = null, $clubId = null)
    {
        $c = rawurlencode($compId);
        $s = ($seasonId !== null && $seasonId !== '') ? rawurlencode($seasonId) : null;
        $r = ($ronda !== null && $ronda !== '') ? rawurlencode($ronda) : null;
        $k = ($clubId !== null && $clubId !== '') ? rawurlencode($clubId) : null;

        $rutas = [];

        // (a) Competencia, con el vocabulario propio de TM.
        if ($c !== '') {
            $rutas[] = '/competition/' . $c . '/gamedays';
            $rutas[] = '/competition/' . $c . '/fixtures';
            $rutas[] = '/competition/' . $c . '/schedule';
            $rutas[] = '/competition/' . $c . '/clubs';
            if ($r !== null) {
                $rutas[] = '/competition/' . $c . '/gameday/' . $r;
                $rutas[] = '/competition/' . $c . '/games?gameDay=' . $r;
            }
            if ($s !== null && $r !== null) {
                $rutas[] = '/competition/' . $c . '/season/' . $s . '/gameday/' . $r;
                $rutas[] = '/competition/' . $c . '/games?seasonId=' . $s . '&gameDay=' . $r;
            }
        }

        // (b) Club: espejo de /coach/{id}/performance-game.
        if ($k !== null) {
            $rutas[] = '/club/' . $k . '/performance-game';
            $rutas[] = '/club/' . $k . '/games';
            $rutas[] = '/club/' . $k . '/fixtures';
            if ($s !== null) $rutas[] = '/club/' . $k . '/season/' . $s . '/games';
        }

        $out = ['rutas' => [], 'llamadas' => 0];

        foreach ($rutas as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $out['llamadas']++;

            $info = ['ok' => false, 'claves' => [], 'items' => 0, 'rama' => null, 'json' => $json];
            if (is_array($json) && !empty($json)) {
                $exito = !array_key_exists('success', $json) || !empty($json['success']);
                $data  = array_key_exists('data', $json) ? $json['data'] : $json;
                $info['ok'] = $exito && !empty($data);

                if (is_array($data)) {
                    $info['claves'] = array_slice(array_keys($data), 0, 30);
                    if (isset($data[0])) {
                        $info['items'] = count($data);
                        $info['rama'] = '(raíz)';
                    } else {
                        // La lista puede colgar de una rama: performance, games, etc.
                        foreach ($data as $clave => $valor) {
                            if (is_array($valor) && isset($valor[0])) {
                                $info['items'] = count($valor);
                                $info['rama'] = $clave;
                                break;
                            }
                        }
                    }
                }
            }
            $out['rutas'][$ruta] = $info;
        }

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
                        . ' con "' . $existente['base'] . '" (#' . $existente['id'] . '). Confirmalo en '
                        . '"Jugadores y árbitros por revisar" — si no es la misma persona, ahí está el botón '
                        . '"Está mal", que corta el apareo para que Rehacer lo cargue bien.');
                }
                continue;
            }

            // La persona puede existir sin el rol de árbitro (ver
            // personaExistenteSinRol): ahí le agregamos el rol en vez de
            // chocar contra el índice único de `personas`.
            $libre = $this->personaExistenteSinRol($datos);

            if (!$escribir) {
                $ficticio = $this->proximoPreview--;
                $this->nombresPreview[$ficticio] = $datos['name'] . ' · árbitro nuevo';
                $out[(string) $tmId] = $ficticio;
                $this->aviso($libre
                    ? 'El árbitro ' . $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ') ya existe como '
                        . 'persona #' . $libre['id'] . ' (' . $libre['base'] . '): se le agregaría el rol de árbitro.'
                    : 'Se crearía el árbitro ' . $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ').');
                continue;
            }

            try {
                if ($libre) {
                    $persona = Persona::findOrFail($libre['id']);
                } else {
                    $foto = $this->descargarFoto($datos['portrait'], $datos['name']);
                    if ($foto) $datos['persona']['foto'] = $foto;
                    $persona = Persona::create($datos['persona']);
                }

                $arbitro = $persona->arbitro ?: $persona->arbitro()->create([]);
                $this->guardarMapeoArbitro($tmId, $arbitro->id, $datos['name'], 'auto', true);
                $out[(string) $tmId] = (int) $arbitro->id;
                if ($libre) {
                    $this->aviso('El árbitro ' . $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ') ya estaba '
                        . 'cargado como persona #' . $libre['id'] . ' (' . $libre['base'] . ', ' . $libre['como'] . '): le agregué '
                        . 'el rol de árbitro (#' . $arbitro->id . ') en vez de duplicarlo. Confirmalo en "Jugadores y árbitros por revisar".');
                }
                if ($informe !== null) $informe['creados']['arbitros'][] = $datos['apellido'] . ', ' . $datos['nombre'] . ' (TM ' . $tmId . ')'
                    . ($libre ? ' — rol agregado a la persona #' . $libre['id'] : '');
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
     * OJO — acá NO alcanza con contar palabras en común (ago-2026):
     * en jugadores, las "2 o más palabras" van SIEMPRE atadas a la misma fecha
     * de nacimiento, y eso es lo que sostiene el apareo. Árbitros y DTs no
     * tienen fecha, así que dos palabras sueltas son casi siempre dos nombres
     * de pila: "Belatti, Juan Pablo" comparte juan+pablo con "González, Juan
     * Pablo" y quedaban aparejados como la misma persona.
     *
     * Entonces exigimos además que se toquen los APELLIDOS, en las dos
     * direcciones: alguna palabra del apellido de TM tiene que aparecer en el
     * nombre completo de la base, y alguna del apellido de la base en el
     * nombre completo de TM. Se compara contra el nombre COMPLETO —no campo
     * contra campo— porque cada fuente parte los apellidos compuestos en un
     * lugar distinto (TM "Venturino" / base "Biskupovic Venturino").
     *
     * @param string $tabla 'arbitros' o 'tecnicos'
     */
    private function buscarPersonaRol($tabla, array $datos)
    {
        $tokensTm = $this->tokensNombre($datos['apellido'] . ' ' . $datos['nombre']);
        if (count($tokensTm) < 2) return null;

        $apeTm = $this->tokensNombre($datos['apellido']);

        $cands = $this->candidatosPorNombre($tabla, $datos);

        $mejor = null; $puntaje = 0; $empatados = 0;
        foreach ($cands as $c) {
            if (!empty($datos['nacimiento']) && !empty($c->nacimiento)
                && substr((string) $c->nacimiento, 0, 10) !== $datos['nacimiento']) {
                continue;   // fechas distintas: no es él
            }

            $tokensBase = $this->tokensNombre($c->apellido . ' ' . $c->nombre);
            $apeBase    = $this->tokensNombre($c->apellido);

            if (!$this->apellidosSeTocan($apeTm, $tokensTm, $apeBase, $tokensBase)) continue;

            $p = count(array_intersect($tokensTm, $tokensBase));
            if ($p > $puntaje) { $puntaje = $p; $mejor = $c; $empatados = 1; }
            elseif ($p === $puntaje && $p > 0) { $empatados++; }
        }

        if (!$mejor || $puntaje < 2) return null;

        return ['id' => (int) $mejor->id, 'base' => trim($mejor->apellido . ', ' . $mejor->nombre),
            'revisar' => ($empatados > 1 || $puntaje < count($tokensTm))];
    }

    /**
     * ¿Los apellidos de las dos fichas se tocan? Ver buscarPersonaRol.
     *
     * Si de algún lado no sale apellido (TM a veces manda todo junto en el
     * nombre), no inventamos: pedimos que el nombre completo sea exactamente
     * el mismo conjunto de palabras.
     */
    private function apellidosSeTocan(array $apeTm, array $tokensTm, array $apeBase, array $tokensBase)
    {
        if (empty($apeTm) || empty($apeBase)) {
            $a = $tokensTm;   sort($a);
            $b = $tokensBase; sort($b);
            return $a === $b;
        }

        return count(array_intersect($apeTm, $tokensBase)) > 0
            && count(array_intersect($apeBase, $tokensTm)) > 0;
    }

    /**
     * ¿La PERSONA ya está en la base, aunque sin el rol que estamos cargando?
     *
     * Una persona = una fila de `personas` con uno o más roles (`jugadors`,
     * `tecnicos`, `arbitros`). buscarPersonaRol() mira SOLO la tabla del rol,
     * así que a un DT que ya estaba cargado como jugador no lo encontraba y se
     * intentaba crear la persona de nuevo, contra el índice único
     * (nombre, apellido, nacimiento). La base rechaza el INSERT con un 1062:
     *
     *   Duplicate entry 'Juan Luciano-Pajuelo Chávez-1974-09-23' (TM 62171)
     *
     * y el partido se quedaba sin DT. Acá buscamos la persona y después se le
     * AGREGA el rol que falta.
     *
     * El criterio es más estricto que en buscarPersonaRol() porque el candidato
     * puede ser cualquiera de las 30.000+ personas, y un apareo equivocado le
     * pega la carrera de uno a la ficha del otro:
     *
     *   1. La MISMA clave que rechaza la base (nombre + apellido + nacimiento).
     *      Si para la base son la misma fila, son la misma persona.
     *   2. Misma fecha de nacimiento + apellidos que se tocan + 2 o más
     *      palabras del nombre en común (el mismo listón de
     *      buscarJugadorExistente, que es seguro porque va atado a la fecha).
     *
     * SIN fecha de nacimiento no aparejamos nada: ahí el nombre solo no alcanza
     * (ver el caso Belatti/González) y, además, sin fecha el índice único ni
     * siquiera choca — crear la persona nueva no rompe nada y el duplicado se
     * ve después en /admin/verificarPersonas.
     *
     * @return array|null  ['id' => persona_id, 'base' => 'Apellido, Nombre', 'como' => motivo]
     */
    private function personaExistenteSinRol(array $datos)
    {
        if (empty($datos['nacimiento'])) return null;
        $nac = $datos['nacimiento'];

        $nombre   = isset($datos['persona']['nombre'])   ? trim((string) $datos['persona']['nombre'])   : '';
        $apellido = isset($datos['persona']['apellido']) ? trim((string) $datos['persona']['apellido']) : '';

        // 1) La clave exacta del índice único.
        if ($nombre !== '' && $apellido !== '') {
            $exacta = DB::table('personas')
                ->where('nombre', $nombre)
                ->where('apellido', $apellido)
                ->where('nacimiento', $nac)
                ->select('id', 'nombre', 'apellido')->first();
            if ($exacta) {
                return ['id' => (int) $exacta->id,
                    'base' => trim($exacta->apellido . ', ' . $exacta->nombre),
                    'como' => 'misma clave nombre + apellido + fecha de nacimiento'];
            }
        }

        // 2) Misma fecha + apellidos que se tocan + 2 palabras en común.
        $tokensTm = $this->tokensNombre($datos['apellido'] . ' ' . $datos['nombre']);
        if (count($tokensTm) < 2) return null;
        $apeTm = $this->tokensNombre($datos['apellido']);

        $cands = DB::table('personas')
            ->where('nacimiento', $nac)
            ->select('id', 'nombre', 'apellido')->limit(50)->get();

        $mejor = null; $puntaje = 0; $empatados = 0;
        foreach ($cands as $c) {
            $tokensBase = $this->tokensNombre($c->apellido . ' ' . $c->nombre);
            $apeBase    = $this->tokensNombre($c->apellido);
            if (!$this->apellidosSeTocan($apeTm, $tokensTm, $apeBase, $tokensBase)) continue;

            $p = count(array_intersect($tokensTm, $tokensBase));
            if ($p > $puntaje) { $puntaje = $p; $mejor = $c; $empatados = 1; }
            elseif ($p === $puntaje && $p > 0) { $empatados++; }
        }

        if (!$mejor || $puntaje < 2) return null;

        if ($empatados > 1) {
            $this->aviso('Hay ' . $empatados . ' personas nacidas el ' . $nac . ' que se parecen a '
                . $datos['apellido'] . ', ' . $datos['nombre'] . '. Uso la #' . $mejor->id . ' pero revisalo.');
        }

        return ['id' => (int) $mejor->id,
            'base' => trim($mejor->apellido . ', ' . $mejor->nombre),
            'como' => 'misma fecha de nacimiento y ' . $puntaje . ' palabras del nombre en común'];
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

    /**
     * Rol del árbitro. Devuelve un valor del enum de `partido_arbitros`:
     * 'Principal', 'Linea 1', 'Linea 2', 'Cuarto', 'VAR'  — SIN acento, y no
     * existe 'Desconocido'. Cualquier otra cosa la rechaza la base.
     *
     * Devuelve null si no reconoce el texto: ahí `planArbitros` asigna por
     * posición en vez de inventar un rol. Antes caía todo a 'Principal' y
     * podías terminar con cuatro principales en el mismo partido.
     */
    private function rolArbitro($txt)
    {
        $t = mb_strtolower(trim((string) $txt));
        if ($t === '') return null;

        // VAR primero: "video assistant referee" contiene "assistant".
        if (mb_strpos($t, 'var') !== false || mb_strpos($t, 'video') !== false) return 'VAR';

        if (mb_strpos($t, 'fourth') !== false || mb_strpos($t, '4th') !== false
            || mb_strpos($t, 'cuarto') !== false || mb_strpos($t, 'vierter') !== false) return 'Cuarto';

        if (mb_strpos($t, 'assistant') !== false || mb_strpos($t, 'asistente') !== false
            || mb_strpos($t, 'linesman') !== false || mb_strpos($t, 'línea') !== false
            || mb_strpos($t, 'linea') !== false || mb_strpos($t, 'assistent') !== false) {
            if (mb_strpos($t, '2') !== false || mb_strpos($t, 'second') !== false
                || mb_strpos($t, 'segundo') !== false || mb_strpos($t, 'zweiter') !== false) return 'Linea 2';
            return 'Linea 1';
        }

        if (mb_strpos($t, 'referee') !== false || mb_strpos($t, 'árbitro') !== false
            || mb_strpos($t, 'arbitro') !== false || mb_strpos($t, 'main') !== false
            || mb_strpos($t, 'principal') !== false || mb_strpos($t, 'schiedsrichter') !== false) {
            return 'Principal';
        }

        return null;
    }

    /** Los roles válidos de `partido_arbitros.tipo`. */
    private static $rolesArbitro = ['Principal', 'Linea 1', 'Linea 2', 'Cuarto', 'VAR'];

    /**
     * `refereeIds` de tmapi NO es una lista: es un objeto donde CADA CLAVE dice
     * el rol. Confirmado en Aldosivi-Unión (fecha 6, Clausura 2026):
     *
     *   refereeId 54272 (Amiconi) · secondRefereeAssistantId 69495 (Viglietti)
     *   firstVideoAssistantId 21416 · secondVideoAssistantId 65524
     *
     * y coincide con livefutbol: Amiconi principal, Viglietti asistente 2.
     * Los que TM no tiene vienen en null (acá faltaba el asistente 1, Castelli).
     *
     * OJO: iterar esto con foreach sobre los VALORES tira las claves, que son
     * el dato. Fue exactamente el bug que puso a Viglietti como VAR.
     *
     * 'AVAR' y los jueces de gol no existen en el enum de `partido_arbitros`.
     */
    private static $clavesArbitroTm = [
        'refereeId'                => 'Principal',
        'firstRefereeAssistantId'  => 'Linea 1',
        'secondRefereeAssistantId' => 'Linea 2',
        'fourthOfficialId'         => 'Cuarto',
        'firstVideoAssistantId'    => 'VAR',
        'secondVideoAssistantId'   => 'AVAR',
        'firstGoalJudgeId'         => 'Juez de gol 1',
        'secondGoalJudgeId'        => 'Juez de gol 2',
    ];

    /**
     * Roles que Transfermarkt manda, no entran en el enum, y encima se quieren
     * silenciar. Vacío a propósito: el usuario prefiere que le avise siempre
     * (ago-2026), aunque el AVAR no lo vaya a cargar — sirve para saber que el
     * dato existía. Si algún aviso se vuelve puro ruido, sumar el rol acá.
     */
    private static $rolesArbitroIgnorados = [];

    /**
     * Orden en que Transfermarkt lista la terna cuando NO manda el rol.
     * Observado en Aldosivi-Unión (fecha 6, Clausura 2026) y confirmado contra
     * livefutbol: Amiconi principal, Viglietti asistente 2 — cae en la posición
     * 4 con este orden, no con el del enum.
     *
     * Es una inferencia por posición, no un dato: si TM omite a alguien o
     * cambia el orden, se corre todo. Por eso lo cargado así va marcado como
     * dudoso y con aviso.
     */
    private static $ordenArbitroTm = ['Principal', 'VAR', 'Linea 1', 'Linea 2', 'Cuarto'];

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
            // La persona puede estar cargada con otro rol (un DT o un árbitro
            // que también jugó). Ver personaExistenteSinRol().
            $libre = $this->personaExistenteSinRol($datos);
            if ($libre) {
                $persona = Persona::findOrFail($libre['id']);
            } else {
                $foto = $this->descargarFoto($datos['portrait'], $etiqueta);
                if ($foto) $datos['persona']['foto'] = $foto;
                $persona = Persona::create($datos['persona']);
            }

            $jugador = $persona->jugador ?: $persona->jugador()->create($datos['jugador']);
            $this->guardarMapeoJugador($tmId, $jugador->id, $datos['name'], 'auto', true);
            if ($libre) {
                $this->aviso($etiqueta . ' ya estaba cargado como persona #' . $libre['id'] . ' (' . $libre['base'] . ', '
                    . $libre['como'] . '): le agregué el rol de jugador (#' . $jugador->id . ') en vez de duplicarlo. '
                    . 'Confirmalo en "jugadores por revisar".');
            }
            return ['jugador_id' => (int) $jugador->id, 'creado' => true, 'descripcion' => $etiqueta
                . ($libre ? ' — rol de jugador agregado a la persona #' . $libre['id'] : ' — creado') . ' #' . $jugador->id];
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
    /**
     * Graba los penales fallados sin repetir los que ya estén.
     *
     * A diferencia de goles o tarjetas, `penals` puede tener filas puestas a
     * mano y filas creadas por ControlPenales, así que no se borra todo y se
     * reescribe: se inserta sólo lo que no está. La identidad de una fila es
     * partido + jugador + minuto + tipo (`<=>` porque el minuto puede ser NULL).
     */
    private function grabarPenales(array $filas)
    {
        if (empty($filas)) return;

        $nuevas = [];
        foreach ($filas as $r) {
            $f = $this->limpiar($r);
            $ya = DB::table('penals')
                ->where('partido_id', $f['partido_id'])
                ->where('jugador_id', $f['jugador_id'])
                ->where('tipo', $f['tipo'])
                ->whereRaw('minuto <=> ?', [$f['minuto']])
                ->exists();
            if ($ya) continue;
            $nuevas[] = $r;
        }

        $this->grabarFilas(Penal::class, $nuevas, 'un penal');
    }

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
            ->where('plantilla_jugadors.dorsal', '!=', 0)
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

        // Última red: un jugador_id que ya no existe no puede quedarse con el
        // dorsal de nadie. El INSERT iba a fallar igual por foreign key, pero
        // para entonces al jugador de verdad ya le habíamos borrado el número,
        // y ese dato no vuelve solo. Se verifica antes de tocar nada.
        $pedidos = [];
        foreach ($filas as $f) {
            if (!empty($f['jugador_id']) && (int) $f['jugador_id'] > 0) $pedidos[(int) $f['jugador_id']] = true;
        }
        $vivos = [];
        if ($pedidos) {
            foreach (DB::table('jugadors')->whereIn('id', array_keys($pedidos))->pluck('id') as $id) {
                $vivos[(int) $id] = true;
            }
        }

        $plantillas = [];   // "torneo-equipo" => plantilla_id
        foreach ($filas as $f) {
            if (!isset($vivos[(int) $f['jugador_id']])) {
                $this->fallidas++;
                $this->aviso('No sumé a la plantilla a "' . $f['_nombre'] . '": el jugador #' . (int) $f['jugador_id']
                    . ' no existe en la base (mapeo viejo de jugador_tm). No le toqué el dorsal a nadie.');
                continue;
            }
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

                // El dorsal es único por plantilla (índice `plantilla_id_dorsal`).
                // Si ya lo tiene OTRO jugador, gana el que dice Transfermarkt:
                // al que estaba se le borra el número —sigue en la plantilla,
                // solo pierde el dorsal— y se avisa para que lo revises.
                if ($f['dorsal'] !== null && $f['dorsal'] !== '' && (int) $f['dorsal'] !== 0) {
                    $ocupa = \App\PlantillaJugador::where('plantilla_id', $plantillas[$clave])
                        ->where('dorsal', $f['dorsal'])
                        ->where('jugador_id', '!=', $f['jugador_id'])->first();
                    if ($ocupa) {
                        $ocupa->update(['dorsal' => null]);
                        // El token [[plantilla:N]] lo convierte en link la pantalla
                        // que muestra los avisos, DESPUÉS de escapar el texto.
                        $this->aviso('Dorsal ' . $f['dorsal'] . ' en ' . $f['_equipo'] . ': se lo saqué a "'
                            . $this->nombreJugador($ocupa->jugador_id) . '" y se lo di a "' . $f['_nombre']
                            . '", que es lo que dice Transfermarkt. El otro quedó sin dorsal: revisalo. '
                            . '[[plantilla:' . (int) $plantillas[$clave] . ']]');
                    }
                }

                $pj = \App\PlantillaJugador::where('plantilla_id', $plantillas[$clave])
                    ->where('jugador_id', $f['jugador_id'])->first();

                if (!$pj) {
                    \App\PlantillaJugador::create([
                        'plantilla_id' => $plantillas[$clave],
                        'jugador_id'   => $f['jugador_id'],
                        'dorsal'       => $f['dorsal'],
                    ]);
                } elseif (($pj->dorsal === null || $pj->dorsal === '')
                    && $f['dorsal'] !== null && (int) $f['dorsal'] !== 0) {
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
        if (!$this->esHispana($nacionalidad)) return $n;

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

    /**
     * Nacionalidades donde la costumbre son DOS apellidos. La usan las dos
     * reglas de reparto de nombre de acá abajo.
     */
    private function esHispana($nacionalidad)
    {
        static $dosApellidos = ['Argentina', 'Bolivia', 'Chile', 'Colombia', 'Costa Rica', 'Cuba',
            'Ecuador', 'El Salvador', 'España', 'Guatemala', 'Guinea Ecuatorial', 'Honduras',
            'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Puerto Rico',
            'República Dominicana', 'Uruguay', 'Venezuela'];

        return $nacionalidad !== null && in_array($nacionalidad, $dosApellidos, true);
    }

    /**
     * Segundo apellido cuando Transfermarkt no manda NINGÚN ancla.
     *
     * El JSON de árbitros (`/referees`) viene pelado: sólo `name`, sin
     * shortName, sin passportName y sin displayName (confirmado con el
     * diagnóstico del árbitro 28495, ago-2026). Sin ancla,
     * `NombreHelper::separarTM` cae al fallback "la última palabra es el
     * apellido", y TODO árbitro con apellido doble entra partido mal:
     *
     *   "Yael Falcón Pérez"  ->  nombre "Yael Falcón" / apellido "Pérez"   (mal)
     *                        ->  nombre "Yael"        / apellido "Falcón Pérez"
     *
     * Por la FORMA no se puede decidir: "Yael | Falcón Pérez" y "Jorge Daniel |
     * Baliño" son las dos "tres palabras, argentino". Cualquier regla del tipo
     * "las dos últimas son apellidos" arregla al primero y rompe al segundo.
     *
     * Entonces no inventamos la regla: le preguntamos a la base, que ya tiene
     * miles de personas cargadas a mano. Ver `tokenEsApellido()`.
     *
     * Sólo corre cuando el perfil NO trajo shortName. Si TM mandó ancla, esa
     * manda: no le discutimos a un dato por una estadística.
     */
    private function apellidoDobleSinAncla(array $n, $nacionalidad, array $perfil)
    {
        if (!empty($perfil['shortName'])) return $n;
        if (!$this->esHispana($nacionalidad)) return $n;

        $nombres   = array_values(array_filter(preg_split('/\s+/', trim($n['nombre'])), 'strlen'));
        $apellidos = array_values(array_filter(preg_split('/\s+/', trim($n['apellido'])), 'strlen'));

        // Con tres o más nombres ya trabajó rescatarPrimerApellido. El caso que
        // queda es el de tres palabras: dos "nombres" y un apellido.
        if (count($nombres) !== 2 || count($apellidos) !== 1) return $n;

        $palabra   = end($nombres);
        $veredicto = $this->tokenEsApellido($palabra);

        if ($veredicto === true) {
            array_pop($nombres);
            $n['nombre']   = implode(' ', $nombres);
            $n['apellido'] = $palabra . ' ' . $n['apellido'];
            return $n;
        }

        if ($veredicto === null) {
            $this->aviso('No sé cómo se parte "' . trim($n['name']) . '": Transfermarkt no manda el apellido '
                . 'aparte y "' . $palabra . '" no aparece claro en la base ni como nombre ni como apellido. '
                . 'Quedó como "' . trim($n['apellido']) . ', ' . trim($n['nombre']) . '" — si está mal, '
                . 'corregilo en la ficha.');
        }

        return $n;
    }

    /**
     * ¿"falcón" es apellido o nombre de pila? Lo decide el índice invertido
     * `persona_tokens` (el que arma `DuplicadosPersonas`), contando cuántas
     * personas distintas lo tienen en cada campo: 'a' apellido, 'n' nombre.
     *
     *   true  = es apellido
     *   false = es nombre de pila
     *   null  = no sé (o la tabla no está)
     *
     * Se exige una diferencia clara —el doble y al menos 3 fichas— porque hay
     * palabras que son las dos cosas ("Martín", "Nicolás"). Ante la duda
     * devuelve null y el nombre queda como vino: un apellido mal partido se
     * corrige en dos clics, uno mal "corregido" no se nota nunca.
     *
     * OJO: `tokensDe()` indexa los nombres TAMBIÉN como apellido cuando la
     * ficha tiene el apellido vacío, así que hay algo de ruido en el campo 'a'.
     * Por eso el umbral pide el doble y no un simple "más que".
     */
    private function tokenEsApellido($palabra)
    {
        static $cache = [];

        $tokens = DuplicadosPersonas::tokenizar($palabra);
        $tok    = $tokens ? $tokens[0] : '';
        if ($tok === '') return null;
        if (array_key_exists($tok, $cache)) return $cache[$tok];

        try {
            if (!Schema::hasTable('persona_tokens')) return $cache[$tok] = null;

            $conteo = DB::table('persona_tokens')
                ->select('campo', DB::raw('COUNT(DISTINCT persona_id) as n'))
                ->where('token', $tok)
                ->groupBy('campo')
                ->pluck('n', 'campo')
                ->all();
        } catch (\Exception $e) {
            return $cache[$tok] = null;
        }

        $comoApellido = isset($conteo['a']) ? (int) $conteo['a'] : 0;
        $comoNombre   = isset($conteo['n']) ? (int) $conteo['n'] : 0;

        if ($comoApellido >= 3 && $comoApellido >= 2 * $comoNombre) return $cache[$tok] = true;
        if ($comoNombre   >= 3 && $comoNombre   >= 2 * $comoApellido) return $cache[$tok] = false;

        return $cache[$tok] = null;
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
        $n = $this->apellidoDobleSinAncla($n, $nacionalidad, $p);

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

    /**
     * tm_player_id -> jugador_id, SÓLO con las fichas que todavía existen.
     *
     * `jugador_tm` no tiene foreign key contra `jugadors`, y hasta ago-2026 ni
     * la fusión de personas ni el borrado de huérfanas la repuntaban: al
     * unificar dos fichas, el mapeo seguía apuntando a la que se borró. Ese id
     * fantasma después viajaba entero hasta el INSERT y reventaba con el 1452
     * de MySQL —pero recién DESPUÉS de haberle sacado el dorsal al jugador de
     * verdad, que es como se perdió el 16 de Atenas de San Carlos—.
     *
     * Un mapeo que apunta a la nada no es un dato, es basura: se saltea y el
     * tm_id vuelve a la bolsa de "sin mapear", para que resolverJugador() lo
     * aparee de nuevo y de paso deje la fila apuntando bien.
     */
    private function mapaJugadores()
    {
        if ($this->mapaJugadores !== null) return $this->mapaJugadores;
        $mapa = [];
        $rotos = [];
        $filas = DB::table('jugador_tm')
            ->leftJoin('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
            ->select('jugador_tm.tm_player_id', 'jugador_tm.jugador_id', 'jugadors.id as vive')
            ->get();
        foreach ($filas as $r) {
            if ($r->vive === null) {
                $rotos[(string) $r->tm_player_id] = (int) $r->jugador_id;
                continue;
            }
            $mapa[(string) $r->tm_player_id] = (int) $r->jugador_id;
        }
        $this->mapeosRotosJugador = $rotos;
        return $this->mapaJugadores = $mapa;
    }

    /**
     * Los mapeos rotos que hay hoy en la base, sin filtrar por partido. Lo usa
     * la pantalla de reparación (`import_detalles.mapeos`), que los borra: la
     * próxima bajada los vuelve a crear apuntando a la ficha correcta.
     *
     * @return array ['jugador' => [...], 'arbitro' => [...]]
     */
    public static function mapeosRotos()
    {
        $out = ['jugador' => [], 'arbitro' => []];

        if (Schema::hasTable('jugador_tm')) {
            $out['jugador'] = DB::table('jugador_tm')
                ->leftJoin('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
                ->whereNull('jugadors.id')
                ->select('jugador_tm.id', 'jugador_tm.tm_player_id as tm_id', 'jugador_tm.jugador_id as ficha_id',
                    'jugador_tm.nombre_tm', 'jugador_tm.origen', 'jugador_tm.created_at')
                ->orderBy('jugador_tm.id')->get()->all();
        }

        if (Schema::hasTable('arbitro_tm')) {
            $out['arbitro'] = DB::table('arbitro_tm')
                ->leftJoin('arbitros', 'arbitros.id', '=', 'arbitro_tm.arbitro_id')
                ->whereNull('arbitros.id')
                ->select('arbitro_tm.id', 'arbitro_tm.tm_referee_id as tm_id', 'arbitro_tm.arbitro_id as ficha_id',
                    'arbitro_tm.nombre_tm', 'arbitro_tm.origen', 'arbitro_tm.created_at')
                ->orderBy('arbitro_tm.id')->get()->all();
        }

        return $out;
    }

    /**
     * Cuántos mapeos rotos hay, sin traerlos. El index del importador lo pinta
     * en cada carga y `jugador_tm` va camino a las decenas de miles de filas:
     * un COUNT por tabla, no la lista entera.
     */
    public static function contarMapeosRotos()
    {
        $n = 0;

        if (Schema::hasTable('jugador_tm')) {
            $n += DB::table('jugador_tm')
                ->leftJoin('jugadors', 'jugadors.id', '=', 'jugador_tm.jugador_id')
                ->whereNull('jugadors.id')->count();
        }

        if (Schema::hasTable('arbitro_tm')) {
            $n += DB::table('arbitro_tm')
                ->leftJoin('arbitros', 'arbitros.id', '=', 'arbitro_tm.arbitro_id')
                ->whereNull('arbitros.id')->count();
        }

        return $n;
    }

    /** Borra las filas de mapeo que apuntan a fichas inexistentes. */
    public static function limpiarMapeosRotos()
    {
        $rotos = self::mapeosRotos();
        $out = ['jugador' => 0, 'arbitro' => 0];

        foreach (['jugador' => 'jugador_tm', 'arbitro' => 'arbitro_tm'] as $rol => $tabla) {
            $ids = [];
            foreach ($rotos[$rol] as $r) $ids[] = (int) $r->id;
            if ($ids) $out[$rol] = DB::table($tabla)->whereIn('id', $ids)->delete();
        }

        return $out;
    }

    /** Igual que mapaJugadores(): un arbitro_id que ya no existe no es un mapeo. Ver allá el porqué. */
    private function mapaArbitros()
    {
        if ($this->mapaArbitros !== null) return $this->mapaArbitros;
        $mapa = [];
        $rotos = [];
        $filas = DB::table('arbitro_tm')
            ->leftJoin('arbitros', 'arbitros.id', '=', 'arbitro_tm.arbitro_id')
            ->select('arbitro_tm.tm_referee_id', 'arbitro_tm.arbitro_id', 'arbitros.id as vive')
            ->get();
        foreach ($filas as $r) {
            if ($r->vive === null) {
                $rotos[(string) $r->tm_referee_id] = (int) $r->arbitro_id;
                continue;
            }
            $mapa[(string) $r->tm_referee_id] = (int) $r->arbitro_id;
        }
        $this->mapeosRotosArbitro = $rotos;
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
     * Expresión SQL que saca el `NNN` de `.../spieler/NNN` (aguanta que después
     * venga `/saison/2020` o un `?query=`). Sirve para cruzar
     * `jugadors.transfermarkt_url` contra `jugador_tm.tm_player_id` sin traerse
     * las dos tablas enteras a PHP.
     */
    const SQL_TM_ID_DE_URL = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(j.transfermarkt_url, '/spieler/', -1), '/', 1), '?', 1)";

    /**
     * Los dos números que mira el index del importador:
     *   pendientes = URLs de Transfermarkt cuyo id todavía NO tiene fila en
     *                `jugador_tm`. Es lo único que la siembra puede crear.
     *   conflictos = URLs cuyo id YA está en `jugador_tm` pero atado a otra
     *                ficha que también existe. La siembra no las toca (pisar el
     *                mapeo sería peor): casi siempre es la misma persona
     *                cargada dos veces.
     *
     * OJO con la cuenta vieja: hasta ago-2026 el index calculaba los pendientes
     * como `URLs - filas con origen='url'`. Esa resta miente, porque el mapeo lo
     * crea también el importador con `origen='api'`. Resultado: jugadores ya
     * atados seguían contándose como pendientes, el cartel rojo no se iba nunca
     * y la siembra contestaba "0 nuevos / N ya estaban" por más veces que la
     * apretaras. Cualquier cuenta nueva se hace contra la tabla, no por origen.
     */
    public static function estadoSiembra()
    {
        if (!Schema::hasTable('jugador_tm')) return ['pendientes' => 0, 'conflictos' => 0];

        $base = function () {
            return DB::table('jugadors as j')
                ->leftJoin('jugador_tm as t', function ($join) {
                    $join->on('t.tm_player_id', '=', DB::raw(self::SQL_TM_ID_DE_URL));
                })
                ->whereNotNull('j.transfermarkt_url')
                ->where('j.transfermarkt_url', 'like', '%/spieler/%');
        };

        return [
            'pendientes' => $base()->whereNull('t.id')->count(),
            'conflictos' => $base()->whereNotNull('t.id')
                ->join('jugadors as v', 'v.id', '=', 't.jugador_id')
                ->whereColumn('t.jugador_id', '!=', 'j.id')->count(),
        ];
    }

    /**
     * Siembra `jugador_tm` con los jugadores que ya tenías cargados y tienen
     * la URL de Transfermarkt. Es lo que evita que el importador cree de nuevo
     * a alguien que ya está en la base.
     *
     * Cuatro finales por jugador, y los cuatro se informan:
     *   creados     = no había mapeo para ese id de TM: se crea.
     *   ya_estaban  = ya estaba atado a esta misma ficha. No hay nada que hacer.
     *   repuntados  = el mapeo apuntaba a una ficha que ya no existe (se la
     *                 llevó una fusión o el borrado de huérfanas): se lo repunta
     *                 al jugador que tiene la URL, en vez de dejar basura.
     *   conflictos  = el mapeo apunta a OTRA ficha que sí existe. No se toca: si
     *                 lo pisáramos, los partidos ya cargados con ese id quedarían
     *                 colgados de la ficha equivocada. Se listan para que los
     *                 mires a mano — suelen ser personas duplicadas.
     */
    public static function sembrarDesdeUrls()
    {
        $creados = 0; $yaEstaban = 0; $sinId = 0; $repuntados = 0;
        $conflictos = []; $nConflictos = 0;

        // tm_player_id -> ['fila' => id de jugador_tm, 'jugador' => a quién apunta]
        $existentes = [];
        foreach (DB::table('jugador_tm')->select('id', 'tm_player_id', 'jugador_id')->get() as $r) {
            $existentes[(string) $r->tm_player_id] = ['fila' => (int) $r->id, 'jugador' => (int) $r->jugador_id];
        }

        // Fichas que todavía existen: `jugador_tm` no tiene foreign key, así que
        // un mapeo puede estar apuntando a un id que ya no está en la base.
        $vivos = [];
        foreach (DB::table('jugadors')->select('id')->get() as $r) $vivos[(int) $r->id] = true;

        DB::table('jugadors')
            ->whereNotNull('transfermarkt_url')->where('transfermarkt_url', '!=', '')
            ->select('id', 'transfermarkt_url')
            ->orderBy('id')
            ->chunk(500, function ($filas) use (&$creados, &$yaEstaban, &$sinId, &$repuntados,
                &$conflictos, &$nConflictos, &$existentes, &$vivos) {
                $insert = [];
                foreach ($filas as $f) {
                    if (!preg_match('#/spieler/(\d+)#', (string) $f->transfermarkt_url, $m)) { $sinId++; continue; }
                    $tm = $m[1];
                    $ficha = (int) $f->id;

                    if (!isset($existentes[$tm])) {
                        $existentes[$tm] = ['fila' => 0, 'jugador' => $ficha];
                        $insert[] = ['tm_player_id' => $tm, 'jugador_id' => $ficha, 'nombre_tm' => null,
                            'origen' => 'url', 'revisar' => 0, 'created_at' => now(), 'updated_at' => now()];
                        $creados++;
                        continue;
                    }

                    $atado = $existentes[$tm]['jugador'];
                    if ($atado === $ficha) { $yaEstaban++; continue; }

                    if (!isset($vivos[$atado])) {
                        DB::table('jugador_tm')->where('id', $existentes[$tm]['fila'])
                            ->update(['jugador_id' => $ficha, 'origen' => 'url', 'updated_at' => now()]);
                        $existentes[$tm]['jugador'] = $ficha;
                        $repuntados++;
                        continue;
                    }

                    $nConflictos++;
                    if (count($conflictos) < 300) {
                        $conflictos[] = ['tm' => $tm, 'ficha_url' => $ficha, 'ficha_mapeo' => $atado];
                    }
                }
                if (!empty($insert)) DB::table('jugador_tm')->insert($insert);
            });

        return ['creados' => $creados, 'ya_estaban' => $yaEstaban, 'sin_id' => $sinId,
            'repuntados' => $repuntados, 'n_conflictos' => $nConflictos, 'conflictos' => $conflictos];
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

    private function resumenCrudo($a, $largo = 200)
    {
        return mb_substr(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, $largo);
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
