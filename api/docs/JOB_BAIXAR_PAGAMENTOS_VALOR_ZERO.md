# Job: Baixar Pagamentos com Valor Zero

## 📋 Descrição

Job mensal que baixa automaticamente todos os pagamentos com valor R$ 0,00 que possuem status "Aguardando".

### Contexto

Quando um contrato é criado:
1. Uma primeira fatura é gerada com o **valor da aquisição** (do plano)
2. Quando esse pagamento é baixado, uma **próxima fatura é gerada** para cobrança mensal
3. Alguns pagamentos podem ser criados com **valor 0** (ajustes, bônus, cortesias, etc.)

Como esses pagamentos de R$ 0,00 não precisam de processamento financeiro, este job permite que sejam **baixados automaticamente**.

## ⚙️ Configuração CRON

### Opção 1: Executar no 1º dia do mês às 03:00 AM

```bash
0 3 1 * * /usr/bin/php /caminho/completo/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

### Opção 2: Executar a cada 10 dias

```bash
0 3 */10 * * /usr/bin/php /caminho/completo/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

### Opção 3: Executar semanalmente (toda segunda-feira às 03:00)

```bash
0 3 * * 1 /usr/bin/php /caminho/completo/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

## 🚀 Instalação

### 1. Verificar caminho do PHP

```bash
which php
# Resultado esperado: /usr/bin/php ou /usr/local/bin/php
```

### 2. Editar o arquivo crontab

```bash
crontab -e
```

### 3. Adicionar a linha CRON

```bash
# Opção recomendada (mensal)
0 3 1 * * /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

### 4. Criar diretório de logs (se necessário)

```bash
sudo mkdir -p /var/log/appcheckin
sudo chown www-data:www-data /var/log/appcheckin
sudo chmod 755 /var/log/appcheckin
```

### 5. Salvar e verificar

```bash
# Verificar se foi adicionado
crontab -l

# Verificar se o job está rodando
sudo tail -f /var/log/appcheckin/jobs.log
```

## 🧪 Teste Manual

Para testar o job antes de colocar em produção:

```bash
# Executar manualmente
php /caminho/api/jobs/BaixarPagamentosValorZero.php

# Com redirecionamento de logs
php /caminho/api/jobs/BaixarPagamentosValorZero.php | tee /tmp/job_test.log
```

## 📊 O que o Job faz

1. **Busca** todos os pagamentos com:
   - Valor = R$ 0,00
   - Status = "Aguardando" (não pagos)

2. **Agrupa** por academia para relatório

3. **Processa** a baixa de cada pagamento:
   - Muda status para "Pago"
   - Define data de pagamento como data atual
   - Adiciona observação automática

4. **Registra** logs detalhados:
   - Quantidade de pagamentos processados
   - Detalhes de cada pagamento
   - Erros (se houver)

## 📝 Exemplo de Saída

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

## 🔍 Monitorando Logs

```bash
# Ver últimas linhas
tail -20 /var/log/appcheckin/jobs.log

# Seguir logs em tempo real
tail -f /var/log/appcheckin/jobs.log

# Procurar erros
grep "❌ ERRO" /var/log/appcheckin/jobs.log

# Ver quantas execuções por mês
grep "INICIANDO JOB" /var/log/appcheckin/jobs.log | wc -l
```

## ⚠️ Notas Importantes

1. **Timezone**: O horário do cron é baseado no timezone do servidor
2. **Usuário**: O job executa com o usuário que está configurado no crontab
3. **Permissões**: Certifique-se de que o usuário tem permissão para:
   - Acessar o arquivo PHP
   - Escrever no arquivo de logs
   - Conectar ao banco de dados
4. **Backup**: A operação é atômica (tudo ou nada via transaction)

## 🛠️ Troubleshooting

### Job não está executando

1. Verificar se cron está rodando:
```bash
sudo service cron status
# ou
sudo systemctl status cron
```

2. Verificar se o job está na lista:
```bash
crontab -l
```

3. Verificar logs do cron:
```bash
sudo grep CRON /var/log/syslog | tail -20
```

### Erros de permissão

```bash
# Dar permissão de execução
chmod +x /var/www/api/jobs/BaixarPagamentosValorZero.php

# Verificar dono
ls -la /var/www/api/jobs/BaixarPagamentosValorZero.php
```

### Erro de conexão com banco

1. Verificar arquivo `config/database.php`
2. Testar conexão manualmente:
```bash
php -r "require 'config/database.php'; echo 'OK';"
```

## 📅 Histórico de Execuções

Para manter histórico completo, modifique o cron para adicionar timestamp:

```bash
# Com timestamp no log
0 3 1 * * echo "$(date '+%Y-%m-%d %H:%M:%S') - Iniciando job..." >> /var/log/appcheckin/jobs.log && /usr/bin/php /var/www/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
```

## ✨ Próximos Passos

- [ ] Adicionar notificação por email ao término
- [ ] Criar dashboard para visualizar pagamentos processados
- [ ] Implementar retry automático em caso de erro
- [ ] Adicionar métrica de performance
