# 📊 Fluxo Completo: POST /mobile/pacotes/contratos/{contratoId}/pagar

## 🎯 Request do Cliente

```
POST https://api.appcheckin.com.br/mobile/pacotes/contratos/4/pagar
Authorization: Bearer <token_usuario>
Content-Type: application/json

Body (opcional):
{
  "force_new": true  // força gerar novo pagamento (ignora payment_url anterior)
}
```

---

## 🔄 Fluxo Step-by-Step

### 1️⃣ **MobileController::pagarPacote() Recebe Request**

```php
POST /mobile/pacotes/contratos/{contratoId}/pagar
↓
MobileController::pagarPacote(Request, Response, args)
↓
Extrai:
  - tenantId = 3 (do token)
  - userId = 3 (do token, o pagante)
  - contratoId = 4 (do URL)
  - forceNew = false (padrão, reusar se existir)
```

**Validações:**
- ✅ contratoId > 0
- ✅ Contrato existe e pertence ao usuario
- ✅ Contrato status = 'pendente' (não pode pagar duas vezes)

### 2️⃣ **Buscar Dados do Contrato**

```sql
SELECT pc.*, p.nome, p.valor_total, p.plano_ciclo_id, pc2.permite_recorrencia
FROM pacote_contratos pc
INNER JOIN pacotes p ON p.id = pc.pacote_id
LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
WHERE pc.id = 4 AND pc.tenant_id = 3 AND pc.pagante_usuario_id = 3
```

**Resultado Esperado:**
```
id = 4
tenant_id = 3
pagante_usuario_id = 3
pacote_id = 1
status = 'pendente'
pacote_nome = 'Pacote 3 Alunos'
valor_total = 2.00
permite_recorrencia = 1 (true)  ⭐ IMPORTANTE!
payment_url = null (primeira vez) ou URL (reusar)
payment_preference_id = null ou "ID"
```

### 3️⃣ **Check: Já Existe payment_url (Reusar)?**

```php
if (!empty($contrato['payment_url']) && !$forceNew) {
    // Reusar pagamento anterior
    return {
        'success': true,
        'message': 'Pagamento já gerado',
        'data': {
            'payment_url': 'https://checkout.mercadopago.com/...',
            'preference_id': 'ID',
            'valor_total': 2.00
        }
    }
}
```

**Por quê reusar?**
- Cliente pode clicar várias vezes sem gerar múltiplas preferências
- Economiza requisições ao Mercado Pago
- Se `force_new=true`, ignora e gera novo

### 4️⃣ **Montar Dados para Pagamento**

```php
$dadosPagamento = [
    'tenant_id' => 3,
    'usuario_id' => 3,
    'aluno_nome' => 'ANDRE CABRAL SILVA',
    'aluno_email' => 'andre@appcheckin.com',
    'aluno_cpf' => '123.456.789-00',
    'item_id' => 'PACOTE_4',
    'external_reference' => 'PAC-4-1771434041',  // ⭐ CHAVE para identificar depois
    'valor' => 2.00,
    'plano_nome' => 'Pacote 3 Alunos',
    'descricao' => 'Pacote: Pacote 3 Alunos',
    'apenas_cartao' => true,  // Recorrentes SÓ com cartão (sem boleto)
    'metadata_extra' => [
        'tipo' => 'pacote',
        'pacote_contrato_id' => 4
    ]
];
```

**Por que `apenas_cartao = true`?**
- Assinaturas recorrentes no MP precisam de método persistente
- Boleto não permite renovação automática
- PIX também não (é único por cobrança)
- Cartão permite débito repetido ✓

### 5️⃣ **Decisão Crítica: Recorrente ou Avulso?**

```php
if ($permiteRecorrencia) {
    // PACOTE RECORRENTE = Criar PREAPPROVAL (Assinatura)
    // Cobrar todo mês automaticamente
    $preferencia = $mercadoPago->criarPreferenciaAssinatura($dados, 1);
} else {
    // PACOTE AVULSO = Criar PREFERENCE (Pagamento único)
    // Cobrar uma única vez
    $preferencia = $mercadoPago->criarPreferenciaPagamento($dados);
}
```

**Diferença:**
| Tipo | Webhook | Cobrança | Uso |
|------|---------|----------|-----|
| **PREAPPROVAL** | `subscription_preapproval` | Automática todo mês | Pacotes com recorrência |
| **PREFERENCE** | `payment` | Uma única vez | Pacotes avulsos |

### 6️⃣ **Chamar Mercado Pago API**

#### **Se Recorrente (PREAPPROVAL):**

```php
$mercadoPagoService->criarPreferenciaAssinatura($dadosPagamento, 1)
    ↓
POST https://api.mercadopago.com/preapproval_plan
{
    "reason": "Pacote 3 Alunos",
    "auto_recurring": {
        "frequency": 1,
        "frequency_type": "months",
        "transaction_amount": 2.00,
        "currency_id": "BRL"
    },
    "payer_email": "andre@appcheckin.com",
    "external_reference": "PAC-4-1771434041",
    "back_url": "https://appcheckin.com.br/...",
    ...
}
    ↓
Response:
{
    "id": "123abc456def",
    "init_point": "https://checkout.mercadopago.com/...",
    "status": "pending"
}
```

