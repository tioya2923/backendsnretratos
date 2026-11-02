# Disparador externo do cron (Cloudflare Workers)

Chama `enviar_lembretes.php` a cada 5 minutos, sem depender do cPanel da
PTisp nem do cron-job.org (bloqueado pelo filtro anti-bot do alojamento
desde 2026-10-29). Código em `worker.js`.

**Estado:** montado e a funcionar desde 2026-11-02 — Worker `sn-cron`
(`https://sn-cron.mwenhondumba.workers.dev`), Cron Trigger `*/5 * * * *`.

## Montar de raiz (≈10 min, conta gratuita)

1. Criar conta em https://dash.cloudflare.com/sign-up (plano gratuito).
2. *Workers & Pages* → *Create* → *Start with Hello World* → nome `sn-cron` → *Deploy*.
3. *Edit code* → apagar o conteúdo → colar `worker.js` → *Deploy*.
4. *Settings* → *Variables and Secrets* → *Add*:
   - tipo **Secret**, nome `CRON_TOKEN`, valor = o texto entre aspas em
     `define('EXTERNAL_CRON_TOKEN', '…')`, em
     `backend-sn/components/enviar_lembretes.php`. Marcar **Secret**.
5. *Settings* → *Trigger events* → *Cron Triggers* → *Add* → expressão `*/5 * * * *`.
6. Testar: abrir o endereço do Worker no browser. Deve mostrar
   `"status": 200` e `"desafio": false`.
   - `status 403` com `{"error":"Acesso negado"}` → o `CRON_TOKEN` está errado.
   - `desafio: true` → o alojamento também bloqueia a Cloudflare; usar o cron do cPanel.

## Ver se está a correr

Dashboard → Worker `sn-cron` → *Observability* → *Events*: deve haver uma linha
`*/5 * * * *` seguida de `status=200 desafio=false` de 5 em 5 minutos.

## Se mudar o token

Mudar `EXTERNAL_CRON_TOKEN` em `enviar_lembretes.php` (e fazer deploy do backend)
**e** o segredo `CRON_TOKEN` no Worker. O disparo pela app aberta
(`cron_fallback.php`) também tem o token fixo no código — atualizar aí.
