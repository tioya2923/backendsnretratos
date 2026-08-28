<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador para enviar UMA
// mensagem em massa (texto confirmado em chat) a todos os utilizadores
// aprovados, por email e push. Remove-se logo a seguir. Réplica do
// enviar_mensagem_todos.php (CLI-only), mas disparável por HTTP com
// token próprio, já que não há acesso a CLI/cPanel neste momento.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/push_utils.php';

$tokenEsperado = 'envio-massa-20260828';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

$assunto = 'Passos importantes para usar a app';

$conn->set_charset("utf8mb4");

$res = $conn->query("SELECT id, name, email FROM usuarios WHERE status = 'aprovado'");
$resultado = ['emails_ok' => 0, 'emails_falha' => 0, 'sem_email' => 0, 'utilizadores' => []];

while ($user = $res->fetch_assoc()) {
    $nome = trim($user['name']);

    $bodyHtml = "
        Olá, <strong>$nome</strong>!<br><br>
        Para não perder nenhuma refeição, siga estes 3 passos:<br><br>
        1️⃣ Abra a app aqui: <a href=\"https://sn.paroquiasaonicolau.pt\">https://sn.paroquiasaonicolau.pt</a><br>
        2️⃣ Instale-a no telemóvel (Android/iPhone) ou no PC<br>
        3️⃣ Ative as notificações push quando pedido<br><br>
        <strong>E o mais importante: confirme sempre a sua presença!</strong> Toque no botão \"Confirmar presença\" junto ao seu nome, na primeira hora depois do início do almoço ou do jantar. Isto ajuda-nos a saber mesmo quem vem, todos os dias.<br><br>
        Obrigado!<br>
        Paróquia de São Nicolau
    ";

    $entrada = ['nome' => $nome];

    if (!empty($user['email'])) {
        $erro = null;
        $ok = sendEmail($user['email'], $assunto, $bodyHtml, true, $erro);
        $entrada['email'] = $ok ? 'ok' : "falha: $erro";
        $ok ? $resultado['emails_ok']++ : $resultado['emails_falha']++;
    } else {
        $entrada['email'] = 'sem endereço';
        $resultado['sem_email']++;
    }

    $resultado['utilizadores'][] = $entrada;
    usleep(300000);
}

sendPushNotification(
    $conn,
    'Paróquia de São Nicolau',
    'Passos importantes para usar a app — veja o seu email!',
    '/',
    [],
    'psn-mensagem',
    86400,
    'high'
);
$resultado['push'] = 'enviado a todos os subscritores';

echo json_encode($resultado, JSON_PRETTY_PRINT);
