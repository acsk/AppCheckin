# 🐛 Debug de Webhooks do Mercado Pago

## Endpoints Disponíveis

### 1. Listar Webhooks Salvos

**Endpoint:** `GET /api/webhooks/mercadopago/list`

**Parâmetros Query:**
- `filtro` (opcional): `erro`, `sucesso` ou vazio (todos)
- `limite` (opcional): Número de registros (padrão: 50)

**Exemplos:**
```bash
# Últimos 50 webhooks
curl http://localhost:8080/api/webhooks/mercadopago/list

# Apenas webhooks com erro
curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=erro

# Últimos 100 com sucesso
curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=sucesso&limite=100
```

**Resposta:**
```json
{
  "success": true,
  "total": 5,
  "webhooks": [
    {
      "id": 1,
      "created_at": "2026-02-18 13:44:55",
      "tipo": "payment",
      "data_id": 146749614928,
      "status": "erro",
      "external_reference": null,
      "payment_id": "146749614928",
      "preapproval_id": null,
      "erro_processamento": "Matrícula não identificada no pagamento"
    },
    ...
  ]
}
```

---

### 2. Ver Detalhes de um Webhook

**Endpoint:** `GET /api/webhooks/mercadopago/show/{id}`

**Exemplo:**
```bash
# Ver webhook com ID 1
curl http://localhost:8080/api/webhooks/mercadopago/show/1
```

**Resposta:**
```json
{
  "success": true,
  "webhook": {
    "id": 1,
    "created_at": "2026-02-18 13:44:55",
    "tipo": "payment",
    "data_id": 146749614928,
    "payment_id": "146749614928",
    "status": "erro",
    "erro_processamento": "Matrícula não identificada no pagamento",
    "payload": {
      "action": "payment.created",
      "api_version": "v1",
      "data": {
        "id": "146749614928"
      },
      "date_created": "2026-02-18T13:44:55Z",
      "id": 129227287202,
      "live_mode": true,
      "type": "payment",
      "user_id": "195078879"
    },
    "resultado_processamento": null
  }
}
```

---

### 3. Reprocessar um Webhook Salvo

**Endpoint:** `POST /api/webhooks/mercadopago/reprocess/{id}`

Útil para reprocessar webhooks que falharam após corrigir o código.

**Exemplo:**
```bash
# Reprocessar webhook com ID 1
curl -X POST http://localhost:8080/api/webhooks/mercadopago/reprocess/1
```

**Resposta:**
```json
{
  "success": true,
  "message": "Webhook reprocessado com sucesso"
}
```

---

### 4. Buscar Pagamento da API do MP

**Endpoint:** `GET /api/webhooks/mercadopago/payment/{paymentId}`

Busca os dados completos de um pagamento diretamente da API do Mercado Pago.

**Exemplo:**
```bash
# Buscar pagamento com ID 146749614928
curl http://localhost:8080/api/webhooks/mercadopago/payment/146749614928
```

**Resposta:**
```json
{
  "success": true,
  "pagamento": {
    "id": 146749614928,
    "status": "approved",
    "status_detail": "accredited",
    "external_reference": "MAT-123-1771421288",
    "preference_id": "195078879-...",
    "metadata": {
      "tenant_id": 1,
      "matricula_id": 123,
      "aluno_id": 456,
      "usuario_id": 789
    },
    "transaction_amount": 150.00,
    "date_approved": "2026-02-18T13:44:55...",
    "date_created": "2026-02-18T13:44:55...",
    "payer": {
      "email": "aluno@email.com",
      "identification": {...}
    },
    "payment_method_id": "credit_card",
    "payment_type_id": "credit_card",
    "installments": 1,
    "raw": {...}
  }
}
```

---

### 5. Reprocessar um Pagamento Específico

**Endpoint:** `POST /api/webhooks/mercadopago/payment/{paymentId}/reprocess`

Busca um pagamento da API do MP e reprocessa como se tivesse recebido um webhook.

**Exemplo:**
```bash
# Reprocessar pagamento com ID 146749614928
curl -X POST http://localhost:8080/api/webhooks/mercadopago/payment/146749614928/reprocess
```

**Resposta:**
```json
{
  "success": true,
  "message": "Pagamento reprocessado com sucesso",
  "payment_id": "146749614928"
}
```

---

## Fluxo de Debug Recomendado

### Cenário: Webhook falhou sem recuperar a matrícula

**Passo 1:** Listar webhooks com erro
```bash
curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=erro
```

**Passo 2:** Ver detalhes do webhook
```bash
curl http://localhost:8080/api/webhooks/mercadopago/show/1
```

**Passo 3:** Buscar pagamento na API do MP (para ver se external_reference está lá)
```bash
curl http://localhost:8080/api/webhooks/mercadopago/payment/146749614928
```

**Passo 4:** Verificar no banco se existe a matrícula correspondente
```sql
SELECT * FROM matriculas WHERE aluno_id = 456 ORDER BY created_at DESC LIMIT 5;
```

