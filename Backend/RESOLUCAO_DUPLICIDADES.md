# Resolução de Duplicidades - Análise e Fix

## 🔍 O Problema

Você reportou que havia múltiplas turmas com **exatamente o mesmo horário** (06:00:00 - 07:00:00) no mesmo dia. Isso violava a validação de conflito implementada.

### Dados Problemáticos
```
ID 187: 06:00-07:00 (João Pedro)
ID 188: 06:00-07:00 (Maria Silva)
ID 189: 06:00-07:00 (Fernando Costa)
ID 190: 06:00-07:00 (Beatriz Oliveira)
ID 191: 06:00-07:00 (Lucas Santos)
ID 192: 06:00-07:00 (João Pedro)
ID 193: 06:00-07:00 (Maria Silva)
ID 194: 06:00-07:00 (Lucas Santos)
```

## ✅ Causa Identificada

O problema era nos **seeds do banco de dados**! Três arquivos seeds estavam tentando usar `horario_id`:

1. `seed_turmas_hoje_9jan.sql` - Inserindo turmas com horario_id
2. `seed_professores_turmas_crossfit.sql` - Usando SELECT com horarios table
3. `seed_tenant5_crossfit.sql` - Mesmo problema

Como removemos a coluna `horario_id` na migração, essas inserts ou falharam silenciosamente ou inseriam com valores incorretos.

## 🔧 Soluções Aplicadas

### 1. Atualizar Seeds para Nova Estrutura

**Antes** (seed_turmas_hoje_9jan.sql):
```sql
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_id, ...)
VALUES (5, 6, 1, 17, 47, 'CrossFit - 06:00 - João Pedro', ...)
```

**Depois** ✅:
```sql
INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, ...)
VALUES (5, 6, 1, 17, '06:00:00', '07:00:00', 'CrossFit - 06:00 - João Pedro', ...)
```

### 2. Remover JOINs com Tabela Horarios

**Antes** (seed_professores_turmas_crossfit.sql):
```sql
INSERT INTO turmas (..., horario_id, ...)
SELECT 1, 1, 1, h.dia_id, h.id, ...
FROM horarios h
WHERE h.hora = '06:00:00' AND h.dia_id <= 70
```

**Depois** ✅:
```sql
INSERT INTO turmas (..., horario_inicio, horario_fim, ...)
SELECT 1, 1, 1, d.id, '06:00:00', '07:00:00', ...
FROM dias d
WHERE d.ativo = 1 AND d.id <= 70
```

### 3. Limpar Dados Duplicados

Script `cleanup_duplicate_turmas.php`:
- Encontrou 8 turmas com o mesmo horário (06:00-07:00) no dia 17
- Manteve a ID 187 (primeira cronologicamente)
- Deletou IDs 188, 189, 190, 191, 192, 193, 194

**Resultado**: ✅ Nenhuma duplicata restante

## 📊 Dados Atuais

```
Total de turmas ativas: 74

Turmas do dia 09/01/2026:
- ID 195: 04:00-04:30 (Lucas Santos) - Turma de teste criada via API ✨
- ID 187: 06:00-07:00 (João Pedro) - Único horário 06:00 agora

Estatísticas:
- 04:00-04:30: 2 turmas
- 06:00-07:00: 72 turmas (reduzido de ~80)

Duplicatas: NENHUMA ✅
```

## 🎯 Validação Funcionando

Agora a validação de conflito está funcionando corretamente:

```php
// Detecta conflito quando há sobreposição:
horario_inicio_nova < horario_fim_existente AND 
horario_fim_nova > horario_inicio_existente
```

Exemplos:
- ✅ 04:00-04:30 permitido (não se sobrepõe com 06:00-07:00)
- ✅ 06:00-07:00 permitido APENAS uma vez por dia
- ❌ 04:15-04:45 bloqueado (sobrepõe com 04:00-04:30)

## 📝 Mudanças de Arquivo

| Arquivo | Mudança |
|---------|---------|
| `seed_turmas_hoje_9jan.sql` | Atualizado para usar horario_inicio/horario_fim |
| `seed_professores_turmas_crossfit.sql` | Removido SELECT da tabela horarios |
| `seed_tenant5_crossfit.sql` | Removido SELECT da tabela horarios |
| `cleanup_duplicate_turmas.php` | NOVO - Script de limpeza |
| `verify_turmas_final.php` | NOVO - Script de verificação |

## 🚀 Próximas Etapas

1. **Não rodar os seeds antigos** - Eles causarão o mesmo problema
2. **Se precisar re-popular dados** - Use apenas os seeds atualizados
3. **Validar no frontend** - Tente criar turmas com horários que se sobrepõem (deve falhar)

## ✨ Testes Recomendados

```bash
# 1. Criar turma com horário customizado
curl -X POST "http://localhost:8080/admin/turmas" \
  -H "Authorization: Bearer token" \
  -d '{"nome":"Test","dia_id":17,"horario_inicio":"05:00","horario_fim":"05:30",...}'

# 2. Tentar criar overlapping (deve falhar com 400)
curl -X POST "http://localhost:8080/admin/turmas" \
  -H "Authorization: Bearer token" \
  -d '{"nome":"Test","dia_id":17,"horario_inicio":"05:15","horario_fim":"05:45",...}'

# 3. Listar turmas do dia (verificar sem duplicatas)
curl -X GET "http://localhost:8080/admin/turmas?data=2026-01-09" \
  -H "Authorization: Bearer token"
```

## ✅ Status Final

- ✅ Causa identificada: seeds não atualizados
- ✅ Seeds corrigidos em 3 arquivos
- ✅ Duplicatas removidas (7 turmas deletadas)
- ✅ Nenhuma duplicata restante
- ✅ Validação funcionando corretamente
- ✅ Turmas customizadas podem ser criadas via API
