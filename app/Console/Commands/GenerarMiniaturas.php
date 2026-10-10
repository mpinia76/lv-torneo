<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Miniaturas de public/images/ en public/img-min/ (mismo nombre y subcarpetas),
 * de MAX píxeles como máximo de lado. Las sirve nginx en lugar de la original
 * en las páginas públicas (ver App\Services\ImagenesLivianas).
 *
 * MAX = 240: lo más grande que muestra el sitio público es la foto de la ficha
 * (116 px); 240 cubre pantallas de doble densidad.
 *
 * Incremental: solo procesa las imágenes nuevas o cambiadas desde la última
 * corrida (compara fechas). Va en el crontab de 'deploy', después del sitemap:
 *
 *   45 4 * * * /usr/bin/php /var/www/lv-torneo/artisan imagenes:miniaturas >> /dev/null 2>&1
 *
 * Si la miniatura no sale más liviana que la original (imagen ya chica, o una
 * que GD no puede leer), se copia la original tal cual: así queda registrada y
 * no se reintenta cada noche.
 */
class GenerarMiniaturas extends Command
{
    protected $signature = 'imagenes:miniaturas {--forzar : rehace todas, aunque ya existan}';

    protected $description = 'Genera public/img-min/ con copias livianas de public/images/';

    const MAX = 240;
    const CALIDAD_JPG = 82;

    public function handle()
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->error('Falta la extensión GD de PHP.');
            return 1;
        }

        $origen  = public_path('images');
        $destino = public_path('img-min');
        $forzar  = (bool) $this->option('forzar');

        $n = ['nuevas' => 0, 'copiadas' => 0, 'al_dia' => 0, 'error' => 0];
        $antes = $despues = 0;

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($origen, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            if (!$archivo->isFile() || !preg_match('/\.(png|jpe?g)$/i', $archivo->getFilename())) {
                continue;
            }
            $src = $archivo->getPathname();
            $dst = $destino . substr($src, strlen($origen));

            if (!$forzar && is_file($dst) && filemtime($dst) >= filemtime($src)) {
                $n['al_dia']++;
                continue;
            }
            if (!is_dir(dirname($dst))) {
                mkdir(dirname($dst), 0775, true);
            }

            $peso = filesize($src);
            try {
                $ok = $this->achicar($src, $dst);
            } catch (\Throwable $e) {
                $ok = false;
            }

            if (!$ok || !is_file($dst) || filesize($dst) >= $peso) {
                // No mejora (o GD no la lee): queda la original, así no se reintenta.
                if (!copy($src, $dst)) {
                    $n['error']++;
                    continue;
                }
                $n['copiadas']++;
            } else {
                $n['nuevas']++;
            }
            $antes   += $peso;
            $despues += filesize($dst);
        }

        $this->info(sprintf('Miniaturas: %d nuevas, %d copiadas sin cambio, %d al día, %d con error.',
            $n['nuevas'], $n['copiadas'], $n['al_dia'], $n['error']));
        if ($antes > 0) {
            $this->line(sprintf('Procesadas en esta corrida: %s KB → %s KB.', number_format($antes / 1024, 0, ',', '.'), number_format($despues / 1024, 0, ',', '.')));
        }
        return 0;
    }

    /** Escribe en $dst la versión de MAX px como máximo. false si no pudo. */
    protected function achicar($src, $dst)
    {
        $info = @getimagesize($src);
        if (!$info) {
            return false;
        }
        [$w, $h, $tipo] = $info;
        $esPng = $tipo === IMAGETYPE_PNG;
        if (!$esPng && $tipo !== IMAGETYPE_JPEG) {
            return false;
        }

        $escala = min(1, self::MAX / max($w, $h));
        $nw = max(1, (int) round($w * $escala));
        $nh = max(1, (int) round($h * $escala));

        $img = $esPng ? @imagecreatefrompng($src) : @imagecreatefromjpeg($src);
        if (!$img) {
            return false;
        }

        $out = imagecreatetruecolor($nw, $nh);
        if ($esPng) {
            // Los escudos tienen transparencia: hay que conservarla.
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        }
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $ok = $esPng ? imagepng($out, $dst, 9) : imagejpeg($out, $dst, self::CALIDAD_JPG);
        imagedestroy($img);
        imagedestroy($out);

        return $ok;
    }
}
