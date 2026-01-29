# API - Buscar e Associar Aluno por CPF

## Visão Geral

Essa funcionalidade permite buscar um aluno pelo CPF em toda a base de dados (global, cross-tenant) e, se encontrado, associá-lo ao tenant atual. Se não encontrado, permite criar um novo aluno.

## Fluxo de Uso

```
┌─────────────────────────────────────────────────────────────────┐
│                    FLUXO CADASTRO DE ALUNO                      │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │  Informar CPF    │
                    └──────────────────┘
                              │
                              ▼
        ┌─────────────────────────────────────────────┐
        │   GET /admin/alunos/buscar-cpf/{cpf}        │
        └─────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              │                               │
              ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐
    │  found: true    │             │  found: false   │
    │  Aluno existe!  │             │  Não existe     │
    └─────────────────┘             └─────────────────┘
              │                               │
              ▼                               ▼
    ┌─────────────────┐             ┌─────────────────┐
    │ ja_associado?   │             │ Exibir form     │
    └─────────────────┘             │ cadastro novo   │
              │                     └─────────────────┘
     ┌────────┴────────┐                      │
     │                 │                      ▼
     ▼                 ▼            ┌─────────────────┐
┌─────────┐     ┌───────────┐      │ POST /admin/    │
│  TRUE   │     │   FALSE   │      │ alunos          │
│Já está  │     │ Pode      │      │ (criar novo)    │
│no tenant│     │ associar  │      └─────────────────┘
└─────────┘     └───────────┘
     │                 │
     ▼                 ▼
┌─────────────┐  ┌────────────────┐
│ Mostrar     │  │ POST /admin/   │
│ mensagem    │  │ alunos/associar│
│ "já existe" │  │ {aluno_id: X}  │
└─────────────┘  └────────────────┘
```

---

## Endpoints

### 1. Buscar Aluno por CPF (Global)

**Endpoint:** `GET /admin/alunos/buscar-cpf/{cpf}`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
```

**Parâmetros:**
- `cpf` (path): CPF do aluno (11 dígitos, com ou sem formatação)

#### Resposta - Aluno Encontrado

```json
{
  "success": true,
  "found": true,
  "aluno": {
    "id": 3,
    "usuario_id": 3,
    "nome": "ANDRE CABRAL SILVA",
    "email": "andrecabrall@gmail.com",
    "telefone": "82988376381",
    "cpf": "05809498426"
  },
  "tenants": [
    {
      "id": 2,
      "nome": "Academia XYZ",
      "slug": "academia-xyz"
    }
  ],
  "ja_associado": false,
  "pode_associar": true
}
```

**Campos importantes:**
| Campo | Tipo | Descrição |
|-------|------|-----------|
| `found` | boolean | Se o aluno foi encontrado na base |
| `aluno` | object | Dados básicos do aluno encontrado |
| `tenants` | array | Lista de academias onde o aluno já está cadastrado |
| `ja_associado` | boolean | Se o aluno já está associado ao tenant ATUAL |
| `pode_associar` | boolean | Se pode associar ao tenant atual (inverso de `ja_associado`) |

#### Resposta - Aluno Não Encontrado

```json
{
  "success": true,
  "found": false,
  "message": "Aluno não encontrado. Você pode cadastrar um novo aluno."
}
```

#### Erros Possíveis

```json
// CPF inválido (menos de 11 dígitos)
{
  "success": false,
  "error": "CPF deve conter 11 dígitos"
}

// CPF falha na validação matemática
{
  "success": false,
  "error": "CPF inválido"
}
```

---

### 2. Associar Aluno Existente ao Tenant

**Endpoint:** `POST /admin/alunos/associar`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json
```

**Body:**
```json
{
  "aluno_id": 3
}
```

#### Resposta de Sucesso

```json
{
  "success": true,
  "message": "Aluno associado com sucesso",
  "aluno": {
    "id": 3,
    "usuario_id": 3,
    "nome": "ANDRE CABRAL SILVA",
    "email": "andrecabrall@gmail.com",
    "telefone": "82988376381",
    "cpf": "05809498426",
    "ativo": true,
    "plano": null,
    "matricula_id": null,
    "total_checkins": 0,
    "ultimo_checkin": null,
    "pagamento_ativo": null
  }
}
```

#### Erros Possíveis

```json
// Aluno não encontrado
{
  "success": false,
  "error": "Aluno não encontrado"
}

// Aluno já associado
{
  "success": false,
  "error": "Aluno já está associado a esta academia"
}
```

---

### 3. Criar Novo Aluno

