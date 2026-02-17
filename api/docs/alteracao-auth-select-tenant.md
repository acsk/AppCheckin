# Mudança: Seleção de Tenant (Auth)

## Resumo
Houve uma alteração no fluxo de seleção de tenant:
- `POST /auth/select-tenant` agora é **protegido** por token.
- Para seleção **inicial** (antes do token), use os endpoints **públicos**:
  - `POST /auth/select-tenant-public`
  - `POST /auth/select-tenant-initial` (alias de compatibilidade)

Isso explica o erro:
```
401 MISSING_TOKEN
Token não fornecido
```
ao chamar `/auth/select-tenant` sem `Authorization`.

---

## Quando usar cada endpoint

### 1) Seleção inicial (público)
Use quando o login retorna múltiplos tenants **sem token**.

`POST /auth/select-tenant-public`  
ou  
`POST /auth/select-tenant-initial`

Payload:
```json
{
  "user_id": 123,
  "email": "usuario@teste.com",
  "tenant_id": 3
}
```

Resposta esperada:
- Retorna `token`, `user`, `tenant` e `tenants`.
- O client salva o token e o tenant atual.

### 2) Troca de tenant após login (protegido)
Use quando o usuário **já está autenticado**.

`POST /auth/select-tenant`

Headers:
```
Authorization: Bearer <token>
```

Payload:
```json
{
  "tenant_id": 3
}
```

---

## Impacto no painel (front web)
Se o painel estava usando `POST /auth/select-tenant` **sem token**, deve trocar para:
- `POST /auth/select-tenant-public` (fluxo inicial)

Ou, se já tiver token:
- enviar `Authorization: Bearer <token>` e manter `POST /auth/select-tenant`.

---

## Rotas atuais (API)
```php
// Protegido
$app->post('/auth/select-tenant', [AuthController::class, 'selectTenant'])->add(AuthMiddleware::class);
$app->get('/auth/tenants', [AuthController::class, 'listTenants'])->add(AuthMiddleware::class);

// Público (seleção inicial)
$app->post('/auth/select-tenant-initial', [AuthController::class, 'selectTenantPublic']);
$app->post('/auth/select-tenant-public', [AuthController::class, 'selectTenantPublic']);
```

---

## Referência mobile (funcionando)
No mobile, o fluxo já usa `selectTenantPublic` quando não há token e `selectTenant` quando já há token. Isso está correto.
