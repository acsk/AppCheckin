# 🏗️ Arquitetura: Check-in em Turmas

## Diagrama de Componentes

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         APP MOBILE (Frontend)                            │
│                                                                          │
│  1. GET /mobile/horarios-disponiveis → Lista de Turmas                  │
│     └─ Retorna: [{id, nome, professor, modalidade, vagas}]             │
│                                                                          │
│  2. POST /mobile/checkin → Registra check-in                            │
│     └─ Envia: {turma_id: 494}                                           │
│     └─ Recebe: {checkin_id, turma_details, vagas_updated}              │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↕
                          (JWT Authentication)
                                    ↕
┌──────────────────────────────────────────────────────────────────────────┐
│                        API BACKEND (Slim 4)                             │
│                                                                          │
│  POST /mobile/checkin (MobileController::registrarCheckin)              │
│  └─ Validações:                                                         │
│     ├─ 1. tenantId obrigatório                                         │
│     ├─ 2. turma_id obrigatório                                         │
│     ├─ 3. turma_id tipo int                                            │
│     ├─ 4. Turma existe? → $turmaModel->findById()                     │
│     ├─ 5. Turma pertence ao tenant?                                    │
│     ├─ 6. Sem duplicata? → $checkinModel->usuarioTemCheckinNaTurma()   │
│     ├─ 7. Vagas disponíveis? → $turmaModel->contarAlunos()            │
│     ├─ 8. Cria check-in → $checkinModel->createEmTurma()              │
│     └─ 9. Retorna 201 com detalhes                                      │
└──────────────────────────────────────────────────────────────────────────┘
                                    ↕
                            (SQL Queries)
                                    ↕
┌──────────────────────────────────────────────────────────────────────────┐
│                        MySQL Database                                    │
│                                                                          │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────┐      │
│  │   turmas        │  │   usuarios       │  │   tenants        │      │
│  ├─────────────────┤  ├──────────────────┤  ├──────────────────┤      │
│  │ id              │  │ id               │  │ id               │      │
│  │ tenant_id   ────┼──→ tenant_id   ────┼──→ id               │      │
│  │ nome            │  │ nome             │  │ nome             │      │
│  │ professor_id    │  │ email            │  │ ativo            │      │
│  │ modalidade_id   │  │ password         │  └──────────────────┘      │
│  │ dia_id          │  │ ativo            │                             │
│  │ horario_inicio  │  │ created_at       │                             │
│  │ horario_fim     │  │ updated_at       │                             │
│  │ limite_alunos   │  └──────────────────┘                             │
│  │ alunos_count    │                                                    │
│  │ ativo           │                                                    │
│  └────────┬────────┘                                                    │
│           │ 1:N                                                         │
│           │                                                             │
│  ┌────────↓──────────────┐                                             │
│  │   checkins (NOVO)     │                                             │
│  ├───────────────────────┤                                             │
│  │ id                    │                                             │
│  │ usuario_id        ────┼──→ usuarios.id                              │
│  │ turma_id (NOVO)   ────┼──→ turmas.id [FK]                          │
│  │ horario_id (LEGADO)   │                                             │
│  │ registrado_por_admin  │                                             │
│  │ admin_id              │                                             │
│  │ created_at            │                                             │
│  │ updated_at            │                                             │
│  └───────────────────────┘                                             │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## Fluxo de Dados: POST /mobile/checkin

