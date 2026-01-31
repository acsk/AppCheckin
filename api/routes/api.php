<?php

use App\Controllers\AuthController;
use App\Controllers\DiaController;
use App\Controllers\CheckinController;
use App\Controllers\UsuarioController;
use App\Controllers\TurmaController;
use App\Controllers\ProfessorController;
use App\Controllers\AdminController;
use App\Controllers\AlunoController;
use App\Controllers\PlanoController;
use App\Controllers\ContasReceberController;
use App\Controllers\MatriculaController;
use App\Controllers\ConfigController;
use App\Controllers\SuperAdminController;
use App\Controllers\PlanosSistemaController;
use App\Controllers\TenantPlanosSistemaController;
use App\Controllers\PagamentoContratoController;
use App\Controllers\PagamentoPlanoController;
use App\Controllers\FormaPagamentoController;
use App\Controllers\ModalidadeController;
use App\Controllers\TenantFormaPagamentoController;
use App\Controllers\CepController;
use App\Controllers\StatusController;
use App\Controllers\FeatureFlagController;
use App\Controllers\MobileController;
use App\Controllers\WodController;
use App\Controllers\WodBlocoController;
use App\Controllers\WodVariacaoController;
use App\Controllers\WodResultadoController;
use App\Controllers\PresencaController;
use App\Controllers\MaintenanceController;
use App\Controllers\DashboardController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\TenantMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\SuperAdminMiddleware;
use App\Middlewares\ProfessorMiddleware;

