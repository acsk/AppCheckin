# API de Descontos por Matrícula

Gerenciamento de descontos vinculados a matrículas. Os descontos são aplicados **automaticamente** na geração de cada parcela (pagamentos_plano).

---

## Conceitos

| Campo | Descrição |
|---|---|
| `tipo` | `primeira_mensalidade` – aplica-se **somente** na 1ª parcela da matrícula. `recorrente` – aplica-se em **todas** as parcelas dentro da vigência. |
| `valor` | Desconto fixo em R$ (ex: `50.00`). Mutuamente exclusivo com `percentual`. |
| `percentual` | Desconto em % sobre o valor da parcela (ex: `10.00` = 10%). Mutuamente exclusivo com `valor`. |
| `vigencia_inicio` | Data (YYYY-MM-DD) a partir da qual o desconto começa a valer. Default: data atual. |
| `vigencia_fim` | Data (YYYY-MM-DD) final da vigência. **`null` = infinito** – o desconto fica ativo até ser desativado manualmente. |
| `parcelas_restantes` | Quantidade de parcelas ainda disponíveis. Decrementado automaticamente a cada aplicação. **`null` = sem limite** (respeita somente a vigência). Quando chega a `0`, o desconto é desativado automaticamente. |
| `motivo` | Texto obrigatório descrevendo o motivo (ex: "Promoção de inauguração", "Funcionário", "Indicação"). |
| `ativo` | `1` = ativo, `0` = desativado. Descontos inativos não são aplicados. |

### Campos de desconto no pagamento

| Campo em `pagamentos_plano` | Descrição |
|---|---|
| `valor_original` | Valor **cheio** da parcela, antes de qualquer desconto. |
| `valor` | Valor **final** a pagar (= `valor_original - desconto`). É este valor que o aluno efetivamente paga. |
| `desconto` | Soma de todos os descontos aplicados nesta parcela. |
| `motivo_desconto` | Texto concatenado dos motivos (ex: "Funcionário + Indicação"). |

> **Proteção contra dupla-subtração:** ao dar baixa, o sistema verifica se `desconto > 0` antes de tentar aplicar descontos. Pagamentos que já possuem desconto **não** são alterados novamente.

### Rastreabilidade (tabela pivot)

Cada parcela possui registros na tabela `pagamento_desconto_aplicado` que vinculam o pagamento aos descontos específicos de `matricula_descontos`:

| Campo | Tipo | Descrição |
|---|---|---|
| `pagamento_plano_id` | FK | ID do pagamento na `pagamentos_plano` |
| `matricula_desconto_id` | FK | ID do desconto na `matricula_descontos` |
| `valor_desconto` | DECIMAL(10,2) | Quanto este desconto específico abateu na parcela |

Isso permite saber **exatamente quais descontos** foram aplicados em cada parcela e quanto cada um contribuiu.

### Regras de aplicação automática

- Ao gerar uma parcela, o sistema busca **todos os descontos ativos** da matrícula cuja vigência abranja a `data_vencimento` da parcela.
- Descontos do tipo `primeira_mensalidade` são considerados **somente** na 1ª parcela.
- Múltiplos descontos são **somados** (valores fixos + percentuais sobre o valor base).
- O desconto total **nunca ultrapassa** o valor da parcela (piso R$ 0,00). Se exceder, os valores são ajustados proporcionalmente.
- O campo `valor_original` armazena o valor cheio; `valor` armazena o valor já com desconto.
- A tabela pivot `pagamento_desconto_aplicado` registra cada desconto individual aplicado.

---

## Endpoints

**Base:** `/admin`  
**Autenticação:** Bearer Token (admin)

---

### 1. Listar descontos de uma matrícula

```
GET /admin/matriculas/{matriculaId}/descontos
```

