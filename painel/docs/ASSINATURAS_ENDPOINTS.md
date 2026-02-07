# Endpoints de Assinaturas - Admin e SuperAdmin

## Visão Geral
Endpoints para gerenciamento de assinaturas de planos pelos alunos/membros das academias. Inclui operações de criar, atualizar, renovar, suspender e cancelar assinaturas.

---

## 1. GET /admin/assinaturas

### Descrição
Lista todas as assinaturas da academia do usuário logado com paginação e filtros.

### Request
```bash
GET /admin/assinaturas?status=ativa&plano_id=1&aluno_id=5&pagina=1&limite=20
```

### Headers
```
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
```

### Query Parameters
- `status` (string, opcional): `ativa`, `suspensa`, `cancelada`, `vencida`, `todas` (padrão: `ativa`)
- `plano_id` (integer, opcional): Filtrar por plano específico
- `aluno_id` (integer, opcional): Filtrar por aluno específico
- `modalidade_id` (integer, opcional): Filtrar por modalidade
- `data_inicio_min` (date, opcional): Formato: YYYY-MM-DD
- `data_inicio_max` (date, opcional): Formato: YYYY-MM-DD
- `data_vencimento_min` (date, opcional): Formato: YYYY-MM-DD
- `data_vencimento_max` (date, opcional): Formato: YYYY-MM-DD
- `pagina` (integer, padrão: 1)
- `limite` (integer, padrão: 20, máximo: 100)

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinaturas listadas com sucesso",
  "data": {
    "assinaturas": [
      {
        "id": 1,
        "aluno_id": 5,
        "aluno_nome": "João Silva",
        "aluno_cpf": "123.456.789-00",
        "plano_id": 2,
        "plano_nome": "Plano Ouro - Crossfit",
        "modalidade_id": 3,
        "modalidade_nome": "CrossFit",
        "academia_id": 1,
        "academia_nome": "Academia Central",
        "status": "ativa",
        "data_inicio": "2025-01-15",
        "data_vencimento": "2025-02-15",
        "dias_restantes": 12,
        "valor_mensal": 150.00,
        "forma_pagamento": "cartao_credito",
        "ciclo_tipo": "mensal",
        "permite_recorrencia": true,
        "renovacoes_restantes": 11,
        "motivo_cancelamento": null,
        "data_cancelamento": null,
        "criado_em": "2025-01-15T10:30:00Z",
        "atualizado_em": "2025-01-15T10:30:00Z"
      }
    ],
    "paginacao": {
      "total": 45,
      "pagina": 1,
      "limite": 20,
      "total_paginas": 3
    }
  }
}
```

### Erros
- `401 Unauthorized`: Token inválido ou expirado
- `403 Forbidden`: Usuário não tem permissão para acessar
- `400 Bad Request`: Parâmetros inválidos

---

## 2. GET /superadmin/assinaturas

### Descrição
Lista assinaturas de todas as academias (apenas SuperAdmin). Mesmo set de filtros que `/admin/assinaturas`.

### Request
```bash
GET /superadmin/assinaturas?status=ativa&tenant_id=1&pagina=1&limite=20
```

### Query Parameters
Todos os parâmetros de `/admin/assinaturas` MAIS:
- `tenant_id` (integer, opcional): Filtrar por academia específica

### Response
Igual ao endpoint `/admin/assinaturas`

---

## 3. GET /admin/assinaturas/:id

### Descrição
Busca detalhes completos de uma assinatura específica.

### Request
```bash
GET /admin/assinaturas/1
```

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura encontrada",
  "data": {
    "assinatura": {
      "id": 1,
      "aluno_id": 5,
      "aluno": {
        "id": 5,
        "nome": "João Silva",
        "cpf": "123.456.789-00",
        "email": "joao@email.com",
        "telefone": "11999999999",
        "data_nascimento": "1990-01-15",
        "ativo": true
      },
      "plano": {
        "id": 2,
        "nome": "Plano Ouro - Crossfit",
        "descricao": "Acesso total ao CrossFit",
        "valor": 150.00,
        "ciclo_tipo": "mensal",
        "checkins_semana": 12,
        "permite_recorrencia": true,
        "ativo": true
      },
      "modalidade": {
        "id": 3,
        "nome": "CrossFit",
        "ativo": true
      },
      "academia": {
        "id": 1,
        "nome": "Academia Central",
        "email": "contato@academia.com.br"
      },
      "status": "ativa",
      "data_inicio": "2025-01-15",
      "data_vencimento": "2025-02-15",
      "dias_restantes": 12,
      "valor_mensal": 150.00,
      "forma_pagamento": "cartao_credito",
      "renovacoes_restantes": 11,
      "historico_renovacoes": [
        {
          "id": 1,
          "data": "2025-01-15",
          "valor": 150.00,
          "proxima_data_vencimento": "2025-02-15"
        }
      ],
      "motivo_cancelamento": null,
      "data_cancelamento": null,
      "data_suspensao": null,
      "motivo_suspensao": null,
      "criado_em": "2025-01-15T10:30:00Z",
      "atualizado_em": "2025-01-15T10:30:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `403 Forbidden`: Sem permissão para acessar esta assinatura

---

## 4. POST /admin/assinaturas

### Descrição
Criar nova assinatura para um aluno. Valida disponibilidade de plano, conflito com assinaturas ativas e integra com sistema de pagamentos.

### Request
```bash
POST /admin/assinaturas
Content-Type: application/json

