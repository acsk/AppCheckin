# API de Gestão de Admins - Documentação Frontend

Esta documentação descreve os endpoints para gerenciar usuários administradores em academias. Um admin pode ter múltiplos papéis (Admin, Professor, Aluno).

## 📋 Sumário
- [Autenticação](#autenticação)
- [Papéis Disponíveis](#papéis-disponíveis)
- [Endpoints](#endpoints)
  - [Listar Papéis](#1-listar-papéis-disponíveis)
  - [Listar Admins](#2-listar-admins-da-academia)
  - [Criar Admin](#3-criar-admin)
  - [Atualizar Admin](#4-atualizar-admin)
  - [Desativar Admin](#5-desativar-admin)
  - [Reativar Admin](#6-reativar-admin)

---

## 🔐 Autenticação

Todas as requisições devem incluir o token JWT no header:
```
Authorization: Bearer {token}
```

## 👥 Papéis Disponíveis

| ID | Nome | Descrição |
|----|------|-----------|
| 1 | Aluno | Pode acessar o app mobile e fazer check-in |
| 2 | Professor | Pode marcar presença e gerenciar turmas |
| 3 | Admin | Pode acessar o painel administrativo |

**Importante:** Um admin pode ter múltiplos papéis simultaneamente. Por exemplo, pode ser Admin + Professor.

---

## 📍 Endpoints

### 1. Listar Papéis Disponíveis

Lista todos os papéis que podem ser atribuídos a um admin.

**Endpoint:** `GET /superadmin/papeis`

**Permissões:** Admin (3) ou Super Admin (4)

**Resposta Sucesso (200):**
```json
{
  "papeis": [
    {
      "id": 1,
      "nome": "Aluno",
      "descricao": "Pode acessar o app mobile e fazer check-in"
    },
    {
      "id": 2,
      "nome": "Professor",
      "descricao": "Pode marcar presença e gerenciar turmas"
    },
    {
      "id": 3,
      "nome": "Admin",
      "descricao": "Pode acessar o painel administrativo"
    }
  ]
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const response = await fetch('/superadmin/papeis', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
console.log(data.papeis);
```

---

### 2. Listar Admins da Academia

Lista todos os administradores de uma academia específica.

**Endpoint:** `GET /superadmin/academias/{tenantId}/admins`

**Permissões:** 
- Admin (3): Pode listar apenas admins da própria academia
- Super Admin (4): Pode listar admins de qualquer academia

**Parâmetros de URL:**
- `tenantId` (required): ID da academia

**Resposta Sucesso (200):**
```json
{
  "academia": {
    "id": 1,
    "nome": "Academia Central",
    "cnpj": "12345678000190"
  },
  "admins": [
    {
      "id": 10,
      "nome": "João Silva",
      "email": "joao@academia.com",
      "telefone": "11987654321",
      "cpf": "12345678900",
      "ativo": 1,
      "vinculado_em": "2024-01-15 10:30:00",
      "papeis": [3, 2]  // Admin e Professor
    },
    {
      "id": 15,
      "nome": "Maria Santos",
      "email": "maria@academia.com",
      "telefone": "11912345678",
      "cpf": "98765432100",
      "ativo": 1,
      "vinculado_em": "2024-02-20 14:15:00",
      "papeis": [3]  // Apenas Admin
    }
  ],
  "total": 2
}
```

**Resposta Erro (403):**
```json
{
  "error": "Você não tem permissão para acessar esta academia"
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const tenantId = 1;
const response = await fetch(`/superadmin/academias/${tenantId}/admins`, {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();
```

---

### 3. Criar Admin

Cria um novo administrador para uma academia.

**Endpoint:** `POST /superadmin/academias/{tenantId}/admin`

**Permissões:** 
- Admin (3): Pode criar admins apenas na própria academia
- Super Admin (4): Pode criar admins em qualquer academia

**Parâmetros de URL:**
- `tenantId` (required): ID da academia

**Body (JSON):**
```json
{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "senha": "senha123",
  "telefone": "(11) 98765-4321",
  "cpf": "123.456.789-00",
  "papeis": [3, 2]  // Opcional, padrão: [3] (apenas Admin)
}
```

**Campos:**
- `nome` (required): Nome completo
- `email` (required): Email válido (único no sistema)
- `senha` (required): Senha com mínimo 6 caracteres
- `telefone` (optional): Telefone (somente números)
- `cpf` (optional): CPF (somente números)
- `papeis` (optional): Array de IDs dos papéis [1, 2, 3]. Deve conter pelo menos [3].

**Validações:**
- Email deve ser válido e único
- Senha mínima de 6 caracteres
- Papéis devem incluir obrigatoriamente o ID 3 (Admin)
- Papéis válidos: 1 (Aluno), 2 (Professor), 3 (Admin)

**Resposta Sucesso (201):**
```json
{
  "message": "Admin criado com sucesso",
  "admin": {
    "id": 20,
    "nome": "João Silva",
    "email": "joao@academia.com",
    "papeis": [3, 2],
    "tenant": {
      "id": 1,
      "nome": "Academia Central"
    }
  }
}
```

**Resposta Erro (422):**
```json
{
  "errors": [
    "Email já cadastrado",
    "Usuário deve ter pelo menos o papel de Admin"
  ]
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const tenantId = 1;
const novoAdmin = {
  nome: "João Silva",
  email: "joao@academia.com",
  senha: "senha123",
  telefone: "11987654321",
  cpf: "12345678900",
  papeis: [3, 2]  // Admin e Professor
};

const response = await fetch(`/superadmin/academias/${tenantId}/admin`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify(novoAdmin)
});
const data = await response.json();
```

---

### 4. Atualizar Admin

Atualiza dados de um administrador existente, incluindo seus papéis.

**Endpoint:** `PUT /superadmin/academias/{tenantId}/admins/{adminId}`

**Permissões:** 
- Admin (3): Pode atualizar admins apenas da própria academia
- Super Admin (4): Pode atualizar admins de qualquer academia

**Parâmetros de URL:**
- `tenantId` (required): ID da academia
- `adminId` (required): ID do admin a ser atualizado

**Body (JSON):**
```json
{
  "nome": "João Silva Santos",
  "email": "joao.novo@academia.com",
  "senha": "novaSenha123",
  "telefone": "(11) 91234-5678",
  "cpf": "123.456.789-00",
  "papeis": [3, 2, 1]  // Admin, Professor e Aluno
}
```

**Campos (todos opcionais):**
- `nome`: Nome completo
- `email`: Email válido
- `senha`: Nova senha (mínimo 6 caracteres)
- `telefone`: Telefone
- `cpf`: CPF
- `papeis`: Array de IDs dos papéis. Deve conter pelo menos [3].

**Regras:**
- Ao atualizar papéis, o papel Admin (3) é obrigatório
- Se adicionar papel Professor (2), cria/ativa registro em `professores`
- Se adicionar papel Aluno (1), cria/ativa registro em `alunos`
- Se remover papéis, desativa registros correspondentes

**Resposta Sucesso (200):**
```json
{
  "message": "Admin atualizado com sucesso",
  "admin": {
    "id": 20,
    "nome": "João Silva Santos",
    "email": "joao.novo@academia.com",
    "telefone": "11912345678",
    "cpf": "12345678900",
    "papeis": [3, 2, 1]
  }
}
```

**Resposta Erro (422):**
```json
{
  "errors": [
    "Usuário deve manter pelo menos o papel de Admin"
  ]
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const tenantId = 1;
const adminId = 20;
const dadosAtualizados = {
  nome: "João Silva Santos",
  papeis: [3, 2]  // Mantém Admin e Professor, remove Aluno
};

const response = await fetch(
  `/superadmin/academias/${tenantId}/admins/${adminId}`,
  {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dadosAtualizados)
  }
);
const data = await response.json();
```

---

### 5. Desativar Admin

Desativa um administrador de uma academia (soft delete).

**Endpoint:** `DELETE /superadmin/academias/{tenantId}/admins/{adminId}`

**Permissões:** 
- Admin (3): Pode desativar admins apenas da própria academia
- Super Admin (4): Pode desativar admins de qualquer academia

**Parâmetros de URL:**
- `tenantId` (required): ID da academia
- `adminId` (required): ID do admin a ser desativado

**Regras:**
- Não é possível desativar o último admin ativo da academia
- A desativação é soft delete (ativo = 0)

**Resposta Sucesso (200):**
```json
{
  "message": "Admin desativado com sucesso"
}
```

**Resposta Erro (400):**
```json
{
  "error": "Não é possível desativar o único admin da academia. Crie outro admin primeiro."
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const tenantId = 1;
const adminId = 20;

const response = await fetch(
  `/superadmin/academias/${tenantId}/admins/${adminId}`,
  {
    method: 'DELETE',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);
const data = await response.json();
```

---

### 6. Reativar Admin

Reativa um administrador previamente desativado.

**Endpoint:** `POST /superadmin/academias/{tenantId}/admins/{adminId}/reativar`

**Permissões:** 
- Admin (3): Pode reativar admins apenas da própria academia
- Super Admin (4): Pode reativar admins de qualquer academia

**Parâmetros de URL:**
- `tenantId` (required): ID da academia
- `adminId` (required): ID do admin a ser reativado

**Resposta Sucesso (200):**
```json
{
  "message": "Admin reativado com sucesso"
}
```

**Exemplo de Uso (JavaScript):**
```javascript
const tenantId = 1;
const adminId = 20;

const response = await fetch(
  `/superadmin/academias/${tenantId}/admins/${adminId}/reativar`,
  {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    }
  }
);
const data = await response.json();
```

---

## 🔄 Fluxo Completo de Uso

### 1. Listar Papéis Disponíveis
```javascript
// Buscar papéis para mostrar no formulário
const papeis = await buscarPapeis();
// Renderizar checkboxes com os papéis
```

### 2. Criar Novo Admin
```javascript
// Formulário de criação
const novoAdmin = {
  nome: form.nome,
  email: form.email,
  senha: form.senha,
  telefone: form.telefone,
  cpf: form.cpf,
  papeis: form.papeisCheckbox  // [3, 2] por exemplo
};
await criarAdmin(tenantId, novoAdmin);
```

### 3. Listar Admins
```javascript
// Buscar e exibir lista de admins
const { admins } = await listarAdmins(tenantId);
// Renderizar tabela com nome, email, papéis, ações
```

### 4. Editar Admin
```javascript
// Formulário de edição (pré-preenchido)
const dadosAtualizados = {
  nome: form.nome,
  papeis: form.papeisCheckbox
};
await atualizarAdmin(tenantId, adminId, dadosAtualizados);
```

### 5. Desativar/Reativar
```javascript
// Botão de ação na lista
if (admin.ativo) {
  await desativarAdmin(tenantId, adminId);
} else {
  await reativarAdmin(tenantId, adminId);
}
```

---

## 💡 Casos de Uso Comuns

### Admin que também é Professor
```json
{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "senha": "senha123",
  "papeis": [3, 2]  // Admin + Professor
}
```
**Resultado:** João pode acessar o painel admin E dar aulas como professor.

### Admin que também é Aluno
```json
{
  "nome": "Maria Santos",
  "email": "maria@academia.com",
  "senha": "senha123",
  "papeis": [3, 1]  // Admin + Aluno
}
```
**Resultado:** Maria pode gerenciar a academia E treinar como aluna.

### Admin Completo (Admin + Professor + Aluno)
```json
{
  "nome": "Carlos Oliveira",
  "email": "carlos@academia.com",
  "senha": "senha123",
  "papeis": [3, 2, 1]  // Todos os papéis
}
```
**Resultado:** Carlos tem acesso total: painel admin, dar aulas e treinar.

---

## ⚠️ Observações Importantes

1. **Papel Admin é Obrigatório:** Todo usuário criado/atualizado nesta API deve ter pelo menos o papel 3 (Admin).

2. **Permissões por Papel:**
   - Admin (3): Gerencia apenas sua própria academia
   - Super Admin (4): Gerencia todas as academias

3. **Proteção:** Não é possível desativar o último admin ativo de uma academia.

4. **Telefone e CPF:** São sanitizados automaticamente (apenas números são salvos).

5. **Email Único:** Cada email pode existir apenas uma vez no sistema.

6. **Registros Relacionados:** 
   - Se adicionar papel Professor, cria registro em `professores`
   - Se adicionar papel Aluno, cria registro em `alunos`
   - Se remover papéis, os registros são desativados
   - **Importante**: Ao listar professores, o sistema busca de duas fontes:
     - Tabela `professores` (cadastro tradicional)
     - Tabela `usuarios` com papel Professor (papel_id=2) - ex: admins que também são professores

---

## 🎨 Exemplo de Interface

### Formulário de Criação/Edição
```html
<form>
  <input type="text" name="nome" placeholder="Nome completo" required>
  <input type="email" name="email" placeholder="Email" required>
  <input type="password" name="senha" placeholder="Senha (mín. 6 caracteres)" required>
  <input type="tel" name="telefone" placeholder="Telefone">
  <input type="text" name="cpf" placeholder="CPF">
  
  <fieldset>
    <legend>Papéis</legend>
    <label>
      <input type="checkbox" name="papeis" value="3" checked disabled>
      Admin (obrigatório)
    </label>
    <label>
      <input type="checkbox" name="papeis" value="2">
      Professor
    </label>
    <label>
      <input type="checkbox" name="papeis" value="1">
      Aluno
    </label>
  </fieldset>
  
  <button type="submit">Salvar</button>
</form>
```

---

## � Integração com Listagem de Professores

Quando você listar professores no sistema (endpoint de professores), o sistema agora **automaticamente inclui**:

1. **Professores tradicionais**: Cadastrados diretamente na tabela `professores`
2. **Admins com papel Professor**: Usuários cadastrados como Admin + Professor através desta API

### Exemplo Prático:

Se você criar um admin com papéis `[3, 2]` (Admin + Professor):
```javascript
await criarAdmin(1, {
  nome: "João Silva",
  email: "joao@academia.com",
  senha: "senha123",
  papeis: [3, 2]  // Admin + Professor
});
```

**Resultado:**
- ✅ João aparece na listagem de admins da academia
- ✅ João **também aparece** na listagem de professores da academia
- ✅ João pode ser selecionado ao criar turmas/aulas
- ✅ João pode marcar presenças nas turmas dele

### Identificação na Listagem:

Professores vindos de admins podem ter `id: null` na listagem, pois não possuem registro na tabela `professores`. Eles são identificados pelo `usuario_id`.

---

## �📞 Suporte

Para dúvidas ou problemas com a API, consulte a equipe de desenvolvimento.
