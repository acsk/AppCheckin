# 🏗️ Sistema de Hierarquia de Usuários

## 📊 Estrutura de Roles

O sistema possui 3 níveis hierárquicos:

```
┌─────────────────────────────────────┐
│  SuperAdmin (role_id = 3)           │
│  - Gerencia TODAS as academias     │
│  - Cria academias/tenants           │
│  - Atribui admins às academias      │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Admin (role_id = 2)                │
│  - Gerencia SUA academia            │
│  - Cria planos                      │
│  - Cria e gerencia alunos           │
│  - Visualiza relatórios             │
└──────────────┬──────────────────────┘
               │
               ▼
┌─────────────────────────────────────┐
│  Usuário/Aluno (role_id = 1)        │
│  - Realiza check-ins                │
│  - Visualiza seus dados             │
│  - Acessa funcionalidades básicas   │
└─────────────────────────────────────┘
```

## 🔐 Credenciais Iniciais

### SuperAdmin
- **Email:** superadmin@appcheckin.com
- **Senha:** SuperAdmin@2025
- **Tenant:** Sistema AppCheckin (id: 1)

## 🚀 Endpoints da API

### 📍 Autenticação (Público)

#### POST `/auth/login`
Login com email e senha. Retorna token JWT e lista de academias se usuário tiver múltiplos vínculos.

**Request:**
```json
{
  "email": "superadmin@appcheckin.com",
  "senha": "SuperAdmin@2025"
}
```

**Response (usuário com 1 academia):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "usuario": {
    "id": 1,
    "nome": "Super Administrador",
    "email": "superadmin@appcheckin.com",
    "role_id": 3,
    "tenant_id": 1
  }
}
```

**Response (usuário com múltiplas academias):**
```json
{
  "requires_tenant_selection": true,
  "tenants": [
    {
      "tenant_id": 1,
      "nome": "Academia Alpha",
      "slug": "alpha",
      "plano_nome": "Premium"
    },
    {
      "tenant_id": 2,
      "nome": "Academia Beta",
      "slug": "beta",
      "plano_nome": "Básico"
    }
  ],
  "usuario": {
    "id": 5,
    "nome": "João Silva",
    "email": "joao@email.com",
    "role_id": 2
  }
}
```

#### POST `/auth/select-tenant`
Seleciona academia após login (para usuários com múltiplos vínculos).

**Headers:**
```
Authorization: Bearer <token_inicial>
```

**Request:**
```json
{
  "tenant_id": 2
}
```

**Response:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "tenant_id": 2,
  "tenant_nome": "Academia Beta",
  "mensagem": "Tenant selecionado com sucesso"
}
```

---

### 👑 SuperAdmin (`/superadmin/*`)

**Middlewares:** `AuthMiddleware` + `SuperAdminMiddleware`  
**Acesso:** Apenas `role_id = 3`

#### GET `/superadmin/academias`
Lista todas as academias do sistema.

**Response:**
```json
{
  "academias": [
    {
      "id": 1,
      "nome": "Sistema AppCheckin",
      "slug": "sistema",
      "email": "admin@appcheckin.com",
      "telefone": null,
      "ativo": 1,
      "created_at": "2025-12-26 10:00:00"
    }
  ]
}
```

#### POST `/superadmin/academias`
Cria nova academia/tenant.

**Request:**
```json
{
  "nome": "Academia Fitness Pro",
  "email": "contato@fitnesspro.com",
  "telefone": "(11) 98765-4321",
  "endereco": "Rua das Flores, 123 - São Paulo"
}
```

**Response:**
```json
{
  "mensagem": "Academia criada com sucesso",
  "academia": {
    "id": 2,
    "nome": "Academia Fitness Pro",
    "slug": "academia-fitness-pro",
    "email": "contato@fitnesspro.com",
    "telefone": "(11) 98765-4321",
    "endereco": "Rua das Flores, 123 - São Paulo",
    "ativo": 1
  }
}
```

#### POST `/superadmin/academias/{tenantId}/admin`
Cria um usuário Admin para uma academia específica.

