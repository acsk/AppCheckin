# API de Auditoria

Base URL: `/admin/auditoria`

Todos os endpoints requerem autenticação (token JWT) e retornam dados do tenant autenticado.

---

## 1. Anomalias de Datas

Verifica inconsistências em matrículas que podem causar erros de check-in, duplicações ou cancelamentos indevidos.

### `GET /admin/auditoria/anomalias-datas`

**Resposta (200):**

```json
{
  "resumo": {
    "total_anomalias": 5,
    "tipos_encontrados": 3
  },
  "anomalias": [
    {
      "tipo": "proxima_data_vencimento_null",
      "descricao": "Matrículas ativas com proxima_data_vencimento NULL",
      "severidade": "alta",
      "total": 2,
      "registros": [
        {
          "matricula_id": 251,
          "aluno_nome": "MARIA CECÍLIA",
          "plano_nome": "Natação 2x/Semana",
          "data_vencimento": "2026-04-10",
          "proxima_data_vencimento": null,
          "status": "ativa"
        }
      ]
    }
  ]
}
```

### Tipos de anomalias retornadas

| tipo | severidade | descricao |
|------|-----------|-----------|
| `proxima_data_vencimento_null` | `alta` | Matrículas ativas com `proxima_data_vencimento` NULL. Pode causar erro no check-in e duplicação de matrícula. |
| `proxima_data_vencimento_desatualizada` | `media` | O campo `proxima_data_vencimento` da matrícula não bate com a data da próxima parcela pendente. Campos extras: `vencimento_matricula`, `proxima_parcela_pendente`. |
| `ativa_vencimento_expirado` | `alta` | Matrícula marcada como ativa mas com vencimento expirado há mais de 5 dias. Campos extras: `vencimento_efetivo`, `dias_vencido`. |
| `cancelada_com_parcelas_futuras` | `alta` | Matrícula cancelada/vencida que ainda tem parcelas futuras pendentes (provável cancelamento indevido). Campos extras: `parcelas_futuras_pendentes`, `proxima_parcela`. |
| `matriculas_duplicadas` | `media` | Mesmo aluno com mais de uma matrícula ativa na mesma modalidade. Campos extras: `matricula_ids` (CSV), `planos`, `total`. |
| `ativa_sem_parcelas` | `media` | Matrícula ativa que não possui nenhuma parcela (exceto canceladas). |

> Somente tipos com registros encontrados são retornados no array `anomalias`. Se não há anomalias, o array vem vazio.

### Estrutura dos registros por tipo

**proxima_data_vencimento_null / ativa_sem_parcelas:**
```
matricula_id, aluno_nome, plano_nome, data_vencimento, proxima_data_vencimento, status
```

**proxima_data_vencimento_desatualizada:**
```
matricula_id, aluno_nome, plano_nome, vencimento_matricula, proxima_parcela_pendente, status
```

**ativa_vencimento_expirado:**
```
matricula_id, aluno_nome, plano_nome, vencimento_efetivo, dias_vencido, status
```

**cancelada_com_parcelas_futuras:**
```
matricula_id, aluno_nome, plano_nome, status, proxima_data_vencimento, data_vencimento, parcelas_futuras_pendentes, proxima_parcela
```

**matriculas_duplicadas:**
```
aluno_nome, modalidade_nome, matricula_ids, planos, total
```

---

## 2. Pagamentos Duplicados (Resumo)

### `GET /admin/auditoria/pagamentos-duplicados`

Retorna grupos de parcelas duplicadas (mesma data de vencimento, mesmo aluno/matrícula/plano).

**Resposta (200):**

```json
{
  "resumo": {
    "total_grupos_duplicados": 3,
    "total_pagamentos_envolvidos": 8
  },
  "grupos": [
    {
      "aluno_id": 45,
      "aluno_nome": "JOÃO SILVA",
      "matricula_id": 120,
      "plano_id": 5,
      "plano_nome": "Musculação Mensal",
      "data_vencimento": "2026-03-15",
      "total_parcelas": 2,
      "ids_pagamentos": "301,302",
      "valores": "89.90,89.90",
      "statuses": "Pago,Aguardando"
    }
  ]
}
```

---

## 3. Pagamentos Duplicados (Detalhe)

### `GET /admin/auditoria/pagamentos-duplicados/detalhe`

Retorna todos os pagamentos individuais que fazem parte de grupos duplicados.

**Query params (opcionais):**

| param | tipo | descricao |
|-------|------|-----------|
| `aluno_id` | int | Filtrar por aluno |
| `matricula_id` | int | Filtrar por matrícula |
| `ano` | int | Filtrar por ano de vencimento |
| `mes` | int | Filtrar por mês de vencimento (1-12) |

**Resposta (200):**

```json
{
  "total": 4,
  "pagamentos": [
    {
      "id": 301,
      "aluno_id": 45,
      "aluno_nome": "JOÃO SILVA",
      "matricula_id": 120,
      "plano_id": 5,
      "plano_nome": "Musculação Mensal",
      "valor": "89.90",
      "data_vencimento": "2026-03-15",
      "data_pagamento": "2026-03-15",
      "status": "Pago",
      "credito_id": null,
      "credito_aplicado": null,
      "observacoes": null,
      "created_at": "2026-02-15 10:30:00"
    }
  ]
}
```

