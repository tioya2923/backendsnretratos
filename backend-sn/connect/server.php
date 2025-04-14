<?php
require 'cors.php'; // Habilita o CORS
//require_once 'vendor/autoload.php'; // Autoloader do Composer
require_once __DIR__ . '/../../vendor/autoload.php';

// Recuperar as variáveis do ambiente configuradas no Heroku
$dbUrl = getenv('DB_URL');
$mailUsername = getenv('MAIL_USERNAME');
$mailPassword = getenv('MAIL_PASSWORD');

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
