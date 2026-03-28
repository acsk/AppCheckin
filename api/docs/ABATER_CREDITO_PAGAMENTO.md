# 💳 ABATIMENTO AUTOMÁTICO DE CRÉDITO NO PAGAMENTO

## 📌 O que foi implementado

O sistema agora **abate automaticamente créditos** quando um aluno troca de plano ou efetua um pagamento. Os créditos são gerenciados pela tabela `creditos_aluno` (com status ativo/utilizado/cancelado).

### Formas de gerar crédito na alteração de plano

| Opção | Parâmetro | Como funciona |
|-------|-----------|---------------|
| **Valor cheio do plano** | `abater_plano_anterior: true` | Usa o valor integral do plano/ciclo atual como crédito |
| **Proporcional (dias restantes)** | `abater_pagamento_anterior: true` | Calcula `(valor / dias_totais) × dias_restantes` |
| **Manual** | `credito: 100` | Valor fixo informado pelo admin |

> `usar_credito_existente: true` pode ser **combinado** com qualquer opção acima para somar créditos ativos existentes do aluno.

### Fluxo de Alteração de Plano com Crédito

```
Admin quer trocar plano do aluno
       ↓
POST /admin/matriculas/{id}/alterar-plano
  { abater_plano_anterior: true, usar_credito_existente: true }
       ↓
Sistema calcula:
  1. Crédito do plano anterior (valor cheio ou proporcional)
  2. Créditos existentes do aluno (saldo > 0)
  3. Total = crédito gerado + créditos existentes
       ↓
Aplica na 1ª parcela:
  valor_parcela = max(0, valorNovoPlano - totalCredito)
       ↓
Registra tudo na tabela creditos_aluno
       ↓
Retorna response com detalhes completos
```

---

## 🔄 Como Funciona

### 1️⃣ Quando Confirmar um Pagamento

Chamar o endpoint padrão:
```bash
POST /admin/pagamentos-plano/{id}/confirmar
```

**Exemplo com cURL:**
```bash
curl -X POST http://localhost:8084/admin/pagamentos-plano/27/confirmar \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d '{
    "data_pagamento": "2026-01-11",
    "forma_pagamento_id": 1,
    "observacoes": "Pagamento via cartão"
  }'
```

### 2️⃣ Sistema Processa Automaticamente

Nos bastidores, o sistema:

1. **Busca créditos ativos** do aluno via `creditos_aluno`
   ```sql
   SELECT * FROM creditos_aluno 
   WHERE aluno_id = ? AND tenant_id = ? 
   AND status_credito_id = 1  -- ativo
   AND (valor - valor_utilizado) > 0
   ORDER BY created_at ASC
   ```

2. **Gera novo crédito** (se `abater_plano_anterior` ou `abater_pagamento_anterior`)
   - Insere na tabela `creditos_aluno` com o valor calculado
   - Marca quanto foi utilizado na parcela

3. **Consome créditos existentes** (se `usar_credito_existente: true`)
   - Do mais antigo ao mais recente
   - Atualiza `valor_utilizado` de cada crédito
   - Muda status para `utilizado` quando saldo = 0

4. **Calcula valor da parcela**
   - `valor_parcela = max(0, valorNovoPlano - totalCredito)`
   - Registra `credito_id` e `credito_aplicado` na parcela

---

## 📊 Exemplo de Resposta

### ✅ Antes (sem crédito)
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso",
  "pagamento": {
    "id": 27,
    "valor": "150.00",
    "data_vencimento": "2026-02-10",
    "status": "Pago",
    "data_pagamento": "2026-01-11"
  }
}
```

### ✅ Depois (com crédito descontado)
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso",
  "pagamento": {
    "id": 27,
    "valor": "100.00",          // ← Valor atualizado
    "data_vencimento": "2026-02-10",
    "status": "Pago",
    "data_pagamento": "2026-01-11",
    "observacoes": "..., Crédito de R$ 50.00 descontado"
  },
  "credito_aplicado": {
    "valor_original": 150.00,
    "credito_descontado": 50.00,
    "valor_final": 100.00,
    "observacao": "Crédito de R$ 50.00 aplicado. Valor original: R$ 150.00 | Desconto: R$ 50.00 | A pagar: R$ 100.00"
  }
}
```

