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

// 2) Relay local do próprio servidor (porta 25, sem TLS) — muitas vezes
// disponível mesmo quando SMTP externo está bloqueado.
$start = microtime(true);
$fp = @fsockopen("127.0.0.1", 25, $errno, $errstr, 6);
$elapsed = round(microtime(true) - $start, 2);
if ($fp) {
    $results["fsockopen_localhost_25"] = "OK em {$elapsed}s";
    fclose($fp);
} else {
    $results["fsockopen_localhost_25"] = "FALHOU em {$elapsed}s: [$errno] $errstr";
}

// 3) Função nativa mail() do PHP — usa o sendmail/MTA local diretamente,
// sem qualquer ligação de rede explícita.
$results["mail_function_exists"] = function_exists('mail') ? 'sim' : 'não';
if (function_exists('mail')) {
    $start = microtime(true);
    $ok = @mail('retratospsn@gmail.com', 'Teste de diagnóstico (apagar)', 'Teste de diagnóstico da função mail() nativa do PHP.');
    $elapsed = round(microtime(true) - $start, 2);
    $results["mail_function_result"] = ($ok ? 'devolveu true' : 'devolveu false') . " em {$elapsed}s";
}

echo json_encode($results, JSON_PRETTY_PRINT);
