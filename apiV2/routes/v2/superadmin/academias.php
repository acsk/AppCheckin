<?php

use App\Http\Controllers\Api\V2\SuperAdmin\AcademiaController;
use Illuminate\Support\Facades\Route;

Route::get('/academias', [AcademiaController::class, 'index']);
Route::post('/academias', [AcademiaController::class, 'store']);
Route::get('/academias/{id}', [AcademiaController::class, 'show'])->where('id', '[0-9]+');
Route::put('/academias/{id}', [AcademiaController::class, 'update'])->where('id', '[0-9]+');
Route::delete('/academias/{id}', [AcademiaController::class, 'destroy'])->where('id', '[0-9]+');

Route::get('/academias/{tenantId}/admins', [AcademiaController::class, 'listarAdmins'])->where('tenantId', '[0-9]+');
Route::post('/academias/{tenantId}/admin', [AcademiaController::class, 'criarAdmin'])->where('tenantId', '[0-9]+');
Route::put('/academias/{tenantId}/admins/{adminId}', [AcademiaController::class, 'atualizarAdmin'])
    ->where(['tenantId' => '[0-9]+', 'adminId' => '[0-9]+']);
Route::delete('/academias/{tenantId}/admins/{adminId}', [AcademiaController::class, 'desativarAdmin'])
    ->where(['tenantId' => '[0-9]+', 'adminId' => '[0-9]+']);
Route::post('/academias/{tenantId}/admins/{adminId}/reativar', [AcademiaController::class, 'reativarAdmin'])
    ->where(['tenantId' => '[0-9]+', 'adminId' => '[0-9]+']);
