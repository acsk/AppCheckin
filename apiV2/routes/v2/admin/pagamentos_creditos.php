<?php

use App\Http\Controllers\Api\V2\Admin\ContasReceberController;
use App\Http\Controllers\Api\V2\Admin\CreditoAlunoController;
use App\Http\Controllers\Api\V2\Admin\MatriculaDescontoController;
use App\Http\Controllers\Api\V2\Admin\PagamentoPlanoController;
use Illuminate\Support\Facades\Route;

/*
| Pagamentos, créditos, descontos e contas a receber.
| Contexto: prefix('v2/admin') + jwt.auth + admin.auth.
| Rotas estáticas antes de {id}.
*/

// Contas a receber
Route::get('/contas-receber', [ContasReceberController::class, 'index']);
Route::get('/contas-receber/relatorio', [ContasReceberController::class, 'relatorio']);
Route::get('/contas-receber/estatisticas', [ContasReceberController::class, 'estatisticas']);
Route::post('/contas-receber/{id}/baixa', [ContasReceberController::class, 'darBaixa']);
Route::post('/contas-receber/{id}/cancelar', [ContasReceberController::class, 'cancelar']);

// Pagamentos-plano (estáticas)
Route::get('/pagamentos-plano', [PagamentoPlanoController::class, 'index']);
Route::get('/pagamentos-plano/resumo', [PagamentoPlanoController::class, 'resumo']);
Route::post('/pagamentos-plano/marcar-atrasados', [PagamentoPlanoController::class, 'marcarAtrasados']);

// Matrículas — subpaths antes do {id} genérico do MatriculaController principal
Route::get('/matriculas/{matriculaId}/pagamentos-plano', [PagamentoPlanoController::class, 'listarPorMatricula']);
Route::post('/matriculas/{matriculaId}/pagamentos-plano', [PagamentoPlanoController::class, 'store']);
Route::get('/matriculas/{matriculaId}/descontos', [MatriculaDescontoController::class, 'listar']);
Route::post('/matriculas/{matriculaId}/descontos', [MatriculaDescontoController::class, 'store']);

Route::get('/usuarios/{usuarioId}/pagamentos-plano', [PagamentoPlanoController::class, 'listarPorUsuario']);

// Créditos de aluno
Route::get('/alunos/{alunoId}/creditos', [CreditoAlunoController::class, 'listarPorAluno']);
Route::get('/alunos/{alunoId}/creditos/saldo', [CreditoAlunoController::class, 'saldo']);
Route::post('/alunos/{alunoId}/creditos', [CreditoAlunoController::class, 'store']);
Route::delete('/creditos/{id}', [CreditoAlunoController::class, 'cancelar']);

// Descontos isolados
Route::get('/matricula-descontos/{id}', [MatriculaDescontoController::class, 'show']);
Route::put('/matricula-descontos/{id}', [MatriculaDescontoController::class, 'update']);
Route::delete('/matricula-descontos/{id}', [MatriculaDescontoController::class, 'destroy']);

// Pagamentos-plano por ID (subpaths específicos antes do DELETE genérico)
Route::get('/pagamentos-plano/{id}', [PagamentoPlanoController::class, 'show']);
Route::put('/pagamentos-plano/{id}', [PagamentoPlanoController::class, 'update']);
Route::post('/pagamentos-plano/{id}/confirmar', [PagamentoPlanoController::class, 'confirmar']);
Route::delete('/pagamentos-plano/{id}/excluir', [PagamentoPlanoController::class, 'excluir']);
Route::delete('/pagamentos-plano/{id}', [PagamentoPlanoController::class, 'cancelar']);
