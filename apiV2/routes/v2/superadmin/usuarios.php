<?php

use App\Http\Controllers\Api\V2\SuperAdmin\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::get('/usuarios', [UsuarioController::class, 'index']);
Route::get('/usuarios/{id}', [UsuarioController::class, 'show'])->where('id', '[0-9]+');
Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->where('id', '[0-9]+');
Route::delete('/usuarios/{id}', [UsuarioController::class, 'destroy'])->where('id', '[0-9]+');
