<?php

use App\Http\Controllers\Api\V2\Admin\MinhaContaController;
use Illuminate\Support\Facades\Route;

// Minha Conta: perfil/senha do usuário logado, dados operacionais da escola e dados do CNPJ.
// Plano e assinatura: /meu-contrato (meu_contrato.php).
Route::get('/minha-conta/perfil', [MinhaContaController::class, 'perfil']);
Route::put('/minha-conta/perfil', [MinhaContaController::class, 'atualizarPerfil']);
Route::put('/minha-conta/senha', [MinhaContaController::class, 'alterarSenha']);
Route::get('/minha-conta/escola', [MinhaContaController::class, 'escola']);
Route::put('/minha-conta/escola', [MinhaContaController::class, 'atualizarEscola']);
Route::get('/minha-conta/cnpj', [MinhaContaController::class, 'cnpj']);
Route::put('/minha-conta/cnpj', [MinhaContaController::class, 'atualizarCnpj']);
