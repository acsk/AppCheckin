# ✅ Novo Endpoint: Listar Contratos/Planos Ativos

**Data:** 10 de janeiro de 2026  
**Status:** ✅ Implementado

---

## 📋 Resumo

Novo endpoint adicionado para permitir que o app mobile consulte o plano/contrato ativo da academia com todas as informações necessárias.

---

## 🔧 O que foi implementado

### 1. **Backend (PHP/Laravel)**

**Arquivo:** [Backend/app/Controllers/MobileController.php](Backend/app/Controllers/MobileController.php)

**Novo método:** `contratos()`

Retorna:
- ✅ Plano ativo (nome, valor, features, limites)
- ✅ Status do contrato (ativo, pendente, vencido, etc)
- ✅ Vigência (datas, dias restantes, percentual de uso)
- ✅ Pagamentos (lista com status de cada parcela)
- ✅ Informações do tenant

**Arquivo:** [Backend/routes/api.php](Backend/routes/api.php)

**Nova rota:**
```
GET /mobile/contratos
```

---

### 2. **Frontend Mobile (React Native/Expo)**

**Arquivo:** [mobile/src/services/mobileService.js](mobile/src/services/mobileService.js)

**Novo método:** `getContratos()`

```javascript
// Usar assim no app:
const contratoData = await mobileService.getContratos();

if (contratoData.data.contrato_ativo) {
  console.log(contratoData.data.contrato_ativo.plano.nome);
  console.log(contratoData.data.contrato_ativo.vigencia.dias_restantes);
}
```

---

### 3. **Documentação**

**Arquivo:** [DOCUMENTACAO_COMPLETA_API.md](DOCUMENTACAO_COMPLETA_API.md)

Adicionado:
- ✅ Seção "Endpoints Mobile" completa
- ✅ Documentação do novo endpoint `GET /mobile/contratos`
- ✅ Exemplos de uso
- ✅ Fluxos de negócio
- ✅ Respostas de sucesso e erro

---

### 4. **Script de Teste**

**Arquivo:** [test_mobile_contratos.sh](test_mobile_contratos.sh)

Para testar rapidamente:
```bash
# Após fazer login, copie o token e execute:
bash test_mobile_contratos.sh "seu_token_aqui"
```

---

## 📊 Dados Retornados

O endpoint retorna um objeto completo com:

```json
{
  "contrato_ativo": {
    "id": 5,
    "plano": {
      "id": 2,
      "nome": "Enterprise",
      "valor": 250.00,
      "max_alunos": 500,
      "max_admins": 10,
      "features": ["relatórios_avançados", "api_integracao", "suporte_24h"]
    },
    "status": {
      "nome": "Ativo",
      "codigo": "ativo"
    },
    "vigencia": {
      "data_inicio": "2026-01-05",
      "data_fim": "2027-01-05",
      "dias_restantes": 360,
      "dias_total": 365,
      "percentual_uso": 1,
      "ativo": true
    },
    "pagamentos": {
      "total": 12,
      "lista": [
        {
          "id": 1,
          "valor": 250.00,
          "data_vencimento": "2026-01-05",
          "data_pagamento": "2026-01-05",
          "status": "Pago",
          "forma_pagamento": "pix"
        }
      ]
    }
  }
}
```

---

## 🎯 Casos de Uso

### Aluno visualizando seu plano
```javascript
const dados = await mobileService.getContratos();
console.log('Seu plano: ' + dados.contrato_ativo.plano.nome);
console.log('Acaba em: ' + dados.contrato_ativo.vigencia.data_fim);
```

### Gestor verificando saúde do contrato
```javascript
const dados = await mobileService.getContratos();
if (dados.contrato_ativo.vigencia.dias_restantes < 30) {
  console.log('⚠️ Atenção: Contrato vence em breve!');
}
```

### Verificando status de pagamentos
```javascript
const pagamentos = dados.contrato_ativo.pagamentos.lista;
const pendentes = pagamentos.filter(p => p.status === 'Aguardando');
if (pendentes.length > 0) {
  console.log(`${pendentes.length} pagamentos aguardando`);
}
```

---

## 🧪 Como Testar

### Opção 1: Com o script bash
```bash
# 1. Login para obter token
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "teste@exemplo.com", "senha": "password123"}'

# 2. Copiar o token da resposta

# 3. Testar o novo endpoint
bash test_mobile_contratos.sh "seu_token_aqui"
```

### Opção 2: Com curl direto
```bash
TOKEN="seu_token_aqui"
curl http://localhost:8080/mobile/contratos \
  -H "Authorization: Bearer $TOKEN"
```

### Opção 3: No app mobile
```javascript
import { mobileService } from '@/services/mobileService';

try {
  const data = await mobileService.getContratos();
  console.log('Contrato ativo:', data.data.contrato_ativo);
} catch (error) {
  console.error('Erro:', error);
}
```

---

## 📝 Notas Importantes

- ✅ Endpoint requer autenticação (bearer token)
- ✅ Respeita multi-tenant (usa o tenant_id do token)
- ✅ Se não houver contrato ativo, retorna `contrato_ativo: null`
- ✅ Calcula automaticamente dias restantes e percentual de uso
- ✅ Inclui informações de todos os pagamentos do contrato
- ✅ Features do plano são retornadas como array (se configuradas)

---

## 🔗 Links Relacionados

- 📄 [Documentação Completa da API](DOCUMENTACAO_COMPLETA_API.md)
- 📄 [Login Multi-tenant](LOGIN_MULTITENANT_IMPLEMENTACAO.md)
- 📄 [Sistema de Contratos e Planos](SISTEMA_CONTRATOS_PLANOS.md)
- 📄 [Sistema de Pagamentos](SISTEMA_PAGAMENTOS_CONTRATOS.md)

---

**Último atualizado:** 10 de janeiro de 2026
