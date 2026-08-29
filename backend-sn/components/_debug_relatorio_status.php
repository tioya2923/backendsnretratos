<?php
// TEMPORÁRIO — autorizado (verificar o estado do relatório quinzenal aos
// administradores). Só leitura. Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'relatorio-status-20260829';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

$log = $conn->query("SELECT id, enviado_em FROM relatorio_quinzenal_log ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$admins = $conn->query("SELECT name_admin, email_admin FROM admins")->fetch_all(MYSQLI_ASSOC);

// Emails do relatório já enfileirados/enviados (pelo assunto característico)
$emailsRelatorio = $conn->query("
    SELECT id, destinatario, assunto, tentativas, enviado_em, created_at
    FROM emails_pendentes
    WHERE assunto LIKE 'Relatório quinzenal%'
    ORDER BY id DESC
")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'agora' => date('Y-m-d H:i:s'),
    'log_relatorio_quinzenal' => $log,
    'admins' => $admins,
    'emails_relatorio_na_fila' => $emailsRelatorio,
], JSON_PRETTY_PRINT);
