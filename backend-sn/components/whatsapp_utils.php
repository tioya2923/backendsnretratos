<?php
/**
 * Envia uma mensagem de WhatsApp através do servidor Node.js na Hetzner.
 * Otimizado para PHP 8.0+ e com lógica de re-tentativa para maior fiabilidade.
 * * @param string $to O número de destino (será limpo automaticamente)
 * @param string $message O texto da mensagem a enviar
 * @return bool Retorna true em caso de sucesso, false após falha nas tentativas
 */
function sendWhatsApp($to, $message) {
    // 1. Limpeza rigorosa do número: remove espaços, traços, símbolos (+) e letras.
    $to = preg_replace('/\D/', '', $to);

    if (empty($to)) {
        error_log("Erro sendWhatsApp: Número de destino está vazio.");
        return false;
    }

    $url = 'http://95.217.178.106:3000/send-message';
    $payload = json_encode([
        'number'  => $to,
        'message' => $message
    ]);

    $maxTentativas = 2; // Tenta uma segunda vez se a primeira falhar com erro 500
    $tentativaAtual = 0;

    while ($tentativaAtual < $maxTentativas) {
        $tentativaAtual++;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        // Timeouts ajustados para produção externa
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); 

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        // No PHP 8.0+, curl_close é opcional. Para evitar o log de "Deprecated" em versões 8.5+:
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        // Se correu tudo bem (Código 200)
        if (!$curlError && $httpCode === 200) {
            return true;
        }

        // Se chegamos aqui, houve erro. Vamos logar a falha.
        error_log("Tentativa $tentativaAtual falhou para $to: HTTP $httpCode | Erro: $curlError | Resposta: $response");

        // Se for a última tentativa, desistimos. Caso contrário, esperamos 1 segundo antes de repetir.
        if ($tentativaAtual < $maxTentativas) {
            usleep(1000000); // Espera 1 segundo (1.000.000 microsegundos)
        }
    }

    return false;
}
?>