<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
include './updateAdministradores.php';

$id = $_GET['id_admin'];
$current_user_id = $_GET['current_user_id']; // Obtenha o ID do usuário atual
deleteAdmin($id, $current_user_id); // Passe ambos os IDs para a função
?>
