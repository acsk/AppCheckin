<?php

declare(strict_types=1);

/**
 * Testes funcionais de migração de plano (MatriculaMigracaoService + banco).
 *
 * Executar:
 *   php api/tests/MatriculaMigracaoFunctionalTest.php
 *
 * Requer MySQL (docker-compose: porta 3307, DB appcheckin).
 */

require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/Support/MigracaoFunctionalFixture.php';

use App\Services\MatriculaMigracaoService;

date_default_timezone_set('America/Sao_Paulo');

function connectDb(): PDO
{
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3307';
    $name = getenv('DB_NAME') ?: 'appcheckin';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'root';

    return new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
}

function assertTrue(bool $cond, string $msg): void
{
    if (! $cond) {
        throw new RuntimeException($msg);
    }
}

function assertEq(mixed $expected, mixed $actual, string $label): void
{
    if ($expected != $actual) {
        throw new RuntimeException("{$label}: esperado ".var_export($expected, true).', obteve '.var_export($actual, true));
    }
}

function assertFloat(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.01) {
        throw new RuntimeException("{$label}: esperado {$expected}, obteve {$actual}");
    }
}

$total = 0;
$passed = 0;

function run(string $name, callable $fn): void
{
    global $total, $passed;
    $total++;
    try {
        $fn();
        $passed++;
        echo "✅ {$name}\n";
    } catch (Throwable $e) {
        echo "❌ {$name}: {$e->getMessage()}\n";
    }
}

try {
    $db = connectDb();
    $db->query('SELECT 1');
} catch (Throwable $e) {
    echo "❌ Não foi possível conectar ao MySQL: {$e->getMessage()}\n";
    echo "   Suba o banco: docker compose up -d mysql\n";
    exit(1);
}

run('Upgrade: calcularCreditoMigracao retorna valor_cheio_plano com pagamento_origem_id', function () use ($db) {
    $fixture = new MigracaoFunctionalFixture();
    $fixture->seed($db);

    try {
        $service = new MatriculaMigracaoService($db);
        $matricula = $fixture->matriculaRow($db);
        $credito = $service->calcularCreditoMigracao($matricula, $fixture->tenantId, $fixture->matriculaId, 100.0);

        assertEq('valor_cheio_plano', $credito['tipo_credito'], 'tipo_credito');
        assertFloat(70.0, (float) $credito['credito'], 'credito');
        assertEq($fixture->pagamentoPagoId, (int) $credito['pagamento_origem_id'], 'pagamento_origem_id');

        $parcela = max(0, round(100.0 - min((float) $credito['credito'], 100.0), 2));
        assertFloat(30.0, $parcela, 'diferenca upgrade');
    } finally {
        $fixture->cleanup($db);
    }
});

run('Proporcional: usa data_vencimento e ignora proxima_data_vencimento', function () use ($db) {
    $fixture = new MigracaoFunctionalFixture();
    $fixture->seed($db);

    try {
        $service = new MatriculaMigracaoService($db);
        $matricula = $fixture->matriculaRow($db);

        // Downgrade 100 → 70: crédito proporcional pelo ciclo vigente (não upgrade)
        $credito = $service->calcularCreditoMigracao($matricula, $fixture->tenantId, $fixture->matriculaId, 70.0);

        assertEq('proporcional', $credito['tipo_credito'], 'tipo_credito');
        assertTrue((float) $credito['credito'] > 0, 'credito proporcional deve ser > 0');
        assertTrue((float) $credito['credito'] < 70.0, 'credito proporcional deve ser < valor cheio');

        // Com proxima_data_vencimento distante, o bug antigo geraria crédito menor
        $venc = (string) $matricula['data_vencimento'];
        $prox = (string) $matricula['proxima_data_vencimento'];
        assertTrue($venc !== $prox, 'fixture deve ter proxima_data_vencimento diferente');

        $bugAntigo = MatriculaMigracaoService::calcularCreditoProporcional(
            70.0,
            (string) $matricula['data_inicio'],
            $prox,
            date('Y-m-d'),
        );
        assertTrue(
            (float) $credito['credito'] < (float) $bugAntigo['credito'],
            'credito com data_vencimento deve ser menor que o cálculo errado com proxima_data_vencimento',
        );

        $esperado = MatriculaMigracaoService::calcularCreditoProporcional(
            70.0,
            (string) $matricula['data_inicio'],
            $venc,
            date('Y-m-d'),
        );
        assertFloat((float) $esperado['credito'], (float) $credito['credito'], 'credito proporcional esperado');
    } finally {
        $fixture->cleanup($db);
    }
});

