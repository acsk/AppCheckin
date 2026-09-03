<?php

use App\Http\Controllers\Api\V2\Admin\AssinaturaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)

Route::get('/assinaturas', [AssinaturaController::class, 'index']);
