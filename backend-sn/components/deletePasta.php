<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

$bucketName = 'retratos-paroquia-sao-nicolau';
$IAM_KEY = getenv('AWS_ACCESS_KEY_ID');
$IAM_SECRET = getenv('AWS_SECRET_ACCESS_KEY');

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $data);
    $pastaId = isset($data['pasta_id']) ? intval($data['pasta_id']) : 0;

    // Verifique se a pasta ainda contém arquivos
    $stmt = $conn->prepare("SELECT * FROM fotos WHERE pasta_id = ?");
    $stmt->bind_param("i", $pastaId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Antes de eliminar a pasta elimine os ficheiros da mesma"]);
        exit;
    }

    if ($pastaId > 0) {
        $stmt = $conn->prepare("DELETE FROM pastas WHERE id = ?");
        $stmt->bind_param("i", $pastaId);
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "Pasta apagada com sucesso"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Erro ao deletar a pasta"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "ID da pasta inválido"]);
    }
}

$conn->close();
?>

