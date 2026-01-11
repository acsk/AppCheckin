<?php

/**
 * Script para replicar turmas do tenant 5
 * Dia origem: 17 (2026-01-09)
 * Dias_semana: 2,3,4,5,6 (seg-sex)
 * Mês: 2026-01
 */

require 'vendor/autoload.php';

use App\Models\Turma;

try {
    $dsn = 'mysql:host=mysql;dbname=appcheckin;charset=utf8mb4';
    $user = 'root';
    $pass = 'root';
    
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $turmaModel = new Turma($pdo);
    
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║  Replicação de Turmas - Tenant 5                             ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    $tenantId = 5;
    $diaOrigemId = 17; // 2026-01-09 (quinta)
    $diasSemana = [2, 3, 4, 5, 6]; // seg-sex
    $mes = '2026-01';
    
    // 1. Buscar turmas do dia origem
    echo "1️⃣  Buscando turmas do dia origem (dia_id=$diaOrigemId)...\n";
    $turmasOrigem = $turmaModel->listarPorDia($tenantId, $diaOrigemId);
    echo "   ✅ Encontradas: " . count($turmasOrigem) . " turmas\n\n";
    
    foreach ($turmasOrigem as $t) {
        echo "   - Turma #{$t['id']}: {$t['horario_inicio']} - {$t['horario_fim']} ({$t['professor_nome']})\n";
    }
    
    // 2. Buscar dias destino
    echo "\n2️⃣  Buscando dias da semana (seg-sex de janeiro)...\n";
    
    $diasSemanaStr = implode(',', $diasSemana);
    $sql = "SELECT id, data FROM dias 
            WHERE DATE_FORMAT(data, '%Y-%m') = ? 
            AND DAYOFWEEK(data) IN ($diasSemanaStr)
            AND ativo = 1
            AND id != ?
            ORDER BY data ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $diaOrigemId]);
    $diasDestino = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   ✅ Encontrados: " . count($diasDestino) . " dias\n\n";
    
    // 3. Replicar turmas
    echo "3️⃣  Replicando turmas...\n\n";
    
    $totalCriadas = 0;
    $totalPuladas = 0;
    $detalhes = [];
    
    foreach ($turmasOrigem as $turmaOrigem) {
        $detalhe = [
            'turma_id' => $turmaOrigem['id'],
            'horario' => $turmaOrigem['horario_inicio'] . ' - ' . $turmaOrigem['horario_fim'],
            'criadas' => 0,
            'puladas' => 0,
            'dias_criadas' => []
        ];
        
        echo "   Processando turma #{$turmaOrigem['id']} ({$turmaOrigem['horario_inicio']} - {$turmaOrigem['horario_fim']}):\n";
        
        foreach ($diasDestino as $diaDestino) {
            // Verificar conflito
            $temConflito = $turmaModel->verificarHorarioOcupado(
                $tenantId,
                $diaDestino['id'],
                $turmaOrigem['horario_inicio'],
                $turmaOrigem['horario_fim']
            );
            
            if ($temConflito) {
                echo "      ⏭️  {$diaDestino['data']}: PULADA (conflito)\n";
                $detalhe['puladas']++;
                $totalPuladas++;
                continue;
            }
            
            // Criar nova turma
            $novaTurma = [
                'tenant_id' => $tenantId,
                'professor_id' => (int) $turmaOrigem['professor_id'],
                'modalidade_id' => (int) $turmaOrigem['modalidade_id'],
                'dia_id' => (int) $diaDestino['id'],
                'horario_inicio' => $turmaOrigem['horario_inicio'],
                'horario_fim' => $turmaOrigem['horario_fim'],
                'nome' => $turmaOrigem['nome'] ?? '',
                'limite_alunos' => (int) $turmaOrigem['limite_alunos'],
                'ativo' => 1
            ];
            
            $idNovo = $turmaModel->create($novaTurma);
            if ($idNovo) {
                echo "      ✅ {$diaDestino['data']}: CRIADA (ID: $idNovo)\n";
                $detalhe['criadas']++;
                $detalhe['dias_criadas'][] = $diaDestino['data'];
                $totalCriadas++;
            }
        }
        
        $detalhes[] = $detalhe;
        echo "\n";
    }
    
    // 4. Resumo
    echo "╔════════════════════════════════════════════════════════════════╗\n";
    echo "║                         RESULTADO FINAL                        ║\n";
    echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    
    echo "📊 Estatísticas:\n";
    echo "   ├─ Turmas do dia origem: " . count($turmasOrigem) . "\n";
    echo "   ├─ Dias destino: " . count($diasDestino) . "\n";
    echo "   ├─ Total de turmas criadas: $totalCriadas ✅\n";
    echo "   └─ Total puladas por conflito: $totalPuladas ⏭️\n\n";
    
    echo "📋 Detalhes por turma:\n";
    foreach ($detalhes as $d) {
        echo "   Turma #{$d['turma_id']} ({$d['horario']}):\n";
        echo "      ├─ Criadas: {$d['criadas']}\n";
        echo "      └─ Puladas: {$d['puladas']}\n";
        
        if (!empty($d['dias_criadas'])) {
            echo "      Datas criadas: " . implode(', ', $d['dias_criadas']) . "\n";
        }
    }
    
    echo "\n✅ Operação concluída com sucesso!\n";
    
} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
