<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * La pareja de un cambio viaja pegada.
 *
 * Un cambio son DOS filas en `cambios` —una «Entra» y una «Sale»— que no
 * tienen ningún vínculo entre sí: lo único que las une es el minuto. Por eso
 * el control «Entra sin salir» compara, minuto por minuto, cuántos entran
 * contra cuántos salen.
 *
 * Corregir UNA sola de las dos filas parte la pareja. Es lo que pasó con el
 * repaso de minutos: corrige protagonista por protagonista y, cuando al
 * compañero no lo puede aparear —no está en `jugador_tm`, o su fila quedó a
 * más de un minuto y el jugador tiene dos filas en el partido, que es lo que
 * rechaza la pasada floja—, mueve una y deja la otra donde estaba. El
 * 09/09/2026 eso dejó 3299 partidos marcados: `90+4 94`, `90 90+2`, `63 64`.
 *
 * Acá está el criterio para volver a juntarlas, y se usa desde dos lados:
 *
 *   · `TmDetallePartido::repasarMinutos()` le pasa los ids que apareó contra
 *     Transfermarkt: el minuto bueno es el que dice TM, y la pareja lo sigue.
 *   · El botón del control va SIN TM y no gasta una sola llamada: sólo une la
 *     pareja cuando una fila tiene el descuento y la otra es la MISMA jugada
 *     escrita en una de las formas viejas —el 90+4 cargado como 90 (TM tiraba
 *     el `addedTime`) o como 94 (promiedos lo sumaba)—. Un `63 64` no lo toca:
 *     sin preguntarle a TM no hay forma de saber cuál de los dos es el bueno.
 *
 * Nunca adivina. Mueve una fila sólo si se cumple todo esto:
 *
 *   · al grupo que la recibe le falta exactamente UNA fila de ese tipo;
 *   · al grupo que la larga le sobra exactamente UNA de ese tipo (así los dos
 *     minutos quedan parejos: mismo Entra que Sale);
 *   · hay UNA sola candidata (con dos, avisa y no toca nada);
 *   · la fila que se mueve no es de las que dijo TM en esta misma pasada.
 */
class CambiosPareja
{
    const ENTRA = 'Entra';
    const SALE  = 'Sale';

    /**
     * Tope de movimientos por partido. Cada vuelta arregla una pareja y vuelve
     * a mirar el partido entero, así una corrección puede destrabar la
     * siguiente. El tope es para que un caso raro no deje el bucle girando.
     */
    const VUELTAS = 20;

    /** Partidos por tanda del botón: el hosting corta las requests largas. */
    const LIMITE_APLICAR = 1000;

    /** @var Controles */
    private $controles;

    public function __construct(Controles $controles)
    {
        $this->controles = $controles;
    }

    // ------------------------------------------------------------------
    // El criterio (no toca la base)
    // ------------------------------------------------------------------

    /**
     * Qué filas habría que mover para que las parejas del partido cierren.
     *
     * @param iterable $filas      todas las filas de `cambios` del partido
     * @param array    $idsTm      ids apareados contra un evento de TM en esta
     *                             pasada: su minuto es el bueno y no se mueven
     * @param array    $yaEscrito  id => ['minuto','adicionado'] que el llamador
     *                             ya tiene planeado escribir (el repaso)
     * @return array ['escribir' => [id => ['minuto','adicionado']],
     *                'movidas'  => [['id','jugador_id','tipo','de','a']],
     *                'avisos'   => [string]]
     */
    public function plan($filas, array $idsTm = [], array $yaEscrito = [])
    {
        $fijos  = array_flip(array_map('intval', $idsTm));
        $estado = [];

        foreach ($filas as $f) {
            $id   = (int) $f->id;
            $tipo = isset($f->tipo) ? (string) $f->tipo : '';

            // Sólo Entra y Sale: si algún día aparece otro tipo, que no lo
            // arrastre un criterio pensado para estos dos.
            if ($tipo !== self::ENTRA && $tipo !== self::SALE) continue;

            $m = ($f->minuto === null || $f->minuto === '') ? null : (int) $f->minuto;
            $a = (isset($f->adicionado) && $f->adicionado !== null && $f->adicionado !== '')
                ? (int) $f->adicionado : null;

            $planeada = isset($yaEscrito[$id]);
            if ($planeada) {
                $m = $yaEscrito[$id]['minuto'];
                $a = isset($yaEscrito[$id]['adicionado']) ? $yaEscrito[$id]['adicionado'] : null;
                $m = ($m === null || $m === '') ? null : (int) $m;
            }

            // Una fila sin minuto no está en ningún grupo: el control tampoco
            // la mira, y moverla sería inventar.
            if ($m === null) continue;

            $estado[$id] = [
                'minuto'     => $m,
                'adicionado' => ($a === null || (int) $a === 0) ? null : (int) $a,
                'tipo'       => $tipo,
                'jugador_id' => isset($f->jugador_id) ? (int) $f->jugador_id : null,
                // Fija = su minuto ya lo decidió TM en esta pasada. Puede
                // recibir a la pareja, nunca moverse.
                'fija'       => isset($fijos[$id]) || $planeada,
                'de'         => MinutoHelper::texto($m, ($a === null || (int) $a === 0) ? null : $a),
            ];
        }

        $escribir = [];
        $movidas  = [];
        $avisos   = [];

        for ($vuelta = 0; $vuelta < self::VUELTAS; $vuelta++) {
            $paso = $this->unMovimiento($estado, $avisos);
            if ($paso === null) break;

            $id = $paso['id'];
            $escribir[$id] = ['minuto' => $paso['minuto'], 'adicionado' => $paso['adicionado']];
            $movidas[] = [
                'id'         => $id,
                'jugador_id' => $estado[$id]['jugador_id'],
                'tipo'       => $estado[$id]['tipo'],
                'de'         => $estado[$id]['de'],
                'a'          => MinutoHelper::texto($paso['minuto'], $paso['adicionado']),
            ];

            $estado[$id]['minuto']     = $paso['minuto'];
            $estado[$id]['adicionado'] = $paso['adicionado'];
        }

        return ['escribir' => $escribir, 'movidas' => $movidas, 'avisos' => $avisos];
    }

