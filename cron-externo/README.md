# Disparador externo do cron (Cloudflare Workers)

Chama `enviar_lembretes.php` a cada 5 minutos, sem depender do cPanel da
PTisp. Ver `worker.js` para o código.

## Montar (≈10 min, conta gratuita)

1. Criar conta em https://dash.cloudflare.com/sign-up (plano gratuito).
2. *Workers & Pages* → *Create* → *Create Worker* → dar o nome `sn-cron` → *Deploy*.
3. *Edit code* → apagar o conteúdo → colar `worker.js` → *Deploy*.
4. *Settings* → *Variables and Secrets* → *Add*:
   - `CRON_URL` (Text): `https://api-sn.paroquiasaonicolau.pt/components/enviar_lembretes.php`
   - `CRON_TOKEN` (Secret): o valor de `EXTERNAL_CRON_TOKEN` em `backend-sn/components/enviar_lembretes.php`
5. *Settings* → *Triggers* → *Cron Triggers* → *Add* → `*/5 * * * *`.
6. Testar: abrir o endereço `https://sn-cron.<conta>.workers.dev`. Deve mostrar
   `"status": 200` e `"desafio": false`. Se mostrar `desafio: true` (ou 403 com
   página HTML), o alojamento também bloqueia a Cloudflare — usar a alternativa
   (cron do cPanel ou pedir à PTisp a isenção).
