<?php
// TEMPORÁRIO — autorizado (investigar um 502 pontual reportado pelo
// cron-job.org). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'check-health-20260904';
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
echo implode('', array_slice($linhas, -25));
