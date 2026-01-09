# 📱 API de Turmas - Documentação para Frontend

## Endpoint: GET /admin/turmas

Retorna as turmas disponíveis para um dia específico ou para o dia atual.

---

## 📥 Como Enviar a Requisição

### Base URL
```
http://localhost:8080/admin/turmas
```

### Headers Obrigatórios
```
Authorization: Bearer {SEU_TOKEN_JWT}
Content-Type: application/json
```

### Query Parameters (Opcionais)

| Parâmetro | Tipo | Descrição | Exemplo |
|-----------|------|-----------|---------|
| `data` | string | Data no formato YYYY-MM-DD | `2026-01-10` |
| `dia_id` | integer | ID do dia (compatibilidade) | `18` |
| `apenas_ativas` | boolean | Filtrar apenas turmas ativas | `true` |

---

## 💡 Exemplos de Requisição

### 1️⃣ Turmas de Hoje (Padrão)
```bash
curl -X GET "http://localhost:8080/admin/turmas" \
  -H "Authorization: Bearer seu_token_aqui" \
  -H "Content-Type: application/json"
```

**Resultado:** Retorna turmas do dia atual (09/01/2026)

---

### 2️⃣ Turmas de uma Data Específica ⭐ **RECOMENDADO**
```bash
curl -X GET "http://localhost:8080/admin/turmas?data=2026-01-10" \
  -H "Authorization: Bearer seu_token_aqui" \
  -H "Content-Type: application/json"
```

**Resultado:** Retorna turmas de 10/01/2026

---

### 3️⃣ Apenas Turmas Ativas
```bash
curl -X GET "http://localhost:8080/admin/turmas?data=2026-01-10&apenas_ativas=true" \
  -H "Authorization: Bearer seu_token_aqui" \
  -H "Content-Type: application/json"
```

**Resultado:** Retorna apenas turmas ativas de 10/01/2026

---

### 4️⃣ Usando ID do Dia (Compatibilidade)
```bash
curl -X GET "http://localhost:8080/admin/turmas?dia_id=18" \
  -H "Authorization: Bearer seu_token_aqui" \
  -H "Content-Type: application/json"
```

---

## 📤 Resposta de Sucesso (HTTP 200)

### Estrutura JSON
```json
{
  "dia": {
    "id": 17,
    "data": "2026-01-09",
    "ativo": 1
  },
  "turmas": [
    {
      "id": 187,
      "tenant_id": 5,
      "professor_id": 6,
      "modalidade_id": 1,
      "dia_id": 17,
      "horario_id": 47,
      "nome": "CrossFit - 06:00 - João Pedro",
      "limite_alunos": 15,
      "ativo": 1,
      "created_at": "2026-01-09T17:19:25",
      "updated_at": "2026-01-09T17:20:53",
      "professor_nome": "João Pedro",
      "professor_id": 6,
      "modalidade_nome": "CrossFit",
      "modalidade_icone": "weight-lifter",
      "dia_data": "2026-01-09",
      "horario_hora": "06:00:00",
      "alunos_count": 0
    }
  ]
}
```

### Campos Retornados

#### Dia
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID único do dia |
| `data` | string | Data no formato YYYY-MM-DD |
| `ativo` | integer | 1 = ativo, 0 = inativo |

#### Turmas (Array)
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | integer | ID da turma |
| `tenant_id` | integer | ID da academia/tenant |
| `professor_id` | integer | ID do professor |
| `professor_nome` | string | Nome do professor responsável |
| `modalidade_id` | integer | ID da modalidade (1 = CrossFit) |
| `modalidade_nome` | string | Nome da modalidade (ex: "CrossFit") |
| `modalidade_icone` | string | Ícone da modalidade (ex: "weight-lifter") ⭐ |
| `dia_id` | integer | ID do dia |
| `dia_data` | string | Data da turma (YYYY-MM-DD) |
| `horario_id` | integer | ID do horário |
| `horario_hora` | string | Horário no formato HH:MM:SS |
| `nome` | string | Nome descritivo da turma |
| `limite_alunos` | integer | Máximo de alunos permitidos |
| `ativo` | integer | 1 = ativa, 0 = inativa |
| `created_at` | string | Data de criação (ISO 8601) |
| `updated_at` | string | Data de atualização (ISO 8601) |
| `alunos_count` | integer | Número de alunos inscritos |

---

## ❌ Respostas de Erro