run('simular: upgrade 1x→2x retorna diferença R$30', function () use ($db) {
    $fixture = new MigracaoFunctionalFixture();
    $fixture->seed($db);

    try {
        $service = new MatriculaMigracaoService($db);
        $result = $service->simular($fixture->usuarioId, $fixture->tenantId, $fixture->planoNovoId, null);

        assertEq(200, $result['status'], 'status HTTP');
        assertTrue($result['body']['success'] ?? false, 'success');
        assertFloat(70.0, (float) $result['body']['data']['credito']['valor'], 'credito simulado');
        assertFloat(30.0, (float) $result['body']['data']['valor_parcela'], 'valor_parcela simulado');
        assertEq('upgrade', $result['body']['data']['motivo_migracao'], 'motivo');
    } finally {
        $fixture->cleanup($db);
    }
});

run('migrar: upgrade cancela pagamento pago e parcela aberta', function () use ($db) {
    $fixture = new MigracaoFunctionalFixture();
    $fixture->seed($db);

    try {
        $service = new MatriculaMigracaoService($db);
        $result = $service->migrar($fixture->usuarioId, $fixture->tenantId, [
            'plano_id' => $fixture->planoNovoId,
            'metodo_pagamento' => 'checkout',
        ]);

        // MP pode falhar sem credenciais; migração persiste antes do checkout
        $statusOk = in_array($result['status'], [200, 400, 500], true);
        assertTrue($statusOk, 'migrar deve retornar resposta estruturada');

        $stmtPago = $db->prepare('SELECT status_pagamento_id, observacoes FROM pagamentos_plano WHERE id = ?');
        $stmtPago->execute([$fixture->pagamentoPagoId]);
        $pago = $stmtPago->fetch();

        assertEq(4, (int) $pago['status_pagamento_id'], 'pagamento pago deve ser cancelado');
        assertTrue(
            str_contains((string) $pago['observacoes'], 'Convertido em crédito'),
            'observação de conversão em crédito',
        );

        $stmtAberto = $db->prepare('SELECT status_pagamento_id FROM pagamentos_plano WHERE id = ?');
        $stmtAberto->execute([$fixture->pagamentoAbertoId]);
        $aberto = $stmtAberto->fetch();

        assertEq(4, (int) $aberto['status_pagamento_id'], 'parcela aberta deve ser cancelada');

        $stmtMat = $db->prepare('SELECT plano_id, valor FROM matriculas WHERE id = ?');
        $stmtMat->execute([$fixture->matriculaId]);
        $mat = $stmtMat->fetch();

        assertEq($fixture->planoNovoId, (int) $mat['plano_id'], 'matricula deve apontar para novo plano');
        assertFloat(100.0, (float) $mat['valor'], 'valor da matricula atualizado');

        $stmtNovoPag = $db->prepare('
            SELECT valor, status_pagamento_id FROM pagamentos_plano
            WHERE matricula_id = ? AND id NOT IN (?, ?)
            ORDER BY id DESC LIMIT 1
        ');
        $stmtNovoPag->execute([$fixture->matriculaId, $fixture->pagamentoPagoId, $fixture->pagamentoAbertoId]);
        $novoPag = $stmtNovoPag->fetch();

        assertTrue($novoPag !== false, 'deve existir nova parcela da migração');
        assertFloat(30.0, (float) $novoPag['valor'], 'valor da nova parcela');
        assertEq(1, (int) $novoPag['status_pagamento_id'], 'nova parcela pendente');
    } finally {
        $fixture->cleanup($db);
    }
});

echo "\n{$passed}/{$total} testes funcionais passaram\n";
exit($passed === $total ? 0 : 1);
