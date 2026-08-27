<?php

namespace App\Services;

use App\Http\Controllers\JugadorController;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa las fichas que no tienen fecha de nacimiento con el perfil de
 * Transfermarkt.
 *
 * Por qué existe: la fecha es el desempate de `DuplicadosPersonas::puntuar()`.
 * Una persona sin fecha no suma el +12 de "misma fecha" ni se lleva la resta de
 * "fechas distintas", así que queda flotando en el medio y ensucia la pantalla
 * de repetidos (los árbitros, que casi nunca traen fecha, son el caso típico).
 * Cada fecha que se completa es un par menos que hay que mirar a mano.
 *
 * Cuesta una llamada cada 50 personas: es la misma API y el mismo formato que
 * usa `TmDetallePartido` para resolver los jugadores de un partido
 * (`/players?ids[]=`, `/coaches?ids[]=`, `/referees?ids[]=`).
 *
 * De dónde sale el id de TM de cada quien:
 *   jugadores → `jugador_tm.tm_player_id`, y si no está, la URL de la ficha
 *               (`jugadors.transfermarkt_url`, .../profil/spieler/{id})
 *   técnicos  → `tecnicos.transfermarkt_url` (.../profil/trainer/{id})
 *   árbitros  → `arbitro_tm.tm_referee_id` (+ la URL, si la tabla la tiene)
 *
 * OJO con `jugador_tm`: las fusiones de personas no repuntan esa tabla, así que
 * puede tener filas apuntando a jugadores que ya no existen. Por eso todas las
 * consultas de acá salen DESDE `personas`/`jugadors` y usan `jugador_tm` como
 * LEFT JOIN: un id muerto no aparece nunca.
 *
 * Nunca pisa un dato cargado: escribe solo donde el campo está vacío. Es a
 * propósito — Transfermarkt manda fechas mal seguido, y lo que ya revisaste a
 * mano vale más que lo que diga la API.
 */
class TmFechas
{
    const TMAPI = 'https://tmapi.transfermarkt.technology';

    /** Cuántos ids entran en una llamada. Mismo tope que usa el importador. */
    const POR_LLAMADA = 50;

    /** Dónde queda anotado qué ya se consultó (ver la migración homónima). */
    const TABLA_INTENTOS = 'persona_fecha_tm';

    /**
     * Dónde quedan las que ya se buscaron en todos lados y no aparecen.
     *
     * Es una decisión humana, no el resultado de una consulta: por eso vive en
     * su propia tabla y no como un estado más de TABLA_INTENTOS. Si estuviera
     * ahí, el tilde "reintentar las ya consultadas" borraría de un saque todo
     * lo que alguien revisó a mano.
     */
    const TABLA_DESCARTADAS = 'persona_sin_fecha';

    /**
     * Todas las personas sin fecha de nacimiento, ordenadas por apellido.
     *
     * Devuelve [persona_id => ['tipo', 'rol_id', 'tm', 'apellido', 'nombre']],
     * con `tm` en null cuando no tenemos con qué ir a buscarla.
     *
     * Las marcadas a mano como "sin fecha conocida" quedan afuera: ya se
     * buscaron y no existen, así que no son trabajo pendiente ni tiene sentido
     * volver a preguntarlas. $incluirDescartadas las trae igual, que es lo que
     * necesita la pantalla para listarlas y poder deshacer la marca.
     */
    public static function pendientes(bool $incluirDescartadas = false): array
    {
        $out = self::fichas(null, true);

        if (!$incluirDescartadas && $out) {
            $out = array_diff_key($out, self::descartadas());
        }

        return $out;
    }

