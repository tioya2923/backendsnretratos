<?php
// Lista de origens permitidas
$origensPermitidas = [
    'http://snrefeicoes.pt',
    'http://www.snrefeicoes.pt',
    'http://localhost:3000' // Caso você esteja testando localmente
];

// Verifica se a origem da requisição está na lista de permitidas
if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $origensPermitidas)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: OPTIONS, PATCH, DELETE, POST, PUT, GET');
    header('Access-Control-Allow-Headers: X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version, Authorization');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        // Finaliza a requisição para preflight de métodos OPTIONS
        exit(0);
    }
}
header('Content-Type: application/json');
?>
