# Deploy: correção PIX + matrícula vencida fantasma

Checklist rápido para produção (`api.appcheckin.com.br`, tenant 3).

---

## 1. Arquivos que precisam estar no servidor

```text
api/app/Models/PagamentoPlano.php
api/app/Controllers/MercadoPagoWebhookController.php
api/app/Controllers/MobileController.php
api/jobs/atualizar_pagamentos_mp.php
api/jobs/cancelar_parcela_plano.php
api/debug_pagamento_mp.php
api/scripts/relatorio_pix_saude.php
api/scripts/destravar_matricula_vencida.php
```

Opcional (apiV2, se usar `/v2/mobile`):

```text
apiV2/app/Services/PagamentoPlanoService.php
apiV2/app/Services/Mobile/MobileCompraPlanoService.php
apiV2/app/Services/Mobile/MobilePagamentoService.php
```

---

## 2. Deploy (no servidor)

```bash
cd ~/domains/appcheckin.com.br/public_html/api
git pull   # ou copiar os arquivos acima via FTP/rsync
```

---

## 3. Cron PIX (somente PIX, a cada 15 min)

```bash
crontab -e
```

Adicione (ajuste o caminho se necessário):

```cron
*/15 * * * * cd /home/u304177849/domains/appcheckin.com.br/public_html/api && /opt/alt/php83/usr/bin/php jobs/atualizar_pagamentos_mp.php --tenant=3 >> storage/logs/atualizar_pagamentos_mp.log 2>&1
```

Confirme:

```bash
crontab -l | grep atualizar_pagamentos_mp
```

---

## 4. Teste pós-deploy (2 min)

```bash
cd ~/domains/appcheckin.com.br/public_html/api

# Saúde geral PIX (7 dias)
/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php --tenant=3 --days=7

# Caso conhecido resolvido (deve estar ativa)
/opt/alt/php83/usr/bin/php debug_matricula_status.php 251 --tenant=3

# Job PIX manual (não deve listar approved sem baixa, exceto cancelled)
/opt/alt/php83/usr/bin/php jobs/atualizar_pagamentos_mp.php --tenant=3

tail -20 storage/logs/atualizar_pagamentos_mp.log
```

Esperado no log do job:

- `↪️  Modo: somente PIX (pagamentos_pix)`
- `🧹 N duplicata(s) cancelada(s)` (quando houver legado)
- `Job finalizado (PIX)`

---

## 5. Se aluno ainda aparecer vencido com PIX pago

```bash
# Diagnóstico
/opt/alt/php83/usr/bin/php scripts/relatorio_pix_saude.php --tenant=3 --payment-id=PAYMENT_ID
/opt/alt/php83/usr/bin/php debug_matricula_status.php MATRICULA_ID --tenant=3

# Correção automática (duplicata + status)
/opt/alt/php83/usr/bin/php scripts/destravar_matricula_vencida.php --matricula-id=MATRICULA_ID --tenant=3
```

---

## 6. Mercado Pago (paralelo — reduzir dependência do cron)

No painel MP da conta do tenant 3:

- URL de notificação: `https://api.appcheckin.com.br/api/webhooks/mercadopago`
- Eventos de **pagamentos** ativos
- Verificar entregas falhas para payments recentes

---

## O que mudou (resumo)

| Antes | Depois |
|-------|--------|
| Cron só assinaturas 2 dias | Cron varre `pagamentos_pix` 7 dias |
| Baixa na parcela **mais antiga** | Baixa na **mais recente** (mesmo valor) |
| Duplicata deixava matrícula vencida | `cancelarParcelasDuplicadasAposBaixa` automático |
| Correção só manual (`destravar`) | Cron + webhook limpam duplicata |
