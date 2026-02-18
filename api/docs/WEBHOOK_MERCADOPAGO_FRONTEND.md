# Documentação: Webhook Mercado Pago - AppCheckin API

## 📋 Visão Geral

O webhook Mercado Pago é a **porta de entrada** para notificações de pagamentos e assinaturas recorrentes. Este documento descreve como o sistema processa essas notificações e o que o frontend precisa saber.

---

## 🔗 Endpoint do Webhook

```
POST /api/webhooks/mercadopago
```

**Base URL:** 
- Produção: `https://appcheckin.com.br/api/webhooks/mercadopago`
- Desenvolvimento: `http://localhost:8080/api/webhooks/mercadopago`

**Autenticação:** None (o webhook é público)

**Content-Type:** `application/json`

---

## 📥 Payload Recebido do Mercado Pago

O Mercado Pago envia notificações em 2 formatos:

### Tipo 1: Notificação de Pagamento
```json
{
  "id": 1234567890,
  "type": "payment",
  "data": {
    "id": "146749614928"
  }
}
```

### Tipo 2: Notificação de Assinatura (Preapproval)
```json
{
  "id": 1234567890,
  "type": "subscription_preapproval",
  "data": {
    "id": "PREAPPROVAL_ID_MP"
  }
}
```

### Tipo 3: Notificação de Pagamento de Assinatura
```json
{
  "id": 1234567890,
  "type": "subscription",
  "data": {
    "id": "SUBSCRIPTION_ID_MP"
  }
}
```

---

## 🎯 Como o Webhook Funciona

### 1️⃣ Recepção
O webhook **recebe notificação do Mercado Pago** com apenas o `payment_id` ou `subscription_id`.

### 2️⃣ Busca de Detalhes
A API faz uma **chamada reversa** à API do Mercado Pago para obter:
- Status do pagamento (`approved`, `pending`, `rejected`, etc)
- Metadados do pagamento (tenant_id, aluno_id, tipo, etc)
- External Reference (identificador customizado: `MAT-123-timestamp` ou `PAC-123-timestamp`)

### 3️⃣ Identificação do Tipo
Baseado no `external_reference`, o sistema identifica:

| Prefixo | Tipo | Ação |
|---------|------|------|
| `MAT-` | Matrícula Avulsa | Cria/atualiza matrícula individual |
| `PAC-` | Pacote (Dependentes) | Cria matrículas para pagante + beneficiários |

### 4️⃣ Processamento
Se `status = 'approved'`:
- ✅ Ativa matrícula(s)
- ✅ Cria registro em `pagamentos_plano`
- ✅ Para pacotes: cria assinatura **APENAS** para o pagante
- ✅ Registra resultado em `webhook_payloads_mercadopago`

---

## 📦 Fluxos de Pagamento

### Fluxo 1: Matrícula Avulsa
```
Frontend                    |    API AppCheckin              |    Mercado Pago
─────────────────────────────────────────────────────────────────────────────
User clica "Comprar Plano"
                            | POST /mobile/comprar-plano
                            | → Cria matrícula (status: pendente)
                            | → Gera link MP com external_ref="MAT-{id}-{ts}"
                            | ← Retorna preferenceId + init_point
Redireciona para MP         |
(checkout)                  |
                            |                              ← User paga
                            | ← Webhook: payment.approved
                            | → Busca detalhes do payment
                            | → Identifica MAT-X-Y
                            | → Ativa matrícula #X
                            | → Cria assinatura (se recorrente)
User retorna ao app/web    | ← Salva webhook_payloads
(após pagamento)            |
                            | Matricula já está ATIVA
```

### Fluxo 2: Pacote com Dependentes
```
Frontend                    |    API AppCheckin              |    Mercado Pago
─────────────────────────────────────────────────────────────────────────────
User clica "Comprar Pacote" |
                            | POST /pacotes/{id}/contratar
                            | → Cria pacote_contrato (status: pendente)
                            | → Cria beneficiários (dependentes)
                            | ← Retorna link MP com externa_ref="PAC-{id}-{ts}"
                            |
Frontend mostra modal       |
"Selecione Beneficiários"   |
                            | POST /pacotes/contratos/{id}/beneficiarios
                            | (Frontend envia lista de alunos)
                            | → Salva 3 beneficiários
                            |
User clica "Pagar"          |
                            | Gera checkout MP com PAC-{contratoId}
Redireciona MP              |
                            |                               ← Webhook
                            | POST /webhooks/mercadopago
                            | ← type: "payment"
                            | ← external_reference: "PAC-3-1771427607"
                            | → Busca pelo PAC-3-XXXX
                            | → Encontra contrato ID=3
                            | → Busca pagante: usuario_id=3 → aluno_id=72
                            | → Busca beneficiários: alunos 94, 95, 96
                            | → Cria 4 matrículas (pagante + 3 beneficiários)
                            | → Cria 1 assinatura (APENAS para aluno 72)
                            | → Rateio: R$ 2.00 ÷ 4 = R$ 0.50 cada
User retorna               | ← Salva tudo em transação DB
(pacote está ATIVO)        |
```

