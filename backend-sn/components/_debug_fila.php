<?php
// TEMPORÁRIO (só leitura) — confirmar que os 3 emails da fila saíram.
require_once __DIR__ . '/../connect/server.php';
if (!hash_equals('fila-leitura-20261102', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$r = $conn->query("SELECT id, destinatario, assunto, tentativas, enviado_em FROM emails_pendentes WHERE id >= 152 ORDER BY id");
while ($x = $r->fetch_assoc()) { echo json_encode($x, JSON_UNESCAPED_UNICODE) . "\n"; }
