<?php

ini_set('display_errors', 1); // Ativa a exibição de erros para depuração

function handleUncaughtException($e) {
    error_log($e->getMessage()); // Loga o erro
    exit('Olá! Estaremos juntos brevemente!'); // Mensagem amigável para o usuário
}

set_exception_handler('handleUncaughtException'); // Define o manipulador de exceções

require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Adicionar Refeição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    error_log("Dados recebidos: " . print_r($data, true)); // Logar os dados recebidos para depuração

    if (isset($data['data'], $data['nome'], $data['levar_refeicao'], $data['almoco'], $data['almoco_mais_cedo'], $data['almoco_mais_tarde'], $data['jantar'], $data['jantar_mais_cedo'], $data['jantar_mais_tarde'])) {
        $data_refeicao = $data['data'];
        $nome_completo = $data['nome'];
        $levar_refeicao = filter_var($data['levar_refeicao'], FILTER_VALIDATE_BOOLEAN);
        $almoco = filter_var($data['almoco'], FILTER_VALIDATE_BOOLEAN);
        $almoco_mais_cedo = filter_var($data['almoco_mais_cedo'], FILTER_VALIDATE_BOOLEAN);
        $almoco_mais_tarde = filter_var($data['almoco_mais_tarde'], FILTER_VALIDATE_BOOLEAN);
        $jantar = filter_var($data['jantar'], FILTER_VALIDATE_BOOLEAN);
        $jantar_mais_cedo = filter_var($data['jantar_mais_cedo'], FILTER_VALIDATE_BOOLEAN);
        $jantar_mais_tarde = filter_var($data['jantar_mais_tarde'], FILTER_VALIDATE_BOOLEAN);

        // Verificar se o nome já está inscrito para a mesma refeição no mesmo dia
        $check_sql = "SELECT * FROM refeicoes WHERE nome_completo = ? AND data = ? AND (
            (levar_refeicao = ? AND levar_refeicao = 1) OR
            (almoco = ? AND almoco = 1) OR
            (almoco_mais_cedo = ? AND almoco_mais_cedo = 1) OR
            (almoco_mais_tarde = ? AND almoco_mais_tarde = 1) OR
            (jantar = ? AND jantar = 1) OR
            (jantar_mais_cedo = ? AND jantar_mais_cedo = 1) OR
            (jantar_mais_tarde = ? AND jantar_mais_tarde = 1)
        )";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ssiiiiiii", $nome_completo, $data_refeicao, $levar_refeicao, $almoco, $almoco_mais_cedo, $almoco_mais_tarde, $jantar, $jantar_mais_cedo, $jantar_mais_tarde);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows > 0) {
            echo json_encode(["message" => "Já inscrito para esta refeição", "nome" => $nome_completo]);
            exit();
        }

        $sql = "INSERT INTO refeicoes (nome_completo, data, levar_refeicao, almoco, almoco_mais_cedo, almoco_mais_tarde, jantar, jantar_mais_cedo, jantar_mais_tarde) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiiiiiii", $nome_completo, $data_refeicao, $levar_refeicao, $almoco, $almoco_mais_cedo, $almoco_mais_tarde, $jantar, $jantar_mais_cedo, $jantar_mais_tarde);
        if ($stmt->execute() !== TRUE) {
            echo json_encode(["message" => "Erro ao adicionar refeição: " . $conn->error]);
            exit();
        }

        echo json_encode(["message" => "Refeição adicionada com sucesso"]);
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

// Obter Refeições
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['nomes'])) {
    $sql = "SELECT id, nome_completo, data, levar_refeicao, almoco, almoco_mais_cedo, almoco_mais_tarde, jantar, jantar_mais_cedo, jantar_mais_tarde FROM refeicoes";
    $result = $conn->query($sql);
    $refeicoes = [];
    while ($row = $result->fetch_assoc()) {
        // Garantir que os valores booleanos sejam corretamente interpretados
        $row['levar_refeicao'] = (bool)$row['levar_refeicao'];
        $row['almoco'] = (bool)$row['almoco'];
        $row['almoco_mais_cedo'] = (bool)$row['almoco_mais_cedo'];
        $row['almoco_mais_tarde'] = (bool)$row['almoco_mais_tarde'];
        $row['jantar'] = (bool)$row['jantar'];
        $row['jantar_mais_cedo'] = (bool)$row['jantar_mais_cedo'];
        $row['jantar_mais_tarde'] = (bool)$row['jantar_mais_tarde'];
        $refeicoes[] = $row;
    }
    echo json_encode($refeicoes);
}

// Obter Totais de Refeições
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['totais'])) {
    $sql = "SELECT 
                SUM(CASE WHEN almoco = 1 THEN 1 ELSE 0 END) AS almoco,
                SUM(CASE WHEN almoco_mais_cedo = 1 THEN 1 ELSE 0 END) AS almoco_mais_cedo,
                SUM(CASE WHEN almoco_mais_tarde = 1 THEN 1 ELSE 0 END) AS almoco_mais_tarde,
                SUM(CASE WHEN jantar = 1 THEN 1 ELSE 0 END) AS jantar,
                SUM(CASE WHEN jantar_mais_cedo = 1 THEN 1 ELSE 0 END) AS jantar_mais_cedo,
                SUM(CASE WHEN jantar_mais_tarde = 1 THEN 1 ELSE 0 END) AS jantar_mais_tarde,
                SUM(CASE WHEN levar_refeicao = 1 THEN 1 ELSE 0 END) AS levar_refeicao
            FROM refeicoes
            WHERE data = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    $result = $conn->query($sql);
    $totais = $result->fetch_assoc();
    echo json_encode($totais);
}

// Excluir Refeição
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['id'])) {
        $id = $data['id'];
        $sql = "DELETE FROM refeicoes WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute() === TRUE) {
            echo json_encode(["message" => "Refeição excluída com sucesso"]);
        } else {
            echo json_encode(["message" => "Erro ao excluir refeição: " . $conn->error]);
        }
    } else {
        echo json_encode(["message" => "ID não fornecido"]);
    }
}

$conn->close();
?>
