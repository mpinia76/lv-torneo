<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Fotos rotas: las que la ficha muestra como imagen quebrada.
 *
 * `personas.foto` guarda SOLO el nombre del archivo; la imagen vive en
 * `public/images/`. Nada garantiza que las dos cosas coincidan: el importador
 * de Transfermarkt baja la foto por ScraperAPI y, si la respuesta vino cortada
 * o la escritura falló a mitad, en la base queda el nombre igual. Después
 * `<img src="images/loquesea.jpg">` no dibuja nada y en pantalla se ve el
 * cuadradito roto.
 *
 * Dos problemas distintos, y conviene no mezclarlos:
 *   falta  → el archivo no está. Hay que volver a bajarlo.
 *   vacio  → el archivo está pero pesa nada o no es una imagen (típicamente una
 *            página de error guardada con extensión .jpg). El navegador tampoco
 *            lo dibuja, y como el archivo existe, un `file_exists` pelado no lo
 *            encuentra nunca.
 *
 * Lo que NO es problema y por eso no entra: `foto` vacío. Esas fichas muestran
 * `sin_foto.png` a propósito y son miles; meterlas acá sería tapar las rotas.
 *
 * La detección no cuesta una consulta por persona: se lee el directorio UNA vez
 * (nombre => tamaño) y después se compara en PHP, agrupando por nombre de
 * archivo. Dos personas con la misma foto se revisan una sola vez.
 */
class FotosPersonas
{
    /**
     * Debajo de esto no hay foto que valga.
     *
     * Un retrato de TM pesa entre 4 y 15 KB. Lo que baja de 512 bytes es un
     * archivo cortado o un HTML de error, así que se confirma con getimagesize
     * antes de acusarlo (que sea chico no lo hace inválido por sí solo).
     */
    const MIN_BYTES = 512;

    const FALTA = 'falta';
    const VACIO = 'vacio';

    /** Cuántos ids entran en una llamada a la API. Mismo tope que TmFechas. */
    const POR_LLAMADA = 50;

    /** Cuántas personas entran en un whereIn al resolver los ids de TM. */
    const POR_WHEREIN = 800;

    /** El directorio leído una vez por request. */
    private static $archivos = null;

    /** El índice en minúsculas, para detectar diferencias de mayúsculas. */
    private static $porMinuscula = null;

    // ------------------------------------------------------------------
    // Detección
    // ------------------------------------------------------------------

    /**
     * Las personas cuya foto no se ve.
     *
     * Devuelve [persona_id => ['foto', 'motivo', 'bytes', 'parecido']], ordenado
     * por apellido. `parecido` es el nombre real del archivo cuando existe pero
     * con otras mayúsculas: en Windows se ve bien y en el server (Linux) no,
     * que es de los casos más difíciles de entender mirando la pantalla.
     */
    public static function problemas(): array
    {
        $porArchivo = [];   // nombre de archivo => diagnóstico (o null si está sano)
        $out        = [];

        // De a tandas y ordenado por id: son decenas de miles de fichas con foto
        // y traerlas todas juntas para quedarnos con un puñado es la clase de
        // consulta que se come la memoria del hosting. El orden por apellido se
        // aplica al final, sobre las pocas que tienen problema.
        DB::table('personas')
            ->select('id', 'foto', 'apellido', 'nombre')
            ->whereNotNull('foto')
            ->where('foto', '<>', '')
            ->orderBy('id')
            ->chunk(2000, function ($filas) use (&$porArchivo, &$out) {
                foreach ($filas as $f) {
                    $foto = trim((string) $f->foto);
                    if ($foto === '') continue;

                    // Alguna ficha vieja guarda una URL entera en vez del nombre
                    // del archivo. Eso se sirve solo y no es asunto de acá.
                    if (strpos($foto, '://') !== false) continue;

                    if (!array_key_exists($foto, $porArchivo)) {
                        $porArchivo[$foto] = self::revisar($foto);
                    }
                    if ($porArchivo[$foto] === null) continue;

                    $out[(int) $f->id] = $porArchivo[$foto] + [
                        'foto'     => $foto,
                        'apellido' => (string) $f->apellido,
                        'nombre'   => (string) $f->nombre,
                    ];
                }
            });

        uasort($out, function ($a, $b) {
            $cmp = strcasecmp($a['apellido'], $b['apellido']);
            return $cmp !== 0 ? $cmp : strcasecmp($a['nombre'], $b['nombre']);
        });

        return $out;
    }