#### **Se Avulso (PREFERENCE):**

```php
$mercadoPagoService->criarPreferenciaPagamento($dadosPagamento)
    ↓
POST https://api.mercadopago.com/checkout/preferences
{
    "purpose": "wallet_purchase",
    "items": [{
        "id": "PACOTE_4",
        "title": "Pacote 3 Alunos",
        "amount": 2.00,
        "quantity": 1
    }],
    "payer": {
        "name": "ANDRE CABRAL SILVA",
        "email": "andre@appcheckin.com"
    },
    "external_reference": "PAC-4-1771434041",
    "back_urls": { ... },
    ...
}
    ↓
Response:
{
    "id": "987zyxwvu",
    "init_point": "https://checkout.mercadopago.com/...",
    "status": "pending"
}
```

### 7️⃣ **Salvar no Banco de Dados**

```sql
UPDATE pacote_contratos
SET payment_url = 'https://checkout.mercadopago.com/...',
    payment_preference_id = '123abc456def',
    updated_at = NOW()
WHERE id = 4 AND tenant_id = 3
```

**O que foi salvo?**
- `payment_url`: Link para Mercado Pago onde cliente paga
- `payment_preference_id`: ID da preferência no MP (para requerys futuras)
- Banco de dados agora tem referência ao pagamento

### 8️⃣ **Responder ao Cliente**

```json
HTTP 200 OK
{
  "success": true,
  "data": {
    "contrato_id": 4,
    "payment_url": "https://checkout.mercadopago.com/checkout/v1/...",
    "preference_id": "123abc456def",
    "valor_total": 2.00
  }
}
```

---

## 🌐 Frontend Recebe URL

```javascript
// JavaScript no app/website
const response = await fetch('/mobile/pacotes/contratos/4/pagar', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer token',
    'Content-Type': 'application/json'
  }
});

const data = await response.json();

// Redirecionar para Mercado Pago
window.location.href = data.data.payment_url;
// ou abrir em popup/webview:
window.open(data.data.payment_url, '_blank');
```

---

## 💳 Cliente no Mercado Pago

```
Cliente clica no link
    ↓
Abre https://checkout.mercadopago.com/checkout/v1/...
    ↓
┌─────────────────────────────────────┐
│ Mercado Pago Checkout               │
│                                     │
│ Pacote 3 Alunos                    │
│ R$ 2.00                             │
│                                     │
│ [ Escolher forma de pagamento ]     │
│ [X] Cartão (necessário para recorrente)
│                                     │
│ Nome: ANDRE CABRAL SILVA           │
│ Email: andre@appcheckin.com         │
│ CPF: 123.456.789-00                │
│                                     │
│         [ PAGAR ]                   │
└─────────────────────────────────────┘
    ↓
Cliente entra dados do cartão
    ↓
MP processa pagamento
    ↓
✅ APROVADO ou ❌ RECUSADO
```

---

## ✅ Se Aprovado (Pagamento bem-sucedido)

### Cliente é Redirecionado de Volta

```
MP verifica: permiteRecorrencia = true (PREAPPROVAL)
    ↓
Pedir aprovação da assinatura recorrente
    ↓
"Você autoriza débitos mensais automáticos?"
    ↓
Cliente clica: "Autorizar"
    ↓
Redireciona para: https://appcheckin.com.br/sucesso?...
```

### MP Envia Webhook #1: subscription_preapproval

```
event_type = "subscription_preapproval"
data = {
  "id": "preapproval_id_123abc456"
}

POST https://api.appcheckin.com.br/api/webhooks/mercadopago
{
  "type": "subscription_preapproval",
  "data": { "id": "preapproval_id_123abc456" }
}
    ↓
MercadoPagoWebhookController::processarWebhook()
    ↓
MercadoPagoWebhookController::atualizarAssinatura()
    ↓
Detecta: external_reference = "PAC-4-..."
Extrai: contratoId = 4
    ↓
criarMatriculaPagantePacote(4, "preapproval_id_123abc456", "approved")
    ↓
✅ Matrícula 500 (pagante/aluno 72) criada
✅ Assinatura 300 (com pacote_contrato_id = 4) criada
```

**Banco após webhook 1:**
```
matriculas:
  id=500, aluno_id=72, pacote_contrato_id=4, status_id=2 (ativa)

assinaturas:
  id=300, matricula_id=500, gateway_assinatura_id="preapproval_id_123abc456", 
  pacote_contrato_id=4, status_id=2 (ativa)
```

### MP Faz Primeira Cobrança (imediatamente ou próximo ciclo)

```
MP cobra automaticamente do cartão
    ↓
Status = "approved"
payment_id = 146079536501
external_reference = "PAC-4-1771434041"
    ↓
```

### MP Envia Webhook #2: payment

