# 📱 API Mobile - Endpoints de Check-in

## Visão Geral

Os endpoints mobile retornam dados estruturados para construir uma interface com:
- **Barra de seleção de dias** (próximos 7 dias)
- **Lista de turmas** ordenadas por horário
- **Informações de modalidade, professor, horário**
- **Lotação (confirmados/total de vagas)**
- **Funcionalidade de check-in**

---

## 📍 Endpoint 1: Perfil Completo do Usuário

**GET** `/mobile/perfil`

Retorna o perfil completo do usuário logado com estatísticas de check-in.

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Response 200 Success
```json
{
    "success": true,
    "data": {
        "id": 1,
        "nome": "João Silva",
        "email": "joao@example.com",
        "email_global": "joao@example.com",
        "cpf": "123.456.789-00",
        "telefone": "(11) 98765-4321",
        "foto_base64": "data:image/jpeg;base64,...",
        "data_nascimento": "1990-05-15",
        "role_id": 1,
        "role_nome": "Aluno",
        "membro_desde": "2025-01-01T10:00:00",
        "tenant": {
            "id": 1,
            "nome": "Academia XYZ",
            "slug": "academia-xyz",
            "email": "contato@academia.com",
            "telefone": "(11) 3456-7890"
        },
        "plano": {
            "id": 5,
            "nome": "Plano Gold",
            "valor": 199.90,
            "duracao_dias": 30,
            "descricao": "Acesso a todas as modalidades",
            "data_inicio": "2026-01-01",
            "data_fim": "2026-01-31",
            "vinculo_status": "ativo"
        },
        "estatisticas": {
            "total_checkins": 24,
            "checkins_mes": 12,
            "sequencia_dias": 3,
            "ultimo_checkin": {
                "data": "2026-01-10",
                "hora": "07:30:00"
            }
        }
    }
}
```

### Uso Recomendado
- Carregar ao abrir o app e entrar em perfil
- Mostrar estatísticas na home do app
- Atualizar a cada 5 minutos ou quando o usuário abrir perfil

---

## 📍 Endpoint 2: Listar Tenants do Usuário

**GET** `/mobile/tenants`

Retorna a lista de academias/tenants disponíveis para o usuário logado.

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Response 200 Success
```json
{
    "success": true,
    "data": {
        "tenants": [
            {
                "id": 1,
                "nome": "Academia XYZ",
                "slug": "academia-xyz",
                "email": "contato@academia.com",
                "telefone": "(11) 3456-7890",
                "ativo": true
            },
            {
                "id": 2,
                "nome": "Academia 123",
                "slug": "academia-123",
                "email": "contato@academia123.com",
                "telefone": "(11) 9876-5432",
                "ativo": true
            }
        ],
        "total": 2
    }
}
```

### Uso Recomendado
- Mostrar ao usuário ao fazer login se tiver múltiplas academias
- Permitir troca de academia sem fazer logout

---

## 📍 Endpoint 3: Listar Planos do Usuário (via Matrículas)

**GET** `/mobile/planos`

Retorna a lista de **planos que o usuário tem matrículas ativas/pendentes** no tenant selecionado. Busca através da tabela de matrículas, não da tabela de planos disponíveis.

### Query Parameters
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `todas` | boolean | false | Se true, retorna todos os planos (inclusive com matrículas canceladas/finalizadas) |

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Exemplos de Requisição
```bash
# Apenas planos COM MATRÍCULAS ATIVAS/PENDENTES (padrão)
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/planos

# Retornar todos os planos (inclusive matrículas canceladas)
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/planos?todas=true
```