    /**
     * El diagnóstico de un archivo, o null si está sano.
     *
     * @return array|null ['motivo' => ..., 'bytes' => int, 'parecido' => ?string]
     */
    public static function revisar(string $foto)
    {
        // Nombre con carpeta adentro: no está en el índice plano del directorio,
        // así que se pregunta derecho por él.
        if (strpos($foto, '/') !== false || strpos($foto, '\\') !== false) {
            $ruta = public_path('images/' . $foto);
            if (!is_file($ruta)) {
                return ['motivo' => self::FALTA, 'bytes' => 0, 'parecido' => null];
            }
            return self::porTamano($ruta, (int) @filesize($ruta));
        }

        $archivos = self::archivos();

        if (!array_key_exists($foto, $archivos)) {
            $indice   = self::porMinuscula();
            $clave    = mb_strtolower($foto);
            $parecido = isset($indice[$clave]) ? $indice[$clave] : null;

            return ['motivo' => self::FALTA, 'bytes' => 0, 'parecido' => $parecido];
        }

        return self::porTamano(public_path('images/' . $foto), (int) $archivos[$foto]);
    }

    /** Un archivo que existe: decide si además sirve. */
    private static function porTamano(string $ruta, int $bytes)
    {
        if ($bytes <= 0) {
            return ['motivo' => self::VACIO, 'bytes' => 0, 'parecido' => null];
        }

        // Solo los sospechosamente chicos pagan el getimagesize. Son un puñado:
        // hacerlo con los 8000 archivos costaría más que toda la pantalla.
        if ($bytes < self::MIN_BYTES && @getimagesize($ruta) === false) {
            return ['motivo' => self::VACIO, 'bytes' => $bytes, 'parecido' => null];
        }

        return null;
    }

    /**
     * El contenido de public/images como [nombre => tamaño].
     *
     * Una sola pasada por el directorio en vez de un file_exists por persona.
     * El que llama lo cachea afuera (el controller lo guarda 10 minutos).
     */
    public static function archivos(): array
    {
        if (self::$archivos !== null) return self::$archivos;

        self::$archivos = [];

        $dir = public_path('images');
        if (!is_dir($dir)) return self::$archivos;

        try {
            $it = new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS);
            foreach ($it as $archivo) {
                if (!$archivo->isFile()) continue;
                self::$archivos[$archivo->getFilename()] = (int) $archivo->getSize();
            }
        } catch (\Exception $e) {
            // Si el directorio no se puede leer, mejor no acusar a nadie.
            self::$archivos = [];
        }

