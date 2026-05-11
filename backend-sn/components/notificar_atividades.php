<?php
/**
 * Cron: Notificações de atividades pessoais (executa a cada minuto)
 * Heroku Scheduler: configurar para correr a cada 10 minutos com ajuste de janela
 */
date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';

$conn->set_charset("utf8mb4");

$logfile = __DIR__ . '/notificar_atividades_cron.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($logfile, "--- Iniciado em $timestamp ---\n", FILE_APPEND);

// Janela: atividades que começam entre 9 e 11 minutos a partir de agora
// (±1 minuto de tolerância para execuções que não sejam ao segundo exato)
$horaMin  = date('H:i:s', strtotime('+9 minutes'));
$horaMax  = date('H:i:s', strtotime('+11 minutes'));
$hoje     = date('Y-m-d');

// DAYOFWEEK MySQL: 1=Dom, 2=Seg, ..., 7=Sab → subtrai 1 para obter 0-6
$diaSemana = (int)date('w'); // PHP date('w'): 0=Dom, 6=Sab — já no formato correto

$sql = "
    SELECT a.id, a.user_id, a.tipo, a.titulo, a.hora_inicio,
           COALESCE(NULLIF(a.titulo,''), a.tipo) AS nome_atividade
    FROM atividades_usuario a
    WHERE a.ativo = 1
      AND a.dia_semana = ?
      AND TIME(a.hora_inicio) BETWEEN ? AND ?
      AND (a.ultima_notificacao IS NULL OR DATE(a.ultima_notificacao) < ?)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $diaSemana, $horaMin, $horaMax, $hoje);
$stmt->execute();
$atividades = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($atividades)) {
    file_put_contents($logfile, "Nenhuma atividade para notificar.\n", FILE_APPEND);
    exit;
}

foreach ($atividades as $atv) {
    $hora   = substr($atv['hora_inicio'], 0, 5);
    $titulo = ucfirst(strtolower($atv['nome_atividade']));
    $body   = "$titulo começa às $hora. Não te esqueças!";

    sendPushNotification(
        $conn,
        "⏰ Lembrete — $titulo",
        $body,
        '/atividades',
        [$atv['user_id']]
    );

    // Marcar como notificado hoje para não reenviar
    $updStmt = $conn->prepare(
        "UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ?"
    );
    $updStmt->bind_param("i", $atv['id']);
    $updStmt->execute();

    file_put_contents($logfile, "[OK] User {$atv['user_id']}: {$titulo} às {$hora}\n", FILE_APPEND);
}

file_put_contents($logfile, "--- Concluído em " . date('Y-m-d H:i:s') . " ---\n\n", FILE_APPEND);
