<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para
// confirmar que o cron-job.org está a disparar. Remove-se logo a seguir.
$tokenEsperado = 'verificacao-cronjob-org-20260827';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
if (!file_exists($logfile)) { echo "log não existe\n"; exit; }
$linhas = file($logfile);
echo implode('', array_slice($linhas, -20));
