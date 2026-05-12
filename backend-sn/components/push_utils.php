<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// Cria a tabela de subscrições se não existir
function criarTabelaSubscricoes($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(512) NOT NULL,
        auth VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_endpoint (endpoint(500))
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Envia notificação push para subscrições específicas ou para todas.
 *
 * @param string $tag     Tag da notificação (agrupa tipo: 'psn-refeicao', 'psn-atividade', etc.)
 * @param int    $ttl     Tempo de vida em segundos (0 = entrega imediata ou descarta)
 * @param string $urgency 'very-low' | 'low' | 'normal' | 'high'
 *                        'high' fura o Doze mode do Android e APNS priority no iOS.
 */
function sendPushNotification(
    $conn,
    string $title,
    string $body,
    string $url = '/',
    array  $userIds = [],
    string $tag     = 'psn',
    int    $ttl     = 3600,
    string $urgency = 'high'
) {
    criarTabelaSubscricoes($conn);

    $vapidPublic  = getenv('VAPID_PUBLIC_KEY');
    $vapidPrivate = getenv('VAPID_PRIVATE_KEY');
    $vapidSubject = getenv('VAPID_SUBJECT') ?: 'mailto:retratospsn@gmail.com';

    if (!$vapidPublic || !$vapidPrivate) {
        error_log('Push: Chaves VAPID não configuradas.');
        return;
    }

    $auth = [
        'VAPID' => [
            'subject'    => $vapidSubject,
            'publicKey'  => $vapidPublic,
            'privateKey' => $vapidPrivate,
        ]
    ];

    // Opções por notificação: urgência e TTL garantem entrega mesmo em Doze mode (Android)
    $notifOptions = ['TTL' => $ttl, 'urgency' => $urgency, 'topic' => $tag];

    $webPush = new WebPush($auth);
    $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag]);

    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $conn->prepare(
            "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id IN ($placeholders)"
        );
        $types = str_repeat('i', count($userIds));
        $stmt->bind_param($types, ...$userIds);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT endpoint, p256dh, auth FROM push_subscriptions");
    }

    $endpointsToDelete = [];

    while ($row = $result->fetch_assoc()) {
        $subscription = Subscription::create([
            'endpoint' => $row['endpoint'],
            'keys'     => ['p256dh' => $row['p256dh'], 'auth' => $row['auth']]
        ]);
        // Passa TTL, urgência e topic explicitamente em cada notificação
        $webPush->queueNotification($subscription, $payload, $notifOptions);
    }

    foreach ($webPush->flush() as $report) {
        if ($report->isSubscriptionExpired()) {
            $endpointsToDelete[] = $report->getEndpoint();
        }
    }

    foreach ($endpointsToDelete as $endpoint) {
        $stmt = $conn->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $stmt->bind_param("s", $endpoint);
        $stmt->execute();
    }
}
