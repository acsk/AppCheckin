# ✅ CHECKLIST DE DEPLOYMENT - Servidor Compartilhado

## 📋 PRÉ-REQUISITOS
- [ ] Acesso FTP/SFTP ao servidor
- [ ] Acesso SSH/Terminal ao servidor
- [ ] Credenciais do banco de dados MySQL
- [ ] Domínio apontado para /public_html
- [ ] PHP 8.1+ instalado
- [ ] Composer instalado no servidor

## 🚀 PASSO 1: UPLOAD DOS ARQUIVOS
- [ ] Conectar via FTP/SFTP
- [ ] Navegar até `/public_html`
- [ ] Fazer upload de toda a pasta `api` (excluir: vendor, .git, node_modules)
- [ ] Verificar se `/public/` existe

**Via SCP (SSH):**
```bash
scp -r . usuario@api.appcheckin.com.br:/public_html/
```

## 🔧 PASSO 2: CRIAR .env
- [ ] Conectar via SSH
- [ ] `cd /public_html`
- [ ] `cp .env.example .env` (ou use o .env.production.example)
- [ ] `nano .env` e editar as variáveis
- [ ] Salvar (Ctrl+X, Y, Enter)

**Variáveis críticas:**
```
DB_HOST=localhost
DB_NAME=u304177849_api
DB_USER=u304177849_api
DB_PASS=+DEEJ&7t
JWT_SECRET=<gerar com openssl>
APP_URL=https://api.appcheckin.com.br
```

## 📦 PASSO 3: INSTALAR DEPENDÊNCIAS
- [ ] No servidor: `cd /public_html`
- [ ] Executar: `composer install --no-dev --optimize-autoloader`
- [ ] Esperar conclusão (2-5 min)
- [ ] Verificar se `/vendor` foi criado

## 🔐 PASSO 4: PERMISSÕES
```bash
chmod 755 public
chmod 755 app
chmod 755 config
chmod 755 database
chmod 644 .env
```
- [ ] Executar no servidor
- [ ] Não deve dar erro

## 🗄️ PASSO 5: MIGRATIONS (se banco está vazio)
**Via phpMyAdmin ou SSH:**
```bash
mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/000_init_migrations.sql
mysql -h localhost -u u304177849_api -p u304177849_api < database/migrations/001_create_tables.sql
# ... etc
```
- [ ] Todas as migrations executadas
- [ ] Sem erros de DEFINER ✅ (agora usando PHP)
- [ ] Tabelas criadas no banco

## 🌐 PASSO 6: .htaccess (Slim Framework)
**Criar `/public/.htaccess`:**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```
- [ ] Arquivo criado
- [ ] mod_rewrite habilitado

## ✅ PASSO 7: TESTE
- [ ] Abrir: `https://api.appcheckin.com.br/status`
- [ ] Deve receber JSON com status
- [ ] Logs em `/logs/` ou `/var/log/appcheckin/`

## 🐛 TROUBLESHOOTING

### Erro 404
- [ ] .htaccess está em `/public/`?
- [ ] mod_rewrite está habilitado?
- [ ] APP_URL está correto no .env?

### Erro de conexão com banco
- [ ] Credenciais DB_* estão corretas?
- [ ] Banco existe?
- [ ] Usuário tem permissões?

### Erro 500
- [ ] Verificar logs: `tail -f logs/error.log`
- [ ] .env tem todas as variáveis?
- [ ] Permissions corretas (755)?

### Función MySQL não encontrada
- [ ] ✅ RESOLVIDO! Agora usando PHP (TenantService)
- [ ] Não precisa criar a função no banco

## 📞 SUPORTE
Se tiver problemas, verifique:
1. Logs da aplicação em `/logs/`
2. Logs do Apache: `/var/log/apache2/error.log`
3. Credenciais do banco de dados
4. Domínio apontando corretamente
5. SSL certificado válido (HTTPS)

---
**Última atualização:** 19 de janeiro de 2026
**Versão API:** 1.0
**Framework:** Slim Framework 4
**PHP:** 8.1+
