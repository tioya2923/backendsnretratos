<?php
require_once 'cors.php';
require_once 'vendor/autoload.php';

// Carrega as variáveis do arquivo .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Recupera a variável DB_URL do .env
$clearDbUrl = getenv('DB_URL');

if (!$clearDbUrl) {
    die(json_encode(['error' => 'A variável DB_URL não foi configurada.']));
}

$url = parse_url($clearDbUrl);

if (!isset($url["host"], $url["user"], $url["pass"], $url["path"])) {
    die(json_encode(['error' => 'URL de conexão ao banco está incompleta.']));
}

$host = $url["host"];
$user = $url["user"];
$password = $url["pass"];
$db = ltrim($url["path"], '/');

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