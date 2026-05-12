<?php
date_default_timezone_set('Europe/Lisbon');
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';

$conn->set_charset("utf8mb4");

// Migração automática: se a tabela tem o schema antigo (dia_semana), recria
$tableExists = $conn->query("SHOW TABLES LIKE 'atividades_usuario'")->num_rows > 0;
if ($tableExists) {
    $hasOld = $conn->query("SHOW COLUMNS FROM atividades_usuario LIKE 'dia_semana'")->num_rows > 0;
    if ($hasOld) {
        $conn->query("DROP TABLE atividades_usuario");
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS atividades_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(100),
    data_atividade DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_notificacao DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_data (data_atividade, hora_inicio, ativo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$userId = getAuthUserId($conn);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET — listar atividades do utilizador
if ($method === 'GET') {
    $stmt = $conn->prepare(
        "SELECT id, tipo, titulo, data_atividade, hora_inicio, ativo
         FROM atividades_usuario WHERE user_id = ?
         ORDER BY data_atividade, hora_inicio"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r['id']    = (int)$r['id'];
        $r['ativo'] = (bool)$r['ativo'];
        $r['hora_inicio'] = substr($r['hora_inicio'], 0, 5);
    }
    echo json_encode($rows);
    exit;
}

// POST — criar atividade
if ($method === 'POST') {
    $data          = json_decode(file_get_contents('php://input'), true);
    $tipo          = trim($data['tipo'] ?? '');
    $titulo        = trim($data['titulo'] ?? '');
    $dataAtividade = trim($data['data_atividade'] ?? '');
    $horaInicio    = trim($data['hora_inicio'] ?? '');

    if (empty($tipo) || empty($dataAtividade) || empty($horaInicio)) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios: tipo, data_atividade, hora_inicio']);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataAtividade)) {
        http_response_code(400);
        echo json_encode(['error' => 'Formato de data inválido']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO atividades_usuario (user_id, tipo, titulo, data_atividade, hora_inicio)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $userId, $tipo, $titulo, $dataAtividade, $horaInicio);
    $stmt->execute();

    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
}

// PUT — atualizar atividade
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID obrigatório']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM atividades_usuario WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado']);
        exit;
    }

    $fields = [];
    $types  = '';
    $values = [];

    if (isset($data['ativo'])) {
        $fields[] = 'ativo = ?';
        $types   .= 'i';
        $values[] = $data['ativo'] ? 1 : 0;
    }
    if (isset($data['hora_inicio'])) {
        $fields[] = 'hora_inicio = ?';
        $types   .= 's';
        $values[] = $data['hora_inicio'];
    }
    if (isset($data['tipo'])) {
        $fields[] = 'tipo = ?';
        $types   .= 's';
        $values[] = $data['tipo'];
    }
    if (isset($data['titulo'])) {
        $fields[] = 'titulo = ?';
        $types   .= 's';
        $values[] = $data['titulo'];
    }
    if (isset($data['data_atividade'])) {
        $fields[] = 'data_atividade = ?';
        $types   .= 's';
        $values[] = $data['data_atividade'];
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'Nenhum campo para atualizar']);
        exit;
    }

    $types   .= 'i';
    $values[] = $id;
    $sql = "UPDATE atividades_usuario SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit;
}

// DELETE — eliminar atividade
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID obrigatório']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM atividades_usuario WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Atividade não encontrada']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não suportado']);
