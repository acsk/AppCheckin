# ✅ Sistema de Check-in por Turmas

## 📚 Resumo do Sistema

O sistema foi atualizado para funcionar como **check-in de presença em aulas/turmas** com as seguintes características:

### 🎯 Características Principais

1. **Turmas por Horário** 
   - Cada turma tem duração de 1 hora
   - Horários disponíveis:
     - **Manhã**: 06h, 07h, 08h
     - **Tarde/Noite**: 16h, 17h, 18h, 19h

2. **Limite de Alunos**
   - Cada turma tem limite de **30 alunos**
   - Sistema mostra vagas disponíveis em tempo real

3. **Tolerância de Check-in**
   - **10 minutos** de tolerância após início da aula
   - Não permite check-in antes do horário de início
   - Não permite check-in após o limite de tolerância
   - Registra o **momento exato** que o aluno fez check-in

4. **Endpoint de Disponibilidade**
   - Mostra dias e horários disponíveis
   - Indica se pode fazer check-in no momento
   - Exibe motivo quando check-in não é permitido

## 🗄️ Estrutura do Banco de Dados

### Tabela: `horarios`

```sql
CREATE TABLE horarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia_id INT NOT NULL,
    hora TIME NOT NULL,
    horario_inicio TIME NOT NULL,      -- Início da aula
    horario_fim TIME NOT NULL,          -- Fim da aula
    limite_alunos INT NOT NULL,         -- Máximo de alunos por turma
    tolerancia_minutos INT NOT NULL,    -- Minutos de tolerância para check-in
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dia_id) REFERENCES dias(id)
);
```

### Tabela: `checkins`

```sql
CREATE TABLE checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    horario_id INT NOT NULL,
    data_checkin DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,  -- Momento exato do check-in
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (horario_id) REFERENCES horarios(id),
    UNIQUE KEY unique_usuario_horario (usuario_id, horario_id)
);
```

## 🔧 Validações Implementadas

### 1. Validação de Horário (Model: Horario.php)

O método `podeRealizarCheckin()` valida:

```php
// ✅ Verifica se horário existe e está ativo
// ✅ Verifica se há vagas disponíveis
// ✅ Verifica se está dentro do período permitido:
//    - Não antes do início da aula
//    - Não após tolerância de 10 minutos
```

### 2. Validação no Controller (CheckinController.php)

```php
// ✅ Verifica se usuário já tem check-in neste horário
// ✅ Usa podeRealizarCheckin() para validar todas as regras
// ✅ Registra o momento exato do check-in
```

## 📡 API Endpoints Atualizados

### GET `/dias/{id}/horarios`

Retorna informações completas sobre disponibilidade:

**Exemplo de Resposta:**

```json
{
  "dia": {
    "id": 15,
    "data": "2025-11-24",
    "ativo": 1
  },
  "horarios": [
    {
      "id": 98,
      "hora": "06:00:00",
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "limite_alunos": 30,
      "alunos_registrados": 0,
      "vagas_disponiveis": 30,
      "tolerancia_minutos": 10,
      "pode_fazer_checkin": false,
      "motivo_indisponibilidade": "Check-in só pode ser feito a partir do horário de início da aula",
      "ativo": true
    },
    {
      "id": 119,
      "hora": "16:00:00",
      "horario_inicio": "16:00:00",
      "horario_fim": "17:00:00",
      "limite_alunos": 30,
      "alunos_registrados": 15,
      "vagas_disponiveis": 15,
      "tolerancia_minutos": 10,
      "pode_fazer_checkin": true,
      "motivo_indisponibilidade": null,
      "ativo": true
    }
  ]
}
```

### GET `/turmas`

**Novo!** Lista todas as turmas com estatísticas de ocupação:

**Exemplo de Resposta:**

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
      ]
    }
  ],
  "total_turmas": 49
}
```

### GET `/turmas/{id}/alunos`

**Novo!** Lista todos os alunos que fizeram check-in em uma turma específica:

**Request:**
```bash
GET /turmas/147/alunos
Authorization: Bearer {TOKEN}
```

**Exemplo de Resposta:**

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

### POST `/checkin`

Realiza check-in com validação completa:

**Request:**
```json
{
  "horario_id": 119
}
```

**Respostas Possíveis:**

✅ **Sucesso (201):**
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 1,
    "usuario_id": 4,
    "horario_id": 119,
    "data_checkin": "2025-11-24 16:05:30"
  }
}
```

❌ **Erro - Antes do horário (400):**
```json
{
  "error": "Check-in só pode ser feito a partir do horário de início da aula"
}
```

❌ **Erro - Após tolerância (400):**
```json
{
  "error": "Check-in não permitido. Prazo limite: 10 minutos após o início"
}
```

