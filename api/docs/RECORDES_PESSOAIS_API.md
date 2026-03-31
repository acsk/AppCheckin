# Recordes Pessoais — Documentação API

## Visão Geral

Módulo de **Recordes Pessoais (PRs)** para registrar tempos e marcas de alunos em provas de natação e outras modalidades. Tanto o aluno (via mobile) quanto o professor/admin (via painel) podem registrar recordes. A escola também pode manter seus próprios recordes oficiais.

---

## Conceitos

| Conceito | Descrição |
|---|---|
| **Prova** | Tipo de evento/prova (ex: "25m Crawl", "50m Costas"). Configurável pelo admin. |
| **Recorde** | Um registro de tempo/valor de um aluno em uma prova, com data. |
| **Origem `aluno`** | Recorde registrado pelo próprio aluno (PR pessoal). |
| **Origem `escola`** | Recorde registrado pelo professor/admin (recorde oficial da escola). |
| **Unidade de medida** | `tempo` (segundos), `metros`, `repeticoes`, `peso_kg` — define como interpretar o valor. |

---

## Estrutura dos Dados

### Prova (`recorde_provas`)

```json
{
  "id": 1,
  "tenant_id": 1,
  "modalidade_id": 3,
  "modalidade_nome": "Natação",
  "nome": "25m Crawl",
  "distancia_metros": 25,
  "estilo": "Crawl",
  "unidade_medida": "tempo",
  "ativo": 1,
  "ordem": 1,
  "created_at": "2026-03-31 10:00:00",
  "updated_at": "2026-03-31 10:00:00"
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `modalidade_id` | int \| null | ID da modalidade associada (ex: Natação, CrossFit). Pode ser `null` |
| `modalidade_nome` | string \| null | Nome da modalidade (retornado apenas na leitura) |
| `nome` | string | Nome da prova (ex: "25m Crawl") |
| `distancia_metros` | int \| null | Distância em metros |
| `estilo` | string \| null | Crawl, Costas, Peito, Borboleta, Medley |
| `unidade_medida` | enum | `tempo`, `metros`, `repeticoes`, `peso_kg` |
| `ativo` | 0 \| 1 | Se a prova está disponível |
| `ordem` | int | Ordem de exibição na listagem |

### Recorde (`recordes_pessoais`)

```json
{
  "id": 1,
  "tenant_id": 1,
  "aluno_id": 42,
  "prova_id": 1,
  "tempo_segundos": 32.45,
  "valor": null,
  "data_registro": "2026-03-15",
  "observacoes": "Treino matutino",
  "origem": "aluno",
  "registrado_por": 10,
  "prova_nome": "25m Crawl",
  "distancia_metros": 25,
  "estilo": "Crawl",
  "unidade_medida": "tempo",
  "modalidade_id": 3,
  "modalidade_nome": "Natação",
  "aluno_nome": "João Silva",
  "registrado_por_nome": "João Silva"
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `aluno_id` | int \| null | ID do aluno. `null` = recorde da escola |
| `prova_id` | int | ID da prova |
| `tempo_segundos` | decimal \| null | Tempo em segundos (usar quando `unidade_medida = tempo`) |
| `valor` | decimal \| null | Valor genérico (usar quando `unidade_medida != tempo`) |
| `data_registro` | date | Data do recorde (YYYY-MM-DD) |
| `observacoes` | string \| null | Texto livre |
| `origem` | enum | `aluno` ou `escola` |
| `registrado_por` | int \| null | ID do usuário que registrou |

> **Regra de valor:** Se a prova tem `unidade_medida = "tempo"`, envie `tempo_segundos`. Para outras unidades, envie `valor`.

---

## Endpoints — Painel Admin

Base: `/admin`  
Auth: Bearer Token + AdminMiddleware

### Provas (CRUD)

#### Listar Provas
```
GET /admin/recordes/provas
```
Query params:
- `todas=true` — inclui provas desativadas (default: só ativas)
- `modalidade_id=3` — filtrar por modalidade

**Resposta 200:**
```json
{
  "provas": [
    {
      "id": 1,
      "modalidade_id": 3,
      "modalidade_nome": "Natação",
      "nome": "25m Crawl",
      "distancia_metros": 25,
      "estilo": "Crawl",
      "unidade_medida": "tempo",
      "ativo": 1,
      "ordem": 1
    }
  ]
}
```

#### Buscar Prova por ID
```
GET /admin/recordes/provas/{id}
```

**Resposta 200:**
```json
{
  "prova": { ... }
}
```

#### Criar Prova
```
POST /admin/recordes/provas
Content-Type: application/json
```
**Body:**
```json
{
  "nome": "100m Medley",
  "modalidade_id": 3,
  "distancia_metros": 100,
  "estilo": "Medley",
  "unidade_medida": "tempo",
  "ordem": 10
}
```
Campos obrigatórios: `nome`

**Resposta 201:**
```json
{
  "type": "success",
  "message": "Prova criada com sucesso",
  "prova": { ... }
}
```

#### Atualizar Prova
```
PUT /admin/recordes/provas/{id}
Content-Type: application/json
```
**Body:**
```json
{
  "nome": "100m Medley",
  "modalidade_id": 3,
  "distancia_metros": 100,
  "estilo": "Medley",
  "unidade_medida": "tempo",
  "ordem": 10,
  "ativo": 1
}
```
Campos obrigatórios: `nome`

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Prova atualizada com sucesso",
  "prova": { ... }
}
```

#### Desativar Prova
```
DELETE /admin/recordes/provas/{id}
```
> Não exclui fisicamente, apenas desativa (`ativo = 0`).

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Prova desativada com sucesso"
}
```

