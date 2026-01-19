# ✅ Dashboard Endpoints - Especificação

## Visão Geral
Endpoints para obter contadores e estatísticas do dashboard para uso no frontend.

**Requisição:** `GET /admin/dashboard/*`  
**Autenticação:** Sim (Bearer Token)  
**Acesso:** Admin (role_id = 2) ou Super Admin (role_id = 3)  
**Grupo de Rotas:** `/admin/`

---

## Rotas Disponíveis

1. `GET /admin/dashboard` - Contadores principais
2. `GET /admin/dashboard/turmas-por-modalidade` - Turmas agrupadas por modalidade
3. `GET /admin/dashboard/alunos-por-modalidade` - Alunos agrupados por modalidade
4. `GET /admin/dashboard/checkins-últimos-7-dias` - Check-ins dos últimos 7 dias

---

## Permissões Necessárias

### ✅ Quem pode acessar:
- **Admin** (role_id = 2) ← Principal usuário
- **Super Admin** (role_id = 3)

### ❌ Quem NÃO pode acessar:
- Aluno (role_id = 1)
- Usuários não autenticados

---

## Implementação no Backend

### Laravel/PHP

```php
// routes/api.php
Route::middleware(['auth:api', 'role:2,3'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/turmas-por-modalidade', [DashboardController::class, 'turmasPorModalidade']);
    Route::get('/dashboard/alunos-por-modalidade', [DashboardController::class, 'alunosPorModalidade']);
    Route::get('/dashboard/checkins-últimos-7-dias', [DashboardController::class, 'checkinsUltimos7Dias']);
});
```

### Middleware de Verificação

```php
// app/Http/Middleware/CheckRole.php
public function handle($request, Closure $next, ...$roles)
{
    $userRoleId = $request->user()->role_id;
    
    // Converter roles para array de inteiros
    $allowedRoles = array_map('intval', $roles);
    
    if (!in_array($userRoleId, $allowedRoles)) {
        return response()->json([
            'erro' => 'Acesso negado. Administrador necessário.',
            'role_necessaria' => implode(',', $allowedRoles),
            'role_atual' => $userRoleId
        ], 403);
    }
    
    return $next($request);
}
```

---

## Regras de Negócio

### Filtros Aplicados
- **tenant_id**: Todos os dados são filtrados pelo tenant do usuário autenticado
- **ativo = 1**: Apenas registros ativos são contabilizados
- **Data atual**: Para check-ins e receita do mês

### Cálculo de Receita
```sql
-- Receita Paga: status IN ('concluido', 'processando')
-- Receita Pendente: status IN ('pendente', 'aguardando')
-- Total: Soma de pago + pendente
```

---

## Status da Implementação

### ✅ Frontend (Implementado)
- Service: `src/services/dashboardService.js`
- Componente: `src/screens/Dashboard/index.js`
- Tratamento de erros de permissão
- Loading states
- Formatação de dados

### ⚠️ Backend (Requer configuração)
As rotas devem estar configuradas para aceitar:
- `role_id = 2` (admin) ✅ **Principal**
- `role_id = 3` (super_admin) ✅ **Adicional**

---

## Teste de Permissões

```bash
# ✅ Deve funcionar - Admin (role_id = 2)
curl -X GET http://localhost:8080/admin/dashboard \
  -H "Authorization: Bearer token_admin"

# ✅ Deve funcionar - Super Admin (role_id = 3)  
curl -X GET http://localhost:8080/admin/dashboard \
  -H "Authorization: Bearer token_super_admin"

# ❌ Deve retornar 403 - Aluno (role_id = 1)
curl -X GET http://localhost:8080/admin/dashboard \
  -H "Authorization: Bearer token_aluno"
```

---

## Exemplo de Resposta (200 OK)

```json
{
  "type": "success",
  "data": {
    "alunos": 45,
    "turmas": 12,
    "professores": 8,
    "modalidades": 4,
    "checkins_hoje": 23,
    "matrículas_ativas": 38,
    "receita_mes": {
      "pago": 5000.00,
      "pendente": 1500.00,
      "total": 6500.00,
      "mes": "2026-01"
    },
    "contratos_ativos": 15
  }
}
```

---

## Notas Importantes

- Todos os dados são filtrados pelo `tenant_id` do usuário autenticado
- Apenas usuários com papel `admin` (role_id = 2) ou `super_admin` (role_id = 3) podem acessar
- Os contadores consideram apenas registros `ativo = 1`
- Datas estão no fuso horário da aplicação
- Para receita: apenas pagamentos com status `concluido` ou `processando` são contados como pago

---

## Ver Também

- Documentação completa: Ver documentação fornecida pelo usuário
- Frontend implementado: `src/screens/Dashboard/index.js`
- Service: `src/services/dashboardService.js`
