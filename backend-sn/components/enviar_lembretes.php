<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições e Inscrição via WhatsApp
 * Localização sugerida: /app/scripts/enviar_lembretes_cron.php
 */

date_default_timezone_set('Europe/Lisbon');

// Importação das dependências
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/whatsapp_utils.php';

// Configuração do Log
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($logfile, "--- Script iniciado em $timestamp ---\n", FILE_APPEND);
echo "[LOG] Script iniciado em $timestamp\n";

/**
 * NOVO: Função para enviar lembrete de inscrição semanal
 * Segundas às 09:00 e Quintas às 21:30
 */
function enviarLembreteInscricao() {
    global $logfile, $conn;

    $diaSemana = date('N'); // 1 (Segunda) a 7 (Domingo)
    $horaMinuto = date('H:i');

    // Define os alvos: Segunda às 09:00 e Quinta às 21:30
    $isSegundaManha = ($diaSemana == 1 && $horaMinuto === '09:00');
    $isQuintaNoite = ($diaSemana == 4 && $horaMinuto === '21:30');

    if (!$isSegundaManha && !$isQuintaNoite) {
        return; // Não é horário de lembrete de inscrição
    }

    echo "[LOG] Iniciando lembrete de INSCRIÇÃO semanal...\n";
    
    $link = "https://snref-fronten-8dbe187fda6c.herokuapp.com/";
    $sql = "SELECT name, whatsapp, email FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
    $res = $conn->query($sql);

    if ($res) {
        while ($user = $res->fetch_assoc()) {
            $nome = trim($user['name']);
            $whats = $user['whatsapp'];
            $email = $user['email'];

            $msg = "Olá $nome! Recordamos que deve fazer a sua inscrição para as próximas refeições. Para se inscrever devidamente, clique aqui: $link";

            // Envio via WhatsApp
            $resultado = sendWhatsApp($whats, $msg);
            
            // Envio via Email (Utilizando a função mail padrão do PHP ou sua integração)
            $assunto = "Recordatório: Inscrição para Refeições";
            $headers = "From: no-reply@saonicolau.pt\r\nContent-Type: text/plain; charset=UTF-8";
            mail($email, $assunto, $msg, $headers);

            $status = $resultado ? "SUCESSO" : "FALHA";
            file_put_contents($logfile, "[$status] Lembrete Inscrição: $nome ($whats / $email)\n", FILE_APPEND);
            usleep(200000); // 0.2s pausa
        }
    }
}

/**
 * Função principal para lembretes diários (Mantida conforme original)
 */
function enviarLembretes() {
    global $logfile, $conn;

    $horarios = [
        'almoco' => '13:30',
        'jantar' => '20:00'
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        $horaEnvio = date('H:i', strtotime($horaRefeicao) - 30 * 60);

        if ($horaAgora !== $horaEnvio) {
            echo "[DEBUG] Hora atual ($horaAgora) não coincide com a hora de envio ($horaEnvio) para o $tipo.\n";
            continue;
        }
 
        echo "[LOG] Processando envios para o $tipo (Refeição: $horaRefeicao)...\n";

        $usuarios = [];
        $sqlUsuarios = "SELECT id, name, whatsapp FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
        $resUsuarios = $conn->query($sqlUsuarios);
        
        if ($resUsuarios) {
            while ($row = $resUsuarios->fetch_assoc()) {
                $usuarios[$row['id']] = $row;
            }
        }

        if (empty($usuarios)) continue;

        $inscritos = [];
        $sqlInscritos = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resInscritos = $conn->query($sqlInscritos);
        
        if ($resInscritos) {
            while ($row = $resInscritos->fetch_assoc()) {
                $inscritos[] = trim($row['nome_completo']);
            }
        }

        foreach ($usuarios as $user) {
            $nomeUsuario = trim($user['name']);
            $numeroWhatsApp = $user['whatsapp'];
            $estaInscrito = in_array($nomeUsuario, $inscritos);

            if ($estaInscrito) {
                $msg = ($tipo === 'almoco') 
                    ? "Olá $nomeUsuario! Não te esqueças que estás inscrito para o almoço de hoje às $horaRefeicao. Bom apetite!" 
                    : "Olá $nomeUsuario! Não te esqueças que estás inscrito para o jantar de hoje às $horaRefeicao. Bom apetite!";
            } else {
                $msg = ($tipo === 'almoco')
                    ? "Olá $nomeUsuario. Informamos que não constas na lista de inscritos para o almoço de hoje."
                    : "Olá $nomeUsuario. Informamos que não constas na lista de inscritos para o jantar de hoje.";
            }

            $resultado = sendWhatsApp($numeroWhatsApp, $msg);
            $statusLog = $resultado ? "SUCESSO" : "FALHA";
            file_put_contents($logfile, "[$statusLog] Lembrete Diário: $nomeUsuario ($tipo)\n", FILE_APPEND);
            usleep(200000);
        }
    }
}

// Execução das duas lógicas
enviarLembreteInscricao(); // Verifica se é hora de lembrar da inscrição semanal
enviarLembretes();         // Verifica se é hora dos lembretes diários 30min antes

echo "[LOG] Script finalizado.\n";
file_put_contents($logfile, "--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
?>