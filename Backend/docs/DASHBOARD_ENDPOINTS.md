# Dashboard Endpoints

## Visão Geral
Endpoints para obter contadores e estatísticas do dashboard para uso no frontend.

**Requisição:** `GET /admin/dashboard/*`  
**Autenticação:** Sim (Bearer Token)  
**Acesso:** Admin (role_id = 2) ou Super Admin (role_id = 3)  
**Grupo de Rotas:** `/admin/`

---

## 1. GET /admin/dashboard

### Descrição
Retorna todos os contadores principais do dashboard em uma única requisição.

### Request
```bash
curl -X GET http://localhost:8080/admin/dashboard \
  -H "Authorization: Bearer seu_token"
```

### Response (200 OK)
```json
{
  "type": "success",
  "data": {
    "alunos": 45,
    "turmas": 12,
    "professores": 8,
    "modalidades": 4,
    "checkins_hoje": 23,
    "matrículas_ativas": 38,
    "receita_mes": {
      "pago": 5000.00,
      "pendente": 1500.00,
      "total": 6500.00,
      "mes": "2026-01"
    },
    "contratos_ativos": 15
  }
}
```

### Campos Retornados

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `alunos` | integer | Total de alunos ativos do tenant |
| `turmas` | integer | Total de turmas ativas |
| `professores` | integer | Total de professores ativos |
| `modalidades` | integer | Total de modalidades cadastradas |
| `checkins_hoje` | integer | Check-ins realizados no dia atual |
| `matrículas_ativas` | integer | Total de matrículas ativas |
| `receita_mes` | object | Informações de receita do mês atual |
| `receita_mes.pago` | float | Valor total recebido no mês |
| `receita_mes.pendente` | float | Valor total pendente no mês |
| `receita_mes.total` | float | Soma de pago + pendente |
| `receita_mes.mes` | string | Mês no formato YYYY-MM |
| `contratos_ativos` | integer | Total de contratos ativos |

---

## 2. GET /admin/dashboard/turmas-por-modalidade

### Descrição
Retorna a quantidade de turmas agrupadas por modalidade, ordenado por quantidade decrescente.

### Request
```bash
curl -X GET http://localhost:8080/admin/dashboard/turmas-por-modalidade \
  -H "Authorization: Bearer seu_token"
```

### Response (200 OK)
```json
{
  "type": "success",
  "data": [
    {
      "id": 5,
      "nome": "CrossFit",
      "total": 8
    },
    {
      "id": 3,
      "nome": "Natação",
      "total": 6
    },
    {
      "id": 1,
      "nome": "Pilates",
      "total": 4
    },
    {
      "id": 2,
      "nome": "Yoga",
      "total": 2
    }
  ]
}
```

### Campos Retornados

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID da modalidade |
| `nome` | string | Nome da modalidade |
| `total` | integer | Quantidade de turmas dessa modalidade |

### Observações
- Inclui apenas modalidades ativas
- Resultado ordenado por total (decrescente)
- Modalidades sem turmas aparecem com `total: 0`

---

## 3. GET /admin/dashboard/alunos-por-modalidade

### Descrição
Retorna a quantidade de alunos inscritos agrupados por modalidade, ordenado por quantidade decrescente.

### Request
```bash
curl -X GET http://localhost:8080/admin/dashboard/alunos-por-modalidade \
  -H "Authorization: Bearer seu_token"
```

### Response (200 OK)
```json
{
  "type": "success",
  "data": [
    {
      "id": 5,
      "nome": "CrossFit",
      "total": 52
    },
    {
      "id": 3,
      "nome": "Natação",
      "total": 38
    },
    {
      "id": 1,
      "nome": "Pilates",
      "total": 24
    },
    {
      "id": 2,
      "nome": "Yoga",
      "total": 15
    }
  ]
}
```

### Campos Retornados

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID da modalidade |
| `nome` | string | Nome da modalidade |
| `total` | integer | Quantidade de alunos inscritos |

### Observações
- Inclui apenas alunos com inscrições ativas
- Inclui apenas turmas ativas
- Resultado ordenado por total (decrescente)
- Alunos contam apenas uma vez mesmo se inscritos em múltiplas turmas

---

## 4. GET /admin/dashboard/checkins-últimos-7-dias

### Descrição
Retorna a quantidade de check-ins dos últimos 7 dias (incluindo hoje), agrupado por data.

### Request
```bash
curl -X GET http://localhost:8080/admin/dashboard/checkins-últimos-7-dias \
  -H "Authorization: Bearer seu_token"
```

