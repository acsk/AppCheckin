<?php

use App\Http\Controllers\Api\V2\Admin\AcessoAlunoController;
use Illuminate\Support\Facades\Route;

// Ver app como aluno: token de 1h, só leitura, com motivo registrado. Fora de admin/alunos de
// propósito: sem regra em PermissaoRotas, exige admin da academia (não basta "editar alunos").
Route::post('/acesso-aluno/{alunoId}', [AcessoAlunoController::class, 'iniciar'])->where('alunoId', '[0-9]+');
