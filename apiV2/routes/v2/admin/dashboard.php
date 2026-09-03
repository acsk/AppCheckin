<?php

use App\Http\Controllers\Api\V2\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rota estática /cards antes de qualquer {id} futuro.

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::get('/dashboard/cards', [DashboardController::class, 'cards']);
