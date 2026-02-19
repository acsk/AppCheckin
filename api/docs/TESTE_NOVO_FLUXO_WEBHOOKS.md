# 🧪 Guia de Testes: Novo Fluxo de Webhooks para Pacotes

## 📋 Antes de Testes

Certifique-se de que:
1. ✅ Migração executada: `pacote_contrato_id` coluna existe em `assinaturas`
2. ✅ Código deploy: novo `MercadoPagoWebhookController.php` em produção
3. ✅ PHP reiniciado: `sudo systemctl restart php8.2-fpm`
4. ✅ Banco atualizado: tabelas com novas colunas

---

## 🧬 Teste 1: Validar Coluna Adicionada

### Via SQL Direto

```sql
DESC assinaturas;
```

Procure por:
```
| Field               | Type     | Null | Key | Default |
|─────────────────────┼──────────┼──────┼─────┼─────────|
| pacote_contrato_id  | int      | YES  |     | NULL    |
```

### Via Script PHP

```php
<?php
$db = require 'config/database.php';

$stmt = $db->query("DESC assinaturas");
$colunas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "=== COLUNAS DE ASSINATURAS ===\n";
foreach ($colunas as $col) {
    if ($col['Field'] === 'pacote_contrato_id') {
        echo "✅ ENCONTRADO: {$col['Field']} ({$col['Type']}, Nulo: {$col['Null']})\n";
    }
}
?>
```

---

## 🎯 Teste 2: Simular Webhook de Assinatura (PAC-)

### Dados de Teste

```
Contrato: ID = 4
Pagante: usuario_id = 3 (ANDRE CABRAL SILVA)
Beneficiários: aluno 94, 95, 96
```

### Curl Request

```bash
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "subscription_preapproval",
    "data": {
      "id": "test_assinatura_pac4_123456"
    }
  }' \
  -v
```

### Esperado na Resposta

- Status: 200 OK
- Response:
  ```json
  {
    "success": true,
    "message": "Assinatura processada",
    "preapproval_id": "test_assinatura_pac4_123456"
  }
  ```

### Validar no Banco

```sql
-- 1. Verificar se matrícula do pagante foi criada
SELECT id, aluno_id, pacote_contrato_id, status_id 
FROM matriculas 
WHERE pacote_contrato_id = 4 
ORDER BY created_at DESC LIMIT 1;

-- Esperado:
-- | id  | aluno_id | pacote_contrato_id | status_id |
-- | 500 | 72       | 4                  | 2         |


-- 2. Verificar se assinatura foi criada com pacote_contrato_id
SELECT id, gateway_assinatura_id, matriz...cula_id, pacote_contrato_id, status_id 
FROM assinaturas 
WHERE pacote_contrato_id = 4 
ORDER BY created_at DESC LIMIT 1;

-- Esperado:
-- | id  | gateway_assinatura_id      | matricula_id | pacote_contrato_id | status_id |
-- | 300 | test_assinatura_pac4_123456| 500          | 4                  | 2         |


-- 3. Validar webhook foi registrado
SELECT id, tipo, status, external_reference 
FROM webhook_payloads_mercadopago 
WHERE external_reference LIKE 'PAC-4-%' 
ORDER BY created_at DESC LIMIT 1;

-- Esperado:
-- | id | tipo | status   | external_reference    |
-- | XX | subscription_preapproval | sucesso | PAC-4-... |
```

---

## 💳 Teste 3: Simular Webhook de Pagamento (PAC-)

### Curl Request

```bash
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": {
      "id": 146079536501
    }
  }' \
  -v
```

### Esperado na Resposta

- Status: 200 OK
- Response:
  ```json
  {
    "success": true,
    "message": "Notificação processada",
    "payment_status": "approved"
  }
  ```

### Validar no Banco

```sql
-- 1. Verificar matrículas dos beneficiários criadas
SELECT id, aluno_id, pacote_contrato_id, status_id 
FROM matriculas 
WHERE pacote_contrato_id = 4 
ORDER BY created_at DESC;

-- Esperado: 4 matrículas (1 pagante + 3 beneficiários)
-- | id  | aluno_id | pacote_contrato_id | status_id |
-- | 500 | 72       | 4                  | 2         |
-- | 501 | 94       | 4                  | 2         |
-- | 502 | 95       | 4                  | 2         |
-- | 503 | 96       | 4                  | 2         |


-- 2. Verificar pagamentos marcados como "pago"
SELECT pp.id, pp.aluno_id, pp.matricula_id, pp.valor, sp.codigo 
FROM pagamentos_plano pp
INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
WHERE pp.matricula_id IN (
  SELECT id FROM matriculas WHERE pacote_contrato_id = 4
)
ORDER BY pp.created_at DESC;

-- Esperado: 4 pagamentos com status = 'pago'
-- | id  | aluno_id | matricula_id | valor | codigo |
-- | ... | 72       | 500          | 2.00  | pago   |
-- | ... | 94       | 501          | 0.50  | pago   |
-- | ... | 95       | 502          | 0.50  | pago   |
-- | ... | 96       | 503          | 0.50  | pago   |


-- 3. Verificar contrato marcado como 'ativo'
SELECT id, status, pagamento_id 
FROM pacote_contratos 
WHERE id = 4;

-- Esperado:
-- | id | status | pagamento_id   |
-- | 4  | ativo  | 146079536501   |


-- 4. Verificar webhook registrado
SELECT id, tipo, status, external_reference 
FROM webhook_payloads_mercadopago 
WHERE external_reference LIKE 'PAC-4-%' 
ORDER BY created_at DESC LIMIT 1;

-- Esperado (último webhook):
-- | id | tipo   | status   | external_reference |
-- | YY | payment| sucesso  | PAC-4-...          |
```

