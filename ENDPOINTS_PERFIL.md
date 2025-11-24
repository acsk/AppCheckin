# Endpoints de Perfil de Usuário

## Visão Geral
Documentação dos endpoints para gerenciamento de perfil de usuário, incluindo atualização de dados pessoais e foto em base64.

## Base URL
```
http://localhost:8080
```

## Autenticação
Todos os endpoints requerem autenticação via JWT Bearer token no header:
```
Authorization: Bearer {seu_token_jwt}
```

---

## Endpoints

### 1. Obter Dados do Usuário Logado
Retorna os dados completos do usuário autenticado.

**Endpoint:** `GET /me`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional, padrão: tenant_id=1)
```

**Resposta de Sucesso (200):**
```json
{
  "id": 1,
  "tenant_id": 1,
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "created_at": "2025-11-20 10:30:00",
  "updated_at": "2025-11-23 14:20:00"
}
```

**Resposta de Erro (404):**
```json
{
  "error": "Usuário não encontrado"
}
```

---

### 2. Atualizar Perfil do Usuário
Atualiza os dados do perfil do usuário autenticado (nome, email, senha e/ou foto).

**Endpoint:** `PUT /me`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Body:**
```json
{
  "nome": "João da Silva",
  "email": "joao.silva@exemplo.com",
  "senha": "novaSenha123",
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Campos:**
- `nome` (opcional): Nome completo do usuário
- `email` (opcional): Email válido. Verifica duplicidade no mesmo tenant
- `senha` (opcional): Nova senha (mínimo 6 caracteres). Hash gerado automaticamente
- `foto_base64` (opcional): Imagem em formato base64 com data URI

**Validações:**
- Email: formato válido e único no tenant
- Senha: mínimo 6 caracteres
- Foto: 
  - Formato: `data:image/{tipo};base64,{dados}`
  - Tipos aceitos: jpeg, jpg, png, gif, webp
  - Tamanho máximo: 5MB
  - Base64 válido

**Resposta de Sucesso (200):**
```json
{
  "message": "Usuário atualizado com sucesso",
  "user": {
    "id": 1,
    "tenant_id": 1,
    "nome": "João da Silva",
    "email": "joao.silva@exemplo.com",
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "created_at": "2025-11-20 10:30:00",
    "updated_at": "2025-11-23 14:25:00"
  }
}
```

**Resposta de Erro - Validação (422):**
```json
{
  "errors": [
    "Email inválido",
    "Senha deve ter no mínimo 6 caracteres",
    "Imagem muito grande. Tamanho máximo: 5MB"
  ]
}
```

**Resposta de Erro - Nenhum Dado (400):**
```json
{
  "error": "Nenhum dado foi atualizado"
}
```

---

### 3. Obter Estatísticas do Usuário
Retorna estatísticas de um usuário específico (check-ins, PRs, foto).

**Endpoint:** `GET /usuarios/{id}/estatisticas`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Parâmetros de URL:**
- `id`: ID do usuário

**Resposta de Sucesso (200):**
```json
{
  "id": 1,
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "foto_url": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "total_checkins": 45,
  "total_prs": 0,
  "created_at": "2025-11-20 10:30:00",
  "updated_at": "2025-11-23 14:20:00"
}
```

**Resposta de Erro (404):**
```json
{
  "error": "Usuário não encontrado"
}
```

---

## Exemplos de Uso

### Exemplo 1: Atualizar Apenas o Nome
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João da Silva Junior"
  }'
```

### Exemplo 2: Atualizar Email e Senha
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "email": "joao.junior@exemplo.com",
    "senha": "novaSenha456"
  }'
```

### Exemplo 3: Atualizar Foto de Perfil
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..."
  }'
```

### Exemplo 4: Atualizar Tudo
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João da Silva Junior",
    "email": "joao.junior@exemplo.com",
    "senha": "novaSenha789",
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD..."
  }'
```

### Exemplo 5: Remover Foto (enviar string vazia)
```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "foto_base64": ""
  }'
```

---

## Notas Importantes

### Formato da Foto Base64
A foto deve ser enviada no formato Data URI completo:
```
data:image/{tipo};base64,{dados_base64}
```

Exemplo:
```
data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQ...
```

### Conversão de Arquivo para Base64

**JavaScript (Frontend):**
```javascript
function convertToBase64(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => resolve(e.target?.result as string);
    reader.onerror = reject;
    reader.readAsDataURL(file);
  });
}

// Uso:
const file = input.files[0];
const base64 = await convertToBase64(file);
```

**PHP (Backend - para testes):**
```php
$imageData = file_get_contents('foto.jpg');
$base64 = 'data:image/jpeg;base64,' . base64_encode($imageData);
```

**Bash (para testes):**
```bash
base64 -i foto.jpg | awk '{printf "data:image/jpeg;base64,%s", $0}'
```

### Limitações
- Tamanho máximo da foto: 5MB
- Formatos aceitos: JPEG, JPG, PNG, GIF, WebP
- O campo LONGTEXT do MySQL suporta até ~4GB, mas recomenda-se manter imagens menores
- Para melhor performance, considere redimensionar imagens antes do upload (ex: 500x500px)

### Multi-tenancy
- Todas as operações respeitam o tenant do usuário autenticado
- Header `X-Tenant-Slug` pode ser usado para especificar o tenant
- Se não especificado, usa `tenant_id=1` como padrão

### Segurança
- Senha é sempre hasheada com `PASSWORD_BCRYPT` antes de salvar
- Token JWT é validado em todas as requisições
- Validação de formato e tamanho de imagem para prevenir uploads maliciosos
- Email é validado para formato e duplicidade no tenant
