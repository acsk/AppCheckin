<?php

use App\Http\Controllers\Api\V2\SuperAdmin\MiscController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/superadmin') + jwt.auth + superadmin.auth (routes/api.php)

Route::get('/papeis', [MiscController::class, 'papeis']);
Route::get('/env', [MiscController::class, 'env']);
Route::get('/planos', [MiscController::class, 'planosAlunos']);
Route::get('/assinaturas', [MiscController::class, 'assinaturas']);
