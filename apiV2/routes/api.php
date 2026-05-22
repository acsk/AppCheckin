<?php

use App\Http\Controllers\Api\V2\AuthController;
use App\Http\Controllers\Api\V2\HealthController;
use App\Http\Controllers\Api\V2\MeController;
use App\Http\Controllers\Api\V2\MobileController;
use Illuminate\Support\Facades\Route;

/*
| Rotas da API v2 (Laravel). Prefixo global: /v2
| JWT compatível com a API Slim (mesmo JWT_SECRET).
*/

Route::prefix('v2')->group(function () {
    Route::get('/ping', [HealthController::class, 'ping']);
    Route::get('/health', [HealthController::class, 'health']);
    Route::get('/health/basic', [HealthController::class, 'healthBasic']);

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/select-tenant-initial', [AuthController::class, 'selectTenantPublic']);
    Route::post('/auth/select-tenant-public', [AuthController::class, 'selectTenantPublic']);
    Route::post('/auth/password-recovery/request', [AuthController::class, 'requestPasswordRecovery']);
    Route::post('/auth/password-recovery/validate-token', [AuthController::class, 'validatePasswordToken']);
    Route::post('/auth/password-recovery/reset', [AuthController::class, 'resetPassword']);

    Route::middleware('jwt.auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/select-tenant', [AuthController::class, 'selectTenant']);
        Route::get('/auth/tenants', [AuthController::class, 'tenants']);
        Route::get('/me', [MeController::class, 'show']);

        Route::prefix('mobile')->group(function () {
            Route::get('/horarios-disponiveis', [MobileController::class, 'horariosDisponiveis']);
            Route::post('/checkin', [MobileController::class, 'registrarCheckin']);
            Route::delete('/checkin/{checkinId}/desfazer', [MobileController::class, 'desfazerCheckin']);
        });
    });
});