return function ($app) {
    // Aplicar TenantMiddleware globalmente
    $app->add(TenantMiddleware::class);
    
    // ========================================
    // ROTAS DE TESTE E HEALTH CHECK (PÚBLICAS)
    // ========================================
    
    // Ping simples - verifica se PHP está rodando
    $app->get('/ping', function($request, $response) {
        $response->getBody()->write(json_encode([
            'message' => 'pong',
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => phpversion(),
            'app_env' => $_ENV['APP_ENV'] ?? 'unknown'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    
    // Health check - verifica PHP e conexão com banco
    $app->get('/health', function($request, $response) {
        try {
            $db = require __DIR__ . '/../config/database.php';
            $stmt = $db->query("SELECT 1");
            $dbConnected = $stmt !== false;
            
            $response->getBody()->write(json_encode([
                'status' => 'ok',
                'php' => 'running',
                'database' => $dbConnected ? 'connected' : 'disconnected',
                'timestamp' => date('Y-m-d H:i:s'),
                'environment' => $_ENV['APP_ENV'] ?? 'unknown'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(503);
        }
    });
    
    // Status da API - verifica se está online
    $app->get('/status', function($request, $response) {
        $response->getBody()->write(json_encode([
            'status' => 'online',
            'app' => 'AppCheckin API',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Rota pública para servir fotos de perfil (sem autenticação)
    $app->get('/uploads/fotos/{filename}', function($request, $response, $args) {
        try {
            $filename = $args['filename'] ?? '';
            
            // Validar nome do arquivo (evitar directory traversal)
            if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
                return $response->withStatus(400);
            }
            
            $caminhoCompleto = __DIR__ . '/../public/uploads/fotos/' . $filename;
            
            // Validar que o arquivo existe
            if (!file_exists($caminhoCompleto)) {
                return $response->withStatus(404);
            }
            
            // Validar que o arquivo está dentro do diretório correto (evitar exploração)
            $realpath = realpath($caminhoCompleto);
            $expectedDir = realpath(__DIR__ . '/../public/uploads/fotos');
            
            if ($realpath === false || strpos($realpath, $expectedDir) !== 0) {
                return $response->withStatus(403);
            }
            
            // Determinar tipo MIME
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $caminhoCompleto);
            finfo_close($finfo);
            
            // Validar que é uma imagem
            if (strpos($mimeType, 'image/') !== 0) {
                return $response->withStatus(400);
            }
            
            // Ler e enviar arquivo
            $conteudo = file_get_contents($caminhoCompleto);
            $response->getBody()->write($conteudo);
            
            return $response
                ->withHeader('Content-Type', $mimeType)
                ->withHeader('Cache-Control', 'public, max-age=86400')
                ->withStatus(200);
        } catch (\Exception $e) {
            error_log("Erro ao servir foto: " . $e->getMessage());
            return $response->withStatus(500);
        }
    });
    
    // Rotas públicas
    $app->post('/auth/register', [AuthController::class, 'register']);
    $app->post('/auth/login', [AuthController::class, 'login']);
    $app->post('/auth/password-recovery/request', [AuthController::class, 'forgotPassword']);
    $app->post('/auth/password-recovery/validate-token', [AuthController::class, 'validatePasswordToken']);
    $app->post('/auth/password-recovery/reset', [AuthController::class, 'resetPassword']);
    
    // ========================================
    // Rota pública de consulta CEP
    $app->get('/cep/{cep}', [CepController::class, 'buscar']);
    
    // Rotas públicas de Status (apenas leitura)
    $app->get('/status/{tipo}', [StatusController::class, 'listar']);
    $app->get('/status/{tipo}/{id}', [StatusController::class, 'buscar']);
    $app->get('/status/{tipo}/codigo/{codigo}', [StatusController::class, 'buscarPorCodigo']);
    
    // Rota pública de formas de pagamento
    $app->get('/formas-pagamento', [FormaPagamentoController::class, 'index']);

    // Rotas públicas de feature flags (somente leitura)
    $app->get('/feature-flags/{key}', function($request, $response, $args) {
        $controller = new FeatureFlagController(require __DIR__ . '/../config/database.php');
        return $controller->get($request, $response, $args);
    });
    
    // Logout (protegido para validar token)
    $app->post('/auth/logout', [AuthController::class, 'logout'])->add(AuthMiddleware::class);
    
    // Seleção de tenant/academia (protegido, mas não precisa de tenant no contexto ainda)
    $app->post('/auth/select-tenant', [AuthController::class, 'selectTenant'])->add(AuthMiddleware::class);
    $app->get('/auth/tenants', [AuthController::class, 'listTenants'])->add(AuthMiddleware::class);
    
    // Seleção inicial de tenant durante login (rota pública - usada quando múltiplos tenants)
    $app->post('/auth/select-tenant-initial', [AuthController::class, 'selectTenantPublic']);

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
        
        // === VARIÁVEIS DE AMBIENTE (Debug) ===
        $group->get('/env', [SuperAdminController::class, 'getEnvironmentVariables']);
        
        // === PLANOS DE ALUNOS (de todas as academias) ===
        $group->get('/planos', [SuperAdminController::class, 'listarPlanosAlunos']);
        
        // === PLANOS DO SISTEMA (CRUD) ===
        $group->get('/planos-sistema', [PlanosSistemaController::class, 'index']);
        $group->get('/planos-sistema/disponiveis', [PlanosSistemaController::class, 'disponiveis']);
        $group->get('/planos-sistema/{id}', [PlanosSistemaController::class, 'show']);
        $group->get('/planos-sistema/{id}/academias', [PlanosSistemaController::class, 'listarAcademias']);
        $group->post('/planos-sistema', [PlanosSistemaController::class, 'create']);
        $group->put('/planos-sistema/{id}', [PlanosSistemaController::class, 'update']);
        $group->post('/planos-sistema/{id}/marcar-historico', [PlanosSistemaController::class, 'marcarHistorico']);
        $group->delete('/planos-sistema/{id}', [PlanosSistemaController::class, 'delete']);
        
        // === CONTRATOS (Associação Academia + Plano Sistema) ===
        $group->get('/contratos/proximos-vencimento', [TenantPlanosSistemaController::class, 'proximosVencimento']);
        $group->get('/contratos/vencidos', [TenantPlanosSistemaController::class, 'vencidos']);
        $group->get('/contratos', [TenantPlanosSistemaController::class, 'index']);
        $group->get('/contratos/{id}', [TenantPlanosSistemaController::class, 'show']);
        $group->get('/academias/{tenantId}/contratos', [TenantPlanosSistemaController::class, 'contratosPorAcademia']);
        $group->get('/academias/{tenantId}/contrato-ativo', [TenantPlanosSistemaController::class, 'contratoAtivo']);
        $group->post('/academias/{tenantId}/contratos', [TenantPlanosSistemaController::class, 'associarPlano']);
        $group->post('/academias/{tenantId}/trocar-plano', [TenantPlanosSistemaController::class, 'trocarPlano']);
        $group->post('/contratos/{id}/renovar', [TenantPlanosSistemaController::class, 'renovar']);
        $group->delete('/contratos/{id}', [TenantPlanosSistemaController::class, 'cancelar']);
        
        // === PAGAMENTOS DE CONTRATOS ===
        $group->get('/pagamentos-contrato/resumo', [PagamentoContratoController::class, 'resumo']);
        $group->post('/pagamentos-contrato/marcar-atrasados', [PagamentoContratoController::class, 'marcarAtrasados']);
        $group->post('/pagamentos-contrato/{id}/confirmar', [PagamentoContratoController::class, 'confirmar']);
        $group->delete('/pagamentos-contrato/{id}', [PagamentoContratoController::class, 'cancelar']);
        $group->get('/pagamentos-contrato', [PagamentoContratoController::class, 'index']);
        $group->get('/contratos/{id}/pagamentos-contrato', [PagamentoContratoController::class, 'listarPorContrato']);
        $group->post('/contratos/{id}/pagamentos-contrato', [PagamentoContratoController::class, 'criar']);
        
        // Gerenciar usuários de todos os tenants
        $group->get('/usuarios', [UsuarioController::class, 'listarTodos']);
        $group->get('/usuarios/{id}', [UsuarioController::class, 'buscarSuperAdmin']);
        $group->put('/usuarios/{id}', [UsuarioController::class, 'atualizarSuperAdmin']);
        $group->delete('/usuarios/{id}', [UsuarioController::class, 'excluir']);
    })->add(SuperAdminMiddleware::class)->add(AuthMiddleware::class);

    // ========================================
    // Rotas Mobile (App Mobile)
    // ========================================
    $app->group('/mobile', function ($group) {
        // Perfil completo com estatísticas
        $group->get('/perfil', [MobileController::class, 'perfil']);
        $group->post('/perfil/foto', [MobileController::class, 'uploadFotoPerfil']);
        $group->get('/perfil/foto', [MobileController::class, 'obterFotoPerfil']);
        
        // Tenants do usuário
        $group->get('/tenants', [MobileController::class, 'tenants']);
        
        // Planos de alunos disponíveis
        $group->get('/planos', [MobileController::class, 'planosDoUsuario']);
        
        // Detalhes da matrícula e pagamentos
        $group->get('/matriculas/{matriculaId}', [MobileController::class, 'detalheMatricula']);
        
        // Check-in
        $group->post('/checkin', [MobileController::class, 'registrarCheckin']);
        $group->delete('/checkin/{checkinId}/desfazer', [MobileController::class, 'desfazerCheckin']);
        $group->get('/checkins', [MobileController::class, 'historicoCheckins']);
        $group->get('/checkins/por-modalidade', [MobileController::class, 'checkinsPorModalidade']);
        
        // Turmas e horários
        $group->get('/turmas', [MobileController::class, 'listarTurmas']);
        $group->get('/turma/{turmaId}/participantes', [MobileController::class, 'participantesTurma']);
        $group->get('/turma/{turmaId}/detalhes', [MobileController::class, 'detalheTurma']);
        
        // Horários das aulas
        $group->get('/horarios-disponiveis', [MobileController::class, 'horariosDisponiveis']);
        
        // Presença (professor marca se aluno veio ou não)
        $group->post('/turma/{turmaId}/confirmar-presenca', [MobileController::class, 'confirmarPresenca']);
        
        // WOD do dia
        $group->get('/wod/hoje', [MobileController::class, 'wodDodia']);
        $group->get('/wods/hoje', [MobileController::class, 'wodsDodia']);
        
        // Ranking de check-ins
        $group->get('/ranking/mensal', [MobileController::class, 'rankingMensal']);
    })->add(AuthMiddleware::class);

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
        $group->get('/dias/por-data', [DiaController::class, 'porData']);
        $group->get('/dias/periodo', [DiaController::class, 'periodo']);
        $group->get('/dias/proximos', [DiaController::class, 'diasProximos']);
        $group->get('/dias/horarios', [DiaController::class, 'horariosPorData']);
        $group->get('/dias', [DiaController::class, 'index']);
        $group->get('/dias/{id}/horarios', [DiaController::class, 'horarios']);
        
        // Check-ins
        $group->post('/checkin', [CheckinController::class, 'store']);
        $group->get('/me/checkins', [CheckinController::class, 'myCheckins']);
        $group->delete('/checkin/{id}', [CheckinController::class, 'cancel']);
        $group->delete('/checkin/{id}/desfazer', [CheckinController::class, 'desfazer']);
        
        // Gestão de Turmas
        $group->get('/turmas', [TurmaController::class, 'index']);
        $group->get('/turmas/dia/{diaId}', [TurmaController::class, 'listarPorDia']);
        $group->get('/turmas/{id}/vagas', [TurmaController::class, 'verificarVagas']);
        
        // Planos (público para alunos verem)
        $group->get('/planos', [PlanoController::class, 'index']);
        $group->get('/planos/{id}', [PlanoController::class, 'show']);
        
        // Configurações (formas de pagamento e status)
        $group->get('/config/formas-pagamento', [ConfigController::class, 'listarFormasPagamento']);
        $group->get('/config/formas-pagamento-ativas', [TenantFormaPagamentoController::class, 'listarAtivas']);
        $group->get('/config/status-conta', [ConfigController::class, 'listarStatusConta']);
    })->add(AuthMiddleware::class);

    // ========================================
    // Rotas Tenant - Gestão de Usuários (Admin/Alunos)
    // ========================================
    $app->group('/tenant', function ($group) {
        // CRUD de Usuários do Tenant
        $group->get('/usuarios', [UsuarioController::class, 'listar']);
        $group->get('/usuarios/{id}', [UsuarioController::class, 'buscar']);
        $group->post('/usuarios', [UsuarioController::class, 'criar']);
        $group->put('/usuarios/{id}', [UsuarioController::class, 'atualizar']);
        $group->delete('/usuarios/{id}', [UsuarioController::class, 'excluir']);
        
        // Buscar e associar usuário existente
        $group->get('/usuarios/buscar-cpf/{cpf}', [UsuarioController::class, 'buscarPorCpf']);
        $group->post('/usuarios/associar', [UsuarioController::class, 'associarUsuario']);
    })->add(AdminMiddleware::class)->add(AuthMiddleware::class);

    // ========================================
    // Rotas Admin (role_id = 2 ou 3)
    // ========================================
    $app->group('/admin', function ($group) {
        // Feature Flags (admin)
        $group->get('/feature-flags', function($request, $response) {
            $controller = new FeatureFlagController(require __DIR__ . '/../config/database.php');
            return $controller->list($request, $response);
        });
        $group->get('/feature-flags/{key}', function($request, $response, $args) {
            $controller = new FeatureFlagController(require __DIR__ . '/../config/database.php');
            return $controller->get($request, $response, $args);
        });
        // Dashboard e estatísticas
        $group->get('/dashboard', [AdminController::class, 'dashboard']);
        $group->get('/dashboard/cards', [DashboardController::class, 'cards']);
        
        // Gestão de Alunos (CRUD completo)
        $group->get('/alunos', [AlunoController::class, 'index']);
        $group->get('/alunos/basico', [AlunoController::class, 'listarBasico']);
        $group->get('/alunos/buscar-cpf/{cpf}', [AlunoController::class, 'buscarPorCpf']);
        $group->post('/alunos/associar', [AlunoController::class, 'associarAluno']);
        $group->get('/alunos/{id}', [AlunoController::class, 'show']);
        $group->get('/alunos/{id}/historico-planos', [AlunoController::class, 'historicoPlanos']);
        $group->post('/alunos', [AlunoController::class, 'create']);
        $group->put('/alunos/{id}', [AlunoController::class, 'update']);
        $group->delete('/alunos/{id}', [AlunoController::class, 'delete']);
        
        // Gestão de Planos
        $group->get('/planos/{id}', [PlanoController::class, 'show']);
        $group->post('/planos', [PlanoController::class, 'create']);
        $group->put('/planos/{id}', [PlanoController::class, 'update']);
        $group->delete('/planos/{id}', [PlanoController::class, 'delete']);
        
        // Dias e Horários (Admin)
        $group->delete('/dias/{id}/horarios', [DiaController::class, 'deletarHorariosDoDia']);
        
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
        $group->get('/matriculas/{id}', [MatriculaController::class, 'buscar']);
        $group->get('/matriculas/{id}/pagamentos', [MatriculaController::class, 'buscarPagamentos']);
        $group->post('/matriculas/{id}/cancelar', [MatriculaController::class, 'cancelar']);
        $group->post('/matriculas/contas/{id}/baixa', [MatriculaController::class, 'darBaixaConta']);
        
        // Pagamentos de Planos/Matrículas
        $group->get('/pagamentos-plano', [PagamentoPlanoController::class, 'index']);
        $group->get('/pagamentos-plano/resumo', [PagamentoPlanoController::class, 'resumo']);
        $group->get('/pagamentos-plano/{id}', [PagamentoPlanoController::class, 'buscar']);
        $group->get('/matriculas/{id}/pagamentos-plano', [PagamentoPlanoController::class, 'listarPorMatricula']);
        $group->get('/usuarios/{id}/pagamentos-plano', [PagamentoPlanoController::class, 'listarPorUsuario']);
        $group->post('/matriculas/{id}/pagamentos-plano', [PagamentoPlanoController::class, 'criar']);
        $group->post('/pagamentos-plano/{id}/confirmar', [PagamentoPlanoController::class, 'confirmar']);
        $group->delete('/pagamentos-plano/{id}', [PagamentoPlanoController::class, 'cancelar']);
        $group->post('/pagamentos-plano/marcar-atrasados', [PagamentoPlanoController::class, 'marcarAtrasados']);
        
        // Modalidades
        $group->get('/modalidades', [ModalidadeController::class, 'index']);
        $group->get('/modalidades/{id}', [ModalidadeController::class, 'show']);
        $group->post('/modalidades', [ModalidadeController::class, 'create']);
        $group->put('/modalidades/{id}', [ModalidadeController::class, 'update']);
        $group->delete('/modalidades/{id}', [ModalidadeController::class, 'delete']);
        
        // Professores
        $group->get('/professores', [ProfessorController::class, 'index']);
        $group->get('/professores/{id}', [ProfessorController::class, 'show']);
        $group->post('/professores', [ProfessorController::class, 'create']);
        $group->put('/professores/{id}', [ProfessorController::class, 'update']);
        $group->delete('/professores/{id}', [ProfessorController::class, 'delete']);
        
        // Turmas/Aulas
        $group->get('/turmas', [TurmaController::class, 'index']);
        $group->get('/turmas/dia/{diaId}', [TurmaController::class, 'listarPorDia']);
        $group->get('/turmas/{id}', [TurmaController::class, 'show']);
        $group->post('/turmas', [TurmaController::class, 'create']);
        $group->post('/turmas/replicar', [TurmaController::class, 'replicarPorDiasSemana']);
        $group->post('/turmas/desativar', [TurmaController::class, 'desativarTurma']);
        $group->put('/turmas/{id}', [TurmaController::class, 'update']);
        $group->delete('/turmas/{id}', [TurmaController::class, 'delete']);
        $group->get('/turmas/{id}/vagas', [TurmaController::class, 'verificarVagas']);
        $group->get('/professores/{professorId}/turmas', [TurmaController::class, 'listarPorProfessor']);
        
        // Desativar dias (feriados, sem aula, etc)
        $group->post('/dias/desativar', [DiaController::class, 'desativarDias']);
        
        // Formas de Pagamento por Tenant (Configurações)
        $group->get('/formas-pagamento-config', [TenantFormaPagamentoController::class, 'listar']);
        $group->get('/formas-pagamento-config/{id}', [TenantFormaPagamentoController::class, 'buscar']);
        $group->put('/formas-pagamento-config/{id}', [TenantFormaPagamentoController::class, 'atualizar']);
        $group->post('/formas-pagamento-config/calcular-taxas', [TenantFormaPagamentoController::class, 'calcularTaxas']);
        $group->post('/formas-pagamento-config/calcular-parcelas', [TenantFormaPagamentoController::class, 'calcularParcelas']);
        
        // WOD (Workout of the Day)
        $group->get('/wods/modalidades', [WodController::class, 'listarModalidades']); // Listar modalidades disponíveis
        $group->get('/wods/buscar', [WodController::class, 'buscarPorDataModalidade']); // Buscar WOD por data + modalidade
        $group->get('/wods', [WodController::class, 'index']);
        $group->get('/wods/{id}', [WodController::class, 'show']);
        $group->post('/wods', [WodController::class, 'create']);
        $group->post('/wods/completo', [WodController::class, 'createCompleto']); // Criar WOD completo com blocos
        $group->put('/wods/{id}', [WodController::class, 'update']);
        $group->delete('/wods/{id}', [WodController::class, 'delete']);
        $group->patch('/wods/{id}/publish', [WodController::class, 'publish']);
        $group->patch('/wods/{id}/archive', [WodController::class, 'archive']);
        
        // Blocos de WOD
        $group->get('/wods/{wodId}/blocos', [WodBlocoController::class, 'index']);
        $group->post('/wods/{wodId}/blocos', [WodBlocoController::class, 'create']);
        $group->put('/wods/{wodId}/blocos/{id}', [WodBlocoController::class, 'update']);
        $group->delete('/wods/{wodId}/blocos/{id}', [WodBlocoController::class, 'delete']);
        
        // Variações de WOD
        $group->get('/wods/{wodId}/variacoes', [WodVariacaoController::class, 'index']);
        $group->post('/wods/{wodId}/variacoes', [WodVariacaoController::class, 'create']);
        $group->put('/wods/{wodId}/variacoes/{id}', [WodVariacaoController::class, 'update']);
        $group->delete('/wods/{wodId}/variacoes/{id}', [WodVariacaoController::class, 'delete']);
        
        // Resultados/Leaderboard de WOD
        $group->get('/wods/{wodId}/resultados', [WodResultadoController::class, 'index']);
        $group->post('/wods/{wodId}/resultados', [WodResultadoController::class, 'create']);
        $group->put('/wods/{wodId}/resultados/{id}', [WodResultadoController::class, 'update']);
        $group->delete('/wods/{wodId}/resultados/{id}', [WodResultadoController::class, 'delete']);
        
        // Controle de Presença (Professor/Admin)
        $group->get('/turmas/{turmaId}/presencas', [PresencaController::class, 'listarPresencas']);
        $group->patch('/checkins/{checkinId}/presenca', [PresencaController::class, 'marcarPresenca']);
        $group->post('/turmas/{turmaId}/presencas/lote', [PresencaController::class, 'marcarPresencaLote']);
    })->add(AdminMiddleware::class)->add(AuthMiddleware::class);

    // ========================================
    // Rotas Professor (papel_id = 2 no tenant)
    // Permite também acesso de Admin (papel_id = 3) e Super Admin (role_id = 3)
    // O papel é verificado em usuario_tenant.papel_id, não em usuarios.role_id
    // ========================================
    $app->group('/professor', function ($group) {
        // Dashboard do professor
        $group->get('/dashboard', [PresencaController::class, 'dashboardProfessor']);
        
        // Turmas com check-ins pendentes de confirmação
        $group->get('/turmas/pendentes', [PresencaController::class, 'listarTurmasPendentes']);
        
        // Listar check-ins de uma turma para marcar presença
        $group->get('/turmas/{turmaId}/checkins', [PresencaController::class, 'listarCheckinsParaPresenca']);
        
        // Confirmar presença da turma (marca presentes/faltas e opcionalmente remove faltantes)
        $group->post('/turmas/{turmaId}/confirmar-presenca', [PresencaController::class, 'confirmarPresencaTurma']);
        
        // Remover check-ins de faltantes manualmente (libera créditos para remarcar)
        $group->delete('/turmas/{turmaId}/faltantes', [PresencaController::class, 'removerFaltantes']);
    })->add(ProfessorMiddleware::class)->add(AuthMiddleware::class);

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
