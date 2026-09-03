<?php

use App\Http\Controllers\Api\V2\Admin\DiaController as AdminDiaController;
use App\Http\Controllers\Api\V2\Admin\TurmaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes das rotas com {id}.

Route::get('/turmas', [TurmaController::class, 'index']);
Route::post('/turmas', [TurmaController::class, 'store']);
Route::post('/turmas/replicar', [TurmaController::class, 'replicar']);
Route::post('/turmas/replicar-semana', [TurmaController::class, 'replicarSemana']);
Route::post('/turmas/desativar', [TurmaController::class, 'desativar']);
Route::get('/turmas/{id}/vagas', [TurmaController::class, 'vagas']);
Route::post('/turmas/{id}/bloquear-checkin', [TurmaController::class, 'bloquearCheckin']);
Route::post('/turmas/{id}/desbloquear-checkin', [TurmaController::class, 'desbloquearCheckin']);
Route::get('/turmas/{id}', [TurmaController::class, 'show']);
Route::put('/turmas/{id}', [TurmaController::class, 'update']);
Route::delete('/turmas/{id}/permanente', [TurmaController::class, 'destroyPermanente']);
Route::delete('/turmas/{id}', [TurmaController::class, 'destroy']);

Route::get('/dias', [AdminDiaController::class, 'index']);
Route::post('/dias/desativar', [AdminDiaController::class, 'desativar']);
Route::delete('/dias/{id}/horarios', [AdminDiaController::class, 'deletarHorarios']);