{
  "aluno_id": 5,
  "plano_id": 2,
  "data_inicio": "2025-01-15",
  "forma_pagamento": "cartao_credito",
  "renovacoes": 12,
  "observacoes": "Assinatura iniciada em recepção"
}
```

### Body Parameters
- `aluno_id` (integer, **obrigatório**): ID do aluno
- `plano_id` (integer, **obrigatório**): ID do plano
- `data_inicio` (date, **obrigatório**): Formato YYYY-MM-DD
- `forma_pagamento` (string, **obrigatório**): `dinheiro`, `cartao_credito`, `cartao_debito`, `pix`, `boleto`
- `renovacoes` (integer, opcional): Quantas renovações automáticas (padrão: 0 = ilimitado)
- `observacoes` (string, opcional): Notas do admin

### Response (201 Created)
```json
{
  "type": "success",
  "message": "Assinatura criada com sucesso",
  "data": {
    "assinatura": {
      "id": 25,
      "aluno_id": 5,
      "plano_id": 2,
      "status": "ativa",
      "data_inicio": "2025-01-15",
      "data_vencimento": "2025-02-15",
      "valor_mensal": 150.00,
      "forma_pagamento": "cartao_credito",
      "renovacoes_restantes": 12,
      "criado_em": "2025-01-15T10:30:00Z"
    }
  }
}
```

### Erros
- `400 Bad Request`: Dados inválidos ou aluno já possui assinatura ativa para este plano
- `404 Not Found`: Aluno ou plano não encontrado
- `409 Conflict`: Conflito com assinatura existente (aluno já ativo nesta modalidade)
- `422 Unprocessable Entity`: Aluno não pode assinar este plano (restrições de documentação, etc)

---

## 5. PUT /admin/assinaturas/:id

### Descrição
Atualizar detalhes de uma assinatura ativa (datas, forma de pagamento, renovações restantes).

### Request
```bash
PUT /admin/assinaturas/1
Content-Type: application/json

{
  "data_vencimento": "2025-03-15",
  "forma_pagamento": "pix",
  "renovacoes_restantes": 5,
  "observacoes": "Extensão concedida por cliente VIP"
}
```

### Body Parameters
- `data_vencimento` (date, opcional): Formato YYYY-MM-DD
- `forma_pagamento` (string, opcional): Alterar método de pagamento
- `renovacoes_restantes` (integer, opcional): Atualizar número de renovações
- `observacoes` (string, opcional): Adicionar/atualizar notas

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura atualizada com sucesso",
  "data": {
    "assinatura": {
      "id": 1,
      "aluno_id": 5,
      "status": "ativa",
      "data_vencimento": "2025-03-15",
      "forma_pagamento": "pix",
      "renovacoes_restantes": 5,
      "atualizado_em": "2025-01-20T15:45:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `400 Bad Request`: Dados inválidos
- `409 Conflict`: Não é possível atualizar assinatura neste status

---

## 6. POST /admin/assinaturas/:id/renovar

### Descrição
Renovar uma assinatura estendendo sua data de vencimento por mais um ciclo. Requer pagamento processado ou crédito na conta.

### Request
```bash
POST /admin/assinaturas/1/renovar
Content-Type: application/json

{
  "data_renovacao": "2025-02-15",
  "gerar_cobranca": true,
  "observacoes": "Renovação automática processada"
}
```

### Body Parameters
- `data_renovacao` (date, opcional): Data da renovação (padrão: hoje)
- `gerar_cobranca` (boolean, opcional): Se true, gera cobrança automática (padrão: false)
- `observacoes` (string, opcional): Notas sobre a renovação

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura renovada com sucesso",
  "data": {
    "assinatura": {
      "id": 1,
      "status": "ativa",
      "data_vencimento": "2025-03-15",
      "renovacoes_restantes": 10,
      "valor_renovacao": 150.00,
      "proxima_renovacao": "2025-03-15",
      "atualizado_em": "2025-02-15T10:30:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `400 Bad Request`: Não há renovações restantes ou assinatura não está ativa
- `409 Conflict`: Assinatura já foi renovada recentemente

---

## 7. POST /admin/assinaturas/:id/suspender

### Descrição
Suspender uma assinatura ativa. Aluno perde acesso mas pode reativar depois.

### Request
```bash
POST /admin/assinaturas/1/suspender
Content-Type: application/json

