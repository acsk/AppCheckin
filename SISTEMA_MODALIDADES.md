# Sistema de Modalidades por Tenant

## Visão Geral
Sistema para gerenciar diferentes modalidades esportivas por academia (tenant), permitindo que cada uma tenha custos específicos.

---

## Banco de Dados

### Tabela: `modalidades`

**Estrutura:**
```sql
id                  INT (PK, AUTO_INCREMENT)
tenant_id           INT (FK → tenants.id)
nome                VARCHAR(100) NOT NULL
descricao           TEXT
valor_mensalidade   DECIMAL(10,2) DEFAULT 0.00
cor                 VARCHAR(7) (Hexadecimal)
icone               VARCHAR(50)
ativo               TINYINT(1) DEFAULT 1
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Índices:**
- `idx_tenant` (tenant_id)
- `idx_ativo` (ativo)
- `unique_tenant_modalidade` (tenant_id, nome) - UNIQUE

**Exemplos de Modalidades:**
- Musculação
- CrossFit
- Dança
- Karate
- Judo
- Natação
- Yoga
- Pilates
- Spinning
- Funcional

---

## API Endpoints

### 1. Listar Modalidades
**GET** `/admin/modalidades`

**Query Params:**
- `apenas_ativas=true` (opcional) - Retorna apenas modalidades ativas

**Response:**
```json
{
  "modalidades": [
    {
      "id": 1,
      "tenant_id": 1,
      "nome": "Musculação",
      "descricao": "Treinamento de força e condicionamento físico",
      "valor_mensalidade": "150.00",
      "cor": "#f97316",
      "icone": "activity",
      "ativo": 1,
      "created_at": "2026-01-05 10:00:00",
      "updated_at": "2026-01-05 10:00:00"
    }
  ]
}
```

### 2. Buscar Modalidade
**GET** `/admin/modalidades/{id}`

**Response:**
```json
{
  "modalidade": {
    "id": 1,
    "tenant_id": 1,
    "nome": "CrossFit",
    "descricao": "Treinamento funcional de alta intensidade",
    "valor_mensalidade": "200.00",
    "cor": "#ef4444",
    "icone": "zap",
    "ativo": 1
  }
}
```

### 3. Criar Modalidade
**POST** `/admin/modalidades`

**Body:**
```json
{
  "nome": "Dança",
  "descricao": "Aulas de dança variadas",
  "valor_mensalidade": 120.00,
  "cor": "#ec4899",
  "icone": "music",
  "ativo": 1
}
```

**Validações:**
- ✅ Nome obrigatório
- ✅ Nome único por tenant
- ✅ Valor mensalidade numérico

### 4. Atualizar Modalidade
**PUT** `/admin/modalidades/{id}`

**Body:**
```json
{
  "nome": "Dança Moderna",
  "descricao": "Aulas de dança com novos estilos",
  "valor_mensalidade": 150.00,
  "cor": "#ec4899",
  "icone": "music",
  "ativo": 1
}
```

### 5. Excluir Modalidade (Soft Delete)
**DELETE** `/admin/modalidades/{id}`

**Response:**
```json
{
  "type": "success",
  "message": "Modalidade desativada com sucesso"
}
```

---

## Segurança

### Autenticação e Autorização
- ✅ Requer autenticação (AuthMiddleware)
- ✅ Requer permissão de Admin (AdminMiddleware)
- ✅ Isolamento por Tenant (TenantMiddleware)
- ✅ Validação de propriedade (modalidade pertence ao tenant)

### Validações
- Nome único por tenant
- Verificação de tenant_id em todas operações
- Soft delete (preserva dados históricos)

---

## Associação com Planos ✅ OBRIGATÓRIA

### Estrutura
Cada plano **DEVE** estar associado a UMA modalidade específica através do campo `modalidade_id` (FK - **NOT NULL**).

**Tabela `planos`:**
```sql
id                  INT (PK)
tenant_id           INT (FK → tenants.id)
modalidade_id       INT NOT NULL (FK → modalidades.id) ✅ OBRIGATÓRIO
nome                VARCHAR(100)
descricao           TEXT
valor               DECIMAL(10,2)
duracao_dias        INT
checkins_mensais    INT NULL
max_alunos          INT NULL
ativo               TINYINT(1)
atual               TINYINT(1)
```

### Exemplos de Uso

**1. Plano de Musculação:**
```json
{
  "nome": "Musculação Mensal",
  "modalidade_id": 1,  // ID da modalidade "Musculação" - OBRIGATÓRIO
  "valor": 120.00,
  "duracao_dias": 30
}
```

**2. Academia com Múltiplas Modalidades:**
Para criar um plano "combo", crie uma modalidade específica:
```json
// Primeiro criar a modalidade combo
{
  "nome": "Combo Total",
  "descricao": "Acesso a todas modalidades",
  "valor_mensalidade": 0.00
}

