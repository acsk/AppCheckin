# 📚 Documentação Completa da API - App Check-in

**Data:** 10 de janeiro de 2026  
**Versão:** 1.0  
**Ambiente:** http://localhost:8080

---

## 📑 Índice

1. [Autenticação](#autenticação)
2. [Endpoints Públicos](#endpoints-públicos)
3. [Endpoints de Perfil](#endpoints-de-perfil)
4. [Endpoints de Turmas](#endpoints-de-turmas)
5. [Endpoints de Dias e Horários](#endpoints-de-dias-e-horários)
6. [Endpoints de Contratos/Planos](#endpoints-de-contratosplanos)
7. [Endpoints de Pagamentos](#endpoints-de-pagamentos)
8. [Endpoints Mobile](#endpoints-mobile)
9. [Testes e Exemplos](#testes-e-exemplos)
10. [Fluxos Completos](#fluxos-completos)

---

## 🔐 Autenticação

Todos os endpoints protegidos requerem um JWT Bearer token no header:

```
Authorization: Bearer {seu_token_jwt}
```

Suporte a Multi-tenant (opcional):
```
X-Tenant-Slug: {tenant_slug}
```

---

## 🔓 Endpoints Públicos

### 1. Health Check - Verificar API

**Endpoint:** `GET /`

**Resposta de Sucesso (200):**
```json
{
  "message": "API Check-in - funcionando!",
  "version": "1.0.0"
}
```

---

### 2. Registrar Novo Usuário

**Endpoint:** `POST /auth/register`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "senha": "senha123"
}
```

**Validações:**
- Email: formato válido e único
- Senha: mínimo 6 caracteres
- Nome: obrigatório

**Resposta de Sucesso (201):**
```json
{
  "message": "Usuário criado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 2,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "created_at": "2025-11-23 10:30:00",
    "updated_at": "2025-11-23 10:30:00"
  }
}
```

**Resposta de Erro - Email já existe (422):**
```json
{
  "error": "Email já registrado"
}
```

---

### 3. Login

**Endpoint:** `POST /auth/login`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "email": "teste@exemplo.com",
  "senha": "password123"
}
```

**Resposta de Sucesso (200):**
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "nome": "Usuário Teste",
    "email": "teste@exemplo.com",
    "created_at": "2025-11-23 10:00:00",
    "updated_at": "2025-11-23 10:00:00"
  },
  "requires_tenant_selection": false
}
```

**Resposta - Multi-tenant (200):**
```json
{
  "message": "Login realizado com sucesso",
  "requires_tenant_selection": true,
  "tenants": [
    {
      "id": 1,
      "nome": "Academia Principal",
      "slug": "academia-principal"
    },
    {
      "id": 2,
      "nome": "Academia Secundária",
      "slug": "academia-secundaria"
    }
  ],
  "user": {
    "id": 1,
    "nome": "Usuário Teste",
    "email": "teste@exemplo.com"
  }
}
```

**Resposta de Erro - Credenciais inválidas (401):**
```json
{
  "error": "Email ou senha incorretos"
}
```

---

### 4. Selecionar Tenant (Multi-tenant)

**Endpoint:** `POST /auth/select-tenant`

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
  "tenant_id": 1
}
```

**Resposta de Sucesso (200):**
```json
{
  "message": "Tenant selecionado com sucesso",
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
}
```

---

## 👤 Endpoints de Perfil

### 1. Obter Dados do Usuário Logado

**Endpoint:** `GET /me`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Resposta de Sucesso (200):**
```json
{
  "id": 1,
  "tenant_id": 1,
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "cpf": "12345678901",
  "cep": "01310-100",
  "telefone": "(11) 98765-4321",
  "logradouro": "Avenida Paulista",
  "numero": "1000",
  "complemento": "Apto 501",
  "bairro": "Bela Vista",
  "cidade": "São Paulo",
  "estado": "SP",
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "created_at": "2025-11-20 10:30:00",
  "updated_at": "2025-11-23 14:20:00"
}
```

**Resposta de Erro (401):**
```json
{
  "error": "Token inválido ou expirado"
}
```

---

### 2. Atualizar Perfil do Usuário

**Endpoint:** `PUT /me`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Body (todos os campos opcionais):**
```json
{
  "nome": "João da Silva",
  "email": "joao.silva@exemplo.com",
  "senha": "novaSenha123",
  "cpf": "12345678901",
  "cep": "01310-100",
  "telefone": "(11) 98765-4321",
  "logradouro": "Avenida Paulista",
  "numero": "1000",
  "complemento": "Apto 501",
  "bairro": "Bela Vista",
  "cidade": "São Paulo",
  "estado": "SP",
  "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Validações:**
- Email: formato válido e único no tenant
- Senha: mínimo 6 caracteres
- Foto: 
  - Formato: `data:image/{tipo};base64,{dados}`
  - Tipos: jpeg, jpg, png, gif, webp
  - Tamanho máximo: 5MB
- CPF: somente números
- CEP: somente números

**Resposta de Sucesso (200):**
```json
{
  "message": "Usuário atualizado com sucesso",
  "user": {
    "id": 1,
    "tenant_id": 1,
    "nome": "João da Silva",
    "email": "joao.silva@exemplo.com",
    "cpf": "12345678901",
    "cep": "01310-100",
    "telefone": "(11) 98765-4321",
    "logradouro": "Avenida Paulista",
    "numero": "1000",
    "complemento": "Apto 501",
    "bairro": "Bela Vista",
    "cidade": "São Paulo",
    "estado": "SP",
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "created_at": "2025-11-20 10:30:00",
    "updated_at": "2025-11-23 14:25:00"
  }
}
```

**Resposta de Erro - Validação (422):**
```json
{
  "errors": [
    "Email inválido",
    "Senha deve ter no mínimo 6 caracteres",
    "Imagem muito grande. Tamanho máximo: 5MB"
  ]
}
```

---

### 3. Obter Estatísticas do Usuário

**Endpoint:** `GET /usuarios/{id}/estatisticas`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Parâmetros de URL:**
- `id`: ID do usuário

**Resposta de Sucesso (200):**
```json
{
  "id": 1,
  "nome": "João Silva",
  "email": "joao@exemplo.com",
  "foto_url": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "total_checkins": 45,
  "total_prs": 0,
  "created_at": "2025-11-20 10:30:00",
  "updated_at": "2025-11-23 14:20:00"
}
```

**Resposta de Erro (404):**
```json
{
  "error": "Usuário não encontrado"
}
```

---

## 📅 Endpoints de Dias e Horários

### 1. Listar Dias Disponíveis

**Endpoint:** `GET /dias`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Resposta de Sucesso (200):**
```json
{
  "dias": [
    {
      "id": 1,
      "data": "2025-11-24",
      "ativo": 1,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    },
    {
      "id": 2,
      "data": "2025-11-25",
      "ativo": 1,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    }
  ]
}
```

---

### 2. Listar Horários de um Dia Específico

**Endpoint:** `GET /dias/{id}/horarios`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Parâmetros de URL:**
- `id`: ID do dia

**Resposta de Sucesso (200):**
```json
{
  "dia": {
    "id": 1,
    "data": "2025-11-24",
    "ativo": 1,
    "created_at": "2025-11-23 10:00:00",
    "updated_at": "2025-11-23 10:00:00"
  },
  "horarios": [
    {
      "id": 1,
      "dia_id": 1,
      "hora": "08:00:00",
      "vagas": 10,
      "ativo": 1,
      "checkins_count": 2,
      "vagas_disponiveis": 8,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    },
    {
      "id": 2,
      "dia_id": 1,
      "hora": "09:00:00",
      "vagas": 15,
      "ativo": 1,
      "checkins_count": 0,
      "vagas_disponiveis": 15,
      "created_at": "2025-11-23 10:00:00",
      "updated_at": "2025-11-23 10:00:00"
    }
  ]
}
```

---

### 3. Fazer Check-in

**Endpoint:** `POST /checkin`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Body:**
```json
{
  "horario_id": 1
}
```

**Resposta de Sucesso (201):**
```json
{
  "message": "Check-in realizado com sucesso",
  "checkin": {
    "id": 1,
    "usuario_id": 1,
    "horario_id": 1,
    "data_checkin": "2025-11-24 08:05:00",
    "created_at": "2025-11-24 08:05:00"
  }
}
```

**Resposta de Erro - Vagas cheias (422):**
```json
{
  "error": "Horário lotado, sem vagas disponíveis"
}
```

---

## 📚 Endpoints de Turmas

### 1. Listar Todas as Turmas com Ocupação

**Endpoint:** `GET /turmas`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Resposta de Sucesso (200):**
```json
{
  "turmas_por_dia": [
    {
      "data": "2025-11-24",
      "dia_ativo": true,
      "turmas": [
        {
          "id": 147,
          "hora": "06:00:00",
          "horario_inicio": "06:00:00",
          "horario_fim": "07:00:00",
          "limite_alunos": 30,
          "alunos_registrados": 5,
          "vagas_disponiveis": 25,
          "percentual_ocupacao": 16.67,
          "ativo": true
        },
        {
          "id": 154,
          "hora": "07:00:00",
          "horario_inicio": "07:00:00",
          "horario_fim": "08:00:00",
          "limite_alunos": 30,
          "alunos_registrados": 12,
          "vagas_disponiveis": 18,
          "percentual_ocupacao": 40.0,
          "ativo": true
        }
      ]
    }
  ],
  "total_turmas": 49
}
```

**Casos de Uso:**
- Dashboard administrativo
- Visualização de ocupação geral
- Planejamento de capacidade
- Identificar turmas lotadas ou vazias

---

### 2. Listar Alunos de uma Turma

**Endpoint:** `GET /turmas/{id}/alunos`

**Headers:**
```
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

**Parâmetros de URL:**
- `id`: ID da turma/horário

**Resposta de Sucesso (200):**
```json
{
  "turma": {
    "id": 147,
    "data": "2025-11-24",
    "hora": "06:00:00",
    "horario_inicio": "06:00:00",
    "horario_fim": "07:00:00",
    "limite_alunos": 30,
    "alunos_registrados": 2,
    "vagas_disponiveis": 28
  },
  "alunos": [
    {
      "id": 4,
      "nome": "Aluno Novo",
      "email": "aluno@novo.com",
      "data_checkin": "2025-11-24 06:05:00",
      "created_at": "2025-11-23 17:33:51"
    },
    {
      "id": 5,
      "nome": "João Silva",
      "email": "joao@exemplo.com",
      "data_checkin": "2025-11-24 06:08:30",
      "created_at": "2025-11-23 17:35:22"
    }
  ],
  "total_alunos": 2
}
```

**Casos de Uso:**
- Chamada de presença
- Verificação de check-ins
- Relatórios de frequência
- Lista de participantes
- Verificar horário de chegada

---

## � Endpoints Mobile

### 1. Obter Perfil Completo

**Endpoint:** `GET /mobile/perfil`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "nome": "João Silva",
    "email": "joao@exemplo.com",
    "email_global": "joao@exemplo.com",
    "cpf": "12345678901",
    "telefone": "(11) 98765-4321",
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "role_id": 1,
    "role_nome": "Aluno",
    "membro_desde": "2025-11-20 10:30:00",
    "tenant": {
      "id": 1,
      "nome": "Academia Principal",
      "slug": "academia-principal",
      "email": "contato@academia.com",
      "telefone": "(11) 3000-0000"
    },
    "plano": {
      "id": 1,
      "nome": "Plano Mensal",
      "valor": "99.90",
      "duracao_dias": 30,
      "descricao": "Acesso completo",
      "data_inicio": "2025-11-20",
      "data_fim": "2025-12-20",
      "vinculo_status": "ativo"
    },
    "estatisticas": {
      "total_checkins": 15,
      "checkins_mes": 12,
      "sequencia_dias": 5,
      "ultimo_checkin": {
        "data": "2025-11-24",
        "hora": "06:05:00"
      }
    }
  }
}
```

---

### 2. Listar Tenants do Usuário

**Endpoint:** `GET /mobile/tenants`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "tenants": [
      {
        "id": 1,
        "nome": "Academia Principal",
        "slug": "academia-principal",
        "email": "contato@academia.com",
        "telefone": "(11) 3000-0000"
      },
      {
        "id": 2,
        "nome": "Academia Secundária",
        "slug": "academia-secundaria",
        "email": "contato2@academia.com",
        "telefone": "(11) 3000-0001"
      }
    ],
    "total": 2
  }
}
```

---

### 3. Obter Contrato/Plano Ativo ⭐ NOVO

**Endpoint:** `GET /mobile/contratos`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200) - Contrato Ativo:**
```json
{
  "success": true,
  "data": {
    "contrato_ativo": {
      "id": 5,
      "plano": {
        "id": 2,
        "nome": "Enterprise",
        "descricao": "Plano para grandes academias",
        "valor": 250.00,
        "max_alunos": 500,
        "max_admins": 10,
        "features": [
          "relatórios_avançados",
          "api_integracao",
          "suporte_24h"
        ]
      },
      "status": {
        "id": 1,
        "nome": "Ativo",
        "codigo": "ativo"
      },
      "vigencia": {
        "data_inicio": "2026-01-05",
        "data_fim": "2027-01-05",
        "dias_restantes": 360,
        "dias_total": 365,
        "percentual_uso": 1,
        "ativo": true
      },
      "pagamentos": {
        "total": 12,
        "lista": [
          {
            "id": 1,
            "valor": 250.00,
            "data_vencimento": "2026-01-05",
            "data_pagamento": "2026-01-05",
            "status": "Pago",
            "forma_pagamento": "pix"
          },
          {
            "id": 2,
            "valor": 250.00,
            "data_vencimento": "2026-02-05",
            "data_pagamento": null,
            "status": "Aguardando",
            "forma_pagamento": "pix"
          }
        ]
      },
      "observacoes": "Contrato com pagamento inicial realizado"
    },
    "tenant": {
      "id": 4,
      "nome": "Sporte e Saúde - Baixa Grande",
      "slug": "sporte-saude-baixa-grande"
    }
  }
}
```

**Resposta (200) - Sem Contrato Ativo:**
```json
{
  "success": true,
  "data": {
    "contrato_ativo": null,
    "mensagem": "Nenhum contrato ativo no momento"
  }
}
```

**Resposta de Erro (400) - Sem Tenant Selecionado:**
```json
{
  "success": false,
  "error": "Nenhum tenant selecionado"
}
```

---

### 4. Listar Todos os Planos/Contratos ⭐ NOVO

**Endpoint:** `GET /mobile/planos`

**Descrição:** Retorna TODOS os contratos/planos do tenant (ativos, vencidos, pendentes, etc). Use este endpoint quando a academia tiver múltiplos contratos ativos (plano anterior ainda vigente + novo plano). O endpoint retorna agregações de pagamentos para cada contrato, útil para exibir resumo de status no app.

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200) - Múltiplos Planos:**
```json
{
  "success": true,
  "data": {
    "planos": [
      {
        "id": 5,
        "plano": {
          "id": 2,
          "nome": "Enterprise",
          "descricao": "Plano para grandes academias",
          "valor": 250.00,
          "max_alunos": 500,
          "max_admins": 10,
          "features": [
            "relatórios_avançados",
            "api_integracao",
            "suporte_24h"
          ]
        },
        "status": {
          "id": 1,
          "nome": "Ativo",
          "codigo": "ativo"
        },
        "vigencia": {
          "data_inicio": "2026-01-05",
          "data_fim": "2027-01-05",
          "dias_restantes": 360,
          "dias_total": 365,
          "percentual_uso": 1,
          "ativo": true
        },
        "pagamentos": {
          "total": 12,
          "pago": 1,
          "aguardando": 11,
          "atrasado": 0,
          "lista": [
            {
              "id": 1,
              "valor": 250.00,
              "data_vencimento": "2026-01-05",
              "data_pagamento": "2026-01-05",
              "status": "Pago",
              "forma_pagamento": "pix"
            },
            {
              "id": 2,
              "valor": 250.00,
              "data_vencimento": "2026-02-05",
              "data_pagamento": null,
              "status": "Aguardando",
              "forma_pagamento": "pix"
            }
          ]
        }
      },
      {
        "id": 4,
        "plano": {
          "id": 1,
          "nome": "Professional",
          "descricao": "Plano intermediário",
          "valor": 150.00,
          "max_alunos": 200,
          "max_admins": 5,
          "features": [
            "relatórios_básicos",
            "suporte_email"
          ]
        },
        "status": {
          "id": 3,
          "nome": "Cancelado",
          "codigo": "cancelado"
        },
        "vigencia": {
          "data_inicio": "2025-12-05",
          "data_fim": "2026-01-05",
          "dias_restantes": 0,
          "dias_total": 31,
          "percentual_uso": 100,
          "ativo": false
        },
        "pagamentos": {
          "total": 1,
          "pago": 1,
          "aguardando": 0,
          "atrasado": 0,
          "lista": []
        }
      }
    ],
    "total": 2,
    "tenant": {
      "id": 4,
      "nome": "Sporte e Saúde - Baixa Grande",
      "slug": "sporte-saude-baixa-grande"
    }
  }
}
```

**Resposta (200) - Sem Contratos:**
```json
{
  "success": true,
  "data": {
    "planos": [],
    "total": 0,
    "tenant": {
      "id": 4,
      "nome": "Sporte e Saúde - Baixa Grande",
      "slug": "sporte-saude-baixa-grande"
    }
  }
}
```

**Resposta de Erro (400) - Sem Tenant Selecionado:**
```json
{
  "success": false,
  "error": "Nenhum tenant selecionado"
}
```

**Uso no App:**
```javascript
// Listar todos os planos (para exibir transições entre planos)
const planosData = await mobileService.getPlanos();

// Filtrar apenas ativos
const planosAtivos = planosData.data.planos.filter(p => p.status.id === 1);

// Verificar status de pagamentos
planosData.data.planos.forEach(plano => {
  console.log(`${plano.plano.nome}: ${plano.pagamentos.aguardando} pagamentos pendentes`);
});
```

---

### 5. Registrar Check-in

**Endpoint:** `POST /mobile/checkin`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body (opcional - app pode buscar horário automaticamente):**
```json
{
  "horario_id": 1
}
```

**Resposta de Sucesso (201):**
```json
{
  "success": true,
  "message": "Check-in realizado com sucesso",
  "data": {
    "checkin_id": 123,
    "usuario_id": 1,
    "horario_id": 1,
    "data_checkin": "2025-11-24 06:05:00"
  }
}
```

---

### 5. Listar Horários de Hoje

**Endpoint:** `GET /mobile/horarios`

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "dia": {
      "id": 1,
      "data": "2025-11-24",
      "ativo": true
    },
    "horarios": [
      {
        "id": 1,
        "hora": "06:00:00",
        "horario_inicio": "06:00:00",
        "horario_fim": "07:00:00",
        "limite_alunos": 30,
        "ativo": true,
        "turmas": [
          {
            "turma_id": 5,
            "turma_nome": "Turma A",
            "ativa": true,
            "modalidade": {
              "id": 1,
              "nome": "Musculação",
              "cor": "#FF0000"
            },
            "professor": {
              "id": 2,
              "nome": "Prof. Carlos"
            },
            "confirmados": 15,
            "vagas": 15
          }
        ]
      }
    ],
    "total_horarios": 5
  }
}
```

---

### 6. Listar Próximos Horários

**Endpoint:** `GET /mobile/horarios/proximos`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros Query:**
- `dias` (opcional): Número de dias à frente (padrão 7)

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "horarios": [
      {
        "data": "2025-11-24",
        "hora": "06:00:00",
        "turma_nome": "Turma A",
        "professor": "Prof. Carlos",
        "modalidade": "Musculação",
        "vagas": 15
      }
    ],
    "total": 1
  }
}
```