### Response 200 Success
```json
{
    "success": true,
    "data": {
        "planos": [
            {
                "id": 23,
                "tenant_id": 5,
                "modalidade": {
                    "id": 5,
                    "nome": "CrossFit",
                    "cor": "#10b981"
                },
                "nome": "1x por semana",
                "descricao": "",
                "valor": 110.00,
                "duracao_dias": 30,
                "checkins_semanais": 1,
                "ativo": true,
                "atual": true,
                "ultima_data_vencimento": "2026-02-06",
                "tem_matricula_ativa": true,
                "created_at": "2026-01-07T03:40:49",
                "updated_at": "2026-01-07T04:16:37"
            },
            {
                "id": 18,
                "tenant_id": 5,
                "modalidade": {
                    "id": 4,
                    "nome": "Natação",
                    "cor": "#3b82f6"
                },
                "nome": "2x por Semana",
                "descricao": "",
                "valor": 120.00,
                "duracao_dias": 30,
                "checkins_semanais": 2,
                "ativo": true,
                "atual": true,
                "ultima_data_vencimento": "2026-02-08",
                "tem_matricula_ativa": true,
                "created_at": "2026-01-07T02:30:16",
                "updated_at": "2026-01-07T02:33:31"
            }
        ],
        "total": 2,
        "apenas_ativos": true
    }
}
```

### Response 400 Bad Request (Sem tenant)
```json
{
    "success": false,
    "error": "Nenhum tenant selecionado"
}
```

### Uso Recomendado
- Mostrar na tela do usuário seus planos contratados
- Indicar data de vencimento de cada plano
- Mostrar quais planos estão com matrícula ativa
- Permitir renovação ou upgrade de planos

---

## 📍 Endpoint 4: Detalhes da Matrícula e Pagamentos

**GET** `/mobile/matriculas/{matriculaId}`

Retorna detalhes completos de uma matrícula com histórico de pagamentos, permitindo o usuário acompanhar status, vencimentos e formas de pagamento.

### URL Parameters
| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `matriculaId` | integer | ID da matrícula (obrigatório) |

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Exemplos de Requisição
```bash
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/matriculas/15
```

### Response 200 Success
```json
{
    "success": true,
    "data": {
        "matricula": {
            "id": 15,
            "usuario": "CAROLINA FERREIRA",
            "plano": {
                "nome": "3x por semana",
                "valor": 150.00,
                "duracao_dias": 30,
                "checkins_semanais": 3,
                "modalidade": {
                    "nome": "Natação",
                    "cor": "#3b82f6"
                }
            },
            "datas": {
                "matricula": "2026-01-09",
                "inicio": "2026-01-09",
                "vencimento": "2026-02-08"
            },
            "valor_total": 150.00,
            "status": "ativa",
            "motivo": "nova"
        },
        "pagamentos": [
            {
                "id": 25,
                "valor": 150.00,
                "data_vencimento": "2026-03-10",
                "data_pagamento": null,
                "status": "Aguardando",
                "forma_pagamento": null,
                "pendente": true
            },
            {
                "id": 24,
                "valor": 150.00,
                "data_vencimento": "2026-02-08",
                "data_pagamento": "2026-01-10",
                "status": "Pago",
                "forma_pagamento": "Pix",
                "pendente": false
            },
            {
                "id": 23,
                "valor": 150.00,
                "data_vencimento": "2026-01-09",
                "data_pagamento": "2026-01-09",
                "status": "Pago",
                "forma_pagamento": "Pix",
                "pendente": false
            }
        ],
        "resumo_financeiro": {
            "total_previsto": 450.00,
            "total_pago": 300.00,
            "total_pendente": 150.00,
            "quantidade_pagamentos": 3,
            "pagamentos_realizados": 2
        }
    }
}
```

### Response 400 Bad Request (Sem matrícula ID)
```json
{
    "success": false,
    "error": "ID da matrícula não informado"
}
```

### Response 404 Not Found
```json
{
    "success": false,
    "error": "Matrícula não encontrada"
}
```

### Uso Recomendado
- Mostrar na tela de detalhes da matrícula
- Exibir cronograma de pagamentos
- Indicar visualmente quais pagamentos estão pendentes
- Mostrar resumo financeiro (quanto já pagou e quanto falta)
- Permitir visualizar formas de pagamento realizadas

---

## 📍 Endpoint 5: Registrar Check-in

**POST** `/mobile/checkin`

