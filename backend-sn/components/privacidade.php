<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
session_start();

header('Content-Type: application/json');

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
                criarTabelaAdminSessoes($conn);
                $token = bin2hex(random_bytes(32));
                $insert = $conn->prepare("INSERT INTO admin_sessoes (admin_id, token) VALUES (?, ?)");
                $insert->bind_param("is", $id_admin, $token);
                $insert->execute();
                $insert->close();

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Login bem-sucedido',
                    'name' => $name_admin,
                    'is_super' => (int) $is_super,
                    'token' => $token,
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Email ou palavra passe incorretos']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'área não permitida']);
        }
        $stmt->close();
    }
}
?>
