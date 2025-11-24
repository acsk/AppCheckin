# 🧪 Testes da API - App Check-in

Este arquivo contém exemplos de requisições para testar todos os endpoints da API.

## 📋 Pré-requisitos

- API rodando em http://localhost:8080
- Cliente HTTP (curl, Postman, Insomnia, etc.)

---

## 🔓 Endpoints Públicos

### 1. Verificar se API está funcionando

```bash
curl http://localhost:8080
```

**Resposta esperada:**
```json
{
  "message": "API Check-in - funcionando!",
  "version": "1.0.0"
}
```

---

### 2. Registrar novo usuário

```bash
curl -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "senha": "senha123"
  }'
```

**Resposta esperada:**
```json
{
  "message": "Usuário criado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 2,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "created_at": "2025-11-23 10:30:00",
    "updated_at": "2025-11-23 10:30:00"
  }
}
```

---

### 3. Fazer Login

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@exemplo.com",
    "senha": "password123"
  }'
```

**Resposta esperada:**
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "nome": "Usuário Teste",
    "email": "teste@exemplo.com",
    "created_at": "2025-11-23 10:00:00",
    "updated_at": "2025-11-23 10:00:00"
  }
}
```

**⚠️ Importante:** Copie o token recebido para usar nas próximas requisições!

---

## 🔒 Endpoints Protegidos

**Nota:** Substitua `SEU_TOKEN_AQUI` pelo token JWT recebido no login.

### 4. Obter dados do usuário logado

```bash
curl http://localhost:8080/me \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "id": 1,
  "nome": "Usuário Teste",
  "email": "teste@exemplo.com",
  "created_at": "2025-11-23 10:00:00",
  "updated_at": "2025-11-23 10:00:00"
}
```

---

### 5. Atualizar perfil do usuário

```bash
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João Silva Atualizado",
    "email": "novoemail@exemplo.com"
  }'
```

**Resposta esperada:**
```json
{
  "message": "Usuário atualizado com sucesso",
  "user": {
    "id": 1,
    "nome": "João Silva Atualizado",
    "email": "novoemail@exemplo.com",
    "created_at": "2025-11-23 10:00:00",
    "updated_at": "2025-11-23 11:00:00"
  }
}
```

---

### 6. Listar dias disponíveis

```bash
curl http://localhost:8080/dias \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "dias": [
    {
      "id": 1,
      "data": "2025-11-24",
      "ativo": 1,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    },
    {
      "id": 2,
      "data": "2025-11-25",
      "ativo": 1,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    }
  ]
}
```

---

### 7. Listar horários de um dia específico

```bash
curl http://localhost:8080/dias/1/horarios \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "dia": {
    "id": 1,
    "data": "2025-11-24",
    "ativo": 1,
    "created_at": "2025-11-23 10:00:00",
    "updated_at": "2025-11-23 10:00:00"
  },
  "horarios": [
    {
      "id": 1,
      "dia_id": 1,
      "hora": "08:00:00",
      "vagas": 10,
      "ativo": 1,
      "checkins_count": 2,
      "vagas_disponiveis": 8,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    },
    {
      "id": 2,
      "dia_id": 1,
      "hora": "10:00:00",
      "vagas": 10,
      "ativo": 1,
      "checkins_count": 0,
      "vagas_disponiveis": 10,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    }
  ]
}
```

---

### 8. Realizar check-in

```bash
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "horario_id": 1
  }'
```

