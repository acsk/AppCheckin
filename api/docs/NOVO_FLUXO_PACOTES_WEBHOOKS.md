# 🎁 Novo Fluxo de Webhooks para Pacotes

## 🎯 Problema Original

O pagamento 146079536501 com `external_reference = "PAC-4-1771434041"` falhou silenciosamente porque:

1. ❌ Webhook de **pagamento** chegou com **metadata vazia**
2. ❌ Código não conseguiu identificar que era um pacote
3. ❌ Nenhuma matrícula foi criada
4. ❌ Webhook foi marcado como "sucesso" mas sem ação

## ✅ Solução Implementada

**Novo fluxo em 2 etapas:**

### 1️⃣ **Webhook de Assinatura Recorrente** (subscription_preapproval)
**External Reference**: `PAC-4-1771434041`

```
Mercado Pago
    ↓
Webhook chega: type='subscription_preapproval'
    ↓
MercadoPagoWebhookController::processarWebhook()
    ↓
MercadoPagoWebhookController::atualizarAssinatura()
    ↓
Detecta: strpos(external_reference, 'PAC-') === 0
    ✅ EXTRAIR: contratoId = 4 usando regex /PAC-(\d+)-/
    ↓
criarMatriculaPagantePacote(4, preapprovalId, status)
    ✅ Buscar contrato 4 (com tenant_id, plano_id, valor_total)
    ✅ Buscar aluno_id do pagante (usuario_id → alunos.usuario_id)
    ✅ CRIAR Matrícula do pagante
       - pacote_contrato_id = 4
       - tipo_cobranca = 'recorrente'
       - status = 'ativa'
       - valor_rateado = R$ 2.00 (valor total, por enquanto)
    ✅ CRIAR Assinatura
       - gateway_assinatura_id = preapprovalId
       - pacote_contrato_id = 4  ⭐ CHAVE: Armazena aqui!
       - tipo_cobranca = 'recorrente'
       - status = 'ativa'
    ↓
✅ Retorna (webhook de assinatura processado)
```

### 2️⃣ **Webhook de Pagamento** (payment)
**External Reference**: `PAC-4-1771434041`

```
Mercado Pago
    ↓
Webhook chega: type='payment' (primeira cobrança)
    ↓
MercadoPagoWebhookController::processarWebhook()
    ↓
MercadoPagoWebhookController::atualizarPagamento()
    ↓
Detecta: strpos(external_reference, 'PAC-') === 0
    ✅ EXTRAIR: contratoId = 4
    ↓
processarPagamentoPacote(4, pagamento)
    ✅ Buscar contrato 4
    ✅ Buscar matrícula do pagante (criada no passo 1)
    ✅ CRIAR Matrículas dos beneficiários
       - aluno_id = 94, 95, 96
       - pacote_contrato_id = 4
       - tipo_cobranca = 'recorrente'
       - status = 'ativa'
       - valor_rateado = R$ 0.50 cada (R$ 2.00 / 4 pessoas)
    ✅ MARCAR Pagamentos como realizados
       - 1 pagamento para pagante
       - 3 pagamentos para beneficiários
       - status_pagamento = 'pago'
       - data_pagamento = NOW()
    ✅ MARCAR Contrato como 'ativo'
    ↓
✅ Retorna (webhook de pagamento processado, pacote totalmente ativo)
```

## 📊 Comparação: Antes vs Depois

### ❌ Antes (Quebrado)
```
Webhook paga PAC-4
    ↓
try to call atualizarPagamento()
    ↓
Metadata vazio → não encontra tipo
    ↓
Fall back to external_reference → encontra PAC-4
    ↓
call ativarPacoteContrato() COM TODOS OS PASSOS
    ↓
Tenta criar matrículas (4 pessoas)
    ↓
Erro em algum lugar (vendedor não é aluno? falta de dados?)
    ↓
❌ Webhook marcado como 'sucesso' mas SEM AÇÃO
```

### ✅ Depois (Funcionando)
```
Webhook assinatura PAC-4
    ↓
criarMatriculaPagantePacote()
    ↓
✅ Matrícula pagante + Assinatura criadas
    (armazena pacote_contrato_id na assinatura)
    ↓
---
Webhook pagamento PAC-4
    ↓
processarPagamentoPacote()
    ↓
✅ Busca assinatura anterior (pela pacote_contrato_id)
✅ Cria matrículas beneficiários
✅ Marca pagamentos como realizados
✅ Contrato ativo
```

## 🔧 Mudanças no Código

### 1. Novo Método: `criarMatriculaPagantePacote()`
```php
private function criarMatriculaPagantePacote(
    int $contratoId, 
    string $preapprovalId, 
    string $statusAssinatura
): void
```

**O que faz:**
- Cria matrícula do pagante
- Cria assinatura com `pacote_contrato_id` preenchido
- Called by: `atualizarAssinatura()` quando detecta `PAC-` no external_reference

### 2. Novo Método: `processarPagamentoPacote()`
```php
private function processarPagamentoPacote(
    int $contratoId, 
    array $pagamento
): void
```

