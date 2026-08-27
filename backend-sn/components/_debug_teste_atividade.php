<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para testar
// ao vivo o lembrete de atividades. Remove-se logo a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'teste-atividade-20260827';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
date_default_timezone_set('Europe/Lisbon');

if (isset($_GET['log'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $logfile = __DIR__ . '/enviar_lembretes_cron.log';
    if (!file_exists($logfile)) { echo "log não existe\n"; exit; }
    $linhas = file($logfile);
    echo implode('', array_slice($linhas, -30));
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$email = $_GET['email'] ?? '';
if (!$email) {
    echo json_encode(['error' => 'falta ?email=']);
    exit;
}

$stmt = $conn->prepare("SELECT id, name, email FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['error' => 'utilizador não encontrado para esse email']);
    exit;
}

// Cria a atividade de teste para daqui a 8 minutos
$dataAtividade = date('Y-m-d');
$horaInicio    = date('H:i:s', strtotime('+8 minutes'));

$insert = $conn->prepare(
    "INSERT INTO atividades_usuario (user_id, tipo, titulo, data_atividade, hora_inicio, ativo)
     VALUES (?, 'Outro', 'TESTE de lembrete (apagar)', ?, ?, 1)"
);
$insert->bind_param("iss", $user['id'], $dataAtividade, $horaInicio);
$insert->execute();
$novoId = $conn->insert_id;
$insert->close();

echo json_encode([
    'status' => 'success',
    'user' => ['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email']],
    'atividade_id' => $novoId,
    'data_atividade' => $dataAtividade,
    'hora_inicio' => $horaInicio,
]);
