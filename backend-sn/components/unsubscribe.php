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

// Captura email via JSON ou form-data
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

if (!$email) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['email'])) {
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    }
}

if (!$email) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email inválido.']);
    exit();
}

// Verifica se existe
$stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ?');
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Email não encontrado.']);
    exit();
}

$stmt->close();

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
