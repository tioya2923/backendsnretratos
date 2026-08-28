<?php
// TEMPORÁRIO — autorizado (mesma investigação). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'tipos-json-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

// Replica exatamente o que refeicoes.php faz para o campo id (sem cast)
$res = $conn->query("SELECT id FROM refeicoes WHERE id = 17 LIMIT 1");
$row = $res->fetch_assoc();
echo "refeicoes.php (id sem cast): " . json_encode($row) . "\n";
echo "gettype(\$row['id']): " . gettype($row['id']) . "\n\n";

// Replica exatamente o que confirmar_presenca.php faz (com cast para int)
$res2 = $conn->query("SELECT refeicao_id FROM confirmacoes_presenca WHERE refeicao_id = 17 LIMIT 1");
$row2 = $res2->fetch_assoc();
$comCast = ['refeicao_id' => (int) $row2['refeicao_id']];
echo "confirmar_presenca.php (refeicao_id com cast int): " . json_encode($comCast) . "\n";
