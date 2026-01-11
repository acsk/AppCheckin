# 📱 GUIA SIMPLES PARA O FRONTEND - ABATER CRÉDITO

## 🎯 Resumo em 3 passos

1. **Identifique o crédito** na lista de pagamentos
2. **Envie o ID do crédito** junto com o pagamento
3. **Pronto!** O backend faz o desconto automaticamente

---

## 📋 PASSO 1: Como Identificar o Crédito

Quando você listar os pagamentos, procure por um com essas características:

```json
{
  "id": 30,                          // ← Use este ID
  "valor": "20.00",
  "status_pagamento_id": 1,          // Status: Aguardando
  "observacoes": "Ajuste de downgrade - Crédito para aplicar",  // ← Texto chave
  "data_pagamento": null             // Ainda não foi aplicado
}
```

### ✅ JavaScript/React para Identificar

```javascript
// Encontrar crédito na lista de pagamentos
const encontrarCredito = (pagamentos) => {
  return pagamentos.find(p => 
    p.observacoes?.includes('downgrade') || 
    p.observacoes?.includes('crédito')
  );
};

// Usar:
const credito = encontrarCredito(pagamentos);
if (credito) {
  console.log(`Crédito encontrado: R$ ${credito.valor}`);
}
```

---

## 💳 PASSO 2: Enviar o Crédito na Solicitação

Ao confirmar um pagamento, **incluir o `credito_id`** no JSON:

### ❌ Antes (SEM crédito):
```javascript
const dados = {
  data_pagamento: "2026-01-11",
  forma_pagamento_id: "2",
  comprovante: "",
  observacoes: ""
};

fetch('/admin/pagamentos-plano/33/confirmar', {
  method: 'POST',
  body: JSON.stringify(dados)
});
```

### ✅ Depois (COM crédito):
```javascript
const dados = {
  data_pagamento: "2026-01-11",
  forma_pagamento_id: "2",
  comprovante: "",
  observacoes: "",
  credito_id: 30  // ← ADICIONAR ISTO!
};

fetch('/admin/pagamentos-plano/33/confirmar', {
  method: 'POST',
  body: JSON.stringify(dados)
});
```

### 🔄 Código Completo (Vue/React)

```javascript
async function confirmarPagamento(pagamentoId, creditoId = null) {
  const dados = {
    data_pagamento: new Date().toISOString().split('T')[0],
    forma_pagamento_id: "2",
    comprovante: "",
    observacoes: ""
  };
  
  // Se tem crédito, adicionar
  if (creditoId) {
    dados.credito_id = creditoId;
  }
  
  try {
    const response = await fetch(
      `/admin/pagamentos-plano/${pagamentoId}/confirmar`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(dados)
      }
    );
    
    const resultado = response.json();
    
    // Se teve crédito aplicado, exibir na tela
    if (resultado.credito_aplicado) {
      console.log(`✅ Crédito de R$ ${resultado.credito_aplicado.credito_descontado} aplicado!`);
      console.log(`Pagou apenas: R$ ${resultado.credito_aplicado.valor_final}`);
    }
    
    return resultado;
  } catch (erro) {
    console.error('Erro ao confirmar pagamento:', erro);
  }
}

// Usar assim:
// 1. Sem crédito
await confirmarPagamento(33);

// 2. Com crédito
await confirmarPagamento(33, 30);
```

---

## 📊 O que Você Verá na Resposta

### ✅ SEM crédito
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso",
  "pagamento": {
    "id": 33,
    "valor": "110.00",
    "status_pagamento_id": 2
  },
  "credito_aplicado": null  // ← null = nada foi descontado
}
```

### ✅ COM crédito
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso",
  "pagamento": {
    "id": 33,
    "valor": "90.00",        // ← Atualizado! (110 - 20 = 90)
    "status_pagamento_id": 2,
    "observacoes": "Crédito de R$ 20.00 descontado"
  },
  "credito_aplicado": {
    "valor_original": 110.00,
    "credito_descontado": 20.00,
    "valor_final": 90.00,
    "observacao": "..."
  }
}
```

