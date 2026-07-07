<?php

declare(strict_types=1);

/**
 * Testes somente leitura em PRODUÇÃO — não insere, altera nem deleta dados.
 *
 * Valida cenário da matrícula #354 (Erica): upgrade 1x→2x pagando diferença.
 *
 * Executar:
 *   php api/tests/MatriculaMigracaoProdTest.php
 *
 * Variáveis opcionais: PROD_DB_HOST, PROD_DB_NAME, PROD_DB_USER, PROD_DB_PASS
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../app/Services/MatriculaMigracaoService.php';

use App\Services\MatriculaMigracaoService;

date_default_timezone_set('America/Sao_Paulo');

const MATRICULA_ID = 354;
const PLANO_NOVO_ID = 8;   // 2x por Semana
const PLANO_CICLO_ID = 50; // Mensal R$120

function connectProd(): PDO
{
    $host = getenv('PROD_DB_HOST') ?: 'srv1314.hstgr.io';
    $name = getenv('PROD_DB_NAME') ?: 'u304177849_api';
    $user = getenv('PROD_DB_USER') ?: 'u304177849_api';
    $pass = getenv('PROD_DB_PASS') ?: '+DEEJ&7t';

    return new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
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

echo "=== Testes PRODUÇÃO (somente leitura) — matrícula #".MATRICULA_ID." ===\n";
echo 'Data: '.date('Y-m-d H:i:s')."\n\n";

try {
    $db = connectProd();
    $db->query('SELECT 1');
} catch (Throwable $e) {
    echo "❌ Falha ao conectar produção: {$e->getMessage()}\n";
    exit(1);
}

$ctx = (function () use ($db): array {
    $stmt = $db->prepare("
        SELECT m.*, sm.codigo AS status_codigo, p.nome AS plano_nome, p.modalidade_id,
               a.id AS aluno_id, a.usuario_id, u.nome AS aluno_nome, u.email
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        INNER JOIN planos p ON p.id = m.plano_id
        INNER JOIN alunos a ON a.id = m.aluno_id
        INNER JOIN usuarios u ON u.id = a.usuario_id
        WHERE m.id = ?
    ");
    $stmt->execute([MATRICULA_ID]);
    $row = $stmt->fetch();
    if (! $row) {
        throw new RuntimeException('Matrícula #'.MATRICULA_ID.' não encontrada em produção');
    }

    return $row;
})();

echo "Aluno: {$ctx['aluno_nome']} (usuario {$ctx['usuario_id']}, tenant {$ctx['tenant_id']})\n";
echo "Plano atual: {$ctx['plano_nome']} R\${$ctx['valor']} | status {$ctx['status_codigo']}\n";
echo "Vigência: {$ctx['data_inicio']} → ".($ctx['proxima_data_vencimento'] ?: $ctx['data_vencimento'])."\n\n";

$service = new MatriculaMigracaoService($db);
$tenantId = (int) $ctx['tenant_id'];
$userId = (int) $ctx['usuario_id'];
$alunoId = (int) $ctx['aluno_id'];

run('Matrícula #354 está ativa e elegível para migração', function () use ($service, $alunoId, $tenantId, $ctx) {
    $mat = $service->buscarMatriculaAtivaModalidade($alunoId, $tenantId, (int) $ctx['modalidade_id']);
    assertTrue($mat !== null, 'buscarMatriculaAtivaModalidade retornou null');
    assertEq(MATRICULA_ID, (int) $mat['id'], 'matricula_id');
    assertEq('ativa', $mat['status_codigo'], 'status');
});

run('Upgrade: crédito valor cheio do plano (R$70) com pagamento_origem_id', function () use ($service, $ctx, $tenantId, $db) {
    $stmt = $db->prepare("
        SELECT id FROM pagamentos_plano
        WHERE matricula_id = ? AND tenant_id = ? AND status_pagamento_id = 2
        ORDER BY data_vencimento DESC LIMIT 1
    ");
    $stmt->execute([MATRICULA_ID, $tenantId]);
    $ultimoPagoId = (int) $stmt->fetchColumn();

    $credito = $service->calcularCreditoMigracao($ctx, $tenantId, MATRICULA_ID, 120.0);

    assertEq('valor_cheio_plano', $credito['tipo_credito'], 'tipo_credito');
    assertFloat(70.0, (float) $credito['credito'], 'credito');
    assertEq($ultimoPagoId, (int) $credito['pagamento_origem_id'], 'pagamento_origem_id');
});

run('simular: upgrade 1x→2x Mensal retorna diferença R$50', function () use ($service, $userId, $tenantId) {
    $result = $service->simular($userId, $tenantId, PLANO_NOVO_ID, PLANO_CICLO_ID);

    assertEq(200, $result['status'], 'status');
    assertTrue($result['body']['success'] ?? false, 'success');
    assertFloat(70.0, (float) $result['body']['data']['credito']['valor'], 'credito');
    assertFloat(50.0, (float) $result['body']['data']['valor_parcela'], 'valor_parcela');
    assertEq('upgrade', $result['body']['data']['motivo_migracao'], 'motivo');
});

run('Upgrade usa valor cheio (R$70), não crédito proporcional inflado por proxima_data_vencimento', function () use ($service, $ctx, $tenantId) {
    $credito = $service->calcularCreditoMigracao($ctx, $tenantId, MATRICULA_ID, 120.0);

    assertEq('valor_cheio_plano', $credito['tipo_credito'], 'tipo_credito upgrade');
    assertFloat(70.0, (float) $credito['credito'], 'credito upgrade');

    $prox = (string) ($ctx['proxima_data_vencimento'] ?? $ctx['data_vencimento']);
    $proporcionalBug = MatriculaMigracaoService::calcularCreditoProporcional(
        (float) $ctx['valor'],
        (string) $ctx['data_inicio'],
        $prox,
        date('Y-m-d'),
    );

    assertTrue(
        (float) $credito['credito'] > (float) $proporcionalBug['credito'],
        'crédito de upgrade deve ser maior que proporcional errado com proxima_data_vencimento',
    );
});

run('pode_migrar: plano 2x está disponível para esta aluna', function () use ($db, $service, $alunoId, $tenantId, $ctx) {
    $mat = $service->buscarMatriculaAtivaModalidade($alunoId, $tenantId, (int) $ctx['modalidade_id']);
    $podeMigrar = $mat && (int) $mat['plano_id'] !== PLANO_NOVO_ID;
    assertTrue($podeMigrar, 'pode_migrar deveria ser true para plano 2x');

    $stmt = $db->prepare('SELECT id, nome, valor, ativo FROM planos WHERE id = ? AND tenant_id = ?');
    $stmt->execute([PLANO_NOVO_ID, $tenantId]);
    $plano = $stmt->fetch();
    assertTrue($plano && (int) $plano['ativo'] === 1, 'plano destino ativo');
    assertFloat(120.0, (float) $plano['valor'], 'valor plano base');
});

echo "\n{$passed}/{$total} testes de produção passaram (nenhuma escrita no banco)\n";
exit($passed === $total ? 0 : 1);
