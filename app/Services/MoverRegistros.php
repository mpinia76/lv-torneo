<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Traspaso PARCIAL de carrera entre dos fichas que SON personas distintas.
 *
 * El caso que lo motivó: Iván Gómez #291 (28/02/1997) e Iván Gómez #3160
 * (26/01/1990) son dos personas. Pero el tramo "Platense 2021-2022" está
 * colgado de #3160 y en realidad es de #291. No hay nada que fusionar —las dos
 * fichas tienen que seguir existiendo— y sin embargo hay que mover un pedazo.
 *
 * Qué lo hace distinto de una fusión:
 *  - La ficha de origen NO se borra, ni siquiera si queda sin un solo registro.
 *    Si quedó vacía, cae sola en la pestaña "Sin registros" y se decide ahí.
 *  - Se mueve un subconjunto elegido a mano, no todo lo que cuelga.
 *  - El criterio de choque NO puede ser el de la fusión: ver choqueDe(). Dos
 *    filas en el mismo partido son la misma fila solo si además son del mismo
 *    equipo (y del mismo plantel, donde eso aplique); acá las dos fichas pueden
 *    haber jugado ese partido una contra la otra. Y si falta el dato que
 *    identifica al club, la fila no se mueve: se avisa y se resuelve a mano.
 *
 * La trampa central, y la razón de que el "club" no alcance como criterio:
 * `gols`, `tarjetas`, `cambios` y `penals` NO tienen equipo ni torneo, solo
 * `partido_id` + la ficha. Si se mueve la alineación y se olvidan ellas, el gol
 * queda en la ficha vieja para un partido en el que ya no figura: la ficha
 * pública muestra el partido sin el gol de un lado y una estadística fantasma
 * del otro. Por eso la unidad real del movimiento es el PARTIDO (y la
 * PLANTILLA), y el club solo sirve para elegirlos.
 *
 * La otra trampa: un partido CONTRA el club también tiene al club de local o
 * visitante. Si la ficha jugó ese partido para el rival, sus goles no son de
 * este tramo. Cuando hay alineación cargada eso se resuelve solo; cuando el
 * import trajo el gol suelto, sin alineación, NO hay forma de saberlo desde la
 * base: esos partidos salen marcados como dudosos y destildados.
 */
class MoverRegistros
{
    /**
     * Roles soportados. Los árbitros quedan afuera a propósito: `partido_arbitros`
     * no tiene equipo, así que "el club" no existe como criterio de corte —lo que
     * la pantalla les muestra son torneos. Partir la carrera de un árbitro es otro
     * problema; mover su ficha entera ya lo resuelve "Reasignar".
     */
    const ROLES = ['jugador', 'tecnico'];

    /** Caché de Schema::hasTable/hasColumn: son consultas a information_schema. */
    private static $esquema = [];

    /**
     * Tablas de las que sale "este registro es de tal equipo", por rol. Son las
     * únicas que tienen `equipo_id` propio; el resto se atribuye por el partido.
     *
     * OJO: todo lo que se nombre acá tiene que estar también entre los hijos del
     * rol en FusionPersonas y tener `partido_id`. Si no, las cuentas de la
     * pantalla (partidos del club / contra el club / de otros clubes) dejan de
     * sumar el total y pueden dar negativo.
     */
    private static function pivotesConEquipo(string $rol): array
    {
        $mapa = [
            'jugador' => ['alineacions'],
            'tecnico' => ['partido_tecnicos'],
        ];

        return isset($mapa[$rol]) ? $mapa[$rol] : [];
    }

    /** La tabla que ata la ficha a un plantel, por rol. */
    private static function pivotePlantilla(string $rol)
    {
        $mapa = [
            'jugador' => 'plantilla_jugadors',
            'tecnico' => 'plantilla_tecnicos',
        ];

        return isset($mapa[$rol]) ? $mapa[$rol] : null;
    }

    /**
     * Config del rol: sale de FusionPersonas para que las tablas sean LAS MISMAS
     * que usa la fusión. Si mañana se agrega una tabla hija, se agrega en un solo
     * lugar.
     */
    private static function base(string $rol)
    {
        $relaciones = FusionPersonas::mapaRoles();

        if (!in_array($rol, self::ROLES, true) || !isset($relaciones[$rol])) {
            return null;
        }

        $cfg                     = $relaciones[$rol];
        $cfg['pivotePlantilla']  = self::pivotePlantilla($rol);
        $cfg['pivotesConEquipo'] = self::pivotesConEquipo($rol);

        return $cfg;
    }

    /**
     * Columnas que definen "esta fila del destino y esta del origen son el mismo
     * hecho", en el contexto de un traspaso parcial.
     *
     * Se parte de las de la fusión y se le suma toda columna que identifique al
     * club o al plantel (`equipo_id`, `plantilla_id`). En una fusión las dos filas
     * son de la misma persona, así que el partido alcanza. Acá no: el partido
     * puede ser justo el que las dos fichas jugaron una contra la otra, y
     * "unificar" ahí borraría la alineación del rival y le escribiría encima el
     * equipo equivocado.
     */
    private static function choqueDe(string $hijo, array $base): array
    {
        $choque = isset($base['hijos'][$hijo]) ? $base['hijos'][$hijo] : [];

        // La regla es "toda columna que identifique al club o al plantel entra en
        // el choque", no "arreglar alineacions". Escrito como un if por nombre de
        // tabla, la próxima tabla con partido_id + equipo_id repite el bug.
        foreach (['equipo_id', 'plantilla_id'] as $columna) {
            if (self::hayColumna($hijo, $columna) && !in_array($columna, $choque, true)) {
                $choque[] = $columna;
            }
        }

        return $choque;
    }

    // ------------------------------------------------------------------
    // Previsualización
    // ------------------------------------------------------------------

