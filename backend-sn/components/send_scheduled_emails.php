<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'path/to/PHPMailer/src/Exception.php';
require 'path/to/PHPMailer/src/PHPMailer.php';
require 'path/to/PHPMailer/src/SMTP.php';

function sendEmail($subject, $body, $recipients)
{
    $mail = new PHPMailer(true);
    try {
        $phpmailer = new PHPMailer();
        $phpmailer->isSMTP();
        $phpmailer->Host = 'live.smtp.mailtrap.io';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = 587;
        $phpmailer->Username = 'api';
        $phpmailer->Password = 'f68cd37feaf8527e66a95ecbc9aa8fb5';

        $mail->setFrom('retratospsn@gmail.com', utf8_decode('Paróquia de São Nicolau'));

        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        echo "Email enviado com sucesso para todos os destinatários.\n";
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo "Erro ao enviar email: " . $e->getMessage() . "\n";
    }
}

// Carregar emails dos usuários
$sql = "SELECT email FROM usuarios";
$result = $conn->query($sql);
$emails = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }
    echo "Emails carregados com sucesso.\n";
} else {
    echo "Nenhum email encontrado.\n";
}

while (true) {
    $currentDayOfWeek = date('N');
    $currentHour = date('G');
    $currentMinute = date('i');
    $currentTime = date('H:i');

    echo "Verificando emails a serem enviados. Data e Hora atuais: " . date('Y-m-d H:i:s') . "\n";
    echo "Dia atual: $currentDayOfWeek\n";
    echo "Hora atual: $currentTime\n";

    // Definir os horários para envio dos emails
    $timesToCheck = [
        '3-17:35' => ['subject' => 'Bom dia!', 'body' => '<p>Olá, Bom dia! Preparado para mais uma semana laboral?</p><p>Passo apenas para lhe fazer lembrar o seguinte: INSCREVA-TE PARA AS REFEIÇÕES.</p> <p>São Nicolau agradece!</p>'],
        '4-21:00' => ['subject' => 'Boa noite!', 'body' => '<p>Olá, boa noite! Como está a decorrer a tua semana laboral?</p><p>Se ainda não fez a inscrição para as refeições, faça-o agora mesmo.</a></p> <p>São Nicolau agradece!</p>'],
        '6-14:30' => ['subject' => 'Boa tarde!', 'body' => '<p>Olá, boa tarde!</p><p>Aproveite o final de semana para fazer a inscrição para as refeições.</p> <p>São Nicolau agradece!</p>']
    ];

    // Verificar se é o horário de envio de email
    foreach ($timesToCheck as $dayTime => $emailData) {
        list($day, $time) = explode('-', $dayTime);
        if ($currentDayOfWeek == $day && $currentTime == $time) {
            echo "Enviando email para: $currentTime\n";
            sendEmail($emailData['subject'], $emailData['body'], $emails);
        }
    }

    // Esperar por 60 segundos antes de verificar novamente
    sleep(60);
}


?>