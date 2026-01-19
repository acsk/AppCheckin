# ⏮️ DESFAZER CHECK-IN COM VALIDAÇÕES

## 📌 Visão Geral

Novo endpoint para **desfazer check-in** com restrições de horário:
- ✅ Pode desfazer ANTES da aula começar
- ✅ Pode desfazer DURANTE a tolerância (primeiros X minutos)
- ❌ NÃO pode desfazer DEPOIS que a tolerância expirou
- ❌ NÃO pode desfazer DEPOIS que a aula terminou

---

## 🔄 Fluxo

```
Aluno faz check-in
      ↓
Aula tem 10 min de tolerância
      ↓
Aluno pode desfazer dentro desse tempo
      ↓
Se passar da tolerância, NÃO permite mais
```

### Exemplo com Timings

```
08:00 - Início programado da aula
07:50 - Abertura para check-in (10 min antes)
08:10 - ÚLTIMA CHANCE para desfazer (tolerância)
08:11 - ❌ NÃO PODE mais desfazer
09:00 - Fim da aula
```

---

## 🔌 Endpoints

### 1️⃣ Fazer Check-in
```bash
POST /checkin
{
  "horario_id": 123
}
```

**Resposta:**
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 456,
    "usuario_id": 11,
    "horario_id": 123,
    "data_checkin": "2026-01-11 08:05:30"
  }
}
```

---

### 2️⃣ Desfazer Check-in (NOVO)
```bash
DELETE /checkin/{id}/desfazer
```

**Exemplo:**
```bash
DELETE /checkin/456/desfazer
```

---

## 📊 Respostas

### ✅ Sucesso - Check-in Desfeito
```json
{
  "message": "Check-in desfeito com sucesso",
  "checkin_id": 456,
  "horario": {
    "data": "2026-01-11",
    "inicio": "08:00:00",
    "fim": "09:00:00"
  }
}
```

### ❌ Erro - Passou da Tolerância
```json
{
  "error": "Não é possível desfazer o check-in. O prazo expirou (a aula já começou)",
  "horario": {
    "data": "2026-01-11",
    "inicio": "08:00:00",
    "tolerancia_minutos": 10,
    "limite_para_desfazer": "2026-01-11 08:10:00"
  }
}
```

### ❌ Erro - Aula Já Terminou
```json
{
  "error": "Não é possível desfazer o check-in. A aula já terminou",
  "horario": {
    "data": "2026-01-11",
    "inicio": "08:00:00",
    "fim": "09:00:00"
  }
}
```

### ❌ Erro - Sem Permissão
```json
{
  "error": "Você não tem permissão para desfazer este check-in"
}
```

---

## 🎨 Frontend Implementation

### Vue 3 / React - Verificar se pode Desfazer

```javascript
async function desfazerCheckin(checkinId) {
  try {
    const response = await fetch(
      `/checkin/${checkinId}/desfazer`,
      {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      }
    );
    
    const data = await response.json();
    
    if (response.ok) {
      // Sucesso
      alert('✅ Check-in desfeito com sucesso!');
      recarregarListaCheckins();
    } else {
      // Erro
      alert(`❌ ${data.error}`);
      
      // Mostrar limite se disponível
      if (data.horario?.limite_para_desfazer) {
        console.log(`Limite: ${data.horario.limite_para_desfazer}`);
      }
    }
  } catch (erro) {
    console.error('Erro:', erro);
  }
}
```

### Botão Condicional (Vue)

```vue
<template>
  <div class="checkin-item">
    <p>{{ checkin.horario_nome }}</p>
    <p>{{ checkin.data_checkin }}</p>
    
    <button 
      v-if="podeDesfazer(checkin)"
      @click="desfazer(checkin.id)"
      class="btn btn-warning"
    >
      ⏮️ Desfazer
    </button>
    
    <span v-else class="text-danger">
      ❌ Não é possível desfazer
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const podeDesfazer = (checkin) => {
  // Simplesmente mostrar botão, deixar servidor validar
  // Ou calcular se ainda temos tempo
  const agora = new Date();
  const limite = new Date(checkin.limite_desfazer); // Se api retornar
  return agora < limite;
}

