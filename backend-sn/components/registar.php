<?php

ini_set('display_errors', 0); // Desativa a exibição de erros para o usuário

function handleUncaughtException($e)
{
    error_log($e->getMessage()); // Loga o erro
    exit('Olá! Estaremos juntos brevemente!'); // Mensagem amigável para o usuário
}

set_exception_handler('handleUncaughtException'); // Define o manipulador de exceções

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sanitizar e validar dados de entrada
$name = filter_input(INPUT_POST, "name", FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, "email", FILTER_VALIDATE_EMAIL);
$password = filter_input(INPUT_POST, "password", FILTER_UNSAFE_RAW);
$newRegistration = filter_var($_POST['newRegistration'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

if (empty($name) || !$email || empty($password) || is_null($newRegistration)) {
    exit('Dados inválidos.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

// Verificar se o email já está registrado
$sql = "SELECT * FROM usuarios WHERE email = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    if ($result->num_rows > 0) {
        echo "O email já está registado";
        exit();
    }
}

// Gerar código de aprovação e URL
$approvalCode = bin2hex(random_bytes(16));
$approvalUrl = "https://snref-backend-8d85ffa999cd.herokuapp.com/components/linkAprovacao.php?code=$approvalCode";

$adminEmail = 'retratospsn@gmail.com';
$sql = "INSERT INTO usuarios (name, email, password, status, approval_code) VALUES (?, ?, ?, 'pendente', ?)";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ssss", $name, $email, $passwordHash, $approvalCode);
    if ($stmt->execute()) {
        if ($newRegistration) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = getenv('MAIL_USERNAME'); // Carregar credenciais do .env
                $mail->Password = getenv('MAIL_PASSWORD'); // Carregar credenciais do .env
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                $mail->setFrom('retratospsn@gmail.com', utf8_decode('Paróquia de São Nicolau'));
                $mail->addAddress($adminEmail);

                $mail->isHTML(true);
                $mail->Subject = 'Novo registro';
                $mail->Body = "O usuário $name se registrou. <br><a href='$approvalUrl'>Aprovar?</a><br>";
                $mail->AltBody = "O usuário $name se registrou. Aprovar? $approvalUrl";

                $mail->send();
                echo 'Registo feito com sucesso. Aguarde pela aprovação do Administrador.';
            } catch (Exception $e) {
                handleUncaughtException($e);
            }
        }
    }
    $stmt->close();
}

$conn->close();
?>