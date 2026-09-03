<?php
/**
 * Corrige vencimento divergente pós-migração (ex.: matrícula #335).
 *
 * Uso:
 *   php debug_corrigir_vencimento_matricula.php --matricula=335
 *   php debug_corrigir_vencimento_matricula.php --matricula=335 --apply
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/Services/AuditoriaCreditoMigracaoService.php';

$matriculaId = 335;
$tenantId = 0;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--matricula=')) {
        $matriculaId = (int) substr($arg, strlen('--matricula='));
    }
    if (str_starts_with($arg, '--tenant=')) {
        $tenantId = (int) substr($arg, strlen('--tenant='));
    }
}

$apply = in_array('--apply', $argv ?? [], true);

function conectarPdo(): PDO
{
    $useProd = getenv('PROD_DB_HOST') || getenv('PROD_DB_NAME');
    if ($useProd) {
        return new PDO(
            'mysql:host=' . (getenv('PROD_DB_HOST') ?: 'localhost') . ';port=3306;dbname=' . getenv('PROD_DB_NAME') . ';charset=utf8mb4',
            getenv('PROD_DB_USER') ?: '',
            getenv('PROD_DB_PASS') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    $pdo = require __DIR__ . '/config/database.php';
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

try {
    $pdo = conectarPdo();

    if ($tenantId <= 0) {
        $stmt = $pdo->prepare('SELECT tenant_id FROM matriculas WHERE id = ? LIMIT 1');
        $stmt->execute([$matriculaId]);
        $tenantId = (int) $stmt->fetchColumn();
    }

    if ($tenantId <= 0) {
        throw new RuntimeException("Matrícula #{$matriculaId} não encontrada.");
    }

    echo "=== Matrícula #{$matriculaId} (tenant {$tenantId}) ===" . PHP_EOL;
    echo ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL;

    $service = new \App\Services\AuditoriaCreditoMigracaoService($pdo);

    if (!$apply) {
        $stmt = $pdo->prepare('SELECT data_inicio, data_vencimento, proxima_data_vencimento FROM matriculas WHERE id = ?');
        $stmt->execute([$matriculaId]);
        $atual = $stmt->fetch(PDO::FETCH_ASSOC);
        echo 'Atual: ' . json_encode($atual, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo 'Use --apply para gravar (início+1 mês após reset de migração, alinha parcela e assinatura).' . PHP_EOL;
        exit(0);
    }

    $result = $service->repararVencimentoMatricula($tenantId, $matriculaId);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(($result['ok'] ?? false) ? 0 : 1);
} catch (Throwable $e) {
    echo 'ERRO: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
