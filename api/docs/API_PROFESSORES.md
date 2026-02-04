# API de Professores

**Arquitetura Simplificada (2026-02-03)**

Usa **APENAS** `tenant_usuario_papel` para vincular professores aos tenants.

```
professores (dados básicos)
    ↓ usuario_id
tenant_usuario_papel (papel_id=2)
    ↓ tenant_id
tenants
```

---

## 📍 Endpoints

### 1. Listar Professores

```http
GET /api/admin/professores
```

**Query Parameters:**
- `apenas_ativos` (opcional): `true` ou `false`

**Response 200:**
```json
{
  "professores": [
    {
      "id": 1,
      "nome": "Carlos Mendes",
      "cpf": "12345678901",
      "email": "carlos@teste.com",
      "foto_url": null,
      "ativo": 1,
      "usuario_id": 5,
      "telefone": "11999999999",
      "vinculo_ativo": 1,
      "turmas_count": 3
    }
  ]
}
```

**Campos:**
- `vinculo_ativo`: Status do vínculo em `tenant_usuario_papel` (1=ativo, 0=inativo)
- `turmas_count`: Número de turmas ativas do professor

---

### 2. Buscar Professor por ID

```http
GET /api/admin/professores/{id}
```

**Path Parameters:**
- `id` (integer): ID do professor

**Response 200:**
```json
{
  "professor": {
    "id": 1,
    "nome": "Carlos Mendes",
    "cpf": "12345678901",
    "email": "carlos@teste.com",
    "foto_url": null,
    "ativo": 1,
    "usuario_id": 5,
    "telefone": "11999999999",
    "vinculo_ativo": 1,
    "created_at": "2024-01-15 10:30:00",
    "updated_at": "2024-02-03 14:20:00"
  }
}
```

**Response 404:**
```json
{
  "type": "error",
  "message": "Professor não encontrado"
}
```

---

### 3. Buscar Professor por CPF

```http
GET /api/admin/professores/cpf/{cpf}
```

**Path Parameters:**
- `cpf` (string): CPF com 11 dígitos (aceita com ou sem formatação)

**Exemplos:**
- `12345678901` ✅
- `123.456.789-01` ✅

**Response 200:**
```json
{
  "professor": {
    "id": 1,
    "nome": "Carlos Mendes",
    "cpf": "12345678901",
    "email": "carlos@teste.com",
    "vinculo_ativo": 1,
    "turmas_count": 3
  }
}
```

**Response 400:**
```json
{
  "type": "error",
  "message": "CPF inválido. Deve conter 11 dígitos."
}
```

**Response 404:**
```json
{
  "type": "error",
  "message": "Professor não encontrado com este CPF"
}
```

---

### 4. Criar e Associar Professor ⭐

```http
POST /api/admin/professores
```

**Request Body:**
```json
{
  "nome": "João Silva",
  "email": "joao.silva@exemplo.com",
  "cpf": "12345678901",
  "telefone": "11999998888",
  "foto_url": "https://exemplo.com/foto.jpg"
}
```

**Campos:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| nome | string | ✅ Sim | Nome completo do professor |
| email | string | ✅ Sim | Email do professor |
| cpf | string | ✅ Sim | CPF com 11 dígitos |
| telefone | string | ⚪ Não | Telefone |
| foto_url | string | ⚪ Não | URL da foto |

---

## 🎯 Fluxo de Criação

O endpoint `POST /admin/professores` é **inteligente** e trata 3 cenários:

### Cenário 1: Professor novo (não existe no sistema)
```
✅ Cria usuário
✅ Cria professor
✅ Insere em tenant_usuario_papel (papel_id=2)
✅ Gera senha temporária
```

**Response 201:**
```json
{
  "type": "success",
  "message": "Professor criado com sucesso",
  "professor": {
    "id": 101,
    "nome": "João Silva",
    "cpf": "12345678901",
    "email": "joao.silva@exemplo.com",
    "vinculo_ativo": 1
  },
  "usuario": {
    "id": 150,
    "criado": true,
    "vinculado_ao_tenant": true,
    "papel": "professor"
  },
  "professor_existia": false,
  "credenciais": {
    "email": "joao.silva@exemplo.com",
    "senha_temporaria": "Xy89Kp2m",
    "mensagem": "Informe estas credenciais ao professor..."
  }
}
```

### Cenário 2: Professor existe, mas não neste tenant
```
✅ Busca professor existente
✅ Insere em tenant_usuario_papel (papel_id=2)
❌ NÃO cria novo usuário
❌ NÃO gera senha
```

