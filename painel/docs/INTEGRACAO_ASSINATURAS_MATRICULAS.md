# Integração Assinaturas com Matrículas

## 📋 Visão Geral

O sistema de **assinaturas** está integrado com o sistema de **matrículas**. Uma assinatura representa o plano ativo de um aluno, enquanto a matrícula representa o vínculo do aluno com a academia.

**Relação:**
- Uma matrícula pode ter **uma** assinatura
- Uma assinatura está vinculada a **uma** matrícula
- Status da assinatura sincroniza com status da matrícula

---

## 🔄 Relação Matrícula vs Assinatura

### Estrutura de Dados

```
┌─────────────────────────────────┐
│         MATRÍCULA               │
├─────────────────────────────────┤
│ id                              │
│ aluno_id                        │
│ academia_id                     │
│ plano_id                        │
│ data_inicio                     │
│ proxima_data_vencimento         │
│ status: ativa/suspensa/cancelada│
│ forma_pagamento                 │
└──────────────┬──────────────────┘
               │ 1:1
               ▼
┌─────────────────────────────────┐
│         ASSINATURA              │
├─────────────────────────────────┤
│ id                              │
│ matricula_id (FK)               │
│ aluno_id                        │
│ plano_id                        │
│ data_inicio                     │
│ data_vencimento                 │
│ status: ativa/suspensa/cancelada│
│ valor_mensal                    │
│ forma_pagamento                 │
│ renovacoes_restantes            │
└─────────────────────────────────┘
```

### Fluxo de Criação

```
Usuario cria Matrícula
        ↓
   ┌─ Novo Aluno?
   │   ├─ Sim: Criar Aluno
   │   └─ Não: Usar Existente
   │
   ├─ Criar Matrícula
   │   (status: ativa)
   │
   ├─ Auto-criar Assinatura?
   │   ├─ Sim (padrão)
   │   │   └─ Criar Assinatura
   │   │       (vinculada à matrícula)
   │   │
   │   └─ Não
   │       └─ Assinatura criada manualmente depois
   │
   └─ Ambos com status: ATIVA
```

---

## 📡 Endpoints Integrados

### 1. Criar Matrícula COM Assinatura Automática

```bash
POST /admin/matriculas
Content-Type: application/json

{
  "aluno_id": 5,
  "plano_id": 2,
  "data_inicio": "2025-01-15",
  "forma_pagamento": "cartao_credito",
  "criar_assinatura": true  ← Novo parâmetro
}
```

**Response:**
```json
{
  "type": "success",
  "message": "Matrícula e assinatura criadas com sucesso",
  "data": {
    "matricula": { ... },
    "assinatura": {
      "id": 1,
      "matricula_id": 10,
      "status": "ativa",
      "data_vencimento": "2025-02-15"
    }
  }
}
```

### 2. Criar Assinatura a Partir de Matrícula Existente

```bash
POST /admin/matriculas/{matricula_id}/assinatura
Content-Type: application/json

{
  "data_inicio": "2025-01-15",
  "renovacoes": 12
}
```

**Response:**
```json
{
  "type": "success",
  "message": "Assinatura criada para matrícula",
  "data": {
    "assinatura": {
      "id": 1,
      "matricula_id": 10,
      "aluno_id": 5,
      "status": "ativa"
    }
  }
}
```

### 3. Sincronizar Status Assinatura com Matrícula

```bash
POST /admin/assinaturas/{assinatura_id}/sincronizar-matricula
```

**O que faz:**
- Se matrícula está CANCELADA → Cancela assinatura
- Se matrícula está SUSPENSA → Suspende assinatura
- Se matrícula está ATIVA → Ativa assinatura
- Atualiza datas de vencimento

### 4. Listar Assinaturas com Dados de Matrícula

```bash
GET /admin/assinaturas?status=ativa&incluir_matriculas=true

# Response inclui:
{
  "assinaturas": [
    {
      "id": 1,
      "aluno_nome": "João Silva",
      "plano_nome": "Ouro",
      "status": "ativa",
      "data_vencimento": "2025-02-15",
      "matricula": {
        "id": 10,
        "status": "ativa",
        "proxima_data_vencimento": "2025-02-15"
      }
    }
  ]
}
```

### 5. Listar Assinaturas sem Matrícula

```bash
GET /admin/assinaturas/sem-matricula

# Response:
{
  "assinaturas": [
    {
      "id": 5,
      "aluno_nome": "Maria Santos",
      "status": "ativa",
      "motivo": "Assinatura criada manualmente sem vincular a matrícula"
    }
  ],
  "total": 3
}
```

---

