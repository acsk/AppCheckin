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

### Persistência de recordes

`GET /v2/admin/recordes` lista todos os recordes válidos do tenant, de alunos
e da academia. Aceita `origem=aluno|academia`, `aluno_id`, `definicao_id` e
`modalidade_id`. Quando apenas `aluno_id` é informado, lista os recordes de
origem aluno desse aluno.

A tabela `recorde_valores` do schema compartilhado com a API Slim possui
`created_at`, mas não `updated_at`. A criação e a substituição das métricas
devem respeitar esse schema; não é necessária uma migração para registrar recordes.

Os testes de regressão usam SQLite em memória, sem gravar no banco da aplicação:

```bash
docker exec -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= appcheckin_php_v2 php vendor/bin/phpunit tests/Feature/RecordePessoalPersistenceTest.php tests/Feature/V2AdminRecordeRoutesTest.php
```

### Subindo a API

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

## Telefones internacionais

Novos telefones são validados com `giggsey/libphonenumber-for-php` e armazenados
em E.164 (`+` e DDI). A validação Angular legada não participa dos fluxos atuais:
painel/mobile/site React reutilizam `packages/phone` com `libphonenumber-js/max`.
Brasil é o padrão para **novas entradas nacionais** de clientes antigos; a
migração **não** atribui país a números existentes sem DDI.

Campos: `telefone`, `whatsapp`, `emergencia_telefone`, `responsavel_telefone`,
inclusive em objetos aninhados. Enviar `null` limpa um campo opcional; omiti-lo
preserva o valor. Entradas inválidas retornam HTTP 422 com `errors` por campo.
Webhooks não são normalizados pelo middleware cadastral.

### Publicação e auditoria segura

Faça backup do banco e instale as dependências com `composer install` antes dos
comandos abaixo. Nenhuma conversão deve ser feita automaticamente no deploy.
A auditoria é somente leitura por padrão e não imprime números pessoais:

```bash
php artisan telefones:normalizar --detalhes
```

Ela inventaria as colunas existentes em `usuarios`, `alunos`, `professores` e
`tenants`, verifica índices e classifica números como normalizados, conversíveis,
sem DDI ou inválidos. Não utiliza tabelas de histórico como fonte cadastral.

Ordem de implantação:

1. Criar somente o histórico dos originais:
   `php artisan migrate --path=database/migrations/2026_11_03_200000_create_telefone_normalizacoes_table.php --force`.
2. Auditar os registros e índices no banco de destino.
3. Converter os números com DDI explícito e válido:
   `php artisan telefones:normalizar --aplicar`.
4. Confirmar o país dos registros sem DDI antes de converter. Exemplo restrito:
   `php artisan telefones:normalizar --aplicar --pais-legado=BR --tabela=usuarios --id=123`.
   Não usar `--pais-legado=BR` globalmente sem verificar a origem dos dados.
5. Resolver manualmente valores maiores que 20 caracteres e casos ambíguos,
   sem apagá-los. A migração de schema recusa truncamento antes de alterar colunas.
6. Executar
   `php artisan migrate --path=database/migrations/2026_11_03_201000_telefones_e164_varchar20.php --force`
   e publicar as interfaces junto da API.

O original de cada conversão é preservado em `telefone_normalizacoes` na mesma
transação da atualização. Restrinja acesso a essa tabela: ela contém dados pessoais.
Reexecutar o comando não reconverte números E.164. Os métodos `down` não removem
o histórico nem reduzem a coluna, pois isso poderia destruir números internacionais.

Telefones compartilhados por familiares continuam permitidos: não foi adicionada
unicidade global. Índices existentes são preservados; conversões com colisão
normalizada em índice único são bloqueadas para revisão (a verificação de índices
compostos é conservadora). Buscas aceitam E.164 e os formatos legados equivalentes.

WhatsApp usa `https://wa.me/<E164 sem +>`. Os gateways brasileiros Asaas/Mercado Pago
continuam recebendo somente o número nacional brasileiro em seus campos opcionais:
telefones estrangeiros não são enviados como falsos números brasileiros, mas
permanecem íntegros no cadastro.

### Testes

```bash
php vendor/bin/phpunit tests/Unit/TelefoneTest.php tests/Feature/TelefonesInternacionaisTest.php
# A partir da raiz:
node --test packages/phone/phone.test.mjs painel/scripts/whatsapp.test.mjs
```

Os testes de banco usam SQLite em memória. Execute a auditoria no MySQL de destino
antes da migração; os testes não inspecionam nem modificam o banco de produção.

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