**Response 201:**
```json
{
  "type": "success",
  "message": "Professor existente associado ao tenant com sucesso",
  "professor": {
    "id": 50,
    "nome": "João Silva",
    "cpf": "12345678901",
    "vinculo_ativo": 1
  },
  "usuario": {
    "id": 80,
    "criado": false,
    "vinculado_ao_tenant": true,
    "papel": "professor"
  },
  "professor_existia": true
}
```

### Cenário 3: Professor já vinculado a este tenant
```
❌ Retorna erro 409 (Conflict)
```

**Response 409:**
```json
{
  "type": "error",
  "message": "Professor já está vinculado a este tenant"
}
```

---

## 🗄️ Arquitetura do Banco

### Tabelas Envolvidas

```sql
-- 1. professores (cadastro global)
CREATE TABLE professores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    email VARCHAR(255),
    foto_url VARCHAR(500),
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. tenant_usuario_papel (vínculo + permissão)
CREATE TABLE tenant_usuario_papel (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel_id INT NOT NULL,  -- 2 = professor
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (tenant_id, usuario_id, papel_id)
);

-- 3. usuarios (autenticação)
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    cpf VARCHAR(14),
    telefone VARCHAR(20),
    senha VARCHAR(255),
    -- ...
);
```

### Query que a API Usa

```sql
-- Listar professores do tenant
SELECT p.id, p.nome, p.cpf, p.email, p.foto_url, p.ativo, p.usuario_id,
       u.telefone,
       tup.ativo as vinculo_ativo,
       (SELECT COUNT(*) FROM turmas t 
        WHERE t.professor_id = p.id AND t.ativo = 1) as turmas_count
FROM professores p 
INNER JOIN tenant_usuario_papel tup 
    ON tup.usuario_id = p.usuario_id
    AND tup.tenant_id = :tenant_id
    AND tup.papel_id = 2  -- ← PROFESSOR
LEFT JOIN usuarios u ON u.id = p.usuario_id
WHERE p.ativo = 1 AND tup.ativo = 1
ORDER BY p.nome ASC;
```

**Chave de Ligação:**
```
professores.usuario_id = tenant_usuario_papel.usuario_id
tenant_usuario_papel.papel_id = 2 (professor)
```

---

## 🧪 Exemplos com cURL

### Listar todos os professores
```bash
curl -X GET "http://localhost:8080/api/admin/professores" \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Listar apenas ativos
```bash
curl -X GET "http://localhost:8080/api/admin/professores?apenas_ativos=true" \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Buscar por ID
```bash
curl -X GET "http://localhost:8080/api/admin/professores/1" \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Buscar por CPF
```bash
curl -X GET "http://localhost:8080/api/admin/professores/cpf/12345678901" \
  -H "Authorization: Bearer SEU_TOKEN"
```

### Criar novo professor
```bash
curl -X POST "http://localhost:8080/api/admin/professores" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "Maria Santos",
    "email": "maria.santos@teste.com",
    "cpf": "98765432100",
    "telefone": "11988887777"
  }'
```

---

## 📊 Swagger/OpenAPI

A documentação OpenAPI completa está disponível em:

```
http://localhost:8080/swagger.json
```

Para visualizar no Swagger UI, acesse:
```
http://localhost:8080/swagger-ui/
```

---

## ✅ Mudanças da Arquitetura

### ❌ ANTES (Redundante)
```
professores → tenant_professor → tenant
professores → usuarios → tenant_usuario_papel → tenant
```
- Duas tabelas fazendo a mesma coisa
- Dados duplicados
- Mais queries necessárias

### ✅ AGORA (Simplificado)
```
professores → usuarios → tenant_usuario_papel (papel_id=2) → tenant
```
- Uma única fonte de verdade
- Query mais simples
- Arquitetura consistente
- Mesma lógica para alunos (papel_id=1) e professores (papel_id=2)

---

## 🔐 Autenticação

Todos os endpoints requerem autenticação via Bearer Token:

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

O token deve conter:
- `tenant_id`: ID do tenant
- `user_id`: ID do usuário autenticado
- `role_id`: Papel do usuário (deve ter permissão de admin)

---

## 📝 Notas Importantes

1. **CPF e EMAIL são obrigatórios** no cadastro de professores
2. **Senha temporária** é gerada automaticamente para novos usuários
3. **Vínculo automático** ao tenant via `tenant_usuario_papel` (papel_id=2)
4. **Professor pode estar em múltiplos tenants** (mesmo usuario_id, diferentes tenant_id)
5. **Soft delete**: Professores são desativados, não deletados fisicamente

---

**Data da Atualização:** 03/02/2026  
**Versão da API:** v1  
**Autor:** André Cabral
