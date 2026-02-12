# 🚨 DEPLOY URGENTE - Correção Listagem de Alunos

## Problema Identificado

A rota `/admin/alunos` não está funcionando porque o código em **produção está desatualizado**. 

As correções já foram feitas no commit `6f2f0fb` mas ainda não foram aplicadas no servidor.

### Erros SQL Corrigidos:
- ❌ `ut.tenant_id` → ✅ `tup.tenant_id` (AdminController.php)
- ❌ `ut.status = 'ativo'` → ✅ `tup.ativo = 1` (Usuario.php)

---

## ⚡ Solução Rápida (2 minutos)

### 1️⃣ Conectar ao Servidor via SSH

```bash
ssh u304177849@appcheckin.com.br
```

### 2️⃣ Navegar até o diretório da API

```bash
cd /home/u304177849/domains/api.appcheckin.com.br/public_html
```

### 3️⃣ Fazer Pull das Atualizações

```bash
git pull origin main
```

### 4️⃣ Testar a API

```bash
curl -X GET 'https://api.appcheckin.com.br/admin/alunos?pagina=1&por_pagina=20' \
  -H 'Authorization: Bearer SEU_TOKEN_AQUI'
```

---

## 🔍 Verificação

### Ver Últimos Commits em Produção

```bash
ssh u304177849@appcheckin.com.br
cd /home/u304177849/domains/api.appcheckin.com.br/public_html
git log --oneline -3
```

**Deve mostrar:**
```
39c42cb fix: Corrigir referências SQL após migração usuario_tenant -> tenant_usuario_papel
6f2f0fb fix: corrigir referência de tenant_id e status de usuário no AdminController e Usuario
024e69c chore: update code structure for better readability and maintainability
```

### Ver Diferenças com Produção

```bash
git diff origin/main app/Controllers/AdminController.php
```

Se mostrar diferenças, significa que produção está desatualizada.

---

## 📋 Checklist Pós-Deploy

- [ ] `git pull` executado com sucesso
- [ ] Commit `6f2f0fb` presente em produção
- [ ] GET `/admin/alunos` retorna 200 OK
- [ ] JSON com lista de alunos retornado
- [ ] Matrícula listadas com sucesso

---

## 🆘 Se Ainda Não Funcionar

### 1. Verificar Logs de Erro

```bash
ssh u304177849@appcheckin.com.br
tail -50 /home/u304177849/domains/api.appcheckin.com.br/public_html/public/php-error.log
```

### 2. Limpar Cache (se houver)

```bash
cd /home/u304177849/domains/api.appcheckin.com.br/public_html
rm -rf storage/cache/*
```

### 3. Reiniciar PHP-FPM (se necessário)

```bash
# Verificar processo
ps aux | grep php-fpm

# Pode precisar de sudo ou contato com suporte do servidor
sudo systemctl restart php8.2-fpm
```

---

## 📊 Resumo da Migração

**Data:** 2026-01-29 02:52:14  
**Tabela:** `usuario_tenant` → `tenant_usuario_papel`  
**Conversão:** VARCHAR `status` → TINYINT `ativo`  
**Registros Migrados:** 4 usuários do tenant_id=2

**Arquivos Corrigidos Após Migração:**
- ✅ `app/Controllers/AdminController.php` - linhas 181, 193
- ✅ `app/Models/Usuario.php` - linha 599, 607
- ✅ `database/migrations/*` - função e trigger atualizados

---

## 💡 Prevenção Futura

Para evitar esse tipo de problema:

1. **Sempre fazer deploy após migrations**
2. **Testar endpoints críticos após deploy**
3. **Manter staging sincronizado com produção**
4. **Usar CI/CD para deploy automático**

---

## 📞 Suporte

Se precisar de ajuda:
- **GitHub Commit:** `6f2f0fb`
- **Script Deploy:** `deploy_migration_fix.sh`
- **Documentação:** `20260204_producao_validacao.sql`
