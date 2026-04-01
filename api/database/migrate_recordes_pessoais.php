<?php
/**
 * Migration: Criar tabelas de Recordes (modelagem genérica)
 * Suporta qualquer modalidade: natação, cross, musculação, corrida, etc.
 * Execução: php database/migrate_recordes_pessoais.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = require __DIR__ . '/../config/database.php';

echo "=== Migração: Recordes (Modelagem Genérica) ===\n\n";

try {
    // 1. Criar tabela recorde_definicoes
    $db->exec("
        CREATE TABLE IF NOT EXISTS recorde_definicoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            modalidade_id INT NULL,
            nome VARCHAR(150) NOT NULL,
            categoria ENUM('movimento', 'prova', 'workout', 'teste_fisico') NOT NULL DEFAULT 'movimento',
            descricao TEXT NULL,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_ativo (tenant_id, ativo),
            INDEX idx_modalidade (tenant_id, modalidade_id),
            INDEX idx_categoria (tenant_id, categoria),
            FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recorde_definicoes criada\n";

    // 2. Criar tabela recorde_definicao_metricas
    $db->exec("
        CREATE TABLE IF NOT EXISTS recorde_definicao_metricas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            definicao_id INT NOT NULL,
            codigo VARCHAR(50) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            tipo_valor ENUM('inteiro', 'decimal', 'tempo_ms') NOT NULL DEFAULT 'decimal',
            unidade VARCHAR(30) NULL,
            ordem_comparacao INT NOT NULL DEFAULT 1,
            direcao ENUM('maior_melhor', 'menor_melhor') NOT NULL,
            obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_definicao_codigo (definicao_id, codigo),
            INDEX idx_definicao (definicao_id),
            FOREIGN KEY (definicao_id) REFERENCES recorde_definicoes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recorde_definicao_metricas criada\n";

    // 3. Criar tabela recordes
    $db->exec("
        CREATE TABLE IF NOT EXISTS recordes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            aluno_id INT NULL,
            definicao_id INT NOT NULL,
            origem ENUM('aluno', 'academia') NOT NULL DEFAULT 'aluno',
            data_recorde DATE NOT NULL,
            observacoes TEXT NULL,
            registrado_por INT NULL,
            valido TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_aluno (tenant_id, aluno_id),
            INDEX idx_tenant_definicao (tenant_id, definicao_id),
            INDEX idx_origem (tenant_id, origem),
            INDEX idx_data (tenant_id, data_recorde),
            FOREIGN KEY (definicao_id) REFERENCES recorde_definicoes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recordes criada\n";

    // 4. Criar tabela recorde_valores
    $db->exec("
        CREATE TABLE IF NOT EXISTS recorde_valores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recorde_id INT NOT NULL,
            metrica_id INT NOT NULL,
            valor_int BIGINT NULL,
            valor_decimal DECIMAL(12,3) NULL,
            valor_tempo_ms BIGINT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_recorde (recorde_id),
            INDEX idx_metrica (metrica_id),
            UNIQUE KEY uk_recorde_metrica (recorde_id, metrica_id),
            FOREIGN KEY (recorde_id) REFERENCES recordes(id) ON DELETE CASCADE,
            FOREIGN KEY (metrica_id) REFERENCES recorde_definicao_metricas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recorde_valores criada\n";

    // 5. Inserir definições padrão para todos os tenants ativos
    $stmtTenants = $db->query("SELECT id FROM tenants WHERE ativo = 1");
    $tenants = $stmtTenants->fetchAll(PDO::FETCH_COLUMN);

    // Definições padrão com suas métricas
    $definicoesPadrao = [
        // Natação
        ['nome' => '25m Crawl',     'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 1],
        ['nome' => '50m Crawl',     'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 2],
        ['nome' => '100m Crawl',    'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 3],
        ['nome' => '50m Costas',    'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 4],
        ['nome' => '50m Peito',     'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 5],
        ['nome' => '50m Borboleta', 'categoria' => 'prova', 'tipo' => 'natacao', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 6],
        // Musculação / Cross
        ['nome' => 'Deadlift',      'categoria' => 'movimento', 'tipo' => 'forca', 'metricas' => [
            ['codigo' => 'peso_kg', 'nome' => 'Carga', 'tipo_valor' => 'decimal', 'unidade' => 'kg', 'direcao' => 'maior_melhor']
        ], 'ordem' => 10],
        ['nome' => 'Back Squat',    'categoria' => 'movimento', 'tipo' => 'forca', 'metricas' => [
            ['codigo' => 'peso_kg', 'nome' => 'Carga', 'tipo_valor' => 'decimal', 'unidade' => 'kg', 'direcao' => 'maior_melhor']
        ], 'ordem' => 11],
        ['nome' => 'BMU Máximo de Repetições', 'categoria' => 'movimento', 'tipo' => 'forca', 'metricas' => [
            ['codigo' => 'repeticoes', 'nome' => 'Repetições', 'tipo_valor' => 'inteiro', 'unidade' => 'reps', 'direcao' => 'maior_melhor']
        ], 'ordem' => 12],
        // Corrida
        ['nome' => 'Corrida 5km',   'categoria' => 'prova', 'tipo' => 'corrida', 'metricas' => [
            ['codigo' => 'tempo_ms', 'nome' => 'Tempo', 'tipo_valor' => 'tempo_ms', 'unidade' => 'ms', 'direcao' => 'menor_melhor']
        ], 'ordem' => 20],
    ];

    $stmtInsertDef = $db->prepare("
        INSERT INTO recorde_definicoes (tenant_id, modalidade_id, nome, categoria, ordem)
        SELECT ?, ?, ?, ?, ?
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM recorde_definicoes WHERE tenant_id = ? AND nome = ?
        )
    ");

    $stmtInsertMetrica = $db->prepare("
        INSERT INTO recorde_definicao_metricas (definicao_id, codigo, nome, tipo_valor, unidade, ordem_comparacao, direcao)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");

    $stmtGetDefId = $db->prepare("
        SELECT id FROM recorde_definicoes WHERE tenant_id = ? AND nome = ? LIMIT 1
    ");

    $stmtCheckMetrica = $db->prepare("
        SELECT COUNT(*) FROM recorde_definicao_metricas WHERE definicao_id = ?
    ");

    // Buscar modalidades por tipo
    $stmtModalidadeNatacao = $db->prepare("
        SELECT id FROM modalidades WHERE tenant_id = ? AND LOWER(nome) LIKE '%nata%' AND ativo = 1 ORDER BY id ASC LIMIT 1
    ");
    $stmtModalidadeForca = $db->prepare("
        SELECT id FROM modalidades WHERE tenant_id = ? AND (LOWER(nome) LIKE '%cross%' OR LOWER(nome) LIKE '%muscula%' OR LOWER(nome) LIKE '%funcional%') AND ativo = 1 ORDER BY id ASC LIMIT 1
    ");
    $stmtModalidadeCorrida = $db->prepare("
        SELECT id FROM modalidades WHERE tenant_id = ? AND (LOWER(nome) LIKE '%corrid%' OR LOWER(nome) LIKE '%running%') AND ativo = 1 ORDER BY id ASC LIMIT 1
    ");

    foreach ($tenants as $tenantId) {
        $stmtModalidadeNatacao->execute([$tenantId]);
        $modNatacao = $stmtModalidadeNatacao->fetchColumn() ?: null;

        $stmtModalidadeForca->execute([$tenantId]);
        $modForca = $stmtModalidadeForca->fetchColumn() ?: null;

        $stmtModalidadeCorrida->execute([$tenantId]);
        $modCorrida = $stmtModalidadeCorrida->fetchColumn() ?: null;

        $count = 0;
        foreach ($definicoesPadrao as $def) {
            $modalidadeId = null;
            if ($def['tipo'] === 'natacao') $modalidadeId = $modNatacao;
            elseif ($def['tipo'] === 'forca') $modalidadeId = $modForca;
            elseif ($def['tipo'] === 'corrida') $modalidadeId = $modCorrida;

            $stmtInsertDef->execute([
                $tenantId, $modalidadeId, $def['nome'], $def['categoria'], $def['ordem'],
                $tenantId, $def['nome']
            ]);

            if ($stmtInsertDef->rowCount() > 0) {
                $defId = (int) $db->lastInsertId();
                foreach ($def['metricas'] as $metrica) {
                    $stmtInsertMetrica->execute([
                        $defId, $metrica['codigo'], $metrica['nome'],
                        $metrica['tipo_valor'], $metrica['unidade'], $metrica['direcao']
                    ]);
                }
                $count++;
            } else {
                // Definição já existe, garantir que tem métricas
                $stmtGetDefId->execute([$tenantId, $def['nome']]);
                $defId = (int) $stmtGetDefId->fetchColumn();
                if ($defId) {
                    $stmtCheckMetrica->execute([$defId]);
                    if ((int) $stmtCheckMetrica->fetchColumn() === 0) {
                        foreach ($def['metricas'] as $metrica) {
                            $stmtInsertMetrica->execute([
                                $defId, $metrica['codigo'], $metrica['nome'],
                                $metrica['tipo_valor'], $metrica['unidade'], $metrica['direcao']
                            ]);
                        }
                    }
                }
            }
        }

        if ($count > 0) {
            echo "  📋 Tenant #{$tenantId}: {$count} definições padrão inseridas\n";
        }
    }

    echo "\n✅ Migração concluída com sucesso!\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
