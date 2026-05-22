# API v2 — Referência

## Variáveis de ambiente (apiV2/.env)

```env
DB_CONNECTION=mysql
DB_HOST=mysql          # Docker; local: 127.0.0.1
DB_PORT=3306           # Docker; local: 3307
DB_DATABASE=appcheckin
DB_USERNAME=root
DB_PASSWORD=root

JWT_SECRET=<mesmo da api Slim>
JWT_EXPIRATION=86400

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

(Config defaults em `config/session.php`, `cache.php`, `queue.php` também usam file/sync — não dependem só do `.env`.)
APP_URL=http://localhost:9090
```

`docker-compose.yml` serviço `php-v2`: `env_file: ./apiV2/.env` + overrides (DB_HOST=mysql, JWT, `APP_KEY` com fallback). Sem `.env`, rode `php artisan key:generate` em `apiV2/`.

## Timezone MySQL

`AppServiceProvider` executa `SET time_zone = '-03:00'` na conexão mysql (alinhar com `api/config/database.php`).

## Papéis (tenant_usuario_papel)

| papel_id | Uso |
|----------|-----|
| 1 | Aluno |
| 2 | Professor |
| 3 | Admin tenant |
| 4 | Super admin (sem tenant no token) |

Login com múltiplos tenants: `token` pode ser `null` e `requires_tenant_selection: true` até `select-tenant`.

## CORS

`config/cors.php` — paths `v2/*`, `up`.

## Erros comuns

| Sintoma | Causa | Ação |
|---------|--------|------|
| Table `sessions` not found | SESSION_DRIVER=database | Usar `file` |
| Token inválido na v2 | JWT_SECRET diferente da Slim | Alinhar .env |
| 404 em /api/v2/... | Prefixo errado | Rotas são `/v2/...`, não `/api/v2` |

## Não fazer

- `php artisan migrate` no banco `appcheckin` para users/sessions/cache/jobs
- `composer setup` / scripts do `composer.json` não devem rodar migrate (banco compartilhado com Slim)
- Alterar `JWT_SECRET` só na v2
- Mudar formato de erro (`type`/`code`/`message`) sem alinhar clientes
