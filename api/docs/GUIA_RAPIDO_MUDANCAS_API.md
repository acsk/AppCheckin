# 📋 Guia Rápido - Mudanças de API (Antes vs Depois)

## 🔄 Mudanças de Endpoints

### 1. Listar Turmas de um Dia

#### GET /admin/dias/{id}/horarios

**Antes** - Chamava HorarioModel:
```bash
GET /admin/dias/18/horarios
```

**Depois** - Chama TurmaModel (mesma rota, dados diferentes):
```bash
GET /admin/dias/18/horarios
```

**Resposta Antes** (campos incompletos):
```json
{
  "horarios": [
    {
      "id": 1,
      "hora": "05:00",
      "horario_inicio": "05:00",
      "horario_fim": "06:00",
      "limite_alunos": 20,
      "alunos_registrados": 5,
      "tolerancia_minutos": 10
    }
  ]
}
```

**Resposta Depois** (campos completos + turma info):
```json
{
  "turmas": [
    {
      "id": 1,
      "nome": "Natação - 05:00",
      "professor_nome": "Carlos",
      "professor_id": 5,
      "modalidade_nome": "Natação",
      "modalidade_icone": "🏊",
      "modalidade_cor": "#1E90FF",
      "horario_inicio": "05:00",
      "horario_fim": "06:00",
      "limite_alunos": 20,
      "alunos_registrados": 5,
      "vagas_disponiveis": 15,
      "tolerancia_minutos": 10,
      "tolerancia_antes_minutos": 480,
      "ativo": true
    }
  ]
}
```

---

### 2. Turmas por Data

#### GET /mobile/horarios?data=2026-01-20

**Antes** - Retornava horarios:
```json
{
  "turmas": [
    {
      "id": "h_123",
      "hora": "05:00"
    }
  ]
}
```

**Depois** - Retorna turmas completas:
```json
{
  "turmas": [
    {
      "id": 1,
      "nome": "Natação - 05:00",
      "horario_inicio": "05:00",
      "horario_fim": "06:00",
      "professor_nome": "Carlos",
      "modalidade_nome": "Natação",
      "limite_alunos": 20,
      "alunos_registrados": 5,
      "vagas_disponiveis": 15,
      "percentual_ocupacao": 25,
      "tolerancia_minutos": 10,
      "tolerancia_antes_minutos": 480,
      "ativo": true
    }
  ]
}
```

---

## 📤 Mudanças nos Requests (POST/PUT)

### 3. Criar Check-in

#### POST /checkin

**Antes** - Esperava `horario_id`:
```json
{
  "horario_id": 123
}
```

**Depois** - Espera `turma_id`:
```json
{
  "turma_id": 1
}
```

**Resposta Antes**:
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 1,
    "usuario_id": 5,
    "horario_id": 123,
    "data_checkin": "2026-01-22 06:15:00"
  }
}
```

**Resposta Depois**:
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 1,
    "usuario_id": 5,
    "turma_id": 1,
    "data_checkin": "2026-01-22 06:15:00"
  }
}
```

---

### 4. Registrar Check-in por Admin

#### POST /admin/checkins/registrar

**Antes**:
```json
{
  "usuario_id": 5,
  "horario_id": 123
}
```

**Depois**:
```json
{
  "usuario_id": 5,
  "turma_id": 1
}
```

---

## ⚠️ Erros Migração

### Erro 422: `turma_id é obrigatório`

Se você receber:
```json
{
  "error": "turma_id é obrigatório"
}
```

**Solução**: Use `turma_id` em vez de `horario_id`:
```json
{
  "turma_id": 1  // ✅ Correto
}
```

---

## 🔍 Como Encontrar `turma_id`

### Opção 1: Listar Turmas
```bash
GET /admin/turmas
```

Response inclui `id` de cada turma.

### Opção 2: Listar Turmas de um Dia
```bash
GET /admin/dias/18/horarios
```

Response tem `turmas[].id`.

### Opção 3: Buscar Turma por ID
```bash
GET /admin/turmas/1
```

---

## 📊 Campos de Tolerância

### Antes (Ignorados)
```json
{
  "nome": "Turma A",
  "tolerancia_minutos": 15,       // ❌ Ignorado
  "tolerancia_antes_minutos": 600 // ❌ Ignorado
}
```

### Depois (Salvos)
```json
{
  "nome": "Turma A",
  "tolerancia_minutos": 15,       // ✅ Salvo
  "tolerancia_antes_minutos": 600 // ✅ Salvo
}
```

---

## 🚀 Resumo Rápido

| O quê | Antes | Depois |
|------|-------|--------|
| **Modelo usado** | HorarioModel | ✅ TurmaModel |
| **Campo para check-in** | `horario_id` | ✅ `turma_id` |
| **Tolerância antes salva?** | ❌ NÃO | ✅ SIM |
| **Dados retornados** | Incompletos | ✅ Completos |
| **Fonte de verdade** | Confusa (2 tabelas) | ✅ Uma (turmas) |

---

**Para suporte**: Consulte `docs/CONSOLIDACAO_COMPLETA_HORARIOS.md`
