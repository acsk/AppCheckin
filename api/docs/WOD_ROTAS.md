# Rotas para WOD

Adicione estas rotas ao arquivo de configuração de rotas (provavelmente em `routes/api.php` ou similar):

```php
// Grupo de WODs - Admin
$app->group('/admin/wods', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Listar WODs
    $group->get('', '\App\Controllers\WodController:index');
    
    // Criar novo WOD
    $group->post('', '\App\Controllers\WodController:create');
    
    // Obter detalhes de um WOD
    $group->get('/{id}', '\App\Controllers\WodController:show');
    
    // Atualizar WOD
    $group->put('/{id}', '\App\Controllers\WodController:update');
    
    // Deletar WOD
    $group->delete('/{id}', '\App\Controllers\WodController:delete');
    
    // Publicar WOD
    $group->patch('/{id}/publish', '\App\Controllers\WodController:publish');
    
    // Arquivar WOD
    $group->patch('/{id}/archive', '\App\Controllers\WodController:archive');
})->add(new \App\Middlewares\TenantMiddleware())->add(new \App\Middlewares\AuthMiddleware());

// Grupo de Blocos de WOD
$app->group('/admin/wods/{wodId}/blocos', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Listar blocos
    $group->get('', '\App\Controllers\WodBlocoController:index');
    
    // Criar bloco
    $group->post('', '\App\Controllers\WodBlocoController:create');
    
    // Atualizar bloco
    $group->put('/{id}', '\App\Controllers\WodBlocoController:update');
    
    // Deletar bloco
    $group->delete('/{id}', '\App\Controllers\WodBlocoController:delete');
})->add(new \App\Middlewares\TenantMiddleware())->add(new \App\Middlewares\AuthMiddleware());

// Grupo de Variações de WOD
$app->group('/admin/wods/{wodId}/variacoes', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Listar variações
    $group->get('', '\App\Controllers\WodVariacaoController:index');
    
    // Criar variação
    $group->post('', '\App\Controllers\WodVariacaoController:create');
    
    // Atualizar variação
    $group->put('/{id}', '\App\Controllers\WodVariacaoController:update');
    
    // Deletar variação
    $group->delete('/{id}', '\App\Controllers\WodVariacaoController:delete');
})->add(new \App\Middlewares\TenantMiddleware())->add(new \App\Middlewares\AuthMiddleware());

// Grupo de Resultados de WOD
$app->group('/admin/wods/{wodId}/resultados', function (\Slim\Routing\RouteCollectorProxy $group) {
    // Listar resultados (leaderboard)
    $group->get('', '\App\Controllers\WodResultadoController:index');
    
    // Registrar resultado
    $group->post('', '\App\Controllers\WodResultadoController:create');
    
    // Atualizar resultado
    $group->put('/{id}', '\App\Controllers\WodResultadoController:update');
    
    // Deletar resultado
    $group->delete('/{id}', '\App\Controllers\WodResultadoController:delete');
})->add(new \App\Middlewares\TenantMiddleware())->add(new \App\Middlewares\AuthMiddleware());
```

## Exemplo de Uso

### Listar WODs
```
GET /admin/wods
GET /admin/wods?status=published
GET /admin/wods?data=2026-01-14
GET /admin/wods?data_inicio=2026-01-01&data_fim=2026-01-31
```

### Criar WOD
```
POST /admin/wods
Content-Type: application/json

{
  "titulo": "WOD 14/01/2026",
  "descricao": "Treino de força e resistência",
  "data": "2026-01-14",
  "status": "draft"
}
```

### Obter WOD com todos os detalhes
```
GET /admin/wods/{id}
```

### Atualizar WOD
```
PUT /admin/wods/{id}
Content-Type: application/json

{
  "titulo": "WOD atualizado",
  "status": "published"
}
```

### Publicar WOD
```
PATCH /admin/wods/{id}/publish
```

### Adicionar bloco ao WOD
```
POST /admin/wods/{wodId}/blocos
Content-Type: application/json

{
  "ordem": 1,
  "tipo": "warmup",
  "titulo": "5 min de aquecimento",
  "conteudo": "1 min rope skip\n1 min row\n2 min mobilidade",
  "tempo_cap": "5 min"
}
```
