<?php
require '../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

if (isset($_FILES['file'])) {
    $pasta = isset($_POST['pasta']) ? mysqli_real_escape_string($conn, $_POST['pasta']) : 'pasta_padrao';
    $dataCriacao = isset($_POST['dataCriacao']) ? mysqli_real_escape_string($conn, $_POST['dataCriacao']) : date('Y-m-d H:i:s'); // Adicione este
    $nomeUsuario = isset($_POST['nomeUsuario']) ? mysqli_real_escape_string($conn, $_POST['nomeUsuario']) : 'Usuário desconhecido';
    $emailAdmin = 'retratospsn@gmail.com';

    $stmtPasta = $conn->prepare("INSERT INTO pastas (nome, data_criacao) VALUES (?, ?)"); // Modifique esta linha
    $stmtPasta->bind_param("ss", $pasta, $dataCriacao); // Modifique esta linha
    $stmtPasta->execute();
    $pasta_id = $conn->insert_id;
    $stmtPasta->close();

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'retratospsn@gmail.com';
        $mail->Password = 'thqyngnejodzttwl'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('retratospsn@gmail.com', 'Igreja de São Nicolau');
        $mail->addAddress($emailAdmin);

        $mail->isHTML(true);
        $mail->Subject = 'Nova pasta criada';
        $mail->Body    = "O usuário $nomeUsuario criou uma nova pasta chamada '$pasta' em " . date('Y-m-d H:i:s');

        $mail->send();
    } catch (Exception $e) {
        echo "Erro ao enviar o email: {$mail->ErrorInfo}";
    }

    $success = true;
    foreach ($_FILES['file']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['file']['error'][$key] === UPLOAD_ERR_OK) {
            $filename = mysqli_real_escape_string($conn, $_FILES['file']['name'][$key]);
            $nome = isset($_POST['nome']) ? mysqli_real_escape_string($conn, $_POST['nome']) : 'Nome padrão';
            
            try {
                $key = "uploads/{$pasta}/{$filename}";
                $filetype = mime_content_type($tmp_name);
                $tipo = strstr($filetype, 'video/') ? 'video' : 'foto'; // Determine o tipo com base no tipo MIME
                $result = $s3->putObject([
                    'Bucket' => $bucketName,
                    'Key'    => $key,
                    'SourceFile' => $tmp_name,
                    'ACL'    => 'public-read',
                    'ContentType' => $filetype
                ]);

                $stmt = $conn->prepare("INSERT INTO fotos (arquivo, tipo, pasta_id) VALUES (?, ?, ?)"); // Adicione 'tipo' à consulta SQL
                $stmt->bind_param("ssi", $key, $tipo, $pasta_id); // Vincule a variável $tipo ao parâmetro 'tipo'

                if (!$stmt->execute()) {
                    $success = false;
                    echo "Erro ao enviar a foto: " . $stmt->error;
                }
                $stmt->close();
            } catch (S3Exception $e) {
                $success = false;
                echo "Houve um erro ao fazer upload no S3: " . $e->getMessage();
            }
        }
    }

    if ($success) {
        echo "Pasta criada com sucesso.";
    }
} else {
    echo "Nenhum arquivo selecionado ou erro no arquivo.";
}

$conn->close();
?>
