# 🏗️ MELHORIAS ARQUITETURAIS IMPLEMENTADAS

## 📋 Resumo das Correções

Este documento consolida as melhorias implementadas para resolver inconsistências e dívidas técnicas no banco de dados.

---

## ✅ 1. Multi-tenant: Fonte Única de Verdade

### ❌ Problema Identificado
```
- usuarios.tenant_id (com default 1)
- tabela usuario_tenant (usuario_id, tenant_id, ...)

Risco: Usuário com tenant_id=1 mas vínculo ativo em outro tenant
```

### ✅ Solução Implementada
**Migration:** `003_remove_tenant_id_from_usuarios.sql`

- ✅ Removido `tenant_id` de `usuarios`
- ✅ `usuario_tenant` é a ÚNICA fonte de verdade
- ✅ Usuário pode participar de múltiplos tenants (modelo SaaS correto)
- ✅ Dados migrados automaticamente
- ✅ Email_global mantém identificação única

**Modelo Final:**
```sql
usuarios (
  id, nome, email, email_global, role_id
  -- SEM tenant_id
)

usuario_tenant (
  usuario_id, tenant_id, status, data_inicio, data_fim
  -- FONTE DE VERDADE para relação usuário-tenant
)
```

**Impacto no Código:**
- ✅ Sempre consultar tenant através de JOIN com `usuario_tenant`
- ✅ Verificar tenant ativo: `WHERE usuario_tenant.status = 'ativo'`
- ✅ Permitir usuário em múltiplos tenants simultaneamente

---

## ✅ 2. Check-in: Constraint Corrigida

### ❌ Problema Identificado
```sql
UNIQUE (usuario_id, horario_id)

Risco: Usuário só pode fazer checkin uma vez PARA SEMPRE naquele horário
Bloqueia checkins recorrentes (ex: aula de segunda às 18h toda semana)
```

### ✅ Solução Implementada
**Migration:** `040_fix_checkin_constraint.sql`

**Antes:**
```sql
UNIQUE KEY unique_usuario_horario (usuario_id, horario_id)
-- ❌ Bloqueia checkins recorrentes
```

**Depois:**
```sql
-- Adiciona coluna gerada automaticamente
data_checkin_date DATE GENERATED ALWAYS AS (DATE(data_checkin)) STORED

-- Nova constraint: 1 checkin por usuário por horário POR DIA
UNIQUE KEY unique_usuario_horario_data (usuario_id, horario_id, data_checkin_date)
-- ✅ Permite checkins recorrentes em dias diferentes
```

**Regra de Negócio:**
- ✅ Usuário pode fazer checkin no mesmo horário em dias diferentes
- ✅ Exemplo: Pode fazer checkin às 18h toda segunda-feira
- ✅ Não pode fazer 2 checkins no mesmo horário no mesmo dia

**Alternativa Disponível:**
Se preferir **1 checkin por dia** independente do horário:
```sql
UNIQUE (usuario_id, data_checkin_date)
```

---

## ✅ 3. Financeiro: Redundâncias Eliminadas

### ❌ Problema Identificado

#### 3.1 Redundância de Plano em Usuários
```
usuarios.plano_id + usuarios.data_vencimento_plano
matriculas.plano_id + matriculas.data_vencimento
contas_receber com informações de plano

Risco: Divergências (usuário diz X, matrícula diz Y, conta diz Z)
```

#### 3.2 FK com Nome Enganoso
```sql
pagamentos_contrato.contrato_id → tenant_planos_sistema(id)

Risco: Nome sugere apontar para tabela 'contratos' que não existe
```

### ✅ Solução Implementada

#### 3.1 Plano Removido de Usuários
**Migration:** `036_remove_plano_from_usuarios.sql`

- ✅ Removido `plano_id` de `usuarios`
- ✅ Removido `data_vencimento_plano` de `usuarios`
- ✅ **FONTE DE VERDADE:** `matriculas` (plano ativo do aluno)
- ✅ **INADIMPLÊNCIA:** `contas_receber`

**Modelo Final:**
```sql
usuarios (
  id, nome, email, role_id
  -- SEM plano_id, SEM data_vencimento_plano
)

matriculas (
  usuario_id, plano_id, data_inicio, data_vencimento, status
  -- FONTE DE VERDADE para plano ativo
)

contas_receber (
  usuario_id, plano_id, valor, data_vencimento, status_id
  -- FONTE DE VERDADE para inadimplência
)
```