---

## 🔍 External Reference (Identificador Customizado)

O `external_reference` é **crucial** para correlacionar pagamentos com contratos locais.

### Formato Matrícula
```
MAT-{matricula_id}-{timestamp}
Exemplo: MAT-123-1708107600
```

### Formato Pacote
```
PAC-{pacote_contrato_id}-{timestamp}
Exemplo: PAC-3-1771427607
```

### Como Usar no Frontend

Ao criar a preference de pagamento no Mercado Pago, enviar:

```javascript
const preferenceData = {
  items: [...],
  external_reference: `MAT-${matriculaId}-${Date.now()}`, // Para matrícula
  // OU
  external_reference: `PAC-${contratoId}-${Date.now()}`,  // Para pacote
  metadata: {
    tipo: "matricula", // ou "pacote"
    matricula_id: matriculaId,
    tenant_id: tenantId,
    aluno_id: alunoId,
    // ... outros dados úteis
  }
};
```

---

## ✅ Estados da Matrícula

Após pagamento aprovado:

| Estado | Significado | Próximo Passo |
|--------|-------------|---------------|
| `pendente` | Antes do pagamento | User pagar |
| `ativa` | Pagamento aprovado | User pode usar |
| `vencida` | Passou data de término | Renovar/comprar novo |
| `cancelada` | Cancelada manualmente | Desaparecer de planos ativos |

---

## 🔐 Estados da Assinatura (Recorrente)

| Estado | Significado | Ação |
|--------|-------------|------|
| `ativa` | Assinatura autorizada e cobrando | Renovações automáticas |
| `pausada` | Temporariamente parada | User pode reativar |
| `cancelada` | Cancelada permanentemente | Sem renovações |
| `pendente` | Aguardando 1ª aprovação | Pode não ter cobrado ainda |

---

## 📊 Resposta do Webhook

A API **não retorna dados do webhook** (apenas HTTP 200). O frontend **não espera resposta**.

```
POST /api/webhooks/mercadopago
→ HTTP 200 OK
(sem body ou com mensagem simples)
```

---

## 🎯 Casos de Uso - O Que Cada Página Precisa Fazer

### 1. Página de Compra de Plano Avulso

```javascript
// 1. User clica "Comprar"
async function comprarPlano(planoId) {
  const response = await fetch('/api/mobile/comprar-plano', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ plano_id: planoId })
  });
  
  const data = await response.json();
  
  // 2. Redirecionar para Mercado Pago
  window.location.href = data.init_point;
  // MP vai notificar nosso webhook automaticamente
}

// 3. Após user retornar da MP (sucesso ou não)
// A matrícula será ativada (ou falhar) recebendo o webhook
// Chamar endpoint para verificar status:

async function verificarStatusMatricula(matriculaId) {
  const response = await fetch(`/api/mobile/matriculas/${matriculaId}`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json();
}
```

### 2. Página de Pacote com Dependentes

```javascript
// 1. User clica "Contratar Pacote"
async function contratarPacote(pacoteId) {
  const response = await fetch(`/api/admin/pacotes/${pacoteId}/contratar`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ 
      pagante_usuario_id: usuarioId,
      tenant_id: tenantId 
    })
  });
  
  const { contrato_id, init_point } = await response.json();
  return contrato_id;
}

// 2. Modal: "Selecione os Dependentes"
async function definirBeneficiarios(contratoId, alunoIds) {
  const response = await fetch(`/api/admin/pacotes/contratos/${contratoId}/beneficiarios`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify({ aluno_ids: alunoIds })
  });
  
  return response.json();
}

// 3. Redirecionar para MP (ele dispara webhook automaticamente)
window.location.href = initPoint;

// 4. Após retorno, verificar se contrato virou "ativo"
async function verificarPacote(contratoId) {
  const response = await fetch(`/api/admin/pacotes/contratos/${contratoId}`, {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  return response.json(); // status deve ser 'ativo'
}
```

