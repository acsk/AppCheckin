# Soluções para Envio de Emails em Produção

## Problema Identificado

A Hostinger está bloqueando autenticação SMTP com as credenciais do `mail@appcheckin.com.br` (erro: 535 5.7.8 Authentication failed).

## Soluções Disponíveis (em ordem de recomendação)

### 1. ✅ **Mailtrap** (Recomendado - GRATUITO para teste/desenvolvimento)

**Vantagens:**
- ✅ Gratuito para emails ilimitados
- ✅ Perfeito para testes e desenvolvimento
- ✅ Sandbox seguro (emails não saem para a internet)
- ✅ Suporta 100 SMTP sessions/dia
- ✅ Fácil de configurar

**Como configurar:**

1. Acesse: https://mailtrap.io/
2. Crie uma conta gratuita
3. Crie um novo projeto
4. Copie as credenciais SMTP (aparece um código pronto)
5. Adicione ao `.env`:

```
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<seu_username>
MAIL_PASSWORD=<sua_password>
MAIL_FROM_ADDRESS=app@appcheckin.com.br
MAIL_FROM_NAME=App Check-in
```

**Testar:**
```bash
# Localmente
php test_smtp_credentials.php live.smtp.mailtrap.io <username> <password>
```

---

### 2. ✅ **SendGrid** (GRATUITO - 100 emails/dia)

**Vantagens:**
- ✅ Emails entregues de verdade (não sandbox)
- ✅ 100 emails/dia gratuito
- ✅ Muito confiável

**Como configurar:**

1. Acesse: https://sendgrid.com/
2. Crie conta gratuita
3. Gere uma chave API
4. Copie o código:

```
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=apikey
MAIL_PASSWORD=SG.seu_api_key_aqui
MAIL_FROM_ADDRESS=app@appcheckin.com.br
MAIL_FROM_NAME=App Check-in
```

---

### 3. **Hostinger - Resolver Bloqueio**

Se quiser continuar com Hostinger:

**Opções:**
1. **Contatar suporte Hostinger** para desbloquear SMTP autenticado
2. **Usar email padrão da conta** (pode haver um email padrão criado automaticamente)
3. **Ativar 2FA se está desativado** - pode ser requisito de segurança

---

## 🔧 Implementação Recomendada (Mailtrap)

### Passo 1: Criar conta Mailtrap
```
https://mailtrap.io/register
```

### Passo 2: Atualizar `.env` em Produção

```bash
ssh u304177849@appcheckin.com.br

# Editar o arquivo .env
nano /home/u304177849/domains/appcheckin.com.br/public_html/api/.env
```

Substituir a seção de EMAIL por:

```
# Email/SMTP - Mailtrap (SANDBOX para testes)
MAIL_HOST=live.smtp.mailtrap.io
MAIL_PORT=587
MAIL_USERNAME=seu_username_mailtrap
MAIL_PASSWORD=sua_password_mailtrap
MAIL_FROM_ADDRESS=app@appcheckin.com.br
MAIL_FROM_NAME=App Check-in
MAIL_ENCRYPTION=tls
MAIL_DEBUG=false
```

### Passo 3: Testar

```bash
curl -X POST https://api.appcheckin.com.br/auth/password-recovery/request \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@appcheckin.com"}' -s
```

Depois verifique no painel do Mailtrap se o email foi recebido (em Inbox).

---

## 📊 Comparação de Soluções

| Aspecto | Mailtrap | SendGrid | Hostinger SMTP |
|--------|----------|----------|----------------|
| **Custo** | Gratuito | 100 emails/dia grátis | Incluído |
| **Confiabilidade** | Alta (sandbox) | Muito alta | Bloqueado |
| **Configuração** | Fácil (TLS) | Fácil (API) | Problemático |
| **Emails reais** | Não (sandbox) | Sim | Sim (se funcionar) |
| **Para testes** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ❌ |
| **Para produção** | ❌ | ⭐⭐⭐⭐⭐ | ❌ (bloqueado) |

---

## 🚀 Próximos Passos

1. **Escolha a solução** (recomendo Mailtrap para teste)
2. **Crie a conta** (5 minutos)
3. **Atualize o `.env`** em produção
4. **Teste o endpoint**
5. **Verifique o inbox** (Mailtrap) ou spam folder (SendGrid)

Qual solução prefere usar?
