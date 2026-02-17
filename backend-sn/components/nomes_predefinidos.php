<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Adicionar Nome
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    error_log('Dados recebidos: ' . print_r($data, true)); // Log dos dados recebidos

    if (isset($data['nome'])) {
        $nome = $data['nome'];
        $email = $data['email'] ?? null;

        error_log("Dados processados: nome=$nome, email=$email");

        // Inserir nome na tabela nomes_predefinidos
        $sql = "INSERT INTO nomes_predefinidos (nome, email) VALUES ('$nome', '$email')";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(["message" => "Nome adicionado com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao adicionar nome: " . $conn->error]);
        }
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

// Obter Nomes Predefinidos
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $sql = "SELECT nome, email FROM nomes_predefinidos";
    $result = $conn->query($sql);
    $nomes = [];
    while ($row = $result->fetch_assoc()) {
        $nomes[] = $row;
    }
    echo json_encode($nomes);
}

$conn->close();
?>
