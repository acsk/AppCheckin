<?php

use App\Controllers\AuthController;
use App\Controllers\DiaController;
use App\Controllers\CheckinController;
use App\Controllers\UsuarioController;
use App\Controllers\TurmaController;
use App\Middlewares\AuthMiddleware;

return function ($app) {
    // Rotas públicas
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);

    // Rotas protegidas
    $app->group('', function ($group) {
        // Usuário
        $group->get('/me', [UsuarioController::class, 'me']);
        $group->put('/me', [UsuarioController::class, 'update']);
        
        // Dias disponíveis
        $group->get('/dias', [DiaController::class, 'index']);
        $group->get('/dias/{id}/horarios', [DiaController::class, 'horarios']);
        
        // Check-ins
        $group->post('/checkin', [CheckinController::class, 'store']);
        $group->get('/me/checkins', [CheckinController::class, 'myCheckins']);
        $group->delete('/checkin/{id}', [CheckinController::class, 'cancel']);
        
        // Gestão de Turmas
        $group->get('/turmas', [TurmaController::class, 'index']);
        $group->get('/turmas/hoje', [TurmaController::class, 'hoje']);
        $group->get('/turmas/{id}/alunos', [TurmaController::class, 'alunos']);
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
