# Route partials da migração Slim → apiV2

Um arquivo por módulo. Carregados automaticamente por `routes/api.php`:

| Pasta | Contexto aplicado |
|-------|-------------------|
| `admin/` | dentro de `prefix('v2/admin')` + `jwt.auth` + `admin.auth` |
| `superadmin/` | dentro de `prefix('v2/superadmin')` + `jwt.auth` + `superadmin.auth` |
| `shared/` | dentro de `prefix('v2')` + `jwt.auth` (define o próprio prefixo) |

Dentro de `admin/` e `superadmin/` os paths são relativos (sem repetir o prefixo).

Exemplo `admin/professores.php`:

```php
<?php
use App\Http\Controllers\Api\V2\Admin\ProfessorController;
use Illuminate\Support\Facades\Route;

Route::get('/professores', [ProfessorController::class, 'index']);
Route::get('/professores/{id}', [ProfessorController::class, 'show']);
```

Rotas estáticas devem vir antes de rotas com `{id}`.
