<?php

use App\Http\Controllers\Api\V2\Admin\AcessoAlunoController;
use Illuminate\Support\Facades\Route;

// Encerra o "ver app como aluno" (chamado pelo app com o token do acesso; contexto: prefix v2 + jwt.auth)
Route::post('/suporte/aluno/encerrar', [AcessoAlunoController::class, 'encerrar']);
