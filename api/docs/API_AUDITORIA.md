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

## Erros

Todos os endpoints retornam o seguinte formato em caso de erro (status 500):

```json
{
  "type": "error",
  "message": "Descrição do erro"
}
```
