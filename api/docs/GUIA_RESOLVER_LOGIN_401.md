# 🔧 GUIA RÁPIDO: Resolver Erro 401 no Login

## 🎯 Problema
Frontend retorna `POST http://localhost:8080/auth/login 401 (Unauthorized)`

## ✅ Solução em 3 Passos

### Passo 1: Garantir que MySQL está rodando
```bash
docker-compose up -d mysql
docker-compose ps
```

### Passo 2: Criar usuários de teste
```bash
cd /Users/andrecabral/Projetos/AppCheckin/api
chmod +x scripts/criar_usuarios_teste.sh
bash scripts/criar_usuarios_teste.sh
```

**Credenciais geradas:**
```
Email: teste@example.com
Email admin: admin@example.com  
Email gerenciador: gerenciador@example.com
Senha (todos): senha123
```

### Passo 3: Testar endpoints
```bash
chmod +x scripts/testar_auth.sh
bash scripts/testar_auth.sh
```

---

## 🧪 Teste Manual com curl

### Registrar novo usuário
```bash
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Seu Nome",
    "email": "seu@email.com",
    "senha": "senha123"
  }'
```

### Fazer Login
```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "seu@email.com",
    "senha": "senha123"
  }'
```

**Resposta esperada (200):**
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJhbGciOiJIUzI1NiIs...",
  "user": {
    "id": 1,
    "nome": "Seu Nome",
    "email": "seu@email.com",
    "role_id": 1
  },
  "tenants": [],
  "requires_tenant_selection": false
}
```

---

## 🔍 Checklist de Diagnóstico

- [ ] MySQL está rodando: `docker-compose ps`
- [ ] Banco de dados existe: `docker-compose exec mysql mysql -u root -proot -e "SHOW DATABASES LIKE 'appcheckin'"`
- [ ] Usuários foram criados: `docker-compose exec mysql mysql -u root -proot appcheckin -e "SELECT COUNT(*) FROM usuarios"`
- [ ] API respondendo: `curl http://localhost:8080/health`
- [ ] Registrar novo usuário funciona
- [ ] Login funciona
- [ ] Token é retornado no login
- [ ] Frontend consegue salvar o token

---

## 🚨 Se Ainda Não Funcionar

### Erro: "Credenciais inválidas" (401)
- ✅ Usuário não existe → Criar via `/auth/register`
- ✅ Senha incorreta → Verificar se enviando corretamente
- ✅ Email errado → Confirmar email no banco

### Erro: "Email e senha são obrigatórios" (422)
- ✅ Frontend não está enviando dados no body
- ✅ Content-Type não é application/json
- ✅ Verificar headers da requisição

### Erro: Conexão recusada
- ✅ MySQL não está rodando
- ✅ Docker não está instalado
- ✅ Porta 3306 está em uso

---

## 📚 Documentação Relacionada

- [DIAGNOSTICO_ERRO_LOGIN_401.md](DIAGNOSTICO_ERRO_LOGIN_401.md) - Análise técnica detalhada
- [ANALISE_USO_HORARIO_MODEL.md](ANALISE_USO_HORARIO_MODEL.md) - Análise da tabela horarios
- [CONSOLIDACAO_COMPLETA_HORARIOS.md](CONSOLIDACAO_COMPLETA_HORARIOS.md) - Refatoração concluída

---

**Criado:** 20 de janeiro de 2026  
**Última atualização:** Agora  
**Status:** Pronto para Uso
