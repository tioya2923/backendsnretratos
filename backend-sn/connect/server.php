<?php

require_once 'cors.php';

// Dados de conexão fornecidos
$clearDbUrl = 'mysql://root:19101989Jodu!@135.181.47.213:3306/snrefeicoes';

// Parse a URL e extrai os detalhes da conexão
$url = parse_url($clearDbUrl);

// Verifique se todas as partes necessárias estão presentes
if (!isset($url["host"]) || !isset($url["user"]) || !isset($url["pass"]) || !isset($url["path"])) {
    die("URL de conexão com o banco de dados está incompleta ou incorreta.");
}
$host = $url["host"];
$user = $url["user"];
$password = $url["pass"];
$db = substr($url["path"], 1);

// Definir as variáveis de conexão
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $db);

// Conexão com a base de dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Verificar a conexão
if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
} else {
    echo "Conexão bem-sucedida!";
}

?>
