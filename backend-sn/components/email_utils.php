<?php
/**
 * Utilitário de email via API HTTP da Brevo (antes usava PHPMailer/Gmail
 * SMTP — a porta SMTP de saída está bloqueada na PTisp, confirmado ao vivo:
 * "SMTP Error: Could not connect to SMTP host". A API da Brevo usa HTTPS
 * (porta 443), que não tem esse bloqueio.
 * Usar em cron scripts e outros componentes do backend.
 */

/**
 * Envia um email via a API da Brevo.
 *
 * @param string $to      Endereço de destino
 * @param string $subject Assunto
 * @param string $body    Corpo (HTML ou texto simples)
 * @param bool   $isHtml  true = HTML, false = texto simples
 * @return bool
 */
function sendEmail(string $to, string $subject, string $body, bool $isHtml = false, ?string &$erro = null): bool {
    if (empty(trim($to))) {
        $erro = 'endereço de destino vazio';
        error_log("sendEmail: $erro.");
        return false;
    }

    $apiKey = getenv('BREVO_API_KEY');
    if (!$apiKey) {
        $erro = 'BREVO_API_KEY não configurado';
        error_log("sendEmail: $erro.");
        return false;
    }

    // Tem de ser um remetente verificado na conta Brevo (single sender ou
    // domínio verificado) — senão a API recusa o pedido.
    $remetenteEmail = getenv('MAIL_USERNAME') ?: 'retratospsn@gmail.com';

    $payload = [
        'sender'      => ['name' => 'Paróquia de São Nicolau', 'email' => $remetenteEmail],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'htmlContent' => $isHtml ? $body : ('<pre style="font-family:inherit;white-space:pre-wrap">' . htmlspecialchars($body) . '</pre>'),
        'textContent' => $isHtml ? strip_tags($body) : $body,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        // Timeout curto: uma API HTTPS lenta/em baixo não deve prender o
        // pedido que disparou o envio (mesma razão de ser da fila).
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErro = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        $erro = "Falha de rede: $curlErro";
        error_log("sendEmail (Brevo) falhou para $to: $erro");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    $erro = "HTTP $httpCode: $response";
    error_log("sendEmail (Brevo) falhou para $to: $erro");
    return false;
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
