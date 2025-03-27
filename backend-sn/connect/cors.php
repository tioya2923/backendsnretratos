<?php
// Lista de origens permitidas
$origensPermitidas = [
    'http://snrefeicoes.pt',
    'http://www.snrefeicoes.pt',
    'http://localhost:3000'
];

// Verifica se a origem da requisição está na lista de permitidas
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
