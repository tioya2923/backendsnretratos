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



if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $image = $_FILES['image'];
    $filename = mysqli_real_escape_string($conn, $image['name']);
    $nome = isset($_POST['nome']) ? mysqli_real_escape_string($conn, $_POST['nome']) : 'Nome padrão';
    $descricao = isset($_POST['descricao']) ? mysqli_real_escape_string($conn, $_POST['descricao']) : 'Descrição padrão';
    
    try {
        $key = "uploads/{$filename}";
        $result = $s3->putObject([
            'Bucket' => $bucketName,
            'Key'    => $key,
            'SourceFile' => $image['tmp_name'],
            'ACL'    => 'public-read',
            'ContentType' => 'image/png'
        ]);

        $stmt = $conn->prepare("INSERT INTO fotos (nome, foto, descricao) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $key, $descricao);

        if ($stmt->execute()) {
            echo "Foto enviada com sucesso!";
        } else {
            echo "Erro ao enviar a foto: " . $stmt->error;
        }
        $stmt->close();
    } catch (S3Exception $e) {
        echo "Houve um erro ao fazer upload no S3: " . $e->getMessage();
    }
} else {
    echo "Nenhuma fotografia selecionada ou erro no arquivo.";
}

$conn->close();
?>
