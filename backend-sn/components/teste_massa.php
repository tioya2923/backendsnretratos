<?php
// Configurações de ambiente
date_default_timezone_set('Europe/Lisbon');
require_once __DIR__ . '/../connect/server.php'; // Sua conexão DB
require_once __DIR__ . '/whatsapp_utils.php';    // Onde está a função sendWhatsApp corrigida

// 1. Buscar todos os usuários que têm número de WhatsApp preenchido
$sql = "SELECT name, whatsapp FROM usuarios WHERE whatsapp IS NOT NULL AND whatsapp != ''";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<h2>Iniciando teste de envio para " . $result->num_rows . " usuários</h2>";
    
    while($row = $result->fetch_assoc()) {
        $nome = $row['name'];
        $numero = $row['whatsapp'];
        
        // Mensagem de teste personalizada
        $mensagem = "Olá $nome, o patriarca africano manda saudações.";
        
        echo "Enviando para $nome ($numero)... ";
        
        // Chamada da função que agora aponta para http://95.217.178.106:3000
        $enviou = sendWhatsApp($numero, $mensagem);
        
        if ($enviou) {
            echo "<span style='color:green'>Sucesso!</span><br>";
        } else {
            echo "<span style='color:red'>Falha. Verifique o log do Node.js na Hetzner.</span><br>";
        }
        
        // IMPORTANTE: Pequena pausa para evitar ser banido pelo WhatsApp por spam
        usleep(500000); // 0,5 segundos de intervalo entre cada mensagem
    }
    
    echo "<h3>TioYa</h3>";
} else {
    echo "Nenhum usuário com WhatsApp encontrado na base de dados.";
}
?>