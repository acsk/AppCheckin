<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AssinaturaController;
use Illuminate\Support\Facades\Route;

Route::get('/assinaturas/{id}/cobrancas', [AssinaturaController::class, 'cobrancas']);
Route::post('/assinaturas/{id}/cobrancas/{paymentId}/cancelar', [AssinaturaController::class, 'cancelarCobranca']);
Route::get('/assinaturas', [AssinaturaController::class, 'index']);
