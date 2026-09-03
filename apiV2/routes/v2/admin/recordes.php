<?php

use App\Http\Controllers\Api\V2\Admin\RecordeController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas de definições e ranking antes de /recordes/{id}.

Route::get('/recordes/definicoes', [RecordeController::class, 'listarDefinicoes']);
Route::post('/recordes/definicoes', [RecordeController::class, 'criarDefinicao']);
Route::get('/recordes/definicoes/{id}', [RecordeController::class, 'buscarDefinicao']);
Route::put('/recordes/definicoes/{id}', [RecordeController::class, 'atualizarDefinicao']);
Route::delete('/recordes/definicoes/{id}', [RecordeController::class, 'excluirDefinicao']);
Route::get('/recordes/ranking/{definicaoId}', [RecordeController::class, 'ranking']);
Route::get('/recordes', [RecordeController::class, 'listarRecordes']);
Route::post('/recordes', [RecordeController::class, 'criarRecorde']);
Route::get('/recordes/{id}', [RecordeController::class, 'buscarRecorde']);
Route::put('/recordes/{id}', [RecordeController::class, 'atualizarRecorde']);
Route::delete('/recordes/{id}', [RecordeController::class, 'excluirRecorde']);
