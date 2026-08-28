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
 * El criterio es uno solo: **entra lo que el navegador no puede dibujar**.
 * Tres formas de que pase:
 *
 *   falta    → el archivo no está. Hay que volver a bajarlo.
 *   vacio    → el archivo está y pesa cero.
 *   corrupto → el archivo pesa lo que tiene que pesar pero NO es una imagen:
 *              una página de error o un JSON guardados con nombre de foto, o el
 *              binario mangleado por UTF-8 (cada byte alto convertido en
 *              `EF BF BD`) que devuelve el proxy cuando trata la respuesta como
 *              texto. Es el caso más común y el más difícil de ver: el archivo
 *              existe, pesa 20 KB, y no se dibuja.
 *
 * Lo que NO entra aunque parezca mal: que la extensión no coincida con el
 * contenido. Un `.jpg` que por dentro es WEBP o PNG el navegador lo sniffea y lo
 * dibuja igual. Se midió en producción: de 276 fichas marcadas por la primera
 * versión, 157 eran de esas y se veían todas bien.
 *
 * **Mirar el tamaño no alcanza**: los archivos rotos que aparecieron en
 * producción pesan 2 KB, 20 KB y 280 KB. Lo único que los delata son los
 * primeros bytes, así que de cada archivo se leen 32.
 *
 * Lo que NO es problema y por eso no entra: `foto` vacío. Esas fichas muestran
 * `sin_foto.png` a propósito y son miles; meterlas acá sería tapar las rotas.
 *
 * La detección no cuesta una consulta por persona: se lee el directorio UNA vez
 * (nombre => tamaño) y después se agrupa por nombre de archivo, así dos personas
 * con la misma foto se revisan una sola vez.
 */
class FotosPersonas
{
    /**
     * Piso de tamaño para ACEPTAR una descarga nueva. No se usa para acusar a
     * un archivo que ya está: un retrato de TM pesa entre 4 y 15 KB, pero que
     * un archivo sea chico no lo hace inválido — eso lo decide la firma.
     */
    const MIN_BYTES = 512;

    const FALTA    = 'falta';
    const VACIO    = 'vacio';
    const CORRUPTO = 'corrupto';

    /** Los tres motivos, en el orden en que conviene atacarlos. */
    const MOTIVOS = [self::CORRUPTO, self::FALTA, self::VACIO];

    /** Cuántos ids entran en una llamada a la API. Mismo tope que TmFechas. */
    const POR_LLAMADA = 50;

    /** Cuántas personas entran en un whereIn al resolver los ids de TM. */
    const POR_WHEREIN = 800;

    /**
     * Cuántas fallas seguidas, sin ningún acierto, cortan la pasada.
     *
     * Bajar cada foto gasta un crédito de ScraperAPI. Si las primeras cinco
     * fallan todas, lo que está roto es el camino de descarga y seguir hasta 100
     * solo cuesta plata para llegar a la misma conclusión.
     */
    const FALLAS_SEGUIDAS = 5;

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
            if (!is_file($ruta)) return self::falta(null);

