<?php
// TEMPORÁRIO — autorizado (enviar o relatório já confirmado aos
// administradores reais, sem tocar em relatorio_quinzenal_log — os
// próximos envios agendados continuam intactos). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/presenca_utils.php';
require_once __DIR__ . '/relatorio_utils.php';

$tokenEsperado = 'enviar-relatorio-admins-20260901';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

// Mesmo período do relatório já enviado (16 a 31/08/2026) — não mexe em
// relatorio_quinzenal_log, por isso o período de setembro (dias 1-3,
// '2026-09-A') continua por enviar, agendado normalmente.
$inicio = '2026-08-16';
$fim    = '2026-08-31';
$periodoFormatado = '16/08/2026 a 31/08/2026';

$relatorio = gerarCorpoRelatorioQuinzenal($conn, $inicio, $fim, $periodoFormatado);
$assunto = "Relatório quinzenal de inscrições ($periodoFormatado)";

$resAdmins = $conn->query("SELECT name_admin, email_admin FROM admins");
$resultados = [];
while ($admin = $resAdmins->fetch_assoc()) {
    if (empty($admin['email_admin'])) continue;
    $erro = null;
    $ok = sendEmail($admin['email_admin'], $assunto, $relatorio['body'], true, $erro);
    $resultados[] = ['admin' => $admin['name_admin'], 'email' => $admin['email_admin'], 'sucesso' => $ok, 'erro' => $erro];
}

echo json_encode(['resultados' => $resultados], JSON_PRETTY_PRINT);
