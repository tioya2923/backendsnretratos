<?php
// Função para enviar mensagem WhatsApp via API HTTP local (WPPConnect)
function sendWhatsApp($to, $message) {
    $url = 'http://localhost:3000/send-message';

    $data = [
        'number' => $to,
        'message' => $message
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 10
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        error_log('Erro ao enviar WhatsApp via WPPConnect');
        return false;
    }

    return true;
}
?>