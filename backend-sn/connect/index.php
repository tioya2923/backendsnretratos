
Página para a Comunidade Paroquial de São Nicolau: REFEIÇÕES.
<?php
// Roteador simples para /api/unsubscribe
if (preg_match('#^/api/unsubscribe$#', $_SERVER['REQUEST_URI'])) {
	require_once __DIR__ . '/components/unsubscribe.php';
	exit();
}
/*
require 'vendor/autoload.php';



$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
$dotenv->load();

echo "Recuperar o ambiente: " . getenv('DB_URL') . "<br>";
echo "Recuperar o ambiente: " . $_ENV['DB_URL'] . "<br>";
echo "Recuperar o ambiente: " . $_SERVER['DB_URL'] . "<br>";
*/
?>