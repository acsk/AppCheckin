<?php

use App\Http\Controllers\Api\V2\Admin\RecorrenciaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php). Estáticas antes de /{id}.

Route::get('/recorrencias', [RecorrenciaController::class, 'index']);
Route::get('/recorrencias/indicadores', [RecorrenciaController::class, 'indicadores']);
Route::post('/recorrencias/cancelamentos/{cancelamentoId}/confirmar', [RecorrenciaController::class, 'confirmarCancelamento'])->whereNumber('cancelamentoId');
Route::get('/recorrencias/{id}', [RecorrenciaController::class, 'show'])->whereNumber('id');
Route::get('/recorrencias/{id}/simular-cancelamento', [RecorrenciaController::class, 'simular'])->whereNumber('id');
Route::post('/recorrencias/{id}/cancelar', [RecorrenciaController::class, 'cancelar'])->whereNumber('id');
Route::post('/recorrencias/{id}/sincronizar', [RecorrenciaController::class, 'sincronizar'])->whereNumber('id');
