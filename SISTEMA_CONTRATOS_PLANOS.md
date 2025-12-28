# Sistema de Contratos de Planos

## Visão Geral

O sistema de contratos gerencia a associação entre Tenants (academias) e Planos de forma completa, mantendo histórico de mudanças, datas de vigência e formas de pagamento.

### Características

- **Contratos com períodos definidos**: Cada tenant tem um contrato com data de início e vencimento
- **Formas de pagamento**: Suporta `cartao`, `pix` e `operadora`
- **Histórico completo**: Mantém registro de todos os contratos (ativos e inativos)
- **Troca de plano**: Desativa o plano anterior automaticamente ao criar um novo
- **Renovação**: Sistema de renovação que mantém o histórico
- **Alertas**: Endpoints para identificar contratos próximos do vencimento ou vencidos

---

## Estrutura da Tabela `tenant_planos`

```sql
id                 INT (PK)
tenant_id          INT (FK → tenants)
plano_id           INT (FK → planos)
data_inicio        DATE
data_vencimento    DATE
forma_pagamento    ENUM('cartao', 'pix', 'operadora')
status             ENUM('ativo', 'inativo', 'cancelado')
observacoes        TEXT
created_at         TIMESTAMP
updated_at         TIMESTAMP
```

### Regras de Negócio

1. **Apenas um contrato ativo por tenant** - Constraint `UNIQUE KEY uk_tenant_ativo (tenant_id, status)`
2. **Período mensal** - Por padrão, contratos têm duração de 1 mês
3. **Histórico preservado** - Contratos antigos ficam com `status = 'inativo'`
4. **Troca automática** - Ao trocar de plano, o sistema desativa o anterior

---

## Endpoints da API

### 1. Criar Contrato para Academia

**POST** `/superadmin/academias/{id}/contrato`

Cria um novo contrato de plano para uma academia, desativando o contrato atual se existir.

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "plano_id": 1,
  "forma_pagamento": "pix",
  "data_inicio": "2025-01-01",
  "data_vencimento": "2025-02-01",
  "observacoes": "Contrato inicial"
}
```

**Campos obrigatórios:**
- `plano_id` (int)
- `forma_pagamento` (string: 'cartao', 'pix' ou 'operadora')

**Campos opcionais:**
- `data_inicio` (date, default: hoje)
- `data_vencimento` (date, default: hoje + 1 mês)
- `observacoes` (string)

**Response 201:**
```json
{
  "message": "Contrato criado com sucesso",
  "contrato_id": 5
}
```

---

### 2. Trocar Plano da Academia

**POST** `/superadmin/academias/{id}/trocar-plano`

Troca o plano ativo da academia, desativando o contrato atual e criando um novo.

**Body:**
```json
{
  "plano_id": 2,
  "forma_pagamento": "cartao",
  "observacoes": "Upgrade para plano Premium"
}
```

**Response 200:**
```json
{
  "message": "Plano trocado com sucesso",
  "contrato": {
    "success": true,
    "contrato_id": 6,
    "data_inicio": "2025-12-28",
    "data_vencimento": "2026-01-28"
  }
}
```

---

### 3. Listar Contratos da Academia

**GET** `/superadmin/academias/{id}/contratos`

Retorna o contrato ativo e o histórico completo de contratos de uma academia.

**Response 200:**
```json
{
  "contrato_ativo": {
    "id": 6,
    "tenant_id": 1,
    "plano_id": 2,
    "plano_nome": "Premium",
    "valor": 199.90,
    "max_usuarios": 50,
    "max_turmas": 20,
    "data_inicio": "2025-12-28",
    "data_vencimento": "2026-01-28",
    "forma_pagamento": "cartao",
    "status": "ativo",
    "observacoes": "Upgrade para plano Premium",
    "created_at": "2025-12-28 10:30:00"
  },
  "historico": [
    {
      "id": 6,
      "plano_nome": "Premium",
      "valor": 199.90,
      "data_inicio": "2025-12-28",
      "data_vencimento": "2026-01-28",
      "forma_pagamento": "cartao",
      "status": "ativo",
      "created_at": "2025-12-28 10:30:00"
    },
    {
      "id": 5,
      "plano_nome": "Básico",
      "valor": 99.90,
      "data_inicio": "2025-11-28",
      "data_vencimento": "2025-12-28",
      "forma_pagamento": "pix",
      "status": "inativo",
      "created_at": "2025-11-28 09:00:00"
    }
  ]
}
```

---

### 4. Renovar Contrato

**POST** `/superadmin/contratos/{id}/renovar`

Renova um contrato existente por mais um período (1 mês). Desativa o contrato atual e cria um novo com as mesmas condições.

**Body:**
```json
{
  "observacoes": "Renovação automática"
}
```

**Response 200:**
```json
{
  "message": "Contrato renovado com sucesso",
  "novo_contrato": {
    "success": true,
    "contrato_id": 7,
    "data_inicio": "2026-01-29",
    "data_vencimento": "2026-02-28"
  }
}
```

---

### 5. Contratos Próximos do Vencimento

**GET** `/superadmin/contratos/proximos-vencimento?dias=7`

Lista contratos ativos que vencerão nos próximos X dias (padrão: 7 dias).

**Query Parameters:**
- `dias` (int, opcional, default: 7)

**Response 200:**
```json
{
  "total": 3,
  "dias_alerta": 7,
  "contratos": [
    {
      "id": 10,
      "tenant_id": 5,
      "tenant_nome": "Academia Fitness",
      "email": "contato@fitness.com",
      "plano_nome": "Premium",
      "valor": 199.90,
      "data_vencimento": "2026-01-02",
      "forma_pagamento": "pix",
      "status": "ativo"
    }
  ]
}
```

---

### 6. Contratos Vencidos

**GET** `/superadmin/contratos/vencidos`

Lista todos os contratos ativos que já venceram (data_vencimento < hoje).

**Response 200:**
```json
{
  "total": 2,
  "contratos": [
    {
      "id": 8,
      "tenant_id": 3,
      "tenant_nome": "Box CrossFit",
      "email": "contato@crossfit.com",
      "plano_nome": "Básico",
      "data_vencimento": "2025-12-20",
      "forma_pagamento": "operadora",
      "status": "ativo"
    }
  ]
}
```

---

## Fluxos de Uso

### Fluxo 1: Cadastro de Nova Academia com Plano

```
1. POST /superadmin/academias
   Body: { ..., plano_id: 1, forma_pagamento: 'pix' }
   
   → Cria academia
   → Cria contrato automaticamente
   → Cria admin da academia

