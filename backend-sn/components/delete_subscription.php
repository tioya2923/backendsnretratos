<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$userId = getAuthUserId($conn);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}
$user = ['id' => $userId];

$data = json_decode(file_get_contents('php://input'), true);
$endpoint = $data['endpoint'] ?? '';

if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode(['error' => 'Endpoint obrigatório']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
$stmt->bind_param("is", $user['id'], $endpoint);
$stmt->execute();

echo json_encode(['success' => true]);
