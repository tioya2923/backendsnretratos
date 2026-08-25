<?php
/**
 * Passo 2 da recuperação de palavra-passe: recebe o código do link do
 * email e a nova palavra-passe. Valida que o código existe, não expirou
 * (1h) e ainda não foi usado, antes de gravar a nova password.
 */

ini_set('display_errors', 0);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$token    = trim($data['token'] ?? '');
$password = $data['password'] ?? '';

if (!$token || !ctype_xdigit($token) || strlen($token) !== 64) {
    echo json_encode(['status' => 'error', 'message' => 'Link inválido.']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'A palavra-passe deve ter pelo menos 8 caracteres.']);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, user_id, expira_em, usado FROM password_resets WHERE token = ?
");
$stmt->bind_param("s", $token);
$stmt->execute();
$reset = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if (!$reset) {
    echo json_encode(['status' => 'error', 'message' => 'Link inválido ou já usado.']);
    exit;
}

if ((int) $reset['usado'] === 1) {
    echo json_encode(['status' => 'error', 'message' => 'Este link já foi usado. Peça um novo, se ainda precisar de redefinir a palavra-passe.']);
    exit;
}

if (strtotime($reset['expira_em']) < time()) {
    echo json_encode(['status' => 'error', 'message' => 'Este link expirou. Peça um novo.']);
    exit;
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$userId       = (int) $reset['user_id'];

$upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
$upd->bind_param("si", $passwordHash, $userId);
$upd->execute();
$upd->close();

$marcar = $conn->prepare("UPDATE password_resets SET usado = 1 WHERE id = ?");
$marcar->bind_param("i", $reset['id']);
$marcar->execute();
$marcar->close();

// Termina sessões já abertas — quem tinha acesso com a password antiga
// (a própria pessoa, ou alguém que a tenha obtido) fica com login pedido
// de novo em qualquer dispositivo.
$delSessoes = $conn->prepare("DELETE FROM sessoes WHERE user_id = ?");
$delSessoes->bind_param("i", $userId);
$delSessoes->execute();
$delSessoes->close();

$conn->close();
echo json_encode(['status' => 'success', 'message' => 'Palavra-passe redefinida com sucesso. Já pode iniciar sessão.']);
exit;