```
┌────────────────────────────────────────────────────────────────┐
│ 1. Requisição HTTP                                             │
│    POST /mobile/checkin                                        │
│    Header: Authorization: Bearer JWT                           │
│    Body: {"turma_id": 494}                                    │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 2. Middleware de Autenticação                                  │
│    ├─ Decodifica JWT                                           │
│    ├─ Extrai: userId = 11, tenantId = 4                       │
│    └─ Atribui ao Request                                       │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 3. MobileController::registrarCheckin()                        │
│    ├─ Extrai: $userId=11, $tenantId=4, $turmaId=494          │
│    └─ Iniciado: Validações                                    │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 4. Validação 1-3: Input Básico                                │
│    ├─ if (!$tenantId) → return 400                            │
│    ├─ if (!$turmaId) → return 400                             │
│    └─ $turmaId = (int) $turmaId                               │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 5. Validação 4-5: Turma Existe                                │
│    SELECT turmas WHERE id=494 AND tenant_id=4                │
│    ├─ $turma = $this->turmaModel->findById(494, 4)           │
│    └─ if (!$turma) → return 404 "Turma não encontrada"       │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 6. Validação 6: Sem Duplicata                                 │
│    SELECT COUNT(*) FROM checkins                              │
│    WHERE usuario_id=11 AND turma_id=494                       │
│    └─ if ($this->checkinModel->usuarioTemCheckinNaTurma(...))│
│       → return 400 "Já realizou check-in"                     │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 7. Validação 7: Vagas Disponíveis                             │
│    ├─ $alunosCount = $this->turmaModel->contarAlunos(494)    │
│    ├─ if ($alunosCount >= $turma['limite_alunos'])           │
│    └─ → return 400 "Sem vagas disponíveis"                    │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 8. Validação 8: Cria Check-in                                 │
│    INSERT INTO checkins (usuario_id, turma_id)                │
│    VALUES (11, 494)                                            │
│    ├─ $checkinId = $this->checkinModel->createEmTurma(11, 494)│
│    ├─ try/catch PDOException (code 23000 = duplicata race)   │
│    └─ if (!$checkinId) → return 500                           │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 9. Validação 9: Resposta                                      │
│    return JSON 201 Created                                     │
│    {                                                            │
│      "success": true,                                          │
│      "message": "Check-in realizado com sucesso!",            │
│      "data": {                                                 │
│        "checkin_id": 123,                                      │
│        "turma": {                                              │
│          "id": 494,                                            │
│          "nome": "CrossFit - 05:00 - Beatriz Oliveira",       │
│          "professor": "Beatriz Oliveira",                     │
│          "modalidade": "CrossFit"                             │
│        },                                                       │
│        "data_checkin": "2026-01-11 14:30:45",                │
│        "vagas_atualizadas": 14                                │
│      }                                                         │
│    }                                                            │
└────────────────────────────────────────────────────────────────┘
                            ↓
┌────────────────────────────────────────────────────────────────┐
│ 10. Resposta HTTP                                              │
│     Status: 201 Created                                        │
│     Content-Type: application/json; charset=utf-8              │
│     Body: [JSON acima]                                         │
└────────────────────────────────────────────────────────────────┘
```

---

## Estrutura de Classes

### App\Models\Checkin

```php
class Checkin {
    private PDO $db;
    
    // Métodos Originais
    public function create(int $usuarioId, int $horarioId): ?int
    public function createByAdmin(int $usuarioId, int $horarioId, int $adminId): ?int
    public function getByUsuarioId(int $usuarioId): array
    public function findById(int $id): ?array
    public function delete(int $id): bool
    public function usuarioTemCheckin(int $usuarioId, int $horarioId): bool
    
    // NOVOS Métodos para Turma-based Check-in
    public function createEmTurma(int $usuarioId, int $turmaId): ?int
        ├─ INSERT INTO checkins (usuario_id, turma_id, registrado_por_admin)
        ├─ try/catch PDOException (code 23000)
        └─ return checkin_id | null
    
    public function usuarioTemCheckinNaTurma(int $usuarioId, int $turmaId): bool
        ├─ SELECT COUNT(*) FROM checkins
        ├─ WHERE usuario_id AND turma_id
        └─ return boolean
}
```

### App\Controllers\MobileController

```php
class MobileController {
    private Usuario $usuarioModel;
    private Turma $turmaModel;           // NOVO
    private Checkin $checkinModel;       // NOVO
    private PDO $db;
    
    public function __construct()
    
    // Métodos Existentes
    public function perfil(Request, Response): Response
    public function tenants(Request, Response): Response
    public function contratos(Request, Response): Response
    public function planos(Request, Response): Response
    public function historicoCheckins(Request, Response): Response
    public function horariosHoje(Request, Response): Response
    public function horariosProximos(Request, Response): Response
    public function horariosPorDia(Request, Response, array): Response
    public function planosDoUsuario(Request, Response): Response
    public function horariosDisponiveis(Request, Response): Response
    public function detalheMatricula(Request, Response, array): Response
    
    // NOVO Método
    public function registrarCheckin(Request $request, Response $response): Response {
        ├─ 9 Validações (conforme fluxo acima)
        ├─ $this->turmaModel->findById()
        ├─ $this->checkinModel->usuarioTemCheckinNaTurma()
        ├─ $this->turmaModel->contarAlunos()
        ├─ $this->checkinModel->createEmTurma()
        └─ return JSON 201/400/404/500
    }
}
```

---

## Fluxo de Requisição: 9 Validações

```
HTTP Request
    ↓
[V1] tenantId obrigatório ──→ 400 se falha
    ↓
[V2] turma_id obrigatório ──→ 400 se falha
    ↓
[V3] turma_id tipo int ──→ (conversão)
    ↓
[V4] Turma existe ──→ 404 se falha
    ↓
[V5] Turma pertence ao tenant ──→ 404 se falha
    ↓
[V6] Sem duplicata ──→ 400 se falha
    ↓
[V7] Vagas disponíveis ──→ 400 se falha
    ↓
[V8] Cria check-in ──→ 500 se falha (race condition)
    ↓
[V9] Retorna 201 ✅
```