---

### Recordes (CRUD)

#### Listar Recordes
```
GET /admin/recordes
```
Query params:
- `aluno_id=42` — filtrar por aluno
- `prova_id=1` — filtrar por prova
- `origem=escola` — apenas recordes da escola
- `modalidade_id=3` — filtrar por modalidade

**Resposta 200:**
```json
{
  "recordes": [
    {
      "id": 1,
      "aluno_id": 42,
      "prova_id": 1,
      "tempo_segundos": "32.45",
      "valor": null,
      "data_registro": "2026-03-15",
      "observacoes": null,
      "origem": "escola",
      "prova_nome": "25m Crawl",
      "aluno_nome": "João Silva",
      "registrado_por_nome": "Prof. Carlos"
    }
  ]
}
```

#### Buscar Recorde por ID
```
GET /admin/recordes/{id}
```

**Resposta 200:**
```json
{
  "recorde": { ... }
}
```

#### Criar Recorde
```
POST /admin/recordes
Content-Type: application/json
```
**Body:**
```json
{
  "prova_id": 1,
  "aluno_id": 42,
  "tempo_segundos": 28.90,
  "data_registro": "2026-03-20",
  "observacoes": "Competição interna",
  "origem": "escola"
}
```
Campos obrigatórios: `prova_id`, `data_registro`, (`tempo_segundos` ou `valor`)

| Campo | Observação |
|---|---|
| `aluno_id` | Opcional. Se null = recorde geral da escola |
| `origem` | Default: `escola`. Use `aluno` se quiser registrar um PR do aluno pelo admin |
| `tempo_segundos` | Usar para provas com `unidade_medida = tempo` |
| `valor` | Usar para provas com `unidade_medida != tempo` |

**Resposta 201:**
```json
{
  "type": "success",
  "message": "Recorde registrado com sucesso",
  "recorde": { ... }
}
```

#### Atualizar Recorde
```
PUT /admin/recordes/{id}
Content-Type: application/json
```
**Body:**
```json
{
  "prova_id": 1,
  "tempo_segundos": 27.50,
  "data_registro": "2026-03-25",
  "observacoes": "Melhorou o tempo"
}
```
Campos obrigatórios: `prova_id`, `data_registro`

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Recorde atualizado com sucesso",
  "recorde": { ... }
}
```

#### Excluir Recorde
```
DELETE /admin/recordes/{id}
```
> Exclui fisicamente o registro.

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Recorde excluído com sucesso"
}
```

---

### Ranking

#### Ranking por Prova
```
GET /admin/recordes/ranking/{provaId}
```
Query params:
- `limit=50` — quantidade máxima de resultados (default: 50, max: 100)

