<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Detección de personas repetidas.
 *
 * Idea central: no comparar todos contra todos (14.000 personas serían 98
 * millones de pares) sino "bloquear" por apellido usando el índice invertido
 * `persona_tokens`. Solo se puntúan los pares que comparten al menos una
 * palabra de apellido; el resto ni se toca.
 *
 * Todo el trabajo pesado pasa acá, una vez, fuera de la pantalla.
 */
class DuplicadosPersonas
{
    /** Puntaje mínimo (0-100) para que un par se guarde como candidato. */
    public const UMBRAL = 70;

    /**
     * Un apellido que aparece en más personas que esto no sirve para bloquear
     * (generaría cientos de miles de pares inútiles). Se avisa cuáles se saltearon.
     */
    public const MAX_POR_TOKEN = 500;

    /** Cuántas personas se procesan por lote al reconstruir las claves. */
    private const LOTE = 500;

    private const ACENTOS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
        'š' => 's', 'ś' => 's', 'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
        'č' => 'c', 'ć' => 'c', 'ď' => 'd', 'đ' => 'd', 'ł' => 'l',
        'ř' => 'r', 'ť' => 't', 'ů' => 'u', 'ě' => 'e', 'ğ' => 'g', 'ı' => 'i',
    ];

    /**
     * Partículas que unen apellidos. No se usan para bloquear ni para puntuar,
     * porque "De la Cruz" y "Cruz" tienen que poder encontrarse.
     * OJO: no se tocan "san", "santa", "mac" ni "mc": ahí sí son parte del apellido.
     */
    private const PARTICULAS = [
        'de', 'del', 'da', 'do', 'dos', 'das', 'di', 'della', 'dello',
        'la', 'las', 'los', 'le', 'les', 'lo',
        'van', 'von', 'der', 'den', 'ter', 'du', 'des',
        'y', 'e', 'bin', 'ibn', 'el', 'al',
    ];

    // ------------------------------------------------------------------
    // Normalización
    // ------------------------------------------------------------------

    /** Minúsculas, sin acentos, sin puntuación, sin espacios de más. */
    public static function normalizar($texto): string
    {
        $t = mb_strtolower(trim((string) $texto), 'UTF-8');

        // Lo que va entre paréntesis no forma parte del nombre de la persona
        // (ej. "Rodríguez (h)", "Gómez (arquero)").
        $t = preg_replace('/\s*\(.*?\)\s*/u', ' ', $t);

        $t = strtr($t, self::ACENTOS);

        // Apóstrofes y comillas se pegan: d'alessandro -> dalessandro.
        $t = str_replace(["'", '’', '‘', '`', '´'], '', $t);

        // Lo que quedó fuera de la tabla de acentos (polaco, checo, cirílico...)
        // se translitera; si no, se convertiría en separador y partiría el token.
        if (preg_match('/[^\x20-\x7E]/', $t)) {
            $conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t);
            if ($conv !== false && $conv !== '') {
                $t = mb_strtolower($conv, 'UTF-8');
            }
        }

        // Todo lo demás que no sea letra o número pasa a ser separador.
        $limpio = preg_replace('/[^a-z0-9]+/u', ' ', $t);
        if ($limpio === null) {
            // texto no válido en UTF-8: se reintenta sin el modificador /u
            $limpio = preg_replace('/[^a-z0-9]+/', ' ', $t);
        }

        return trim(preg_replace('/\s+/', ' ', (string) $limpio));
    }

    /** Tokens útiles de un campo: sin partículas y sin iniciales sueltas. */
    public static function tokenizar($texto): array
    {
        $tokens = [];
        foreach (explode(' ', self::normalizar($texto)) as $t) {
            if ($t === '' || mb_strlen($t) < 2) {
                continue; // "j." no sirve para bloquear
            }
            if (in_array($t, self::PARTICULAS, true)) {
                continue;
            }
            $tokens[$t] = $t;
        }

        return array_values($tokens);
    }

    /**
     * Las dos claves indexadas de una persona.
     *  - clave_norm : "juan carlos perez"  (orden natural)
     *  - clave_orden: "carlos juan perez"  (tokens ordenados alfabéticamente)
     * La segunda hace que nombre y apellido invertidos, o los nombres en distinto
     * orden, den exactamente la misma clave.
     */
    public static function claves($nombre, $apellido): array
    {
        $norm = trim(self::normalizar($nombre) . ' ' . self::normalizar($apellido));
        $norm = trim(preg_replace('/\s+/', ' ', $norm));

        $partes = array_values(array_filter(explode(' ', $norm), function ($p) {
            return $p !== '';
        }));
        sort($partes);

        return [
            'clave_norm'  => mb_substr($norm, 0, 191),
            'clave_orden' => mb_substr(implode(' ', $partes), 0, 191),
        ];
    }

    // ------------------------------------------------------------------
    // Reconstrucción de claves y tokens
    // ------------------------------------------------------------------

    /**
     * Recalcula clave_norm, clave_orden y los tokens de una sola persona.
     * Es lo que se llama al crear o editar una persona: cuesta 3 consultas.
     */
    public static function indexarPersona($persona): void
    {
        $id = is_object($persona) ? $persona->id : (int) $persona;
        if (!$id) {
            return;
        }

        $fila = DB::table('personas')->select('id', 'nombre', 'apellido')->find($id);
        if (!$fila) {
            DB::table('persona_tokens')->where('persona_id', $id)->delete();
            return;
        }

        $claves = self::claves($fila->nombre, $fila->apellido);
        DB::table('personas')->where('id', $id)->update($claves);

        DB::table('persona_tokens')->where('persona_id', $id)->delete();

        $filas = [];
        foreach (self::tokensDe($fila->nombre, $fila->apellido) as $campo => $lista) {
            foreach ($lista as $token) {
                $filas[] = ['persona_id' => $id, 'token' => mb_substr($token, 0, 64), 'campo' => $campo];
            }
        }
        if ($filas) {
            DB::table('persona_tokens')->insertOrIgnore($filas);
        }
    }

    /**
     * Tokens de una persona separados por campo.
     *
     * Detalle importante: si el apellido está vacío (pasa seguido, con todo
     * cargado en `nombre`), los tokens del nombre se indexan TAMBIÉN como
     * apellido. Si no, esa persona no entra en ningún bloque y nunca se puede
     * detectar como repetida.
     */
    public static function tokensDe($nombre, $apellido): array
    {
        $n = self::tokenizar($nombre);
        $a = self::tokenizar($apellido);

        if (!$a) {
            $a = $n;
        } elseif (!$n) {
            $n = $a;
        }

        return ['n' => $n, 'a' => $a];
    }

    /**
     * Reconstruye las claves y el índice de tokens de TODAS las personas.
     * Va por lotes y actualiza con un solo UPDATE ... CASE por lote, así son
     * unas pocas decenas de consultas en vez de una por persona.
     *
     * @param callable|null $avisar function(int $procesadas, int $total)
     */
    public static function reconstruirIndice($avisar = null): int
    {
        DB::table('persona_tokens')->truncate();

        $total       = (int) DB::table('personas')->count();
        $procesadas  = 0;
        $ultimoId    = 0;

        while (true) {
            $personas = DB::table('personas')
                ->select('id', 'nombre', 'apellido')
                ->where('id', '>', $ultimoId)
                ->orderBy('id')
                ->limit(self::LOTE)
                ->get();

            if ($personas->isEmpty()) {
                break;
            }

            $ids        = [];
            $casoNorm   = '';
            $casoOrden  = '';
            $tokens     = [];
            $parametros = [];

            foreach ($personas as $p) {
                $ultimoId = $p->id;
                $ids[]    = $p->id;

                $claves = self::claves($p->nombre, $p->apellido);

                $casoNorm  .= ' WHEN ? THEN ?';
                $casoOrden .= ' WHEN ? THEN ?';
                $parametros['norm'][]  = [$p->id, $claves['clave_norm']];
                $parametros['orden'][] = [$p->id, $claves['clave_orden']];

                foreach (self::tokensDe($p->nombre, $p->apellido) as $campo => $lista) {
                    foreach ($lista as $token) {
                        $tokens[] = [
                            'persona_id' => $p->id,
                            'token'      => mb_substr($token, 0, 64),
                            'campo'      => $campo,
                        ];
                    }
                }
            }

            // Un solo UPDATE por lote.
            $bind = [];
            foreach ($parametros['norm'] as $par) {
                $bind[] = $par[0];
                $bind[] = $par[1];
            }
            foreach ($parametros['orden'] as $par) {
                $bind[] = $par[0];
                $bind[] = $par[1];
            }
            $listaIds = implode(',', array_map('intval', $ids));

            DB::update(
                "UPDATE personas
                    SET clave_norm  = CASE id{$casoNorm} END,
                        clave_orden = CASE id{$casoOrden} END
                  WHERE id IN ({$listaIds})",
                $bind
            );

            foreach (array_chunk($tokens, 1000) as $trozo) {
                DB::table('persona_tokens')->insertOrIgnore($trozo);
            }

            $procesadas += count($ids);
            if ($avisar) {
                $avisar($procesadas, $total);
            }
        }

        return $procesadas;
    }

    // ------------------------------------------------------------------
    // Generación de candidatos
    // ------------------------------------------------------------------

    /**
     * Recalcula la tabla `persona_duplicados`.
     *
     * Respeta lo ya resuelto: un par marcado como `descartado` conserva su
     * estado (el ON DUPLICATE KEY UPDATE no toca esa columna), así que no
     * vuelve a aparecer. Los `pendiente` que dejaron de calificar se borran.
     * Los pares fusionados no existen más: la fusión los elimina y deja el
     * rastro en `persona_fusiones`.
     *
     * @return array resumen con contadores
     */
    public static function recalcular(int $umbral = self::UMBRAL, $avisar = null): array
    {
        $umbral = max(1, min(100, $umbral));
        $inicio = now();

        // GROUP_CONCAT por defecto corta en 1024 caracteres: un apellido muy
        // repetido perdería personas en silencio.
        try {
            DB::statement('SET SESSION group_concat_max_len = 1000000');
        } catch (\Exception $e) {
            // motor sin soporte: se sigue igual, los grupos grandes se saltean abajo
        }

        $personas = self::cargarPersonas();

        $pares      = [];   // "menor-mayor" => [puntaje, motivo]
        $saltados   = [];   // grupos demasiado grandes para comparar todos contra todos
        $guardados  = 0;

        // Volcar cada tanto evita que $pares crezca sin control (y que se caiga
        // por memoria dejando el trabajo a medias sin nada persistido).
        $volcar = function (&$pares, $forzar = false) use (&$guardados) {
            if ($forzar || count($pares) >= 20000) {
                $guardados += self::guardarPares($pares);
                $pares = [];
            }
        };

        // --- Bloque A: misma clave ordenada. Es el caso más fuerte y el más barato.
        $grupos = DB::table('personas')
            ->select('clave_orden', DB::raw('GROUP_CONCAT(id) as ids'), DB::raw('COUNT(*) as n'))
            ->whereNotNull('clave_orden')
            ->where('clave_orden', '<>', '')
            ->groupBy('clave_orden')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($grupos as $grupo) {
            // Un grupo enorme (típico: decenas de personas cargadas como "NN" o
            // "Sin Nombre") daría n²/2 pares, todos con puntaje 100. No sirven
            // para nada y tapan la pantalla.
            if ($grupo->n > self::MAX_POR_TOKEN) {
                $saltados[] = '"' . $grupo->clave_orden . '" (' . $grupo->n . ' personas con el mismo nombre)';
                continue;
            }
            $ids = array_map('intval', explode(',', $grupo->ids));
            self::acumularPares($ids, $personas, $pares, $umbral);
            $volcar($pares);
        }

        // --- Bloque B: comparten al menos una palabra de apellido.
        $tokens = DB::table('persona_tokens')
            ->select('token', DB::raw('COUNT(*) as n'))
            ->where('campo', 'a')
            ->groupBy('token')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('token')
            ->get();

        $procesados = 0;
        foreach ($tokens as $t) {
            $procesados++;

            if ($t->n > self::MAX_POR_TOKEN) {
                $saltados[] = $t->token . ' (' . $t->n . ' personas)';
                continue;
            }

            $ids = DB::table('persona_tokens')
                ->where('campo', 'a')
                ->where('token', $t->token)
                ->pluck('persona_id')
                ->map(function ($v) { return (int) $v; })
                ->all();

            self::acumularPares($ids, $personas, $pares, $umbral);
            $volcar($pares);

            if ($avisar && $procesados % 200 === 0) {
                $avisar($procesados, count($tokens), $guardados + count($pares));
            }
        }

        // El estado de los pares ya resueltos (descartado) se conserva: el
        // ON DUPLICATE KEY UPDATE de guardarPares() no toca la columna `estado`.
        $volcar($pares, true);

        // Los pendientes que ya no califican (porque se corrigió un nombre, se
        // fusionó, o subió el umbral) dejan de aparecer.
        $borrados = DB::table('persona_duplicados')
            ->where('estado', 'pendiente')
            ->where('updated_at', '<', $inicio)
            ->delete();

        // Higiene: pares que apuntan a personas que ya no existen.
        $prefijo = DB::getTablePrefix();
        DB::statement("
            DELETE d FROM `{$prefijo}persona_duplicados` d
            LEFT JOIN `{$prefijo}personas` p1 ON p1.id = d.persona_id
            LEFT JOIN `{$prefijo}personas` p2 ON p2.id = d.simil_id
            WHERE p1.id IS NULL OR p2.id IS NULL
        ");

        return [
            'personas'  => count($personas),
            'pares'     => $guardados,
            'guardados' => $guardados,
            'borrados'  => $borrados,
            'saltados'  => $saltados,
            'umbral'    => $umbral,
        ];
    }

    /** Trae a memoria lo mínimo de cada persona para poder puntuar sin ir a la base. */
    private static function cargarPersonas(): array
    {
        $personas = [];

        DB::table('personas')
            ->select('id', 'nombre', 'apellido', 'clave_orden', 'nacimiento', 'fallecimiento', 'nacionalidad')
            ->orderBy('id')
            ->chunk(2000, function ($filas) use (&$personas) {
                foreach ($filas as $f) {
                    $tk = self::tokensDe($f->nombre, $f->apellido);
                    $personas[(int) $f->id] = [
                        'id'          => (int) $f->id,
                        'orden'       => (string) $f->clave_orden,
                        'n'           => $tk['n'],
                        'a'           => $tk['a'],
                        // union precalculada: puntuar() se llama millones de veces
                        't'           => array_values(array_unique(array_merge($tk['n'], $tk['a']))),
                        'nacimiento'  => $f->nacimiento ? substr($f->nacimiento, 0, 10) : null,
                        'nacion'      => self::normalizar($f->nacionalidad),
                        'roles'       => [],
                        'tm'          => null,
                    ];
                }
            });

        // Roles y URL de Transfermarkt: dos personas con URL de TM distinta casi
        // seguro NO son la misma persona, y eso baja mucho el puntaje.
        $mapa = [
            'jugadors' => 'jugador',
            'tecnicos' => 'tecnico',
            'arbitros' => 'arbitro',
        ];

        foreach ($mapa as $tabla => $rol) {
            if (!Schema::hasTable($tabla) || !Schema::hasColumn($tabla, 'persona_id')) {
                continue;
            }
            $tieneTm = Schema::hasColumn($tabla, 'transfermarkt_url');
            $cols    = $tieneTm ? ['persona_id', 'transfermarkt_url'] : ['persona_id'];

            DB::table($tabla)->select($cols)->orderBy('persona_id')
                ->chunk(5000, function ($filas) use (&$personas, $rol, $tieneTm) {
                    foreach ($filas as $f) {
                        $pid = (int) $f->persona_id;
                        if (!isset($personas[$pid])) {
                            continue;
                        }
                        $personas[$pid]['roles'][$rol] = true;
                        if ($tieneTm && !empty($f->transfermarkt_url) && empty($personas[$pid]['tm'])) {
                            $personas[$pid]['tm'] = trim($f->transfermarkt_url);
                        }
                    }
                });
        }

        return $personas;
    }

    /** Puntúa todas las combinaciones de un grupo y se queda con las que pasan el umbral. */
    private static function acumularPares(array $ids, array &$personas, array &$pares, int $umbral): void
    {
        sort($ids);
        $n = count($ids);

        for ($i = 0; $i < $n; $i++) {
            $a = $ids[$i];
            if (!isset($personas[$a])) {
                continue;
            }
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $ids[$j];
                if ($a === $b || !isset($personas[$b])) {
                    continue;
                }

                $clave = $a . '-' . $b;
                if (isset($pares[$clave])) {
                    continue; // ya lo puntuamos por otro token
                }

                $r = self::puntuar($personas[$a], $personas[$b]);
                if ($r['puntaje'] >= $umbral) {
                    $pares[$clave] = $r;
                }
            }
        }
    }

    /**
     * Puntaje 0-100 de que dos personas sean la misma.
     * Devuelve además el motivo, que es lo que se muestra en pantalla para que
     * se entienda por qué el par está ahí.
     */
    public static function puntuar(array $p1, array $p2): array
    {
        $tokA = isset($p1['t']) ? $p1['t'] : array_values(array_unique(array_merge($p1['n'], $p1['a'])));
        $tokB = isset($p2['t']) ? $p2['t'] : array_values(array_unique(array_merge($p2['n'], $p2['a'])));

        if (!$tokA || !$tokB) {
            return ['puntaje' => 0, 'motivo' => ''];
        }

        $base   = 0;
        $motivo = '';

        // ¿La señal del nombre es fuerte? (nombre idéntico, o uno contenido en
        // el otro). Lo necesita el castigo por fecha, más abajo.
        $nombreFuerte = false;

        if ($p1['orden'] !== '' && $p1['orden'] === $p2['orden']) {
            $base   = 100;
            $motivo = 'nombre idéntico';
            $nombreFuerte = true;
        } else {
            $inter = array_values(array_intersect($tokA, $tokB));
            $ni    = count($inter);

            if ($ni === 0) {
                return ['puntaje' => 0, 'motivo' => ''];
            }

            $union     = count(array_unique(array_merge($tokA, $tokB)));
            $jaccard   = $union ? $ni / $union : 0;
            $contenido = ($ni === count($tokA) || $ni === count($tokB));
            $apeComun  = count(array_intersect($p1['a'], $p2['a'])) > 0;

            if (!$apeComun) {
                // Comparten nombres de pila pero ningún apellido: casi nunca es lo mismo.
                $base   = (int) round(55 * $jaccard);
                $motivo = 'coinciden nombres, no el apellido';
            } elseif ($contenido) {
                // "Juan Pérez" contra "Juan Carlos Pérez".
                $base   = 88;
                $motivo = 'un nombre está contenido en el otro';
                $nombreFuerte = true;
            } else {
                $nInter = array_intersect($p1['n'], $p2['n']);
                if ($nInter) {
                    $base   = 82;
                    $motivo = 'mismo apellido y algún nombre en común';
                } else {
                    $ini1 = self::iniciales($p1['n']);
                    $ini2 = self::iniciales($p2['n']);
                    if ($ini1 && $ini2 && array_intersect($ini1, $ini2)) {
                        $base   = 70;
                        $motivo = 'mismo apellido y misma inicial de nombre';
                    } elseif (!$p1['n'] || !$p2['n']) {
                        $base   = 68;
                        $motivo = 'mismo apellido, uno sin nombre';
                    } else {
                        $base   = 45;
                        $motivo = 'mismo apellido, distinto nombre';
                    }
                }
            }
        }

        $extras = [];

        // Fecha de nacimiento: es el desempate más confiable que hay… salvo
        // cuando el nombre es la evidencia fuerte y la fecha es justamente el
        // dato dudoso.
        //
        // El castigo entero (−45) esconde los repetidos más obvios que hay:
        //
        //   Falcón Pérez, Yael / Pérez, Yael Falcón   100 − 45 =  55
        //   Gariano, Carlos Andrés / Gariano, Andrés   88 − 45 =  43
        //
        // Los dos quedaban abajo del umbral 70 y no aparecían nunca, y en los
        // dos la fecha era el dato malo (Transfermarkt las manda mal seguido).
        // Por eso, si el nombre es fuerte —idéntico o uno contenido en el
        // otro—, la resta se topea: el par sigue siendo visible y el motivo
        // avisa en mayúsculas que las fechas no coinciden, que es lo que hay
        // que mirar. Con nombres flojos ("mismo apellido, distinto nombre",
        // 45 de base) el castigo va entero y el par sigue afuera, que es lo
        // que evita que la pantalla se llene de homónimos.
        if ($p1['nacimiento'] && $p2['nacimiento']) {
            if ($p1['nacimiento'] === $p2['nacimiento']) {
                $base += 12;
                $extras[] = 'misma fecha de nacimiento';
            } else {
                $base -= $nombreFuerte ? 18 : 45;
                $extras[] = 'FECHAS DE NACIMIENTO DISTINTAS';
            }
        }

        // Transfermarkt: dos URLs distintas son dos personas distintas.
        if (!empty($p1['tm']) && !empty($p2['tm'])) {
            if ($p1['tm'] === $p2['tm']) {
                $base += 20;
                $extras[] = 'misma ficha de Transfermarkt';
            } else {
                $base -= 30;
                $extras[] = 'fichas de Transfermarkt distintas';
            }
        }

        // Nacionalidades distintas: señal débil (a veces está mal cargada).
        if ($p1['nacion'] && $p2['nacion'] && $p1['nacion'] !== $p2['nacion']) {
            $base -= 8;
        }

        // Mismo nombre en roles distintos (jugador que después fue DT, por ejemplo):
        // en la base tendrían que ser UNA sola persona con dos roles.
        $roles1 = array_keys($p1['roles']);
        $roles2 = array_keys($p2['roles']);
        if ($roles1 && $roles2 && !array_intersect($roles1, $roles2)) {
            $base += 6;
            $extras[] = 'roles distintos (' . implode('/', $roles1) . ' y ' . implode('/', $roles2) . ')';
        }

        $puntaje = max(0, min(100, (int) round($base)));

        if ($extras) {
            $motivo = trim($motivo . ' · ' . implode(' · ', $extras));
        }

        return ['puntaje' => $puntaje, 'motivo' => mb_substr($motivo, 0, 150)];
    }

    private static function iniciales(array $tokens): array
    {
        $r = [];
        foreach ($tokens as $t) {
            $r[] = mb_substr($t, 0, 1);
        }

        return array_values(array_unique($r));
    }

    /**
     * Guarda los pares sin pisar el estado de los ya resueltos.
     * ON DUPLICATE KEY UPDATE toca puntaje/motivo/updated_at y deja `estado` como está.
     */
    private static function guardarPares(array $pares): int
    {
        if (!$pares) {
            return 0;
        }

        $ahora     = now();
        $guardados = 0;
        $prefijo   = DB::getTablePrefix();

        foreach (array_chunk($pares, 500, true) as $trozo) {
            $valores = [];
            $bind    = [];

            foreach ($trozo as $clave => $datos) {
                list($a, $b) = explode('-', $clave);
                $valores[] = '(?, ?, ?, ?, ?, ?, ?)';
                $bind[] = (int) $a;
                $bind[] = (int) $b;
                $bind[] = $datos['puntaje'];
                $bind[] = $datos['motivo'];
                $bind[] = 'pendiente';
                $bind[] = $ahora;
                $bind[] = $ahora;
            }

            // `estado` queda deliberadamente fuera del UPDATE: si el usuario ya
            // marcó el par como "personas distintas", el recálculo no lo revive.
            DB::insert(
                "INSERT INTO `{$prefijo}persona_duplicados`
                    (persona_id, simil_id, puntaje, motivo, estado, created_at, updated_at)
                 VALUES " . implode(',', $valores) . "
                 ON DUPLICATE KEY UPDATE
                    puntaje    = VALUES(puntaje),
                    motivo     = VALUES(motivo),
                    updated_at = VALUES(updated_at)",
                $bind
            );

            $guardados += count($trozo);
        }

        return $guardados;
    }
}