---

## Comparação: Antigo vs Novo

```
┌─────────────────┬──────────────────────┬──────────────────────┐
│ Aspecto         │ Sistema Antigo        │ Sistema Novo         │
├─────────────────┼──────────────────────┼──────────────────────┤
│ Tabela BD       │ horarios             │ turmas               │
│ Foreign Key     │ horario_id           │ turma_id             │
│ Conceito        │ Horário específico   │ Classe inteira       │
│ Exemplo         │ "05:00"              │ "CrossFit 05:00"     │
│ Check-in agrupa │ Por horário          │ Por turma            │
│ Vagas           │ Por horário          │ Por turma            │
│ Duplicatas      │ 1 por horário/user   │ 1 por turma/user     │
│ Método          │ create()             │ createEmTurma()      │
│ Validação dupl. │ usuarioTemCheckin()  │ usuarioTemCheckinN...│
│ App exibe       │ Apenas horário       │ Turma completa       │
│ Coluna BD       │ horario_id (EXISTS)  │ turma_id (NOVO)      │
└─────────────────┴──────────────────────┴──────────────────────┘
```

---

## Tratamento de Erros

```
Erro                           Status  Resposta
─────────────────────────────  ──────  ──────────────────────────
Input inválido                  400    {"error": "..."}
Turma não existe                404    {"error": "..."}
Turma de outro tenant           404    {"error": "..."}
Sem vagas                       400    {"error": "..."}
Duplicata                       400    {"error": "..."}
Race condition (2x simultâneo)  500    {"error": "..."}
Sucesso                         201    {"success": true, "data": {...}}
```

---

## Performance

| Operação | Query Count | Índices Usados | Tempo Est. |
|----------|-------------|-----------------|-----------|
| findById() | 1 | PK turmas.id | < 1ms |
| usuarioTemCheckinNaTurma() | 1 | IDX usuario+turma | < 1ms |
| contarAlunos() | 1 | IDX turma_id | < 1ms |
| createEmTurma() | 1 | PK checkins | < 1ms |
| Total endpoint | 4-5 | Múltiplos | 5-10ms |

---

## Segurança

```
┌─────────────────────────────────────────────┐
│ 1. Autenticação (JWT)                       │
│    └─ Valida token, extrai userId/tenantId │
│                                             │
│ 2. Autorização (Tenant Isolation)           │
│    └─ Verifica turma pertence ao tenant    │
│                                             │
│ 3. Validação Input                          │
│    ├─ Tipo (int)                            │
│    ├─ Obrigatoriedade                       │
│    └─ Existência (SELECT WHERE id)          │
│                                             │
│ 4. Constraint BD (Integridade)              │
│    ├─ Foreign Keys                          │
│    ├─ Unique (user + turma)                 │
│    └─ Not Null                              │
│                                             │
│ 5. Race Condition Protection                │
│    └─ try/catch PDOException (code 23000)   │
└─────────────────────────────────────────────┘
```

---

## Sequência de Inicialização

```
Application Start
    ↓
[Bootstrap] Slim Framework
    ↓
[Routes] Carrega routes/api.php
    ├─ Registra: POST /mobile/checkin
    └─ Handler: MobileController::registrarCheckin
    ↓
[Middleware] Autenticação JWT
    ├─ Valida token
    └─ Atribui userId, tenantId
    ↓
[Controller] MobileController::__construct()
    ├─ Instancia: $usuarioModel = new Usuario($db)
    ├─ Instancia: $turmaModel = new Turma($db)      [NOVO]
    ├─ Instancia: $checkinModel = new Checkin($db)  [NOVO]
    └─ Store: $this->db = require database.php
    ↓
[Pronto] Aguarda requisições HTTP
```

---

## Integração com App Mobile

```
App Frontend                       Backend API
─────────────────────────────     ─────────────────────────────

1. GET /mobile/horarios-disponiveis
   └─ Retorna: [{id, nome, prof, mod, vagas}]
   
2. Usuário toca na turma
   └─ Exibe: Confirmação com detalhes

3. Usuário clica "Check-in"
   └─ POST /mobile/checkin {turma_id: 494}

4. Backend valida (9 checks)
   └─ if sucesso: return 201 ✅
   └─ else: return erro

5. if 201: Mostra "Sucesso!"
   └─ Atualiza: vagas_restantes

6. if erro: Mostra mensagem
   └─ "Sem vagas", "Duplicado", etc.
```
