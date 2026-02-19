<?php
function sendWhatsApp($to, $message) {
    // Altere o IP para o da sua Hetzner
    $url = 'http://95.217.178.106:3000/send-message'; 

    $data = [
        'number' => $to,
        'message' => $message
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 15 // Aumentei um pouco o timeout para conexões externas
        ]
    ];

    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        // Pega o erro detalhado para o log
        $error = error_get_last();
        error_log('Erro ao enviar WhatsApp: ' . $error['message']);
        return false;
    }

    return true;
}
?>