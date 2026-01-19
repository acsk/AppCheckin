# Regra: Máximo 1 Matrícula Ativa por Usuário/Tenant

## Problema

Sem proteção adequada, um usuário poderia ter múltiplas matrículas simultâneas "ativa" no mesmo tenant:

```
usuario_id=12, tenant_id=5, status='ativa' → Matrícula A (plano 1x semana)
usuario_id=12, tenant_id=5, status='ativa' → Matrícula B (plano 2x semana) ❌
```

Isso pode acontecer por:
- Renovação sem cancelar a anterior
- Upgrade/downgrade sem finalizar
- Erro manual do admin
- Erro de sistema/race condition

## Solução Implementada (MVP - Opção A)

### 1. **Validação em Nível de Aplicação**

Arquivo: [app/Controllers/MatriculaController.php](app/Controllers/MatriculaController.php)

Método: `criar()`

**Lógica:**
```php
// 1. Iniciar transação
$db->beginTransaction();

// 2. Buscar todas as matrículas 'ativa' do usuário/tenant
SELECT id FROM matriculas 
WHERE usuario_id = ? AND tenant_id = ? AND status = 'ativa'
FOR UPDATE;  // Evita race condition

// 3. Cancelar todas ativas encontradas
UPDATE matriculas 
SET status = 'cancelada',
    cancelado_por = ?,
    motivo_cancelamento = 'Nova matrícula criada'
WHERE id = ? AND status = 'ativa';

// 4. Criar nova matrícula com status='ativa'
INSERT INTO matriculas (...) 
VALUES (...);

// 5. Confirmar transação (all or nothing)
$db->commit();
```

### 2. **Transação ACID**

Garante atomicidade:
- **A**tomicity: Tudo ou nada (não fica no meio)
- **C**onsistency: Estado válido ao final
- **I**solation: `FOR UPDATE` evita race condition
- **D**urability: Persiste após commit

### 3. **Resposta da API**

```json
{
  "message": "Matrícula criada com sucesso",
  "data": {
    "id": 42,
    "usuario_id": 12,
    "plano_id": 25,
    "status": "ativa",
    "motivo": "renovacao",
    "matriculas_anteriores_canceladas": 1
  }
}
```

## Comportamento

### Cenário 1: Primeira Matrícula
```
Entrada: usuario_id=12, tenant_id=5, plano_id=25
Ação: 
  - Nenhuma matrícula ativa encontrada
  - Criar nova matrícula status='ativa'
Resultado: ✅ 1 matrícula ativa
```

### Cenário 2: Renovação
```
Entrada: usuario_id=12, tenant_id=5, plano_id=25 (mesmo plano)
Estado Inicial: 1 matrícula ativa com plano_id=25
Ação:
  - Encontra 1 matrícula ativa
  - Cancela com motivo_cancelamento='Nova matrícula criada'
  - Cria nova matrícula status='ativa' com motivo='renovacao'
  - matricula_anterior_id aponta para cancelada
Resultado: ✅ 1 matrícula ativa (renovada)
```

### Cenário 3: Upgrade
```
Entrada: usuario_id=12, tenant_id=5, plano_id=26 (plano diferente, valor maior)
Estado Inicial: 1 matrícula ativa com plano_id=25 (1x semana)
Ação:
  - Encontra 1 matrícula ativa
  - Cancela anterior
  - Cria nova matrícula status='ativa' com motivo='upgrade'
  - matricula_anterior_id=anterior, plano_anterior_id=25
Resultado: ✅ 1 matrícula ativa (upgrade)
```

### Cenário 4: Erro de Duplicação (máquina criou 2 antes da fix)
```
Estado Atual: 2 matrículas ativas
Entrada: usuario_id=12, tenant_id=5, plano_id=27
Ação:
  - Encontra 2 matrículas ativas (query retorna 2)
  - Cancela ambas
  - Cria nova matrícula
Resultado: ✅ 1 matrícula ativa (problema resolvido)
```

## Rastreabilidade

Cada matrícula cancelada registra:
- `status`: 'cancelada'
- `cancelado_por`: ID do admin que criou a nova
- `data_cancelamento`: Data atual
- `motivo_cancelamento`: 'Nova matrícula criada pelo admin'

E a nova matrícula registra:
- `matricula_anterior_id`: ID da cancelada
- `plano_anterior_id`: ID do plano anterior
- `motivo`: 'nova', 'renovacao', 'upgrade', 'downgrade'

## Integridade

**Proteção contra race condition:**
```sql
FOR UPDATE  -- Lock exclusivo até commit
```

Isso garante que se 2 admins criarem matrícula simultaneamente:
1. Admin A lê: 0 matrículas ativas
2. Admin B tenta ler: AGUARDA (locked)
3. Admin A cria + commit
4. Admin B lê: 1 matrícula ativa
5. Admin B cancela + cria (atômico)

## Futuro (Opção B - Constraint no DB)

Se quiser proteção no banco (além de app):

```sql
-- MySQL 8.0+: Coluna gerada
ALTER TABLE matriculas 
ADD COLUMN check_ativa INT GENERATED ALWAYS AS 
(IF(status = 'ativa', 1, NULL)) STORED;

CREATE UNIQUE INDEX ux_matricula_ativa 
ON matriculas (usuario_id, tenant_id, check_ativa);
```

Permite:
- Múltiplos NULL (matrículas inativas)
- Máximo 1 com valor=1 (matrículas ativas)

Porém, para MVP, a validação em app é suficiente e mais flexível.

## Verificação

Query para auditar se regra está sendo respeitada:

```sql
SELECT usuario_id, tenant_id, COUNT(*) as total_ativas
FROM matriculas
WHERE status = 'ativa'
GROUP BY usuario_id, tenant_id
HAVING total_ativas > 1;

-- Resultado esperado: 0 rows
```

Se houver resultado, executar script de limpeza em migration 059.

## Status

✅ **Implementada e testada**
- Transação ACID implementada
- Locking com FOR UPDATE
- Rastreabilidade completa
- Resposta da API com contagem de canceladas
