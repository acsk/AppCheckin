# 🚀 Guia de Deployment - api.appcheckin.com.br

## 1️⃣ Pré-requisitos no Servidor

```bash
# PHP 8.2+
php --version

# MySQL 5.7+
mysql --version

# Composer
composer --version
```

## 2️⃣ Clonar e Instalar Dependências

```bash
cd /var/www/html/appcheckin-api
git clone seu_repo.git .
composer install --no-dev --optimize-autoloader
```

## 3️⃣ Configurar Variáveis de Ambiente

### Opção A: Arquivo `.env`

```bash
# Copiar e editar o arquivo
cp .env.example .env

# Editar com suas variáveis
nano .env
```

**Variáveis essenciais:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.appcheckin.com.br

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u304177849_api
DB_USER=u304177849_api
DB_PASS=sua_senha_segura

# JWT - Gerar com:
# openssl rand -base64 64
JWT_SECRET=gerar_chave_segura_aqui
JWT_EXPIRATION=86400

# CORS
CORS_ALLOWED_ORIGINS=https://appcheckin.com.br,https://www.appcheckin.com.br,https://app.appcheckin.com.br

# Timezone
APP_TIMEZONE=America/Sao_Paulo

# Logs
LOG_LEVEL=error
LOG_PATH=/var/log/appcheckin
```

### Opção B: Variáveis de Sistema

Se usar container ou sem arquivo `.env`:

```bash
export APP_ENV=production
export DB_HOST=localhost
export DB_NAME=u304177849_api
export JWT_SECRET=$(openssl rand -base64 64)
```

## 4️⃣ Criar Estrutura do Banco

```bash
# Executar todas as migrations (na ordem)
mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/000_init_migrations.sql
mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/001_create_tables.sql
# ... continue com as outras migrations

# OU usar script (se disponível)
./scripts/run_migrations.sh
```

## 5️⃣ Permissões de Arquivo

```bash
# Definir proprietário
sudo chown -R www-data:www-data /var/www/html/appcheckin-api

# Definir permissões
chmod 755 -R /var/www/html/appcheckin-api
chmod 644 -R /var/www/html/appcheckin-api/public
chmod 777 /var/log/appcheckin  # Pasta de logs
```

## 6️⃣ Configurar Apache/Nginx

### Apache (.htaccess já incluído em `public/`)

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name api.appcheckin.com.br;

    ssl_certificate /etc/ssl/certs/api.appcheckin.com.br.crt;
    ssl_certificate_key /etc/ssl/private/api.appcheckin.com.br.key;

    root /var/www/html/appcheckin-api/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}

# Redirecionar HTTP para HTTPS
server {
    listen 80;
    server_name api.appcheckin.com.br;
    return 301 https://$server_name$request_uri;
}
```

## 7️⃣ SSL/HTTPS com Let's Encrypt

```bash
sudo certbot certonly --standalone -d api.appcheckin.com.br

# Renovação automática
sudo certbot renew --dry-run
```

## 8️⃣ Testar a API

```bash
# Teste básico
curl -i https://api.appcheckin.com.br/

# Com autenticação (quando implementado)
curl -X GET https://api.appcheckin.com.br/api/endpoint \
  -H "Authorization: Bearer SEU_TOKEN"
```

## 9️⃣ Monitorar Logs

```bash
# Logs da API
tail -f /var/log/appcheckin/api.log

# Logs do PHP-FPM
tail -f /var/log/php-fpm.log

# Logs do Nginx/Apache
tail -f /var/log/nginx/error.log
```

## 🔟 Cron Jobs (se necessário)

```bash
# Editar crontab
crontab -e

# Exemplo de job
0 2 * * * php /var/www/html/appcheckin-api/jobs/gerar_dias_anuais.php >> /var/log/appcheckin/cron.log 2>&1
```

## ✅ Checklist Final

- [ ] Banco de dados criado e migrado
- [ ] `.env` configurado com variáveis de produção
- [ ] SSL/HTTPS ativado
- [ ] Permissões de arquivo corretas
- [ ] Logs configurados
- [ ] API testada e respondendo
- [ ] CORS configurado para domínios permitidos
- [ ] JWT_SECRET seguro gerado
- [ ] Backups do banco configurados

## 🆘 Troubleshooting

### Erro 500 - "Internal Server Error"

```bash
# Verificar logs
tail -50 /var/log/appcheckin/api.log

# Verificar permissões
ls -la /var/log/appcheckin/
```

### Banco não conecta

```bash
# Testar conexão
mysql -h localhost -u u304177849_api -p u304177849_api

# Verificar se o usuário existe
mysql -u root -p -e "SELECT User, Host FROM mysql.user WHERE User='u304177849_api';"
```

### CORS bloqueado

Verificar se `CORS_ALLOWED_ORIGINS` está correto no `.env`

---

**Suporte**: andrecabral@appcheckin.com.br
