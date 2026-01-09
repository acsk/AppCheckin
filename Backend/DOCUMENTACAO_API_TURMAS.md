# Documentação da API de Turmas (Classes)

## Endpoints

### 1. Listar turmas do tenant
**GET** `/admin/turmas`

#### Query Parameters
- `apenas_ativas` (boolean): Se true, retorna apenas turmas ativas. Default: false
- `data` (string): Data no formato YYYY-MM-DD para filtrar turmas de um dia específico
- `dia_id` (integer): ID do dia para filtrar turmas. Se nenhum for fornecido, usa hoje

#### Response Example
```json
{
  "dia": {
    "id": 18,
    "data": "2026-01-15",
    "nome": "Quarta-feira"
  },
  "turmas": [
    {
      "id": 1,
      "tenant_id": 1,
      "nome": "Turma A",
      "professor_id": 1,
      "professor_nome": "João Silva",
      "modalidade_id": 1,
      "modalidade_nome": "Pilates",
      "modalidade_icone": "pilates",
      "modalidade_cor": "#FF5733",
      "dia_id": 18,
      "dia_data": "2026-01-15",
      "horario_inicio": "04:00:00",
      "horario_fim": "04:30:00",
      "limite_alunos": 20,
      "alunos_count": 15,
      "ativo": 1,
      "created_at": "2026-01-09T10:00:00",
      "updated_at": "2026-01-09T10:00:00"
    }
  ]
}
```

---

### 2. Listar turmas de um dia específico
**GET** `/admin/turmas/dia/{diaId}`

#### Path Parameters
- `diaId` (integer): ID do dia

#### Response Example
Mesmo formato do endpoint 1, retorna apenas turmas do dia especificado.

---

### 3. Buscar turma por ID
**GET** `/admin/turmas/{id}`

#### Path Parameters
- `id` (integer): ID da turma

#### Response Example
```json
{
  "turma": {
    "id": 1,
    "tenant_id": 1,
    "nome": "Turma A",
    "professor_id": 1,
    "professor_nome": "João Silva",
    "modalidade_id": 1,
    "modalidade_nome": "Pilates",
    "dia_id": 18,
    "dia_data": "2026-01-15",
    "horario_inicio": "04:00:00",
    "horario_fim": "04:30:00",
    "limite_alunos": 20,
    "alunos_count": 15,
    "ativo": 1,
    "created_at": "2026-01-09T10:00:00",
    "updated_at": "2026-01-09T10:00:00"
  }
}
```

---

### 4. Criar nova turma
**POST** `/admin/turmas`

#### Request Body
```json
{
  "nome": "Turma A",
  "professor_id": 1,
  "modalidade_id": 1,
  "dia_id": 18,
  "horario_inicio": "04:00",
  "horario_fim": "04:30",
  "limite_alunos": 20
}
```

#### Request Fields
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| nome | string | ✓ | Nome da turma |
| professor_id | integer | ✓ | ID do professor |
| modalidade_id | integer | ✓ | ID da modalidade |
| dia_id | integer | ✓ | ID do dia da semana |
| horario_inicio | string | ✓ | Horário de início (HH:MM ou HH:MM:SS) |
| horario_fim | string | ✓ | Horário de término (HH:MM ou HH:MM:SS) |
| limite_alunos | integer | ✗ | Limite de alunos. Default: 20 |

#### Validations
- O horário de fim **deve ser maior** que o horário de início
- Não pode haver **conflito de horário** no mesmo dia (sobreposição de horários)
- Professor deve pertencer ao tenant
- Dia deve existir no sistema

#### Response (201 Created)
```json
{
  "type": "success",
  "message": "Turma criada com sucesso",
  "turma": {
    "id": 1,
    "tenant_id": 1,
    "nome": "Turma A",
    "professor_id": 1,
    "professor_nome": "João Silva",
    "modalidade_id": 1,
    "modalidade_nome": "Pilates",
    "dia_id": 18,
    "horario_inicio": "04:00:00",
    "horario_fim": "04:30:00",
    "limite_alunos": 20,
    "ativo": 1,
    "created_at": "2026-01-09T10:00:00",
    "updated_at": "2026-01-09T10:00:00"
  }
}
```

#### Errors
- **400**: Nome obrigatório, Professor obrigatório, Modalidade obrigatória, Dia obrigatório, Horários obrigatórios, Horário fim inválido, Conflito de horário, Professor/Dia não encontrado
- **500**: Erro ao criar turma

