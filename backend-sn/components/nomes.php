<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Definir o fuso horário para Portugal Continental
date_default_timezone_set('Europe/Lisbon');

// Buscar Nomes e Datas de Aniversário
$sql = "SELECT nome_completo, data_aniversario, data_aniversario_sacerdotal FROM nomes";
$result = $conn->query($sql);
$nomes = [];
while ($row = $result->fetch_assoc()) {
    $dataAniversario = $row['data_aniversario'] ? new DateTime($row['data_aniversario']) : null;
    $dataAniversarioSacerdotal = $row['data_aniversario_sacerdotal'] ? new DateTime($row['data_aniversario_sacerdotal']) : null;
    
    $nomes[] = [
        'nome_completo' => $row['nome_completo'],
        'data_aniversario' => $dataAniversario ? $dataAniversario->format('Y-m-d H:i:s') : null,
        'data_aniversario_sacerdotal' => $dataAniversarioSacerdotal ? $dataAniversarioSacerdotal->format('Y-m-d H:i:s') : null
    ];
}
$conn->close();

// Retornar os nomes e datas de aniversário como JSON
header('Content-Type: application/json');
echo json_encode($nomes);
?>