const desfazer = async (checkinId) => {
  const response = await fetch(`/checkin/${checkinId}/desfazer`, {
    method: 'DELETE',
    headers: { 'Authorization': `Bearer ${token}` }
  });
  
  const data = await response.json();
  
  if (response.ok) {
    alert('✅ Desfeito!');
  } else {
    alert(`❌ ${data.error}`);
  }
}
</script>
```

### HTML Puro com Bootstrap

```html
<div class="checkin-card">
  <h6>Aula em 11/01/2026 às 08:00</h6>
  <p>Check-in feito em: 08:05:30</p>
  
  <div id="botoes">
    <button 
      id="btnDesfazer"
      class="btn btn-warning btn-sm"
      onclick="desfazerCheckin(456)"
    >
      ⏮️ Desfazer Check-in
    </button>
    
    <span id="mensagemErro" style="display: none;" class="text-danger">
      ❌ Não é mais possível desfazer
    </span>
  </div>
</div>

<script>
async function desfazerCheckin(checkinId) {
  const response = await fetch(`/checkin/${checkinId}/desfazer`, {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${localStorage.getItem('token')}`,
      'Content-Type': 'application/json'
    }
  });
  
  const data = await response.json();
  
  if (response.ok) {
    document.getElementById('botoes').innerHTML = 
      '<p class="text-success">✅ Check-in desfeito com sucesso!</p>';
    // Recarregar lista
    location.reload();
  } else {
    document.getElementById('botoes').innerHTML = 
      `<p class="text-danger">${data.error}</p>`;
  }
}
</script>
```

---

## 🧮 Validações Implementadas

### 1. Verifica Proprietário
```
Aluno A não pode desfazer check-in do Aluno B
```

### 2. Verifica Horário Existe
```
Se a aula foi deletada, não deixa desfazer
```

### 3. Verifica Tolerância
```
Aula: 08:00
Tolerância: 10 min
Limite: 08:10
Às 08:11 → ❌ Não permite
```

### 4. Verifica Fim da Aula
```
Aula termina às 09:00
Às 09:01 → ❌ Não permite
```

---

## 📋 Checklist de Implementação no Frontend

- [ ] Adicionar botão "Desfazer" na lista de check-ins
- [ ] Mostrar apenas se ainda tiver tempo
- [ ] Ao clicar, chamar DELETE `/checkin/{id}/desfazer`
- [ ] Mostrar mensagem de sucesso/erro
- [ ] Se erro, exibir o motivo (passou tolerância / aula terminou)
- [ ] Atualizar lista após sucesso
- [ ] Adicionar ícone ⏮️ no botão

---

## 🔍 Exemplo de Resposta JSON Completa

### Listando Check-ins
```bash
GET /me/checkins
```

**Resposta (exemplo):**
```json
{
  "checkins": [
    {
      "id": 454,
      "usuario_id": 11,
      "horario_id": 123,
      "data_checkin": "2026-01-11 07:55:30",
      "horario": {
        "id": 123,
        "dia_id": 5,
        "data": "2026-01-11",
        "horario_inicio": "08:00:00",
        "horario_fim": "09:00:00",
        "tolerancia_minutos": 10,
        "turma_nome": "Turma A",
        "ativo": true
      }
    },
    {
      "id": 455,
      "usuario_id": 11,
      "horario_id": 124,
      "data_checkin": "2026-01-11 08:05:45",
      "horario": {
        "id": 124,
        "data": "2026-01-11",
        "horario_inicio": "08:00:00",
        "horario_fim": "09:00:00",
        "tolerancia_minutos": 10,
        "turma_nome": "Turma B"
      }
    }
  ]
}
```

---

## 🎯 Casos de Uso

### Caso 1: Desfazer Antes de Começar
```
08:00 - Aula começa
07:58 - Aluno faz check-in
07:59 - Aluno muda de ideia e desfaz
✅ Permitido
```

### Caso 2: Desfazer Durante a Tolerância
```
08:00 - Aula começa
08:05 - Aluno faz check-in
08:08 - Aluno quer desfazer (tolerância 10min)
✅ Permitido (ainda está nos 10 min)
```

### Caso 3: Desfazer Após Tolerância
```
08:00 - Aula começa
08:05 - Aluno faz check-in
08:15 - Aluno tenta desfazer (passou 10 min)
❌ NÃO permitido
"Não é possível desfazer. O prazo expirou"
```

### Caso 4: Desfazer Depois que Aula Termina
```
09:00 - Aula termina
09:05 - Aluno tenta desfazer
❌ NÃO permitido
"A aula já terminou"
```

---

## 📝 Resumo

| Item | Descrição |
|------|-----------|
| **Endpoint** | DELETE /checkin/{id}/desfazer |
| **Requer Auth** | Sim |
| **O que faz** | Remove o check-in (reverte) |
| **Quando permite** | Antes/durante tolerância |
| **Quando nega** | Após tolerância e aula termina |
| **Resposta sucesso** | 200 OK + mensagem |
| **Resposta erro** | 400/403/404 + motivo |
