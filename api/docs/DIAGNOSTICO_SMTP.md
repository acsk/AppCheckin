# Diagnóstico SMTP - App Check-in

## ⚠️ Problema Identificado
O servidor SMTP `smtp.hostinger.com` retorna erro de autenticação:
```
535 5.7.8 Error: authentication failed
```

## 🔍 Possíveis Causas

### 1. **Email não ativado para SMTP na Hostinger**
A conta de email pode existir mas não estar configurada para permitir conexões SMTP.

**Solução:**
- Acesse seu painel Hostinger
- Vá para Email > Contas de Email
- Procure por `mail@appcheckin.com.br`
- Verifique se há opção de "Habilitar SMTP" ou similar
- Pode ser necessário ativar autenticação SMTP

### 2. **Senha com caracteres especiais não escapada**
A senha `Emm@3108` contém `@` que pode estar sendo interpretada incorretamente.

**Solução:**
- URL-encode a senha ou use PHP addslashes()
- Teste manualmente com Thunderbird ou outro cliente SMTP para validar credenciais

### 3. **Bloqueio de Segurança da Hostinger**
A Hostinger pode bloquear conexões SMTP de determinados IPs ou regiões.

**Solução:**
- Contate suporte da Hostinger para habilitar SMTP
- Verifique se há whitelist de IPs
- Solicite liberação de acesso SMTP

### 4. **Usar Serviço de Email Externo (Recomendado)**

Para produção, é mais seguro e confiável usar um serviço especializado:

#### **Opção A: SendGrid (Grátis até 100 emails/dia)**
```bash
# Instalar SendGrid
composer require sendgrid/mail

# Criar .env
SENDGRID_API_KEY=seu_api_key_aqui
```

#### **Opção B: Mailgun**
```bash
# Instalar Mailgun
composer require mailgun/mailgun-php

# Criar .env
MAILGUN_DOMAIN=seu_dominio
MAILGUN_SECRET=sua_chave_secreta
```

#### **Opção C: AWS SES**
```bash
# Instalar AWS SDK
composer require aws/aws-sdk-php

# Mais potente e escalável
```

## ✅ Próximos Passos

1. **Verificar no Painel da Hostinger:**
   - Confirmar se SMTP está ativo para `mail@appcheckin.com.br`
   - Testar com cliente como Thunderbird

2. **Se não conseguir com Hostinger:**
   - Escolher entre SendGrid, Mailgun ou AWS SES
   - Vou ajudar a implementar se preferir

3. **Testar localmente:**
   ```bash
   php test_smtp_credentials.php mail@appcheckin.com.br Emm@3108
   ```

4. **Se funcionar, fazer push:**
   - Atualizar .env em produção
   - Testar endpoint `/auth/password-recovery/request`
   - Verificar caixa de entrada

## 📞 Suporte Hostinger
Site: hostinger.com/support
Chat ao vivo disponível 24/7

---

**Status Atual:**
- ❌ SMTP Hostinger com autenticação falhando
- ✅ Código de recuperação de senha está pronto
- ✅ Endpoints funcionando (sem envio de email)
- ⏳ Aguardando resolução SMTP

Qual é a sua preferência? Continuar investigando Hostinger ou migrar para SendGrid/Mailgun?
