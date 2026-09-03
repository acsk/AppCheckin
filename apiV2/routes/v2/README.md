# Route partials da migração Slim → apiV2

Um arquivo por módulo. Carregados automaticamente por `routes/api.php`:

| Pasta | Contexto aplicado |
|-------|-------------------|
| `admin/` | dentro de `prefix('v2/admin')` + `jwt.auth` + `admin.auth` |
| `superadmin/` | dentro de `prefix('v2/superadmin')` + `jwt.auth` + `superadmin.auth` |
| `shared/` | dentro de `prefix('v2')` + `jwt.auth` (define o próprio prefixo) |

Dentro de `admin/` e `superadmin/` os paths são relativos (sem repetir o prefixo).

Rotas estáticas devem vir **antes** de rotas com `{id}`.

Status detalhado da migração: `apiV2/docs/MIGRACAO_ROTAS_SLIM.md`  
Denylist do painel: `painel/src/config/apiRouting.js`

---

## Módulos em `admin/` (migrados)

| Arquivo | Rotas principais |
|---------|------------------|
| `usuarios.php` | usuários do tenant (admin) |
| `professores.php` | CRUD professores + CPF + turmas |
| `turmas_dias.php` | turmas, dias admin, replicar, bloqueio check-in |
| `pagamentos_creditos.php` | pagamentos-plano, créditos, descontos, contas a receber |
| `formas_pagamento_config.php` | config formas de pagamento do tenant |
| `payment_credentials.php` | credenciais Mercado Pago |
| `pacotes.php` | pacotes + contratos (contratar, beneficiários, etc.) |
| `pacote_contratos.php` | listar contratos + gerar matrículas |
| `dashboard.php` | `GET /dashboard`, `GET /dashboard/cards` |
| `assinaturas.php` | `GET /assinaturas` (parcial — ver doc migração) |
| `parametros.php` | parâmetros tenant + alias `/configuracoes` |
| `relatorios.php` | `GET /relatorios/planos-ciclos` |
| `wods.php` | WOD admin (CRUD, blocos, variações, resultados) — 22 rotas |
| `recordes.php` | Recordes pessoais admin (definições, recordes, ranking) — 11 rotas |

## Módulos em `shared/` (migrados)

| Arquivo | Rotas principais |
|---------|------------------|
| `tenant_usuarios.php` | `/tenant/usuarios` |
| `dias.php` | `/dias`, horários, período |
| `config_formas.php` | `/formas-pagamento`, `/config/formas-pagamento*`, `/config/status-conta` |

## Pendente (criar arquivo quando migrar)

- `superadmin/*.php` (academias, contratos, planos-sistema, assinaturas, …)
- assinaturas admin (completar rotas além de listagem)
- matrículas subpaths (`matriculas_extras.php`)

---

## Exemplo

`admin/professores.php`:

```php
<?php
use App\Http\Controllers\Api\V2\Admin\ProfessorController;
use Illuminate\Support\Facades\Route;

Route::get('/professores', [ProfessorController::class, 'index']);
Route::get('/professores/{id}', [ProfessorController::class, 'show']);
```
