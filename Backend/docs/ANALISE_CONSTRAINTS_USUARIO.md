# Análise de Constraints: CPF e Email

## 📊 Estado Atual das Constraints

### Tabela `usuarios`

```sql
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cpf VARCHAR(14) UNIQUE NOT NULL,         -- ❌ GLOBAL UNIQUE
    email VARCHAR(255) UNIQUE NOT NULL,      -- ⚠️ INCONSISTENT
    email_global VARCHAR(255) UNIQUE,        -- ✅ GLOBAL UNIQUE
    nome VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    tenant_id INT NOT NULL,
    status ENUM('ativo', 'inativo'),
    ...
    KEY idx_tenant (tenant_id),
    KEY idx_email (email),
    KEY idx_cpf (cpf)
);
```

## 🚨 Problemas Identificados

### Problema 1: CPF é GLOBALMENTE UNIQUE
**Status:** ❌ **CRÍTICO**

```
┌─────────────────────────────────────────────────────┐
│ TENANT A              │ TENANT B                    │
│ Usuario: João         │ Usuario: João               │
│ CPF: 123.456.789-01   │ CPF: 123.456.789-01 ❌      │
│                       │ ERRO: Duplicate key!        │
└─────────────────────────────────────────────────────┘
```

**Impacto:**
- ❌ Impossível ter mesmo CPF em múltiplos tenants
- ❌ Viola isolamento multi-tenant
- ❌ Limita migração de usuários entre tenants

**Recomendação:** Mudar para `UNIQUE(cpf, tenant_id)`

---

### Problema 2: Email tem DUAS colunas

```
┌──────────────────────────────────────────────────┐
│ email VARCHAR(255) UNIQUE                        │
│ email_global VARCHAR(255) UNIQUE                 │
│                                                  │
│ Qual é usada no login?                          │
│ Qual é "global" (cross-tenant)?                 │
└──────────────────────────────────────────────────┘
```

**Impacto:**
- ⚠️ **Ambíguo**: Qual campo usar para autenticação?
- ⚠️ **Duplicação**: Dados redundantes em 2 colunas
- ⚠️ **Validação**: Como garantir consistência?

---

## 🔍 Análise por Cenário

### Cenário A: Sistema Single-Tenant (um usuário = um tenant)

```
REGRA: email UNIQUE GLOBAL (qualquer um em qualquer tenant)
CPF UNIQUE GLOBAL

SCHEMA:
- email VARCHAR(255) UNIQUE (mantém como está)
- cpf VARCHAR(14) UNIQUE (mantém como está)
- email_global REMOVER (redundante)

RESULTADO: ✅ Simples
```

---

### Cenário B: Sistema Multi-Tenant com email por tenant

```
REGRA: Email pode repetir em tenants diferentes
       CPF pode repetir em tenants diferentes
       Cada usuario está em APENAS 1 tenant

SCHEMA:
- email VARCHAR(255) ← REMOVE UNIQUE
- cpf VARCHAR(14) ← REMOVE UNIQUE
- ADD CONSTRAINT UNIQUE(email, tenant_id)
- ADD CONSTRAINT UNIQUE(cpf, tenant_id)
- email_global VARCHAR(255) UNIQUE (para SSO/global login)

RESULTADO: ✅ Multi-tenant isolado
```

---

### Cenário C: Sistema Multi-Tenant com múltiplos tenants por usuário

```
REGRA: Um usuário pode estar em múltiplos tenants
       Mas email/CPF são GLOBALMENTE ÚNICOS (cross-tenant)
       
SCHEMA:
- email VARCHAR(255) UNIQUE (único em todos tenants)
- cpf VARCHAR(14) UNIQUE (único em todos tenants)
- email_global VARCHAR(255) UNIQUE (opcional, para SSO)
- Tabela: usuario_tenant (1:N relationship)

RESULTADO: ✅ Usuário global, acesso por tenant
```

---

## 📋 Recomendações

### 1️⃣ **Definir Modelo de Usuário**

Qual é o seu modelo atual?

```php
// Opção A: Usuário vinculado a 1 tenant
$user->tenant_id = 5;  // Sempre 1 tenant

// Opção B: Usuário pode estar em múltiplos tenants  
$user->tenants = [1, 3, 5];  // Tabela usuario_tenant
```

**Nossa análise descobriu que você está usando OPÇÃO B** (tabela `usuario_tenant` existe).

### 2️⃣ **Ajustar CPF**

**RECOMENDAÇÃO:**

```sql
-- ANTES (❌ Problemático)
ALTER TABLE usuarios DROP UNIQUE KEY `cpf`;
ALTER TABLE usuarios MODIFY cpf VARCHAR(14) NOT NULL;
ALTER TABLE usuarios ADD UNIQUE KEY `unique_cpf_tenant` (cpf, tenant_id);

-- Resultado:
-- ✅ CPF 123.456.789-01 pode existir em TENANT 1 e TENANT 2
-- ✅ Mas não 2x no mesmo TENANT
-- ✅ Mantém isolamento multi-tenant
```

### 3️⃣ **Clarificar Email**

**OPÇÃO 1 - Recomendado (Simplificar):**

