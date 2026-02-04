# 🚀 Guia de Migração - Produção

## 📋 Pré-requisitos

### 1. Backup Completo
```bash
# Fazer backup do banco ANTES de executar
mysqldump -h 127.0.0.1 -P 3306 -u u304177849_api -p u304177849_api > backup_antes_migracao_$(date +%Y%m%d_%H%M%S).sql
```

### 2. Verificar Conexões Ativas
```sql
-- Ver quem está conectado
SHOW PROCESSLIST;

-- Se necessário, matar conexões
-- KILL <process_id>;
```

### 3. Modo Manutenção
```bash
# Colocar aplicação em manutenção (se possível)
# Ou fazer em horário de baixo tráfego
```

---

## 🔧 Execução da Migração

### Opção 1: Via phpMyAdmin
1. Acesse o phpMyAdmin
2. Selecione o banco `u304177849_api`
3. Vá na aba **SQL**
4. Cole o conteúdo de `20260204_producao_migrar_usuario_tenant.sql`
5. Clique em **Executar**
6. Aguarde a conclusão (deve levar poucos segundos)

### Opção 2: Via Terminal/SSH
```bash
mysql -h 127.0.0.1 -P 3306 -u u304177849_api -p u304177849_api < database/migrations/20260204_producao_migrar_usuario_tenant.sql
```

---

## ✅ Verificações Pós-Migração

### 1. Verificar Contagem de Registros
```sql
-- Devem ser iguais (ou maior na nova tabela se houver múltiplos papéis)
SELECT COUNT(*) FROM usuario_tenant_backup;
SELECT COUNT(DISTINCT usuario_id) FROM tenant_usuario_papel;
```

### 2. Testar Função MySQL
```sql
-- Deve retornar o tenant_id correto
SELECT get_tenant_id_from_usuario(2);  -- Admin da Aqua Masters
SELECT get_tenant_id_from_usuario(3);  -- Aluno teste
```

### 3. Verificar Dados Específicos
```sql
-- Conferir alguns usuários específicos
SELECT 
    u.id,
    u.nome,
    u.email,
    tup.tenant_id,
    tup.papel_id,
    p.nome AS papel_nome,
    tup.ativo
FROM usuarios u
INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
INNER JOIN papeis p ON p.id = tup.papel_id
LIMIT 10;
```

### 4. Testar Endpoint da API
```bash
# Login
curl -X POST 'https://seu-dominio.com/auth/login' \
  -H 'Content-Type: application/json' \
  -d '{"email":"andrecabrall@gmail.com","senha":"123456"}'

# Perfil (com token obtido)
curl -X GET 'https://seu-dominio.com/mobile/perfil' \
  -H 'Authorization: Bearer SEU_TOKEN_AQUI'
```

---

## 🎯 O que a Migração Faz

### Dados Migrados
```
usuario_tenant → tenant_usuario_papel

Mapeamento:
- tenant_id       → tenant_id (sem alteração)
- usuario_id      → usuario_id (sem alteração)
- status          → ativo (VARCHAR → TINYINT)
  - 'ativo'       → 1
  - 'inativo'     → 0
- plano_id        → (removido - não existe na nova tabela)
- data_inicio     → (removido)
- data_fim        → (removido)
+ papel_id        → (novo - 1=aluno, 2=professor, 3=admin)
```

### Função Atualizada
```sql
get_tenant_id_from_usuario(usuario_id)
- ANTES: SELECT FROM usuario_tenant WHERE status='ativo'
- DEPOIS: SELECT FROM tenant_usuario_papel WHERE ativo=1
```

---

## 📊 Estatísticas Esperadas

Com base no dump fornecido:

| Tabela | Registros Originais | Após Migração |
|--------|---------------------|---------------|
| usuario_tenant | 4 | 4 (backup) |
| tenant_usuario_papel | 10 | 14+ |

**Nota:** Pode haver mais registros na nova tabela porque um usuário pode ter múltiplos papéis no mesmo tenant.

---

## ⚠️ Problemas Comuns

### Erro: "Duplicate entry"
```sql
-- Se aparecer erro de chave duplicada, limpe dados antigos:
DELETE FROM tenant_usuario_papel 
WHERE created_at < '2026-02-04'
AND (usuario_id, tenant_id, papel_id) IN (
    SELECT usuario_id, tenant_id, 1 FROM usuario_tenant
);
```

### Usuários sem Tenant
```sql
-- Verificar usuários órfãos
SELECT u.id, u.nome, u.email
FROM usuarios u
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = u.id
);

-- Associar ao tenant padrão se necessário
INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo)
SELECT 2, u.id, 1, 1
FROM usuarios u
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_usuario_papel tup
    WHERE tup.usuario_id = u.id
);
```

---

## 🔙 Rollback (Emergência)

Se algo der errado, execute:
```bash
mysql -h 127.0.0.1 -P 3306 -u u304177849_api -p u304177849_api < database/migrations/20260204_producao_rollback.sql
```

**⚠️ ATENÇÃO:** O rollback restaura a tabela antiga, mas **NÃO remove** os dados migrados para `tenant_usuario_papel`.

---

## 🗑️ Limpeza Final (Após Validar)

Após **pelo menos 7 dias** de validação em produção:

```sql
-- Excluir tabela de backup
DROP TABLE usuario_tenant_backup;

-- Verificar espaço liberado
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'u304177849_api'
AND table_name LIKE 'usuario_tenant%';
```

---

## 📞 Suporte

Se encontrar problemas:
1. **NÃO ENTRE EM PÂNICO** 🧘
2. Execute o rollback
3. Anote o erro exato
4. Restaure o backup se necessário
5. Contate o desenvolvedor

---

## ✅ Checklist de Execução

- [ ] Backup do banco criado
- [ ] Aplicação em manutenção (opcional)
- [ ] Script de migração executado
- [ ] Verificações pós-migração OK
- [ ] Testes de API funcionando
- [ ] Monitoramento por 24-48h
- [ ] Limpeza do backup (após 7 dias)

---

**Data da Migração:** ___/___/______  
**Executado por:** ________________  
**Tempo de execução:** ______ segundos  
**Status:** [ ] Sucesso [ ] Falha [ ] Rollback
