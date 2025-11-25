# Melhorias no Sistema de Matrículas e Contas a Receber

**Data:** 24 de novembro de 2025

## Resumo das Alterações

### 1. Tabelas Auxiliares Criadas

#### Formas de Pagamento (`formas_pagamento`)
- **Campos:**
  - `id` (INT, PK)
  - `nome` (VARCHAR 50) - Ex: Dinheiro, Pix, Débito, Crédito
  - `percentual_desconto` (DECIMAL 5,2) - % que fica com a operadora
  - `ativo` (BOOLEAN)
  - `created_at`, `updated_at` (TIMESTAMP)

- **Registros Padrão:**
  - Dinheiro (0% desconto)
  - Pix (0% desconto)
  - Débito (2.5% desconto)
  - Crédito à vista (3.5% desconto)
  - Crédito parcelado 2x (4.5% desconto)
  - Crédito parcelado 3x (5.0% desconto)
  - Transferência bancária (0% desconto)
  - Boleto (2.0% desconto)

#### Status de Contas (`status_conta`)
- **Campos:**
  - `id` (INT, PK)
  - `nome` (VARCHAR 20) - pendente, pago, vencido, cancelado
  - `cor` (VARCHAR 20) - warning, success, danger, medium

### 2. Atualização da Tabela `contas_receber`

**Novos Campos:**
- `forma_pagamento_id` (INT, FK) - Referência para formas_pagamento
- `valor_liquido` (DECIMAL 10,2) - Valor após desconto da operadora
- `valor_desconto` (DECIMAL 10,2) - Valor do desconto calculado

**Campo Removido:**
- `forma_pagamento` (VARCHAR) - Substituído por `forma_pagamento_id`

### 3. Plano FREE

Criado plano gratuito com as seguintes características:
- **Nome:** FREE
- **Descrição:** Plano gratuito - acesso limitado
- **Valor:** R$ 0,00
- **Duração:** 30 dias
- **Check-ins mensais:** 4 (limitado)

### 4. Validação de Alteração de Plano

**Regra Implementada:**
- Não é permitido alterar o plano de um aluno que estiver **ativo** e **dentro do período de validade**
- A validação verifica se existe pagamento ativo no período atual
- Mensagem de erro clara quando a tentativa é bloqueada

**Lógica SQL:**
```sql
SELECT COUNT(*) FROM contas_receber 
WHERE usuario_id = ? 
  AND tenant_id = ?
  AND status = 'pago'
  AND data_vencimento <= CURDATE()
  AND DATE_ADD(data_vencimento, INTERVAL intervalo_dias DAY) >= CURDATE()
```

### 5. Cálculo Automático de Desconto

**Backend (`MatriculaController` e `ContasReceberController`):**
- Ao dar baixa em uma conta, o sistema busca a forma de pagamento
- Calcula automaticamente o desconto baseado no percentual da operadora
- Armazena `valor_liquido` (valor que a academia recebe)
- Armazena `valor_desconto` (valor que fica com a operadora)

**Exemplo:**
- Valor da mensalidade: R$ 100,00
- Forma de pagamento: Crédito à vista (3.5%)
- Desconto: R$ 3,50
- Valor líquido: R$ 96,50

### 6. Frontend - Novas Interfaces e Services

#### Models (`api.models.ts`)
```typescript
export interface FormaPagamento {
  id: number;
  nome: string;
  percentual_desconto: string;  // DECIMAL vem como string
}

export interface StatusConta {
  id: number;
  nome: string;
  cor: string;
}

export interface ContaReceber {
  // ... campos existentes
  forma_pagamento_id: number | null;
  valor_liquido: string | null;
  valor_desconto: string | null;
}

export interface BaixaContaRequest {
  data_pagamento?: string;
  forma_pagamento_id?: number;
  observacoes?: string;
}
```

#### Service (`config.service.ts`)
```typescript
@Injectable({ providedIn: 'root' })
export class ConfigService {
  listarFormasPagamento(): Observable<FormaPagamento[]>
  listarStatusConta(): Observable<StatusConta[]>
}
```

### 7. Backend - Novos Endpoints

#### ConfigController
- **GET** `/config/formas-pagamento` - Lista formas de pagamento ativas
- **GET** `/config/status-conta` - Lista status de contas disponíveis

### 8. Fluxo de Matrícula com Baixa Imediata

**Componente `gerenciar-alunos.component.ts`:**
1. Ao criar matrícula, pergunta se deseja dar baixa imediatamente
2. Se confirmar, usa forma de pagamento padrão (Pix)
3. Data do pagamento é sempre a data atual (não editável)
4. Confirmação obrigatória antes de processar o pagamento

