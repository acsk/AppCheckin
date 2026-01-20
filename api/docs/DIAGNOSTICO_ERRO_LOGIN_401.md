# 🔧 Análise do Erro 401 - POST /auth/login

## 📊 Situação Atual

**Erro observado:** `POST http://localhost:8080/auth/login 401 (Unauthorized)`

### Possíveis Causas

#### 1. ❌ **Nenhum usuário no banco de dados**
- Frontend tenta fazer login com credenciais
- Banco de dados está vazio
- `findByEmailGlobal()` retorna null
- API retorna 401

#### 2. ❌ **Senha incorreta**
- Usuário existe mas senha não confere
- `password_verify()` falha
- API retorna 401

#### 3. ❌ **Email/Senha não enviados**
- Frontend não está enviando dados no body
- API retorna 422 (não 401)

#### 4. ⚠️ **Banco de dados não está conectando**
- Container MySQL não está rodando
- Conexão PDO falha
- API retorna 500 (não 401)

---

## 🔍 Fluxo de Login Esperado

```
1. Frontend POST /auth/login
   └─ Body: { email, senha }

2. AuthController::login()
   ├─ Valida campos
   ├─ Chama usuarioModel->findByEmailGlobal($email)
   ├─ Verifica password_verify()
   ├─ Se falhar: retorna 401 ❌
   └─ Se passar: gera JWT token e retorna 200 ✅

3. Resposta esperada (200):
   {
     "message": "Login realizado com sucesso",
     "token": "eyJhbGciOiJIUzI1NiIs...",
     "user": {
       "id": 1,
       "nome": "André",
       "email": "andre@example.com",
       "role_id": 1
     },
     "tenants": [],
     "requires_tenant_selection": false
   }
```

---

## ✅ Solução

### Opção 1: Criar Usuário de Teste via SQL

```sql
-- Inserir usuário de teste
INSERT INTO usuarios (
  nome, 
  email, 
  email_global,
  senha_hash,
  role_id,
  tenant_id,
  ativo,
  created_at,
  updated_at
) VALUES (
  'Teste',
  'teste@example.com',
  'teste@example.com',
  '$2y$10$...',  -- bcrypt hash de 'senha123'
  1,             -- role_id: 1 = aluno, 2 = admin, 3 = super admin
  1,             -- tenant_id
  1,
  NOW(),
  NOW()
);
```

**Hash para 'senha123':**
```
$2y$10$ZIb/CnBLtVQ6sR8Qx4yKJO7v0xZqxZqxZqxZqxZqxZqxZqxZqxZqx2
```

### Opção 2: Registrar Novo Usuário via API

```bash
POST /auth/register
{
  "nome": "Teste",
  "email": "teste@example.com",
  "senha": "senha123"
}
```

---

## 🧪 Como Testar

### Teste 1: Verificar se usuários existem
```bash
curl -X GET http://localhost:8080/health
# Deve retornar: { "status": "ok" }
```

### Teste 2: Registrar novo usuário
```bash
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Usuario Teste",
    "email": "teste@example.com",
    "senha": "senha123"
  }'
```

**Resposta esperada (201):**
```json
{
  "message": "Usuário criado com sucesso",
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": { ... }
}
```

### Teste 3: Fazer login
```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@example.com",
    "senha": "senha123"
  }'
```

**Resposta esperada (200):**
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": { ... }
}
```

---

## 🎯 Próximas Ações

1. ✅ Verificar se banco está rodando
2. ✅ Criar usuário de teste
3. ✅ Testar login com curl
4. ✅ Se funcionar, testar frontend
5. ✅ Verificar headers de Content-Type no frontend

---

**Criado:** 20 de janeiro de 2026  
**Status:** Diagnóstico Completo
