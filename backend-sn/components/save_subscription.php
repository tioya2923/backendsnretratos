<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
require_once __DIR__ . '/push_utils.php';

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
$p256dh   = $data['keys']['p256dh'] ?? '';
$auth     = $data['keys']['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados de subscrição inválidos']);
    exit;
}

criarTabelaSubscricoes($conn);

$stmt = $conn->prepare(
    "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)"
);
$stmt->bind_param("isss", $user['id'], $endpoint, $p256dh, $auth);
$stmt->execute();

echo json_encode(['success' => true]);
