<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para
// diagnosticar a falha do cron do cPanel. Remove-se logo a seguir.
$tokenEsperado = 'verificacao-cpanel-cron-20260827';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

$cronTestLog = __DIR__ . '/cron_test.log';
echo "=== cron_test.log ===\n";
if (file_exists($cronTestLog)) {
    echo file_get_contents($cronTestLog);
} else {
    echo "(não existe)\n";
}

echo "\n=== enviar_lembretes_cron.log (últimas 40 linhas) ===\n";
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
if (file_exists($logfile)) {
    $linhas = file($logfile);
    echo implode('', array_slice($linhas, -40));
} else {
    echo "(não existe)\n";
}

echo "\n=== PHP em uso neste pedido (via web) ===\n";
echo PHP_VERSION . "\n";
