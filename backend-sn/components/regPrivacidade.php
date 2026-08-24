<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
session_start();

header('Content-Type: application/json');

// Este endpoint cria uma conta de administrador — inclui potencialmente
// is_super=1, ou seja, controlo total da aplicação. Nunca deve ficar
// aberto ao público. Dois caminhos possíveis:
//   1) o segredo de servidor (ADMIN_REGISTRATION_SECRET) — para scripts/
//      bootstrap sem sessão nenhuma, mesmo padrão já usado no envio de
//      push em massa (components/send_push.php);
//   2) uma sessão de administrador super — o caminho normal, usado pela
//      própria app (AdPrivacidade.jsx).
$adminRegSecret = getenv('ADMIN_REGISTRATION_SECRET');
$viaSecret = !empty($adminRegSecret) && hash_equals($adminRegSecret, getBearerToken());

if (!$viaSecret) {
    $currentAdminId = getAuthAdminId($conn);
    if ($currentAdminId === null) {
        http_response_code(401);
        echo json_encode('Não autenticado');
        exit;
    }

    // Se ainda não existir nenhum super administrador, qualquer admin
    // autenticado pode criar o primeiro — evita um beco sem saída em que
    // ninguém consegue promover ninguém.
    $resSupers = $conn->query("SELECT COUNT(*) AS total FROM admins WHERE is_super = 1");
    $totalSupers = $resSupers ? (int) ($resSupers->fetch_assoc()['total'] ?? 0) : 0;

    if ($totalSupers > 0) {
        $stmtSuper = $conn->prepare("SELECT is_super FROM admins WHERE id_admin = ?");
        $stmtSuper->bind_param("i", $currentAdminId);
        $stmtSuper->execute();
        $rowSuper = stmt_get_result($stmtSuper)->fetch_assoc();
        $stmtSuper->close();

        if (!$rowSuper || !$rowSuper['is_super']) {
            http_response_code(403);
            echo json_encode('Apenas super administradores podem inserir outros administradores');
            exit;
        }
    }
}

// Sanitizar entradas
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';
if (!$name || !$email) {
    echo json_encode('Dados inválidos');
    exit();
}

// Verificar se o email já existe (prepared statement, não concatenação)
$stmt = $conn->prepare("SELECT id_admin FROM admins WHERE email_admin = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$exists = stmt_get_result($stmt)->fetch_assoc();
$stmt->close();
if ($exists) {
    echo json_encode('O email já está em uso');
    exit();
}
// Se o email não existir, continue com o registro

$password = isset($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
$is_super = isset($_POST['is_super']) && $_POST['is_super'] ? 1 : 0;


$stmt = $conn->prepare("INSERT INTO admins (name_admin, email_admin, password_admin, is_super) VALUES (?, ?, ?, ?)");
$stmt->bind_param("sssi", $name, $email, $password, $is_super);
if ($stmt->execute()) {
    echo json_encode('Registo bem-sucedido');
} else {
    // Não exponha detalhes sensíveis de erro
    echo json_encode('Erro no registo.');
}
$stmt->close();

?>
