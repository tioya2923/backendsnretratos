<?php
session_start();
require_once '../connect/server.php';
require_once '../connect/cors.php';

$name = $_POST['name']; // Adicione esta linha
$email = $_POST['email'];
// Verificar se o email já existe
$sql = "SELECT * FROM admins WHERE email_admin = '$email'";
$result = mysqli_query($conn, $sql);
if (mysqli_num_rows($result) > 0) {
    echo json_encode('O email já está em uso');
    exit();
}
// Se o email não existir, continue com o registro
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$is_super = $_POST['is_super'] ? 1 : 0;

$sql = "INSERT INTO admins (name_admin, email_admin, password_admin, is_super) VALUES ('$name', '$email', '$password', '$is_super')";
if (mysqli_query($conn, $sql)) {
    echo json_encode('Registo bem-sucedido');
} else {
    echo json_encode('Erro no registo: ' . mysqli_error($conn));
}

?>
