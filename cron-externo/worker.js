// Cloudflare Worker — chama o enviar_lembretes.php a cada 5 minutos.
//
// Existe porque o cron-job.org passou a receber do alojamento uma página de
// desafio anti-bot (desde a mudança de IP de 2026-10-29) e o GitHub Actions
// não cumpre a cadência. Isto é um terceiro disparador, independente de
// ambos e do cPanel. O enviar_lembretes.php é idempotente, por isso correr
// a mais (com os outros disparadores) não duplica nada.
//
// Variáveis do Worker (Settings → Variables and Secrets):
//   CRON_TOKEN  (Secret)  o valor de EXTERNAL_CRON_TOKEN em enviar_lembretes.php
//   CRON_URL    (Text)    https://api-sn.paroquiasaonicolau.pt/components/enviar_lembretes.php

async function chamar(env) {
  const resp = await fetch(env.CRON_URL, {
    headers: { 'X-External-Cron-Token': env.CRON_TOKEN },
    redirect: 'manual',
  });
  const corpo = await resp.text();
  const desafio = /challenge|captcha|cf-chl|just a moment/i.test(corpo);
  return { status: resp.status, desafio, corpo: corpo.slice(0, 1500) };
}

export default {
  // Disparo agendado (Cron Trigger).
  async scheduled(event, env, ctx) {
    ctx.waitUntil((async () => {
      const r = await chamar(env);
      console.log(`status=${r.status} desafio=${r.desafio}`);
      if (r.status !== 200) console.log(r.corpo);
    })());
  },

  // Teste manual: abrir o endereço do Worker no browser mostra o resultado.
  async fetch(request, env) {
    const r = await chamar(env);
    return new Response(JSON.stringify(r, null, 2), {
      headers: { 'content-type': 'application/json; charset=utf-8' },
    });
  },
};
