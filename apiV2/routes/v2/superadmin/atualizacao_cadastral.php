<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AtualizacaoCadastralController;
use App\Http\Controllers\Api\V2\SuperAdmin\PreMatriculaController;
use Illuminate\Support\Facades\Route;

// Atualização cadastral do app: campanhas (campos, validação de e-mail, troca de senha)
Route::get('/atualizacoes-cadastrais', [AtualizacaoCadastralController::class, 'index']);
Route::post('/atualizacoes-cadastrais', [AtualizacaoCadastralController::class, 'store']);
Route::put('/atualizacoes-cadastrais/{id}', [AtualizacaoCadastralController::class, 'update'])->where('id', '[0-9]+');
Route::delete('/atualizacoes-cadastrais/{id}', [AtualizacaoCadastralController::class, 'destroy'])->where('id', '[0-9]+');
Route::post('/atualizacoes-cadastrais/{id}/encerrar', [AtualizacaoCadastralController::class, 'encerrar'])->where('id', '[0-9]+');
Route::get('/atualizacoes-cadastrais/{id}/pendentes', [AtualizacaoCadastralController::class, 'pendentes'])->where('id', '[0-9]+');

// Pré-matrícula de migração: configuração por academia e revisão sem criar a matrícula real.
Route::get('/pre-matriculas', [PreMatriculaController::class, 'index']);
Route::put('/pre-matriculas/configuracao', [PreMatriculaController::class, 'update']);
Route::delete('/pre-matriculas/configuracao', [PreMatriculaController::class, 'destroyConfiguracao']);
Route::put('/pre-matriculas/{id}', [PreMatriculaController::class, 'atualizarSolicitacao'])->whereNumber('id');
Route::delete('/pre-matriculas/{id}', [PreMatriculaController::class, 'excluirSolicitacao'])->whereNumber('id');
Route::post('/pre-matriculas/{id}/aprovar', [PreMatriculaController::class, 'aprovar'])->whereNumber('id');
Route::post('/pre-matriculas/{id}/reprovar', [PreMatriculaController::class, 'reprovar'])->whereNumber('id');
