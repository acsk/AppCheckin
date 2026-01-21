# Códigos de Erro de Autenticação

Este documento descreve todos os códigos de erro possíveis retornados pela API de autenticação, permitindo que o frontend diferencie e trate cada cenário apropriadamente.

## Status HTTP 401 - Unauthorized

Todos os erros de autenticação retornam HTTP 401, mas com códigos diferentes para facilitar o tratamento no frontend.

### Formato de Resposta

```json
{
  "type": "error",
  "code": "CODIGO_DO_ERRO",
  "message": "Mensagem descritiva"
}
```

## Códigos de Erro

### `MISSING_TOKEN`
- **Descrição**: O header Authorization não foi fornecido
- **Ação no Frontend**: Redirecionar para login
- **Exemplo**:
```json
{
  "type": "error",
  "code": "MISSING_TOKEN",
  "message": "Token não fornecido"
}
```

---

### `INVALID_TOKEN_FORMAT`
- **Descrição**: O formato do token é inválido (não segue Bearer <token>)
- **Ação no Frontend**: Redirecionar para login e limpar token armazenado
- **Exemplo**:
```json
{
  "type": "error",
  "code": "INVALID_TOKEN_FORMAT",
  "message": "Formato de token inválido"
}
```

---

### `TOKEN_EXPIRED_OR_INVALID`
- **Descrição**: O token expirou ou é inválido (assinatura incorreta)
- **Ação no Frontend**: Limpar o token e redirecionar para login
- **Nota**: Pode tentar refresh do token se implementado
- **Exemplo**:
```json
{
  "type": "error",
  "code": "TOKEN_EXPIRED_OR_INVALID",
  "message": "Token inválido ou expirado"
}
```

---

### `USER_NOT_FOUND` ⭐ **NOVO**
- **Descrição**: O usuário associado ao token não existe mais na base de dados (foi removido ou desativado)
- **Ação no Frontend**: Exibir mensagem específica "Sua conta foi removida ou desativada. Entre em contato com o suporte"
- **Diferente de Token Expirado**: Este código indica que o token é válido, mas o usuário não existe mais
- **Exemplo**:
```json
{
  "type": "error",
  "code": "USER_NOT_FOUND",
  "message": "Usuário não existe ou foi removido"
}
```

---

## Exemplos de Tratamento no Frontend

### Vue.js / JavaScript

```javascript
// No interceptor de requisições
axios.interceptors.response.use(
  response => response,
  error => {
    const errorCode = error.response?.data?.code;
    
    switch(errorCode) {
      case 'TOKEN_EXPIRED_OR_INVALID':
        console.warn('Token expirado');
        localStorage.removeItem('token');
        router.push('/login?reason=token_expired');
        break;
        
      case 'USER_NOT_FOUND':
        console.error('Usuário foi removido');
        localStorage.removeItem('token');
        router.push('/login?reason=user_removed');
        break;
        
      case 'MISSING_TOKEN':
      case 'INVALID_TOKEN_FORMAT':
        localStorage.removeItem('token');
        router.push('/login');
        break;
        
      default:
        // Tratar outros erros
        break;
    }
    
    return Promise.reject(error);
  }
);
```

### React

```javascript
// No axios interceptor
axiosInstance.interceptors.response.use(
  response => response,
  error => {
    const { code, message } = error.response?.data || {};
    
    if (error.response?.status === 401) {
      switch(code) {
        case 'USER_NOT_FOUND':
          alert('Sua conta foi removida ou desativada. Entre em contato com o suporte.');
          break;
          
        case 'TOKEN_EXPIRED_OR_INVALID':
          alert('Sessão expirada. Faça login novamente.');
          break;
          
        default:
          alert('Sessão inválida. Faça login novamente.');
      }
      
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    
    return Promise.reject(error);
  }
);
```

---

## Tabela de Referência Rápida

| Código | Status HTTP | Significa | Ação |
|--------|------------|-----------|------|
| `MISSING_TOKEN` | 401 | Sem token | Ir para login |
| `INVALID_TOKEN_FORMAT` | 401 | Formato ruim | Limpar e ir para login |
| `TOKEN_EXPIRED_OR_INVALID` | 401 | Token inválido/expirado | Limpar e ir para login |
| `USER_NOT_FOUND` | 401 | Usuário não existe | Informar ao usuário que conta foi removida |

---

## Histórico de Mudanças

### v1.1 - Melhorias de Diferencação de Erros
- Adicionado código `USER_NOT_FOUND` para diferenciar usuário removido de token expirado
- Estrutura de resposta padronizada com `type`, `code` e `message`
- Adicionados códigos específicos para cada cenário de erro

