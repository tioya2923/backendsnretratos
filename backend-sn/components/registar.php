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
require_once __DIR__ . '/whatsapp_utils.php';
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
$whatsapp = preg_replace('/\D/', '', $data['whatsapp'] ?? '');
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
if (!$name || !$email || !$password || !$whatsapp) {
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
$approvalCode = bin2hex(random_bytes(16));
$backendUrl = rtrim(getenv('BACKEND_URL') ?: '', '/');
$approvalUrl = "$backendUrl/components/linkAprovacao.php?code=$approvalCode";

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO usuarios (name, email, password, whatsapp, status, approval_code, data_aniversario, data_aniversario_sacerdotal)
    VALUES (?, ?, ?, ?, 'pendente', ?, ?, ?)
");
$stmt->bind_param(
    "sssssss",
    $name,
    $email,
    $passwordHash,
    $whatsapp,
    $approvalCode,
    $dataAniversario,
    $dataAniversarioSacerdotal
);

if (!$stmt->execute()) {
    error_log("Erro INSERT: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registar utilizador']);
    exit;
}

$stmt->close();

// -------------------- EMAILS (admin + utilizador) --------------------
// Usa o helper partilhado (email_utils.php): mesma configuração SMTP em
// vez de duplicada duas vezes, e já com timeout curto — sem isto, uma
// porta SMTP bloqueada (como aconteceu na PTisp) prendia o pedido de
// registo inteiro durante minutos.
$bodyAdmin = "
    O utilizador <strong>$name</strong> registou-se.<br><br>
    <a href='$approvalUrl'>Clique aqui para aprovar o registo</a>
";
if (!sendEmail('retratospsn@gmail.com', 'Novo registo de utilizador', $bodyAdmin, true)) {
    error_log("Erro ao enviar email de admin para registo de $name");
}

if (!sendEmail($email, 'Registo efetuado com sucesso', 'O seu registo foi efetuado com sucesso. Aguarde a aprovação do administrador.', true)) {
    error_log("Erro ao enviar email de confirmação de registo para $email");
}

// -------------------- WHATSAPP --------------------
try {
    sendWhatsApp($whatsapp, "Registo feito com sucesso. Aguarde a aprovação do administrador.");
} catch (Exception $e) {
    error_log("Erro WhatsApp: " . $e->getMessage());
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Registo feito com sucesso. Aguarde pela aprovação do Administrador.'
]);
exit;
