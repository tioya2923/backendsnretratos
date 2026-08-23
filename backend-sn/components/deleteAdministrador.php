<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
require_once __DIR__ . '/admin_utils.php';

header('Content-Type: application/json');

// O admin autenticado vem sempre da sessão — nunca do pedido, que era
// exatamente a falha que permitia apagar qualquer admin sem login nenhum.
$currentAdminId = requireAdmin($conn);

$id = isset($_GET['id_admin']) ? intval($_GET['id_admin']) : 0;
if ($id > 0) {
	deleteAdmin($id, $currentAdminId);
} else {
	echo json_encode(["status" => "error", "message" => "ID inválido"]);
}
?>
