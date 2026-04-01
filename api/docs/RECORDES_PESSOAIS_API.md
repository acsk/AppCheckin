# Recordes — Documentação API

## Visão Geral

Módulo genérico de **Recordes / PRs** para registrar marcas de alunos em qualquer modalidade: natação, cross, musculação, corrida, testes físicos, etc. Tanto o aluno (via mobile) quanto o professor/admin (via painel) podem registrar recordes. A academia também pode manter seus próprios recordes oficiais.

A modelagem é baseada em **4 tabelas** que desacoplam a definição do teste das medições:

1. **Definição** (`recorde_definicoes`) — o que está sendo medido (ex: Deadlift, 100m Crawl, AMRAP)
2. **Métricas** (`recorde_definicao_metricas`) — como medir (ex: peso_kg, tempo_ms, rounds + reps)
3. **Recorde** (`recordes`) — a tentativa/PR em si
4. **Valores** (`recorde_valores`) — os valores alcançados para cada métrica

---

## Conceitos

| Conceito | Descrição |
|---|---|
| **Definição** | Tipo de recorde/teste (ex: "Deadlift", "100m Crawl", "AMRAP 12min"). Configurável pelo admin. |
| **Métrica** | Como o recorde é medido. Cada definição tem 1+ métricas (ex: peso_kg, tempo_ms, rounds). |
| **Recorde** | Um registro de tentativa de um aluno ou academia para uma definição, com data. |
| **Valores** | Os valores medidos para cada métrica de um recorde. |
| **Origem `aluno`** | Recorde registrado pelo próprio aluno (PR pessoal). |
| **Origem `academia`** | Recorde registrado pelo professor/admin (recorde oficial da academia). |
| **Direção** | `maior_melhor` (Deadlift, reps) ou `menor_melhor` (tempo de natação, corrida). |
| **Categoria** | `movimento`, `prova`, `workout`, `teste_fisico` — organiza os tipos de recorde. |

---

## Estrutura dos Dados

### Definição (`recorde_definicoes`)

