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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents("php://input"), $data);
    $fotoId = $data['foto_id'];
    $fotoKey = $data['foto_key'];

    try {
        $s3->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $fotoKey,
        ]);
    
        $stmt = $conn->prepare("DELETE FROM fotos WHERE id = ?");
        $stmt->bind_param("i", $fotoId);
        $stmt->execute();
    
        echo json_encode(['message' => 'Foto deletada com sucesso']);
    } catch (S3Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
}

$conn->close();
?>
