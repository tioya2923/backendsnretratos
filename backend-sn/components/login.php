<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Obter os dados do formulário
$email = $_POST['email'];
$password = $_POST['password'];

// Validar os dados
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode(["message" => "Email e/ou password não fornecidos"]);
    exit();
}

$email = $_POST['email'];
$password = $_POST['password'];


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
            
            // Em vez de redirecionar, retorne uma resposta de sucesso
            echo json_encode(["message" => "Login bem-sucedido"]);
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
