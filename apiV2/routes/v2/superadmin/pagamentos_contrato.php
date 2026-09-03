<?php

use App\Http\Controllers\Api\V2\SuperAdmin\PagamentoContratoController;
use Illuminate\Support\Facades\Route;

Route::get('/pagamentos-contrato/resumo', [PagamentoContratoController::class, 'resumo']);
Route::post('/pagamentos-contrato/marcar-atrasados', [PagamentoContratoController::class, 'marcarAtrasados']);
Route::post('/pagamentos-contrato/{id}/confirmar', [PagamentoContratoController::class, 'confirmar'])->where('id', '[0-9]+');
Route::delete('/pagamentos-contrato/{id}', [PagamentoContratoController::class, 'destroy'])->where('id', '[0-9]+');
Route::get('/pagamentos-contrato', [PagamentoContratoController::class, 'index']);

Route::get('/contratos/{id}/pagamentos-contrato', [PagamentoContratoController::class, 'listarPorContrato'])->where('id', '[0-9]+');
Route::post('/contratos/{id}/pagamentos-contrato', [PagamentoContratoController::class, 'store'])->where('id', '[0-9]+');
