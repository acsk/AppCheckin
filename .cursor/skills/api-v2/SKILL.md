---
name: api-v2
description: Migra e mantém a API Laravel em apiV2 (prefixo /v2, porta 9090), compatível com JWT e banco da API Slim. Use ao trabalhar em apiV2, migrar rotas da Slim, auth JWT, multi-tenant, ou quando o usuário mencionar API v2, Laravel migration ou localhost:9090.
---

# AppCheckin API v2 (Laravel)

> **Agente:** leia este arquivo por completo antes de qualquer trabalho em `apiV2/`. Execute o workflow, regras obrigatórias e checklist abaixo. Detalhes extras em [reference.md](reference.md).

Migração **strangler**: `api/` (Slim) continua em produção; novas rotas vão para `apiV2/` (Laravel 13).

## Stack e URLs

| Item | Valor |
|------|--------|
| Pasta | `apiV2/` |
| Prefixo rotas | `/v2` (`routes/api.php`, `apiPrefix: ''` em `bootstrap/app.php`) |
| Local Docker | http://localhost:9090 (`php-v2`, map `9090:80`) |
| API legada | http://localhost:8080 (`api/`) |
| PHP (container) | 8.4 |
| Banco | MySQL `appcheckin` **compartilhado** com Slim |

## Regras obrigatórias