**Resposta esperada:**
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 1,
    "usuario_id": 1,
    "horario_id": 1,
    "data_checkin": "2025-11-23 11:30:00",
    "hora": "08:00:00",
    "data": "2025-11-24",
    "created_at": "2025-11-23 11:30:00",
    "updated_at": "2025-11-23 11:30:00"
  }
}
```

---

### 9. Listar meus check-ins

```bash
curl http://localhost:8080/me/checkins \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "checkins": [
    {
      "id": 1,
      "usuario_id": 1,
      "horario_id": 1,
      "data_checkin": "2025-11-23 11:30:00",
      "hora": "08:00:00",
      "data": "2025-11-24",
      "data_hora_completa": "2025-11-24 08:00:00",
      "created_at": "2025-11-23 11:30:00",
      "updated_at": "2025-11-23 11:30:00"
    }
  ]
}
```

---

### 10. Cancelar check-in

```bash
curl -X DELETE http://localhost:8080/checkin/1 \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "message": "Check-in cancelado com sucesso"
}
```

---

## 🎓 Endpoints de Turmas

### 11. Listar todas as turmas

```bash
curl http://localhost:8080/turmas \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "turmas_por_dia": [
    {
      "data": "2025-11-24",
      "dia_ativo": true,
      "turmas": [
        {
          "id": 1,
          "hora": "08:00:00",
          "horario_inicio": "08:00:00",
          "horario_fim": "09:00:00",
          "limite_alunos": 30,
          "alunos_registrados": 15,
          "vagas_disponiveis": 15,
          "percentual_ocupacao": 50,
          "ativo": true
        }
      ]
    }
  ],
  "total_turmas": 3
}
```

---

### 12. Listar turmas do dia atual

```bash
curl http://localhost:8080/turmas/hoje \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "data": "2025-11-23",
  "turmas": [
    {
      "id": 1,
      "hora": "08:00:00",
      "horario_inicio": "08:00:00",
      "horario_fim": "09:00:00",
      "limite_alunos": 30,
      "alunos_registrados": 15,
      "vagas_disponiveis": 15,
      "percentual_ocupacao": 50,
      "ativo": true,
      "usuario_registrado": true
    },
    {
      "id": 2,
      "hora": "10:00:00",
      "horario_inicio": "10:00:00",
      "horario_fim": "11:00:00",
      "limite_alunos": 25,
      "alunos_registrados": 10,
      "vagas_disponiveis": 15,
      "percentual_ocupacao": 40,
      "ativo": true,
      "usuario_registrado": false
    }
  ],
  "total_turmas": 2
}
```

**📌 Nota:** O campo `usuario_registrado` indica se o usuário logado já está registrado naquela turma específica. Isso impede que ele se registre em múltiplas turmas do mesmo dia.

---

### 13. Listar alunos de uma turma

```bash
curl http://localhost:8080/turmas/1/alunos \
  -H "Authorization: Bearer SEU_TOKEN_AQUI"
```

**Resposta esperada:**
```json
{
  "turma": {
    "id": 1,
    "data": "2025-11-23",
    "hora": "08:00:00",
    "horario_inicio": "08:00:00",
    "horario_fim": "09:00:00",
    "limite_alunos": 30,
    "alunos_registrados": 2,
    "vagas_disponiveis": 28
  },
  "alunos": [
    {
      "id": 1,
      "nome": "João Silva",
      "email": "joao@exemplo.com",
      "data_checkin": "2025-11-23 08:05:00",
      "created_at": "2025-11-23 08:05:00"
    },
    {
      "id": 2,
      "nome": "Maria Santos",
      "email": "maria@exemplo.com",
      "data_checkin": "2025-11-23 08:10:00",
      "created_at": "2025-11-23 08:10:00"
    }
  ],
  "total_alunos": 2
}
```

---

## ❌ Testes de Erros

### Erro 401 - Token não fornecido

```bash
curl http://localhost:8080/me
```

**Resposta:**
```json
{
  "error": "Token não fornecido"
}
```

---

### Erro 401 - Token inválido

```bash
curl http://localhost:8080/me \
  -H "Authorization: Bearer token_invalido"
```

**Resposta:**
```json
{
  "error": "Token inválido ou expirado"
}
```

---

### Erro 422 - Dados inválidos (Login)

```bash
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "emailinvalido",
    "senha": ""
  }'
```

**Resposta:**
```json
{
  "error": "Email e senha são obrigatórios"
}
```

---

### Erro 400 - Horário sem vagas

```bash
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "horario_id": 999
  }'
```

**Resposta:**
```json
{
  "error": "Horário não encontrado"
}
```

---

### Erro 400 - Check-in duplicado

```bash
# Fazer check-in duas vezes no mesmo horário
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json" \
  -d '{
    "horario_id": 1
  }'