### Response (200 OK)
```json
{
  "type": "success",
  "data": [
    {
      "data": "2026-01-05",
      "total": 12
    },
    {
      "data": "2026-01-06",
      "total": 15
    },
    {
      "data": "2026-01-07",
      "total": 18
    },
    {
      "data": "2026-01-08",
      "total": 22
    },
    {
      "data": "2026-01-09",
      "total": 19
    },
    {
      "data": "2026-01-10",
      "total": 25
    },
    {
      "data": "2026-01-11",
      "total": 23
    }
  ]
}
```

### Campos Retornados

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `data` | string | Data no formato YYYY-MM-DD |
| `total` | integer | Quantidade de check-ins naquele dia |

### Observações
- Período: últimos 7 dias (data atual - 6 dias até hoje)
- Resultado ordenado por data (ascendente)
- Inclui apenas turmas ativas
- Se não houver check-ins em um dia, o dia não aparece no resultado

---

## Exemplos de Uso no Frontend

### React
```javascript
// Dashboard principal com todos os contadores
const loadDashboard = async () => {
  const response = await fetch('http://localhost:8080/admin/dashboard', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  console.log(data.data.alunos); // 45
  console.log(data.data.receita_mes.pago); // 5000.00
};

// Gráfico: Turmas por Modalidade
const loadTurmasGrafico = async () => {
  const response = await fetch('http://localhost:8080/admin/dashboard/turmas-por-modalidade', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  // Usar para criar gráfico de barras
};

// Gráfico: Checkins últimos 7 dias
const loadCheckinsGrafico = async () => {
  const response = await fetch('http://localhost:8080/admin/dashboard/checkins-últimos-7-dias', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  const data = await response.json();
  // Usar para criar gráfico de linha
};
```

### Vue 3
```javascript
<script setup>
import { ref, onMounted } from 'vue';

const dashboard = ref(null);
const turmasModalidade = ref([]);
const checkinsData = ref([]);

const token = localStorage.getItem('token');

onMounted(async () => {
  // Buscar dados do dashboard
  const res1 = await fetch('/admin/dashboard', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  dashboard.value = (await res1.json()).data;

  // Buscar turmas por modalidade
  const res2 = await fetch('/admin/dashboard/turmas-por-modalidade', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  turmasModalidade.value = (await res2.json()).data;

  // Buscar checkins
  const res3 = await fetch('/admin/dashboard/checkins-últimos-7-dias', {
    headers: { 'Authorization': `Bearer ${token}` }
  });
  checkinsData.value = (await res3.json()).data;
});
</script>

<template>
  <div class="dashboard">
    <div class="cards">
      <div class="card">
        <h3>Alunos</h3>
        <p>{{ dashboard?.alunos }}</p>
      </div>
      <div class="card">
        <h3>Turmas</h3>
        <p>{{ dashboard?.turmas }}</p>
      </div>
      <div class="card">
        <h3>Receita (Mês)</h3>
        <p>R$ {{ dashboard?.receita_mes?.pago }}</p>
      </div>
    </div>
  </div>
</template>
```

---

## Tratamento de Erros

### Erro 500 - Internal Server Error
```json
{
  "type": "error",
  "message": "Erro ao carregar dashboard: [mensagem de erro específica]"
}
```

### Erro 401 - Não Autenticado
```json
{
  "message": "Invalid token",
  "exception": [...]
}
```

### Erro 403 - Acesso Negado
```json
{
  "message": "Acesso negado. Admin necessário.",
  "exception": [...]
}
```

---

## Performance

### Dicas de Otimização

1. **Cache no Frontend**: Cache os dados por 5-10 minutos
```javascript
const cacheTime = 5 * 60 * 1000; // 5 minutos
localStorage.setItem('dashboardCache', JSON.stringify(data));
localStorage.setItem('dashboardCacheTime', Date.now());
```

2. **Requisições Paralelas**: Faça as 4 requisições simultaneamente
```javascript
const results = await Promise.all([
  fetch('/admin/dashboard'),
  fetch('/admin/dashboard/turmas-por-modalidade'),
  fetch('/admin/dashboard/alunos-por-modalidade'),
  fetch('/admin/dashboard/checkins-últimos-7-dias')
]);
```

3. **Atualização em Tempo Real**: Use WebSocket ou polling a cada 30 segundos

---

## Notas Importantes

- Todos os dados são filtrados pelo `tenant_id` do usuário autenticado
- Apenas usuários com papel `admin` (role_id = 2) ou `super_admin` (role_id = 3) podem acessar
- Os contadores consideram apenas registros `ativo = 1`
- Datas estão no fuso horário da aplicação
- Para receita: apenas pagamentos com status `concluido` ou `processando` são contados como pago

---

## Ver Também

- [ROTAS_POR_PAPEL.md](ROTAS_POR_PAPEL.md) - Documentação completa de todas as rotas por role
- [REPLICAR_TURMAS_API.md](REPLICAR_TURMAS_API.md) - Endpoint para replicar turmas
