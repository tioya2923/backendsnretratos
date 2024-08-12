<?php
// Incluir o ficheiro de conexão
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Iniciar sessão
session_start();

// Função para sanitizar os dados de entrada
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Obter os dados do formulário e sanitizá-los
$email = sanitize_input($_POST['email'] ?? '');
$password = sanitize_input($_POST['password'] ?? '');

// Validar os dados
if (empty($email) || empty($password)) {
    echo json_encode(["message" => "Por favor, preencha todos os campos"]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["message" => "Por favor, insira um email válido"]);
    exit();
}

// Preparar a consulta SQL para evitar injeção de SQL
$sql = "SELECT * FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);

// Executar a consulta
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password'])) {
        // Definir variáveis de sessão
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];
        
        // Em vez de redirecionar, retorne uma resposta de sucesso
        echo json_encode(["message" => "Login bem-sucedido"]);
    } else {
        echo json_encode(["message" => "Senha incorreta, tente novamente"]);
    }
} else {
    echo json_encode(["message" => "Usuário não encontrado, por favor, registre-se"]);
}

// Fechar a declaração preparada
$stmt->close();
?>
