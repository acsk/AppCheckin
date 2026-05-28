# PIX: relatório para colar no chat (Cursor)

## Um comando (recomendado)

No servidor de produção:

```bash
cd ~/domains/appcheckin.com.br/public_html/api   # ajuste o caminho

/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php --tenant=3 --days=7
```

Copie **tudo** entre `=== RELATORIO PIX INICIO ===` e `=== RELATORIO PIX FIM ===` e cole na conversa.

### Pagamento específico

```bash
/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php \
  --tenant=3 --payment-id=161301089356
```

### Tentar corrigir pendentes listados no relatório

```bash
/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php --tenant=3 --days=7 --fix
```

(Só funciona após deploy do `jobs/atualizar_pagamentos_mp.php` versão **somente PIX**.)

---

## O que o relatório mostra

| Bloco | Significado |
|--------|-------------|
| **PIX sem baixa** | `pagamentos_pix` existe, parcela **não** tem `Payment #id` |
| **espelho=AUSENTE** | Webhook não gravou `pagamentos_mercadopago` |
| **webhooks=0** | Nenhum payload salvo → MP provavelmente não notificou |
| **MP live** | Status real no Mercado Pago (com `--payment-id`) |
| **Webhooks salvos** | Últimos eventos que **chegaram** na API |
| **Job log** | Últimas linhas do cron PIX |
| **PHP error_log** | Linhas `[Webhook MP]` se o caminho for encontrado |

---

## Se o PHP error_log não aparecer

Hostinger / cPanel:

1. **Logs** → **Error Log** do domínio `api.appcheckin.com.br`
2. Baixe ou copie linhas com `[Webhook MP]` ou o `payment_id`
3. Rode com caminho explícito:

```bash
/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php --tenant=3 \
  --php-log=/home/u304177849/logs/SEU_ARQUIVO.php.error.log
```

---

## Comandos extras (opcional)

```bash
/opt/alt/php83/usr/bin/php debug_pagamento_mp.php <payment_id> --mp --tenant=3
/opt/alt/php83/usr/bin/php database/show_webhook_payload.php payment <payment_id>
tail -50 storage/logs/atualizar_pagamentos_mp.log
```

---

## Minimizar recorrência (checklist deploy)

1. `jobs/atualizar_pagamentos_mp.php` — **somente PIX** + cron a cada 15 min  
2. `scripts/relatorio_pix_saude.php` — este relatório  
3. Painel MP: URL `https://api.appcheckin.com.br/api/webhooks/mercadopago`  
4. Cron exemplo:

```cron
*/15 * * * * cd /caminho/api && /opt/alt/php83/usr/bin/php jobs/atualizar_pagamentos_mp.php --tenant=3 >> storage/logs/atualizar_pagamentos_mp.log 2>&1
```
