# Ajuste da Constraint de Check-in - Migration 058

## Problema Identificado

A constraint `UNIQUE (usuario_id, horario_id, data_checkin_date)` não funciona corretamente quando `horario_id` é NULL, pois:
- MySQL permite múltiplos NULL em colunas UNIQUE
- Se um check-in não preencher `horario_id`, o aluno poderá fazer vários check-ins no mesmo dia sem bloquear

## Solução Implementada

### ✅ Mudanças Estruturais

1. **Adicionar coluna `turma_id`** (se não existir)
   - Será a nova chave primária para unicidade
   - Preencha com dados existentes baseado em horario_id

2. **Tornar `turma_id` NOT NULL**
   - Todo check-in DEVE ter uma turma associada
   - Compatível com novo sistema de check-in por turmas

3. **Manter `horario_id` como NULL**
   - Compatibilidade com sistema antigo
   - Novos check-ins usam `turma_id`

4. **Remover constraint antiga**
   - `DROP INDEX unique_usuario_horario_data`
   - Usar nova constraint: `unique_usuario_turma_data`

5. **Criar nova constraint**
   - `UNIQUE (usuario_id, turma_id, data_checkin_date)`
   - Garante: 1 check-in por usuário por turma por dia

6. **Adicionar Foreign Key**
   - `FK: turma_id -> turmas(id)`
   - Integridade referencial

### ✅ Regras de Negócio

**Antes (problema):**
```
usuario_id=12, horario_id=NULL, data=2026-01-14 → Múltiplos permitidos ❌
```

**Depois (solução):**
```
usuario_id=12, turma_id=656, data=2026-01-14 → 1º permite ✅
usuario_id=12, turma_id=656, data=2026-01-14 → 2º bloqueia ❌
usuario_id=12, turma_id=664, data=2026-01-14 → Permite ✅ (turma diferente)
```

### ✅ Validações na Aplicação

Além da constraint no DB, o código PHP valida:

1. **VALIDAÇÃO 1: Máximo 1 check-in por dia** (Checkin.php)
   ```php
   usuarioTemCheckinNoDia($usuarioId, $data)
   ```

2. **VALIDAÇÃO 2: Limite semanal por plano** (Checkin.php)
   ```php
   contarCheckinsNaSemana($usuarioId)
   obterLimiteCheckinsPlano($usuarioId, $tenantId)
   ```

3. **VALIDAÇÃO 3: Não pode desfazer após aula começar** (MobileController.php)
   ```php
   desfazerCheckin() - Valida agora >= horario_inicio
   ```

## Execução

```bash
# Aplicar migration
mysql -u usuario -p banco < database/migrations/058_ajustar_checkins_constraint_turma_id.sql

# Ou usar script de execução
./database/run_all_migrations.sh
```

## Verificação

```sql
-- Ver estrutura da tabela
DESCRIBE checkins;

-- Ver constraints
SELECT CONSTRAINT_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'checkins';

-- Testar constraint
-- Deve bloquear 2º insert com mesmo usuario_id, turma_id, data
INSERT INTO checkins (usuario_id, turma_id, data_checkin_date) 
VALUES (12, 656, '2026-01-14');
```

## Compatibilidade

✅ Compatível com:
- Novo sistema de check-in por turmas (mobile app)
- Sistema antigo com horario_id (será preenchido até deprecação)
- Múltiplas turmas no mesmo dia (valida apenas por turma)

## Impacto

- **Nenhum breaking change** para código existente
- `horario_id` continua existente (compatibilidade)
- Novos check-ins usam `turma_id` (obrigatório)
- Queries antigas com `horario_id` continuam funcionando
