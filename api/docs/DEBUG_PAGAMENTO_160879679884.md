# Debug: pagamento PIX não deu baixa (prod)

**Payment ID:** `160879679884`  
**Status MP:** `approved` / `accredited`  
**Valor:** R$ 120,00  
**Método:** pix  
**Criado:** 24/05/2026, 23:12:09  

## O que a baixa depende

1. Mercado Pago envia webhook → `POST /api/webhooks/mercadopago`
2. Controller identifica `matricula_id` via `external_reference` (`MAT-{id}-...`) ou metadata
3. Grava/atualiza `pagamentos_mercadopago`
4. Se `approved` → `baixarPagamentoPlano()` (UPDATE parcela pendente ou INSERT pago)
5. Ativa matrícula / assinatura

Se qualquer passo falhar, o PIX aparece aprovado no MP mas a parcela continua pendente no painel.

---

## Passo 1 — Diagnóstico no servidor (recomendado)

No container/host PHP com `.env` de **produção**:

```bash
cd /caminho/api
php debug_pagamento_mp.php 160879679884
```

Com consulta live ao MP (substitua `N` pelo `tenant_id` da academia):

```bash
php debug_pagamento_mp.php 160879679884 --mp --tenant=N
```

Saída JSON (CI/automação):

```bash
php debug_pagamento_mp.php 160879679884 --json
```

O script verifica:

| # | O quê |
|---|--------|
| 1 | `pagamentos_mercadopago` (espelho local) |
| 2 | `webhook_payloads_mercadopago` (erros de processamento) |
| 3 | API MP (`--mp`) |
| 4–6 | Matrícula, assinatura, `pagamentos_plano` |
| 7 | Lista de causas prováveis |
| 8 | Comandos sugeridos |

---

## Passo 2 — Webhooks com erro

```bash
php database/show_webhook_payload.php last erro
```

Procure linhas com `payment_id` = `160879679884` ou erro **"Matrícula não identificada"**.

---

## Passo 3 — API (com JWT admin)

```bash
# Dados no MP
curl -s -H "Authorization: Bearer <JWT>" \
  "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/160879679884" | jq .

# Reprocessar (dispara fluxo completo do webhook)
curl -s -X POST -H "Authorization: Bearer <JWT>" \
  "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/160879679884/reprocess" | jq .
```

**Bloqueios do reprocess:** matrícula com `plano_anterior_id` ou `data_cancelamento` após o pagamento.

---

## Passo 4 — Corrigir baixa localmente (se matrícula já identificada)

```bash
php debug_pagamento_mp.php 160879679884 --mp --tenant=N --fix
```

Ou pelo job (se souber `matricula_id`):

```bash
php jobs/atualizar_pagamentos_mp.php --matricula-id=<ID>
```

---

## Causas mais comuns

| Sintoma no debug | Causa |
|------------------|--------|
| Sem webhook | MP não notificou / URL errada / timeout |
| Webhook `erro`: Matrícula não identificada | `external_reference` sem `MAT-{id}` na preferência PIX |
| `pagamentos_mercadopago` ausente | Webhook falhou antes de gravar espelho |
| MP `approved`, espelho OK, parcela pendente | `baixarPagamentoPlano` falhou ou job não rodou |
| Sem parcela pendente | Esperado INSERT; ver `error_log` PHP `[Webhook MP]` |
| `PAC-*` no external_reference | Fluxo de pacote, não matrícula avulsa |

---

## Logs em produção

```bash
# Ajuste o caminho do log do PHP no servidor
tail -f /var/log/php-error.log | grep -E "Webhook MP|160879679884|baixarPagamento"
```

---

## Checklist rápido

- [ ] `php debug_pagamento_mp.php 160879679884 --mp --tenant=N`
- [ ] Webhook chegou? (`webhook_payloads_mercadopago`)
- [ ] `external_reference` contém `MAT-{matricula_id}`?
- [ ] Existe parcela pendente em `pagamentos_plano`?
- [ ] Reprocess API ou `--fix` após corrigir causa raiz
