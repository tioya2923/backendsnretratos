<?php
/**
 * Script de envio em massa — execução única via CLI.
 * Envia uma mensagem personalizada a todos os utilizadores aprovados
 * via WhatsApp, Email e Push Notification.
 *
 * Uso: php enviar_mensagem_todos.php "Texto da mensagem"
 */

date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/whatsapp_utils.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/push_utils.php';

$conn->set_charset("utf8mb4");

// Mensagem passada como argumento ou hardcoded
$texto = isset($argv[1]) ? trim($argv[1]) : 'Feliz Quinta-Feira, dia de S. Matias, Apóstolo!';
$assunto = $texto;

echo "\n=== ENVIO EM MASSA ===\n";
echo "Mensagem  : $texto\n";
echo "Iniciado  : " . date('Y-m-d H:i:s') . "\n";
echo "---------------------\n\n";

// Buscar todos os utilizadores aprovados
$sql = "SELECT id, name, email, whatsapp FROM usuarios WHERE status = 'aprovado'";
$res = $conn->query($sql);

$totalWa    = ['ok' => 0, 'falha' => 0, 'sem' => 0];
$totalEmail = ['ok' => 0, 'falha' => 0, 'sem' => 0];
$userIds    = [];

if (!$res) {
    echo "[ERRO] Query falhou: " . $conn->error . "\n";
    exit(1);
}

while ($user = $res->fetch_assoc()) {
    $nome = trim($user['name']);
    $userIds[] = $user['id'];

    echo "[$nome]\n";

    // --- WhatsApp ---
    if (!empty($user['whatsapp'])) {
        $msgWa = "Olá, $nome! $texto";
        $ok = sendWhatsApp($user['whatsapp'], $msgWa);
        if ($ok) {
            echo "  WA     : OK ({$user['whatsapp']})\n";
            $totalWa['ok']++;
        } else {
            echo "  WA     : FALHA ({$user['whatsapp']})\n";
            $totalWa['falha']++;
        }
    } else {
        echo "  WA     : sem número\n";
        $totalWa['sem']++;
    }

    // --- Email ---
    if (!empty($user['email'])) {
        $bodyHtml = "Olá, <strong>$nome</strong>!<br><br>$texto<br><br><small>Paróquia de São Nicolau</small>";
        $ok = sendEmail($user['email'], $assunto, $bodyHtml, true);
        if ($ok) {
            echo "  Email  : OK ({$user['email']})\n";
            $totalEmail['ok']++;
        } else {
            echo "  Email  : FALHA ({$user['email']})\n";
            $totalEmail['falha']++;
        }
    } else {
        echo "  Email  : sem endereço\n";
        $totalEmail['sem']++;
    }

    usleep(300000); // 0.3s entre utilizadores para não sobrecarregar
    echo "\n";
}

// --- Push para todos os subscritores ---
echo "Push: enviando para todos os subscritores...\n";
sendPushNotification(
    $conn,
    'Paróquia de São Nicolau',
    $texto,
    '/',
    [],          // array vazio = envia a todos
    'psn-mensagem',
    86400,       // TTL: 24h
    'high'
);
echo "Push: OK\n\n";

// --- Resumo ---
$total = $totalWa['ok'] + $totalWa['falha'] + $totalWa['sem'];
echo "=====================\n";
echo "RESUMO\n";
echo "  Utilizadores : $total\n";
echo "  WhatsApp     : {$totalWa['ok']} OK | {$totalWa['falha']} FALHA | {$totalWa['sem']} sem número\n";
echo "  Email        : {$totalEmail['ok']} OK | {$totalEmail['falha']} FALHA | {$totalEmail['sem']} sem email\n";
echo "  Push         : enviado para todos os subscritores\n";
echo "  Concluído    : " . date('Y-m-d H:i:s') . "\n";
echo "=====================\n";

$conn->close();
