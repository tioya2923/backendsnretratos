<?php
require '../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

$bucketName = 'retratos-paroquia-sao-nicolau';
$IAM_KEY = getenv('AWS_ACCESS_KEY_ID');
$IAM_SECRET = getenv('AWS_SECRET_ACCESS_KEY');

$s3 = new S3Client([
    'version' => 'latest',
    'region'  => 'us-east-1',
    'credentials' => [
        'key'    => $IAM_KEY,
        'secret' => $IAM_SECRET,
    ],
]);

// Verifica se o ID da foto foi fornecido
if (isset($_GET['id'])) {
  // Sanitiza o ID da foto
  $id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);

  // Consulta para eliminar a foto
  $sql = "DELETE FROM fotos WHERE id = ?";

  // Prepara a declaração
  $stmt = $conn->prepare($sql);

  // Liga o parâmetro
  $stmt->bind_param("i", $id);

  // Executa a declaração
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Foto eliminada com sucesso.']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao eliminar a foto.']);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'ID da foto não fornecido.']);
}

$conn->close();
?>