{
  "motivo": "Pagamento pendente",
  "data_suspensao": "2025-01-20"
}
```

### Body Parameters
- `motivo` (string, **obrigatório**): Razão da suspensão
- `data_suspensao` (date, opcional): Data da suspensão (padrão: hoje)

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura suspensa com sucesso",
  "data": {
    "assinatura": {
      "id": 1,
      "status": "suspensa",
      "motivo_suspensao": "Pagamento pendente",
      "data_suspensao": "2025-01-20",
      "data_vencimento": "2025-02-15",
      "atualizado_em": "2025-01-20T14:20:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `409 Conflict`: Assinatura não está em status que permite suspensão

---

## 8. POST /admin/assinaturas/:id/reativar

### Descrição
Reativar uma assinatura suspensa. Aluno recebe acesso novamente.

### Request
```bash
POST /admin/assinaturas/1/reativar
Content-Type: application/json

{
  "gerar_cobranca": true,
  "observacoes": "Pagamento recebido - reativação"
}
```

### Body Parameters
- `gerar_cobranca` (boolean, opcional): Se true, gera nova cobrança (padrão: false)
- `observacoes` (string, opcional): Notas sobre reativação

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura reativada com sucesso",
  "data": {
    "assinatura": {
      "id": 1,
      "status": "ativa",
      "data_reativacao": "2025-01-20T14:30:00Z",
      "atualizado_em": "2025-01-20T14:30:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `409 Conflict`: Assinatura não está suspensa ou venceu

---

## 9. POST /admin/assinaturas/:id/cancelar

### Descrição
Cancelar uma assinatura permanentemente. Não pode ser revertida.

### Request
```bash
POST /admin/assinaturas/1/cancelar
Content-Type: application/json

{
  "motivo": "Aluno cancelou inscrição",
  "data_cancelamento": "2025-01-20",
  "gerar_reembolso": true
}
```

### Body Parameters
- `motivo` (string, **obrigatório**): Razão do cancelamento
- `data_cancelamento` (date, opcional): Data do cancelamento (padrão: hoje)
- `gerar_reembolso` (boolean, opcional): Se true, inicia processo de reembolso proporcional (padrão: false)

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinatura cancelada com sucesso",
  "data": {
    "assinatura": {
      "id": 1,
      "status": "cancelada",
      "motivo_cancelamento": "Aluno cancelou inscrição",
      "data_cancelamento": "2025-01-20",
      "dias_usados": 6,
      "valor_reembolso": 30.00,
      "atualizado_em": "2025-01-20T14:35:00Z"
    }
  }
}
```

### Erros
- `404 Not Found`: Assinatura não encontrada
- `409 Conflict`: Assinatura já foi cancelada

---

## 10. GET /admin/assinaturas/proximas-vencer

### Descrição
Listar assinaturas que vencerão em breve para lembretes de renovação.

### Request
```bash
GET /admin/assinaturas/proximas-vencer?dias=30&limite=50
```

### Query Parameters
- `dias` (integer, padrão: 30): Número de dias para considerar "próximas"
- `limite` (integer, padrão: 50): Limite de resultados

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Assinaturas próximas de vencer listadas",
  "data": {
    "assinaturas": [
      {
        "id": 1,
        "aluno_nome": "João Silva",
        "plano_nome": "Plano Ouro",
        "data_vencimento": "2025-02-10",
        "dias_restantes": 21,
        "email": "joao@email.com",
        "telefone": "11999999999"
      }
    ],
    "total": 12
  }
}
```

---

## 11. GET /admin/alunos/:aluno_id/assinaturas

### Descrição
Listar histórico completo de assinaturas de um aluno específico.

### Request
```bash
GET /admin/alunos/5/assinaturas?incluir_canceladas=true
```

### Query Parameters
- `incluir_canceladas` (boolean, padrão: true): Incluir assinaturas canceladas

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Histórico de assinaturas do aluno",
  "data": {
    "aluno": {
      "id": 5,
      "nome": "João Silva",
      "cpf": "123.456.789-00"
    },
    "assinaturas": [
      {
        "id": 25,
        "plano_nome": "Plano Ouro - Crossfit",
        "status": "ativa",
        "data_inicio": "2025-01-15",
        "data_vencimento": "2025-02-15",
        "valor_mensal": 150.00
      },
      {
        "id": 24,
        "plano_nome": "Plano Bronze - Crossfit",
        "status": "cancelada",
        "data_inicio": "2024-12-01",
        "data_fim": "2024-12-31",
        "valor_mensal": 100.00,
        "motivo_cancelamento": "Aluno cancelou"
      }
    ]
  }
}
```

