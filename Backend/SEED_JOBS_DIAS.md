# Geração de Dias - Seed e Job

## 📋 Visão Geral

Este módulo automatiza a criação de dias no sistema, preenchendo a tabela `dias` com datas para todo um ano. O sistema funciona em dois níveis:

1. **Seed** - Para preencher dias pela primeira vez
2. **Job** - Para manter os dias atualizados automaticamente a cada ano

---

## 🌱 Seed: `seed_dias_ano.sql`

### O que faz?
Insere 365 dias (a partir da data de hoje) na tabela `dias`, cobrindo um ano completo.

### Como usar?

#### Opção 1: Script auxiliar (recomendado)
```bash
cd /Users/andrecabral/Projetos/AppCheckin/Backend
chmod +x database/seeds/run_seed_dias.sh
./database/seeds/run_seed_dias.sh
```

#### Opção 2: Direto com MySQL
```bash
mysql -h localhost -u root -p app_checkin < database/seeds/seed_dias_ano.sql
```

#### Opção 3: Em Docker
```bash
docker exec seu_container_mysql mysql -u root -p'senha' app_checkin < database/seeds/seed_dias_ano.sql
```

### Resultado esperado
```
-- Resultado da consulta de verificação
dias_inseridos: 365
```

---

## ⚙️ Job: `gerar_dias_anuais.php`

### O que faz?
Um script PHP que pode ser executado manualmente ou agendado via cron para gerar novos dias automaticamente. Ideal para rodar uma vez por ano ou quando necessário preencher datas futuras.

### Como usar?

#### Opção 1: Gerar dias para o próximo ano
```bash
php jobs/gerar_dias_anuais.php
```

**Output esperado:**
```
🕐 Iniciando geração de dias...
📅 De: 09/01/2026
📅 Até: 09/01/2027
---
✓ Inseridos: 30 dias
✓ Inseridos: 60 dias
...
---
✅ Job concluído com sucesso!
📊 Estatísticas:
   • Dias inseridos: 365
   • Dias duplicados (já existentes): 0
   • Total processado: 365 dias
```

#### Opção 2: Ver status dos dias cadastrados
```bash
php jobs/gerar_dias_anuais.php --status
```

**Output esperado:**
```
📊 STATUS ATUAL DOS DIAS:
════════════════════════════════════════
Total de dias cadastrados: 730
Primeira data: 09/01/2026
Última data: 09/01/2027
Data hoje: 09/01/2026
---
Dias futuros: 366
Dias passados: 0
════════════════════════════════════════
```

#### Opção 3: Gerar dias para um período específico
```bash
php jobs/gerar_dias_anuais.php --periodo=2026-01-01:2026-12-31
```

**Output esperado:**
```
🕐 Gerando dias para período específico...
📅 De: 01/01/2026
📅 Até: 31/12/2026
---
✓ Inseridos: 30 dias
✓ Inseridos: 60 dias
...
---
✅ Período gerado com sucesso!
📊 Dias inseridos: 365
📊 Dias duplicados: 0
```

---

## 🔄 Agendamento com Cron (Linux/Mac)

Para executar automaticamente o job uma vez por ano:

### 1. Editar crontab
```bash
crontab -e
```

### 2. Adicionar uma das seguintes linhas:

**Executar 01/01 de cada ano às 00:00**
```cron
0 0 1 1 * php /caminho/para/jobs/gerar_dias_anuais.php >> /var/log/gerar_dias.log 2>&1
```

**Executar todo 01 de janeiro, a cada hora**
```cron
0 * 1 1 * php /caminho/para/jobs/gerar_dias_anuais.php >> /var/log/gerar_dias.log 2>&1
```

**Executar mensalmente (no começo de cada mês)**
```cron
0 2 1 * * php /caminho/para/jobs/gerar_dias_anuais.php --status >> /var/log/dias_status.log 2>&1
```

### 3. Verificar agendamentos
```bash
crontab -l
```