Registra um check-in do usuário em um horário específico.

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
Content-Type: application/json
```

### Request Body
```json
{
    "horario_id": 5
}
```

**Nota:** Se não enviar `horario_id`, a API busca o horário mais próximo do momento atual do dia.

### Response 201 Created
```json
{
    "success": true,
    "message": "Check-in realizado com sucesso!",
    "data": {
        "checkin": {
            "data": "2026-01-10",
            "hora": "07:30:15"
        },
        "estatisticas": {
            "total_checkins": 25,
            "checkins_mes": 13,
            "sequencia_dias": 4,
            "ultimo_checkin": {
                "data": "2026-01-10",
                "hora": "07:30:15"
            }
        }
    }
}
```

### Response 400 Bad Request (Já fez check-in)
```json
{
    "success": false,
    "error": "Você já realizou check-in neste horário!",
    "ja_fez_checkin": true
}
```

### Response 400 Bad Request (Sem horário disponível)
```json
{
    "success": false,
    "error": "Não há horário disponível para check-in hoje"
}
```

### Uso Recomendado
- Mostrar um botão grande de "Fazer Check-in" na home
- Validar se já fez check-in antes de permitir novo
- Animar a confirmação com sucesso

---

## 📍 Endpoint 6: Histórico de Check-ins

**GET** `/mobile/checkins`

Retorna o histórico de check-ins do usuário com paginação.

### Query Parameters
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `limit` | integer | 30 | Quantidade por página (máximo 100) |
| `offset` | integer | 0 | Quantidade a pular (para paginação) |

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Exemplos de Requisição
```bash
# Últimos 30 check-ins
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/checkins

# Próximos 50 check-ins
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/checkins?limit=50

# Com paginação
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/checkins?limit=30&offset=30
```

### Response 200 Success
```json
{
    "success": true,
    "data": {
        "checkins": [
            {
                "id": 1,
                "data_checkin": "2026-01-10 07:30:15",
                "created_at": "2026-01-10 07:30:15",
                "data": "2026-01-10",
                "hora": "07:00:00"
            },
            {
                "id": 2,
                "data_checkin": "2026-01-09 08:15:00",
                "created_at": "2026-01-09 08:15:00",
                "data": "2026-01-09",
                "hora": "08:00:00"
            },
            {
                "id": 3,
                "data_checkin": "2026-01-08 06:45:30",
                "created_at": "2026-01-08 06:45:30",
                "data": "2026-01-08",
                "hora": "06:30:00"
            }
        ],
        "total": 24,
        "limit": 30,
        "offset": 0
    }
}
```

### Uso Recomendado
- Mostrar em uma tela de histórico/calendário
- Implementar infinite scroll com paginação
- Permitir filtrar por período

---

## 📍 Endpoint 7: Listar Horários de Hoje

**GET** `/mobile/horarios`

Retorna todas as turmas disponíveis para **hoje**, agrupadas por horário.

### Query Parameters
Nenhum (opcional para debug)

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Response 200 Success
```json
{
    "type": "success",
    "message": "Horários de hoje carregados",
    "data": {
        "data": "2026-01-10",
        "dia_semana": "Sábado",
        "horarios": [
            {
                "horario_id": 5,
                "horario_inicio": "07:00",
                "horario_fim": "08:00",
                "limite_alunos": 30,
                "confirmados": 18,
                "turmas": [
                    {
                        "turma_id": 42,
                        "turma_nome": "Turma A",
                        "professor": {
                            "id": 12,
                            "nome": "João Silva"
                        },
                        "modalidade": {
                            "id": 3,
                            "nome": "Pilates",
                            "cor": "#FF6B6B"
                        },
                        "confirmados": 18
                    }
                ]
            },
            {
                "horario_id": 6,
                "horario_inicio": "08:00",
                "horario_fim": "09:00",
                "limite_alunos": 25,
                "confirmados": 24,
                "turmas": [
                    {
                        "turma_id": 43,
                        "turma_nome": "Turma B",
                        "professor": {
                            "id": 13,
                            "nome": "Maria Santos"
                        },
                        "modalidade": {
                            "id": 2,
                            "nome": "Yoga",
                            "cor": "#4ECDC4"
                        },
                        "confirmados": 24
                    }
                ]
            }
        ]
    }
}
```

### Response 401 Unauthorized
```json
{
    "type": "error",
    "message": "Token inválido ou não fornecido"
}
```

### Uso Recomendado
- Carregar ao abrir o app
- Mostrar turmas do dia atual
- Atualizar a cada 30 segundos

---

## 📍 Endpoint 8: Listar Próximos Dias com Horários

**GET** `/mobile/horarios/proximos`

Retorna turmas disponíveis para os **próximos 7 dias** (ou N dias especificados), agrupadas por data.

### Query Parameters
| Parâmetro | Tipo | Default | Descrição |
|-----------|------|---------|-----------|
| `dias` | integer | 7 | Número de dias a retornar (1-30) |

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Exemplos de Requisição
```bash
# Próximos 7 dias (padrão)
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/horarios/proximos

