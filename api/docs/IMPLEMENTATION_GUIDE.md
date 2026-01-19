# ✅ Implementação de Check-in em Turmas - Guia de Execução

## Status das Alterações

### ✅ CÓDIGO IMPLEMENTADO (Concluído)

#### 1. Modelo Checkin.php
**Novos métodos adicionados:**
- `createEmTurma(int $usuarioId, int $turmaId): ?int`
- `usuarioTemCheckinNaTurma(int $usuarioId, int $turmaId): bool`

#### 2. MobileController.php
**Propriedades adicionadas:**
```php
private Turma $turmaModel;
private Checkin $checkinModel;
```

**Constructor atualizado:**
```php
public function __construct() {
    $this->db = require __DIR__ . '/../../config/database.php';
    $this->usuarioModel = new Usuario($this->db);
    $this->turmaModel = new Turma($this->db);
    $this->checkinModel = new Checkin($this->db);
}
```

**Novo método implementado:**
- `registrarCheckin(Request $request, Response $response): Response`

#### 3. Rota API
- ✅ Rota já existente em `routes/api.php`
- `POST /mobile/checkin` → `[MobileController::class, 'registrarCheckin']`

---

## 🔄 PRÓXIMAS ETAPAS - Execute Manualmente

### Passo 1: Executar Migration do Banco de Dados

Execute um dos seguintes comandos:

#### Opção A: Via PHP (Recomendado)
```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend
php run_migration.php
```

#### Opção B: Via MySQL Direto
```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend
mysql -h 127.0.0.1 -u root -proot app_checkin << 'EOF'
-- Verificar se coluna já existe
SHOW COLUMNS FROM checkins LIKE 'turma_id';

-- Se não existir, executar:
ALTER TABLE checkins ADD COLUMN turma_id INT NULL AFTER usuario_id;
ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE;

-- Verificar resultado
DESCRIBE checkins;
EOF
```

#### Opção C: Via Docker (Se MySQL estiver em container)
```bash
docker-compose exec mysql mysql -u root -proot app_checkin << 'EOF'
ALTER TABLE checkins ADD COLUMN turma_id INT NULL AFTER usuario_id;
ALTER TABLE checkins ADD CONSTRAINT fk_checkins_turma FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE;
EOF
```

---

### Passo 2: Reiniciar PHP

```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend
docker-compose restart php
sleep 2
```

---

### Passo 3: Testar o Endpoint

#### Test Token & Data
```
EMAIL: carolina.ferreira@tenant4.com
JWT: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoxMSwiZW1haWwiOiJjYXJvbGluYS5mZXJyZWlyYUB0ZW5hbnQ0LmNvbSIsInRlbmFudF9pZCI6NCwiaWF0IjoxNzY4MDg0MTUxLCJleHAiOjE3NjgxNzA1NTF9.NNkHk-tmAvpZBpdIga4KxE0YrVjAhYoeBcr3SKw_9XY
TURMA_ID: 494
```

#### Comando cURL - Sucesso (válido)
```bash
curl -X POST "http://localhost:8080/mobile/checkin" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoxMSwiZW1haWwiOiJjYXJvbGluYS5mZXJyZWlyYUB0ZW5hbnQ0LmNvbSIsInRlbmFudF9pZCI6NCwiaWF0IjoxNzY4MDg0MTUxLCJleHAiOjE3NjgxNzA1NTF9.NNkHk-tmAvpZBpdIga4KxE0YrVjAhYoeBcr3SKw_9XY" \
  -H "Content-Type: application/json" \
  -d '{"turma_id": 494}'
```

**Resposta esperada (201):**
```json
{
  "success": true,
  "message": "Check-in realizado com sucesso!",
  "data": {
    "checkin_id": 123,
    "turma": {
      "id": 494,
      "nome": "CrossFit - 05:00 - Beatriz Oliveira",
      "professor": "Beatriz Oliveira",
      "modalidade": "CrossFit"
    },
    "data_checkin": "2026-01-11 14:30:45",
    "vagas_atualizadas": 14
  }
}
```

#### Teste de Erro - turma_id ausente
```bash
curl -X POST "http://localhost:8080/mobile/checkin" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{}'
```

**Resposta esperada (400):**
```json
{
  "success": false,
  "error": "turma_id é obrigatório"
}
```

#### Teste de Erro - turma não encontrada
```bash
curl -X POST "http://localhost:8080/mobile/checkin" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{"turma_id": 9999}'
```

**Resposta esperada (404):**
```json
{
  "success": false,
  "error": "Turma não encontrada"
}
```

#### Teste de Erro - check-in duplicado
```bash
# Executar duas vezes o mesmo comando
curl -X POST "http://localhost:8080/mobile/checkin" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
  -H "Content-Type: application/json" \
  -d '{"turma_id": 494}'
```

**Resposta esperada na 2ª vez (400):**
```json
{
  "success": false,
  "error": "Você já realizou check-in nesta turma"
}
```

---

## 📊 Verificação da Estrutura do Banco

Após executar a migration, verifique:

```bash
mysql -h 127.0.0.1 -u root -proot app_checkin

DESCRIBE checkins;
```

**Esperado:**
- Coluna `turma_id` (INT, NULL)
- Foreign key `fk_checkins_turma` referenciando `turmas.id`
- Coluna `horario_id` ainda existe (para compatibilidade com código antigo)

---

## 🔗 Validações Implementadas

O endpoint `POST /mobile/checkin` agora valida:

1. ✅ **tenantId obrigatório** - Retorna 400 se não estiver no JWT
2. ✅ **turma_id obrigatório** - Retorna 400 se não estiver no body
3. ✅ **Turma existe** - Retorna 404 se turma_id inválido
4. ✅ **Turma pertence ao tenant** - Retorna 404 se turma é de outro tenant
5. ✅ **Check-in único por turma** - Retorna 400 se usuário já fez check-in
6. ✅ **Vagas disponíveis** - Retorna 400 se turma está cheia
7. ✅ **Duplicatas no BD** - Retorna 500 se constraint viola (janela de concorrência)

---

## 🎯 Fluxo Completo

1. **App pega lista de turmas:** `GET /mobile/horarios-disponiveis?data=2026-01-11`
   - Retorna: Array de turmas com `id`, `nome`, `professor`, `modalidade`, `vagas`

2. **Usuário seleciona uma turma** e clica em "Check-in"

3. **App envia:** `POST /mobile/checkin` com `{"turma_id": 494}`

4. **Backend valida** tudo (turma existe, tem vagas, sem duplicatas)

5. **Backend cria** check-in na tabela `checkins` com `usuario_id` e `turma_id`

6. **App recebe** confirmação com `checkin_id`, detalhes da turma, e vagas atualizadas

---

## 📝 Notas

- Coluna `horario_id` permanece para compatibilidade com check-ins antigos
- Novo sistema usa `turma_id` (ID da classe)
- Antigo usava `horario_id` (ID do horário)
- Ambos podem coexistir temporariamente para migração gradual
- Constraint de unicidade permitirá um check-in por turma por usuário