---

## 🎨 O QUE EXIBIR NO FRONTEND

### 1️⃣ Ao Listar Pagamentos

Mostrar a diferença:
```
PAGAMENTO
├─ Valor Original: R$ 150.00
├─ Desconto (crédito): -R$ 50.00
└─ **Total a Pagar: R$ 100.00** ← Destacar em cor diferente
```

### 2️⃣ Na Tela de Confirmação

Se `credito_aplicado` não for nulo:
```
┌─────────────────────────────────┐
│ ✅ CRÉDITO APLICADO             │
├─────────────────────────────────┤
│ Valor original:  R$ 150.00      │
│ Crédito desc.:   -R$ 50.00      │
│ ───────────────────────────────  │
│ Total a pagar:   R$ 100.00  ✨  │
└─────────────────────────────────┘
```

### 3️⃣ No Resumo do Aluno

Exibir:
```
Créditos Disponíveis:  R$ 50.00  ← Se houver mais
Aplicado neste pag.:   R$ 50.00
Saldo restante:        R$ 0.00
```

---

## 💡 Casos de Uso

### 📖 Caso 1: Crédito Cobre Tudo

```
Aluno tem crédito de R$ 100
Próximo pagamento é R$ 80

Sistema:
- Deduz R$ 80 do crédito
- Saldo de crédito fica: R$ 20
- Pagamento confirmado com valor R$ 0
- Próximo mês cobre com R$ 20 de crédito + R$ 60 em dinheiro
```

### 📖 Caso 2: Crédito Parcial

```
Aluno tem crédito de R$ 30
Próximo pagamento é R$ 150

Sistema:
- Deduz R$ 30 do crédito
- Crédito foi zerado
- Aluno paga R$ 120 em dinheiro
```

### 📖 Caso 3: Sem Crédito

```
Aluno tem 0 de crédito
Próximo pagamento é R$ 150

Sistema:
- Nenhum desconto
- Aluno paga R$ 150 normalmente
- resposta NÃO inclui "credito_aplicado"
```

---

## 🛠 Implementação no Frontend (Vue/React/etc)

### Passo 1: Verificar se tem crédito na resposta

```javascript
async function confirmarPagamento(pagamentoId) {
  const response = await fetch(
    `/admin/pagamentos-plano/${pagamentoId}/confirmar`,
    {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({
        data_pagamento: new Date().toISOString().split('T')[0],
        forma_pagamento_id: formaPagamentoId
      })
    }
  );
  
  const data = await response.json();
  
  // Verificar se teve desconto de crédito
  if (data.credito_aplicado) {
    mostrarMensagem(
      `✅ Crédito de R$ ${data.credito_aplicado.credito_descontado.toFixed(2)} aplicado!`,
      'success'
    );
    
    // Exibir resumo
    console.log('Resumo do pagamento:');
    console.log(`  Valor original: R$ ${data.credito_aplicado.valor_original.toFixed(2)}`);
    console.log(`  Crédito desc.:  -R$ ${data.credito_aplicado.credito_descontado.toFixed(2)}`);
    console.log(`  Total pago:     R$ ${data.credito_aplicado.valor_final.toFixed(2)}`);
  }
  
  return data;
}
```

### Passo 2: Componente React para Mostrar Crédito

```jsx
function PagamentoComCredito({ credito_aplicado }) {
  if (!credito_aplicado) {
    return null; // Sem crédito, não exibir
  }
  
  return (
    <div className="credito-aplicado" style={{ 
      backgroundColor: '#d4edda', 
      border: '1px solid #28a745',
      borderRadius: '4px',
      padding: '15px'
    }}>
      <h4>✅ Crédito Aplicado!</h4>
      <table style={{ width: '100%' }}>
        <tr>
          <td>Valor original:</td>
          <td align="right">
            R$ {credito_aplicado.valor_original.toFixed(2)}
          </td>
        </tr>
        <tr style={{ color: 'green', fontWeight: 'bold' }}>
          <td>Desconto (crédito):</td>
          <td align="right">
            -R$ {credito_aplicado.credito_descontado.toFixed(2)}
          </td>
        </tr>
        <tr style={{ borderTop: '1px solid #28a745', fontWeight: 'bold' }}>
          <td>Total a pagar:</td>
          <td align="right">
            R$ {credito_aplicado.valor_final.toFixed(2)}
          </td>
        </tr>
      </table>
      <p style={{ marginTop: '10px', fontSize: '0.9em', color: '#666' }}>
        {credito_aplicado.observacao}
      </p>
    </div>
  );
}
```