    /**
     * Las fichas de rol de un conjunto de personas, con el id de TM de cada una.
     *
     * Es el cuerpo que antes estaba metido adentro de `pendientes()`, sacado
     * afuera para que lo pueda usar cualquier pantalla que necesite preguntarle
     * algo a Transfermarkt y no solo la de fechas (hoy tambien la de fotos
     * rotas). Devuelve el mismo formato de siempre:
     * [persona_id => ['tipo', 'rol_id', 'tm', 'apellido', 'nombre']].
     *
     * $personaIds acota a esas personas (null = todas). OJO: entra tal cual en
     * un whereIn, asi que el que llama tiene que partirlo en tandas si la lista
     * es larga. Un array vacio devuelve vacio, no todo.
     *
     * $soloSinFecha mantiene el filtro original `nacimiento IS NULL`, que es lo
     * unico que le importa a la pestana de fechas.
     */
    public static function fichas(array $personaIds = null, bool $soloSinFecha = true): array
    {
        $out = [];

        if ($personaIds !== null && !$personaIds) return $out;

        $acotar = function ($q) use ($personaIds, $soloSinFecha) {
            if ($soloSinFecha) $q->whereNull('p.nacimiento');
            if ($personaIds !== null) $q->whereIn('p.id', $personaIds);
            return $q;
        };

        // ── Jugadores ──────────────────────────────────────────────────────
        $q = DB::table('personas as p')
            ->join('jugadors as j', 'j.persona_id', '=', 'p.id');
        $acotar($q);

        $cols = ['p.id as persona_id', 'p.apellido', 'p.nombre', 'j.id as rol_id'];
        if (Schema::hasTable('jugador_tm')) {
            $q->leftJoin('jugador_tm as t', 't.jugador_id', '=', 'j.id');
            $cols[] = 't.tm_player_id as tm';
        }
        $tieneUrl = self::tieneCol('jugadors', 'transfermarkt_url');
        if ($tieneUrl) $cols[] = 'j.transfermarkt_url as url';

        foreach ($q->select($cols)->get() as $f) {
            $tm = self::limpiar(isset($f->tm) ? $f->tm : null);
            if ($tm === null && $tieneUrl) $tm = self::idDeUrl(isset($f->url) ? $f->url : null, 'spieler');
            self::sumar($out, $f, 'jugador', $tm);
        }

        // ── Técnicos ───────────────────────────────────────────────────────
        if (self::tieneCol('tecnicos', 'transfermarkt_url')) {
            $q = DB::table('personas as p')
                ->join('tecnicos as tc', 'tc.persona_id', '=', 'p.id');
            $acotar($q);

            $filas = $q->select('p.id as persona_id', 'p.apellido', 'p.nombre', 'tc.id as rol_id', 'tc.transfermarkt_url as url')
                ->get();

            foreach ($filas as $f) {
                self::sumar($out, $f, 'tecnico', self::idDeUrl($f->url, 'trainer'));
            }
        }

        // ── Árbitros ───────────────────────────────────────────────────────
        $q = DB::table('personas as p')
            ->join('arbitros as a', 'a.persona_id', '=', 'p.id');
        $acotar($q);

        $cols = ['p.id as persona_id', 'p.apellido', 'p.nombre', 'a.id as rol_id'];
        $hayMapa = Schema::hasTable('arbitro_tm');
        if ($hayMapa) {
            $q->leftJoin('arbitro_tm as at', 'at.arbitro_id', '=', 'a.id');
            $cols[] = 'at.tm_referee_id as tm';
        }
        $tieneUrl = self::tieneCol('arbitros', 'transfermarkt_url');
        if ($tieneUrl) $cols[] = 'a.transfermarkt_url as url';

        foreach ($q->select($cols)->get() as $f) {
            $tm = self::limpiar(isset($f->tm) ? $f->tm : null);
            if ($tm === null && $tieneUrl) $tm = self::idDeUrl(isset($f->url) ? $f->url : null, 'schiedsrichter');
            self::sumar($out, $f, 'arbitro', $tm);
        }

        uasort($out, function ($a, $b) {
            $cmp = strcasecmp((string) $a['apellido'], (string) $b['apellido']);
            return $cmp !== 0 ? $cmp : strcasecmp((string) $a['nombre'], (string) $b['nombre']);
        });

        return $out;
    }

