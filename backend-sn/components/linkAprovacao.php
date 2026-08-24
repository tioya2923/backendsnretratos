<?php
// Incluir os ficheiros de conexão e configurações necessários

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/email_utils.php';

// cors.php define Content-Type: application/json por omissão — esta página
// devolve HTML, por isso tem de substituir esse header.
header('Content-Type: text/html; charset=UTF-8');

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
$result = stmt_get_result($stmt);

if ($result->num_rows === 0) {
    renderPage(false, 'Código inválido', 'Este código de aprovação não é válido ou já foi utilizado.');
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();
$userEmail = $user['email'];

// O código de aprovação nunca é limpo depois de usado (fica válido
// para sempre) — sem isto, reabrir o mesmo link reenviava o email de
// aprovação outra vez, a cada clique.
if ($user['status'] === 'aprovado') {
    $loginUrl = $frontendUrl . '/login';
    renderPage(true, 'Já aprovado', 'Esta conta já tinha sido aprovada anteriormente. Pode iniciar sessão.', $loginUrl);
    $stmt->close();
    $conn->close();
    exit;
}

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

// Nota: este fluxo de aprovação por email está obsoleto desde que o
// registo passou a ser automático (registar.php já não gera
// approval_code nenhum) — mantido só por segurança, caso ainda exista
// algum link antigo por aí.
$erroEnvio = null;
$okEnvio = sendEmail(
    $userEmail,
    'Conta aprovada!',
    "Parabéns, registo aprovado! <a href='$loginUrl'>Iniciar sessão</a><br>",
    true,
    $erroEnvio
);
if (!$okEnvio) {
    error_log("Erro ao enviar email de aprovação para $userEmail: $erroEnvio");
}

renderPage(true, 'Registo aprovado!', 'A conta foi aprovada com sucesso. Já pode iniciar sessão na aplicação.', $loginUrl);

$stmt->close();
$conn->close();