### Passo 3: Vue 3 Composition API

```vue
<script setup>
import { ref } from 'vue'

const creditoAplicado = ref(null)

async function confirmarPagamento(pagamentoId) {
  try {
    const response = await fetch(
      `/admin/pagamentos-plano/${pagamentoId}/confirmar`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token.value}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          data_pagamento: new Date().toISOString().split('T')[0]
        })
      }
    )
    
    const data = await response.json()
    creditoAplicado.value = data.credito_aplicado || null
    
    if (creditoAplicado.value) {
      // Atualizar UI com desconto
      atualizarTela()
    }
  } catch (error) {
    console.error('Erro ao confirmar:', error)
  }
}
</script>

<template>
  <div v-if="creditoAplicado" class="alert alert-success">
    <h5>✅ Crédito Aplicado!</h5>
    <p>Valor original: R$ {{ creditoAplicado.valor_original.toFixed(2) }}</p>
    <p style="color: green; font-weight: bold;">
      Desconto: -R$ {{ creditoAplicado.credito_descontado.toFixed(2) }}
    </p>
    <hr />
    <p style="font-weight: bold;">
      Total a pagar: R$ {{ creditoAplicado.valor_final.toFixed(2) }}
    </p>
  </div>
</template>
```

---

## 🔍 Verificar Créditos Pendentes (Opcional)

Se quiser mostrar créditos ANTES de confirmar:

```bash
GET /admin/usuarios/{id}/pagamentos-plano?status_pagamento_id=1

# Vai listar todos os pagamentos pendentes
# Filtrar valor < 0 para pegar só os créditos
```

---

## ⚙️ Configurações de Banco de Dados

### Forma de Pagamento para Desconto

O sistema usa `forma_pagamento_id = 99` para descontos automáticos.

Se quiser que apareça diferente nos relatórios, adicionar:

```sql
INSERT INTO formas_pagamento (id, nome, ativo) 
VALUES (99, 'Desconto de Crédito', 1)
ON DUPLICATE KEY UPDATE ativo = 1;
```

---

## 📋 Resumo

| Item | Descrição |
|------|-----------|
| **O que muda** | Ao alterar plano, créditos são gerados e aplicados automaticamente na 1ª parcela |
| **Tabela principal** | `creditos_aluno` com status: ativo(1), utilizado(2), cancelado(3) |
| **3 opções de crédito** | `abater_plano_anterior` (cheio), `abater_pagamento_anterior` (proporcional), `credito` (manual) |
| **Combinável** | `usar_credito_existente` soma créditos ativos existentes a qualquer opção |
| **Resposta** | Inclui `credito` com breakdown completo (gerado, existentes usados, total aplicado, saldo) |
| **No BD** | Crédito registrado em `creditos_aluno`, parcela com `credito_id` e `credito_aplicado` |
| **No Front** | Exibir `credito` se não for `null`, mostrar saldo via `GET /admin/alunos/{id}/creditos/saldo` |
| **Referência completa** | Ver `API_ALTERAR_PLANO_MATRICULA.md` para todos os parâmetros e exemplos |

---

## 🎯 Próximas Melhorias

1. ✅ Abater crédito automaticamente ← **FEITO AGORA**
2. ⏳ Mostrar créditos disponíveis ANTES de confirmar
3. ⏳ Dashboard com saldo de créditos por aluno
4. ⏳ Relatório de créditos aplicados por período
5. ⏳ Transferir créditos entre matrículas (mesma modalidade)