    /**
     * El próximo movimiento seguro, o null si no queda ninguno.
     *
     * Arma los grupos (minuto + descuento) y busca uno al que le falte
     * exactamente una fila y otro al que le sobre exactamente esa fila.
     */
    private function unMovimiento(array $estado, array &$avisos)
    {
        $grupos = [];
        foreach ($estado as $id => $e) {
            $o = MinutoHelper::orden($e['minuto'], $e['adicionado']);
            if (!isset($grupos[$o])) {
                $grupos[$o] = [
                    'minuto'     => $e['minuto'],
                    'adicionado' => $e['adicionado'],
                    self::ENTRA  => [],
                    self::SALE   => [],
                    'fijo'       => false,
                ];
            }
            $grupos[$o][$e['tipo']][] = $id;
            if ($e['fija']) $grupos[$o]['fijo'] = true;
        }

        foreach ($grupos as $oD => $destino) {
            $dif = count($destino[self::ENTRA]) - count($destino[self::SALE]);

            // Sólo el descalce de a uno. Dos o más no es una pareja partida:
            // es otro problema y no lo arregla mover una fila.
            if ($dif !== 1 && $dif !== -1) continue;
            $falta = $dif > 0 ? self::SALE : self::ENTRA;

            // El grupo que se queda con la fila tiene que ser el que sabemos
            // bueno: o lo acaba de decir TM, o tiene el descuento escrito —que
            // es lo único que escribe el repaso desde TM, porque ninguna de
            // las dos formas viejas lo tenía—.
            if (!$destino['fijo'] && $destino['adicionado'] === null) continue;

            $candidatas = [];
            foreach ($grupos as $oS => $origen) {
                if ($oS === $oD) continue;

                $difS = count($origen[self::ENTRA]) - count($origen[self::SALE]);
                // Al otro grupo le tiene que sobrar justo lo que a éste le
                // falta: así los dos quedan parejos de una.
                if ($falta === self::SALE  && $difS !== -1) continue;
                if ($falta === self::ENTRA && $difS !== 1)  continue;

                if (!$this->compatibles($destino, $origen, $oD, $oS)) continue;

                foreach ($origen[$falta] as $id) {
                    if ($estado[$id]['fija']) continue;   // esa la dijo TM: no se toca
                    $candidatas[] = $id;
                }
            }

            if (count($candidatas) === 0) continue;

            if (count($candidatas) > 1) {
                $avisos[] = 'El minuto ' . MinutoHelper::texto($destino['minuto'], $destino['adicionado'])
                    . ' tiene un «' . ($falta === self::SALE ? self::ENTRA : self::SALE)
                    . '» sin pareja, y hay ' . count($candidatas) . ' filas que podrían serlo: no muevo ninguna.';
                continue;
            }

            return [
                'id'         => $candidatas[0],
                'minuto'     => $destino['minuto'],
                'adicionado' => $destino['adicionado'],
            ];
        }

        return null;
    }

    /**
     * ¿El grupo origen es el mismo instante que el destino, mal escrito?
     *
     * Dos formas viejas, las que dejó cada fuente antes de que existiera la
     * columna `adicionado` (ver MinutoHelper::formasViejas):
     *
     *   · el importador de TM tiraba el descuento  → el 90+4 quedó como 90
     *   · el scraper de promiedos lo sumaba        → el 90+4 quedó como 94
     *
     * Y, sólo cuando el destino lo acaba de decir Transfermarkt, se acepta
     * además un minuto de diferencia: es la misma tolerancia con la que el
     * repaso aparea sus eventos, ni un minuto más.
     */
    private function compatibles(array $destino, array $origen, $oD, $oS)
    {
        if ($origen['adicionado'] === null && $destino['adicionado'] !== null) {
            if ($origen['minuto'] === $destino['minuto']) return true;
            if ($origen['minuto'] === $destino['minuto'] + $destino['adicionado']) return true;
        }

        if ($destino['fijo'] && abs($oD - $oS) <= 100) return true;

        return false;
    }

