<?php
// Incluir o ficheiro de conexão

require_once __DIR__ . '/../../vendor/autoload.php';
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

$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';
$password = isset($_POST['password']) ? htmlspecialchars(trim($_POST['password'])) : '';

// Validar os dados

if (!$email || !$password) {
    echo json_encode(["status" => "error", "message" => "Por favor, preencha todos os campos corretamente"]);
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

        // Verificar se o número de WhatsApp está cadastrado
        if (empty($row['whatsapp'])) {
            echo json_encode(["status" => "whatsapp_required", "message" => "Por favor, insira seu número de WhatsApp para continuar.", "user_id" => $row['id']]);
            exit();
        }

        // Em vez de redirecionar, retorne uma resposta de sucesso
        echo json_encode(["status" => "success", "message" => "Login bem-sucedido"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Senha incorreta, tente novamente"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Usuário não encontrado, por favor, registre-se"]);
}

// Fechar a declaração preparada
$stmt->close();
?>
