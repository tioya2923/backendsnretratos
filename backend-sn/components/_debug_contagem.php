<?php
// TEMPORÁRIO (só leitura, só contagens, não devolve nenhum email) —
// autorizado pelo utilizador para planear o teste fechado da Google Play.
require_once __DIR__ . '/../connect/server.php';
if (!hash_equals('contagem-testadores-20261102', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$q = function ($sql) use ($conn) { return (int) $conn->query($sql)->fetch_row()[0]; };
echo json_encode([
    'total'            => $q("SELECT COUNT(*) FROM usuarios"),
    'aprovados'        => $q("SELECT COUNT(*) FROM usuarios WHERE status='aprovado'"),
    'aprovados_gmail'  => $q("SELECT COUNT(*) FROM usuarios WHERE status='aprovado' AND (email LIKE '%@gmail.com' OR email LIKE '%@googlemail.com')"),
    'aprovados_outros' => $q("SELECT COUNT(*) FROM usuarios WHERE status='aprovado' AND NOT (email LIKE '%@gmail.com' OR email LIKE '%@googlemail.com')"),
    'sem_email'        => $q("SELECT COUNT(*) FROM usuarios WHERE status='aprovado' AND (email IS NULL OR email='')"),
]);
