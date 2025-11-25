# Sistema de Contas a Receber - Guia Completo

## 📋 Visão Geral

O sistema de **Contas a Receber** gerencia automaticamente as cobranças recorrentes dos alunos baseado em seus planos contratados. Quando um aluno é cadastrado ou muda de plano, o sistema cria automaticamente uma conta a receber com vencimento imediato (matrícula).

## 🗄️ Estrutura do Banco de Dados

### Tabela: `contas_receber`

```sql
CREATE TABLE contas_receber (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    usuario_id INT NOT NULL,
    plano_id INT NOT NULL,
    historico_plano_id INT NULL,
    
    -- Valores financeiros
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    
    -- Status e controle
    status ENUM('pendente', 'pago', 'vencido', 'cancelado') DEFAULT 'pendente',
    forma_pagamento VARCHAR(50) NULL,
    referencia_mes VARCHAR(7) NOT NULL, -- YYYY-MM
    
    -- Recorrência
    recorrente BOOLEAN DEFAULT TRUE,
    intervalo_dias INT NULL,
    proxima_conta_id INT NULL,
    conta_origem_id INT NULL,
    
    -- Observações e auditoria
    observacoes TEXT NULL,
    criado_por INT NULL,
    baixa_por INT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Trigger Automático

Um trigger atualiza automaticamente o status para `vencido` quando a data de vencimento passa:

```sql
CREATE TRIGGER update_conta_vencida
BEFORE UPDATE ON contas_receber
FOR EACH ROW
BEGIN
    IF NEW.data_vencimento < CURDATE() AND NEW.status = 'pendente' THEN
        SET NEW.status = 'vencido';
    END IF;
END;
```

## 🔄 Fluxo Automático de Recorrência

### 1. Cadastro/Mudança de Plano
Quando um aluno é cadastrado ou muda de plano:
- ✅ Sistema cria primeira conta a receber
- ✅ Data de vencimento = hoje (matrícula)
- ✅ Valor = valor do plano
- ✅ Status = pendente
- ✅ Recorrente = true
- ✅ Intervalo_dias = duracao_dias do plano

### 2. Dar Baixa (Pagamento)
Quando o admin dá baixa em uma conta:
- ✅ Atualiza conta atual:
  - status → 'pago'
  - data_pagamento → data informada
  - forma_pagamento → ex: Pix, Cartão, Dinheiro
  - baixa_por → ID do admin

- ✅ Se `recorrente = true`:
  - Cria próxima conta automaticamente
  - data_vencimento → data_vencimento_anterior + intervalo_dias
  - Vincula as contas:
    - conta_atual.proxima_conta_id → nova_conta.id
    - nova_conta.conta_origem_id → conta_atual.id

### 3. Exemplo de Fluxo - Plano Mensal

```
Dia 1 (Nov/2025):
  ├─ Aluno cadastrado com Plano Mensal (30 dias)
  └─ Conta #1 criada: vencimento = 2025-11-01, status = pendente

Dia 5 (Nov/2025):
  ├─ Admin dá baixa na Conta #1
  ├─ Conta #1: status = pago, data_pagamento = 2025-11-05
  └─ Conta #2 criada automaticamente: vencimento = 2025-12-01, status = pendente

Dia 3 (Dez/2025):
  ├─ Admin dá baixa na Conta #2
  ├─ Conta #2: status = pago, data_pagamento = 2025-12-03
  └─ Conta #3 criada automaticamente: vencimento = 2026-01-01, status = pendente

