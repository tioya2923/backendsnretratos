<?php

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/whatsapp_utils.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Log
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
file_put_contents($logfile, "Script iniciado em " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "[LOG] Script iniciado em " . date('Y-m-d H:i:s') . "\n";

// Função principal
function enviarLembretes() {
    global $logfile, $conn;

    echo "[LOG] Função enviarLembretes chamada.\n";

    $horarios = [
        'almoco' => '13:30',
        'jantar' => '20:00'
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {

        // Enviar 30 minutos antes
        $horaEnvio = date('H:i', strtotime($horaRefeicao) - 30 * 60);
        if ($horaAgora !== $horaEnvio) continue;

        // Buscar usuários
        $usuarios = [];
        $sqlUsuarios = "SELECT id, name, whatsapp FROM usuarios";
        $resUsuarios = $conn->query($sqlUsuarios);
        while ($row = $resUsuarios->fetch_assoc()) {
            $usuarios[$row['id']] = $row;
        }

        echo "[LOG] Usuários encontrados: " . count($usuarios) . "\n";
        file_put_contents($logfile, "Usuários encontrados: " . count($usuarios) . "\n", FILE_APPEND);

        // Buscar inscritos
        $inscritos = [];
        $sqlInscritos = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resInscritos = $conn->query($sqlInscritos);
        while ($row = $resInscritos->fetch_assoc()) {
            $inscritos[] = $row['nome_completo'];
        }

        echo "[LOG] Inscritos para $tipo: " . count($inscritos) . "\n";
        file_put_contents($logfile, "Inscritos para $tipo: " . count($inscritos) . "\n", FILE_APPEND);

        // Enviar mensagens
        foreach ($usuarios as $id => $user) {

            $inscrito = in_array($user['name'], $inscritos);

            $msg = $inscrito
                ? ($tipo === 'almoco'
                    ? 'Não te esqueças que estás inscrito para o almoço.'
                    : 'Não te esqueças que estás inscrito para o jantar.')
                : ($tipo === 'almoco'
                    ? 'Não estás inscrito para o almoço.'
                    : 'Não estás inscrito para o jantar.');
            if (!empty($user['whatsapp'])) {

                sendWhatsApp($user['whatsapp'], $msg);

                echo "[LOG] Mensagem enviada: {$user['name']} ({$user['whatsapp']}) - $msg\n";
                file_put_contents($logfile, "Mensagem enviada: {$user['name']} ({$user['whatsapp']}) - $msg\n", FILE_APPEND);
            }
        }
    }
}

//enviarLembretes();
//sendWhatsApp('351920124925', 'Mensagem de teste via PHP');


?>
