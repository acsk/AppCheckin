<?php
/**
 * Seed: Matrículas para Teste de Vencimento
 * 
 * Cria diferentes cenários de matrículas para testar o sistema de vencimento:
 * 
 * Cenários criados:
 * 1. Matrícula ATIVA VENCIDA (deve virar "vencida" pelo job)
 * 2. Matrícula ATIVA VÁLIDA (não muda)
 * 3. Matrícula VENCIDA com data válida (deve reativar pelo job)
 * 4. Matrícula TESTE vencida
 * 5. Matrícula com vencimento hoje
 * 6. Matrícula com vencimento próximo (3 dias)
 * 
 * Execução:
 * docker exec appcheckin_php php database/seeds/seed_test_vencimentos.php
 */

require_once __DIR__ . '/../../config/database.php';

echo "🚀 Iniciando seed de matrículas para teste de vencimento...\n\n";

try {
    $pdo->beginTransaction();
    
    $hoje = date('Y-m-d');
    $ontem = date('Y-m-d', strtotime('-1 day'));
    $anteontem = date('Y-m-d', strtotime('-2 days'));
    $amanha = date('Y-m-d', strtotime('+1 day'));
    $daqui3dias = date('Y-m-d', strtotime('+3 days'));
    $daqui30dias = date('Y-m-d', strtotime('+30 days'));
    
    // Buscar tenant_id (usar o primeiro tenant disponível)
    $stmtTenant = $pdo->query("SELECT id FROM tenants WHERE ativo = 1 LIMIT 1");
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);
    $tenantId = $tenant['id'];
    
    echo "📍 Usando Tenant ID: {$tenantId}\n\n";
    
    // Buscar ou criar alunos de teste
    $alunos = [];
    
    // Verificar se já existem usuários de teste
    $stmtCheck = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email LIKE 'teste.vencimento%' LIMIT 6");
    $stmtCheck->execute();
    $usuariosExistentes = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($usuariosExistentes) < 6) {
        echo "👤 Criando usuários de teste...\n";
        
        $nomes = [
            'TESTE VENCIDO ATIVO',
            'TESTE VÁLIDO ATIVO',
            'TESTE REATIVAR',
            'TESTE TRIAL VENCIDO',
            'TESTE VENCE HOJE',
            'TESTE VENCE 3 DIAS'
        ];
        
        for ($i = 1; $i <= 6; $i++) {
            $email = "teste.vencimento{$i}@test.com";
            
            // Verificar se usuário já existe
            $stmtUsuario = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmtUsuario->execute([$email]);
            $usuarioExiste = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
            
            if (!$usuarioExiste) {
                // Criar usuário
                $stmtInsertUser = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, senha_hash, ativo, created_at, updated_at)
                    VALUES (?, ?, ?, 1, NOW(), NOW())
                ");
                $stmtInsertUser->execute([
                    $nomes[$i - 1],
                    $email,
                    password_hash('123456', PASSWORD_DEFAULT)
                ]);
                $usuarioId = $pdo->lastInsertId();
                echo "   ✅ Usuário criado: {$nomes[$i - 1]} (ID: {$usuarioId})\n";
            } else {
                $usuarioId = $usuarioExiste['id'];
                echo "   ℹ️  Usuário já existe: {$email} (ID: {$usuarioId})\n";
            }
            
            // Criar aluno se não existir
            $stmtAluno = $pdo->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$usuarioId]);
            $alunoExiste = $stmtAluno->fetch(PDO::FETCH_ASSOC);
            
            if (!$alunoExiste) {
                $stmtInsertAluno = $pdo->prepare("
                    INSERT INTO alunos (usuario_id, nome, ativo, created_at, updated_at)
                    VALUES (?, ?, 1, NOW(), NOW())
                ");
                $stmtInsertAluno->execute([$usuarioId, $nomes[$i - 1]]);
                $alunoId = $pdo->lastInsertId();
            } else {
                $alunoId = $alunoExiste['id'];
            }
            
            $alunos[] = ['usuario_id' => $usuarioId, 'aluno_id' => $alunoId, 'nome' => $nomes[$i - 1]];
        }
    } else {
        echo "👤 Usando usuários existentes...\n";
        foreach ($usuariosExistentes as $user) {
            $stmtAluno = $pdo->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
            $stmtAluno->execute([$user['id']]);
            $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);
            if ($aluno) {
                $alunos[] = ['usuario_id' => $user['id'], 'aluno_id' => $aluno['id'], 'nome' => $user['nome']];
            }
        }
    }
    
    echo "\n📦 Criando matrículas de teste...\n\n";
    
    // Buscar plano de teste (valor 0)
    $stmtPlanoTeste = $pdo->prepare("SELECT id FROM planos WHERE tenant_id = ? AND valor = 0 LIMIT 1");
    $stmtPlanoTeste->execute([$tenantId]);
    $planoTeste = $stmtPlanoTeste->fetch(PDO::FETCH_ASSOC);
    
    // Buscar plano pago
    $stmtPlanoPago = $pdo->prepare("SELECT id FROM planos WHERE tenant_id = ? AND valor > 0 LIMIT 1");
    $stmtPlanoPago->execute([$tenantId]);
    $planoPago = $stmtPlanoPago->fetch(PDO::FETCH_ASSOC);
    
    $planoIdTeste = $planoTeste ? $planoTeste['id'] : null;
    $planoIdPago = $planoPago ? $planoPago['id'] : null;
    
    if (!$planoIdPago) {
        echo "⚠️  Nenhum plano pago encontrado. Criando um...\n";
        $stmtCriarPlano = $pdo->prepare("
            INSERT INTO planos (tenant_id, nome, valor, duracao_dias, checkins_semanais, modalidade_id, ativo)
            VALUES (?, 'Plano Teste Pago', 100.00, 30, 3, 1, 1)
        ");
        $stmtCriarPlano->execute([$tenantId]);
        $planoIdPago = $pdo->lastInsertId();
    }
    
    // IDs de status
    $statusAtiva = 1;
    $statusVencida = 2;
    $statusPendente = 5;
    
    // Motivo
    $motivoNova = 1;
    
    // Limpar matrículas antigas dos usuários de teste
    $usuariosIds = array_column($alunos, 'usuario_id');
    if (!empty($usuariosIds)) {
        $placeholders = implode(',', array_fill(0, count($usuariosIds), '?'));
        $stmtDelete = $pdo->prepare("
            DELETE m FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            WHERE a.usuario_id IN ($placeholders)
        ");
        $stmtDelete->execute($usuariosIds);
        echo "🗑️  Matrículas antigas removidas\n\n";
    }
    
    $cenarios = [
        [
            'aluno_idx' => 0,
            'descricao' => '1. ATIVA VENCIDA (deve virar "vencida")',
            'plano_id' => $planoIdPago,
            'status_id' => $statusAtiva,
            'proxima_data_vencimento' => $anteontem, // Venceu há 2 dias
            'periodo_teste' => 0,
            'data_inicio' => date('Y-m-d', strtotime('-32 days'))
        ],
        [
            'aluno_idx' => 1,
            'descricao' => '2. ATIVA VÁLIDA (não muda)',
            'plano_id' => $planoIdPago,
            'status_id' => $statusAtiva,
            'proxima_data_vencimento' => $daqui30dias, // Válida por 30 dias
            'periodo_teste' => 0,
            'data_inicio' => $hoje
        ],
        [
            'aluno_idx' => 2,
            'descricao' => '3. VENCIDA com data válida (deve reativar)',
            'plano_id' => $planoIdPago,
            'status_id' => $statusVencida,
            'proxima_data_vencimento' => $daqui30dias, // Válida por 30 dias
            'periodo_teste' => 0,
            'data_inicio' => $hoje
        ],
        [
            'aluno_idx' => 3,
            'descricao' => '4. TESTE vencida',
            'plano_id' => $planoIdTeste ?: $planoIdPago,
            'status_id' => $statusVencida,
            'proxima_data_vencimento' => $ontem, // Venceu ontem
            'periodo_teste' => 1,
            'data_inicio' => date('Y-m-d', strtotime('-8 days'))
        ],
        [
            'aluno_idx' => 4,
            'descricao' => '5. ATIVA vence HOJE',
            'plano_id' => $planoIdPago,
            'status_id' => $statusAtiva,
            'proxima_data_vencimento' => $hoje, // Vence hoje
            'periodo_teste' => 0,
            'data_inicio' => date('Y-m-d', strtotime('-30 days'))
        ],
        [
            'aluno_idx' => 5,
            'descricao' => '6. ATIVA vence em 3 dias',
            'plano_id' => $planoIdPago,
            'status_id' => $statusAtiva,
            'proxima_data_vencimento' => $daqui3dias, // Vence daqui 3 dias
            'periodo_teste' => 0,
            'data_inicio' => date('Y-m-d', strtotime('-27 days'))
        ]
    ];
    
    foreach ($cenarios as $cenario) {
        $aluno = $alunos[$cenario['aluno_idx']];
        
        $stmtInsert = $pdo->prepare("
            INSERT INTO matriculas (
                tenant_id, aluno_id, plano_id, data_matricula, data_inicio, data_vencimento,
                valor, status_id, motivo_id, periodo_teste, proxima_data_vencimento,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $dataVencimento = date('Y-m-d', strtotime($cenario['data_inicio'] . ' +30 days'));
        
        $stmtInsert->execute([
            $tenantId,
            $aluno['aluno_id'],
            $cenario['plano_id'],
            $cenario['data_inicio'],
            $cenario['data_inicio'],
            $dataVencimento,
            $cenario['periodo_teste'] == 1 ? 0 : 100.00,
            $cenario['status_id'],
            $motivoNova,
            $cenario['periodo_teste'],
            $cenario['proxima_data_vencimento']
        ]);
        
        $matriculaId = $pdo->lastInsertId();
        
        $statusNome = $cenario['status_id'] == $statusAtiva ? 'ATIVA' : 'VENCIDA';
        
        echo "✅ {$cenario['descricao']}\n";
        echo "   Aluno: {$aluno['nome']}\n";
        echo "   Matrícula ID: {$matriculaId}\n";
        echo "   Status: {$statusNome}\n";
        echo "   Vencimento: {$cenario['proxima_data_vencimento']}\n";
        echo "   Teste: " . ($cenario['periodo_teste'] ? 'SIM' : 'NÃO') . "\n\n";
    }
    
    $pdo->commit();
    
    echo str_repeat("=", 70) . "\n";
    echo "✅ Seed finalizado com sucesso!\n";
    echo str_repeat("=", 70) . "\n\n";
    
    echo "📋 Próximos passos:\n\n";
    echo "1. Verificar matrículas criadas:\n";
    echo "   SELECT m.id, u.nome, sm.nome as status, m.proxima_data_vencimento\n";
    echo "   FROM matriculas m\n";
    echo "   INNER JOIN alunos a ON a.id = m.aluno_id\n";
    echo "   INNER JOIN usuarios u ON u.id = a.usuario_id\n";
    echo "   INNER JOIN status_matricula sm ON sm.id = m.status_id\n";
    echo "   WHERE u.email LIKE 'teste.vencimento%'\n";
    echo "   ORDER BY m.id DESC;\n\n";
    
    echo "2. Executar job em modo dry-run:\n";
    echo "   docker exec appcheckin_php php jobs/atualizar_status_vencimento.php --dry-run\n\n";
    
    echo "3. Executar job de verdade:\n";
    echo "   docker exec appcheckin_php php jobs/atualizar_status_vencimento.php\n\n";
    
    echo "4. Verificar mudanças:\n";
    echo "   Executar query do passo 1 novamente\n\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
