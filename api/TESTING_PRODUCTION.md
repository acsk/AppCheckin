# 🧪 TESTES DE PRODUÇÃO - AppCheckin API

## 🚀 Testes Rápidos

### 1️⃣ Status Básico (sem autenticação)
```bash
curl -s https://api.appcheckin.com.br/status | jq .
```
**Esperado:** Resposta JSON com status `ok`

---

### 2️⃣ Health Check (Banco de Dados)
```bash
curl -s https://api.appcheckin.com.br/health | jq .
```
**Esperado:** 
```json
{
  "status": "ok",
  "database": "connected"
}
```

---

### 3️⃣ Testar Login
```bash
curl -X POST https://api.appcheckin.com.br/auth/login \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "admin@appcheckin.com.br",
    "password": "sua_senha"
  }' | jq .
```

**Esperado:**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "usuario": {
    "id": 1,
    "nome": "Admin",
    "email": "admin@appcheckin.com.br"
  }
}
```

---

### 4️⃣ Usar Token para Requisição
Salve o token anterior e use:
```bash
TOKEN="seu_token_aqui"

curl -s -H "Authorization: Bearer $TOKEN" \
  https://api.appcheckin.com.br/usuario/perfil | jq .
```

**Esperado:** Dados do usuário logado

---

### 5️⃣ Listar Check-ins
```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  https://api.appcheckin.com.br/checkins | jq .
```

**Esperado:** Array com check-ins

---

### 6️⃣ Testar CORS
```bash
curl -s -i -H "Origin: https://appcheckin.com.br" \
  https://api.appcheckin.com.br/status
```

**Procure por:**
```
Access-Control-Allow-Origin: https://appcheckin.com.br
```

---

## 🔍 Verificar Logs em Produção

### Via SSH:
```bash
# Logs da aplicação
tail -f /var/log/appcheckin/error.log

# Logs do Apache
tail -f /var/log/apache2/error.log
tail -f /var/log/apache2/access.log

# Ou em servidor compartilhado (dentro de public_html)
tail -f logs/error.log
```

---

## 🐛 Troubleshooting

### ❌ "404 Not Found"
```bash
# Verificar se .htaccess está ativo
curl -s -I https://api.appcheckin.com.br/
# Deve ter: HTTP/2 200 (não 404)
```

### ❌ "502 Bad Gateway"
```bash
# Verificar PHP errors
php -r "phpinfo();"

# Verificar .env
cat /public_html/.env

# Reiniciar Apache
sudo systemctl restart apache2
```

### ❌ "500 Internal Server Error"
```bash
# Verificar logs
tail -50 /var/log/apache2/error.log

# Verificar permissões
ls -la /public_html/.env
ls -la /public_html/app
```

### ❌ "Banco de dados não conecta"
```bash
# Testar credenciais
mysql -h localhost -u u304177849_api -p

# Verificar .env
grep DB_ /public_html/.env
```

---

## ✅ Checklist Final

- [ ] `https://api.appcheckin.com.br/status` retorna JSON
- [ ] Login funciona e retorna token
- [ ] Requisições autenticadas funcionam
- [ ] CORS headers corretos
- [ ] HTTPS funciona (não HTTP)
- [ ] Logs não mostram erros
- [ ] Banco de dados conectado
- [ ] Permissões de arquivo corretas

---

## 📊 Monitorar Performance

### Ver requisições em tempo real:
```bash
tail -f /var/log/apache2/access.log | grep "api.appcheckin.com.br"
```

### Contar erros:
```bash
tail -100 /var/log/apache2/error.log | grep "ERROR"
```

### Usar tools online:
- https://httpie.io/cli (alternativa ao curl)
- https://www.postman.com/ (testar endpoints)
- https://uptimerobot.com/ (monitorar 24/7)

---

**Dúvidas?** Verifique os logs e o arquivo `.env` 🔍