## 🔐 Sincronização de Status

### Fluxo de Status Sincronizado

```
MATRÍCULA STATUS          ASSINATURA STATUS
─────────────────────────────────────────────
     ATIVA         ←→        ATIVA
     ↓                         ↓
  SUSPENSA        ←→      SUSPENSA
     ↓                         ↓
  CANCELADA       ←→      CANCELADA
```

### Regras de Sincronização

| Evento | Matrícula | Assinatura | Ação |
|--------|-----------|-----------|------|
| Criar Matrícula | ATIVA | - | Cria assinatura (se `criar_assinatura=true`) |
| Suspender Matrícula | SUSPENSA | ATIVA | Suspende assinatura |
| Reativar Matrícula | ATIVA | SUSPENSA | Ativa assinatura |
| Cancelar Matrícula | CANCELADA | ATIVA | Cancela assinatura |
| Renovar Assinatura | ATIVA | ATIVA | Atualiza `proxima_data_vencimento` na matrícula |
| Cancelar Assinatura | ATIVA | CANCELADA | Cancela matrícula também |

---

## 💾 Estrutura de Dados

### Tabela `assinaturas` - Campo Novo

```sql
ALTER TABLE assinaturas ADD COLUMN (
  matricula_id INT NULL UNIQUE,
  FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE
);
```

### Tabela `matriculas` - Campos Existentes

```sql
-- Campos que já estão em matriculas:
- id
- aluno_id
- academia_id
- plano_id
- data_inicio
- proxima_data_vencimento
- forma_pagamento
- status
```

---

## 🔄 Migrations (SQL)

```sql
-- 1. Adicionar coluna em assinaturas
ALTER TABLE assinaturas ADD COLUMN (
  matricula_id INT UNIQUE NULL,
  FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
  INDEX idx_matricula_id (matricula_id)
);

-- 2. Atualizar assinaturas existentes vinculadas a matrículas
UPDATE assinaturas a
INNER JOIN matriculas m ON a.aluno_id = m.aluno_id 
  AND a.plano_id = m.plano_id 
  AND a.academia_id = m.academia_id
SET a.matricula_id = m.id
WHERE a.matricula_id IS NULL;

-- 3. Criar índice para sincronização rápida
CREATE INDEX idx_assinatura_matricula_sync 
ON assinaturas(matricula_id, status);
```

---

## 📱 Frontend - Fluxo de Uso

### Opção 1: Criar Matrícula COM Assinatura (Recomendado)

```javascript
import { matriculaService } from '../../services/matriculaService';

// Usuário preenche formulário e clica "Criar"
const handleCriarMatriculaComAssinatura = async (formData) => {
  try {
    const resultado = await matriculaService.criar({
      aluno_id: 5,
      plano_id: 2,
      data_inicio: "2025-01-15",
      forma_pagamento: "cartao_credito",
      criar_assinatura: true  // ← Cria assinatura automaticamente
    });

    console.log('✅ Matrícula:', resultado.matricula);
    console.log('✅ Assinatura:', resultado.assinatura);
    
    showToast('Matrícula e assinatura criadas com sucesso');
  } catch (error) {
    showError(error.message);
  }
};
```

### Opção 2: Criar Assinatura Depois

```javascript
import assinaturaService from '../../services/assinaturaService';

// Primeiro cria apenas matrícula
const resultado = await matriculaService.criar({
  aluno_id: 5,
  plano_id: 2,
  criar_assinatura: false  // Não cria assinatura
});

// Depois cria assinatura para aquela matrícula
const assinatura = await assinaturaService.criarDasMatricula(
  resultado.matricula.id,
  { renovacoes: 12 }
);
```

### Opção 3: Sincronizar Manualmente

```javascript
import assinaturaService from '../../services/assinaturaService';

// Se houve desincronização, sincroniza
const status = await assinaturaService.obterStatusSincronizacao(assinaturaId);

if (!status.sincronizado) {
  console.log('⚠️ Desincronizado! Sincronizando...');
  await assinaturaService.sincronizarComMatricula(assinaturaId);
  console.log('✅ Sincronizado!');
}
```

---

## 🧪 Exemplos de Teste

### Teste 1: Criar Matrícula + Assinatura Juntas

```bash
curl -X POST http://localhost:8080/admin/matriculas \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "aluno_id": 5,
    "plano_id": 2,
    "data_inicio": "2025-01-15",
    "forma_pagamento": "cartao_credito",
    "criar_assinatura": true
  }'

# Resposta: Ambas criadas com sucesso
```

### Teste 2: Assinatura a Partir de Matrícula

