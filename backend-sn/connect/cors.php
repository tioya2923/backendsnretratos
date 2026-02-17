<?php
// Evitar envio de headers no CLI
if (php_sapi_name() === 'cli') return;
// Lista de origens permitidas
$origensPermitidas = [
    //'https://snrefeicoes.pt',
    //'https://www.snrefeicoes.pt',
    'https://snref-fronten-8dbe187fda6c.herokuapp.com',
    'http://localhost:3000',
];

// Verifica se a origem da requisição está na lista de permitidas
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $origensPermitidas)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
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
