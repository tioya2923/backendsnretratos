<?php

date_default_timezone_set('Europe/Lisbon'); // Definir o fuso horário para Portugal Continental

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/whatsapp_utils.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Adicionando um log para monitorar execução
file_put_contents('/var/log/enviar_lembretes_cron.log', "Script iniciado em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Função para enviar lembretes

function enviarLembretes() {
    global $conn;

    // Definição dos horários das refeições (hora da refeição => tipo)
    $horarios = [
        'almoco' => '13:30', // Almoço às 13h30
        'jantar' => '20:00'  // Jantar às 20h00
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        // Para teste: ignorar verificação de horário e enviar imediatamente
        // $horaEnvio = date('H:i', strtotime($horaRefeicao) - 30*60);
        // if ($horaAgora !== $horaEnvio) continue;

        // Buscar todos os usuários
        $usuarios = [];
        $sqlUsuarios = "SELECT id, name, whatsapp FROM usuarios";
        $resUsuarios = $conn->query($sqlUsuarios);
        while ($row = $resUsuarios->fetch_assoc()) {
            $usuarios[$row['id']] = $row;
        }

        // Buscar inscrições para a refeição do dia
        $inscritos = [];
        $sqlInscritos = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resInscritos = $conn->query($sqlInscritos);
        while ($row = $resInscritos->fetch_assoc()) {
            $inscritos[] = $row['nome_completo'];
        }

        // Mapear nomes para IDs de usuários
        $mapaNomes = [];
        foreach ($usuarios as $id => $u) {
            $mapaNomes[$u['name']] = $id;
        }

        // Enviar mensagem para inscritos
        foreach ($inscritos as $nome) {
            if (!isset($mapaNomes[$nome])) continue;
            $id = $mapaNomes[$nome];
            $user = $usuarios[$id];
            $msg = $tipo === 'almoco' ?
                'Não te esqueças que estás inscrito para o almoço' :
                'Não te esqueças que estás inscrito para o jantar';
            if (!empty($user['whatsapp'])) {
                sendWhatsApp($user['whatsapp'], $msg);
            }
        }

        // Enviar mensagem para não inscritos
        foreach ($usuarios as $id => $user) {
            if (in_array($user['name'], $inscritos)) continue;
            $msg = $tipo === 'almoco' ?
                'Não estás inscrito para o almoço' :
                'Não estás inscrito para o jantar';
            if (!empty($user['whatsapp'])) {
                sendWhatsApp($user['whatsapp'], $msg);
            }
        }
    }
    $conn->close();
}


?>