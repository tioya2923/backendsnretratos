<?php
/**
 * Envia uma mensagem de WhatsApp através do servidor Node.js na Hetzner.
 * * @param string $to O número de destino (ex: 351920124925 ou +351 920-124-925)
 * @param string $message O texto da mensagem a enviar
 * @return bool Retorna true em caso de sucesso, false em caso de falha
 */
function sendWhatsApp($to, $message) {
    // 1. Limpeza rigorosa do número: remove espaços, traços, símbolos (+) e letras.
    // O WPPConnect na Hetzner precisa apenas da sequência numérica.
    $to = preg_replace('/\D/', '', $to);

    // Validação básica: se o número estiver vazio após a limpeza, cancela o envio.
    if (empty($to)) {
        error_log("Erro sendWhatsApp: Número de destino está vazio.");
        return false;
    }

    // 2. Configuração do endpoint do servidor Hetzner
    $url = 'http://95.217.178.106:3000/send-message';

    // 3. Preparação dos dados em formato JSON
    $payload = json_encode([
        'number'  => $to,
        'message' => $message
    ]);

    // 4. Inicialização e configuração do cURL
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Retorna a resposta como string
    
    // Cabeçalhos essenciais para que o Node.js entenda o corpo da requisição
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    // Definição de Timeouts para evitar que o PHP fique travado se a Hetzner falhar
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // Tempo máximo para estabelecer a ligação
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);         // Tempo máximo para receber a resposta

    // 5. Execução da chamada
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);

    // 6. Verificação de erros
    if ($curlError) {
        // Erro de rede (ex: servidor desligado ou IP inacessível)
        error_log("Erro cURL na Hetzner: " . $curlError);
        return false;
    }

    if ($httpCode !== 200) {
        // O servidor respondeu, mas com erro (ex: 400 Bad Request ou 500 Erro Interno)
        error_log("Erro API Hetzner: Código HTTP $httpCode - Resposta: $response");
        return false;
    }

    // Se chegou aqui, a mensagem foi entregue ao motor do WhatsApp com sucesso
    return true;
}
?>