---

## 🔄 Teste 4: Cenário Completo (End-to-End)

### Setup

```
1. Limpar dados de teste anteriores
2. Criar novo pacote_contrato com ID para teste (ex: ID=99)
3. Adicionar beneficiários ao pacote
```

### Executar Fluxo Completo

```bash
#!/bin/bash

API="https://api.appcheckin.com.br/api/webhooks/mercadopago"

echo "=== Teste 1: Webhook Assinatura ==="
curl -X POST $API \
  -H "Content-Type: application/json" \
  -d '{
    "type": "subscription_preapproval",
    "data": { "id": "test_full_123" }
  }' \
  | jq .

echo ""
echo "=== Aguarde 2 segundos ==="
sleep 2

echo ""
echo "=== Teste 2: Webhook Pagamento ==="
curl -X POST $API \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": { "id": 999999999 }
  }' \
  | jq .

echo ""
echo "=== Validando Banco de Dados ==="
# Usar sua ferramenta SQL favorita para validar dados
```

---

## 🐛 Teste 5: Casos de Erro

### Cenário 1: Webhook de Pagamento ANTES da Assinatura

```bash
# Se webhook de pagamento chegar antes da assinatura
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": { "id": 111111111 }
  }'
```

**Esperado:**
- Webhook marcado como "sucesso" mas sem ação (porque matrícula do pagante ainda não existe)
- Log: `⚠️ Matrícula do pagante não encontrada para contrato X`
- IMPORTANTE: Próximo webhook de assinatura criará a matrícula

### Cenário 2: Contrato Não Existe

```bash
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "subscription_preapproval",
    "data": { "id": "test_invalid_9999" }
  }'
```

**Esperado:**
- Webhook marcado como "sucesso"
- Log: `❌ Contrato 9999 não encontrado`
- Nenhuma matrícula criada

### Cenário 3: Pagante Não é Aluno

Se `usuario_id` não tiver `aluno_id` associado:

**Esperado:**
- Webhook marcado como "sucesso"
- Log: `⚠️ Aluno do pagante não encontrado`
- Nenhuma matrícula criada (esperado, sistema está correto)

---

## ✅ Checklist de Validação

- [ ] **Coluna pacote_contrato_id existe**
  ```sql
  DESC assinaturas;
  ```

- [ ] **Novo método criarMatriculaPagantePacote() funciona**
  - Webhook de assinatura chega
  - Matrícula do pagante criada
  - Assinatura com pacote_contrato_id armazenada

- [ ] **Novo método processarPagamentoPacote() funciona**
  - Webhook de pagamento chega
  - Matrículas dos beneficiários criadas
  - Pagamentos marcados como "pago"

- [ ] **Valores corretos (valor rateado)**
  - Total: R$ 2.00
  - Por pessoa: R$ 0.50 cada (2.00 / 4)

- [ ] **Status corretos**
  - Matrículas: `ativa` (status_id = 2)
  - Assinatura: `ativa` (status_id = 2)
  - Pagamentos: `pago` (status_pagamento_id = 2)
  - Contrato: `ativo`

- [ ] **Logs aparecem corretamente**
  - Webhook registrado em `webhook_payloads_mercadopago`
  - Mensagens de debug no error_log

- [ ] **Transações funcionam (rollback em caso de erro)**
  - Se erro ocorre, nenhuma matrícula fica incompleta
  - Banco mantém consistência

- [ ] **Teste com pagamento real (sandbox MP)**
  - Toda pipeline funciona sem erros

---

## 📊 Teste 6: Performance

### Benchmark

```bash
# Teste com múltiplos webhooks simultâneos
for i in {1..10}; do
  curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
    -H "Content-Type: application/json" \
    -d "{\"type\": \"payment\", \"data\": {\"id\": $((1000 + $i))}}" &
done
wait

echo "✅ 10 webhooks processados simultaneamente"
```

**Esperado:**
- Sem deadlocks
- Banco responde rápido
- Todas as requests retornam 200

---

## 🎯 Próximo Passo

Se todos os testes passarem:
1. ✅ Validação concluída
2. ✅ Pronto para produção
3. ✅ Deploy aos clientes
4. ✅ Começar a receber pacotes reais

Se houver erro:
- [ ] Verificar logs: `/home/u304177849/.logs/`
- [ ] Executar migration novamente
- [ ] Validar dados no banco
- [ ] Restaurar versão anterior se necessário

---

## 📞 Suporte

Se tiver dúvidas:
1. Verifique [NOVO_FLUXO_PACOTES_WEBHOOKS.md](NOVO_FLUXO_PACOTES_WEBHOOKS.md)
2. Execute os comandos dessa guia
3. Analise os logs
4. Valide os dados no banco
