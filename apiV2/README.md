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
