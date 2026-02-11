# 🆘 Ação Imediata - Erros 404 em Produção

## ❌ Problema Identificado

```
404 em: https://painel.appcheckin.com.br/
  • /_expo/static/css/web-*.css
  • /_expo/static/js/web/index-*.js
  • /fonts.css
  • /favicon.ico
```

---

## ✅ Solução Rápida (5 minutos)

### **Passo 1: Verificar Estrutura Local**

Seu `dist/` tem esses arquivos?

```bash
ls -la /Users/andrecabral/Projetos/AppCheckin/painel/dist/ | head -20

# Esperado ver:
# ✅ index.html
# ✅ fonts.css
# ✅ favicon.ico
# ✅ _expo/
```

### **Passo 2: Regenerar se Necessário**

```bash
cd /Users/andrecabral/Projetos/AppCheckin/painel

# Executar deploy completo
./scripts/deploy.sh

# Ou manualmente
npx expo export --platform web
./scripts/copy-fonts-only.sh
```

### **Passo 3: Fazer Upload para Servidor**

```bash
# Via SCP (recomendado)
scp -r /Users/andrecabral/Projetos/AppCheckin/painel/dist/* \
    usuario@seu-servidor:/var/www/painel/

# Ou via rsync (mais rápido para atualizações)
rsync -avz --delete /Users/andrecabral/Projetos/AppCheckin/painel/dist/ \
    usuario@seu-servidor:/var/www/painel/
```

### **Passo 4: Verificar no Servidor**

SSH no servidor e execute:

```bash
ssh usuario@seu-servidor

# Verificar se arquivos estão lá
ls -la /var/www/painel/ | head -20

# Verificar fonts
ls -la /var/www/painel/_expo/Fonts/ | wc -l
# (Esperado: 19+ arquivos .ttf)

# Corrigir permissões
chmod -R 755 /var/www/painel/
find /var/www/painel -type f -exec chmod 644 {} \;

# Corrigir proprietário
sudo chown -R www-data:www-data /var/www/painel/
```

### **Passo 5: Recarregar Servidor Web**

#### **Se Nginx:**
```bash
sudo nginx -t  # Testar configuração
sudo systemctl reload nginx
```

#### **Se Apache:**
```bash
sudo apache2ctl configtest  # Testar configuração
sudo systemctl reload apache2
```

---

## 🔍 Diagnóstico Rápido

Execute este script para testar:

```bash
cd /Users/andrecabral/Projetos/AppCheckin/painel
./scripts/diagnostico.sh https://painel.appcheckin.com.br
```

---

## 🎯 Checklist

```bash
☐ dist/ contém index.html, fonts.css, favicon.ico?
☐ dist/_expo/Fonts/ tem 19 arquivos .ttf?
☐ dist/fonts.css tem ~2.6 KB?
☐ Executou ./scripts/deploy.sh ou npx expo export?
☐ Fez upload de dist/* para servidor?
☐ Verificou permissões no servidor (644/755)?
☐ Recarregou nginx/apache?
☐ Testou em navegador (Ctrl+Shift+Delete cache)?
```

---

## 📊 Estrutura Esperada no Servidor

```
/var/www/painel/  ← DocumentRoot do nginx/apache
├── index.html
├── fonts.css
├── favicon.ico
├── _expo/
│   ├── Fonts/     (19 .ttf)
│   └── static/
│       ├── css/
│       └── js/
└── .htaccess (se Apache)
```

---

## ⚠️ Causas Comuns

| Sintoma | Causa | Solução |
|---------|-------|---------|
| Todos 404 | Arquivos não no servidor | Upload dist/* novamente |
| CSS/JS 404 | DocumentRoot errado | Apuntar para `/var/www/painel` (sem `/dist`) |
| Fonts 404 | Fonts não copiados | Executar `./scripts/deploy.sh` |
| Permissão negada | Permissões erradas | `chmod -R 755 /var/www/painel/` |
| Rewrite não funciona | .htaccess faltando | Criar `.htaccess` (Apache) |

---

## 📞 Debug Avançado

Se ainda não funcionar, execute no servidor:

```bash
# 1. Verificar configuração nginx
sudo cat /etc/nginx/sites-enabled/painel.conf

# 2. Ver logs de erro
sudo tail -50 /var/log/nginx/error.log

# 3. Testar direto
curl -I http://localhost/_expo/static/css/web-*.css

# 4. Verificar se arquivo existe
test -f /var/www/painel/_expo/Fonts/Feather.ttf && echo "✅ Existe" || echo "❌ Não"

# 5. Verificar permissão
ls -l /var/www/painel/_expo/Fonts/Feather.ttf
# Esperado: -rw-r--r-- (644)
```

---

## 🚀 Solução Definitiva

Se problema persistir, passe para o seu DevOps/sysadmin:

```
Problema: Erros 404 em https://painel.appcheckin.com.br/
Arquivos: /var/www/painel/
Servidor: nginx/apache
Necessário:
  1. Verificar DocumentRoot aponta para /var/www/painel
  2. Verificar permissões (644 arquivos, 755 diretórios)
  3. Verificar nginx/apache recarregado
  4. Verificar não há bloqueio de firewall
```

---

## 📚 Documentação Relacionada

- [TROUBLESHOOTING.md](TROUBLESHOOTING.md) - Guia detalhado
- [DEPLOY_SCRIPTS.md](DEPLOY_SCRIPTS.md) - Como fazer deploy
- [DEPLOYMENT_ICONES.md](DEPLOYMENT_ICONES.md) - Configuração nginx/apache

---

**Tempo esperado para resolver: 10-15 minutos**

Faça os passos 1-5 acima e teste no navegador!
