<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
include './updateUsuarios.php';

requireAdmin($conn);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
	deleteUser($id);
} else {
	header('Content-Type: application/json');
	echo json_encode(["status" => "error", "message" => "ID inválido"]);
}
?>
