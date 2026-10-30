<?php

use App\Http\Controllers\Api\V2\Admin\BiController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth + permissao.rota (routes/api.php)
// Inteligência de negócios da academia; tenant sempre o do token.

Route::get('/bi/visao-geral', [BiController::class, 'visaoGeral']);
Route::get('/bi/visao-geral/detalhe', [BiController::class, 'detalhe']);
Route::get('/bi/financeiro', [BiController::class, 'financeiro']);
Route::get('/bi/alunos', [BiController::class, 'alunos']);
Route::get('/bi/frequencia', [BiController::class, 'frequencia']);
Route::get('/bi/retencao', [BiController::class, 'retencao']);
Route::get('/bi/exportar', [BiController::class, 'exportar']);
