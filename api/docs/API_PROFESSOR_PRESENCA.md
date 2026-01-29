# API Professor - Controle de Presença

## Visão Geral

Esta documentação descreve os endpoints disponíveis para o controle de presença pelo professor no app mobile.

### Modelo de Papéis por Tenant

O sistema usa papéis **por tenant** via tabela `tenant_usuario_papel`. Isso significa que:

- Um usuário pode ser **PROFESSOR** no tenant A
- O mesmo usuário pode ser **ALUNO** no tenant B
- Um usuário pode ter **MÚLTIPLOS PAPÉIS** no mesmo tenant (ex: professor + admin)
- Evita duplicar conta/senha entre tenants
- Auditoria fica limpa: `confirmado_por_usuario_id` aponta para `usuarios.id` global

### Tabela `papeis`

| id | nome      | nivel | descrição                         |
|----|-----------|-------|-----------------------------------|
| 1  | aluno     | 10    | Aluno que faz check-in nas aulas  |
| 2  | professor | 50    | Professor que confirma presença   |
| 3  | admin     | 100   | Administrador do tenant           |

### Tabela `tenant_usuario_papel`

```sql
tenant_id  INT  -- FK para tenants
usuario_id INT  -- FK para usuarios (global)
papel_id   INT  -- FK para papeis
ativo      TINYINT(1)
```

**UNIQUE KEY**: `(tenant_id, usuario_id, papel_id)` - um usuário só pode ter cada papel uma vez por tenant

### Papéis de Acesso às Rotas de Presença

| papel_id | Nome      | Acesso                              |
|----------|-----------|-------------------------------------|
| 1        | aluno     | ❌ Sem acesso às rotas de presença  |
| 2        | professor | ✅ Acesso às suas próprias turmas   |
| 3        | admin     | ✅ Acesso total no tenant           |

> **Super Admin** (`usuarios.role_id = 3`) tem acesso total a **todos os tenants**.

### Vinculação Professor ↔ Usuário

O professor é vinculado ao usuário pelo **mesmo email**:
1. Cadastrar usuário em `usuarios` (conta global)
2. Vincular ao tenant via `usuario_tenant` com `papel_id = 2` (professor)
3. O email do usuário deve ser igual ao email em `professores`
4. O sistema vincula automaticamente pelas turmas do professor

---

## Endpoints

### 1. Dashboard do Professor

Retorna um resumo das turmas e presenças do professor.

```
GET /professor/dashboard
```

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "data": {
    "professor": {
      "id": 1,
      "nome": "Carlos Mendes",
      "email": "carlos.mendes@aquamasters.com.br"
    },
    "estatisticas": {
      "total_turmas": 10,
      "checkins_pendentes": 5,
      "presencas_mes": 45,
      "faltas_mes": 3
    },
    "turmas_pendentes": [...],
    "total_turmas_pendentes": 2
  }
}
```

---

### 2. Listar Turmas com Check-ins Pendentes

Lista todas as turmas do professor que possuem check-ins aguardando confirmação de presença.

```
GET /professor/turmas/pendentes
```

**Headers:**
```
Authorization: Bearer <token>
```

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "data": {
    "turmas": [
      {
        "id": 12,
        "nome": "Natação - 17:00 - Marcela Oliveira",
        "horario_inicio": "17:00:00",
        "horario_fim": "18:00:00",
        "dia_data": "2026-01-28",
        "modalidade_nome": "Natação",
        "modalidade_icone": "swim",
        "modalidade_cor": "#3b82f6",
        "pendentes": 5,
        "total_checkins": 8
      }
    ],
    "total": 1
  }
}
```

---

### 3. Listar Check-ins de uma Turma

Lista todos os check-ins de uma turma específica para o professor marcar presença.

```
GET /professor/turmas/{turmaId}/checkins
```

**Headers:**
```
Authorization: Bearer <token>
```