---

### 7. Listar Horários por Dia

**Endpoint:** `GET /mobile/horarios/{diaId}`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros de URL:**
- `diaId`: ID do dia

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "dia": {
      "id": 1,
      "data": "2025-11-24",
      "ativo": true
    },
    "horarios": [
      {
        "id": 1,
        "hora": "06:00:00",
        "horario_inicio": "06:00:00",
        "horario_fim": "07:00:00",
        "limite_alunos": 30,
        "ativo": true,
        "turmas": []
      }
    ],
    "total_horarios": 3
  }
}
```

---

### 8. Listar Histórico de Check-ins

**Endpoint:** `GET /mobile/checkins`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros Query:**
- `limit` (opcional): Limite de registros (padrão 30, máximo 100)
- `offset` (opcional): Offset para paginação

**Resposta de Sucesso (200):**
```json
{
  "success": true,
  "data": {
    "checkins": [
      {
        "id": 123,
        "data": "2025-11-24",
        "hora": "06:05:00",
        "turma_nome": "Turma A",
        "professor": "Prof. Carlos",
        "modalidade": "Musculação"
      }
    ],
    "total": 15,
    "limit": 30,
    "offset": 0
  }
}
```

---

## 💻 Consumindo os Endpoints Mobile no App

### Usando o mobileService (JavaScript/React Native)

```javascript
import { mobileService } from '@/services/mobileService';

