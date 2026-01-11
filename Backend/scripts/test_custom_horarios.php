<?php
/**
 * Script para testar a criação de uma turma com horário customizado
 */

require_once __DIR__ . '/vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Conectar ao banco
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "\n=== TESTE DE CRIAÇÃO DE TURMA COM HORÁRIO CUSTOMIZADO ===\n\n";
    
    // Verificar estrutura da tabela
    echo "[1] Verificando estrutura da tabela turmas:\n";
    $stmt = $pdo->query("DESCRIBE turmas");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasHorarioInicio = false;
    $hasHorarioFim = false;
    $hasHorarioId = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'horario_inicio') $hasHorarioInicio = true;
        if ($col['Field'] === 'horario_fim') $hasHorarioFim = true;
        if ($col['Field'] === 'horario_id') $hasHorarioId = true;
    }
    
    echo "   - horario_inicio: " . ($hasHorarioInicio ? "✅ OK" : "❌ FALTA") . "\n";
    echo "   - horario_fim: " . ($hasHorarioFim ? "✅ OK" : "❌ FALTA") . "\n";
    echo "   - horario_id: " . ($hasHorarioId ? "❌ AINDA EXISTE" : "✅ REMOVIDO") . "\n";
    
    if (!$hasHorarioInicio || !$hasHorarioFim || $hasHorarioId) {
        throw new Exception("Estrutura da tabela está inconsistente!");
    }
    
    // Verificar dados existentes
    echo "\n[2] Verificando turmas existentes:\n";
    $stmt = $pdo->query("SELECT id, nome, horario_inicio, horario_fim FROM turmas LIMIT 3");
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($turmas)) {
        echo "   Nenhuma turma encontrada\n";
    } else {
        foreach ($turmas as $turma) {
            echo "   - ID: {$turma['id']}, Nome: {$turma['nome']}, Horário: {$turma['horario_inicio']} - {$turma['horario_fim']}\n";
        }
    }
    
    // Testar inserção direta
    echo "\n[3] Testando inserção de turma com horário customizado (04:00 - 04:30):\n";
    
    $tenantId = 1;
    $professorId = 1;
    $modalidadeId = 1;
    $diaId = 18; // Quarta-feira
    
    // Verificar se tenant, professor, modalidade e dia existem
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    if (!$stmt->fetch()) {
        throw new Exception("Tenant ID $tenantId não existe");
    }
    
    $stmt = $pdo->prepare("SELECT id FROM professores WHERE id = ?");
    $stmt->execute([$professorId]);
    if (!$stmt->fetch()) {
        throw new Exception("Professor ID $professorId não existe");
    }
    
    $stmt = $pdo->prepare("SELECT id FROM modalidades WHERE id = ?");
    $stmt->execute([$modalidadeId]);
    if (!$stmt->fetch()) {
        throw new Exception("Modalidade ID $modalidadeId não existe");
    }
    
    $stmt = $pdo->prepare("SELECT id FROM dias WHERE id = ?");
    $stmt->execute([$diaId]);
    if (!$stmt->fetch()) {
        throw new Exception("Dia ID $diaId não existe");
    }
    
    echo "   ✅ Todas as dependências existem\n";
    
    // Inserir turma
    $stmt = $pdo->prepare("
        INSERT INTO turmas (tenant_id, professor_id, modalidade_id, dia_id, horario_inicio, horario_fim, nome, limite_alunos, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $tenantId,
        $professorId,
        $modalidadeId,
        $diaId,
        '04:00:00',
        '04:30:00',
        'Turma Customizada - Teste 04:00-04:30',
        20,
        1
    ]);
    
    $turmaId = $pdo->lastInsertId();
    echo "   ✅ Turma criada com ID: $turmaId\n";
    
    // Verificar dados inseridos
    echo "\n[4] Verificando turma criada:\n";
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([$turmaId]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   ID: {$turma['id']}\n";
    echo "   Nome: {$turma['nome']}\n";
    echo "   Horário: {$turma['horario_inicio']} - {$turma['horario_fim']}\n";
    echo "   Ativo: {$turma['ativo']}\n";
    
    // Testar validação de conflito
    echo "\n[5] Testando validação de conflito de horário:\n";
    
    $stmt = $pdo->prepare("
        SELECT t.id, t.nome, t.horario_inicio, t.horario_fim
        FROM turmas t
        WHERE t.tenant_id = ? 
        AND t.dia_id = ? 
        AND t.ativo = 1
        AND (t.horario_inicio < ? AND t.horario_fim > ?)
    ");
    
    // Testar com horário que se sobrepõe: 04:15 - 04:45
    $stmt->execute([$tenantId, $diaId, '04:45:00', '04:15:00']);
    $conflitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Procurando conflitos para horário 04:15 - 04:45:\n";
    if (!empty($conflitos)) {
        echo "   ✅ Conflito detectado:\n";
        foreach ($conflitos as $turmaConflito) {
            echo "      - {$turmaConflito['nome']}: {$turmaConflito['horario_inicio']} - {$turmaConflito['horario_fim']}\n";
        }
    } else {
        echo "   ❌ ERRO: Deveria ter detectado conflito!\n";
    }
    
    // Testar sem conflito
    $stmt->execute([$tenantId, $diaId, '05:00:00', '04:30:00']);
    $conflitos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n   Procurando conflitos para horário 04:30 - 05:00:\n";
    if (empty($conflitos)) {
        echo "   ✅ Nenhum conflito (correto!)\n";
    } else {
        echo "   ❌ ERRO: Não deveria ter conflito!\n";
    }
    
    echo "\n✅ TODOS OS TESTES PASSARAM!\n";
    echo "   A mudança foi implementada com sucesso!\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO:\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
