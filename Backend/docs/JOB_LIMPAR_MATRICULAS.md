# 🧹 Job: Limpeza de Matrículas Duplicadas

## 📋 Descrição

Job automatizado que **limpa matrículas duplicadas e vencidas**, mantendo apenas a matrícula vigente para cada usuário em cada modalidade.

### Objetivo

Quando um usuário tem múltiplas matrículas (geralmente por erro ou duplicação), o job:
1. ✅ **Mantém a mais recente** que ainda está vigente (dentro do período)
2. ❌ **Cancela as antigas** que já venceram
3. 📍 **Garante uma única matrícula ativa** por modalidade por usuário

---

## 🚀 Como Usar

### Execução Manual
```bash
# Executar limpeza
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php

# Ver o que seria feito (sem alterar nada)
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run

# Processar apenas um tenant
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --tenant=4

# Modo silencioso
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --quiet
```

### Configurar Cron (Automático)

Editar crontab:
```bash
crontab -e
```

Adicionar linha para executar diariamente às 5 da manhã:
```
0 5 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1
```

Ou a cada 6 horas:
```
0 */6 * * * php /path/to/jobs/limpar_matriculas_duplicadas.php >> /var/log/limpar_matriculas.log 2>&1
```

---

## 🔍 Lógica de Funcionamento

### Passo 1: Identificar Usuários com Múltiplas Matrículas
```sql
SELECT DISTINCT usuario_id
FROM matriculas
WHERE status IN ('ativa', 'pendente', 'vencida')
GROUP BY usuario_id
HAVING COUNT(*) > 1
```

### Passo 2: Agrupar por Modalidade
Para cada usuário, agrupa suas matrículas por modalidade.

**Exemplo: Carolina tem 3 matrículas**
- CrossFit - 2x/semana (Venc: 10/02/2026) ← Vigente
- Natação - 3x/semana (Venc: 08/02/2026) ← Vigente
- Natação - 2x/semana (Venc: 08/02/2026) ← **CANCELADA** (duplicada)

### Passo 3: Manter Apenas a Vigente por Modalidade
Para cada modalidade:
- Se houver uma matrícula com `data_vencimento >= hoje`: **Mantém a mais recente**
- Se todas estiverem vencidas: **Mantém a mais recente mesmo assim**
- As demais: **Canceladas**

### Passo 4: Cancelar Todas com Data Vencida
Além das duplicatas, também cancela qualquer matrícula com `data_vencimento < hoje` que ainda tenha status ativo/pendente.

---

## 📊 Exemplo Prático

### Antes da Limpeza
| ID | Aluno | Plano | Modalidade | Vencimento | Status |
|----|----|----|----|----|----|
| 1 | Carolina | 2x/sem | CrossFit | 10/02 | ativa |
| 2 | Carolina | 3x/sem | Natação | 08/02 | pendente |
| 3 | Carolina | 2x/sem | Natação | 08/02 | pendente |
| 4 | André | 3x/sem | Natação | 06/02 | pendente |

### Depois da Limpeza
| ID | Aluno | Plano | Modalidade | Vencimento | Status |
|----|----|----|----|----|----|
| 1 | Carolina | 2x/sem | CrossFit | 10/02 | ativa |
| 2 | Carolina | 3x/sem | Natação | 08/02 | pendente |
| ~~3~~ | ~~Carolina~~ | ~~2x/sem~~ | ~~Natação~~ | ~~08/02~~ | **cancelada** |
| 4 | André | 3x/sem | Natação | 06/02 | pendente |

✅ **Matrícula 3 foi cancelada por ser duplicada**

---

## 🔒 Segurança

✅ **Lock File**
- Impede múltiplas execuções simultâneas
- Remove locks antigos (>10 min) automaticamente

✅ **Transações**
- Cada tenant em uma transação separada
- Rollback automático em caso de erro

✅ **Dry-Run**
- Flag `--dry-run` permite testar sem fazer alterações
- Ideal para validar antes de aplicar em produção

---

## 📝 Arquivos

| Arquivo | Descrição |
|---------|-----------|
| [jobs/limpar_matriculas_duplicadas.php](jobs/limpar_matriculas_duplicadas.php) | Script principal do job |
| [jobs/atualizar_status_matriculas.php](jobs/atualizar_status_matriculas.php) | Job de atualização de pagamentos vencidos |

---

## 🧪 Testar Antes de Usar

### 1. Teste em Dry-Run
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --dry-run
```

**Output esperado:**
```
========================================
LIMPEZA DE MATRÍCULAS DUPLICADAS
Data/Hora: 2026-01-11 14:30:45
⚠️ MODO DRY-RUN (Nenhuma alteração será feita)
========================================

[Tenant #4] AppCheckin Demo
  Usuários com múltiplas matrículas: 1
    Cancelando: 2x por Semana Natação (Venc: 2026-02-08)
    Mantendo: 3x por semana Natação (Venc: 2026-02-08) ✓

========================================
✅ CONCLUÍDO
Usuários processados: 1
Matrículas canceladas: 1
Tempo: 0.45s
⚠️ Modo DRY-RUN: Nenhuma alteração foi feita
========================================
```

### 2. Teste em um Tenant Específico
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php --tenant=4
```

### 3. Executar para Todos os Tenants
```bash
docker exec appcheckin_php php /var/www/html/jobs/limpar_matriculas_duplicadas.php
```

---

## 📋 Status de Implementação

| Item | Status |
|------|--------|
| Script Criado | ✅ |
| Lógica Implementada | ✅ |
| Dry-Run | ✅ |
| Lock File | ✅ |
| Transações | ✅ |
| Documentação | ✅ |
| Pronto para Uso | ✅ |

---

## 🎯 Próximos Passos

1. **Testar em Dev**: Execute com `--dry-run` para validar
2. **Validar Dados**: Verifique no admin se as matrículas corretas foram canceladas
3. **Configurar Cron**: Adicione ao crontab para execução automática
4. **Monitorar Logs**: Acompanhe `/var/log/limpar_matriculas.log`

---

**Criado em:** 11 de janeiro de 2026
