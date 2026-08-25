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
// Sem htmlspecialchars aqui — a palavra-passe vai para password_verify(),
// não para HTML; reescrever caracteres como & < > " ' antes de comparar
// fazia falhar o login de qualquer conta cuja password os contivesse
// (login.php, o login real, nunca fez essa transformação).
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

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
$result = stmt_get_result($stmt);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password'])) {
        // Definir variáveis de sessão
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['user_name'] = $row['name'];

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