---

## 🎨 Como Exibir no HTML/Template

### Vue 3 (Tela de Confirmação)

```vue
<template>
  <div class="modal-body">
    <!-- Mostrar crédito disponível -->
    <div v-if="credito" class="alert alert-info">
      <strong>💳 Crédito Disponível!</strong>
      <p>Você tem R$ {{ credito.valor }} de crédito para usar</p>
      <label>
        <input 
          v-model="usarCredito" 
          type="checkbox"
        />
        Aplicar crédito neste pagamento
      </label>
    </div>

    <!-- Resumo do pagamento -->
    <div class="pagamento-resumo">
      <p>Valor original: R$ {{ pagamento.valor }}</p>
      <p v-if="usarCredito && credito" style="color: green; font-weight: bold;">
        Desconto: -R$ {{ credito.valor }}
      </p>
      <hr />
      <p style="font-size: 1.3em; font-weight: bold;">
        Total: R$ {{ calcularTotal() }}
      </p>
    </div>

    <!-- Botão de confirmação -->
    <button 
      @click="confirmar"
      class="btn btn-primary"
    >
      Confirmar Pagamento
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const credito = ref(null)
const usarCredito = ref(false)
const pagamento = ref({ valor: 110 })

const calcularTotal = () => {
  return usarCredito.value && credito.value
    ? (pagamento.value.valor - credito.value.valor).toFixed(2)
    : pagamento.value.valor
}

const confirmar = async () => {
  const creditoId = usarCredito.value && credito.value ? credito.value.id : null
  
  const resultado = await confirmarPagamento(pagamento.value.id, creditoId)
  
  if (resultado.type === 'success') {
    alert('✅ Pagamento confirmado!')
  }
}
</script>
```

### HTML/Bootstrap Puro

```html
<div id="pagamentoModal" class="modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5>Confirmar Pagamento</h5>
      </div>
      
      <div class="modal-body">
        <!-- Alerta de crédito disponível -->
        <div id="alertaCredito" style="display: none;" class="alert alert-info">
          <strong>💳 Crédito Disponível!</strong>
          <p>Você tem <span id="valorCredito"></span> de crédito</p>
          <label>
            <input id="checkCredito" type="checkbox" />
            Usar crédito neste pagamento
          </label>
        </div>

        <!-- Resumo do pagamento -->
        <table class="table table-sm">
          <tr>
            <td>Valor original:</td>
            <td align="right" id="valorOriginal"></td>
          </tr>
          <tr id="linhaDesconto" style="display: none;">
            <td style="color: green; font-weight: bold;">Desconto:</td>
            <td align="right" style="color: green; font-weight: bold;" id="valorDesconto"></td>
          </tr>
          <tr style="border-top: 2px solid #333;">
            <td style="font-weight: bold;">Total a pagar:</td>
            <td align="right" style="font-weight: bold; font-size: 1.2em;" id="totalPagar"></td>
          </tr>
        </table>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" onclick="confirmar()">
          Confirmar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const creditos = { id: 30, valor: 20 }; // Exemplo
const pagamentoId = 33;
const valorPagamento = 110;

// Mostrar alerta de crédito
if (creditos) {
  document.getElementById('alertaCredito').style.display = 'block';
  document.getElementById('valorCredito').textContent = `R$ ${creditos.valor.toFixed(2)}`;
}

// Atualizar valores ao marcar/desmarcar crédito
document.getElementById('checkCredito').addEventListener('change', function() {
  const temCredito = this.checked;
  const linhaDesconto = document.getElementById('linhaDesconto');
  
  if (temCredito) {
    linhaDesconto.style.display = '';
    document.getElementById('valorDesconto').textContent = `-R$ ${creditos.valor.toFixed(2)}`;
  } else {
    linhaDesconto.style.display = 'none';
  }
  
  atualizarTotal();
});

// Atualizar tela
function atualizarTotal() {
  document.getElementById('valorOriginal').textContent = `R$ ${valorPagamento.toFixed(2)}`;
  
  const temCredito = document.getElementById('checkCredito').checked;
  const total = temCredito ? valorPagamento - creditos.valor : valorPagamento;
  
  document.getElementById('totalPagar').textContent = `R$ ${total.toFixed(2)}`;
}

async function confirmar() {
  const temCredito = document.getElementById('checkCredito').checked;
  const creditoId = temCredito ? creditos.id : null;
  
  const dados = {
    data_pagamento: new Date().toISOString().split('T')[0],
    forma_pagamento_id: "2",
    credito_id: creditoId  // ← Enviar isto!
  };
  
  const response = await fetch(`/admin/pagamentos-plano/${pagamentoId}/confirmar`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  });
  
  const resultado = await response.json();
  
  if (resultado.type === 'success') {
    alert('✅ Pagamento confirmado com sucesso!');
    // Redirecionar ou recarregar
  }
}

// Inicializar
atualizarTotal();
</script>
```

