<?php
// TEMPORÁRIO — autorizado (confirmar migração da tabela do relatório).
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'check-schema-20260829';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$colunas = $conn->query("SHOW COLUMNS FROM relatorio_quinzenal_log")->fetch_all(MYSQLI_ASSOC);
$linhas = $conn->query("SELECT * FROM relatorio_quinzenal_log")->fetch_all(MYSQLI_ASSOC);

echo json_encode(['colunas' => $colunas, 'linhas' => $linhas], JSON_PRETTY_PRINT);
