<?php
/**
 * Passo 1 da recuperação de palavra-passe: recebe o email, e se existir
 * uma conta com esse email, gera um código de recuperação válido por 1h e
 * envia um link por email (fila — mesmo motivo de sempre: SMTP síncrono
 * bloqueado na PTisp).
 *
 * Responde sempre com a mesma mensagem genérica, exista ou não a conta —
 * para não revelar a quem pede se um email está ou não registado na app.
 */

ini_set('display_errors', 0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/email_utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

function criarTabelaPasswordResets(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        expira_em DATETIME NOT NULL,
        usado TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_token (token),
        INDEX idx_user (user_id),
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
criarTabelaPasswordResets($conn);

$data  = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);

// Mensagem sempre igual, mesmo se o email for inválido/vazio — evita
// distinguir "email mal formatado" de "email não registado".
$respostaGenerica = [
    'status'  => 'success',
    'message' => 'Se esse email estiver registado, vai receber um link para redefinir a palavra-passe.'
];

if (!$email) {
    echo json_encode($respostaGenerica);
    exit;
}

$stmt = $conn->prepare("SELECT id, name FROM usuarios WHERE email = ? AND status = 'aprovado'");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if ($user) {
    $token    = bin2hex(random_bytes(32));
    $expiraEm = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $insert = $conn->prepare("INSERT INTO password_resets (user_id, token, expira_em) VALUES (?, ?, ?)");
    $insert->bind_param("iss", $user['id'], $token, $expiraEm);
    $insert->execute();
    $insert->close();

    $link = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/redefinir-password?token=' . $token;
    $nome = htmlspecialchars($user['name']);

    $corpo = "
        <p>Olá, $nome.</p>
        <p>Pediu para redefinir a palavra-passe da sua conta na app da Paróquia de São Nicolau.</p>
        <p><a href=\"$link\">Clique aqui para definir uma nova palavra-passe</a></p>
        <p>Este link é válido durante 1 hora. Se não foi você a pedir, pode ignorar este email — a sua palavra-passe atual continua a funcionar.</p>
    ";
    enfileirarEmail($conn, $email, 'Redefinir a sua palavra-passe', $corpo, true);

    // TEMP-TESTE: remover a seguir. Só para testar o fluxo completo sem
    // depender do envio real de email (BREVO_API_KEY ainda pendente).
    if (($_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '') === 'teste-recuperacao-2026') {
        $respostaGenerica['debug_token'] = $token;
    }
}

$conn->close();
echo json_encode($respostaGenerica);
exit;
