require_once 'cors.php';
<?php
require_once 'cors.php';

// Carregar URL de conexão de uma variável de ambiente
$clearDbUrl = getenv('DB_URL');

if (!$clearDbUrl) {
    die("A variável de ambiente DB_URL não foi configurada.");
}

// Parse da URL e extração de detalhes
$url = parse_url($clearDbUrl);

// Validação das partes da URL
if (!isset($url["host"], $url["user"], $url["pass"], $url["path"])) {
    die("URL de conexão com o banco de dados está incompleta ou incorreta.");
}

$host = $url["host"];
$user = $url["user"];
$password = $url["pass"];
$db = ltrim($url["path"], '/');

// Definir constantes de conexão
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $db);

// Conexão segura ao banco de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Verificar conexão
if ($conn->connect_error) {
    die("Erro ao conectar-se ao banco de dados: " . $conn->connect_error);
}

// Informar sucesso
echo json_encode(['status' => 'Conexão bem-sucedida!']);
?>