**Resposta 200:**
```json
{
  "descontos": [
    {
      "id": 1,
      "tenant_id": 1,
      "matricula_id": 42,
      "tipo": "recorrente",
      "valor": null,
      "percentual": "10.00",
      "vigencia_inicio": "2026-04-01",
      "vigencia_fim": null,
      "parcelas_restantes": null,
      "motivo": "Funcionário da academia",
      "ativo": 1,
      "criado_por": 5,
      "created_at": "2026-04-01 10:00:00",
      "updated_at": "2026-04-01 10:00:00"
    }
  ]
}
```

---

### 2. Buscar desconto por ID

```
GET /admin/matricula-descontos/{descontoId}
```

**Resposta 200:**
```json
{
  "desconto": {
    "id": 1,
    "tenant_id": 1,
    "matricula_id": 42,
    "tipo": "recorrente",
    "valor": null,
    "percentual": "10.00",
    "vigencia_inicio": "2026-04-01",
    "vigencia_fim": null,
    "parcelas_restantes": null,
    "motivo": "Funcionário da academia",
    "ativo": 1,
    "criado_por": 5,
    "created_at": "2026-04-01 10:00:00",
    "updated_at": "2026-04-01 10:00:00"
  }
}
```

**Resposta 404:**
```json
{ "type": "error", "message": "Desconto não encontrado" }
```

---

### 3. Criar desconto

```
POST /admin/matriculas/{matriculaId}/descontos
```

**Body (JSON):**

| Campo | Tipo | Obrigatório | Descrição |
|---|---|---|---|
| `tipo` | string | **Sim** | `"primeira_mensalidade"` ou `"recorrente"` |
| `valor` | number | Condicional | Desconto fixo em R$. Obrigatório se não informar `percentual`. |
| `percentual` | number | Condicional | Desconto em %. Obrigatório se não informar `valor`. |
| `motivo` | string | **Sim** | Texto descritivo do motivo |
| `vigencia_inicio` | string (date) | Não | Default: data atual. Formato `YYYY-MM-DD` |
| `vigencia_fim` | string (date) \| null | Não | Default: `null` (infinito). Formato `YYYY-MM-DD` |
| `parcelas_restantes` | integer \| null | Não | Default: `null` (sem limite) |

**Exemplo — Desconto fixo de R$ 50 na 1ª mensalidade:**
```json
{
  "tipo": "primeira_mensalidade",
  "valor": 50.00,
  "motivo": "Promoção de inauguração"
}
```

**Exemplo — Desconto de 15% recorrente por 6 meses:**
```json
{
  "tipo": "recorrente",
  "percentual": 15.00,
  "motivo": "Indicação de aluno",
  "vigencia_inicio": "2026-04-01",
  "vigencia_fim": "2026-09-30"
}
```

**Exemplo — Desconto recorrente de R$ 30 por 3 parcelas (sem data fim):**
```json
{
  "tipo": "recorrente",
  "valor": 30.00,
  "motivo": "Acordo especial",
  "parcelas_restantes": 3
}
```

**Resposta 201:**
```json
{
  "type": "success",
  "message": "Desconto criado com sucesso",
  "desconto": {
    "id": 2,
    "tenant_id": 1,
    "matricula_id": 42,
    "tipo": "recorrente",
    "valor": "30.00",
    "percentual": null,
    "vigencia_inicio": "2026-04-01",
    "vigencia_fim": null,
    "parcelas_restantes": 3,
    "motivo": "Acordo especial",
    "ativo": 1,
    "criado_por": 5,
    "created_at": "2026-04-01 10:00:00",
    "updated_at": "2026-04-01 10:00:00"
  }
}
```

**Resposta 422 (validação):**
```json
{
  "type": "error",
  "message": "Informe valor (R$) ou percentual (%), Motivo é obrigatório"
}
```

**Resposta 404:**
```json
{ "type": "error", "message": "Matrícula não encontrada" }
```

---

### 4. Atualizar desconto

```
PUT /admin/matricula-descontos/{descontoId}
```

**Body (JSON):** Enviar apenas os campos que deseja alterar.

