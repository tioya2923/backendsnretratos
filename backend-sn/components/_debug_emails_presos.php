<?php
// TEMPORÁRIO — autorizado (verificar e decidir sobre emails presos na
// fila). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'emails-presos-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$res = $conn->query("
    SELECT id, destinatario, assunto, tentativas, created_at
    FROM emails_pendentes
    WHERE enviado_em IS NULL AND tentativas >= 5
    ORDER BY id
");
$presos = $res->fetch_all(MYSQLI_ASSOC);

echo json_encode(['total_presos' => count($presos), 'emails' => $presos], JSON_PRETTY_PRINT);