**Request:**
```json
{
  "nome": "Carlos Administrador",
  "email": "carlos@fitnesspro.com",
  "senha": "SenhaSegura123",
  "telefone": "(11) 91234-5678"
}
```

**Response:**
```json
{
  "mensagem": "Admin criado e vinculado à academia com sucesso",
  "admin": {
    "id": 3,
    "nome": "Carlos Administrador",
    "email": "carlos@fitnesspro.com",
    "role_id": 2,
    "tenant_id": 2
  }
}
```

---

### 🎯 Admin (`/admin/*`)

**Middlewares:** `AuthMiddleware` + `AdminMiddleware`  
**Acesso:** `role_id = 2` (Admin) ou `role_id = 3` (SuperAdmin)

#### GET `/admin/dashboard`
Estatísticas gerais da academia.

**Response:**
```json
{
  "total_alunos": 45,
  "alunos_ativos": 38,
  "alunos_inativos": 7,
  "total_checkins_hoje": 12,
  "total_checkins_semana": 89,
  "planos_vencendo": 5,
  "receita_mensal": 8500.00
}
```

#### GET `/admin/alunos`
Lista todos os alunos da academia com informações detalhadas.

**Response:**
```json
{
  "alunos": [
    {
      "id": 10,
      "nome": "Maria Santos",
      "email": "maria@email.com",
      "role_id": 1,
      "plano_id": 3,
      "plano_nome": "Mensal",
      "data_vencimento_plano": "2025-01-15",
      "status_vinculo": "ativo",
      "created_at": "2024-11-01 10:30:00"
    }
  ],
  "total": 38
}
```

#### POST `/admin/alunos`
Cria novo aluno na academia.

**Request:**
```json
{
  "nome": "Pedro Oliveira",
  "email": "pedro@email.com",
  "senha": "senha123",
  "plano_id": 2,
  "data_vencimento_plano": "2025-02-26"
}
```

**Response:**
```json
{
  "mensagem": "Aluno criado com sucesso",
  "aluno": {
    "id": 15,
    "nome": "Pedro Oliveira",
    "email": "pedro@email.com",
    "role_id": 1,
    "tenant_id": 2,
    "plano_id": 2
  }
}
```

#### PUT `/admin/alunos/{id}`
Atualiza dados de um aluno.

#### DELETE `/admin/alunos/{id}`
Desativa um aluno.

#### GET `/admin/alunos/{id}/historico-planos`
Histórico de planos do aluno.

#### GET `/admin/planos`
Lista planos da academia.

#### POST `/admin/planos`
Cria novo plano.

**Request:**
```json
{
  "nome": "Plano Trimestral",
  "descricao": "Acesso ilimitado por 3 meses",
  "valor": 299.90,
  "duracao_dias": 90,
  "checkins_mes": null,
  "ativo": true
}
```

#### PUT `/admin/planos/{id}`
Atualiza plano existente.

#### DELETE `/admin/planos/{id}`
Remove plano (soft delete).

#### GET `/admin/contas-receber`
Lista contas a receber.

#### GET `/admin/contas-receber/estatisticas`
Estatísticas financeiras.

#### POST `/admin/contas-receber/{id}/baixa`
Registra pagamento de conta.

#### GET `/admin/matriculas`
Lista matrículas.

#### POST `/admin/matriculas`
Cria nova matrícula.

#### POST `/admin/checkins/registrar`
Registra check-in manual para aluno.

---

### 👤 Usuário Comum (Autenticado)

**Middleware:** `AuthMiddleware`  
**Acesso:** Qualquer usuário autenticado

#### GET `/me`
Dados do usuário logado.

#### PUT `/me`
Atualiza dados do próprio usuário.

#### GET `/planos`
Lista planos disponíveis (público).

#### POST `/checkin`
Realiza check-in.

#### GET `/me/checkins`
Histórico de check-ins do usuário.

#### DELETE `/checkin/{id}`
Cancela check-in.

