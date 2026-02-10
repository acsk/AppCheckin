<?php
/**
 * Script para importar matrículas em lote
 * 
 * Uso: php scripts/importar_matriculas.php alunos.json
 * 
 * Formato do JSON:
 * [
 *   {
 *     "nome": "João Silva",
 *     "email": "joao@email.com",
 *     "cpf": "12345678901",
 *     "telefone": "82999999999",
 *     "plano_nome": "2x por Semana",  // Nome do plano ou vazio para apenas associar
 *     "ciclo_meses": 2,                 // 1, 2 ou 4 (mensal, bimestral, quadrimestral)
 *     "data_inicio": "2026-02-10"       // Opcional, padrão = hoje
 *   }
 * ]
 */

require __DIR__ . '/../vendor/autoload.php';

// Configurações
$TENANT_ID = 3; // Cia da Natação
$MODALIDADE_ID = 3; // Natação
$CRIADO_POR = 69; // ID do admin que está importando

// Conectar ao banco
$db = require __DIR__ . '/../config/database.php';

// Verificar argumento
if ($argc < 2) {
    echo "❌ Uso: php scripts/importar_matriculas.php alunos.json\n";
    exit(1);
}

$arquivo = $argv[1];

if (!file_exists($arquivo)) {
    echo "❌ Arquivo não encontrado: {$arquivo}\n";
    exit(1);
}

// Ler arquivo JSON
$conteudo = file_get_contents($arquivo);
$alunos = json_decode($conteudo, true);

if (!$alunos) {
    echo "❌ Erro ao ler JSON\n";
    exit(1);
}

echo "📋 Total de alunos a processar: " . count($alunos) . "\n\n";

// Mapa de planos (cache)
$planosMap = [];
$stmt = $db->prepare("SELECT id, nome FROM planos WHERE tenant_id = ? AND modalidade_id = ? AND ativo = 1");
$stmt->execute([$TENANT_ID, $MODALIDADE_ID]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $planosMap[$row['nome']] = (int) $row['id'];
}

// Mapa de ciclos por plano e meses
$ciclosMap = [];
$stmt = $db->query("
    SELECT pc.id, pc.plano_id, pc.meses
    FROM plano_ciclos pc
    WHERE pc.tenant_id = {$TENANT_ID} AND pc.ativo = 1
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $row['plano_id'] . '_' . $row['meses'];
    $ciclosMap[$key] = (int) $row['id'];
}

// IDs de status
$stmtStatusMatricula = $db->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1");
$statusAtivaId = (int) $stmtStatusMatricula->fetchColumn();

$stmtMotivoNova = $db->query("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1");
$motivoNovaId = (int) $stmtMotivoNova->fetchColumn();

$sucessos = 0;
$erros = 0;
$apenasVinculo = 0;

