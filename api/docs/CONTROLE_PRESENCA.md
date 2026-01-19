# Serviço de Controle de Presença

## Descrição

Este serviço permite que **professores e administradores** confirmem a presença dos alunos que fizeram check-in em uma turma.

O fluxo é:
1. **Aluno faz check-in** → Reserva sua vaga na aula
2. **Professor confirma presença** → Marca se o aluno realmente compareceu

## Estrutura do Banco de Dados

Foram adicionados 3 campos na tabela `checkins`:

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `presente` | TINYINT(1) NULL | NULL = não verificado, 1 = presente, 0 = falta |
| `presenca_confirmada_em` | DATETIME NULL | Data/hora da confirmação |
| `presenca_confirmada_por` | INT NULL | ID do usuário que confirmou |

## Endpoints

### 1. Listar Presenças de uma Turma

```
GET /admin/turmas/{turmaId}/presencas
```

**Resposta:**
```json
{
  "type": "success",
  "data": {
    "turma": {
      "id": 1,
      "nome": "CrossFit 07:00",
      "professor": "João Silva",
      "modalidade": "CrossFit",
      "horario_inicio": "07:00:00",
      "horario_fim": "08:00:00",
      "dia_data": "2024-01-15"
    },
    "checkins": [
      {
        "checkin_id": 123,
        "aluno": {
          "id": 10,
          "nome": "Maria Santos",
          "email": "maria@email.com"
        },
        "data_checkin": "2024-01-15 06:45:00",
        "presenca": {
          "status": "nao_verificado",
          "confirmada_em": null,
          "confirmada_por": null
        }
      }
    ],
    "estatisticas": {
      "total_checkins": 15,
      "presentes": 12,
      "faltas": 1,
      "nao_verificados": 2,
      "percentual_presenca": 85.7
    }
  }
}
```

**Status de Presença:**
- `nao_verificado` → Check-in feito, mas presença ainda não confirmada
- `presente` → Aluno compareceu à aula
- `falta` → Aluno fez check-in mas não compareceu

---

### 2. Marcar Presença Individual

```
PATCH /admin/checkins/{checkinId}/presenca
```

**Body:**
```json
{
  "presente": true
}
```

ou para marcar falta:
```json
{
  "presente": false
}
```

**Resposta:**
```json
{
  "type": "success",
  "message": "Presença confirmada",
  "data": {
    "checkin_id": 123,
    "presente": true,
    "confirmado_em": "2024-01-15 08:05:00"
  }
}
```

---

### 3. Marcar Presença em Lote

```
POST /admin/turmas/{turmaId}/presencas/lote
```

**Marcar IDs específicos:**
```json
{
  "checkin_ids": [1, 2, 3, 4],
  "presente": true
}
```

**Marcar todos da turma:**
```json
{
  "marcar_todos": true,
  "presente": true
}
```

**Resposta:**
```json
{
  "type": "success",
  "message": "15 presença(s) confirmada(s)",
  "data": {
    "turma_id": 1,
    "atualizados": 15,
    "presente": true,
    "estatisticas": {
      "total_checkins": 15,
      "presentes": 15,
      "faltas": 0,
      "nao_verificados": 0
    }
  }
}
```

---

## Fluxo de Uso no Frontend

### Tela de Controle de Presença

1. Professor seleciona a turma
2. Chama `GET /admin/turmas/{turmaId}/presencas`
3. Exibe lista de alunos com status de presença
4. Professor pode:
   - Marcar individualmente cada aluno (PATCH)
   - Marcar todos como presentes (POST lote)
   - Marcar todos exceto alguns como falta

### Exemplo de Interface

```
┌─────────────────────────────────────────────────────────────┐
│ CrossFit 07:00 - Segunda 15/01                             │
│ Professor: João Silva                                       │
├─────────────────────────────────────────────────────────────┤
│ Estatísticas: 15 check-ins | 12 ✅ | 1 ❌ | 2 ⏳            │
├─────────────────────────────────────────────────────────────┤
│ [Marcar Todos Presentes] [Marcar Todos como Falta]         │
├─────────────────────────────────────────────────────────────┤
│ ☐ Maria Santos          06:45  ⏳ Não verificado   [✅][❌] │
│ ☐ João Almeida          06:50  ✅ Presente                  │
│ ☐ Ana Paula             06:52  ❌ Falta                     │
│ ☐ Pedro Costa           06:55  ⏳ Não verificado   [✅][❌] │
└─────────────────────────────────────────────────────────────┘
```

---

## Autenticação

Todos os endpoints requerem:
- Token JWT válido no header `Authorization: Bearer {token}`
- Usuário com role de **Admin** (role_id = 2) ou **Super Admin** (role_id = 3)
- Professores podem ter acesso se tiverem role de Admin

---

## Modelo de Dados (Model Methods)

```php
// Listar check-ins da turma para controle de presença
$checkins = $checkinModel->listarCheckinsTurma($turmaId, $tenantId);

// Marcar presença individual
$checkinModel->marcarPresenca($checkinId, $presente, $confirmadoPorId);

// Marcar presença em lote
$checkinModel->marcarPresencaEmLote($checkinIds, $presente, $confirmadoPorId);

// Estatísticas
$stats = $checkinModel->estatisticasPresencaTurma($turmaId);
```
