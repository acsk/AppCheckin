# Sistema de Créditos e Alteração de Plano — Guia para o Frontend

> Documento de referência para integração frontend com a API de créditos de alunos e alteração de plano de matrícula.

---

## 1. Visão Geral

O sistema permite:
- **Alterar o plano** de uma matrícula existente (upgrade, downgrade ou renovação)
- **Gerar crédito automático** ao trocar de plano (abatendo o valor já pago)
- **Criar crédito manual** para um aluno (cortesia, ajuste, etc.)
- **Consultar saldo de créditos** de um aluno
- **Cancelar créditos** que ainda não foram utilizados

---

## 2. Estrutura do Crédito (objeto retornado pela API)

```typescript
interface CreditoAluno {
  id: number;
  tenant_id: number;
  aluno_id: number;
  matricula_origem_id: number | null;   // matrícula que gerou o crédito
  pagamento_origem_id: number | null;   // pagamento que gerou o crédito
  valor: string;                        // "150.00" — valor total do crédito
  valor_utilizado: string;              // "150.00" — quanto já foi consumido
  saldo: string;                        // "0.00" — campo calculado (valor - valor_utilizado)
  motivo: string | null;
  status_credito_id: number;            // FK para status_creditos_aluno
  status: string;                       // código do status ("ativo", "utilizado", "cancelado")
  status_nome: string;                  // nome de exibição ("Ativo", "Utilizado", "Cancelado")
  criado_por: number | null;            // ID do admin que criou
  created_at: string;                   // "2026-03-27 10:00:00"
  updated_at: string;
}
```

### Tabela `status_creditos_aluno`

| id | codigo | nome |
|----|--------|------|
| 1 | `ativo` | Ativo |
| 2 | `utilizado` | Utilizado |
| 3 | `cancelado` | Cancelado |

| Status | Significado |
|--------|-------------|
| 1 — `ativo` | Crédito com saldo disponível |
| 2 — `utilizado` | Crédito totalmente consumido (saldo = 0) |
| 3 — `cancelado` | Crédito cancelado pelo admin |

---

## 3. Endpoints de Créditos

### 3.1 Listar créditos de um aluno

```
GET /admin/alunos/{alunoId}/creditos
```

**Response** — array de `CreditoAluno`:
```json
[
  {
    "id": 1,
    "tenant_id": 1,
    "aluno_id": 42,
    "matricula_origem_id": 161,
    "pagamento_origem_id": 85,
    "valor": "150.00",
    "valor_utilizado": "150.00",
    "saldo": "0.00",
    "motivo": "Crédito do plano anterior (pagamento de R$150,00 em 27/03/2026)",
    "status_credito_id": 2,
    "status": "utilizado",
    "status_nome": "Utilizado",
    "criado_por": 1,
    "created_at": "2026-03-27 10:00:00",
    "updated_at": "2026-03-27 10:00:00"
  },
  {
    "id": 2,
    "tenant_id": 1,
    "aluno_id": 42,
    "matricula_origem_id": null,
    "pagamento_origem_id": null,
    "valor": "50.00",
    "valor_utilizado": "0.00",
    "saldo": "50.00",
    "motivo": "Cortesia por indicação",
    "status_credito_id": 1,
    "status": "ativo",
    "status_nome": "Ativo",
    "criado_por": 1,
    "created_at": "2026-03-27 12:00:00",
    "updated_at": "2026-03-27 12:00:00"
  }
]
```

### 3.2 Consultar saldo de créditos

```
GET /admin/alunos/{alunoId}/creditos/saldo
```

**Response:**
```json
{
  "saldo_total": 50.00,
  "creditos_ativos": [
    {
      "id": 2,
      "valor": "50.00",
      "valor_utilizado": "0.00",
      "saldo": "50.00",
      "motivo": "Cortesia por indicação",
      "status_credito_id": 1,
      "status": "ativo",
      "status_nome": "Ativo",
      "created_at": "2026-03-27 12:00:00"
    }
  ]
}
```

