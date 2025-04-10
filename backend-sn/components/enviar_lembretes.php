<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adicionando um log para monitorar execução
file_put_contents('/var/log/enviar_lembretes_cron.log', "Script iniciado em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Função para enviar lembretes
function enviarLembretes()
{
    global $conn;

    // Carregar emails e nomes dos usuários que ainda não se inscreveram
    $sql = "SELECT email, name FROM usuarios WHERE id NOT IN (SELECT id FROM refeicoes WHERE data = CURDATE())";

    $result = $conn->query($sql);
    $usuarios = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = ['email' => $row['email'], 'name' => $row['name']];
        }
        echo "Usuários que precisam de lembrete carregados com sucesso.\n";
        print_r($usuarios);
    } else {
        echo "Todos os usuários já se inscreveram ou erro na consulta.\n";
        file_put_contents('/var/log/enviar_lembretes_cron.log', "Consulta SQL não retornou resultados em: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
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
            $mail->Username = getenv('MAIL_USERNAME'); // Carregar credenciais do .env
            $mail->Password = getenv('MAIL_PASSWORD'); // Carregar credenciais do .env
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom(getenv('MAIL_USERNAME'), utf8_decode('Paróquia de São Nicolau'));

            foreach ($usuarios as $usuario) {
                $email = $usuario['email'];
                $name = $usuario['name']; // Usar o campo 'name'

                // Substituir placeholder pelo nome do destinatário
                $body = str_replace('{{nome}}', $name, $bodyTemplate);

                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
                $mail->send();
                $mail->clearAddresses(); // Limpar para o próximo e-mail
                echo "E-mail enviado para: $email\n";
            }

            file_put_contents('/var/log/enviar_lembretes_cron.log', "E-mails enviados com sucesso em: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        } catch (Exception $e) {
            error_log($e->getMessage());
            file_put_contents('/var/log/enviar_lembretes_cron.log', "Erro ao enviar email: " . $e->getMessage() . "\n", FILE_APPEND);
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
            'body' => '<p>Olá, {{nome}}! Preparado para mais uma semana? Ainda não fizestes a inscrição para as refeições. <a href="https://snrefeicoes.pt/login">INSCREVA-TE</a></p><p>São Nicolau agradece!</p>'
        ],
        '4-21:30' => [
            'subject' => 'Boa noite!',
            'body' => '<p>Olá, {{nome}}! Como está a sua semana? Relembro que ainda não fizestes a inscrição para as refeições. <a href="https://snrefeicoes.pt/login">INSCREVA-TE</a></p><p>São Nicolau agradece!</p>'
        ],

         '4-16:10' => [
            'subject' => 'Boa tarde!',
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

enviarLembretes();

?>