// 1. Obter perfil com estatísticas
const perfilData = await mobileService.getPerfil();
console.log(perfilData.data.estatisticas.total_checkins);

// 2. Listar tenants do usuário
const tenantsData = await mobileService.getTenants();
console.log(tenantsData.data.tenants);

// 3. Obter contrato ativo (NOVO)
const contratoData = await mobileService.getContratos();
if (contratoData.data.contrato_ativo) {
  console.log('Plano:', contratoData.data.contrato_ativo.plano.nome);
  console.log('Dias restantes:', contratoData.data.contrato_ativo.vigencia.dias_restantes);
  console.log('Pagamentos:', contratoData.data.contrato_ativo.pagamentos.lista);
}

// 4. Obter todos os planos/contratos (NOVO - para transições de planos)
const planosData = await mobileService.getPlanos();
const planosAtivos = planosData.data.planos.filter(p => p.status.id === 1);
console.log(`Tenant tem ${planosAtivos.length} plano(s) ativo(s)`);

// Listar status de pagamentos de todos os planos
planosData.data.planos.forEach(plano => {
  console.log(`${plano.plano.nome}: ${plano.pagamentos.pago} pago(s), ${plano.pagamentos.aguardando} aguardando`);
});

// 5. Registrar check-in
const checkinData = await mobileService.registrarCheckin({
  horario_id: 1
});

