<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Adicionando um log para monitorar execução
file_put_contents('/var/log/enviar_lembretes_cron.log', "Script iniciado em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Função para enviar emails
function enviarLembretes() {
    global $conn;

    // Consulta SQL para buscar todos os usuários
    $sql = "SELECT email, name FROM usuarios";
    $result = $conn->query($sql);
    $usuarios = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = ['email' => $row['email'], 'name' => $row['name']];
        }
        echo "Usuários carregados com sucesso para envio do email.\n";
        print_r($usuarios); // Para visualizar usuários durante o teste
    } else {
        echo "Nenhum usuário encontrado ou erro na consulta.\n";
        file_put_contents('/var/log/enviar_lembretes_cron.log', "Nenhum usuário encontrado em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        return; // Não há usuários para enviar
    }

    // Configurar e enviar os emails
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('MAIL_USERNAME'); // Credenciais do .env
        $mail->Password = getenv('MAIL_PASSWORD'); // Credenciais do .env
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        $mail->setFrom(getenv('MAIL_USERNAME'), utf8_decode('Paróquia de São Nicolau'));
        $mail->isHTML(true);
        $mail->Subject = "Inscrevam-se já!";
        $bodyTemplate = '<p>Olá, {{nome}}, inscreva-se para as refeições, por favor! Abraços.</p>';

        foreach ($usuarios as $usuario) {
            $name = $usuario['name'];
            $email = $usuario['email'];
            $body = str_replace('{{nome}}', $name, $bodyTemplate);

            $mail->addAddress($email);
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            $mail->send();
            $mail->clearAddresses(); // Limpar destinatários para o próximo
            echo "Email enviado para: $email\n";
        }

        file_put_contents('/var/log/enviar_lembretes_cron.log', "Todos os emails enviados com sucesso em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    } catch (Exception $e) {
        echo "Erro ao enviar email: {$mail->ErrorInfo}\n";
        file_put_contents('/var/log/enviar_lembretes_cron.log', "Erro ao enviar emails em " . date('Y-m-d H:i:s') . ": {$mail->ErrorInfo}\n", FILE_APPEND);
    }

    $conn->close(); // Fechar conexão com o banco de dados
}

// Executar envio de lembretes
enviarLembretes();

?>
