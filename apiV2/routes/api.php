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
            Route::get('/perfil', [MobileController::class, 'perfil']);
            Route::get('/acesso', [MobileController::class, 'verificarAcesso']);
            Route::get('/tenants', [MobileController::class, 'tenants']);
            Route::get('/checkins', [MobileController::class, 'historicoCheckins']);
            Route::get('/checkins/por-modalidade', [MobileController::class, 'checkinsPorModalidade']);
            Route::get('/ranking/mensal', [MobileController::class, 'rankingMensal']);
            Route::get('/wod/hoje', [MobileController::class, 'wodHoje']);
            Route::get('/wods/hoje', [MobileController::class, 'wodsHoje']);
            Route::get('/horarios-disponiveis', [MobileController::class, 'horariosDisponiveis']);
            Route::post('/checkin', [MobileController::class, 'registrarCheckin']);
            Route::delete('/checkin/{checkinId}/desfazer', [MobileController::class, 'desfazerCheckin']);

            Route::get('/planos-disponiveis', [MobileController::class, 'planosDisponiveis']);
            Route::get('/planos', [MobileController::class, 'planosDoUsuario']);
            Route::get('/planos/{planoId}', [MobileController::class, 'detalhePlano']);
            Route::get('/matriculas/{matriculaId}', [MobileController::class, 'detalheMatricula']);
            Route::post('/comprar-plano', [MobileController::class, 'comprarPlano']);
            Route::post('/pagamento/pix', [MobileController::class, 'gerarPagamentoPix']);
            Route::post('/verificar-pagamento', [MobileController::class, 'verificarPagamento']);
            Route::get('/pagamento/reabrir/{matriculaId}', [MobileController::class, 'reabrirPagamentoPendente']);
            Route::get('/assinaturas', [MobileController::class, 'minhasAssinaturas']);
            Route::get('/assinaturas/aprovadas-hoje', [MobileController::class, 'assinaturasAprovadasHoje']);
            Route::post('/assinatura/{id}/cancelar', [MobileController::class, 'cancelarAssinatura']);
            Route::post('/diaria/{matriculaId}/cancelar', [MobileController::class, 'cancelarDiaria']);
        });
    });
});