        return self::$archivos;
    }

    /** [nombre en minúsculas => nombre real], para pescar diferencias de caja. */
    private static function porMinuscula(): array
    {
        if (self::$porMinuscula !== null) return self::$porMinuscula;

        self::$porMinuscula = [];
        foreach (array_keys(self::archivos()) as $nombre) {
            self::$porMinuscula[mb_strtolower($nombre)] = $nombre;
        }

        return self::$porMinuscula;
    }

    /** Olvida el directorio leído (después de bajar fotos nuevas). */
    public static function olvidar(): void
    {
        self::$archivos     = null;
        self::$porMinuscula = null;
    }

    // ------------------------------------------------------------------
    // Reparación desde Transfermarkt
    // ------------------------------------------------------------------

    /**
     * Vuelve a bajar de TM la foto de las fichas rotas.
     *
     * El id de TM sale por el mismo camino que usa la pantalla de fechas
     * (`TmFechas::fichas()`): jugador_tm / arbitro_tm y, si no, la URL de la
     * ficha. Las que no tienen id no se pueden ni intentar.
     *
     * Costo: una llamada gratis a la API cada 50 personas para conseguir la URL
     * del retrato, y después UN crédito de ScraperAPI por foto que se baja
     * (los hosts de imágenes de TM están geo-bloqueados para el server, igual
     * que en el importador). Por eso hay tope por pasada.
     *
     * Nunca deja la ficha peor de lo que estaba: si lo que vuelve no es una
     * imagen válida, no se escribe el archivo ni se toca `personas.foto`.
     */
    public static function completar(int $limite = 200, array $problemas = null, array $opciones = []): array
    {
        $problemas = $problemas !== null ? $problemas : self::problemas();

        $r = [
            'personas'     => 0,   // consultadas a la API
            'llamadas'     => 0,
            'sin_tm'       => 0,   // no hay id de TM: no se puede intentar
            'sin_perfil'   => 0,   // la API no devolvió esa ficha
            'sin_portrait' => 0,   // vino el perfil pero TM no tiene foto
            'bajadas'      => 0,
            'fallidas'     => 0,
            'quedan'       => 0,
            'errores'      => [],
        ];

        if (!$problemas) return $r;

        // ── De qué rol es cada una y con qué id de TM se la busca ──────────
        $fichas = [];
        foreach (array_chunk(array_keys($problemas), self::POR_WHEREIN) as $tanda) {
            $fichas += TmFechas::fichas($tanda, false);
        }

        $porTipo = [];
        foreach ($problemas as $personaId => $d) {
            $ficha = isset($fichas[(int) $personaId]) ? $fichas[(int) $personaId] : null;
            if (!$ficha || empty($ficha['tm'])) { $r['sin_tm']++; continue; }

            $porTipo[$ficha['tipo']][] = [
                'persona' => (int) $personaId,
                'tm'      => (string) $ficha['tm'],
                'quien'   => trim($ficha['apellido'] . ', ' . $ficha['nombre']),
            ];
        }

        $cortado = false;

        foreach (['jugador', 'tecnico', 'arbitro'] as $tipo) {
            if (empty($porTipo[$tipo])) continue;

            foreach (array_chunk($porTipo[$tipo], self::POR_LLAMADA) as $tanda) {
                if ($limite > 0 && $r['personas'] >= $limite) { $cortado = true; break 2; }

                $ids = [];
                foreach ($tanda as $fila) $ids[] = $fila['tm'];

                $perfiles = TmFechas::traerPerfiles($tipo, $ids, $r);

                foreach ($tanda as $fila) {
                    $r['personas']++;

                    if (!isset($perfiles[$fila['tm']])) { $r['sin_perfil']++; continue; }

                    $url = self::portraitDe($perfiles[$fila['tm']]);
                    if ($url === null) { $r['sin_portrait']++; continue; }

                    $baja = self::descargar($url, $fila['quien']);

                    if (empty($baja['ok'])) {
                        $r['fallidas']++;
                        if ($baja['error'] !== '' && count($r['errores']) < 20) {
                            $r['errores'][] = $fila['quien'] . ': ' . $baja['error'];
                        }
                        continue;
                    }

                    try {
                        DB::table('personas')->where('id', $fila['persona'])->update(['foto' => $baja['archivo']]);
                        $r['bajadas']++;
                    } catch (\Exception $e) {
                        $r['fallidas']++;
                        $r['errores'][] = 'Persona #' . $fila['persona'] . ': ' . $e->getMessage();
                    }
                }
            }
        }

        if ($cortado) {
            $total = 0;
            foreach ($porTipo as $filas) $total += count($filas);
            $r['quedan'] = max(0, $total - $r['personas']);
        }

        self::olvidar();

        return $r;
    }

    /**
     * La URL del retrato dentro del perfil de la API.
     *
     * `default.jpg` es la silueta gris que TM devuelve cuando NO tiene foto: si
     * se bajara, todas las fichas sin foto terminarían compartiendo la misma
     * imagen y la pantalla diría que están arregladas. Mismo criterio que
     * `TmDetallePartido::personaDesdePerfil()`.
     */
    private static function portraitDe(array $perfil)
    {
        foreach (['portraitUrl', 'imageUrl', 'image'] as $clave) {
            $url = isset($perfil[$clave]) ? trim((string) $perfil[$clave]) : '';
            if ($url === '') continue;
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            if (strpos($url, 'default.jpg') !== false) continue;

            return $url;
        }

        return null;
    }

    /**
     * Baja la imagen y devuelve el nombre del archivo guardado.
     *
     * A diferencia del importador, acá se valida el contenido ANTES de escribir:
     * esta pantalla existe justamente porque quedaron archivos que no eran
     * imágenes, y volver a escribir uno igual sería cambiar una foto rota por
     * otra foto rota.
     *
     * @return array ['ok' => bool, 'archivo' => ?string, 'error' => string]
     */
    public static function descargar(string $url, string $quien = ''): array
    {
        $out = ['ok' => false, 'archivo' => null, 'error' => ''];

        $nombre = self::nombreDe($url);
        if ($nombre === null) {
            $out['error'] = 'la URL de TM no tiene nombre de archivo';
            return $out;
        }

        try {
            $img = HttpHelper::getBinary($url);
        } catch (\Exception $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }

        if (empty($img['ok'])) {
            $out['error'] = 'HTTP ' . (isset($img['http']) ? $img['http'] : '?')
                . (empty($img['error']) ? '' : ' (' . $img['error'] . ')');
            return $out;
        }

        $body = isset($img['body']) ? $img['body'] : '';

        if (strlen($body) < self::MIN_BYTES || @getimagesizefromstring($body) === false) {
            $out['error'] = 'lo que volvió no es una imagen (' . strlen($body) . ' bytes)';
            return $out;
        }

        $destino = public_path('images/') . $nombre;

        if (@file_put_contents($destino, $body) === false) {
            $out['error'] = 'no se pudo escribir public/images/' . $nombre;
            return $out;
        }

        $out['ok']      = true;
        $out['archivo'] = $nombre;

        return $out;
    }

    /** El nombre de archivo que le corresponde a una URL de retrato de TM. */
    private static function nombreDe(string $url)
    {
        $ruta = parse_url($url, PHP_URL_PATH);
        $info = pathinfo((string) $ruta);

        $archivo = isset($info['filename']) ? rtrim($info['filename'], '.') : '';
        if ($archivo === '') return null;

        $extension = isset($info['extension']) && $info['extension'] !== '' ? $info['extension'] : 'jpg';

        // El nombre viaja a un path del filesystem: nada de barras ni de "..".
        $nombre = preg_replace('/[^A-Za-z0-9._-]/', '', $archivo . '.' . $extension);

        return $nombre !== '' ? $nombre : null;
    }
}
