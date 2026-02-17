<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
include './updateAdministradores.php';

$id = isset($_GET['id_admin']) ? intval($_GET['id_admin']) : 0;
$current_user_id = isset($_GET['current_user_id']) ? intval($_GET['current_user_id']) : 0;
if ($id > 0 && $current_user_id > 0) {
	deleteAdmin($id, $current_user_id);
} else {
	echo json_encode(["status" => "error", "message" => "IDs inválidos"]);
}
?>
