<?php

use App\Http\Controllers\Api\V2\SuperAdmin\PlanoSistemaController;
use Illuminate\Support\Facades\Route;

Route::get('/planos-sistema/disponiveis', [PlanoSistemaController::class, 'disponiveis']);
Route::get('/planos-sistema/{id}/academias', [PlanoSistemaController::class, 'academias'])->where('id', '[0-9]+');
Route::post('/planos-sistema/{id}/marcar-historico', [PlanoSistemaController::class, 'marcarHistorico'])->where('id', '[0-9]+');
Route::get('/planos-sistema/{id}', [PlanoSistemaController::class, 'show'])->where('id', '[0-9]+');
Route::put('/planos-sistema/{id}', [PlanoSistemaController::class, 'update'])->where('id', '[0-9]+');
Route::delete('/planos-sistema/{id}', [PlanoSistemaController::class, 'destroy'])->where('id', '[0-9]+');
Route::get('/planos-sistema', [PlanoSistemaController::class, 'index']);
Route::post('/planos-sistema', [PlanoSistemaController::class, 'store']);
