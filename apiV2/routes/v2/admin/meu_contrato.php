<?php

use App\Http\Controllers\Api\V2\Admin\MeuContratoController;
use Illuminate\Support\Facades\Route;

// Contrato da academia com o Check-in (plano do sistema), pelo admin da própria academia
Route::get('/meu-contrato', [MeuContratoController::class, 'show']);
Route::post('/meu-contrato/pagamentos/{id}/pagar', [MeuContratoController::class, 'pagar'])->where('id', '[0-9]+');
Route::post('/meu-contrato/trocar-plano', [MeuContratoController::class, 'trocarPlano']);