```bash
# Primeiro cria matrícula SEM assinatura
curl -X POST http://localhost:8080/admin/matriculas \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "aluno_id": 5,
    "plano_id": 2,
    "data_inicio": "2025-01-15",
    "criar_assinatura": false
  }'

# Depois cria assinatura para essa matrícula (ID: 10)
curl -X POST http://localhost:8080/admin/matriculas/10/assinatura \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "data_inicio": "2025-01-15",
    "renovacoes": 12
  }'
```

### Teste 3: Sincronizar Status

```bash
# Suspender matrícula afeta assinatura
curl -X POST http://localhost:8080/admin/matriculas/10/suspender \
  -H "Authorization: Bearer TOKEN"

# Verificar sincronização
curl -X GET http://localhost:8080/admin/assinaturas/1/status-sincronizacao \
  -H "Authorization: Bearer TOKEN"

# Forçar sincronização
curl -X POST http://localhost:8080/admin/assinaturas/1/sincronizar-matricula \
  -H "Authorization: Bearer TOKEN"
```

---

## ⚙️ Backend - Implementação

### AssinaturaController - Método Novo

```php
/**
 * POST /admin/matriculas/{id}/assinatura
 * Criar assinatura para matrícula existente
 */
public function criarDasMatricula(Request $request, Response $response, array $args)
{
    try {
        $matriculaId = $args['id'];
        $body = $request->getParsedBody();

        // Buscar matrícula
        $stmt = $this->db->prepare(
            "SELECT * FROM matriculas WHERE id = ? AND academia_id = ?"
        );
        $stmt->execute([$matriculaId, $request->getAttribute('tenant_id')]);
        $matricula = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$matricula) {
            return $this->error($response, 'Matrícula não encontrada', 404);
        }

        // Verificar se já tem assinatura
        $existsStmt = $this->db->prepare(
            "SELECT id FROM assinaturas WHERE matricula_id = ?"
        );
        $existsStmt->execute([$matriculaId]);
        if ($existsStmt->fetch()) {
            return $this->error($response, 'Esta matrícula já tem assinatura', 409);
        }

        // Criar assinatura
        $dataInicio = $matricula['data_inicio'];
        $dataVencimento = $this->calcularDataVencimento(
            $dataInicio,
            $matricula['ciclo_tipo']
        );

        $sql = "INSERT INTO assinaturas 
            (matricula_id, aluno_id, plano_id, academia_id, status, 
             data_inicio, data_vencimento, valor_mensal, forma_pagamento, 
             ciclo_tipo, permite_recorrencia, renovacoes_restantes, 
             criado_em, atualizado_em)
            VALUES (?, ?, ?, ?, 'ativa', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $matriculaId,
            $matricula['aluno_id'],
            $matricula['plano_id'],
            $request->getAttribute('tenant_id'),
            $dataInicio,
            $dataVencimento,
            $matricula['valor'],
            $matricula['forma_pagamento'],
            $matricula['ciclo_tipo'],
            true,
            $body['renovacoes'] ?? 0
        ]);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Assinatura criada para matrícula',
            'data' => ['assinatura' => ['id' => $this->db->lastInsertId()]]
        ]));

        return $response->withStatus(201);
    } catch (\Exception $e) {
        return $this->error($response, $e->getMessage(), 500);
    }
}

/**
 * POST /admin/assinaturas/{id}/sincronizar-matricula
 * Sincronizar status com matrícula
 */
public function sincronizarComMatricula(Request $request, Response $response, array $args)
{
    try {
        $assinaturaId = $args['id'];

        $stmt = $this->db->prepare(
            "SELECT a.id, a.matricula_id, a.status,
                    m.status as matricula_status
             FROM assinaturas a
             LEFT JOIN matriculas m ON a.matricula_id = m.id
             WHERE a.id = ? AND a.academia_id = ?"
        );
        $stmt->execute([$assinaturaId, $request->getAttribute('tenant_id')]);
        $assinatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assinatura) {
            return $this->error($response, 'Assinatura não encontrada', 404);
        }

        if (!$assinatura['matricula_id']) {
            return $this->error($response, 'Assinatura sem matrícula associada', 400);
        }

        // Sincronizar status
        $novoStatus = $assinatura['matricula_status'] ?? 'ativa';
        
        $updateStmt = $this->db->prepare(
            "UPDATE assinaturas SET status = ?, atualizado_em = NOW() WHERE id = ?"
        );
        $updateStmt->execute([$novoStatus, $assinaturaId]);

        $response->getBody()->write(json_encode([
            'type' => 'success',
            'message' => 'Status sincronizado com matrícula',
            'data' => ['novo_status' => $novoStatus]
        ]));

        return $response->withStatus(200);
    } catch (\Exception $e) {
        return $this->error($response, $e->getMessage(), 500);
    }
}
```

