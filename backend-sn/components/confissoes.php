<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Função para converter a data
function converterData($data) {
    $meses = [
        'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
        'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
        'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
    ];
    $partes = explode(' de ', $data);
    $dia = $partes[0];
    $mes = $meses[strtolower($partes[1])];
    $ano = $partes[2];
    return "$ano-$mes-$dia";
}

// Função para enviar email
function enviarEmail($email, $nome, $assunto, $corpo) {
    $mail = new PHPMailer(true);
    try {
        // Configurações do servidor
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'retratospsn@gmail.com';
        $mail->Password = 'thqyngnejodzttwl'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Destinatários
        $mail->setFrom('retratospsn@gmail.com', utf8_decode('Acolhimento da Igreja de São Nicolau'));
        $mail->addAddress($email);

        // Conteúdo do email
        $mail->isHTML(true);
        $mail->Subject = utf8_decode($assunto);
        $mail->Body    = $corpo;

        $mail->send();
    } catch (Exception $e) {
        echo "Erro ao enviar email: {$mail->ErrorInfo}";
    }
}

// Função para enviar notificação 30 minutos antes
function enviarNotificacao($email, $nome, $dia_semana, $horario_inicio, $horario_fim, $data) {
    $assunto = 'Confissões';
    $corpo = "Olá $nome,<br><br>Tem Confissões marcadas para $dia_semana, $data, das $horario_inicio às $horario_fim.<br><br>Contamos consigo.";

    // Calcular o horário de envio (30 minutos antes)
    $horario_envio = date('H:i', strtotime($horario_inicio) - 1800);

    // Agendar o envio do email
    $agora = date('H:i');
    if ($agora == $horario_envio) {
        enviarEmail($email, $nome, $assunto, $corpo);
    }
}

// Função para enviar notificação semanal
function enviarNotificacaoSemanal($conn) {
    $hoje = date('Y-m-d');
    $data_envio = date('Y-m-d', strtotime($hoje . ' + 4 days'));

    // Obter todos os horários para a próxima semana
    $sql = "SELECT * FROM horarios WHERE data >= '$data_envio' AND data < DATE_ADD('$data_envio', INTERVAL 7 DAY)";
    $result = $conn->query($sql);
    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = $row;
    }

    // Agrupar horários por email
    $emails = [];
    foreach ($horarios as $horario) {
        $emails[$horario['email']][] = $horario;
    }

    // Enviar email para cada grupo de horários
    foreach ($emails as $email => $horarios) {
        $corpo = "Olá,<br><br>Segue o seu horário para a próxima semana:<br><br>";
        foreach ($horarios as $horario) {
            $corpo .= "Dia: " . $horario['dia_semana'] . ", Data: " . $horario['data'] . ", Horário: " . $horario['horario_inicio'] . " - " . $horario['horario_fim'] . "<br>";
        }
        $corpo .= "<br>Contamos consigo.";
        enviarEmail($email, '', 'Horário Semanal', $corpo);
    }
}

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Adicionar Nome
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (isset($data['dia_semana'], $data['horario_inicio'], $data['horario_fim'], $data['nome'], $data['data'])) {
        $dia_semana = $data['dia_semana'];
        $horario_inicio = $data['horario_inicio'];
        $horario_fim = $data['horario_fim'];
        $nome = $data['nome'];
        $data_confissao = converterData($data['data']);

        // Obter email com base no nome
        $sql = "SELECT email FROM nomes_predefinidos WHERE nome = '$nome'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $email = $row['email'];

            // Verificar se o nome já foi inserido para o mesmo horário, dia da semana e data
            $sql = "SELECT * FROM horarios WHERE nome = '$nome' AND dia_semana = '$dia_semana' AND horario_inicio = '$horario_inicio' AND horario_fim = '$horario_fim' AND data = '$data_confissao'";
            $result = $conn->query($sql);
            if ($result->num_rows == 0) {
                $sql = "INSERT INTO horarios (dia_semana, horario_inicio, horario_fim, nome, email, data) VALUES ('$dia_semana', '$horario_inicio', '$horario_fim', '$nome', '$email', '$data_confissao')";
                if ($conn->query($sql) === TRUE) {
                    enviarNotificacao($email, $nome, $dia_semana, $horario_inicio, $horario_fim, $data_confissao);
                    echo json_encode(["message" => "Nome adicionado com sucesso"]);
                } else {
                    echo json_encode(["message" => "Erro ao adicionar nome: " . $conn->error]);
                }
            } else {
                echo json_encode(["message" => "Nome já inserido para este horário"]);
            }
        } else {
            echo json_encode(["message" => "Nome não está na lista de nomes predefinidos"]);
        }
    } else {
        echo json_encode(["message" => "Dados incompletos"]);
    }
}

// Obter Horários
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $sql = "SELECT * FROM horarios";
    $result = $conn->query($sql);
    $horarios = [];
    while ($row = $result->fetch_assoc()) {
        $horarios[] = $row;
    }
    echo json_encode($horarios);
}

// Enviar notificação semanal
enviarNotificacaoSemanal($conn);

$conn->close();
?>
