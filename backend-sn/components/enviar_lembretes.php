<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições e Inscrição via WhatsApp
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/whatsapp_utils.php';

// FORÇAR UTF-8 para evitar erro em nomes com acentos (ex: João, Pelágio)
$conn->set_charset("utf8mb4");

$logfile = __DIR__ . '/enviar_lembretes_cron.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($logfile, "--- Script iniciado em $timestamp ---\n", FILE_APPEND);
echo "[LOG] Script iniciado em $timestamp\n";

function enviarLembreteInscricao() {
    global $logfile, $conn;
    $diaSemana = date('N'); 
    $horaMinuto = date('H:i');

    $isSegundaManha = ($diaSemana == 1 && $horaMinuto === '09:00');
    $isQuintaNoite = ($diaSemana == 4 && $horaMinuto === '21:30');

    if (!$isSegundaManha && !$isQuintaNoite) return;

    echo "[LOG] Iniciando lembrete de INSCRIÇÃO semanal...\n";
    $link = "https://snref-fronten-8dbe187fda6c.herokuapp.com/";
    $sql = "SELECT name, whatsapp, email FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
    $res = $conn->query($sql);

    if ($res) {
        while ($user = $res->fetch_assoc()) {
            $nome = trim($user['name']);
            $whats = $user['whatsapp'];
            $email = $user['email'];
            $msg = "Olá $nome! Recordamos que deve fazer a sua inscrição para as próximas refeições. Clique aqui: $link";
            sendWhatsApp($whats, $msg);
            $assunto = "Recordatório: Inscrição para Refeições";
            $headers = "From: no-reply@saonicolau.pt\r\nContent-Type: text/plain; charset=UTF-8";
            mail($email, $assunto, $msg, $headers);
            usleep(200000);
        }
    }
}

function enviarLembretes() {
    global $logfile, $conn;

    $horarios = [
        'almoco' => '13:30', // Envio às 13:00
        'jantar' => '20:00'  // Envio às 19:30
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        $horaEnvio = date('H:i', strtotime($horaRefeicao) - 10 * 60);

        if ($horaAgora !== $horaEnvio) {
            echo "[DEBUG] Hora atual ($horaAgora) não coincide com a hora de envio ($horaEnvio) para o $tipo.\n";
            continue;
        }
 
        echo "[LOG] Processando envios para o $tipo...\n";

        // 1. Buscar usuários
        $usuarios = [];
        $sqlUsuarios = "SELECT name, whatsapp FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
        $resUsuarios = $conn->query($sqlUsuarios);
        if ($resUsuarios) {
            while ($row = $resUsuarios->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }

        // 2. Buscar inscritos (Usando o nome da coluna correto: almoco)
        $inscritos = [];
        $sqlInscritos = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resInscritos = $conn->query($sqlInscritos);
        if ($resInscritos) {
            while ($row = $resInscritos->fetch_assoc()) {
                // Normalização: trim e conversão para minúsculas ajuda na comparação
                $inscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8');
            }
        }

        foreach ($usuarios as $user) {
            $nomeOriginal = trim($user['name']);
            $nomeComparacao = mb_strtolower($nomeOriginal, 'UTF-8');
            $numeroWhatsApp = $user['whatsapp'];
            
            // Comparação mais robusta
            $estaInscrito = in_array($nomeComparacao, $inscritos);

            if ($estaInscrito) {
                $msg = "Olá $nomeOriginal! Não te esqueças que estás inscrito para o " . ($tipo === 'almoco' ? "almoço" : "jantar") . " de hoje. Bom apetite!";
            } else {
                $msg = "Olá $nomeOriginal. Informamos que não constas na lista de inscritos para o " . ($tipo === 'almoco' ? "almoço" : "jantar") . " de hoje.";
            }

            $resultado = sendWhatsApp($numeroWhatsApp, $msg);
            $statusLog = $resultado ? "SUCESSO" : "FALHA";
            file_put_contents($logfile, "[$statusLog] Lembrete Diário ($tipo): $nomeOriginal\n", FILE_APPEND);
            usleep(250000); // Aumentado ligeiramente para estabilidade
        }
    }
}

enviarLembreteInscricao();
enviarLembretes();

echo "[LOG] Script finalizado.\n";
file_put_contents($logfile, "--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
?>