### 1. Data em Formato Inválido (HTTP 400)
```bash
curl -X GET "http://localhost:8080/admin/turmas?data=10/01/2026"
```

**Resposta:**
```json
{
  "type": "error",
  "message": "Formato de data inválido. Use YYYY-MM-DD"
}
```

---

### 2. Dia Não Encontrado (HTTP 404)
```bash
curl -X GET "http://localhost:8080/admin/turmas?data=2026-15-45"
```

**Resposta:**
```json
{
  "type": "error",
  "message": "Dia não encontrado para a data informada"
}
```

---

### 3. Dia não Encontrado por ID (HTTP 404)
```bash
curl -X GET "http://localhost:8080/admin/turmas?dia_id=999999"
```

**Resposta:**
```json
{
  "type": "error",
  "message": "Dia não encontrado"
}
```

---

## 🔄 Exemplos em JavaScript/TypeScript

### Fetch API
```javascript
async function getTurmas(data) {
  const token = localStorage.getItem('token');
  
  const url = new URL('http://localhost:8080/admin/turmas');
  url.searchParams.append('data', data); // YYYY-MM-DD
  url.searchParams.append('apenas_ativas', 'true');
  
  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    }
  });
  
  if (!response.ok) {
    throw new Error(`Erro: ${response.status}`);
  }
  
  return response.json();
}

// Uso
const data = '2026-01-10';
getTurmas(data).then(resultado => {
  console.log('Dia:', resultado.dia);
  console.log('Turmas:', resultado.turmas);
}).catch(erro => {
  console.error('Erro ao buscar turmas:', erro);
});
```

### Axios
```javascript
import axios from 'axios';

async function getTurmas(data) {
  const token = localStorage.getItem('token');
  
  try {
    const response = await axios.get('http://localhost:8080/admin/turmas', {
      params: {
        data: data, // YYYY-MM-DD
        apenas_ativas: 'true'
      },
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
    
    return response.data;
  } catch (error) {
    console.error('Erro ao buscar turmas:', error.response.data);
    throw error;
  }
}

// Uso
getTurmas('2026-01-10').then(resultado => {
  console.log('Turmas:', resultado.turmas);
});
```

### React Hook
```javascript
import { useState, useEffect } from 'react';

function TurmasComFiltro({ dataSelecionada }) {
  const [turmas, setTurmas] = useState([]);
  const [dia, setDia] = useState(null);
  const [loading, setLoading] = useState(false);
  const [erro, setErro] = useState(null);
  
  useEffect(() => {
    fetchTurmas(dataSelecionada);
  }, [dataSelecionada]);
  
  async function fetchTurmas(data) {
    setLoading(true);
    setErro(null);
    
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(
        `http://localhost:8080/admin/turmas?data=${data}&apenas_ativas=true`,
        {
          headers: {
            'Authorization': `Bearer ${token}`
          }
        }
      );
      
      if (!response.ok) {
        throw new Error('Erro ao buscar turmas');
      }
      
      const resultado = await response.json();
      setDia(resultado.dia);
      setTurmas(resultado.turmas);
    } catch (err) {
      setErro(err.message);
    } finally {
      setLoading(false);
    }
  }
  
  if (loading) return <div>Carregando...</div>;
  if (erro) return <div>Erro: {erro}</div>;
  
  return (
    <div>
      <h2>Turmas de {dia?.data}</h2>
      <ul>
        {turmas.map(turma => (
          <li key={turma.id}>
            {turma.nome} - {turma.professor_nome}
            ({turma.alunos_count}/{turma.limite_alunos})
          </li>
        ))}
      </ul>
    </div>
  );
}

export default TurmasComFiltro;
```

---

## 📋 Datas Disponíveis

O sistema tem turmas cadastradas para os seguintes períodos:

- **Período:** 09/01/2026 a 09/01/2027 (366 dias)
- **Turmas:** Segunda a Domingo, múltiplos horários
- **Horários:** 6:00, 7:00, 8:00, 17:00, 18:00, 19:00

### Exemplo de Datas Válidas
- `2026-01-09` ✅
- `2026-01-10` ✅
- `2026-12-25` ✅
- `2027-01-09` ✅

---

## 🔐 Autenticação

Todos os endpoints requerem um token JWT válido no header `Authorization`:

```
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

Se o token for inválido ou expirado, você receberá um erro 401 Unauthorized.

---

## 📞 Suporte

Para dúvidas sobre a API, entre em contato com a equipe de backend.

**Última atualização:** 09/01/2026
