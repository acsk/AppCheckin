<?php

use App\Http\Controllers\Api\V2\Admin\AlunoController as AdminAlunoController;
use App\Http\Controllers\Api\V2\Admin\MatriculaController as AdminMatriculaController;
use App\Http\Controllers\Api\V2\Admin\ModalidadeController as AdminModalidadeController;
use App\Http\Controllers\Api\V2\Admin\PlanoCicloController as AdminPlanoCicloController;
use App\Http\Controllers\Api\V2\Admin\PlanoController as AdminPlanoController;
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

        // Planos (JWT) — painel usa GET /planos (não /admin) para listagem
        Route::get('/planos', [AdminPlanoController::class, 'index']);
        Route::get('/planos/{id}', [AdminPlanoController::class, 'show']);

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
            Route::post('/simular-migracao', [MobileController::class, 'simularMigracaoPlano']);
            Route::post('/migrar-plano', [MobileController::class, 'migrarPlano']);
            Route::post('/pagamento/pix', [MobileController::class, 'gerarPagamentoPix']);
            Route::post('/verificar-pagamento', [MobileController::class, 'verificarPagamento']);
            Route::get('/pagamento/reabrir/{matriculaId}', [MobileController::class, 'reabrirPagamentoPendente']);
            Route::get('/assinaturas', [MobileController::class, 'minhasAssinaturas']);
            Route::get('/assinaturas/aprovadas-hoje', [MobileController::class, 'assinaturasAprovadasHoje']);
            Route::post('/assinatura/{id}/cancelar', [MobileController::class, 'cancelarAssinatura']);
            Route::post('/diaria/{matriculaId}/cancelar', [MobileController::class, 'cancelarDiaria']);
        });

        // Painel admin (contrato Slim /admin/* sob prefixo /v2)
        Route::prefix('admin')->middleware('admin.auth')->group(function () {
            Route::get('/modalidades', [AdminModalidadeController::class, 'index']);
            Route::get('/modalidades/{id}', [AdminModalidadeController::class, 'show']);
            Route::post('/modalidades', [AdminModalidadeController::class, 'store']);
            Route::put('/modalidades/{id}', [AdminModalidadeController::class, 'update']);
            Route::delete('/modalidades/{id}', [AdminModalidadeController::class, 'destroy']);

            Route::get('/alunos', [AdminAlunoController::class, 'index']);
            Route::get('/alunos/basico', [AdminAlunoController::class, 'listarBasico']);
            Route::get('/alunos/buscar-cpf/{cpf}', [AdminAlunoController::class, 'buscarPorCpf']);
            Route::post('/alunos/associar', [AdminAlunoController::class, 'associar']);
            Route::get('/alunos/{id}', [AdminAlunoController::class, 'show']);
            Route::get('/alunos/{id}/historico-planos', [AdminAlunoController::class, 'historicoPlanos']);
            Route::get('/alunos/{id}/checkins', [AdminAlunoController::class, 'checkins']);
            Route::get('/alunos/{id}/delete-preview', [AdminAlunoController::class, 'deletePreview']);
            Route::post('/alunos', [AdminAlunoController::class, 'store']);
            Route::put('/alunos/{id}', [AdminAlunoController::class, 'update']);
            Route::delete('/alunos/{id}', [AdminAlunoController::class, 'destroy']);
            Route::delete('/alunos/{id}/hard', [AdminAlunoController::class, 'hardDelete']);

            Route::get('/planos/{id}', [AdminPlanoController::class, 'show']);
            Route::post('/planos', [AdminPlanoController::class, 'store']);
            Route::put('/planos/{id}', [AdminPlanoController::class, 'update']);
            Route::delete('/planos/{id}', [AdminPlanoController::class, 'destroy']);

            Route::get('/assinatura-frequencias', [AdminPlanoCicloController::class, 'listarFrequencias']);
            Route::get('/planos/{planoId}/ciclos', [AdminPlanoCicloController::class, 'listar']);
            Route::post('/planos/{planoId}/ciclos', [AdminPlanoCicloController::class, 'store']);
            Route::post('/planos/{planoId}/ciclos/gerar', [AdminPlanoCicloController::class, 'gerar']);
            Route::put('/planos/{planoId}/ciclos/{id}', [AdminPlanoCicloController::class, 'update']);
            Route::delete('/planos/{planoId}/ciclos/{id}', [AdminPlanoCicloController::class, 'destroy']);

            // Matrículas Wave A+B+C — rotas estáticas / específicas antes de {id}
            Route::get('/matriculas/vencimentos/hoje', [AdminMatriculaController::class, 'vencimentosHoje']);
            Route::get('/matriculas/vencimentos/proximos', [AdminMatriculaController::class, 'proximosVencimentos']);
            Route::post('/matriculas/contas/{id}/baixa', [AdminMatriculaController::class, 'darBaixaConta']);
            Route::get('/matriculas', [AdminMatriculaController::class, 'index']);
            Route::post('/matriculas', [AdminMatriculaController::class, 'store']);
            Route::get('/matriculas/{id}/delete-preview', [AdminMatriculaController::class, 'deletePreview']);
            Route::post('/matriculas/{id}/alterar-plano', [AdminMatriculaController::class, 'alterarPlano']);
            Route::get('/matriculas/{id}', [AdminMatriculaController::class, 'show']);
            Route::get('/matriculas/{id}/pagamentos', [AdminMatriculaController::class, 'pagamentos']);
            Route::post('/matriculas/{id}/bloquear', [AdminMatriculaController::class, 'bloquear']);
            Route::post('/matriculas/{id}/desbloquear', [AdminMatriculaController::class, 'desbloquear']);
            Route::post('/matriculas/{id}/suspender', [AdminMatriculaController::class, 'bloquear']);
            Route::post('/matriculas/{id}/reativar', [AdminMatriculaController::class, 'desbloquear']);
            Route::post('/matriculas/{id}/cancelar', [AdminMatriculaController::class, 'cancelar']);
            Route::put('/matriculas/{id}/proxima-data-vencimento', [AdminMatriculaController::class, 'atualizarProximaDataVencimento']);
            Route::delete('/matriculas/{id}', [AdminMatriculaController::class, 'destroy']);
        });
    });
});
