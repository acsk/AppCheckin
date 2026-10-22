<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AcessoSuporteController;
use Illuminate\Support\Facades\Route;

// Encerra o "acessar como academia" (chamado com o token do acesso; contexto: prefix v2 + jwt.auth)
Route::post('/suporte/acesso/encerrar', [AcessoSuporteController::class, 'encerrar']);
