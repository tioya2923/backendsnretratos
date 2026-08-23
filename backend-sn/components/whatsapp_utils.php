<?php
/**
 * Envia uma mensagem de WhatsApp através da WhatsApp Business Cloud API
 * (Meta) — https://developers.facebook.com/docs/whatsapp/cloud-api
 *
 * Substituiu a antiga ponte Node.js/whatsapp-web.js alojada na Hetzner:
 * aquela abordagem não era oficial (simulava uma sessão de WhatsApp Web),
 * exigia um processo a correr permanentemente (não é possível em hosting
 * partilhado como a PTisp) e corria risco de bloqueio do número pela Meta.
 * A Cloud API é oficial, é só um pedido HTTPS (funciona em qualquer sítio,
 * incluindo a PTisp) e não fica sujeita ao bloqueio de portas não-standard
 * que já afetou a antiga ponte (porta 3000) e o SMTP (465/587).
 *
 * Importante: mensagens iniciadas pela aplicação (lembretes, avisos) fora
 * de uma janela de 24h em que o utilizador tenha escrito primeiro só podem
 * ser enviadas através de um TEMPLATE pré-aprovado pela Meta — não é
 * permitido enviar texto livre à vontade como acontecia antes. Por isso
 * esta função recebe o nome do template e os valores para preencher as
 * variáveis {{1}}, {{2}}, ..., não o texto já composto.
 *
 * @param string $to           Número de destino, com indicativo do país,
 *                              sem "+" (é limpo automaticamente na mesma).
 * @param string $templateName Nome exato do template, tal como aprovado
 *                              na Meta (WhatsApp Manager).
 * @param array  $params       Valores para preencher {{1}}, {{2}}, ... do
 *                              corpo do template, por ordem. Vazio se o
 *                              template não tiver variáveis.
 * @param string $lang         Código de idioma do template (default pt_PT).
 * @return bool Retorna true em caso de sucesso, false em caso de falha.
 */
function sendWhatsApp($to, $templateName, array $params = [], $lang = 'pt_PT') {
    // 1. Limpeza rigorosa do número: remove espaços, traços, símbolos (+) e letras.
    $to = preg_replace('/\D/', '', $to);

    if (empty($to)) {
        error_log("Erro sendWhatsApp: Número de destino está vazio.");
        return false;
    }

    $token         = getenv('WHATSAPP_CLOUD_TOKEN');
    $phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
    $apiVersion    = getenv('WHATSAPP_API_VERSION') ?: 'v21.0';

    // Sem estas duas variáveis não há como enviar — falha de forma
    // silenciosa (fica em log) em vez de rebentar todo o pedido HTTP que
    // chamou esta função (ex.: um registo ou um lembrete em massa).
    if (empty($token) || empty($phoneNumberId)) {
        error_log("sendWhatsApp: WHATSAPP_CLOUD_TOKEN / WHATSAPP_PHONE_NUMBER_ID não configurados — mensagem para $to não enviada (template: $templateName).");
        return false;
    }

    $template = [
        'name'     => $templateName,
        'language' => ['code' => $lang],
    ];

    if (!empty($params)) {
        $template['components'] = [[
            'type'       => 'body',
            'parameters' => array_map(
                fn($valor) => ['type' => 'text', 'text' => (string) $valor],
                array_values($params)
            ),
        ]];
    }

    $payload = json_encode([
        'messaging_product' => 'whatsapp',
        'to'                => $to,
        'type'              => 'template',
        'template'          => $template,
    ]);

    $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);

    // A Cloud API corre em HTTPS (porta 443) — ao contrário da antiga ponte
    // na Hetzner (porta 3000), não deverá haver bloqueio pela firewall da
    // PTisp, mas mantém-se um timeout curto por segurança/previsibilidade.
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if (PHP_VERSION_ID < 80000) {
        curl_close($ch);
    }

    if (!$curlError && $httpCode === 200) {
        return true;
    }

    error_log("sendWhatsApp falhou para $to (template: $templateName): HTTP $httpCode | Erro: $curlError | Resposta: $response");
    return false;
}