Retorna o melhor tempo/valor de cada aluno na prova, ordenado do melhor para o pior.

**Resposta 200:**
```json
{
  "prova": {
    "id": 1,
    "nome": "25m Crawl",
    "unidade_medida": "tempo"
  },
  "ranking": [
    {
      "aluno_id": 42,
      "aluno_nome": "João Silva",
      "melhor_tempo": "25.30",
      "melhor_valor": null,
      "data_recorde": "2026-03-20",
      "unidade_medida": "tempo"
    },
    {
      "aluno_id": 15,
      "aluno_nome": "Maria Santos",
      "melhor_tempo": "26.10",
      "melhor_valor": null,
      "data_recorde": "2026-03-18",
      "unidade_medida": "tempo"
    }
  ]
}
```

> **Para `unidade_medida = "tempo"`**: ordenado por `melhor_tempo` ASC (menor = melhor).  
> **Para outras unidades**: ordenado por `melhor_valor` DESC (maior = melhor).

---

## Endpoints — App Mobile

Base: `/mobile`  
Auth: Bearer Token

### Listar Provas Disponíveis
```
GET /mobile/recordes/provas
```
Query params:
- `modalidade_id=3` — filtrar por modalidade (opcional)

**Resposta 200:**
```json
{
  "success": true,
  "provas": [
    {
      "id": 1,
      "modalidade_id": 3,
      "modalidade_nome": "Natação",
      "nome": "25m Crawl",
      "distancia_metros": 25,
      "estilo": "Crawl",
      "unidade_medida": "tempo",
      "ordem": 1
    }
  ]
}
```

---

### Meus Recordes (PRs do aluno logado)
```
GET /mobile/recordes/meus
```
Query params:
- `prova_id=1` — filtrar por prova (opcional)

Retorna todos os registros + os melhores (PRs) agrupados por prova.

**Resposta 200:**
```json
{
  "success": true,
  "recordes": [
    {
      "id": 1,
      "prova_id": 1,
      "tempo_segundos": "32.45",
      "valor": null,
      "data_registro": "2026-03-10",
      "observacoes": null,
      "origem": "aluno",
      "prova_nome": "25m Crawl",
      "unidade_medida": "tempo"
    },
    {
      "id": 5,
      "prova_id": 1,
      "tempo_segundos": "30.20",
      "valor": null,
      "data_registro": "2026-03-20",
      "observacoes": "Treino forte",
      "origem": "aluno",
      "prova_nome": "25m Crawl",
      "unidade_medida": "tempo"
    }
  ],
  "melhores": [
    {
      "id": 5,
      "prova_id": 1,
      "tempo_segundos": "30.20",
      "data_registro": "2026-03-20",
      "prova_nome": "25m Crawl",
      "distancia_metros": 25,
      "estilo": "Crawl",
      "unidade_medida": "tempo"
    }
  ]
}
```

> `melhores` contém apenas 1 registro por prova (o melhor tempo ou maior valor).

---

### Registrar Meu Recorde
```
POST /mobile/recordes
Content-Type: application/json
```
**Body:**
```json
{
  "prova_id": 1,
  "tempo_segundos": 29.80,
  "data_registro": "2026-03-31",
  "observacoes": "Novo PR!"
}
```
Campos obrigatórios: `prova_id`, `data_registro`, (`tempo_segundos` ou `valor`)

> Automaticamente registra com `origem = "aluno"` e `aluno_id` do usuário logado.

**Resposta 201:**
```json
{
  "success": true,
  "message": "Recorde registrado com sucesso",
  "recorde": { ... }
}
```

---

### Atualizar Meu Recorde
```
PUT /mobile/recordes/{id}
Content-Type: application/json
```
**Body:**
```json
{
  "prova_id": 1,
  "tempo_segundos": 28.50,
  "data_registro": "2026-03-31",
  "observacoes": "Corrigi o tempo"
}
```

> Só permite editar recordes do próprio aluno com `origem = "aluno"`.

**Resposta 200:**
```json
{
  "success": true,
  "message": "Recorde atualizado com sucesso",
  "recorde": { ... }
}
```

