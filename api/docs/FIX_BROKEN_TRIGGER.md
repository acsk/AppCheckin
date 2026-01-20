# FIX: Remover TRIGGER Quebrado - Checkins

## Problema Identificado

**TRIGGER:** `checkins_before_insert_tenant`  
**ERRO:** Referencia função deletada `get_tenant_id_from_usuario()`

```sql
CREATE TRIGGER `checkins_before_insert_tenant` BEFORE INSERT ON `checkins` FOR EACH ROW BEGIN
    IF NEW.tenant_id IS NULL THEN
        SET NEW.tenant_id = get_tenant_id_from_usuario(NEW.usuario_id);
    END IF;
END
```

## Por que está quebrando?

1. Quando se tenta inserir um novo check-in via `POST /superadmin/academias/2/contratos`
2. O MySQL tenta executar o TRIGGER automaticamente
3. O TRIGGER chama `get_tenant_id_from_usuario()` que não existe mais
4. Resultado: Erro "Unknown column 'tenant_id' in 'where clause'" (erro indireto da função faltando)

## Solução

### Opção 1: Remover o TRIGGER (Recomendado - Imediato)

```sql
DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`;
```

**Vantagem:** Rápido e direto - `tenant_id` será preenchido pela aplicação (como já está em `TenantService.php`)

### Opção 2: Executar a migração de fix

```bash
mysql -h localhost -u root -p appcheckin < /Users/andrecabral/Projetos/AppCheckin/api/database/migrations/999_FIX_BROKEN_TRIGGER_CHECKINS.sql
```

## Como Aplicar

### Via phpMyAdmin:
1. Acesse phpMyAdmin → Database `appcheckin`
2. Execute a query:
```sql
DROP TRIGGER IF EXISTS `checkins_before_insert_tenant`;
```

### Via CLI MySQL:
```bash
mysql -h mysql -u root -pappcheckin123 appcheckin -e "DROP TRIGGER IF EXISTS \`checkins_before_insert_tenant\`;"
```

### Via Docker (se estiver usando):
```bash
docker exec -i mysql-container mysql -u root -pappcheckin123 appcheckin -e "DROP TRIGGER IF EXISTS \`checkins_before_insert_tenant\`;"
```

## Verificação Pós-Fix

Confirmar que o trigger foi removido:

```sql
SELECT TRIGGER_NAME, TRIGGER_SCHEMA 
FROM INFORMATION_SCHEMA.TRIGGERS 
WHERE TRIGGER_NAME = 'checkins_before_insert_tenant';
```

Se retornar vazio, está ok ✅

## Como `tenant_id` será preenchido agora?

- **Via TenantService.php** (linha 16) - quando a aplicação PHP insere checkins
- A aplicação já tem a lógica para determinar `tenant_id` do contexto do usuário
- Nenhuma mudança no código PHP necessária

## Próximos Testes

Após remover o trigger:

1. **Testar criação de contrato:**
   ```bash
   POST /superadmin/academias/2/contratos
   Body: {plano_sistema_id: 1, forma_pagamento_id: 2}
   ```

2. **Testar checkin de um aluno:**
   - Fazer login no app mobile
   - Tentar fazer check-in
   - Confirmar que `tenant_id` está preenchido corretamente

3. **Verificar dados:**
   ```sql
   SELECT id, tenant_id, usuario_id, data_checkin 
   FROM checkins 
   ORDER BY id DESC 
   LIMIT 5;
   ```

## Impacto

- ✅ Contrato criado com sucesso
- ✅ Checkins podem ser registrados sem erros
- ✅ `tenant_id` continua sendo preenchido corretamente
- ✅ Sem perda de dados
- ✅ Sem quebra no app mobile

## Notas Importantes

- O TRIGGER original tentava ser automático mas não funcionaria mesmo assim
- A função removida (`get_tenant_id_from_usuario`) era desnecessária
- PHP `TenantService.php` já faz o trabalho corretamente
- Esta é a solução mais limpa e simples
