<?php
// TEMPORÁRIO — autorizado explicitamente pelo utilizador só para
// contar utilizadores. Não expõe dados pessoais. Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'contar-utilizadores-20260827';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

$res = $conn->query("SELECT status, COUNT(*) as total FROM usuarios GROUP BY status");
$porStatus = [];
while ($row = $res->fetch_assoc()) {
    $porStatus[$row['status']] = (int) $row['total'];
}

$totalAdmins = $conn->query("SELECT COUNT(*) as total FROM admins")->fetch_assoc()['total'];

echo json_encode([
    'usuarios_por_status' => $porStatus,
    'total_usuarios' => array_sum($porStatus),
    'total_admins' => (int) $totalAdmins,
]);
