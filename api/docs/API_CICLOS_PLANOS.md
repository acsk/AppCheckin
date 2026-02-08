# API de Ciclos de Planos

Documentação dos endpoints para gerenciamento de ciclos de planos (mensal, bimestral, trimestral, etc.) e assinaturas recorrentes.

---

## 📋 Sumário

1. [Conceitos](#conceitos)
2. [Endpoints Admin (Painel)](#endpoints-admin-painel)
3. [Endpoints Mobile](#endpoints-mobile)
4. [Objetos de Retorno](#objetos-de-retorno)
5. [Casos de Uso](#casos-de-uso)

---

## Conceitos

### Estrutura de Dados

```
assinatura_frequencias (tabela de referência - fixa)
├── Mensal (1 mês)
├── Bimestral (2 meses)
├── Trimestral (3 meses)
├── Quadrimestral (4 meses)
├── Semestral (6 meses)
└── Anual (12 meses)

plano_ciclos (por tenant/plano)
├── plano_id → FK para planos
├── assinatura_frequencia_id → FK para assinatura_frequencias
├── valor → Valor total do ciclo
├── valor_mensal_equivalente → Calculado automaticamente (valor/meses)
├── desconto_percentual → Desconto em relação ao mensal
└── permite_recorrencia → Se aceita assinatura
```

---

## Endpoints Admin (Painel)

### 1. Listar Frequências de Assinatura Disponíveis

```
GET /admin/assinatura-frequencias
```

**Descrição:** Retorna todas as frequências de assinatura disponíveis no sistema.

**Response 200:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "nome": "Mensal", "codigo": "mensal", "meses": 1, "ordem": 1 },
    { "id": 2, "nome": "Bimestral", "codigo": "bimestral", "meses": 2, "ordem": 2 },
    { "id": 3, "nome": "Trimestral", "codigo": "trimestral", "meses": 3, "ordem": 3 },
    { "id": 4, "nome": "Quadrimestral", "codigo": "quadrimestral", "meses": 4, "ordem": 4 },
    { "id": 5, "nome": "Semestral", "codigo": "semestral", "meses": 6, "ordem": 5 },
    { "id": 6, "nome": "Anual", "codigo": "anual", "meses": 12, "ordem": 6 }
  ]
}
```

---

### 2. Listar Ciclos de um Plano

```
GET /admin/planos/{plano_id}/ciclos
```

**Descrição:** Retorna todos os ciclos cadastrados para um plano específico.

**Response 200:**
```json
{
  "success": true,
  "plano": {
    "id": 1,
    "nome": "3 aulas por semana"
  },
  "ciclos": [
    {
      "id": 1,
      "assinatura_frequencia_id": 1,
      "nome": "Mensal",
      "codigo": "mensal",
      "meses": 1,
      "valor": 150.00,
      "valor_formatado": "R$ 150,00",
      "valor_mensal_equivalente": 150.00,
      "valor_mensal_formatado": "R$ 150,00",
      "desconto_percentual": 0,
      "permite_recorrencia": true,
      "ativo": true
    },
    {
      "id": 2,
      "assinatura_frequencia_id": 2,
      "nome": "Bimestral",
      "codigo": "bimestral",
      "meses": 2,
      "valor": 240.00,
      "valor_formatado": "R$ 240,00",
      "valor_mensal_equivalente": 120.00,
      "valor_mensal_formatado": "R$ 120,00",
      "desconto_percentual": 20.00,
      "permite_recorrencia": true,
      "ativo": true
    }
  ],
  "total": 2
}
```

---

### 3. Criar Ciclo para um Plano

```
POST /admin/planos/{plano_id}/ciclos
```

**Request Body:**
```json
{
  "assinatura_frequencia_id": 2,
  "valor": 240.00,
  "permite_recorrencia": true,
  "ativo": true
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| assinatura_frequencia_id | int | ✅ | ID da frequência de assinatura (ver GET /admin/assinatura-frequencias) |
| valor | decimal | ✅ | Valor total do ciclo |
| permite_recorrencia | bool | ❌ | Se aceita assinatura (default: true) |
| ativo | bool | ❌ | Se está ativo (default: true) |

**Response 201:**
```json
{
  "success": true,
  "message": "Ciclo criado com sucesso",
  "id": 5
}
```

**Response 400 (ciclo já existe):**
```json
{
  "error": "Já existe um ciclo deste tipo para este plano"
}
```

---

### 4. Atualizar Ciclo

```
PUT /admin/planos/{plano_id}/ciclos/{ciclo_id}
```

**Request Body:**
```json
{
  "valor": 230.00,
  "permite_recorrencia": true,
  "ativo": true
}
```

> ⚠️ **Nota:** Não é possível alterar o `assinatura_frequencia_id`. Para mudar a frequência, exclua e crie um novo.

**Response 200:**
```json
{
  "success": true,
  "message": "Ciclo atualizado com sucesso"
}
```

---

### 5. Excluir Ciclo

```
DELETE /admin/planos/{plano_id}/ciclos/{ciclo_id}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Ciclo excluído com sucesso"
}
```

**Response 400 (matrículas vinculadas):**
```json
{
  "error": "Não é possível excluir. Existem 5 matrícula(s) vinculada(s) a este ciclo."
}
```

---

### 6. Gerar Ciclos Automáticos

```
POST /admin/planos/{plano_id}/ciclos/gerar
```

**Descrição:** Gera automaticamente todos os ciclos para um plano, aplicando descontos progressivos.

**Request Body (opcional):**
```json
{
  "desconto_mensal": 0,
  "desconto_bimestral": 10,
  "desconto_trimestral": 15,
  "desconto_quadrimestral": 20,
  "desconto_semestral": 25,
  "desconto_anual": 30
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Ciclos gerados com sucesso",
  "ciclos": [
    {
      "assinatura_frequencia_id": 1,
      "nome": "Mensal",
      "meses": 1,
      "valor": 150.00,
      "desconto": 0,
      "economia": 0
    },
    {
      "assinatura_frequencia_id": 2,
      "nome": "Bimestral",
      "meses": 2,
      "valor": 270.00,
      "desconto": 10,
      "economia": 30.00
    }
  ]
}
```

---

## Endpoints Mobile

### 1. Listar Planos Disponíveis (com Ciclos)

```
GET /mobile/planos-disponiveis
```

**Query Params:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| modalidade_id | int | Filtrar por modalidade (opcional) |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "planos": [
      {
        "id": 1,
        "nome": "3 aulas por semana",
        "descricao": "Plano com 3 aulas semanais",
        "valor": 150.00,
        "valor_formatado": "R$ 150,00",
        "duracao_dias": 30,
        "duracao_texto": "30 dias",
        "checkins_semanais": 3,
        "modalidade": {
          "id": 1,
          "nome": "Natação"
        },
        "is_plano_atual": false,
        "label": null,
        "ciclos": [
          {
            "id": 1,
            "nome": "Mensal",
            "codigo": "mensal",
            "meses": 1,
            "valor": 150.00,
            "valor_formatado": "R$ 150,00",
            "valor_mensal": 150.00,
            "valor_mensal_formatado": "R$ 150,00",
            "desconto_percentual": 0,
            "permite_recorrencia": true,
            "economia": null
          },
          {
            "id": 2,
            "nome": "Bimestral",
            "codigo": "bimestral",
            "meses": 2,
            "valor": 240.00,
            "valor_formatado": "R$ 240,00",
            "valor_mensal": 120.00,
            "valor_mensal_formatado": "R$ 120,00",
            "desconto_percentual": 20,
            "permite_recorrencia": true,
            "economia": "Economize 20%"
          }
        ]
      }
    ],
    "total": 1,
    "plano_atual_id": null
  }
}
```

---

### 2. Comprar Plano com Ciclo

```
POST /mobile/comprar-plano
```

**Request Body:**
```json
{
  "plano_id": 1,
  "plano_ciclo_id": 2,
  "dia_vencimento": 10,
  "tipo_cobranca": "avulso"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| plano_id | int | ✅ | ID do plano |
| plano_ciclo_id | int | ❌ | ID do ciclo escolhido (se não informado, usa valor base do plano) |
| dia_vencimento | int | ❌ | Dia do vencimento (1-31) |
| tipo_cobranca | string | ❌ | "avulso" ou "recorrente" (default: avulso) |

**Response 200:**
```json
{
  "success": true,
  "message": "Matrícula criada com sucesso",
  "data": {
    "matricula_id": 45,
    "plano": "3 aulas por semana",
    "ciclo": "Bimestral",
    "valor": 240.00,
    "payment_url": "https://www.mercadopago.com.br/checkout/..."
  }
}
```

---

### 3. Criar Assinatura Recorrente

```
POST /mobile/assinatura/criar
```

**Request Body:**
```json
{
  "matricula_id": 45,
  "plano_ciclo_id": 2
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Assinatura criada com sucesso",
  "data": {
    "assinatura_id": 12,
    "status": "pending",
    "init_point": "https://www.mercadopago.com.br/subscriptions/...",
    "proxima_cobranca": "2026-04-10"
  }
}
```

---

### 4. Minhas Assinaturas

```
GET /mobile/assinaturas
```

**Response 200:**
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "plano": "3 aulas por semana",
      "ciclo": "Bimestral",
      "status": "authorized",
      "status_label": "Ativa",
      "valor": 240.00,
      "valor_formatado": "R$ 240,00",
      "proxima_cobranca": "2026-04-10",
      "pode_cancelar": true
    }
  ]
}
```

---

### 5. Cancelar Assinatura

```
POST /mobile/assinatura/{assinatura_id}/cancelar
```

**Request Body (opcional):**
```json
{
  "motivo": "Mudança de plano"
}
```

**Response 200:**
```json
{
  "success": true,
  "message": "Assinatura cancelada com sucesso"
}
```

---

## Objetos de Retorno

### AssinaturaFrequencia
```typescript
interface AssinaturaFrequencia {
  id: number;
  nome: string;        // "Mensal", "Bimestral", etc
  codigo: string;      // "mensal", "bimestral", etc
  meses: number;       // 1, 2, 3, 4, 6, 12
  ordem: number;       // Ordem de exibição
  ativo: boolean;
}
```

### PlanoCiclo
```typescript
interface PlanoCiclo {
  id: number;
  assinatura_frequencia_id: number;
  nome: string;                    // Vem de assinatura_frequencias
  codigo: string;                  // Vem de assinatura_frequencias
  meses: number;
  valor: number;                   // Valor total do ciclo
  valor_formatado: string;         // "R$ 240,00"
  valor_mensal_equivalente: number; // valor / meses
  valor_mensal_formatado: string;   // "R$ 120,00"
  desconto_percentual: number;      // 0-100
  permite_recorrencia: boolean;
  ativo: boolean;
  economia?: string | null;         // "Economize 20%" (mobile)
}
```

### Assinatura
```typescript
interface Assinatura {
  id: number;
  matricula_id: number;
  plano_ciclo_id: number;
  status: 'pending' | 'authorized' | 'paused' | 'cancelled' | 'finished';
  status_label: string;
  valor: number;
  valor_formatado: string;
  proxima_cobranca: string;   // "2026-04-10"
  ultima_cobranca?: string;
  pode_cancelar: boolean;
}
```

---

## Casos de Uso

### Caso 1: Admin cadastra ciclos para um plano

```
1. GET /admin/assinatura-frequencias
   → Obter lista de frequências disponíveis