    /**
     * Qué se movería: las plantillas y los partidos de ESE club que hoy cuelgan
     * de la ficha de origen.
     *
     * @param  int[] $equipoIds ids reales de `equipos` (nunca el nombre del club)
     */
    public static function previsualizar(int $personaOrigen, int $personaDestino, string $rol, array $equipoIds): array
    {
        $base = self::base($rol);
        if (!$base) {
            return self::error("El traspaso parcial no está disponible para el rol '{$rol}'.");
        }

        if ($personaOrigen === $personaDestino) {
            return self::error('El origen y el destino son la misma persona.');
        }

        $equipoIds = array_values(array_unique(array_filter(array_map('intval', $equipoIds))));
        if (!$equipoIds) {
            return self::error('No se indicó ningún club.');
        }

        $tabla = $base['tabla'];
        if (!self::hayTabla($tabla) || !self::hayColumna($tabla, 'persona_id')) {
            return self::error("La tabla {$tabla} no está disponible en esta base.");
        }

        $personas = DB::table('personas')->whereIn('id', [$personaOrigen, $personaDestino])->get()->keyBy('id');
        if (!$personas->get($personaOrigen) || !$personas->get($personaDestino)) {
            return self::error('Alguna de las dos personas ya no existe.');
        }

        // OJO: persona_id no tiene índice único. Una persona puede tener más de
        // una ficha del mismo rol y hay que mirarlas a TODAS, de los dos lados:
        // si el choque se buscara solo contra la primera ficha del destino, un
        // partido que ya está en la segunda pasaría como "libre" y terminaría
        // repartido entre dos fichas de la misma persona.
        $fichasOrigen  = DB::table($tabla)->where('persona_id', $personaOrigen)->orderBy('id')->pluck('id')->all();
        $fichasDestino = DB::table($tabla)->where('persona_id', $personaDestino)->orderBy('id')->pluck('id')->all();

        if (!$fichasOrigen) {
            return self::error("La persona #{$personaOrigen} no tiene ficha de {$rol}: no hay nada que mover.");
        }

        $equipos    = DB::table('equipos')->whereIn('id', $equipoIds)->pluck('nombre', 'id')->all();
        $permitidos = self::permitidos($base, $fichasOrigen, $equipoIds, $fichasDestino);

        $plantillas = self::detallarPlantillas($base, $fichasOrigen, $permitidos['plantillas'], $fichasDestino);
        $partidos   = self::detallarPartidos(
            $base, $fichasOrigen, $permitidos['partidos'], $fichasDestino, $permitidos['dudosos'], $equipoIds
        );

        return [
            'ok'            => true,
            'mensaje'       => '',
            'rol'           => $rol,
            'origen'        => $personas->get($personaOrigen),
            'destino'       => $personas->get($personaDestino),
            'fichasOrigen'  => $fichasOrigen,
            'fichasDestino' => $fichasDestino,
            'fichaDestino'  => $fichasDestino ? (int) $fichasDestino[0] : null,
            'crearFicha'    => !$fichasDestino,
            'equipos'       => $equipos,
            'plantillas'    => $plantillas,
            'partidos'      => $partidos,
            // Lo que NO entra en el tramo, para que el número de la pantalla
            // cierre y nadie salga a buscar registros que se quedaron.
            'contraElClub'  => $permitidos['contra'],
            'fueraDelClub'  => $permitidos['fuera'],
            'sinPartido'    => $permitidos['sinPartido'],
            'contradictorios' => $permitidos['contradictorios'],
            'sinMover'      => self::tablasQueNoSeMueven($base),
        ];
    }

    /**
     * La lista blanca: qué plantillas y qué partidos de la ficha de origen
     * pertenecen a este club.
     *
     * Es el único lugar donde se decide qué se puede mover. `mover()` la vuelve a
     * calcular DENTRO de la transacción y se queda con la intersección: sin eso,
     * un id escrito a mano en el formulario movería registros de otro club. Ojo:
     * son ids de PARTIDO, no de fila — ver el docblock de mover().
     *
     * @return array ['plantillas'=>int[], 'partidos'=>int[], 'dudosos'=>int[],
     *                'contra'=>int, 'fuera'=>int, 'sinPartido'=>int, 'contradictorios'=>int]
     */
    private static function permitidos(array $base, array $fichasOrigen, array $equipoIds, array $fichasDestino = []): array
    {
        $fk = $base['fk'];

        $delClub    = [];   // el club aparece explícito en una fila con equipo
        $deOtro     = [];   // la ficha jugó ese partido para otro equipo
        $sinPartido = 0;    // filas con partido_id en NULL: no se pueden ubicar

        foreach ($base['pivotesConEquipo'] as $hijo) {
            if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)
                || !self::hayColumna($hijo, 'partido_id') || !self::hayColumna($hijo, 'equipo_id')) {
                continue;
            }

            foreach (DB::table($hijo)->whereIn($fk, $fichasOrigen)->whereIn('equipo_id', $equipoIds)
                        ->whereNotNull('partido_id')->distinct()->pluck('partido_id') as $id) {
                $delClub[(int) $id] = true;
            }

