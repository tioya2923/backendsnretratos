<?php
date_default_timezone_set('Europe/Lisbon');
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';

$conn->set_charset("utf8mb4");

$userId = getAuthUserId($conn);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, email, whatsapp, status FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

// GET — dados do perfil
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($user);
    exit;
}

// PUT — atualizar dados editáveis (whatsapp)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);

    $fields = [];
    $types  = '';
    $values = [];

    if (isset($data['whatsapp'])) {
        $whatsapp = preg_replace('/\D/', '', trim($data['whatsapp']));
        $fields[] = 'whatsapp = ?';
        $types   .= 's';
        $values[] = $whatsapp;
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum campo para atualizar']);
        exit;
    }

    $types   .= 'i';
    $values[] = $user['id'];
    $sql = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não suportado']);