2. GET /superadmin/academias/{id}/contratos
   → Visualiza contrato ativo
```

### Fluxo 2: Troca de Plano

```
1. GET /superadmin/academias/{id}/contratos
   → Verifica plano atual

2. POST /superadmin/academias/{id}/trocar-plano
   Body: { plano_id: 2, forma_pagamento: 'cartao' }
   
   → Desativa contrato anterior (status = 'inativo')
   → Cria novo contrato (status = 'ativo')
   → Retorna novas datas

3. GET /superadmin/academias/{id}/contratos
   → Confirma novo plano ativo
   → Visualiza histórico
```

### Fluxo 3: Renovação de Contrato

```
1. GET /superadmin/contratos/proximos-vencimento?dias=7
   → Identifica contratos a vencer

2. POST /superadmin/contratos/{id}/renovar
   Body: { observacoes: 'Renovação mensal' }
   
   → Desativa contrato atual
   → Cria novo contrato (data_inicio = data_vencimento_anterior + 1 dia)
   → Mantém mesmo plano e forma_pagamento
```

---

## Validações e Regras

### Validações de Negócio

1. **forma_pagamento** deve ser: `cartao`, `pix` ou `operadora`
2. **data_vencimento** deve ser posterior a `data_inicio`
3. **plano_id** deve existir na tabela `planos`
4. **tenant_id** deve existir na tabela `tenants`
5. Apenas **um contrato ativo** por tenant (garantido por constraint)

### Permissões

- Todos os endpoints são exclusivos para **Super Admin** (role_id = 3)
- Middleware: `SuperAdminMiddleware` + `AuthMiddleware`

---

## Integração com Sistema Existente

### Mudanças no Cadastro de Academia

**Antes:**
```php
// Academia tinha plano_id direto
$academiaData = [
    'plano_id' => $data['plano_id']
];
```

**Depois:**
```php
// Plano é associado via contrato
$contratoData = [
    'tenant_id' => $tenantId,
    'plano_id' => $data['plano_id'],
    'forma_pagamento' => $data['forma_pagamento'] ?? 'pix'
];
$this->tenantPlanoModel->criar($contratoData);
```

### Migração de Dados Existentes

Se você tem academias com `plano_id` na tabela `tenants`, execute este script para migrar:

```sql
-- Criar contratos para academias que já têm plano_id
INSERT INTO tenant_planos (tenant_id, plano_id, data_inicio, data_vencimento, forma_pagamento, status)
SELECT 
    id as tenant_id,
    plano_id,
    CURDATE() as data_inicio,
    DATE_ADD(CURDATE(), INTERVAL 1 MONTH) as data_vencimento,
    'pix' as forma_pagamento,
    'ativo' as status
FROM tenants
WHERE plano_id IS NOT NULL;
```

---

## Exemplo de Uso Completo

```bash
# 1. Criar academia com plano
curl -X POST http://localhost/superadmin/academias \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Academia Premium Fit",
    "email": "contato@premiumfit.com",
    "senha_admin": "Admin@123",
    "plano_id": 1,
    "forma_pagamento": "pix"
  }'

# Response: { "tenant_id": 10, "admin_id": 25 }

# 2. Verificar contrato criado
curl -X GET http://localhost/superadmin/academias/10/contratos \
  -H "Authorization: Bearer {token}"

# 3. Trocar para plano premium
curl -X POST http://localhost/superadmin/academias/10/trocar-plano \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "plano_id": 2,
    "forma_pagamento": "cartao",
    "observacoes": "Upgrade solicitado pelo cliente"
  }'

# 4. Verificar contratos próximos do vencimento
curl -X GET "http://localhost/superadmin/contratos/proximos-vencimento?dias=15" \
  -H "Authorization: Bearer {token}"

# 5. Renovar contrato específico
curl -X POST http://localhost/superadmin/contratos/12/renovar \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "observacoes": "Renovação mensal automática"
  }'
```

---

## Próximos Passos Sugeridos

1. **Notificações**: Sistema de alertas automáticos para contratos próximos do vencimento
2. **Pagamentos**: Integração com gateway de pagamento para renovação automática
3. **Bloqueio**: Desativar tenant automaticamente quando contrato vencer
4. **Dashboard**: Métricas de contratos ativos, receita mensal, taxa de renovação
5. **Frontend**: Telas para gerenciar contratos no painel Super Admin
