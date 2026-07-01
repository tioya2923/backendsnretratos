<?php
// Incluir os ficheiros de conexão e configurações necessários

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/whatsapp_utils.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$frontendUrl = rtrim(getenv('FRONTEND_URL') ?: '', '/');

function renderPage(bool $success, string $title, string $message, ?string $loginUrl = null): void {
    $icon = $success ? '✓' : '✕';
    $iconBg = $success ? '#2f7d32' : '#a05c5c';
    $button = $loginUrl
        ? "<a class=\"btn\" href=\"$loginUrl\">Iniciar sessão</a>"
        : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title — Paróquia de São Nicolau</title>
<style>
  * { box-sizing: border-box; }
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #4b0303 80%, #7c1c1c 100%);
    font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;
    padding: 24px;
  }
  .card {
    background: #fff8f8;
    border-radius: 14px;
    max-width: 380px;
    width: 100%;
    padding: 36px 28px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,.25);
  }
  .icon {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: $iconBg;
    color: #fff;
    font-size: 28px;
    line-height: 56px;
    margin: 0 auto 18px;
  }
  h1 { color: #4b0303; font-size: 1.3em; margin: 0 0 10px; }
  p { color: #6b3a3a; margin: 0 0 20px; line-height: 1.5; }
  .btn {
    display: inline-block;
    padding: 12px 28px;
    background: #4b0303;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
  }
  .btn:hover { background: #7c1c1c; }
</style>
</head>
<body>
  <div class="card">
    <div class="icon">$icon</div>
    <h1>$title</h1>
    <p>$message</p>
    $button
  </div>
</body>
</html>
HTML;
}

// Obter e validar o código de aprovação do URL (token hexadecimal de 32 caracteres)
$approvalCode = $_GET['code'] ?? '';
$approvalCode = (is_string($approvalCode) && ctype_xdigit($approvalCode)) ? $approvalCode : '';

if ($approvalCode === '') {
    renderPage(false, 'Código inválido', 'O código de aprovação não foi fornecido ou é inválido.');
    $conn->close();
    exit;
}

$sql = "SELECT * FROM usuarios WHERE approval_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $approvalCode);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    renderPage(false, 'Código inválido', 'Este código de aprovação não é válido ou já foi utilizado.');
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$userEmail = $user['email'];
$userWhatsapp = $user['whatsapp'] ?? '';

$sqlUpdate = "UPDATE usuarios SET status = 'aprovado' WHERE approval_code = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("s", $approvalCode);

if (!$stmtUpdate->execute()) {
    renderPage(false, 'Erro', 'Não foi possível aprovar o registo. Tente novamente.');
    $stmt->close();
    $conn->close();
    exit;
}

$loginUrl = $frontendUrl . '/login';

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('MAIL_USERNAME');
    $mail->Password = getenv('MAIL_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('retratospsn@gmail.com', 'Paróquia de São Nicolau');
    $mail->addAddress($userEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Conta aprovada!';
    $mail->Body = "Parabéns, registo aprovado! <a href='$loginUrl'>Iniciar sessão</a><br>";
    $mail->AltBody = "Parabéns, registo aprovado! Iniciar sessão: $loginUrl";

    $mail->send();
} catch (Exception $e) {
    error_log('Erro ao enviar email de aprovação: ' . $mail->ErrorInfo);
}

if (!empty($userWhatsapp)) {
    try {
        sendWhatsApp($userWhatsapp, "A sua conta foi aprovada! Já pode iniciar sessão: $loginUrl");
    } catch (\Throwable $e) {
        error_log('Erro ao enviar WhatsApp de aprovação: ' . $e->getMessage());
    }
}

renderPage(true, 'Registo aprovado!', 'A conta foi aprovada com sucesso. Já pode iniciar sessão na aplicação.', $loginUrl);

$stmt->close();
$conn->close();
