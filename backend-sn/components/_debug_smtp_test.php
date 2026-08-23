<?php
// Script de diagnóstico temporário: testa a conectividade SMTP de saída
// a partir do servidor, sem enviar nenhum email real nem tocar na BD.
// Apagar logo a seguir a confirmar a causa.
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

$results = [];

// 1) Teste de socket puro, timeout curto — confirma rapidamente se a
// porta está bloqueada pela firewall (sem esperar pelo timeout longo
// do PHPMailer).
foreach ([['smtp.gmail.com', 465], ['smtp.gmail.com', 587]] as [$host, $port]) {
    $start = microtime(true);
    $fp = @fsockopen("ssl://$host", $port, $errno, $errstr, 6);
    $elapsed = round(microtime(true) - $start, 2);
    if ($fp) {
        $results["fsockopen_{$host}_{$port}"] = "OK em {$elapsed}s";
        fclose($fp);
    } else {
        $results["fsockopen_{$host}_{$port}"] = "FALHOU em {$elapsed}s: [$errno] $errstr";
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
