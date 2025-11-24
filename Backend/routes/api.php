<?php

use App\Controllers\AuthController;
use App\Controllers\DiaController;
use App\Controllers\CheckinController;
use App\Controllers\UsuarioController;
use App\Controllers\TurmaController;
use App\Controllers\AdminController;
use App\Controllers\PlanoController;
use App\Controllers\PlanejamentoController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\TenantMiddleware;

return function ($app) {
    // Aplicar TenantMiddleware globalmente
    $app->add(TenantMiddleware::class);
    
    // Rotas públicas
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);
    
    // Logout (protegido para validar token)
    $app->post('/auth/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);

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
        
        // Admin - Protegido (TODO: adicionar AdminMiddleware)
        $group->get('/admin/dashboard', [AdminController::class, 'dashboard']);
        $group->get('/admin/alunos', [AdminController::class, 'listarAlunos']);
        $group->get('/admin/alunos/{id}', [AdminController::class, 'buscarAluno']);
        $group->post('/admin/alunos', [AdminController::class, 'criarAluno']);
        $group->put('/admin/alunos/{id}', [AdminController::class, 'atualizarAluno']);
        $group->delete('/admin/alunos/{id}', [AdminController::class, 'desativarAluno']);
        
        // Admin - Planos
        $group->post('/admin/planos', [PlanoController::class, 'create']);
        $group->put('/admin/planos/{id}', [PlanoController::class, 'update']);
        $group->delete('/admin/planos/{id}', [PlanoController::class, 'delete']);
        
        // Admin - Planejamento de Horários
        $group->get('/admin/planejamentos', [PlanejamentoController::class, 'index']);
        $group->get('/admin/planejamentos/{id}', [PlanejamentoController::class, 'show']);
        $group->post('/admin/planejamentos', [PlanejamentoController::class, 'create']);
        $group->put('/admin/planejamentos/{id}', [PlanejamentoController::class, 'update']);
        $group->delete('/admin/planejamentos/{id}', [PlanejamentoController::class, 'delete']);
        $group->post('/admin/planejamentos/{id}/gerar-horarios', [PlanejamentoController::class, 'gerarHorarios']);
        
        // Admin - Registrar check-in para aluno
        $group->post('/admin/checkins/registrar', [CheckinController::class, 'registrarPorAdmin']);
    })->add(AuthMiddleware::class);

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
