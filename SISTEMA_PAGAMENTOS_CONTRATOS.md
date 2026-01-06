# Sistema de Pagamentos de Contratos

## Resumo

Sistema completo para controle de pagamentos dos contratos de planos entre academias e a plataforma.

## Estrutura do Banco de Dados

### Tabela: `status_pagamento`
Armazena os possíveis status de um pagamento:
- **1 - Aguardando**: Pagamento aguardando confirmação
- **2 - Pago**: Pagamento confirmado
- **3 - Atrasado**: Pagamento em atraso
- **4 - Cancelado**: Pagamento cancelado

### Tabela: `status_contrato` (atualizada)
Status adicionais para contratos:
- **4 - Bloqueado**: Contrato bloqueado por falta de pagamento

### Tabela: `pagamentos_contrato`
Registra cada pagamento do contrato:
- `id`: ID do pagamento
- `contrato_id`: FK para tenant_planos_sistema
- `valor`: Valor do pagamento
- `data_vencimento`: Data de vencimento
- `data_pagamento`: Data em que foi pago (NULL se não pago)
- `status_pagamento_id`: FK para status_pagamento
- `forma_pagamento`: cartao, pix, operadora, boleto, dinheiro
- `comprovante`: Caminho do arquivo de comprovante
- `observacoes`: Observações sobre o pagamento

## Fluxo de Funcionamento

### 1. Criação de Contrato
Ao criar um contrato:
- Contrato é criado com `status_id = 2` (Pendente)
- Primeiro pagamento é criado automaticamente com `status_pagamento_id = 1` (Aguardando)
- Academia **não** tem acesso ao sistema até confirmar o pagamento

### 2. Confirmação de Pagamento
Quando um pagamento é confirmado (`POST /superadmin/pagamentos/{id}/confirmar`):
- Pagamento recebe `status_pagamento_id = 2` (Pago)
- `data_pagamento` é preenchida
- Se não houver mais pagamentos pendentes/atrasados, o contrato é ativado (`status_id = 1`)

### 3. Controle de Atrasos
Job diário (`POST /superadmin/pagamentos/marcar-atrasados`):
- Marca pagamentos vencidos como `status_pagamento_id = 3` (Atrasado)
- Bloqueia contratos que têm pagamentos atrasados (`status_id = 4`)

### 4. Bloqueio Automático
Contratos são automaticamente bloqueados quando:
- Possuem pelo menos um pagamento com status "Atrasado"
- Sistema muda `status_id` do contrato para 4 (Bloqueado)

## Endpoints da API

### Listar Pagamentos
```
GET /superadmin/pagamentos
Query params: status_pagamento_id, tenant_id, data_inicio, data_fim
```

### Resumo de Pagamentos
```
GET /superadmin/pagamentos/resumo
Query params: tenant_id, data_inicio, data_fim
Retorna: total por status, valor total, valor atrasado
```

### Pagamentos por Contrato
```
GET /superadmin/contratos/{id}/pagamentos
Retorna: lista de pagamentos do contrato
```

### Criar Pagamento
```
POST /superadmin/contratos/{id}/pagamentos
Body: {
  valor, data_vencimento, forma_pagamento, 
  data_pagamento?, status_pagamento_id?, comprovante?, observacoes?
}
```

### Confirmar Pagamento
```
POST /superadmin/pagamentos/{id}/confirmar
Body: {
  data_pagamento?, comprovante?, observacoes?
}
```

### Cancelar Pagamento
```
DELETE /superadmin/pagamentos/{id}
Body: { observacoes? }
```

### Marcar Atrasados (Job Diário)
```
POST /superadmin/pagamentos/marcar-atrasados
Retorna: quantidade de pagamentos e contratos afetados
```

## Modelos

### PagamentoContrato
Métodos principais:
- `criar(array $dados): int`
- `listarPorContrato(int $contratoId): array`
- `buscarPorId(int $id): ?array`
- `confirmarPagamento(int $id, ...): bool`
- `cancelar(int $id, ...): bool`
- `marcarAtrasados(): int`
- `temPagamentosPendentes(int $contratoId): bool`
- `resumo(array $filtros): array`

### TenantPlano (atualizado)
Novo método:
- `atualizarStatus(int $contratoId, int $statusId): bool`

## Regras de Negócio

1. **Contrato só fica ativo após primeiro pagamento confirmado**
2. **Contratos são bloqueados automaticamente se houver pagamentos atrasados**
3. **Um contrato pode ter múltiplos pagamentos (mensalidades)**
4. **Sistema deve rodar job diário para marcar atrasos e bloquear contratos**
5. **Academia bloqueada não pode acessar sistema até regularizar**

## Teste com Tenant 4

Execute as migrations e seed:
```bash
# Executar migrations
mysql -u root -p appcheckin_db < Backend/database/migrations/031_create_status_pagamento_table.sql
mysql -u root -p appcheckin_db < Backend/database/migrations/032_add_bloqueado_status.sql
mysql -u root -p appcheckin_db < Backend/database/migrations/033_create_pagamentos_contrato_table.sql

# Executar seed de teste
mysql -u root -p appcheckin_db < Backend/database/seeds/seed_pagamentos_tenant_4.sql
```

Dados de teste criados para **Tenant 4 - Sporte e Saúde - Baixa Grande**:
- 1 contrato com status "Pendente"
- 3 pagamentos de R$ 250,00:
  - 1º vencendo hoje (05/01/2026)
  - 2º vencendo em 05/02/2026
  - 3º vencendo em 05/03/2026

## Próximos Passos

1. Criar interface frontend para:
   - Listar pagamentos
   - Confirmar pagamentos
   - Upload de comprovantes
   - Visualizar histórico

2. Implementar job automático (cron) para:
   - Marcar pagamentos atrasados diariamente
   - Bloquear contratos inadimplentes
   - Enviar notificações de vencimento

3. Sistema de notificações:
   - Email antes do vencimento
   - Email ao atrasar pagamento
   - Email ao bloquear contrato

4. Dashboard financeiro:
   - Gráficos de recebimentos
   - Indicadores de inadimplência
   - Projeções de receita
