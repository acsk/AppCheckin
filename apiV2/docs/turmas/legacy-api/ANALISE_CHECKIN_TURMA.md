# Análise: Check-in em Turma (Após Mudanças na Estrutura)

## 🔍 SITUAÇÃO ATUAL

### Mudança Principal
A estrutura de dados foi alterada:
- **ANTES**: Checkins eram feitos em `horarios` (tabela horarios + tabela checkins)
- **AGORA**: Existem **`turmas`** (Classes específicas com professor, modalidade, horário, limite de alunos)

### Tabelas Envolvidas

#### 1. **Tabela `turmas`** (Nova estrutura)
```
id | tenant_id | professor_id | modalidade_id | dia_id | 
horario_inicio | horario_fim | nome | limite_alunos | ativo | created_at | updated_at
```

#### 2. **Tabela `dias`**
```
id | data | ativo | created_at | updated_at
```

#### 3. **Tabela `checkins`** (Atual)
```
id | usuario_id | horario_id | registrado_por_admin | admin_id | created_at
```

⚠️ **PROBLEMA IDENTIFICADO**: A tabela `checkins` ainda usa `horario_id`, NÃO `turma_id`!

---

## 📊 FLUXO ATUAL (Como Funciona Agora)

```
1. App Mobile chama: GET /mobile/horarios-disponiveis?data=2026-01-11
   ↓
2. Retorna: Lista de TURMAS (não horários)
   {
     turmas: [
       { id: 494, nome: "CrossFit - 05:00", professor, modalidade, horario: {inicio, fim}, ... }
     ]
   }
   ↓
3. Usuário seleciona uma TURMA (ex: turma_id = 494)
   ↓
4. App precisa fazer POST /mobile/checkin com { turma_id: 494 }
   ↓
5. PROBLEMA: CheckinController espera { horario_id } não { turma_id }
```

---

## 🚨 PROBLEMAS IDENTIFICADOS

### Problema 1: Mismatch de IDs
- **Endpoint retorna**: `turma_id`
- **CheckinController espera**: `horario_id`
- **Banco de dados usa**: `horario_id` na tabela checkins

### Problema 2: Relação entre Turma e Horário
- Uma **turma** tem: `horario_inicio` e `horario_fim` (strings de tempo)
- A tabela **horarios** provavelmente tem: `id`, `dia_id`, `hora`, etc.
- **Falta relação direta**: Como ligar `turma_id` → `horario_id`?

### Problema 3: Modelo de Checkin
```php
// CheckinController.php - linha 48
$horarioId = (int) $data['horario_id'];  // ← Espera isso
$this->checkinModel->usuarioTemCheckin($userId, $horarioId);
$this->horarioModel->podeRealizarCheckin($horarioId);
```

---

## ✅ SOLUÇÃO NECESSÁRIA

### Opção A: Adicionar coluna `turma_id` ao Checkin (RECOMENDADO)
```sql
ALTER TABLE checkins ADD COLUMN turma_id INT NOT NULL AFTER usuario_id;
ALTER TABLE checkins ADD FOREIGN KEY (turma_id) REFERENCES turmas(id);
```

**Vantagens:**
- Rastreia direto qual turma foi usada
- Suporta limite de alunos por turma
- Facilita relatórios

### Opção B: Manter apenas `horario_id` (REQUER MUDANÇAS NA TURMA)
```php
// Turma precisa ter horario_id
// Turma teria: horario_id (FK para horarios)
// Ao invés de: horario_inicio, horario_fim
```

**Vantagens:**
- Compatível com código existente
- Menos mudanças no banco

---

## 🔧 MUDANÇAS RECOMENDADAS

### 1. **Banco de Dados**
Adicionar `turma_id` ao checkins:
```sql
ALTER TABLE checkins ADD COLUMN turma_id INT AFTER usuario_id;
ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma 
  FOREIGN KEY (turma_id) REFERENCES turmas(id);
```

