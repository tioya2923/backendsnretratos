<?php
/**
 * Utilitário de email via PHPMailer / Gmail SMTP.
 * Usar em cron scripts e outros componentes do backend.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Envia um email via Gmail SMTP.
 *
 * @param string $to      Endereço de destino
 * @param string $subject Assunto
 * @param string $body    Corpo (HTML ou texto simples)
 * @param bool   $isHtml  true = HTML, false = texto simples
 * @return bool
 */
function sendEmail(string $to, string $subject, string $body, bool $isHtml = false): bool {
    if (empty(trim($to))) {
        error_log("sendEmail: endereço de destino vazio.");
        return false;
    }

    $mailUser = getenv('MAIL_USERNAME') ?: 'retratospsn@gmail.com';
    $mailPass = getenv('MAIL_PASSWORD');

    if (!$mailPass) {
        error_log("sendEmail: MAIL_PASSWORD não configurado.");
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $mailUser;
        $mail->Password   = $mailPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($mailUser, 'Paróquia de São Nicolau');
        $mail->addAddress($to);

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("sendEmail falhou para $to: " . $e->getMessage());
        return false;
    }
}