---

### Excluir Meu Recorde
```
DELETE /mobile/recordes/{id}
```

> Só permite excluir recordes do próprio aluno com `origem = "aluno"`.

**Resposta 200:**
```json
{
  "success": true,
  "message": "Recorde excluído com sucesso"
}
```

---

### Ranking por Prova (Mobile)
```
GET /mobile/recordes/ranking/{provaId}
```
Query params:
- `limit=50` — quantidade máxima (default: 50, max: 100)

**Resposta 200:**
```json
{
  "success": true,
  "prova": {
    "id": 1,
    "nome": "25m Crawl",
    "unidade_medida": "tempo"
  },
  "ranking": [
    {
      "aluno_id": 42,
      "aluno_nome": "João Silva",
      "melhor_tempo": "25.30",
      "melhor_valor": null,
      "data_recorde": "2026-03-20",
      "unidade_medida": "tempo"
    }
  ]
}
```

---

### Recordes da Escola
```
GET /mobile/recordes/escola
```
Query params:
- `prova_id=1` — filtrar por prova (opcional)
- `modalidade_id=3` — filtrar por modalidade (opcional)

Retorna recordes registrados pelo professor/admin (`origem = "escola"`).

**Resposta 200:**
```json
{
  "success": true,
  "recordes": [
    {
      "id": 10,
      "aluno_id": 42,
      "prova_id": 1,
      "tempo_segundos": "24.50",
      "data_registro": "2026-03-28",
      "observacoes": "Recorde da escola",
      "origem": "escola",
      "prova_nome": "25m Crawl",
      "aluno_nome": "João Silva",
      "registrado_por_nome": "Prof. Carlos"
    }
  ]
}
```

---

## Códigos de Erro

| Status | Descrição |
|---|---|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 404 | Recurso não encontrado (prova, recorde, aluno) |
| 422 | Validação falhou (campo obrigatório faltando) |
| 500 | Erro interno do servidor |

---

## Formatação de Tempo (sugestão para o front)

O campo `tempo_segundos` armazena o tempo em **segundos com centésimos** (ex: `92.45` = 1min 32seg 45cent).

Exemplo de formatação:

```javascript
function formatarTempo(segundos) {
  if (!segundos) return '--';
  const s = parseFloat(segundos);
  const min = Math.floor(s / 60);
  const sec = Math.floor(s % 60);
  const cent = Math.round((s % 1) * 100);
  
  if (min > 0) {
    return `${min}:${String(sec).padStart(2, '0')}.${String(cent).padStart(2, '0')}`;
  }
  return `${sec}.${String(cent).padStart(2, '0')}s`;
}

// formatarTempo(92.45) → "1:32.45"
// formatarTempo(25.30) → "25.30s"
```

## Input de Tempo (sugestão para o front)

Para facilitar o input, sugere-se um campo com máscara `MM:SS.cc` ou apenas `SS.cc`:

```javascript
function tempoParaSegundos(input) {
  // Aceita "1:32.45" ou "92.45"
  if (input.includes(':')) {
    const [min, rest] = input.split(':');
    return parseInt(min) * 60 + parseFloat(rest);
  }
  return parseFloat(input);
}
```

---

## Provas Pré-cadastradas

As seguintes provas são criadas automaticamente para cada escola, associadas à modalidade **Natação** (`modalidade_id` vinculado automaticamente caso a modalidade exista):

| Prova | Distância | Estilo | Unidade |
|---|---|---|---|
| 25m Crawl | 25m | Crawl | tempo |
| 50m Crawl | 50m | Crawl | tempo |
| 100m Crawl | 100m | Crawl | tempo |
| 200m Crawl | 200m | Crawl | tempo |
| 25m Costas | 25m | Costas | tempo |
| 50m Costas | 50m | Costas | tempo |
| 25m Peito | 25m | Peito | tempo |
| 50m Peito | 50m | Peito | tempo |
| 25m Borboleta | 25m | Borboleta | tempo |
| 50m Borboleta | 50m | Borboleta | tempo |

O admin pode criar provas adicionais pelo painel, vinculando-as a qualquer modalidade cadastrada.