---

## 📊 Verificação Manual

### Ver quantos dias estão cadastrados
```bash
php jobs/gerar_dias_anuais.php --status
```

### Consultar banco diretamente
```sql
SELECT 
    COUNT(*) as total,
    MIN(data) as primeira,
    MAX(data) as ultima,
    COUNT(CASE WHEN data >= CURDATE() THEN 1 END) as futuros
FROM dias;
```

---

## 🎯 Fluxo Recomendado

### Primeira configuração (Setup Inicial)
1. Executar o seed para preencher o primeiro ano:
   ```bash
   ./database/seeds/run_seed_dias.sh
   ```

2. Verificar status:
   ```bash
   php jobs/gerar_dias_anuais.php --status
   ```

### Manutenção Contínua

**A. Execução Manual (Quando necessário)**
```bash
php jobs/gerar_dias_anuais.php
```

**B. Agendamento Automático (Recomendado)**
- Agendar via cron para executar uma vez por ano
- Receber notificações se houver erro

**C. Monitoramento Periódico**
- Executar `--status` a cada mês para garantir que há dias suficientes

---

## ⚠️ Observações Importantes

### Segurança
- ✅ Usa `INSERT IGNORE` / tratamento de duplicatas
- ✅ Usa prepared statements
- ✅ Sem SQL injection

### Performance
- ✅ Eficiente mesmo com 365+ inserções
- ✅ Índices otimizados na tabela `dias`
- ✅ Evita duplicatas automaticamente

### Dados
- ✅ Soft delete preserva histórico
- ✅ Datas passadas não são removidas
- ✅ Suporta múltiplos anos de dados

---

## 🐛 Troubleshooting

### Problema: "Command not found" ao executar shell script
**Solução:**
```bash
chmod +x database/seeds/run_seed_dias.sh
```

### Problema: "Access denied for user"
**Solução:** Verificar credenciais do MySQL
```bash
mysql -h localhost -u root -p app_checkin
```

### Problema: "Duplicate entry"
**Não é um problema!** O script ignora automaticamente datas que já existem. Continue executando normalmente.

### Problema: Job não executa via cron
**Solução:** Verificar:
1. Caminho absoluto do PHP: `which php`
2. Caminhos absolutos no cron
3. Log de erros: `cat /var/log/gerar_dias.log`

---

## 📝 Exemplos de Uso Completo

### Cenário 1: Setup inicial (primeira vez)
```bash
# 1. Preencher dias do ano
./database/seeds/run_seed_dias.sh

# 2. Verificar se funcionou
php jobs/gerar_dias_anuais.php --status

# 3. Agendar para próximos anos
crontab -e  # Adicionar cron job
```

### Cenário 2: Gerar dias retroativamente
```bash
# Gerar dias de 2025 inteiros
php jobs/gerar_dias_anuais.php --periodo=2025-01-01:2025-12-31

# Gerar apenas janeiro de 2026
php jobs/gerar_dias_anuais.php --periodo=2026-01-01:2026-01-31
```

### Cenário 3: Manutenção contínua
```bash
# Verificar status mensalmente
php jobs/gerar_dias_anuais.php --status

# Se necessário, gerar próximo ano
php jobs/gerar_dias_anuais.php

# Ou agendar automaticamente via cron
crontab -e
```

---

## 📊 Índices e Performance

A tabela `dias` possui índices otimizados:
```sql
-- Índice na data para buscas rápidas
CREATE INDEX idx_dias_data ON dias(data);

-- Índice na coluna ativo para filtragens
CREATE INDEX idx_dias_ativo ON dias(ativo);
```

Isso garante que consultas como `SELECT * FROM dias WHERE data >= CURDATE()` sejam muito rápidas mesmo com milhares de dias.

---

**Data de criação:** 9 de janeiro de 2026  
**Última atualização:** 9 de janeiro de 2026  
**Status:** ✅ Pronto para produção
