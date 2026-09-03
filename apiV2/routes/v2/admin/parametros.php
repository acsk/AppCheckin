<?php

use App\Http\Controllers\Api\V2\Admin\ParametroController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes de parâmetros dinâmicos.

Route::get('/parametros/categorias', [ParametroController::class, 'categorias']);
Route::get('/parametros/pagamentos/resumo', [ParametroController::class, 'resumoPagamentos']);
Route::get('/parametros/valor/{codigo}', [ParametroController::class, 'getValue']);
Route::get('/parametros/{categoria}', [ParametroController::class, 'byCategoria']);
Route::get('/parametros', [ParametroController::class, 'index']);
Route::put('/parametros', [ParametroController::class, 'updateMultiple']);
Route::put('/parametros/{codigo}', [ParametroController::class, 'update']);
Route::patch('/parametros/{codigo}/toggle', [ParametroController::class, 'toggle']);
Route::patch('/parametros/{codigo}', [ParametroController::class, 'patch']);

// Alias painel (config tenant = parâmetros)
Route::get('/configuracoes', [ParametroController::class, 'index']);
Route::put('/configuracoes', [ParametroController::class, 'updateMultiple']);
