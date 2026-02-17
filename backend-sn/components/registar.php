
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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

// -------------------- VALIDAÇÃO --------------------
if (!$name || !$email || !$password || !$whatsapp) {
    echo json_encode(['status' => 'error', 'message' => 'Dados incompletos']);
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
$approvalUrl = "https://snref-backend-8d85ffa999cd.herokuapp.com/components/linkAprovacao.php?code=$approvalCode";

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO usuarios (name, email, password, whatsapp, status, approval_code)
    VALUES (?, ?, ?, ?, 'pendente', ?)
");
$stmt->bind_param("sssss", $name, $email, $passwordHash, $whatsapp, $approvalCode);

if (!$stmt->execute()) {
    error_log("Erro INSERT: " . $stmt->error);
    echo json_encode(['status' => 'error', 'message' => 'Erro ao registar utilizador']);
    exit;
}

$stmt->close();

// -------------------- CONFIGURAR SMTP --------------------
$mailUser = getenv('MAIL_USERNAME') ?: 'retratospsn@gmail.com';
$mailPass = getenv('MAIL_PASSWORD') ?: null;

if (!$mailPass) {
    error_log("ERRO: MAIL_PASSWORD não definido no ambiente");
}

// -------------------- EMAIL PARA ADMIN --------------------
try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $mailUser;
    $mail->Password = $mailPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom($mailUser, 'Paróquia de São Nicolau');
    $mail->addAddress('retratospsn@gmail.com');

    $mail->isHTML(true);
    $mail->Subject = 'Novo registo de utilizador';
    $mail->Body = "
        O utilizador <strong>$name</strong> registou-se.<br><br>
        <a href='$approvalUrl'>Clique aqui para aprovar o registo</a>
    ";
    $mail->AltBody = "Aprovar registo: $approvalUrl";

    $mail->send();
} catch (Exception $e) {
    error_log("Erro email admin: " . $e->getMessage());
}

// -------------------- EMAIL PARA UTILIZADOR --------------------
try {
    $userMail = new PHPMailer(true);
    $userMail->isSMTP();
    $userMail->Host = 'smtp.gmail.com';
    $userMail->SMTPAuth = true;
    $userMail->Username = $mailUser;
    $userMail->Password = $mailPass;
    $userMail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $userMail->Port = 465;

    $userMail->setFrom($mailUser, 'Paróquia de São Nicolau');
    $userMail->addAddress($email);

    $userMail->isHTML(true);
    $userMail->Subject = 'Registo efetuado com sucesso';
    $userMail->Body = 'O seu registo foi efetuado com sucesso. Aguarde a aprovação do administrador.';

    $userMail->send();
} catch (Exception $e) {
    error_log("Erro email utilizador: " . $e->getMessage());
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
