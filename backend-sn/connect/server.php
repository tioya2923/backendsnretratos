<?php
// Importar a biblioteca Dotenv
require_once 'cors.php';
require_once __DIR__ . '/../../vendor/autoload.php';

#use Dotenv;

// Carregar variáveis do arquivo .env
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
$dotenv->load();

// Testar se o Dotenv carregou corretamente
//var_dump($_ENV['DB_URL']);
//var_dump(getenv('DB_URL'));

// Acessar a variável DB_URL
//$dbUrl = getenv('DB_URL');
//$dbUrl = $_ENV['DB_URL'];

var_dump($_ENV['DB_URL']);
var_dump(getenv('DB_URL'));

if (!$dbUrl) {
    die("A variável de ambiente DB_URL não foi carregada corretamente.");
}

// Continuar com o código do CORS
$origensPermitidas = [
    'http://snrefeicoes.pt',
    'http://www.snrefeicoes.pt',
    'http://localhost:3000',
    'http://135.181.47.213'
];

// Verificar se a origem da requisição está na lista de permitidas
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $origensPermitidas)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Retorna sem executar mais nada para as requisições OPTIONS preflight
        exit(0);
    }
}

// Seu código de processamento normal aqui
header('Content-Type: application/json');
?>