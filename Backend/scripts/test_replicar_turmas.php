<?php

require 'vendor/autoload.php';

use App\Models\Turma;
use App\Models\Dia;
use App\Services\JWTService;

// Configurar banco de dados
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
    
    // Simular um POST para replicar turmas
    // Turmas de 2026-01-09 (dia_id=17, quinta-feira)
    // Replicar para todas as quintas-feiras de janeiro (dias_semana=[5])
    
    echo "=== TESTE: Replicar turmas para quintas-feiras de janeiro ===\n\n";
    
    $tenantId = 5; // tenant do crossfit
    $diaOrigemId = 18; // Um dia com turmas (sábado 2026-01-10)
    $diasSemana = [7]; // Sábado (DAYOFWEEK = 7)
    $mes = '2026-01';
    
    // 1. Buscar turmas do dia origem
    echo "1. Buscando turmas do dia origem (dia_id=$diaOrigemId, tenant_id=$tenantId)...\n";
    
    // Debug direto no banco
    $stmt = $pdo->prepare("SELECT * FROM turmas WHERE dia_id = ? AND tenant_id = ?");
    $stmt->execute([$diaOrigemId, $tenantId]);
    $turmasRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Turmas brutes do banco: " . count($turmasRaw) . "\n";
    
    // Via método
    $turmasOrigem = $turmaModel->listarPorDia($tenantId, $diaOrigemId);
    echo "   Encontradas via listarPorDia: " . count($turmasOrigem) . " turmas\n";
    foreach ($turmasOrigem as $t) {
        echo "   - Turma #{$t['id']}: {$t['horario_inicio']} - {$t['horario_fim']} (Prof: {$t['professor_id']}, Modalidade: {$t['modalidade_id']})\n";
    }
    
    // 2. Buscar dias do mês com o mesmo dia da semana
    echo "\n2. Buscando dias da semana para replicação ($mes, dia da semana=7)...\n";
    
    // Construir SQL
    $sql = "SELECT * FROM dias 
            WHERE DATE_FORMAT(data, '%Y-%m') = ? 
            AND DAYOFWEEK(data) = 7
            AND ativo = 1
            AND id != ?
            ORDER BY data ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mes, $diaOrigemId]);
    $diasDestino = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "   Encontrados: " . count($diasDestino) . " dias\n";
    foreach ($diasDestino as $d) {
        echo "   - Dia #{$d['id']}: {$d['data']}\n";
    }
    
    // 3. Simular replicação
    echo "\n3. Simulando replicação...\n";
    
    $totalCriadas = 0;
    $totalPuladas = 0;
    $turmasCriadas = [];
    
    foreach ($turmasOrigem as $turmaOrigem) {
        echo "\n   Processando turma #{$turmaOrigem['id']} ({$turmaOrigem['horario_inicio']} - {$turmaOrigem['horario_fim']}):\n";
        
        foreach ($diasDestino as $diaDestino) {
            // Verificar conflito
            $temConflito = $turmaModel->verificarHorarioOcupado(
                $tenantId,
                $diaDestino['id'],
                $turmaOrigem['horario_inicio'],
                $turmaOrigem['horario_fim']
            );
            
            if ($temConflito) {
                echo "     - {$diaDestino['data']}: PULADA (horário ocupado)\n";
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
                echo "     - {$diaDestino['data']}: CRIADA (ID: $idNovo)\n";
                $totalCriadas++;
                $turmasCriadas[] = $idNovo;
            } else {
                echo "     - {$diaDestino['data']}: ERRO ao criar\n";
            }
        }
    }
    
    echo "\n=== RESULTADO ===\n";
    echo "Total de turmas do dia origem: " . count($turmasOrigem) . "\n";
    echo "Total de dias para replicação: " . count($diasDestino) . "\n";
    echo "Total criadas: $totalCriadas\n";
    echo "Total puladas: $totalPuladas\n";
    echo "Turmas criadas com IDs: " . implode(', ', $turmasCriadas) . "\n";
    
    // Verificar dados criados
    echo "\n=== VERIFICANDO DADOS ===\n";
    $stmt = $pdo->prepare("
        SELECT d.data, t.id, t.horario_inicio, t.horario_fim, t.professor_id
        FROM turmas t
        JOIN dias d ON t.dia_id = d.id
        WHERE t.tenant_id = ? 
        AND DATE_FORMAT(d.data, '%Y-%m') = ?
        AND DAYOFWEEK(d.data) = 7
        ORDER BY d.data ASC, t.horario_inicio ASC
    ");
    $stmt->execute([$tenantId, $mes]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Turmas nos sábados de janeiro:\n";
    foreach ($resultados as $r) {
        echo "  - {$r['data']}: Turma #{$r['id']} {$r['horario_inicio']} - {$r['horario_fim']} (Prof: {$r['professor_id']})\n";
    }
    
} catch (\Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
