# 🔧 Problema: Dados Não Aparecem (WODs vazios)

## O Problema

A API retorna:
```json
{
  "type": "success",
  "message": "WODs listados com sucesso",
  "data": [],
  "total": 0
}
```

Mas você inseriu dados no seed!

## 🎯 A Causa

Seu usuário tem um **tenant_id diferente de 1** mas o seed foi criado com `tenant_id = 1`.

A API filtra automaticamente os WODs pelo `tenant_id` do usuário logado.

---

## ✅ Solução Rápida

### Passo 1: Descubra seu tenant_id

Abra http://localhost:8082 (phpMyAdmin)

Execute esta query:

```sql
SELECT DISTINCT tenant_id FROM usuarios;
```

Anote o `tenant_id` (ex: 1, 2, 3, etc)

---

### Passo 2: Veja o JWT token

No seu navegador, abra o **DevTools** (F12)

Vá em **Application** → **Cookies** → procure por `token` ou `jwt`

Copie o valor

---

### Passo 3: Decodifique o token

Abra https://jwt.io

Cole o token na área esquerda

No painel direito, procure por `tenantId` ou `tenant_id`

Anote esse valor (ex: 2)

---

### Passo 4: Insira dados com o tenant_id correto

No phpMyAdmin, vá para **SQL** e execute:

```sql
-- Substitua "2" pelo SEUTENANT_ID encontrado acima
SET @TENANT_ID = 2;

INSERT INTO wods (tenant_id, data, titulo, descricao, status, criado_por, criado_em, atualizado_em) VALUES
(@TENANT_ID, '2026-01-15', 'WOD 15 de Janeiro', 'WOD com foco em força', 'published', 1, NOW(), NOW()),
(@TENANT_ID, '2026-01-16', 'WOD 16 de Janeiro', 'Dia de acessório', 'published', 1, NOW(), NOW());

-- Verificar
SELECT * FROM wods WHERE tenant_id = @TENANT_ID;
```

---

### Passo 5: Teste a API novamente

```bash
curl http://localhost:8080/admin/wods \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Agora deve retornar seus WODs! ✅

---

## 🚀 Solução Completa (Opção 2)

Se quer usar o seed automático:

1. No phpMyAdmin, abra **SQL**
2. Cole o conteúdo de `database/seeds/seed_wods_multitenancy.sql`
3. Mude `SET @TENANT_ID = 1;` para seu tenant_id
4. Clique **Executar**

---

## 📊 Comandos Úteis

**Ver seu tenant_id:**
```sql
SELECT DISTINCT tenant_id FROM usuarios;
```

**Ver WODs de um tenant específico:**
```sql
SELECT * FROM wods WHERE tenant_id = 2;
```

**Ver tudo do seu banco:**
```sql
SELECT 'WODs' as tipo, COUNT(*) as total FROM wods
UNION ALL
SELECT 'Blocos', COUNT(*) FROM wod_blocos
UNION ALL
SELECT 'Variações', COUNT(*) FROM wod_variacoes;
```

---

## ❌ Se ainda não funcionar

Verifique:

1. ✅ As migrations foram executadas? (Tabelas existem?)
   ```sql
   SHOW TABLES LIKE 'wod%';
   ```

2. ✅ O seu token é válido?
   ```bash
   curl http://localhost:8080/admin/wods \
     -H "Authorization: Bearer SEUTOKEN"
   ```

3. ✅ Qual é seu tenant_id realmente?
   ```sql
   SELECT tenantId FROM usuarios WHERE id = 1;
   ```

4. ✅ Os dados foram inseridos com o tenant correto?
   ```sql
   SELECT tenant_id, COUNT(*) FROM wods GROUP BY tenant_id;
   ```

---

**Depois de resolver, teste:**

```bash
# Ver WODs
curl http://localhost:8080/admin/wods \
  -H "Authorization: Bearer YOUR_TOKEN"

# Criar novo WOD
curl -X POST http://localhost:8080/admin/wods/completo \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d @exemplo_wod_completo.json
```

✅ Pronto!