**O que faz:**
- Busca assinatura criada anteriormente
- Cria matrículas dos beneficiários
- Marca pagamentos como realizados
- Marca contrato como ativo
- Called by: `atualizarPagamento()` quando detecta `PAC-` no external_reference

### 3. Nova Coluna: `assinaturas.pacote_contrato_id`
```sql
ALTER TABLE assinaturas 
ADD COLUMN pacote_contrato_id INT NULL DEFAULT NULL;
```

**Por quê:**
- Permite recuperar o pacote quando webhook de pagamento chega
- Sem metadata, podemos buscar na assinatura: `WHERE pacote_contrato_id = ?`
- Link entre webhook de assinatura → webhook de pagamento

## 🚀 Fluxo Prático: Contrato 4

### Passo 1: Cliente compra pacote
```
Dados:
  - Pagante: usuario_id = 3 (ANDRE)
  - Beneficiários: aluno 94, 95, 96
  - Valor total: R$ 2.00
  - Permite recorrência: SIM
```

### Passo 2: Frontend inicia assinatura recorrente
```
POST mercadopago_api/subscription/create
{
  "external_reference": "PAC-4-1771434041",
  "payer_email": "andre@appcheckin.com",
  ...
}

Response: preapproval_id = 123abc456
```

### Passo 3: MP envia webhook de assinatura
```
POST /api/webhooks/mercadopago
{
  "type": "subscription_preapproval",
  "data": {
    "id": "123abc456"
  }
}

Resultado:
  ✅ Matrícula 500 criada (pagante, aluno 72)
  ✅ Assinatura 300 criada (pacote_contrato_id = 4)
```

### Passo 4: Cliente aprova/paga assinatura
```
MP aprova cobrança

Response do MP API:
  - payment_id = 146079536501
  - external_reference = "PAC-4-1771434041"
  - status = "approved"
```

### Passo 5: MP envia webhook de pagamento
```
POST /api/webhooks/mercadopago
{
  "type": "payment",
  "data": {
    "id": 146079536501
  }
}

Busca: assinatura com pacote_contrato_id = 4
Encontra: assinatura 300 (webhook anterior)

Resultado:
  ✅ Matrícula 501 criada (beneficiário, aluno 94)
  ✅ Matrícula 502 criada (beneficiário, aluno 95)
  ✅ Matrícula 503 criada (beneficiário, aluno 96)
  ✅ 4 Pagamentos criados e marcados como "pago"
     - Pagante: R$ 2.00
     - Beneficiários: R$ 0.50 cada
  ✅ Contrato 4 marcado como "ativo"
```

## ⚡ Vantagens da Nova Abordagem

| Aspecto | Antes | Depois |
|---------|-------|--------|
| **Metadata vazia?** | ❌ Falha silenciosa | ✅ Usa external_reference + assinatura |
| **Quando cria pagante?** | ❌ Junto com beneficiários | ✅ Webhook de assinatura (mais cedo) |
| **Recuperação dados?** | ❌ Precisa de metadata | ✅ Busca assinatura anterior |
| **Fallback** | ❌ Nenhum | ✅ 3 níveis (metadata → external_reference → assinatura) |
| **Robustez** | ❌ Quebra se MP varia payload | ✅ Resiste a variações |
| **Separação** | ❌ Tudo em um método | ✅ 2 métodos bem definidos |

## 📋 Setup Necessário

### 1. Executar Migration
```bash
php database/migrations/add_pacote_contrato_id_to_assinaturas.php
```

### 2. Validar SQL
```sql
DESC assinaturas;
-- Deve mostrar coluna: pacote_contrato_id INT NULL
```

### 3. Deploy do código
- MercadoPagoWebhookController.php (novos métodos)
- Reiniciar PHP-FPM

### 4. Testar
```bash
# Simular webhook de assinatura
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "subscription_preapproval",
    "data": {"id": "test_123"}
  }'

# Simular webhook de pagamento
curl -X POST https://api.appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "type": "payment",
    "data": {"id": 999}
  }'
```

## 🐛 Troubleshooting

### Webhook marcado como erro?
Verificar logs em `webhook_payloads_mercadopago`:
```sql
SELECT * FROM webhook_payloads_mercadopago 
WHERE payment_id = 146079536501;
```

### Matrícula do pagante não criada?
Verificar se webhook de assinatura foi recebido:
```sql
SELECT * FROM webhook_payloads_mercadopago 
WHERE external_reference LIKE 'PAC-4-%' 
AND tipo = 'subscription_preapproval'
ORDER BY created_at DESC;
```

### Beneficiários não criados?
1. Verificar se matrícula do pagante existe
2. Verificar se beneficiários existem em `pacote_beneficiarios`
3. Ver logs do webhook de pagamento

## 📝 Próximas Melhorias

- [ ] Adicionar índices em `assinaturas.pacote_contrato_id`
- [ ] Adicionar índices em `matriculas.pacote_contrato_id`
- [ ] Auto-retry para webhooks falhados
- [ ] Dashboard de pacotes com status visual
- [ ] Notificação ao cliente quando pacote ativar
