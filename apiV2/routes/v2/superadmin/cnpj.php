<?php

use App\Http\Controllers\Api\V2\SuperAdmin\CnpjController;
use Illuminate\Support\Facades\Route;

// CNPJ compartilhado: política global, exceções e gestor cadastral (estáticas antes de {cnpj})
Route::get('/cnpj/politica', [CnpjController::class, 'politica']);
Route::put('/cnpj/politica', [CnpjController::class, 'definirPolitica']);
Route::post('/cnpj/excecoes/{id}/revogar', [CnpjController::class, 'revogarExcecao'])->where('id', '[0-9]+');
Route::get('/cnpj', [CnpjController::class, 'index']);
Route::get('/cnpj/{cnpj}', [CnpjController::class, 'show'])->where('cnpj', '[0-9]{14}');
Route::put('/cnpj/{cnpj}', [CnpjController::class, 'update'])->where('cnpj', '[0-9]{14}');
Route::post('/cnpj/{cnpj}/excecoes', [CnpjController::class, 'autorizarExcecao'])->where('cnpj', '[0-9]{14}');
Route::post('/cnpj/{cnpj}/gestor', [CnpjController::class, 'definirGestor'])->where('cnpj', '[0-9]{14}');
