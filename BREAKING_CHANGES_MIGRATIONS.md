# 🚨 BREAKING CHANGES - Migrations 042-044

## ✅ SOLUÇÃO: Migration Progressiva Disponível

**IMPORTANTE:** Foi criada a **Migration 044b** que NÃO quebra o código existente!

- 🟢 **044b_checkins_tenant_progressivo.sql** - Usa trigger para compatibilidade retroativa
- 🔴 **044_otimizar_indices_tenant_first.sql** - Versão original com breaking changes

**RECOMENDAÇÃO:** Use a 044b para produção. Veja [MIGRACAO_PROGRESSIVA_CHECKINS.md](MIGRACAO_PROGRESSIVA_CHECKINS.md)

---

## ⚠️ ATENÇÃO: Mudanças que Quebram Compatibilidade (Migration 044 Original)

As migrations **042**, **043** e **044** introduzem mudanças estruturais significativas.

**A Migration 044 (original) QUEBRA** o código existente, mas a **044b (progressiva) NÃO quebra**.

---

## 🔴 Migration 044: tenant_id Obrigatório em checkins e dias

### Breaking Change #1: checkins.tenant_id

**O QUE MUDOU:**
```sql
-- ANTES
CREATE TABLE checkins (
    id INT,
    usuario_id INT,
    horario_id INT,
    -- SEM tenant_id
);

-- DEPOIS
CREATE TABLE checkins (
    id INT,
    tenant_id INT NOT NULL, -- ❗ NOVO CAMPO OBRIGATÓRIO
    usuario_id INT,
    horario_id INT,
);
```

**IMPACTO NO CÓDIGO:**

#### Backend - CheckinController.php
```php
// ❌ CÓDIGO ANTIGO (VAI QUEBRAR)
public function criarCheckin($request, $response, $args) {
    $data = $request->getParsedBody();
    
    $sql = "INSERT INTO checkins (usuario_id, horario_id, data_checkin) 
            VALUES (?, ?, NOW())";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$data['usuario_id'], $data['horario_id']]);
}

// ✅ CÓDIGO NOVO (OBRIGATÓRIO)
public function criarCheckin($request, $response, $args) {
    $data = $request->getParsedBody();
    
    // Obter tenant_id do token JWT ou contexto
    $tenant_id = $this->getTenantIdFromToken($request);
    
    $sql = "INSERT INTO checkins (tenant_id, usuario_id, horario_id, data_checkin) 
            VALUES (?, ?, ?, NOW())";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$tenant_id, $data['usuario_id'], $data['horario_id']]);
}
```

#### Backend - Listar Checkins
```php
// ❌ CÓDIGO ANTIGO (INSEGURO)
$sql = "SELECT * FROM checkins WHERE usuario_id = ?";

// ✅ CÓDIGO NOVO (COM ISOLAMENTO)
$sql = "SELECT * FROM checkins WHERE tenant_id = ? AND usuario_id = ?";
```

### Breaking Change #2: dias.tenant_id

**O QUE MUDOU:**
```sql
-- ANTES
CREATE TABLE dias (
    id INT,
    data DATE,
    ativo BOOLEAN
    -- SEM tenant_id (dias globais)
);

-- DEPOIS
CREATE TABLE dias (
    id INT,
    tenant_id INT NOT NULL, -- ❗ NOVO CAMPO OBRIGATÓRIO
    data DATE,
    ativo BOOLEAN
);
```

**IMPACTO NO CÓDIGO:**

#### Backend - DiaController.php
```php
// ❌ CÓDIGO ANTIGO (VAI QUEBRAR)
public function criarDia($request, $response, $args) {
    $data = $request->getParsedBody();
    
    $sql = "INSERT INTO dias (data, ativo) VALUES (?, ?)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$data['data'], $data['ativo']]);
}

// ✅ CÓDIGO NOVO (OBRIGATÓRIO)
public function criarDia($request, $response, $args) {
    $data = $request->getParsedBody();
    $tenant_id = $this->getTenantIdFromToken($request);
    
    $sql = "INSERT INTO dias (tenant_id, data, ativo) VALUES (?, ?, ?)";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$tenant_id, $data['data'], $data['ativo']]);
}
```

---

## 🟡 Migration 043: Constraints UNIQUE

### Breaking Change #3: Validações Estritas

