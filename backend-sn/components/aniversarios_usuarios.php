<?php
/**
 * Lista de aniversários (natalício e sacerdotal) de todos os utilizadores
 * aprovados, para o mapa semanal de refeições calcular quem faz anos em
 * cada um dos próximos dias. Substitui a antiga tabela `nomes` — os dados
 * vêm agora do próprio registo de cada utilizador.
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$sql = "SELECT name AS nome_completo, data_aniversario, data_aniversario_sacerdotal
        FROM usuarios
        WHERE status = 'aprovado'";
$result = $conn->query($sql);
$rows = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode($rows);
