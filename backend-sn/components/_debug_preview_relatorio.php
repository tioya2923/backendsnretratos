<?php
// TEMPORÁRIO — autorizado (enviar pré-visualização do relatório com o
// novo formato de tabela). Usa gerarCorpoRelatorioQuinzenal() real, não
// uma cópia — o que se vê aqui é exatamente o que sai nos envios
// oficiais. Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/email_utils.php';
require_once __DIR__ . '/presenca_utils.php';
require_once __DIR__ . '/relatorio_utils.php';

$tokenEsperado = 'preview-relatorio-20260901';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Lisbon');

// Mesmo período do último relatório real enviado (16 a 31/08/2026).
$inicio = '2026-08-16';
$fim    = '2026-08-31';
$periodoFormatado = '16/08/2026 a 31/08/2026';

$relatorio = gerarCorpoRelatorioQuinzenal($conn, $inicio, $fim, $periodoFormatado);

$erro = null;
$ok = sendEmail('mwenhondumba@gmail.com', '[Pré-visualização] Relatório quinzenal de inscrições (' . $periodoFormatado . ')', $relatorio['body'], true, $erro);

echo json_encode([
    'sucesso' => $ok,
    'erro' => $erro,
    'totalConfirmaram' => $relatorio['totalConfirmaram'],
    'totalFaltaram' => $relatorio['totalFaltaram'],
    'totalNaoInscritos' => $relatorio['totalNaoInscritos'],
]);
