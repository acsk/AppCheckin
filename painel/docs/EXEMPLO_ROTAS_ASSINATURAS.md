/**
 * EXEMPLO DE ROTAS PARA ASSINATURAS
 * 
 * Adicionar ao arquivo routes/api.php do backend PHP/Slim
 * 
 * Estas rotas devem ser adicionadas após o grupo de rotas autenticadas
 * e após o TenantMiddleware ser aplicado.
 */

// ==========================================
// ROTAS DE ASSINATURAS - ADMIN
// ==========================================

// Listar assinaturas da academia
$app->get('/admin/assinaturas', [AssinaturaController::class, 'listar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Buscar assinatura específica
$app->get('/admin/assinaturas/{id}', [AssinaturaController::class, 'buscar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Criar nova assinatura
$app->post('/admin/assinaturas', [AssinaturaController::class, 'criar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Atualizar assinatura
$app->put('/admin/assinaturas/{id}', [AssinaturaController::class, 'atualizar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Renovar assinatura
$app->post('/admin/assinaturas/{id}/renovar', [AssinaturaController::class, 'renovar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Suspender assinatura
$app->post('/admin/assinaturas/{id}/suspender', [AssinaturaController::class, 'suspender'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Reativar assinatura
$app->post('/admin/assinaturas/{id}/reativar', [AssinaturaController::class, 'reativar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Cancelar assinatura
$app->post('/admin/assinaturas/{id}/cancelar', [AssinaturaController::class, 'cancelar'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Assinaturas próximas de vencer
$app->get('/admin/assinaturas/proximas-vencer', [AssinaturaController::class, 'listarProximasVencer'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Histórico de assinaturas de um aluno
$app->get('/admin/alunos/{id}/assinaturas', [AssinaturaController::class, 'listarHistoricoAluno'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// Relatório de assinaturas
$app->get('/admin/assinaturas/relatorio', [AssinaturaController::class, 'relatorio'])
    ->add(AuthMiddleware::class)
    ->add(TenantMiddleware::class)
    ->add(AdminMiddleware::class);

// ==========================================
// ROTAS DE ASSINATURAS - SUPERADMIN
// ==========================================

// Listar todas as assinaturas (todas as academias)
$app->get('/superadmin/assinaturas', [AssinaturaController::class, 'listarTodas'])
    ->add(AuthMiddleware::class)
    ->add(SuperAdminMiddleware::class);

// ==========================================
// IMPORTS NECESSÁRIOS
// ==========================================
/*
Na seção de imports do arquivo routes/api.php, adicione:

use App\Controllers\AssinaturaController;
*/
