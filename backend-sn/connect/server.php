<?php
require 'cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';
//require '../../vendor/autoload.php';
//require_once '../connect/server.php';
//require_once '../connect/cors.php';
//require_once '../../vendor/autoload.php';
// Carregar o arquivo .env
$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

// Recuperar a variável DB_URL do ambiente
$dbUrl = getenv('DB_URL');

if (!$dbUrl) {
    die(json_encode(['error' => 'A variável DB_URL não foi carregada.']));
}

// Exibir a URL para depuração (opcional)
//echo "Recuperar o ambiente: " . $dbUrl . "<br>";

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