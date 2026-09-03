# Migração de rotas Slim → apiV2

Padrão **strangler fig**: painel usa apiV2 por padrão (`apiv2.appcheckin.com.br/v2`); rotas
sem paridade ficam na denylist `painel/src/config/apiRouting.js` (`SLIM_ONLY`).

Rotas modulares em `apiV2/routes/v2/**/*.php`, carregadas por `routes/api.php`. Rotas core
(alunos, matrículas, planos, auth, mobile…) ficam inline em `routes/api.php`.

**Última atualização:** 2026-09-03

---

## Status por módulo (painel)

| Módulo | apiV2 | Testes | Painel → v2 | Arquivo / nota |
|--------|:-----:|:------:|:-----------:|----------------|
| Auth, `/me`, health | ✅ | ✅ | ✅ | `routes/api.php` |
| Mobile (core checkin/planos) | ✅ | parcial | — | `routes/api.php` |
| Modalidades | ✅ | — | ✅ | `routes/api.php` |
| Alunos | ✅ | parcial | ✅ | `routes/api.php` |
| Planos + ciclos | ✅ | — | ✅ | `routes/api.php` |
| Matrículas | ✅ | ✅ | ✅ | `routes/api.php` + `admin/matriculas_extras.php` |
| Auditoria | ✅ | — | ✅ | `routes/api.php` |
| Logs admin / superadmin | ✅ | — | ✅ | `routes/api.php` |
| Usuários tenant | ✅ | ✅ | ✅ | `admin/usuarios.php`, `shared/tenant_usuarios.php` |
| Professores | ✅ | ✅ | ✅ | `admin/professores.php` |
| Turmas / dias (admin) | ✅ | ✅ | ✅ | `admin/turmas_dias.php` |
| Dias / horários (shared) | ✅ | — | ✅ | `shared/dias.php` |
| Pagamentos-plano, créditos, descontos, contas a receber | ✅ | ✅ | ✅ | `admin/pagamentos_creditos.php` |
| Formas-pagamento-**config** | ✅ | ✅ | ✅ | `admin/formas_pagamento_config.php` |
| Payment-credentials | ✅ | ✅ | ✅ | `admin/payment_credentials.php` |
| Pacotes + pacote-contratos | ✅ | ✅ | ✅ | `admin/pacotes.php`, `admin/pacote_contratos.php` |
| Dashboard | ✅ | ✅ | ✅ | `admin/dashboard.php` |
| Assinaturas admin | ✅ | ✅ | ✅ | `admin/assinaturas.php` |
| WOD | ✅ | ✅ | ✅ | `admin/wods.php` — 22 rotas |
| Recordes | ✅ | ✅ | ✅ | `admin/recordes.php` — **requer** `migrate_recordes_pessoais.php` no banco |
| Formas-pagamento (catálogo `/admin/formas-pagamento`) | ❌ | — | Slim | `SLIM_ONLY` |
| Configurações tenant | ✅ | ✅ | ✅ | `admin/parametros.php` (alias `/configuracoes`) |
| Parâmetros | ✅ | ✅ | ✅ | `admin/parametros.php` |
| Relatórios | ✅ | ✅ | ✅ | `admin/relatorios.php` |
| Super Admin (exc. logs) | ✅ | ✅ | ✅ | `routes/v2/superadmin/*.php` |
| CEP / status / formas catálogo | ✅ | ✅ | ✅ | `routes/api.php` (público) + `shared/config_formas.php` |
| Webhooks Mercado Pago | ❌ | — | Slim | `SLIM_ONLY` |

Legenda: ✅ completo para o escopo do painel · ⚠️ parcial · ❌ pendente

---

## Denylist atual (`painel/src/config/apiRouting.js`)

```
/admin/formas-pagamento      ← catálogo; config já está na v2
/api/webhooks
```

Ao concluir um módulo: implementar rotas na v2 **e** remover o prefixo correspondente de `SLIM_ONLY`.

---

## Lacunas menores (painel → v2, rotas auxiliares)

| Rota | Usado por |
|------|-----------|
| `GET /admin/admins` | `descontoMatriculaService` |
| `GET /admin/turmas/dia/{id}`, presenças | Slim (painel não usa hoje) |

---

## Ordem sugerida (próximas waves)

1. **Formas-pagamento** catálogo (`/admin/formas-pagamento` CRUD)
2. **Webhooks MP** (infra; manter Slim até cutover explícito)
3. **Turmas** — presenças / `GET /turmas/dia/{id}` (se painel passar a usar)

---

## Módulos migrados — referência rápida

### Inline em `routes/api.php`

