
<?php
require 'cors.php'; // Habilita o CORS
require_once __DIR__ . '/../../vendor/autoload.php';

// Carregar variáveis do .env corretamente a partir da raiz do projeto
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
    $envVars = $dotenv->safeLoad();
    // Forçar variáveis do .env para getenv, $_ENV e $_SERVER
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Tentar buscar DB_URL de todas as fontes possíveis
$dbUrl = getenv('DB_URL') ?: ($_ENV['DB_URL'] ?? ($_SERVER['DB_URL'] ?? null));
$mailUsername = getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? ($_SERVER['MAIL_USERNAME'] ?? null));
$mailPassword = getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? ($_SERVER['MAIL_PASSWORD'] ?? null));

if (!$dbUrl) {
    $msg = 'FATAL: A variável DB_URL não foi carregada.';
    error_log($msg);
    if (php_sapi_name() !== 'cli') { http_response_code(503); }
    die(php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['error' => $msg]));
}

// Processar a URL de conexão ao banco de dados
$url = parse_url($dbUrl);

if (!isset($url["host"], $url["user"], $url["pass"], $url["path"])) {
    $msg = 'FATAL: URL de conexão ao banco está incompleta.';
    error_log($msg);
    if (php_sapi_name() !== 'cli') { http_response_code(503); }
    die(php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['error' => $msg]));
}

$host = $url["host"];
$user = $url["user"];
$password = $url["pass"];
$db = ltrim($url["path"], '/');

// Definir constantes para conexão ao banco de dados
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $db);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        $msg = 'FATAL: Erro na conexão à BD: ' . $conn->connect_error;
        error_log($msg);
        if (php_sapi_name() !== 'cli') { http_response_code(503); }
        die(php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['error' => $msg]));
    }
} catch (Exception $e) {
    $msg = 'FATAL: Erro ao conectar à BD: ' . $e->getMessage();
    error_log($msg);
    if (php_sapi_name() !== 'cli') { http_response_code(503); }
    die(php_sapi_name() === 'cli' ? $msg . "\n" : json_encode(['error' => $msg]));
}

?>
