<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AppConfigController;
use Illuminate\Support\Facades\Route;

// Configuração remota do app: funcionalidades, parâmetros, versões; rascunho → publicação → rollback
Route::get('/app-config', [AppConfigController::class, 'index']);
Route::put('/app-config/valor', [AppConfigController::class, 'definir']);
Route::post('/app-config/publicar', [AppConfigController::class, 'publicar']);
Route::post('/app-config/descartar', [AppConfigController::class, 'descartar']);
Route::post('/app-config/bloqueio/{funcionalidade}', [AppConfigController::class, 'bloquear'])->where('funcionalidade', '[a-z_]+');
Route::get('/app-config/historico', [AppConfigController::class, 'historico']);
Route::post('/app-config/publicacoes/{versao}/restaurar', [AppConfigController::class, 'restaurar'])->where('versao', '[0-9]+');
Route::get('/app-config/efetiva', [AppConfigController::class, 'efetiva']);