**PODE FALHAR SE HOUVER DADOS DUPLICADOS:**

1. **Email Global Duplicado**
```sql
-- Esta constraint vai FALHAR se existirem emails duplicados
ALTER TABLE usuarios ADD CONSTRAINT unique_email_global UNIQUE (email_global);

-- VERIFICAR ANTES:
SELECT email_global, COUNT(*) 
FROM usuarios 
GROUP BY email_global 
HAVING COUNT(*) > 1;
```

2. **CPF Duplicado**
```sql
ALTER TABLE usuarios ADD CONSTRAINT unique_cpf UNIQUE (cpf);

-- VERIFICAR ANTES:
SELECT cpf, COUNT(*) 
FROM usuarios 
WHERE cpf IS NOT NULL
GROUP BY cpf 
HAVING COUNT(*) > 1;
```

3. **Mensalidades Duplicadas**
```sql
ALTER TABLE contas_receber 
ADD CONSTRAINT unique_conta_mensal 
UNIQUE (tenant_id, usuario_id, plano_id, referencia_mes);

-- VERIFICAR ANTES:
SELECT tenant_id, usuario_id, plano_id, referencia_mes, COUNT(*) 
FROM contas_receber 
GROUP BY tenant_id, usuario_id, plano_id, referencia_mes 
HAVING COUNT(*) > 1;
```

**AÇÃO NECESSÁRIA:**
- Limpar dados duplicados ANTES de executar a migration
- Decidir qual registro manter (mais recente? maior ID?)
- Mesclar ou deletar registros conflitantes

---

## 🟡 Migration 041: Renomear contrato_id

### Breaking Change #4: Campo Renomeado

**O QUE MUDOU:**
```sql
-- ANTES
ALTER TABLE pagamentos_contrato (
    contrato_id INT -- ❌ NOME ANTIGO
);

-- DEPOIS
ALTER TABLE pagamentos_contrato (
    tenant_plano_id INT -- ✅ NOME NOVO
);
```

**IMPACTO NO CÓDIGO:**

#### Backend - PagamentosController
```php
// ❌ CÓDIGO ANTIGO
$sql = "SELECT * FROM pagamentos_contrato WHERE contrato_id = ?";
$data = ['contrato_id' => $id];

// ✅ CÓDIGO NOVO
$sql = "SELECT * FROM pagamentos_contrato WHERE tenant_plano_id = ?";
$data = ['tenant_plano_id' => $id];
```

#### Frontend
```javascript
// ❌ CÓDIGO ANTIGO
const pagamento = {
    contrato_id: form.contratoId,
    valor: form.valor
};

// ✅ CÓDIGO NOVO
const pagamento = {
    tenant_plano_id: form.contratoId,
    valor: form.valor
};
```

---

## 📋 Checklist de Atualização

### 1️⃣ Antes de Executar Migrations

```bash
# Backup completo
mysqldump -u root -p appcheckin > backup_$(date +%Y%m%d_%H%M%S).sql

# Verificar duplicatas (queries acima)
mysql -u root -p appcheckin < verificar_duplicatas.sql

# Limpar duplicatas se necessário
```

### 2️⃣ Atualizar Backend (Obrigatório)

**Arquivos que PRECISAM ser alterados:**

- [ ] `CheckinController.php` - Adicionar tenant_id em INSERT
- [ ] `CheckinController.php` - Adicionar tenant_id em SELECT
- [ ] `DiaController.php` - Adicionar tenant_id em INSERT
- [ ] `DiaController.php` - Adicionar tenant_id em SELECT
- [ ] `HorarioController.php` - JOIN com dias.tenant_id
- [ ] `PagamentosController.php` - Renomear contrato_id → tenant_plano_id
- [ ] `Checkin.php` (Model) - Incluir tenant_id
- [ ] `Dia.php` (Model) - Incluir tenant_id
- [ ] `TenantMiddleware.php` - Validar tenant_id existe

**Criar Helper:**
```php
// app/Helpers/TenantHelper.php
class TenantHelper {
    public static function getTenantIdFromToken($request) {
        $token = $request->getHeaderLine('Authorization');
        $jwt = str_replace('Bearer ', '', $token);
        $decoded = JWTService::decode($jwt);
        return $decoded->tenant_id ?? null;
    }
    
    public static function validateTenantAccess($tenant_id, $resource_id, $resource_type) {
        // Validar se o resource pertence ao tenant
    }
}
```

