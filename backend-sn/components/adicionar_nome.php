<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Adicionar Nome
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['nome_completo'])) {
        $nome_completo = $data['nome_completo'];
        $data_aniversario = !empty($data['data_aniversario']) ? $data['data_aniversario'] : NULL;
        $data_aniversario_sacerdotal = !empty($data['data_aniversario_sacerdotal']) ? $data['data_aniversario_sacerdotal'] : NULL;

        // Verificar se o nome já existe
        $check_sql = "SELECT COUNT(*) FROM nomes WHERE nome_completo = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $nome_completo);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            echo json_encode(["message" => "Nome já existe"]);
        } else {
            $sql = "INSERT INTO nomes (nome_completo, data_aniversario, data_aniversario_sacerdotal) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $nome_completo, $data_aniversario, $data_aniversario_sacerdotal);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Nome adicionado com sucesso"]);
            } else {
                echo json_encode(["message" => "Erro ao adicionar nome: " . $stmt->error]);
            }
            $stmt->close();
        }
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

$conn->close();


?>
