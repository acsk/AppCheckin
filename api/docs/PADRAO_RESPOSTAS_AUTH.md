# 📋 Estrutura Padrão de Respostas - AuthController

## ✅ Resposta de Sucesso

### Login Bem-Sucedido
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": {
    "id": 1,
    "nome": "Usuário Teste",
    "email": "teste@example.com",
    "email_global": "teste@example.com",
    "foto_base64": null,
    "role_id": 1
  },
  "tenants": [],
  "requires_tenant_selection": false
}
```

---

## ❌ Resposta de Erro - Estrutura Padrão

Todos os erros agora seguem este padrão:

```json
{
  "type": "error",
  "code": "CÓDIGO_DO_ERRO",
  "message": "Descrição do erro em português"
}
```

---

## 🔍 Referência de Erros

### 401 - Unauthorized (Credenciais Inválidas)

```json
{
  "type": "error",
  "code": "INVALID_CREDENTIALS",
  "message": "Email ou senha inválidos"
}
```

**Quando:** Email não existe ou senha não confere

---

### 422 - Unprocessable Entity (Validação)

#### Campos faltando
```json
{
  "type": "error",
  "code": "MISSING_CREDENTIALS",
  "message": "Email e senha são obrigatórios"
}
```

#### Campos de validação
```json
{
  "type": "error",
  "code": "VALIDATION_ERROR",
  "message": "Erro de validação",
  "errors": [
    "Nome é obrigatório",
    "Email válido é obrigatório",
    "Senha deve ter no mínimo 6 caracteres"
  ]
}
```

#### Tenant ID faltando
```json
{
  "type": "error",
  "code": "MISSING_TENANT_ID",
  "message": "tenant_id é obrigatório"
}
```

#### Campos de seleção de tenant
```json
{
  "type": "error",
  "code": "MISSING_REQUIRED_FIELDS",
  "message": "user_id, email e tenant_id são obrigatórios"
}
```

---

### 403 - Forbidden (Acesso Negado)

#### Sem vínculo com academia
```json
{
  "type": "error",
  "code": "NO_TENANT_ACCESS",
  "message": "Usuário não possui vínculo com nenhuma academia"
}
```

#### Contrato inativo
```json
{
  "type": "error",
  "code": "NO_ACTIVE_CONTRACT",
  "message": "Sua academia não possui contrato ativo. Entre em contato com o suporte."
}
```

#### Acesso negado a academia
```json
{
  "type": "error",
  "code": "TENANT_ACCESS_DENIED",
  "message": "Você não tem acesso a esta academia"
}
```

---

### 401 - Unauthorized (Dados Inválidos)

```json
{
  "type": "error",
  "code": "INVALID_USER_DATA",
  "message": "Dados inválidos"
}
```

**Quando:** Seleção de tenant com dados inconsistentes

---

### 500 - Internal Server Error

```json
{
  "type": "error",
  "code": "USER_CREATION_ERROR",
  "message": "Erro ao criar usuário"
}
```

---

## 🎯 Códigos de Erro (Para Frontend Tratar)

| Código | HTTP | Significado |
|--------|------|------------|
| `INVALID_CREDENTIALS` | 401 | Email/senha inválidos |
| `MISSING_CREDENTIALS` | 422 | Email ou senha não enviados |
| `VALIDATION_ERROR` | 422 | Erros de validação de campos |
| `MISSING_TENANT_ID` | 422 | tenant_id não enviado |
| `MISSING_REQUIRED_FIELDS` | 422 | Campos obrigatórios faltando |
| `NO_TENANT_ACCESS` | 403 | Usuário sem academia associada |
| `NO_ACTIVE_CONTRACT` | 403 | Academia sem contrato ativo |
| `TENANT_ACCESS_DENIED` | 403 | Usuário sem acesso à academia |
| `INVALID_USER_DATA` | 401 | Dados inconsistentes |
| `USER_CREATION_ERROR` | 500 | Erro ao criar usuário |

---

## 💡 Como Usar no Frontend

```typescript
// Exemplo de tratamento de erro
try {
  const response = await fetch('/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, senha })
  });

  const data = await response.json();

  if (data.type === 'error') {
    switch (data.code) {
      case 'INVALID_CREDENTIALS':
        showErrorMessage('Email ou senha incorretos');
        break;
      case 'NO_ACTIVE_CONTRACT':
        showErrorMessage('Sua academia não tem contrato ativo');
        break;
      case 'NO_TENANT_ACCESS':
        showErrorMessage('Você não tem acesso a nenhuma academia');
        break;
      default:
        showErrorMessage(data.message);
    }
  } else {
    // Login bem-sucedido
    localStorage.setItem('token', data.token);
    // ...
  }
} catch (error) {
  showErrorMessage('Erro de conexão com servidor');
}
```

---

## ✨ Benefícios

✅ **Estrutura consistente** - Todos os erros seguem o mesmo padrão  
✅ **Códigos únicos** - Frontend pode tratar cada erro diferente  
✅ **Mensagens claras** - Mensagens em português para o usuário  
✅ **Fácil de debugar** - Código do erro facilita identificar problema  
✅ **Escalável** - Fácil adicionar novos códigos de erro

---

**Criado:** 20 de janeiro de 2026  
**Status:** ✅ Implementado