            return self::revisarArchivo($ruta, (int) @filesize($ruta));
        }

        $archivos = self::archivos();

        if (!array_key_exists($foto, $archivos)) {
            $indice = self::porMinuscula();
            $clave  = mb_strtolower($foto);

            return self::falta(isset($indice[$clave]) ? $indice[$clave] : null);
        }

        return self::revisarArchivo(public_path('images/' . $foto), (int) $archivos[$foto]);
    }

    private static function falta($parecido)
    {
        return ['motivo' => self::FALTA, 'bytes' => 0, 'parecido' => $parecido,
                'real' => null, 'detalle' => ''];
    }

    /**
     * Un archivo que existe: decide si además es una imagen, y si es la que
     * dice ser.
     *
     * Mirar el TAMAÑO no alcanza y fue el error de la primera versión: los
     * archivos que rompen la pantalla pesan 2 KB, 20 KB o 280 KB. Lo que los
     * delata son los primeros bytes.
     *
     * **Acá no se decodifica nada.** La versión anterior llamaba a
     * `imagecreatefromstring()` para confirmar, y con un WebP arruinado GD se
     * muere con un FATAL ("gd-webp cannot allocate temporary buffer") que el @
     * no tapa y que se lleva puesta la pantalla entera. Todo lo que sigue son
     * lecturas de cabecera y aritmética.
     */
    private static function revisarArchivo(string $ruta, int $bytes)
    {
        if ($bytes <= 0) {
            return ['motivo' => self::VACIO, 'bytes' => 0, 'parecido' => null,
                    'real' => null, 'detalle' => 'el archivo está pero pesa cero'];
        }

        $cabecera = self::cabecera($ruta);
        $firma    = self::firmaDeBytes($cabecera);

        if ($firma === null || !in_array($firma, ['png', 'jpg', 'gif', 'webp', 'bmp', 'avif', 'svg'], true)) {
            $detalle = $firma === 'html' ? 'es una página HTML guardada con nombre de imagen'
                     : ($firma === 'json' ? 'es una respuesta JSON de error guardada con nombre de imagen'
                     : 'los primeros bytes no son los de ninguna imagen conocida');

            return ['motivo' => self::CORRUPTO, 'bytes' => $bytes, 'parecido' => null,
                    'real' => $firma, 'detalle' => $detalle];
        }

        // El WebP dice adentro cuánto tiene que medir. Es la forma barata y
        // segura de pescar los que llegaron cortados o inflados por el proxy:
        // la cabecera RIFF les quedó intacta, así que la firma sola los aprueba.
        if ($firma === 'webp') {
            $declarado = self::tamanoWebp($cabecera);

            if ($declarado !== null && ($bytes < $declarado || $bytes > $declarado + 8)) {
                return ['motivo' => self::CORRUPTO, 'bytes' => $bytes, 'parecido' => null,
                        'real' => 'webp',
                        'detalle' => 'dice ser un WebP de ' . number_format($declarado) . ' bytes y el archivo '
                            . 'tiene ' . number_format($bytes) . ': llegó roto, hay que volver a bajarla'];
            }
        }

        // Que la extensión no coincida con el contenido NO es un problema: un
        // .jpg que por dentro es WEBP o PNG el navegador lo dibuja igual, lo
        // sniffea. Se probó en producción: de 276 fichas marcadas, las 157 de
        // "otro formato" se veían perfectas. Esta pantalla lista SOLO lo que no
        // se ve, así que eso no entra.
        return null;
    }

    /** Los primeros 32 bytes del archivo. Lo más barato que se puede leer. */
    private static function cabecera(string $ruta): string
    {
        $fh = @fopen($ruta, 'rb');
        if ($fh === false) return '';

        $b = (string) fread($fh, 32);
        fclose($fh);

        return $b;
    }

    /**
     * Qué es un archivo de verdad, según sus primeros bytes.
     *
     * Es lo único que distingue una foto de una página de error guardada como
     * .png. `getimagesize()` haría lo mismo pero además parsea la imagen para
     * devolver medidas que acá no se usan.
     *
     * Devuelve 'png', 'jpg', 'gif', 'webp', 'bmp', 'avif', 'svg', 'html', 'json'
     * o null (bytes ilegibles).
     */
    public static function firmaDeBytes(string $b)
    {
        if (strlen($b) < 4) return null;

        if (substr($b, 0, 8) === "\x89PNG\r\n\x1a\n")        return 'png';
        if (substr($b, 0, 3) === "\xff\xd8\xff")               return 'jpg';
        if (substr($b, 0, 6) === 'GIF87a' || substr($b, 0, 6) === 'GIF89a') return 'gif';
        if (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP')     return 'webp';
        if (substr($b, 0, 2) === 'BM')                          return 'bmp';
        if (substr($b, 4, 4) === 'ftyp')                        return 'avif';

        $texto = mb_strtolower(ltrim($b));
        if (strpos($texto, '<?xml') === 0 || strpos($texto, '<svg') === 0) return 'svg';
        if (strpos($texto, '<') === 0)                                     return 'html';
        if (strpos($texto, '{') === 0 || strpos($texto, '[') === 0)        return 'json';

        return null;
    }

    /**
     * Cuánto dice medir un WebP, según su propia cabecera RIFF.
     *
     * Los bytes 4-7 traen el tamaño del contenido en little-endian; el archivo
     * completo son esos + los 8 de "RIFF" y el tamaño. Los rotos de producción
     * declaran 12.846 bytes y ocupan 20.267: cada byte alto del original se
     * convirtió en tres (`EF BF BD`) y el archivo quedó inflado.
     *
     * @return int|null el tamaño total esperado, o null si no se puede leer
     */
    private static function tamanoWebp(string $cabecera)
    {
        if (strlen($cabecera) < 8) return null;

        $n = @unpack('V', substr($cabecera, 4, 4));
        if (!is_array($n) || !isset($n[1])) return null;

        $total = (int) $n[1] + 8;

        return $total > 8 ? $total : null;
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
            'creditos'     => 0,   // intentos de descarga = créditos de ScraperAPI
            'quedan'       => 0,
            'abandonado'   => false,  // se cortó por fallar todo seguido
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
                    $r['creditos'] += isset($baja['intentos']) ? (int) $baja['intentos'] : 1;

                    if (empty($baja['ok'])) {
                        $r['fallidas']++;
                        if ($baja['error'] !== '' && count($r['errores']) < 20) {
                            $r['errores'][] = $fila['quien'] . ': ' . $baja['error'];
                        }

                        // Si viene fallando TODO desde el principio, el problema
                        // no es esta foto: es el camino de descarga. Cada intento
                        // gasta un crédito de ScraperAPI, así que se corta acá en
                        // vez de quemar la tanda entera para llegar a la misma
                        // conclusión.
                        if ($r['bajadas'] === 0 && $r['fallidas'] >= self::FALLAS_SEGUIDAS) {
                            $r['abandonado'] = true;
                            $cortado = true;   // para que 'quedan' diga cuántas no se tocaron
                            break 3;
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
     * Tres diferencias con el importador, y las tres salen de lo que apareció
     * roto en producción:
     *
     * 1. **Se valida el contenido ANTES de escribir**, y no solo la cabecera:
     *    los archivos que rompen la pantalla son respuestas de ScraperAPI que
     *    llegan con HTTP 200 y `Content-Type: image/png` pero traen el binario
     *    pasado por UTF-8 (cada byte alto convertido en U+FFFD, `EF BF BD`).
     *    La cabecera queda intacta y `getimagesizefromstring()` dice que sí, así
     *    que se cuentan los reemplazos y se compara el tamaño declarado.
     *    **Nada de `imagecreatefromstring()`**: con un WebP arruinado GD no
     *    devuelve false, se muere con un FATAL que el @ no tapa.
     * 2. **La extensión sale de los bytes, no de la URL.** TM publica el retrato
     *    como `.png` pero responde WebP cuando el pedido acepta WebP (que es lo
     *    que manda HttpHelper). Guardarlo como `.png` deja un archivo que el
     *    server sirve con el content-type equivocado.
     * 3. El nombre se filtra: sin eso, `.../big/../../etc/passwd` viajaría a un
     *    `file_put_contents`.
     *
     * @return array ['ok' => bool, 'archivo' => ?string, 'error' => string]
     */
    public static function descargar(string $url, string $quien = ''): array
    {
        $out = ['ok' => false, 'archivo' => null, 'error' => '', 'intentos' => 0];

        $base = self::baseDe($url);
        if ($base === null) {
            $out['error'] = 'la URL de TM no tiene nombre de archivo';
            return $out;
        }

        try {
            $img = HttpHelper::getBinary($url);
        } catch (\Exception $e) {
            $out['error'] = $e->getMessage();
            return $out;
        }

        $out['intentos'] = isset($img['intentos']) ? (int) $img['intentos'] : 1;

        if (empty($img['ok'])) {
            $err = isset($img['error']) ? (string) $img['error'] : '';

            $out['error'] = strpos($err, 'binario_mangleado') === 0
                ? 'el proxy devolvió la foto pasada por texto en los ' . $out['intentos'] . ' intentos'
                : ('HTTP ' . (isset($img['http']) ? $img['http'] : '?') . ($err === '' ? '' : ' (' . $err . ')'));

            return $out;
        }

        $body = isset($img['body']) ? $img['body'] : '';

        if (strlen($body) < self::MIN_BYTES) {
            $out['error'] = 'volvieron ' . strlen($body) . ' bytes: muy poco para una foto';
            return $out;
        }

        // Lo primero que se pregunta, antes que el formato: ¿esto es el binario
        // o es el binario pasado por texto? Un retrato normal no tiene NINGÚN
        // U+FFFD; los que rompen la pantalla tienen cientos o miles.
        $mangleado = substr_count($body, "\xef\xbf\xbd");
        if ($mangleado > 20) {
            $out['error'] = 'lo que volvió viene pasado por UTF-8 (' . number_format($mangleado)
                . ' bytes reemplazados): es la respuesta del proxy, no la foto';
            return $out;
        }

        $medidas = @getimagesizefromstring($body);
        if ($medidas === false) {
            $out['error'] = 'lo que volvió no es una imagen (' . number_format(strlen($body)) . ' bytes)';
            return $out;
        }

        // WebP: la cabecera dice cuánto tiene que medir. Si no coincide, llegó
        // cortado o inflado, y escribirlo sería fabricar otra foto rota.
        if (self::firmaDeBytes(substr($body, 0, 32)) === 'webp') {
            $declarado = self::tamanoWebp(substr($body, 0, 32));
            if ($declarado !== null && (strlen($body) < $declarado || strlen($body) > $declarado + 8)) {
                $out['error'] = 'el WebP dice medir ' . number_format($declarado) . ' bytes y llegaron '
                    . number_format(strlen($body)) . ': viene roto';
                return $out;
            }
        }

        $extension = self::extensionDe($medidas, $body);
        $nombre    = $base . '.' . $extension;
        $destino   = public_path('images/') . $nombre;

        if (@file_put_contents($destino, $body) === false) {
            $out['error'] = 'no se pudo escribir public/images/' . $nombre;
            return $out;
        }

        $out['ok']      = true;
        $out['archivo'] = $nombre;

        return $out;
    }

    /** El nombre (sin extensión) que le corresponde a una URL de retrato de TM. */
    private static function baseDe(string $url)
    {
        $ruta = parse_url($url, PHP_URL_PATH);
        $info = pathinfo((string) $ruta);

        $archivo = isset($info['filename']) ? rtrim($info['filename'], '.') : '';
        if ($archivo === '') return null;

        // El nombre viaja a un path del filesystem: nada de barras ni de "..".
        $archivo = preg_replace('/[^A-Za-z0-9._-]/', '', $archivo);

        return $archivo !== '' ? $archivo : null;
    }

    /** La extensión que corresponde a los bytes que efectivamente llegaron. */
    private static function extensionDe($medidas, string $body): string
    {
        $porTipo = [
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_BMP  => 'bmp',
        ];

        if (defined('IMAGETYPE_WEBP')) $porTipo[IMAGETYPE_WEBP] = 'webp';

        $tipo = is_array($medidas) && isset($medidas[2]) ? (int) $medidas[2] : 0;
        if (isset($porTipo[$tipo])) return $porTipo[$tipo];

        // getimagesize no conoce WebP en PHP viejo: se mira la firma a mano.
        if (substr($body, 0, 4) === 'RIFF' && substr($body, 8, 4) === 'WEBP') return 'webp';

        return 'jpg';
    }
}
