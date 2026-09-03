<?php

use App\Http\Controllers\Api\V2\SuperAdmin\ContratoController;
use Illuminate\Support\Facades\Route;

Route::get('/contratos/proximos-vencimento', [ContratoController::class, 'proximosVencimento']);
Route::get('/contratos/vencidos', [ContratoController::class, 'vencidos']);
Route::get('/contratos/{id}', [ContratoController::class, 'show'])->where('id', '[0-9]+');
Route::post('/contratos/{id}/renovar', [ContratoController::class, 'renovar'])->where('id', '[0-9]+');
Route::delete('/contratos/{id}', [ContratoController::class, 'cancelar'])->where('id', '[0-9]+');
Route::get('/contratos', [ContratoController::class, 'index']);

Route::get('/academias/{tenantId}/contratos', [ContratoController::class, 'porAcademia'])->where('tenantId', '[0-9]+');
Route::get('/academias/{tenantId}/contrato-ativo', [ContratoController::class, 'contratoAtivo'])->where('tenantId', '[0-9]+');
Route::post('/academias/{tenantId}/contratos', [ContratoController::class, 'associarPlano'])->where('tenantId', '[0-9]+');
Route::post('/academias/{tenantId}/trocar-plano', [ContratoController::class, 'trocarPlano'])->where('tenantId', '[0-9]+');