# Próximos 14 dias
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/horarios/proximos?dias=14

# Próximos 30 dias
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/horarios/proximos?dias=30
```

### Response 200 Success
```json
{
    "type": "success",
    "message": "Próximos dias carregados",
    "data": {
        "dias": [
            {
                "data": "2026-01-10",
                "dia_semana": "Sábado",
                "ativo": true,
                "turmas_count": 3,
                "horarios": [
                    {
                        "horario_id": 5,
                        "horario_inicio": "07:00",
                        "horario_fim": "08:00",
                        "limite_alunos": 30,
                        "confirmados": 18,
                        "turmas": [
                            {
                                "turma_id": 42,
                                "turma_nome": "Turma A",
                                "professor": {
                                    "id": 12,
                                    "nome": "João Silva"
                                },
                                "modalidade": {
                                    "id": 3,
                                    "nome": "Pilates",
                                    "cor": "#FF6B6B"
                                },
                                "confirmados": 18
                            }
                        ]
                    }
                ]
            },
            {
                "data": "2026-01-11",
                "dia_semana": "Domingo",
                "ativo": true,
                "turmas_count": 2,
                "horarios": [
                    {
                        "horario_id": 1,
                        "horario_inicio": "06:00",
                        "horario_fim": "07:00",
                        "limite_alunos": 20,
                        "confirmados": 20,
                        "turmas": [
                            {
                                "turma_id": 10,
                                "turma_nome": "Turma Premium",
                                "professor": {
                                    "id": 5,
                                    "nome": "Carlos Costa"
                                },
                                "modalidade": {
                                    "id": 1,
                                    "nome": "CrossFit",
                                    "cor": "#FFD93D"
                                },
                                "confirmados": 20
                            }
                        ]
                    }
                ]
            }
        ]
    }
}
```

### Uso Recomendado
- Renderizar barra de carrossel com cada dia
- Ao selecionar um dia, usar **Endpoint 3** para detalhes completos
- Mostrar badge com quantidade de turmas/dia

---

## 📍 Endpoint 9: Detalhes de um Dia Específico

**GET** `/mobile/horarios/{diaId}`

Retorna **todas as turmas** de um dia específico com detalhes completos, ordenadas por horário.

### URL Parameters
| Parâmetro | Tipo | Descrição |
|-----------|------|-----------|
| `diaId` | integer | ID do dia (obrigatório) |

### Headers Obrigatórios
```
Authorization: Bearer {JWT_TOKEN}
```

### Exemplos de Requisição
```bash
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8080/mobile/horarios/150
```

### Response 200 Success
```json
{
    "type": "success",
    "message": "Detalhes do dia carregados",
    "data": {
        "dia": {
            "id": 150,
            "data": "2026-01-10",
            "dia_semana": "Sábado",
            "ativo": true
        },
        "horarios": [
            {
                "horario_id": 5,
                "horario_inicio": "07:00",
                "horario_fim": "08:00",
                "duracao_minutos": 60,
                "limite_alunos": 30,
                "confirmados": 18,
                "vagas_disponiveis": 12,
                "turmas": [
                    {
                        "turma_id": 42,
                        "turma_nome": "Turma A",
                        "professor": {
                            "id": 12,
                            "nome": "João Silva",
                            "email": "joao@example.com"
                        },
                        "modalidade": {
                            "id": 3,
                            "nome": "Pilates",
                            "cor": "#FF6B6B",
                            "descricao": "Aula de pilates para fortalecimento"
                        },
                        "confirmados": 18,
                        "vagas_disponiveis": 12,
                        "lotacao_percentual": 60
                    },
                    {
                        "turma_id": 44,
                        "turma_nome": "Turma C",
                        "professor": {
                            "id": 14,
                            "nome": "Pedro Oliveira",
                            "email": "pedro@example.com"
                        },
                        "modalidade": {
                            "id": 3,
                            "nome": "Pilates",
                            "cor": "#FF6B6B",
                            "descricao": "Aula de pilates para fortalecimento"
                        },
                        "confirmados": 0,
                        "vagas_disponiveis": 25,
                        "lotacao_percentual": 0
                    }
                ]
            },
            {
                "horario_id": 6,
                "horario_inicio": "08:00",
                "horario_fim": "09:00",
                "duracao_minutos": 60,
                "limite_alunos": 25,
                "confirmados": 24,
                "vagas_disponiveis": 1,
                "turmas": [
                    {
                        "turma_id": 43,
                        "turma_nome": "Turma B",
                        "professor": {
                            "id": 13,
                            "nome": "Maria Santos",
                            "email": "maria@example.com"
                        },
                        "modalidade": {
                            "id": 2,
                            "nome": "Yoga",
                            "cor": "#4ECDC4",
                            "descricao": "Aula relaxante de yoga"
                        },
                        "confirmados": 24,
                        "vagas_disponiveis": 1,
                        "lotacao_percentual": 96
                    }
                ]
            }
        ]
    }
}
```

### Response 404 Not Found
```json
{
    "type": "error",
    "message": "Dia não encontrado"
}
```

### Uso Recomendado
- Carregar quando usuário seleciona um dia no carrossel
- Mostrar lista ordenada por horário
- Usar cores da modalidade como badges
- Destacar vagas disponíveis

---

## 🚀 Fluxo de Uso Recomendado

### 1. App Abre
```javascript
GET /mobile/horarios/proximos?dias=7
// Retorna próximos 7 dias com contagem de turmas
```

### 2. Renderizar Barra de Dias
- Mostrar cada dia com data e quantidade de turmas
- Destacar dia atual
- Desabilitar dias desativados

### 3. Usuário Seleciona um Dia
```javascript
GET /mobile/horarios/{diaId}
// Retorna todas as turmas do dia, ordenadas por horário
```

### 4. Renderizar Lista de Turmas
Para cada horário (agrupado):
- Mostrar **horário** (07:00 - 08:00)
- Para cada turma naquele horário:
  - **Modalidade** (com cor)
  - **Professor**
  - **Confirmados / Vagas**
  - **Botão de Check-in**

### 5. Fazer Check-in
```javascript
POST /mobile/checkin
Content-Type: application/json
{
    "turma_id": 42
}
// Retorna sucesso ou erro (turma lotada, já fez check-in, etc)
```

---

## 💾 Estrutura de Dados

### Dia (dia)
```typescript
{
    id: number;
    data: string;           // "2026-01-10"
    dia_semana: string;     // "Sábado"
    ativo: boolean;
}
```

### Horário (horario)
```typescript
{
    horario_id: number;
    horario_inicio: string;  // "07:00"
    horario_fim: string;     // "08:00"
    duracao_minutos: number;
    limite_alunos: number;
    confirmados: number;
    vagas_disponiveis: number;
    turmas: Turma[];
}
```

### Turma (turma)
```typescript
{
    turma_id: number;
    turma_nome: string;
    professor: {
        id: number;
        nome: string;
        email?: string;
    };
    modalidade: {
        id: number;
        nome: string;
        cor: string;        // "#FF6B6B"
        descricao?: string;
    };
    confirmados: number;
    vagas_disponiveis: number;
    lotacao_percentual: number;
}
```

---

## ⚠️ Códigos de Erro

| Código | Tipo | Descrição |
|--------|------|-----------|
| 200 | Success | Requisição bem-sucedida |
| 400 | Bad Request | Parâmetro inválido |
| 401 | Unauthorized | Token ausente ou inválido |
| 404 | Not Found | Recurso não encontrado |
| 500 | Server Error | Erro no servidor |

---

## 🔧 Implementação Backend (PHP)

Todos os **10 endpoints** foram implementados em [app/Controllers/MobileController.php](app/Controllers/MobileController.php):

```php
public function perfil(Request $request, Response $response): Response
public function tenants(Request $request, Response $response): Response
public function planosDoUsuario(Request $request, Response $response): Response
public function detalheMatricula(Request $request, Response $response, array $args): Response
public function registrarCheckin(Request $request, Response $response): Response
public function historicoCheckins(Request $request, Response $response): Response
public function horariosHoje(Request $request, Response $response): Response
public function horariosProximos(Request $request, Response $response): Response
public function horariosPorDia(Request $request, Response $response, array $args): Response
```

E registrados em [routes/api.php](routes/api.php):
```php
$app->group('/mobile', function ($group) {
    $group->get('/perfil', [MobileController::class, 'perfil']);
    $group->get('/tenants', [MobileController::class, 'tenants']);
    $group->get('/planos', [MobileController::class, 'planosDoUsuario']);
    $group->get('/matriculas/{matriculaId}', [MobileController::class, 'detalheMatricula']);
    $group->post('/checkin', [MobileController::class, 'registrarCheckin']);
    $group->get('/checkins', [MobileController::class, 'historicoCheckins']);
    $group->get('/horarios', [MobileController::class, 'horariosHoje']);
    $group->get('/horarios/proximos', [MobileController::class, 'horariosProximos']);
    $group->get('/horarios/{diaId}', [MobileController::class, 'horariosPorDia']);
})->add(AuthMiddleware::class);
```

---

## 📊 Exemplo de Implementação Frontend

```javascript
// 1. Carregar próximos dias ao abrir app
async function carregarProximosDias() {
    const response = await fetch('/mobile/horarios/proximos?dias=7', {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    
    // data.data.dias contém array com próximos 7 dias
    renderizarBarraDias(data.data.dias);
}

// 2. Ao clicar em um dia, carregar detalhes
async function selecionarDia(diaId) {
    const response = await fetch(`/mobile/horarios/${diaId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    });
    const data = await response.json();
    
    // data.data.horarios contém array de horários ordenados
    renderizarTurmasPorHorario(data.data.horarios);
}

// 3. Ao clicar em Check-in
async function fazerCheckin(turmaId) {
    const response = await fetch('/mobile/checkin', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ turma_id: turmaId })
    });
    const data = await response.json();
    
    if (data.success) {
        alert('✅ Check-in realizado!');
        selecionarDia(diaIdAtual); // Recarregar
    }
}
```

---

## ✅ Validação

- ✅ Endpoint GET /mobile/perfil - Implementado
- ✅ Endpoint GET /mobile/tenants - Implementado
- ✅ Endpoint GET /mobile/planos - Implementado (planosDoUsuario)
- ✅ Endpoint GET /mobile/matriculas/{matriculaId} - Implementado (detalheMatricula)
- ✅ Endpoint POST /mobile/checkin - Implementado
- ✅ Endpoint GET /mobile/checkins - Implementado
- ✅ Endpoint GET /mobile/horarios - Implementado
- ✅ Endpoint GET /mobile/horarios/proximos - Implementado
- ✅ Endpoint GET /mobile/horarios/{diaId} - Implementado
- ✅ Suporta query parameter `?dias=N`
- ✅ Suporta query parameter `?todas=true`
- ✅ Suporta paginação com `?limit=X&offset=Y`
- ✅ Retorna dados ordenados por horário
- ✅ Inclui modalidade, professor, lotação
- ✅ Inclui estatísticas de check-in
- ✅ Filtra por tenant_id (multi-tenancy)
- ✅ Valida JWT token
- ✅ Suporta múltiplos tenants por usuário
- ✅ Retorna informações de planos com matrículas do usuário
- ✅ Retorna detalhes da matrícula com histórico de pagamentos
- ✅ Calcula resumo financeiro (pago/pendente)
