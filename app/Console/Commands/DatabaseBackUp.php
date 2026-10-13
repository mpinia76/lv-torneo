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
 * - Si no entra en el mail y BACKUP_RCLONE_REMOTE está configurado, se sube ahí con rclone
 *   (p. ej. Google Drive) y, si la subida salió bien, se BORRA la copia local: no queda en el
 *   servidor. En el remoto se borran los de más de BACKUP_REMOTE_KEEP_DAYS días (30).
 *   Si la subida falla, la copia local queda (con la rotación de siempre) y el mail lo avisa.
 *   Mismo esquema que lv-proyectos.
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

        // Solo lo que no entra en el mail va al remoto.
        $remoto = $adjuntar ? null : $this->subirRemoto($gzPath);
        $quedaLocal = true;
        if ($remoto !== null && $remoto['ok']) {
            if (@unlink($gzPath)) {
                $quedaLocal = false;
            } else {
                Log::warning('database:backup: subido, pero no se pudo borrar ' . $gzPath);
            }
        }

        $body = 'Backup de la BBDD lv-torneo realizado el ' . $fecha . ' (' . $mb . ' MB comprimido).<br><br>';
        if ($adjuntar) {
            $body .= 'Va adjunto. ';
        } else {
            $body .= 'Supera el límite de ' . $maxAttachMb . ' MB para adjuntar, así que no va en el mail. ';
        }
        if ($remoto !== null) {
            $body .= '<br><br>' . $remoto['mensaje'] . '<br><br>';
        }
        if ($quedaLocal) {
            $body .= 'Queda guardado en el servidor en storage/app/backup/' . basename($gzPath)
                . ' (se conservan ' . $keepDays . ' días).';
        } else {
            $body .= 'No queda copia en el servidor.';
        }
        if ($borrados > 0) {
            $body .= '<br>Se borraron ' . $borrados . ' backup(s) viejo(s) del servidor.';
        }

        $subject = 'BackUp lv-torneo';
        if ($remoto !== null && !$remoto['ok']) {
            $subject = 'ERROR subida BackUp lv-torneo';
        }

        $this->notificar($subject, $body, $adjuntar ? [$gzPath] : []);
        $this->info('Backup OK: ' . basename($gzPath) . ' (' . $mb . ' MB)'
            . ($quedaLocal ? ' en ' . $dir : ' — solo en el remoto'));

        return 0;
    }

    /**
     * Sube el backup al remoto de rclone y rota los viejos allá.
     * Devuelve null si no hay remoto configurado, o ['ok' => bool, 'mensaje' => html].
     */
    private function subirRemoto($gzPath)
    {
        $remote = rtrim((string) env('BACKUP_RCLONE_REMOTE', ''), '/');
        if ($remote === '') {
            return null;
        }
        $rclone = escapeshellcmd(env('BACKUP_RCLONE_PATH') ?: '/usr/bin/rclone');
        $destino = $remote . '/' . basename($gzPath);

        $output = [];
        $code = null;
        exec($rclone . ' copyto ' . escapeshellarg($gzPath) . ' ' . escapeshellarg($destino)
            . ' --retries 3 --low-level-retries 10 2>&1', $output, $code);

        if ($code !== 0) {
            $err = implode("\n", array_slice($output, -15));
            Log::error('database:backup: falló la subida a ' . $destino . ': ' . $err);
            $this->warn('Falló la subida a ' . $destino);
            return [
                'ok' => false,
                'mensaje' => '<b>No se pudo subir a ' . e($destino) . '</b> (rclone código ' . $code . '):<br><pre>' . e($err) . '</pre>',
            ];
        }

        $this->info('Subido a ' . $destino);
        $mensaje = 'Copia subida a ' . e($destino) . '.';

        $remoteKeep = (int) env('BACKUP_REMOTE_KEEP_DAYS', 30);
        if ($remoteKeep > 0) {
            $out2 = [];
            $code2 = null;
            // --drive-use-trash=false: si no, en Drive lo borrado sigue ocupando espacio en la papelera.
            exec($rclone . ' delete ' . escapeshellarg($remote)
                . ' --min-age ' . $remoteKeep . 'd --include ' . escapeshellarg('backup-*.sql.gz')
                . ' --drive-use-trash=false 2>&1', $out2, $code2);
            if ($code2 !== 0) {
                Log::warning('database:backup: no se pudieron borrar los viejos en ' . $remote . ': ' . implode("\n", $out2));
                $mensaje .= ' (No se pudieron borrar los de más de ' . $remoteKeep . ' días allá; ver laravel.log.)';
            } else {
                $mensaje .= ' Allá se conservan ' . $remoteKeep . ' días.';
            }
        }

        return ['ok' => true, 'mensaje' => $mensaje];
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
