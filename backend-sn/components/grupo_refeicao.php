<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';
header('Content-Type: application/json');

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Adicionar Refeição
$data = json_decode(file_get_contents("php://input"), true);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($data['grupo_id']) && isset($data['tipo_refeicao']) && isset($data['data_refeicao']) && isset($data['hora_refeicao']) && isset($data['local_refeicao'])) {
    $grupo_id = $data['grupo_id'];
    $tipo_refeicao = $data['tipo_refeicao'];
    $data_refeicao = $data['data_refeicao'];
    $hora_refeicao = $data['hora_refeicao'];
    $local_refeicao = $data['local_refeicao'];

    $sql = "INSERT INTO refeicoes_grupos (grupo_id, tipo_refeicao, data_refeicao, hora_refeicao, local_refeicao) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issss", $grupo_id, $tipo_refeicao, $data_refeicao, $hora_refeicao, $local_refeicao);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Refeição adicionada com sucesso"]);
    } else {
        echo json_encode(["message" => "Erro ao adicionar refeição: " . $stmt->error]);
    }
    $stmt->close();
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo json_encode(["message" => "Dados incompletos"]);
    exit();
}

// Exibir Refeições de um Grupo Específico
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['grupo_id'])) {
    $grupo_id = $_GET['grupo_id'];
    $sql = "SELECT * FROM refeicoes_grupos WHERE grupo_id = ? ORDER BY data_refeicao, hora_refeicao";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $grupo_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $refeicoes = [];
    while ($row = $result->fetch_assoc()) {
        $refeicoes[] = $row;
    }
    echo json_encode($refeicoes);
    $stmt->close();
    exit();
}

// Buscar todos os grupos com suas refeições e total de membros
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !isset($_GET['grupo_id'])) {
    $sql = "SELECT g.id as grupo_id, g.nome_grupo, COUNT(m.id) as total_membros, r.id as refeicao_id, r.tipo_refeicao, r.data_refeicao, r.hora_refeicao, r.local_refeicao
            FROM Grupos g
            LEFT JOIN Membros m ON g.id = m.grupo_id
            LEFT JOIN refeicoes_grupos r ON g.id = r.grupo_id
            GROUP BY g.id, r.id
            ORDER BY g.id, r.data_refeicao, r.hora_refeicao";
    $result = $conn->query($sql);

    $grupos = [];
    while ($row = $result->fetch_assoc()) {
        $grupo_id = $row['grupo_id'];
        if (!isset($grupos[$grupo_id])) {
            $grupos[$grupo_id] = [
                'id' => $grupo_id,
                'nome_grupo' => $row['nome_grupo'],
                'total_membros' => $row['total_membros'],
                'refeicoes' => []
            ];
        }
        if ($row['refeicao_id']) {
            $grupos[$grupo_id]['refeicoes'][] = [
                'id' => $row['refeicao_id'],
                'tipo_refeicao' => $row['tipo_refeicao'],
                'data_refeicao' => $row['data_refeicao'],
                'hora_refeicao' => $row['hora_refeicao'],
                'local_refeicao' => $row['local_refeicao']
            ];
        }
    }
    echo json_encode(array_values($grupos));
    $conn->close();
    exit();
}

$conn->close();
?>
