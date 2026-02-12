# 🔧 Configurar .htaccess em Produção

## ✅ OPÇÃO 1: Upload via FTP/SFTP (mais simples)

1. **Conecte ao servidor via FTP/SFTP**
   - Host: `ftp.appcheckin.com.br` (ou seu FTP)
   - Usuário: seu usuário FTP
   - Senha: sua senha

2. **Navegue até a pasta `public/`**
   ```
   /public_html/api/public/
   ```

3. **Upload do arquivo `.htaccess`**
   - Localize o arquivo em: `/Users/andrecabral/Projetos/AppCheckin/api/public/.htaccess`
   - Faça upload para: `/public_html/api/public/.htaccess`
   - ⚠️ Certifique-se que é um arquivo **oculto** (começa com ponto)

4. **Verificar permissões**
   - Clique direito → Propriedades
   - Permissões: `644` (rw-r--r--)

---

## ✅ OPÇÃO 2: Criar via SSH (mais rápido)

Se tiver acesso SSH ao servidor:

```bash
# 1. Conectar ao servidor
ssh usuario@api.appcheckin.com.br

# 2. Navegar até public
cd /public_html/api/public/

# 3. Criar o arquivo .htaccess
nano .htaccess
```

4. **Cole este conteúdo:**

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>

# Força HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>

# Headers de segurança
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Desabilita listagem de diretórios
Options -Indexes

# Compressão GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

5. **Salve o arquivo**
   - Nano: `Ctrl + X`, depois `Y`, depois `Enter`

6. **Dar permissões corretas**
   ```bash
   chmod 644 .htaccess
   ```

---

## ✅ OPÇÃO 3: Via cPanel (se disponível)

1. Faça login no cPanel
2. Vá para **File Manager**
3. Navegue até `/public_html/api/public/`
4. Clique em **+ File** e crie `.htaccess`
5. Edite e cole o conteúdo acima
6. Salve

---

## ✅ Verificar se funcionou

Depois de fazer upload/criar, teste:

```bash
# Teste 1: Ping (PHP rodando)
curl -s https://api.appcheckin.com.br/ping | jq .

# Teste 2: Status (API online)
curl -s https://api.appcheckin.com.br/status | jq .

# Teste 3: Health (Banco de dados)
curl -s https://api.appcheckin.com.br/health | jq .
```

Se retornar JSON → ✅ `.htaccess` está funcionando!

---

## 🐛 Se ainda não funcionar

### Problema: Recebe 404
```bash
# Verificar se mod_rewrite está ativo
curl -s -I https://api.appcheckin.com.br/ping

# Se retornar 404 → mod_rewrite não está ativo
# Solução: Contate suporte do servidor compartilhado
```

### Problema: Recebe 500
```bash
# Verificar logs
tail -f /var/log/apache2/error.log

# Ou em servidor compartilhado
tail -f /home/usuario/public_html/logs/error.log
```

### Problema: Arquivo .htaccess invisível
Em alguns clientes FTP, arquivos que começam com `.` são ocultos
- **FileZilla:** View → Show hidden files
- **WinSCP:** View → Show hidden files
- **Nautilus:** Ctrl + H

---

## 📋 Checklist

- [ ] `.htaccess` foi feito upload para `/public/`
- [ ] Permissões estão como `644`
- [ ] Teste `/ping` retorna JSON
- [ ] Teste `/status` retorna JSON
- [ ] Teste `/health` retorna JSON
- [ ] Suporte do servidor confirmou `mod_rewrite` ativo

---

**Depois confirme comigo testando as rotas! 🚀**
