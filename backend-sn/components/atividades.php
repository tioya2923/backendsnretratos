<?php
date_default_timezone_set('Europe/Lisbon');
require_once '../connect/server.php';
require_once '../connect/cors.php';

$conn->set_charset("utf8mb4");

// Criar tabela se não existir
$conn->query("CREATE TABLE IF NOT EXISTS atividades_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(100),
    dia_semana TINYINT NOT NULL COMMENT '0=Dom 1=Seg 2=Ter 3=Qua 4=Qui 5=Sex 6=Sab',
    hora_inicio TIME NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_notificacao DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_notif (dia_semana, hora_inicio, ativo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Verificar token
function getUsuarioId($conn) {
    $headers = apache_request_headers();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    if (empty($token)) return null;
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['id'] : null;
}

$userId = getUsuarioId($conn);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET — listar atividades do utilizador
if ($method === 'GET') {
    $stmt = $conn->prepare(
        "SELECT id, tipo, titulo, dia_semana, hora_inicio, ativo
         FROM atividades_usuario WHERE user_id = ? ORDER BY dia_semana, hora_inicio"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r['ativo'] = (bool)$r['ativo'];
        $r['hora_inicio'] = substr($r['hora_inicio'], 0, 5); // HH:MM
    }
    echo json_encode($rows);
    exit;
}

// POST — criar atividade (suporta múltiplos dias)
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $tipo       = trim($data['tipo'] ?? '');
    $titulo     = trim($data['titulo'] ?? '');
    $dias       = $data['dias'] ?? [];      // array de 0-6
    $horaInicio = $data['hora_inicio'] ?? '';

    if (empty($tipo) || empty($dias) || empty($horaInicio)) {
        http_response_code(400);
        echo json_encode(['error' => 'Campos obrigatórios: tipo, dias, hora_inicio']);
        exit;
    }

    $criados = [];
    $stmt = $conn->prepare(
        "INSERT INTO atividades_usuario (user_id, tipo, titulo, dia_semana, hora_inicio)
         VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($dias as $dia) {
        $dia = (int)$dia;
        $stmt->bind_param("issис", $userId, $tipo, $titulo, $dia, $horaInicio);
        $stmt->bind_param("issis", $userId, $tipo, $titulo, $dia, $horaInicio);
        $stmt->execute();
        $criados[] = $conn->insert_id;
    }

    echo json_encode(['success' => true, 'ids' => $criados]);
    exit;
}

// PUT — atualizar atividade (toggle ativo ou editar campos)
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID obrigatório']);
        exit;
    }

    // Verificar que pertence ao utilizador
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
    if (isset($data['dia_semana'])) {
        $fields[] = 'dia_semana = ?';
        $types   .= 'i';
        $values[] = (int)$data['dia_semana'];
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
    $id = (int)($data['id'] ?? 0);

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