    /**
     * Cómo está repartido el problema: por rol y por si se puede resolver.
     *
     * Es lo que hace falta para saber dónde meter el esfuerzo. Sale de la misma
     * lista que ya tiene el controller cacheada, así que no cuesta nada.
     *
     * OJO: hay que pasarle la lista COMPLETA (`pendientes(true)`), con las
     * descartadas adentro. Las cuenta aparte y las resta de todo lo demás, así
     * la columna "sin fecha" del cuadro es trabajo pendiente de verdad y no un
     * número que no baja nunca.
     */
    public static function detalle(array $todas = null): array
    {
        $todas       = $todas !== null ? $todas : self::pendientes(true);
        $intentos    = self::intentos();
        $descartadas = self::descartadas();

        $vacio = ['total' => 0, 'con_tm' => 0, 'sin_tm' => 0, 'agotadas' => 0, 'descartadas' => 0];
        $out = ['jugador' => $vacio, 'tecnico' => $vacio, 'arbitro' => $vacio, 'total' => $vacio];

        foreach ($todas as $personaId => $d) {
            $tipo = isset($out[$d['tipo']]) ? $d['tipo'] : 'jugador';
            $fuera = array_key_exists((int) $personaId, $descartadas);

            foreach ([$tipo, 'total'] as $k) {
                if ($fuera) {
                    $out[$k]['descartadas']++;
                    continue;
                }
                $out[$k]['total']++;
                if (!empty($d['tm'])) $out[$k]['con_tm']++; else $out[$k]['sin_tm']++;
                if (isset($intentos[(int) $personaId])) $out[$k]['agotadas']++;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // "De esta no hay fecha en ningún lado"
    // ------------------------------------------------------------------

    /**
     * Las personas marcadas a mano como sin fecha conocida.
     *
     * Devuelve [persona_id => motivo]. El motivo puede ser cadena vacía, así
     * que para preguntar si una está marcada va `array_key_exists`, nunca
     * `isset` ni `empty`.
     */
    public static function descartadas(): array
    {
        if (!Schema::hasTable(self::TABLA_DESCARTADAS)) return [];

        $out = [];
        foreach (DB::table(self::TABLA_DESCARTADAS)->select('persona_id', 'motivo')->get() as $f) {
            $out[(int) $f->persona_id] = (string) $f->motivo;
        }

        return $out;
    }

    /**
     * Marca personas como "no tiene fecha en ninguna fuente".
     *
     * No toca `personas.nacimiento` ni la bitácora de Transfermarkt: es solo
     * sacarlas de la cola. Si mañana aparece la fecha, se carga a mano y la
     * persona desaparece igual de la pestaña, porque la lista sale de
     * `nacimiento IS NULL`.
     */
    public static function marcar(array $personaIds, string $motivo = null, $userId = null): int
    {
        if (!Schema::hasTable(self::TABLA_DESCARTADAS)) return 0;

        $motivo = $motivo !== null ? mb_substr(trim($motivo), 0, 200) : null;
        $n = 0;

        foreach ($personaIds as $id) {
            $id = (int) $id;
            if ($id <= 0) continue;

            $previo = DB::table(self::TABLA_DESCARTADAS)->where('persona_id', $id)->first();

            DB::table(self::TABLA_DESCARTADAS)->updateOrInsert(
                ['persona_id' => $id],
                [
                    // Al remarcar una que ya estaba, un motivo vacío no pisa el
                    // que había: se supone que el viejo decía algo útil.
                    'motivo'     => ($motivo !== null && $motivo !== '')
                        ? $motivo
                        : ($previo && isset($previo->motivo) ? $previo->motivo : null),
                    'user_id'    => $userId !== null ? (int) $userId : ($previo->user_id ?? null),
                    'updated_at' => now(),
                    'created_at' => $previo && isset($previo->created_at) ? $previo->created_at : now(),
                ]
            );
            $n++;
        }

        return $n;
    }

    /** Las devuelve a la cola. */
    public static function desmarcar(array $personaIds): int
    {
        if (!Schema::hasTable(self::TABLA_DESCARTADAS)) return 0;

        $ids = [];
        foreach ($personaIds as $id) {
            $id = (int) $id;
            if ($id > 0) $ids[] = $id;
        }
        if (!$ids) return 0;

        return (int) DB::table(self::TABLA_DESCARTADAS)->whereIn('persona_id', $ids)->delete();
    }

    /**
     * Baja los perfiles y completa lo que esté vacío.
     *
     * $limite acota cuántas personas se consultan por API en esta pasada
     * (0 = todas). Con 500 son 10 llamadas, que entra cómodo en un request.
     *
     * $opciones:
     *   html        => bool  buscar en la ficha web las que la API deja sin
     *                        fecha. Sale 1 crédito de ScraperAPI por ficha,
     *                        porque Transfermarkt nos bloquea el HTML directo.
     *   limite_html => int   tope de fichas web por pasada (default 25)
     *   reintentar  => bool  volver a preguntar por las ya marcadas como
     *                        agotadas (TM completa fichas con el tiempo)
     */
    public static function completar(int $limite = 500, array $pendientes = null, array $opciones = []): array
    {
        $pendientes  = $pendientes !== null ? $pendientes : self::pendientes();
        $conHtml     = !empty($opciones['html']);
        $limiteHtml  = isset($opciones['limite_html']) ? (int) $opciones['limite_html'] : 25;
        $reintentar  = !empty($opciones['reintentar']);
        $intentos    = $reintentar ? [] : self::intentos();

        $r = [
            'personas'   => 0,   // cuántas se consultaron por API
            'llamadas'   => 0,
            'sin_tm'     => 0,   // no hay id de TM: no se puede ni intentar
            'sin_perfil' => 0,   // la API no devolvió esa ficha
            'sin_fecha'  => 0,   // vino el perfil pero sin fecha de nacimiento
            'agotadas'   => 0,   // ya se habían consultado y no había nada
            'html_paginas' => 0, // fichas web bajadas (= créditos gastados)
            'html_fechas'  => 0, // fechas que salieron de la ficha web
            'campos'     => [],  // campo => cuántas veces se completó
            'quedan'     => 0,
            'errores'    => [],
        ];

        $porTipo = [];
        foreach ($pendientes as $personaId => $d) {
            if (empty($d['tm'])) { $r['sin_tm']++; continue; }
            if (isset($intentos[(int) $personaId])) { $r['agotadas']++; continue; }
            $porTipo[$d['tipo']][] = [
                'persona' => (int) $personaId,
                'rol_id'  => (int) $d['rol_id'],
                'tm'      => (string) $d['tm'],
            ];
        }

        $paises = JugadorController::paisesTM();
        $cortado = false;
        $paraHtml = [];

        foreach (['jugador', 'tecnico', 'arbitro'] as $tipo) {
            if (empty($porTipo[$tipo])) continue;

            foreach (array_chunk($porTipo[$tipo], self::POR_LLAMADA) as $tanda) {
                if ($limite > 0 && $r['personas'] >= $limite) { $cortado = true; break 2; }

                $ids = [];
                foreach ($tanda as $fila) $ids[] = $fila['tm'];

                $perfiles = self::traerPerfiles($tipo, $ids, $r);

                foreach ($tanda as $fila) {
                    $r['personas']++;

                    if (!isset($perfiles[$fila['tm']])) {
                        $r['sin_perfil']++;
                        $paraHtml[] = ['tipo' => $tipo, 'fila' => $fila, 'estado' => 'sin_perfil'];
                        continue;
                    }

                    $datos = self::datosDePerfil($perfiles[$fila['tm']], $paises);

                    try {
                        self::aplicar($tipo, $fila, $datos, $r);
                    } catch (\Exception $e) {
                        $r['errores'][] = 'Persona #' . $fila['persona'] . ': ' . $e->getMessage();
                    }

                    if (empty($datos['nacimiento'])) {
                        $r['sin_fecha']++;
                        $paraHtml[] = ['tipo' => $tipo, 'fila' => $fila, 'estado' => 'sin_fecha'];
                    } else {
                        self::registrar($fila['persona'], $fila['tm'], 'api', 'completada');
                    }
                }
            }
        }

        // ── Segunda vuelta: la ficha web ───────────────────────────────────
        // La API de TM viene sin fecha para casi todos los árbitros, pero la
        // página del perfil sí la muestra. Es la única forma de exprimir esos.
        foreach ($paraHtml as $i => $caso) {
            if (!$conHtml) {
                self::registrar($caso['fila']['persona'], $caso['fila']['tm'], 'api', $caso['estado']);
                continue;
            }
            if ($limiteHtml > 0 && $r['html_paginas'] >= $limiteHtml) {
                // No la marcamos: queda para la próxima pasada.
                continue;
            }

            $r['html_paginas']++;
            $fecha = self::fechaDelHtml($caso['tipo'], $caso['fila']['tm']);

            if ($fecha) {
                $r['html_fechas']++;
                self::aplicarFecha($caso['fila']['persona'], $fecha, $r);
                self::registrar($caso['fila']['persona'], $caso['fila']['tm'], 'html', 'completada');
            } else {
                self::registrar($caso['fila']['persona'], $caso['fila']['tm'], 'html', $caso['estado']);
            }
        }

        if ($cortado) {
            $r['quedan'] = 0;
            foreach ($porTipo as $filas) $r['quedan'] += count($filas);
            $r['quedan'] = max(0, $r['quedan'] - $r['personas']);
        }

        return $r;
    }

    /**
     * La fecha de nacimiento leída de la página del perfil.
     *
     * Se prueban cuatro formas porque el HTML de TM cambia de layout y no todas
     * las fichas traen las mismas etiquetas:
     *   1) el link "qué pasó un día como hoy" (.../datum/AAAA-MM-DD) — el más
     *      confiable, viene en formato ISO;
     *   2) itemprop="birthDate" (layout viejo);
     *   3) el JSON-LD embebido ("dateOfBirth": "...");
     *   4) la etiqueta "Fecha de nacimiento" y lo que venga atrás.
     */
    public static function fechaDelHtml(string $tipo, string $tmId): ?string
    {
        $tramo = $tipo === 'tecnico' ? 'trainer' : ($tipo === 'arbitro' ? 'schiedsrichter' : 'spieler');
        $url   = 'https://www.transfermarkt.es/-/profil/' . $tramo . '/' . rawurlencode($tmId);

        $html = HttpHelper::getHtmlContent($url);
        if (!is_string($html) || $html === '') return null;

        if (preg_match('#/datum/(\d{4}-\d{2}-\d{2})#', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#itemprop="birthDate"[^>]*>\s*([^<]{4,40})#i', $html, $m)) {
            $f = self::aFechaSuelta($m[1]);
            if ($f) return $f;
        }
        if (preg_match('#"dateOfBirth"\s*:\s*"([^"]{4,40})"#i', $html, $m)) {
            $f = self::aFechaSuelta($m[1]);
            if ($f) return $f;
        }
        if (preg_match('#Fecha de nacimiento.{0,300}?(\d{1,2}[\./ ]+(?:de[\. ]+)?[a-zA-Záéíóú]{3,12}[\./ ]+(?:de[\. ]+)?\d{4}|\d{1,2}[\./]\d{1,2}[\./]\d{4})#su', $html, $m)) {
            $f = self::aFechaSuelta($m[1]);
            if ($f) return $f;
        }

        return null;
    }

    /** Fecha suelta de TM (ISO, dd/mm/aaaa o "22 jul 1985") -> Y-m-d. */
    private static function aFechaSuelta($raw): ?string
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string) $raw));
        if ($t === '') return null;

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $t, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        if (preg_match('#^(\d{1,2})[\./](\d{1,2})[\./](\d{4})#', $t, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $meses = [
            'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'ago' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];
        if (preg_match('#^(\d{1,2})[\. ]+(?:de[\. ]+)?([a-záéíóú]{3,12})[\. ]+(?:de[\. ]+)?(\d{4})#ui', $t, $m)) {
            $clave = mb_strtolower(mb_substr($m[2], 0, 3));
            $clave = strtr($clave, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
            if (isset($meses[$clave])) {
                return sprintf('%04d-%02d-%02d', $m[3], $meses[$clave], $m[1]);
            }
        }

        try {
            return Carbon::parse($t)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Las personas que ya se consultaron y no dieron fecha. */
    private static function intentos(): array
    {
        if (!Schema::hasTable(self::TABLA_INTENTOS)) return [];

        return DB::table(self::TABLA_INTENTOS)
            ->whereIn('estado', ['sin_fecha', 'sin_perfil'])
            ->pluck('estado', 'persona_id')
            ->all();
    }

    /** Deja anotado en qué terminó el intento, para no repetirlo al pedo. */
    private static function registrar($personaId, $tmId, string $fuente, string $estado): void
    {
        if (!Schema::hasTable(self::TABLA_INTENTOS)) return;

        $previo = DB::table(self::TABLA_INTENTOS)->where('persona_id', $personaId)->first();

        DB::table(self::TABLA_INTENTOS)->updateOrInsert(
            ['persona_id' => (int) $personaId],
            [
                'tm_id'         => $tmId !== null ? (string) $tmId : null,
                'fuente'        => $fuente,
                'estado'        => $estado,
                'intentos'      => $previo ? ((int) $previo->intentos + 1) : 1,
                'consultado_at' => now(),
                'updated_at'    => now(),
                'created_at'    => $previo && isset($previo->created_at) ? $previo->created_at : now(),
            ]
        );
    }

    // ------------------------------------------------------------------
    // Internas
    // ------------------------------------------------------------------

    private static function sumar(array &$out, $fila, string $tipo, $tm): void
    {
        $id = (int) $fila->persona_id;

        // Una persona puede tener dos roles (jugador que después fue DT). Nos
        // quedamos con el primero que traiga id de TM.
        if (isset($out[$id]) && empty($out[$id]['tm'])) {
            if (!empty($tm)) {
                $out[$id]['tipo']   = $tipo;
                $out[$id]['rol_id'] = (int) $fila->rol_id;
                $out[$id]['tm']     = $tm;
            }
            return;
        }
        if (isset($out[$id])) return;

        $out[$id] = [
            'tipo'     => $tipo,
            'rol_id'   => (int) $fila->rol_id,
            'tm'       => $tm,
            'apellido' => $fila->apellido,
            'nombre'   => $fila->nombre,
        ];
    }

    private static function limpiar($valor): ?string
    {
        $v = trim((string) $valor);
        return ($v === '' || $v === '0') ? null : $v;
    }

    /** Saca el id numérico de una URL de Transfermarkt. */
    private static function idDeUrl($url, string $tramo): ?string
    {
        if (!$url) return null;
        return preg_match('#/' . $tramo . '/(\d+)#', (string) $url, $m) ? $m[1] : null;
    }

    /**
     * Una llamada por tanda. Las rutas alternativas son las mismas que probaba
     * el importador: la API cambió de nombre alguna vez y conviene no atarse.
     *
     * Es pública porque `FotosPersonas` pide los mismos perfiles para sacarles
     * la URL del retrato. Devuelve [tm_id => perfil crudo] y suma en
     * $r['llamadas'] lo que consultó.
     */
    public static function traerPerfiles(string $tipo, array $ids, array &$r): array
    {
        $qs = implode('&', array_map(function ($id) { return 'ids[]=' . urlencode($id); }, $ids));

        if ($tipo === 'jugador') {
            $rutas = ['/players?' . $qs];
            $ramas = ['players'];
            $claves = ['id', 'playerId'];
        } elseif ($tipo === 'tecnico') {
            $rutas = ['/coaches?' . $qs, '/trainers?' . $qs, '/managers?' . $qs];
            $ramas = ['coaches', 'trainers', 'managers'];
            $claves = ['id', 'coachId', 'trainerId'];
        } else {
            $rutas = ['/referees?' . $qs, '/officials?' . $qs];
            $ramas = ['referees', 'officials'];
            $claves = ['id', 'refereeId'];
        }

        $out = [];

        foreach ($rutas as $ruta) {
            $json = HttpHelper::getJson(self::TMAPI . $ruta);
            $r['llamadas']++;
            if (!is_array($json) || empty($json)) continue;

            $data = isset($json['data']) ? $json['data'] : $json;
            foreach ($ramas as $rama) {
                if (isset($data[$rama]) && is_array($data[$rama])) { $data = $data[$rama]; break; }
            }

            foreach ($data as $clave => $perfil) {
                if (!is_array($perfil)) continue;
                $id = null;
                foreach ($claves as $k) {
                    if (isset($perfil[$k]) && $perfil[$k] !== '') { $id = $perfil[$k]; break; }
                }
                if ($id === null && !is_int($clave)) $id = $clave;
                if ($id === null) continue;
                $out[(string) $id] = $perfil;
            }

            if ($out) break;
        }

        return $out;
    }

    /**
     * Traduce el perfil a nuestros campos.
     *
     * Igual que en `TmDetallePartido::personaDesdePerfil()`, hay que leer las
     * dos formas que conviven en tmapi: jugadores y DTs vienen anidados
     * (`lifeDates.dateOfBirth`) y los árbitros vienen planos (`dateOfBirth`).
     * Leyendo solo la anidada, todo árbitro queda sin fecha.
     */
    private static function datosDePerfil(array $p, array $paises): array
    {
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
        $nacionalidad = ($nacId && isset($paises[$nacId])) ? $paises[$nacId] : null;

        $altura = isset($p['attributes']['height']) ? $p['attributes']['height'] : null;
        $altura = ($altura !== null && is_numeric($altura)) ? (float) $altura : null;

        $grupo = strtoupper((string) (isset($p['attributes']['positionGroup']) ? $p['attributes']['positionGroup'] : ''));
        $tipoJugador = null;
        if ($grupo === 'GOALKEEPER') $tipoJugador = 'Arquero';
        elseif ($grupo === 'DEFENDER') $tipoJugador = 'Defensor';
        elseif ($grupo === 'MIDFIELD' || $grupo === 'MIDFIELDER') $tipoJugador = 'Medio';
        elseif ($grupo === 'FORWARD' || $grupo === 'STRIKER' || $grupo === 'ATTACK') $tipoJugador = 'Delantero';

        $pieRaw = trim((string) (isset($p['attributes']['preferredFoot']['name']) ? $p['attributes']['preferredFoot']['name'] : ''));
        $pie = null;
        if ($pieRaw === 'Derecho') $pie = 'Derecha';
        elseif ($pieRaw === 'Izquierdo') $pie = 'Izquierda';
        elseif ($pieRaw === 'Ambidiestro') $pie = 'Ambas';

        return [
            'nacimiento'    => $nacimiento,
            'fallecimiento' => $fallecimiento,
            'ciudad'        => $ciudad !== '' ? $ciudad : null,
            'nacionalidad'  => $nacionalidad,
            'altura'        => $altura,
            'tipoJugador'   => $tipoJugador,
            'pie'           => $pie,
        ];
    }

    /**
     * Escribe solo los campos vacíos.
     *
     * Va con `DB::table()` a propósito: el `saved()` del modelo Persona
     * reindexa duplicados, y acá no se toca ni el nombre ni el apellido, así
     * que no hay nada que reindexar.
     */
    private static function aplicar(string $tipo, array $fila, array $datos, array &$r): void
    {
        $persona = DB::table('personas')->where('id', $fila['persona'])->first();
        if (!$persona) return;

        $set = [];
        foreach (['nacimiento', 'fallecimiento', 'ciudad', 'altura', 'nacionalidad'] as $campo) {
            if (empty($datos[$campo])) continue;
            if (!self::vacio(isset($persona->$campo) ? $persona->$campo : null)) continue;
            $set[$campo] = $datos[$campo];
        }

        if ($set) {
            if (self::tieneCol('personas', 'updated_at')) $set['updated_at'] = now();
            DB::table('personas')->where('id', $fila['persona'])->update($set);
            foreach ($set as $campo => $v) {
                if ($campo === 'updated_at') continue;
                $r['campos'][$campo] = (isset($r['campos'][$campo]) ? $r['campos'][$campo] : 0) + 1;
            }
        }

        if ($tipo !== 'jugador') return;

        $jugador = DB::table('jugadors')->where('id', $fila['rol_id'])->first();
        if (!$jugador) return;

        $setJ = [];
        foreach (['tipoJugador', 'pie'] as $campo) {
            if (empty($datos[$campo])) continue;
            if (!self::tieneCol('jugadors', $campo)) continue;
            if (!self::vacio(isset($jugador->$campo) ? $jugador->$campo : null)) continue;
            $setJ[$campo] = $datos[$campo];
        }

        if (self::tieneCol('jugadors', 'transfermarkt_url')
            && self::vacio(isset($jugador->transfermarkt_url) ? $jugador->transfermarkt_url : null)) {
            $setJ['transfermarkt_url'] = 'https://www.transfermarkt.es/-/profil/spieler/' . $fila['tm'];
        }

        if ($setJ) {
            if (self::tieneCol('jugadors', 'updated_at')) $setJ['updated_at'] = now();
            DB::table('jugadors')->where('id', $fila['rol_id'])->update($setJ);
            foreach ($setJ as $campo => $v) {
                if ($campo === 'updated_at') continue;
                $r['campos'][$campo] = (isset($r['campos'][$campo]) ? $r['campos'][$campo] : 0) + 1;
            }
        }
    }

    /** Escribe solo la fecha (es lo único que sacamos de la ficha web). */
    private static function aplicarFecha($personaId, string $fecha, array &$r): void
    {
        $persona = DB::table('personas')->where('id', $personaId)->first();
        if (!$persona) return;
        if (!self::vacio(isset($persona->nacimiento) ? $persona->nacimiento : null)) return;

        $set = ['nacimiento' => $fecha];
        if (self::tieneCol('personas', 'updated_at')) $set['updated_at'] = now();

        DB::table('personas')->where('id', $personaId)->update($set);
        $r['campos']['nacimiento'] = (isset($r['campos']['nacimiento']) ? $r['campos']['nacimiento'] : 0) + 1;
    }

    /**
     * `self::tieneCol()` pega contra information_schema cada vez que se
     * llama. Acá se llama una vez por persona y por campo, así que sin este
     * cache serían miles de consultas de más por tanda.
     */
    private static function tieneCol(string $tabla, string $columna): bool
    {
        static $cache = [];
        $k = $tabla . '.' . $columna;
        if (!array_key_exists($k, $cache)) {
            try {
                $cache[$k] = Schema::hasColumn($tabla, $columna);
            } catch (\Exception $e) {
                $cache[$k] = false;
            }
        }
        return $cache[$k];
    }

    /** Vacío de verdad: null, string vacío, 0 o la fecha cero de MySQL. */
    private static function vacio($valor): bool
    {
        if ($valor === null) return true;
        $v = trim((string) $valor);
        return $v === '' || $v === '0' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00';
    }
}
