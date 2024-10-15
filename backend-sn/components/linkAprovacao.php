<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Função para enviar email
function sendEmail($userEmail, $userName, $subject, $body)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'retratospsn@gmail.com';
        $mail->Password = 'thqyngnejodzttwl';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->setFrom('retratospsn@gmail.com', utf8_decode('Paróquia de São Nicolau'));
        $mail->addAddress($userEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        $mail->send();
    } catch (Exception $e) {
        handleUncaughtException($e); // Chama o manipulador de exceções personalizado
    }
}

// Função para agendar emails
function scheduleEmails($dayOfWeek, $hour, $minute, $subject, $body)
{
    $conn = new mysqli('host', 'username', 'password', 'database');
    $sql = "SELECT * FROM usuarios WHERE status = 'aprovado'";
    if ($stmt = $conn->prepare($sql)) {
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($user = $result->fetch_assoc()) {
                // Enviar email no horário agendado
                $currentTime = new DateTime();
                $scheduledTime = new DateTime();
                $scheduledTime->setTime($hour, $minute);
                $scheduledTime->modify($dayOfWeek);
                if ($currentTime < $scheduledTime) {
                    sendEmail($user['email'], $user['nome'], $subject, str_replace('nome do usuário', $user['nome'], $body));
                }
            }
        }
        $stmt->close();
    }
    $conn->close();
}

// Agendar emails para segunda-feira às 08:00
scheduleEmails('next Tuesday', 23, 20, 'Lembrete de Inscrição', "<p>Bom dia!</p>
        <p style='font-size: larger; font-weight: bold;'>Relembramos-te que tem que fazer a <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/login'>inscrição para as refeições</a>.</p>
        <p>Que, por intercessão de São Nicolau, Deus abençoe a sua semana!</p>
        <p style='margin-top: 20px; font-size: smaller; font-style: italic;'>Se já fez a inscrição ignore este email.</p>");

// Agendar emails para quinta-feira às 21:00
scheduleEmails('next Thursday', 21, 0, 'Última Chamada para Inscrição', "<p>Boa noite!</p> 
 <p style='font-size: larger; font-weight: bold;'>Se ainda não te inscreveste para as refeições <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/login'>faça-o agora mesmo</a>.</p> 
  <p style='margin-top: 20px; font-size: smaller; font-style: italic;'>Se já fez a inscrição ignore este email.</p>");

// Código de aprovação
$approvalCode = filter_input(INPUT_GET, "code", FILTER_SANITIZE_ADD_SLASHES);
if (!empty($approvalCode)) {
    $sql = "SELECT * FROM usuarios WHERE approval_code = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $approvalCode);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $sql = "UPDATE usuarios SET status = 'aprovado' WHERE approval_code = ?";
                if ($stmt = $conn->prepare($sql)) {
                    $stmt->bind_param("s", $approvalCode);
                    if ($stmt->execute()) {
                        $user = $result->fetch_assoc();
                        $userEmail = $user['email'];
                        sendEmail($userEmail, $user['nome'], 'Conta aprovada!', "Parabéns, registo aprovado! <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/login'>Iniciar sessão</a><br>");
                        echo "Usuário aprovado com sucesso!";
                    } else {
                        echo "Falha ao atualizar o status do usuário.";
                    }
                }
            } else {
                echo "Código de aprovação inválido.";
            }
        } else {
            echo "Falha ao executar a consulta SQL.";
        }
        $stmt->close();
    }
} else {
    echo "Código de aprovação não fornecido.";
}

// Fechar a conexão
$conn->close();
?>