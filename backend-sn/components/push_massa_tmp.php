<?php
/**
 * Endpoint temporário: envia push em massa com chave secreta de uso único.
 * Remove este ficheiro após usar.
 *
 * Chamada: POST /components/push_massa_tmp.php
 * Headers: X-Secret: psn-push-2026-0514
 * Body JSON: { "texto": "..." }   (opcional — usa default se omitido)
 */
date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';

header('Content-Type: application/json');

$secret = 'psn-push-2026-0514';
$recebido = $_SERVER['HTTP_X_SECRET'] ?? '';

if (!hash_equals($secret, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$conn->set_charset("utf8mb4");

$data  = json_decode(file_get_contents('php://input'), true);
$texto = trim($data['texto'] ?? 'Feliz Quinta-Feira, dia de S. Matias, Apóstolo!');

sendPushNotification(
    $conn,
    'Paróquia de São Nicolau',
    $texto,
    '/',
    [],
    'psn-mensagem',
    86400,
    'high'
);

$conn->close();
echo json_encode(['success' => true, 'texto' => $texto, 'ts' => date('Y-m-d H:i:s')]);
