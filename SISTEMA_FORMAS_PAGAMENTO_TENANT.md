# Sistema de Formas de Pagamento Multi-Tenant

## Visão Geral
Sistema que permite cada tenant configurar suas próprias formas de pagamento, taxas da operadora/banco e condições de parcelamento (especialmente para cartão de crédito).

---

## Banco de Dados

### Tabela Global: `formas_pagamento`
Mantém os tipos de pagamento disponíveis no sistema (template).

```sql
id                  INT (PK)
nome                VARCHAR(50) - PIX, Cartão, Boleto, etc
descricao           VARCHAR(255)
percentual_desconto DECIMAL(5,2) - Mantido para compatibilidade
ativo               TINYINT(1)
```

### Tabela por Tenant: `tenant_formas_pagamento`
Configurações específicas de cada tenant para cada forma de pagamento.

```sql
id                      INT (PK)
tenant_id               INT (FK → tenants.id)
forma_pagamento_id      INT (FK → formas_pagamento.id)
ativo                   TINYINT(1) - Tenant ativa/desativa esta forma

-- Taxas da Operadora/Banco
taxa_percentual         DECIMAL(5,2) - Ex: 3.99 = 3.99%
taxa_fixa               DECIMAL(10,2) - Ex: 3.50 = R$ 3,50

-- Parcelamento (Cartão de Crédito)
aceita_parcelamento     TINYINT(1) - 1 = aceita parcelar
parcelas_minimas        INT - Mínimo de parcelas
parcelas_maximas        INT - Máximo de parcelas
juros_parcelamento      DECIMAL(5,2) - % ao mês (ex: 1.99)
parcelas_sem_juros      INT - Qtd parcelas sem juros

-- Outros
dias_compensacao        INT - Dias úteis para compensação
valor_minimo            DECIMAL(10,2) - Valor mínimo aceitável
observacoes             TEXT
```

**Relacionamento:** 1 Tenant → N Configurações (uma por forma de pagamento)

---

## API Endpoints

### 1. Listar Configurações do Tenant
**GET** `/admin/formas-pagamento-config`

**Query Params:**
- `apenas_ativas=true` (opcional) - Retorna apenas formas ativas

**Response:**
```json
{
  "formas_pagamento": [
    {
      "id": 1,
      "tenant_id": 4,
      "forma_pagamento_id": 2,
      "forma_pagamento_nome": "PIX",
      "ativo": 1,
      "taxa_percentual": "0.00",
      "taxa_fixa": "0.00",
      "aceita_parcelamento": 0,
      "dias_compensacao": 0,
      "valor_minimo": "0.00"
    },
    {
      "id": 2,
      "tenant_id": 4,
      "forma_pagamento_id": 9,
      "forma_pagamento_nome": "Cartão",
      "ativo": 1,
      "taxa_percentual": "3.99",
      "taxa_fixa": "0.00",
      "aceita_parcelamento": 1,
      "parcelas_maximas": 12,
      "juros_parcelamento": "1.99",
      "parcelas_sem_juros": 3,
      "dias_compensacao": 30,
      "valor_minimo": "0.00"
    }
  ]
}
```

### 2. Buscar Configuração Específica
**GET** `/admin/formas-pagamento-config/{id}`

### 3. Atualizar Configuração
**PUT** `/admin/formas-pagamento-config/{id}`

**Body:**
```json
{
  "ativo": 1,
  "taxa_percentual": 4.99,
  "taxa_fixa": 0.00,
  "aceita_parcelamento": 1,
  "parcelas_minimas": 1,
  "parcelas_maximas": 12,
  "juros_parcelamento": 1.99,
  "parcelas_sem_juros": 3,
  "dias_compensacao": 30,
  "valor_minimo": 10.00,
  "observacoes": "Taxa promocional"
}
```

### 4. Calcular Taxas (sem parcelamento)
**POST** `/admin/formas-pagamento-config/calcular-taxas`

**Body:**
```json
{
  "forma_pagamento_id": 2,
  "valor": 150.00
}
```

**Response:**
```json
{
  "valor_bruto": "150.00",
  "taxa_percentual": "5.99",
  "taxa_fixa": "0.00",
  "valor_taxas": "5.99",
  "valor_liquido": "144.01"
}
```

### 5. Calcular Parcelas (com juros)
**POST** `/admin/formas-pagamento-config/calcular-parcelas`

**Body:**
```json
{
  "forma_pagamento_id": 9,
  "valor": 300.00,
  "parcelas": 6
}
```