2. POST /admin/planos/{id}/ciclos
   → Criar ciclo mensal com valor R$ 150

3. POST /admin/planos/{id}/ciclos
   → Criar ciclo bimestral com valor R$ 240

4. GET /admin/planos/{id}/ciclos
   → Verificar ciclos criados
```

### Caso 2: Admin gera ciclos automáticos

```
1. POST /admin/planos/{id}/ciclos/gerar
   Body: { "desconto_trimestral": 15, "desconto_semestral": 25 }
   → Sistema calcula valores automaticamente baseado no valor mensal do plano
```

### Caso 3: Aluno escolhe plano no app

```
1. GET /mobile/planos-disponiveis
   → Listar planos com seus ciclos

2. Usuário escolhe "3 aulas/semana" + "Bimestral"

3. POST /mobile/comprar-plano
   Body: { "plano_id": 1, "plano_ciclo_id": 2 }
   → Cria matrícula e retorna link de pagamento
```

### Caso 4: Aluno ativa assinatura recorrente

```
1. POST /mobile/comprar-plano
   Body: { "plano_id": 1, "plano_ciclo_id": 2, "tipo_cobranca": "recorrente" }
   → Cria matrícula com tipo recorrente

2. POST /mobile/assinatura/criar
   Body: { "matricula_id": 45, "plano_ciclo_id": 2 }
   → Cria assinatura no MercadoPago

