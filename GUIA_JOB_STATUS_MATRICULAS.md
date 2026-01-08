# 📋 Guia do Job de Atualização de Status de Matrículas

## Visão Geral

O job `atualizar_status_matriculas.php` é responsável por atualizar automaticamente o status das matrículas baseado nos pagamentos vencidos.

### Lógica de Status

| Status | Dias de Atraso | Descrição |
|--------|----------------|-----------|
| ✅ **Ativa** | 0 dias | Pagamento em dia |
| ⚠️ **Vencida** | 1-4 dias | Aguardando regularização |
| ❌ **Cancelada** | 5+ dias | Inadimplência - acesso bloqueado |

---

## 🚀 Como Executar

### Execução Manual (Teste)

```bash
# Execução padrão
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php
```

### Com Parâmetros

```bash
# Limitar quantidade de tenants processados
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --limit=10

# Aumentar pausa entre tenants (em milissegundos)
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --sleep=500

# Modo silencioso (apenas erros)
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --quiet

# Combinando parâmetros
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --limit=20 --sleep=200 --quiet
```

---

## ⏰ Configurar Cron (Execução Automática)

### No macOS

1. Abra o terminal e edite o crontab:
```bash
crontab -e
```

2. Adicione uma das linhas abaixo:

```bash
# Opção 1: Executar diariamente às 6h da manhã
0 6 * * * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --quiet >> /var/log/status_matriculas.log 2>&1

# Opção 2: Executar a cada 6 horas
0 */6 * * * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --quiet >> /var/log/status_matriculas.log 2>&1

# Opção 3: Executar a cada hora (para muitos tenants)
0 * * * * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --limit=100 --quiet >> /var/log/status_matriculas.log 2>&1
```

3. Salve e saia (`:wq` no vim ou `Ctrl+X` no nano)

4. Verifique se foi salvo:
```bash
crontab -l
```

### No Linux (Servidor)

```bash
# Editar crontab do root
sudo crontab -e

# Adicionar a linha
0 6 * * * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --quiet >> /var/log/status_matriculas.log 2>&1
```

### Verificar Logs

```bash
# Ver últimas 50 linhas do log
tail -50 /var/log/status_matriculas.log

# Acompanhar em tempo real
tail -f /var/log/status_matriculas.log
```

---

## ⚙️ Parâmetros Disponíveis

| Parâmetro | Padrão | Descrição |
|-----------|--------|-----------|
| `--limit=N` | 50 | Número máximo de tenants por execução |
| `--sleep=N` | 100 | Pausa em milissegundos entre tenants |
| `--quiet` | false | Modo silencioso (só mostra erros) |

---

## 🔒 Proteções de Segurança

O job possui várias proteções para não afetar o backend:

### 1. Lock File
- Impede execuções simultâneas
- Localização: `/tmp/atualizar_status_matriculas.lock`
- Auto-remove após 10 minutos (se travado)

### 2. Limite por Query
- Máx. 1000 pagamentos por tenant
- Máx. 500 matrículas por operação

### 3. Timeout
- Tempo máximo: 5 minutos
- Para automaticamente se exceder

### 4. Transações Isoladas
- Cada tenant é processado em transação separada
- Erro em um tenant não afeta os outros

---

## 📊 O Que o Job Faz

Para cada tenant ativo:

1. **Marca pagamentos como Atrasados**
   - Pagamentos pendentes com vencimento passado → Status 3 (Atrasado)

2. **Atualiza matrículas para Vencida**
   - Matrículas ativas com 1-4 dias de atraso → Status "vencida"

3. **Atualiza matrículas para Cancelada**
   - Matrículas com 5+ dias de atraso → Status "cancelada"

4. **Reativa matrículas regularizadas**
   - Matrículas vencidas sem pagamentos pendentes → Status "ativa"

---

## 🔧 Troubleshooting

### Erro: "Já existe uma execução em andamento"

O job detectou que outra instância está rodando. Aguarde ou remova o lock manualmente:

```bash
docker exec appcheckin_php rm -f /tmp/atualizar_status_matriculas.lock
```

### Erro: "Connection timed out"

O banco de dados está sobrecarregado. Aumente o sleep:

```bash
docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --sleep=500 --limit=20
```

### Job demora muito

Com muitos tenants, configure execuções mais frequentes com menos tenants:

```bash
# Cron a cada 30 minutos, processando 30 tenants por vez
*/30 * * * * docker exec appcheckin_php php /var/www/html/jobs/atualizar_status_matriculas.php --limit=30 --quiet
```

### Verificar se o Docker está rodando

```bash
docker ps | grep appcheckin
```

### Testar conexão com o banco

```bash
docker exec appcheckin_php php -r "require '/var/www/html/config/database.php';"
```

---

## 📈 Recomendações por Volume

| Tenants | Frequência | Parâmetros |
|---------|------------|------------|
| < 50 | 1x ao dia | `--limit=50` |
| 50-200 | 2x ao dia | `--limit=50 --sleep=150` |
| 200-500 | 4x ao dia | `--limit=100 --sleep=200` |
| > 500 | A cada hora | `--limit=100 --sleep=300` |

---

## 📁 Arquivos Relacionados

| Arquivo | Descrição |
|---------|-----------|
| `/Backend/jobs/atualizar_status_matriculas.php` | Script principal do job |
| `/Backend/config/database.php` | Configuração do banco de dados |
| `/var/log/status_matriculas.log` | Log de execução (se configurado) |
| `/tmp/atualizar_status_matriculas.lock` | Arquivo de lock |

---

## 🗄️ Tabelas Afetadas

- `matriculas` - Campo `status` e `status_id`
- `pagamentos_plano` - Campo `status_pagamento_id`
- `status_matricula` - Tabela de referência (somente leitura)

---

## 📞 Suporte

Em caso de problemas:

1. Verifique os logs: `tail -100 /var/log/status_matriculas.log`
2. Teste manualmente sem `--quiet` para ver detalhes
3. Verifique se o container está rodando: `docker ps`
4. Verifique conexão com o banco de dados

---

*Última atualização: Janeiro/2026*
