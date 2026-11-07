<?php

use App\Http\Controllers\Api\V2\Admin\PreMatriculaController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth + permissao.rota.
Route::get('/pre-matriculas', [PreMatriculaController::class, 'index']);
Route::put('/pre-matriculas/{id}', [PreMatriculaController::class, 'update'])->whereNumber('id');
Route::post('/pre-matriculas/{id}/aprovar', [PreMatriculaController::class, 'aprovar'])->whereNumber('id');
Route::post('/pre-matriculas/{id}/reprovar', [PreMatriculaController::class, 'reprovar'])->whereNumber('id');
