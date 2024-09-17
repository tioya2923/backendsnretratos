<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Função para limpar registros antigos
function limparRegistrosAntigos($conn) {
    // Calcular a data limite para exclusão (30 dias atrás)
    $data_limite = date('Y-m-d', strtotime('-30 days'));

    // Excluir registros antigos
    $sql = "DELETE FROM refeicoes WHERE data < '$data_limite'";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["message" => "Registros antigos excluídos com sucesso"]);
    } else {
        echo json_encode(["message" => "Erro ao excluir registros antigos: " . $conn->error]);
    }

    // Atualizar a data da última limpeza
    $hoje = date('Y-m-d');
    $sql = "UPDATE limpeza SET ultima_limpeza = '$hoje' WHERE id = 1";
    $conn->query($sql);
}

// Verificar se é necessário realizar a limpeza
$sql = "SELECT ultima_limpeza FROM limpeza WHERE id = 1";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $ultima_limpeza = $row['ultima_limpeza'];
    $data_limite = date('Y-m-d', strtotime('-30 days'));

    if ($ultima_limpeza < $data_limite) {
        limparRegistrosAntigos($conn);
    }
} else {
    // Se não houver registro de limpeza, criar um e realizar a limpeza
    $sql = "INSERT INTO limpeza (ultima_limpeza) VALUES (CURDATE())";
    $conn->query($sql);
    limparRegistrosAntigos($conn);
}

// Adicionar Refeição
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    error_log("Dados recebidos: " . print_r($data, true));
    if (isset($data['data'], $data['tipo_refeicao'], $data['nomes_completos']) && is_array($data['nomes_completos'])) {
        $data_refeicao = $data['data'];
        $tipo_refeicao = $data['tipo_refeicao'];
        $nomes_completos = $data['nomes_completos'];

        foreach ($nomes_completos as $nome_completo) {
            // Verificar se o nome já está inscrito para o mesmo dia e tipo de refeição
            $check_sql = "SELECT * FROM refeicoes WHERE nome_completo = '$nome_completo' AND data = '$data_refeicao' AND tipo_refeicao = '$tipo_refeicao'";
            $check_result = $conn->query($check_sql);
            if ($check_result->num_rows > 0) {
                echo json_encode(["message" => "Já inscrito para esta refeição", "nome" => $nome_completo]);
                exit();
            }

            $sql = "INSERT INTO refeicoes (nome_completo, data, tipo_refeicao) VALUES ('$nome_completo', '$data_refeicao', '$tipo_refeicao')";
            if ($conn->query($sql) !== TRUE) {
                echo json_encode(["message" => "Erro ao adicionar refeição: " . $conn->error]);
                exit();
            }
        }
        echo json_encode(["message" => "Refeições adicionadas com sucesso"]);
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

// Obter Refeições
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $sql = "SELECT * FROM refeicoes";
    $result = $conn->query($sql);
    $refeicoes = [];
    while ($row = $result->fetch_assoc()) {
        $refeicoes[] = $row;
    }
    echo json_encode($refeicoes);
}

// Excluir Refeição
if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['id'])) {
        $id = $data['id'];
        $sql = "DELETE FROM refeicoes WHERE id = '$id'";
        if ($conn->query($sql) === TRUE) {
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
