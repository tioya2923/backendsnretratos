<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições e Inscrição via WhatsApp
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/whatsapp_utils.php';
require_once __DIR__ . '/push_utils.php';

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

    $isSegundaManha = ($diaSemana == 1 && $horaMinuto === '13:10');
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
            $msg = "Olá, $nome! Recordamos que deve fazer a sua inscrição para as próximas refeições. Clique aqui: $link";
            sendWhatsApp($whats, $msg);
            $assunto = "Recordatório: Inscrição para Refeições";
            $headers = "From: no-reply@saonicolau.pt\r\nContent-Type: text/plain; charset=UTF-8";
            mail($email, $assunto, $msg, $headers);
            usleep(200000);
        }
    }

    // Push notification para todos os subscritores
    sendPushNotification(
        $conn,
        'Inscrição para Refeições',
        'Recorda-te de fazer a tua inscrição para as próximas refeições!',
        '/refeicoes',
        [],
        'psn-refeicao',
        7200,
        'high'
    );
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
                $msg = "Olá, $nomeOriginal! Não te esqueças que estás inscrito para o " . ($tipo === 'almoco' ? "almoço" : "jantar") . " de hoje. Bom apetite!";
            } else {
                $msg = "Olá $nomeOriginal. Informamos que não constas na lista de inscritos para o " . ($tipo === 'almoco' ? "almoço" : "jantar") . " de hoje.";
            }

            $resultado = sendWhatsApp($numeroWhatsApp, $msg);
            $statusLog = $resultado ? "SUCESSO" : "FALHA";
            file_put_contents($logfile, "[$statusLog] Lembrete Diário ($tipo): $nomeOriginal\n", FILE_APPEND);
            usleep(1000000);
        }

        // Push notifications personalizadas (igual ao WhatsApp)
        $tipoLabel = $tipo === 'almoco' ? 'almoço' : 'jantar';
        $inscritosIds   = [];
        $naoInscritosIds = [];

        $resTodosUsers = $conn->query("SELECT id, name FROM usuarios");
        if ($resTodosUsers) {
            while ($u = $resTodosUsers->fetch_assoc()) {
                $nomeComp = mb_strtolower(trim($u['name']), 'UTF-8');
                if (in_array($nomeComp, $inscritos)) {
                    $inscritosIds[] = $u['id'];
                } else {
                    $naoInscritosIds[] = $u['id'];
                }
            }
        }

        if (!empty($inscritosIds)) {
            sendPushNotification(
                $conn,
                ucfirst($tipoLabel) . " de hoje",
                "Estás inscrito para o $tipoLabel de hoje. Bom apetite! 🍽️",
                '/refeicoes',
                $inscritosIds,
                'psn-refeicao',
                3600,
                'high'
            );
        }

        if (!empty($naoInscritosIds)) {
            sendPushNotification(
                $conn,
                ucfirst($tipoLabel) . " de hoje",
                "Não estás inscrito para o $tipoLabel de hoje.",
                '/refeicoes',
                $naoInscritosIds,
                'psn-refeicao',
                3600,
                'high'
            );
        }
    }
}

/**
 * Envia notificações push para atividades que começam nos próximos 30 minutos.
 * Corre sempre que o cron dispara — não tem condições de hora/dia.
 */
function notificarAtividades() {
    global $logfile, $conn;

    $hoje    = date('Y-m-d');
    $agora   = date('H:i:s');
    $horaMax = date('H:i:s', strtotime('+30 minutes'));

    $stmt = $conn->prepare("
        SELECT a.id, a.user_id, a.hora_inicio,
               COALESCE(NULLIF(a.titulo,''), a.tipo) AS nome_atividade
        FROM atividades_usuario a
        WHERE a.ativo = 1
          AND a.data_atividade = ?
          AND TIME(a.hora_inicio) BETWEEN ? AND ?
          AND a.ultima_notificacao IS NULL
    ");
    $stmt->bind_param("sss", $hoje, $agora, $horaMax);
    $stmt->execute();
    $atividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($atividades)) {
        file_put_contents($logfile, "[Atividades] Nenhuma atividade para notificar agora.\n", FILE_APPEND);
        return;
    }

    foreach ($atividades as $atv) {
        $hora   = substr($atv['hora_inicio'], 0, 5);
        $titulo = ucfirst(mb_strtolower($atv['nome_atividade'], 'UTF-8'));
        $body   = "$titulo começa às $hora. Não te esqueças!";

        sendPushNotification(
            $conn,
            "Lembrete — $titulo",
            $body,
            '/perfil',
            [$atv['user_id']],
            'psn-atividade',
            1800,
            'high'
        );

        $upd = $conn->prepare("UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ?");
        $upd->bind_param("i", $atv['id']);
        $upd->execute();

        file_put_contents($logfile, "[OK] Atividade notificada: User {$atv['user_id']}: {$titulo} às {$hora}\n", FILE_APPEND);
    }
}

enviarLembreteInscricao();
enviarLembretes();
notificarAtividades();

echo "[LOG] Script finalizado.\n";
file_put_contents($logfile, "--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
?>