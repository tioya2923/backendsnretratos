<?php
require '../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$bucketName = 'retratos-paroquia-sao-nicolau';
$IAM_KEY = getenv('AWS_ACCESS_KEY_ID');
$IAM_SECRET = getenv('AWS_SECRET_ACCESS_KEY');

// Configurar cliente S3
$s3 = S3Client::factory([
    'credentials' => [
        'key' => $IAM_KEY,
        'secret' => $IAM_SECRET,
    ],
    'version' => 'latest',
    'region'  => 'us-east-1'
]);

// Verifica se o ID do vídeo foi fornecido
if (isset($_GET['id'])) {
  // Validação de entrada
  $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
  if ($id === false) {
    echo json_encode(['status' => 'error', 'message' => 'ID do vídeo inválido.']);
    exit;
  }

  // Consulta para eliminar o vídeo
  $sql = "DELETE FROM videos WHERE id = ?";

  // Prepara a declaração
  $stmt = $conn->prepare($sql);
  if ($stmt === false) {
    // Erro na preparação da declaração
    error_log('Erro na preparação da declaração: ' . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao eliminar o vídeo.']);
    exit;
  }

  // Liga o parâmetro
  $stmt->bind_param("i", $id);

  // Executa a declaração
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Vídeo eliminado com sucesso.']);
  } else {
    // Erro na execução da declaração
    error_log('Erro na execução da declaração: ' . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao eliminar o vídeo.']);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'ID do vídeo não fornecido.']);
}

$conn->close();
?>
