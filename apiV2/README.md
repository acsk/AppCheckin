# AppCheckin API v2 (Laravel)

API Laravel em paralelo à API Slim (`api/`), com prefixo **`/v2`** e **JWT compatível** (mesmo `JWT_SECRET`).

## Rotas iniciais

| Método | Rota | Auth |
|--------|------|------|
| GET | `/v2/ping` | Não |
| GET | `/v2/health` | Não |
| GET | `/v2/health/basic` | Não |
| POST | `/v2/auth/login` | Não |
| POST | `/v2/auth/register` | Não |
| POST | `/v2/auth/password-recovery/request` | Não |
| POST | `/v2/auth/password-recovery/validate-token` | Não |
| POST | `/v2/auth/password-recovery/reset` | Não |
| POST | `/v2/auth/select-tenant` | JWT |
| POST | `/v2/auth/select-tenant-initial` | Não |
| POST | `/v2/auth/select-tenant-public` | Não |
| POST | `/v2/auth/logout` | JWT |
| GET | `/v2/auth/tenants` | JWT |
| GET | `/v2/me` | JWT |
| GET | `/v2/mobile/perfil` | JWT (+ `tenant_id` no token) |
| GET | `/v2/mobile/acesso` | JWT |
| GET | `/v2/mobile/tenants` | JWT |
| GET | `/v2/mobile/checkins` | JWT (`limit`, `offset`) |
| GET | `/v2/mobile/checkins/por-modalidade` | JWT |
| GET | `/v2/mobile/ranking/mensal` | JWT (`modalidade_id` opcional) |
| GET | `/v2/mobile/wod/hoje` | JWT |
| GET | `/v2/mobile/wods/hoje` | JWT |
| GET | `/v2/mobile/horarios-disponiveis?data=YYYY-MM-DD` | JWT |
| POST | `/v2/mobile/checkin` | JWT (`turma_id` no body) |
| DELETE | `/v2/mobile/checkin/{checkinId}/desfazer` | JWT |
| GET | `/v2/mobile/planos-disponiveis` | JWT |
| GET | `/v2/mobile/planos` | JWT (`todas=true` opcional) |
| GET | `/v2/mobile/planos/{planoId}` | JWT |
| GET | `/v2/mobile/matriculas/{matriculaId}` | JWT |
| POST | `/v2/mobile/comprar-plano` | JWT |
| POST | `/v2/mobile/pagamento/pix` | JWT (`matricula_id`) |
| POST | `/v2/mobile/verificar-pagamento` | JWT |
| GET | `/v2/mobile/pagamento/reabrir/{matriculaId}` | JWT |
| GET | `/v2/mobile/assinaturas` | JWT |
| GET | `/v2/mobile/assinaturas/aprovadas-hoje` | JWT (`matricula_id`) |
| POST | `/v2/mobile/assinatura/{id}/cancelar` | JWT |
| POST | `/v2/mobile/diaria/{matriculaId}/cancelar` | JWT |

Respostas **mobile** usam `{ "success": true/false, "data"?, "error"? }` (igual à Slim), não o formato `ApiError` da auth.

Pagamentos usam `App\Services\MercadoPagoService` (portado da Slim) com credenciais por tenant ou variáveis `MP_*` / `MP_FAKE_API_URL`.

## Desenvolvimento local (Docker)

Com o stack principal no ar:

```bash
docker compose up -d mysql php-v2
```

- **API Slim:** http://localhost:8080  
- **API v2:** http://localhost:9090  

Exemplo de login:

```bash
curl -s -X POST http://localhost:9090/v2/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"teste@exemplo.com","senha":"password123"}'
```

## Variáveis de ambiente

Copie `.env.example` para `.env` e alinhe com a API Slim:

```bash
cp .env.example .env
php artisan key:generate
```

- `APP_KEY` — obrigatório (`php artisan key:generate`); o Docker usa `apiV2/.env` via `env_file`
- `DB_*` — mesmo banco `appcheckin`
- `JWT_SECRET` — **obrigatório**, mesmo valor da API Slim
- `JWT_EXPIRATION` — padrão `86400`

## Servidor embutido (sem Docker)

```bash
cd apiV2
composer install
cp .env.example .env
php artisan key:generate
php artisan serve --port=9090
```
