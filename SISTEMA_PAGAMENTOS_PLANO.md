# Sistema de Pagamentos de Planos/Matrículas

## Visão Geral

Sistema de pagamentos para gerenciar as mensalidades dos alunos (Tenant Admin → Aluno), similar ao sistema de pagamentos_contrato (Super Admin → Tenant Admin).

## Funcionalidades

### 1. Criação Automática de Pagamento
- **Ao criar matrícula**: Sistema cria automaticamente o primeiro pagamento
- **Status inicial**: Aguardando (status_pagamento_id = 1)
- **Data vencimento**: Data de início da matrícula
- **Valor**: Valor do plano contratado

### 2. Baixa Manual de Pagamento
- Admin confirma o pagamento recebido
- Informa:
  - Data do pagamento (opcional, padrão = hoje)
  - Forma de pagamento (opcional)
  - Comprovante (opcional)
  - Observações (opcional)
- Sistema registra quem deu a baixa (baixado_por)

### 3. Geração Automática do Próximo Pagamento
- **Após confirmar pagamento**: Sistema gera automaticamente o próximo
- **Cálculo da data**: data_vencimento + duracao_dias do plano
- **Exemplo**: Plano mensal (30 dias)
  - Pagamento 1: vence em 2026-01-07
  - Pagamento 2: gerado automaticamente para 2026-02-06
  - Pagamento 3: gerado automaticamente para 2026-03-08
  - E assim sucessivamente...

### 4. Controle de Status
- **Aguardando (1)**: Pagamento pendente
- **Pago (2)**: Pagamento confirmado
- **Atrasado (3)**: Vencido e não pago
- **Cancelado (4)**: Pagamento cancelado

## Endpoints da API

### Listar Pagamentos de uma Matrícula
```
GET /admin/matriculas/{id}/pagamentos-plano
```
Retorna todos os pagamentos de uma matrícula específica.

### Listar Pagamentos de um Aluno
```
GET /admin/usuarios/{id}/pagamentos-plano
```
Retorna todos os pagamentos de um aluno.

### Listar Todos os Pagamentos
```
GET /admin/pagamentos-plano
```
Filtros opcionais:
- `status_pagamento_id`: Filtrar por status
- `usuario_id`: Filtrar por aluno
- `data_inicio`: Data inicial
- `data_fim`: Data final

### Resumo Financeiro
```
GET /admin/pagamentos-plano/resumo
```
Retorna estatísticas:
- Total de pagamentos
- Quantidade por status
- Valor total recebido
- Valor pendente

### Buscar Pagamento
```
GET /admin/pagamentos-plano/{id}
```
Retorna detalhes de um pagamento específico.

### Criar Pagamento Manual
```
POST /admin/matriculas/{id}/pagamentos-plano
```
Body:
```json
{
  "usuario_id": 123,
  "plano_id": 456,
  "valor": 150.00,
  "data_vencimento": "2026-02-07",
  "observacoes": "Pagamento extra"
}
```

### Confirmar Pagamento (Dar Baixa)
```
POST /admin/pagamentos-plano/{id}/confirmar
```
Body (todos opcionais):
```json
{
  "data_pagamento": "2026-01-07",
  "forma_pagamento_id": 1,
  "comprovante": "comprovante.pdf",
  "observacoes": "Pago em dinheiro"
}
```

**Importante**: Ao confirmar, o sistema gera automaticamente o próximo pagamento!

### Cancelar Pagamento
```
DELETE /admin/pagamentos-plano/{id}
```
Body (opcional):
```json
{
  "observacoes": "Motivo do cancelamento"
}
```

### Marcar Atrasados
```
POST /admin/pagamentos-plano/marcar-atrasados
```
Marca automaticamente como atrasados os pagamentos vencidos.

## Estrutura da Tabela

```sql
CREATE TABLE pagamentos_plano (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    matricula_id INT NOT NULL,
    usuario_id INT NOT NULL COMMENT 'ID do aluno',
    plano_id INT NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    status_pagamento_id INT NOT NULL DEFAULT 1,
    forma_pagamento_id INT NULL,
    comprovante VARCHAR(255) NULL,
    observacoes TEXT NULL,
    criado_por INT NULL COMMENT 'Admin que criou',
    baixado_por INT NULL COMMENT 'Admin que deu baixa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Fluxo Completo de Uso

### 1. Criar Matrícula
```
POST /admin/matriculas
```
- Sistema cria matrícula
- Sistema cria automaticamente primeiro pagamento
- Status: Aguardando

### 2. Aluno Paga a Mensalidade
```
POST /admin/pagamentos-plano/{id}/confirmar
```
- Admin dá baixa no pagamento
- Sistema marca como Pago
- Sistema gera próximo pagamento automaticamente

### 3. Visualizar Pagamentos na Tela de Detalhes
```
GET /admin/matriculas/{id}/pagamentos-plano
```
- Grid com todos os pagamentos (igual ao de contratos)
- Mostra: valor, vencimento, status, forma de pagamento
- Ações: Confirmar, Cancelar

### 4. Relatórios e Controle
```
GET /admin/pagamentos-plano/resumo
GET /admin/pagamentos-plano?status_pagamento_id=1
```
- Resumo financeiro
- Pagamentos pendentes
- Pagamentos atrasados

## Diferenças com Contas a Receber

O sistema já tinha `contas_receber`, mas `pagamentos_plano` oferece:

1. **Melhor controle**: Vinculado diretamente à matrícula
2. **Geração automática**: Cria próximo pagamento ao dar baixa
3. **Histórico completo**: Todos os pagamentos da matrícula em um lugar
4. **Rastreabilidade**: Sabe quem criou e quem deu baixa
5. **Integração**: Interface similar ao pagamentos_contrato

## Componentes Frontend

### Grid de Pagamentos (Reutilizar do Contrato)
- Mostrar lista de pagamentos
- Botão "Confirmar Pagamento"
- Botão "Cancelar"
- Filtros por status

### Modal de Confirmação
- Data do pagamento
- Forma de pagamento
- Comprovante (upload)
- Observações

### Tela de Detalhes da Matrícula
- Informações da matrícula
- Grid de pagamentos (mesmo componente de contratos)
- Resumo: total pago, pendente, atrasado

## Exemplo de Uso Prático

**Cenário**: Aluno João contrata plano mensal de R$ 150,00

1. **07/01/2026**: Admin cria matrícula
   - Sistema cria: Pagamento 1 - Vence 07/01/2026 - R$ 150,00 - Aguardando

2. **07/01/2026**: João paga primeira mensalidade
   - Admin confirma pagamento
   - Sistema marca: Pagamento 1 - Pago
   - Sistema cria: Pagamento 2 - Vence 06/02/2026 - R$ 150,00 - Aguardando

3. **06/02/2026**: João paga segunda mensalidade
   - Admin confirma pagamento
   - Sistema marca: Pagamento 2 - Pago
   - Sistema cria: Pagamento 3 - Vence 08/03/2026 - R$ 150,00 - Aguardando

4. E assim por diante, automaticamente!

## Benefícios

✅ **Automação**: Não precisa criar pagamentos manualmente
✅ **Controle**: Histórico completo de pagamentos
✅ **Rastreabilidade**: Sabe quem fez cada ação
✅ **Relatórios**: Fácil visualizar pendências e recebimentos
✅ **Interface**: Mesma UX do sistema de contratos
✅ **Multi-tenant**: Isolado por tenant
