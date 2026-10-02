<?php

namespace App\Http\Controllers;



// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Illuminate\Support\Facades\Log;

class PhpmailerController extends Controller {

    /**
     * Devuelve true si el mail salió, false si no (el motivo queda en laravel.log).
     */
    public function sendEmail ($data) {

        $mail = new PHPMailer(true); // Passing `true` enables exceptions

        try {

            // Mail server settings

            // 0 = sin debug. Con 4 vuelca cada línea enviada al SMTP (adjuntos incluidos).
            $mail->SMTPDebug = 0;
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host = env('MAIL_HOST'); // Specify main and backup SMTP servers
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = env('MAIL_USERNAME'); // SMTP username
            $mail->Password = env('MAIL_PASSWORD'); // SMTP password
            $mail->SMTPSecure = env('MAIL_ENCRYPTION'); // Enable TLS encryption, `ssl` also accepted
            $mail->Port = env('MAIL_PORT'); // TCP port to connect to
            $mail->CharSet = PHPMailer::CHARSET_UTF8;

            $mail->setFrom(env('MAIL_FROM_ADDRESS'));
            $mail->addAddress($data['email']); // Add a recipient, Name is optional

            if (!empty($data['attachs'])) {
                foreach ($data['attachs'] as $attach){
                    $mail->addAttachment($attach); // Optional name
                }
            }

            $mail->isHTML(true); // Set email format to HTML

            $mail->Subject = $data['subject'];
            $mail->Body    = $data['body'];

            if( !$mail->send() ) {
                Log::error('Error al enviar mail: '.$mail->ErrorInfo,[]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            Log::error('Error al enviar mail: '.$mail->ErrorInfo.' '.$e->getMessage(),[]);
            return false;
        }

    }
}