    // ------------------------------------------------------------------
    // Aplicarlo (esto sí toca la base)
    // ------------------------------------------------------------------

    /**
     * El mismo criterio que el control «Entra sin salir»: los partidos donde
     * algún minuto tiene distinta cantidad de «Entra» que de «Sale».
     *
     * Devuelve SQL y no ids porque se usa adentro de un `whereIn`: la lista
     * son miles de partidos y no tiene sentido traerlos para volver a
     * mandarlos. El `partido_id` viene repetido —una fila por minuto
     * descalzado—, que a un `IN` no le molesta.
     */
    public static function sqlPartidosImpares()
    {
        return "SELECT partido_id
                FROM cambios
                GROUP BY partido_id, minuto, adicionado
                HAVING SUM(CASE WHEN tipo = 'Entra' THEN 1 ELSE 0 END)
                     <> SUM(CASE WHEN tipo = 'Sale'  THEN 1 ELSE 0 END)";
    }

    /** Junta las parejas de UN partido. Devuelve el plan que aplicó. */
    public function arreglarPartido($partidoId, $escribir = true)
    {
        $filas = DB::table('cambios')->where('partido_id', (int) $partidoId)
            ->orderBy('minuto')->orderBy('adicionado')->orderBy('id')->get();

        $plan = $this->plan($filas);

        if ($escribir && !empty($plan['escribir'])) {
            DB::transaction(function () use ($plan) {
                foreach ($plan['escribir'] as $id => $vals) {
                    DB::table('cambios')->where('id', (int) $id)->update($vals);
                }
            });
        }

        return $plan;
    }

    /**
     * Los partidos donde puede haber una pareja partida por el descuento.
     *
     * Filtra por el grupo que TIENE descuento: si ningún minuto con `+n` está
     * descalzado, no hay nada que este pase pueda decidir sin llamar a TM. Con
     * eso la lista baja de los 3299 del control a los que sirven, y el botón
     * no tiene que arrastrar un cursor entre tandas.
     */
    public function partidosConDescuentoImpar(array $filtros, $limite)
    {
        $derivada = "SELECT partido_id
                     FROM cambios
                     WHERE adicionado IS NOT NULL AND adicionado > 0
                     GROUP BY partido_id, minuto, adicionado
                     HAVING SUM(CASE WHEN tipo = 'Entra' THEN 1 ELSE 0 END)
                          <> SUM(CASE WHEN tipo = 'Sale'  THEN 1 ELSE 0 END)";

        $q = $this->controles
            ->base($filtros, ['raw' => $derivada, 'alias' => 't1', 'partido' => 't1.partido_id'])
            ->select('partidos.id')
            ->distinct();

        return $this->controles->sinIncidencia($q)
            ->orderBy('partidos.id')
            ->limit((int) $limite)
            ->pluck('id')->all();
    }

    /**
     * Pasada completa: junta todas las parejas que se pueden decidir sin
     * preguntarle nada a Transfermarkt.
     */
    public function aplicar(array $filtros)
    {
        $ids = $this->partidosConDescuentoImpar($filtros, self::LIMITE_APLICAR + 1);

        $restantes = 0;
        if (count($ids) > self::LIMITE_APLICAR) {
            $ids       = array_slice($ids, 0, self::LIMITE_APLICAR);
            $restantes = -1;   // hay más, no sabemos cuántos sin volver a contar
        }

        $partidos = 0;
        $movidas  = 0;
        $dudosos  = 0;
        $detalle  = [];

        foreach ($ids as $pid) {
            $plan = $this->arreglarPartido((int) $pid, true);

            if (!empty($plan['movidas'])) {
                $partidos++;
                $movidas += count($plan['movidas']);
                if (count($detalle) < 50) {
                    foreach ($plan['movidas'] as $m) {
                        $detalle[] = 'Partido #' . (int) $pid . ': el «' . $m['tipo'] . '» del '
                            . $m['de'] . ' pasa a ' . $m['a'] . '.';
                    }
                }
            }

            if (!empty($plan['avisos'])) $dudosos++;
        }

        $this->controles->invalidarConteos();

        return [
            'mirados'   => count($ids),
            'partidos'  => $partidos,
            'movidas'   => $movidas,
            'dudosos'   => $dudosos,
            'restantes' => $restantes,
            'detalle'   => $detalle,
        ];
    }
}
