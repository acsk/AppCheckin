<?php

use App\Http\Controllers\Api\V2\DiaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2') + jwt.auth (routes/api.php)
// Rotas estáticas antes das rotas com parâmetro.

Route::get('/dias/horarios', [DiaController::class, 'horariosPorData']);
Route::get('/dias/{diaId}/horarios', [DiaController::class, 'horarios']);
