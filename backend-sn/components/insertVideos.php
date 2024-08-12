<?php


require '../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

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
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $descricao = mysqli_real_escape_string($conn, $_POST['descricao']);

    if (isset($_FILES['video']) && $_FILES['video']['error'] == 0) {
        $video = $_FILES['video'];
        $filename = mysqli_real_escape_string($conn, $video['name']);
        
        try {
            $key = "uploads/{$filename}";
            $result = $s3->putObject([
                'Bucket' => $bucketName,
                'Key'    => $key,
                'SourceFile' => $video['tmp_name'],
                'ACL'    => 'public-read',
                'ContentType' => 'video/mp4'
            ]);

            $stmt = $conn->prepare("INSERT INTO videos (nome, video, descricao) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $nome, $key, $descricao);

            if ($stmt->execute()) {
                echo "Vídeo enviado com sucesso!";
            } else {
                echo "Ocorreu um erro ao enviar o vídeo: " . $stmt->error;
            }
            $stmt->close();
        } catch (S3Exception $e) {
            echo "Houve um erro ao fazer upload no S3: " . $e->getMessage();
        }
    } else {
        echo "Erro no upload: " . $_FILES['video']['error'];
    }
} else {
    echo "Método de solicitação inválido.";
}

$conn->close();
?>
