# Estrutura de Aulas - Documentação

## Visão Geral

Foi implementado um sistema completo de gerenciamento de aulas (turmas) que permite que cada tenant cadastre seus professores e crie turmas com limite de alunos. O sistema integra professores, modalidades, dias e horários para criar uma estrutura completa de agendamento de aulas.

---

## Componentes Criados

### 1. Migrations (Banco de Dados)

#### `054_create_professores_table.sql`
Cria a tabela `professores` com os campos:
- `id` - ID do professor
- `tenant_id` - Referência ao tenant (academia)
- `nome` - Nome do professor
- `email` - Email único por tenant
- `telefone` - Telefone de contato
- `cpf` - CPF único do professor
- `foto_url` - URL da foto do professor
- `ativo` - Status do professor
- Índices para otimizar buscas por tenant e status

#### `055_create_turmas_table.sql`
Cria a tabela `turmas` (aulas) com os campos:
- `id` - ID da turma
- `tenant_id` - Referência ao tenant
- `professor_id` - Referência ao professor que leciona
- `modalidade_id` - Referência à modalidade (ex: Pilates, CrossFit)
- `dia_id` - Referência ao dia da semana
- `horario_id` - Referência ao horário específico
- `nome` - Nome da turma
- `limite_alunos` - Limite máximo de alunos por turma
- `ativo` - Status da turma
- Índices para otimizar buscas por tenant, professor, modalidade, dia e status

---

## Models

### `Professor.php`
Responsável pela lógica de gerenciamento de professores.

**Métodos principais:**
- `listarPorTenant(tenantId, apenasAtivos)` - Lista professores do tenant
- `findById(id, tenantId)` - Busca professor por ID
- `findByEmail(email, tenantId)` - Busca professor por email (único por tenant)
- `create(data)` - Cria novo professor
- `update(id, data)` - Atualiza professor
- `delete(id)` - Soft delete de professor
- `pertenceAoTenant(professorId, tenantId)` - Verifica se professor pertence ao tenant

### `Turma.php`
Responsável pela lógica de gerenciamento de turmas/aulas.

**Métodos principais:**
- `listarPorTenant(tenantId, apenasAtivas)` - Lista todas as turmas do tenant
- `listarPorDia(tenantId, diaId, apenasAtivas)` - **Para mobile**: lista turmas de um dia específico
- `findById(id, tenantId)` - Busca turma por ID com dados relacionados
- `listarPorProfessor(professorId, tenantId)` - Lista turmas de um professor
- `temVagas(turmaId)` - Verifica se há vagas disponíveis
- `create(data)` - Cria nova turma
- `update(id, data)` - Atualiza turma
- `delete(id)` - Soft delete de turma
- `contarAlunos(turmaId)` - Conta alunos inscritos na turma
- `pertenceAoTenant(turmaId, tenantId)` - Verifica se turma pertence ao tenant

---

## Controllers

### `ProfessorController.php`
Gerencia requisições HTTP para professores.

**Endpoints:**
- `GET /admin/professores` - Listar professores
- `GET /admin/professores/{id}` - Buscar professor
- `POST /admin/professores` - Criar professor
- `PUT /admin/professores/{id}` - Atualizar professor
- `DELETE /admin/professores/{id}` - Deletar professor

**Validações:**
- Nome obrigatório
- Email único por tenant (se fornecido)
- Autenticação e autorização obrigatórias

### `TurmaController.php`
Gerencia requisições HTTP para turmas.

**Endpoints principais:**
- `GET /admin/turmas` - Listar todas as turmas do tenant
- `GET /admin/turmas/dia/{diaId}` - **Filtrar turmas por dia** (para mobile)
- `GET /admin/turmas/{id}` - Buscar turma específica
- `POST /admin/turmas` - Criar turma
- `PUT /admin/turmas/{id}` - Atualizar turma
- `DELETE /admin/turmas/{id}` - Deletar turma
- `GET /admin/turmas/{id}/vagas` - Verificar vagas disponíveis
- `GET /admin/professores/{professorId}/turmas` - Listar turmas de um professor

