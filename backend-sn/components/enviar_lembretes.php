<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições e Inscrição
 * via WhatsApp, Email (PHPMailer) e Push Notification.
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/whatsapp_utils.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/push_utils.php';

$conn->set_charset("utf8mb4");

$logfile   = __DIR__ . '/enviar_lembretes_cron.log';
$timestamp = date('Y-m-d H:i:s');

file_put_contents($logfile, "--- Script iniciado em $timestamp ---\n", FILE_APPEND);
echo "[LOG] Script iniciado em $timestamp\n";

// ---------------------------------------------------------------------------
// Lembrete semanal de inscrição (2ª-feira 13:10 e 5ª-feira 21:30)
// ---------------------------------------------------------------------------
function enviarLembreteInscricao() {
    global $logfile, $conn;

    $diaSemana  = date('N');
    $horaMinuto = date('H:i');

    $isSegundaManha = ($diaSemana == 1 && $horaMinuto === '13:10');
    $isQuintaNoite  = ($diaSemana == 4 && $horaMinuto === '21:30');

    if (!$isSegundaManha && !$isQuintaNoite) return;

    echo "[LOG] Iniciando lembrete de INSCRIÇÃO semanal...\n";

    $link    = "https://snref-fronten-8dbe187fda6c.herokuapp.com/";
    $assunto = "Recordatório: Inscrição para Refeições";

    $sql = "SELECT name, whatsapp, email FROM usuarios WHERE status = 'aprovado'";
    $res = $conn->query($sql);

    if ($res) {
        while ($user = $res->fetch_assoc()) {
            $nome  = trim($user['name']);
            $msg   = "Olá, $nome! Recorda-te de fazer a tua inscrição para as próximas refeições. Clica aqui: $link";

            // WhatsApp
            if (!empty($user['whatsapp'])) {
                $ok = sendWhatsApp($user['whatsapp'], $msg);
                file_put_contents($logfile, "[Inscrição WA] " . ($ok ? "OK" : "FALHA") . ": $nome\n", FILE_APPEND);
            }

            // Email
            if (!empty($user['email'])) {
                $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>
                             Recorda-te de fazer a tua inscrição para as próximas refeições.<br>
                             <a href='$link'>Clica aqui para te inscrever</a>";
                $ok = sendEmail($user['email'], $assunto, $bodyHtml, true);
                file_put_contents($logfile, "[Inscrição Email] " . ($ok ? "OK" : "FALHA") . ": $nome\n", FILE_APPEND);
            }

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

    file_put_contents($logfile, "[Inscrição Push] Enviado para todos os subscritores.\n", FILE_APPEND);
}

// ---------------------------------------------------------------------------
// Lembrete diário de refeições (almoço 13:20, jantar 19:50)
// ---------------------------------------------------------------------------
function enviarLembretes() {
    global $logfile, $conn;

    $horarios = [
        'almoco' => '13:30',
        'jantar' => '20:00',
    ];

    $dataHoje = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        $horaEnvio = date('H:i', strtotime($horaRefeicao) - 10 * 60);

        if ($horaAgora !== $horaEnvio) {
            echo "[DEBUG] Hora atual ($horaAgora) não coincide com hora de envio ($horaEnvio) para $tipo.\n";
            continue;
        }

        echo "[LOG] Processando envios para $tipo...\n";

        // Buscar utilizadores aprovados
        $usuarios = [];
        $sqlU = "SELECT id, name, whatsapp, email FROM usuarios WHERE status = 'aprovado'";
        $resU = $conn->query($sqlU);
        if ($resU) {
            while ($row = $resU->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }

        // Buscar inscritos para hoje
        $inscritos = [];
        $sqlI = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo = '1'";
        $resI = $conn->query($sqlI);
        if ($resI) {
            while ($row = $resI->fetch_assoc()) {
                $inscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8');
            }
        }

        $tipoLabel  = $tipo === 'almoco' ? 'almoço' : 'jantar';
        $assunto    = ucfirst($tipoLabel) . " de hoje — " . date('d/m/Y');
        $inscritosIds    = [];
        $naoInscritosIds = [];

        foreach ($usuarios as $user) {
            $nomeOriginal  = trim($user['name']);
            $nomeComp      = mb_strtolower($nomeOriginal, 'UTF-8');
            $estaInscrito  = in_array($nomeComp, $inscritos);

            if ($estaInscrito) {
                $msgWa   = "Olá, $nomeOriginal! Não te esqueças que estás inscrito para o $tipoLabel de hoje. Bom apetite!";
                $msgHtml = "Olá, <strong>$nomeOriginal</strong>!<br><br>Não te esqueças que estás inscrito para o <strong>$tipoLabel</strong> de hoje.<br>Bom apetite! 🍽️";
                $inscritosIds[] = $user['id'];
            } else {
                $msgWa   = "Olá, $nomeOriginal. Informamos que não constás na lista de inscritos para o $tipoLabel de hoje.";
                $msgHtml = "Olá, <strong>$nomeOriginal</strong>.<br><br>Informamos que não constás na lista de inscritos para o <strong>$tipoLabel</strong> de hoje.";
                $naoInscritosIds[] = $user['id'];
            }

            // WhatsApp
            if (!empty($user['whatsapp'])) {
                $ok = sendWhatsApp($user['whatsapp'], $msgWa);
                $status = $ok ? "OK" : "FALHA";
                file_put_contents($logfile, "[$status] WA $tipo: $nomeOriginal\n", FILE_APPEND);
            }

            // Email
            if (!empty($user['email'])) {
                $ok = sendEmail($user['email'], $assunto, $msgHtml, true);
                $status = $ok ? "OK" : "FALHA";
                file_put_contents($logfile, "[$status] Email $tipo: $nomeOriginal\n", FILE_APPEND);
            }

            usleep(500000);
        }

        // Push para inscritos
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

        // Push para não inscritos
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

        file_put_contents($logfile, "[Push $tipo] Inscritos: " . count($inscritosIds) . " | Não inscritos: " . count($naoInscritosIds) . "\n", FILE_APPEND);
    }
}

// ---------------------------------------------------------------------------
// Notificações de atividades pessoais (próximos 30 minutos)
// ---------------------------------------------------------------------------
function notificarAtividades() {
    global $logfile, $conn;

    $hoje    = date('Y-m-d');
    $agora   = date('H:i:s');
    $horaMax = date('H:i:s', strtotime('+30 minutes'));

    $stmt = $conn->prepare("
        SELECT a.id, a.user_id, a.hora_inicio,
               COALESCE(NULLIF(a.titulo,''), a.tipo) AS nome_atividade,
               u.name, u.whatsapp, u.email
        FROM atividades_usuario a
        JOIN usuarios u ON u.id = a.user_id
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
        $nome   = trim($atv['name']);

        $msgWa   = "Olá, $nome! $titulo começa às $hora. Não te esqueças!";
        $msgHtml = "Olá, <strong>$nome</strong>!<br><br><strong>$titulo</strong> começa às <strong>$hora</strong>. Não te esqueças!";
        $assunto = "Lembrete — $titulo às $hora";

        // WhatsApp
        if (!empty($atv['whatsapp'])) {
            $ok = sendWhatsApp($atv['whatsapp'], $msgWa);
            file_put_contents($logfile, "[Atividade WA " . ($ok ? "OK" : "FALHA") . "] User {$atv['user_id']}: $titulo às $hora\n", FILE_APPEND);
        }

        // Email
        if (!empty($atv['email'])) {
            $ok = sendEmail($atv['email'], $assunto, $msgHtml, true);
            file_put_contents($logfile, "[Atividade Email " . ($ok ? "OK" : "FALHA") . "] User {$atv['user_id']}: $titulo às $hora\n", FILE_APPEND);
        }

        // Push
        sendPushNotification(
            $conn,
            "Lembrete — $titulo",
            "$titulo começa às $hora. Não te esqueças!",
            '/perfil',
            [$atv['user_id']],
            'psn-atividade',
            1800,
            'high'
        );

        $upd = $conn->prepare("UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ?");
        $upd->bind_param("i", $atv['id']);
        $upd->execute();

        file_put_contents($logfile, "[Atividade Push OK] User {$atv['user_id']}: $titulo às $hora\n", FILE_APPEND);
    }
}

// ---------------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------------
enviarLembreteInscricao();
enviarLembretes();
notificarAtividades();

echo "[LOG] Script finalizado.\n";
file_put_contents($logfile, "--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
