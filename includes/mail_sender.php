<?php
/**
 * Función centralizada para enviar correos electrónicos usando PHPMailer.
 * AHORA CON SOPORTE MEJORADO PARA ARCHIVOS ADJUNTOS DESDE RUTA.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/Exception.php';
require_once 'PHPMailer/PHPMailer.php';
require_once 'PHPMailer/SMTP.php';
require_once 'config.php';

/**
 * Envía un correo electrónico.
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $body
 * @param array $attachments Array de rutas de archivos para adjuntar. Ej: ['/ruta/al/reporte.pdf']
 * @return bool
 */
function send_email($toEmail, $toName, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Remitente y Destinatario
        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($toEmail, $toName);

        // --- NUEVA LÓGICA PARA ARCHIVOS ADJUNTOS ---
        if (!empty($attachments)) {
            foreach ($attachments as $file_path) {
                // Adjuntar un archivo desde una ruta del servidor
                if (file_exists($file_path)) {
                    $mail->addAttachment($file_path);
                }
            }
        }

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("El mensaje no pudo ser enviado. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}