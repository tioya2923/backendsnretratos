<?php
// TEMPORÁRIO — autorizado pelo utilizador (2026-11-02): reenviar só por
// email o lembrete de inscrição que falhou por bloqueio de IP no Brevo.
// Remove-se logo a seguir.
date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/../connect/server.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/email_utils.php';

$tokenEsperado = 'reenvio-inscricao-20261102';
if (!hash_equals($tokenEsperado, $_SERVER['HTTP_X_DEBUG_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'acesso negado']);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(300);
$conn->set_charset("utf8mb4");

// Só uma vez.
$conn->query("CREATE TABLE IF NOT EXISTS lembretes_enviados (
    id INT AUTO_INCREMENT PRIMARY KEY, data DATE NOT NULL, tipo VARCHAR(30) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_data_tipo (data, tipo)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// 1) Teste a um endereço da própria paróquia — se falhar, pára aqui.
$erro = null;
$ok = sendEmail('retratospsn@gmail.com', 'Teste de envio (Brevo)', 'Teste após desbloqueio do IP. Pode ignorar.', false, $erro);
echo "Teste: " . ($ok ? "OK" : "FALHA $erro") . "\n";
if (!$ok) exit;

$hoje = date('Y-m-d');
$tipo = 'reenvio_insc_email';
$stmt = $conn->prepare("INSERT IGNORE INTO lembretes_enviados (data, tipo) VALUES (?, ?)");
$stmt->bind_param("ss", $hoje, $tipo);
$stmt->execute();
if ($stmt->affected_rows === 0) { echo "Reenvio já feito hoje. A sair.\n"; exit; }

// 2) Mesma regra da 5ª-feira: quem ainda não se inscreveu esta semana.
$segunda = date('Y-m-d', strtotime('monday this week'));
$domingo = date('Y-m-d', strtotime("$segunda +6 days"));
$jaInscritos = [];
$st = $conn->prepare("SELECT DISTINCT nome_completo FROM refeicoes WHERE data BETWEEN ? AND ?");
$st->bind_param("ss", $segunda, $domingo);
$st->execute();
$r = stmt_get_result($st);
while ($row = $r->fetch_assoc()) { $jaInscritos[] = mb_strtolower(trim($row['nome_completo']), 'UTF-8'); }
echo "Semana $segunda a $domingo — já inscritos: " . count($jaInscritos) . "\n";

$link = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/';
$unsub = rtrim(getenv('FRONTEND_URL') ?: '', '/') . '/unsubscribe';
$rodape = "<br><br><small>Se já não fazes parte da nossa comunidade, <a href='$unsub'>clica aqui para deixares de receber as nossas mensagens</a>.</small>";

$enviados = 0; $falhas = 0; $saltados = 0;
$res = $conn->query("SELECT name, email FROM usuarios WHERE status = 'aprovado'");
while ($u = $res->fetch_assoc()) {
    $nome = trim($u['name']);
    if (empty($u['email'])) continue;
    if (in_array(mb_strtolower($nome, 'UTF-8'), $jaInscritos)) { $saltados++; continue; }
    $body = "Olá, <strong>$nome</strong>!<br><br>Recorda-te de fazer a tua inscrição para as próximas refeições.<br><a href='$link'>Clica aqui para te inscrever</a>" . $rodape;
    $e = null;
    if (sendEmail($u['email'], 'Recordatório: Inscrição para Refeições', $body, true, $e)) { $enviados++; }
    else { $falhas++; echo "FALHA $nome: $e\n"; }
    usleep(200000);
}
echo "Enviados: $enviados | Falhas: $falhas | Já inscritos (saltados): $saltados\n";
