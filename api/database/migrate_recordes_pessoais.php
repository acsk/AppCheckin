<?php
/**
 * Migration: Criar tabelas de Recordes Pessoais
 * Execução: php database/migrate_recordes_pessoais.php
 */

require __DIR__ . '/../vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$db = require __DIR__ . '/../config/database.php';

echo "=== Migração: Recordes Pessoais ===\n\n";

try {
    // 1. Criar tabela recorde_provas
    $db->exec("
        CREATE TABLE IF NOT EXISTS recorde_provas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            modalidade_id INT NULL,
            nome VARCHAR(100) NOT NULL,
            distancia_metros INT NULL,
            estilo VARCHAR(50) NULL,
            unidade_medida ENUM('tempo', 'metros', 'repeticoes', 'peso_kg') NOT NULL DEFAULT 'tempo',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant (tenant_id),
            INDEX idx_ativo (tenant_id, ativo),
            INDEX idx_modalidade (tenant_id, modalidade_id),
            FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recorde_provas criada\n";

    // 2. Criar tabela recordes_pessoais
    $db->exec("
        CREATE TABLE IF NOT EXISTS recordes_pessoais (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            aluno_id INT NULL,
            prova_id INT NOT NULL,
            tempo_segundos DECIMAL(10,2) NULL,
            valor DECIMAL(10,2) NULL,
            data_registro DATE NOT NULL,
            observacoes TEXT NULL,
            origem ENUM('aluno', 'escola') NOT NULL DEFAULT 'aluno',
            registrado_por INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tenant_aluno (tenant_id, aluno_id),
            INDEX idx_tenant_prova (tenant_id, prova_id),
            INDEX idx_origem (tenant_id, origem),
            INDEX idx_ranking (tenant_id, prova_id, tempo_segundos),
            FOREIGN KEY (prova_id) REFERENCES recorde_provas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabela recordes_pessoais criada\n";

    // 2.5. Adicionar coluna modalidade_id se não existir (para re-run)
    try {
        $db->exec("ALTER TABLE recorde_provas ADD COLUMN modalidade_id INT NULL AFTER tenant_id");
        $db->exec("ALTER TABLE recorde_provas ADD INDEX idx_modalidade (tenant_id, modalidade_id)");
        $db->exec("ALTER TABLE recorde_provas ADD FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL");
        echo "✅ Coluna modalidade_id adicionada à recorde_provas\n";
    } catch (\Exception $e) {
        // Coluna já existe, ignorar
    }

    // 3. Inserir provas padrão para todos os tenants ativos
    $stmtTenants = $db->query("SELECT id FROM tenants WHERE ativo = 1");
    $tenants = $stmtTenants->fetchAll(PDO::FETCH_COLUMN);

    $provasPadrao = [
        ['nome' => '25m Crawl',     'distancia' => 25,  'estilo' => 'Crawl',     'ordem' => 1],
        ['nome' => '50m Crawl',     'distancia' => 50,  'estilo' => 'Crawl',     'ordem' => 2],
        ['nome' => '100m Crawl',    'distancia' => 100, 'estilo' => 'Crawl',     'ordem' => 3],
        ['nome' => '200m Crawl',    'distancia' => 200, 'estilo' => 'Crawl',     'ordem' => 4],
        ['nome' => '25m Costas',    'distancia' => 25,  'estilo' => 'Costas',    'ordem' => 5],
        ['nome' => '50m Costas',    'distancia' => 50,  'estilo' => 'Costas',    'ordem' => 6],
        ['nome' => '25m Peito',     'distancia' => 25,  'estilo' => 'Peito',     'ordem' => 7],
        ['nome' => '50m Peito',     'distancia' => 50,  'estilo' => 'Peito',     'ordem' => 8],
        ['nome' => '25m Borboleta', 'distancia' => 25,  'estilo' => 'Borboleta', 'ordem' => 9],
        ['nome' => '50m Borboleta', 'distancia' => 50,  'estilo' => 'Borboleta', 'ordem' => 10],
    ];

    $stmtInsert = $db->prepare("
        INSERT INTO recorde_provas (tenant_id, modalidade_id, nome, distancia_metros, estilo, unidade_medida, ordem)
        SELECT ?, ?, ?, ?, ?, 'tempo', ?
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM recorde_provas WHERE tenant_id = ? AND nome = ?
        )
    ");

    // Buscar modalidade de natação de cada tenant
    $stmtModalidade = $db->prepare("
        SELECT id FROM modalidades
        WHERE tenant_id = ? AND LOWER(nome) LIKE '%nata%' AND ativo = 1
        ORDER BY id ASC LIMIT 1
    ");

    foreach ($tenants as $tenantId) {
        // Buscar modalidade natação do tenant
        $stmtModalidade->execute([$tenantId]);
        $modalidadeId = $stmtModalidade->fetchColumn() ?: null;

        $count = 0;
        foreach ($provasPadrao as $prova) {
            $stmtInsert->execute([
                $tenantId, $modalidadeId, $prova['nome'], $prova['distancia'], $prova['estilo'], $prova['ordem'],
                $tenantId, $prova['nome']
            ]);
            if ($stmtInsert->rowCount() > 0) $count++;
        }
        if ($count > 0) {
            echo "  📋 Tenant #{$tenantId}: {$count} provas padrão inseridas\n";
        }
    }

    echo "\n✅ Migração concluída com sucesso!\n";

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
