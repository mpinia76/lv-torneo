<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sitemap para los buscadores: public/sitemap.xml (índice) + public/sitemaps/*.xml.gz.
 *
 * Se arma como archivos estáticos (los sirve nginx, sin tocar PHP ni la base) y
 * se regenera una vez por día desde el crontab de 'deploy':
 *
 *   30 4 * * * /usr/bin/php /var/www/lv-torneo/artisan sitemap:generar >> /dev/null 2>&1
 *
 * Solo entran fichas que tienen contenido (un jugador con al menos una
 * alineación, un técnico con al menos un partido dirigido…): las vacías son
 * páginas pobres que bajan la calidad del sitio entero para Google.
 *
 * Van las URLs en español. Las de /en las descubre Google por los
 * <link rel="alternate" hreflang> de cada página (metaPublic.blade.php).
 *
 * Límite del protocolo: 50.000 URLs y 50 MB por archivo. Se corta en
 * POR_ARCHIVO para quedar lejos de los dos.
 */
class GenerarSitemap extends Command
{
    protected $signature = 'sitemap:generar';

    protected $description = 'Genera public/sitemap.xml y public/sitemaps/*.xml.gz';

    const POR_ARCHIVO = 40000;

    public function handle()
    {
        $base = rtrim(config('app.url'), '/');
        if (!preg_match('#^https://#', $base)) {
            $this->error('APP_URL tiene que ser la URL pública con https (hoy: ' . $base . ').');
            return 1;
        }

        $dirFinal = public_path('sitemaps');
        $dirTmp   = public_path('sitemaps.tmp');
        $this->borrarDir($dirTmp);
        mkdir($dirTmp, 0775, true);

        // [nombre del archivo, ruta, parámetro, consulta que devuelve los ids]
        $grupos = [
            // La página de un torneo es su fixture (la que usa el menú); torneos.ver
            // es una página de paso con cinco botones y lleva noindex.
            ['torneos',   'fechas.ver',     'torneoId',
                'SELECT id FROM torneos ORDER BY id'],
            ['equipos',   'equipos.ver',    'equipoId',
                'SELECT e.id FROM equipos e
                  WHERE EXISTS (SELECT 1 FROM partidos p WHERE p.equipol_id = e.id OR p.equipov_id = e.id)
                  ORDER BY e.id'],
            ['jugadores', 'jugadores.ver',  'jugadorId',
                'SELECT DISTINCT jugador_id AS id FROM alineacions ORDER BY jugador_id'],
            ['tecnicos',  'tecnicos.ver',   'tecnicoId',
                'SELECT DISTINCT tecnico_id AS id FROM partido_tecnicos ORDER BY tecnico_id'],
            // Árbitros: afuera hasta que la ficha muestre estadísticas (hoy está
            // vacía y lleva noindex). Para volver a sumarlos:
            // ['arbitros', 'arbitros.ver', 'arbitroId',
            //     'SELECT DISTINCT arbitro_id AS id FROM partido_arbitros ORDER BY arbitro_id'],
            ['partidos',  'fechas.detalle', 'partidoId',
                'SELECT id FROM partidos WHERE golesl IS NOT NULL AND golesv IS NOT NULL ORDER BY id'],
        ];

        $archivos = [];

        // Páginas fijas del sitio público.
        $fijas = [];
        foreach (['home', 'torneos.explorar', 'torneos.historiales', 'torneos.goleadores',
                  'torneos.jugadores', 'torneos.tarjetas', 'torneos.posiciones',
                  'torneos.estadisticasOtras', 'torneos.tecnicos', 'torneos.arqueros',
                  'torneos.titulos'] as $nombre) {
            $fijas[] = $this->url($base, $nombre, null, null);
        }
        $archivos[] = $this->escribir($dirTmp, 'paginas-1.xml.gz', $fijas);
        $total = count($fijas);

        foreach ($grupos as [$nombre, $ruta, $param, $sql]) {
            // La URL se arma una sola vez con un marcador y después se reemplaza el
            // id: route() por cada una de 1,7 M filas sería lento.
            $molde = $this->url($base, $ruta, $param, '__ID__');

            $ids = array_map(function ($f) { return (int) $f->id; }, DB::select($sql));
            $partes = array_chunk($ids, self::POR_ARCHIVO);

            foreach ($partes as $i => $bloque) {
                $urls = [];
                foreach ($bloque as $id) {
                    $urls[] = str_replace('__ID__', $id, $molde);
                }
                $archivos[] = $this->escribir($dirTmp, $nombre . '-' . ($i + 1) . '.xml.gz', $urls);
            }

            $this->line(sprintf('%-10s %7d URLs en %d archivo(s)', $nombre, count($ids), count($partes)));
            $total += count($ids);
        }

        // Cambio atómico: primero el directorio, después el índice. Si algo falló
        // antes, queda el sitemap del día anterior intacto.
        $this->borrarDir($dirFinal . '.old');
        if (is_dir($dirFinal)) {
            rename($dirFinal, $dirFinal . '.old');
        }
        rename($dirTmp, $dirFinal);
        $this->borrarDir($dirFinal . '.old');

        $hoy = date('Y-m-d');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($archivos as $a) {
            $xml .= '  <sitemap><loc>' . $base . '/sitemaps/' . $a . '</loc><lastmod>' . $hoy . '</lastmod></sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>' . "\n";

        $indice = public_path('sitemap.xml');
        file_put_contents($indice . '.tmp', $xml);
        rename($indice . '.tmp', $indice);

        $this->info('Sitemap OK: ' . $total . ' URLs en ' . count($archivos) . ' archivos → ' . $base . '/sitemap.xml');
        return 0;
    }

    /** URL absoluta en español, con el dominio de APP_URL. */
    private function url($base, $ruta, $param, $valor)
    {
        $u = $param === null
            ? route($ruta, [], false)
            : route($ruta, [$param => $valor], false);

        return $base . ($u === '' ? '/' : $u);
    }

    private function escribir($dir, $archivo, array $urls)
    {
        $gz = gzopen($dir . '/' . $archivo, 'wb6');
        gzwrite($gz, '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");
        foreach ($urls as $u) {
            gzwrite($gz, '<url><loc>' . htmlspecialchars($u, ENT_XML1) . '</loc></url>' . "\n");
        }
        gzwrite($gz, '</urlset>' . "\n");
        gzclose($gz);

        return $archivo;
    }

    private function borrarDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
