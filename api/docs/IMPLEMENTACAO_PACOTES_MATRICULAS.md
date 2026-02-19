# Implementação: Matrículas e Pagamentos Rateados para Pacotes

## 📋 Resumo das Mudanças

Implementada a funcionalidade completa de criação de matrículas e pagamentos rateados quando um pacote é ativado via webhook de pagamento Mercado Pago.

## 📊 Fluxo Implementado

### 1. **Compra do Pacote (pagarPacote)**
- Usuário (pagante) inicia a compra de um pacote
- Sistema cria uma assinatura com external_reference no formato `PAC-{pacote_contrato_id}-{timestamp}`
- Assinatura é armazenada no banco com `pacote_contrato_id` associado

### 2. **Aprovação do Pagamento (Webhook)**
- Webhook recebe notificação de pagamento aprovado
- Detecta que é um pacote (external_reference começa com "PAC-")
- Chama `ativarPacoteContrato()` para processar

### 3. **Ativação do Pacote (ativarPacoteContrato)**
Nova implementação cria:

#### a) **Matrículas Rateadas**
- Busca todos os beneficiários do pacote
- Calcula valor rateado: `valor_total / (beneficiários + pagante)`
- Cria matrícula para o **pagante**
- Cria matrícula para cada **beneficiário** do pacote
- Associa cada matrícula ao `pacote_contrato_id` para rastreamento

#### b) **Pagamentos Rateados**
- Para cada matrícula criada, cria um registro em `pagamentos_plano`
- Pagamento já é registrado como "PAGO" (status 2)
- Inclui referência ao `pacote_contrato_id` para auditoria
- Forma de pagamento: Cartão (ID 9)
- Tipo de baixa: Automático (ID 2)

#### c) **Tabelas Atualizadas**
```
matriculas:
  - pacote_contrato_id (INT NULL) - Vincula à compra do pacote
  - valor_rateado (DECIMAL) - Valor pago de forma rateada
  - status_id (INT) - Ativo após webhook aprovado

pagamentos_plano:
  - pacote_contrato_id (INT NULL) - Rastreamento do pacote
  - status_pagamento_id = 2 (PAGO)
  - forma_pagamento_id = 9 (Cartão)
  - tipo_baixa_id = 2 (Automático)

pacote_beneficiarios:
  - matricula_id (INT NULL) - Vinculação com matrícula criada
  - status = 'ativo' - Após webhook aprovado
  - valor_rateado (DECIMAL) - Valor efetivamente pago
```

### 4. **Atualização via Webhook de Assinatura**
Nova funcionalidade: quando webhook de assinatura recorrente chega com `pacote_contrato_id`:

- Chama `atualizarMatriculasDoPackge()` em vez do fluxo normal
- **Ativa todas as matrículas** do pacote (se ainda não estiverem)
- **Marca todos os pagamentos como PAGO**
- Mantém log detalhado de cada operação

## 🔄 Fluxo Detalhado de Métodos

### Método: `ativarPacoteContrato()`
**Localização**: `MercadoPagoWebhookController.php` (linhas 1004-1197)

**Responsabilidades**:
1. Buscar dados do contrato e pacote
2. Buscar informações do pagante
3. Buscar beneficiários pendentes
4. Calcular valor rateado
5. Criar matrícula do pagante
6. Criar matrícula de cada beneficiário
7. Associar pagamentos rateados
8. Atualizar status do contrato para 'ativo'

**Query Principal**:
```php
$stmt->execute([
    $tenantId,
    $pagante['aluno_id'],
    $plano['id'],
    $statusAtivo,
    $dataInicio,
    $dataVencimento,
    $valorRateado,
    $valorRateado,
    $contratoId
]);
```

### Método: `criarPagamentoPacote()`
**Localização**: `MercadoPagoWebhookController.php` (linhas 1199-1231)

**Responsabilidades**:
1. Criar registro em `pagamentos_plano`
2. Marcar como PAGO automaticamente
3. Associar ao `pacote_contrato_id`
4. Registrar observações de auditoria

### Método: `atualizarMatriculasDoPackge()`
**Localização**: `MercadoPagoWebhookController.php` (linhas 1233-1318)

**Responsabilidades**:
1. Buscar todas as matrículas do pacote
2. Ativar cada matrícula
3. Buscar pagamentos pendentes
4. Marcar pagamentos como PAGO
5. Registrar log detalhado

## 📝 Logs Esperados

```
[Webhook MP] 🎯 Ativando contrato #5 e criando matrículas
[Webhook MP] 📦 Pacote encontrado: ID=2, plano_id=1
[Webhook MP] 👤 Pagante encontrado: usuario_id=10, tenant_id=1
[Webhook MP] 👥 Beneficiários encontrados: 2
[Webhook MP] 💰 Valor rateado: 66.67 por pessoa (total: 3 pessoas)
[Webhook MP] 📝 Criando matrícula para pagante (usuario_id=10)
[Webhook MP] ✅ Matrícula do pagante criada: ID=101
[Webhook MP] 💳 Criando pagamento para matrícula #101
[Webhook MP] ✅ Pagamento criado: ID=501 (valor=66.67)
[Webhook MP] ✅ Contrato #5 ativado com sucesso - matrículas criadas
```

## ✅ Checklist de Validação

- [x] Coluna `pacote_contrato_id` adicionada em `matriculas`
- [x] Coluna `valor_rateado` adicionada em `matriculas`
- [x] Coluna `pacote_contrato_id` adicionada em `pagamentos_plano`
- [x] Método `ativarPacoteContrato()` cria todas as matrículas
- [x] Método `ativarPacoteContrato()` cria todos os pagamentos
- [x] Método `atualizarMatriculasDoPackge()` atualiza matrículas na assinatura
- [x] Webhook detecta e roteia pacotes corretamente
- [x] Logs são registrados para auditoria
- [x] Associações entre tabelas mantidas (beneficiários, contratos, etc)

## 🔍 Casos de Teste Recomendados

### Teste 1: Criar Pacote com 3 Pessoas
1. Pagante compra pacote para 2 beneficiários
2. Valor total: R$ 200
3. Esperado: 3 matrículas de R$ 66,67 cada
4. Verificar: `SELECT * FROM matriculas WHERE pacote_contrato_id = 1`
5. Verificar: `SELECT * FROM pagamentos_plano WHERE pacote_contrato_id = 1`

### Teste 2: Webhook de Assinatura Recorrente
1. Criar pacote com assinatura recorrente
2. Simular webhook de assinatura com status 'approved'
3. Verificar: todas as matrículas são ativadas
4. Verificar: todos os pagamentos são marcados como PAGO

### Teste 3: Beneficiário sem Usuário/Aluno
1. Criar pacote com beneficiário sem aluno_id
2. Webhook deve logar ⚠️ e pular o beneficiário
3. Matrículas dos outros devem ser criadas normalmente

## 🚀 Deployment
- Commit: `c9e0c8d` - feat: criar matrículas e pagamentos rateados para pacotes no webhook
- Branch: `main`
- Arquivo modificado: `app/Controllers/MercadoPagoWebhookController.php`
- Linhas adicionadas: ~313

## 📌 Próximos Passos

- [ ] Testar fluxo completo de compra de pacote
- [ ] Validar cálculo de valor rateado
- [ ] Verificar atualização via webhook recorrente
- [ ] Monitorar logs de produção
- [ ] Adicionar testes unitários
