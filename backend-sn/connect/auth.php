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
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['user_id'] : null;
}
