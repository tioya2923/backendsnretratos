<?php
// TEMPORÁRIO — autorizado (mesma investigação: testar o envio de email
// agora que o BREVO_API_KEY está configurado). Remove-se a seguir.
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

$acao = $_GET['acao'] ?? 'listar';

if ($acao === 'listar') {
    $res = $conn->query("SELECT id, destinatario, assunto, tentativas, enviado_em, created_at FROM emails_pendentes ORDER BY id DESC LIMIT 20");
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
    exit;
}

if ($acao === 'teste_direto') {
    // Testa sendEmail() diretamente (API da Brevo), sem passar pela fila.
    $erro = null;
    $ok = sendEmail('mwenhondumba@gmail.com', 'Teste Brevo (apagar)', '<p>Teste de envio direto via API da Brevo.</p>', true, $erro);
    echo json_encode(['sucesso' => $ok, 'erro' => $erro]);
    exit;
}

echo json_encode(['error' => 'ação desconhecida']);