```
event_type = "payment"
data = {
  "id": 146079536501
}

POST https://api.appcheckin.com.br/api/webhooks/mercadopago
{
  "type": "payment",
  "data": { "id": 146079536501 }
}
    ↓
MercadoPagoWebhookController::processarWebhook()
    ↓
MercadoPagoWebhookController::atualizarPagamento()
    ↓
Detecta: external_reference = "PAC-4-..."
Extrai: contratoId = 4
    ↓
processarPagamentoPacote(4, pagamento)
    ↓
Busca assinatura anterior: WHERE pacote_contrato_id = 4
Encontra: assinatura 300 (criada no webhook #1)
    ↓
✅ Matrícula 501 (beneficiário/aluno 94) criada + paga
✅ Matrícula 502 (beneficiário/aluno 95) criada + paga
✅ Matrícula 503 (beneficiário/aluno 96) criada + paga
✅ 4 pagamentos marcados como "pago"
✅ Contrato marcado como "ativo"
```

**Banco após webhook 2:**
```
matriculas: (agora 4)
  id=500, aluno_id=72, pacote_contrato_id=4, status_id=2 (ativa) - PAGANTE
  id=501, aluno_id=94, pacote_contrato_id=4, status_id=2 (ativa) - BENEFICIÁRIO
  id=502, aluno_id=95, pacote_contrato_id=4, status_id=2 (ativa) - BENEFICIÁRIO
  id=503, aluno_id=96, pacote_contrato_id=4, status_id=2 (ativa) - BENEFICIÁRIO

pagamentos_plano:
  id=X, matricula_id=500, valor=2.00, status_pagamento_id=2 (pago)
  id=Y, matricula_id=501, valor=0.50, status_pagamento_id=2 (pago)
  id=Z, matricula_id=502, valor=0.50, status_pagamento_id=2 (pago)
  id=W, matricula_id=503, valor=0.50, status_pagamento_id=2 (pago)

assinaturas:
  id=300, matricula_id=500, status_id=2 (ativa)
  (próximas cobranças acontecerão automaticamente)

pacote_contratos:
  id=4, status='ativo', pagamento_id=146079536501
```

---

## 📱 Cliente Vê Resultado

```
Frontend polling/websocket detecta:
  GET /mobile/pacotes/contratos/4
  → status = "ativo"
    ↓
UI muda de "Pagar" para "Ativo"
    ↓
Cliente vê:
  ✅ Pacote 3 Alunos - ATIVO
  ✅ 4 matrículas criadas
  ✅ Cobrança mensal automática
```

---

## 🔄 Ciclo Mensal Automático (Se Recorrente)

```
Mês 1:
  Webhook #1 (assinatura aprovada)
  Webhook #2 (primeiro pagamento)
  ✅ Matrículas criadas

Mês 2:
  MP cobra automaticamente
  Webhook #2 (segundo pagamento, payment_id diferente)
  ✅ Novo pagamento na tabela pagamentos_plano

Mês 3:
  MP cobra automaticamente
  Webhook #2 (terceiro pagamento)
  ✅ Novo pagamento registrado

...E assim por diante até cancelar
```

---

## 🎯 Resumo do Fluxo Completo

```
┌─────────────────────────────────────────────────────────────────┐
│ POST /mobile/pacotes/contratos/4/pagar                          │
└────────────┬────────────────────────────────────────────────────┘
             │
             ├─→ ✅ Validar contrato (existe, pendente, seu)
             ├─→ ✅ Reusar payment_url se já existe
             ├─→ ✅ Montar dados para pagamento
             ├─→ ✅ Decidir: recorrente vs avulso
             ├─→ ✅ Chamar MP API (PREAPPROVAL ou PREFERENCE)
             ├─→ ✅ Salvar URL + preference_id no banco
             │
             └─→ Retornar payment_url
                    │
                    ├─→ Frontend redireciona cliente
                         │
                         └─→ https://checkout.mercadopago.com/...
                              │
                              ├─→ Cliente entra dados cartão
                              ├─→ MP processa pagamento
                              ├─→ ✅ APROVADO
                              │
                              ├─→ Webhook #1: subscription_preapproval
                              │     └─→ criarMatriculaPagantePacote()
                              │     └─→ ✅ Matrícula pagante + Assinatura
                              │
                              └─→ Webhook #2: payment (primeira cobrança)
                                    └─→ processarPagamentoPacote()
                                    └─→ ✅ Matrículas beneficiários
                                    └─→ ✅ Pagamentos marcados
                                    └─→ ✅ Contrato ativo
```

---

## 🔑 Ponto-Chave da Nova Solução

**Sem metadata (webhook pode chegar quebrado):**
1. **Webhook de assinatura** cria a matrícula do pagante **CEDO**
2. Armazena `pacote_contrato_id` na assinatura
3. **Webhook de pagamento** busca a assinatura anterior
4. Recupera pacote mesmo sem metadata ✅

**Com metadata (webhook chega 100% OK):**
1. Usa metadados para processar mais rápido
2. Fallback para assinatura se metadata vazio
3. Mesmo assim funciona ✅

---

## ✨ O Que Você Implementou Resolve

**Pagamento 146079536501:**
```
❌ ANTES: Webhook chegava sem metadata → Falha silenciosa
✅ DEPOIS: Webhook busca assinatura anterior → Funciona!
```

Qualquer variação do Mercado Pago no payload agora é tratada.

