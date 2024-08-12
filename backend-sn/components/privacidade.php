<?php
session_start();
require_once '../connect/server.php';
require_once '../connect/cors.php';

// Verifique se o email e a senha foram postados
if(isset($_POST['email']) && isset($_POST['password'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Preparar a declaração
    if ($stmt = $conn->prepare('SELECT * FROM admins WHERE email_admin = ?')) {
        // Vincular parâmetros (s = string, i = int, b = blob, etc), no nosso caso o email é uma string, então usamos "s"
        $stmt->bind_param('s', $email);
        $stmt->execute();
        // Armazenar o resultado para que possamos verificar se a conta existe no banco de dados ou não
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id_admin, $name_admin, $email_admin, $password_admin, $is_super, $created_at);
            $stmt->fetch();            
            if (password_verify($password, $password_admin)) {
                echo json_encode('Login bem-sucedido');
            } else {
                echo json_encode('Email ou palavra passe incorretos');
            }
        } else {
            echo json_encode('área não permitida');
        }
        $stmt->close();
    }
}
?>
