# 🆘 Troubleshooting - Erros 404 em Produção

## ❌ Sintomas

```
GET https://painel.appcheckin.com.br/_expo/static/css/web-*.css 404
GET https://painel.appcheckin.com.br/_expo/static/js/web/index-*.js 404
GET https://painel.appcheckin.com.br/fonts.css 404
GET https://painel.appcheckin.com.br/favicon.ico 404
```

---

## 🔍 Passo 1: Verificar se os Arquivos Estão no Servidor

SSH no servidor e execute:

```bash
# Conectar ao servidor
ssh usuario@seu-servidor

# Verificar se dist existe
ls -la /var/www/seu-app/dist/ | head -20

# Verificar se fonts foram copiados
ls -la /var/www/seu-app/dist/_expo/Fonts/ | head -5

# Verificar tamanho
du -sh /var/www/seu-app/dist/

# Verificar permissões
find /var/www/seu-app/dist/ -type f | xargs ls -l | head -10
```

**Esperado:**
- ✅ Pasta `dist/` com ~50+ MB
- ✅ Arquivo `fonts.css` (~2.6 KB)
- ✅ Pasta `_expo/Fonts/` com 19 arquivos .ttf
- ✅ Permissões 644 em arquivos, 755 em diretórios

---

## 🔍 Passo 2: Verificar Configuração do Servidor Web

### **NGINX**

```bash
# Conectar ao servidor
ssh usuario@seu-servidor

# Verificar configuração
sudo cat /etc/nginx/sites-enabled/seu-dominio.conf

# Deve ter algo assim:
# server {
#     root /var/www/seu-app/dist;  ← Aponta para DIST
#     location / {
#         try_files $uri $uri/ /index.html;
#     }
# }

# Testar configuração
sudo nginx -t

# Se OK, recarregar
sudo systemctl reload nginx
```

### **APACHE**

```bash
# Conectar ao servidor
ssh usuario@seu-servidor

# Verificar configuração
sudo cat /etc/apache2/sites-enabled/seu-dominio.conf

# Deve ter:
# <Directory /var/www/seu-app/dist>
#     DocumentRoot /var/www/seu-app/dist
#     AllowOverride All
#     Require all granted
# </Directory>

# Verificar .htaccess
cat /var/www/seu-app/dist/.htaccess

# Ativar mod_rewrite
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## 🔍 Passo 3: Verificar DocumentRoot

A raiz do servidor deve apontar para `dist/`, **não** para um diretório pai.

```bash
# CORRETO:
DocumentRoot /var/www/painel/dist

# ERRADO:
DocumentRoot /var/www/painel
```

---

## 🔧 Passo 4: Corrigir Permissões

Se os arquivos existem mas retornam 404, pode ser problema de permissões:

```bash
ssh usuario@seu-servidor

# Entrar no diretório
cd /var/www/seu-app/dist

# Corrigir permissões de arquivos (644)
find . -type f -exec chmod 644 {} \;

# Corrigir permissões de diretórios (755)
find . -type d -exec chmod 755 {} \;

# Certificar que o usuário web pode ler
sudo chown -R www-data:www-data /var/www/seu-app/dist/

# Verificar
ls -la | head -5
```

---

## 🔍 Passo 5: Testar Manualmente

### **Via curl:**
```bash
# Do seu computador
curl -I https://painel.appcheckin.com.br/_expo/static/css/web-7c347f7ba1c2b5fdd8e1ec682d3ced07.css

# Esperado: HTTP/1.1 200 OK
# Errado: HTTP/1.1 404 Not Found
```

### **Via SSH (do servidor):**
```bash
ssh usuario@seu-servidor

# Testar arquivo existe
test -f /var/www/seu-app/dist/_expo/static/css/web-7c347f7ba1c2b5fdd8e1ec682d3ced07.css && echo "✅ Existe" || echo "❌ Não existe"