// 6. Buscar horários de hoje
const horariosData = await mobileService.getHorarios();
```

---

## �💳 Endpoints de Pagamentos

### 1. Listar Todos os Pagamentos (SuperAdmin)

**Endpoint:** `GET /superadmin/pagamentos`

**Query Params:**
- `status_pagamento_id`: Filtrar por status (1=Aguardando, 2=Pago, 3=Atrasado)
- `tenant_id`: Filtrar por tenant

**Exemplo:**
```
GET /superadmin/pagamentos?status_pagamento_id=1&tenant_id=4
```

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
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

---

### 2. Resumo de Pagamentos

**Endpoint:** `GET /superadmin/pagamentos/resumo`

**Query Params:**
- `tenant_id`: ID do tenant

**Headers:**
```
Authorization: Bearer {token}
```

**Resposta de Sucesso (200):**
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

---

### 3. Pagamentos por Contrato

**Endpoint:** `GET /superadmin/contratos/{contrato_id}/pagamentos`

**Headers:**
```
Authorization: Bearer {token}
```

**Parâmetros de URL:**
- `contrato_id`: ID do contrato

**Resposta de Sucesso (200):**
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
    }
  ]
}
```

---

### 4. Confirmar Pagamento

**Endpoint:** `POST /superadmin/pagamentos/{pagamento_id}/confirmar`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "data_pagamento": "2026-01-05",
  "observacoes": "Pagamento confirmado via PIX"
}
```

**O que acontece:**
1. Pagamento muda para status "Pago"
2. data_pagamento é preenchida
3. Sistema verifica se há outros pagamentos pendentes/atrasados
4. Se não houver, contrato muda para status "Ativo"

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "message": "Pagamento confirmado com sucesso"
}
```