#### 3.2 FK Renomeada
**Migration:** `041_rename_contrato_id.sql`

**Antes:**
```sql
pagamentos_contrato (
  contrato_id INT -- ❌ Nome enganoso
  FOREIGN KEY (contrato_id) REFERENCES tenant_planos_sistema(id)
)
```

**Depois:**
```sql
pagamentos_contrato (
  tenant_plano_id INT -- ✅ Nome claro
  FOREIGN KEY (tenant_plano_id) REFERENCES tenant_planos_sistema(id)
)
```

**Impacto no Código:**
- ⚠️ Atualizar backend: `contrato_id` → `tenant_plano_id`
- ⚠️ Atualizar frontend se usar esse campo

---

## ✅ 4. Status: Padronização Completa

### ❌ Problema Identificado
```
contas_receber.status = ENUM('pendente', 'pago', ...)
matriculas.status = ENUM('ativa', 'suspensa', ...)

+ tabelas status_conta, status_contrato (não usadas)

Risco: Duplicidade estrutural, dificulta manutenção
```

### ✅ Solução Implementada
**Migrations:** `037_*, 038_*, 039_*`

**Sistema Completo:**
- ✅ 6 tabelas de status criadas (conta_receber, matricula, pagamento, checkin, usuario, contrato)
- ✅ Campos ricos: `cor`, `icone`, `ordem`, `permite_edicao`, `permite_checkin`
- ✅ API REST: `/api/status/{tipo}`
- ✅ Frontend: `statusService.js` + `StatusBadge` component
- ✅ Migrations seguras (mantém ENUMs para rollback)

**Benefícios:**
- ✅ Adicionar status = INSERT (não precisa ALTER TABLE)
- ✅ Metadados para UI (cores, ícones)
- ✅ Auditável e escalável
- ✅ Regras de negócio (permite_edicao, permite_checkin)

**Documentação:**
- 📖 `SISTEMA_STATUS_PADRONIZADO.md`
- 🚀 `QUICK_START_STATUS.md`
- 💻 `EXEMPLO_ATUALIZACAO_MODEL.php`

---

## ✅ 5. Collation: Padronização UTF-8

### ❌ Problema Identificado
```
- Algumas tabelas: utf8mb4 (sem collation específica)
- Outras tabelas: utf8mb4_unicode_ci
- Inconsistência: utf8mb4_0900_ai_ci vs utf8mb4_unicode_ci

Risco: Comparações inconsistentes, problemas com ordenação, erros em JOINs
```

### ✅ Solução Implementada
**Migration:** `042_padronizar_collation.sql`

- ✅ Todas as tabelas convertidas para `utf8mb4_unicode_ci`
- ✅ Charset configurado na sessão
- ✅ Índices reconstruídos automaticamente

**Benefícios:**
- ✅ Comparações de strings consistentes
- ✅ Ordenação multilíngue correta
- ✅ Suporte completo a Unicode (emojis, acentos)
- ✅ Case-insensitive por padrão (a = A)

---

## ✅ 6. Regras de Unicidade

### ❌ Problema Identificado
```
- usuarios.email: sem UNIQUE explícito
- usuarios.cpf: sem UNIQUE (permite duplicação)
- contas_receber: permite duplicação de mensalidade do mesmo mês
- matriculas: permite múltiplas matrículas ativas do mesmo plano

Risco: Dados duplicados, cobranças indevidas, CPFs repetidos
```

### ✅ Solução Implementada
**Migration:** `043_adicionar_constraints_unicidade.sql`

**Constraints Adicionadas:**
```sql
-- Email global único
UNIQUE (email_global)

-- CPF único (NULL permitido)
UNIQUE (cpf)

-- Tenant nome único
UNIQUE (nome)

-- Tenant CNPJ único
UNIQUE (cnpj)

-- Contas: 1 por tenant/usuário/plano/mês
UNIQUE (tenant_id, usuario_id, plano_id, referencia_mes)
```

**Triggers Criados:**
- `validar_matricula_ativa_unica` - Previne múltiplas matrículas ativas
- `validar_matricula_ativa_unica_update` - Valida em UPDATE

