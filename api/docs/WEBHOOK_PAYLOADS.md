# 📦 Armazenamento de Webhooks do Mercado Pago

## Visão Geral

Foi criada uma nova tabela `webhook_payloads_mercadopago` para armazenar todos os payloads completos recebidos do Mercado Pago. Isso facilita:

- ✅ **Auditoria**: Verificar exatamente o que foi recebido
- 🐛 **Debug**: Investigar problemas sem esperar por novos webhooks
- 📊 **Análise**: Estudar padrões e tendências
- 🔍 **Rastreabilidade**: Ver o histórico completo de cada notificação

## Criando a Tabela

### Opção 1: Via Script PHP
```bash
cd /seu/projeto
php database/create_webhook_payloads_table.php
```

### Opção 2: Via SQL Direto
```bash
mysql -u seu_usuario -p seu_database < database/setup_webhook_payloads.sql
```

### Opção 3: Via Migration Laravel
```bash
php artisan migrate --path=database/migrations
```

## Estrutura da Tabela

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `id` | BIGINT | ID único |
| `tenant_id` | INT | ID do tenant |
| `tipo` | VARCHAR(50) | Tipo de notificação (`payment`, `subscription_preapproval`, etc) |
| `data_id` | BIGINT | ID do objeto (payment_id ou preapproval_id) |
| `external_reference` | VARCHAR(255) | Referência externa (MAT-xxx ou PAC-xxx) |
| `payment_id` | BIGINT | ID do pagamento (se aplicável) |
| `preapproval_id` | VARCHAR(255) | ID da assinatura (se aplicável) |
| `status` | VARCHAR(50) | Status (`sucesso` ou `erro`) |
| `erro_processamento` | VARCHAR(500) | Mensagem de erro (se houver) |
| `payload` | LONGTEXT | Payload completo em JSON |
| `resultado_processamento` | LONGTEXT | Resultado do processamento em JSON |
| `created_at` | TIMESTAMP | Data de criação |
| `updated_at` | TIMESTAMP | Data de atualização |

### Índices

- `idx_tenant_id`: Buscar por tenant
- `idx_tipo`: Filtrar por tipo de notificação
- `idx_data_id`: Buscar por ID do objeto
- `idx_external_reference`: Buscar por referência externa
- `idx_payment_id`: Buscar pagamentos
- `idx_preapproval_id`: Buscar assinaturas
- `idx_status`: Filtrar por sucesso/erro
- `idx_created_at`: Ordenar por data

## Scripts de Consulta

### 1. Listar Webhooks Recentes

```bash
# Últimos 20 webhooks
php database/view_webhook_payloads.php

# Últimos 100 webhooks
php database/view_webhook_payloads.php 100

# Apenas com erro
php database/view_webhook_payloads.php erro

# Apenas com sucesso
php database/view_webhook_payloads.php sucesso
```

### 2. Ver Detalhes Completos

```bash
# Ver webhook específico
php database/show_webhook_payload.php 1

# Ver último webhook
php database/show_webhook_payload.php last

# Ver último webhook com erro
php database/show_webhook_payload.php last erro

# Ver último webhook com sucesso
php database/show_webhook_payload.php last sucesso
```

## Exemplos de Uso

### Verificar se houve erro num webhook
```bash
$ php database/view_webhook_payloads.php erro
```

Saída:
```
📋 WEBHOOKS DO MERCADO PAGO
============================================================...
❌ ID: 5 | 💳 payment | 2026-02-18 10:30:45
   Data ID: 1234567890 | Status: erro
   ❌ Erro: Matrícula não identificada no pagamento
...
```

### Investigar um webhook específico
```bash
$ php database/show_webhook_payload.php 5
```

