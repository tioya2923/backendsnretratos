<?php
// TEMPORÁRIO — autorizado (verificar a reivindicação atómica anti-duplicado
// recém-adicionada). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';

$tokenEsperado = 'teste-claim-20260829';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

// Cria uma linha de teste igual às reais, mas para um destinatário que
// nunca vai realmente ser contactado (não chega a chamar sendEmail aqui).
$destinatario = 'teste-claim-atomico@exemplo.invalid';
$conn->query("INSERT INTO emails_pendentes (destinatario, assunto, corpo, is_html) VALUES ('$destinatario', 'teste', 'teste', 0)");
$id = $conn->insert_id;

// Simula duas "execuções" a lerem a MESMA linha (tentativas=0) ANTES de
// qualquer uma escrever — tal como aconteceria com o GitHub Actions e o
// cron-job.org a correrem quase ao mesmo tempo — e depois ambas a
// tentarem reivindicar, exatamente a query nova de
// processarEmailsPendentes() (compare-and-swap pelo valor exato lido).
$tentativasLidas = 0; // as duas "execuções" leem isto antes de qualquer escrita

$claim1 = $conn->prepare("UPDATE emails_pendentes SET tentativas = tentativas + 1 WHERE id = ? AND enviado_em IS NULL AND tentativas = ?");
$claim1->bind_param("ii", $id, $tentativasLidas);
$claim1->execute();
$resultado1 = $claim1->affected_rows;

$claim2 = $conn->prepare("UPDATE emails_pendentes SET tentativas = tentativas + 1 WHERE id = ? AND enviado_em IS NULL AND tentativas = ?");
$claim2->bind_param("ii", $id, $tentativasLidas);
$claim2->execute();
$resultado2 = $claim2->affected_rows;

// Limpa a linha de teste
$conn->query("DELETE FROM emails_pendentes WHERE id = $id");

// Mesmo teste para a reivindicação de atividades (ultima_notificacao IS
// NULL -> NOW(), atómico na mesma instrução).
$conn->query("INSERT INTO atividades_usuario (user_id, tipo, titulo, data_atividade, hora_inicio, ativo) VALUES (20, 'Outro', 'TESTE claim atomico (apagar)', '2020-01-01', '00:00:00', 1)");
$idAtv = $conn->insert_id;

$claimA1 = $conn->prepare("UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ? AND ultima_notificacao IS NULL");
$claimA1->bind_param("i", $idAtv);
$claimA1->execute();
$resultadoA1 = $claimA1->affected_rows;

$claimA2 = $conn->prepare("UPDATE atividades_usuario SET ultima_notificacao = NOW() WHERE id = ? AND ultima_notificacao IS NULL");
$claimA2->bind_param("i", $idAtv);
$claimA2->execute();
$resultadoA2 = $claimA2->affected_rows;

$conn->query("DELETE FROM atividades_usuario WHERE id = $idAtv");

echo json_encode([
    'emails_pendentes' => [
        'primeira_reivindicacao_affected_rows' => $resultado1,
        'segunda_reivindicacao_affected_rows' => $resultado2,
        'passou' => ($resultado1 === 1 && $resultado2 === 0),
    ],
    'atividades_usuario' => [
        'primeira_reivindicacao_affected_rows' => $resultadoA1,
        'segunda_reivindicacao_affected_rows' => $resultadoA2,
        'passou' => ($resultadoA1 === 1 && $resultadoA2 === 0),
    ],
]);