            // whereNotIn deja afuera las filas con equipo_id NULL (NOT IN contra
            // NULL nunca es verdadero). Esas no dicen para qué equipo jugó, así
            // que no restan acá: caen en los huérfanos y salen como dudosas.
            foreach (DB::table($hijo)->whereIn($fk, $fichasOrigen)->whereNotIn('equipo_id', $equipoIds)
                        ->whereNotNull('partido_id')->distinct()->pluck('partido_id') as $id) {
                $deOtro[(int) $id] = true;
            }
        }

        // Todos los partidos donde la ficha tiene algo, sin importar la tabla.
        $todos = [];
        foreach (self::hijosPorPartido($base) as $hijo) {
            foreach (DB::table($hijo)->whereIn($fk, $fichasOrigen)->distinct()->pluck('partido_id') as $id) {
                if ($id === null || (int) $id === 0) {
                    continue;
                }
                $todos[(int) $id] = true;
            }
        }

        // Las filas sin partido se cuentan aparte y de a filas: dentro del
        // distinct() de arriba el grupo NULL devuelve UNA fila por tabla, así que
        // sumarlas ahí daba un número que nunca pasaba de 4.
        foreach (self::hijosPorPartido($base) as $hijo) {
            $sinPartido += DB::table($hijo)->whereIn($fk, $fichasOrigen)
                ->where(function ($w) {
                    $w->whereNull('partido_id')->orWhere('partido_id', 0);
                })
                ->count();
        }

        // Los partidos donde la ficha tiene solo filas sin equipo (un gol suelto,
        // una tarjeta) se atribuyen por los equipos del partido. Es una conjetura,
        // no un dato: el mismo partido pudo jugarlo para el rival y que la
        // alineación no esté cargada. Por eso quedan marcados como dudosos.
        $dudosos   = [];
        $huerfanos = array_diff_key($todos, $delClub, $deOtro);

        if ($huerfanos && self::hayTabla('partidos')) {
            $columnas = array_values(array_filter(['equipol_id', 'equipov_id'], function ($c) {
                return self::hayColumna('partidos', $c);
            }));

            if ($columnas) {
                $q = DB::table('partidos')->whereIn('id', array_keys($huerfanos));
                $q->where(function ($w) use ($columnas, $equipoIds) {
                    foreach ($columnas as $c) {
                        $w->orWhereIn($c, $equipoIds);
                    }
                });

                foreach ($q->pluck('id') as $id) {
                    $delClub[(int) $id] = true;
                    $dudosos[(int) $id] = true;
                }
            }
        }

        // La resta va al final: si la ficha jugó ese partido para otro equipo, el
        // partido no es de este tramo aunque además tenga una fila suelta.
        foreach (array_keys($deOtro) as $id) {
            unset($delClub[$id], $dudosos[$id]);
        }

        // Partidos donde la ficha de DESTINO ya juega para otro equipo: las dos se
        // enfrentaron. Una misma persona no puede estar en las dos alineaciones,
        // así que mover el tramo ahí es imposible por definición, y encima es el
        // caso que rompería contra un único (ficha, partido). Se bloquean, no se
        // ofrecen destildados.
        $contradictorios = [];
        if ($fichasDestino) {
            foreach ($base['pivotesConEquipo'] as $hijo) {
                if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)
                    || !self::hayColumna($hijo, 'partido_id') || !self::hayColumna($hijo, 'equipo_id')) {
                    continue;
                }

                $q = DB::table($hijo)->whereIn($fk, $fichasDestino)->whereNotNull('partido_id');
                if ($delClub) {
                    $q->whereIn('partido_id', array_keys($delClub));
                }

                // orWhereNull igual que del lado del origen: whereNotIn nunca es
                // verdadero contra NULL, y una alineación del destino sin equipo
                // cargado es tan imposible de combinar como una del rival —si se
                // dejara pasar, la persona terminaría con dos alineaciones en el
                // mismo partido (o el INSERT reventaría contra un único).
                $q->where(function ($w) use ($equipoIds) {
                    $w->whereNotIn('equipo_id', $equipoIds)->orWhereNull('equipo_id');
                });

                foreach ($q->distinct()->pluck('partido_id') as $id) {
                    if (isset($delClub[(int) $id])) {
                        $contradictorios[(int) $id] = true;
                        unset($delClub[(int) $id], $dudosos[(int) $id]);
                    }
                }
            }
        }

        // "Contra el club" es SOLO donde jugó para otro equipo Y el club está en
        // el partido. Sin esta intersección el número sería toda la carrera en
        // otros clubes, y la pantalla diría que jugó 300 partidos contra Platense.
        $contra = 0;
        if ($deOtro && self::hayTabla('partidos') && self::hayColumna('partidos', 'equipol_id')) {
            $q = DB::table('partidos')->whereIn('id', array_keys($deOtro));
            $q->where(function ($w) use ($equipoIds) {
                $w->orWhereIn('equipol_id', $equipoIds);
                if (self::hayColumna('partidos', 'equipov_id')) {
                    $w->orWhereIn('equipov_id', $equipoIds);
                }
            });
            $contra = $q->count();
        }

        $ids = array_keys($delClub);
        sort($ids);

        $dudososIds = array_keys($dudosos);
        sort($dudososIds);

        return [
            'plantillas' => self::plantillasDe($base, $fichasOrigen, $equipoIds),
            'partidos'   => $ids,
            'dudosos'    => $dudososIds,
            'contra'     => $contra,
            // max(0) defensivo: hoy los conjuntos son disjuntos, pero
            // pivotesConEquipo() y los hijos del rol se mantienen en archivos
            // distintos y un descuido acá se vería como "-3 partidos".
            'fuera'      => max(0, count($todos) - count($delClub) - $contra - count($contradictorios)),
            'sinPartido' => $sinPartido,
            'contradictorios' => count($contradictorios),
        ];
    }

    /** Ids de los planteles del club que cuelgan de la ficha de origen. */
    private static function plantillasDe(array $base, array $fichasOrigen, array $equipoIds): array
    {
        $pivote = $base['pivotePlantilla'];
        $fk     = $base['fk'];

        if (!$pivote || !self::hayTabla($pivote) || !self::hayColumna($pivote, 'plantilla_id')
            || !self::hayTabla('plantillas') || !self::hayColumna('plantillas', 'equipo_id')) {
            return [];
        }

        $ids = DB::table($pivote . ' as pv')
            ->join('plantillas as pl', 'pl.id', '=', 'pv.plantilla_id')
            ->whereIn('pv.' . $fk, $fichasOrigen)
            ->whereIn('pl.equipo_id', $equipoIds)
            ->distinct()
            ->pluck('pv.plantilla_id')
            ->map(function ($id) { return (int) $id; })
            ->unique()
            ->values()
            ->all();

        sort($ids);

        return $ids;
    }

    /**
     * Los planteles de la lista blanca, con torneo, año y dorsal.
     *
     * `plantillas` tiene `grupo_id` en producción (agregada a mano) y `torneo_id`
     * en la migración original. Se soportan las dos: sin este guard, una base con
     * el esquema viejo devolvería la lista vacía sin decir por qué.
     */
    private static function detallarPlantillas(array $base, array $fichasOrigen, array $ids, array $fichasDestino): array
    {
        if (!$ids) {
            return [];
        }

        $pivote = $base['pivotePlantilla'];
        $fk     = $base['fk'];

        $q = DB::table($pivote . ' as pv')
            ->join('plantillas as pl', 'pl.id', '=', 'pv.plantilla_id')
            ->join('equipos as e', 'e.id', '=', 'pl.equipo_id')
            ->whereIn('pv.' . $fk, $fichasOrigen)
            ->whereIn('pv.plantilla_id', $ids);

        $select = ['pv.plantilla_id', 'e.nombre as equipo', 't.nombre as torneo', 't.year'];

        if (!self::hayTabla('torneos') || !self::hayTabla('equipos')) {
            return [];
        }

        if (self::hayColumna('plantillas', 'grupo_id') && self::hayTabla('grupos')) {
            $q->join('grupos as g', 'g.id', '=', 'pl.grupo_id')
              ->join('torneos as t', 't.id', '=', 'g.torneo_id');
            $select[] = 'g.nombre as grupo';
        } elseif (self::hayColumna('plantillas', 'torneo_id')) {
            $q->join('torneos as t', 't.id', '=', 'pl.torneo_id');
        } else {
            return [];
        }

        if (self::hayColumna($pivote, 'dorsal')) {
            $select[] = 'pv.dorsal';
        }

        // Planteles donde el destino YA está: no rompen, se unifican, pero el que
        // aprieta el botón tiene que verlo antes. Se mira contra TODAS las fichas
        // del destino, no solo la primera.
        $choques = [];
        if ($fichasDestino) {
            foreach (DB::table($pivote)->whereIn($fk, $fichasDestino)->whereIn('plantilla_id', $ids)
                        ->distinct()->pluck('plantilla_id') as $id) {
                $choques[(int) $id] = true;
            }
        }

        // Una misma ficha (o dos fichas del origen) puede tener dos filas en el
        // mismo plantel con dorsales distintos: el DISTINCT de SQL no las colapsa
        // porque el dorsal entra en el SELECT. Se agrupa acá, si no la pantalla
        // muestra dos veces el mismo plantel y el contador miente.
        $porPlantilla = [];
        foreach ($q->select($select)->get() as $f) {
            $id = (int) $f->plantilla_id;

            if (!isset($porPlantilla[$id])) {
                $porPlantilla[$id] = [
                    'id'      => $id,
                    'equipo'  => $f->equipo,
                    'torneo'  => $f->torneo,
                    'year'    => $f->year,
                    'grupo'   => isset($f->grupo) ? $f->grupo : null,
                    'dorsales' => [],
                    'dorsal'  => null,
                    'choca'   => array_key_exists($id, $choques),
                ];
            }

            // Los dorsales se juntan en una lista y se comparan enteros. Con
            // strpos sobre la cadena ya armada, el dorsal 7 se perdía apenas
            // hubiera un 17.
            if (isset($f->dorsal) && $f->dorsal !== null && $f->dorsal !== ''
                && !in_array((string) $f->dorsal, $porPlantilla[$id]['dorsales'], true)) {
                $porPlantilla[$id]['dorsales'][] = (string) $f->dorsal;
            }
        }

        foreach ($porPlantilla as $id => $entrada) {
            $porPlantilla[$id]['dorsal'] = $entrada['dorsales'] ? implode(' / ', $entrada['dorsales']) : null;
        }

        $salida = array_values($porPlantilla);

        usort($salida, function ($a, $b) {
            if ($a['year'] === $b['year']) {
                return strcmp((string) $a['torneo'], (string) $b['torneo']);
            }
            return strcmp((string) $a['year'], (string) $b['year']);
        });

        return $salida;
    }

    /**
     * Tablas hijas del rol que se mueven por partido (sin bitácoras).
     *
     * El criterio tiene que ser EL MISMO que el de mover(): una tabla que se
     * cuenta acá y se mueve por plantilla allá deja filas colgadas apuntando a
     * partidos que ya se fueron. Por eso `partido_id` gana siempre que exista.
     */
    private static function hijosPorPartido(array $base): array
    {
        $excluidas = FusionPersonas::hijosQueNoSonRegistro();
        $salida    = [];

        foreach (array_keys($base['hijos']) as $hijo) {
            if (in_array($hijo, $excluidas, true)) {
                continue;
            }
            if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $base['fk']) || !self::hayColumna($hijo, 'partido_id')) {
                continue;
            }
            $salida[] = $hijo;
        }

        return $salida;
    }

    /** Tablas hijas que este movimiento NO toca, para avisarlo en la pantalla. */
    private static function tablasQueNoSeMueven(array $base): array
    {
        $salida = [];

        foreach (array_keys($base['hijos']) as $hijo) {
            // Las bitácoras de importación no son registros deportivos: no se
            // mueven y tampoco hace falta avisar que no se movieron.
            if (in_array($hijo, FusionPersonas::hijosQueNoSonRegistro(), true)) {
                continue;
            }
            if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $base['fk'])) {
                continue;
            }
            if (self::hayColumna($hijo, 'partido_id') || self::hayColumna($hijo, 'plantilla_id')) {
                continue;
            }
            $salida[] = $hijo;
        }

        return $salida;
    }

    /**
     * Qué tiene la ficha en cada partido del tramo, para que la pantalla no pida
     * confirmar una lista de ids.
     *
     * El detalle se arma recorriendo las MISMAS tablas hijas que después se
     * mueven: si mañana aparece una tabla nueva, se ve en la previsualización
     * sola, en vez de moverse en silencio.
     */
    private static function detallarPartidos(
        array $base,
        array $fichasOrigen,
        array $ids,
        array $fichasDestino,
        array $dudosos,
        array $equipoIds
    ): array
    {
        if (!$ids || !self::hayTabla('partidos')) {
            return [];
        }

        $fk      = $base['fk'];
        $dudoso  = array_flip($dudosos);

        $q = DB::table('partidos as pa')->whereIn('pa.id', $ids);
        $select = ['pa.id'];

        foreach (['fecha' => 'dia', 'golesl' => 'golesl', 'golesv' => 'golesv'] as $col => $alias) {
            if (self::hayColumna('partidos', $col)) {
                $select[] = 'pa.' . $col . ' as ' . $alias;
            }
        }

        if (self::hayTabla('fechas') && self::hayColumna('partidos', 'fecha_id')) {
            $q->leftJoin('fechas as fe', 'fe.id', '=', 'pa.fecha_id');
            $select[] = 'fe.numero as jornada';

            if (self::hayTabla('grupos') && self::hayTabla('torneos')) {
                $q->leftJoin('grupos as g', 'g.id', '=', 'fe.grupo_id')
                  ->leftJoin('torneos as t', 't.id', '=', 'g.torneo_id');
                $select[] = 't.nombre as torneo';
                $select[] = 't.year as year';
            }
        }

        if (self::hayTabla('equipos') && self::hayColumna('partidos', 'equipol_id')) {
            $q->leftJoin('equipos as el', 'el.id', '=', 'pa.equipol_id')
              ->leftJoin('equipos as ev', 'ev.id', '=', 'pa.equipov_id');
            $select[] = 'el.nombre as local';
            $select[] = 'ev.nombre as visitante';
        }

        $cabeceras = $q->select($select)->get()->keyBy('id');

        // Qué cuelga de la ficha en cada partido, una consulta por tabla hija.
        $etiquetas = [];
        $filas     = [];

        foreach (self::hijosPorPartido($base) as $hijo) {
            $cols = ['partido_id'];
            if (self::hayColumna($hijo, 'tipo')) {
                $cols[] = 'tipo';
            }

            $agrupado = DB::table($hijo)
                ->select(array_merge($cols, [DB::raw('COUNT(*) as n')]))
                ->whereIn($fk, $fichasOrigen)
                ->whereIn('partido_id', $ids)
                ->groupBy($cols)
                ->get();

            foreach ($agrupado as $f) {
                $pid = (int) $f->partido_id;
                $n   = (int) $f->n;

                $etiquetas[$pid][] = self::etiqueta($hijo, isset($f->tipo) ? $f->tipo : null, $n);
                $filas[$pid]       = (isset($filas[$pid]) ? $filas[$pid] : 0) + $n;
            }
        }

        // Partidos donde el destino ya figura POR EL MISMO CLUB: esos sí se
        // unifican. El criterio tiene que ser el mismo que el de moverFilas
        // —choqueDe(), que incluye el equipo—; si acá se mirara solo el partido,
        // la pantalla prometería "se unifica" en casos donde después se hace un
        // UPDATE plano. (Los partidos donde el destino juega para OTRO equipo ya
        // ni llegan hasta acá: permitidos() bloquea los del rival y también los
        // que el destino tiene sin equipo cargado.)
        $choques = [];
        if ($fichasDestino) {
            foreach ($base['pivotesConEquipo'] as $hijo) {
                if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)) {
                    continue;
                }

                $q = DB::table($hijo)->whereIn($fk, $fichasDestino)->whereIn('partido_id', $ids);
                if (self::hayColumna($hijo, 'equipo_id')) {
                    $q->whereIn('equipo_id', $equipoIds);
                }

                foreach ($q->distinct()->pluck('partido_id') as $id) {
                    $choques[(int) $id] = true;
                }
            }
        }

        $salida = [];
        foreach ($ids as $id) {
            $c = $cabeceras->get($id);

            $salida[] = [
                'id'         => (int) $id,
                'dia'        => $c && isset($c->dia) ? $c->dia : null,
                'jornada'    => $c && isset($c->jornada) ? $c->jornada : null,
                'torneo'     => $c && isset($c->torneo) ? $c->torneo : null,
                'year'       => $c && isset($c->year) ? $c->year : null,
                'local'      => $c && isset($c->local) ? $c->local : null,
                'visitante'  => $c && isset($c->visitante) ? $c->visitante : null,
                'resultado'  => $c && isset($c->golesl) && $c->golesl !== null && isset($c->golesv) && $c->golesv !== null
                    ? $c->golesl . '-' . $c->golesv
                    : null,
                'detalle'    => isset($etiquetas[$id]) ? $etiquetas[$id] : [],
                'filas'      => isset($filas[$id]) ? $filas[$id] : 0,
                'choca'      => array_key_exists((int) $id, $choques),
                // Sin alineación cargada, que el club esté en el partido no
                // prueba que haya jugado PARA el club: pudo jugar en contra.
                'dudoso'     => array_key_exists((int) $id, $dudoso),
            ];
        }

        usort($salida, function ($a, $b) {
            return strcmp((string) $a['dia'], (string) $b['dia']);
        });

        return $salida;
    }

    /** Texto corto de una tabla hija: "Titular", "2 goles", "Amarilla". */
    private static function etiqueta(string $hijo, $tipo, int $n): string
    {
        switch ($hijo) {
            case 'alineacions':
                return $tipo ? $tipo : 'En la planilla';
            case 'gols':
                return $n === 1 ? '1 gol' . ($tipo ? ' (' . mb_strtolower($tipo) . ')' : '')
                                : $n . ' goles' . ($tipo ? ' (' . mb_strtolower($tipo) . ')' : '');
            case 'tarjetas':
                return $n === 1 ? (string) $tipo : $n . ' ' . mb_strtolower((string) $tipo);
            case 'cambios':
                $texto = $tipo === 'Entra' ? 'Entró' : ($tipo === 'Sale' ? 'Salió' : (string) $tipo);
                return $n === 1 ? $texto : $n . ' ' . mb_strtolower($texto);
            case 'penals':
                return 'Penal' . ($tipo ? ' ' . mb_strtolower($tipo) : '') . ($n > 1 ? ' x' . $n : '');
            case 'partido_tecnicos':
                return 'Dirigió';
            default:
                return $n . ' en ' . $hijo;
        }
    }

    // ------------------------------------------------------------------
    // Ejecución
    // ------------------------------------------------------------------

    /**
     * Mueve las plantillas y los partidos elegidos de la ficha de origen a la de
     * destino, en una transacción.
     *
     * Lo que llega del formulario NO se usa como viene: la lista blanca se vuelve
     * a calcular DENTRO de la transacción, con las fichas bloqueadas, y solo
     * sobrevive la intersección. Así un id escrito a mano no puede sacar un
     * partido de otro club.
     *
     * Lo que esto NO garantiza: la lista blanca son ids de PARTIDO, no de fila.
     * Un gol insertado en un partido del tramo entre que se abrió la pantalla y
     * se apretó el botón se mueve igual, sin haberse mostrado. Es lo correcto
     * —ese gol pertenece al partido que se está moviendo— pero el "N registros"
     * de la previsualización puede no coincidir con el del resultado.
     *
     * La ficha de origen NUNCA se borra, ni aunque quede sin un solo registro:
     * eso lo decide después la pestaña "Sin registros", con su propio conteo
     * bajo lock.
     */
    public static function mover(
        int $personaOrigen,
        int $personaDestino,
        string $rol,
        array $equipoIds,
        array $plantillaIds,
        array $partidoIds,
        string $etiqueta = ''
    ): array {
        // Valida el par, el rol y los equipos, y da el mensaje de error bueno.
        $previo = self::previsualizar($personaOrigen, $personaDestino, $rol, $equipoIds);
        if (!$previo['ok']) {
            return $previo;
        }

        $base      = self::base($rol);
        $tabla     = $base['tabla'];
        $fk        = $base['fk'];
        $equipoIds = array_values(array_unique(array_filter(array_map('intval', $equipoIds))));

        $pedidasPl = array_values(array_unique(array_filter(array_map('intval', $plantillaIds))));
        $pedidosPa = array_values(array_unique(array_filter(array_map('intval', $partidoIds))));

        if (!$pedidasPl && !$pedidosPa) {
            return self::error('No se eligió ningún registro para mover.');
        }

        $detalle     = [];
        $filas       = 0;
        $committeado = false;

        try {
            DB::beginTransaction();

            $fichasOrigen = DB::table($tabla)->where('persona_id', $personaOrigen)
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();

            if (!$fichasOrigen) {
                DB::rollBack();
                return self::error("La persona #{$personaOrigen} ya no tiene ficha de {$rol}.");
            }

            $fichasDestino = DB::table($tabla)->where('persona_id', $personaDestino)
                ->orderBy('id')->lockForUpdate()->pluck('id')->all();

            // Recalculado bajo lock: esta es la lista que manda, no la del POST.
            $permitidos   = self::permitidos($base, $fichasOrigen, $equipoIds, $fichasDestino);
            $permitidasPl = array_flip($permitidos['plantillas']);
            $permitidosPa = array_flip($permitidos['partidos']);

            $plantillaIds = array_values(array_filter($pedidasPl, function ($id) use ($permitidasPl) {
                return array_key_exists($id, $permitidasPl);
            }));
            $partidoIds = array_values(array_filter($pedidosPa, function ($id) use ($permitidosPa) {
                return array_key_exists($id, $permitidosPa);
            }));

            $descartados = (count($pedidasPl) - count($plantillaIds)) + (count($pedidosPa) - count($partidoIds));

            if (!$plantillaIds && !$partidoIds) {
                DB::rollBack();
                return self::error(
                    'No quedó ningún registro para mover: ninguno de los tildados pertenece a ese club '
                    . 'en esta ficha. Volvé a abrir la pantalla, los datos cambiaron.'
                );
            }

            if ($descartados > 0) {
                $detalle[] = "{$descartados} tildados se ignoraron: ya no pertenecen a este tramo";
            }

            if (!$fichasDestino) {
                $nueva           = self::crearFicha($base, $personaDestino, (int) $fichasOrigen[0]);
                $fichasDestino[] = $nueva;
                $detalle[]       = "{$tabla} #{$nueva}: ficha creada para la persona {$personaDestino} "
                    . '(se copiaron del origen los campos obligatorios: revisá puesto y datos)';
            }

            $fichaDestino = (int) $fichasDestino[0];

            if (in_array($fichaDestino, array_map('intval', $fichasOrigen), true)) {
                throw new \RuntimeException('El origen y el destino son la misma ficha.');
            }

            foreach (array_keys($base['hijos']) as $hijo) {
                if (in_array($hijo, FusionPersonas::hijosQueNoSonRegistro(), true)) {
                    continue;
                }
                if (!self::hayTabla($hijo) || !self::hayColumna($hijo, $fk)) {
                    continue;
                }

                // `partido_id` gana siempre que exista: es el mismo orden con el
                // que se contaron las filas en la previsualización. Al revés, una
                // tabla con las dos columnas se mostraría dentro del partido y se
                // movería con el plantel.
                if (self::hayColumna($hijo, 'partido_id')) {
                    $columna = 'partido_id';
                    $ids     = $partidoIds;
                } elseif (self::hayColumna($hijo, 'plantilla_id')) {
                    $columna = 'plantilla_id';
                    $ids     = $plantillaIds;
                } else {
                    // Estadísticas manuales, ciclos: no cuelgan de un partido ni
                    // de un plantel, así que no hay forma de saber qué parte de
                    // ellas es de este tramo. Se quedan donde están, a la vista.
                    continue;
                }

                if (!$ids) {
                    continue;
                }

                $r = self::moverFilas(
                    $hijo,
                    $fk,
                    self::choqueDe($hijo, $base),
                    $fichasOrigen,
                    $fichasDestino,
                    $fichaDestino,
                    $columna,
                    $ids,
                    in_array($hijo, $base['pivotesConEquipo'], true)
                );

                if ($r['movidas'] || $r['unificadas']) {
                    $detalle[] = "{$hijo}: {$r['movidas']} movidas"
                        . ($r['unificadas'] ? ", {$r['unificadas']} repetidas unificadas" : '');
                    $filas += $r['movidas'] + $r['unificadas'];
                }

                if ($r['omitidas']) {
                    $detalle[] = "{$hijo}: {$r['omitidas']} filas NO se movieron "
                        . '(les falta el dato que identifica el club o el plantel y hay más de una fila '
                        . 'posible en el destino: hay que resolverlas a mano)';
                }
            }

            if (self::hayTabla('persona_movimientos')) {
                DB::table('persona_movimientos')->insert([
                    'persona_origen_id'  => $personaOrigen,
                    'persona_destino_id' => $personaDestino,
                    'ficha_origen_id'    => $fichasOrigen[0],
                    'ficha_destino_id'   => $fichaDestino,
                    'rol'                => $rol,
                    'etiqueta'           => mb_substr($etiqueta !== '' ? $etiqueta : implode(', ', $previo['equipos']), 0, 191),
                    'plantillas'         => count($plantillaIds),
                    'partidos'           => count($partidoIds),
                    'filas'              => $filas,
                    'detalle'            => json_encode([
                        'equipos'    => $equipoIds,
                        'plantillas' => $plantillaIds,
                        'partidos'   => $partidoIds,
                        'acciones'   => $detalle,
                    ], JSON_UNESCAPED_UNICODE),
                    'user_id'            => Auth::id(),
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            DB::commit();
            $committeado = true;

        } catch (\Throwable $e) {
            // Igual que en FusionPersonas: si el que falló fue el propio commit,
            // la transacción ya no existe y un rollBack acá tira una segunda
            // excepción DESDE el catch, que sale del método sin el array que el
            // controller espera.
            if (!$committeado) {
                DB::rollBack();
            }

            Log::error('Traspaso parcial de carrera fallido', [
                'origen'  => $personaOrigen,
                'destino' => $personaDestino,
                'rol'     => $rol,
                'error'   => $e->getMessage(),
            ]);

            return [
                'ok'      => false,
                'mensaje' => 'No se pudo mover: ' . $e->getMessage(),
                'detalle' => $detalle,
            ];
        }

        return [
            'ok'      => true,
            'mensaje' => "Se movieron {$filas} registros ("
                . count($plantillaIds) . ' plantillas, ' . count($partidoIds) . ' partidos) '
                . "de la persona #{$personaOrigen} a la #{$personaDestino}.",
            'detalle' => $detalle,
            'filas'   => $filas,
        ];
    }

    /**
     * Mueve las filas de una tabla hija resolviendo los choques igual que la
     * fusión: si el destino ya tiene una fila para el mismo hecho, se borra la
     * del origen y se completan los campos vacíos de la que queda (así no se
     * pierde, por ejemplo, el dorsal que solo tenía una).
     *
     * "El mismo hecho" lo define choqueDe(), no la fusión: incluye el equipo y el
     * plantel. Sin eso, el partido que las dos fichas jugaron una contra la otra
     * terminaría con una sola alineación y el equipo cambiado. Y cuando falta el
     * dato que identifica al club, la fila no se unifica ni se mueve: se cuenta
     * en `omitidas` y se avisa.
     *
     * El choque se busca contra TODAS las fichas del destino, no solo contra la
     * que recibe: si la fila repetida cuelga de una segunda ficha de la misma
     * persona, unificar ahí es correcto y mover a ciegas sería duplicar.
     */
    private static function moverFilas(
        string $hijo,
        string $fk,
        array $choque,
        array $fichasOrigen,
        array $fichasDestino,
        int $fichaDestino,
        string $columna,
        array $ids,
        bool $vetada = false
    ): array {
        $colsChoque = array_values(array_filter($choque, function ($c) use ($hijo) {
            return self::hayColumna($hijo, $c);
        }));

        $filas = DB::table($hijo)
            ->whereIn($fk, $fichasOrigen)
            ->whereIn($columna, $ids)
            ->get();

        $movidas    = 0;
        $unificadas = 0;
        $omitidas   = 0;

        foreach ($filas as $fila) {
            $gana    = null;
            $ambigua = false;

            if ($colsChoque) {
                // Una columna de choque en NULL no se compara: dos NULL no son el
                // mismo hecho. (Laravel traduce where($col, null) a IS NULL, que
                // es justo lo contrario de lo que hace falta acá.) Pero tampoco
                // se puede saltear el choque sin más: si la tabla tuviera un
                // único (ficha, partido), el UPDATE plano lo rompe y se cae todo
                // el traspaso. Entonces se compara por las columnas que SÍ tienen
                // valor, y si esa comparación parcial deja una sola candidata, se
                // unifica; si deja dos, no hay forma de elegir y se mueve.
                $usables = [];
                foreach ($colsChoque as $c) {
                    if (property_exists($fila, $c) && $fila->$c !== null) {
                        $usables[] = $c;
                    }
                }

                $completa = count($usables) === count($colsChoque);

                if ($usables) {
                    $q = DB::table($hijo)->whereIn($fk, $fichasDestino);
                    foreach ($usables as $c) {
                        $q->where($c, $fila->$c);
                    }

                    // orderBy explícito: sin él, con dos candidatas la que gana
                    // depende de lo que devuelva MySQL esa vez.
                    $candidatas = $q->orderBy('id')->limit(2)->get();

                    if ($completa) {
                        $gana = count($candidatas) ? $candidatas[0] : null;
                    } elseif ($vetada && count($candidatas) === 1) {
                        // Comparación incompleta. Solo se acepta en las tablas que
                        // permitidos() ya revisó partido por partido (alineacions,
                        // partido_tecnicos): ahí lo único que puede quedar en el
                        // destino para ese partido es una fila del MISMO club, así
                        // que unificar es correcto aunque falte el equipo.
                        $gana = $candidatas[0];
                    } else {
                        // Ninguna garantía: no se unifica y tampoco se mueve. Una
                        // fila de más se arregla; una fila borrada contra el hecho
                        // equivocado no se recupera.
                        $ambigua = true;
                    }
                } elseif (!$completa) {
                    $ambigua = true;
                }
            }

            if ($ambigua) {
                $omitidas++;
                continue;
            }

            if ($gana) {
                // Primero se borra la sobrante y después se completa la que
                // queda: mismo motivo que en la fusión, los índices únicos.
                DB::table($hijo)->where('id', $fila->id)->delete();

                $campos = array_values(array_diff(
                    FusionPersonas::columnas($hijo),
                    ['id', $fk, 'created_at', 'updated_at']
                ));

                $completar = FusionPersonas::completar($gana, $fila, $campos);
                if ($completar) {
                    DB::table($hijo)->where('id', $gana->id)->update($completar);
                }

                $unificadas++;
                continue;
            }

            DB::table($hijo)->where('id', $fila->id)->update([$fk => $fichaDestino]);
            $movidas++;
        }

        return ['movidas' => $movidas, 'unificadas' => $unificadas, 'omitidas' => $omitidas];
    }

    /**
     * Crea la ficha de rol del destino cuando no existe.
     *
     * `jugadors` tiene enums NOT NULL sin default (tipoJugador, pie,
     * tipoDocumento) y `tecnicos` tiene tipoDocumento: con MySQL en modo estricto
     * el INSERT sin ellos falla. Las columnas obligatorias se leen del esquema en
     * vez de hardcodearlas —la lista buena para `jugadors` no es la de
     * `tecnicos`—, del origen se copian SOLO las descriptivas (puesto, pie, tipo
     * de documento) y el resto se completa con un valor neutro: el documento y
     * los links son de la otra persona. El aviso viaja en el detalle.
     */
    private static function crearFicha(array $base, int $personaId, int $fichaModelo): int
    {
        $tabla  = $base['tabla'];
        $modelo = DB::table($tabla)->where('id', $fichaModelo)->first();

        $fila       = ['persona_id' => $personaId];
        $reservadas = ['id', 'persona_id', 'created_at', 'updated_at'];

        foreach (self::obligatorias($tabla) as $campo => $tipo) {
            if (in_array($campo, $reservadas, true)) {
                continue;
            }

            // Solo se copian los campos DESCRIPTIVOS del tramo que se está
            // moviendo: el puesto y el pie describen a quien jugó esos partidos,
            // que es justamente la persona de destino. Todo lo demás que sea
            // obligatorio (documento, slug, url, transfermarkt) identifica a OTRA
            // persona: copiarlo le pondría a #291 el documento de #3160, y con un
            // índice único encima haría fallar el INSERT.
            if (in_array($campo, self::$camposDescriptivos, true)
                && $modelo && property_exists($modelo, $campo) && $modelo->$campo !== null) {
                $fila[$campo] = $modelo->$campo;
                continue;
            }

            // Sin tipo no se inventa nada: escribir '' en un enum lo trunca en
            // silencio. Que falle el INSERT y se vea el error es mejor.
            if ((string) $tipo === '') {
                continue;
            }

            $fila[$campo] = self::valorNeutro($tipo);
        }

        foreach (['created_at', 'updated_at'] as $campo) {
            if (self::hayColumna($tabla, $campo)) {
                $fila[$campo] = now();
            }
        }

        return (int) DB::table($tabla)->insertGetId($fila);
    }

    /**
     * Campos obligatorios que SÍ se copian del origen: describen el tramo que se
     * mueve, no a la persona que lo tenía mal asignado.
     */
    private static $camposDescriptivos = ['tipoJugador', 'pie', 'tipoDocumento'];

    /**
     * Columnas NOT NULL sin valor por defecto: las que hacen fallar un INSERT
     * incompleto. Si SHOW COLUMNS no está disponible se cae a la lista conocida.
     *
     * @return array [columna => tipo SQL]
     */
    private static function obligatorias(string $tabla): array
    {
        try {
            $columnas = DB::select('SHOW COLUMNS FROM `' . DB::getTablePrefix() . $tabla . '`');
        } catch (\Throwable $e) {
            $salida = [];
            foreach (self::$camposDescriptivos as $c) {
                if (self::hayColumna($tabla, $c)) {
                    $salida[$c] = '';
                }
            }
            return $salida;
        }

        $salida = [];
        foreach ($columnas as $c) {
            $nulo    = isset($c->Null) ? $c->Null : (isset($c->null) ? $c->null : 'YES');
            $default = isset($c->Default) ? $c->Default : (isset($c->default) ? $c->default : null);
            $extra   = isset($c->Extra) ? $c->Extra : (isset($c->extra) ? $c->extra : '');
            $campo   = isset($c->Field) ? $c->Field : (isset($c->field) ? $c->field : null);
            $tipo    = isset($c->Type) ? $c->Type : (isset($c->type) ? $c->type : '');

            if (!$campo || $nulo !== 'NO' || $default !== null || strpos($extra, 'auto_increment') !== false) {
                continue;
            }
            $salida[$campo] = (string) $tipo;
        }

        return $salida;
    }

    /**
     * Un valor que MySQL acepte para una columna obligatoria sin inventar un dato
     * de nadie: la primera opción del enum, 0 para los números, una fecha mínima
     * para las fechas, cadena vacía para el resto.
     */
    private static function valorNeutro(string $tipo)
    {
        $tipo = trim($tipo);
        if ($tipo === '') {
            return '';
        }

        // Se despacha por el tipo BASE, no por substring: 'point' contiene 'int',
        // y tinyint/bigint/smallint no empiezan con 'int'.
        preg_match('/^([a-zA-Z]+)/', $tipo, $m);
        $familia = isset($m[1]) ? mb_strtolower($m[1]) : '';

        // El valor del enum se devuelve TAL CUAL: con una collation _bin o _cs,
        // 'delantero' no es 'Delantero'.
        if ($familia === 'enum') {
            $abre   = strpos($tipo, '(');
            $lista  = substr($tipo, $abre + 1, strrpos($tipo, ')') - $abre - 1);
            $partes = str_getcsv($lista, ',', "'");

            return isset($partes[0]) ? $partes[0] : '';
        }

        // En un SET el conjunto vacío es válido, y es el neutro de verdad.
        if ($familia === 'set') {
            return '';
        }

        // En modo estricto la cadena vacía no sirve para un número ni para una
        // fecha: son los errores 1366 y 1292, que voltean el INSERT entero.
        $numericos = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
                      'decimal', 'numeric', 'float', 'double', 'real', 'bit', 'year'];
        if (in_array($familia, $numericos, true)) {
            return 0;
        }

        if ($familia === 'date') {
            return '1970-01-01';
        }
        if ($familia === 'datetime' || $familia === 'timestamp') {
            return '1970-01-01 00:00:00';
        }
        if ($familia === 'time') {
            return '00:00:00';
        }
        if ($familia === 'json') {
            return '{}';
        }

        return '';
    }

    // ------------------------------------------------------------------

    private static function error(string $mensaje): array
    {
        return ['ok' => false, 'mensaje' => $mensaje, 'detalle' => []];
    }

    private static function hayTabla(string $tabla): bool
    {
        $k = 't:' . $tabla;
        if (!array_key_exists($k, self::$esquema)) {
            self::$esquema[$k] = Schema::hasTable($tabla);
        }

        return self::$esquema[$k];
    }

    private static function hayColumna(string $tabla, string $columna): bool
    {
        $k = 'c:' . $tabla . '.' . $columna;
        if (!array_key_exists($k, self::$esquema)) {
            self::$esquema[$k] = self::hayTabla($tabla) && Schema::hasColumn($tabla, $columna);
        }

        return self::$esquema[$k];
    }
}
