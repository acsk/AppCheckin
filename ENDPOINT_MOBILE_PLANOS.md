# 📋 Endpoint: GET /mobile/planos

**Fluxo:** Aluno → Tenant → Planos

**Data:** 10 de janeiro de 2026  
**Versão:** 1.0  
**Status:** ✅ Implementado

---

## 📝 Descrição

Retorna **TODOS os planos do TENANT** (academia) ao qual o aluno está conectado.

O aluno faz login → Seleciona um Tenant → Vê os planos daquele Tenant

```
┌────────────┐
│   Aluno    │
└─────┬──────┘
      │ login
      ↓
┌─────────────────┐
│    Tenant       │  (Academia)
│ (X-Tenant-Slug) │
└────────┬────────┘
         │ lista planos
         ↓
    ┌─────────────┐
    │ Planos[n]   │
    │ - Plano 1   │  (Ativo)
    │ - Plano 2   │  (Vencido)
    │ - Plano 3   │  (Cancelado)
    └─────────────┘
```

---

## 🔧 Implementação Técnica

### Backend

**Arquivo:** `Backend/app/Controllers/MobileController.php`

**Método:** `planos()`

Retorna todos os contratos/planos do Tenant com:
- Informações do plano
- Status do contrato
- Vigência (datas, dias restantes, percentual de uso)
- Pagamentos agregados (pago, aguardando, atrasado)

**Resposta:**
```json
{
  "success": true,
  "data": {
    "planos": [
      {
        "id": 5,
        "plano": {
          "id": 2,
          "nome": "Enterprise",
          "valor": 250.00,
          "max_alunos": 500,
          "features": ["feature1", "feature2"]
        },
        "status": {
          "id": 1,
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
          "pago": 1,
          "aguardando": 11,
          "atrasado": 0
        }
      }
    ],
    "total": 1,
    "tenant": {
      "id": 4,
      "nome": "Sporte e Saúde",
      "slug": "sporte-saude"
    }
  }
}
```

### Rota

**Arquivo:** `Backend/routes/api.php`

```php
$app->group('/mobile', function ($group) {
    $group->get('/planos', [MobileController::class, 'planos']);  // Todos os planos do tenant
})->add(AuthMiddleware::class);
```

### Frontend

**Arquivo:** `mobile/src/services/mobileService.js`

```javascript
async getPlanos() {
  try {
    const response = await api.get('/mobile/planos');
    return response.data;
  } catch (error) {
    throw error.response?.data || error;
  }
},
```

---

## 🧪 Como Usar

### No App Mobile

```javascript
import { mobileService } from '@/services/mobileService';

// O aluno já fez login e selecionou um tenant
// Agora carrega os planos daquele tenant
const planosData = await mobileService.getPlanos();

if (planosData.success) {
  const planos = planosData.data.planos;  // Array com todos os planos do tenant
  const tenant = planosData.data.tenant;   // Informações do tenant
  
  // Exibir planos
  planos.forEach(plano => {
    console.log(`${plano.plano.nome} - ${plano.status.nome}`);
  });
}
```

### Teste com cURL

```bash
curl -X GET http://localhost:8080/mobile/planos \
  -H "Authorization: Bearer {token}" \
  -H "X-Tenant-Slug: {tenant_slug}"
```

---

## 📊 Fluxo de Dados

```
Aluno logged in
     ↓
X-Tenant-Slug header (qual tenant o aluno selecionou)
     ↓
GET /mobile/planos
     ↓
Query: SELECT * FROM tenant_planos_sistema 
       WHERE tenant_id = ? 
       ORDER BY status_id, data_inicio DESC
     ↓
Retorna array de planos do tenant
     ↓
Renderizar no app (lista de planos)
```

---

## 💡 Casos de Uso

### 1. Aluno vê plano ativo
```javascript
const planosData = await mobileService.getPlanos();
const planoAtivo = planosData.data.planos.find(p => p.status.id === 1);
console.log(`Seu plano atual: ${planoAtivo.plano.nome}`);
```

### 2. Verificar pagamentos pendentes do tenant
```javascript
const planosData = await mobileService.getPlanos();
planosData.data.planos.forEach(plano => {
  if (plano.pagamentos.aguardando > 0) {
    console.log(`Tenant tem ${plano.pagamentos.aguardando} pagamento(s) pendente(s)`);
  }
});
```

### 3. Histórico de planos do tenant
```javascript
const planosData = await mobileService.getPlanos();

// Mostrar timeline (ativo → vencido → cancelado)
const timeline = planosData.data.planos.map(p => ({
  periodo: `${p.vigencia.data_inicio} até ${p.vigencia.data_fim}`,
  plano: p.plano.nome,
  status: p.status.nome
}));
```

---

## 🔑 Diferenças

| Endpoint | Retorna | Para quê |
|----------|---------|----------|
| `/mobile/contratos` | 1 plano ativo (ou null) | Dashboard principal |
| `/mobile/planos` | Array de todos os planos | Histórico, transições, gerenciamento |

---

## ✅ Checklist

- ✅ Método `planos()` implementado em MobileController.php
- ✅ Rota registrada em api.php
- ✅ Método `getPlanos()` adicionado em mobileService.js
- ✅ Documentação criada
- ✅ Nomenclatura correta: Aluno → Tenant → Planos

