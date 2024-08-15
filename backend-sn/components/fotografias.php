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

$stmt = $conn->prepare("SELECT * FROM pastas ORDER BY data_criacao DESC");
$stmt->execute();
$result = $stmt->get_result();
$pastas = array();

while ($row = $result->fetch_assoc()) {
    $stmtFotos = $conn->prepare("SELECT * FROM fotos WHERE pasta_id = ? ORDER BY data_hora DESC");
    $stmtFotos->bind_param("i", $row['id']);
    $stmtFotos->execute();
    $resultFotos = $stmtFotos->get_result();
    $fotos = array();

    while ($rowFoto = $resultFotos->fetch_assoc()) {
        $rowFoto['arquivo'] = $s3->getObjectUrl($bucketName, $rowFoto['arquivo']); // Altere 'foto' para 'arquivo'
        $fotos[] = $rowFoto;
    }

    $row['fotos'] = $fotos;
    $pastas[] = $row;
}

echo json_encode($pastas);

$conn->close();
?>
