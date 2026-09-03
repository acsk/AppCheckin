<?php

use App\Http\Controllers\Api\V2\Admin\UsuarioController;
use Illuminate\Support\Facades\Route;

/*
| Slim: grupo /tenant com AdminMiddleware + AuthMiddleware.
| Contexto herdado: prefix('v2') + jwt.auth.
*/
Route::prefix('tenant')->middleware('admin.auth')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index']);
    Route::post('/usuarios', [UsuarioController::class, 'store']);
    Route::post('/usuarios/associar', [UsuarioController::class, 'associar']);
    Route::get('/usuarios/buscar-cpf/{cpf}', [UsuarioController::class, 'buscarPorCpf']);
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show']);
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy']);
});

// Slim: rota do grupo protegido só com AuthMiddleware (qualquer papel autenticado).
Route::get('/usuarios/{id}/estatisticas', [UsuarioController::class, 'estatisticas']);