**Regras de Negócio:**
- ✅ Email global único no sistema (login cross-tenant)
- ✅ CPF único (múltiplos NULL permitidos - CPF opcional)
- ✅ 1 mensalidade por usuário/plano/mês (previne duplicação)
- ✅ 1 matrícula ativa por usuário/plano/tenant
- ✅ Nome e CNPJ únicos por tenant

---

## ✅ 7. Índices Tenant-First

### ❌ Problema Identificado
```
- Índices sem tenant_id como primeira coluna
- checkins sem tenant_id (deriva via JOIN)
- dias sem tenant_id (global para todos tenants)
- Índices otimizados para single-tenant

Risco: Performance ruim, isolamento de dados comprometido
```

### ✅ Solução Implementada
**Migration:** `044_otimizar_indices_tenant_first.sql`

**Mudanças Estruturais:**
```sql
-- Adicionar tenant_id em checkins
ALTER TABLE checkins ADD COLUMN tenant_id INT NOT NULL;

-- Adicionar tenant_id em dias
ALTER TABLE dias ADD COLUMN tenant_id INT NOT NULL;
```

**Índices Criados:**
```sql
-- Contas Receber
idx_tenant_status (tenant_id, status)
idx_tenant_vencimento (tenant_id, data_vencimento)
idx_tenant_referencia (tenant_id, referencia_mes)
idx_tenant_usuario_status_venc (tenant_id, usuario_id, status, data_vencimento)

-- Matrículas
idx_tenant_usuario_status (tenant_id, usuario_id, status)
idx_tenant_plano_status (tenant_id, plano_id, status)
idx_tenant_data_vencimento (tenant_id, data_vencimento)

-- Check-ins
idx_tenant_usuario_data (tenant_id, usuario_id, data_checkin_date)
idx_tenant_horario_data (tenant_id, horario_id, data_checkin_date)
idx_tenant_data (tenant_id, data_checkin_date)

-- Planos
idx_tenant_ativo (tenant_id, ativo)
idx_tenant_atual_ativo (tenant_id, atual, ativo)
idx_tenant_modalidade (tenant_id, modalidade_id)

-- Dias
idx_tenant_data_ativo (tenant_id, data, ativo)

-- Turmas
idx_tenant_status_turma (tenant_id, status)
idx_tenant_modalidade_turma (tenant_id, modalidade_id)
```

**Princípio Tenant-First:**
- ✅ Toda query começa filtrando por `tenant_id`
- ✅ Índice composto `(tenant_id, campo)` otimiza queries multi-tenant
- ✅ Melhora isolamento de dados entre academias
- ✅ Reduz risco de data leak entre tenants

**Índices Removidos (substituídos):**
- ❌ `idx_status` → ✅ `idx_tenant_status`
- ❌ `idx_checkins_usuario` → ✅ `idx_tenant_usuario_data`
- ❌ `idx_planos_disponiveis` → ✅ `idx_tenant_atual_ativo`

---

## 📊 Resumo das Migrations

| # | Migration | Descrição | Breaking Change | Status |
|---|-----------|-----------|-----------------|--------|
| 003 | `remove_tenant_id_from_usuarios.sql` | Remove tenant_id de usuarios | ⚠️ Sim | ✅ Criada |
| 036 | `remove_plano_from_usuarios.sql` | Remove plano_id de usuarios | ⚠️ Sim | ✅ Criada |
| 037 | `create_status_tables.sql` | Cria tabelas de status | ✅ Não | ✅ Criada |
| 038 | `add_status_id_columns.sql` | Adiciona FKs de status | ✅ Não | ✅ Criada |
| 039 | `remove_enum_columns.sql` | Remove ENUMs (após validação) | ⚠️ Sim | ✅ Criada |
| 040 | `fix_checkin_constraint.sql` | Corrige constraint de checkin | ✅ Não | ✅ Criada |
| 041 | `rename_contrato_id.sql` | Renomeia FK de pagamentos | ⚠️ Sim | ✅ Criada |
| 042 | `padronizar_collation.sql` | Padroniza utf8mb4_unicode_ci | ✅ Não | ✅ Criada |
| 043 | `adicionar_constraints_unicidade.sql` | Adiciona UNIQUE constraints | ⚠️ Pode falhar | ✅ Criada |
| **044b** | **`checkins_tenant_progressivo.sql`** | **Índices tenant-first SEM quebrar** | **✅ Não (trigger)** | **✅ Criada** |
| 044 | `otimizar_indices_tenant_first.sql` | Índices tenant-first (versão original) | ❌ Sim | ✅ Criada |

