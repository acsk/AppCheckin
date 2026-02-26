# AppCheckin API — Referência para Frontend

> **Base URL:** `https://<dominio>/`  
> **Autenticação:** Bearer Token (JWT) via header `Authorization: Bearer <token>`  
> **Tenant:** Header `X-Tenant-Id: <id>` (resolvido automaticamente do token na maioria dos casos)  
> **Content-Type:** `application/json` (exceto upload de foto)

---

## Índice

1. [Autenticação](#1-autenticação)
2. [Mobile — Perfil](#2-mobile--perfil)
3. [Mobile — Planos e Compra](#3-mobile--planos-e-compra)
4. [Mobile — Assinaturas](#4-mobile--assinaturas)
5. [Mobile — Pagamentos](#5-mobile--pagamentos)
6. [Mobile — Pacotes (Plano Família)](#6-mobile--pacotes-plano-família)
7. [Mobile — Check-in](#7-mobile--check-in)
8. [Mobile — Turmas e Horários](#8-mobile--turmas-e-horários)
9. [Mobile — WOD (Treino do Dia)](#9-mobile--wod-treino-do-dia)
10. [Mobile — Ranking](#10-mobile--ranking)
11. [Admin — Dashboard](#11-admin--dashboard)
12. [Admin — Alunos](#12-admin--alunos)
13. [Admin — Matrículas](#13-admin--matrículas)
14. [Admin — Pagamentos de Plano](#14-admin--pagamentos-de-plano)
15. [Admin — Contas a Receber](#15-admin--contas-a-receber)
16. [Admin — Planos](#16-admin--planos)
17. [Admin — Ciclos de Plano](#17-admin--ciclos-de-plano)
18. [Admin — Modalidades](#18-admin--modalidades)
19. [Admin — Turmas](#19-admin--turmas)
20. [Admin — Professores](#20-admin--professores)
21. [Admin — Pacotes](#21-admin--pacotes)
22. [Admin — Assinaturas](#22-admin--assinaturas)
23. [Admin — Check-ins](#23-admin--check-ins)
24. [Admin — WODs](#24-admin--wods)
25. [Admin — Presença](#25-admin--presença)
26. [Admin — Relatórios](#26-admin--relatórios)
27. [Admin — Formas de Pagamento](#27-admin--formas-de-pagamento)
28. [Admin — Credenciais de Pagamento](#28-admin--credenciais-de-pagamento)
29. [Admin — Dias e Horários](#29-admin--dias-e-horários)
30. [Admin — Usuários (Tenant)](#30-admin--usuários-tenant)
31. [Professor](#31-professor)
32. [Rotas Públicas](#32-rotas-públicas)
33. [Códigos de Erro](#33-códigos-de-erro)

---

## 1. Autenticação

### `POST /auth/login`
Login do usuário. Se tiver múltiplos tenants, retorna `requires_tenant_selection: true` e `token: null`.

**Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `email` | string | ✅ | Email do usuário |
| `senha` | string | ✅ | Senha |

**Resposta 200:**
```json
{
  "message": "Login realizado com sucesso",
  "token": "eyJ...",
  "user": {
    "id": 1,
    "nome": "João",
    "email": "joao@email.com",
    "email_global": "joao@email.com",
    "foto_base64": null,
    "papel_id": 1
  },
  "tenants": [...],
  "requires_tenant_selection": false
}
```

---

### `POST /auth/register-mobile`
Cadastro público de aluno via mobile. Senha = CPF.

**Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `nome` | string | ✅ | Nome completo |
| `email` | string | ✅ | Email |
| `cpf` | string | ✅ | CPF (11 dígitos) |
| `data_nascimento` | string | ✅ | Data `YYYY-MM-DD` |
| `tenant_id` | integer | ❌ | ID da academia |
| `telefone` | string | ❌ | Telefone |
| `whatsapp` | string | ❌ | WhatsApp |
| `cep` | string | ❌ | CEP |
| `logradouro` | string | ❌ | Endereço |
| `numero` | string | ❌ | Número |
| `complemento` | string | ❌ | Complemento |
| `bairro` | string | ❌ | Bairro |
| `cidade` | string | ❌ | Cidade |
| `estado` | string | ❌ | Estado (UF) |
| `recaptcha_token` | string | ❌ | Token reCAPTCHA v3 |

**Resposta 201:**
```json
{
  "message": "Cadastro realizado com sucesso",
  "token": "eyJ...",
  "user": { "id": 1, "nome": "JOÃO", "email": "joao@email.com", "cpf": "12345678901", "data_nascimento": "1990-01-15" }
}
```

---

### `POST /auth/select-tenant` 🔒
Selecionar tenant após login (quando múltiplos tenants).

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `tenant_id` | integer | ✅ |

**Resposta 200:**
```json
{
  "message": "Tenant selecionado",
  "token": "eyJ... (novo token com tenant_id)",
  "user": { "id": 1, "nome": "João", "papel_id": 1 },
  "tenant": { "id": 2, "nome": "Box CrossFit" }
}
```

---

### `POST /auth/select-tenant-public`
Selecionar tenant sem JWT (durante fluxo de login multi-tenant).

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `user_id` | integer | ✅ |
| `email` | string | ✅ |
| `tenant_id` | integer | ✅ |

---

### `GET /auth/tenants` 🔒
Lista tenants/academias do usuário autenticado.

**Resposta 200:**
```json
{
  "tenants": [{ "id": 2, "nome": "Box CrossFit", "slug": "box-crossfit" }],
  "requires_tenant_selection": true,
  "current_tenant_id": null
}
```

---

### `POST /auth/logout` 🔒
Confirma logout (stateless — cliente remove o token).

---

### `POST /auth/password-recovery/request`
Solicitar recuperação de senha.

**Body:** `{ "email": "joao@email.com" }`

---

### `POST /auth/password-recovery/validate-token`
Validar token de recuperação.

**Body:** `{ "token": "abc123" }`

---

### `POST /auth/password-recovery/reset`
Resetar senha.

**Body:**
```json
{ "token": "abc123", "nova_senha": "novaSenha123", "confirmacao_senha": "novaSenha123" }
```

---

## 2. Mobile — Perfil

> Prefixo: `/mobile` — Todas as rotas requerem 🔒 Auth

### `GET /mobile/perfil`
Perfil completo com estatísticas, plano ativo, ranking e tenants.

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "id": 1, "aluno_id": 5, "nome": "João", "email": "joao@email.com",
    "cpf": "12345678901", "telefone": "(11) 99999-0000",
    "foto_caminho": "/uploads/fotos/abc.jpg",
    "papel_id": 1, "papel_nome": "Aluno", "membro_desde": "2024-01-15",
    "tenants": [{ "id": 2, "nome": "Box CrossFit" }],
    "plano": { "id": 3, "nome": "Mensal", "valor": 150.00 },
    "estatisticas": {
      "total_checkins": 45, "checkins_mes": 12,
      "sequencia_dias": 3, "ultimo_checkin": "2025-06-20"
    },
    "ranking_modalidades": [{ "modalidade": "CrossFit", "posicao": 5, "total": 12 }]
  }
}
```

---

### `POST /mobile/perfil/foto`
Upload de foto de perfil (multipart/form-data).

**Body:** Form-data com campo `foto` (JPG/PNG/GIF/WebP, máx 5MB)

**Resposta 200:**
```json
{
  "success": true, "message": "Foto atualizada",
  "data": { "caminho_url": "/uploads/fotos/abc.jpg" }
}
```

---

### `GET /mobile/perfil/foto`
Retorna imagem binária da foto do perfil. Content-Type: `image/*`.

---

## 3. Mobile — Planos e Compra

### `GET /mobile/planos`
Matrículas/planos que o usuário contratou.

**Query:** `todas=true` para incluir canceladas/finalizadas.

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "matriculas": [{
      "matricula_id": 10, "valor": 150.00, "status": "ativa",
      "plano": { "id": 3, "nome": "Mensal", "valor": 150, "checkins_semanais": 5, "modalidade": "CrossFit" },
      "datas": { "matricula": "2025-01-15", "inicio": "2025-01-15", "vencimento": "2025-07-15" }
    }],
    "total": 1
  }
}
```

---

### `GET /mobile/planos-disponiveis`
Planos disponíveis para contratação com ciclos de pagamento.

**Query:** `modalidade_id` (int, opcional)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "planos": [{
      "id": 3, "nome": "CrossFit Mensal", "valor": 150.00, "valor_formatado": "R$ 150,00",
      "duracao_dias": 30, "duracao_texto": "30 dias", "checkins_semanais": 5,
      "modalidade": { "id": 1, "nome": "CrossFit" },
      "is_plano_atual": false,
      "ciclos": [{
        "id": 1, "nome": "Mensal", "codigo": "mensal", "meses": 1,
        "valor": 150.00, "valor_mensal": 150.00,
        "desconto_percentual": 0, "permite_recorrencia": true,
        "pix_disponivel": true,
        "metodos_pagamento": ["checkout", "pix"]
      }, {
        "id": 2, "nome": "Trimestral", "codigo": "trimestral", "meses": 3,
        "valor": 382.50, "valor_mensal": 127.50,
        "desconto_percentual": 15, "economia": "R$ 67,50"
      }]
    }],
    "total": 2, "plano_atual_id": null
  }
}
```

---

### `GET /mobile/planos/{planoId}`
Detalhes de um plano específico.

**Resposta 200:** Plano completo com `ciclos[]` e `matricula_ativa` (se houver).

---

### `POST /mobile/comprar-plano`
Comprar plano — cria matrícula + gera link de pagamento (MercadoPago ou PIX).

**Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `plano_id` | integer | ✅ | ID do plano |
| `dia_vencimento` | integer | ❌ | Dia do mês (1-31), padrão 5 |
| `metodo_pagamento` | string | ❌ | `"pix"` ou `"checkout"`, padrão `"checkout"` |
| `plano_ciclo_id` | integer | ❌ | ID do ciclo (mensal, trimestral...) |

**Resposta 201:**
```json
{
  "success": true, "message": "Matrícula criada",
  "data": {
    "matricula_id": 10, "plano_id": 3, "plano_nome": "CrossFit Mensal",
    "valor": 150.00, "valor_formatado": "R$ 150,00",
    "status": "pendente", "data_inicio": "2025-06-20", "data_vencimento": "2025-07-20",
    "payment_url": "https://www.mercadopago.com.br/checkout/...",
    "preference_id": "abc123",
    "tipo_pagamento": "checkout", "metodo_pagamento": "checkout",
    "tipo_cobranca": "avulso", "recorrente": false,
    "pix": null
  }
}
```

Se `metodo_pagamento = "pix"`:
```json
{
  "data": {
    "pix": {
      "payment_id": 12345, "status": "pending",
      "qr_code": "00020126...", "qr_code_base64": "data:image/png;base64,...",
      "ticket_url": "https://...", "expires_at": "2025-06-21T00:00:00"
    }
  }
}
```

---

## 4. Mobile — Assinaturas

### `GET /mobile/assinaturas`
Listar assinaturas do usuário (recorrentes e avulsas) + pacotes como pagante.

**Resposta 200:**
```json
{
  "success": true,
  "assinaturas": [{
    "id": 5, "status": "ativa", "valor": 150.00,
    "tipo_cobranca": "recorrente", "ciclo": "mensal",
    "plano": { "id": 3, "nome": "CrossFit Mensal" },
    "pode_pagar": false, "payment_url": null
  }],
  "total": 1,
  "pacotes": [{
    "contrato_id": 2, "pacote_nome": "Família 3",
    "beneficiarios": [{ "aluno_id": 5, "nome": "Filho 1" }]
  }]
}
```

---

### `GET /mobile/assinaturas/aprovadas-hoje`
Polling pós-pagamento PIX — verifica se pagamento foi aprovado.

**Query:** `matricula_id` (int, ✅ obrigatório)

**Resposta 200:**
```json
{
  "success": true, "approved": true,
  "data": { "assinatura_id": 5, "matricula_id": 10, "status_gateway": "approved" }
}
```

---

### `POST /mobile/assinatura/criar`
Criar assinatura recorrente (cartão de crédito via MercadoPago).

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `plano_ciclo_id` | integer | ✅ |
| `card_token` | string | ✅ |
| `back_url` | string | ❌ |

---

### `POST /mobile/assinatura/{id}/cancelar`
Cancelar assinatura (banco + MercadoPago).

**Body:** `{ "motivo": "Motivo opcional" }`

---

## 5. Mobile — Pagamentos

### `POST /mobile/verificar-pagamento`
Verifica status do pagamento e ativa matrícula se aprovado.

**Body:** `{ "matricula_id": 10 }`

**Resposta 200:**
```json
{ "success": true, "message": "Matrícula ativada", "data": { "matricula_id": 10, "status": "ativa" } }
```

---

### `POST /mobile/pagamento/pix`
Gerar QR Code PIX para matrícula pendente. Requer CPF no perfil.

**Body:** `{ "matricula_id": 10 }`

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "matricula_id": 10, "valor": 150.00,
    "pix": {
      "payment_id": 12345, "status": "pending",
      "qr_code": "00020126...", "qr_code_base64": "data:image/png;base64,...",
      "ticket_url": "https://...", "expires_at": "2025-06-21T00:00:00"
    }
  }
}
```

---

### `GET /mobile/pagamento/reabrir/{matriculaId}`
Recuperar dados de pagamento pendente para retomar.

**Resposta 200:** Mesma estrutura de `POST /mobile/comprar-plano` com dados existentes.

---

### `GET /mobile/matriculas/{matriculaId}`
Detalhes da matrícula com histórico de pagamentos.

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "matricula": {
      "id": 10, "plano": { "nome": "CrossFit", "valor": 150.00 },
      "datas": { "inicio": "2025-01-15", "vencimento": "2025-07-15" },
      "status": "ativa"
    },
    "pagamentos": [{
      "id": 1, "valor": 150.00, "data_vencimento": "2025-02-15",
      "data_pagamento": "2025-02-14", "status": "pago", "pendente": false
    }],
    "resumo_financeiro": {
      "total_previsto": 900.00, "total_pago": 750.00,
      "total_pendente": 150.00, "quantidade_pagamentos": 6, "pagamentos_realizados": 5
    }
  }
}
```

---

### `POST /mobile/diaria/{matriculaId}/cancelar`
Cancelar compra de diária avulsa (se não houver check-in ou presença confirmada).

---

## 6. Mobile — Pacotes (Plano Família)

### `GET /mobile/pacotes/pendentes`
Lista pacotes com pagamento pendente do usuário pagante.

**Resposta 200:**
```json
{
  "success": true,
  "pacotes": [{
    "contrato_id": 2, "status": "pendente",
    "valor_total": 300.00, "pacote_nome": "Família 3",
    "payment_url": "https://...", "payment_preference_id": "abc123"
  }],
  "total": 1
}
```

---

### `POST /mobile/pacotes/contratos/{contratoId}/pagar`
Gerar pagamento para pacote pendente.

**Query:** `force_new=true` (opcional, força nova preferência)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "contrato_id": 2, "assinatura_id": 5,
    "payment_url": "https://...", "preference_id": "abc123",
    "valor_total": 300.00
  }
}
```

---

## 7. Mobile — Check-in

### `POST /mobile/checkin`
Registrar check-in do aluno em uma turma.

**Body:** `{ "turma_id": 15 }`

**Resposta 201:**
```json
{
  "success": true, "message": "Check-in realizado!",
  "data": {
    "checkin_id": 100,
    "usuario": { "id": 1, "nome": "João", "foto_caminho": "/uploads/fotos/abc.jpg" },
    "turma": { "id": 15, "nome": "CrossFit 07h", "professor": "Prof. Carlos", "modalidade": "CrossFit" },
    "data_checkin": "2025-06-20 07:05:00",
    "vagas_atualizadas": { "ocupadas": 12, "disponiveis": 3, "limite": 15 }
  }
}
```

> **Validações:** matrícula ativa, dentro da tolerância de horário, vagas disponíveis, limite semanal/mensal não atingido.

---

### `DELETE /mobile/checkin/{checkinId}/desfazer`
Cancelar check-in (só se a aula ainda não começou).

---

### `GET /mobile/checkins`
Histórico de check-ins com paginação.

**Query:** `limit` (int, máx 100, padrão 30), `offset` (int, padrão 0)

---

### `GET /mobile/checkins/por-modalidade`
Check-ins por modalidade na semana, com calendário semanal.

**Query:** `offset` (int, semanas — 0=atual), `data_referencia` (YYYY-MM-DD)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "semana_inicio": "2025-06-16", "semana_fim": "2025-06-22",
    "dias": [{ "data": "2025-06-20", "modalidade": { "id": 1, "nome": "CrossFit", "cor": "#FF5722", "icone": "💪" } }],
    "modalidades": [{ "id": 1, "nome": "CrossFit", "cor": "#FF5722", "total": 3 }]
  }
}
```

---

### `POST /mobile/checkin/manual` 🔒 Professor
Check-in manual de aluno (professor/admin).

**Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `turma_id` | integer | ✅ | — |
| `aluno_id` | integer | ⚠️ | Um dos dois |
| `usuario_id` | integer | ⚠️ | Um dos dois |

---

### `DELETE /mobile/checkin/manual/{checkinId}/desfazer` 🔒 Professor

---

### `GET /mobile/alunos/buscar` 🔒 Professor
Buscar alunos para check-in manual.

**Query:** `q` (busca geral), `nome`, `cpf`, `email`, `limit` (máx 50), `offset`

---

## 8. Mobile — Turmas e Horários

### `GET /mobile/turmas`
Listar turmas ativas do tenant.

---

### `GET /mobile/turma/{turmaId}/participantes`
Alunos que fizeram check-in na turma.

---

### `GET /mobile/turma/{turmaId}/detalhes`
Detalhes completos com alunos, check-ins recentes e estatísticas.

---

### `GET /mobile/horarios-disponiveis`
Turmas/horários do dia com status de check-in (aberto/fechado).

**Query:** `data` (YYYY-MM-DD, padrão hoje)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "dia": { "id": 50, "data": "2025-06-20", "ativo": true },
    "turmas": [{
      "id": 15, "nome": "CrossFit 07h",
      "professor": { "id": 1, "nome": "Prof. Carlos" },
      "modalidade": { "id": 1, "nome": "CrossFit", "icone": "💪", "cor": "#FF5722" },
      "horario": { "inicio": "07:00:00", "fim": "08:00:00" },
      "checkin": {
        "disponivel": true, "ja_abriu": true, "ja_fechou": false,
        "abertura": "2025-06-20 06:52:00", "fechamento": "2025-06-20 07:10:00",
        "tolerancia_antes_minutos": 8, "tolerancia_depois_minutos": 10
      },
      "limite_alunos": 15, "alunos_inscritos": 10, "vagas_disponiveis": 5
    }],
    "total": 5
  }
}
```

---

### `POST /mobile/turma/{turmaId}/confirmar-presenca` 🔒 Professor
Confirmar presença/falta dos alunos.

**Body:**
```json
{
  "presencas": { "100": true, "101": false, "102": true },
  "remover_faltantes": true
}
```

---

## 9. Mobile — WOD (Treino do Dia)

### `GET /mobile/wod/hoje`
WOD do dia para uma modalidade.

**Query:** `data` (YYYY-MM-DD), `modalidade_id` (int)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "id": 5, "titulo": "FRAN", "descricao": "21-15-9",
    "data": "2025-06-20", "status": "published",
    "blocos": [{
      "id": 1, "ordem": 1, "tipo": "metcon",
      "titulo": "METCON", "conteudo": "21-15-9\nThrusters (43/30kg)\nPull-ups",
      "tempo_cap": 10
    }],
    "variacoes": [
      { "id": 1, "nome": "RX", "descricao": "43/30kg" },
      { "id": 2, "nome": "Scaled", "descricao": "33/20kg" }
    ]
  }
}
```

---

### `GET /mobile/wods/hoje`
Todos os WODs publicados do dia (de todas as modalidades).

**Query:** `data` (YYYY-MM-DD)

---

## 10. Mobile — Ranking

### `GET /mobile/ranking/mensal`
Ranking de check-ins do mês.

**Query:** `modalidade_id` (int, opcional)

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "periodo": "Junho/2025", "mes": 6, "ano": 2025,
    "ranking": [
      { "posicao": 1, "aluno": { "id": 5, "nome": "Maria", "foto_caminho": null }, "total_checkins": 22 },
      { "posicao": 2, "aluno": { "id": 1, "nome": "João", "foto_caminho": "/uploads/fotos/abc.jpg" }, "total_checkins": 18 }
    ]
  }
}
```

---

## 11. Admin — Dashboard

> Prefixo: `/admin` — Requer 🔒 Auth + Admin

### `GET /admin/dashboard`
Estatísticas gerais.

**Resposta 200:**
```json
{
  "total_alunos": 150, "alunos_ativos": 120, "alunos_inativos": 30,
  "novos_alunos_mes": 8, "total_checkins_hoje": 45, "total_checkins_mes": 890,
  "planos_vencendo": 12, "receita_mensal": 18000.00,
  "contas_pendentes_qtd": 25, "contas_pendentes_valor": 3750.00,
  "contas_vencidas_qtd": 5, "contas_vencidas_valor": 750.00
}
```

---

### `GET /admin/dashboard/cards`
Cards do dashboard.

**Resposta 200:**
```json
{
  "success": true,
  "data": {
    "total_alunos": { "total": 150, "ativos": 120, "inativos": 30 },
    "receita_mensal": { "valor": 18000.00, "valor_formatado": "R$ 18.000,00", "contas_pendentes": 25 },
    "checkins_hoje": { "hoje": 45, "no_mes": 890 },
    "planos_vencendo": { "vencendo": 12, "novos_este_mes": 8 }
  }
}
```

---

## 12. Admin — Alunos

### `GET /admin/alunos`
Listar alunos com paginação e busca.

**Query:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `apenas_ativos` | string | `"true"` / `"false"` |
| `busca` | string | Busca por nome ou email |
| `pagina` | integer | Página (default: 1) |
| `por_pagina` | integer | Itens/página (default: 50) |

**Resposta 200:**
```json
{ "alunos": [...], "total": 150, "pagina": 1, "por_pagina": 50, "total_paginas": 3 }
```

---

### `GET /admin/alunos/basico`
Lista simplificada (para selects/autocomplete).

**Resposta:** `{ "alunos": [{ "id": 5, "nome": "João", "email": "...", "usuario_id": 1 }], "total": 120 }`

---

### `GET /admin/alunos/buscar-cpf/{cpf}`
Busca global por CPF (para reutilizar cadastro entre academias).

**Resposta 200:**
```json
{
  "success": true, "found": true,
  "aluno": { "id": 5, "usuario_id": 1, "nome": "João", "email": "...", "cpf": "12345678901" },
  "tenants": [{ "id": 3, "nome": "Outra Academia" }],
  "ja_associado": false, "pode_associar": true
}
```

---

### `POST /admin/alunos/associar`
Associar aluno existente ao tenant.

**Body:** `{ "aluno_id": 5 }`

---

### `GET /admin/alunos/{id}`
Buscar aluno com dados enriquecidos.

---

### `GET /admin/alunos/{id}/historico-planos`
Histórico de matrículas/planos.

---

### `GET /admin/alunos/{id}/delete-preview`
Preview de impacto do delete.

---

### `POST /admin/alunos`
Criar aluno.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `nome` | string | ✅ |
| `email` | string | ✅ |
| `cpf` | string | ✅ (11 dígitos) |
| `data_nascimento` | string | ✅ (YYYY-MM-DD) |
| `senha` | string | ✅ (mín 6 chars) |
| `telefone` | string | ❌ |

---

### `PUT /admin/alunos/{id}`
Atualizar aluno. Todos os campos são opcionais.

---

### `DELETE /admin/alunos/{id}`
Soft delete (desativa aluno).

---

### `DELETE /admin/alunos/{id}/hard`
Hard delete (exclusão permanente e irreversível).

---

## 13. Admin — Matrículas

### `POST /admin/matriculas`
Criar matrícula (individual ou via pacote).

**Body (matrícula individual):**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `usuario_id` ou `aluno_id` | integer | ✅ | ID do aluno |
| `plano_id` | integer | ✅* | *Obrigatório se não for pacote |
| `plano_ciclo_id` | integer | ❌ | Ciclo (define valor/duração) |
| `dia_vencimento` | integer | ✅ | Dia do mês (1-31) |
| `valor` | float | ❌ | Valor (default: valor do ciclo/plano) |
| `data_inicio` | date | ❌ | Default: hoje |
| `observacoes` | string | ❌ | — |
| `motivo` | string | ❌ | nova, renovacao, troca_plano |

**Body (matrícula via pacote):**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `pacote_id` | integer | ✅ | ID do pacote |
| `usuario_id` ou `aluno_id` | integer | ✅ | Pagante principal |
| `dependentes` | int[] | ❌ | Array de aluno_ids beneficiários |
| `dia_vencimento` | integer | ✅ | Dia do mês |

**Resposta 201 (individual):**
```json
{
  "message": "Matrícula criada com sucesso",
  "matricula": { "id": 10, "usuario_id": 1, "plano_id": 3, "status": "pendente" },
  "pagamentos": [{ "id": 1, "valor": 150.00, "data_vencimento": "2025-07-20" }],
  "total_pagamentos": 1
}
```

**Resposta 201 (pacote):**
```json
{
  "message": "Matrícula de pacote criada com sucesso",
  "pacote_contrato_id": 5,
  "matriculas": [
    { "aluno_id": 5, "matricula_id": 10, "tipo": "pagante", "valor_rateado": 100.00 },
    { "aluno_id": 8, "matricula_id": 11, "tipo": "dependente", "valor_rateado": 100.00 }
  ]
}
```

---

### `GET /admin/matriculas`
Listar matrículas.

**Query:** `aluno_id`, `status` (ativa/pendente/vencida/cancelada/finalizada), `incluir_inativos`, `pagina`, `por_pagina`

---

### `GET /admin/matriculas/{id}`
Buscar matrícula com dados completos (aluno, plano, ciclo, pagamentos).

---

### `GET /admin/matriculas/{id}/pagamentos`
Listar pagamentos vinculados.

---

### `GET /admin/matriculas/{id}/delete-preview`
Preview de impacto do delete.

---

### `DELETE /admin/matriculas/{id}`
Hard delete de matrícula + pagamentos + assinaturas vinculadas.

---

### `POST /admin/matriculas/{id}/cancelar`
Cancelar matrícula (soft).

**Body:** `{ "motivo_cancelamento": "Pedido do aluno" }`

---

### `POST /admin/matriculas/contas/{id}/baixa`
Dar baixa (marcar como pago) em pagamento. Ativa matrícula e gera próxima parcela.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `data_pagamento` | date | ❌ (default: hoje) |
| `forma_pagamento_id` | integer | ❌ |
| `observacoes` | string | ❌ |

**Resposta 200:**
```json
{
  "message": "Baixa realizada",
  "pagamento": { "id": 1, "status": "pago" },
  "proxima_parcela": { "id": 2, "data_vencimento": "2025-08-20", "valor": 150.00, "status": "pendente" }
}
```

---

### `PUT /admin/matriculas/{id}/proxima-data-vencimento`
Atualizar data de vencimento.

**Body:** `{ "proxima_data_vencimento": "2025-08-15" }`

---

### `GET /admin/matriculas/vencimentos/hoje`
Matrículas com vencimento hoje.

---

### `GET /admin/matriculas/vencimentos/proximos`
Matrículas com vencimento nos próximos N dias.

**Query:** `dias` (int, padrão 7)

---

## 14. Admin — Pagamentos de Plano

### `GET /admin/pagamentos-plano`
Listar pagamentos.

**Query:** `status_pagamento_id`, `usuario_id`, `data_inicio`, `data_fim`

---

### `GET /admin/pagamentos-plano/resumo`
Resumo financeiro.

**Query:** `data_inicio`, `data_fim`

---

### `GET /admin/pagamentos-plano/{id}`
Buscar pagamento por ID.

---

### `GET /admin/matriculas/{id}/pagamentos-plano`
Pagamentos de uma matrícula.

---

### `GET /admin/usuarios/{id}/pagamentos-plano`
Pagamentos de um usuário.

---

### `POST /admin/matriculas/{id}/pagamentos-plano`
Criar pagamento manual.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `valor` | number | ✅ |
| `data_vencimento` | date | ✅ |
| `usuario_id` | integer | ✅ |
| `plano_id` | integer | ✅ |
| `forma_pagamento_id` | integer | ❌ |
| `observacoes` | string | ❌ |

---

### `POST /admin/pagamentos-plano/{id}/confirmar`
Confirmar pagamento (dar baixa). Ativa matrícula, gera próximo automaticamente.

**Body:** `{ "data_pagamento": "2025-06-20", "forma_pagamento_id": 2 }`

---

### `DELETE /admin/pagamentos-plano/{id}`
Cancelar pagamento.

---

### `POST /admin/pagamentos-plano/marcar-atrasados`
Marcar pagamentos vencidos como atrasados (batch).

---

## 15. Admin — Contas a Receber

### `GET /admin/contas-receber`
Listar contas.

**Query:** `status`, `usuario_id`, `mes_referencia` (YYYY-MM)

---

### `GET /admin/contas-receber/relatorio`
Relatório com totalizadores.

**Query:** `data_inicio`, `data_fim`, `status`, `formas_pagamento` (IDs separados por vírgula)

---

### `GET /admin/contas-receber/estatisticas`
Estatísticas de contas.

**Query:** `mes_referencia` (YYYY-MM, default mês atual)

---

### `POST /admin/contas-receber/{id}/baixa`
Dar baixa em conta.

**Body:** `{ "data_pagamento": "2025-06-20", "forma_pagamento_id": 2 }`

---

### `POST /admin/contas-receber/{id}/cancelar`
Cancelar conta pendente.

**Body:** `{ "observacoes": "Motivo" }`

---

## 16. Admin — Planos

### `GET /admin/planos` (também disponível em `GET /planos` para alunos)
Listar planos.

**Query:** `ativos` (bool)

**Resposta:** `{ "planos": [...], "total": 5 }`

---

### `GET /admin/planos/{id}`
Buscar plano.

---

### `POST /admin/planos`
Criar plano.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `modalidade_id` | integer | ✅ |
| `nome` | string | ✅ |
| `valor` | float | ✅ (>= 0) |
| `checkins_semanais` | integer | ✅ (>= 1) |
| `duracao_dias` | integer | ❌ (default: 30) |

---

### `PUT /admin/planos/{id}`
Atualizar plano.

---

### `DELETE /admin/planos/{id}`
Desativar plano.

---

## 17. Admin — Ciclos de Plano

### `GET /admin/assinatura-frequencias`
Listar frequências disponíveis (mensal, trimestral, etc.).

**Resposta 200:**
```json
{ "success": true, "data": [{ "id": 1, "nome": "Mensal", "codigo": "mensal", "meses": 1, "ordem": 1 }] }
```

---

### `GET /admin/planos/{plano_id}/ciclos`
Listar ciclos de um plano.

**Query:** `ativo` (`"0"` ou `"1"`)

**Resposta 200:**
```json
{
  "success": true,
  "plano": { "id": 3, "nome": "CrossFit", "valor": 150.00 },
  "ciclos": [{
    "id": 1, "nome": "Mensal", "codigo": "mensal", "meses": 1,
    "valor": 150.00, "valor_mensal_equivalente": 150.00,
    "desconto_percentual": 0, "permite_recorrencia": true,
    "permite_reposicao": true, "ativo": true
  }, {
    "id": 2, "nome": "Trimestral", "codigo": "trimestral", "meses": 3,
    "valor": 382.50, "valor_mensal_equivalente": 127.50,
    "desconto_percentual": 15, "economia_valor": 67.50,
    "economia_formatada": "R$ 67,50"
  }]
}
```

---

### `POST /admin/planos/{plano_id}/ciclos`
Criar ciclo.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `assinatura_frequencia_id` | integer | ✅ |
| `valor` | float | ✅ |
| `permite_recorrencia` | boolean | ❌ (default: true) |
| `permite_reposicao` | boolean | ❌ (default: true) |
| `ativo` | boolean | ❌ (default: true) |

---

### `POST /admin/planos/{plano_id}/ciclos/gerar`
Gerar ciclos automaticamente com descontos progressivos.

**Body:**
```json
{
  "desconto_mensal": 0,
  "desconto_bimestral": 10,
  "desconto_trimestral": 15,
  "desconto_semestral": 25,
  "desconto_anual": 30,
  "permite_reposicao": true
}
```

---

### `PUT /admin/planos/{plano_id}/ciclos/{id}`
Atualizar ciclo.

---

### `DELETE /admin/planos/{plano_id}/ciclos/{id}`
Excluir ciclo (bloqueia se houver matrículas vinculadas).

---

## 18. Admin — Modalidades

### `GET /admin/modalidades`
Listar modalidades.

**Query:** `apenas_ativas` (`"true"` / `"false"`)

---

### `GET /admin/modalidades/{id}`
Buscar modalidade.

---

### `POST /admin/modalidades`
Criar modalidade (opcionalmente com planos).

**Body:**
```json
{
  "nome": "CrossFit",
  "planos": [
    { "nome": "Mensal", "valor": 150.00, "checkins_semanais": 5, "duracao_dias": 30 },
    { "nome": "Trimestral", "valor": 400.00, "checkins_semanais": 5, "duracao_dias": 90 }
  ]
}
```

---

### `PUT /admin/modalidades/{id}`
Atualizar modalidade e gerenciar planos.

---

### `DELETE /admin/modalidades/{id}`
Toggle ativo/inativo.

---

## 19. Admin — Turmas

### `GET /admin/turmas`
Listar turmas.

**Query:** `apenas_ativas`, `data` (YYYY-MM-DD), `dia_id` (int)

---

### `GET /admin/turmas/dia/{diaId}`
Turmas de um dia.

---

### `GET /admin/turmas/{id}`
Detalhes da turma.

---

### `POST /admin/turmas`
Criar turma.

**Body:**
| Campo | Tipo | Obrigatório | Descrição |
|-------|------|:-----------:|-----------|
| `nome` | string | ✅ | — |
| `professor_id` | integer | ✅ | — |
| `modalidade_id` | integer | ✅ | — |
| `dia_id` | integer | ✅ | — |
| `horario_inicio` | string | ✅ | `HH:MM` ou `HH:MM:SS` |
| `horario_fim` | string | ✅ | `HH:MM` ou `HH:MM:SS` |
| `limite_alunos` | integer | ❌ | >= 1 |
| `tolerancia_minutos` | integer | ❌ | Default: 10 |
| `tolerancia_antes_minutos` | integer | ❌ | Default: 480 |

---

### `PUT /admin/turmas/{id}`
Atualizar turma.

---

### `DELETE /admin/turmas/{id}`
Soft delete.

---

### `DELETE /admin/turmas/{id}/permanente`
Hard delete permanente.

---

### `GET /admin/turmas/{id}/vagas`
Verificar vagas.

**Resposta:** `{ "turma_id": 15, "limite_alunos": 15, "alunos_inscritos": 10, "vagas_disponiveis": 5, "tem_vagas": true }`

---

### `GET /admin/professores/{professorId}/turmas`
Turmas de um professor.

---

### `POST /admin/turmas/replicar`
Replicar turmas para outros dias.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `dia_id` | integer | ✅ |
| `periodo` | string | ❌ | `proxima_semana`, `mes_todo`, `custom` |
| `mes` | string | ⚠️ | YYYY-MM (para mes_todo e custom) |
| `dias_semana` | int[] | ⚠️ | 1-7 (para custom) |
| `modalidade_id` | integer | ❌ |

---

### `POST /admin/turmas/desativar`
Desativar turma (com propagação opcional).

**Body:** `{ "turma_id": 15, "periodo": "mes_todo", "mes": "2025-07" }`

---

## 20. Admin — Professores

### `GET /admin/professores`
Listar professores.

**Query:** `apenas_ativos`

---

### `GET /admin/professores/{id}`
Buscar professor.

---

### `POST /admin/professores`
Criar/associar professor.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `nome` | string | ✅ |
| `email` | string | ✅ |
| `cpf` | string | ✅ (11 dígitos) |
| `telefone` | string | ❌ |
| `foto_url` | string | ❌ |

**Resposta 201:** Retorna `credenciais` com `senha_temporaria` se usuário foi criado.

---

### `PUT /admin/professores/{id}`
Atualizar professor.

---

### `DELETE /admin/professores/{id}`
Desativar professor.

---

### `GET /admin/professores/cpf/{cpf}`
Buscar por CPF no tenant.

---

### `GET /admin/professores/global/cpf/{cpf}`
Buscar por CPF globalmente (cross-tenant).

---

## 21. Admin — Pacotes

### `GET /admin/pacotes`
Listar pacotes.

---

### `POST /admin/pacotes`
Criar pacote.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `nome` | string | ✅ |
| `valor_total` | float | ✅ |
| `qtd_beneficiarios` | integer | ✅ |
| `plano_id` | integer | ✅ |
| `descricao` | string | ❌ |
| `plano_ciclo_id` | integer | ❌ |

---

### `PUT /admin/pacotes/{id}`
Atualizar pacote.

---

### `POST /admin/pacotes/{pacoteId}/contratar`
Contratar pacote (criar contrato).

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `pagante_usuario_id` | integer | ✅ |
| `beneficiarios` | int[] | ❌ (array de aluno_ids) |

---

### `POST /admin/pacotes/contratos/{contratoId}/beneficiarios`
Definir beneficiários.

**Body:** `{ "beneficiarios": [5, 8, 12] }`

---

### `POST /admin/pacotes/contratos/{contratoId}/confirmar-pagamento`
Confirmar pagamento. Ativa contrato, cria matrículas + pagamentos para beneficiários.

---

### `GET /admin/pacote-contratos`
Listar contratos de pacotes.

**Query:** `status` (pendente/ativo/cancelado/expirado)

---

### `POST /admin/pacote-contratos/{contratoId}/gerar-matriculas`
Gerar matrículas para beneficiários de contrato ativo.

---

## 22. Admin — Assinaturas

### `GET /admin/assinaturas`
Listar assinaturas do tenant.

**Query:**
| Param | Tipo | Descrição |
|-------|------|-----------|
| `status` | string | ativa, pendente, etc. |
| `tipo_cobranca` | string | recorrente, avulso |
| `busca` | string | Nome do aluno |
| `page` | integer | Default: 1 |
| `per_page` | integer | Default: 20, max: 100 |

---

## 23. Admin — Check-ins

### `POST /admin/checkins/registrar`
Registrar check-in para aluno (admin).

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `usuario_id` | integer | ✅ |
| `turma_id` | integer | ✅ |

---

## 24. Admin — WODs

### `GET /admin/wods`
Listar WODs.

**Query:** `status`, `data_inicio`, `data_fim`, `data`, `modalidade_id`

---

### `GET /admin/wods/{id}`
Detalhes do WOD com blocos, variações e resultados.

---

### `POST /admin/wods`
Criar WOD básico.

**Body:** `{ "titulo": "FRAN", "data": "2025-06-20", "modalidade_id": 1, "descricao": "...", "status": "draft" }`

---

### `POST /admin/wods/completo`
Criar WOD completo com blocos e variações.

**Body:**
```json
{
  "titulo": "FRAN",
  "data": "2025-06-20",
  "modalidade_id": 1,
  "blocos": [{
    "ordem": 1, "tipo": "metcon", "titulo": "METCON",
    "conteudo": "21-15-9\nThrusters\nPull-ups", "tempo_cap": 10
  }],
  "variacoes": [
    { "nome": "RX", "descricao": "43/30kg" },
    { "nome": "Scaled", "descricao": "33/20kg" }
  ]
}
```

---

### `PUT /admin/wods/{id}`
Atualizar WOD.

---

### `DELETE /admin/wods/{id}`
Deletar WOD.

---

### `PATCH /admin/wods/{id}/publish`
Publicar WOD.

---

### `PATCH /admin/wods/{id}/archive`
Arquivar WOD.

---

### `GET /admin/wods/modalidades`
Modalidades disponíveis para WODs.

---

### `GET /admin/wods/buscar`
Buscar WOD por data e modalidade.

**Query:** `data` (YYYY-MM-DD, ✅), `modalidade_id` (int, ✅)

---

#### Blocos de WOD

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/admin/wods/{wodId}/blocos` | Listar blocos |
| `POST` | `/admin/wods/{wodId}/blocos` | Criar bloco |
| `PUT` | `/admin/wods/{wodId}/blocos/{id}` | Atualizar bloco |
| `DELETE` | `/admin/wods/{wodId}/blocos/{id}` | Deletar bloco |

**Body criar bloco:**
```json
{ "tipo": "metcon", "conteudo": "21-15-9...", "ordem": 1, "titulo": "METCON", "tempo_cap": 10 }
```

Tipos de bloco: `warmup`, `strength`, `metcon`, `accessory`, `cooldown`, `note`

---

#### Variações de WOD

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/admin/wods/{wodId}/variacoes` | Listar variações |
| `POST` | `/admin/wods/{wodId}/variacoes` | Criar variação |
| `PUT` | `/admin/wods/{wodId}/variacoes/{id}` | Atualizar |
| `DELETE` | `/admin/wods/{wodId}/variacoes/{id}` | Deletar |

**Body:** `{ "nome": "RX", "descricao": "43/30kg" }`

---

#### Resultados de WOD (Leaderboard)

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/admin/wods/{wodId}/resultados` | Listar resultados |
| `POST` | `/admin/wods/{wodId}/resultados` | Registrar resultado |
| `PUT` | `/admin/wods/{wodId}/resultados/{id}` | Atualizar |
| `DELETE` | `/admin/wods/{wodId}/resultados/{id}` | Deletar |

**Body:** `{ "usuario_id": 1, "tipo_score": "time", "variacao_id": 1, "valor_num": 185, "valor_texto": "3:05", "observacao": "PR!" }`

---

## 25. Admin — Presença

### `GET /admin/turmas/{turmaId}/presencas`
Listar alunos com check-in para controle de presença.

**Resposta 200:**
```json
{
  "type": "success",
  "data": {
    "turma": { "id": 15, "nome": "CrossFit 07h", "professor": "Carlos", "horario_inicio": "07:00" },
    "checkins": [{
      "checkin_id": 100,
      "aluno": { "id": 5, "nome": "João", "email": "..." },
      "data_checkin": "2025-06-20 07:05:00",
      "presenca": { "status": "presente", "confirmada_em": "2025-06-20 08:15:00" }
    }],
    "estatisticas": { "total_checkins": 12, "presentes": 10, "faltas": 1, "nao_verificados": 1 }
  }
}
```

---

### `PATCH /admin/checkins/{checkinId}/presenca`
Marcar presença individual.

**Body:** `{ "presente": true }`

---

### `POST /admin/turmas/{turmaId}/presencas/lote`
Marcar presença em lote.

**Body:**
```json
{ "presente": true, "checkin_ids": [100, 101, 102] }
```
Ou: `{ "presente": true, "marcar_todos": true }`

---

## 26. Admin — Relatórios

### `GET /admin/relatorios/planos-ciclos`
Relatório de planos e ciclos de pagamento.

**Query:** `ativo` (0/1), `modalidade_id` (int)

---

## 27. Admin — Formas de Pagamento

### `GET /admin/formas-pagamento-config`
Listar formas de pagamento configuradas.

**Query:** `apenas_ativas` (bool)

---

### `GET /admin/formas-pagamento-config/{id}`
Buscar configuração.

---

### `PUT /admin/formas-pagamento-config/{id}`
Atualizar configuração.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `taxa_percentual` | float | ✅ |
| `taxa_fixa` | float | ✅ |
| `ativo` | integer | ❌ |
| `aceita_parcelamento` | boolean | ❌ |
| `parcelas_maximas` | integer | ⚠️ (se parcelamento) |
| `juros_parcelamento` | float | ❌ |
| `parcelas_sem_juros` | integer | ❌ |
| `dias_compensacao` | integer | ❌ |
| `valor_minimo` | float | ❌ |

---

### `POST /admin/formas-pagamento-config/calcular-taxas`
Calcular taxas sobre um valor.

**Body:** `{ "forma_pagamento_id": 2, "valor": 150.00 }`

---

### `POST /admin/formas-pagamento-config/calcular-parcelas`
Calcular parcelas com juros.

**Body:** `{ "forma_pagamento_id": 2, "valor": 450.00, "parcelas": 3 }`

---

## 28. Admin — Credenciais de Pagamento

### `GET /admin/payment-credentials`
Obter credenciais (valores sensíveis mascarados).

---

### `POST /admin/payment-credentials`
Salvar/atualizar credenciais MercadoPago.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `provider` | string | ❌ (default: mercadopago) |
| `environment` | string | ❌ (default: sandbox) |
| `access_token_test` | string | ❌ |
| `access_token_prod` | string | ❌ |
| `public_key_test` | string | ❌ |
| `public_key_prod` | string | ❌ |
| `webhook_secret` | string | ❌ |
| `is_active` | boolean | ❌ |

---

### `POST /admin/payment-credentials/test`
Testar conexão com MercadoPago.

---

## 29. Admin — Dias e Horários

### `GET /dias`
Listar dias ativos. 🔒 Auth

---

### `GET /dias/{id}/horarios`
Turmas/horários de um dia específico. 🔒 Auth

---

### `GET /dias/por-data?data=YYYY-MM-DD`
Buscar dia por data. 🔒 Auth

---

### `GET /dias/periodo?data_inicio=YYYY-MM-DD&data_fim=YYYY-MM-DD`
Listar dias por período. 🔒 Auth

---

### `GET /dias/proximos?data=YYYY-MM-DD`
5 dias ao redor de uma data (2 antes, atual, 2 depois). 🔒 Auth

---

### `GET /dias/horarios?data=YYYY-MM-DD`
Turmas com dados completos por data. 🔒 Auth

---

### `POST /admin/dias/desativar`
Desativar dia(s) — feriados, dias sem aula.

**Body:**
```json
{ "dia_id": 50, "periodo": "mes_todo", "mes": "2025-07" }
```

---

### `DELETE /admin/dias/{id}/horarios`
Deletar todas as turmas de um dia.

---

## 30. Admin — Usuários (Tenant)

### `GET /tenant/usuarios` 🔒 Admin
Listar usuários do tenant.

**Query:** `ativos` (`true`/`false`)

---

### `GET /tenant/usuarios/{id}` 🔒 Admin
Buscar usuário.

---

### `POST /tenant/usuarios` 🔒 Admin
Criar usuário.

**Body:**
| Campo | Tipo | Obrigatório |
|-------|------|:-----------:|
| `nome` | string | ✅ |
| `email` | string | ✅ |
| `senha` | string | ✅ (mín 6 chars) |
| `telefone` | string | ❌ |
| `cpf` | string | ❌ (11 dígitos) |
| `cep` | string | ❌ |
| `logradouro` | string | ❌ |
| `numero` | string | ❌ |
| `complemento` | string | ❌ |
| `bairro` | string | ❌ |
| `cidade` | string | ❌ |
| `estado` | string | ❌ |

---

### `PUT /tenant/usuarios/{id}` 🔒 Admin
Atualizar usuário. Mesmos campos, todos opcionais.

---

### `DELETE /tenant/usuarios/{id}` 🔒 Admin
Toggle ativo/inativo.

---

### `GET /tenant/usuarios/buscar-cpf/{cpf}` 🔒 Admin
Buscar por CPF globalmente.

---

### `POST /tenant/usuarios/associar` 🔒 Admin
Associar usuário existente ao tenant.

**Body:** `{ "usuario_id": 1 }`

---

### Rotas de Perfil (qualquer usuário autenticado)

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/me` | Dados do usuário autenticado |
| `PUT` | `/me` | Atualizar perfil (nome, email, senha, foto_base64) |

---

## 31. Professor

> Prefixo: `/professor` — Requer 🔒 Auth + papel >= Professor

### `GET /professor/dashboard`
Dashboard do professor.

**Resposta 200:**
```json
{
  "type": "success",
  "data": {
    "professor": { "id": 1, "nome": "Carlos", "email": "carlos@..." },
    "estatisticas": { "total_turmas": 10, "checkins_pendentes": 5, "presencas_mes": 200, "faltas_mes": 15 },
    "turmas_pendentes": [...],
    "total_turmas_pendentes": 3
  }
}
```

---

### `GET /professor/turmas/pendentes`
Turmas com check-ins pendentes de confirmação.

---

### `GET /professor/turmas/{turmaId}/checkins`
Check-ins de uma turma para marcar presença.

---

### `POST /professor/turmas/{turmaId}/confirmar-presenca`
Confirmar presença da turma (marca presentes/faltas).

**Body:**
```json
{
  "presencas": { "100": true, "101": false, "102": true },
  "remover_faltantes": true
}
```

---

### `DELETE /professor/turmas/{turmaId}/faltantes`
Remover check-ins de faltantes (libera créditos).

---

## 32. Rotas Públicas

| Método | Rota | Descrição |
|--------|------|-----------|
| `GET` | `/ping` | Health check básico |
| `GET` | `/health` | Health com banco de dados |
| `GET` | `/health/basic` | Health sem banco |
| `GET` | `/status` | Status da API |
| `GET` | `/cep/{cep}` | Busca de CEP (ViaCEP) |
| `GET` | `/status/{tipo}` | Listar status por tipo |
| `GET` | `/status/{tipo}/{id}` | Buscar status por ID |
| `GET` | `/status/{tipo}/codigo/{codigo}` | Buscar status por código |
| `GET` | `/formas-pagamento` | Listar formas de pagamento |
| `GET` | `/feature-flags/{key}` | Consultar feature flag |
| `GET` | `/auth/tenants-public` | Listar academias ativas (público) |
| `GET` | `/uploads/fotos/{filename}` | Servir foto de perfil |

---

## 33. Códigos de Erro

Todas as respostas de erro seguem o padrão:
```json
{
  "type": "error",
  "code": "CODIGO_DO_ERRO",
  "message": "Descrição legível do erro"
}
```

Ou no mobile:
```json
{
  "success": false,
  "error": "Descrição do erro",
  "code": "CODIGO_DO_ERRO"
}
```

### HTTP Status Codes

| Código | Significado |
|--------|-------------|
| 200 | Sucesso |
| 201 | Criado com sucesso |
| 400 | Requisição inválida / Regra de negócio violada |
| 401 | Não autenticado / Token inválido |
| 403 | Sem permissão (papel inadequado, recurso de outro tenant) |
| 404 | Não encontrado |
| 409 | Conflito (email/CPF duplicado, aluno já associado) |
| 422 | Erro de validação (campos obrigatórios faltando) |
| 429 | Rate limit excedido |
| 500 | Erro interno |

### Códigos de Erro Comuns

| Código | Contexto |
|--------|----------|
| `MISSING_CREDENTIALS` | Login sem email/senha |
| `INVALID_CREDENTIALS` | Email ou senha inválidos |
| `NO_TENANT_ACCESS` | Sem vínculo com academia |
| `EMAIL_ALREADY_EXISTS` | Email duplicado |
| `CPF_ALREADY_EXISTS` | CPF duplicado |
| `VALIDATION_ERROR` | Campos obrigatórios faltando |
| `RATE_LIMIT_EXCEEDED` | Muitas tentativas |
| `RECAPTCHA_VALIDATION_FAILED` | Falha no reCAPTCHA |
| `MATRICULA_NOT_FOUND` | Matrícula não encontrada |
| `ALUNO_NOT_FOUND` | Aluno não encontrado |
| `PLANO_NOT_FOUND` | Plano não encontrado |

---

## Webhook MercadoPago

> Estas rotas são chamadas automaticamente pelo MercadoPago, não pelo frontend.

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| `POST` | `/api/webhooks/mercadopago` | Público | Webhook principal |
| `POST` | `/api/webhooks/mercadopago/v2` | Público | Webhook v2 (SDK oficial) |
| `POST` | `/api/webhooks/mercadopago/recuperar-assinatura` | 🔒 | Recuperar assinatura manualmente |
| `GET` | `/api/webhooks/mercadopago/test` | Público | Simular webhook (DEV) |
| `GET` | `/api/webhooks/mercadopago/cobrancas` | 🔒 Admin | Consultar cobranças por external_reference |
| `GET` | `/api/webhooks/mercadopago/list` | 🔒 Admin | Listar webhooks recebidos |
| `GET` | `/api/webhooks/mercadopago/show/{id}` | 🔒 Admin | Ver webhook específico |
| `POST` | `/api/webhooks/mercadopago/reprocess/{id}` | 🔒 Admin | Reprocessar webhook |
| `GET` | `/api/webhooks/mercadopago/payment/{paymentId}` | 🔒 Admin | Debug de pagamento |
| `POST` | `/api/webhooks/mercadopago/payment/{paymentId}/reprocess` | 🔒 Admin | Reprocessar pagamento |

---

> **Última atualização:** Junho 2025  
> **Versão da API:** 1.0.0