```sql
-- Usar apenas 1 coluna
ALTER TABLE usuarios DROP COLUMN email_global;
ALTER TABLE usuarios MODIFY email VARCHAR(255) NOT NULL;
ALTER TABLE usuarios ADD UNIQUE KEY `unique_email_tenant` (email, tenant_id);

-- Resultado:
-- ✅ email = login dentro do tenant
-- ✅ Pode repetir em tenants diferentes
-- ✅ Mais simples
```

**OPÇÃO 2 - Se precisa SSO Global:**

```sql
-- Manter ambas, ser explícito
ALTER TABLE usuarios DROP UNIQUE KEY `email`;
ALTER TABLE usuarios MODIFY email VARCHAR(255) NOT NULL;
ALTER TABLE usuarios ADD UNIQUE KEY `unique_email_tenant` (email, tenant_id);
ALTER TABLE usuarios MODIFY email_global VARCHAR(255);
-- email_global pode ser NULL (usuários locais apenas)

-- Resultado:
-- ✅ email = login por tenant (pode repetir)
-- ✅ email_global = login global (único) (opcional)
```

---

## 🔐 Validações de Segurança

### Antes de Alterar Constraints:

1. **Auditoria de Dados Existentes:**

```sql
-- Verificar CPFs duplicados GLOBALMENTE
SELECT cpf, COUNT(*) as qtd_usuarios
FROM usuarios
GROUP BY cpf HAVING COUNT(*) > 1;

-- Verificar emails duplicados GLOBALMENTE
SELECT email, COUNT(*) as qtd_usuarios
FROM usuarios
GROUP BY email HAVING COUNT(*) > 1;

-- Verificar emails no mesmo tenant
SELECT tenant_id, email, COUNT(*) as qtd
FROM usuarios
GROUP BY tenant_id, email HAVING COUNT(*) > 1;
```

2. **Estratégia de Limpeza:**

```sql
-- Se houver duplicatas, fazer merge ou eliminar duplicadas
-- Exemplo: João (id=1, tenant=1, cpf=123) vs João2 (id=50, tenant=2, cpf=123)
-- OPÇÃO A: Manter ambos (CPF terá (123, 1) e (123, 2))
-- OPÇÃO B: Consolidar dados + desativar duplicatas
```

---

## 📝 Implementação da Validação no Backend

### Atualmente (SEM validação de email/CPF):

```php
// ❌ Falta validação
public function criar(Request $request, Response $response) {
    $email = $body['email'];
    $cpf = $body['cpf'];
    
    // Inserir direto
    $stmt = $db->prepare("
        INSERT INTO usuarios (email, cpf, tenant_id, ...)
        VALUES (?, ?, ?, ...)
    ");
    // Se email já existe globalmente → ERRO 1062 do DB
}
```

### Proposta (COM validação)

```php
public function criar(Request $request, Response $response) {
    $userId = $request->getAttribute('userId');
    $tenantId = $request->getAttribute('tenantId');
    $body = $request->getParsedBody();
    
    // ✅ VALIDAÇÃO 1: Multi-tenant
    $usuarioTenant = new UsuarioTenant($db);
    if (!$usuarioTenant->validarAcesso($userId, $tenantId)) {
        return $response->withStatus(403);
    }
    
    // ✅ VALIDAÇÃO 2: Email único por tenant
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM usuarios 
        WHERE email = ? AND tenant_id = ?
    ");
    $stmt->execute([$body['email'], $tenantId]);
    if ($stmt->fetchColumn() > 0) {
        return error('Email já registrado neste tenant');
    }
    
    // ✅ VALIDAÇÃO 3: CPF único por tenant
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM usuarios 
        WHERE cpf = ? AND tenant_id = ?
    ");
    $stmt->execute([$body['cpf'], $tenantId]);
    if ($stmt->fetchColumn() > 0) {
        return error('CPF já registrado neste tenant');
    }
    
    // ✅ VALIDAÇÃO 4: email_global se for usado para SSO
    if (!empty($body['email_global'])) {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM usuarios 
            WHERE email_global = ? AND id != ?
        ");
        $stmt->execute([$body['email_global'], $body['id'] ?? 0]);
        if ($stmt->fetchColumn() > 0) {
            return error('Email global já registrado (SSO)');
        }
    }
    
    // Inserir...
}
```

---

## ✅ Checklist de Ação

- [ ] **Definir**: Qual é o modelo de usuário? (Opção A, B ou C?)
- [ ] **Auditoria**: Verificar dados existentes (ver queries acima)
- [ ] **Decisão**: Qual strategy para CPF/Email? (Opção 1 ou 2?)
- [ ] **Migrate**: Executar ALTER TABLE com backup
- [ ] **Validar**: Testar inserção com dados válidos/inválidos
- [ ] **Code**: Adicionar validações no backend (padrão acima)
- [ ] **Test**: Testar cross-tenant (não deve vazar dados)

---

## 📚 Referências

**Arquivo de Schema:**
- [Revisar schema atual](../config/database.php)

**Controllers relacionados:**
- [UsuarioController](../app/Controllers/UsuarioController.php)
- [AuthController](../app/Controllers/AuthController.php)

**Models:**
- [UsuarioTenant](../app/Models/UsuarioTenant.php) ← Usar para validações

---

**Status:** 🔴 **BLOQUEADO** - Aguardando decisão sobre modelo de usuário