**Validações:**
- Nome, professor, modalidade, dia e horário obrigatórios
- Limite de alunos deve ser > 0
- Verificação se recursos pertencem ao tenant
- Autenticação e autorização obrigatórias

---

## Fluxo de Funcionamento

### Para Admin (Backend/Dashboard)

1. **Cadastro de Professores**
   - Admin vai em `/admin/professores`
   - Cadastra novo professor com nome, email, CPF, etc.
   - Professor fica associado ao tenant

2. **Criação de Turmas/Aulas**
   - Admin vai em `/admin/turmas`
   - Seleciona: Professor, Modalidade, Dia, Horário
   - Define nome da turma e limite de alunos
   - Sistema valida se todos os recursos existem e pertencem ao tenant

3. **Gerenciamento**
   - Admin pode listar, editar, deletar turmas
   - Pode verificar vagas disponíveis (`/admin/turmas/{id}/vagas`)
   - Pode ver todas as turmas de um professor

### Para Mobile (App)

1. **Visualizar Dias Disponíveis**
   - Usuário clica em um dia específico
   - Chama `GET /turmas/dia/{diaId}`

2. **Ver Aulas do Dia**
   - Retorna todas as turmas daquele dia com:
     - Nome da aula
     - Professor
     - Modalidade
     - Horário
     - Vagas disponíveis
     - Número de alunos inscritos

3. **Inscrever-se em Turma**
   - Sistema verifica disponibilidade de vagas
   - Se houver vaga, aluno se inscreve (tabela `matriculas`)

---

## Integração com Estrutura Existente

### Relacionamentos
- **Professores** → vinculado a **Tenants** (cada academy tem seus professores)
- **Turmas** → vinculada a **Professor, Modalidade, Dia, Horário**
- **Turmas** → referenciada em **Matriculas** (alunos inscritos)
- **Dias** → gerados uma vez por ano (conforme requisito)

### Multi-tenancy
- Todas as operações validam se os recursos pertencem ao tenant autenticado
- Listagens retornam apenas dados do tenant
- Isolamento completo de dados entre tenants

---

## Exemplo de Requisições

### Criar Professor
```bash
POST /admin/professores
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@academia.com",
  "telefone": "11999999999",
  "cpf": "12345678901",
  "foto_url": "https://..."
}
```

### Criar Turma
```bash
POST /admin/turmas
Content-Type: application/json

{
  "nome": "Pilates Segunda 9h",
  "professor_id": 1,
  "modalidade_id": 2,
  "dia_id": 5,
  "horario_id": 10,
  "limite_alunos": 20
}
```

### Listar Turmas do Dia (Mobile)
```bash
GET /turmas/dia/5

Response:
{
  "dia": {
    "id": 5,
    "data": "2026-01-13"
  },
  "turmas": [
    {
      "id": 1,
      "nome": "Pilates 9h",
      "professor_nome": "João Silva",
      "modalidade_nome": "Pilates",
      "horario_hora": "09:00:00",
      "limite_alunos": 20,
      "alunos_count": 15,
      "vagas_disponiveis": 5
    }
  ]
}
```

### Verificar Vagas
```bash
GET /admin/turmas/1/vagas

Response:
{
  "turma_id": 1,
  "limite_alunos": 20,
  "alunos_inscritos": 15,
  "vagas_disponiveis": 5,
  "tem_vagas": true
}
```

---

## Próximas Etapas (Sugestões)

1. **Tabela de Matrículas em Turmas** - Já existe (`matriculas`), apenas validar integração
2. **Gerar Dias Automaticamente** - Job que cria dias uma vez por ano
3. **Relatórios de Ocupação** - Turmas mais procuradas, horários mais cheios
4. **Notificações** - Quando turma atinge limite, quando há cancelamento, etc.
5. **Integração com Check-in** - Vincular check-in à turma específica

---

## Segurança

✅ Validação de tenant em todas as operações  
✅ Soft delete (dados não são perdidos)  
✅ Autenticação obrigatória  
✅ Autorização por papel (Admin)  
✅ Validação de entrada em todos os endpoints  
✅ Prepared statements contra SQL injection  

---

**Data de criação:** 9 de janeiro de 2026  
**Status:** ✅ Pronto para usar