---

## 🚨 Tratamento de Erros

### Webhook não chega / Matrícula não ativa

**Causa comum:** Metadata vazia no pagamento MP

**Solução:**
1. Garantir que `external_reference` está preenchido (`MAT-X-Y` ou `PAC-X-Y`)
2. Incluir `metadata` com `tipo`, `tenant_id`, etc.
3. Sistema faz fallback: extrai `pacote_contrato_id` do `external_reference`

### Matrícula falha a ativar

**Debug:**
```javascript
// Frontend pode consultar logs de webhook:
GET /api/webhooks/mercadopago/list (admin only)
GET /api/webhooks/mercadopago/show/{webhookId} (admin only)
```

### Recovery Automático

Sistema executa **CRON a cada 5 minutos** que:
- Busca webhooks com `status='sucesso'` mas `matricula_id=null`
- Reprocessa automaticamente
- Cria matrículas perdidas

---

## 🔗 Endpoints Relacionados

### Para Mobile/User
```
POST   /mobile/comprar-plano              → Comprar plano avulso
GET    /mobile/matriculas/{id}            → Status da matrícula
POST   /mobile/verificar-pagamento        → Verificar se pagou
GET    /mobile/planos                     → Listar planos disponíveis
GET    /mobile/pacotes/pendentes          → Listar pacotes do user
POST   /mobile/pacotes/contratos/{id}/pagar → Pagar pacote
```

### Para Admin
```
POST   /admin/pacotes                     → Criar pacote
POST   /admin/pacotes/{id}/contratar      → Contratar pacote
POST   /admin/pacotes/contratos/{id}/beneficiarios → Definir dependentes
GET    /admin/pacotes                     → Listar pacotes
GET    /admin/matriculas                  → Listar todas matrículas
POST   /admin/pacotes/contratos/{id}/confirmar-pagamento → Marcar como pago
```

### Debug (Admin/Dev)
```
GET    /api/webhooks/mercadopago/list                    → Listar webhooks recebidos
GET    /api/webhooks/mercadopago/show/{id}               → Detalhes webhook
GET    /api/webhooks/mercadopago/payment/{paymentId}     → Consultar MP direto
POST   /api/webhooks/mercadopago/payment/{id}/reprocess  → Reprocessar manualmente
```

---

## 📝 Checklist: Antes de Rodar em Produção

- [ ] Configurar webhook no Mercado Pago apontando para `/api/webhooks/mercadopago`
- [ ] Certificar que `external_reference` é sempre enviado nos checkouts
- [ ] Testar fluxo completo: plano avulso → pagamento → ver matrícula ativa
- [ ] Testar fluxo pacote: contratar → definir dependentes → pagar → ver 4 matrículas
- [ ] Verificar que assinatura recorrente é criada **APENAS** para pagante (não beneficiários)
- [ ] Testar CRON reprocessamento: simular webhook perdido e verificar recovery
- [ ] Verificar logs em `webhook_payloads_mercadopago` table
- [ ] Testar cancelamento de matrícula e impacto em assinatura

---

## 🎓 Exemplos Reais (curl/postman)

### Simular Webhook Mercado Pago (Test)
```bash
curl -X POST https://appcheckin.com.br/api/webhooks/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "id": 1234567890,
    "type": "payment",
    "data": {
      "id": "146749614928"
    }
  }'
```

### Verificar Status de Matrícula
```bash
curl -X GET https://appcheckin.com.br/api/mobile/matriculas/123 \
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

### Listar Webhooks Recebidos (Admin)
```bash
curl -X GET https://appcheckin.com.br/api/webhooks/mercadopago/list \
  -H "Authorization: Bearer YOUR_ADMIN_JWT_TOKEN"
```

---

## 📞 Contato / Suporte

Para dúvidas sobre integração ou erros de webhook, verificar:
1. Logs: `/api/webhooks/mercadopago/list`
2. Arquivo de log: `storage/logs/webhook_mp.log`
3. Database: `webhook_payloads_mercadopago` table

---

**Versão:** 1.0  
**Data:** 18 de Fevereiro de 2026  
**Status:** ✅ Em Produção
