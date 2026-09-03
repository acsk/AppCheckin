<?php

use App\Http\Controllers\Api\V2\ConfigController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2') + jwt.auth (routes/api.php)

Route::get('/formas-pagamento', [ConfigController::class, 'formasPagamento']);
Route::get('/config/formas-pagamento', [ConfigController::class, 'listarFormasPagamento']);
Route::get('/config/formas-pagamento-ativas', [ConfigController::class, 'listarFormasPagamentoAtivas']);
Route::get('/config/status-conta', [ConfigController::class, 'listarStatusConta']);
