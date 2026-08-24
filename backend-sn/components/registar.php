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
$conn->close();

// -------------------- RESPONDER JÁ, EMAILS DEPOIS --------------------
// A conta já está criada e ativa neste ponto — o utilizador não precisa de
// esperar pelos emails para poder continuar. Antes, os dois sendEmail()
// abaixo corriam ANTES desta resposta: com a porta SMTP da PTisp bloqueada
// (timeout de 10s cada), isso prendia o registo inteiro até ~20s. Devolver
// a resposta já e só depois enviar os emails corta isso para o tempo real
// do pedido (bem abaixo de 1s).
echo json_encode([
    'status' => 'success',
    'message' => 'Registo feito com sucesso. Já pode iniciar sessão.'
]);

// fastcgi_finish_request() entrega a resposta ao browser e fecha a ligação,
// mas o script continua a correr a seguir — é isto que torna os emails
// "em segundo plano". Só existe sob PHP-FPM; noutros SAPIs (ex.: mod_php)
// simplesmente não existe, e os emails voltam a correr antes de terminar
// (mais lento, mas nunca quebra nada).
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// -------------------- EMAILS (admin + utilizador) --------------------
// Usa o helper partilhado (email_utils.php): mesma configuração SMTP em
// vez de duplicada duas vezes, e já com timeout curto — sem isto, uma
// porta SMTP bloqueada (como aconteceu na PTisp) prendia o pedido de
// registo inteiro durante minutos.
// Aviso ao admin é só informativo agora — a conta já está ativa, não
// precisa de nenhuma ação para o utilizador poder entrar.
$bodyAdmin = "
    O utilizador <strong>$name</strong> registou-se e a conta já está ativa (não é necessária aprovação).
";
if (!sendEmail('retratospsn@gmail.com', 'Novo registo de utilizador', $bodyAdmin, true)) {
    error_log("Erro ao enviar email de admin para registo de $name");
}

if (!sendEmail($email, 'Registo efetuado com sucesso', 'O seu registo foi efetuado com sucesso. Já pode iniciar sessão.', true)) {
    error_log("Erro ao enviar email de confirmação de registo para $email");
}

exit;
