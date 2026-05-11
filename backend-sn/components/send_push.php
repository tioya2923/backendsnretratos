<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar que é admin (token de administrador)
$headers = apache_request_headers();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token obrigatório']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM admins WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$title = $data['title'] ?? 'Paróquia de São Nicolau';
$body  = $data['body']  ?? '';
$url   = $data['url']   ?? '/';

if (empty($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensagem obrigatória']);
    exit;
}

sendPushNotification($conn, $title, $body, $url);

echo json_encode(['success' => true, 'message' => 'Notificações enviadas']);
