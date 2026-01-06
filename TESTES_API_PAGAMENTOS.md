# Testes da API de Pagamentos

## Dados de Teste Criados

**Tenant 4 - Sporte e Saúde - Baixa Grande**
- Contrato ID: 2
- Plano: Enterprise (R$ 250,00/mês)
- Status: Pendente (aguardando primeiro pagamento)
- Período: 05/01/2026 a 04/02/2026

**Pagamentos Criados:**
1. ID 1: R$ 250,00 - Vencimento: 05/01/2026 - Status: Aguardando
2. ID 2: R$ 250,00 - Vencimento: 05/02/2026 - Status: Aguardando
3. ID 3: R$ 250,00 - Vencimento: 05/03/2026 - Status: Aguardando

## Endpoints para Testar

### 1. Listar Todos os Pagamentos
```bash
GET /superadmin/pagamentos
Query Params: ?status_pagamento_id=1&tenant_id=4
```

**Exemplo de resposta:**
```json
{
  "type": "success",
  "data": [
    {
      "id": 1,
      "contrato_id": 2,
      "tenant_nome": "Sporte e Saúde - Baixa Grande",
      "plano_nome": "Enterprise",
      "valor": "250.00",
      "data_vencimento": "2026-01-05",
      "data_pagamento": null,
      "status_nome": "Aguardando",
      "forma_pagamento": "pix"
    }
  ]
}
```

### 2. Resumo de Pagamentos
```bash
GET /superadmin/pagamentos/resumo?tenant_id=4
```

**Exemplo de resposta:**
```json
{
  "type": "success",
  "data": {
    "resumo": [
      {
        "status_nome": "Aguardando",
        "quantidade": 3,
        "valor_total": "750.00"
      }
    ],
    "totais": {
      "total_geral": "750.00",
      "total_pago": "0.00",
      "total_aguardando": "750.00",
      "total_atrasado": "0.00"
    }
  }
}
```

### 3. Pagamentos por Contrato
```bash
GET /superadmin/contratos/2/pagamentos
```

**Exemplo de resposta:**
```json
{
  "type": "success",
  "data": [
    {
      "id": 1,
      "valor": "250.00",
      "data_vencimento": "2026-01-05",
      "data_pagamento": null,
      "status_nome": "Aguardando",
      "forma_pagamento": "pix",
      "observacoes": "Primeiro pagamento do contrato"
    },
    {
      "id": 2,
      "valor": "250.00",
      "data_vencimento": "2026-02-05",
      "data_pagamento": null,
      "status_nome": "Aguardando",
      "forma_pagamento": "pix",
      "observacoes": "Segundo pagamento do contrato"
    },
    {
      "id": 3,
      "valor": "250.00",
      "data_vencimento": "2026-03-05",
      "data_pagamento": null,
      "status_nome": "Aguardando",
      "forma_pagamento": "pix",
      "observacoes": "Terceiro pagamento do contrato"
    }
  ]
}
```

### 4. Confirmar Pagamento (Ativa o Contrato)
```bash
POST /superadmin/pagamentos/1/confirmar
Content-Type: application/json

{
  "data_pagamento": "2026-01-05",
  "observacoes": "Pagamento confirmado via PIX"
}
```

**O que acontece:**
1. Pagamento ID 1 muda para status "Pago" (status_pagamento_id = 2)
2. data_pagamento é preenchida
3. Sistema verifica se há outros pagamentos pendentes/atrasados
4. Se não houver, contrato muda para status "Ativo" (status_id = 1)
5. Academia pode acessar o sistema

**Exemplo de resposta:**
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso"
}
```

### 5. Criar Novo Pagamento
```bash
POST /superadmin/contratos/2/pagamentos
Content-Type: application/json

{
  "valor": 250.00,
  "data_vencimento": "2026-04-05",
  "forma_pagamento": "pix",
  "observacoes": "Quarto pagamento do contrato"
}
```

### 6. Marcar Pagamentos Atrasados (Job Diário)
```bash
POST /superadmin/pagamentos/marcar-atrasados
```

**O que acontece:**
1. Sistema busca todos os pagamentos com status "Aguardando" (status_pagamento_id = 1)
2. Verifica se data_vencimento < CURDATE()
3. Muda status para "Atrasado" (status_pagamento_id = 3)
4. Busca contratos que têm pagamentos atrasados
5. Bloqueia contratos (status_id = 4)

**Exemplo de resposta:**
```json
{
  "type": "success",
  "message": "5 pagamentos marcados como atrasados. 3 contratos bloqueados."
}
```

### 7. Cancelar Pagamento
```bash
DELETE /superadmin/pagamentos/3
Content-Type: application/json

