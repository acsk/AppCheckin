# Sistema Multi-Tenant - Documentação

## Visão Geral

O backend foi atualizado para suportar **múltiplos contratos de usuários em diferentes tenants/academias**. Um único usuário pode estar vinculado a várias academias simultaneamente, cada uma com seu próprio plano e status.

## Arquitetura

### Tabela `usuario_tenant`

Tabela de relacionamento N:N entre usuários e tenants, permitindo múltiplos contratos:

```sql
CREATE TABLE usuario_tenant (
    id INT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tenant_id INT NOT NULL,
    plano_id INT NULL,
    status ENUM('ativo', 'inativo', 'suspenso', 'cancelado'),
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Fluxo de Autenticação Multi-Tenant

#### 1. Login (POST /auth/login)

**Comportamento:**
- Busca usuário por `email_global` (independente de tenant)
- Retorna lista de todos os tenants vinculados ao usuário
- Se usuário tem apenas **1 tenant**: retorna token diretamente
- Se usuário tem **múltiplos tenants**: requer seleção (`requires_tenant_selection: true`)

**Request:**
```json
{
  "email": "maria@teste.com",
  "senha": "password"
}
```

**Response (Múltiplos Tenants):**
```json
{
  "message": "Login realizado com sucesso",
  "token": null,
  "user": {
    "id": 2,
    "nome": "Maria Santos",
    "email": "maria@teste.com",
    "email_global": "maria@teste.com",
    "foto_base64": null
  },
  "tenants": [
    {
      "vinculo_id": 5,
      "status": "ativo",
      "data_inicio": "2024-06-26",
      "data_fim": "2026-06-26",
      "tenant": {
        "id": 1,
        "nome": "Academia Principal",
        "slug": "principal",
        "email": "contato@academia.com",
        "telefone": null
      },
      "plano": {
        "id": 1,
        "nome": "Plano Mensal",
        "valor": "150.00",
        "periodo_dias": 30
      }
    },
    {
      "vinculo_id": 6,
      "status": "ativo",
      "data_inicio": "2024-10-26",
      "data_fim": "2025-10-26",
      "tenant": {
        "id": 2,
        "nome": "CrossFit Elite",
        "slug": "crossfit-elite",
        "email": "contato@crossfitelite.com",
        "telefone": "(11) 98765-4321"
      },
      "plano": {
        "id": 5,
        "nome": "CrossFit Anual",
        "valor": "2400.00",
        "periodo_dias": 365
      }
    }
  ],
  "requires_tenant_selection": true
}
```

#### 2. Seleção de Tenant (POST /auth/select-tenant)

Após login com múltiplos tenants, usuário seleciona qual academia deseja acessar:

**Request:**
```json
{
  "tenant_id": 2
}
```

**Headers:**
```
Authorization: Bearer {token_parcial_do_login}
```

**Response:**
```json
{
  "message": "Academia selecionada com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "tenant": {
    "vinculo_id": 6,
    "status": "ativo",
    "data_inicio": "2024-10-26",
    "data_fim": "2025-10-26",
    "tenant": {
      "id": 2,
      "nome": "CrossFit Elite",
      "slug": "crossfit-elite",
      "email": "contato@crossfitelite.com",
      "telefone": "(11) 98765-4321"
    },
    "plano": {
      "id": 5,
      "nome": "CrossFit Anual",
      "valor": "2400.00",
      "periodo_dias": 365
    }
  }
}
```

### TenantMiddleware

O middleware foi atualizado para obter `tenant_id` de 3 formas (em ordem de prioridade):

1. **Do JWT** (quando usuário já está autenticado e selecionou tenant)
2. **Do header `X-Tenant-Slug`** (para chamadas sem autenticação ou multi-tenant)
3. **Padrão (tenant_id = 1)** (fallback)

## Dados de Teste

### Usuários Criados

Execute a migration e o seed:

```bash
cd Backend
# Aplicar migration
mysql -u root -p appcheckin < database/migrations/014_multi_tenant_usuarios.sql

# Carregar dados de teste
mysql -u root -p appcheckin < database/seeds/seed_multi_tenant_teste.sql
```

### Credenciais de Teste

| Usuário | Email | Senha | Academias | Cenário |
|---------|-------|-------|-----------|---------|
| João Silva | joao@teste.com | password | Academia Principal, CrossFit Elite | 2 contratos ativos |
| Maria Santos | maria@teste.com | password | Todas (4 academias) | Super usuária - múltiplos contratos |
| Pedro Costa | pedro@teste.com | password | Pilates Studio Premium | 1 contrato ativo, 1 cancelado |

### Academias/Tenants

1. **Academia Principal** (slug: `principal`) - Tenant padrão
2. **CrossFit Elite** (slug: `crossfit-elite`)
3. **Pilates Studio Premium** (slug: `pilates-premium`)
4. **Yoga & Bem Estar** (slug: `yoga-bem-estar`)

## Testes

### Teste 1: Login com Tenant Único (João)

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao@teste.com",
    "senha": "password"
  }'
```

Resultado esperado: `token` preenchido, pois João tem apenas 2 academias.

### Teste 2: Login com Múltiplos Tenants (Maria)

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "maria@teste.com",
    "senha": "password"
  }'
```

Resultado esperado: `token: null`, `requires_tenant_selection: true`, lista com 4 academias.

### Teste 3: Selecionar Tenant

```bash
# Primeiro faça login e copie o token
TOKEN="seu_token_aqui"

curl -X POST http://localhost:8080/auth/select-tenant \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "tenant_id": 2
  }'
```

### Teste 4: Acessar Recursos com Tenant no JWT

```bash
# Após selecionar tenant, use o novo token
NEW_TOKEN="token_com_tenant"

curl -X GET http://localhost:8080/me \
  -H "Authorization: Bearer $NEW_TOKEN"
```

O `tenant_id` será automaticamente extraído do JWT pelo TenantMiddleware.

## Mudanças no Frontend Mobile

O app mobile precisará:

1. **Tela de seleção de academia** após login (se `requires_tenant_selection === true`)
2. **Botão para trocar de academia** no menu/perfil
3. **Armazenar tenant selecionado** no contexto/estado global
4. **Mostrar nome da academia atual** no header

## Migrações Aplicadas

- **014_multi_tenant_usuarios.sql**: Cria tabela `usuario_tenant` e campo `email_global`

## Seeds Disponíveis

- **seed_multi_tenant_teste.sql**: Dados completos de teste com 3 usuários e 4 academias

## Próximos Passos

1. ✅ Backend atualizado
2. ⏳ Implementar tela de seleção no mobile
3. ⏳ Adicionar alternador de academia no menu
4. ⏳ Testar fluxo completo end-to-end