### 3.3 Criar crédito manual

```
POST /admin/alunos/{alunoId}/creditos
```

**Request:**
```json
{
  "valor": 100.00,
  "motivo": "Cortesia por indicação",
  "matricula_origem_id": 161
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `valor` | number | Sim | Valor do crédito (> 0) |
| `motivo` | string | Não | Motivo/descrição (default: "Crédito manual") |
| `matricula_origem_id` | number | Não | Matrícula relacionada |
| `pagamento_origem_id` | number | Não | Pagamento relacionado |

**Response (201):**
```json
{
  "message": "Crédito criado com sucesso",
  "credito": {
    "id": 3,
    "tenant_id": 1,
    "aluno_id": 42,
    "valor": "100.00",
    "valor_utilizado": "0.00",
    "motivo": "Cortesia por indicação",
    "status_credito_id": 1,
    "status": "ativo",
    "status_nome": "Ativo",
    "criado_por": 1,
    "created_at": "2026-03-27 14:00:00"
  }
}
```

**Erro (422):**
```json
{ "error": "valor é obrigatório e deve ser maior que zero" }
```

### 3.4 Cancelar crédito

```
DELETE /admin/creditos/{creditoId}
```

Só pode cancelar créditos com status `ativo`. Créditos já `utilizado` ou `cancelado` retornam 404.

**Response (200):**
```json
{ "message": "Crédito cancelado com sucesso" }
```

**Erro (404):**
```json
{ "error": "Crédito não encontrado ou já utilizado/cancelado" }
```

---

## 4. Alterar Plano da Matrícula

```
POST /admin/matriculas/{matriculaId}/alterar-plano
```

### 4.1 Parâmetros do Request

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `plano_id` | number | Sim | ID do novo plano |
| `plano_ciclo_id` | number | Não | ID do ciclo do novo plano |
| `data_inicio` | string | Não | Data de início (`YYYY-MM-DD`). Default: hoje |
| `dia_vencimento` | number | Não | Dia de vencimento (1-31). Se omitido, mantém o atual |
| `abater_plano_anterior` | boolean | Não | Se `true`, usa o **valor cheio** do plano/ciclo atual como crédito para abater do novo plano |
| `abater_pagamento_anterior` | boolean | Não | Se `true`, gera crédito **proporcional** aos dias restantes do ciclo atual |
| `usar_credito_existente` | boolean | Não | Se `true`, usa créditos ativos existentes do aluno (saldo > 0) para abater adicionalmente |
| `credito` | number | Não | Valor de crédito manual a aplicar na 1ª parcela |
| `motivo_credito` | string | Não | Motivo do crédito (gerado automaticamente se usar `abater_plano_anterior`/`abater_pagamento_anterior`) |
| `valor` | number | Não | Override do valor do plano/ciclo |
| `observacoes` | string | Não | Observações sobre a alteração |

> **Importante:** `abater_plano_anterior`, `abater_pagamento_anterior` e `credito` são **mutuamente exclusivos** — use apenas um.
> `usar_credito_existente` pode ser **combinado** com qualquer uma das opções acima para somar créditos existentes.

### 4.2 Response de Sucesso (200)

```json
{
  "message": "Plano alterado com sucesso",
  "matricula": {
    "id": 161,
    "plano_id": 10,
    "plano_ciclo_id": 67,
    "valor": "240.00",
    "status_codigo": "pendente",
    "plano_nome": "Natação 4x por Semana"
  },
  "plano_anterior": "Natação 3x por Semana",
  "plano_novo": "Natação 4x por Semana",
  "parcelas_canceladas": 1,
  "novo_pagamento_id": 85,
  "valor_parcela": 90.00,
  "credito": {
    "credito_gerado_id": 1,
    "credito_gerado_valor": 150.00,
    "creditos_existentes_usados": [],
    "credito_existente_utilizado": 0.00,
    "total_aplicado": 150.00,
    "saldo_creditos_restante": 0.00,
    "motivo": "Crédito do plano anterior (Natação 3x por Semana - R$150,00)"
  },
  "motivo": "upgrade"
}
```

> **O campo `credito` será `null` se não foi aplicado nenhum crédito.**

#### Campos do objeto `credito`

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `credito_gerado_id` | number\|null | ID do novo crédito gerado (se `abater_plano_anterior`, `abater_pagamento_anterior` ou `credito` foi usado) |
| `credito_gerado_valor` | number | Valor do crédito gerado (0 se nenhum novo crédito) |
| `creditos_existentes_usados` | array | Lista de créditos existentes consumidos `[{id, valor_usado}]` (quando `usar_credito_existente: true`) |
| `credito_existente_utilizado` | number | Total de créditos existentes que foram consumidos |
| `total_aplicado` | number | Total de crédito aplicado na parcela (gerado + existentes) |
| `saldo_creditos_restante` | number | Saldo de créditos ativos do aluno após a operação |
| `motivo` | string | Descrição/motivo do crédito |

### 4.3 Erros Possíveis

| HTTP | Condição | Resposta |
|------|----------|----------|
| 422 | `plano_id` não informado | `{ "error": "plano_id é obrigatório" }` |
| 404 | Matrícula não encontrada | `{ "error": "Matrícula não encontrada" }` |
| 400 | Matrícula finalizada | `{ "error": "Não é possível alterar plano de matrícula finalizada" }` |
| 404 | Plano não encontrado | `{ "error": "Novo plano não encontrado" }` |
| 404 | Ciclo inválido | `{ "error": "Ciclo não encontrado ou não pertence ao plano informado" }` |
| 400 | Mesmo plano/ciclo | `{ "error": "O plano e ciclo selecionados são iguais aos atuais" }` |
| 500 | Erro interno | `{ "error": "Erro ao alterar plano: ..." }` |

---

## 5. Casos de Uso — Fluxos de Tela

### Caso 1: Abater valor cheio do plano anterior

**Cenário:** Aluno no plano Bimestral R$120 quer ir para Quadrimestral R$200. Admin quer usar o valor cheio do plano atual como crédito.

**Fluxo na tela:**
1. Admin abre a tela de detalhe da matrícula
2. Clica em "Alterar Plano"
3. Seleciona o novo plano e ciclo
4. A tela mostra o valor do novo plano: **R$200,00**
5. Admin marca a opção **"Usar plano anterior como crédito"** (`abater_plano_anterior: true`)
6. A tela mostra preview: *"Crédito de R$120,00 será aplicado. Primeira parcela: R$80,00"*
7. Admin confirma
8. API retorna sucesso com `credito.total_aplicado = 120.00` e `valor_parcela = 80.00`
9. Tela mostra mensagem de sucesso: *"Plano alterado! Primeira parcela de R$80,00 (crédito de R$120,00 aplicado)"*

**Request enviado:**
```json
{
  "plano_id": 10,
  "plano_ciclo_id": 67,
  "abater_plano_anterior": true
}
```

### Caso 1b: Abater proporcional (dias restantes do ciclo)

**Cenário:** Aluno no plano Bimestral R$120 (60 dias) quer ir para Quadrimestral R$200. Restam 30 dias no ciclo atual. Admin quer gerar crédito proporcional.

**Cálculo proporcional:**
- Valor por dia = R$120 / 60 dias = R$2,00/dia
- Crédito = R$2,00 × 30 dias restantes = **R$60,00**

**Fluxo na tela:**
1. Admin marca a opção **"Abater pagamento proporcional"** (`abater_pagamento_anterior: true`)
2. Preview: *"Crédito proporcional de R$60,00 (30 dias restantes). Primeira parcela: R$140,00"*
3. API retorna `credito.credito_gerado_valor = 60.00` e `valor_parcela = 140.00`

**Request enviado:**
```json
{
  "plano_id": 10,
  "plano_ciclo_id": 67,
  "abater_pagamento_anterior": true
}
```

> **Nota:** Se o ciclo já estiver vencido, o sistema busca o último pagamento pago como fallback.

### Caso 2: Crédito manual (cortesia, ajuste)

**Cenário:** Admin quer dar um crédito de R$50 para o aluno por indicação.

**Fluxo na tela:**
1. Admin abre o perfil do aluno
2. Vai na aba/seção "Créditos"
3. Clica em "Adicionar Crédito"
4. Preenche: valor = 50, motivo = "Cortesia por indicação"
5. Confirma
6. API retorna o crédito criado
7. Tela atualiza a lista de créditos e o saldo

**Request:**
```json
POST /admin/alunos/42/creditos
{
  "valor": 50,
  "motivo": "Cortesia por indicação"
}
```

### Caso 3: Alterar plano com crédito manual

**Cenário:** Admin quer dar R$100 de desconto na troca de plano (sem ser baseado em pagamento anterior).

**Fluxo na tela:**
1. Admin abre "Alterar Plano"
2. Seleciona novo plano (R$240)
3. Em vez de marcar "Abater último pagamento", digita no campo **"Crédito"**: `100`
4. Opcionalmente preenche o motivo: "Acordo comercial"
5. Preview: *"Crédito de R$100,00. Primeira parcela: R$140,00"*
6. Confirma

**Request:**
```json
{
  "plano_id": 10,
  "plano_ciclo_id": 67,
  "credito": 100,
  "motivo_credito": "Acordo comercial"
}
```

### Caso 4: Troca simples (sem crédito)

**Cenário:** Aluno quer mudar de plano sem nenhum desconto/crédito.

**Request:**
```json
{
  "plano_id": 10,
  "plano_ciclo_id": 67
}
```

Response terá `credito: null` e `valor_parcela` = valor cheio do ciclo.

### Caso 5: Crédito maior que a parcela (saldo restante)

**Cenário:** Aluno pagou R$300 no plano anterior, novo plano custa R$200.

**O que acontece:**
- Crédito gerado: R$300
- Valor aplicado na parcela: R$200 (valor da parcela)
- Saldo restante: R$100 (fica disponível no crédito)
- Valor da parcela: R$0,00

**Response terá:**
```json
{
  "valor_parcela": 0.00,
  "credito": {
    "credito_gerado_id": 5,
    "credito_gerado_valor": 300.00,
    "creditos_existentes_usados": [],
    "credito_existente_utilizado": 0.00,
    "total_aplicado": 200.00,
    "saldo_creditos_restante": 100.00,
    "motivo": "..."
  }
}
```

> O saldo de R$100 fica como crédito `ativo` do aluno e pode ser consultado via `GET /admin/alunos/{id}/creditos/saldo`.

### Caso 7: Combinar crédito existente com plano anterior

**Cenário:** Aluno tem R$50 de crédito ativo de operação anterior. Agora está no plano R$120 e quer ir para R$200. Admin quer abater o valor cheio do plano + usar o crédito existente.

**O que acontece:**
- Crédito gerado (plano anterior): R$120
- Crédito existente utilizado: R$50
- Total aplicado: R$170
- Valor da parcela: R$200 - R$170 = **R$30,00**

**Request:**
```json
{
  "plano_id": 10,
  "plano_ciclo_id": 67,
  "abater_plano_anterior": true,
  "usar_credito_existente": true
}
```

**Response terá:**
```json
{
  "valor_parcela": 30.00,
  "credito": {
    "credito_gerado_id": 8,
    "credito_gerado_valor": 120.00,
    "creditos_existentes_usados": [{"id": 2, "valor_usado": 50.00}],
    "credito_existente_utilizado": 50.00,
    "total_aplicado": 170.00,
    "saldo_creditos_restante": 0.00,
    "motivo": "Crédito do plano anterior (Bimestral - R$120,00)"
  }
}
```

### Caso 6: Cancelar crédito

**Cenário:** Admin criou um crédito por engano e quer cancelar.

**Fluxo:**
1. Na lista de créditos do aluno, crédito aparece como `ativo` com saldo R$50
2. Admin clica em "Cancelar"
3. Confirmação: *"Tem certeza que deseja cancelar este crédito de R$50,00?"*
4. `DELETE /admin/creditos/2`
5. Crédito muda para status `cancelado`

> **Só pode cancelar créditos com status `ativo`.** Créditos `utilizado` não podem ser cancelados.

---

## 6. Sugestão de Componentes de Tela

### Na tela de Detalhe da Matrícula

- **Botão "Alterar Plano"** → abre modal/tela
  - Select de plano + ciclo
  - Exibir valor do novo plano
  - **Radio ou Select de crédito** (mutuamente exclusivos):
    - "Usar plano anterior como crédito" (`abater_plano_anterior`) — valor cheio do plano/ciclo atual
    - "Abater proporcional (dias restantes)" (`abater_pagamento_anterior`) — crédito proporcional
    - "Crédito manual" (`credito`) — campo numérico livre
    - "Sem crédito" — nenhuma das opções
  - Checkbox: "Usar créditos existentes do aluno" (`usar_credito_existente`) — pode ser combinado com qualquer opção acima
    - Mostrar saldo disponível: *"Saldo disponível: R$50,00"*
  - Campo texto: "Motivo do crédito" (opcional)
  - Preview do valor da primeira parcela: `max(0, valorPlano - totalCredito)`
  - Botão confirmar

### Na tela de Detalhe/Perfil do Aluno

- **Seção/Aba "Créditos"**
  - Card com saldo total: `GET /admin/alunos/{id}/creditos/saldo` → `saldo_total`
  - Lista de créditos: `GET /admin/alunos/{id}/creditos`
    - Cada item mostra: valor, saldo, motivo, status (badge colorido), data
    - Botão "Cancelar" (só para status `ativo`)
  - Botão "Adicionar Crédito" → modal com campos: valor, motivo

### Badges de Status do Crédito

| Status | Cor sugerida | Label |
|--------|-------------|-------|
| `ativo` | verde | Ativo |
| `utilizado` | cinza | Utilizado |
| `cancelado` | vermelho | Cancelado |

### Na parcela (pagamentos_plano)

Se a parcela tem `credito_id` e `credito_aplicado`, exibir informação:
- *"Crédito de R$150,00 aplicado nesta parcela"*

---

## 7. Regras de Negócio (resumo)

| Regra | Detalhe |
|-------|---------|
| Matrícula `finalizada` | Não pode alterar plano (HTTP 400) |
| Mesmo plano+ciclo | Não permite (HTTP 400) |
| Motivo automático | `upgrade` (valor maior), `downgrade` (menor), `renovacao` (igual) |
| Crédito parcial | Se crédito > parcela, aplica só o necessário e saldo fica ativo |
| Próxima parcela | Sempre valor cheio do plano (crédito NÃO se propaga) |
| Transação | Tudo dentro de transaction — se falhar, nada é alterado |
| `abater_plano_anterior` | Usa o **valor cheio** do plano/ciclo atual (`matricula.valor`) como crédito |
| `abater_pagamento_anterior` | Calcula crédito **proporcional** aos dias restantes do ciclo. Se ciclo vencido, usa último pagamento pago |
| `usar_credito_existente` | Pode ser **combinado** com qualquer opção de crédito. Consome créditos do mais antigo ao mais recente |
| Mutuamente exclusivos | `abater_plano_anterior`, `abater_pagamento_anterior` e `credito` — use apenas **um** |