// Depois criar o plano associado
{
  "nome": "All-Inclusive",
  "modalidade_id": 10,  // ID da modalidade "Combo Total"
  "valor": 300.00
}
```

**3. Centro de Artes Marciais:**
```json
[
  {
    "nome": "Karate Kids",
    "modalidade_id": 5,  // Karate
    "valor": 100.00
  },
  {
    "nome": "CrossFit Intenso",
    "modalidade_id": 2,  // CrossFit
    "valor": 180.00
  },
  {
    "nome": "Jiu-Jitsu Avançado",
    "modalidade_id": 8,  // Jiu-Jitsu
    "valor": 200.00
  }
]
```

### Comportamento
- **modalidade_id**: **OBRIGATÓRIO** - Todo plano deve ter uma modalidade
- **ON DELETE RESTRICT**: Não permite excluir modalidade se houver planos vinculados
- **Consultas**: API retorna automaticamente nome, cor e ícone da modalidade via INNER JOIN
- **Validação**: Controller valida presença de modalidade_id ao criar plano
- **Planos Combo**: Crie uma modalidade específica chamada "Combo" ou "All-Inclusive"

---

## Casos de Uso

### 1. Academia de Musculação
```json
[
  {
    "nome": "Musculação",
    "valor_mensalidade": 120.00,
    "cor": "#f97316"
  },
  {
    "nome": "Spinning",
    "valor_mensalidade": 100.00,
    "cor": "#3b82f6"
  }
]
```

### 2. Centro de Artes Marciais
```json
[
  {
    "nome": "Karate",
    "valor_mensalidade": 150.00,
    "cor": "#dc2626"
  },
  {
    "nome": "Judo",
    "valor_mensalidade": 150.00,
    "cor": "#059669"
  },
  {
    "nome": "Jiu-Jitsu",
    "valor_mensalidade": 180.00,
    "cor": "#1e40af"
  }
]
```

### 3. Estúdio Multi-Esportes
```json
[
  {
    "nome": "Yoga",
    "valor_mensalidade": 100.00,
    "cor": "#8b5cf6"
  },
  {
    "nome": "Pilates",
    "valor_mensalidade": 130.00,
    "cor": "#ec4899"
  },
  {
    "nome": "Funcional",
    "valor_mensalidade": 110.00,
    "cor": "#f59e0b"
  }
]
```

---

## Ícones Sugeridos (Feather Icons)

| Modalidade | Ícone | Descrição |
|------------|-------|-----------|
| Musculação | `activity` | Ícone de atividade física |
| CrossFit | `zap` | Raio (intensidade) |
| Dança | `music` | Nota musical |
| Artes Marciais | `shield` | Escudo |
| Natação | `droplet` | Gota d'água |
| Yoga | `heart` | Coração (bem-estar) |
| Spinning | `disc` | Disco (roda) |
| Funcional | `hexagon` | Hexágono (versatilidade) |

---

## Cores Sugeridas

| Cor | Hex | Uso |
|-----|-----|-----|
| Laranja | `#f97316` | Musculação, Força |
| Vermelho | `#ef4444` | CrossFit, Alta Intensidade |
| Azul | `#3b82f6` | Natação, Spinning |
| Verde | `#10b981` | Yoga, Pilates |
| Roxo | `#8b5cf6` | Dança, Expressão |
| Rosa | `#ec4899` | Pilates, Leveza |
| Amarelo | `#f59e0b` | Funcional, Energia |

---

## Próximos Passos

### Backend (Concluído ✅)
- [x] Migration da tabela `modalidades`
- [x] Model `Modalidade`
- [x] Controller `ModalidadeController`
- [x] Rotas API REST completas
- [x] Validações e segurança

### Frontend (A Fazer)
- [ ] Tela de listagem de modalidades
- [ ] Tela de criar/editar modalidade
- [ ] Seletor de cor
- [ ] Seletor de ícone
- [ ] Validação de formulário
- [ ] Integração com API

### Integrações Concluídas
- [x] Associar modalidades aos planos ✅

### Integrações Futuras
- [ ] Associar modalidades às turmas
- [ ] Associar modalidades às matrículas
- [ ] Relatórios por modalidade
- [ ] Dashboard de modalidades mais populares
- [ ] Filtrar check-ins por modalidade

---

## Arquivos Criados

**Backend:**
- `Backend/database/migrations/039_create_modalidades_table.sql`
- `Backend/database/migrations/040_add_modalidade_to_planos.sql` ✅ NOVO
- `Backend/app/Models/Modalidade.php`
- `Backend/app/Models/Plano.php` (atualizado com LEFT JOIN) ✅
- `Backend/app/Controllers/ModalidadeController.php`
- `Backend/routes/api.php` (atualizado)

**Documentação:**
- `SISTEMA_MODALIDADES.md` (este arquivo)

---

**Data de Criação**: 05/01/2026  
**Versão**: 1.0.0  
**Status**: ✅ Backend Implementado
