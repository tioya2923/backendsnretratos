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

$stmt = $conn->prepare("SELECT * FROM fotos ORDER BY data_hora DESC");
$stmt->execute();
$result = $stmt->get_result();
$fotos = array();

while ($row = $result->fetch_assoc()) {
    $row['foto'] = $s3->getObjectUrl($bucketName, $row['foto']);
    $fotos[] = $row;
}


echo json_encode($fotos);

$conn->close();
?>