**Parâmetros de URL:**
- `turmaId` (int): ID da turma

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "data": {
    "turma": {
      "id": 12,
      "nome": "Natação - 17:00",
      "professor": "Marcela Oliveira",
      "modalidade": "Natação",
      "horario_inicio": "17:00:00",
      "horario_fim": "18:00:00",
      "dia_data": "2026-01-28"
    },
    "checkins": [
      {
        "checkin_id": 3,
        "aluno": {
          "id": 3,
          "nome": "ANDRE CABRAL SILVA",
          "email": "andrecabrall@gmail.com"
        },
        "data_checkin": "2026-01-28 14:30:00",
        "presenca": {
          "status": "pendente",
          "confirmada_em": null,
          "confirmada_por": null
        }
      }
    ],
    "estatisticas": {
      "total_checkins": 8,
      "presentes": 3,
      "faltas": 0,
      "pendentes": 5
    }
  }
}
```

**Status de Presença:**
- `pendente`: Aguardando confirmação do professor
- `presente`: Aluno confirmado como presente
- `falta`: Aluno confirmado como faltante

---

### 4. Confirmar Presença da Turma

Confirma a presença de todos os alunos de uma turma. Este é o principal endpoint para o professor usar no app mobile.

```
POST /professor/turmas/{turmaId}/confirmar-presenca
```

**Headers:**
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Parâmetros de URL:**
- `turmaId` (int): ID da turma

**Body:**
```json
{
  "presencas": {
    "3": true,      // checkin_id: 3 -> presente
    "6": false,     // checkin_id: 6 -> falta
    "9": true       // checkin_id: 9 -> presente
  },
  "remover_faltantes": true  // Opcional, default: true
}
```

**Parâmetros do Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `presencas` | object | ✅ | Objeto com checkin_id como chave e true/false como valor |
| `remover_faltantes` | boolean | ❌ | Se `true`, remove os check-ins marcados como falta (libera crédito do aluno). Default: `true` |

**Comportamento de `remover_faltantes`:**

Quando `remover_faltantes = true`:
1. Marca os alunos como presente ou falta
2. **REMOVE** os check-ins dos alunos marcados como falta
3. Isso **LIBERA o crédito semanal** do aluno, permitindo que ele faça check-in em outra aula

Quando `remover_faltantes = false`:
1. Apenas marca os alunos como presente ou falta
2. O check-in permanece no sistema
3. O crédito do aluno **NÃO** é liberado

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "message": "Presença confirmada: 2 presente(s), 1 falta(s). 1 check-in(s) de faltantes removido(s) (créditos liberados)",
  "data": {
    "turma_id": 12,
    "turma_nome": "Natação - 17:00",
    "confirmados": 3,
    "presencas": 2,
    "faltas": 1,
    "checkins_removidos": {
      "removidos": 1,
      "checkins": [
        {
          "id": 6,
          "usuario_id": 5,
          "turma_id": 12,
          "data_checkin": "2026-01-28 14:45:00",
          "aluno_nome": "JOAO SILVA",
          "aluno_email": "joao@email.com"
        }
      ]
    },
    "estatisticas": {
      "total_checkins": 2,
      "presentes": 2,
      "faltas": 0,
      "nao_verificados": 0
    },
    "confirmado_em": "2026-01-28 18:30:00",
    "confirmado_por": 10
  }
}
```

---

### 5. Remover Check-ins de Faltantes

Remove manualmente os check-ins de alunos já marcados como falta, liberando seus créditos.

```
DELETE /professor/turmas/{turmaId}/faltantes
```

**Headers:**
```
Authorization: Bearer <token>
```

**Parâmetros de URL:**
- `turmaId` (int): ID da turma

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "message": "2 check-in(s) de faltante(s) removido(s). Créditos liberados para remarcar.",
  "data": {
    "turma_id": 12,
    "turma_nome": "Natação - 17:00",
    "removidos": 2,
    "alunos_liberados": [
      {
        "id": 5,
        "nome": "JOAO SILVA",
        "email": "joao@email.com"
      },
      {
        "id": 8,
        "nome": "MARIA SANTOS",
        "email": "maria@email.com"
      }
    ]
  }
}
```

---

## Códigos de Erro

| Código HTTP | Código de Erro | Descrição |
|-------------|----------------|-----------|
| 401 | `NOT_AUTHENTICATED` | Token não fornecido ou inválido |
| 403 | `ACCESS_DENIED` | Usuário não tem permissão (não é professor/admin) |
| 403 | `NOT_YOUR_CLASS` | Professor tentando acessar turma de outro professor |
| 404 | `PROFESSOR_NOT_FOUND` | Usuário com role professor mas sem cadastro na tabela professores |
| 404 | - | Turma não encontrada |

---

## Fluxo de Uso no App Mobile

### 1. Professor faz login
O professor faz login normalmente. O sistema identifica que ele tem `role_id = 4`.

### 2. Tela inicial do professor
O app chama `GET /professor/dashboard` para mostrar:
- Quantas turmas tem
- Quantos check-ins pendentes
- Estatísticas do mês

### 3. Lista de turmas pendentes
Se existem check-ins pendentes, o app pode chamar `GET /professor/turmas/pendentes` para listar as turmas que precisam de confirmação.

### 4. Marcar presença
Ao selecionar uma turma, o app:
1. Chama `GET /professor/turmas/{id}/checkins` para listar os alunos
2. Exibe uma lista com checkbox para cada aluno
3. Professor marca quem está presente/faltou
4. Ao confirmar, chama `POST /professor/turmas/{id}/confirmar-presenca`

### 5. Liberação de créditos
Quando `remover_faltantes = true`:
- Os alunos que faltaram têm seu check-in **DELETADO**
- Isso **libera** o crédito semanal do plano
- O aluno pode remarcar em outra aula da semana

---

## Vinculação Professor ↔ Usuário

Para que um usuário com `role_id = 4` seja reconhecido como professor:

1. O usuário deve existir na tabela `usuarios` com `role_id = 4`
2. Deve existir um registro na tabela `professores` com o **mesmo email**
3. Ambos devem pertencer ao mesmo `tenant_id`

**Exemplo:**
```sql
-- Usuário
INSERT INTO usuarios (nome, email, role_id, senha_hash) 
VALUES ('Carlos Mendes', 'carlos@academia.com', 4, '...');

-- Professor (mesmo email)
INSERT INTO professores (tenant_id, nome, email) 
VALUES (2, 'Carlos Mendes', 'carlos@academia.com');
```

---

## Migration SQL

Execute a migration para adicionar a role de professor:

```sql
-- Arquivo: database/migrations/2026_01_28_add_role_professor.sql

INSERT INTO roles (id, nome, descricao)
SELECT 4, 'professor', 'Professor responsável por marcar presença dos alunos'
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE id = 4);
```
