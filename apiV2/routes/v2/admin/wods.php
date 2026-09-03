<?php

use App\Http\Controllers\Api\V2\Admin\WodBlocoController;
use App\Http\Controllers\Api\V2\Admin\WodController;
use App\Http\Controllers\Api\V2\Admin\WodResultadoController;
use App\Http\Controllers\Api\V2\Admin\WodVariacaoController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes de /wods/{id}.

Route::get('/wods/modalidades', [WodController::class, 'listarModalidades']);
Route::get('/wods/buscar', [WodController::class, 'buscar']);
Route::get('/wods', [WodController::class, 'index']);
Route::post('/wods', [WodController::class, 'store']);
Route::post('/wods/completo', [WodController::class, 'storeCompleto']);
Route::get('/wods/{id}', [WodController::class, 'show']);
Route::put('/wods/{id}', [WodController::class, 'update']);
Route::delete('/wods/{id}', [WodController::class, 'destroy']);
Route::patch('/wods/{id}/publish', [WodController::class, 'publish']);
Route::patch('/wods/{id}/archive', [WodController::class, 'archive']);

Route::get('/wods/{wodId}/blocos', [WodBlocoController::class, 'index']);
Route::post('/wods/{wodId}/blocos', [WodBlocoController::class, 'store']);
Route::put('/wods/{wodId}/blocos/{id}', [WodBlocoController::class, 'update']);
Route::delete('/wods/{wodId}/blocos/{id}', [WodBlocoController::class, 'destroy']);

Route::get('/wods/{wodId}/variacoes', [WodVariacaoController::class, 'index']);
Route::post('/wods/{wodId}/variacoes', [WodVariacaoController::class, 'store']);
Route::put('/wods/{wodId}/variacoes/{id}', [WodVariacaoController::class, 'update']);
Route::delete('/wods/{wodId}/variacoes/{id}', [WodVariacaoController::class, 'destroy']);

Route::get('/wods/{wodId}/resultados', [WodResultadoController::class, 'index']);
Route::post('/wods/{wodId}/resultados', [WodResultadoController::class, 'store']);
Route::put('/wods/{wodId}/resultados/{id}', [WodResultadoController::class, 'update']);
Route::delete('/wods/{wodId}/resultados/{id}', [WodResultadoController::class, 'destroy']);