**Endpoint:** `POST /admin/alunos`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-ID: {tenant_id}
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "João da Silva",
  "email": "joao@email.com",
  "senha": "123456",
  "cpf": "12345678909",
  "telefone": "11999998888",
  "cep": "01310100",
  "logradouro": "Av Paulista",
  "numero": "1000",
  "complemento": "Apto 101",
  "bairro": "Bela Vista",
  "cidade": "São Paulo",
  "estado": "SP"
}
```

**Campos Obrigatórios:**
- `nome`
- `email`
- `senha` (mínimo 6 caracteres)

**Campos Opcionais:**
- `cpf`, `telefone`
- `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `estado`

#### Resposta de Sucesso

```json
{
  "type": "success",
  "message": "Aluno criado com sucesso",
  "aluno": {
    "id": 10,
    "usuario_id": 15,
    "nome": "JOÃO DA SILVA",
    "email": "joao@email.com",
    "cpf": "12345678909",
    "telefone": "11999998888",
    "ativo": true
  }
}
```

---

## Implementação no Frontend

### Exemplo React/TypeScript

```typescript
interface BuscarCpfResponse {
  success: boolean;
  found: boolean;
  message?: string;
  aluno?: {
    id: number;
    usuario_id: number;
    nome: string;
    email: string;
    telefone: string;
    cpf: string;
  };
  tenants?: Array<{
    id: number;
    nome: string;
    slug: string;
  }>;
  ja_associado?: boolean;
  pode_associar?: boolean;
}

// 1. Buscar aluno por CPF
async function buscarAlunoPorCpf(cpf: string): Promise<BuscarCpfResponse> {
  const response = await api.get(`/admin/alunos/buscar-cpf/${cpf}`);
  return response.data;
}

// 2. Associar aluno existente
async function associarAluno(alunoId: number): Promise<void> {
  await api.post('/admin/alunos/associar', { aluno_id: alunoId });
}

// 3. Criar novo aluno
async function criarAluno(dados: NovoAlunoDTO): Promise<void> {
  await api.post('/admin/alunos', dados);
}

// Fluxo completo
async function handleCadastroAluno(cpf: string, formData: FormData) {
  // Passo 1: Buscar por CPF
  const resultado = await buscarAlunoPorCpf(cpf);
  
  if (resultado.found) {
    if (resultado.ja_associado) {
      // Já está cadastrado nesta academia
      toast.warning('Este aluno já está cadastrado nesta academia');
      return;
    }
    
    // Pode associar - perguntar ao usuário
    const confirmar = await dialog.confirm({
      title: 'Aluno encontrado!',
      message: `O aluno ${resultado.aluno.nome} já está cadastrado em outra academia. Deseja associá-lo a esta academia?`,
      confirmText: 'Sim, associar',
      cancelText: 'Não, cancelar'
    });
    
    if (confirmar) {
      await associarAluno(resultado.aluno.id);
      toast.success('Aluno associado com sucesso!');
    }
  } else {
    // Não encontrado - criar novo
    await criarAluno(formData);
    toast.success('Aluno criado com sucesso!');
  }
}
```

### Fluxo de UI Sugerido

1. **Campo de CPF no início do formulário**
   - Ao sair do campo (blur) ou ao digitar 11 caracteres, dispara busca automática

2. **Loading enquanto busca**
   - Mostrar spinner no campo de CPF

3. **Se encontrado (`found: true`):**
   - Se `ja_associado: true` → Mostrar alerta "Aluno já cadastrado nesta academia"
   - Se `pode_associar: true` → Mostrar modal com dados do aluno e botão "Associar"

4. **Se não encontrado (`found: false`):**
   - Liberar formulário completo para cadastro

5. **Após associar ou criar:**
   - Redirecionar para lista de alunos ou tela de matrícula

---

## Observações Importantes

1. **CPF é global**: O CPF é único em toda a base, permitindo identificar o mesmo aluno em diferentes academias.

2. **Usuário compartilhado**: Quando um aluno é associado a um novo tenant, o mesmo usuário (login) é utilizado. O aluno pode acessar múltiplas academias com o mesmo email/senha.

3. **Dados do aluno são globais**: Nome, CPF, endereço ficam na tabela `alunos` (compartilhada). Dados específicos do tenant (matrícula, plano, pagamentos) ficam em tabelas separadas.

4. **Ao associar, são criados:**
   - Vínculo em `usuario_tenant` (se não existir)
   - Vínculo em `tenant_usuario_papel` com papel_id=1 (Aluno)

5. **O aluno pode ter planos diferentes** em cada academia que frequenta.
