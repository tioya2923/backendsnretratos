<?php
// TEMPORÁRIO — autorizado (testar a remoção de grupo de uma refeição, de
// ponta a ponta via HTTP real). Remove-se a seguir.
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../connect/auth.php';

$tokenEsperado = 'teste-remover-grupo-20260831';
$recebido = $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '';
if (!hash_equals($tokenEsperado, $recebido)) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

// 1. Cria um grupo de teste
$nomeGrupo = 'TESTE remover grupo (apagar)';
$conn->query("INSERT INTO Grupos (nome_grupo, numero_pessoas) VALUES ('" . $conn->real_escape_string($nomeGrupo) . "', 3)");
$grupoId = $conn->insert_id;

// 2. Marca-o para duas refeições de teste
$conn->query("INSERT INTO refeicoes_grupos (grupo_id, tipo_refeicao, data_refeicao, hora_refeicao, local_refeicao) VALUES ($grupoId, 'Almoço', '2026-09-01', '13:30:00', 'Salão')");
$refeicaoId1 = $conn->insert_id;
$conn->query("INSERT INTO refeicoes_grupos (grupo_id, tipo_refeicao, data_refeicao, hora_refeicao, local_refeicao) VALUES ($grupoId, 'Jantar', '2026-09-02', '20:00:00', 'Refeitório')");
$refeicaoId2 = $conn->insert_id;

// 3. Cria uma sessão de admin temporária, para chamar o endpoint real por
// HTTP (não só testar a query SQL isolada) exatamente como o frontend faz.
criarTabelaAdminSessoes($conn);
$tokenAdminTeste = bin2hex(random_bytes(16));
$resAdmin = $conn->query("SELECT id_admin FROM admins LIMIT 1");
$adminId = $resAdmin->fetch_assoc()['id_admin'];
$conn->query("INSERT INTO admin_sessoes (admin_id, token) VALUES ($adminId, '$tokenAdminTeste')");

// 4. Chama o endpoint real via HTTP, removendo só a primeira marcação
$ch = curl_init('https://api-sn.paroquiasaonicolau.pt/components/grupo_refeicao.php');
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'DELETE',
    CURLOPT_POSTFIELDS => json_encode(['id' => $refeicaoId1]),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', "Authorization: Bearer $tokenAdminTeste"],
    CURLOPT_RETURNTRANSFER => true,
]);
$respostaDelete = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 5. Confirma o estado final: a 1ª marcação deve ter desaparecido, a 2ª
// deve continuar, e o grupo em si deve continuar a existir.
$restantes = $conn->query("SELECT id, tipo_refeicao FROM refeicoes_grupos WHERE grupo_id = $grupoId")->fetch_all(MYSQLI_ASSOC);
$grupoAindaExiste = $conn->query("SELECT id FROM Grupos WHERE id = $grupoId")->num_rows > 0;

// 6. Limpa tudo (grupo, marcações restantes, sessão de admin de teste)
$conn->query("DELETE FROM refeicoes_grupos WHERE grupo_id = $grupoId");
$conn->query("DELETE FROM Grupos WHERE id = $grupoId");
$conn->query("DELETE FROM admin_sessoes WHERE token = '$tokenAdminTeste'");

echo json_encode([
    'http_code_delete' => $httpCode,
    'resposta_delete' => json_decode($respostaDelete, true),
    'marcacoes_restantes_antes_da_limpeza' => $restantes,
    'grupo_ainda_existe' => $grupoAindaExiste,
    'passou' => ($httpCode === 200 && count($restantes) === 1 && $restantes[0]['tipo_refeicao'] === 'Jantar' && $grupoAindaExiste === true),
], JSON_PRETTY_PRINT);
