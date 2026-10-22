<?php

use App\Http\Controllers\Api\V2\SuperAdmin\UnificacaoUsuarioController;
use Illuminate\Support\Facades\Route;

// Unificar cadastros duplicados da mesma pessoa (origem → destino)
Route::get('/usuarios-unificar/previa', [UnificacaoUsuarioController::class, 'previa']);
Route::post('/usuarios-unificar', [UnificacaoUsuarioController::class, 'unificar']);
