# Diagnóstico de Erros de Login

## Problema Identificado

Quando um usuário não existe ou faz login inválido, a aplicação exibe mensagens genéricas como "Sessão expirada", ao invés de mensagens específicas como "Usuário não encontrado".

## Melhorias Realizadas

### 1. Frontend (App Checkin Mobile)

#### Arquivo: `src/services/authService.js`

- ✅ Adicionado logging detalhado de erros de login
- ✅ Captura informações do status HTTP, dados de erro e mensagens

**Log de Debug Esperado:**

```
❌ ERRO NO LOGIN: {
  status: 401,
  statusCode: 401,
  errorData: { error: "Usuário não encontrado" },
  message: "...",
  isNetworkError: false
}
```

#### Arquivo: `app/(auth)/login.jsx`

- ✅ Mapeamento de mensagens de erro mais específicas
- ✅ Diferenciação entre tipos de erro:
  - "usuário não encontrado" → Mostrar "Usuário não encontrado. Verifique o email."
  - "senha inválida" → Mostrar "Senha incorreta. Tente novamente."
  - "usuário inativo" → Mostrar "Usuário inativo. Entre em contato com o administrador."
  - Erro de conexão → Mostrar "Erro de conexão. Verifique sua internet."

**Log de Debug Esperado:**

```
❌ ERRO AO FAZER LOGIN: {
  erro: {...},
  status: 401,
  message: "...",
  errorField: "usuário não encontrado"
}
```

## O Que Precisa Ser Verificado no Backend

### CRÍTICO: Resposta de Erro do Endpoint `/auth/login`

Quando o login falha (usuário não existe, senha errada), o backend deve retornar:

```json
{
  "status": 401,
  "error": "Usuário não encontrado" // Mensagem específica
}
```

### Mensagens Esperadas do Backend

O backend deve retornar uma das mensagens no campo `error`:

| Cenário            | Status | Mensagem esperada no `error`            |
| ------------------ | ------ | --------------------------------------- |
| Usuário não existe | 401    | "Usuário não encontrado" ou similar     |
| Senha incorreta    | 401    | "Senha inválida" ou "Senha incorreta"   |
| Usuário inativo    | 401    | "Usuário inativo" ou "Conta desativada" |
| Erro interno       | 500    | Mensagem descritiva do erro             |

### Teste de Integração

Para testar se está funcionando:

1. **No console do navegador (DevTools):**
   - Abra o console
   - Tente fazer login com um email que não existe
   - Procure pelo log: `❌ ERRO NO LOGIN:`
   - Verifique o campo `errorData` - deve conter a mensagem do backend

2. **Cenários a testar:**
   ```
   ✅ Email válido + Senha válida → Login bem-sucedido
   ✅ Email inválido + Qualquer senha → "Usuário não encontrado"
   ✅ Email válido + Senha inválida → "Senha incorreta"
   ✅ Email válido + Usuário inativo → "Usuário inativo"
   ✅ Sem internet → "Erro de conexão"
   ```

## Próximos Passos

### Para Validar se o Backend Está OK:

1. Verifique o endpoint `/auth/login` no seu código backend
2. Procure por tratamento de erro que retorna 401
3. Verifique se está retornando um campo `error` com mensagem específica
4. Se não estiver, atualize o backend para retornar:

```javascript
// Exemplo Node.js/Express
if (!user) {
  return res.status(401).json({
    error: "Usuário não encontrado",
    code: "USER_NOT_FOUND",
  });
}

if (senha !== user.senha) {
  return res.status(401).json({
    error: "Senha incorreta",
    code: "INVALID_PASSWORD",
  });
}
```

### Logs para Monitorar

1. **Frontend**: Procure por `❌ ERRO NO LOGIN:` no console
2. **Frontend**: Procure por `❌ ERRO AO FAZER LOGIN:` no console
3. **Backend**: Logs de requisição POST em `/auth/login`

## Notas Importantes

- O frontend agora está pronto para receber mensagens específicas do backend
- Se o backend não retornar uma mensagem específica, o frontend exibirá "Email ou senha incorretos" (genérica)
- O logging ajudará a diagnosticar problemas de integração entre frontend e backend

---

**Status**: ✅ Frontend pronto para mensagens específicas
**Ação Necessária**: ⏳ Verificar/atualizar backend para retornar mensagens de erro específicas
