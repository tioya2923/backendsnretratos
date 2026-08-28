<?php
require 'cors.php'; // Habilita o CORS
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/mysqli_polyfill.php'; // stmt_get_result(): funciona com ou sem mysqlnd

// Carregar variáveis do .env corretamente a partir da raiz do projeto.
// .env.deploy é opcional e só existe se for gerado pelo deploy do GitHub
// Actions (a partir de um Secret do repositório) — permite configurar
// coisas como o BREVO_API_KEY sem precisar de editar ficheiros no
// servidor via cPanel. Nunca escreve nem apaga o .env principal.
if (class_exists('Dotenv\Dotenv')) {
    // shortCircuit=false é essencial aqui — por omissão o Dotenv para na
    // primeira lista que encontrar (.env, que existe sempre), e nunca
    // chegaria a ler o .env.deploy a seguir.
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2), ['.env', '.env.deploy'], false);
    $envVars = $dotenv->safeLoad();
    // Forçar variáveis do .env para getenv, $_ENV e $_SERVER
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Tentar buscar DB_URL de todas as fontes possíveis
$dbUrl = getenv('DB_URL');
if (!$dbUrl) $dbUrl = $_ENV['DB_URL'] ?? null;
if (!$dbUrl) $dbUrl = $_SERVER['DB_URL'] ?? null;

// DEBUG - Log para diagnosticar qual variável foi carregada
error_log('DEBUG: getenv(DB_URL)=' . (getenv('DB_URL') ? 'SET' : 'UNSET'));
error_log('DEBUG: $_ENV[DB_URL]=' . ($_ENV['DB_URL'] ? 'SET' : 'UNSET'));
error_log('DEBUG: $_SERVER[DB_URL]=' . ($_SERVER['DB_URL'] ? 'SET' : 'UNSET'));
error_log('DEBUG: dbUrl final=' . ($dbUrl ? substr($dbUrl, 0, 40) : 'NULO'));

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

// DEBUG - Mostrar o resultado do parse_url
error_log('DEBUG: parse_url result: ' . json_encode($url));

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

// DEBUG - Testar DNS antes de conectar
error_log('DEBUG: Host to connect: ' . $host);
$ip = @gethostbyname($host);
error_log('DEBUG: DNS resolution for ' . $host . ' = ' . $ip);

// Definir constantes para conexão ao banco de dados
define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASSWORD', $password);
define('DB_NAME', $db);
define('DB_PORT', $port);

// Alguns fornecedores de base de dados exigem TLS na ligação. Ativar com
// DB_SSL=true. DB_SSL_CA (opcional) deve conter o conteúdo do certificado
// CA (ca.pem) fornecido por esse fornecedor; sem ele a ligação é cifrada
// mas o certificado do servidor não é validado. Numa base de dados local
// no mesmo servidor (o caso normal na PTisp), isto fica desligado.
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
