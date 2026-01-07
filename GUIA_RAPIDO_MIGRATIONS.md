# 🎯 Guia Rápido - Executar Migrations com Segurança

## ✅ Solução: Migration Progressiva Implementada

A **Migration 044b** foi criada para adicionar `tenant_id` em `checkins` **SEM quebrar código existente**.

---

## 🚀 Execução Rápida (Recomendado)

### Opção 1: Script Automático
```bash
cd Backend/database/migrations
./executar_migrations.sh
```

O script irá:
1. ✅ Criar backup automático
2. ✅ Verificar duplicatas
3. ✅ Executar migrations selecionadas
4. ✅ Validar resultado

---

### Opção 2: Manual (Passo a Passo)

#### 1️⃣ Backup
```bash
mysqldump -u root -p appcheckin > backup_$(date +%Y%m%d).sql
```

#### 2️⃣ Verificar Duplicatas
```bash
mysql -u root -p appcheckin < verificar_duplicatas.sql
```

Se encontrar duplicatas, **NÃO execute Migration 043**.

#### 3️⃣ Executar Migrations
```bash
# Collation
mysql -u root -p appcheckin < 042_padronizar_collation.sql

# UNIQUE constraints (só se NÃO houver duplicatas)
mysql -u root -p appcheckin < 043_adicionar_constraints_unicidade.sql

# Índices tenant-first PROGRESSIVO (recomendado)
mysql -u root -p appcheckin < 044b_checkins_tenant_progressivo.sql
```

---

## 📋 Checklist Pós-Migration

### Verificar tenant_id em checkins
```sql
-- Todos devem ter tenant_id preenchido
SELECT COUNT(*) FROM checkins WHERE tenant_id IS NULL;
-- Esperado: 0

-- Verificar se trigger foi criado
SHOW TRIGGERS LIKE 'checkins';
-- Esperado: checkins_before_insert_tenant
```

### Testar Checkin (Código Antigo)
```bash
curl -X POST http://localhost/api/checkins \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"usuario_id": 1, "horario_id": 5}'
```

**Resultado esperado:** Checkin criado com tenant_id preenchido automaticamente ✅

---

## 🔧 Troubleshooting

### Erro: "Duplicate entry for email_global"
```sql
-- Encontrar duplicatas
SELECT email_global, COUNT(*) 
FROM usuarios 
GROUP BY email_global 
HAVING COUNT(*) > 1;

-- Decidir qual manter e deletar os outros
-- DELETE FROM usuarios WHERE id IN (2, 3); -- IDs duplicados
```

### Erro: "Column tenant_id already exists"
```sql
-- Pular migration 044b ou remover coluna primeiro
ALTER TABLE checkins DROP COLUMN tenant_id;
```

### Erro: "Cannot add foreign key constraint"
```sql
-- Verificar se todos tenant_id são válidos
SELECT DISTINCT c.tenant_id 
FROM checkins c 
LEFT JOIN tenants t ON c.tenant_id = t.id 
WHERE t.id IS NULL;

-- Corrigir tenant_id inválidos
UPDATE checkins SET tenant_id = 1 WHERE tenant_id NOT IN (SELECT id FROM tenants);
```

---

## 📊 Comparação: 044 vs 044b

| Característica | 044 (Original) | 044b (Progressiva) |
|----------------|----------------|-------------------|
| **Breaking Change** | ❌ Sim | ✅ Não |
| **Código antigo funciona** | ❌ Não | ✅ Sim (trigger) |
| **Atualização urgente** | ❌ Obrigatória | ✅ Opcional |
| **Deploy imediato** | ❌ Arriscado | ✅ Seguro |
| **Performance** | ✅ Ótima | ⚠️ Boa (trigger ~0.1ms) |
| **Recomendado para** | Dev/Novos projetos | Produção |

---

## 🎯 Decisão Final

### Use 044b se:
- ✅ Aplicação em **produção** com usuários
- ✅ Quer deploy **sem risco**
- ✅ Precisa **tempo** para atualizar código
- ✅ Prioriza **estabilidade**

### Use 044 se:
- ⚠️ Aplicação em **desenvolvimento**
- ⚠️ Pode **atualizar código** antes do deploy
- ⚠️ Não há **usuários ativos**
- ⚠️ Prioriza **performance máxima**

---

## 📂 Arquivos Criados

- ✅ `044b_checkins_tenant_progressivo.sql` - Migration progressiva
- ✅ `verificar_duplicatas.sql` - Script de verificação
- ✅ `executar_migrations.sh` - Script automatizado
- ✅ `MIGRACAO_PROGRESSIVA_CHECKINS.md` - Documentação completa
- ✅ `GUIA_RAPIDO_MIGRATIONS.md` - Este arquivo

---

## 🆘 Rollback

Se precisar reverter:

```bash
# Restaurar backup
mysql -u root -p appcheckin < backup_YYYYMMDD.sql

# Ou reverter apenas 044b
mysql -u root -p appcheckin << EOF
DROP TRIGGER IF EXISTS checkins_before_insert_tenant;
DROP FUNCTION IF EXISTS get_tenant_id_from_usuario;
ALTER TABLE checkins DROP FOREIGN KEY fk_checkins_tenant;
ALTER TABLE checkins DROP COLUMN tenant_id;
EOF
```

---

**Recomendação Final:** Use **044b** para deploy seguro em produção 🚀
