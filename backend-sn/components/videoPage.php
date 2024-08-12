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

// Verificar se o id foi fornecido
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No id provided']);
    exit;
}

$id = $_GET['id'];

// Preparar a consulta SQL
$stmt = $conn->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->bind_param("i", $id);

// Executar a consulta
$stmt->execute();

// Obter o resultado
$result = $stmt->get_result();

// Verificar se o vídeo existe
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Video not found']);
    exit;
}

$video = $result->fetch_assoc();

// Obter o URL do objeto S3
$video['video'] = $s3->getObjectUrl($bucketName, $video['video']);

// Converter para JSON
echo json_encode($video);

// Fechar a conexão
$stmt->close();
$conn->close();
?>
