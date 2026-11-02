<?php
// TEMPORÁRIO — autorizado pelo utilizador só para confirmar se o lembrete
// de inscrição de segunda-feira saiu. Remove-se logo a seguir.
$tokenEsperado = 'verificacao-inscricao-20261102';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
if (!file_exists($logfile)) { echo "log não existe\n"; exit; }
$cab = '';
$out = [];
foreach (file($logfile) as $l) {
    if (strpos($l, 'Script iniciado em') !== false) { $cab = trim($l); continue; }
    if (strpos($cab, '2026-11-02') !== false && stripos($l, 'inscri') !== false) {
        $out[] = $cab . "\n    " . rtrim($l);
    }
}
echo $out ? implode("\n", array_slice($out, -60)) : "sem linhas de inscrição hoje\n";
$linhas = file($logfile);
echo "\n--- últimas 3 linhas ---\n" . implode('', array_slice($linhas, -3));
