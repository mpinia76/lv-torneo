<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Http\Controllers\PhpmailerController;

/**
 * Backup diario de la BD.
 *
 * - mysqldump a .sql y compresión a .sql.gz por bloques (no carga el dump en memoria).
 * - El .sql.gz queda en storage/app/backup y se borran los de más de BACKUP_KEEP_DAYS días
 *   (también los backup-*.zip viejos que quedaban cuando fallaba el mail).
 * - Se adjunta al mail solo si pesa hasta BACKUP_MAX_ATTACH_MB; si no, se manda un aviso
 *   sin adjunto. PHPMailer arma el mensaje en memoria (base64 + MIME + partido en líneas),
 *   así que adjuntar archivos grandes agota el memory_limit, y Gmail además los rechaza.
 */
class DatabaseBackUp extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Copia de BD';

    const DESTINO = 'crones.codnet@gmail.com';

    public function handle()
    {
        $dir = storage_path('app/backup');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fecha = Carbon::now()->format('Y-m-d');
        $sqlPath = $dir . '/backup-' . $fecha . '.sql';
        $gzPath = $sqlPath . '.gz';

        $keepDays = max(1, (int) env('BACKUP_KEEP_DAYS', 7));
        $maxAttachMb = (float) env('BACKUP_MAX_ATTACH_MB', 15);

        try {
            $this->dump($sqlPath);
            $this->comprimir($sqlPath, $gzPath);
        } catch (\Throwable $e) {
            if (is_file($gzPath)) {
                unlink($gzPath);
            }
            Log::error('database:backup falló: ' . $e->getMessage());
            $this->error($e->getMessage());
            $this->notificar(
                'ERROR BackUp lv-torneo',
                'El backup de la BBDD lv-torneo del ' . $fecha . ' falló:<br><br><pre>' . e($e->getMessage()) . '</pre>'
            );
            return 1;
        } finally {
            if (is_file($sqlPath)) {
                unlink($sqlPath);
            }
        }

        $borrados = $this->rotar($dir, $keepDays, basename($gzPath));

        $bytes = filesize($gzPath);
        $mb = round($bytes / 1048576, 1);
        $adjuntar = $bytes <= $maxAttachMb * 1048576;

        $body = 'Backup de la BBDD lv-torneo realizado el ' . $fecha . ' (' . $mb . ' MB comprimido).<br><br>';
        if ($adjuntar) {
            $body .= 'Va adjunto. ';
        } else {
            $body .= 'Supera el límite de ' . $maxAttachMb . ' MB para adjuntar, así que no va en el mail. ';
        }
        $body .= 'Queda guardado en el servidor en storage/app/backup/' . basename($gzPath)
            . ' (se conservan ' . $keepDays . ' días).';
        if ($borrados > 0) {
            $body .= '<br>Se borraron ' . $borrados . ' backup(s) viejo(s).';
        }

        $this->notificar('BackUp lv-torneo', $body, $adjuntar ? [$gzPath] : []);
        $this->info('Backup OK: ' . $gzPath . ' (' . $mb . ' MB)');

        return 0;
    }

    private function dump($sqlPath)
    {
        $command = env('DUMP_PATH')
            . ' --user=' . escapeshellarg(env('DB_USERNAME'))
            . ' --password=' . escapeshellarg(env('DB_PASSWORD'))
            . ' --host=' . escapeshellarg(env('DB_HOST'))
            . ' --single-transaction --quick'
            . ' --result-file=' . escapeshellarg($sqlPath)
            . ' ' . escapeshellarg(env('DB_DATABASE'))
            . ' 2>&1';

        $output = [];
        $returnVar = null;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \RuntimeException('mysqldump terminó con código ' . $returnVar . ': ' . implode("\n", $output));
        }
        if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
            throw new \RuntimeException('mysqldump no generó el archivo o quedó vacío. ' . implode("\n", $output));
        }
    }

    private function comprimir($origen, $destino)
    {
        $in = fopen($origen, 'rb');
        if ($in === false) {
            throw new \RuntimeException('No se pudo abrir ' . $origen);
        }
        $out = gzopen($destino, 'wb6');
        if ($out === false) {
            fclose($in);
            throw new \RuntimeException('No se pudo crear ' . $destino);
        }

        try {
            while (!feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === false) {
                    throw new \RuntimeException('Error leyendo ' . $origen);
                }
                if ($chunk !== '' && gzwrite($out, $chunk) === false) {
                    throw new \RuntimeException('Error escribiendo ' . $destino . ' (¿disco lleno?)');
                }
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    /**
     * Borra backup-*.sql.gz / .zip / .sql con más de $keepDays días. Devuelve cuántos borró.
     */
    private function rotar($dir, $keepDays, $actual)
    {
        $limite = time() - $keepDays * 86400;
        $borrados = 0;

        foreach (glob($dir . '/backup-*') ?: [] as $archivo) {
            if (basename($archivo) === $actual || !is_file($archivo)) {
                continue;
            }
            if (!preg_match('/\.(sql\.gz|zip|sql)$/', $archivo)) {
                continue;
            }
            if (filemtime($archivo) < $limite && @unlink($archivo)) {
                $borrados++;
            }
        }

        return $borrados;
    }

    private function notificar($subject, $body, array $attachs = [])
    {
        $mailer = new PhpmailerController();
        $ok = $mailer->sendEmail([
            'email' => self::DESTINO,
            'subject' => $subject,
            'body' => $body,
            'attachs' => $attachs,
        ]);

        if (!$ok) {
            $this->warn('No se pudo enviar el mail de aviso (ver laravel.log).');
        }
    }
}