1. **JWT compatível** — Mesmo `JWT_SECRET` e claims da Slim: `user_id`, `email`, `tenant_id`, `aluno_id`, `is_super_admin`. Pacote: `firebase/php-jwt` v7, HS256. Serviço: `App\Services\JwtService`.
2. **Não criar tabelas Laravel no banco compartilhado** — `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Não rodar `php artisan migrate` em `appcheckin` sem aprovação explícita.
3. **Contrato JSON igual à Slim** — Auth/admin: `App\Support\ApiError` (`type`, `code`, `message`). **Mobile:** `{ "success": true/false, "error"?, "data"? }` via `App\Support\MobileResponse` / controllers mobile.
4. **Multi-tenant** — Resolver `tenant_id` do JWT (middleware `jwt.auth`). Atributos no request: `userId`, `tenantId`, `tenant_id`, `aluno_id`, `jwt_payload`, `usuario`.
5. **Escopo mínimo** — Uma rota/módulo por vez; não refatorar Slim nem mobile até a v2 estar paridade.

## Estrutura de código

```
apiV2/
├── routes/api.php              # Todas as rotas sob prefix('v2')
├── app/Http/Controllers/Api/V2/
├── app/Http/Middleware/JwtAuthenticate.php   # alias: jwt.auth
├── app/Services/               # JwtService, AuthService, ...
├── app/Repositories/           # Queries ao schema existente (preferir a Eloquent novo)
├── app/Support/ApiError.php
└── config/appcheckin.php       # jwt_secret, jwt_expiration, api_version
```

## Workflow: portar uma rota da Slim

1. Localizar rota e controller em `api/routes/api.php` e `api/app/Controllers/`.
2. Copiar validações, SQL e formato de resposta **sem mudar contrato**.
3. Implementar em `app/Http/Controllers/Api/V2/` + repository/service se a query for reutilizável.
4. Registrar em `routes/api.php` dentro de `Route::prefix('v2')`.
5. Rotas protegidas: `Route::middleware('jwt.auth')`.
6. Testar com curl na 9090; token da Slim deve funcionar na v2.

Checklist por endpoint:

```
- [ ] Path: /v2/... (não quebrar path Slim)
- [ ] Status codes e body iguais à Slim
- [ ] tenant_id / aluno_id do JWT respeitados
- [ ] Sem migrations Laravel no appcheckin
```

## Rotas já migradas

- `GET /v2/ping`, `/v2/health`, `/v2/health/basic`
- `POST /v2/auth/login`
- `POST /v2/auth/select-tenant` (JWT)
- `POST /v2/auth/select-tenant-initial`, `/v2/auth/select-tenant-public` (públicas)
- `POST /v2/auth/register`
- `POST /v2/auth/password-recovery/request`, `validate-token`, `reset`
- `POST /v2/auth/logout`, `GET /v2/auth/tenants` (JWT)
- `GET /v2/me` (JWT)
- Mobile (JWT, formato `success`/`data`):
  - `GET /v2/mobile/perfil`
  - `GET /v2/mobile/acesso`
  - `GET /v2/mobile/tenants`
  - `GET /v2/mobile/horarios-disponiveis?data=YYYY-MM-DD`
  - `POST /v2/mobile/checkin` (`turma_id`)
  - `DELETE /v2/mobile/checkin/{checkinId}/desfazer`
  - `GET /v2/mobile/checkins` (`limit`, `offset`)
  - `GET /v2/mobile/checkins/por-modalidade` (`offset`, `data_referencia`)
  - `GET /v2/mobile/ranking/mensal` (`modalidade_id`)
  - `GET /v2/mobile/wod/hoje` (`data`, `modalidade_id`)
  - `GET /v2/mobile/wods/hoje` (`data`)
  - `GET /v2/mobile/planos-disponiveis` (`modalidade_id`)
  - `GET /v2/mobile/planos` (`todas`)
  - `GET /v2/mobile/planos/{planoId}`
  - `GET /v2/mobile/matriculas/{matriculaId}`
  - `POST /v2/mobile/comprar-plano`
  - `POST /v2/mobile/pagamento/pix`
  - `POST /v2/mobile/verificar-pagamento`
  - `GET /v2/mobile/pagamento/reabrir/{matriculaId}`
  - `GET /v2/mobile/assinaturas`
  - `GET /v2/mobile/assinaturas/aprovadas-hoje` (`matricula_id`)
  - `POST /v2/mobile/assinatura/{id}/cancelar`
  - `POST /v2/mobile/diaria/{matriculaId}/cancelar`

## Limitações vs Slim (Fase 4 — planos / pagamentos)

Rotas da Fase 4 existem na v2 com contrato JSON compatível, mas **não fechar cutover** de `planos.tsx`, `plano-detalhes.tsx` e fluxo PIX até resolver os itens abaixo. Referência Slim: `api/app/Controllers/MobileController.php` (`comprarPlano`, `gerarPagamentoPix`, `reabrirPagamentoPendente`) e `api/app/Controllers/AssinaturaController.php` (`aprovadasHoje`).

| Endpoint v2 | Implementação v2 | O que falta vs Slim |
|-------------|------------------|---------------------|
| `POST /v2/mobile/comprar-plano` | `MobileCompraPlanoService` | Fluxo principal: nova matrícula + PIX / checkout (preference) / recorrente (preapproval) + reutilizar matrícula **pendente** na mesma escolha (`plano_id` + `plano_ciclo_id`). **Não** cobre: reuso de matrícula **vencida/cancelada** (UPDATE em vez de INSERT), pendência com método diferente (ex.: pendente PIX + novo checkout), histórico de planos, marcação automática vencida antes do reuse, e demais ramos do controller Slim (~1000 linhas). |
| `GET /v2/mobile/assinaturas/aprovadas-hoje` | `MobileAssinaturaService::aprovadasHoje` | Passos 1–2: assinatura já aprovada localmente ou matrícula já `ativa`. **Falta** passo 3–4 da Slim: consultar Mercado Pago por `external_reference`, chamar `processarPagamentoAprovadoMP` quando `approved` (fallback quando webhook não chegou). Usado em `plano-detalhes.tsx` após pagamento. |
| Pacotes mobile | — (não migrado) | `GET /mobile/pacotes/contratos`, `GET /mobile/pacotes/pendentes`, `POST /mobile/pacotes/contratos/{contratoId}/pagar` — listados em “Próximos candidatos”. `minhas-assinaturas.tsx` já usa `GET /assinaturas` (v2 ok). |

**Código v2 relevante:** `MobileCompraPlanoService`, `MobilePagamentoService`, `MobileAssinaturaService`, `MercadoPagoService` (portado da Slim).

**Antes de apontar o mobile só para `:9090` em compra/PIX:** validar cenários de matrícula pendente reaberta, renovação pós-vencimento e polling pós-pagamento (`aprovadas-hoje` com MP).

## Próximos candidatos (ordem sugerida)

1. Mobile: professor (check-in manual, presença, bloqueio turma) — `TurmaCheckinBloqueioService` já existe na v2
2. Fechar paridade Fase 4:
   - `comprar-plano`: reuso matrícula vencida + ramos pendentes/checkout da Slim
   - `aprovadas-hoje`: fallback Mercado Pago + ativação (`AssinaturaController::processarPagamentoAprovadoMP`)
3. Mobile: pacotes (`GET /pacotes/contratos`, `GET /pacotes/pendentes`, `POST /pacotes/contratos/{id}/pagar`)

## TESTS
1. Sempre execute testes

## Comandos úteis

```bash
docker compose up -d mysql php-v2
docker exec appcheckin_php_v2 php artisan route:list --path=v2
docker exec appcheckin_php_v2 php artisan config:clear
curl http://localhost:9090/v2/ping
```

## Referência Slim

- Rotas: `api/routes/api.php`
- JWT: `api/app/Services/JWTService.php`
- Auth: `api/app/Controllers/AuthController.php`
- Tenant: `api/app/Middlewares/TenantMiddleware.php`, `AuthMiddleware.php`



Detalhes de paridade e env: [reference.md](reference.md)