---

## 📊 Casos de Uso

### Caso 1: Aluno Novo Faz Matrícula

```
1. Admin cria matrícula para aluno novo
   POST /admin/matriculas
   └─ criar_assinatura: true
   
2. Sistema:
   ├─ Cria aluno
   ├─ Cria matrícula (status: ATIVA)
   └─ Cria assinatura (status: ATIVA)
   
3. Resultado:
   ├─ Aluno com acesso imediato
   └─ Assinatura ativa para cobranças
```

### Caso 2: Aluno Atrasa Pagamento

```
1. Pagamento vence
   └─ Webhook de pagamento atualiza status
   
2. Sistema:
   ├─ Matrícula → SUSPENSA
   └─ Assinatura → SUSPENSA (via sincronização)
   
3. Resultado:
   └─ Aluno perde acesso
```

### Caso 3: Aluno Paga e Reativa

```
1. Admin recebe pagamento
   └─ Clica "Reativar matrícula"
   
2. Sistema:
   ├─ PUT /admin/matriculas/10 → status: ATIVA
   └─ Assinatura sincroniza → status: ATIVA
   
3. Resultado:
   └─ Aluno recupera acesso
```

### Caso 4: Aluno Renova Assinatura

```
1. Admin clica "Renovar" na assinatura
   └─ POST /admin/assinaturas/1/renovar
   
2. Sistema:
   ├─ Estende data_vencimento da assinatura
   ├─ Atualiza proxima_data_vencimento da matrícula
   └─ Registra renovação no histórico
   
3. Resultado:
   └─ Ambos com datas sincronizadas
```

---

## 🛡️ Validações

### Ao Criar Matrícula COM Assinatura

```javascript
✓ Aluno válido e ativo
✓ Plano válido e ativo
✓ Não há assinatura ativa para este aluno+plano
✓ Não há matrícula ativa para este aluno+plano
✓ Data de início válida
✓ Forma de pagamento suportada
```

### Ao Criar Assinatura de Matrícula

```javascript
✓ Matrícula existe e pertence à academia
✓ Matrícula está em status ATIVA
✓ Matrícula não tem assinatura associada
✓ Dados consistentes (aluno_id, plano_id, etc)
```

### Ao Sincronizar

```javascript
✓ Assinatura tem matricula_id
✓ Matrícula associada existe
✓ Status são compatíveis para sincronização
```

---

## 📈 Benefícios da Integração

✅ **Unificação de Status**: Uma mudança no status da matrícula automaticamente afeta a assinatura

✅ **Consistência de Datas**: Datas de vencimento sincronizadas entre matrícula e assinatura

✅ **Facilidade de Uso**: Admin não precisa gerenciar duas entidades separadas

✅ **Segurança**: Matrícula cancelada = Assinatura cancelada automaticamente

✅ **Auditoria**: Histórico completo de mudanças em ambas as tabelas

✅ **Flexibilidade**: Opção de criar ambas juntas OU criar assinatura depois

---

## 🔄 Fluxograma Completo

```
┌─ Criar Matrícula ─┐
│                   │
│ criar_assinatura? │
│   ↙         ↘    │
│ SIM       NÃO    │
│  │         │     │
│  ▼         ▼     │
│ Criar    Esperar │
│Assinatura criar  │
│  │       depois  │
│  │         │     │
│  └────┬────┘     │
│       │          │
│       ▼          │
│  Matrícula ATIVA │
│  Assinatura ATIVA│
│                   │
└───────┬───────────┘
        │
   ┌────┴────┐
   │ Eventos │
   └────┬────┘
        │
    ┌───┴────────┬──────────┬──────────┐
    │            │          │          │
    ▼            ▼          ▼          ▼
 Suspender   Reativar   Renovar    Cancelar
    │            │          │          │
    ▼            ▼          ▼          ▼
Sincroniza   Sincroniza  Atualiza   Sincroniza
   Status      Status      Datas      Status
    │            │          │          │
    ▼            ▼          ▼          ▼
 SUSPENSA      ATIVA      VENCIDA    CANCELADA
```

---

**Status**: ✅ **Documentação de Integração Completa**

**Próximas Etapas**:
1. Implementar endpoints de sincronização no backend
2. Adicionar coluna `matricula_id` em `assinaturas`
3. Criar migrations SQL
4. Testar fluxos de sincronização
5. Atualizar tela de assinaturas para mostrar dados de matrícula
