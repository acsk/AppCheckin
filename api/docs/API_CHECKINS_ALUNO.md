# Check-ins do Aluno — Documentação para Frontend

> **Endpoint:** `GET /admin/alunos/{id}/checkins`  
> **Autenticação:** Bearer Token (JWT)  
> **Middleware:** AdminMiddleware

---

## Comportamento dinâmico

| Parâmetros enviados | Resposta |
|---------------------|----------|
| Nenhum | Resumo agrupado **mês a mês** de toda a história do aluno |
| `mes` + `ano` | Lista detalhada de todos os check-ins naquele mês |
| + `modalidade_id` (opcional em ambos) | Filtra por modalidade |

---

## 1. Resumo mês a mês (sem filtro de data)

Ideal para exibir o histórico geral do aluno, ex: calendário de frequência ou gráfico de evolução.

### Request

```http
GET /admin/alunos/{id}/checkins
GET /admin/alunos/{id}/checkins?modalidade_id=3
```

### Query Parameters

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `modalidade_id` | integer | Não | Filtra por modalidade (ex: `3` = Natação) |

### Response (200)

```json
{
  "success": true,
  "total_geral": 47,
  "meses": [
    {
      "ano": 2026,
      "mes": 4,
      "total": 5,
      "presentes": 5
    },
    {
      "ano": 2026,
      "mes": 3,
      "total": 12,
      "presentes": 11
    },
    {
      "ano": 2026,
      "mes": 2,
      "total": 10,
      "presentes": 10
    }
  ]
}
```

### Campos de `meses[]`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `ano` | integer | Ano da aula |
| `mes` | integer | Mês da aula (1–12) |
| `total` | integer | Total de check-ins registrados no mês |
| `presentes` | integer | Check-ins com `presente = 1` (confirmados) |

> `total - presentes` = check-ins pendentes ou ausentes no mês.

---

## 2. Detalhes de um mês específico

Ideal para exibir a lista de aulas do aluno ao clicar em um mês.

### Request

```http
GET /admin/alunos/{id}/checkins?mes=3&ano=2026
GET /admin/alunos/{id}/checkins?mes=3&ano=2026&modalidade_id=3
```

### Query Parameters

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `mes` | integer | Sim (junto com `ano`) | Mês (1–12) |
| `ano` | integer | Sim (junto com `mes`) | Ano (ex: 2026) |
| `modalidade_id` | integer | Não | Filtra por modalidade |

### Response (200)

```json
{
  "success": true,
  "mes": 3,
  "ano": 2026,
  "total": 12,
  "checkins": [
    {
      "id": 429,
      "data_aula": "2026-03-05",
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "modalidade_id": 3,
      "modalidade": "Natação",
      "presente": 1,
      "registrado_por_admin": 0,
      "created_at": "2026-03-05 06:01:22"
    },
    {
      "id": 441,
      "data_aula": "2026-03-07",
      "horario_inicio": "06:00:00",
      "horario_fim": "07:00:00",
      "modalidade_id": 3,
      "modalidade": "Natação",
      "presente": null,
      "registrado_por_admin": 1,
      "created_at": "2026-03-07 20:15:00"
    }
  ]
}
```

### Campos de `checkins[]`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID do check-in |
| `data_aula` | string (date) | Data real da aula (`dias.data`) |
| `horario_inicio` | string (time) | Hora de início da turma |
| `horario_fim` | string (time) | Hora de fim da turma |
| `modalidade_id` | integer | ID da modalidade |
| `modalidade` | string | Nome da modalidade |
| `presente` | integer\|null | `1` = presente, `0` = ausente, `null` = pendente |
| `registrado_por_admin` | integer | `1` = registrado manualmente por admin |
| `created_at` | string (datetime) | Quando o check-in foi criado no sistema |

---

## Exemplos de uso no frontend

### Fluxo recomendado: histórico de frequência

```js
// 1. Carrega resumo ao abrir o perfil do aluno
const resumo = await fetch(`/admin/alunos/${alunoId}/checkins`, { headers })
// → exibe lista de meses com badges de frequência

// 2. Ao clicar em um mês, carrega o detalhe
const detalhe = await fetch(`/admin/alunos/${alunoId}/checkins?mes=3&ano=2026`, { headers })
// → exibe lista de aulas do mês

// 3. Filtrar por modalidade (opcional)
const natacao = await fetch(`/admin/alunos/${alunoId}/checkins?mes=3&ano=2026&modalidade_id=3`, { headers })
```

### Exibir status do check-in

```js
function statusCheckin(presente) {
  if (presente === 1)    return { label: 'Presente',  cor: 'green' }
  if (presente === 0)    return { label: 'Ausente',   cor: 'red' }
  return                        { label: 'Pendente',  cor: 'gray' }
}
```

---

## Observações

- **`data_aula`** é a data real da aula conforme cadastro (`dias.data`), **não** a data de criação do registro. Um check-in pré-registrado na noite anterior terá `created_at` diferente de `data_aula`.
- Ordenação do resumo: **mais recente primeiro**.
- Ordenação do detalhe: **mais antigo primeiro** dentro do mês.
