<?php

use App\Http\Controllers\Api\V2\Admin\PaymentCredentialsController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rota estática /test antes de qualquer {id} futuro.

Route::get('/payment-credentials', [PaymentCredentialsController::class, 'obter']);
Route::post('/payment-credentials', [PaymentCredentialsController::class, 'salvar']);
Route::post('/payment-credentials/test', [PaymentCredentialsController::class, 'testar']);