❌ **Erro - Turma lotada (400):**
```json
{
  "error": "Turma lotada"
}
```

❌ **Erro - Já tem check-in (400):**
```json
{
  "error": "Você já tem check-in neste horário"
}
```

## 🧪 Testes Realizados

### 1. Listar Dias Disponíveis ✅
```bash
curl http://localhost:8080/dias \
  -H "Authorization: Bearer {TOKEN}"
# Retorna 7 dias
```

### 2. Ver Horários e Disponibilidade ✅
```bash
curl http://localhost:8080/dias/15/horarios \
  -H "Authorization: Bearer {TOKEN}"
# Retorna 7 turmas (06h, 07h, 08h, 16h, 17h, 18h, 19h)
# Mostra vagas disponíveis e se pode fazer check-in
```

### 3. Tentar Check-in Antes do Horário ✅
```bash
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer {TOKEN}" \
  -d '{"horario_id": 98}'
# Retorna erro: "Check-in só pode ser feito a partir do horário de início da aula"
```

### 4. Check-in no Horário Correto ✅
```bash
# Durante a aula ou até 10 minutos após início
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer {TOKEN}" \
  -d '{"horario_id": 119}'
# Sucesso: registra check-in com timestamp exato
```

## 📊 Regras de Negócio

| Regra | Implementação |
|-------|---------------|
| Turmas de 1 hora | `horario_inicio` e `horario_fim` |
| Limite de 30 alunos | `limite_alunos` = 30 |
| 7 turmas por dia | Manhã (3) + Tarde/Noite (4) |
| Tolerância de 10 min | `tolerancia_minutos` = 10 |
| Registro do momento | `data_checkin` com timestamp |
| 1 check-in por turma | UNIQUE KEY `(usuario_id, horario_id)` |
| Controle de vagas | Count de checkins vs limite_alunos |

## 🚀 Como Usar

### 1. Ver Dias Disponíveis
```bash
GET /dias
```

### 2. Ver Horários de um Dia Específico
```bash
GET /dias/{id}/horarios
```

**Informações retornadas:**
- ✅ Horário de início e fim da aula
- ✅ Limite de alunos
- ✅ Quantos alunos já se registraram
- ✅ Vagas disponíveis
- ✅ Se pode fazer check-in AGORA
- ✅ Motivo caso não possa fazer check-in

### 3. Fazer Check-in
```bash
POST /checkin
{
  "horario_id": 119
}
```

**Sistema valida automaticamente:**
- ✅ Se é o horário correto (não antes, não muito depois)
- ✅ Se há vagas disponíveis
- ✅ Se aluno já tem check-in nesta turma
- ✅ Se a turma está ativa

## 📝 Arquivos Modificados

1. **Backend/database/migrations/002_adjust_horarios_for_classes.sql**
   - Adiciona campos: `horario_inicio`, `horario_fim`, `limite_alunos`, `tolerancia_minutos`
   - Remove campo obsoleto: `vagas`

2. **Backend/database/seeds/seed_data_v2.sql**
   - Dados de teste com 7 turmas por dia
   - 30 alunos por turma
   - 10 minutos de tolerância

3. **Backend/app/Models/Horario.php**
   - Método `podeRealizarCheckin()` - validação completa
   - Método `getAllWithStats()` - lista turmas com estatísticas
   - Método `getAlunosByHorarioId()` - lista alunos de uma turma
   - Atualizado para usar novos campos

4. **Backend/app/Controllers/CheckinController.php**
   - Validação de tolerância de horário
   - Registro de timestamp exato

5. **Backend/app/Controllers/DiaController.php**
   - Endpoint `horarios()` retorna disponibilidade em tempo real

6. **Backend/app/Controllers/TurmaController.php** (Novo!)
   - Endpoint `index()` - lista todas as turmas com estatísticas
   - Endpoint `alunos()` - lista alunos de uma turma específica

7. **Backend/routes/api.php**
   - Rotas: `GET /turmas` e `GET /turmas/{id}/alunos`

## 🆕 Novos Endpoints de Gestão

### 📊 GET `/turmas`

Lista todas as turmas organizadas por dia com:
- Número de alunos registrados
- Vagas disponíveis  
- Percentual de ocupação
- Informações completas do horário

**Caso de Uso:** Dashboard administrativo, visualização geral de ocupação

### 👥 GET `/turmas/{id}/alunos`

Lista todos os alunos que fizeram check-in em uma turma com:
- Dados do aluno (nome, email)
- Horário exato do check-in
- Informações da turma

**Caso de Uso:** Chamada de alunos, verificação de presença, relatórios

---

**Data de Implementação**: 23/11/2025  
**Status**: ✅ Totalmente funcional e testado
