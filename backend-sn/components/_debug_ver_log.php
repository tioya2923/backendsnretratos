<?php
// TEMPORÁRIO (só leitura) — ver a que horas o cron correu hoje.
if (!hash_equals('log-disparos-20261102', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$f = __DIR__ . '/enviar_lembretes_cron.log';
foreach (file($f) as $l) {
    if (strpos($l, 'Script iniciado em 2026-11-02') !== false) echo trim($l), "\n";
}
echo "agora: ", date('Y-m-d H:i:s'), "\n";