---

### 5. Criar Novo Pagamento

**Endpoint:** `POST /superadmin/contratos/{contrato_id}/pagamentos`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "valor": 250.00,
  "data_vencimento": "2026-04-05",
  "forma_pagamento": "pix",
  "observacoes": "Quarto pagamento do contrato"
}
```

---

### 6. Marcar Pagamentos Atrasados (Job)

**Endpoint:** `POST /superadmin/pagamentos/marcar-atrasados`

**Headers:**
```
Authorization: Bearer {token}
```

**O que acontece:**
1. Sistema busca pagamentos com status "Aguardando"
2. Verifica se data_vencimento < CURDATE()
3. Muda status para "Atrasado"
4. Bloqueia contratos com pagamentos atrasados

**Resposta de Sucesso (200):**
```json
{
  "type": "success",
  "message": "5 pagamentos marcados como atrasados. 3 contratos bloqueados."
}
```

---

### 7. Cancelar Pagamento

**Endpoint:** `DELETE /superadmin/pagamentos/{pagamento_id}`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Body:**
```json
{
  "observacoes": "Pagamento cancelado por alteração de plano"
}
```

---

## 🧪 Testes e Exemplos

### Exemplo 1: Fluxo Completo de Login

```bash
# 1. Login
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@exemplo.com",
    "senha": "password123"
  }'

