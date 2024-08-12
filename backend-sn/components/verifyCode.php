<?php

require_once '../connect/server.php';
require_once '../connect/cors.php';

// Verificar a conexão
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); // Convertendo para array associativo

if (isset($input['codigo'])) {
    $codigoUsuario = $input['codigo'];
    $stmt = $conn->prepare('SELECT * FROM codigosecreto WHERE verificacao = ?');
    $stmt->bind_param('s', $codigoUsuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        echo json_encode(['status' => 'success', 'message' => 'Código correto!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Código incorreto!']);
    }

    // Fechar a conexão
    $stmt->close();
}

if ($conn) {
    $conn->close();
}