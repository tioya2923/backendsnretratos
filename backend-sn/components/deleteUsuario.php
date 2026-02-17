<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
include './updateUsuarios.php';


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
	deleteUser($id);
} else {
	echo json_encode(["status" => "error", "message" => "ID inválido"]);
}
?>
