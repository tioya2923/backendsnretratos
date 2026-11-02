<?php
// TEMPORÁRIO — autorizado pelo utilizador (2026-11-02/03): convocatória
// para testadores da app Android (push + mensagem na app, sem email).
// Corre uma única vez e é apagado logo a seguir.
date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';

if (!hash_equals('convocatoria-testadores-20261103', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS lembretes_enviados (
    id INT AUTO_INCREMENT PRIMARY KEY, data DATE NOT NULL, tipo VARCHAR(30) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_data_tipo (data, tipo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$hoje = date('Y-m-d'); $tipo = 'convocatoria_testes';
$st = $conn->prepare("INSERT IGNORE INTO lembretes_enviados (data, tipo) VALUES (?, ?)");
$st->bind_param("ss", $hoje, $tipo);
$st->execute();
if ($st->affected_rows === 0) { echo "Já enviada hoje. A sair.\n"; exit; }

$corpo = "Ajude-nos a testar a app no Android\n\n"
  . "Se tem um telemóvel Android e uma conta Gmail, ajude-nos a testar a app SN Refeições antes de ficar na Play Store:\n\n"
  . "1. Entre no grupo com o seu Gmail: https://groups.google.com/g/sn-refeicoes-testadores\n"
  . "2. Abra este link e toque em \"Tornar-me testador\": https://play.google.com/apps/testing/pt.paroquiasaonicolau.sn\n"
  . "3. Instale a app pela Play Store (a ligação aparece na mesma página).\n\n"
  . "Precisamos de pelo menos 12 pessoas a manter a app instalada durante 14 dias. Obrigado!";

try {
    $ins = $conn->prepare("INSERT INTO mensagens (remetente_id, destinatario_id, corpo) VALUES (NULL, NULL, ?)");
    $ins->bind_param("s", $corpo);
    $ins->execute();
    echo "Mensagem na app: OK (id " . $conn->insert_id . ")\n";
} catch (Throwable $e) {
    echo "Mensagem na app: FALHA - " . $e->getMessage() . "\n";
}

sendPushNotification(
    $conn,
    'Ajude-nos a testar a app no Android',
    'Tem Android e Gmail? Abra a mensagem na app para ver os 3 passos para testar a SN Refeições.',
    '/mensagens',
    [],
    'psn-teste-android',
    86400,
    'high'
);
echo "Push: enviado a todos os subscritores\n";
