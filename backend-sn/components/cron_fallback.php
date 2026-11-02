<?php
/**
 * Terceiro disparador do cron de lembretes, independente do GitHub Actions
 * e do cron-job.org: chamado por pedidos que a app já faz com frequência
 * enquanto alguém a tem aberta (ver mensagens.php?nao_lidas=1, a cada 12s).
 *
 * Os dois disparadores externos já falharam em dias reais (a 2026-11-02 nem
 * um nem outro chamou o servidor durante mais de 7h e o lembrete de
 * inscrição das 13:10 não saiu). Isto garante que, enquanto houver alguém
 * com a app aberta, o cron corre — o enviar_lembretes.php é idempotente
 * (marcarComoEnviado(), reivindicação atómica das filas), por isso correr
 * a mais não duplica nada.
 */
function dispararCronSeNecessario(): void {
    $intervalo = 240; // segundos entre disparos
    $marca = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sn_cron_fallback_ultimo';

    $ultimo = @filemtime($marca);
    if ($ultimo !== false && (time() - $ultimo) < $intervalo) {
        return;
    }
    // Marca já, antes de disparar, para não haver tempestade de disparos
    // concorrentes (cada app aberta pede a cada 12s).
    if (@touch($marca) === false) {
        return;
    }

    // Destino fixo, NUNCA derivado do pedido (HTTP_HOST vem do cliente —
    // enviaria o token a qualquer domínio que alguém lá pusesse).
    $base = 'https://api-sn.paroquiasaonicolau.pt';

    // Fire-and-forget: o enviar_lembretes.php usa ignore_user_abort, por
    // isso continua a correr depois de este pedido desistir à espera.
    $ch = curl_init($base . '/components/enviar_lembretes.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-External-Cron-Token: 365a3a7f1da4ea2e13c13f35ecb586f2a859f2b25e0e7595'],
        CURLOPT_CONNECTTIMEOUT_MS => 1000,
        CURLOPT_TIMEOUT_MS     => 1000,
        CURLOPT_NOSIGNAL       => 1,
    ]);
    @curl_exec($ch);
    curl_close($ch);
}
