<?php
// TEMPORÁRIO — autorizado pelo utilizador (2026-11-02): repor as tentativas
// dos 3 emails da fila que falharam por bloqueio de IP no Brevo, para o
// cron os reenviar. Remove-se logo a seguir.
require_once __DIR__ . '/../connect/server.php';
if (!hash_equals('fila-reenvio-20261102', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$conn->query("UPDATE emails_pendentes SET tentativas = 0 WHERE id IN (155,156,157) AND enviado_em IS NULL");
echo "Repostos: " . $conn->affected_rows . "\n";
$r = $conn->query("SELECT id, destinatario, assunto, tentativas, enviado_em, created_at FROM emails_pendentes WHERE id >= 150 ORDER BY id");
while ($x = $r->fetch_assoc()) { echo json_encode($x, JSON_UNESCAPED_UNICODE) . "\n"; }
