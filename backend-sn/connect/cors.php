<?php
// Evitar envio de headers no CLI
if (php_sapi_name() === 'cli') return;
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$defaultAllowedOrigins = [
    'http://localhost:3000',
    'http://127.0.0.1:3000',
    'https://sn.paroquiasaonicolau.pt',
];
$allowedOrigins = $defaultAllowedOrigins;
$allowedEnv = getenv('ALLOWED_ORIGINS');
if ($allowedEnv) {
    $providedOrigins = array_filter(array_map('trim', explode(',', $allowedEnv)));
    if (!empty($providedOrigins)) {
        $allowedOrigins = $providedOrigins;
    }
}
$permitida = in_array($origin, $allowedOrigins, true);
if ($origin && $permitida) {
    header("Access-Control-Allow-Origin: " . $origin);
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0); // Preflight request
    }
}
// Seu código de processamento normal aqui
header('Content-Type: application/json');
?>
