# Refatoração: Multi-Tenant Usuários

## Problema Identificado

Havia uma inconsistência no modelo de dados:
- `usuarios.tenant_id` (relação 1:1)
- `usuario_tenant` (relação N:N)

Isso gerava confusão sobre qual era a "fonte da verdade".

## Solução Implementada

✅ **Removido**: `usuarios.tenant_id`  
✅ **Mantido**: `usuario_tenant` como única fonte de verdade  
✅ **Resultado**: Um usuário pode pertencer a múltiplos tenants

## Modelo Final

### Tabela `usuarios`
```sql
- id (PK)
- nome
- email (específico do tenant/contexto atual)
- email_global (único, para login)
- ativo
- role_id
- plano_id (será movido para usuario_tenant)
- senha_hash
- foto_base64
- created_at
- updated_at
```

### Tabela `usuario_tenant` (Pivot)
```sql
- id (PK)
- usuario_id (FK -> usuarios.id)
- tenant_id (FK -> tenants.id)
- plano_id (FK -> planos.id) - plano específico neste tenant
- status (ativo, inativo, suspenso, cancelado)
- data_inicio
- data_fim
- created_at
- updated_at
```

## Fluxo de Autenticação

### 1. Login (Fase 1)
```
POST /auth/login
{
  "email": "usuario@email.com", // busca por email_global
  "senha": "***"
}

Resposta:
{
  "token_temporario": "...",
  "tenants": [
    { "id": 1, "nome": "Academia A", "slug": "academia-a" },
    { "id": 2, "nome": "Academia B", "slug": "academia-b" }
  ]
}
```

### 2. Seleção de Tenant (Fase 2)
```
POST /auth/select-tenant
{
  "tenant_id": 1
}

Resposta:
{
  "token": "JWT com usuario_id + tenant_id",
  "user": { ... },
  "tenant": { ... }
}
```

## Mudanças Necessárias no Código

### Backend - Models

#### Usuario.php
```php
// REMOVER:
- Referências a $this->tenant_id
- Queries que filtram por tenant_id direto

// ADICIONAR:
- Método getTenants()
- Método isActiveInTenant($tenantId)
- Método getPlanoInTenant($tenantId)
```

#### AuthController.php
```php
// Atualizar login():
1. Buscar por email_global
2. Retornar lista de tenants do usuário
3. Retornar token temporário

// Adicionar selectTenant():
1. Validar token temporário
2. Verificar se usuário tem acesso ao tenant
3. Gerar JWT final com tenant_id
```

#### Middlewares
```php
// AuthMiddleware:
- Extrair usuario_id + tenant_id do JWT
- Validar vínculo em usuario_tenant

// TenantMiddleware:
- Verificar status ativo em usuario_tenant
- Anexar tenant_id ao request
```

### Frontend

#### authService.js
```javascript
// Atualizar login():
async login(email, senha) {
  const response = await api.post('/auth/login', { email, senha });
  
  if (response.data.tenants.length > 1) {
    // Mostrar tela de seleção
    return { 
      needsTenantSelection: true, 
      tenants: response.data.tenants,
      tempToken: response.data.token_temporario 
    };
  } else {
    // Auto-selecionar único tenant
    return await this.selectTenant(
      response.data.tenants[0].id, 
      response.data.token_temporario
    );
  }
}

// Adicionar selectTenant():
async selectTenant(tenantId, tempToken) {
  const response = await api.post('/auth/select-tenant', 
    { tenant_id: tenantId },
    { headers: { Authorization: `Bearer ${tempToken}` } }
  );
  
  await AsyncStorage.setItem('token', response.data.token);
  await AsyncStorage.setItem('user', JSON.stringify(response.data.user));
  await AsyncStorage.setItem('tenant', JSON.stringify(response.data.tenant));
  
  return response.data;
}
```

## Queries Comuns

### Buscar tenants do usuário
```sql
SELECT t.*, ut.status, ut.plano_id
FROM tenants t
INNER JOIN usuario_tenant ut ON ut.tenant_id = t.id
WHERE ut.usuario_id = ? 
  AND ut.status = 'ativo'
ORDER BY t.nome;
```

### Buscar usuários do tenant
```sql
SELECT u.*, ut.status, ut.plano_id, ut.data_inicio
FROM usuarios u
INNER JOIN usuario_tenant ut ON ut.usuario_id = u.id
WHERE ut.tenant_id = ? 
  AND ut.status = 'ativo'
ORDER BY u.nome;
```

### Verificar acesso do usuário ao tenant
```sql
SELECT COUNT(*) as tem_acesso
FROM usuario_tenant
WHERE usuario_id = ?
  AND tenant_id = ?
  AND status = 'ativo';
```

## Migração de Dados

A migration `003_remove_tenant_id_from_usuarios.sql` já:
1. ✅ Migra dados de `usuarios.tenant_id` para `usuario_tenant`
2. ✅ Remove foreign keys
3. ✅ Remove índices antigos
4. ✅ Remove a coluna `tenant_id`
5. ✅ Garante que `email_global` está preenchido

## Vantagens da Nova Arquitetura

✅ **Flexibilidade**: Usuário pode trabalhar em múltiplas academias  
✅ **Consistência**: Única fonte de verdade para vínculo usuário-tenant  
✅ **Segurança**: Validação explícita de acesso por tenant  
✅ **Escalabilidade**: Fácil adicionar/remover usuário de tenants  
✅ **Auditoria**: Histórico completo em `usuario_tenant`

## Próximos Passos

1. ✅ Executar migration `003_remove_tenant_id_from_usuarios.sql`
2. ⏳ Atualizar Model Usuario.php
3. ⏳ Atualizar AuthController.php
4. ⏳ Atualizar Middlewares
5. ⏳ Atualizar queries em todos os controllers
6. ⏳ Atualizar frontend (authService, tela de seleção de tenant)
7. ⏳ Testar fluxo completo de autenticação
8. ⏳ Atualizar documentação da API

## Rollback (se necessário)

Se precisar reverter temporariamente:
```sql
-- Adicionar tenant_id de volta (APENAS EMERGÊNCIA)
ALTER TABLE usuarios 
ADD COLUMN tenant_id INT DEFAULT 1 AFTER id;

-- Preencher com tenant primário de cada usuário
UPDATE usuarios u
INNER JOIN (
    SELECT usuario_id, MIN(tenant_id) as tenant_id
    FROM usuario_tenant
    WHERE status = 'ativo'
    GROUP BY usuario_id
) ut ON u.id = ut.usuario_id
SET u.tenant_id = ut.tenant_id;
```

---

**Data**: 2026-01-06  
**Status**: Migration criada, aguardando execução  
**Impacto**: ALTO - Afeta autenticação e todas as queries de usuários
