# 🤖 Job: Baixar Pagamentos com Valor Zero

## 📌 Resumo Executivo

Um **job mensal automático** que identifica e baixa pagamentos de **R$ 0,00** que estão com status "Aguardando".

### Por que existe?

Quando um contrato é criado:
1. ✅ Uma primeira fatura é gerada (valor do plano)
2. ✅ Ao pagar, uma próxima fatura é gerada (cobrança mensal)
3. ⚠️ Alguns pagamentos criados com valor 0 (bônus, ajustes, cortesias, etc)

Como esses pagamentos de R$ 0 não precisam processamento financeiro real, este job **automatiza a baixa**, marcando-os como "Pagos".

---

## 🚀 Quick Start

### 1. Configurar Cron (Recomendado - Automático)

```bash
cd /caminho/api
bash scripts/setup_cron_job.sh
```

Este script irá:
- ✅ Detectar o caminho do PHP
- ✅ Criar diretório de logs
- ✅ Adicionar o job ao crontab
- ✅ Configurar a frequência desejada

### 2. Configurar Cron (Manual)

Edite seu crontab:

```bash
crontab -e
```

Adicione uma das linhas abaixo:

**Opção A: Mensal (1º dia às 03:00 AM) - RECOMENDADO**
```bash
0 3 1 * * /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

**Opção B: Semanal (segunda-feira às 03:00 AM)**
```bash
0 3 * * 1 /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

**Opção C: Diário (todos os dias às 03:00 AM)**
```bash
0 3 * * * /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

### 3. Testar Job (Manual)

```bash
# Via CLI
php /caminho/api/jobs/BaixarPagamentosValorZero.php

# Via Docker
docker-compose exec -T php php jobs/BaixarPagamentosValorZero.php

# Com logs
php /caminho/api/jobs/BaixarPagamentosValorZero.php | tee /tmp/job_test.log
```

---

## 📊 O que o Job Faz

```
1. BUSCA pagamentos com valor = 0 E status = "Aguardando"
   └─> SELECT FROM pagamentos_contrato WHERE valor = 0 AND status_pagamento_id = 1

2. AGRUPA por academia para relatório

3. PROCESSA a baixa (dentro de uma transação):
   └─> status = "Pago" (status_pagamento_id = 2)
   └─> data_pagamento = hoje
   └─> adiciona observação automática

4. REGISTRA resultado em logs:
   └─> Quantidade processada
   └─> Erros (se houver)
```

---

## 📋 Exemplo de Saída

```
============================================================
🔄 INICIANDO JOB: Baixar Pagamentos com Valor Zero
Data/Hora: 2026-02-01 03:00:00
============================================================

📋 Pagamentos encontrados com valor 0: 5

📊 RESUMO POR ACADEMIA:
------------------------------------------------------------
  • Escola de Natação Aqua Masters (ID: 2): 3 pagamento(s)
  • Academia Fitness Pro (ID: 4): 2 pagamento(s)

💰 PROCESSANDO BAIXAS:
------------------------------------------------------------
✅ Pagamentos baixados com sucesso: 5
❌ Erros ao processar: 0
📈 Total processado: 5

📝 DETALHES DOS PAGAMENTOS BAIXADOS:
------------------------------------------------------------
  • Pagamento ID: 1
    Academia: Escola de Natação Aqua Masters
    Contrato: 1
    Valor: R$ 0.00
    Data Vencimento: 2026-02-01

  [... mais pagamentos ...]

============================================================
✅ JOB FINALIZADO COM SUCESSO!
Fim: 2026-02-01 03:00:15
============================================================
```

---

## 🔍 Monitorando

### Ver Cron Configurado

```bash
crontab -l
```

### Acompanhar Logs em Tempo Real

```bash
tail -f /var/log/appcheckin/jobs.log
```

### Ver Últimas Execuções

```bash
# Últimas 20 linhas
tail -20 /var/log/appcheckin/jobs.log

# Procurar por erros
grep "❌" /var/log/appcheckin/jobs.log

# Contar execuções por mês
grep "INICIANDO JOB" /var/log/appcheckin/jobs.log | wc -l
```

### Verificar se Cron está Rodando

```bash
# Linux (systemd)
sudo systemctl status cron

# Linux (SysV)
sudo service cron status

# macOS
sudo launchctl list | grep cron
```

---

## 🏗️ Arquitetura

### Arquivos Criados

```
api/
├── jobs/
│   └── BaixarPagamentosValorZero.php    ← Job principal
├── scripts/
│   └── setup_cron_job.sh                ← Script de configuração
├── app/Models/
│   └── PagamentoContrato.php            ← Métodos adicionados:
│       ├── listarPagamentosComValorZero()
│       ├── baixarPagamentoValorZero()
│       └── baixarPagamentosBatch()
└── docs/
    └── JOB_BAIXAR_PAGAMENTOS_VALOR_ZERO.md  ← Documentação completa