**Legenda:**
- 🟢 **044b**: Versão progressiva com trigger - **RECOMENDADA para produção**
- 🔴 **044**: Versão original que quebra código - Apenas para dev/novos projetos

---

## 🎯 Ordem de Execução

### ✅ Opção A: Migração Progressiva (RECOMENDADO)
```bash
# 1. Multi-tenant (se ainda não aplicada)
mysql < 003_remove_tenant_id_from_usuarios.sql

# 2. Status (sistema completo)
mysql < 037_create_status_tables.sql
mysql < 038_add_status_id_columns.sql
# 039 executar DEPOIS de validar

# 3. Plano de usuários (se ainda não aplicada)
mysql < 036_remove_plano_from_usuarios.sql

# 4. Checkin constraint
mysql < 040_fix_checkin_constraint.sql

# 5. Renomear FK
mysql < 041_rename_contrato_id.sql

# 6. Padronizar collation (IMPORTANTE: faz ALTER em todas tabelas)
mysql < 042_padronizar_collation.sql

# 7. Regras de unicidade (CUIDADO: pode falhar se houver duplicatas)
mysql < 043_adicionar_constraints_unicidade.sql

# 8. Índices tenant-first - VERSÃO PROGRESSIVA (SEM BREAKING CHANGES)
mysql < 044b_checkins_tenant_progressivo.sql
```

### ⚠️ Opção B: Migração Completa Original (REQUER ATUALIZAÇÃO DE CÓDIGO)
```bash
# ... migrations 003 a 043 iguais ...

# 8. Índices tenant-first - VERSÃO ORIGINAL (COM BREAKING CHANGES)
mysql < 044_otimizar_indices_tenant_first.sql
# ❌ CÓDIGO QUEBRA SE NÃO ATUALIZAR CheckinController e DiaController ANTES
```

⚠️ **ATENÇÃO:** 
- **Migration 044b (progressiva):** ✅ Código antigo funciona, atualização gradual
- **Migration 044 (original):** ❌ Requer atualização de código ANTES do deploy
- Migration 043: Verificar duplicatas ANTES de executar
- Migration 042: Pode demorar em tabelas grandes

📖 **Leia:** [MIGRACAO_PROGRESSIVA_CHECKINS.md](MIGRACAO_PROGRESSIVA_CHECKINS.md) para entender a estratégia

---

## ⚠️ Impactos no Código

### Backend (PHP)

#### 1. Remover tenant_id de usuarios
```php
// ❌ ANTES
$sql = "SELECT * FROM usuarios WHERE tenant_id = ?";

// ✅ DEPOIS
$sql = "
    SELECT u.*, ut.tenant_id 
    FROM usuarios u
    JOIN usuario_tenant ut ON u.id = ut.usuario_id
    WHERE ut.tenant_id = ? AND ut.status = 'ativo'
";
```

#### 2. Remover plano_id de usuarios
```php
// ❌ ANTES
$sql = "SELECT u.*, u.plano_id FROM usuarios u";

// ✅ DEPOIS
$sql = "
    SELECT u.*, m.plano_id, p.nome as plano_nome
    FROM usuarios u
    LEFT JOIN matriculas m ON u.id = m.usuario_id AND m.status = 'ativa'
    LEFT JOIN planos p ON m.plano_id = p.id
";
```

#### 3. Usar status_id ao invés de ENUM
```php
// ❌ ANTES
WHERE contas_receber.status = 'pendente'

// ✅ DEPOIS
JOIN status_conta_receber scr ON cr.status_id = scr.id
WHERE scr.codigo = 'pendente'
```

#### 4. Renomear contrato_id
```php
// ❌ ANTES
$sql = "SELECT * FROM pagamentos_contrato WHERE contrato_id = ?";

// ✅ DEPOIS
$sql = "SELECT * FROM pagamentos_contrato WHERE tenant_plano_id = ?";
```

