<?php

use App\Http\Controllers\Api\V2\Admin\MatriculaController;
use Illuminate\Support\Facades\Route;

// Rotas estáticas / específicas de matrículas (antes de {id} em routes/api.php)
// Matrícula encerrada com pagamento: vincula à atual em vez de excluir
Route::post('/matriculas/{id}/vincular-atual', [MatriculaController::class, 'vincularAtual'])->whereNumber('id')->middleware('permissao:financeiro_avancado');
Route::post('/matriculas/pacote-contrato/{contratoId}/baixa', [MatriculaController::class, 'darBaixaPacote']);
// Motivos que o admin escolhe ao cancelar (App\Support\MotivoCancelamento)
Route::get('/matriculas/motivos-cancelamento', [MatriculaController::class, 'motivosCancelamento']);
