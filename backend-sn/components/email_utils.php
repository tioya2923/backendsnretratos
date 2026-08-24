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
        // Sem isto, uma porta SMTP bloqueada (como aconteceu na PTisp)
        // fica pendurada minutos em vez de falhar depressa — o pedido
        // HTTP inteiro (ex.: registo) ficava à espera desse tempo todo.
        $mail->Timeout       = 10;
        $mail->SMTPKeepAlive = false;

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

/**
 * Fila de emails a enviar em segundo plano (processada pelo cron, em
 * enviar_lembretes.php). Usar em vez de sendEmail() direto sempre que o
 * envio não pode atrasar a resposta ao pedido que o disparou — ex.:
 * registar.php, onde esperar pelo SMTP (bloqueado na PTisp, timeout de
 * 10s) chegou a prender o registo durante ~20s.
 */
function criarTabelaEmailsPendentes($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS emails_pendentes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        destinatario VARCHAR(255) NOT NULL,
        assunto VARCHAR(255) NOT NULL,
        corpo MEDIUMTEXT NOT NULL,
        is_html TINYINT(1) NOT NULL DEFAULT 1,
        tentativas INT NOT NULL DEFAULT 0,
        enviado_em TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Põe um email na fila em vez de o enviar já. Só faz um INSERT — não toca
 * em SMTP, por isso é sempre rápido, mesmo com a porta bloqueada.
 */
function enfileirarEmail(mysqli $conn, string $to, string $subject, string $body, bool $isHtml = true): void {
    criarTabelaEmailsPendentes($conn);
    $isHtmlInt = $isHtml ? 1 : 0;
    $stmt = $conn->prepare(
        "INSERT INTO emails_pendentes (destinatario, assunto, corpo, is_html) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("sssi", $to, $subject, $body, $isHtmlInt);
    $stmt->execute();
    $stmt->close();
}
