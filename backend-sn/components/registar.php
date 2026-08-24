<?php

ini_set('display_errors', 0);

// Handler de exceções
function handleUncaughtException($e)
{
    error_log('[UNCAUGHT] ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Olá! Estaremos juntos brevemente!'
    ]);
    exit;
}

set_exception_handler('handleUncaughtException');

// -------------------- DEPENDÊNCIAS --------------------
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    error_log("ERRO: autoload.php não encontrado em $autoloadPath");
    echo json_encode(['status' => 'error', 'message' => 'Erro interno (autoload)']);
    exit;
}

require_once $autoloadPath;
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';
require_once __DIR__ . '/email_utils.php';

header('Content-Type: application/json');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido']);
    exit;
}

// -------------------- RECEBER JSON --------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode(['status' => 'error', 'message' => 'Formato inválido']);
    exit;
}

$name     = trim($data['name'] ?? '');
$email    = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$password = $data['password'] ?? '';
$newRegistration = filter_var($data['newRegistration'] ?? true, FILTER_VALIDATE_BOOLEAN);

/**
 * Valida uma data no formato YYYY-MM-DD, não vazia e não no futuro.
 */
function validarDataAniversario(?string $data): ?string {
    if (empty($data)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $data);
    if (!$d || $d->format('Y-m-d') !== $data) return null;
    if ($d > new DateTime()) return null;
    return $data;
}

$dataAniversario           = validarDataAniversario($data['dataAniversario'] ?? null);
$dataAniversarioSacerdotal = validarDataAniversario($data['dataAniversarioSacerdotal'] ?? null);

// -------------------- VALIDAÇÃO --------------------
if (!$name || !$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
    exit;
}

if (!$dataAniversario) {
    echo json_encode(['status' => 'error', 'message' => 'Data de aniversário natalício inválida ou não fornecida']);
    exit;
}

if (!empty($data['dataAniversarioSacerdotal']) && !$dataAniversarioSacerdotal) {
    echo json_encode(['status' => 'error', 'message' => 'Data de aniversário sacerdotal inválida']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['status' => 'error', 'message' => 'A palavra passe deve ter pelo menos 8 caracteres']);
    exit;
}

// -------------------- VERIFICAR EMAIL DUPLICADO --------------------
$stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(['status' => 'email_exists']);
    exit;
}
$stmt->close();

// -------------------- INSERIR UTILIZADOR --------------------
// A aprovação do administrador deixou de ser exigida — a conta fica
// logo 'aprovado' e o utilizador consegue iniciar sessão de imediato
// (login.php só deixa entrar contas com este status).
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO usuarios (name, email, password, status, data_aniversario, data_aniversario_sacerdotal)
    VALUES (?, ?, ?, 'aprovado', ?, ?)
");
$stmt->bind_param(
    "sssss",
    $name,
    $email,
    $passwordHash,
    $dataAniversario,
    $dataAniversarioSacerdotal
);

if (!$stmt->execute()) {
    error_log("Erro INSERT: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registar utilizador']);
    exit;
}

$stmt->close();

// -------------------- EMAILS (admin + utilizador) — em fila --------------------
// fastcgi_finish_request() foi tentado antes para responder já e mandar os
// emails a seguir "em segundo plano", mas medido ao vivo não fez diferença
// nenhuma (continuava a demorar os mesmos ~20s) — o PHP deste servidor não
// deve correr sob um SAPI que suporte essa função. Em vez disso, os emails
// nunca chegam a ser tentados neste pedido: só ficam em fila (uma tabela,
// sem tocar em SMTP) e o cron (enviar_lembretes.php, a cada 5 min) é que os
// envia a sério, com processarEmailsPendentes(). A conta já está criada e
// ativa neste ponto, por isso não há razão nenhuma para o registo esperar.
$bodyAdmin = "
    O utilizador <strong>$name</strong> registou-se e a conta já está ativa (não é necessária aprovação).
";
enfileirarEmail($conn, 'retratospsn@gmail.com', 'Novo registo de utilizador', $bodyAdmin, true);
enfileirarEmail($conn, $email, 'Registo efetuado com sucesso', 'O seu registo foi efetuado com sucesso. Já pode iniciar sessão.', true);

$conn->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Registo feito com sucesso. Já pode iniciar sessão.'
]);
exit;
