<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
session_start();

// Sanitizar entradas
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL) : '';
// Verificar se o email já existe
$sql = "SELECT * FROM admins WHERE email_admin = '$email'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
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
