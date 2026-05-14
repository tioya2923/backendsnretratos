<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar que é admin via chave secreta de ambiente (PUSH_ADMIN_SECRET)
$adminSecret = getenv('PUSH_ADMIN_SECRET');
$token = getBearerToken();

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token obrigatório']);
    exit;
}

if (empty($adminSecret) || !hash_equals($adminSecret, $token)) {
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
