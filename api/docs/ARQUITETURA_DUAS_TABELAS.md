# Arquitetura: Sistema de Duas Tabelas para Multi-Tenant

**Data da Decisão:** 03 de Fevereiro de 2026  
**Status:** ✅ Implementado e Documentado

---

## 📋 Contexto

Durante a análise do banco de dados, identificamos duas tabelas que aparentemente tinham responsabilidades sobrepostas:
- `usuario_tenant`
- `tenant_usuario_papel`

Após análise detalhada, optamos por **manter ambas as tabelas** com responsabilidades distintas ao invés de consolidá-las em uma única tabela.

---

## 🎯 Decisão Arquitetural

### ✅ **Opção Escolhida: Manter Duas Tabelas**

Separação de responsabilidades seguindo o **Single Responsibility Principle (SRP)**:

#### 1️⃣ **Tabela: `usuario_tenant`**
**Responsabilidade:** Vínculo básico usuário ↔ tenant + Status + Plano

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT | Chave primária |
| `usuario_id` | INT | FK para `usuarios.id` |
| `tenant_id` | INT | FK para `tenants.id` |
| `plano_id` | INT | FK para `planos.id` (plano/assinatura) |
| `status` | ENUM | `'ativo'` ou `'inativo'` |
| `data_inicio` | DATE | Data de início do vínculo |
| `data_fim` | DATE | Data de término (NULL se ativo) |
| `created_at` | TIMESTAMP | - |
| `updated_at` | TIMESTAMP | - |

**Cardinalidade:** 1 registro por usuário/tenant  
**Use Case:** "Este usuário está vinculado a este tenant? Qual seu plano? Está ativo?"

---

#### 2️⃣ **Tabela: `tenant_usuario_papel`**
**Responsabilidade:** Papéis/Permissões do usuário no tenant

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | INT | Chave primária |
| `tenant_id` | INT | FK para `tenants.id` |
| `usuario_id` | INT | FK para `usuarios.id` |
| `papel_id` | INT | FK para `papeis.id` (1=Aluno, 2=Professor, 3=Admin, 4=SuperAdmin) |
| `ativo` | TINYINT(1) | Papel ativo ou não |
| `created_at` | TIMESTAMP | - |
| `updated_at` | TIMESTAMP | - |

**Cardinalidade:** N registros por usuário/tenant (múltiplos papéis)  
**Use Case:** "Quais papéis este usuário tem neste tenant?"

---

## 🔍 Exemplo Prático

### Cenário: João é aluno E professor no tenant "Academia XYZ"

```sql
-- Tabela: usuario_tenant (1 registro)
-- Define QUE João está vinculado ao tenant, seu status e plano
INSERT INTO usuario_tenant (usuario_id, tenant_id, plano_id, status, data_inicio)
VALUES (10, 5, 2, 'ativo', '2026-01-01');

-- Tabela: tenant_usuario_papel (2 registros)
-- Define QUAIS papéis João tem no tenant
INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
VALUES (5, 10, 1, 1); -- papel_id=1 (Aluno)

INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
VALUES (5, 10, 2, 1); -- papel_id=2 (Professor)
```

**Resultado:**
- João tem **1 vínculo** com o tenant (plano Premium, status ativo)
- João tem **2 papéis** no tenant (Aluno e Professor simultaneamente)

---

## ✅ Vantagens da Arquitetura Escolhida

| Vantagem | Descrição |
|----------|-----------|
| **Separação de Concerns** | Vínculo/plano separado de permissões/roles |
| **Flexibilidade** | Usuário pode ter múltiplos papéis sem duplicar dados de plano |
| **Manutenção** | Alterações em plano não afetam lógica de papéis |
| **Clareza** | Código mais legível com responsabilidades bem definidas |
| **Baixo Risco** | Não requer refatoração de 25+ queries existentes |

---

## ⚠️ Alternativa Rejeitada

### ❌ **Consolidar tudo em `tenant_usuario_papel`**

Mover `plano_id`, `status`, `data_inicio`, `data_fim` para `tenant_usuario_papel`.

**Por que foi rejeitada:**
- ✗ Mistura lógica de plano/assinatura com lógica de permissões
- ✗ Requer atualizar 25+ queries em 4 Models
- ✗ Alto risco de quebrar funcionalidades existentes
- ✗ Viola o Single Responsibility Principle

---

## 📊 Impacto nos Models

### Models Afetados:

| Model | Referências a `usuario_tenant` | Status |
|-------|--------------------------------|--------|
| `Usuario.php` | 17 referências | ✅ Mantidas (corretas) |
| `UsuarioTenant.php` | 4 referências | ✅ Mantidas (model dedicado) |
| `Tenant.php` | 1 referência | ✅ Mantida (JOIN correto) |
| `Aluno.php` | 3 referências | ✅ Mantidas (DELETE cascade) |

**Total:** 25 referências mantidas intencionalmente.

---

## 🛠️ Ações Executadas

### 1. ✅ Migração de Dados (2026-02-03)
```sql
-- Script: database/migrations/consolidar_tabelas_usuario.sql
-- Resultado: 12 registros migrados, 0 orphans
-- Backups criados: usuario_tenant_backup, tenant_usuario_papel_backup
```

### 2. ✅ Limpeza de Colunas Temporárias
```sql
-- Script: database/migrations/limpar_colunas_temporarias.sql
-- Removidas: plano_id_temp, status_temp, data_inicio_temp, data_fim_temp
```

### 3. ✅ Documentação em Code
- Comentários adicionados em `Usuario.php`
- Comentários adicionados em `UsuarioTenant.php`

---

## 📖 Como Usar

### Query: Buscar usuário com vínculo e papéis

```php
$sql = "
    SELECT 
        u.*,
        ut.status as vinculo_status,
        ut.plano_id,
        ut.data_inicio,
        ut.data_fim,
        GROUP_CONCAT(tup.papel_id) as papeis
    FROM usuarios u
    INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
    LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id 
        AND tup.tenant_id = ut.tenant_id 
        AND tup.ativo = 1
    WHERE u.id = :usuario_id 
    AND ut.tenant_id = :tenant_id
    AND ut.status = 'ativo'
    GROUP BY u.id
";
```

### Query: Verificar se usuário tem papel específico

```php
$sql = "
    SELECT COUNT(*) > 0 as tem_papel
    FROM tenant_usuario_papel
    WHERE usuario_id = :usuario_id
    AND tenant_id = :tenant_id
    AND papel_id = :papel_id  -- 1=Aluno, 2=Professor, 3=Admin
    AND ativo = 1
";
```

---

## 🔗 Referências

- Model: [`app/Models/Usuario.php`](../app/Models/Usuario.php)
- Model: [`app/Models/UsuarioTenant.php`](../app/Models/UsuarioTenant.php)
- Migração: [`database/migrations/consolidar_tabelas_usuario.sql`](../database/migrations/consolidar_tabelas_usuario.sql)
- Limpeza: [`database/migrations/limpar_colunas_temporarias.sql`](../database/migrations/limpar_colunas_temporarias.sql)

---

## 📝 Notas

- Esta arquitetura foi escolhida após análise detalhada dos dados existentes
- A migração preservou todos os dados com backups automáticos
- As duas tabelas coexistem **intencionalmente** e não devem ser mescladas
- Qualquer dúvida sobre esta decisão, consulte este documento

---

**Aprovado por:** Equipe de Desenvolvimento  
**Data:** 03/02/2026  
**Revisão:** v1.0
