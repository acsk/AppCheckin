# ⚠️ CORREÇÃO URGENTE: Erro de Parsing do .env

## 🔴 Problema

**Erro ao fazer login:**
```
Fatal error: Uncaught Dotenv\Exception\InvalidFileException: 
Failed to parse dotenv file. Encountered unexpected whitespace at [App Check-in]
```

## ✅ Causa & Solução

O problema ocorre quando variáveis com **espaços** no `.env` não estão entre **aspas**.

### Linha com Problema:
```bash
# ❌ ERRADO
MAIL_FROM_NAME=App Check-in

# ✅ CORRETO
MAIL_FROM_NAME="App Check-in"
```

## 🚀 Como Corrigir em Produção

### Passo 1: Conectar via SSH

```bash
ssh u304177849@appcheckin.com.br
cd /home/u304177849/domains/appcheckin.com.br/public_html/api
```

### Passo 2: Editar arquivo .env

```bash
# Abrir .env em editor
nano .env

# Ou copiar do exemplo
cp .env.example .env
```

### Passo 3: Verificar/Corrigir Valores

**Linhas críticas a verificar:**

```bash
# ✅ CORRETO - Valores com espaços entre aspas
MAIL_FROM_NAME="App Check-in"
APP_URL=https://api.appcheckin.com.br

# ✅ CORRETO - Valores simples sem espaços
DB_HOST=localhost
DB_PORT=3306

# ✅ CORRETO - Strings de senha sem aspas (sem espaços)
DB_PASS=sua_senha_aqui
JWT_SECRET=sua_chave_segura_aqui

# ✅ CORRETO - Se tiver espaços, usar aspas
MAIL_FROM_NAME="App Check-in Production"
```

### Passo 4: Adicionar SendGrid API Key

```bash
# Adicionar (ou deixar vazio por enquanto)
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxx
```

### Passo 5: Salvar e Testar

```bash
# Se usando nano, pressionar: Ctrl+X, Y, Enter

# Testar parsing
php -r "require '/home/u304177849/domains/appcheckin.com.br/public_html/api/vendor/autoload.php'; \$dotenv = new Dotenv\Dotenv('/home/u304177849/domains/appcheckin.com.br/public_html/api'); \$dotenv->load(); echo 'OK - .env carregado com sucesso';"
```

## 🔍 Referência Completa de Valores

| Variável | Valor Exemplo | Precisa Aspas? |
|----------|---------------|----------------|
| `DB_HOST` | localhost | ❌ Não |
| `DB_PORT` | 3306 | ❌ Não |
| `DB_USER` | root | ❌ Não |
| `DB_PASS` | senha123 | ❌ Não (sem espaços) |
| `JWT_SECRET` | sua_chave_muito_segura | ❌ Não (sem espaços) |
| `MAIL_FROM_NAME` | App Check-in | ✅ SIM (tem espaço!) |
| `APP_URL` | https://api.appcheckin.com.br | ❌ Não |
| `SENDGRID_API_KEY` | SG.xxxxx... | ❌ Não |

## ⚡ Quick Fix

Se quiser uma solução rápida via SSH:

```bash
ssh u304177849@appcheckin.com.br
cat > /home/u304177849/domains/appcheckin.com.br/public_html/api/.env << 'EOF'
DB_HOST=localhost
DB_PORT=3306
DB_NAME=appcheckin
DB_USER=u304177849
DB_PASS=sua_senha_db_aqui
JWT_SECRET=sua_chave_secreta_muito_segura_aqui
APP_ENV=production
APP_URL=https://api.appcheckin.com.br
SENDGRID_API_KEY=sua_sendgrid_key_aqui
MAIL_FROM_ADDRESS=mail@appcheckin.com.br
MAIL_FROM_NAME="App Check-in"
EOF
```

## ✅ Verificação Pós-Correção

```bash
# Fazer login deve funcionar
curl -X POST "https://api.appcheckin.com.br/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"seu_email@example.com","password":"senha"}'

# Esperado: resposta JSON (não erro de parsing)
```

## 📚 Referência da Documentação

- `.env.example` - Template com todas as variáveis
- `DEPLOY_PRODUCTION.md` - Guide de deployment
- `DEBUG_UPLOAD_500.md` - Guide de debug

---

**Commit:** `3d3c1dd`  
**Data:** 23/01/2026  
**Status:** ✅ Corrigido e documentado
