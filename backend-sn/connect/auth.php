<?php
/**
 * Extrai o Bearer token do header Authorization de forma robusta,
 * compatível com Apache mod_php e FastCGI (Heroku).
 */
function getBearerToken(): string {
    // $_SERVER['HTTP_AUTHORIZATION'] funciona em FastCGI/Heroku
    $auth = $_SERVER['HTTP_AUTHORIZATION']
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
         ?? '';

    // Fallback para apache_request_headers() (mod_php)
    if (empty($auth) && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
    }

    if (stripos($auth, 'Bearer ') === 0) {
        return trim(substr($auth, 7));
    }
    return '';
}

/**
 * Retorna o ID do utilizador autenticado ou null.
 */
function getAuthUserId(mysqli $conn): ?int {
    $token = getBearerToken();
    if (empty($token)) return null;

    $stmt = $conn->prepare("SELECT user_id FROM sessoes WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = stmt_get_result($stmt)->fetch_assoc();
    return $row ? (int)$row['user_id'] : null;
}

/**
 * Sessões de administrador — equivalente à tabela `sessoes` dos
 * utilizadores normais, mas para `admins`. Criada em runtime (mesmo
 * padrão já usado para outras tabelas no projeto) para não depender de
 * uma migração manual.
 */
function criarTabelaAdminSessoes(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS admin_sessoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_token (token),
        INDEX idx_admin (admin_id),
        CONSTRAINT fk_admin_sessoes_admin FOREIGN KEY (admin_id) REFERENCES admins(id_admin) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

/**
 * Retorna o ID do administrador autenticado ou null. Endpoints de
 * gestão (apagar/listar administradores e utilizadores, gerir grupos)
 * devem verificar isto antes de agir.
 */
function getAuthAdminId(mysqli $conn): ?int {
    $token = getBearerToken();
    if (empty($token)) return null;

    $stmt = $conn->prepare("SELECT admin_id FROM admin_sessoes WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = stmt_get_result($stmt)->fetch_assoc();
    return $row ? (int)$row['admin_id'] : null;
}

/**
 * Corta a execução com 403 se não houver uma sessão de administrador
 * válida no pedido atual.
 */
function requireAdmin(mysqli $conn): int {
    $adminId = getAuthAdminId($conn);
    if ($adminId === null) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Acesso negado — sessão de administrador inválida ou em falta']);
        exit;
    }
    return $adminId;
}
