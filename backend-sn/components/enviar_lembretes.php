<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Função para enviar lembretes
// Função para enviar lembretes
function enviarLembretes()
{
    global $conn;

    // Carregar emails e nomes dos usuários que ainda não se inscreveram
    $sql = "SELECT email, nome FROM usuarios WHERE id NOT IN (SELECT usuario_id FROM refeicoes WHERE data = CURDATE())";
    $result = $conn->query($sql);
    $usuarios = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = ['email' => $row['email'], 'nome' => $row['nome']];
        }
        echo "Usuários que precisam de lembrete carregados com sucesso.\n";
    } else {
        echo "Todos os usuários já se inscreveram.\n";
        return; // Finalizar, pois não há lembretes a enviar
    }

    // Função para enviar e-mails
    function sendEmail($subject, $bodyTemplate, $usuarios)
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'retratospsn@gmail.com';
            $mail->Password = 'thqyngnejodzttwl'; // Mantenha senhas seguras!
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('retratospsn@gmail.com', utf8_decode('Paróquia de São Nicolau'));

            foreach ($usuarios as $usuario) {
                $email = $usuario['email'];
                $nome = $usuario['nome'];

                // Substituir placeholder pelo nome do destinatário
                $body = str_replace('{{nome}}', $nome, $bodyTemplate);

                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                $mail->send();
                $mail->clearAddresses(); // Limpar para o próximo e-mail
            }

            echo "E-mails enviados com sucesso.\n";
        } catch (Exception $e) {
            error_log($e->getMessage());
            echo "Erro ao enviar email: " . $e->getMessage() . "\n";
        }
    }

    // Agendamento de envio de emails
    $currentDayOfWeek = date('N');
    $currentTime = date('H:i');
    echo "Verificando emails a serem enviados. Data e Hora atuais: " . date('Y-m-d H:i:s') . "\n";

    // Horários e conteúdos de e-mails
    $timesToCheck = [
        '1-09:00' => [
            'subject' => 'Bom dia!',
            'body' => '<p>Olá, {{nome}}! Preparado para mais uma semana? Ainda não fizestes a isncrição para as refeições. <a href="https://snrefeicoes.pt/login">INSCREVA-TE</a></p><p>São Nicolau agradece!</p>'
        ],
        '4-00:20' => [
            'subject' => 'Boa noite!',
            'body' => '<p>Olá, {{nome}}! Como está a sua semana? Relembro que ainda não fizestes a inscrição para as refeições. <a href="https://snrefeicoes.pt/login">INSCREVA-TE</a></p><p>São Nicolau agradece!</p>'
        ],
    ];

    // Verificar e enviar lembretes
    foreach ($timesToCheck as $dayTime => $emailData) {
        list($day, $time) = explode('-', $dayTime);
        if ($currentDayOfWeek == $day && $currentTime == $time) {
            sendEmail($emailData['subject'], $emailData['body'], $usuarios);
        }
    }

    $conn->close();
}

?>