# Resposta contém o token. Copie e use:
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

# 2. Obter dados do usuário
curl http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN"

# 3. Atualizar perfil
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nome": "João Silva Atualizado",
    "telefone": "(11) 98765-4321"
  }'
```

---

### Exemplo 2: Fluxo de Check-in

```bash
TOKEN="seu_token_aqui"

# 1. Listar dias disponíveis
curl http://localhost:8080/dias \
  -H "Authorization: Bearer $TOKEN"

# 2. Listar horários de um dia
curl http://localhost:8080/dias/1/horarios \
  -H "Authorization: Bearer $TOKEN"

# 3. Fazer check-in
curl -X POST http://localhost:8080/checkin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"horario_id": 1}'
```

---

### Exemplo 3: Consultar Turmas e Alunos

```bash
TOKEN="seu_token_aqui"

# 1. Listar todas as turmas
curl http://localhost:8080/turmas \
  -H "Authorization: Bearer $TOKEN"

# 2. Ver alunos de uma turma
curl http://localhost:8080/turmas/147/alunos \
  -H "Authorization: Bearer $TOKEN"
```

---

### Exemplo 4: Atualizar Foto de Perfil

```bash
TOKEN="seu_token_aqui"

# 1. Converter imagem para base64 (no seu sistema)
# base64 < /caminho/para/imagem.jpg > imagem_base64.txt