... o ciclo continua automaticamente
```

## 📊 Tipos de Plano e Intervalos

| Plano | Duração | Intervalo | Exemplo de Recorrência |
|-------|---------|-----------|------------------------|
| Mensal | 30 dias | 30 dias | Nov 1 → Dez 1 → Jan 1 |
| Trimestral | 90 dias | 90 dias | Nov 1 → Fev 1 → Mai 1 |
| Semestral | 180 dias | 180 dias | Nov 1 → Mai 1 → Nov 1 |
| Anual | 365 dias | 365 dias | Nov 1/2025 → Nov 1/2026 |

## 🎯 Endpoints da API

### 1. Listar Contas
```http
GET /admin/contas-receber
```

**Query Parameters:**
- `status`: pendente | pago | vencido | cancelado
- `usuario_id`: filtrar por aluno
- `mes_referencia`: YYYY-MM (ex: 2025-11)

**Resposta:**
```json
{
  "contas": [
    {
      "id": 1,
      "usuario_id": 5,
      "aluno_nome": "João Silva",
      "aluno_email": "joao@email.com",
      "plano_nome": "Plano Mensal",
      "valor": 150.00,
      "data_vencimento": "2025-11-24",
      "data_pagamento": null,
      "status": "pendente",
      "recorrente": true,
      "intervalo_dias": 30,
      "referencia_mes": "2025-11"
    }
  ],
  "total": 1
}
```

### 2. Dar Baixa
```http
POST /admin/contas-receber/{id}/baixa
```

**Body:**
```json
{
  "data_pagamento": "2025-11-24",
  "forma_pagamento": "Pix",
  "observacoes": "Pagamento via Pix - Confirmado"
}
```

**Resposta:**
```json
{
  "message": "Baixa realizada com sucesso",
  "conta": { ... },
  "proxima_conta_id": 123,
  "proxima_vencimento": "2025-12-24"
}
```

### 3. Cancelar Conta
```http
POST /admin/contas-receber/{id}/cancelar
```

**Body:**
```json
{
  "observacoes": "Cliente solicitou cancelamento"
}
```

### 4. Estatísticas
```http
GET /admin/contas-receber/estatisticas?mes_referencia=2025-11
```

**Resposta:**
```json
{
  "por_status": [
    { "status": "pendente", "quantidade": 45, "total": 6750.00 },
    { "status": "pago", "quantidade": 120, "total": 18000.00 },
    { "status": "vencido", "quantidade": 8, "total": 1200.00 }
  ],
  "vencidas": {
    "quantidade": 8,
    "total": 1200.00
  },
  "a_vencer_7_dias": {
    "quantidade": 12,
    "total": 1800.00
  },
  "mes_referencia": "2025-11"
}
```

## 💻 Frontend - Componente

### Rota
```
/admin/contas-receber
```

### Funcionalidades
- ✅ Listagem de contas com filtros (status, busca)
- ✅ Resumo financeiro (total pendente, vencido)
- ✅ Dar baixa com modal de confirmação
- ✅ Cancelar conta
- ✅ Visualização detalhada (aluno, plano, valores, datas)
- ✅ Indicadores de recorrência
- ✅ Filtro por mês de referência

### Formulário de Baixa
Ao dar baixa, o admin informa:
- Data do pagamento (padrão: hoje)
- Forma de pagamento (ex: Pix, Cartão, Dinheiro, Boleto)
- Observações opcionais

## 📈 Dashboard - Estatísticas Adicionadas

O dashboard admin agora exibe:
- `contas_pendentes_qtd`: Quantidade de contas pendentes
- `contas_pendentes_valor`: Valor total pendente
- `contas_vencidas_qtd`: Quantidade de contas vencidas
- `contas_vencidas_valor`: Valor total vencido

## 🔗 Integração com Histórico de Planos

Cada conta a receber está vinculada a um registro de `historico_planos`:
- Rastreabilidade completa
- Saber qual mudança de plano gerou cada conta
- Auditoria de valores e datas

```sql
SELECT 
    cr.*,
    hp.motivo,
    hp.plano_anterior_nome,
    hp.plano_novo_nome
FROM contas_receber cr
LEFT JOIN historico_planos hp ON cr.historico_plano_id = hp.id
WHERE cr.usuario_id = ?
```

## 🎨 Status e Cores

| Status | Cor | Descrição |
|--------|-----|-----------|
| `pendente` | warning (amarelo) | Aguardando pagamento |
| `pago` | success (verde) | Pagamento confirmado |
| `vencido` | danger (vermelho) | Vencimento passou |
| `cancelado` | medium (cinza) | Conta cancelada |

## 🔒 Segurança e Validações

### Backend
- ✅ Verificação de tenant_id em todas as queries
- ✅ Validação de status antes de dar baixa
- ✅ Não permite dar baixa em conta já paga
- ✅ Não permite cancelar conta paga
- ✅ Auditoria: registra quem criou e quem deu baixa

### Frontend
- ✅ Confirmação antes de dar baixa
- ✅ Confirmação antes de cancelar
- ✅ Mensagens de sucesso/erro
- ✅ Reload automático após operações

## 🚀 Como Usar

### 1. Cadastrar Aluno com Plano
```typescript
// No modal de aluno
{
  nome: "Maria Santos",
  email: "maria@email.com",
  plano_id: 2, // Plano Trimestral
  // Sistema calcula automaticamente:
  // - data_vencimento_plano = hoje + 90 dias
  // - Cria conta_receber com vencimento = hoje
}
```

### 2. Acessar Contas a Receber
```
Menu Admin → Contas a Receber
```

### 3. Dar Baixa
```
1. Localizar conta pendente
2. Clicar em "Dar Baixa"
3. Confirmar forma de pagamento
4. Sistema cria automaticamente próxima cobrança
```

### 4. Acompanhar Recorrências
Cada conta mostra:
- Se é recorrente
- Intervalo (30, 90, 180, 365 dias)
- Valor e vencimento
- Aluno e plano

## 📝 Observações Importantes

1. **Primeira conta sempre vence hoje**: Representa a matrícula/início do plano
2. **Próximas contas**: Calculadas a partir do vencimento anterior + intervalo
3. **Cancelamento de plano**: Não cancela contas automaticamente - admin deve fazer manualmente
4. **Forma de pagamento**: Campo livre para registrar método usado
5. **Referência mês**: Usado para relatórios e filtros (formato YYYY-MM)

## 🔄 Migration

Execute a migration para criar a tabela:

```bash
# Backend/database/migrations/011_create_contas_receber.sql
```

## 🎯 Próximas Melhorias Sugeridas

- [ ] Relatório mensal de recebimentos
- [ ] Envio de lembretes de vencimento por email
- [ ] Geração de boletos/links de pagamento
- [ ] Histórico de pagamentos por aluno
- [ ] Gráficos de inadimplência
- [ ] Exportação para Excel
- [ ] Integração com gateway de pagamento

---

**Sistema implementado em:** Novembro 2024  
**Versão:** 1.0
