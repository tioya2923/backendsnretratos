<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Obter os dados do formulário
$email = $_POST['email'];
$password = $_POST['password'];

// Validar os dados
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode(["message" => "Email e/ou password não fornecidos"]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["message" => "Por favor, insira um email válido"]);
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
            
            // Gerar um token de autenticação
            $token = bin2hex(random_bytes(16));
            
            // Armazenar o token no banco de dados
            $update_stmt = $conn->prepare("UPDATE usuarios SET token = ? WHERE id = ?");
            $update_stmt->bind_param("si", $token, $row['id']);
            $update_stmt->execute();
            
            // Retornar uma resposta de sucesso com o token
            echo json_encode(["message" => "Login bem-sucedido", "name" => $row['name'], "token" => $token]);
        } else {
            echo json_encode(["message" => "A sua conta ainda não foi aprovada pelo administrador."]);
        }
        exit();
    } else {
        echo json_encode(["message" => "Senha incorreta, tente novamente"]);
    }
} else {
    echo json_encode(["message" => "Usuário não encontrado, por favor, regista-te"]);
}
?>
