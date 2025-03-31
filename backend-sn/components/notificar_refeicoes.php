<?php
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../../vendor/autoload.php';
header('Content-Type: application/json');

// Definir o fuso horário para Portugal
date_default_timezone_set('Europe/Lisbon');

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

// Buscar refeições que precisam de notificação e ordenar por data e hora
$sql = "SELECT r.*, g.nome_grupo, COUNT(m.id) as total_membros 
        FROM refeicoes_grupos r
        JOIN Grupos g ON r.grupo_id = g.id
        JOIN Membros m ON g.id = m.grupo_id
        WHERE TIMESTAMPDIFF(HOUR, NOW(), CONCAT(r.data_refeicao, ' ', r.hora_refeicao)) BETWEEN 0 AND 24
        GROUP BY r.id
        ORDER BY r.data_refeicao ASC, r.hora_refeicao ASC";
$result = $conn->query($sql);

$notificacoes = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        error_log("Refeição encontrada: " . $row['data_refeicao'] . " " . $row['hora_refeicao']);
        
        // Adicionar notificação à lista
        $notificacoes[] = [
            'tipo' => $row['tipo_refeicao'],
            'data' => $row['data_refeicao'],
            'hora' => $row['hora_refeicao'],
            'local' => $row['local_refeicao'],
            'nome_grupo' => $row['nome_grupo'],
            'total_membros' => $row['total_membros']
        ];

        // Marcar como notificado
        $update_sql = "UPDATE refeicoes_grupos SET notificado = TRUE WHERE id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("i", $row['id']);
        $stmt->execute();
        $stmt->close();
    }
} else {
    error_log("Nenhuma refeição encontrada para notificação.");
}

echo json_encode($notificacoes);
$conn->close();
?>