### 3️⃣ Atualizar Frontend

- [ ] Remover referências a `contrato_id`
- [ ] Usar `tenant_plano_id` em formulários de pagamento
- [ ] Testar criação de checkin (deve continuar funcionando)
- [ ] Testar criação de dia (deve continuar funcionando)

### 4️⃣ Testes Críticos

```bash
# 1. Criar checkin
curl -X POST http://localhost/api/checkins \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"usuario_id": 1, "horario_id": 5}'

# 2. Listar checkins (deve filtrar por tenant automaticamente)
curl http://localhost/api/checkins?data=2026-01-06 \
  -H "Authorization: Bearer $TOKEN"

# 3. Criar dia
curl -X POST http://localhost/api/dias \
  -H "Authorization: Bearer $TOKEN" \
  -d '{"data": "2026-01-15", "ativo": true}'

# 4. Testar isolamento (não deve ver dados de outro tenant)
curl http://localhost/api/checkins \
  -H "Authorization: Bearer $TOKEN_OUTRO_TENANT"
```

---

## 🆘 Rollback Emergency

Se algo der errado após executar as migrations:

```bash
# 1. Restaurar backup
mysql -u root -p appcheckin < backup_YYYYMMDD_HHMMSS.sql

# 2. Reverter código
git revert <commit_hash>

# 3. Restart services
docker-compose restart
```

**Ou reverter migrations individualmente:**

```sql
-- Reverter 044 (tenant_id)
ALTER TABLE checkins DROP FOREIGN KEY fk_checkins_tenant;
ALTER TABLE checkins DROP COLUMN tenant_id;
ALTER TABLE dias DROP FOREIGN KEY fk_dias_tenant;
ALTER TABLE dias DROP COLUMN tenant_id;

-- Reverter 043 (UNIQUE constraints)
ALTER TABLE usuarios DROP CONSTRAINT unique_email_global;
ALTER TABLE usuarios DROP CONSTRAINT unique_cpf;
ALTER TABLE contas_receber DROP CONSTRAINT unique_conta_mensal;

-- Reverter 041 (renomear FK)
ALTER TABLE pagamentos_contrato CHANGE COLUMN tenant_plano_id contrato_id INT NOT NULL;
```

---

## 📊 Impacto Estimado

| Área | Arquivos Afetados | Complexidade | Tempo Estimado |
|------|-------------------|--------------|----------------|
| Backend Controllers | 5-7 arquivos | Alta | 2-3 horas |
| Backend Models | 3-4 arquivos | Média | 1-2 horas |
| Frontend Services | 2-3 arquivos | Baixa | 30-60 min |
| Frontend Telas | 4-6 arquivos | Baixa | 1 hora |
| Testes | Todos | Alta | 2-4 horas |
| **TOTAL** | **15-20 arquivos** | **Alta** | **6-10 horas** |

---

## 🎯 Ordem Recomendada de Implementação

1. **Dia 1: Preparação**
   - Backup completo ✅
   - Verificar duplicatas ✅
   - Executar migrations 042, 043 ✅
   - Testar isoladamente ✅

2. **Dia 2: Backend Core**
   - Criar TenantHelper ✅
   - Atualizar CheckinController ✅
   - Atualizar DiaController ✅
   - Executar migration 044 ✅

3. **Dia 3: Backend Completo**
   - Atualizar PagamentosController ✅
   - Atualizar Models ✅
   - Testes unitários ✅

4. **Dia 4: Frontend + Testes**
   - Atualizar frontend ✅
   - Testes integrados ✅
   - Validação completa ✅

---

## ✅ Validação Final

Checklist antes de ir para produção:

- [ ] Todas as migrations executadas com sucesso
- [ ] Nenhum erro em logs do backend
- [ ] Testes E2E passando
- [ ] Checkins funcionando com tenant_id
- [ ] Dias funcionando com tenant_id
- [ ] Isolamento de dados validado (tenant A não vê dados de tenant B)
- [ ] Performance aceitável (queries usando novos índices)
- [ ] Rollback testado e funcional
- [ ] Documentação atualizada
- [ ] Equipe treinada nas mudanças

---

**Versão:** 1.0  
**Data:** 06/01/2026  
**Status:** 🔴 CRÍTICO - LEITURA OBRIGATÓRIA
