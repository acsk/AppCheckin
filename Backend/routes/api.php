<?php

use App\Controllers\AuthController;
use App\Controllers\DiaController;
use App\Controllers\CheckinController;
use App\Controllers\UsuarioController;
use App\Controllers\TurmaController;
use App\Controllers\AdminController;
use App\Controllers\PlanoController;
use App\Controllers\PlanejamentoController;
use App\Controllers\ContasReceberController;
use App\Controllers\MatriculaController;
use App\Controllers\ConfigController;
use App\Controllers\SuperAdminController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\TenantMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\SuperAdminMiddleware;

return function ($app) {
    // Aplicar TenantMiddleware globalmente
    $app->add(TenantMiddleware::class);
    
    // Rotas públicas
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);
    
    // Logout (protegido para validar token)
    $app->post('/auth/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
    
    // Seleção de tenant/academia (protegido, mas não precisa de tenant no contexto ainda)
    $app->post('/auth/select-tenant', [AuthController::class, 'selectTenant'])->add(AuthMiddleware::class);

    // ========================================
    // Rotas Super Admin (role_id = 3)
    // ========================================
    $app->group('/superadmin', function ($group) {
        // Gerenciar academias
        $group->get('/academias', [SuperAdminController::class, 'listarAcademias']);
        $group->get('/academias/{id}', [SuperAdminController::class, 'buscarAcademia']);
        $group->post('/academias', [SuperAdminController::class, 'criarAcademia']);
        $group->put('/academias/{id}', [SuperAdminController::class, 'atualizarAcademia']);
        $group->delete('/academias/{id}', [SuperAdminController::class, 'excluirAcademia']);
        $group->post('/academias/{tenantId}/admin', [SuperAdminController::class, 'criarAdminAcademia']);
    })->add(SuperAdminMiddleware::class)->add(AuthMiddleware::class);

    // ========================================
    // Rotas Protegidas (Usuários Autenticados)
    // ========================================
    // Rotas protegidas
    $app->group('', function ($group) {
        // Usuário
        $group->get('/me', [UsuarioController::class, 'me']);
        $group->put('/me', [UsuarioController::class, 'update']);
        $group->get('/usuarios/{id}/estatisticas', [UsuarioController::class, 'estatisticas']);
        
        // Dias disponíveis
        $group->get('/dias', [DiaController::class, 'index']);
        $group->get('/dias/proximos', [DiaController::class, 'diasProximos']);
        $group->get('/dias/horarios', [DiaController::class, 'horariosPorData']);
        $group->get('/dias/{id}/horarios', [DiaController::class, 'horarios']);
        
        // Check-ins
        $group->post('/checkin', [CheckinController::class, 'store']);
        $group->get('/me/checkins', [CheckinController::class, 'myCheckins']);
        $group->delete('/checkin/{id}', [CheckinController::class, 'cancel']);
        
        // Gestão de Turmas
        $group->get('/turmas', [TurmaController::class, 'index']);
        $group->get('/turmas/hoje', [TurmaController::class, 'hoje']);
        $group->get('/turmas/{id}/alunos', [TurmaController::class, 'alunos']);
        
        // Planos (público para alunos verem)
        $group->get('/planos', [PlanoController::class, 'index']);
        $group->get('/planos/{id}', [PlanoController::class, 'show']);
        
        // Configurações (formas de pagamento e status)
        $group->get('/config/formas-pagamento', [ConfigController::class, 'listarFormasPagamento']);
        $group->get('/config/status-conta', [ConfigController::class, 'listarStatusConta']);
    })->add(AuthMiddleware::class);

    // ========================================
    // Rotas Admin (role_id = 2 ou 3)
    // ========================================
    $app->group('/admin', function ($group) {
        // Dashboard e estatísticas
        $group->get('/dashboard', [AdminController::class, 'dashboard']);
        
        // Gestão de Alunos
        $group->get('/alunos', [AdminController::class, 'listarAlunos']);
        $group->get('/alunos/basico', [AdminController::class, 'listarAlunosBasico']);
        $group->get('/alunos/{id}', [AdminController::class, 'buscarAluno']);
        $group->get('/alunos/{id}/historico-planos', [AdminController::class, 'historicoPlanos']);
        $group->post('/alunos', [AdminController::class, 'criarAluno']);
        $group->put('/alunos/{id}', [AdminController::class, 'atualizarAluno']);
        $group->delete('/alunos/{id}', [AdminController::class, 'desativarAluno']);
        
        // Gestão de Planos
        $group->post('/planos', [PlanoController::class, 'create']);
        $group->put('/planos/{id}', [PlanoController::class, 'update']);
        $group->delete('/planos/{id}', [PlanoController::class, 'delete']);
        
        // Planejamento de Horários
        $group->get('/planejamentos', [PlanejamentoController::class, 'index']);
        $group->get('/planejamentos/{id}', [PlanejamentoController::class, 'show']);
        $group->post('/planejamentos', [PlanejamentoController::class, 'create']);
        $group->put('/planejamentos/{id}', [PlanejamentoController::class, 'update']);
        $group->delete('/planejamentos/{id}', [PlanejamentoController::class, 'delete']);
        $group->post('/planejamentos/{id}/gerar-horarios', [PlanejamentoController::class, 'gerarHorarios']);
        
        // Registrar check-in para aluno
        $group->post('/checkins/registrar', [CheckinController::class, 'registrarPorAdmin']);
        
        // Contas a Receber
        $group->get('/contas-receber', [ContasReceberController::class, 'index']);
        $group->get('/contas-receber/relatorio', [ContasReceberController::class, 'relatorio']);
        $group->get('/contas-receber/estatisticas', [ContasReceberController::class, 'estatisticas']);
        $group->post('/contas-receber/{id}/baixa', [ContasReceberController::class, 'darBaixa']);
        $group->post('/contas-receber/{id}/cancelar', [ContasReceberController::class, 'cancelar']);
        
        // Matrículas
        $group->post('/matriculas', [MatriculaController::class, 'criar']);
        $group->get('/matriculas', [MatriculaController::class, 'listar']);
        $group->post('/matriculas/{id}/cancelar', [MatriculaController::class, 'cancelar']);
        $group->post('/matriculas/contas/{id}/baixa', [MatriculaController::class, 'darBaixaConta']);
    })->add(AdminMiddleware::class)->add(AuthMiddleware::class);

    // Rota de teste
    $app->get('/', function ($request, $response) {
        $response->getBody()->write(json_encode([
            'message' => 'API Check-in - funcionando!',
            'version' => '1.0.0',
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });
};