---

## 12. GET /admin/assinaturas/relatorio

### Descrição
Gerar relatório analítico de assinaturas (receita, churn rate, renovação, etc).

### Request
```bash
GET /admin/assinaturas/relatorio?data_inicio=2025-01-01&data_fim=2025-01-31&agrupar_por=modalidade
```

### Query Parameters
- `data_inicio` (date, opcional): YYYY-MM-DD
- `data_fim` (date, opcional): YYYY-MM-DD
- `agrupar_por` (string, opcional): `modalidade`, `plano`, `forma_pagamento`, `nenhuma` (padrão)
- `status` (string, opcional): `ativa`, `cancelada`, `suspensa`, etc

### Response (200 OK)
```json
{
  "type": "success",
  "message": "Relatório gerado com sucesso",
  "data": {
    "periodo": {
      "data_inicio": "2025-01-01",
      "data_fim": "2025-01-31"
    },
    "resumo": {
      "total_assinaturas": 150,
      "assinaturas_ativas": 145,
      "assinaturas_canceladas": 3,
      "assinaturas_suspensas": 2,
      "receita_total": 18500.00,
      "receita_media_por_assinatura": 127.59,
      "churn_rate": 2.0,
      "cancelamentos_este_periodo": 3,
      "novas_assinaturas": 8,
      "renovacoes": 142
    },
    "por_modalidade": [
      {
        "modalidade": "CrossFit",
        "total": 85,
        "receita": 12000.00,
        "valor_medio": 141.18,
        "churn_rate": 1.2
      },
      {
        "modalidade": "Musculação",
        "total": 65,
        "receita": 6500.00,
        "valor_medio": 100.00,
        "churn_rate": 3.1
      }
    ]
  }
}
```

---

## Estrutura de Dados - Tabela `assinaturas`

```sql
CREATE TABLE assinaturas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  aluno_id INT NOT NULL,
  plano_id INT NOT NULL,
  academia_id INT NOT NULL,
  status ENUM('ativa', 'suspensa', 'cancelada', 'vencida') DEFAULT 'ativa',
  data_inicio DATE NOT NULL,
  data_vencimento DATE NOT NULL,
  data_suspensao DATE NULL,
  data_cancelamento DATE NULL,
  data_reativacao DATETIME NULL,
  motivo_suspensao VARCHAR(255) NULL,
  motivo_cancelamento VARCHAR(255) NULL,
  valor_mensal DECIMAL(10, 2) NOT NULL,
  forma_pagamento ENUM('dinheiro', 'cartao_credito', 'cartao_debito', 'pix', 'boleto') DEFAULT 'dinheiro',
  ciclo_tipo VARCHAR(50) NOT NULL,
  permite_recorrencia BOOLEAN DEFAULT true,
  renovacoes_restantes INT DEFAULT 0,
  observacoes TEXT NULL,
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (aluno_id) REFERENCES alunos(id),
  FOREIGN KEY (plano_id) REFERENCES planos(id),
  FOREIGN KEY (academia_id) REFERENCES academias(id),
  INDEX idx_aluno_id (aluno_id),
  INDEX idx_plano_id (plano_id),
  INDEX idx_academia_id (academia_id),
  INDEX idx_status (status),
  INDEX idx_data_vencimento (data_vencimento)
);

CREATE TABLE assinatura_renovacoes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  assinatura_id INT NOT NULL,
  data_renovacao DATE NOT NULL,
  proxima_data_vencimento DATE NOT NULL,
  valor_renovacao DECIMAL(10, 2) NOT NULL,
  forma_pagamento VARCHAR(50) DEFAULT 'mesma',
  criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assinatura_id) REFERENCES assinaturas(id),
  INDEX idx_assinatura_id (assinatura_id)
);
```

---

## Codes de Status HTTP

- `200 OK`: Sucesso em GET, PUT, POST (renovar, suspender, etc)
- `201 Created`: Sucesso em POST (criar)
- `400 Bad Request`: Validação falhou
- `401 Unauthorized`: Token inválido/expirado
- `403 Forbidden`: Sem permissão
- `404 Not Found`: Recurso não encontrado
- `409 Conflict`: Conflito (assinatura duplicada, status inválido, etc)
- `422 Unprocessable Entity`: Dados semanticamente inválidos
- `500 Internal Server Error`: Erro do servidor

---

## Middleware e Autenticação

Todos os endpoints requerem:
1. **AuthMiddleware**: Validação de JWT token
2. **TenantMiddleware**: Isolamento de dados por academia (admin) ou acesso total (superadmin)
3. **AdminMiddleware** (exceto /superadmin/*): Validação de role Admin
4. **SuperAdminMiddleware** (para /superadmin/*): Validação de role SuperAdmin
