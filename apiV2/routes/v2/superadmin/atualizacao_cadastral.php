<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AtualizacaoCadastralController;
use Illuminate\Support\Facades\Route;

// Atualização cadastral do app: campanhas (campos, validação de e-mail, troca de senha)
Route::get('/atualizacoes-cadastrais', [AtualizacaoCadastralController::class, 'index']);
Route::post('/atualizacoes-cadastrais', [AtualizacaoCadastralController::class, 'store']);
Route::put('/atualizacoes-cadastrais/{id}', [AtualizacaoCadastralController::class, 'update'])->where('id', '[0-9]+');
Route::post('/atualizacoes-cadastrais/{id}/encerrar', [AtualizacaoCadastralController::class, 'encerrar'])->where('id', '[0-9]+');
Route::get('/atualizacoes-cadastrais/{id}/pendentes', [AtualizacaoCadastralController::class, 'pendentes'])->where('id', '[0-9]+');
