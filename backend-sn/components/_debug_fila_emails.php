<?php
// TEMPORÁRIO — autorizado (mesma investigação: testar o envio de email
// agora que o BREVO_API_KEY e o IP estão configurados). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/email_utils.php';

$tokenEsperado = 'fila-emails-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$erro = null;
$ok = sendEmail('mwenhondumba@gmail.com', 'Teste Brevo (apagar)', '<p>Teste de envio direto via API da Brevo.</p>', true, $erro);
echo json_encode(['sucesso' => $ok, 'erro' => $erro]);
