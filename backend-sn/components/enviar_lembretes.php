<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições via WhatsApp
 * Localização sugerida: /app/scripts/enviar_lembretes_cron.php
 */

date_default_timezone_set('Europe/Lisbon');

// Importação das dependências
require_once __DIR__ . '/../connect/server.php';
// Certifique-se de que o whatsapp_utils.php contém a função sendWhatsApp com cURL e o IP da Hetzner
require_once __DIR__ . '/whatsapp_utils.php';

// Configuração do Log
$logfile = __DIR__ . '/enviar_lembretes_cron.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($logfile, "--- Script iniciado em $timestamp ---\n", FILE_APPEND);
echo "[LOG] Script iniciado em $timestamp\n";

/**
 * Função principal que processa a lógica de envio
 */
function enviarLembretes() {
    global $logfile, $conn;

    // Definição dos horários das refeições
    $horarios = [
        'almoco' => '13:30',
        'jantar' => '20:00'
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        
        // Calcula a hora de envio (30 minutos antes da refeição)
        $horaEnvio = date('H:i', strtotime($horaRefeicao) - 30 * 60);

        /**
         *Se não for o minuto exato de envio, ignora esta refeição
        *if ($horaAgora !== $horaEnvio) {
            *echo "[DEBUG] Hora atual ($horaAgora) não coincide com a hora de envio ($horaEnvio) para o $tipo.\n";
           *continue;
        *}
 */
        echo "[LOG] Processando envios para o $tipo (Refeição: $horaRefeicao)...\n";
        file_put_contents($logfile, "Processando $tipo das $horaRefeicao\n", FILE_APPEND);

        // 1. Buscar todos os usuários
        $usuarios = [];
        $sqlUsuarios = "SELECT id, name, whatsapp FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
        $resUsuarios = $conn->query($sqlUsuarios);
        
        if ($resUsuarios) {
            while ($row = $resUsuarios->fetch_assoc()) {
                $usuarios[$row['id']] = $row;
            }
        }

        if (empty($usuarios)) {
            echo "[AVISO] Nenhum usuário com WhatsApp encontrado.\n";
            continue;
        }

        // 2. Buscar inscritos na refeição de hoje
        $inscritos = [];
        $sqlInscritos = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resInscritos = $conn->query($sqlInscritos);
        
        if ($resInscritos) {
            while ($row = $resInscritos->fetch_assoc()) {
                $inscritos[] = trim($row['nome_completo']);
            }
        }

        echo "[LOG] Usuários totais: " . count($usuarios) . " | Inscritos hoje: " . count($inscritos) . "\n";

        // 3. Loop de envio de mensagens
        foreach ($usuarios as $user) {
            $nomeUsuario = trim($user['name']);
            $numeroWhatsApp = $user['whatsapp'];

            // Verifica se o nome do usuário está na lista de inscritos
            $estaInscrito = in_array($nomeUsuario, $inscritos);

            // Define a mensagem
            if ($estaInscrito) {
                $msg = ($tipo === 'almoco') 
                    ? "Olá $nomeUsuario! Não te esqueças que estás inscrito para o almoço de hoje às $horaRefeicao. Bom apetite!" 
                    : "Olá $nomeUsuario! Não te esqueças que estás inscrito para o jantar de hoje às $horaRefeicao. Bom apetite!";
            } else {
                $msg = ($tipo === 'almoco')
                    ? "Olá $nomeUsuario. Informamos que não constas na lista de inscritos para o almoço de hoje."
                    : "Olá $nomeUsuario. Informamos que não constas na lista de inscritos para o jantar de hoje.";
            }

            // Dispara para o servidor Hetzner
            $resultado = sendWhatsApp($numeroWhatsApp, $msg);

            $statusLog = $resultado ? "SUCESSO" : "FALHA";
            $logMsg = "[$statusLog] Destinatário: $nomeUsuario ($numeroWhatsApp) | Tipo: $tipo\n";
            
            echo "[LOG] $logMsg";
            file_put_contents($logfile, $logMsg, FILE_APPEND);

            // Pequena pausa (0.2s) para não sobrecarregar a API da Hetzner em envios massivos
            usleep(200000);
        }
    }
}

// Execução da função
enviarLembretes();

echo "[LOG] Script finalizado.\n";
file_put_contents($logfile, "--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);

?>