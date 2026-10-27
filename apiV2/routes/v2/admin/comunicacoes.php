<?php

use App\Http\Controllers\Api\V2\Admin\ComunicacaoController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php). Estáticas antes de /{id}.

Route::get('/comunicacoes', [ComunicacaoController::class, 'index']);
Route::post('/comunicacoes', [ComunicacaoController::class, 'store']);
Route::get('/comunicacoes/opcoes', [ComunicacaoController::class, 'opcoes']);
Route::get('/comunicacoes/indicadores', [ComunicacaoController::class, 'indicadores']);
Route::post('/comunicacoes/previa-publico', [ComunicacaoController::class, 'previaPublico']);
Route::post('/comunicacoes/anexos', [ComunicacaoController::class, 'anexo']);
Route::get('/comunicacoes/{id}', [ComunicacaoController::class, 'show'])->whereNumber('id');
Route::put('/comunicacoes/{id}', [ComunicacaoController::class, 'update'])->whereNumber('id');
Route::delete('/comunicacoes/{id}', [ComunicacaoController::class, 'destroy'])->whereNumber('id');
Route::post('/comunicacoes/{id}/duplicar', [ComunicacaoController::class, 'duplicar'])->whereNumber('id');
Route::post('/comunicacoes/{id}/publicar', [ComunicacaoController::class, 'publicar'])->whereNumber('id');
Route::post('/comunicacoes/{id}/incluir-destinatarios', [ComunicacaoController::class, 'incluirDestinatarios'])->whereNumber('id');
Route::post('/comunicacoes/{id}/encerrar', [ComunicacaoController::class, 'encerrar'])->whereNumber('id');
Route::post('/comunicacoes/{id}/cancelar', [ComunicacaoController::class, 'cancelar'])->whereNumber('id');
Route::get('/comunicacoes/{id}/destinatarios', [ComunicacaoController::class, 'destinatarios'])->whereNumber('id');
Route::get('/comunicacoes/{id}/resultados', [ComunicacaoController::class, 'resultados'])->whereNumber('id');
Route::get('/comunicacoes/{id}/respostas', [ComunicacaoController::class, 'respostas'])->whereNumber('id');
