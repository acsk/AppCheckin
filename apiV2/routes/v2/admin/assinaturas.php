<?php

use App\Http\Controllers\Api\V2\Admin\AssinaturaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes de {id}

Route::get('/assinaturas/proximas-vencer', [AssinaturaController::class, 'proximasVencer']);
Route::get('/assinaturas/sem-matricula', [AssinaturaController::class, 'semMatricula']);
Route::get('/assinaturas/relatorio', [AssinaturaController::class, 'relatorio']);
Route::get('/assinaturas', [AssinaturaController::class, 'index']);
Route::post('/assinaturas', [AssinaturaController::class, 'store']);
Route::get('/assinaturas/{id}', [AssinaturaController::class, 'show']);
Route::put('/assinaturas/{id}', [AssinaturaController::class, 'update']);
Route::post('/assinaturas/{id}/renovar', [AssinaturaController::class, 'renovar']);
Route::post('/assinaturas/{id}/suspender', [AssinaturaController::class, 'suspender']);
Route::post('/assinaturas/{id}/reativar', [AssinaturaController::class, 'reativar']);
Route::post('/assinaturas/{id}/cancelar', [AssinaturaController::class, 'cancelar']);
Route::post('/assinaturas/{id}/sincronizar-matricula', [AssinaturaController::class, 'sincronizarMatricula']);
Route::get('/assinaturas/{id}/status-sincronizacao', [AssinaturaController::class, 'statusSincronizacao']);
Route::get('/alunos/{id}/assinaturas', [AssinaturaController::class, 'listarPorAluno']);