#### GET `/dias`
Lista dias disponíveis.

#### GET `/dias/proximos`
Próximos dias com horários.

#### GET `/turmas`
Lista turmas.

---

## 🔒 Middlewares

### 1. **TenantMiddleware** (Global)
- Extrai `tenant_id` do JWT ou header
- Adiciona ao request como atributo
- Aplica-se a TODAS as rotas (exceto públicas)

### 2. **AuthMiddleware**
- Valida token JWT
- Extrai dados do usuário
- Adiciona ao request como atributo
- Retorna 401 se não autenticado

### 3. **AdminMiddleware**
- Valida `role_id = 2` (Admin) ou `role_id = 3` (SuperAdmin)
- Retorna 403 se não for admin
- Usado em rotas `/admin/*`

### 4. **SuperAdminMiddleware**
- Valida `role_id = 3` (SuperAdmin)
- Retorna 403 se não for superadmin
- Usado em rotas `/superadmin/*`

---

## 📝 Fluxo de Trabalho

### 1️⃣ Setup Inicial (SuperAdmin)
```bash
# 1. Login como SuperAdmin
POST /auth/login
{
  "email": "superadmin@appcheckin.com",
  "senha": "SuperAdmin@2025"
}

# 2. Criar nova academia
POST /superadmin/academias
{
  "nome": "Academia X",
  "email": "contato@academiax.com"
}
# Retorna tenant_id: 2

# 3. Criar admin para academia
POST /superadmin/academias/2/admin
{
  "nome": "Admin da Academia X",
  "email": "admin@academiax.com",
  "senha": "senha123"
}
```

### 2️⃣ Configuração da Academia (Admin)
```bash
# 1. Login como Admin
POST /auth/login
{
  "email": "admin@academiax.com",
  "senha": "senha123"
}

# 2. Criar planos
POST /admin/planos
{
  "nome": "Mensal",
  "valor": 99.90,
  "duracao_dias": 30
}

# 3. Criar alunos
POST /admin/alunos
{
  "nome": "João Aluno",
  "email": "joao@email.com",
  "senha": "senha123",
  "plano_id": 3
}
```

### 3️⃣ Uso do Sistema (Aluno)
```bash
# 1. Login
POST /auth/login
{
  "email": "joao@email.com",
  "senha": "senha123"
}

# 2. Fazer check-in
POST /checkin
{
  "horario_id": 45
}

# 3. Ver meus check-ins
GET /me/checkins
```

---

## 🗄️ Estrutura do Banco

### Tabelas Principais

#### `usuarios`
```sql
- id
- tenant_id (academia padrão)
- nome
- email
- email_global (para multi-tenant)
- role_id (1=aluno, 2=admin, 3=super_admin)
- plano_id
- data_vencimento_plano
- senha_hash
```

#### `tenants` (Academias)
```sql
- id
- nome
- slug
- email
- telefone
- endereco
- ativo
```

#### `usuario_tenant` (Vínculos multi-tenant)
```sql
- usuario_id
- tenant_id
- plano_id
- status (ativo/inativo/suspenso)
- data_inicio
- data_fim
```

#### `roles`
```sql
- id
- nome (aluno, admin, super_admin)
```

#### `planos`
```sql
- id
- tenant_id
- nome
- valor
- duracao_dias
- ativo
```

---

## ✅ Status Atual

- ✅ Banco de dados resetado
- ✅ SuperAdmin criado
- ✅ Rotas SuperAdmin implementadas
- ✅ Rotas Admin reorganizadas
- ✅ Middlewares de autorização criados
- ✅ Multi-tenant funcional
- ✅ Sistema de hierarquia completo

## 🚧 Próximos Passos

1. **Frontend:**
   - Tela de seleção de tenant após login
   - Dashboard SuperAdmin
   - Dashboard Admin
   
2. **Funcionalidades:**
   - Relatórios avançados
   - Notificações de vencimento
   - Sistema de mensagens

3. **Melhorias:**
   - Logs de auditoria
   - Histórico de alterações
   - Backup automático
