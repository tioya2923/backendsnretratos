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

// Só aceita chamadas do disparador de cron (evita que qualquer pessoa na
// internet dispare envios em massa de WhatsApp/email a todos os utilizadores).
$cronSecret = getenv('CRON_SECRET');
$recebido   = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
if (php_sapi_name() !== 'cli' && (!$cronSecret || !hash_equals($cronSecret, $recebido))) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$conn->set_charset("utf8mb4");

$logfile   = __DIR__ . '/enviar_lembretes_cron.log';
$timestamp = date('Y-m-d H:i:s');

function logMsg($msg) {
    global $logfile;
    file_put_contents($logfile, $msg . "\n", FILE_APPEND);
    echo $msg . "\n";
}

logMsg("--- Script iniciado em $timestamp ---");

// ---------------------------------------------------------------------------
// Cria a tabela de idempotência se não existir
// ---------------------------------------------------------------------------
function criarTabelaLembretesEnviados($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS lembretes_enviados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data DATE NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_data_tipo (data, tipo)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Verifica se um lembrete do tipo $tipo já foi enviado hoje.
 * Regista-o atomicamente para evitar duplicados em execuções simultâneas.
 * Retorna true se acabou de registar (deve prosseguir), false se já existia.
 */
function marcarComoEnviado($conn, $tipo) {
    $hoje = date('Y-m-d');
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO lembretes_enviados (data, tipo) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $hoje, $tipo);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// ---------------------------------------------------------------------------
// Lembrete semanal de inscrição (2ª-feira ~13:10 e 5ª-feira ~21:30)
// ---------------------------------------------------------------------------
function enviarLembreteInscricao() {
    global $conn;

    criarTabelaLembretesEnviados($conn);

    $diaSemana = (int) date('N');
    $agora     = time();

    // Verifica se estamos dentro de ±10 min do horário configurado
    $janelas = [
        ['dia' => 1, 'hora' => '13:10', 'tipo' => 'inscricao_segunda'],
        ['dia' => 4, 'hora' => '21:30', 'tipo' => 'inscricao_quinta'],
    ];

    $tipoAtivo = null;
    foreach ($janelas as $j) {
        if ($diaSemana !== $j['dia']) continue;
        $alvo = strtotime('today ' . $j['hora']);
        if (abs($agora - $alvo) <= 600) {
            $tipoAtivo = $j['tipo'];
            break;
        }
    }

    if ($tipoAtivo === null) return;

    // Idempotência: garante envio único mesmo que o cron dispare várias vezes
    if (!marcarComoEnviado($conn, $tipoAtivo)) {
        logMsg("[Inscrição] Lembrete '$tipoAtivo' já enviado hoje. A saltar.");
        return;
    }

    logMsg("[LOG] Iniciando lembrete de INSCRIÇÃO semanal ($tipoAtivo)...");

    $link            = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/';
    $unsubscribeLink = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/unsubscribe';
    $assunto         = "Recordatório: Inscrição para Refeições";

    $rodapeWa   = "\n\nSe já não fazes parte da nossa comunidade, clica aqui para deixares de receber as nossas mensagens: $unsubscribeLink";
    $rodapeHtml = "<br><br><small>Se já não fazes parte da nossa comunidade, <a href='$unsubscribeLink'>clica aqui para deixares de receber as nossas mensagens</a>.</small>";

    // À 5ª-feira só reenviamos a quem, desde a 2ª-feira, ainda não se
    // inscreveu em nenhuma refeição desta semana (Segunda a Domingo).
    $jaInscritos = [];
    if ($tipoAtivo === 'inscricao_quinta') {
        $segunda = date('Y-m-d', strtotime('-' . ($diaSemana - 1) . ' days'));
        $domingo = date('Y-m-d', strtotime("$segunda +6 days"));

        $stmtSemana = $conn->prepare("SELECT DISTINCT nome_completo FROM refeicoes WHERE data BETWEEN ? AND ?");
        $stmtSemana->bind_param("ss", $segunda, $domingo);
        $stmtSemana->execute();
        $resSemana = $stmtSemana->get_result();
        while ($row = $resSemana->fetch_assoc()) {
            $jaInscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8');
        }
        $stmtSemana->close();
        logMsg("[Inscrição] Semana $segunda a $domingo — já inscritos: " . count($jaInscritos));
    }

    $sql = "SELECT id, name, whatsapp, email FROM usuarios WHERE status = 'aprovado'";
    $res = $conn->query($sql);

    $destinatarioIds = [];

    if ($res) {
        while ($user = $res->fetch_assoc()) {
            $nome = trim($user['name']);

            if ($tipoAtivo === 'inscricao_quinta' && in_array(mb_strtolower($nome, 'UTF-8'), $jaInscritos)) {
                continue; // já se inscreveu esta semana, não repetir o lembrete
            }

            if ($tipoAtivo === 'inscricao_quinta') {
                $msg      = "Olá, $nome! Estás a receber este lembrete de novo porque na segunda-feira já te avisámos e ainda não fizeste a tua inscrição para as próximas refeições. Clica aqui: $link";
                $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>
                             Estás a receber este lembrete de novo porque na segunda-feira já te avisámos e ainda não fizeste a tua inscrição para as próximas refeições.<br>
                             <a href='$link'>Clica aqui para te inscrever</a>";
            } else {
                $msg      = "Olá, $nome! Recorda-te de fazer a tua inscrição para as próximas refeições. Clica aqui: $link";
                $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>
                             Recorda-te de fazer a tua inscrição para as próximas refeições.<br>
                             <a href='$link'>Clica aqui para te inscrever</a>";
            }

            $msg      .= $rodapeWa;
            $bodyHtml .= $rodapeHtml;

            if (!empty($user['whatsapp'])) {
                $ok = sendWhatsApp($user['whatsapp'], $msg);
                logMsg("[Inscrição WA] " . ($ok ? "OK" : "FALHA") . ": $nome");
            }

            if (!empty($user['email'])) {
                $ok = sendEmail($user['email'], $assunto, $bodyHtml, true);
                logMsg("[Inscrição Email] " . ($ok ? "OK" : "FALHA") . ": $nome");
            }

            $destinatarioIds[] = (int) $user['id'];

            usleep(200000);
        }
    }

    if (!empty($destinatarioIds)) {
        sendPushNotification(
            $conn,
            'Inscrição para Refeições',
            'Recorda-te de fazer a tua inscrição para as próximas refeições!',
            '/refeicoes',
            $destinatarioIds,
            'psn-refeicao',
            7200,
            'high'
        );
    }

    logMsg("[Inscrição Push] Enviado a " . count($destinatarioIds) . " utilizador(es).");
}

// ---------------------------------------------------------------------------
// Lembrete diário de refeições (almoço ~13:20, jantar ~19:50)
// ---------------------------------------------------------------------------
function enviarLembretes() {
    global $conn;

    criarTabelaLembretesEnviados($conn);

    // Hora da refeição => calcular hora de envio (10 min antes)
    $horarios = [
        'almoco' => '13:30',
        'jantar' => '20:00',
    ];

    $agora     = time();
    $dataHoje  = date('Y-m-d');
    $horaAgora = date('H:i');

    foreach ($horarios as $tipo => $horaRefeicao) {
        // Hora de envio = 10 minutos antes da refeição
        $horaEnvio   = date('H:i', strtotime($horaRefeicao) - 10 * 60);
        $horaEnvioTs = strtotime("today $horaEnvio");

        // Janela de ±10 minutos: cobre jitter do Heroku Scheduler
        if (abs($agora - $horaEnvioTs) > 600) {
            logMsg("[DEBUG] Hora actual ($horaAgora) fora da janela de envio ($horaEnvio ±10 min) para $tipo. A saltar.");
            continue;
        }

        // Idempotência: garante que, mesmo dentro da janela, só envia uma vez por dia
        if (!marcarComoEnviado($conn, $tipo)) {
            logMsg("[DEBUG] Lembrete '$tipo' já enviado hoje. A saltar duplicado.");
            continue;
        }

        logMsg("[LOG] Processando envios para $tipo (hora: $horaAgora, alvo: $horaEnvio)...");

        // Utilizadores aprovados
        $usuarios = [];
        $sqlU = "SELECT id, name, whatsapp, email FROM usuarios WHERE status = 'aprovado'";
        $resU = $conn->query($sqlU);
        if ($resU) {
            while ($row = $resU->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }
        logMsg("[LOG] Utilizadores encontrados: " . count($usuarios));

        // Inscritos para hoje (aceita '1' e 'Sim' para compatibilidade com registos antigos)
        $inscritos = [];
        $sqlI = "SELECT nome_completo FROM refeicoes WHERE data = '$dataHoje' AND $tipo IN ('1', 'Sim')";
        $resI = $conn->query($sqlI);
        if ($resI) {
            while ($row = $resI->fetch_assoc()) {
                $inscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8');
            }
        }
        logMsg("[LOG] Inscritos para $tipo: " . count($inscritos));

        $tipoLabel       = $tipo === 'almoco' ? 'almoço' : 'jantar';
        $assunto         = ucfirst($tipoLabel) . " de hoje — " . date('d/m/Y');
        $inscritosIds    = [];
        $naoInscritosIds = [];

        foreach ($usuarios as $user) {
            $nomeOriginal = trim($user['name']);
            $nomeComp     = mb_strtolower($nomeOriginal, 'UTF-8');
            $estaInscrito = in_array($nomeComp, $inscritos);

            if ($estaInscrito) {
                $msgWa   = "Olá, $nomeOriginal! Não te esqueças que estás inscrito para o $tipoLabel de hoje. Bom apetite!";
                $msgHtml = "Olá, <strong>$nomeOriginal</strong>!<br><br>Não te esqueças que estás inscrito para o <strong>$tipoLabel</strong> de hoje.<br>Bom apetite! 🍽️";
                $inscritosIds[] = $user['id'];
            } else {
                $msgWa   = "Olá, $nomeOriginal. Informamos que não te inscreveste para o $tipoLabel de hoje.";
                $msgHtml = "Olá, <strong>$nomeOriginal</strong>.<br><br>Informamos que não te inscreveste para o <strong>$tipoLabel</strong> de hoje.";
                $naoInscritosIds[] = $user['id'];
            }

            if (!empty($user['whatsapp'])) {
                $ok     = sendWhatsApp($user['whatsapp'], $msgWa);
                $status = $ok ? "OK" : "FALHA";
                logMsg("[$status] WA $tipo: $nomeOriginal");
            }

            if (!empty($user['email'])) {
                $ok     = sendEmail($user['email'], $assunto, $msgHtml, true);
                $status = $ok ? "OK" : "FALHA";
                logMsg("[$status] Email $tipo: $nomeOriginal");
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

        logMsg("[Push $tipo] Inscritos: " . count($inscritosIds) . " | Não inscritos: " . count($naoInscritosIds));
    }
}

// ---------------------------------------------------------------------------
// Notificações de atividades pessoais (próximos 30 minutos)
// ---------------------------------------------------------------------------
function notificarAtividades() {
    global $conn;

    $hoje    = date('Y-m-d');
    $horaMin = date('H:i:s', strtotime('-10 minutes'));
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
    $stmt->bind_param("sss", $hoje, $horaMin, $horaMax);
    $stmt->execute();
    $atividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    if (empty($atividades)) {
        logMsg("[Atividades] Nenhuma atividade para notificar agora.");
        return;
    }

    foreach ($atividades as $atv) {
        $hora   = substr($atv['hora_inicio'], 0, 5);
        $titulo = ucfirst(mb_strtolower($atv['nome_atividade'], 'UTF-8'));
        $nome   = trim($atv['name']);

        $msgWa   = "Olá, $nome! $titulo começa às $hora. Não te esqueças!";
        $msgHtml = "Olá, <strong>$nome</strong>!<br><br><strong>$titulo</strong> começa às <strong>$hora</strong>. Não te esqueças!";
        $assunto = "Lembrete — $titulo às $hora";

        if (!empty($atv['whatsapp'])) {
            $ok = sendWhatsApp($atv['whatsapp'], $msgWa);
            logMsg("[Atividade WA " . ($ok ? "OK" : "FALHA") . "] User {$atv['user_id']}: $titulo às $hora");
        }

        if (!empty($atv['email'])) {
            $ok = sendEmail($atv['email'], $assunto, $msgHtml, true);
            logMsg("[Atividade Email " . ($ok ? "OK" : "FALHA") . "] User {$atv['user_id']}: $titulo às $hora");
        }

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

        logMsg("[Atividade Push OK] User {$atv['user_id']}: $titulo às $hora");
    }
}

// ---------------------------------------------------------------------------
// Aniversários (natalício e sacerdotal) — aviso a toda a comunidade
// ---------------------------------------------------------------------------
function criarTabelaAniversarioAvisos($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS aniversario_avisos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        ano INT NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_ano_tipo (user_id, ano, tipo)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Verifica se já foi enviado um aviso deste tipo para este utilizador este ano.
 * Regista atomicamente para evitar duplicados em execuções simultâneas.
 */
function marcarAniversarioAvisado($conn, $userId, $tipo) {
    $ano = (int) date('Y');
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO aniversario_avisos (user_id, ano, tipo) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $userId, $ano, $tipo);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function notificarAniversarios() {
    global $conn;

    criarTabelaAniversarioAvisos($conn);

    $hojeMesDia = date('m-d');

    $sql = "SELECT id, name, data_aniversario, data_aniversario_sacerdotal
            FROM usuarios
            WHERE status = 'aprovado'
              AND (DATE_FORMAT(data_aniversario, '%m-%d') = ?
                   OR DATE_FORMAT(data_aniversario_sacerdotal, '%m-%d') = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $hojeMesDia, $hojeMesDia);
    $stmt->execute();
    $aniversariantes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($aniversariantes)) {
        logMsg("[Aniversários] Nenhum aniversariante hoje.");
        return;
    }

    // Determina, para cada aniversariante, quais tipos ('natalicio'/'sacerdotal')
    // fazem hoje e ainda não foram avisados este ano.
    $paraAvisar = [];
    foreach ($aniversariantes as $u) {
        $tipos = [];
        if ($u['data_aniversario'] && date('m-d', strtotime($u['data_aniversario'])) === $hojeMesDia) {
            $tipos[] = 'natalicio';
        }
        if ($u['data_aniversario_sacerdotal'] && date('m-d', strtotime($u['data_aniversario_sacerdotal'])) === $hojeMesDia) {
            $tipos[] = 'sacerdotal';
        }
        foreach ($tipos as $tipo) {
            if (marcarAniversarioAvisado($conn, (int) $u['id'], $tipo)) {
                $paraAvisar[] = ['nome' => trim($u['name']), 'tipo' => $tipo];
            }
        }
    }

    if (empty($paraAvisar)) {
        logMsg("[Aniversários] Aniversariante(s) de hoje já tinham sido avisados este ano.");
        return;
    }

    // Utilizadores aprovados (destinatários do aviso — toda a comunidade)
    $usuarios = [];
    $res = $conn->query("SELECT name, whatsapp, email FROM usuarios WHERE status = 'aprovado'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }

    foreach ($paraAvisar as $av) {
        $tipoLabel = $av['tipo'] === 'natalicio' ? 'Natalício' : 'Sacerdotal';
        $msg       = "Hoje é aniversário {$tipoLabel} de {$av['nome']}, vamos parabenizá-lo!";
        $assunto   = "Aniversário {$tipoLabel} de {$av['nome']}";

        foreach ($usuarios as $destinatario) {
            $nomeDest = trim($destinatario['name']);

            if (!empty($destinatario['whatsapp'])) {
                $ok = sendWhatsApp($destinatario['whatsapp'], $msg);
                logMsg("[Aniversário WA " . ($ok ? "OK" : "FALHA") . "] $tipoLabel {$av['nome']} -> $nomeDest");
            }

            if (!empty($destinatario['email'])) {
                $bodyHtml = "<p>$msg</p>";
                $ok = sendEmail($destinatario['email'], $assunto, $bodyHtml, true);
                logMsg("[Aniversário Email " . ($ok ? "OK" : "FALHA") . "] $tipoLabel {$av['nome']} -> $nomeDest");
            }

            usleep(200000);
        }

        sendPushNotification(
            $conn,
            "Aniversário {$tipoLabel}",
            $msg,
            '/',
            [],
            'psn-aniversario',
            7200,
            'high'
        );

        logMsg("[Aniversário Push] Enviado a todos os subscritores: $tipoLabel {$av['nome']}");
    }
}

// ---------------------------------------------------------------------------
// Lembrete de mensagens por ler há mais de 24h (uma vez por mensagem/destinatário)
// ---------------------------------------------------------------------------
function criarTabelaMensagemLembretes($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS mensagem_lembretes (
        mensagem_id INT NOT NULL,
        utilizador_id INT NOT NULL,
        enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (mensagem_id, utilizador_id)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Verifica se já foi enviado lembrete desta mensagem a este utilizador.
 * Regista atomicamente para evitar duplicados em execuções simultâneas.
 */
function marcarLembreteMensagemEnviado($conn, $mensagemId, $userId) {
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO mensagem_lembretes (mensagem_id, utilizador_id) VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $mensagemId, $userId);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function lembrarMensagensNaoLidas() {
    global $conn;

    criarTabelaMensagemLembretes($conn);

    $link = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/mensagens';

    $sql = "SELECT m.id, m.corpo, m.destinatario_id, m.remetente_id, u.name AS remetente_nome
            FROM mensagens m
            JOIN usuarios u ON u.id = m.remetente_id
            WHERE m.created_at <= (NOW() - INTERVAL 24 HOUR)";
    $res = $conn->query($sql);
    $mensagens = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    if (empty($mensagens)) {
        logMsg("[Mensagens] Nenhuma mensagem com mais de 24h a verificar.");
        return;
    }

    // Utilizadores aprovados, indexados por id (para resolver mensagens "para todos")
    $todosUsuarios = [];
    $resU = $conn->query("SELECT id, name, whatsapp, email FROM usuarios WHERE status = 'aprovado'");
    if ($resU) {
        while ($row = $resU->fetch_assoc()) {
            $todosUsuarios[(int) $row['id']] = $row;
        }
    }

    $enviados = 0;

    foreach ($mensagens as $msg) {
        $mensagemId    = (int) $msg['id'];
        $remetenteId   = (int) $msg['remetente_id'];
        $remetenteNome = trim($msg['remetente_nome']);
        $corpoResumo   = mb_strlen($msg['corpo']) > 100 ? mb_substr($msg['corpo'], 0, 97) . '…' : $msg['corpo'];

        $destinatarioIds = $msg['destinatario_id'] !== null
            ? [(int) $msg['destinatario_id']]
            : array_filter(array_keys($todosUsuarios), fn($id) => $id !== $remetenteId);

        foreach ($destinatarioIds as $destId) {
            if ($destId === $remetenteId || !isset($todosUsuarios[$destId])) continue;

            $lidaStmt = $conn->prepare("SELECT 1 FROM mensagem_leituras WHERE mensagem_id = ? AND utilizador_id = ?");
            $lidaStmt->bind_param("ii", $mensagemId, $destId);
            $lidaStmt->execute();
            $jaLida = $lidaStmt->get_result()->num_rows > 0;
            $lidaStmt->close();
            if ($jaLida) continue;

            // Marca atomicamente — se já tinha sido lembrado, salta
            if (!marcarLembreteMensagemEnviado($conn, $mensagemId, $destId)) continue;

            $destinatario = $todosUsuarios[$destId];
            $nomeDest     = trim($destinatario['name']);

            $msgWa    = "Olá, $nomeDest! Ainda não leste a mensagem que $remetenteNome te enviou há mais de 24 horas: \"$corpoResumo\"\n\nVê aqui: $link";
            $bodyHtml = "Olá, <strong>$nomeDest</strong>!<br><br>Ainda não leste a mensagem que <strong>$remetenteNome</strong> te enviou há mais de 24 horas:<br><em>\"" . htmlspecialchars($corpoResumo) . "\"</em><br><br><a href='$link'>Vê a mensagem</a>";

            if (!empty($destinatario['whatsapp'])) {
                $ok = sendWhatsApp($destinatario['whatsapp'], $msgWa);
                logMsg("[Msg lembrete WA " . ($ok ? "OK" : "FALHA") . "] Msg $mensagemId -> $nomeDest");
            }

            if (!empty($destinatario['email'])) {
                $ok = sendEmail($destinatario['email'], "Mensagem por ler de $remetenteNome", $bodyHtml, true);
                logMsg("[Msg lembrete Email " . ($ok ? "OK" : "FALHA") . "] Msg $mensagemId -> $nomeDest");
            }

            sendPushNotification(
                $conn,
                'Mensagem por ler',
                "$remetenteNome enviou-te uma mensagem há mais de 24h que ainda não leste.",
                '/mensagens',
                [$destId],
                'psn-mensagem',
                3600,
                'high'
            );

            $enviados++;
            usleep(150000);
        }
    }

    logMsg("[Mensagens] Lembretes de mensagens não lidas enviados: $enviados");
}

// ---------------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------------
enviarLembreteInscricao();
enviarLembretes();
notificarAtividades();
notificarAniversarios();
lembrarMensagensNaoLidas();

logMsg("--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n");
