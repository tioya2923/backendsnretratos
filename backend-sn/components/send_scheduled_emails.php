<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once '../connect/server.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($subject, $body, $recipients) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'retratospsn@gmail.com';
        $mail->Password = 'thqyngnejodzttwl'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom('retratospsn@gmail.com', 'Administrador');

        foreach ($recipients as $recipient) {
            $mail->addAddress($recipient);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

$sql = "SELECT email FROM usuarios";
$result = $conn->query($sql);
$emails = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $emails[] = $row['email'];
    }
}

$dayOfWeek = date('N');
$hour = date('G');
$minute = date('i');

if ($dayOfWeek == 1 && $hour == 21 && $minute == 35) {
    $subject = "Bom dia!";
    $body = "<p>Olá, Bom dia! Preparado para mais uma semana laboral?</p>
    <p>Passo apenas para lhe fazer lembrar o seguinte: <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/refeicoes'>INSCREVA-TE PARA AS REFEIÇÕES.</a></p>";
    sendEmail($subject, $body, $emails);
}

if ($dayOfWeek == 4 && $hour == 21 && $minute == 0) {
    $subject = "Boa noite!";
    $body = "<p>Olá, boa noite! Como está a decorrer a tua semana laboral?</p>
    <p>Se ainda não te inscreveste para as refeições <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/refeicoes'>faça-o agora mesmo.</a></p>";
    sendEmail($subject, $body, $emails);
}

if ($dayOfWeek == 6 && $hour == 14 && $minute == 30) {
    $subject = "Boa tarde!";
    $body = "<p>Olá, boa tarde!</p>
    <p>Aproveite o final de semana para <a href='https://frontend-sn-e0e8d7df269a.herokuapp.com/refeicoes'>fazer a inscrição</a> para as refeições.</p>";
    sendEmail($subject, $body, $emails);
}

$conn->close();
?>