---

### 5. Atualizar turma
**PUT** `/admin/turmas/{id}`

#### Path Parameters
- `id` (integer): ID da turma

#### Request Body
```json
{
  "nome": "Turma A - Atualizada",
  "professor_id": 2,
  "modalidade_id": 1,
  "dia_id": 19,
  "horario_inicio": "05:00",
  "horario_fim": "05:30",
  "limite_alunos": 25,
  "ativo": 1
}
```

Apenas os campos que você deseja atualizar são necessários.

#### Validations
- Se atualizando `horario_inicio` e/ou `horario_fim`: não pode haver conflito com outras turmas no mesmo dia
- Se atualizando `professor_id`: deve pertencer ao tenant
- Se atualizando `dia_id`: deve existir no sistema
- `horario_fim` deve ser maior que `horario_inicio`

#### Response
```json
{
  "type": "success",
  "message": "Turma atualizada com sucesso",
  "turma": {
    "id": 1,
    "tenant_id": 1,
    "nome": "Turma A - Atualizada",
    ...
  }
}
```

#### Errors
- **404**: Turma não encontrada
- **400**: Validações falharam, Professor/Dia não encontrado
- **500**: Erro ao atualizar

---

### 6. Deletar turma (soft delete)
**DELETE** `/admin/turmas/{id}`

#### Path Parameters
- `id` (integer): ID da turma

#### Response
```json
{
  "type": "success",
  "message": "Turma deletada com sucesso"
}
```

#### Errors
- **404**: Turma não encontrada
- **500**: Erro ao deletar

---

### 7. Listar turmas de um professor
**GET** `/admin/professores/{professorId}/turmas`

#### Path Parameters
- `professorId` (integer): ID do professor

#### Response
```json
{
  "turmas": [
    {
      "id": 1,
      "tenant_id": 1,
      "nome": "Turma A",
      "professor_id": 1,
      "modalidade_nome": "Pilates",
      "dia_id": 18,
      "horario_inicio": "04:00:00",
      "horario_fim": "04:30:00",
      "alunos_count": 15,
      ...
    }
  ]
}
```

#### Errors
- **404**: Professor não encontrado

---

### 8. Verificar disponibilidade de vagas
**GET** `/admin/turmas/{id}/vagas`

#### Path Parameters
- `id` (integer): ID da turma

#### Response
```json
{
  "turma_id": 1,
  "limite_alunos": 20,
  "alunos_inscritos": 15,
  "vagas_disponiveis": 5,
  "tem_vagas": true
}
```

#### Errors
- **404**: Turma não encontrada

---

## Schema da Tabela turmas

```sql
CREATE TABLE turmas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  professor_id INT NOT NULL,
  modalidade_id INT NOT NULL,
  dia_id INT NOT NULL,
  horario_inicio TIME NOT NULL,     -- Novo: Horário de início direto
  horario_fim TIME NOT NULL,        -- Novo: Horário de término direto
  nome VARCHAR(255) NOT NULL,
  limite_alunos INT NOT NULL,
  ativo TINYINT(1),
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (professor_id) REFERENCES professores(id),
  FOREIGN KEY (modalidade_id) REFERENCES modalidades(id),
  FOREIGN KEY (dia_id) REFERENCES dias(id)
);
```

### Mudanças Recentes
- ✅ Removida dependência da tabela `horarios`
- ✅ Adicionadas colunas `horario_inicio` e `horario_fim` como TIME direto na tabela `turmas`
- ✅ Removida coluna `horario_id` e sua foreign key constraint
- ✅ Validação de conflito de horário agora detecta **sobreposição** de horários (não apenas valores exatos)
- ✅ Frontend pode enviar horários em qualquer formato (HH:MM ou HH:MM:SS)

---

## Notas

### Detecção de Conflito de Horário
O sistema detecta conflito quando há **sobreposição** de horários:

```
Conflito detectado quando:
horario_inicio_nova < horario_fim_existente AND
horario_fim_nova > horario_inicio_existente
```

**Exemplos:**
- Turma existente: 04:00 - 04:30
  - 04:15 - 04:45 ❌ Conflito (sobrepõe 04:15-04:30)
  - 04:00 - 04:30 ❌ Conflito (exato)
  - 03:30 - 04:00 ✅ OK (termina exatamente quando começa)
  - 04:30 - 05:00 ✅ OK (começa exatamente quando termina)

### 3️⃣ Apenas Turmas Ativas
```bash
