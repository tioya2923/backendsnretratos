<?php
// TEMPORÁRIO — autorizado (verificar marcações reais dos grupos). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'ver-marcacoes-20260831';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$grupos = $conn->query("SELECT id, nome_grupo FROM Grupos")->fetch_all(MYSQLI_ASSOC);
$marcacoes = $conn->query("SELECT * FROM refeicoes_grupos ORDER BY grupo_id")->fetch_all(MYSQLI_ASSOC);

echo json_encode(['grupos' => $grupos, 'marcacoes' => $marcacoes], JSON_PRETTY_PRINT);