### 2. **Modelo Checkin** (app/Models/Checkin.php)
```php
public function create(int $usuarioId, int $turmaId): ?int
{
    try {
        $stmt = $this->db->prepare(
            "INSERT INTO checkins (usuario_id, turma_id, registrado_por_admin) 
             VALUES (:usuario_id, :turma_id, 0)"
        );
        
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'turma_id' => $turmaId
        ]);

        return (int) $this->db->lastInsertId();
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            return null;
        }
        throw $e;
    }
}

public function usuarioTemCheckinNaTurma(int $userId, int $turmaId): bool
{
    $stmt = $this->db->prepare(
        "SELECT COUNT(*) as count FROM checkins 
         WHERE usuario_id = :usuario_id AND turma_id = :turma_id"
    );
    $stmt->execute(['usuario_id' => $userId, 'turma_id' => $turmaId]);
    return (int) $stmt->fetch()['count'] > 0;
}
```

### 3. **Controller Mobile** (app/Controllers/MobileController.php)

Modificar método `registrarCheckin`:
```php
public function registrarCheckin(Request $request, Response $response): Response
{
    try {
        $userId = $request->getAttribute('userId');
        $tenantId = $request->getAttribute('tenantId');
        $body = $request->getParsedBody() ?? [];
        
        $turmaId = $body['turma_id'] ?? null;
        
        if (!$turmaId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'turma_id é obrigatório'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Validar se turma existe e pertence ao tenant
        $turma = $this->turmaModel->findById($turmaId, $tenantId);
        
        if (!$turma) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Turma não encontrada'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Verificar se usuário já fez check-in nesta turma
        if ($this->checkinModel->usuarioTemCheckinNaTurma($userId, $turmaId)) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Você já realizou check-in nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Verificar vagas disponíveis
        $alunosCount = $this->turmaModel->contarAlunos($turmaId);
        if ($alunosCount >= $turma['limite_alunos']) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Sem vagas disponíveis nesta turma'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Registrar check-in
        $checkinId = $this->checkinModel->create($userId, $turmaId);

        if (!$checkinId) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Erro ao registrar check-in'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Check-in realizado com sucesso!',
            'data' => [
                'checkin_id' => $checkinId,
                'turma' => [
                    'id' => (int) $turma['id'],
                    'nome' => $turma['nome'],
                    'professor' => $turma['professor_nome'],
                    'modalidade' => $turma['modalidade_nome']
                ],
                'data_checkin' => date('Y-m-d H:i:s')
            ]
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

    } catch (\Exception $e) {
        error_log("Erro em registrarCheckin: " . $e->getMessage());
        $response->getBody()->write(json_encode([
            'success' => false,
            'error' => 'Erro ao registrar check-in'
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}
```

### 4. **MobileController** (Adicionar propriedades)
```php
private Turma $turmaModel;
private Checkin $checkinModel;

public function __construct()
{
    $this->db = require __DIR__ . '/../../config/database.php';
    $this->turmaModel = new Turma($this->db);
    $this->checkinModel = new Checkin($this->db);
    // ... outras propriedades
}
```

---

## 📋 RESUMO DAS MUDANÇAS

| Componente | Mudança | Motivo |
|-----------|---------|--------|
| **Banco** | Adicionar `turma_id` ao checkins | Vincular check-in direto à turma |
| **Modelo Checkin** | Aceitar `turma_id` | Refletir nova estrutura |
| **MobileController** | Método `registrarCheckin` aceita `turma_id` | Compatível com nova resposta |
| **Rota Mobile** | POST `/mobile/checkin` com `turma_id` | Consistência com dados retornados |

---

## 🚀 FLUXO FINAL (Após Mudanças)

```
1. GET /mobile/horarios-disponiveis?data=2026-01-11
   ↓ Retorna: List de turmas [{ id: 494, nome: "CrossFit...", ... }]
   ↓
2. User seleciona turma_id = 494
   ↓
3. POST /mobile/checkin { turma_id: 494 }
   ↓
4. MobileController valida turma, vagas, duplicatas
   ↓
5. Cria checkin com turma_id
   ↓
6. Retorna: { success: true, checkin_id: X, turma: { ... } }
```

---

## ⚠️ IMPACTO EM OUTROS SISTEMAS

- **CheckinController (Admin)**: Pode continuar usando `horario_id` (compatível)
- **Horarios Model**: Sem mudanças necessárias
- **Turma Model**: Sem mudanças necessárias
- **Relatórios**: Precisarão atualizar JOINs para usar `turma_id`
