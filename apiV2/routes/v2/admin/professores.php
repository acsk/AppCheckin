<?php

use App\Http\Controllers\Api\V2\Admin\ProfessorController;
use Illuminate\Support\Facades\Route;

// Contexto: prefix('v2/admin') + jwt.auth + admin.auth (routes/api.php)
// Rotas estáticas antes das rotas com {id}.

Route::get('/professores', [ProfessorController::class, 'index']);
Route::post('/professores', [ProfessorController::class, 'store']);
Route::get('/professores/global/cpf/{cpf}', [ProfessorController::class, 'buscarPorCpfGlobal']);
Route::get('/professores/cpf/{cpf}', [ProfessorController::class, 'buscarPorCpf']);
Route::get('/professores/{professorId}/turmas', [ProfessorController::class, 'turmas']);
Route::get('/professores/{id}', [ProfessorController::class, 'show']);
Route::put('/professores/{id}', [ProfessorController::class, 'update']);
Route::delete('/professores/{id}', [ProfessorController::class, 'destroy']);
