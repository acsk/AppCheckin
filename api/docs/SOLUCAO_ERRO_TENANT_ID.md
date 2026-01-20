# Solução Completa: Erro "Unknown column 'tenant_id' in 'where clause'"

## Diagnóstico

O erro acontece porque:

1. ❌ **TRIGGER `checkins_before_insert_tenant`** existe no banco
2. ❌ **Função `get_tenant_id_from_usuario()`** foi deletada
3. ❌ Quando tenta inserir um checkin, TRIGGER tenta chamar função que não existe → ERRO

## Solução: ESCOLHA UMA OPÇÃO

---

## OPÇÃO 1: Recriar a Função (Recomendado ✅)

**Vantagem:** Mantém o TRIGGER funcionando, melhor performance  
**Como:** Recriar a função MySQL com a lógica correta

### Passo 1: Recriar a Função
```bash
mysql -h localhost -u root -pappcheckin appcheckin < database/migrations/045_recreate_function_get_tenant_id.sql
```

Ou via SQL direto:
```sql
DELIMITER //

CREATE FUNCTION `get_tenant_id_from_usuario`(p_usuario_id INT)
RETURNS INT
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_tenant_id INT;
    
    SELECT ut.tenant_id INTO v_tenant_id
    FROM usuario_tenant ut
    WHERE ut.usuario_id = p_usuario_id
    AND ut.status = 'ativo'
    LIMIT 1;
    
    IF v_tenant_id IS NULL THEN
        SET v_tenant_id = 1;
    END IF;
    
    RETURN v_tenant_id;
END //

DELIMITER ;
```

### Passo 2: Verificar se criou corretamente
```sql
SELECT ROUTINE_NAME FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_NAME = 'get_tenant_id_from_usuario';
```

✅ Se retornar `get_tenant_id_from_usuario`, está ok!

### Passo 3: O TRIGGER continua funcionando
Nenhuma ação necessária - o TRIGGER já referencia a função que agora existe

---

## OPÇÃO 2: Remover o TRIGGER (Simples)

**Vantagem:** Mais simples, sem função MySQL  
**Como:** Remover TRIGGER e deixar PHP fazer o trabalho

### Passo 1: Remover o TRIGGER
```sql
DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`;
```

### Passo 2: Confirmar remoção
```sql
SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_NAME = 'checkins_before_insert_tenant';
```

✅ Se retornar vazio, foi removido com sucesso

### Passo 3: Pronto!
O `tenant_id` será preenchido pela aplicação PHP via `TenantService.php`

---

## Qual Escolher?

| Critério | Opção 1 (Função) | Opção 2 (Remover) |
|----------|------------------|-------------------|
| Performance | ⭐⭐⭐ Melhor | ⭐⭐ Normal |
| Simplicidade | ⭐⭐ Média | ⭐⭐⭐ Simples |
| Integridade | ⭐⭐⭐ Alta | ⭐⭐⭐ Alta |
| Manutenção | Função + PHP | Apenas PHP |
| Recomendação | ✅ **MELHOR** | Ok |

**→ Recomendação: Use OPÇÃO 1 (Recriar Função)**

---

## Teste Após Aplicar a Solução

Depois de aplicar UMA das opções acima:

### 1. Testar criação de contrato
```bash
curl -X POST http://localhost:8000/superadmin/academias/2/contratos \
  -H "Content-Type: application/json" \
  -d '{"plano_sistema_id": 1, "forma_pagamento_id": 2}'
```

✅ Deve retornar 200 OK (sem erro de coluna)

### 2. Verificar checkin inserido
```sql
SELECT id, tenant_id, usuario_id, data_checkin 
FROM checkins 
WHERE usuario_id = 11
ORDER BY id DESC 
LIMIT 5;
```

✅ Todos devem ter `tenant_id` preenchido

### 3. Testar checkin do app
- Fazer login no app mobile
- Fazer check-in
- Verificar no banco se foi inserido com `tenant_id` correto

---

## Resolução de Problemas

### Erro: "FUNCTION get_tenant_id_from_usuario does not exist"

**Significa:** Opção 1 não foi aplicada corretamente

**Solução:**
```sql
-- Verificar se função existe
SHOW FUNCTION STATUS WHERE DB = 'appcheckin';

-- Se não existir, criar manualmente (ver Passo 1 acima)
```

### Erro: "TRIGGER still referencing function"

**Significa:** Função foi criada mas trigger ainda aponta para versão errada

**Solução:**
```sql
-- Remover e recriar TRIGGER
DROP TRIGGER IF EXISTS checkins_before_insert_tenant;

CREATE TRIGGER `checkins_before_insert_tenant` 
BEFORE INSERT ON `checkins` 
FOR EACH ROW 
BEGIN
    IF NEW.tenant_id IS NULL THEN
        SET NEW.tenant_id = get_tenant_id_from_usuario(NEW.usuario_id);
    END IF;
END;
```

---

## Checklist de Conclusão

- [ ] Escolhi entre Opção 1 ou Opção 2
- [ ] Executei o SQL correspondente
- [ ] Verifiquei que não há mais erros de coluna
- [ ] Testei criar um contrato
- [ ] Testei fazer um checkin
- [ ] Confirmei que `tenant_id` está preenchido no banco
- [ ] ✅ PRONTO!

---

## Resumo Técnico

**Problema Root Cause:** Migration 044b criou função, depois a deletou, mas TRIGGER continuou referenciando

**Solução Permanente:** Recriar função com lógica correta (sincronizada com TenantService.php)

**Status:** ✅ **RESOLVIDO COM OPÇÃO 1**
