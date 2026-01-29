# API de Alunos - Documentação para Frontend

> **Base URL:** `/admin/alunos`  
> **Autenticação:** Bearer Token (JWT)  
> **Middleware:** AdminMiddleware (role_id = 2 ou 3)

---

## Índice

1. [Listar Alunos](#1-listar-alunos)
2. [Listar Alunos (Básico)](#2-listar-alunos-básico)
3. [Buscar Aluno por ID](#3-buscar-aluno-por-id)
4. [Criar Aluno](#4-criar-aluno)
5. [Atualizar Aluno](#5-atualizar-aluno)
6. [Deletar Aluno](#6-deletar-aluno)
7. [Histórico de Planos](#7-histórico-de-planos)
8. [Objetos](#8-objetos)

---

## 1. Listar Alunos

Lista todos os alunos do tenant com dados completos.

### Request

```http
GET /admin/alunos
GET /admin/alunos?apenas_ativos=true
GET /admin/alunos?busca=joao&pagina=1&por_pagina=20
```

### Query Parameters

| Parâmetro | Tipo | Obrigatório | Descrição |
|-----------|------|-------------|-----------|
| `apenas_ativos` | boolean | Não | Se `true`, retorna apenas alunos ativos |
| `busca` | string | Não | Busca por nome, email ou CPF |
| `pagina` | integer | Não | Número da página (default: 1) |
| `por_pagina` | integer | Não | Itens por página (default: 50) |

### Response Success (200)

```json
{
  "alunos": [
    {
      "id": 3,
      "nome": "ANDRE CABRAL SILVA",
      "telefone": "82988376381",
      "cpf": "05809498426",
      "foto_url": null,
      "ativo": 1,
      "usuario_id": 3,
      "email": "andrecabrall@gmail.com",
      "cep": null,
      "logradouro": null,
      "numero": null,
      "complemento": null,
      "bairro": null,
      "cidade": null,
      "estado": null,
      "plano": {
        "id": 1,
        "nome": "Plano Mensal",
        "valor": 150.00
      },
      "matricula_id": 5,
      "total_checkins": 42,
      "ultimo_checkin": "2026-01-28 10:30:00",
      "pagamento_ativo": true
    }
  ],
  "total": 1
}
```

### Response com Paginação (200)

```json
{
  "alunos": [...],
  "total": 150,
  "pagina": 1,
  "por_pagina": 20,
  "total_paginas": 8
}
```

---

## 2. Listar Alunos (Básico)

Lista alunos com dados mínimos (ideal para selects/dropdowns).

### Request

```http
GET /admin/alunos/basico
```

### Response Success (200)

```json
{
  "alunos": [
    {
      "id": 3,
      "nome": "ANDRE CABRAL SILVA",
      "email": "andrecabrall@gmail.com",
      "usuario_id": 3
    },
    {
      "id": 4,
      "nome": "JOÃO SILVA",
      "email": "joao@email.com",
      "usuario_id": 7
    }
  ],
  "total": 2
}
```

---

## 3. Buscar Aluno por ID

Retorna dados completos de um aluno específico.

### Request

```http
GET /admin/alunos/{id}
```

### Path Parameters

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `id` | integer | ID do aluno (tabela alunos) |

### Response Success (200)

```json
{
  "aluno": {
    "id": 3,
    "nome": "ANDRE CABRAL SILVA",
    "telefone": "82988376381",
    "cpf": "05809498426",
    "foto_url": null,
    "foto_base64": null,
    "ativo": 1,
    "usuario_id": 3,
    "email": "andrecabrall@gmail.com",
    "cep": "57000000",
    "logradouro": "RUA DAS FLORES",
    "numero": "100",
    "complemento": "APTO 201",
    "bairro": "CENTRO",
    "cidade": "MACEIÓ",
    "estado": "AL",
    "created_at": "2026-01-20 14:01:00",
    "updated_at": "2026-01-28 15:30:00",
    "plano": {
      "id": 1,
      "nome": "Plano Mensal",
      "valor": 150.00
    },
    "matricula_id": 5,
    "total_checkins": 42,
    "ultimo_checkin": "2026-01-28 10:30:00",
    "pagamento_ativo": true
  }
}
```

### Response Error (404)

```json
{
  "type": "error",
  "message": "Aluno não encontrado"
}
```

---

## 4. Criar Aluno

Cria um novo aluno. Automaticamente cria o usuário para autenticação.

### Request

```http
POST /admin/alunos
Content-Type: application/json
```

### Request Body

```json
{
  "nome": "João da Silva",
  "email": "joao@email.com",
  "senha": "123456",
  "telefone": "82999998888",
  "cpf": "12345678901",
  "cep": "57000000",
  "logradouro": "Rua das Flores",
  "numero": "100",
  "complemento": "Apto 201",
  "bairro": "Centro",
  "cidade": "Maceió",
  "estado": "AL"
}
```

### Body Parameters

| Campo | Tipo | Obrigatório | Validação | Descrição |
|-------|------|-------------|-----------|-----------|
| `nome` | string | **Sim** | - | Nome completo (será convertido para MAIÚSCULAS) |
| `email` | string | **Sim** | Email válido, único | Email para login |
| `senha` | string | **Sim** | Mínimo 6 caracteres | Senha para login |
| `telefone` | string | Não | 10-11 dígitos | Telefone com DDD |
| `cpf` | string | Não | 11 dígitos | CPF (pode ter formatação) |
| `cep` | string | Não | 8 dígitos | CEP (pode ter formatação) |
| `logradouro` | string | Não | - | Endereço |
| `numero` | string | Não | - | Número do endereço |
| `complemento` | string | Não | - | Complemento |
| `bairro` | string | Não | - | Bairro |
| `cidade` | string | Não | - | Cidade |
| `estado` | string | Não | 2 caracteres | UF |

### Response Success (201)

```json
{
  "type": "success",
  "message": "Aluno criado com sucesso",
  "aluno": {
    "id": 5,
    "nome": "JOÃO DA SILVA",
    "telefone": "82999998888",
    "cpf": "12345678901",
    "foto_url": null,
    "ativo": 1,
    "usuario_id": 8,
    "email": "joao@email.com",
    "cep": "57000000",
    "logradouro": "RUA DAS FLORES",
    "numero": "100",
    "complemento": "APTO 201",
    "bairro": "CENTRO",
    "cidade": "MACEIÓ",
    "estado": "AL",
    "plano": null,
    "matricula_id": null,
    "total_checkins": 0,
    "ultimo_checkin": null,
    "pagamento_ativo": false
  }
}
```

### Response Error - Validação (422)

```json
{
  "type": "error",
  "message": "Erro de validação",
  "errors": [
    "Nome é obrigatório",
    "Email já cadastrado",
    "Senha deve ter no mínimo 6 caracteres"
  ]
}
```

### Response Error - Email Duplicado (400)

```json
{
  "type": "error",
  "message": "Este email já está cadastrado como aluno neste tenant"
}
```

---

## 5. Atualizar Aluno

Atualiza dados de um aluno existente.

### Request

```http
PUT /admin/alunos/{id}
Content-Type: application/json
```

### Path Parameters

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `id` | integer | ID do aluno |

### Request Body

Todos os campos são **opcionais**. Envie apenas os campos que deseja atualizar.

```json
{
  "nome": "João da Silva Santos",
  "telefone": "82999997777",
  "cpf": "12345678901",
  "cep": "57000001",
  "logradouro": "Av. Principal",
  "numero": "200",
  "complemento": "Casa",
  "bairro": "Jardim",
  "cidade": "Maceió",
  "estado": "AL",
  "email": "joao.novo@email.com",
  "senha": "novaSenha123",
  "ativo": 1
}
```

### Body Parameters (Opcionais)

| Campo | Tipo | Validação | Descrição |
|-------|------|-----------|-----------|
| `nome` | string | - | Nome completo |
| `telefone` | string | 10-11 dígitos | Telefone com DDD |
| `cpf` | string | 11 dígitos | CPF |
| `cep` | string | 8 dígitos | CEP |
| `logradouro` | string | - | Endereço |
| `numero` | string | - | Número |
| `complemento` | string | - | Complemento |
| `bairro` | string | - | Bairro |
| `cidade` | string | - | Cidade |
| `estado` | string | 2 caracteres | UF |
| `email` | string | Email válido, único | Novo email |
| `senha` | string | Mínimo 6 caracteres | Nova senha |
| `ativo` | integer | 0 ou 1 | Status ativo/inativo |

### Response Success (200)

```json
{
  "type": "success",
  "message": "Aluno atualizado com sucesso",
  "aluno": {
    "id": 5,
    "nome": "JOÃO DA SILVA SANTOS",
    "telefone": "82999997777",
    ...
  }
}
```

### Response Error (404)

```json
{
  "type": "error",
  "message": "Aluno não encontrado"
}
```

### Response Error - Validação (422)

```json
{
  "type": "error",
  "message": "Erro de validação",
  "errors": [
    "Email já cadastrado",
    "Senha deve ter no mínimo 6 caracteres"
  ]
}
```

---

## 6. Deletar Aluno

Desativa um aluno (soft delete). O aluno não é removido do banco, apenas marcado como inativo.

### Request

```http
DELETE /admin/alunos/{id}
```

### Path Parameters

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `id` | integer | ID do aluno |

### Response Success (200)

```json
{
  "type": "success",
  "message": "Aluno desativado com sucesso"
}
```

### Response Error (404)

```json
{
  "type": "error",
  "message": "Aluno não encontrado"
}
```

---

## 7. Histórico de Planos

Retorna o histórico de matrículas/planos do aluno.

### Request

```http
GET /admin/alunos/{id}/historico-planos
```

### Path Parameters

| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `id` | integer | ID do aluno |

### Response Success (200)

```json
{
  "historico": [
    {
      "id": 5,
      "usuario_id": 3,
      "plano_id": 1,
      "tenant_id": 2,
      "status": "ativa",
      "data_inicio": "2026-01-01",
      "data_fim": null,
      "observacoes": null,
      "created_at": "2026-01-01 10:00:00",
      "updated_at": "2026-01-01 10:00:00",
      "plano_nome": "Plano Mensal",
      "plano_valor": 150.00
    },
    {
      "id": 3,
      "usuario_id": 3,
      "plano_id": 2,
      "tenant_id": 2,
      "status": "cancelada",
      "data_inicio": "2025-06-01",
      "data_fim": "2025-12-31",
      "observacoes": "Mudou para plano mensal",
      "created_at": "2025-06-01 10:00:00",
      "updated_at": "2025-12-31 18:00:00",
      "plano_nome": "Plano Trimestral",
      "plano_valor": 400.00
    }
  ]
}
```

---

## 8. Objetos

### Objeto Aluno (Completo)

```typescript
interface Aluno {
  // Identificação
  id: number;                    // ID na tabela alunos
  usuario_id: number;            // ID na tabela usuarios (para autenticação)
  
  // Dados pessoais
  nome: string;                  // Nome em MAIÚSCULAS
  email: string;                 // Email (vem da tabela usuarios)
  telefone: string | null;       // Telefone com DDD
  cpf: string | null;            // CPF sem formatação (11 dígitos)
  
  // Endereço
  cep: string | null;            // CEP sem formatação (8 dígitos)
  logradouro: string | null;     // Endereço em MAIÚSCULAS
  numero: string | null;
  complemento: string | null;    // Em MAIÚSCULAS
  bairro: string | null;         // Em MAIÚSCULAS
  cidade: string | null;         // Em MAIÚSCULAS
  estado: string | null;         // UF (2 caracteres)
  
  // Foto
  foto_url: string | null;       // URL da foto no servidor
  foto_base64: string | null;    // Foto em base64 (se disponível)
  
  // Status e controle
  ativo: number;                 // 1 = ativo, 0 = inativo
  created_at: string;            // Data de criação
  updated_at: string;            // Data de atualização
  
  // Plano/Matrícula (dados enriquecidos)
  plano: Plano | null;           // Plano ativo
  matricula_id: number | null;   // ID da matrícula ativa
  
  // Estatísticas (dados enriquecidos)
  total_checkins: number;        // Total de check-ins
  ultimo_checkin: string | null; // Data/hora do último check-in
  pagamento_ativo: boolean;      // Se tem pagamento válido no período
}
```

### Objeto Aluno (Básico)

```typescript
interface AlunoBasico {
  id: number;
  nome: string;
  email: string;
  usuario_id: number;
}
```

### Objeto Plano

```typescript
interface Plano {
  id: number;
  nome: string;
  valor: number;
}
```

### Objeto Matrícula (Histórico)

```typescript
interface Matricula {
  id: number;
  usuario_id: number;
  plano_id: number;
  tenant_id: number;
  status: 'ativa' | 'cancelada' | 'suspensa' | 'encerrada';
  data_inicio: string;           // YYYY-MM-DD
  data_fim: string | null;       // YYYY-MM-DD
  observacoes: string | null;
  created_at: string;
  updated_at: string;
  plano_nome: string;
  plano_valor: number;
}
```

### Objeto Erro

```typescript
interface ErrorResponse {
  type: 'error';
  message: string;
  errors?: string[];             // Lista de erros de validação
}
```

### Objeto Sucesso

```typescript
interface SuccessResponse {
  type: 'success';
  message: string;
  aluno?: Aluno;                 // Presente em create/update
}
```

---

## Exemplos de Uso (JavaScript/TypeScript)

### Listar Alunos

```javascript
const response = await fetch('/admin/alunos?apenas_ativos=true', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
const data = await response.json();
// data.alunos, data.total
```

### Criar Aluno

```javascript
const response = await fetch('/admin/alunos', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    nome: 'João da Silva',
    email: 'joao@email.com',
    senha: '123456',
    telefone: '82999998888'
  })
});

if (response.status === 201) {
  const data = await response.json();
  console.log('Aluno criado:', data.aluno);
} else if (response.status === 422) {
  const data = await response.json();
  console.error('Erros:', data.errors);
}
```

### Atualizar Aluno

```javascript
const response = await fetch(`/admin/alunos/${alunoId}`, {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    telefone: '82999997777',
    cidade: 'Maceió'
  })
});
```

### Deletar Aluno

```javascript
const response = await fetch(`/admin/alunos/${alunoId}`, {
  method: 'DELETE',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

---

## Códigos de Status HTTP

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida (email duplicado, etc) |
| 401 | Não autenticado |
| 403 | Sem permissão (não é admin) |
| 404 | Aluno não encontrado |
| 422 | Erro de validação |
| 500 | Erro interno do servidor |

---

## Notas Importantes

1. **Campos em MAIÚSCULAS**: Nome, logradouro, complemento, bairro, cidade e estado são automaticamente convertidos para maiúsculas.

2. **CPF e CEP**: Podem ser enviados com ou sem formatação. A API remove automaticamente caracteres não numéricos.

3. **Email único**: O email é único globalmente. Se já existir um usuário com o email, ele será vinculado ao tenant como aluno.

4. **Soft Delete**: Ao deletar um aluno, ele não é removido do banco. O campo `ativo` é setado para 0.

5. **Autenticação**: O aluno pode fazer login com email/senha definidos na criação.

6. **Tenant**: Todas as operações são automaticamente filtradas pelo tenant do admin autenticado.
