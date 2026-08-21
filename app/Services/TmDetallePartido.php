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

    /** Claves candidatas: la API cambió de nombres más de una vez, probamos todas. */
    private static $kJugador   = ['playerId', 'player_id', 'id', 'scorerId', 'goalScorerId', 'playerID'];
    private static $kMinuto    = ['minute', 'minuto', 'min', 'time', 'gameMinute'];
    private static $kDorsal    = ['shirtNumber', 'shirtNo', 'jerseyNumber', 'number', 'dorsal', 'squadNumber'];
    private static $kEntra     = ['playerInId', 'playerOnId', 'substituteInId', 'inId', 'playerIn', 'substituteId'];
    private static $kSale      = ['playerOutId', 'playerOffId', 'substituteOutId', 'outId', 'playerOut', 'replacedPlayerId'];
    private static $kDescGol   = ['action', 'reason', 'goalType', 'type', 'typeName', 'description', 'actionName'];
    private static $kDescCard  = ['cardType', 'type', 'action', 'reason', 'typeName', 'description'];

    /** Avisos y datos sin reconocer que junta la corrida (se muestran en pantalla). */
    private $avisos = [];
    /** Cache de mapeos para no consultar de más dentro de una tanda. */
    private $mapaJugadores = null;
    private $mapaArbitros  = null;
    private $mapaEquipos   = null;
    /** En la vista previa los jugadores que se crearían llevan un id negativo. */
    private $nombresPreview = [];
    private $proximoPreview = -1;
    /** tm_club_id -> equipo_id sacado de la fila de import_partidos del partido. */
    private $mapaStaging = [];

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

        $this->avisos = [];

        $informe = [
            'ok'          => false,
            'escrito'     => false,
            'error'       => null,
            'partido_id'  => (int) $partidoId,
            'game_id'     => (string) $gameId,
            'avisos'      => [],
            'plan'        => ['alineacions' => [], 'gols' => [], 'tarjetas' => [], 'cambios' => [], 'arbitros' => []],
            'creados'     => ['jugadores' => [], 'arbitros' => []],
            'llamadas'    => 0,
            'crudo'       => null,
        ];

        $partido = Partido::find($partidoId);
        if (!$partido) {
            $informe['error'] = 'No existe el partido #' . (int) $partidoId . '.';
            return $informe;
        }

        if (!$forzar && Alineacion::where('partido_id', $partido->id)->exists()) {
            $informe['error'] = 'El partido #' . $partido->id . ' ya tiene alineación cargada. '
                . 'Si querés reemplazarla, pedilo con forzar=1.';
            return $informe;
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
        $plan = ['alineacions' => [], 'gols' => [], 'tarjetas' => [], 'cambios' => [], 'arbitros' => []];

        foreach ($lados as $lado) {
            $equipoId = $lado['equipo_id'];

            // Alineación: titulares primero, después el banco.
            foreach ($this->jugadoresDelLado($game, $lado['clave']) as $p) {
                $jugadorId = isset($mapa[(string) $p['tm_id']]) ? $mapa[(string) $p['tm_id']] : null;
                if (!$jugadorId) {
                    $this->aviso('Alineación: jugador TM ' . $p['tm_id'] . ' sin resolver, lo salteo.');
                    continue;
                }
                $plan['alineacions'][] = [
                    'partido_id' => $partido->id,
                    'jugador_id' => $jugadorId,
                    'equipo_id'  => $equipoId,
                    'dorsal'     => $p['dorsal'],
                    'tipo'       => $p['tipo'],
                    'orden'      => $p['orden'],
                    '_nombre'    => $this->nombreJugador($jugadorId),
                    '_equipo'    => $lado['equipo_nombre'],
                ];
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
                        '_equipo'    => $lado['equipo_nombre'],
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
                    $sale  = isset($mapa[(string) $accion['ids'][0]]) ? $mapa[(string) $accion['ids'][0]] : null;
                    $entra = isset($mapa[(string) $accion['ids'][1]]) ? $mapa[(string) $accion['ids'][1]] : null;
                    foreach ([['Sale', $sale], ['Entra', $entra]] as $par) {
                        if (!$par[1]) { $this->aviso('Cambio con un jugador sin resolver, salteo el lado "' . $par[0] . '".'); continue; }
                        $plan['cambios'][] = [
                            'partido_id' => $partido->id,
                            'jugador_id' => $par[1],
                            'minuto'     => $accion['minuto'],
                            'tipo'       => $par[0],
                            '_nombre'    => $this->nombreJugador($par[1]),
                            '_equipo'    => $lado['equipo_nombre'],
                        ];
                    }
                }
            }
        }

        // Árbitros: sólo los que ya estén atados en arbitro_tm. Los que no,
        // se listan como aviso — no inventamos personas sin nombre.
        $plan['arbitros'] = $this->planArbitros($game, $partido);

        $informe['plan']   = $plan;
        $informe['avisos'] = $this->avisos;
        $informe['ok']     = true;

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
                    foreach ($plan['alineacions'] as $r) Alineacion::create($this->limpiar($r));
                    foreach ($plan['gols'] as $r)        Gol::create($this->limpiar($r));
                    foreach ($plan['tarjetas'] as $r)    Tarjeta::create($this->limpiar($r));
                    foreach ($plan['cambios'] as $r)     Cambio::create($this->limpiar($r));
                    foreach ($plan['arbitros'] as $r)    PartidoArbitro::create($this->limpiar($r));
                });
                $informe['escrito'] = true;
            } catch (\Exception $e) {
                $informe['ok']    = false;
                $informe['error'] = 'Error guardando el detalle: ' . $e->getMessage();
                Log::error('TmDetallePartido partido ' . $partido->id . ': ' . $e->getMessage(), []);
            }
        }

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

                if ($clase === 'cambio') {
                    $sale  = $this->valor($a, self::$kSale);
                    $entra = $this->valor($a, self::$kEntra);
                    if ($sale === null && $entra === null) {
                        $this->aviso('Cambio sin ids reconocibles: ' . $this->resumenCrudo($a));
                        continue;
                    }
                    $out[] = ['clase' => 'cambio', 'minuto' => $minuto, 'ids' => [$sale, $entra], 'crudo' => $a];
                } else {
                    $id = $this->valor($a, self::$kJugador);
                    if ($id === null) {
                        $this->aviso(ucfirst($clase) . ' sin id de jugador: ' . $this->resumenCrudo($a));
                        continue;
                    }
                    $out[] = ['clase' => $clase, 'minuto' => $minuto, 'ids' => [$id], 'crudo' => $a];
                }
            }
        }
        return $out;
    }

    /**
     * Transfermarkt describe el gol en texto libre. Mapeamos lo que reconocemos
     * y el resto cae en 'Jugada', pero queda marcado como dudoso para que se
     * vea en pantalla y podamos ampliar esta tabla.
     */
    private function tipoGol(array $a)
    {
        $txt = mb_strtolower($this->juntarTexto($a, self::$kDescGol));

        if ($txt === '') {
            return ['tipo' => self::GOL_JUGADA, 'fuente' => '(vacío)', 'dudoso' => true];
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

        return ['tipo' => self::GOL_JUGADA, 'fuente' => $txt, 'dudoso' => true];
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

        // Algunas versiones traen un código numérico en vez de texto.
        $cod = $this->valor($a, ['cardTypeId', 'typeId']);
        if ($cod !== null) {
            $mapa = [1 => 'Amarilla', 2 => 'Doble Amarilla', 3 => 'Roja'];
            if (isset($mapa[(int) $cod])) return ['tipo' => $mapa[(int) $cod], 'fuente' => 'código ' . $cod];
        }

        return ['tipo' => null, 'fuente' => $txt !== '' ? $txt : $this->resumenCrudo($a)];
    }

    /**
     * Árbitros del partido. Sólo se cargan los que ya estén atados en
     * `arbitro_tm`; los desconocidos quedan como aviso con su id, para que
     * los ates a mano una vez (son pocos y se repiten mucho).
     */
    private function planArbitros(array $game, Partido $partido)
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

        foreach ($pares as $par) {
            list($tmId, $rol) = $par;
            if ($tmId === null || $tmId === '') continue;
            if (!isset($mapa[(string) $tmId])) {
                $this->aviso('Árbitro TM ' . $tmId . ' (' . $rol . ') no está en arbitro_tm: no lo cargo. '
                    . 'Atalo una vez y queda para siempre.');
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

        // ¿Ya lo tenemos, aunque sin mapear? Igualamos por apellido + nacimiento,
        // que es el criterio del índice único de personas.
        $existente = null;
        if ($datos['apellido'] !== '' && !empty($datos['nacimiento'])) {
            $existente = DB::table('jugadors')
                ->join('personas', 'personas.id', '=', 'jugadors.persona_id')
                ->where('personas.apellido', $datos['apellido'])
                ->where('personas.nacimiento', $datos['nacimiento'])
                ->select('jugadors.id')->first();
        }
        if ($existente) {
            if ($escribir) $this->guardarMapeoJugador($tmId, $existente->id, $datos['name'], 'auto', false);
            return ['jugador_id' => (int) $existente->id, 'creado' => false, 'descripcion' => $etiqueta . ' — ya existía'];
        }

        if (!$escribir) {
            // Vista previa: no creamos nada, pero le damos un id negativo para
            // que igual aparezca en la alineación y en las incidencias.
            $ficticio = $this->proximoPreview--;
            $this->nombresPreview[$ficticio] = ($datos['name'] !== '' ? $datos['name'] : $datos['apellido']) . ' · nuevo';
            return ['jugador_id' => $ficticio, 'creado' => true, 'descripcion' => $etiqueta . ' — SE CREARÍA'
                . ($datos['nacimiento'] ? ' · ' . $datos['nacimiento'] : ' · sin fecha de nacimiento')
                . ($datos['nacionalidad'] ? ' · ' . $datos['nacionalidad'] : ' · sin nacionalidad')];
        }

        if ($datos['name'] === '' || $datos['apellido'] === '') {
            $this->aviso('Perfil TM ' . $tmId . ' sin nombre utilizable: no lo creo.');
            return ['jugador_id' => null, 'creado' => false, 'descripcion' => $etiqueta];
        }

        try {
            $persona = Persona::create($datos['persona']);
            $jugador = $persona->jugador()->create($datos['jugador']);
            $this->guardarMapeoJugador($tmId, $jugador->id, $datos['name'], 'auto', true);
            return ['jugador_id' => (int) $jugador->id, 'creado' => true, 'descripcion' => $etiqueta . ' — creado #' . $jugador->id];
        } catch (\Exception $e) {
            $this->aviso('No pude crear al jugador ' . $etiqueta . ': ' . $e->getMessage());
            return ['jugador_id' => null, 'creado' => false, 'descripcion' => $etiqueta];
        }
    }

    /** Traduce el perfil de la API a nuestros campos (mismo criterio que el import de jugadores). */
    private function personaDesdePerfil(array $p)
    {
        $n = NombreHelper::separarTM($p);

        $nacimiento = null;
        $raw = isset($p['lifeDates']['dateOfBirth']) ? $p['lifeDates']['dateOfBirth'] : null;
        if ($raw) {
            try { $nacimiento = Carbon::parse($raw)->format('Y-m-d'); } catch (\Exception $e) { $nacimiento = null; }
        }
        $fallecimiento = null;
        $rawF = isset($p['lifeDates']['dateOfDeath']) ? $p['lifeDates']['dateOfDeath'] : null;
        if ($rawF) {
            try { $fallecimiento = Carbon::parse($rawF)->format('Y-m-d'); } catch (\Exception $e) { $fallecimiento = null; }
        }

        $ciudad = trim((string) (isset($p['birthPlaceDetails']['placeOfBirth']) ? $p['birthPlaceDetails']['placeOfBirth'] : ''));
        $ciudad = $ciudad !== '' ? $ciudad : null;

        $nacionalidad = null;
        $nacId = (int) (isset($p['nationalityDetails']['nationalities']['nationalityId'])
            ? $p['nationalityDetails']['nationalities']['nationalityId'] : 0);
        if (!$nacId && isset($p['nationalityDetails']['nationalities'][0]['nationalityId'])) {
            $nacId = (int) $p['nationalityDetails']['nationalities'][0]['nationalityId'];
        }
        if ($nacId) {
            $paises = JugadorController::paisesTM();
            $nacionalidad = isset($paises[$nacId]) ? $paises[$nacId] : null;
            if ($nacionalidad === null) {
                $this->aviso('Código de país de Transfermarkt sin mapear: ' . $nacId . ' (' . $n['name'] . ').');
            }
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

        return [
            'name'         => trim($n['name']),
            'nombre'       => trim($n['nombre']),
            'apellido'     => trim($n['apellido']),
            'nacimiento'   => $nacimiento,
            'nacionalidad' => $nacionalidad,
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
