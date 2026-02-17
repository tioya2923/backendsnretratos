<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/cors.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Obter os dados do formulário

$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validar os dados

if (!$email || !$password) {
    echo json_encode(["status" => "error", "message" => "Email e/ou password não fornecidos ou inválidos"]);
    exit();
}

// Preparar a consulta SQL para evitar a injeção de SQL
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) { 
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password'])) {
        // Verificar se o usuário foi aprovado
        if ($row['status'] == 'aprovado') {
            session_start();
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['name'];
            // Verificar se o número de WhatsApp está cadastrado
            if (empty($row['whatsapp'])) {
                echo json_encode(["status" => "whatsapp_required", "message" => "Por favor, insira seu número de WhatsApp para continuar.", "user_id" => $row['id']]);
                exit();
            }
            // Gerar um token de autenticação
            $token = bin2hex(random_bytes(16));
            // Armazenar o token no banco de dados
            $update_stmt = $conn->prepare("UPDATE usuarios SET token = ? WHERE id = ?");
            $update_stmt->bind_param("si", $token, $row['id']);
            $update_stmt->execute();
            // Retornar uma resposta de sucesso com o token
            echo json_encode(["status" => "success", "message" => "Login bem-sucedido", "name" => $row['name'], "token" => $token]);
        } else {
            echo json_encode(["status" => "error", "message" => "A sua conta ainda não foi aprovada pelo administrador."]);
        }
        exit();
    } else {
        echo json_encode(["status" => "error", "message" => "Senha incorreta, tente novamente"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Usuário não encontrado, por favor, regista-te"]);
}
?>
