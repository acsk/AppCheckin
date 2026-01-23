# Documentação - Recuperação de Senha (Frontend)

## Overview
Sistema completo de recuperação de senha com envio de emails. O processo funciona em 3 etapas:

1. **Solicitar Recuperação** - Usuário entra com email
2. **Validar Token** - Verificar se o token é válido
3. **Resetar Senha** - Definir nova senha

---

## 🔴 Endpoint 1: Solicitar Recuperação de Senha

**Método:** `POST`  
**URL:** `https://api.appcheckin.com.br/auth/password-recovery/request`

### Request
```json
{
  "email": "usuario@example.com"
}
```

### Response - Sucesso (200)
```json
{
  "message": "Se o email existe em nossa base de dados, você receberá um link de recuperação"
}
```

### Response - Erro (422)
```json
{
  "type": "error",
  "code": "MISSING_EMAIL",
  "message": "Email é obrigatório"
}
```

### O que acontece?
- ✅ Email é verificado no banco
- ✅ Se existir, um token é gerado e enviado por email
- ✅ Token expira em **15 minutos**
- ℹ️ Sempre retorna mensagem de sucesso (por segurança, não informa se email existe)

### Exemplo Frontend (JavaScript/React)
```javascript
async function solicitarRecuperacao(email) {
  try {
    const response = await fetch('https://api.appcheckin.com.br/auth/password-recovery/request', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email })
    });

    const data = await response.json();
    console.log(data.message);
    // Mostrar mensagem: "Verifique seu email para o link de recuperação"
    
  } catch (error) {
    console.error('Erro:', error);
  }
}
```

---

## 🟡 Endpoint 2: Validar Token

**Método:** `POST`  
**URL:** `https://api.appcheckin.com.br/auth/password-recovery/validate-token`

### Request
```json
{
  "token": "3f8a2b9c1d4e7f6a5b8c9d2e1f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f"
}
```

### Response - Sucesso (200)
```json
{
  "message": "Token válido",
  "user": {
    "id": 1,
    "nome": "Super Administrador",
    "email": "superadmin@appcheckin.com"
  }
}
```

### Response - Erro (401)
```json
{
  "type": "error",
  "code": "INVALID_OR_EXPIRED_TOKEN",
  "message": "Token inválido ou expirado"
}
```

### O que acontece?
- ✅ Valida o token no banco
- ✅ Verifica se ainda não expirou (15 minutos)
- ✅ Retorna dados do usuário se válido

### Exemplo Frontend
```javascript
async function validarToken(token) {
  try {
    const response = await fetch('https://api.appcheckin.com.br/auth/password-recovery/validate-token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ token })
    });

    if (response.status === 200) {
      const data = await response.json();
      console.log('Token válido! Usuário:', data.user.nome);
      return true;
    } else {
      console.log('Token inválido ou expirado');
      return false;
    }
    
  } catch (error) {
    console.error('Erro:', error);
    return false;
  }
}
```

---

## 🟢 Endpoint 3: Resetar Senha

**Método:** `POST`  
**URL:** `https://api.appcheckin.com.br/auth/password-recovery/reset`

### Request
```json
{
  "token": "3f8a2b9c1d4e7f6a5b8c9d2e1f3a4b5c6d7e8f9a0b1c2d3e4f5a6b7c8d9e0f",
  "nova_senha": "NovaSenha@2025",
  "confirmacao_senha": "NovaSenha@2025"
}
```

### Response - Sucesso (200)
```json
{
  "message": "Senha alterada com sucesso. Faça login com sua nova senha."
}
```

### Response - Erro (422) - Validação
```json
{
  "type": "error",
  "code": "VALIDATION_ERROR",
  "errors": [
    "Nova senha deve ter no mínimo 6 caracteres",
    "As senhas não coincidem"
  ]
}
```

### Response - Erro (401) - Token inválido
```json
{
  "type": "error",
  "code": "INVALID_OR_EXPIRED_TOKEN",
  "message": "Token inválido ou expirado"
}
```

### Regras de Validação
- ✅ Mínimo 6 caracteres
- ✅ Senhas devem coincidir
- ✅ Token deve ser válido e não expirado

### Exemplo Frontend
```javascript
async function resetarSenha(token, novaSenha, confirmacaoSenha) {
  try {
    const response = await fetch('https://api.appcheckin.com.br/auth/password-recovery/reset', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        token,
        nova_senha: novaSenha,
        confirmacao_senha: confirmacaoSenha
      })
    });

    const data = await response.json();

    if (response.status === 200) {
      console.log('Sucesso!', data.message);
      // Redirecionar para login
      window.location.href = '/login';
    } else if (response.status === 422) {
      console.log('Erros de validação:', data.errors);
      // Mostrar erros para o usuário
      data.errors.forEach(erro => alert(erro));
    } else {
      console.log('Erro:', data.message);
      alert('Token inválido ou expirado. Solicite nova recuperação.');
    }
    
  } catch (error) {
    console.error('Erro:', error);
  }
}
```

---

## 📧 Flow Completo no Frontend

```javascript
// 1. Página de "Esqueci Minha Senha"
async function handleForgotPassword(email) {
  await solicitarRecuperacao(email);
  alert('Verifique seu email para o link de recuperação');
}

// 2. Link clicado no email (extrai token da URL)
function extrairTokenDaURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('token');
}

// 3. Página de resetar senha
async function handleResetPassword(novaSenha, confirmacao) {
  const token = extrairTokenDaURL();
  
  // Validar token primeiro
  const tokenValido = await validarToken(token);
  if (!tokenValido) {
    alert('Link expirado. Solicite uma nova recuperação.');
    return;
  }
  
  // Reset da senha
  await resetarSenha(token, novaSenha, confirmacao);
}
```

