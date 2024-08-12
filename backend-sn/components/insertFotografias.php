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

if (isset($_FILES['image'])) {
    foreach ($_FILES['image']['error'] as $key => $error) {
        if ($error === UPLOAD_ERR_OK) {
            $image = $_FILES['image'];
            $filename = mysqli_real_escape_string($conn, $image['name'][$key]);
            $nome = isset($_POST['nome']) ? mysqli_real_escape_string($conn, $_POST['nome']) : 'Nome padrão';
            $pasta = isset($_POST['pasta']) ? mysqli_real_escape_string($conn, $_POST['pasta']) : 'pasta_padrao';
            
            try {
                $key = "uploads/{$pasta}/{$filename}";
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $key,
                    'SourceFile' => $image['tmp_name'][$key],
                    'ACL'    => 'public-read',
                    'ContentType' => 'image/png'
                ]);

                $stmt = $conn->prepare("INSERT INTO fotos (nome, foto) VALUES (?, ?)");
                $stmt->bind_param("ss", $nome, $key);

                if ($stmt->execute()) {
                    echo "Foto enviada com sucesso!";
                } else {
                    echo "Erro ao enviar a foto: " . $stmt->error;
                }
                $stmt->close();
            } catch (S3Exception $e) {
                echo "Houve um erro ao fazer upload no S3: " . $e->getMessage();
            }
        }
    }
} else {
    echo "Nenhuma fotografia selecionada ou erro no arquivo.";
}

$conn->close();
?>
