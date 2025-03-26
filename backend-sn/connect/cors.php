<?php

$origensPermitidas = [
    'http://localhost:3000',
    'http://135.181.47.213/frontendsnretratos/'
    ];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $origensPermitidas)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: OPTIONS, PATCH, DELETE, POST, PUT, GET');
    header('Access-Control-Allow-Headers: X-CSRF-Token, X-Requested-With, Accept, Accept-Version, Content-Length, Content-MD5, Content-Type, Date, X-Api-Version, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        // Sai do script após retornar os cabeçalhos para solicitações OPTIONS preflight
        exit(0);
    }
}

header('Content-Type: application/json');

?>