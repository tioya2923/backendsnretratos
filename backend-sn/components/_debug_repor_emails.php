<?php
// TEMPORÁRIO — autorizado (repor emails presos para tentarem de novo,
// excluindo password-resets expirados e endereços de teste). Remove-se
// a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'repor-emails-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$stmt = $conn->prepare("
    UPDATE emails_pendentes
    SET tentativas = 0
    WHERE enviado_em IS NULL
      AND tentativas >= 5
      AND assunto <> 'Redefinir a sua palavra-passe'
      AND destinatario NOT LIKE '%@example.com'
      AND destinatario NOT LIKE '%@exemplo.com'
");
$stmt->execute();

echo json_encode(['status' => 'success', 'repostos' => $stmt->affected_rows]);