```

**Resposta:**
```json
{
  "error": "Você já tem check-in neste horário"
}
```

---

## 📝 Postman Collection

Para importar no Postman, crie uma collection com:

**Variáveis de ambiente:**
- `baseUrl`: http://localhost:8080
- `token`: (será preenchido após login)

**Requisições:**

1. **Health Check**
   - GET `{{baseUrl}}/`

2. **Register**
   - POST `{{baseUrl}}/auth/register`
   - Body: JSON com nome, email, senha
   - Test Script: `pm.environment.set("token", pm.response.json().token);`

3. **Login**
   - POST `{{baseUrl}}/auth/login`
   - Body: JSON com email, senha
   - Test Script: `pm.environment.set("token", pm.response.json().token);`

4. **Get User**
   - GET `{{baseUrl}}/me`
   - Header: `Authorization: Bearer {{token}}`

5. **Update User**
   - PUT `{{baseUrl}}/me`
   - Header: `Authorization: Bearer {{token}}`
   - Body: JSON com dados a atualizar

6. **Get Dias**
   - GET `{{baseUrl}}/dias`
   - Header: `Authorization: Bearer {{token}}`

7. **Get Horarios**
   - GET `{{baseUrl}}/dias/1/horarios`
   - Header: `Authorization: Bearer {{token}}`

8. **Create Checkin**
   - POST `{{baseUrl}}/checkin`
   - Header: `Authorization: Bearer {{token}}`
   - Body: JSON com horario_id

9. **My Checkins**
   - GET `{{baseUrl}}/me/checkins`
   - Header: `Authorization: Bearer {{token}}`

10. **Cancel Checkin**
    - DELETE `{{baseUrl}}/checkin/1`
    - Header: `Authorization: Bearer {{token}}`

---

## 🔍 Verificar Dados no MySQL

```bash
# Acessar MySQL
docker-compose exec mysql mysql -uroot -proot appcheckin

# Listar usuários
SELECT * FROM usuarios;

# Listar dias
SELECT * FROM dias;

# Listar horários
SELECT * FROM horarios;

# Listar check-ins
SELECT * FROM checkins;

# Ver check-ins com detalhes
SELECT 
    c.id,
    u.nome as usuario,
    d.data,
    h.hora,
    c.data_checkin
FROM checkins c
INNER JOIN usuarios u ON c.usuario_id = u.id
INNER JOIN horarios h ON c.horario_id = h.id
INNER JOIN dias d ON h.dia_id = d.id;
```

---

## 📊 Fluxo de Teste Completo

```bash
# 1. Verificar API
curl http://localhost:8080

# 2. Registrar usuário
TOKEN=$(curl -s -X POST http://localhost:8080/auth/register \
  -H "Content-Type: application/json" \
  -d '{"nome":"Test User","email":"test@test.com","senha":"123456"}' \
  | grep -o '"token":"[^"]*' | cut -d'"' -f4)

echo "Token: $TOKEN"

# 3. Listar dias
curl http://localhost:8080/dias \
  -H "Authorization: Bearer $TOKEN"

# 4. Listar horários do primeiro dia
curl http://localhost:8080/dias/1/horarios \
  -H "Authorization: Bearer $TOKEN"

# 5. Fazer check-in
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"horario_id": 1}'

# 6. Ver meus check-ins
curl http://localhost:8080/me/checkins \
  -H "Authorization: Bearer $TOKEN"
```

---

## ✅ Checklist de Testes

- [ ] API responde no /
- [ ] Registro de usuário funciona
- [ ] Login funciona e retorna token
- [ ] Token é validado corretamente
- [ ] Listar dias funciona
- [ ] Listar horários funciona
- [ ] Check-in é criado corretamente
- [ ] Não permite check-in duplicado
- [ ] Histórico mostra check-ins
- [ ] Cancelamento funciona
- [ ] Atualização de perfil funciona
- [ ] Listar todas as turmas funciona
- [ ] Listar turmas do dia funciona
- [ ] Campo `usuario_registrado` identifica corretamente a turma do usuário
- [ ] Listar alunos de uma turma funciona
- [ ] Erros retornam status correto
- [ ] CORS permite requisições do frontend

---

Bons testes! 🧪🚀
