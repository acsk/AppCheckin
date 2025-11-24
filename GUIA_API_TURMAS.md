# 📚 Guia de Uso - API de Gestão de Turmas

## 🎯 Novos Endpoints Implementados

### 1. Listar Todas as Turmas - `GET /turmas`

**Descrição:** Retorna todas as turmas organizadas por dia com estatísticas completas de ocupação.

**Headers:**
```
Authorization: Bearer {seu_token_jwt}
```

**Resposta de Sucesso (200):**
```json
{
  "turmas_por_dia": [
    {
      "data": "2025-11-24",
      "dia_ativo": true,
      "turmas": [
        {
          "id": 147,
          "hora": "06:00:00",
          "horario_inicio": "06:00:00",
          "horario_fim": "07:00:00",
          "limite_alunos": 30,
          "alunos_registrados": 5,
          "vagas_disponiveis": 25,
          "percentual_ocupacao": 16.67,
          "ativo": true
        },
        {
          "id": 154,
          "hora": "07:00:00",
          "horario_inicio": "07:00:00",
          "horario_fim": "08:00:00",
          "limite_alunos": 30,
          "alunos_registrados": 12,
          "vagas_disponiveis": 18,
          "percentual_ocupacao": 40.0,
          "ativo": true
        }
        // ... mais turmas
      ]
    },
    {
      "data": "2025-11-25",
      "dia_ativo": true,
      "turmas": [
        // turmas do dia 25/11
      ]
    }
  ],
  "total_turmas": 49
}
```

**Exemplo de Uso:**
```bash
curl http://localhost:8080/turmas \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**Casos de Uso:**
- 📊 Dashboard administrativo
- 📈 Visualização de ocupação geral
- 📅 Planejamento de capacidade
- 🔍 Identificar turmas lotadas ou vazias

---

### 2. Listar Alunos de uma Turma - `GET /turmas/{id}/alunos`

**Descrição:** Retorna lista completa de alunos que fizeram check-in em uma turma específica.

**Parâmetros:**
- `id` (path): ID da turma/horário

**Headers:**
```
Authorization: Bearer {seu_token_jwt}
```

**Resposta de Sucesso (200):**
```json
{
  "turma": {
    "id": 147,
    "data": "2025-11-24",
    "hora": "06:00:00",
    "horario_inicio": "06:00:00",
    "horario_fim": "07:00:00",
    "limite_alunos": 30,
    "alunos_registrados": 2,
    "vagas_disponiveis": 28
  },
  "alunos": [
    {
      "id": 4,
      "nome": "Aluno Novo",
      "email": "aluno@novo.com",
      "data_checkin": "2025-11-24 06:05:00",
      "created_at": "2025-11-23 17:33:51"
    },
    {
      "id": 5,
      "nome": "João Silva",
      "email": "joao@exemplo.com",
      "data_checkin": "2025-11-24 06:08:30",
      "created_at": "2025-11-23 17:35:22"
    }
  ],
  "total_alunos": 2
}
```

**Resposta de Erro (404):**
```json
{
  "error": "Horário/Turma não encontrado"
}
```

**Exemplo de Uso:**
```bash
curl http://localhost:8080/turmas/147/alunos \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

**Casos de Uso:**
- 📝 Chamada de presença
- ✅ Verificação de check-ins
- 📊 Relatórios de frequência
- 👥 Lista de participantes da aula
- 🕐 Verificar horário que cada aluno chegou

---

## 🔄 Fluxo de Uso Completo

### Cenário: Gestor verificando ocupação das turmas

```bash
# 1. Login
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "gestor@escola.com", "senha": "senha123"}'

# Resposta contém o token JWT

# 2. Listar todas as turmas para ver ocupação
curl http://localhost:8080/turmas \
  -H "Authorization: Bearer {TOKEN}"

# 3. Escolher uma turma específica e ver os alunos
curl http://localhost:8080/turmas/147/alunos \
  -H "Authorization: Bearer {TOKEN}"
```