{
  "observacoes": "Pagamento cancelado por alteração de plano"
}
```

## Fluxo de Teste Completo

### Passo 1: Verificar Contrato Pendente
```sql
SELECT * FROM tenant_planos_sistema WHERE id = 2;
-- status_id deve ser 2 (Pendente)
```

### Passo 2: Listar Pagamentos do Contrato
```bash
GET /superadmin/contratos/2/pagamentos
-- Deve retornar 3 pagamentos com status "Aguardando"
```

### Passo 3: Confirmar Primeiro Pagamento
```bash
POST /superadmin/pagamentos/1/confirmar
{
  "data_pagamento": "2026-01-05",
  "observacoes": "PIX confirmado"
}
```

### Passo 4: Verificar Contrato Ativado
```sql
SELECT * FROM tenant_planos_sistema WHERE id = 2;
-- status_id deve ser 1 (Ativo) agora
```

### Passo 5: Simular Atraso (Alterar Data de Vencimento)
```sql
-- Alterar vencimento do pagamento 2 para o passado
UPDATE pagamentos_contrato 
SET data_vencimento = '2025-12-01' 
WHERE id = 2;
```

### Passo 6: Rodar Job de Atrasos
```bash
POST /superadmin/pagamentos/marcar-atrasados
```

### Passo 7: Verificar Contrato Bloqueado
```sql
SELECT * FROM tenant_planos_sistema WHERE id = 2;
-- status_id deve ser 4 (Bloqueado) agora

SELECT * FROM pagamentos_contrato WHERE id = 2;
-- status_pagamento_id deve ser 3 (Atrasado)
```

### Passo 8: Regularizar Pagamento Atrasado
```bash
POST /superadmin/pagamentos/2/confirmar
{
  "data_pagamento": "2026-01-06",
  "observacoes": "Pagamento atrasado regularizado"
}
```

### Passo 9: Verificar Contrato Desbloqueado
```sql
SELECT * FROM tenant_planos_sistema WHERE id = 2;
-- status_id deve voltar para 1 (Ativo)
```

## Verificações no Banco

### Ver todos os status de pagamento
```sql
SELECT * FROM status_pagamento;
```

### Ver todos os status de contrato
```sql
SELECT * FROM status_contrato;
```

### Ver pagamentos com informações completas
```sql
SELECT 
    pc.id,
    t.nome as academia,
    ps.nome as plano,
    pc.valor,
    pc.data_vencimento,
    pc.data_pagamento,
    sp.nome as status_pagamento,
    sc.nome as status_contrato
FROM pagamentos_contrato pc
INNER JOIN tenant_planos_sistema tps ON pc.contrato_id = tps.id
INNER JOIN tenants t ON tps.tenant_id = t.id
INNER JOIN planos_sistema ps ON tps.plano_sistema_id = ps.id
INNER JOIN status_pagamento sp ON pc.status_pagamento_id = sp.id
INNER JOIN status_contrato sc ON tps.status_id = sc.id
WHERE t.id = 4
ORDER BY pc.data_vencimento;
```

## Regras Importantes

1. **Contrato só fica Ativo após primeiro pagamento confirmado**
2. **Contrato é bloqueado automaticamente quando há pagamento atrasado**
3. **Contrato é desbloqueado automaticamente quando todos os atrasos são pagos**
4. **Job marcar-atrasados deve rodar diariamente (via cron)**
5. **Academia bloqueada não pode acessar o sistema**

## Próximos Passos

- [ ] Criar tela frontend para listar pagamentos
- [ ] Criar tela para confirmar pagamentos (com upload de comprovante)
- [ ] Implementar job automático (cron) para marcar atrasos
- [ ] Adicionar notificações por email
- [ ] Criar dashboard financeiro
- [ ] Adicionar relatórios de inadimplência