**Código:**
```typescript
darBaixaImediata(contaId: number): void {
  // Carrega formas de pagamento se necessário
  // Confirma com o usuário
  // Usa data de hoje (não alterável)
  // Usa Pix como padrão (sem desconto)
  // Chama darBaixaConta com forma_pagamento_id
}
```

### 9. Normalização do Banco de Dados

**Melhorias Aplicadas:**
1. Status de contas movido para tabela própria (normalização)
2. Formas de pagamento em tabela separada
3. Remoção de campo `forma_pagamento` VARCHAR duplicado
4. Foreign keys configuradas adequadamente
5. Índices criados para melhor performance

**Índices Adicionados:**
```sql
CREATE INDEX idx_forma_pagamento_ativo ON formas_pagamento(ativo);
CREATE INDEX idx_conta_forma_pagamento ON contas_receber(forma_pagamento_id);
```

### 10. Otimização de Código

**Backend:**
- Removido código duplicado de cálculo de desconto
- Método `darBaixaConta` consolidado com lógica de desconto
- Validação centralizada de alteração de plano

**Frontend:**
- Removido uso de `prompt()` - substituído por confirmação simples
- Data de pagamento fixada como hoje (não editável)
- Imports desnecessários removidos
- Conversão adequada de DECIMAL (string) para number

## Arquivos Criados

1. `Backend/database/migrations/013_create_auxiliar_tables.sql`
2. `Backend/app/Controllers/ConfigController.php`
3. `FrontEnd/src/app/services/config.service.ts`

## Arquivos Modificados

### Backend
1. `Backend/database/seeds/seed_dashboard_test.sql` - Plano FREE
2. `Backend/app/Controllers/MatriculaController.php` - Validação e desconto
3. `Backend/app/Controllers/ContasReceberController.php` - Desconto
4. `Backend/routes/api.php` - Novos endpoints

### Frontend
1. `FrontEnd/src/app/models/api.models.ts` - Novas interfaces
2. `FrontEnd/src/app/components/admin/gerenciar-alunos/gerenciar-alunos.component.ts` - Baixa imediata
3. `FrontEnd/src/app/components/contas-receber/contas-receber.component.ts` - Conversão de valores
4. `FrontEnd/src/app/components/contas-receber/contas-receber.component.html` - Remoção de campo antigo

## Migration Executada

```bash
docker exec -i appcheckin_mysql mysql -uroot -proot appcheckin \
  < Backend/database/migrations/013_create_auxiliar_tables.sql
```

## Como Testar

### 1. Criar Matrícula com Baixa Imediata
1. Acessar "Gerenciar Alunos"
2. Clicar em "Matricular" em um aluno
3. Selecionar plano e confirmar
4. Confirmar quando perguntar sobre dar baixa
5. Verificar que status do aluno mudou para "Ativo"

### 2. Verificar Cálculo de Desconto
1. Acessar "Contas a Receber"
2. Dar baixa em uma conta pendente
3. Escolher forma de pagamento com desconto (ex: Crédito)
4. Verificar no banco que `valor_desconto` e `valor_liquido` foram calculados

```sql
SELECT id, valor, forma_pagamento_id, valor_desconto, valor_liquido 
FROM contas_receber 
WHERE status = 'pago' 
ORDER BY id DESC 
LIMIT 5;
```

### 3. Validar Bloqueio de Alteração de Plano
1. Criar matrícula e dar baixa (aluno ativo)
2. Tentar criar nova matrícula com plano diferente
3. Verificar mensagem de erro bloqueando a operação

### 4. Plano FREE
```sql
SELECT * FROM planos WHERE nome = 'FREE';
```
Verificar que existe plano com valor 0.00 e 4 check-ins mensais.

## Próximos Passos Sugeridos

1. **Interface para Gerenciar Formas de Pagamento**
   - CRUD de formas de pagamento
   - Ativar/desativar formas
   - Ajustar percentuais

2. **Relatório de Descontos**
   - Total de descontos por forma de pagamento
   - Comparativo de receita bruta vs líquida

3. **Dashboard Financeiro**
   - Gráfico de formas de pagamento mais usadas
   - Análise de impacto dos descontos

4. **Notificações**
   - Alertar quando aluno tentar alterar plano ativo
   - Notificar sobre contas com alto desconto

## Observações Técnicas

- **DECIMAL no MySQL:** Retorna como string no PHP/JSON para evitar perda de precisão
- **Conversão Frontend:** Use `parseFloat()` ou `+valor` para cálculos
- **Foreign Keys:** Configuradas com `ON DELETE SET NULL` para segurança
- **Status Normalizado:** Facilita futura internacionalização e customização