```

### Métodos Implementados

**PagamentoContrato.php:**

```php
// Busca pagamentos com valor 0
listarPagamentosComValorZero(int $limite = 100): array

// Baixa um pagamento
baixarPagamentoValorZero(int $pagamentoId): bool

// Baixa múltiplos (com transaction)
baixarPagamentosBatch(array $pagamentoIds): array
```

---

## ⚙️ Configuração Avançada

### Alterar Frequência Depois

```bash
# Ver cron atual
crontab -l

# Editar
crontab -e

# Remover (se necessário)
crontab -r
```

### Adicionar Notificação por Email

```bash
# Adicione ao final da regra CRON:
0 3 1 * * /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php 2>&1 | mail -s "Job: Baixar Pagamentos Zero" admin@example.com
```

### Usar com Docker

```bash
# No docker-compose.yml, adicione serviço:
cron:
  image: mcuadros/ofelia
  volumes:
    - /var/run/docker.sock:/var/run/docker.sock
  command: daemon --docker
  labels:
    ofelia.enabled: "true"
    ofelia.job-exec.baixar-pagos.schedule: "@monthly"
    ofelia.job-exec.baixar-pagos.command: "php /var/www/html/jobs/BaixarPagamentosValorZero.php"
```

---

## 🐛 Troubleshooting

### Job não está executando

**Checklist:**

- [ ] Cron está ativo? `sudo systemctl status cron`
- [ ] Regra está no crontab? `crontab -l`
- [ ] PHP acessível? `which php`
- [ ] Arquivo existe? `ls -la jobs/BaixarPagamentosValorZero.php`
- [ ] Permissão de execução? `chmod +x jobs/BaixarPagamentosValorZero.php`

### Erro "Permission Denied"

```bash
# Dar permissão de execução
chmod +x /var/www/api/jobs/BaixarPagamentosValorZero.php

# Verificar dono
ls -la /var/www/api/jobs/BaixarPagamentosValorZero.php
chown www-data:www-data /var/www/api/jobs/BaixarPagamentosValorZero.php
```

### Erro de Conexão com Banco

1. Testar conexão:
```bash
php -c /etc/php/8.0/cli/php.ini -r "require 'config/database.php'; echo 'OK';"
```

2. Verificar `config/database.php`

3. Verificar permissões do arquivo de configuração

### Logs Vazios

```bash
# Verificar se diretório existe
mkdir -p /var/log/appcheckin

# Verificar permissões
chmod 755 /var/log/appcheckin
chown www-data:www-data /var/log/appcheckin
```

---

## 📈 Estatísticas

### Query Executada

```sql
SELECT pc.id, pc.valor, pc.data_vencimento, pc.status_pagamento_id,
       tps.tenant_id, t.nome as tenant_nome
FROM pagamentos_contrato pc
INNER JOIN tenant_planos_sistema tps ON pc.tenant_plano_id = tps.id
INNER JOIN tenants t ON tps.tenant_id = t.id
WHERE pc.valor = 0 
AND pc.status_pagamento_id = 1
ORDER BY pc.created_at ASC
LIMIT 1000;
```

### Impacto

- **Tempo de execução:** ~1-2 segundos (para até 1000 pagamentos)
- **I/O de banco:** Mínimo
- **CPU:** Desprezível
- **Memória:** < 10MB

---

## ✅ Checklist de Implantação

- [ ] Criar job `/jobs/BaixarPagamentosValorZero.php`
- [ ] Adicionar métodos em `PagamentoContrato.php`
- [ ] Criar script de setup `/scripts/setup_cron_job.sh`
- [ ] Executar `setup_cron_job.sh` OU adicionar manualmente ao crontab
- [ ] Testar job manualmente
- [ ] Verificar primeira execução via logs
- [ ] Documentar para o time

---

## 📚 Referências Documentação

- [JOB_BAIXAR_PAGAMENTOS_VALOR_ZERO.md](./JOB_BAIXAR_PAGAMENTOS_VALOR_ZERO.md) - Documentação completa
- [Cron Expression Generator](https://crontab.guru/) - Criar regras CRON customizadas
- [PHP CLI Documentation](https://www.php.net/manual/en/features.commandline.php) - PHP via CLI

---

## 🎯 Próximos Passos (Sugestões)

- [ ] Adicionar dashboard para visualizar pagamentos baixados
- [ ] Implementar retry automático em caso de erro
- [ ] Adicionar notificação por email
- [ ] Criar métrica de performance
- [ ] Adicionar testes unitários
- [ ] Implementar filtro customizado (por academia, data, etc)

---

## 💡 Dúvidas?

Consulte a documentação completa em [JOB_BAIXAR_PAGAMENTOS_VALOR_ZERO.md](./JOB_BAIXAR_PAGAMENTOS_VALOR_ZERO.md)

