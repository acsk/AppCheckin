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
| Matrículas (core) | ✅ | ✅ | ✅ | `routes/api.php` + exceções abaixo |
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
| Assinaturas admin | ⚠️ | ✅ | ✅ | `admin/assinaturas.php` — **só** `GET /assinaturas` |
| WOD | ✅ | ✅ | ✅ | `admin/wods.php` — 22 rotas |
| Recordes | ✅ | ✅ | ✅ | `admin/recordes.php` — 11 rotas |
| Formas-pagamento (catálogo `/admin/formas-pagamento`) | ❌ | — | Slim | `SLIM_ONLY` |
| Configurações tenant | ✅ | ✅ | ✅ | `admin/parametros.php` (alias `/configuracoes`) |
| Parâmetros | ✅ | ✅ | ✅ | `admin/parametros.php` |
| Relatórios | ✅ | ✅ | ✅ | `admin/relatorios.php` |
| Super Admin (exc. logs) | ❌ | — | Slim | `SLIM_ONLY` |
| CEP / status / formas catálogo | ✅ | ✅ | ✅ | `routes/api.php` (público) + `shared/config_formas.php` |
| Webhooks Mercado Pago | ❌ | — | Slim | `SLIM_ONLY` |

Legenda: ✅ completo para o escopo do painel · ⚠️ parcial · ❌ pendente

---

## Denylist atual (`painel/src/config/apiRouting.js`)

```
/superadmin/assinaturas
/admin/formas-pagamento      ← catálogo; config já está na v2
/admin/parametros            ← migrado (mantido só se build antigo)
/superadmin/academias|usuarios|papeis|contratos|pagamentos-contrato|planos
/papeis
/api/webhooks
```

Ao concluir um módulo: implementar rotas na v2 **e** remover o prefixo correspondente de `SLIM_ONLY`.

---

## Matrículas — subpaths ainda na Slim

Definidos em `SLIM_MATRICULA_PATHS` (painel continua na Slim para estes):

- `POST /admin/matriculas` quando body contém `pacote_id`
- `GET …/simular-cancelamento`
- `POST …/cancelar-com-credito`
- `…/pacote-contrato/…`
- `…/pagamentos/{id}/confirmar`
- `…/assinatura`, `…/sincronizar-assinatura`

---

## Lacunas (v2 incompleto, painel já aponta para v2)

Rotas **sem** entrada em `SLIM_ONLY` que ainda podem faltar na apiV2:

| Rota | Usado por |
|------|-----------|
| `GET /admin/admins` | `descontoMatriculaService` |
| `GET /formas-pagamento` | modals de baixa, contratos |
| `GET /config/formas-pagamento-ativas` | `BaixaPagamentoModal` |
| Assinaturas admin (exc. listagem) | `assinaturaService` — ver abaixo |
| `GET /admin/turmas/dia/{id}`, presenças | Slim (painel não usa hoje) |

### Assinaturas admin — pendente na v2

Painel já vai para apiV2, mas só existe `GET /v2/admin/assinaturas`. Faltam migrar:

- `GET/POST/PUT /admin/assinaturas/{id}`
- `POST …/renovar`, `…/suspender`, `…/reativar`, `…/cancelar`
- `GET …/proximas-vencer`, `…/sem-matricula`, `…/relatorio`
- `POST …/sincronizar-matricula`, `GET …/status-sincronizacao`
- `GET /admin/alunos/{id}/assinaturas`

---

## Ordem sugerida (próximas waves)

1. **Assinaturas admin** (completar) + **`/superadmin/assinaturas`**
2. **Parâmetros** + **configurações** tenant
3. **WOD** + **Recordes**
4. **Super Admin** (academias, contratos, planos-sistema, usuários)
5. **Auxiliares:** `/cep`, `/status`, `/formas-pagamento`, `/config/formas-pagamento-ativas`, `GET /admin/admins`
6. **Matrículas** — subpaths em `SLIM_MATRICULA_PATHS`
7. **Relatórios** (`/admin/relatorios/planos-ciclos`)
8. **Webhooks MP** (infra; manter Slim até cutover explícito)

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
- Mobile: perfil, checkin, planos, assinaturas, pagamento pix, etc.

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
| `assinaturas.php` | `GET /assinaturas` |
| `wods.php` | 22 rotas WOD |
| `recordes.php` | 11 rotas recordes |

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

### `/admin/assinaturas` — demais rotas (listagem ✅)

- `GET /admin/assinaturas/{id}`
- `POST /admin/assinaturas`
- `PUT /admin/assinaturas/{id}`
- `POST /admin/assinaturas/{id}/renovar|suspender|reativar|cancelar`
- `GET /admin/assinaturas/proximas-vencer|sem-matricula|relatorio`
- `POST /admin/assinaturas/{id}/sincronizar-matricula`
- `GET /admin/assinaturas/{id}/status-sincronizacao`
- `GET /admin/alunos/{id}/assinaturas`

### `/admin/checkins` — 2 rotas

- `POST /admin/checkins/registrar`
- `PATCH /admin/checkins/{id}/presenca`

### `/admin/formas-pagamento` — catálogo global (≠ config)

- Rotas CRUD em `formas_pagamento` (Slim)

### `/admin/matriculas` — subpaths Slim-only

- `POST /admin/matriculas/pacote-contrato/{id}/baixa`
- `POST /admin/matriculas/{id}/cancelar-com-credito`
- `GET /admin/matriculas/{id}/simular-cancelamento`
- `POST /admin/matriculas/{id}/pagamentos/{id}/confirmar`
- rotas assinatura / sincronizar (ver `SLIM_MATRICULA_PATHS`)

### `/admin/feature-flags` — 2 rotas

- `GET /admin/feature-flags`
- `GET /admin/feature-flags/{id}`

### `/admin/turmas` — rotas ainda na Slim

- `GET /admin/turmas/dia/{id}`
- `GET|POST /admin/turmas/{id}/presencas` (+ lote)

### `/api/webhooks/mercadopago` — 10 rotas

- Webhooks + listagem/reprocessamento (painel Mercado Pago)

### `/superadmin/*` — módulos inteiros

- `academias` (14 rotas)
- `contratos` (8 rotas)
- `pagamentos-contrato` (5 rotas)
- `usuarios` (4 rotas)
- `papeis`, `planos`, `planos-sistema` (8 rotas)
- `assinaturas`
- `env`

### Fora do painel (baixa prioridade strangler)

- `/professor/*` (5 rotas)
- `/mobile/*` restante (recordes, pacotes, turma, perfil foto, etc.)
- `/v1/*` legado
- `/checkin`, `/signin`, `/uploads`, `/usuarios/{id}/estatisticas`

---

## Como validar após migrar um módulo

1. Implementar repository + service + controller + `routes/v2/admin/<modulo>.php`
2. Testes feature em `tests/Feature/V2Admin*RoutesTest.php`
3. Remover prefixo de `SLIM_ONLY` em `painel/src/config/apiRouting.js`
4. Deploy apiV2 + rebuild painel
5. Atualizar esta tabela de status
