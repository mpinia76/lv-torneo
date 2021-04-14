<?php

namespace App\Http\Controllers;



// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

use Illuminate\Support\Facades\Log;

class PhpmailerController extends Controller {

    public function sendEmail ($data) {



        //require __DIR__.'/vendor/autoload.php'; // load Composer's autoloader

        $mail = new PHPMailer(true); // Passing `true` enables exceptions

        try {

            // Mail server settings

            $mail->SMTPDebug = 4; // Enable verbose debug output
            $mail->isSMTP(); // Set mailer to use SMTP
            $mail->Host = env('MAIL_HOST'); // Specify main and backup SMTP servers
            $mail->SMTPAuth = true; // Enable SMTP authentication
            $mail->Username = env('MAIL_USERNAME'); // SMTP username
            $mail->Password = env('MAIL_PASSWORD'); // SMTP password
            $mail->SMTPSecure = env('MAIL_ENCRYPTION'); // Enable TLS encryption, `ssl` also accepted
            $mail->Port = env('MAIL_PORT'); // TCP port to connect to

            $mail->setFrom(env('MAIL_FROM_ADDRESS'));
            $mail->addAddress($data['email']); // Add a recipient, Name is optional
            /*$mail->addCC($_POST['email-cc']);
            $mail->addBCC($_POST['email-bcc']);
            $mail->addReplyTo('your-email@gmail.com', 'Your Name');*/
            // print_r($_FILES['file']); exit;

            /*for ($i=0; $i < count($_FILES['file']['tmp_name']) ; $i++) {
                $mail->addAttachment($_FILES['file']['tmp_name'][$i], $_FILES['file']['name'][$i]); // Optional name
            }*/

            foreach ($data['attachs'] as $attach){
                $mail->addAttachment($attach); // Optional name
            }

            $mail->isHTML(true); // Set email format to HTML

            $mail->Subject = $data['subject'];//'BackUp lv-torneo'
            $mail->Body    = $data['body'];//'En el archivo adjunto se encuentra el BackUp de la BBDD lv-torneo realizado el '
            // $mail->AltBody = plain text version of your message;

            if( !$mail->send() ) {

                Log::error('Error al enviar mail: '.$mail->ErrorInfo,[]);
                /*echo 'Message could not be sent.';
                echo 'Mailer Error: ' . $mail->ErrorInfo;*/
            }

        } catch (Exception $e) {
            // return back()->with('error','Message could not be sent.');
        }

    }
}
