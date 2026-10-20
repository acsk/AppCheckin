<?php

use App\Http\Controllers\Api\V2\Admin\ConciliacaoBancariaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)

Route::get('/financeiro/conciliacao', [ConciliacaoBancariaController::class, 'index']);
Route::post('/financeiro/conciliacao/{paymentId}/reprocessar', [ConciliacaoBancariaController::class, 'reprocessar']);