```json
{
  "id": 1,
  "tenant_id": 1,
  "modalidade_id": 2,
  "modalidade_nome": "CrossFit",
  "nome": "Deadlift",
  "categoria": "movimento",
  "descricao": null,
  "ativo": 1,
  "ordem": 10,
  "metricas": [
    {
      "id": 1,
      "definicao_id": 1,
      "codigo": "peso_kg",
      "nome": "Carga",
      "tipo_valor": "decimal",
      "unidade": "kg",
      "ordem_comparacao": 1,
      "direcao": "maior_melhor",
      "obrigatoria": 1
    }
  ]
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `modalidade_id` | int \| null | FK para modalidade (Natação, CrossFit, etc.) |
| `nome` | string | Nome da definição (ex: "Deadlift", "100m Crawl") |
| `categoria` | enum | `movimento`, `prova`, `workout`, `teste_fisico` |
| `descricao` | string \| null | Descrição opcional |
| `ativo` | 0 \| 1 | Se está disponível |
| `ordem` | int | Ordem de exibição |
| `metricas` | array | Métricas associadas (sempre retornadas junto) |

### Métrica (`recorde_definicao_metricas`)

| Campo | Tipo | Descrição |
|---|---|---|
| `codigo` | string | Identificador único por definição (ex: `peso_kg`, `tempo_ms`, `repeticoes`) |
| `nome` | string | Nome legível (ex: "Carga", "Tempo", "Repetições") |
| `tipo_valor` | enum | `inteiro`, `decimal`, `tempo_ms` |
| `unidade` | string \| null | Unidade de medida (ex: `kg`, `ms`, `reps`, `m`) |
| `ordem_comparacao` | int | 1 = principal (usado no ranking), 2 = desempate, etc. |
| `direcao` | enum | `maior_melhor` ou `menor_melhor` |
| `obrigatoria` | 0 \| 1 | Se o valor é obrigatório ao registrar |

### Recorde (`recordes`)

```json
{
  "id": 1,
  "tenant_id": 1,
  "aluno_id": 10,
  "definicao_id": 1,
  "definicao_nome": "Deadlift",
  "categoria": "movimento",
  "modalidade_nome": "CrossFit",
  "origem": "aluno",
  "data_recorde": "2026-04-01",
  "observacoes": "Novo PR de deadlift",
  "registrado_por": 10,
  "valido": 1,
  "aluno_nome": "João Silva",
  "valores": [
    {
      "recorde_id": 1,
      "metrica_id": 1,
      "codigo": "peso_kg",
      "metrica_nome": "Carga",
      "tipo_valor": "decimal",
      "unidade": "kg",
      "direcao": "maior_melhor",
      "valor_int": null,
      "valor_decimal": "180.000",
      "valor_tempo_ms": null
    }
  ]
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `aluno_id` | int \| null | ID do aluno. `null` = recorde da academia |
| `definicao_id` | int | ID da definição |
| `origem` | enum | `aluno` ou `academia` |
| `data_recorde` | date | Data do recorde (YYYY-MM-DD) |
| `observacoes` | string \| null | Texto livre |
| `valido` | 0 \| 1 | Se o recorde é válido |
| `valores` | array | Valores para cada métrica |

### Valor (`recorde_valores`)

Cada valor usa o campo correspondente ao `tipo_valor` da métrica:

| tipo_valor | Campo usado |
|---|---|
| `inteiro` | `valor_int` (BIGINT) |
| `decimal` | `valor_decimal` (DECIMAL 12,3) |
| `tempo_ms` | `valor_tempo_ms` (BIGINT — milissegundos) |

---

## Endpoints — Painel Admin

Base: `/admin`
Auth: Bearer Token + AdminMiddleware

### Definições (CRUD)

#### Listar Definições
```
GET /admin/recordes/definicoes
```
Query params:
- `todas=true` — inclui desativadas (default: só ativas)
- `modalidade_id=2` — filtrar por modalidade
- `categoria=movimento` — filtrar por categoria

**Resposta 200:**
```json
{
  "definicoes": [
    {
      "id": 1,
      "nome": "Deadlift",
      "categoria": "movimento",
      "modalidade_nome": "CrossFit",
      "ativo": 1,
      "ordem": 10,
      "metricas": [
        {
          "codigo": "peso_kg",
          "nome": "Carga",
          "tipo_valor": "decimal",
          "unidade": "kg",
          "direcao": "maior_melhor"
        }
      ]
    }
  ]
}
```

#### Buscar Definição por ID
```
GET /admin/recordes/definicoes/{id}
```

#### Criar Definição (com métricas)
```
POST /admin/recordes/definicoes
Content-Type: application/json
```
**Body:**
```json
{
  "nome": "AMRAP 12 min - Cindy",
  "modalidade_id": 2,
  "categoria": "workout",
  "descricao": "5 Pull-ups, 10 Push-ups, 15 Squats",
  "ordem": 15,
  "metricas": [
    {
      "codigo": "rounds",
      "nome": "Rounds",
      "tipo_valor": "inteiro",
      "unidade": "rounds",
      "ordem_comparacao": 1,
      "direcao": "maior_melhor"
    },
    {
      "codigo": "repeticoes",
      "nome": "Repetições extras",
      "tipo_valor": "inteiro",
      "unidade": "reps",
      "ordem_comparacao": 2,
      "direcao": "maior_melhor",
      "obrigatoria": 0
    }
  ]
}
```
Campos obrigatórios: `nome`, `metricas` (array com pelo menos 1 item contendo `codigo`, `nome`, `direcao`)

#### Atualizar Definição
```
PUT /admin/recordes/definicoes/{id}
```
Mesma estrutura do POST. Se `metricas` for enviado, as anteriores são substituídas.

#### Desativar Definição
```
DELETE /admin/recordes/definicoes/{id}
```
> Não exclui fisicamente, apenas desativa (`ativo = 0`).

---

### Recordes (CRUD)

#### Listar Recordes
```
GET /admin/recordes
```
Query params:
- `aluno_id=42` — filtrar por aluno
- `definicao_id=1` — filtrar por definição
- `origem=academia` — apenas recordes da academia
- `modalidade_id=2` — filtrar por modalidade

#### Buscar Recorde por ID
```
GET /admin/recordes/{id}
```

#### Criar Recorde
```
POST /admin/recordes
Content-Type: application/json
```
**Body:**
```json
{
  "definicao_id": 1,
  "aluno_id": 10,
  "data_recorde": "2026-04-01",
  "observacoes": "Novo PR de deadlift",
  "origem": "academia",
  "valores": [
    { "metrica_id": 1, "valor_decimal": 180.000 }
  ]
}
```
Campos obrigatórios: `definicao_id`, `data_recorde`, `valores` (array com pelo menos 1 item)

| Campo | Descrição |
|---|---|
| `aluno_id` | Opcional. Se null = recorde geral da academia |
| `origem` | Default: `academia` |
| `valores[].metrica_id` | ID da métrica |
| `valores[].valor_int` | Para tipo `inteiro` |
| `valores[].valor_decimal` | Para tipo `decimal` |
| `valores[].valor_tempo_ms` | Para tipo `tempo_ms` |

**Exemplo AMRAP (múltiplas métricas):**
```json
{
  "definicao_id": 5,
  "aluno_id": 10,
  "data_recorde": "2026-04-01",
  "valores": [
    { "metrica_id": 8, "valor_int": 15 },
    { "metrica_id": 9, "valor_int": 7 }
  ]
}
```

#### Atualizar Recorde
```
PUT /admin/recordes/{id}
```

#### Excluir Recorde
```
DELETE /admin/recordes/{id}
```

---

### Ranking

#### Ranking por Definição
```
GET /admin/recordes/ranking/{definicaoId}
```
Query params:
- `limit=50` — max de resultados (default: 50, max: 100)

Usa a **métrica principal** (`ordem_comparacao = 1`) e sua `direcao` para ordenar.

**Resposta 200:**
```json
{
  "definicao": { "id": 1, "nome": "Deadlift", "metricas": [...] },
  "ranking": [
    {
      "aluno_id": 10,
      "aluno_nome": "João Silva",
      "melhor_valor": "180.000",
      "data_recorde": "2026-04-01",
      "metrica_codigo": "peso_kg",
      "metrica_nome": "Carga",
      "metrica_unidade": "kg",
      "metrica_direcao": "maior_melhor",
      "metrica_tipo_valor": "decimal"
    }
  ]
}
```

---

## Endpoints — App Mobile

Base: `/mobile`
Auth: Bearer Token

### Listar Definições Disponíveis
```
GET /mobile/recordes/definicoes
```
Query params:
- `modalidade_id=2` — filtrar por modalidade
- `categoria=movimento` — filtrar por categoria

### Meus Recordes
```
GET /mobile/recordes/meus
```
Query params:
- `definicao_id=1` — filtrar por definição

Retorna todos os registros + os melhores (PRs) agrupados por definição.

**Resposta 200:**
```json
{
  "success": true,
  "recordes": [...],
  "melhores": [...]
}
```

### Registrar Meu Recorde
```
POST /mobile/recordes
Content-Type: application/json
```
**Body:**
```json
{
  "definicao_id": 1,
  "data_recorde": "2026-04-01",
  "observacoes": "Novo PR!",
  "valores": [
    { "metrica_id": 1, "valor_decimal": 185.000 }
  ]
}
```
Campos obrigatórios: `definicao_id`, `data_recorde`, `valores`

### Atualizar Meu Recorde
```
PUT /mobile/recordes/{id}
```
> Só permite editar recordes do próprio aluno com `origem = "aluno"`.

### Excluir Meu Recorde
```
DELETE /mobile/recordes/{id}
```

### Ranking por Definição (Mobile)
```
GET /mobile/recordes/ranking/{definicaoId}
```

### Recordes da Academia
```
GET /mobile/recordes/academia
```
Query params:
- `definicao_id=1` — filtrar por definição
- `modalidade_id=2` — filtrar por modalidade

---

## Códigos de Erro

| Status | Descrição |
|---|---|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 404 | Recurso não encontrado (definição, recorde, aluno) |
| 422 | Validação falhou (campo obrigatório faltando) |
| 500 | Erro interno do servidor |

---

## Formatação de Tempo (sugestão para o front)

O campo `valor_tempo_ms` armazena tempo em **milissegundos** (ex: `92450` = 1min 32seg 450ms).

```javascript
function formatarTempo(ms) {
  if (!ms) return '--';
  const totalSec = ms / 1000;
  const min = Math.floor(totalSec / 60);
  const sec = Math.floor(totalSec % 60);
  const milli = Math.round(ms % 1000);

  if (min > 0) {
    return `${min}:${String(sec).padStart(2, '0')}.${String(milli).padStart(3, '0')}`;
  }
  return `${sec}.${String(milli).padStart(3, '0')}s`;
}

// formatarTempo(92450) → "1:32.450"
// formatarTempo(25300) → "25.300s"
```

---

## Definições Pré-cadastradas

As seguintes definições são criadas automaticamente para cada escola na migration:

### Natação

| Nome | Categoria | Métrica | Unidade | Direção |
|---|---|---|---|---|
| 25m Crawl | prova | tempo_ms | ms | menor_melhor |
| 50m Crawl | prova | tempo_ms | ms | menor_melhor |
| 100m Crawl | prova | tempo_ms | ms | menor_melhor |
| 50m Costas | prova | tempo_ms | ms | menor_melhor |
| 50m Peito | prova | tempo_ms | ms | menor_melhor |
| 50m Borboleta | prova | tempo_ms | ms | menor_melhor |

### Musculação / Cross

| Nome | Categoria | Métrica | Unidade | Direção |
|---|---|---|---|---|
| Deadlift | movimento | peso_kg | kg | maior_melhor |
| Back Squat | movimento | peso_kg | kg | maior_melhor |
| BMU Máximo de Repetições | movimento | repeticoes | reps | maior_melhor |

### Corrida

| Nome | Categoria | Métrica | Unidade | Direção |
|---|---|---|---|---|
| Corrida 5km | prova | tempo_ms | ms | menor_melhor |

O admin pode criar definições adicionais pelo painel, cada uma com suas próprias métricas e regras de comparação.

---

## Exemplos de Uso

### Deadlift (1 métrica, maior melhor)
```json
// Definição: Deadlift → peso_kg → maior_melhor
// Registrar PR:
POST /mobile/recordes
{
  "definicao_id": 7,
  "data_recorde": "2026-04-01",
  "valores": [{ "metrica_id": 10, "valor_decimal": 180.0 }]
}
```

### 100m Crawl (1 métrica, menor melhor)
```json
// Definição: 100m Crawl → tempo_ms → menor_melhor
POST /mobile/recordes
{
  "definicao_id": 3,
  "data_recorde": "2026-04-01",
  "valores": [{ "metrica_id": 3, "valor_tempo_ms": 65230 }]
}
```

### AMRAP (2 métricas)
```json
// Definição: AMRAP 12min → rounds (principal) + reps (desempate)
POST /mobile/recordes
{
  "definicao_id": 5,
  "data_recorde": "2026-04-01",
  "valores": [
    { "metrica_id": 8, "valor_int": 15 },
    { "metrica_id": 9, "valor_int": 7 }
  ]
}
