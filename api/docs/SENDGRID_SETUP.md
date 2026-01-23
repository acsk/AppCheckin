# SendGrid - Configuração Rápida

## 🚀 Guia de Implementação SendGrid

### Passo 1: Criar Conta SendGrid

1. Acesse: https://sendgrid.com/
2. Clique em **"Try for Free"**
3. Preencha as informações:
   - Email
   - Senha segura
   - Aceite os termos
4. Verifique seu email

### Passo 2: Criar API Key

1. Login no SendGrid
2. Vá para **Settings > API Keys** 
3. Clique **Create API Key**
4. Dê um nome: `AppCheckin API Key`
5. Selecione permissões: **Restricted Access**
   - ✅ Mail Send (criar)
   - ❌ Desabilite o resto
6. Clique **Create & Copy**
7. **Copie e guarde a chave em local seguro**

### Passo 3: Configurar .env em Produção

```bash
ssh u304177849@appcheckin.com.br

# Editar o arquivo .env
nano /home/u304177849/domains/appcheckin.com.br/public_html/api/.env
```

Adicione no final:

```
# Email/SMTP - SendGrid API
SENDGRID_API_KEY=sua_api_key_aqui
MAIL_FROM_ADDRESS=noreply@appcheckin.com.br
MAIL_FROM_NAME=App Check-in
```

Exemplo de como fica:
```
SENDGRID_API_KEY=SG.abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
MAIL_FROM_ADDRESS=noreply@appcheckin.com.br
MAIL_FROM_NAME=App Check-in
```

### Passo 4: Pull em Produção

```bash
cd /home/u304177849/domains/appcheckin.com.br/public_html/api
git pull origin main
composer update
```

### Passo 5: Testar

```bash
curl -X POST https://api.appcheckin.com.br/auth/password-recovery/request \
  -H "Content-Type: application/json" \
  -d '{"email":"superadmin@appcheckin.com"}' -s
```

Resposta esperada:
```json
{"message":"Se o email existe em nossa base de dados, você receberá um link de recuperação"}
```

### Passo 6: Verificar Email

1. Vá para **Dashboard > Inbox** no SendGrid
2. Você verá o email de teste que foi enviado
3. Clique nele para ver os detalhes

---

## 📊 Limites SendGrid Grátis

- **100 emails/dia** ✅ Suficiente para testes
- **1 mês de teste gratuito**
- **Depois: plano pago** (começa em $14.95/mês para 100k emails)

---

## ✅ Verificação

Após configurar, o sistema:
- ✅ Aceitará requisições de recuperação de senha
- ✅ Enviará emails via SendGrid automaticamente
- ✅ Usuários receberão emails com links de recuperação
- ✅ Links funcionarão por 15 minutos

---

## 🔑 Importante

- **Nunca compartilhe a API Key**
- Se vazar, delete no SendGrid e crie uma nova
- A API Key permite enviar emails, então proteja bem

---

## Próximos Passos

1. ✅ Criar conta SendGrid
2. ✅ Gerar API Key
3. ✅ Adicionar ao .env em produção
4. ✅ Fazer pull do código
5. ✅ Testar endpoint
6. 📋 Frontend está pronto para usar (ver IMPLEMENTACAO_PASSWORD_RECOVERY_FRONTEND.md)

Está tudo pronto? Deixa eu saber quando tiver a API Key do SendGrid!
