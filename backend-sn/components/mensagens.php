<?php
date_default_timezone_set('Europe/Lisbon');
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';

$conn->set_charset("utf8mb4");

// Tabela principal de mensagens
$conn->query("CREATE TABLE IF NOT EXISTS mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id VARCHAR(40) DEFAULT NULL,
    remetente_id INT NOT NULL,
    destinatario_id INT DEFAULT NULL,
    corpo TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dest (destinatario_id),
    INDEX idx_rem (remetente_id),
    INDEX idx_grupo (grupo_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// Tabela de leituras por utilizador
$conn->query("CREATE TABLE IF NOT EXISTS mensagem_leituras (
    mensagem_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    PRIMARY KEY (mensagem_id, utilizador_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$userId = getAuthUserId($conn);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────────────────────

if ($method === 'GET') {

    // ?utilizadores → lista de utilizadores para o seletor de destinatários
    if (isset($_GET['utilizadores'])) {
        $stmt = $conn->prepare("SELECT id, name FROM usuarios WHERE id != ? ORDER BY name");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$r) $r['id'] = (int)$r['id'];
        echo json_encode($rows);
        exit;
    }

    // ?tipo=enviadas → mensagens enviadas (agrupadas por grupo_id)
    if (isset($_GET['tipo']) && $_GET['tipo'] === 'enviadas') {
        $stmt = $conn->prepare("
            SELECT
                MIN(m.id) AS id,
                m.grupo_id,
                m.corpo,
                MIN(m.created_at) AS created_at,
                MAX(CASE WHEN m.destinatario_id IS NULL THEN 1 ELSE 0 END) AS para_todos,
                GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR '|||') AS destinatarios_str
            FROM mensagens m
            LEFT JOIN usuarios u ON u.id = m.destinatario_id
            WHERE m.remetente_id = ?
            GROUP BY COALESCE(m.grupo_id, CAST(m.id AS CHAR))
            ORDER BY MIN(m.created_at) DESC
            LIMIT 100
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$r) {
            $r['id']        = (int)$r['id'];
            $r['para_todos'] = (bool)$r['para_todos'];
            $r['destinatarios'] = $r['destinatarios_str']
                ? explode('|||', $r['destinatarios_str'])
                : [];
            unset($r['destinatarios_str']);
        }
        echo json_encode($rows);
        exit;
    }

    // Recebidas (inbox)
    $stmt = $conn->prepare("
        SELECT m.id, m.corpo, m.created_at,
               u.name AS remetente_nome,
               CASE WHEN m.destinatario_id IS NULL THEN 1 ELSE 0 END AS para_todos,
               CASE WHEN ml.mensagem_id IS NOT NULL THEN 1 ELSE 0 END AS lida
        FROM mensagens m
        JOIN usuarios u ON u.id = m.remetente_id
        LEFT JOIN mensagem_leituras ml
               ON ml.mensagem_id = m.id AND ml.utilizador_id = ?
        WHERE (m.destinatario_id = ? OR m.destinatario_id IS NULL)
          AND m.remetente_id != ?
        ORDER BY m.created_at DESC
        LIMIT 100
    ");
    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r['id']       = (int)$r['id'];
        $r['lida']     = (bool)$r['lida'];
        $r['para_todos'] = (bool)$r['para_todos'];
    }
    echo json_encode($rows);
    exit;
}

// ── POST — enviar mensagem ────────────────────────────────────────────────────

if ($method === 'POST') {
    $data          = json_decode(file_get_contents('php://input'), true);
    $corpo         = trim($data['corpo'] ?? '');
    $destinatarios = $data['destinatarios'] ?? null;

    if (empty($corpo)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mensagem não pode estar vazia']);
        exit;
    }

    if ($destinatarios === 'todos') {
        // Uma só linha com destinatario_id = NULL
        $stmt = $conn->prepare(
            "INSERT INTO mensagens (remetente_id, destinatario_id, corpo) VALUES (?, NULL, ?)"
        );
        $stmt->bind_param("is", $userId, $corpo);
        $stmt->execute();

    } elseif (is_array($destinatarios) && !empty($destinatarios)) {
        // Uma linha por destinatário, com o mesmo grupo_id
        $grupoId = uniqid('msg_', true);
        $stmt = $conn->prepare(
            "INSERT INTO mensagens (grupo_id, remetente_id, destinatario_id, corpo)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($destinatarios as $destId) {
            $destId = (int)$destId;
            if ($destId > 0 && $destId !== $userId) {
                $stmt->bind_param("siis", $grupoId, $userId, $destId, $corpo);
                $stmt->execute();
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Destinatários inválidos']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── PUT — marcar como lida ────────────────────────────────────────────────────

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID obrigatório']);
        exit;
    }
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO mensagem_leituras (mensagem_id, utilizador_id) VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE — eliminar mensagem ────────────────────────────────────────────────

if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = (int)($data['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID obrigatório']);
        exit;
    }
    // Permite eliminar se for o remetente OU o destinatário específico
    $stmt = $conn->prepare(
        "DELETE FROM mensagens WHERE id = ? AND (remetente_id = ? OR destinatario_id = ?)"
    );
    $stmt->bind_param("iii", $id, $userId, $userId);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $del = $conn->prepare("DELETE FROM mensagem_leituras WHERE mensagem_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        echo json_encode(['success' => true]);
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'Sem permissão ou mensagem não encontrada']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método não suportado']);