---

## 📋 Checklist para o Frontend

- [ ] Ao listar pagamentos, procurar por créditos (observações com "downgrade" ou "crédito")
- [ ] Exibir checkbox "Usar crédito neste pagamento"
- [ ] Ao marcar, mostrar visualmente o desconto
- [ ] Enviar `credito_id` junto com o pagamento se checkbox estiver marcado
- [ ] Na resposta, verificar se `credito_aplicado` é nulo ou tem dados
- [ ] Se tiver dados, exibir mensagem de sucesso com o valor descontado
- [ ] Atualizar lista de pagamentos (o crédito vai desaparecer ou mudar de status)

---

## 🔍 Exemplo Completo com Requisição Real

### Passo 1: Listar pagamentos
```bash
GET /admin/matriculas/24/pagamentos-plano
```

**Resposta:**
```json
{
  "pagamentos": [
    { "id": 29, "valor": "110", "status_pagamento_id": 2, "observacoes": "" },
    { "id": 30, "valor": "20", "status_pagamento_id": 1, "observacoes": "Ajuste de downgrade - Crédito para aplicar" },  ← Crédito
    { "id": 33, "valor": "110", "status_pagamento_id": 1, "observacoes": "" }  ← Pagar este
  ]
}
```

### Passo 2: Encontrar crédito
```javascript
const credito = pagamentos.find(p => p.observacoes.includes('downgrade'));
// credito = { id: 30, valor: 20, ... }
```

### Passo 3: Confirmar pagamento 33 COM crédito 30
```bash
POST /admin/pagamentos-plano/33/confirmar

{
  "data_pagamento": "2026-01-11",
  "forma_pagamento_id": "2",
  "credito_id": 30  ← Novo campo!
}
```

### Passo 4: Resposta com desconto
```json
{
  "type": "success",
  "pagamento": {
    "id": 33,
    "valor": "90.00",  ← Reduzido de 110 para 90!
    "status_pagamento_id": 2,
    "observacoes": "Crédito de R$ 20.00 descontado"
  },
  "credito_aplicado": {
    "valor_original": 110,
    "credito_descontado": 20,
    "valor_final": 90
  }
}
```

---

## ❓ Perguntas Frequentes

**P: E se o aluno não quiser usar o crédito?**
R: Simples! Não envie o `credito_id`. O pagamento será confirmado normalmente sem desconto.

**P: E se tiver múltiplos créditos?**
R: Por enquanto, o frontend escolhe qual crédito enviar. Pode enviar um por um, ou implementar lógica de enviar múltiplos IDs em um array.

**P: O crédito desaparece depois?**
R: Não, ele muda de status para "Pago" e fica registrado no sistema com a observação "[Aplicado em pagamento #33]".

**P: Como mostrar saldo de crédito?**
R: Listar pagamentos do aluno e somar os com observações que contenham "crédito" e status_pagamento_id = 1.

