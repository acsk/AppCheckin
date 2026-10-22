<?php

use App\Http\Controllers\Api\V2\Admin\EquipeController;
use Illuminate\Support\Facades\Route;

// Equipe e permissões da academia: admin principal ou quem tiver equipe.gerenciar
Route::middleware('permissao:equipe.gerenciar')->group(function () {
    Route::get('/equipe', [EquipeController::class, 'index']);
    Route::get('/equipe/buscar', [EquipeController::class, 'buscar']);
    Route::post('/equipe', [EquipeController::class, 'store']);
    Route::put('/equipe/{usuarioId}', [EquipeController::class, 'update'])->whereNumber('usuarioId');
    Route::delete('/equipe/{usuarioId}', [EquipeController::class, 'destroy'])->whereNumber('usuarioId');
});