### Cenário: Aluno fazendo check-in

```bash
# 1. Ver dias disponíveis
curl http://localhost:8080/dias \
  -H "Authorization: Bearer {TOKEN}"

# 2. Ver horários de um dia específico
curl http://localhost:8080/dias/15/horarios \
  -H "Authorization: Bearer {TOKEN}"

# 3. Fazer check-in em um horário
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"horario_id": 147}'
```

---

## 📊 Informações Retornadas

### No endpoint `/turmas`:

| Campo | Descrição |
|-------|-----------|
| `id` | ID único da turma |
| `hora` | Horário de referência |
| `horario_inicio` | Início da aula |
| `horario_fim` | Fim da aula |
| `limite_alunos` | Capacidade máxima |
| `alunos_registrados` | Quantos fizeram check-in |
| `vagas_disponiveis` | Vagas restantes |
| `percentual_ocupacao` | % de ocupação (0-100) |
| `ativo` | Se a turma está ativa |

### No endpoint `/turmas/{id}/alunos`:

**Informações da Turma:**
- Dados completos do horário
- Estatísticas de ocupação

**Informações dos Alunos:**
- ID, nome e email
- `data_checkin`: Momento exato do check-in
- `created_at`: Quando o usuário foi criado

---

## 🧪 Testes Realizados

### ✅ Teste 1: Listar turmas vazias
```bash
curl http://localhost:8080/turmas -H "Authorization: Bearer {TOKEN}"
```
**Resultado:** Todas as turmas com `alunos_registrados: 0`

### ✅ Teste 2: Fazer check-ins e verificar atualização
```bash
# Fazer check-in
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer {TOKEN}" \
  -d '{"horario_id": 147}'

# Verificar turma
curl http://localhost:8080/turmas/147/alunos \
  -H "Authorization: Bearer {TOKEN}"
```
**Resultado:** Aluno aparece na lista com horário exato do check-in

### ✅ Teste 3: Percentual de ocupação
```bash
# Após 5 check-ins em turma de 30 vagas
curl http://localhost:8080/turmas -H "Authorization: Bearer {TOKEN}"
```
**Resultado:** `percentual_ocupacao: 16.67` (5/30 * 100)

---

## 💡 Dicas de Implementação Frontend

### Dashboard de Turmas
```javascript
// Buscar todas as turmas
fetch('http://localhost:8080/turmas', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  data.turmas_por_dia.forEach(dia => {
    console.log(`Dia: ${dia.data}`);
    dia.turmas.forEach(turma => {
      console.log(`  ${turma.hora}: ${turma.alunos_registrados}/${turma.limite_alunos} (${turma.percentual_ocupacao}%)`);
    });
  });
});
```

### Lista de Alunos
```javascript
// Buscar alunos de uma turma
fetch('http://localhost:8080/turmas/147/alunos', {
  headers: { 'Authorization': `Bearer ${token}` }
})
.then(res => res.json())
.then(data => {
  console.log(`Turma: ${data.turma.hora} - ${data.turma.data}`);
  console.log(`Total de alunos: ${data.total_alunos}`);
  data.alunos.forEach(aluno => {
    console.log(`- ${aluno.nome} (${aluno.data_checkin})`);
  });
});
```

---

## 🔐 Autenticação

Todos os endpoints de gestão requerem autenticação JWT. O token deve ser incluído no header:

```
Authorization: Bearer {seu_token_aqui}
```

Para obter o token, faça login em:
```bash
POST /auth/login
```

---

## 📌 Resumo dos Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/turmas` | Lista todas as turmas com estatísticas |
| GET | `/turmas/{id}/alunos` | Lista alunos de uma turma |
| GET | `/dias` | Lista dias disponíveis |
| GET | `/dias/{id}/horarios` | Horários de um dia com disponibilidade |
| POST | `/checkin` | Fazer check-in |
| GET | `/me/checkins` | Histórico do usuário |

---

**Última Atualização:** 23/11/2025
