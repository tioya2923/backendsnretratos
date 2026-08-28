<?php
// TEMPORÁRIO — autorizado (mesma investigação já em curso). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'verificar-duplicado-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

$hoje = date('Y-m-d');
$nome = 'Pe. João Dumba';

$stmt = $conn->prepare("SELECT id, nome_completo, data, almoco, almoco_mais_cedo, almoco_mais_tarde FROM refeicoes WHERE data = ? AND nome_completo = ?");
$stmt->bind_param("ss", $hoje, $nome);
$stmt->execute();
$refeicoes = stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

$stmt2 = $conn->prepare("
    SELECT c.refeicao_id, c.tipo, c.confirmado_em
    FROM confirmacoes_presenca c
    JOIN refeicoes r ON r.id = c.refeicao_id
    WHERE r.data = ? AND r.nome_completo = ?
");
$stmt2->bind_param("ss", $hoje, $nome);
$stmt2->execute();
$confirmacoes = stmt_get_result($stmt2)->fetch_all(MYSQLI_ASSOC);

echo json_encode(['refeicoes' => $refeicoes, 'confirmacoes' => $confirmacoes], JSON_PRETTY_PRINT);
