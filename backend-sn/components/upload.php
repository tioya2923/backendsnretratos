<?php

require_once __DIR__ . '/../../vendor/autoload.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pastaId = $_POST['pasta_id'];
    $files = $_FILES['file'];

    foreach ($files['name'] as $index => $name) {
        $key = basename($name);
        $tmpFilePath = $files['tmp_name'][$index];

        $filetype = mime_content_type($tmpFilePath);
        $validFileTypes = ['image/gif', 'image/jpeg', 'image/png', 'image/jpg', 'video/mp4', 'video/mpeg', 'video/ogg', 'video/webm'];
        if (!in_array($filetype, $validFileTypes)) {
            echo json_encode(['error' => 'Formato de arquivo inválido. Por favor, faça upload de uma imagem ou vídeo.']);
            exit;
        }
        $tipo = strstr($filetype, 'image/') ? 'foto' : (strstr($filetype, 'video/') ? 'video' : 'desconhecido');

        try {
            $s3->putObject([
                'Bucket' => $bucketName,
                'Key'    => $key,
                'SourceFile' => $tmpFilePath,
                'ACL'    => 'public-read',
            ]);

            $stmt = $conn->prepare("INSERT INTO fotos (arquivo, tipo, pasta_id) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $key, $tipo, $pastaId);
            $stmt->execute();
        
            echo json_encode(['message' => 'Arquivos carregados com sucesso']);
        } catch (S3Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

$conn->close();
?>