#### 5. Adicionar tenant_id em checkins (CRÍTICO)
```php
// ❌ ANTES - Criar checkin sem tenant
$sql = "INSERT INTO checkins (usuario_id, horario_id) VALUES (?, ?)";

// ✅ DEPOIS - OBRIGATÓRIO passar tenant_id
$sql = "INSERT INTO checkins (tenant_id, usuario_id, horario_id) VALUES (?, ?, ?)";

// Obter tenant_id do usuário logado:
$tenant_id = $this->getTenantIdFromToken(); // ou do contexto da request
```

#### 6. Adicionar tenant_id em dias (CRÍTICO)
```php
// ❌ ANTES - Criar dia sem tenant
$sql = "INSERT INTO dias (data, ativo) VALUES (?, ?)";

// ✅ DEPOIS - OBRIGATÓRIO passar tenant_id
$sql = "INSERT INTO dias (tenant_id, data, ativo) VALUES (?, ?, ?)";
```

#### 7. Usar índices tenant-first em queries
```php
// ❌ ANTES - Buscar sem tenant (lento, inseguro)
$sql = "SELECT * FROM contas_receber WHERE usuario_id = ?";

// ✅ DEPOIS - Sempre filtrar por tenant primeiro
$sql = "SELECT * FROM contas_receber WHERE tenant_id = ? AND usuario_id = ?";
```
Collation** | Mistura de charsets | utf8mb4_unicode_ci ✅ |
| **Unicidade** | Sem validações | UNIQUE constraints ✅ |
| **Performance** | Índices genéricos | Índices tenant-first ✅ |
| **Isolamento** | tenant_id opcional | tenant_id obrigatório ✅ |
| **Escalabilidade** | Limitada | Pronta para crescimento ✅ |
| **Manutenibilidade** | Complexa | Simplificada ✅ |
| **Segurança** | Risco de data leak | Isolamento forte

#### 1. Usar StatusBadge
```javascript
// ❌ ANTES
<Text>{conta.status}</Text>

// ✅ DEPOIS
import StatusBadge from '../../components/StatusBadge';
<StatusBadge status={conta.status_info} />
```

#### 2. Remover referências a plano de usuário
```javascript
// ❌ ANTES - FormUsuarioScreen tinha campo plano_id
// ✅ DEPOIS - Campo removido, plano gerenciado via matrículas
```

#### 3. Validação de duplicatas no frontend
```javascript
// Adicionar validação antes de criar conta
if (contaJaExiste(tenant_id, usuario_id, plano_id, referencia_mes)) {
    Alert.alert('Erro', 'Já existe uma conta para este usuário neste mês');
    return;
}
```

---

## 📈 Benefícios Alcançados

| Área | Antes | Depois |
|------|-------|--------|
| **Multi-tenant** | Inconsistência (2 fontes) | Única fonte de verdade ✅ |
| **Check-in** | Bloqueado após 1º uso | Checkins recorrentes ✅ |
| **Financeiro** | 3 lugares com plano | 1 fonte de verdade ✅ |
| **Status** | ENUM + Tabelas (duplicado) | Tabelas padronizadas ✅ |
| **Clareza** | FK mal nomeada | Nomes descritivos ✅ |
| **Escalabilidade** | Limitada | Pronta para crescimento ✅ |
| **Manutenibilidade** | Complexa | Simplificada ✅ |

---

## 🚀 Próximos Passos

### ⚠️ CRÍTICO - Antes de Executar Migrations

1. **Verificar Duplicatas (Migration 043)**
```sql
-- Verificar emails duplicados
SELECT email_global, COUNT(*) 
FROM usuarios 
GROUP BY email_global 
HAVING COUNT(*) > 1;

-- Verificar CPFs duplicados
SELECT cpf, COUNT(*) 
FROM usuarios 
WHERE cpf IS NOT NULL
GROUP BY cpf 
HAVING COUNT(*) > 1;

-- Verificar contas duplicadas
SELECT tenant_id, usuario_id, plano_id, referencia_mes, COUNT(*) 
FROM contas_receber 
GROUP BY tenant_id, usuario_id, plano_id, referencia_mes 
HAVING COUNT(*) > 1;
```

2. **Backup Completo**
```bash
mysqldump -u root -p appcheckin > backup_antes_migrations_$(date +%Y%m%d).sql
```

