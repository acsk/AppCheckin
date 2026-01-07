# Sistema de Modalidades e Planos

## Estrutura Revisada

### Conceito
- **Admin cadastra planos baseados em quantidade de checkins na semana**
- **Uma modalidade pode ter múltiplos planos** (ex: Musculação 2x, 3x, 5x semana)
- **No ato da criação da modalidade, os planos são criados juntos**

### Tabelas

#### `modalidades`
```sql
- id
- tenant_id (FK)
- nome (ex: Musculação, Natação, Crossfit)
- descricao
- valor_mensalidade (valor sugerido)
- cor (hex)
- icone (feather icon name)
- ativo
```

#### `planos`
```sql
- id
- tenant_id (FK)
- modalidade_id (FK) → Uma modalidade pode ter vários planos
- nome (ex: "2x por semana", "3x por semana")
- valor (preço mensal)
- checkins_semanais (2, 3, 5, 999=ilimitado)
- duracao_dias (30, 90, 365)
- ativo
- atual (plano disponível para novos contratos)
```

### Relacionamentos

```
modalidade (1) ──< (N) planos

Exemplo:
Modalidade: Musculação
  ├── Plano: 2x por semana (R$ 99,90 - 2 checkins/semana)
  ├── Plano: 3x por semana (R$ 129,90 - 3 checkins/semana)  
  ├── Plano: 5x por semana (R$ 169,90 - 5 checkins/semana)
  └── Plano: Ilimitado (R$ 199,90 - 999 checkins/semana)
```

### Fluxo de Cadastro

1. **Admin cria Modalidade:**
   - Nome: "Musculação"
   - Descrição: "Treino de força"
   - Ícone e Cor
   
2. **Adiciona Planos Dinamicamente:**
   - Plano 1: Nome, Checkins/Semana, Valor, Duração
   - Plano 2: Nome, Checkins/Semana, Valor, Duração
   - ...pode adicionar quantos quiser
   
3. **Sistema cria tudo junto** usando transação SQL

### Migration

Arquivo: `Backend/database/migrations/045_planos_checkins_semanais.sql`

- Adiciona coluna `checkins_semanais` em planos
- Remove coluna `checkins_mensais` (obsoleta)
- Cria índice `idx_modalidade_ativo` para performance

### Frontend

#### FormModalidadeScreen
- Campos da modalidade (nome, descrição, ícone, cor)
- Seção "Planos desta Modalidade"
  - Lista dinâmica de planos
  - Botão "Adicionar Outro Plano"
  - Cada plano tem: nome, checkins/semana, valor, duração
- Validação completa
- Só permite adicionar planos ao **criar** modalidade

#### Backend API
- Endpoint: `POST /admin/modalidades`
- Aceita objeto com:
  ```json
  {
    "nome": "Musculação",
    "descricao": "...",
    "cor": "#f97316",
    "icone": "activity",
    "valor_mensalidade": 150.00,
    "planos": [
      {
        "nome": "2x por semana",
        "checkins_semanais": 2,
        "valor": 99.90,
        "duracao_dias": 30
      },
      {
        "nome": "3x por semana",
        "checkins_semanais": 3,
        "valor": 129.90,
        "duracao_dias": 30
      }
    ]
  }
  ```
- Usa transação SQL para garantir atomicidade

### Controle de Checkins

#### Lógica Semanal
- Sistema conta checkins do usuário **na semana atual**
- Compara com `plano.checkins_semanais`
- Se usuário atingiu o limite, bloqueia novo checkin
- Semana reseta toda segunda-feira 00:00

#### Exemplo
Usuário com plano "3x por semana":
- Segunda: ✅ Checkin (1/3)
- Quarta: ✅ Checkin (2/3)
- Sexta: ✅ Checkin (3/3)
- Sábado: ❌ Limite atingido
- Segunda (próxima semana): ✅ Reset - pode fazer checkin novamente

### Benefícios da Nova Estrutura

1. ✅ **Flexibilidade**: Admin cria quantos planos quiser por modalidade
2. ✅ **Organização**: Planos agrupados por modalidade
3. ✅ **Controle Semanal**: Mais natural para academias (treino 2x, 3x semana)
4. ✅ **Atomicidade**: Modalidade + planos criados juntos
5. ✅ **Escalável**: Fácil adicionar novos planos depois
6. ✅ **Intuitivo**: Interface mostra claramente a relação modalidade → planos