| Campo | Tipo | Descrição |
|---|---|---|
| `tipo` | string | `"primeira_mensalidade"` ou `"recorrente"` |
| `valor` | number \| null | Se informado, limpa o `percentual` |
| `percentual` | number \| null | Se informado, limpa o `valor` |
| `motivo` | string | Texto do motivo |
| `vigencia_inicio` | string (date) | Início da vigência |
| `vigencia_fim` | string (date) \| null | Fim da vigência (`null` = infinito) |
| `parcelas_restantes` | integer \| null | Parcelas restantes (`null` = sem limite) |
| `ativo` | integer | `1` para reativar, `0` para desativar |

**Exemplo — Estender vigência:**
```json
{
  "vigencia_fim": "2026-12-31"
}
```

**Exemplo — Trocar de percentual para valor fixo:**
```json
{
  "valor": 40.00
}
```

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Desconto atualizado",
  "desconto": { ... }
}
```

---

### 5. Desativar desconto (soft delete)

```
DELETE /admin/matricula-descontos/{descontoId}
```

O desconto **não é excluído** do banco, apenas marcado como `ativo = 0`. Parcelas já geradas com esse desconto mantêm o valor registrado.

**Resposta 200:**
```json
{
  "type": "success",
  "message": "Desconto desativado com sucesso"
}
```

---

## Impacto nos pagamentos existentes

Os campos `valor_original`, `desconto` e `motivo_desconto` existem em `pagamentos_plano`. Ao listar pagamentos de uma matrícula (`GET /admin/matriculas/{id}/pagamentos`), cada parcela retorna:

```json
{
  "id": 100,
  "valor_original": 200.00,
  "valor": 170.00,
  "desconto": "30.00",
  "motivo_desconto": "Acordo especial",
  "data_vencimento": "2026-05-01",
  "status_pagamento_id": 1
}
```

> - `valor_original` = valor cheio do plano/ciclo **antes** do desconto.
> - `valor` = valor **final** a pagar (`valor_original - desconto`). É o que o aluno paga.
> - `desconto` = soma de todos os descontos aplicados.
> - Ao dar baixa, o sistema **não** subtrai o desconto novamente (proteção contra dupla-subtração).

### Detalhes dos descontos aplicados

Para verificar quais descontos compõem o abatimento de uma parcela, consulte a tabela `pagamento_desconto_aplicado`:

```sql
SELECT pda.matricula_desconto_id, pda.valor_desconto, md.motivo, md.tipo
FROM pagamento_desconto_aplicado pda
INNER JOIN matricula_descontos md ON md.id = pda.matricula_desconto_id
WHERE pda.pagamento_plano_id = 100;
```

Exemplo de resultado:

| matricula_desconto_id | valor_desconto | motivo | tipo |
|---|---|---|---|
| 1 | 20.00 | Funcionário | recorrente |
| 3 | 10.00 | Indicação | recorrente |

---

## Migração necessária

Executar a migração para adicionar `valor_original` e criar a tabela pivot:

```bash
php database/migrate_pagamento_desconto_aplicado.php
```

A migração:
1. Adiciona coluna `valor_original` em `pagamentos_plano`
2. Faz backfill dos registros existentes (`valor_original = valor + COALESCE(desconto, 0)`)
3. Cria a tabela `pagamento_desconto_aplicado`

---

## Fluxo sugerido na interface

1. **Tela de matrícula** → Seção "Descontos" listando os descontos ativos.
2. **Botão "Adicionar Desconto"** → Form com campos: tipo (select), valor/percentual (toggle), motivo, vigência início/fim, parcelas restantes.
3. **Cada item** na lista com botões Editar e Desativar.
4. **Na listagem de pagamentos**, exibir:
   - Coluna `Valor Original` (quando diferente de `Valor`)
   - Coluna `Desconto` quando `desconto > 0`
   - O valor destacado com risco no original e o final em evidência (ex: ~~R$ 200~~ **R$ 170**)
5. **Ao dar baixa**, o valor exibido no botão deve ser o `valor` (já com desconto). Não há risco de dupla-subtração.
