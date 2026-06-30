<?php
// Evitar envio de headers no CLI
if (php_sapi_name() === 'cli') return;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$permitida = (
    $origin === 'http://localhost:3000' ||
    str_ends_with($origin, '.herokuapp.com') ||
    str_ends_with($origin, '.onrender.com') ||
    str_ends_with($origin, '.snrefeicoes.pt') ||
    $origin === 'https://snrefeicoes.pt'
);

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