### Imediato
- [ ] **Executar verificação de duplicatas**
- [ ] **Fazer backup do banco**
- [ ] **Executar migrations em ambiente de teste**
- [ ] **Validar cada migration individualmente**

### Backend (Alta Prioridade)
- [ ] **Atualizar CheckinController** - Adicionar tenant_id obrigatório
- [ ] **Atualizar DiaController** - Adicionar tenant_id obrigatório
- [ ] **Atualizar ContasReceberController** - Renomear contrato_id
- [ ] **Atualizar PagamentosController** - Usar tenant_plano_id
- [ ] **Atualizar todos Models** - Remover tenant_id de usuarios
- [ ] **Atualizar todos Models** - Remover plano_id de usuarios
- [ ] **Adicionar JOINs com status_*** - Em todos controllers
- [ ] **Criar middleware** - Validar tenant_id em toda request

### Frontend
- [ ] **Remover referências** - usuario.plano_id
- [ ] **Remover referências** - pagamentos.contrato_id
- [ ] **Implementar StatusBadge** - Em todas as telas
- [ ] **Atualizar filtros** - Usar status_info

### Testes (Críticos)
- [ ] **Testar checkins recorrentes** - Mesmo horário, dias diferentes
- [ ] **Testar multi-tenant** - Usuário em múltiplos tenants
- [ ] **Testar isolamento** - Dados não vazam entre tenants
- [ ] **Testar constraints UNIQUE** - Duplicação bloqueada
- [ ] **Testar matrículas ativas** - Trigger funciona
- [ ] **Performance** - Queries com novos índices

### Documentação
- [ ] **Atualizar diagramas ER** - Refletir tenant_id em checkins/dias
- [ ] **Atualizar API docs** - Novos campos obrigatórios
- [ ] **Criar guia de migração** - Para desenvolvedores
- [ ] **Documentar breaking changes** - tenant_id obrigatório

### Monitoramento Pós-Deploy
- [ ] **Monitorar logs de erro** - Queries quebradas
- [ ] **Verificar performance** - Índices funcionando
- [ ] **Auditoria de isolamento** - Queries sem tenant_id
- [ ] **Validar constraints** - Duplicatas bloqueadas

---

## 🆘 Rollback (Se Necessário)

### Checkin
```sql
ALTER TABLE checkins DROP INDEX unique_usuario_horario_data;
ALTER TABLE checkins DROP COLUMN data_checkin_date;
ALTER TABLE checkins ADD UNIQUE (usuario_id, horario_id);
```

### Status
```sql
-- ENUMs foram mantidos para rollback seguro
-- Basta usar as colunas antigas até validar
```

### Plano de Usuários
```sql
ALTER TABLE usuarios ADD COLUMN plano_id INT NULL;
ALTER TABLE usuarios ADD COLUMN data_vencimento_plano DATE NULL;
```

---

## 📚 Documentação Relacionada

- 📖 **Status:** `SISTEMA_STATUS_PADRONIZADO.md`
- 🚀 **Quick Start:** `QUICK_START_STATUS.md`
- 💻 **Exemplos:** `Backend/EXEMPLO_ATUALIZACAO_MODEL.php`
- 📋 **Resumo Geral:** `PADRONIZACAO_STATUS_RESUMO.md`

---

**Status:** ✅ **TODAS AS 7 MELHORIAS IMPLEMENTADAS**  
**Data:** 06/01/2026  
**Versão:** 3.0 - Arquitetura Otimizada e Segura

---

## 📚 Índice das Melhorias

1. ✅ [Multi-tenant: Fonte Única de Verdade](#-1-multi-tenant-fonte-única-de-verdade)
2. ✅ [Check-in: Constraint Corrigida](#-2-check-in-constraint-corrigida)
3. ✅ [Financeiro: Redundâncias Eliminadas](#-3-financeiro-redundâncias-eliminadas)
4. ✅ [Status: Padronização Completa](#-4-status-padronização-completa)
5. ✅ [Collation: Padronização UTF-8](#-5-collation-padronização-utf-8)
6. ✅ [Regras de Unicidade](#-6-regras-de-unicidade)
7. ✅ [Índices Tenant-First](#-7-índices-tenant-first)

---