# Debug: PIX R$ 70 — matrícula #251 (STEFANY ALVES)

**Payment ID:** `161260132980`  
**External reference:** `MAT-251-1779910615`  
**Status MP:** `approved` / `accredited`  
**Valor:** R$ 70,00  
**Método:** pix  
**Criado:** 27/05/2026, 16:36:56  

Mesma classe de problema do caso [#235 / payment 160879679884](DEBUG_PAGAMENTO_160879679884.md): parcelas duplicadas ou baixa na parcela errada → matrícula continua **vencida** mesmo com PIX pago.

---

## 1. Diagnóstico (no servidor)

```bash
cd ~/domains/appcheckin.com.br/public_html/api   # ajuste o caminho se necessário

/opt/alt/php83/usr/bin/php debug_pagamento_mp.php 161260132980 --tenant=3
/opt/alt/php83/usr/bin/php debug_pagamento_mp.php 161260132980 --mp --tenant=3
/opt/alt/php83/usr/bin/php debug_matricula_status.php 251 --tenant=3
/opt/alt/php83/usr/bin/php database/show_webhook_payload.php payment 161260132980
```

Confira na saída:

| O quê | Esperado se OK |
|--------|----------------|
| `pagamentos_mercadopago` | registro com `matricula_id` = 251 |
| `pagamentos_plano` pago | **uma** parcela R$ 70 com `data_pagamento` e MP id |
| Matrícula #251 | status **ativa** |
| Parcelas abertas | **nenhuma** com valor divergente |

Se houver **duas** parcelas (ex.: uma R$ 70 antiga cancelada + outra paga na errada), use o passo 2.

---

## 2. Corrigir baixa / status

```bash
# Sincronizar pelo payment (match por valor + atualiza status matrícula)
/opt/alt/php83/usr/bin/php jobs/atualizar_pagamentos_mp.php --payment-id=161260132980 --tenant=3

# Ou diagnóstico + fix integrado
/opt/alt/php83/usr/bin/php debug_pagamento_mp.php 161260132980 --mp --tenant=3 --fix
```

Se o PIX foi baixado na **parcela errada** (valor/data), identifique IDs no debug e:

```bash
# Exemplo — substitua IDs reais da saída do debug
/opt/alt/php83/usr/bin/php jobs/corrigir_baixa_parcela_mp.php \
  --parcela-id=PARCELA_CORRETA --payment-id=161260132980 --tenant=3 \
  --cancelar-parcela-errada=PARCELA_ERRADA

/opt/alt/php83/usr/bin/php jobs/cancelar_parcela_plano.php --parcela-id=PARCELA_DUPLICATA --tenant=3
```

Revalidar:

```bash
/opt/alt/php83/usr/bin/php debug_matricula_status.php 251 --tenant=3
```

---

## 3. Deploy preventivo (obrigatório)

O app em produção usa **`POST /mobile/comprar-plano`** na API Slim (`api.appcheckin.com.br`), não apiV2.

Subir para produção:

- `api/app/Models/PagamentoPlano.php` — `garantirParcelaPendenteUnica()`
- `api/app/Controllers/MobileController.php` — compra + `gerarPagamentoPix`
- `api/app/Controllers/MercadoPagoWebhookController.php` — `ORDER BY ABS(valor - ?)`
- `api/jobs/atualizar_pagamentos_mp.php`

(apiV2: `MobileCompraPlanoService` / `PagamentoPlanoService` — para quando migrar o app para `/v2`.)

---

## 4. Reprocessar webhook (opcional)

Com JWT admin:

```bash
curl -s -X POST -H "Authorization: Bearer <JWT>" \
  "https://api.appcheckin.com.br/api/webhooks/mercadopago/payment/161260132980/reprocess"
```
