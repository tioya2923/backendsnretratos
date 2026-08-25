<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para verificar
// ao vivo se o lembrete de hoje foi entregue. Remove-se logo a seguir,
// no próximo commit.
$tokenEsperado = 'verificacao-push-20260825';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
if (!file_exists($logfile)) {
    echo "log não existe\n";
    exit;
}
$linhas = file($logfile);
$tail = array_slice($linhas, -150);
echo implode('', $tail);
