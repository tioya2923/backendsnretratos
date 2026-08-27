<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para confirmar
// que o cron do cPanel está a chamar o script. Remove-se logo a seguir,
// no próximo commit.
$tokenEsperado = 'verificacao-cpanel-cron-20260827';
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
$tail = array_slice($linhas, -60);
echo implode('', $tail);