# 2. Usar na API
curl -X PUT http://localhost:8080/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "foto_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
  }'
```

---

### Exemplo 5: Gerenciar Pagamentos

```bash
TOKEN="seu_token_admin"

# 1. Listar pagamentos aguardando
curl "http://localhost:8080/superadmin/pagamentos?status_pagamento_id=1" \
  -H "Authorization: Bearer $TOKEN"

# 2. Confirmar um pagamento
curl -X POST http://localhost:8080/superadmin/pagamentos/1/confirmar \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "data_pagamento": "2026-01-05",
    "observacoes": "PIX confirmado"
  }'

# 3. Ver resumo de pagamentos
curl "http://localhost:8080/superadmin/pagamentos/resumo?tenant_id=4" \
  -H "Authorization: Bearer $TOKEN"
```

---

### Exemplo 6: Obter Contrato Ativo (Mobile)

```bash
TOKEN="seu_token_aqui"

# Obter informações do contrato/plano ativo da academia
curl http://localhost:8080/mobile/contratos \
  -H "Authorization: Bearer $TOKEN"

# Resposta contém:
# - Plano ativo (nome, valor, features, etc)
# - Status do contrato
# - Vigência e dias restantes
# - Lista de pagamentos
# - Informações da academia
```

---

### Exemplo 7: Fluxo Completo com Mobile

```bash
# 1. Login
curl -X POST http://localhost:8080/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "teste@exemplo.com",
    "senha": "password123"
  }'

