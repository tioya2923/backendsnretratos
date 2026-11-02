<?php
// TEMPORÁRIO — diagnóstico: porque é que um push não chegou. Remove-se logo a seguir.
date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/push_utils.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

if (!hash_equals('push-diag-20261103', $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403); echo json_encode(['error' => 'acesso negado']); exit;
}
header('Content-Type: text/plain; charset=utf-8');
$conn->set_charset("utf8mb4");
$q = function ($sql) use ($conn) { return (int) $conn->query($sql)->fetch_row()[0]; };
echo "Subscrições push: ", $q("SELECT COUNT(*) FROM push_subscriptions"), "\n";
echo "Utilizadores com pelo menos 1 subscrição: ", $q("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions"), "\n";
echo "Aprovados: ", $q("SELECT COUNT(*) FROM usuarios WHERE status='aprovado'"), "\n";
$m = $conn->query("SELECT id, created_at FROM mensagens WHERE id >= 126 ORDER BY id");
while ($r = $m->fetch_assoc()) echo "Mensagem na app: id {$r['id']} criada {$r['created_at']}\n";

$st = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$e = 'mwenhondumba@gmail.com'; $st->bind_param("s", $e); $st->execute();
$u = stmt_get_result($st)->fetch_assoc();
if (!$u) { echo "Utilizador do teste não encontrado\n"; exit; }
$uid = (int) $u['id'];
$n = $q("SELECT COUNT(*) FROM push_subscriptions WHERE user_id = $uid");
echo "Subscrições deste utilizador (id $uid): $n\n";
if ($n === 0) { echo "=> Este utilizador NÃO tem subscrição push (nunca ativou ou foi removida).\n"; exit; }

// teste só para este utilizador, com relatório de entrega
$web = new WebPush(['VAPID' => [
    'subject' => getenv('VAPID_SUBJECT') ?: 'mailto:retratospsn@gmail.com',
    'publicKey' => getenv('VAPID_PUBLIC_KEY'), 'privateKey' => getenv('VAPID_PRIVATE_KEY')]]);
$res = $conn->query("SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = $uid");
while ($row = $res->fetch_assoc()) {
    $web->queueNotification(
        Subscription::create(['endpoint' => $row['endpoint'], 'keys' => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']]]),
        json_encode(['title' => 'Teste de notificação', 'body' => 'Se vir isto, o push está a funcionar.', 'url' => '/mensagens', 'tag' => 'psn-teste-diag']),
        ['TTL' => 3600, 'urgency' => 'high', 'topic' => 'psn-teste-diag']
    );
}
foreach ($web->flush() as $r) {
    $host = parse_url($r->getEndpoint(), PHP_URL_HOST);
    echo "Envio para $host: ", $r->isSuccess() ? "OK" : ("FALHA - " . $r->getReason() . ($r->isSubscriptionExpired() ? " (expirada)" : "")), "\n";
}
