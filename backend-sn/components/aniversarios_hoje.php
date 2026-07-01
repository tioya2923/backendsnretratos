<?php
/**
 * Devolve os utilizadores aprovados cujo aniversário (natalício e/ou
 * sacerdotal) cai hoje (compara apenas dia/mês, ignora o ano).
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

$hojeMesDia = date('m-d');

$stmt = $conn->prepare("
    SELECT id, name, data_aniversario, data_aniversario_sacerdotal
    FROM usuarios
    WHERE status = 'aprovado'
      AND (DATE_FORMAT(data_aniversario, '%m-%d') = ?
           OR DATE_FORMAT(data_aniversario_sacerdotal, '%m-%d') = ?)
");
$stmt->bind_param("ss", $hojeMesDia, $hojeMesDia);
$stmt->execute();
$result = $stmt->get_result();

$natalicio = [];
$sacerdotal = [];

while ($row = $result->fetch_assoc()) {
    if ($row['data_aniversario'] && date('m-d', strtotime($row['data_aniversario'])) === $hojeMesDia) {
        $natalicio[] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    if ($row['data_aniversario_sacerdotal'] && date('m-d', strtotime($row['data_aniversario_sacerdotal'])) === $hojeMesDia) {
        $sacerdotal[] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
}

$stmt->close();
$conn->close();

echo json_encode(['natalicio' => $natalicio, 'sacerdotal' => $sacerdotal]);