Saída:
```
========================================================...
📋 DETALHES DO WEBHOOK ID: 5
========================================================...
❌ Status: erro
⏰ Data: 2026-02-18 10:30:45
📝 Tipo: payment
🔢 Data ID: 1234567890

📦 PAYLOAD RECEBIDO:
{
  "type": "payment",
  "data": {
    "id": 1234567890,
    "status": "approved",
    "external_reference": "MAT-123-1771421288",
    ...
  }
}

❌ ERRO:
Matrícula não identificada no pagamento
```

## Salvamento Automático

O webhook agora salva **automaticamente** cada notificação recebida:

✅ Quando a notificação é processada com sucesso:
- `status = 'sucesso'`
- `erro_processamento = NULL`
- `resultado_processamento` = resultado da ação

❌ Quando ocorre erro:
- `status = 'erro'`
- `erro_processamento = mensagem de erro`
- `resultado_processamento = NULL`

## Queries Úteis

### Contar webhooks por tipo
```sql
SELECT tipo, COUNT(*) as total 
FROM webhook_payloads_mercadopago 
GROUP BY tipo;
```

### Webhooks com erro
```sql
SELECT id, created_at, tipo, data_id, erro_processamento
FROM webhook_payloads_mercadopago 
WHERE status = 'erro'
ORDER BY created_at DESC
LIMIT 50;
```

### Webhooks de um payment_id específico
```sql
SELECT * FROM webhook_payloads_mercadopago 
WHERE payment_id = 123456789 
ORDER BY created_at DESC;
```

### Webhooks de um pacote específico
```sql
SELECT * FROM webhook_payloads_mercadopago 
WHERE external_reference LIKE 'PAC-2-%'
ORDER BY created_at DESC;
```

### Taxa de sucesso
```sql
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) as sucessos,
    ROUND(100.0 * SUM(CASE WHEN status = 'sucesso' THEN 1 ELSE 0 END) / COUNT(*), 2) as taxa_sucesso
FROM webhook_payloads_mercadopago;
```

## Limpeza de Dados

### Manter apenas os últimos 90 dias
```sql
DELETE FROM webhook_payloads_mercadopago 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Manter apenas últimos 1000 registros
```sql
DELETE FROM webhook_payloads_mercadopago 
WHERE id < (SELECT id FROM webhook_payloads_mercadopago ORDER BY id DESC LIMIT 1000, 1);
```

## Monitoramento

### Verificar em tempo real (a cada 5 segundos)
```bash
watch -n 5 'php database/view_webhook_payloads.php 5'
```

### Ver estatísticas
```bash
php database/view_webhook_payloads.php 1
# Mostrará ao final:
# 📊 ESTATÍSTICAS:
#    Total de webhooks: 150
#    ✅ Processados com sucesso: 145
#    ❌ Com erro: 5
#    Tipos de notificação: 2
```

## Troubleshooting

### Tabela não existe
```bash
php database/create_webhook_payloads_table.php
```

### Payload muito grande
A coluna `payload` é `LONGTEXT` que suporta até 4GB. Se precisar otimizar:
```sql
ALTER TABLE webhook_payloads_mercadopago 
ADD COLUMN payload_hash VARCHAR(64) GENERATED ALWAYS AS (SHA2(payload, 256)) STORED;
```

### Performance com muitos registros
Considerando o crescimento, você pode particionar a tabela por data:
```sql
ALTER TABLE webhook_payloads_mercadopago 
PARTITION BY RANGE (YEAR_MONTH(created_at)) (
    PARTITION p202401 VALUES LESS THAN (202402),
    PARTITION p202402 VALUES LESS THAN (202403),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

## Segurança

⚠️ **Nota importante**: A tabela armazena payloads completos que podem conter dados sensíveis. Recomenda-se:

1. Limitar acesso ao banco de dados
2. Ter política de retenção (não manter forever)
3. Criptografar dados sensíveis se necessário
4. Fazer backup regular

## Próximas Melhorias

- [ ] Dashboard web para visualizar webhooks
- [ ] Busca avançada por external_reference
- [ ] Replay de webhooks falhos
- [ ] Alertas automáticos para erros frequentes
- [ ] Exportação de relatórios
