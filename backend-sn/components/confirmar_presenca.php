<?php
/**
 * Confirmação de presença numa refeição — o próprio utilizador confirma,
 * dentro da janela de 1h a contar do início da refeição (13h30-14h30 para
 * o almoço; 20h00-21h00 para o jantar, ou 20h30-21h30 ao domingo/feriados,
 * tal como o horário mostrado no mapa de refeições). "Mais cedo"/"mais
 * tarde" têm janelas próprias (1h de desvio), e o Takeaway confirma-se na
 * véspera — ver janelaConfirmacao()/diaConfirmacao() em presenca_utils.php.
 *
 * O nome de cada tipo é exatamente o nome da coluna correspondente em
 * `refeicoes` — usa-se diretamente para ler a inscrição, sem mapa à parte.
 */
const TIPOS_CONFIRMACAO_VALIDOS = [
    'almoco', 'almoco_mais_cedo', 'almoco_mais_tarde',
    'jantar', 'jantar_mais_cedo', 'jantar_mais_tarde',
    'levar_refeicao',
];

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../connect/server.php';
require_once '../connect/cors.php';
require_once '../connect/auth.php';
require_once __DIR__ . '/presenca_utils.php';

header('Content-Type: application/json');
date_default_timezone_set('Europe/Lisbon');

function criarTabelaConfirmacoes($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS confirmacoes_presenca (
        id INT AUTO_INCREMENT PRIMARY KEY,
        refeicao_id INT NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        confirmado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_confirmacao (refeicao_id, tipo),
        CONSTRAINT fk_confirmacoes_refeicao FOREIGN KEY (refeicao_id) REFERENCES refeicoes(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}
criarTabelaConfirmacoes($conn);

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $userId = getAuthUserId($conn);
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Não autenticado']);
        exit;
    }

    $data       = json_decode(file_get_contents('php://input'), true);
    $refeicaoId = isset($data['refeicao_id']) ? (int) $data['refeicao_id'] : 0;
    $tipo       = $data['tipo'] ?? '';

    if ($refeicaoId <= 0 || !in_array($tipo, TIPOS_CONFIRMACAO_VALIDOS, true)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT nome_completo, data, almoco, almoco_mais_cedo, almoco_mais_tarde,
               jantar, jantar_mais_cedo, jantar_mais_tarde, levar_refeicao
        FROM refeicoes WHERE id = ?
    ");
    $stmt->bind_param("i", $refeicaoId);
    $stmt->execute();
    $refeicao = stmt_get_result($stmt)->fetch_assoc();
    $stmt->close();

    if (!$refeicao) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Inscrição não encontrada']);
        exit;
    }

    $stmtU = $conn->prepare("SELECT name FROM usuarios WHERE id = ?");
    $stmtU->bind_param("i", $userId);
    $stmtU->execute();
    $user = stmt_get_result($stmtU)->fetch_assoc();
    $stmtU->close();

    // Só o próprio pode confirmar a sua presença — comparação por nome,
    // porque a tabela refeicoes não guarda o id do utilizador (mesma
    // convenção já usada no resto da aplicação).
    if (!$user || mb_strtolower(trim($user['name']), 'UTF-8') !== mb_strtolower(trim($refeicao['nome_completo']), 'UTF-8')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Só podes confirmar a tua própria presença']);
        exit;
    }

    // O nome do tipo é o nome exato da coluna (ver TIPOS_CONFIRMACAO_VALIDOS).
    if (!$refeicao[$tipo]) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Não estás inscrito para esta refeição']);
        exit;
    }

    $diaConf = diaConfirmacao($tipo, $refeicao['data']);
    [$horaInicio, $horaFim] = janelaConfirmacao($tipo, new DateTime($diaConf));
    $inicio = DateTime::createFromFormat('Y-m-d H:i', "$diaConf $horaInicio");
    $fim    = DateTime::createFromFormat('Y-m-d H:i', "$diaConf $horaFim");
    $agora  = new DateTime();

    if ($agora < $inicio || $agora > $fim) {
        http_response_code(403);
        echo json_encode([
            'status'  => 'error',
            'message' => "A confirmação só está disponível entre as $horaInicio e as $horaFim."
        ]);
        exit;
    }

    $stmtC = $conn->prepare("INSERT IGNORE INTO confirmacoes_presenca (refeicao_id, tipo) VALUES (?, ?)");
    $stmtC->bind_param("is", $refeicaoId, $tipo);
    $stmtC->execute();
    $stmtC->close();

    echo json_encode(['status' => 'success', 'message' => 'Presença confirmada!']);
    exit;
}

if ($method === 'GET') {
    // Lista de confirmações já feitas — o frontend usa isto para mostrar o
    // "visto" junto ao nome de quem já confirmou. Sem dados sensíveis, só
    // exige sessão válida (utilizador ou admin), como o resto do mapa.
    requireAnySession($conn);
    $result = $conn->query("SELECT refeicao_id, tipo FROM confirmacoes_presenca");
    $confirmacoes = [];
    while ($row = $result->fetch_assoc()) {
        $confirmacoes[] = ['refeicao_id' => (int) $row['refeicao_id'], 'tipo' => $row['tipo']];
    }
    echo json_encode($confirmacoes);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Método não suportado']);
