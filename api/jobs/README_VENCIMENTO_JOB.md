# Job: Atualização Automática de Status de Vencimento

## 📋 Descrição

Job que verifica e atualiza automaticamente o status das matrículas baseado na `proxima_data_vencimento`.

### Lógica de Atualização

1. **Matrículas ATIVAS vencidas** (`proxima_data_vencimento < hoje`):
   - Status: `ativa` (1) → `vencida` (2)
   - Check-in: Bloqueado

2. **Matrículas VENCIDAS com data válida** (`proxima_data_vencimento >= hoje`):
   - Status: `vencida` (2) → `ativa` (1)
   - Check-in: Liberado

## 🚀 Execução Manual

### Via Docker (Desenvolvimento)
```bash
# Execução normal
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php

# Modo simulação (não altera banco)
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php --dry-run

# Modo silencioso (apenas erros)
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php --quiet

# Processar apenas um tenant específico
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php --tenant=2
```

### Via SSH (Produção)
```bash
# Execução normal
php /caminho/api/jobs/atualizar_status_vencimento.php

# Com log
php /caminho/api/jobs/atualizar_status_vencimento.php >> /var/log/vencimento.log 2>&1
```

## ⏰ Configuração do Cron

### Recomendado: Todo dia às 00:05

```cron
# Atualizar status de vencimento diariamente às 00:05
5 0 * * * php /var/www/html/jobs/atualizar_status_vencimento.php >> /var/log/vencimento.log 2>&1
```

### Outras opções:

```cron
# A cada 6 horas
0 */6 * * * php /var/www/html/jobs/atualizar_status_vencimento.php >> /var/log/vencimento.log 2>&1

# A cada hora (se precisar verificação mais frequente)
0 * * * * php /var/www/html/jobs/atualizar_status_vencimento.php --quiet >> /var/log/vencimento.log 2>&1
```

### Via Docker no servidor:

```cron
# Criar arquivo: /etc/cron.d/appcheckin-vencimento
5 0 * * * root docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_vencimento.php >> /var/log/appcheckin/vencimento.log 2>&1
```

## 📊 Exemplo de Saída

```
[2026-02-06 00:05:01] 🚀 Iniciando job de atualização de status de vencimento
[2026-02-06 00:05:01] ✅ Conexão com banco estabelecida

[2026-02-06 00:05:01] 📋 Buscando matrículas ATIVAS vencidas...
[2026-02-06 00:05:01]    Encontradas: 3 matrículas
[2026-02-06 00:05:01]    ✅ Matrícula #26 → VENCIDA (venceu 2026-02-05)
[2026-02-06 00:05:01]    ✅ Matrícula #27 → VENCIDA (venceu 2026-02-04)
[2026-02-06 00:05:01]    ✅ Matrícula #28 → VENCIDA (venceu 2026-02-03)

[2026-02-06 00:05:02] 📋 Buscando matrículas VENCIDAS com data válida para reativar...
[2026-02-06 00:05:02]    Encontradas: 1 matrículas
[2026-02-06 00:05:02]    ✅ Matrícula #29 → ATIVA (válida até 2026-03-10)

======================================================================
📊 RESUMO DA EXECUÇÃO
======================================================================
Data de referência: 2026-02-06

Matrículas ATIVAS que venceram:        3
Matrículas VENCIDAS reativadas:        1
Total processado:                      4
Erros:                                 0
Tempo de execução:                     0.15s
======================================================================

[2026-02-06 00:05:02] ✅ Job finalizado com sucesso!
```

## 🛡️ Segurança

- **Lock File**: Impede execuções simultâneas
- **Timeout**: 5 minutos máximo de execução
- **Dry-run**: Permite testar sem alterar dados
- **Logs**: Registra todas operações e erros

## 🔍 Monitoramento

### Verificar última execução:
```bash
tail -n 50 /var/log/vencimento.log
```

### Verificar se há erros:
```bash
grep "❌" /var/log/vencimento.log
```

### Verificar estatísticas:
```bash
grep "RESUMO DA EXECUÇÃO" -A 10 /var/log/vencimento.log | tail -n 15
```

## 🐛 Troubleshooting

### Job não executa

**Problema**: Lock file travado
```bash
# Remover lock manualmente
rm /tmp/atualizar_status_vencimento.lock
```

**Problema**: Permissões
```bash
# Dar permissão de execução
chmod +x /caminho/api/jobs/atualizar_status_vencimento.php
```

### Testar antes de ativar cron

```bash
# 1. Testar em modo dry-run
php jobs/atualizar_status_vencimento.php --dry-run

# 2. Se ok, executar de verdade
php jobs/atualizar_status_vencimento.php

# 3. Verificar resultado no banco
mysql -e "SELECT id, status_id, proxima_data_vencimento FROM matriculas WHERE proxima_data_vencimento < CURDATE();"
```

## 📝 Integração com Evento MySQL

Este job **complementa** o evento MySQL criado anteriormente:

- **Evento MySQL** (`atualizar_matriculas_vencidas`):
  - Roda automaticamente às 00:01 pelo MySQL
  - Atualiza apenas: ativa → vencida
  - Não reativa matrículas

- **Job PHP** (`atualizar_status_vencimento.php`):
  - Roda via cron às 00:05
  - Atualiza: ativa → vencida **E** vencida → ativa
  - Logs detalhados
  - Permite dry-run e testes

**Recomendação**: Manter ambos ativos para redundância.

## 🔗 Arquivos Relacionados

- Script: `/jobs/atualizar_status_vencimento.php`
- Migration SQL: `/database/migrations/add_trigger_atualizar_status_vencido.sql`
- Documentação Frontend: `/docs/FRONTEND_VENCIMENTOS_MATRICULAS.md`
