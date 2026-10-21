<?php

use App\Http\Controllers\Api\V2\Admin\ProspeccaoController;
use Illuminate\Support\Facades\Route;

/*
| Módulo Prospecção → Recuperação de Alunos.
| Contexto herdado: prefix('v2') + jwt.auth. Fica fora de admin/ porque o papel Comercial
| também acessa (prospeccao.auth confere admin ou comercial no tenant do token).
| Rotas estáticas antes das rotas com {id}.
*/
Route::prefix('admin/prospeccao')->middleware('prospeccao.auth')->group(function () {
    Route::get('/contexto', [ProspeccaoController::class, 'contexto']);
    Route::get('/dashboard', [ProspeccaoController::class, 'dashboard']);
    Route::get('/relatorios/recuperacao', [ProspeccaoController::class, 'relatorio']);
    Route::post('/sincronizar', [ProspeccaoController::class, 'sincronizar']);
    Route::get('/diagnostico', [ProspeccaoController::class, 'diagnostico']);
    Route::get('/alunos-cancelados', [ProspeccaoController::class, 'alunosCancelados']);

    Route::get('/oportunidades', [ProspeccaoController::class, 'listar']);
    Route::post('/oportunidades', [ProspeccaoController::class, 'iniciar']);
    Route::get('/oportunidades/{id}', [ProspeccaoController::class, 'detalhar'])->whereNumber('id');
    Route::patch('/oportunidades/{id}', [ProspeccaoController::class, 'atualizar'])->whereNumber('id');
    Route::patch('/oportunidades/{id}/status', [ProspeccaoController::class, 'alterarStatus'])->whereNumber('id');
    Route::post('/oportunidades/{id}/interacoes', [ProspeccaoController::class, 'registrarInteracao'])->whereNumber('id');
    Route::post('/oportunidades/{id}/remover-restricao', [ProspeccaoController::class, 'removerRestricao'])->whereNumber('id');
    Route::get('/oportunidades/{id}/recuperacao', [ProspeccaoController::class, 'recuperacao'])->whereNumber('id');
    Route::post('/oportunidades/{id}/score', [ProspeccaoController::class, 'recalcularScore'])->whereNumber('id');

    Route::get('/agendamentos', [ProspeccaoController::class, 'agenda']);
    Route::post('/agendamentos', [ProspeccaoController::class, 'criarAgendamento']);
    Route::patch('/agendamentos/{id}', [ProspeccaoController::class, 'atualizarAgendamento'])->whereNumber('id');

    Route::get('/modelos-mensagem', [ProspeccaoController::class, 'listarModelos']);
    Route::post('/modelos-mensagem', [ProspeccaoController::class, 'criarModelo']);
    Route::put('/modelos-mensagem/{id}', [ProspeccaoController::class, 'atualizarModelo'])->whereNumber('id');
    Route::delete('/modelos-mensagem/{id}', [ProspeccaoController::class, 'excluirModelo'])->whereNumber('id');

    // Somente administradores
    Route::middleware('prospeccao.auth:admin')->group(function () {
        Route::get('/score/config', [ProspeccaoController::class, 'scoreConfig']);
        Route::put('/score/config', [ProspeccaoController::class, 'salvarScoreConfig']);
        Route::post('/score/recalcular', [ProspeccaoController::class, 'recalcularTodos']);
        Route::get('/equipe', [ProspeccaoController::class, 'listarEquipe']);
        Route::post('/equipe', [ProspeccaoController::class, 'adicionarEquipe']);
        Route::delete('/equipe/{usuarioId}', [ProspeccaoController::class, 'removerEquipe'])->whereNumber('usuarioId');
    });
});