- `GET/POST` auth, select-tenant, password recovery
- `GET /me`
- `GET /planos`, `GET /planos/{id}`
- `GET/POST/PUT/DELETE /admin/modalidades`
- `GET/POST/PUT/DELETE /admin/alunos` (+ basico, cpf, associar, histórico, checkins, delete-preview)
- `GET/POST/PUT/DELETE /admin/planos`, ciclos, assinatura-frequencias
- Matrículas: index, show, store, update, delete, bloquear, cancelar, alterar-plano, vencimentos, pagamentos
- Auditoria (7 rotas)
- Logs `/admin/logs`, alias `/superadmin/logs`
- Mobile: perfil, checkin, planos, assinaturas, pagamento pix, **turmas**, **perfil/foto**, **check-in manual professor**, **bloquear/desbloquear check-in**, **pacotes mobile** (`/pacotes/contratos`, `/pacotes/pendentes`, `POST .../pagar`), etc.

### Partials `routes/v2/admin/`

| Arquivo | Rotas |
|---------|-------|
| `usuarios.php` | tenant usuarios admin |
| `professores.php` | CRUD + CPF + turmas |
| `turmas_dias.php` | turmas CRUD, replicar, desativar, bloqueio; dias admin |
| `pagamentos_creditos.php` | pagamentos-plano, créditos, descontos, contas-receber |
| `formas_pagamento_config.php` | 5 rotas config tenant |
| `payment_credentials.php` | 3 rotas Mercado Pago |
| `pacotes.php` | 7 rotas pacotes/contratos |
| `pacote_contratos.php` | listar + gerar-matriculas |
| `dashboard.php` | index + cards |
| `parametros.php` | parâmetros + `/configuracoes` |
| `relatorios.php` | planos-ciclos |
| `assinaturas.php` | CRUD + ações admin |
| `matriculas_extras.php` | simular-cancelamento, cancelar-com-credito, baixa pacote, assinatura |
| `wods.php` | 22 rotas WOD |
| `recordes.php` | 11 rotas recordes |

### Partials `routes/v2/superadmin/`

| Arquivo | Rotas |
|---------|-------|
| `academias.php` | CRUD academias + admins |
| `usuarios.php` | usuários globais |
| `papeis.php` | listar papéis (+ alias `GET /v2/papeis`) |
| `planos.php` | planos de alunos |
| `planos_sistema.php` | CRUD planos sistema |
| `contratos.php` | contratos tenant ↔ plano sistema |
| `pagamentos_contrato.php` | pagamentos de contratos |
| `assinaturas.php` | listagem global |
| `misc.php` | `GET /env` |

### Partials `routes/v2/shared/`

| Arquivo | Rotas |
|---------|-------|
| `tenant_usuarios.php` | `/tenant/usuarios` |
| `dias.php` | `/dias`, horários, período |
| `config_formas.php` | formas-pagamento + config tenant |

---

## Inventário Slim — ainda pendente (por módulo)

Detalhamento das rotas que **ainda não existem** na apiV2. Módulos já migrados foram removidos desta lista.

### `/admin/admins` — 1 rota

- `GET /admin/admins`

### `/admin/checkins` — 2 rotas

- `POST /admin/checkins/registrar`
- `PATCH /admin/checkins/{id}/presenca`

### `/admin/formas-pagamento` — catálogo global (≠ config)

- Rotas CRUD em `formas_pagamento` (Slim)

### `/admin/feature-flags` — 2 rotas

- `GET /admin/feature-flags`
- `GET /admin/feature-flags/{id}`

### `/admin/turmas` — rotas ainda na Slim

- `GET /admin/turmas/dia/{id}`
- `GET|POST /admin/turmas/{id}/presencas` (+ lote)

### `/api/webhooks/mercadopago` — 10 rotas

- Webhooks + listagem/reprocessamento (painel Mercado Pago)

### Fora do painel (baixa prioridade strangler)

- `/professor/*` (5 rotas)
- `/mobile/*` restante (recordes, `/professor/*` dashboard/presença)
- `/v1/*` legado
- `/checkin`, `/signin`, `/uploads`, `/usuarios/{id}/estatisticas`

---

## Como validar após migrar um módulo

1. Implementar repository + service + controller + `routes/v2/admin/<modulo>.php`
2. Testes feature em `tests/Feature/V2Admin*RoutesTest.php`
3. Remover prefixo de `SLIM_ONLY` em `painel/src/config/apiRouting.js`
4. **Schema:** se o módulo usa tabelas novas, rodar a migration Slim antes do deploy (ex.: recordes → `api/database/migrate_recordes_pessoais.php` — ver `api/docs/EXECUTAR_MIGRATIONS_RECORDES.md`)
5. Deploy apiV2 + rebuild painel
6. Atualizar esta tabela de status