foreach ($alunos as $index => $alunoData) {
    $numero = $index + 1;
    echo "---\n";
    echo "[{$numero}] {$alunoData['nome']}\n";
    
    try {
        // 1. Verificar/criar usuário
        $email = strtolower(trim($alunoData['email']));
        $cpf = preg_replace('/[^0-9]/', '', $alunoData['cpf'] ?? '');
        
        if (empty($email)) {
            echo "  ⚠️  Email vazio, pulando...\n";
            $erros++;
            continue;
        }
        
        // Buscar usuário por email
        $stmtUsuario = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmtUsuario->execute([$email]);
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            // Criar novo usuário
            $senhaHash = password_hash('123456', PASSWORD_BCRYPT); // Senha padrão
            $stmtInsertUser = $db->prepare("
                INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash, ativo, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmtInsertUser->execute([
                $alunoData['nome'],
                $email,
                $cpf ?: null,
                $alunoData['telefone'] ?? null,
                $senhaHash
            ]);
            $usuarioId = (int) $db->lastInsertId();
            echo "  ✅ Usuário criado (ID: {$usuarioId})\n";
        } else {
            $usuarioId = (int) $usuario['id'];
            echo "  ℹ️  Usuário já existe (ID: {$usuarioId})\n";
        }
        
        // 2. Verificar/criar aluno
        $stmtAluno = $db->prepare("SELECT id FROM alunos WHERE usuario_id = ?");
        $stmtAluno->execute([$usuarioId]);
        $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);
        
        if (!$aluno) {
            $stmtInsertAluno = $db->prepare("
                INSERT INTO alunos (usuario_id, nome, cpf, email, telefone, tenant_id, ativo, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            $stmtInsertAluno->execute([
                $usuarioId,
                $alunoData['nome'],
                $cpf ?: null,
                $email,
                $alunoData['telefone'] ?? null,
                $TENANT_ID
            ]);
            $alunoId = (int) $db->lastInsertId();
            echo "  ✅ Aluno criado (ID: {$alunoId})\n";
        } else {
            $alunoId = (int) $aluno['id'];
            echo "  ℹ️  Aluno já existe (ID: {$alunoId})\n";
        }
        
        // 3. Adicionar vínculo tenant_usuario_papel (papel_id=1 para Aluno)
        $stmtCheckVinculo = $db->prepare("
            SELECT id FROM tenant_usuario_papel 
            WHERE usuario_id = ? AND tenant_id = ? AND papel_id = 1
        ");
        $stmtCheckVinculo->execute([$usuarioId, $TENANT_ID]);
        $vinculo = $stmtCheckVinculo->fetch(PDO::FETCH_ASSOC);
        
        if (!$vinculo) {
            $stmtInsertVinculo = $db->prepare("
                INSERT INTO tenant_usuario_papel (usuario_id, tenant_id, papel_id, ativo, created_at, updated_at)
                VALUES (?, ?, 1, 1, NOW(), NOW())
            ");
            $stmtInsertVinculo->execute([$usuarioId, $TENANT_ID]);
            echo "  ✅ Vínculo com tenant criado\n";
        } else {
            echo "  ℹ️  Vínculo com tenant já existe\n";
        }
        
        // 4. Criar matrícula (se plano foi especificado)
        $planoNome = trim($alunoData['plano_nome'] ?? '');
        
        if (empty($planoNome)) {
            echo "  ⚠️  Sem plano especificado, apenas associado ao tenant\n";
            $apenasVinculo++;
            continue;
        }
        
        // Buscar plano_id
        if (!isset($planosMap[$planoNome])) {
            echo "  ❌ Plano '{$planoNome}' não encontrado\n";
            $erros++;
            continue;
        }
        
        $planoId = $planosMap[$planoNome];
        $cicloMeses = (int) ($alunoData['ciclo_meses'] ?? 1);
        
        // Buscar ciclo_id
        $cicloKey = $planoId . '_' . $cicloMeses;
        if (!isset($ciclosMap[$cicloKey])) {
            echo "  ❌ Ciclo de {$cicloMeses} mês(es) não encontrado para plano '{$planoNome}'\n";
            $erros++;
            continue;
        }
        
        $planoCicloId = $ciclosMap[$cicloKey];
        
        // Buscar dados do ciclo para valor
        $stmtCiclo = $db->prepare("SELECT valor, meses FROM plano_ciclos WHERE id = ?");
        $stmtCiclo->execute([$planoCicloId]);
        $ciclo = $stmtCiclo->fetch(PDO::FETCH_ASSOC);
        
        $valor = (float) $ciclo['valor'];
        $meses = (int) $ciclo['meses'];
        
        // Verificar matrícula existente
        $stmtCheckMatricula = $db->prepare("
            SELECT m.id 
            FROM matriculas m
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.aluno_id = ? 
            AND m.tenant_id = ? 
            AND m.plano_id = ?
            AND sm.codigo IN ('ativa', 'pendente')
            AND m.proxima_data_vencimento >= CURDATE()
        ");
        $stmtCheckMatricula->execute([$alunoId, $TENANT_ID, $planoId]);
        $matriculaExistente = $stmtCheckMatricula->fetch(PDO::FETCH_ASSOC);
        
        if ($matriculaExistente) {
            echo "  ⚠️  Já possui matrícula ativa neste plano\n";
            continue;
        }
        
        // Datas
        $dataInicio = $alunoData['data_inicio'] ?? date('Y-m-d');
        $dataInicioObj = new DateTime($dataInicio);
        $dataVencimento = clone $dataInicioObj;
        $dataVencimento->modify("+{$meses} months");
        
        // Criar matrícula
        $stmtMatricula = $db->prepare("
            INSERT INTO matriculas 
            (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca, 
             data_matricula, data_inicio, data_vencimento, proxima_data_vencimento,
             valor, status_id, motivo_id, dia_vencimento, periodo_teste, criado_por, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'recorrente', ?, ?, ?, ?, ?, ?, ?, 5, 0, ?, NOW(), NOW())
        ");
        
        $stmtMatricula->execute([
            $TENANT_ID,
            $alunoId,
            $planoId,
            $planoCicloId,
            $dataInicio,
            $dataInicio,
            $dataVencimento->format('Y-m-d'),
            $dataVencimento->format('Y-m-d'),
            $valor,
            $statusAtivaId,
            $motivoNovaId,
            $CRIADO_POR
        ]);
        
        $matriculaId = (int) $db->lastInsertId();
        
        echo "  ✅ Matrícula criada (ID: {$matriculaId}) - {$planoNome} ({$cicloMeses} mês(es)) - R$ " . number_format($valor, 2, ',', '.') . "\n";
        $sucessos++;
        
    } catch (Exception $e) {
        echo "  ❌ Erro: " . $e->getMessage() . "\n";
        $erros++;
    }
}

echo "\n";
echo "==========================================\n";
echo "📊 RESUMO\n";
echo "==========================================\n";
echo "✅ Matrículas criadas: {$sucessos}\n";
echo "⚠️  Apenas vínculo: {$apenasVinculo}\n";
echo "❌ Erros: {$erros}\n";
echo "📋 Total processado: " . count($alunos) . "\n";
echo "==========================================\n";
