
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
$port = $url["port"] ?? 3306;

// Definir constantes para conexão ao banco de dados
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $db);
define('DB_PORT', $port);

// Fornecedores como o Aiven exigem TLS na ligação. Ativar com DB_SSL=true.
// DB_SSL_CA (opcional) deve conter o conteúdo do certificado CA (ca.pem)
// descarregado da consola do Aiven; sem ele a ligação é cifrada mas o
// certificado do servidor não é validado.
$useSsl = filter_var(getenv('DB_SSL') ?: 'false', FILTER_VALIDATE_BOOLEAN);

try {
    $conn = mysqli_init();

    if ($useSsl) {
        $caPem = getenv('DB_SSL_CA') ?: null;
        if ($caPem) {
            $caFile = sys_get_temp_dir() . '/db_ssl_ca.pem';
            if (!file_exists($caFile)) {
                file_put_contents($caFile, str_replace('\n', "\n", $caPem));
            }
            mysqli_ssl_set($conn, null, null, $caFile, null, null);
        } else {
            $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
            mysqli_ssl_set($conn, null, null, null, null, null);
        }
    }

    $connected = $conn->real_connect(
        DB_HOST,
        DB_USER,
        DB_PASSWORD,
        DB_NAME,
        (int) DB_PORT,
        null,
        $useSsl ? MYSQLI_CLIENT_SSL : 0
    );

    if (!$connected) {
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
