
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
    die(json_encode(['error' => 'A variável DB_URL não foi carregada.']));
}

// Processar a URL de conexão ao banco de dados
$url = parse_url($dbUrl);

if (!isset($url["host"], $url["user"], $url["pass"], $url["path"])) {
    die(json_encode(['error' => 'URL de conexão ao banco está incompleta.']));
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
        die(json_encode(['error' => 'Erro na conexão: ' . $conn->connect_error]));
    }
} catch (Exception $e) {
    die(json_encode(['error' => 'Erro ao conectar: ' . $e->getMessage()]));
}

?>
