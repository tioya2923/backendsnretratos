<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';

header('Content-Type: application/json');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit();
}

// Captura email/password via JSON ou form-data
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? null;

if (!$email || $password === null) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$email && isset($input['email'])) {
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    }
    if ($password === null && isset($input['password'])) {
        $password = $input['password'];
    }
}

if (!$email || empty($password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email e password são obrigatórios.']);
    exit();
}

// Confirmar a password — sem isto, bastava saber o email de outra
// pessoa (por exemplo, visto num grupo do WhatsApp) para lhe apagar a
// conta, sem confirmação nenhuma.
$stmt = $conn->prepare('SELECT id, password FROM usuarios WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$row = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($password, $row['password'])) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Email ou palavra-passe incorretos.']);
    exit();
}

// Remove
$stmt = $conn->prepare('DELETE FROM usuarios WHERE email = ?');
$stmt->bind_param('s', $email);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Usuário removido com sucesso.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao processar remoção.']);
}

$stmt->close();
$conn->close();