3. Usuário é redirecionado para autorizar no MP

4. GET /mobile/assinaturas
   → Ver status da assinatura
```

---

## Fluxo Visual

```
┌─────────────────────────────────────────────────────────────────┐
│                         PAINEL ADMIN                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Plano: 3 aulas por semana (R$ 150,00/mês base)                │
│                                                                 │
│  ┌─────────────┬─────────────┬─────────────┬─────────────┐     │
│  │   Mensal    │  Bimestral  │ Trimestral  │   Anual     │     │
│  │   R$ 150    │   R$ 240    │   R$ 400    │   R$ 1.440  │     │
│  │   0% desc   │   20% desc  │   11% desc  │   20% desc  │     │
│  │   [Ativo]   │   [Ativo]   │  [Inativo]  │   [Ativo]   │     │
│  └─────────────┴─────────────┴─────────────┴─────────────┘     │
│                                                                 │
│  [+ Adicionar Ciclo]  [⚡ Gerar Automático]                     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                           APP MOBILE                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌───────────────────────────────────────────────────────┐     │
│  │  3 AULAS POR SEMANA                                   │     │
│  │                                                       │     │
│  │  ○ Mensal        R$ 150,00                           │     │
│  │  ● Bimestral     R$ 240,00  ✨ Economize 20%         │     │
│  │  ○ Anual         R$ 1.440   ✨ Economize 20%         │     │
│  │                                                       │     │
│  │  [ ] Ativar cobrança recorrente                       │     │
│  │                                                       │     │
│  │  [        ASSINAR PLANO - R$ 240,00        ]         │     │
│  └───────────────────────────────────────────────────────┘     │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 400 | Dados inválidos ou ciclo já existe |
| 404 | Plano ou ciclo não encontrado |
| 422 | Validação falhou (campos obrigatórios) |
| 500 | Erro interno do servidor |