**Response (6x - 3 sem juros + 3 com juros):**
```json
{
  "valor_original": "300.00",
  "numero_parcelas": 6,
  "parcelas_sem_juros": 3,
  "aplica_juros": true,
  "juros_percentual": 1.99,
  "taxa_operadora_percentual": 3.99,
  "taxa_operadora_fixa": "0.00",
  "valor_total_taxas": "11.97",
  "valor_total_juros": "18.65",
  "valor_final_total": "330.62",
  "valor_por_parcela": "55.10",
  "descricao_parcelamento": "6x de R$ 55,10 com juros"
}
```

**Response (3x - sem juros):**
```json
{
  "valor_original": "300.00",
  "numero_parcelas": 3,
  "parcelas_sem_juros": 3,
  "aplica_juros": false,
  "juros_percentual": 0.00,
  "taxa_operadora_percentual": 3.99,
  "taxa_operadora_fixa": "0.00",
  "valor_total_taxas": "11.97",
  "valor_total_juros": "0.00",
  "valor_final_total": "311.97",
  "valor_por_parcela": "103.99",
  "descricao_parcelamento": "3x de R$ 103,99 sem juros"
}
```

---

## Exemplos de Configuração

### Academia que Aceita Todas as Formas

**PIX (sem taxas):**
```json
{
  "ativo": true,
  "taxa_percentual": 0.00,
  "taxa_fixa": 0.00,
  "aceita_parcelamento": false,
  "dias_compensacao": 0
}
```

**Cartão de Crédito (com parcelamento):**
```json
{
  "ativo": true,
  "taxa_percentual": 3.99,
  "taxa_fixa": 0.00,
  "aceita_parcelamento": true,
  "parcelas_maximas": 12,
  "juros_parcelamento": 1.99,
  "parcelas_sem_juros": 3,
  "dias_compensacao": 30
}
```

**Boleto (com taxa fixa):**
```json
{
  "ativo": true,
  "taxa_percentual": 0.00,
  "taxa_fixa": 3.50,
  "aceita_parcelamento": false,
  "dias_compensacao": 3,
  "valor_minimo": 10.00
}
```

### Academia que Só Aceita PIX e Dinheiro

```json
{
  "PIX": { "ativo": true },
  "Dinheiro": { "ativo": true },
  "Cartão": { "ativo": false },
  "Boleto": { "ativo": false }
}
```

---

## Lógica de Cálculo

### Taxas Simples (sem parcelamento)
```
Taxa Percentual = (valor × taxa_percentual) / 100
Taxa Fixa = taxa_fixa
Valor Total Taxas = Taxa Percentual + Taxa Fixa
Valor Líquido = Valor Bruto - Valor Total Taxas
```

### Parcelamento com Juros
```
1. Calcular taxas da operadora
   Valor com Taxas = Valor + Taxa Percentual + Taxa Fixa

2. Aplicar juros compostos (se parcelas > parcelas_sem_juros)
   Parcelas com Juros = Número Parcelas - Parcelas Sem Juros
   Valor Final = Valor com Taxas × (1 + taxa_juros)^(Parcelas com Juros)

3. Calcular valor da parcela
   Valor Parcela = Valor Final / Número Parcelas
```

**Exemplo Prático:**
- Valor: R$ 300,00
- Taxa Operadora: 3.99%
- Parcelas: 6x
- Parcelas sem juros: 3x
- Juros: 1.99% ao mês

```
1. Taxa Operadora = 300 × 0.0399 = R$ 11,97
2. Valor com Taxas = 300 + 11,97 = R$ 311,97
3. Parcelas com Juros = 6 - 3 = 3
4. Valor Final = 311,97 × (1.0199)³ = R$ 330,62
5. Valor Parcela = 330,62 / 6 = R$ 55,10
```

---

## Segurança

✅ **Isolamento por Tenant** - Cada tenant vê apenas suas configurações  
✅ **Permissão Admin** - Apenas admins podem configurar  
✅ **Validações** - Taxas, parcelas e valores validados  
✅ **FK Constraints** - Integridade referencial garantida

---

## Arquivos Criados

**Backend:**
- `Backend/database/migrations/042_create_tenant_formas_pagamento.sql`
- `Backend/app/Models/TenantFormaPagamento.php`
- `Backend/app/Controllers/TenantFormaPagamentoController.php`
- `Backend/routes/api.php` (atualizado)

**Documentação:**
- `SISTEMA_FORMAS_PAGAMENTO_TENANT.md` (este arquivo)

---

**Data de Criação**: 06/01/2026  
**Versão**: 1.0.0  
**Status**: ✅ Implementado e Testado
