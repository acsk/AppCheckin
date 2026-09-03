<?php

use App\Http\Controllers\Api\V2\Admin\FormaPagamentoConfigController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes das rotas com {id}.

Route::get('/formas-pagamento-config', [FormaPagamentoConfigController::class, 'index']);
Route::post('/formas-pagamento-config/calcular-taxas', [FormaPagamentoConfigController::class, 'calcularTaxas']);
Route::post('/formas-pagamento-config/calcular-parcelas', [FormaPagamentoConfigController::class, 'calcularParcelas']);
Route::get('/formas-pagamento-config/{id}', [FormaPagamentoConfigController::class, 'show']);
Route::put('/formas-pagamento-config/{id}', [FormaPagamentoConfigController::class, 'update']);