# Testar permissão de leitura
test -r /var/www/seu-app/dist/_expo/static/css/web-7c347f7ba1c2b5fdd8e1ec682d3ced07.css && echo "✅ Legível" || echo "❌ Não legível"
```

---

## 📋 Checklist de Diagnóstico

```bash
☐ Arquivos estão em /var/www/seu-app/dist/?
☐ Pasta _expo/Fonts/ tem 19 arquivos .ttf?
☐ Arquivo fonts.css existe em /var/www/seu-app/dist/?
☐ DocumentRoot no nginx/apache aponta para dist/?
☐ Permissões são 644 para arquivos, 755 para diretórios?
☐ Usuario web (www-data) tem permissão de leitura?
☐ Configuração do nginx/apache está correta?
☐ nginx/apache foi recarregado após mudanças?
☐ Não há firewall bloqueando?
```

---

## 🚨 Problemas Comuns

### **1. Estrutura de Diretórios Errada**

```bash
# ❌ ERRADO:
/var/www/painel/dist/index.html
# Servidor apontando para /var/www/painel (acima)

# ✅ CORRETO:
/var/www/painel/dist/index.html
# Servidor apontando para /var/www/painel/dist
```

**Solução:**
```nginx
# nginx.conf
root /var/www/painel/dist;  # ← Apontar para DIST
```

---

### **2. Fonts não foram copiados**

```bash
# ❌ Verifica
ls /var/www/seu-app/dist/_expo/Fonts/
# (vazio ou não existe)

# ✅ Solução
ssh seu-servidor
cd /var/www/seu-app
./copy-fonts-only.sh  # ou ./deploy.sh
```

---

### **3. HTML não tem link de fonts.css**

```bash
# ❌ Verifica
grep fonts.css /var/www/seu-app/dist/index.html
# (sem resultado)

# ✅ Solução - Regenerar
cd /Users/andrecabral/Projetos/AppCheckin/painel
./deploy.sh

# Upload de novo
scp dist/index.html usuario@seu-servidor:/var/www/seu-app/dist/
scp dist/fonts.css usuario@seu-servidor:/var/www/seu-app/dist/
```

---

### **4. .htaccess faltando (Apache)**

```bash
# ✅ Verificar/Criar
cat > /var/www/seu-app/dist/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteRule ^index\.html$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . /index.html [L]
</IfModule>
EOF

chmod 644 /var/www/seu-app/dist/.htaccess
```

---

## 🧪 Script de Teste Rápido

Execute no seu computador:

```bash
# Testar se site está respondendo
./diagnostico.sh https://painel.appcheckin.com.br
```

---

## ✅ Se Tudo Passou

Se após esses passos os 404s sumirem:

```bash
# Limpar cache do navegador
# Ctrl+Shift+Delete (Windows/Linux)
# Cmd+Shift+Delete (Mac)

# Ou testar em incógnito
```

---

## 📞 Se Ainda não Funcionar

Colete informações para debug:

```bash
# 1. Configuração do nginx/apache
sudo cat /etc/nginx/sites-enabled/seu-dominio.conf 2>/dev/null || \
sudo cat /etc/apache2/sites-enabled/seu-dominio.conf

# 2. Estrutura de arquivos
ls -la /var/www/seu-app/dist/ | head -20

# 3. Logs
sudo tail -50 /var/log/nginx/error.log 2>/dev/null || \
sudo tail -50 /var/log/apache2/error.log

# 4. Teste de conectividade
curl -v https://painel.appcheckin.com.br/index.html

# Compartilhe esses outputs para diagnóstico
```

---

## 🔗 Referências Rápidas

- [Nginx DocumentRoot](https://nginx.org/en/docs/http/ngx_http_core_module.html#root)
- [Apache DocumentRoot](https://httpd.apache.org/docs/2.4/mod/core.html#documentroot)
- [.htaccess SPAs](https://router.vuejs.org/guide/deployment.html#apache)
- [Permissões Linux](https://linux.die.net/man/1/chmod)

---

**Última atualização**: 19 de Janeiro de 2026
