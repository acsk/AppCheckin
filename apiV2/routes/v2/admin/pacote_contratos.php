<?php

use App\Http\Controllers\Api\V2\Admin\PacoteContratoController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)

Route::get('/pacote-contratos', [PacoteContratoController::class, 'index']);
Route::post('/pacote-contratos/{contratoId}/gerar-matriculas', [PacoteContratoController::class, 'gerarMatriculas']);
