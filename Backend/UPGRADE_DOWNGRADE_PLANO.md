# 📊 UPGRADE/DOWNGRADE DE PLANO COM CÁLCULO PROPORCIONAL

## ✅ O que foi Implementado

### 1️⃣ Função: `calcularProporcionalPlano()`
Calcula a diferença proporcional entre dois planos

**Entrada:**
- `$planoAnterior` - Dados do plano atual
- `$planoNovo` - Dados do novo plano
- `$dataVencimentoAnterior` - Data de vencimento da matrícula atual

**Saída:**
```php
[
    'tipo' => 'upgrade|downgrade|igual',
    'valor' => 50.75,  // Valor da diferença
    'dias_restantes' => 15
]
```

**Lógica:**
1. Calcula dias restantes até vencimento
2. Calcula valor diário de cada plano
3. Multiplica diferença diária pelo número de dias restantes
4. Retorna tipo (upgrade/downgrade/igual) e valor

---

### 2️⃣ Função: `criarPagamentoAjuste()`
Cria automaticamente um pagamento extra para cobrir a diferença

**Comportamento:**
- **UPGRADE**: Cria novo pagamento com a diferença a COBRAR
- **DOWNGRADE**: Cria crédito (valor negativo ou com observação)
- **IGUAL**: Nenhum ajuste

---

### 3️⃣ Integração no Método `criar()`

Após criar a matrícula e o pagamento inicial, o sistema:

1. ✅ Verifica se foi mudança de plano (mesma modalidade)
2. ✅ Calcula proporcional automaticamente
3. ✅ Se houver diferença, cria pagamento de ajuste
4. ✅ Retorna informações na resposta JSON

---

## 📋 Exemplos de Resposta

### ✅ UPGRADE: Plano 2x/semana → 4x/semana

```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": {...},
  "pagamentos": [
    {
      "id": 27,
      "valor": "180.00",
      "data_vencimento": "2026-02-10",
      "status": "Aguardando",
      "observacoes": "Primeiro pagamento da matrícula"
    },
    {
      "id": 28,
      "valor": "25.50",
      "data_vencimento": "2026-01-11",
      "status": "Aguardando",
      "observacoes": "Ajuste de upgrade - Diferença proporcional a cobrar"
    }
  ],
  "total": 205.50,
  "ajuste_plano": {
    "tipo": "upgrade",
    "valor": 25.50,
    "dias_restantes": 15,
    "descricao": "Cobrança proporcional de R$ 25.50 para upgradar o plano"
  }
}
```

### ⬇️ DOWNGRADE: Plano 4x/semana → 2x/semana

```json
{
  "message": "Matrícula realizada com sucesso",
  "matricula": {...},
  "pagamentos": [
    {
      "id": 29,
      "valor": "100.00",
      "data_vencimento": "2026-02-10",
      "status": "Aguardando"
    },
    {
      "id": 30,
      "valor": "40.00",
      "data_vencimento": "2026-01-11",
      "status": "Aguardando",
      "observacoes": "Ajuste de downgrade - Crédito para aplicar"
    }
  ],
  "total": 140.00,
  "ajuste_plano": {
    "tipo": "downgrade",
    "valor": 40.00,
    "dias_restantes": 20,
    "descricao": "Crédito de R$ 40.00 para downgrades de plano"
  }
}
```

### ➡️ MESMO PLANO (sem ajuste)

```json
{
  "ajuste_plano": null
}
```

---

## 🧮 Exemplos de Cálculo

### Cenário 1: UPGRADE
```
Plano Anterior: 2x/semana = R$ 110/mês (30 dias)
Plano Novo:    4x/semana = R$ 180/mês (30 dias)
Dias Restantes: 15 dias

Valor diário antigo: R$ 110 ÷ 30 = R$ 3,67/dia
Valor diário novo:  R$ 180 ÷ 30 = R$ 6,00/dia
Diferença/dia:      R$ 6,00 - R$ 3,67 = R$ 2,33/dia
Total:              R$ 2,33 × 15 dias = R$ 34,95 A COBRAR ✓
```

### Cenário 2: DOWNGRADE
```
Plano Anterior: 4x/semana = R$ 180/mês (30 dias)
Plano Novo:    2x/semana = R$ 110/mês (30 dias)
Dias Restantes: 20 dias

Valor diário antigo: R$ 180 ÷ 30 = R$ 6,00/dia
Valor diário novo:  R$ 110 ÷ 30 = R$ 3,67/dia
Diferença/dia:      R$ 3,67 - R$ 6,00 = -R$ 2,33/dia
Total:              R$ 2,33 × 20 dias = R$ 46,60 CRÉDITO ✓
```

---

## 🔄 Fluxo Completo

```
┌─────────────────────────────────────┐
│  User Quer Mudar de Plano           │
│  POST /admin/matriculas             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Validar se é mudança na mesma      │
│  modalidade e se tem atrasos        │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Finalizar matrícula anterior       │
│  Criar nova matrícula               │
│  Criar pagamento inicial            │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  NOVO: Calcular proporcional        │
│  - Dias restantes?                  │
│  - Diferença de valores?            │
│  - É upgrade ou downgrade?          │
└──────────────┬──────────────────────┘
               │
               ▼
        ┌──────────────┐
        │ Há diferença?│
        └────┬──────┬──┘
             │      │
        SIM  │      │ NÃO
             ▼      ▼
   ┌──────────────────┐  Responder
   │ Criar pagamento  │  sem ajuste
   │ de ajuste        │
   └──────────┬───────┘
              │
              ▼
      ┌───────────────┐
      │ Responder com │
      │ ajuste_plano  │
      │ preenchido    │
      └───────────────┘
```

---

## 🧪 Testando a Funcionalidade

### Via Frontend
1. Acesse a tela de Matrículas
2. Editar matrícula de um aluno em plano ativo
3. Trocar para plano diferente (mesma modalidade)
4. Observe a resposta com campo `ajuste_plano`

### Via cURL

```bash
curl -X POST http://localhost:8084/admin/matriculas \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "usuario_id": 11,
    "plano_id": 2,
    "data_inicio": "2026-01-15"
  }' | jq '.ajuste_plano'
```

---

## 💡 Notas Importantes

1. **Créditos em Downgrades**
   - O crédito aparece como um "pagamento negativo" na tabela
   - Será descontado do próximo pagamento ou renovação
   - Pode ser configurado diferente conforme necessário

2. **Validações Mantidas**
   - ✅ Verifica se tem atrasos na matrícula atual
   - ✅ Só aplica se for mesma modalidade
   - ✅ Valida se plano anterior e novo existem

3. **Segurança**
   - ✅ Cálculo usa dados do banco de dados
   - ✅ Valores são arredondados a 2 casas decimais
   - ✅ Registra no histórico

---

## 📚 Arquivos Modificados

- `app/Controllers/MatriculaController.php`
  - ✅ Função `calcularProporcionalPlano()` (novas linhas 303-329)
  - ✅ Função `criarPagamentoAjuste()` (novas linhas 331-351)
  - ✅ Integração no método `criar()` (após linha 287)
  - ✅ Resposta JSON com ajuste (após linha 314)

---

## 🚀 Próximas Melhorias Possíveis

1. Aplicar créditos automaticamente no próximo pagamento
2. Dashboard com resumo de créditos do aluno
3. Relatório de upgrades/downgrades por período
4. Configuração de regras (ex: só permitir upgrade após X dias)
5. Notificação ao aluno sobre ajuste de valor