---

## 4. Reparar proxima_data_vencimento

Corrige matrículas ativas onde `proxima_data_vencimento` diverge da próxima parcela pendente.

### `POST /admin/auditoria/reparar-proxima-data-vencimento`

**Query params (opcionais):**

| param | tipo | descricao |
|-------|------|-----------|
| `dry-run` | flag | Se presente, simula sem gravar alterações |

**Resposta (200):**

```json
{
  "dry_run": false,
  "total_divergentes": 3,
  "total_reparados": 3,
  "casos": [
    {
      "matricula_id": 214,
      "aluno_nome": "JESSICA ASSUNÇÃO",
      "valor_atual": "2026-04-08",
      "valor_correto": "2026-05-08"
    }
  ]
}
```

> Com `?dry-run`, `total_reparados` é sempre `0` e `casos` lista as divergências sem aplicar.

---

## 5. Check-ins Acima do Limite Contratado

Detecta alunos que realizaram mais check-ins do que o plano permite no período.

### `GET /admin/auditoria/checkins-acima-do-limite`

**Query params (opcionais):**

| param | tipo | default | descricao |
|-------|------|---------|-----------|
| `ano` | int | ano atual | Ano de referência |
| `mes` | int | mês atual | Mês de referência (1-12) |

**Resposta (200):**

```json
{
  "periodo": {
    "ano": 2026,
    "mes": 3,
    "bonus_cinco_semanas": true
  },
  "resumo": {
    "total_violacoes_mensais": 1,
    "total_violacoes_semanais": 2
  },
  "violacoes_mensais": [
    {
      "aluno_id": 72,
      "aluno_nome": "JOSÉ MURILO",
      "modalidade_id": 2,
      "modalidade": "Natação",
      "plano": "Natação 3x/Semana",
      "limite_mensal": 13,
      "total_checkins": 15,
      "excesso": 2,
      "checkin_ids": "401,402,403,404,405,406,407,408,409,410,411,412,413,414,415"
    }
  ],
  "violacoes_semanais": [
    {
      "aluno_id": 88,
      "aluno_nome": "ANA PAULA",
      "modalidade_id": 2,
      "modalidade": "Natação",
      "plano": "Natação 2x/Semana",
      "semana_ano": 202611,
      "semana_inicio": "2026-03-09",
      "semana_fim": "2026-03-13",
      "limite_semanal": 2,
      "total_checkins": 3,
      "excesso": 1,
      "checkin_ids": "520,521,522"
    }
  ]
}
```

### Lógica de limites

| `permite_reposicao` | Tipo de verificação | Fórmula do limite |
|---------------------|--------------------|--------------------|
| `1` (mensal) | Agrupa por aluno/modalidade no mês inteiro | `checkins_semanais × 4 [+1 se mês tem 5 semanas]` |
| `0` (semanal) | Agrupa por aluno/modalidade/semana | `checkins_semanais [+1 se mês tem 5 semanas]` |

> O bônus de `+1` é aplicado automaticamente quando o mês tem 5 semanas (domingo–sábado). O campo `bonus_cinco_semanas` indica se o bônus está ativo no período consultado.

---

## 6. Check-ins Múltiplos no Mesmo Dia

Detecta alunos que realizaram mais de 1 check-in no mesmo dia (possível fraude ou erro de sistema).

### `GET /admin/auditoria/checkins-multiplos-no-dia`

**Query params (opcionais):**

| param | tipo | default | descricao |
|-------|------|---------|-----------|
| `data_inicio` | Y-m-d | 1º dia do mês atual | Início do intervalo |
| `data_fim` | Y-m-d | hoje | Fim do intervalo |
| `aluno_id` | int | — | Filtrar por aluno específico |
| `modalidade_id` | int | — | Filtrar por modalidade |
| `mesma_modalidade` | `0` ou `1` | `0` | `1` = detecta duplicatas **somente na mesma modalidade no mesmo dia**; `0` = qualquer duplicata no mesmo dia |

**Resposta (200):**

```json
{
  "filtros": {
    "data_inicio": "2026-03-01",
    "data_fim": "2026-03-31",
    "mesma_modalidade": false
  },
  "total": 2,
  "registros": [
    {
      "aluno_id": 55,
      "aluno_nome": "CARLOS HENRIQUE",
      "data": "2026-03-12",
      "modalidade_id": null,
      "modalidade": null,
      "modalidades_do_dia": "Natação | Musculação",
      "total_checkins": 2,
      "checkin_ids": "610,614"
    }
  ]
}
```

> Com `mesma_modalidade=1`, `modalidade_id` e `modalidade` são preenchidos (agrupamento inclui a modalidade). Com `mesma_modalidade=0` esses campos vêm `null` e `modalidades_do_dia` lista todas as modalidades do dia concatenadas.

---

## Erros

Todos os endpoints retornam o seguinte formato em caso de erro (status 500):

```json
{
  "type": "error",
  "message": "Descrição do erro"
}
```