---

## 🎯 Instruções para Implementação no Frontend

### 1. Criar página "Esqueci Minha Senha"
- Campo de email
- Botão "Enviar Link de Recuperação"
- Mensagem de confirmação

### 2. Criar página "Resetar Senha"
- Campos: nova senha, confirmação de senha
- Validação de força de senha (opcional)
- Botão "Atualizar Senha"

### 3. Extrair token do link de email
```javascript
// URL no email será algo como:
// https://painel.appcheckin.com.br/recuperar-senha?token=ABC123...

const token = new URLSearchParams(window.location.search).get('token');
```

### 4. Estados possíveis
```javascript
{
  // Aguardando email
  state: 'WAITING_EMAIL',
  
  // Email enviado com sucesso
  state: 'EMAIL_SENT',
  
  // Validando token
  state: 'VALIDATING_TOKEN',
  
  // Token válido, mostrar formulário de nova senha
  state: 'FORM_RESET_SENHA',
  
  // Processando reset
  state: 'PROCESSING_RESET',
  
  // Sucesso
  state: 'SUCCESS',
  
  // Erro
  state: 'ERROR',
  error: 'mensagem de erro'
}
```

---

## 🔒 Segurança

- ✅ Token expira em 15 minutos
- ✅ Token é único (gerado com `random_bytes`)
- ✅ Senha é hasheada com bcrypt
- ✅ Email não é revelado (retorna mesma mensagem se email existe ou não)
- ✅ HTTPS obrigatório
- ✅ CORS configurado para o painel

---

## 📝 Exemplo Completo em React

```jsx
import { useState } from 'react';

export function RecuperacaoSenha() {
  const [step, setStep] = useState('email'); // 'email', 'validate', 'reset'
  const [email, setEmail] = useState('');
  const [token, setToken] = useState('');
  const [novaSenha, setNovaSenha] = useState('');
  const [confirmacao, setConfirmacao] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  // Step 1: Solicitar recuperação
  const handleRequestReset = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    try {
      const res = await fetch('https://api.appcheckin.com.br/auth/password-recovery/request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email })
      });

      setMessage('Verifique seu email para o link de recuperação');
      // Extrair token da URL se necessário
      const urlParams = new URLSearchParams(window.location.search);
      const extractedToken = urlParams.get('token');
      if (extractedToken) {
        setToken(extractedToken);
        setStep('validate');
      }
    } catch (error) {
      setMessage('Erro ao solicitar recuperação');
    } finally {
      setLoading(false);
    }
  };

  // Step 2: Validar token
  const handleValidateToken = async () => {
    setLoading(true);
    try {
      const res = await fetch('https://api.appcheckin.com.br/auth/password-recovery/validate-token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token })
      });

      if (res.ok) {
        setStep('reset');
        setMessage('');
      } else {
        setMessage('Token inválido ou expirado');
      }
    } catch (error) {
      setMessage('Erro ao validar token');
    } finally {
      setLoading(false);
    }
  };

  // Step 3: Resetar senha
  const handleReset = async (e) => {
    e.preventDefault();
    
    if (novaSenha !== confirmacao) {
      setMessage('Senhas não coincidem');
      return;
    }

    if (novaSenha.length < 6) {
      setMessage('Senha deve ter no mínimo 6 caracteres');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch('https://api.appcheckin.com.br/auth/password-recovery/reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token,
          nova_senha: novaSenha,
          confirmacao_senha: confirmacao
        })
      });

      const data = await res.json();

      if (res.ok) {
        setMessage('Senha alterada com sucesso! Faça login agora.');
        setTimeout(() => window.location.href = '/login', 2000);
      } else {
        setMessage(data.message || 'Erro ao resetar senha');
      }
    } catch (error) {
      setMessage('Erro ao resetar senha');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="recuperacao-senha">
      <h2>Recuperação de Senha</h2>
      
      {step === 'email' && (
        <form onSubmit={handleRequestReset}>
          <input
            type="email"
            placeholder="Digite seu email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
          <button type="submit" disabled={loading}>
            {loading ? 'Enviando...' : 'Enviar Link'}
          </button>
        </form>
      )}

      {step === 'reset' && (
        <form onSubmit={handleReset}>
          <input
            type="password"
            placeholder="Nova senha"
            value={novaSenha}
            onChange={(e) => setNovaSenha(e.target.value)}
            required
          />
          <input
            type="password"
            placeholder="Confirme a senha"
            value={confirmacao}
            onChange={(e) => setConfirmacao(e.target.value)}
            required
          />
          <button type="submit" disabled={loading}>
            {loading ? 'Salvando...' : 'Atualizar Senha'}
          </button>
        </form>
      )}

      {message && <p className="message">{message}</p>}
    </div>
  );
}
```

---

## ✅ Checklist de Implementação

- [ ] Criar página "Esqueci a Senha"
- [ ] Integrar endpoint `/auth/password-recovery/request`
- [ ] Criar página "Resetar Senha"
- [ ] Extrair token da URL
- [ ] Validar token com `/auth/password-recovery/validate-token`
- [ ] Integrar reset de senha com `/auth/password-recovery/reset`
- [ ] Testes em ambiente de produção
- [ ] Tratamento de erros e mensagens de usuário

---

## 📞 Suporte

Qualquer dúvida, verifique o console do navegador (DevTools) para erros!
