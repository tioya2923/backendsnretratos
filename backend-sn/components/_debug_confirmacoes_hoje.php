<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para
// confirmar que as confirmações de presença de hoje ficaram gravadas.
// Remove-se logo a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'confirmacoes-hoje-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

$hoje = date('Y-m-d');

$stmt = $conn->prepare("
    SELECT c.refeicao_id, c.tipo, c.confirmado_em, r.nome_completo, r.data
    FROM confirmacoes_presenca c
    JOIN refeicoes r ON r.id = c.refeicao_id
    WHERE r.data = ? AND c.tipo LIKE 'almoco%'
    ORDER BY c.confirmado_em
");
$stmt->bind_param("s", $hoje);
$stmt->execute();
$rows = stmt_get_result($stmt);
$confirmacoes = [];
while ($row = $rows->fetch_assoc()) {
    $confirmacoes[] = $row;
}

echo json_encode(['data' => $hoje, 'total' => count($confirmacoes), 'confirmacoes' => $confirmacoes]);
