<?php
/**
 * Script de Cron: Envio de Lembretes de Refeições e Inscrição
 * via Email (API da Brevo) e Push Notification.
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/push_utils.php';
require_once __DIR__ . '/presenca_utils.php';

// Só aceita chamadas do disparador de cron (evita que qualquer pessoa na
// internet dispare envios em massa de email a todos os utilizadores).
//
// Dois segredos válidos, de propósito separados:
// - CRON_SECRET (variável de ambiente, .env) — usado pelo GitHub Actions.
// - EXTERNAL_CRON_TOKEN (fixo aqui no código, não no .env) — usado por um
//   disparador externo (cron-job.org), para não depender de acesso ao
//   cPanel para configurar. O GitHub Actions sozinho não é fiável (já se
//   viram gaps de várias horas, incluindo uma incidência confirmada na
//   própria página de estado do GitHub) — isto dá um segundo disparador,
//   independente, que corre à hora certa. Se precisar de revogar só este,
//   basta mudar esta constante e fazer deploy — não mexe no CRON_SECRET.
define('EXTERNAL_CRON_TOKEN', '365a3a7f1da4ea2e13c13f35ecb586f2a859f2b25e0e7595');

$cronSecret      = getenv('CRON_SECRET');
$recebidoCron    = $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
$recebidoExterno = $_SERVER['HTTP_X_EXTERNAL_CRON_TOKEN'] ?? '';

if (php_sapi_name() !== 'cli') {
    $okCron    = $cronSecret && hash_equals($cronSecret, $recebidoCron);
    $okExterno = hash_equals(EXTERNAL_CRON_TOKEN, $recebidoExterno);

    if (!$okCron && !$okExterno) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }
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

    // Só há um limite "cedo demais" (35 min antes do alvo) — do lado
    // tardio, dispara a qualquer hora desse mesmo dia (o próprio filtro do
    // dia da semana, $diaSemana, já garante que não passa para o dia
    // seguinte). Antes, uma janela ±35 min simétrica podia perder o
    // lembrete todo se o GitHub Actions falhasse a cadência (já se viram
    // gaps de quase 2h) sem nenhum disparo a cair lá dentro; agora, tarde
    // é sempre melhor do que não sair — marcarComoEnviado() garante um
    // único envio por dia de qualquer forma.
    $janelas = [
        ['dia' => 1, 'hora' => '13:10', 'tipo' => 'inscricao_segunda'],
        ['dia' => 4, 'hora' => '21:30', 'tipo' => 'inscricao_quinta'],
    ];

    $tipoAtivo = null;
    foreach ($janelas as $j) {
        if ($diaSemana !== $j['dia']) continue;
        $alvo = strtotime('today ' . $j['hora']);
        if ($agora >= ($alvo - 2100)) {
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
        $resSemana = stmt_get_result($stmtSemana);
        while ($row = $resSemana->fetch_assoc()) {
            $jaInscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8');
        }
        $stmtSemana->close();
        logMsg("[Inscrição] Semana $segunda a $domingo — já inscritos: " . count($jaInscritos));
    }

    $sql = "SELECT id, name, email FROM usuarios WHERE status = 'aprovado'";
    $res = $conn->query($sql);

    $destinatarioIds = [];

    if ($res) {
        while ($user = $res->fetch_assoc()) {
            $nome = trim($user['name']);

            if ($tipoAtivo === 'inscricao_quinta' && in_array(mb_strtolower($nome, 'UTF-8'), $jaInscritos)) {
                continue; // já se inscreveu esta semana, não repetir o lembrete
            }

            if ($tipoAtivo === 'inscricao_quinta') {
                $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>
                             Estás a receber este lembrete de novo porque na segunda-feira já te avisámos e ainda não fizeste a tua inscrição para as próximas refeições.<br>
                             <a href='$link'>Clica aqui para te inscrever</a>";
            } else {
                $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>
                             Recorda-te de fazer a tua inscrição para as próximas refeições.<br>
                             <a href='$link'>Clica aqui para te inscrever</a>";
            }

            $bodyHtml .= $rodapeHtml;

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

        // O GitHub Actions não garante a cadência de 5 em 5 min do cron.yml
        // — já se viram falhas de 20 min a mais de 2h entre disparos (sem
        // cron nativo no servidor a fazer de rede de segurança, ficamos
        // 100% dependentes do agendamento do GitHub, que é sempre "melhor
        // esforço"). Uma janela simétrica (ex.: ±35 min) já falhou duas
        // vezes por causa disto: se NENHUM disparo caísse dentro da
        // janela, o lembrete desse dia perdia-se para sempre. Agora só há
        // um limite "cedo demais" (35 min antes do envio); do lado
        // tardio, dispara até 5h depois da própria refeição — folga
        // generosa para os gaps já observados — atrasado é sempre melhor
        // do que não sair, e marcarComoEnviado() abaixo continua a
        // garantir um único envio por dia.
        $horaRefeicaoTs = strtotime("today $horaRefeicao");
        $cedoDemais  = $agora < ($horaEnvioTs - 2100);
        $tardeDemais = $agora > ($horaRefeicaoTs + 5 * 3600);
        if ($cedoDemais || $tardeDemais) {
            logMsg("[DEBUG] Hora actual ($horaAgora) fora da janela de envio (a partir de " . date('H:i', $horaEnvioTs - 2100) . ", até 5h depois da refeição) para $tipo. A saltar.");
            continue;
        }

        // Idempotência: garante que, mesmo dentro da janela, só envia uma vez por dia
        if (!marcarComoEnviado($conn, $tipo)) {
            logMsg("[DEBUG] Lembrete '$tipo' já enviado hoje. A saltar duplicado.");
            continue;
        }

        logMsg("[LOG] Processando envios para $tipo (hora: $horaAgora, alvo: $horaEnvio)...");

        // Utilizadores aprovados (só id/name — este lembrete é só push, não email)
        $usuarios = [];
        $sqlU = "SELECT id, name FROM usuarios WHERE status = 'aprovado'";
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

        // Lembrete "de hoje" (almoço/jantar) é só dentro da app — não por email.
        // O email fica reservado ao lembrete semanal de inscrição
        // (enviarLembreteInscricao), que é o que efetivamente leva alguém a
        // agir antes do prazo; isto aqui é só um aviso do próprio dia.
        $tipoLabel       = $tipo === 'almoco' ? 'almoço' : 'jantar';
        $inscritosIds    = [];
        $naoInscritosIds = [];

        foreach ($usuarios as $user) {
            $nomeOriginal = trim($user['name']);
            $nomeComp     = mb_strtolower($nomeOriginal, 'UTF-8');
            $estaInscrito = in_array($nomeComp, $inscritos);

            if ($estaInscrito) {
                $inscritosIds[] = $user['id'];
            } else {
                $naoInscritosIds[] = $user['id'];
            }
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

    // O limite de baixo (-5h, era -15min) existe para recuperar atividades
    // "perdidas" por falhas do cron do GitHub Actions (já se viram gaps de
    // mais de 2h sem nenhum disparo, e sem cron nativo no servidor como
    // rede de segurança, ficamos 100% dependentes do agendamento do
    // GitHub) — como esta janela desliza com o "agora" a cada execução,
    // uma atividade cujo horário já tenha ficado para trás do limite
    // antigo nunca mais voltava a entrar na consulta, e o aviso
    // perdia-se de vez. ultima_notificacao IS NULL continua a garantir
    // um único aviso por atividade, mesmo com a janela maior.
    //
    // Comparação por datetime completo (não só TIME(hora_inicio)) — perto
    // da meia-noite, "-5 horas" cai no dia anterior, e um BETWEEN só com
    // horas (sem a data) fica invertido (min > max) e nunca dá match
    // nenhum nesse período do dia.
    $dtMin = date('Y-m-d H:i:s', strtotime('-5 hours'));
    $dtMax = date('Y-m-d H:i:s', strtotime('+40 minutes'));

    $stmt = $conn->prepare("
        SELECT a.id, a.user_id, a.hora_inicio,
               COALESCE(NULLIF(a.titulo,''), a.tipo) AS nome_atividade,
               u.name, u.email
        FROM atividades_usuario a
        JOIN usuarios u ON u.id = a.user_id
        WHERE a.ativo = 1
          AND CONCAT(a.data_atividade, ' ', a.hora_inicio) BETWEEN ? AND ?
          AND a.ultima_notificacao IS NULL
    ");
    $stmt->bind_param("ss", $dtMin, $dtMax);
    $stmt->execute();
    $atividades = stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    if (empty($atividades)) {
        logMsg("[Atividades] Nenhuma atividade para notificar agora.");
        return;
    }

    foreach ($atividades as $atv) {
        // Reivindica esta atividade ANTES de enviar, não depois — desde
        // que passámos a ter dois disparadores independentes (GitHub
        // Actions + cron-job.org, ver EXTERNAL_CRON_TOKEN acima), os dois
        // podem correr dentro da mesma janela de 5 min e fazer o SELECT
        // de cima antes de qualquer um marcar ultima_notificacao — sem
        // esta reivindicação atómica, ambos enviavam a mesma notificação
        // (push + email) à mesma pessoa. O WHERE ultima_notificacao IS
        // NULL garante que só um dos dois processos ganha.
        $upd = $conn->prepare("UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ? AND ultima_notificacao IS NULL");
        $upd->bind_param("i", $atv['id']);
        $upd->execute();
        if ($upd->affected_rows === 0) {
            continue; // outro processo já reivindicou esta atividade entretanto
        }

        $hora   = substr($atv['hora_inicio'], 0, 5);
        $titulo = ucfirst(mb_strtolower($atv['nome_atividade'], 'UTF-8'));
        $nome   = trim($atv['name']);

        $msgHtml = "Olá, <strong>$nome</strong>!<br><br><strong>$titulo</strong> começa às <strong>$hora</strong>. Não te esqueças!";
        $assunto = "Lembrete — $titulo às $hora";

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
    $aniversariantes = stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
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
    $res = $conn->query("SELECT name, email FROM usuarios WHERE status = 'aprovado'");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $usuarios[] = $row;
        }
    }

    foreach ($paraAvisar as $av) {
        $tipoLabel = $av['tipo'] === 'natalicio' ? 'Natalício' : 'Sacerdotal';
        $titulo    = "Aniversariante do Dia: {$av['nome']}";
        $assunto   = $titulo;

        foreach ($usuarios as $destinatario) {
            $nomeDest = trim($destinatario['name']);

            if (!empty($destinatario['email'])) {
                $bodyHtml = "<p><strong>$titulo</strong></p><p>Hoje é aniversário {$tipoLabel} de <strong>{$av['nome']}</strong>. Vamos todos parabenizá-lo!</p>";
                $ok = sendEmail($destinatario['email'], $assunto, $bodyHtml, true);
                logMsg("[Aniversário Email " . ($ok ? "OK" : "FALHA") . "] $tipoLabel {$av['nome']} -> $nomeDest");
            }

            usleep(200000);
        }

        // Mensagem dentro da app (visível para todos, sem remetente humano)
        // — corpo é texto simples (MensagensPage.jsx mostra-o direto, sem
        // HTML). Antes usava uma variável $msg nunca definida (bind a NULL
        // — `corpo` é NOT NULL, o INSERT falhava sempre, em silêncio).
        $corpoMsg = "Hoje é aniversário $tipoLabel de {$av['nome']}. Vamos todos parabenizá-lo!";
        $insertMsg = $conn->prepare(
            "INSERT INTO mensagens (remetente_id, destinatario_id, corpo) VALUES (NULL, NULL, ?)"
        );
        $insertMsg->bind_param("s", $corpoMsg);
        $insertMsg->execute();
        logMsg("[Aniversário App] Mensagem criada: $tipoLabel {$av['nome']}");

        sendPushNotification(
            $conn,
            $titulo,
            "Hoje é aniversário {$tipoLabel} de {$av['nome']}. Vamos todos parabenizá-lo!",
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
    $resU = $conn->query("SELECT id, name, email FROM usuarios WHERE status = 'aprovado'");
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
            $jaLida = stmt_get_result($lidaStmt)->num_rows > 0;
            $lidaStmt->close();
            if ($jaLida) continue;

            // Marca atomicamente — se já tinha sido lembrado, salta
            if (!marcarLembreteMensagemEnviado($conn, $mensagemId, $destId)) continue;

            $destinatario = $todosUsuarios[$destId];
            $nomeDest     = trim($destinatario['name']);

            $bodyHtml = "Olá, <strong>$nomeDest</strong>!<br><br>Ainda não leste a mensagem que <strong>$remetenteNome</strong> te enviou há mais de 24 horas:<br><em>\"" . htmlspecialchars($corpoResumo) . "\"</em><br><br><a href='$link'>Vê a mensagem</a>";

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
// Relatório quinzenal de inscrições, para os administradores (a cada 15 dias)
// ---------------------------------------------------------------------------
function criarTabelaRelatorioQuinzenal($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS relatorio_quinzenal_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        periodo VARCHAR(10) NOT NULL,
        enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_periodo (periodo)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Migração: a versão antiga desta tabela não tinha 'periodo' (o envio
    // era "15 dias depois do último", sem data fixa nenhuma) e podia ter
    // várias linhas. Todas ficam com periodo='' depois do ADD COLUMN — se
    // fossem 2 ou mais, o ADD UNIQUE KEY a seguir falhava (chave
    // duplicada) e, como este projeto nunca desliga o modo de exceções do
    // mysqli (o padrão do PHP 8.1), isso rebentava o script do cron a
    // meio, antes até de processar a fila de emails. As linhas antigas
    // não correspondem a nenhum período novo (só o código novo grava um
    // periodo real, nunca ''), por isso apagam-se com segurança — não é
    // histórico útil, é só o resultado do mecanismo antigo já substituído.
    $temColuna = $conn->query("SHOW COLUMNS FROM relatorio_quinzenal_log LIKE 'periodo'")->num_rows > 0;
    if (!$temColuna) {
        $conn->query("ALTER TABLE relatorio_quinzenal_log ADD COLUMN periodo VARCHAR(10) NOT NULL DEFAULT '' AFTER id");
        $conn->query("DELETE FROM relatorio_quinzenal_log WHERE periodo = ''");
        $conn->query("ALTER TABLE relatorio_quinzenal_log ADD UNIQUE KEY unique_periodo (periodo)");
    }
}

function enviarRelatorioQuinzenal() {
    global $conn;

    criarTabelaRelatorioQuinzenal($conn);

    // Calendário fixo, por pedido: um relatório a cada 15 dias, sempre a
    // começar no dia 1 do mês — ou seja, sai no dia 1 (cobrindo a segunda
    // metade do mês anterior) e no dia 16 (cobrindo a primeira metade do
    // mês atual). Janela de alguns dias de folga (não só o próprio dia 1
    // ou 16) para aguentar falhas do cron — o "periodo" é que garante um
    // único envio por metade de mês, não a data exata em que corre.
    $diaMes = (int) date('j');

    if ($diaMes >= 1 && $diaMes <= 3) {
        // Período A: dia 16 a fim do mês ANTERIOR
        $fim     = date('Y-m-t', strtotime('first day of last month'));
        $inicio  = date('Y-m-16', strtotime($fim));
        $periodo = date('Y-m', strtotime($fim)) . '-A';
    } elseif ($diaMes >= 16 && $diaMes <= 18) {
        // Período B: dia 1 a 15 do mês ATUAL
        $inicio  = date('Y-m-01');
        $fim     = date('Y-m-15');
        $periodo = date('Y-m') . '-B';
    } else {
        return;
    }

    // Idempotência: garante um único envio por período (metade de mês),
    // mesmo que o cron dispare várias vezes dentro da janela de folga.
    $stmtLog = $conn->prepare("INSERT IGNORE INTO relatorio_quinzenal_log (periodo) VALUES (?)");
    $stmtLog->bind_param("s", $periodo);
    $stmtLog->execute();
    if ($stmtLog->affected_rows === 0) {
        logMsg("[Relatório Quinzenal] Período '$periodo' já enviado. A saltar.");
        return;
    }

    $periodoFormatado = date('d/m/Y', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));

    logMsg("[LOG] Iniciando relatório quinzenal de inscrições ($periodoFormatado)...");

    // Todas as inscrições do período, para cruzar com as confirmações de
    // presença — cada linha da tabela refeicoes pode cobrir várias
    // variantes ao mesmo tempo (ex.: almoço normal + takeaway), por isso
    // trata-se cada uma separadamente.
    $stmtR = $conn->prepare("
        SELECT id, nome_completo, data, almoco, almoco_mais_cedo, almoco_mais_tarde,
               jantar, jantar_mais_cedo, jantar_mais_tarde, levar_refeicao
        FROM refeicoes
        WHERE data BETWEEN ? AND ?
        ORDER BY data, nome_completo
    ");
    $stmtR->bind_param("ss", $inicio, $fim);
    $stmtR->execute();
    $refeicoesPeriodo = stmt_get_result($stmtR)->fetch_all(MYSQLI_ASSOC);
    $stmtR->close();

    // Confirmações já feitas no período — carregadas todas de uma vez
    // (refeicao_id|tipo => true) para consulta rápida no ciclo abaixo.
    $confirmadosSet = [];
    $stmtC = $conn->prepare("
        SELECT c.refeicao_id, c.tipo
        FROM confirmacoes_presenca c
        JOIN refeicoes r ON r.id = c.refeicao_id
        WHERE r.data BETWEEN ? AND ?
    ");
    $stmtC->bind_param("ss", $inicio, $fim);
    $stmtC->execute();
    $resC = stmt_get_result($stmtC);
    while ($row = $resC->fetch_assoc()) {
        $confirmadosSet[$row['refeicao_id'] . '|' . $row['tipo']] = true;
    }
    $stmtC->close();

    // Cada variante é tratada como o seu próprio "tipo" de confirmação —
    // o nome de cada uma é exatamente o nome da coluna correspondente em
    // `refeicoes` (mesma convenção usada em confirmar_presenca.php).
    $tiposPossiveis = [
        'almoco'            => 'Almoço',
        'almoco_mais_cedo'  => 'Almoço mais cedo',
        'almoco_mais_tarde' => 'Almoço mais tarde',
        'jantar'            => 'Jantar',
        'jantar_mais_cedo'  => 'Jantar mais cedo',
        'jantar_mais_tarde' => 'Jantar mais tarde',
        'levar_refeicao'    => 'Takeaway',
    ];

    $confirmaram       = [];
    $faltaram          = [];
    $nomesComInscricao = [];

    foreach ($refeicoesPeriodo as $r) {
        $nome = trim($r['nome_completo']);
        $nomesComInscricao[mb_strtolower($nome, 'UTF-8')] = true;

        foreach ($tiposPossiveis as $tipo => $tipoLabel) {
            if (!$r[$tipo]) continue;

            // Só conta como "confirmou" ou "faltou" depois de a janela
            // fechar — uma refeição de hoje ainda por acontecer (ou um
            // takeaway cuja véspera ainda não chegou) fica de fora (não se
            // pode dizer que alguém faltou a algo que ainda não teve
            // oportunidade de confirmar).
            if (!janelaJaFechou($tipo, $r['data'])) continue;

            $dataFormatada = date('d/m/Y', strtotime($r['data']));
            $entrada       = "$nome — $dataFormatada — $tipoLabel";

            if (isset($confirmadosSet["{$r['id']}|$tipo"])) {
                $confirmaram[] = $entrada;
            } else {
                $faltaram[] = $entrada;
            }
        }
    }

    // Não se inscreveram nenhuma vez no período (nenhuma linha em refeicoes)
    $naoInscritos = [];
    $resU = $conn->query("SELECT name FROM usuarios WHERE status = 'aprovado' ORDER BY name");
    while ($row = $resU->fetch_assoc()) {
        $nome = trim($row['name']);
        if (!isset($nomesComInscricao[mb_strtolower($nome, 'UTF-8')])) {
            $naoInscritos[] = $nome;
        }
    }

    $paraLista = function (array $itens) {
        if (empty($itens)) return '<p><em>Ninguém.</em></p>';
        $li = array_map(fn($n) => '<li>' . htmlspecialchars($n) . '</li>', $itens);
        return '<ul>' . implode('', $li) . '</ul>';
    };

    $body = "
        <h2>Relatório quinzenal de inscrições</h2>
        <p>Período: <strong>$periodoFormatado</strong></p>
        <h3>Confirmaram presença (" . count($confirmaram) . ")</h3>
        " . $paraLista($confirmaram) . "
        <h3>Faltaram — inscreveram-se mas não confirmaram presença (" . count($faltaram) . ")</h3>
        " . $paraLista($faltaram) . "
        <h3>Não se inscreveram em nenhuma refeição (" . count($naoInscritos) . ")</h3>
        " . $paraLista($naoInscritos) . "
    ";

    // enfileirarEmail(), não sendEmail() direto — o envio síncrono por SMTP
    // falha aqui (porta bloqueada na PTisp, mesma causa já corrigida no
    // registo). Fica em fila e o processarEmailsPendentes() mais abaixo
    // (já corre a cada ciclo deste script) trata da entrega, com retries.
    $resAdmins = $conn->query("SELECT email_admin FROM admins");
    $enfileirados = 0;
    while ($admin = $resAdmins->fetch_assoc()) {
        if (empty($admin['email_admin'])) continue;
        enfileirarEmail($conn, $admin['email_admin'], "Relatório quinzenal de inscrições ($periodoFormatado)", $body, true);
        $enfileirados++;
    }

    logMsg("[Relatório Quinzenal] Enfileirado para $enfileirados administrador(es). Confirmaram: " . count($confirmaram) . " | Faltaram: " . count($faltaram) . " | Não se inscreveram: " . count($naoInscritos));
}

// ---------------------------------------------------------------------------
// Processa a fila de emails (ver enfileirarEmail() em email_utils.php) —
// usada por endpoints como registar.php que não podem esperar pelo SMTP
// (bloqueado na PTisp) antes de responder ao pedido que os disparou.
// ---------------------------------------------------------------------------
function processarEmailsPendentes() {
    global $conn;

    criarTabelaEmailsPendentes($conn);

    // Limite por disparo: o cron corre a cada 5 min, e cada tentativa
    // falhada pode demorar até ao Timeout do SMTP (10s) — um lote grande
    // arriscava-se a ainda estar a processar quando o próximo disparo
    // chegasse. Desiste ao fim de 5 tentativas falhadas (fica só em log).
    $res = $conn->query("
        SELECT id, destinatario, assunto, corpo, is_html, tentativas
        FROM emails_pendentes
        WHERE enviado_em IS NULL AND tentativas < 5
        ORDER BY id
        LIMIT 10
    ");
    $emails = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    if (empty($emails)) return;

    $enviados = 0;
    $falhas   = [];
    foreach ($emails as $row) {
        // Reivindica este email ANTES de o enviar (incrementa já
        // tentativas), não depois — com dois disparadores independentes
        // (GitHub Actions + cron-job.org) a poderem correr dentro da
        // mesma janela de 5 min, ambos podiam fazer o SELECT de cima
        // antes de qualquer um gravar o resultado, e enviar o mesmo
        // email em duplicado (ex.: o relatório quinzenal aos admins,
        // duas vezes). "tentativas = ?" (o valor exato lido acima, não só
        // "< 5") é o que fecha mesmo a porta: assim que um processo grava
        // o incremento, o valor deixa de bater certo para o outro — um
        // simples "< 5" não servia, porque os dois liam o mesmo valor
        // antes de qualquer um escrever, e ambos continuavam a passar
        // nesse teste depois.
        $tentativasLidas = (int) $row['tentativas'];
        $claim = $conn->prepare("UPDATE emails_pendentes SET tentativas = tentativas + 1 WHERE id = ? AND enviado_em IS NULL AND tentativas = ?");
        $claim->bind_param("ii", $row['id'], $tentativasLidas);
        $claim->execute();
        $ganhou = $claim->affected_rows > 0;
        $claim->close();
        if (!$ganhou) {
            continue; // outro processo já reivindicou esta linha entretanto
        }

        $erro = null;
        $ok = sendEmail($row['destinatario'], $row['assunto'], $row['corpo'], (bool) $row['is_html'], $erro);
        if ($ok) {
            $upd = $conn->prepare("UPDATE emails_pendentes SET enviado_em = NOW() WHERE id = ?");
            $upd->bind_param("i", $row['id']);
            $upd->execute();
            $upd->close();
            $enviados++;
        } else {
            // Antes, a razão exata só ia para o error_log do servidor —
            // invisível no log do cron. Regista aqui também, para dar
            // visibilidade imediata sem precisar de acesso ao servidor.
            $falhas[] = "#{$row['id']} {$row['destinatario']}: $erro";
        }
    }
    if (!empty($falhas)) {
        logMsg("[Fila de Emails] Falhas nesta ronda:\n  - " . implode("\n  - ", $falhas));
    }

    logMsg("[Fila de Emails] Processados: " . count($emails) . " | Enviados: $enviados");
}

// ---------------------------------------------------------------------------
// Execução
// ---------------------------------------------------------------------------
enviarLembreteInscricao();
enviarLembretes();
notificarAtividades();
notificarAniversarios();
lembrarMensagensNaoLidas();
enviarRelatorioQuinzenal();
processarEmailsPendentes();

logMsg("--- Script finalizado em " . date('Y-m-d H:i:s') . " ---\n");
