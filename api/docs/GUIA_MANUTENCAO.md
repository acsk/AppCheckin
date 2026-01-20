# 🔧 Guia de Manutenção - API AppCheckin

## 📋 Índice
1. [Verificar Estado do Banco](#verificar-estado-do-banco)
2. [Limpar Banco de Dados](#limpar-banco-de-dados)
3. [Recriar SuperAdmin](#recriar-superadmin)
4. [Troubleshooting Comum](#troubleshooting-comum)
5. [Monitoramento da API](#monitoramento-da-api)

---

## Verificar Estado do Banco

### 🔍 Script de Verificação
Verifique o estado completo do banco com este comando:

```bash
# Local ou via SSH
php database/check_database_state.php
```

### Exemplo de Output
```
╔════════════════════════════════════════════════════╗
║  VERIFICAÇÃO DE ESTADO DO BANCO DE DADOS           ║
╚════════════════════════════════════════════════════╝

📊 CONTAGEM DE TABELAS
--------------------------------------------------
  ✓ Usuários                                  1 registros
  ✓ Tenants/Academias                         1 registros
  ○ Turmas                                    0 registros
  ○ Matrículas                                0 registros
  ○ Check-ins                                 0 registros

👤 USUÁRIOS
--------------------------------------------------
  • Professor: 0
  • Admin: 0
  • SuperAdmin: 1
  • Cliente/Aluno: 0

✅ Banco de dados está limpo e pronto para uso!
```

### O que Verificar

| Item | Status OK | Status Aviso |
|------|-----------|--------------|
| SuperAdmin | 1 ou mais | 0 |
| Tenant padrão | id=1 existe | Faltando |
| Planos do Sistema | 5+ | <5 ou vazio |
| Formas de Pagamento | 3+ | <3 ou vazio |
| Check-ins | Qualquer | Vazio após iniciar uso |

---

## Limpar Banco de Dados

### ⚠️ Antes de Começar
- [ ] Faça backup do banco
- [ ] Confirme que é um ambiente de **desenvolvimento**
- [ ] Notifique a equipe que o banco será resetado
- [ ] Exporte dados importantes se necessário

### 🚀 3 Métodos Disponíveis

#### Método 1: Via Endpoint API (Recomendado)

```bash
# 1. Obter token SuperAdmin
TOKEN=$(curl -s -X POST https://api.appcheckin.com.br/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@app.com","password":"senha"}' | jq -r '.token')

# 2. Limpar banco
curl -X POST https://api.appcheckin.com.br/superadmin/cleanup-database \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"

# Resposta esperada:
# {
#   "status": "success",
#   "message": "Banco de dados limpo com sucesso",
#   "tables_cleaned": 15,
#   "timestamp": "2026-01-19 15:30:45"
# }
```

#### Método 2: Via Script PHP (Desenvolvimento Local)

```bash
# Executar com confirmação interativa
php database/cleanup.php

# Output:
# ╔════════════════════════════════════════════════════╗
# ║   LIMPEZA DE BANCO DE DADOS - AppCheckin API      ║
# ╚════════════════════════════════════════════════════╝
#
# ⚠️  AVISO: Esta operação é IRREVERSÍVEL!
#
# Deseja continuar? (SIM/NÃO): SIM
# [Processando...]
# ✓ Limpeza concluída com sucesso!
```

#### Método 3: Via SQL Direto (Automação)

```bash
# Localmente
mysql -u root -p < database/migrations/999_LIMPAR_BANCO_DADOS.sql

# Remotamente (Hostinger)
mysql -h u304177849_api.mysql.db -u u304177849_api -p \
  < database/migrations/999_LIMPAR_BANCO_DADOS.sql

# Via SSH
ssh -p 65002 u304177849@147.79.108.125 \
  "cd public_html/api && mysql -u u304177849_api -p < database/migrations/999_LIMPAR_BANCO_DADOS.sql"
```

### ✅ Verificar Após Limpeza

```bash
# Confirmar limpeza
php database/check_database_state.php

# Deverá mostrar:
# • Usuários: 1 (SuperAdmin)
# • Check-ins: 0
# • Matrículas: 0
# • Turmas: 0
```

---

## Recriar SuperAdmin

### 🆕 Criar SuperAdmin Novo

```bash
php database/create_superadmin.php
```

### Output Esperado
```
✅ Usuário criado com ID: 1

==================================================
🎉 SuperAdmin criado com sucesso!
==================================================

📧 Email:    admin@appcheckin.com
🔐 Senha:    SuperAdmin@2024!
👤 Nome:     Super Admin
🔑 Role ID:  3 (SuperAdmin)
🏢 Tenant:   1

==================================================
⚠️  SEGURANÇA: Mude a senha após primeiro login!
==================================================

✅ Credenciais verificadas com sucesso!
   Pronto para fazer login via endpoint /auth/login
```

### 🔑 Testar Login

```bash
# Fazer login com as credenciais criadas
curl -X POST https://api.appcheckin.com.br/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@appcheckin.com",
    "password": "SuperAdmin@2024!"
  }'

# Resposta esperada:
# {
#   "status": "success",
#   "message": "Login realizado com sucesso",
#   "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
#   "user": {
#     "id": 1,
#     "email": "admin@appcheckin.com",
#     "role_id": 3
#   }
# }
```

### 🔄 Alterar Senha do SuperAdmin

Após o primeiro login, altere a senha via API (deve estar implementado):

```php
// Via endpoint (quando implementado)
PUT /superadmin/usuarios/{usuarioId}
{
  "senha_atual": "SuperAdmin@2024!",
  "senha_nova": "NovaSenhaForte@2024!"
}
```

Ou diretamente no banco:

```sql
UPDATE usuarios 
SET senha = PASSWORD('NovaSenhaForte@2024!')
WHERE id = 1 AND role_id = 3;
```

---

## Troubleshooting Comum

### ❌ Erro: "Bloqueado em produção"

**Problema**: Tentou executar limpeza em produção

**Solução**:
```bash
# Verificar APP_ENV
cat .env.production | grep APP_ENV

# Deve mostrar: APP_ENV=development (NUNCA production!)
```

### ❌ Erro: "Apenas SuperAdmin pode acessar"

**Problema**: Usuário não tem role_id = 3

**Solução**:
```bash
# Verificar role do usuário
mysql> SELECT id, email, role_id FROM usuarios WHERE id = 1;

# Se não for 3, atualizar:
mysql> UPDATE usuarios SET role_id = 3 WHERE id = 1;
```

### ❌ Erro: "Token inválido" 

**Problema**: JWT token expirado ou inválido

**Solução**:
```bash
# Fazer login novamente para pegar novo token
TOKEN=$(curl -s -X POST https://api.appcheckin.com.br/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@app.com","password":"senha"}' | jq -r '.token')

# Confirmar token foi obtido
echo $TOKEN
```

### ❌ Erro: "Conexão recusada"

**Problema**: Banco de dados não está acessível

**Solução**:
```bash
# Verificar status da API
curl https://api.appcheckin.com.br/health

# Se retornar erro, verificar:
# 1. Conexão com servidor
ssh -p 65002 u304177849@147.79.108.125 "ps aux | grep mysql"

# 2. Credenciais
cat /home/u304177849/public_html/api/.env.production

# 3. Host do banco
nslookup u304177849_api.mysql.db
```

### ❌ Dados Não Deletados

**Problema**: Executou limpeza mas dados ainda existem

**Solução**:
```bash
# 1. Verificar se script foi executado completamente
tail -100 cleanup_output.txt

# 2. Verificar se FK checks estão desabilitadas
mysql> SELECT @@foreign_key_checks;
# Deve retornar: 0 (durante execução)

# 3. Executar limpeza novamente com mais verbosidade
php database/cleanup.php --verbose
```

---

## Monitoramento da API

### 🏥 Health Check Endpoints

Estes endpoints retornam informações sobre a saúde da API:

#### GET /ping
```bash
curl https://api.appcheckin.com.br/ping

# Resposta:
# {
#   "message": "pong",
#   "timestamp": "2026-01-19 15:30:45",
#   "php_version": "8.3.25"
# }
```

#### GET /health
```bash
curl https://api.appcheckin.com.br/health

# Resposta:
# {
#   "status": "ok",
#   "php": "running",
#   "database": "connected",
#   "timestamp": "2026-01-19 15:30:45",
#   "environment": "development"
# }
```

#### GET /status
```bash
curl https://api.appcheckin.com.br/status

# Resposta:
# {
#   "status": "online",
#   "app": "AppCheckin API",
#   "version": "1.0.0",
#   "timestamp": "2026-01-19 15:30:45"
# }
```

### 🔐 SuperAdmin Endpoints

#### GET /superadmin/env (SuperAdmin Only)
```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://api.appcheckin.com.br/superadmin/env

# Retorna dados do ambiente (sem senha do banco)
```

#### POST /superadmin/cleanup-database (SuperAdmin Only)
```bash
curl -X POST \
  -H "Authorization: Bearer $TOKEN" \
  https://api.appcheckin.com.br/superadmin/cleanup-database

# Limpa banco mantendo SuperAdmin e dados essenciais
```

### 📊 Monitoramento Recomendado

```bash
#!/bin/bash
# Script de monitoramento contínuo

while true; do
  echo "=== $(date) ==="
  
  # 1. Verificar PHP
  curl -s https://api.appcheckin.com.br/ping | jq .
  
  # 2. Verificar Banco
  curl -s https://api.appcheckin.com.br/health | jq .
  
  # 3. Verificar Status
  curl -s https://api.appcheckin.com.br/status | jq .
  
  # Aguardar 30 segundos
  sleep 30
done
```

### 🚨 Alertas Críticos

Monitore estes sinais de alerta:

| Sinal | Significado | Ação |
|-------|-----------|------|
| `/health` retorna 503 | Banco desconectado | Verificar credenciais BD |
| `/ping` retorna 500 | PHP com erro | Verificar logs de erro |
| `database: "disconnected"` | Conexão BD falha | Reiniciar MySQL |
| Response time > 5s | Performance degradada | Verificar queries lentas |

---

## 📞 Procedimento de Emergência

Se tudo estiver com erro:

```bash
# 1. Verificar status do servidor
ssh -p 65002 u304177849@147.79.108.125

# 2. Verificar logs
tail -100 /home/u304177849/public_html/api/storage/logs/app.log

# 3. Reiniciar PHP
# (Geralmente automático em shared hosting)

# 4. Verificar MySQL
mysql -h u304177849_api.mysql.db -u u304177849_api -p
> SELECT 1;

# 5. Restaurar do backup
mysql -h u304177849_api.mysql.db -u u304177849_api -p < backup.sql

# 6. Verificar se está online
curl https://api.appcheckin.com.br/health
```

---

## 📋 Checklist de Manutenção Mensal

- [ ] Fazer backup do banco (`mysqldump`)
- [ ] Executar `check_database_state.php`
- [ ] Verificar espaço em disco
- [ ] Limpar logs antigos
- [ ] Revisar tabelas grandes
- [ ] Testar endpoints críticos
- [ ] Atualizar documentação se necessário

---

## 🔗 Links Úteis

- [Documentação Completa de Limpeza](./LIMPEZA_BANCO_DADOS.md)
- [API Quick Reference](./API_QUICK_REFERENCE.md)
- [Troubleshooting Guide](./GUIA_TESTES.md)