# Obter token da resposta
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

# 2. Obter perfil completo com estatísticas
curl http://localhost:8080/mobile/perfil \
  -H "Authorization: Bearer $TOKEN"

# 3. Verificar contrato ativo
curl http://localhost:8080/mobile/contratos \
  -H "Authorization: Bearer $TOKEN"

# 4. Ver horários de hoje
curl http://localhost:8080/mobile/horarios \
  -H "Authorization: Bearer $TOKEN"

# 5. Fazer check-in
curl -X POST http://localhost:8080/mobile/checkin \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"horario_id": 1}'
```

---

## 📊 Fluxos Completos

### Fluxo 1: Novo Usuário até Check-in

1. **Registrar** - `POST /auth/register`
2. **Login** - `POST /auth/login` → recebe token
3. **Completar Perfil** - `PUT /me` (CPF, CEP, etc)
4. **Ver Dias** - `GET /dias`
5. **Ver Horários** - `GET /dias/{id}/horarios`
6. **Fazer Check-in** - `POST /checkin`
7. **Ver Estatísticas** - `GET /usuarios/{id}/estatisticas`

---

### Fluxo 2: Gestor Verificando Ocupação

1. **Login** - `POST /auth/login`
2. **Listar Turmas** - `GET /turmas` (vê ocupação por dia)
3. **Ver Alunos de Turma** - `GET /turmas/{id}/alunos` (chamada)
4. **Exportar Relatório** - (filtrar dados)

---

### Fluxo 3: Admin Gerenciando Pagamentos

1. **Login** - `POST /auth/login`
2. **Ver Pagamentos Pendentes** - `GET /superadmin/pagamentos?status_pagamento_id=1`
3. **Confirmar Pagamento** - `POST /superadmin/pagamentos/{id}/confirmar`
4. **Verificar Status Contrato** - Sistema ativa automaticamente
5. **Listar Pagamentos Atrasados** - `GET /superadmin/pagamentos?status_pagamento_id=3`

---

### Fluxo 4: Aluno Consultando Plano Ativo (Mobile)

1. **Login** - `POST /auth/login`
2. **Obter Perfil** - `GET /mobile/perfil`
3. **Verificar Contrato Ativo** - `GET /mobile/contratos` (mostra plano, vigência, pagamentos)
4. **Ver Horários** - `GET /mobile/horarios`
5. **Fazer Check-in** - `POST /mobile/checkin`
6. **Histórico** - `GET /mobile/checkins`

---

### Fluxo 5: Gestor Monitorando Saúde do Contrato (Mobile)

1. **Login** - `POST /auth/login`
2. **Obter Contrato Ativo** - `GET /mobile/contratos`
3. **Verificar:**
   - ✅ Se há dias restantes (`vigencia.dias_restantes`)
   - ✅ Status dos pagamentos (`pagamentos.lista`)
   - ✅ Features disponíveis (`plano.features`)
   - ✅ Limite de alunos (`plano.max_alunos`)

---

## ⚠️ Códigos de Erro Comuns

| Código | Significado |
|--------|-----------|
| 200 | Sucesso (GET, PUT) |
| 201 | Criado com sucesso (POST) |
| 400 | Requisição inválida (dados faltando) |
| 401 | Não autenticado (token faltando/inválido) |
| 404 | Recurso não encontrado |
| 422 | Validação falhou |
| 500 | Erro interno do servidor |

---

## 🔗 Headers Padrão

```
Content-Type: application/json
Authorization: Bearer {token}
X-Tenant-Slug: {tenant_slug} (opcional)
```

---

## 📝 Notas Importantes

- ✅ Todos os tokens JWT expiram após 24 horas
- ✅ Emails são únicos por tenant
- ✅ Fotos são limitadas a 5MB em base64
- ✅ CPF e CEP devem ser apenas números
- ✅ Senhas têm mínimo de 6 caracteres
- ✅ Check-ins não podem ser feitos mais de uma vez no mesmo horário
- ✅ Pagamentos atrasados bloqueiam automaticamente o contrato

---

**Último atualizado:** 10 de janeiro de 2026