**Passo 5:** Reprocessar o pagamento (após corrigir o código se necessário)
```bash
curl -X POST http://localhost:8080/api/webhooks/mercadopago/payment/146749614928/reprocess
```

**Passo 6:** Verificar logs
```bash
docker-compose exec -T php tail -50 /var/log/php-error.log | grep -i "webhook\|pagamento"
```

---

## Mécanismo de Recuperação de Matrícula

O webhook agora tenta recuperar a matrícula pelos seguintes métodos:

1. **Método 1:** Extrair do `external_reference` (formato: `MAT-123-timestamp`)
2. **Método 2:** Obter do campo `metadata.matricula_id`
3. **Fallback 1:** Procurar na tabela `pagamentos_mercadopago` pelo `payment_id`
4. **Fallback 2:** Procurar na tabela `matriculas` pelo `aluno_id` (últimas nos últimos dados hora)

Se nenhum método funcionar, o webhook é marcado como `erro` e armazenado para análise posterior.

---

## Queries Úteis para Investigação

### Ver todos os pagamentos sem external_reference
```sql
SELECT id, payment_id, status, external_reference, created_at
FROM webhook_payloads_mercadopago
WHERE external_reference IS NULL OR external_reference = ''
ORDER BY created_at DESC;
```

### Ver webhooks com erro agrupados por tipo
```sql
SELECT tipo, COUNT(*) as total
FROM webhook_payloads_mercadopago
WHERE status = 'erro'
GROUP BY tipo;
```

### Ver estatísticas completas
```sql
SELECT 
    COUNT(*) as total_webhooks,
    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
    SUM(CASE WHEN status = 'erro' THEN 1 ELSE 0 END) as erros,
    ROUND(100.0 * SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) / COUNT(*), 2) as taxa_sucesso_percent
FROM webhook_payloads_mercadopago;
```

### Encontrar webhooks órfãos (sem matrícula identificada)
```sql
SELECT w.id, w.data_id, w.payment_id, w.external_reference, w.erro_processamento
FROM webhook_payloads_mercadopago w
WHERE w.status = 'erro'
  AND w.erro_processamento LIKE '%Matrícula não identificada%'
ORDER BY w.created_at DESC;
```

---

## Troubleshooting

### Erro: "Matrícula não identificada no pagamento"

**Causa:** O pagamento foi feito sem ter uma `external_reference` vinculada.

**Solução:**
1. Verificar se a preferência foi criada corretamente
2. Verificar se o checkout foi feito com a preferência correta
3. Buscar o pagamento na API do MP (`GET /api/webhooks/mercadopago/payment/{paymentId}`)
4. Se tiver `external_reference` na API, o problema é que não chegou no webhook
5. Se não tiver `external_reference`, o problema é na criação da preferência

### Erro: "Webhook não encontrado"

**Causa:** O ID do webhook não existe na tabela.

**Solução:**
1. Verificar se o webhook foi de fato salvo
2. Listar webhooks com `GET /api/webhooks/mercadopago/list`
3. Usar o ID correto

### Status: "erro" mas quer reprocessar

**Solução:**
```bash
# Opção 1: Reprocessar webhook salvo
curl -X POST http://localhost:8080/api/webhooks/mercadopago/reprocess/{id}

# Opção 2: Reprocessar direto da API do MP
curl -X POST http://localhost:8080/api/webhooks/mercadopago/payment/{paymentId}/reprocess
```

---

## Logs

Todos os webhooks geram logs em:
- Arquivo: `/projeto/storage/logs/webhook_mercadopago.log`
- Sistema: `error_log()` do PHP

Para ver logs em tempo real:
```bash
docker-compose exec -T php tail -f /var/log/php-error.log | grep -i "webhook"
```

---

## Exemplos Completos

### Debug de um pagamento que falhou

```bash
# 1. Ver último webhook com erro
curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=erro&limite=1 | jq '.webhooks[0]'

# 2. Pegar payment_id
PAYMENT_ID=$(curl http://localhost:8080/api/webhooks/mercadopago/list?filtro=erro&limite=1 | jq -r '.webhooks[0].payment_id')

# 3. Ver detalhes completos do payment
curl http://localhost:8080/api/webhooks/mercadopago/payment/$PAYMENT_ID | jq '.pagamento'

# 4. Verificar se tem external_reference
curl http://localhost:8080/api/webhooks/mercadopago/payment/$PAYMENT_ID | jq '.pagamento.external_reference'

# 5. Reprocessar
curl -X POST http://localhost:8080/api/webhooks/mercadopago/payment/$PAYMENT_ID/reprocess

# 6. Verificar logs
docker-compose exec -T php tail -20 /var/log/php-error.log | grep -i "webhook"
```

---

## API de Resgate de Matrícula

Quando um webhook falha, você pode usar esse endpoint para tentar recuperar a matrícula manualmente:

**Endpoint (futuro):** `POST /api/webhooks/mercadopago/recover-matricula`

**Body:**
```json
{
  "payment_id": "146749614928",
  "aluno_id": 456,
  "tenant_id": 1
}
```

(Será implementado em versão futura se necessário)
