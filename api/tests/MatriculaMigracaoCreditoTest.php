<?php

/**
 * Teste do cálculo de crédito proporcional na migração de plano.
 * Executar: php api/tests/MatriculaMigracaoCreditoTest.php
 */

require_once __DIR__.'/../app/Services/MatriculaMigracaoService.php';

use App\Services\MatriculaMigracaoService;

function assertEq(float $expected, float $actual, string $label): void
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

run('70% usado → 30% de crédito em plano R$100', function () {
    // 30 dias de 30: início 2026-05-01, vence 2026-05-31, hoje 2026-05-22 → ~9 dias restantes
    $r = MatriculaMigracaoService::calcularCreditoProporcional(100.0, '2026-05-01', '2026-05-31', '2026-05-22');
    assertEq(9, (float) $r['dias_restantes'], 'dias_restantes');
    assertEq(30.0, (float) $r['credito'], 'credito');
    assertEq(70.0, (float) $r['valor_consumido'], 'valor_consumido');
});

run('Plano encerrado → crédito proporcional zero', function () {
    $r = MatriculaMigracaoService::calcularCreditoProporcional(100.0, '2026-05-01', '2026-05-31', '2026-06-08');
    assertEq(0, (float) $r['credito'], 'credito');
    assertEq(0, (float) $r['dias_restantes'], 'dias_restantes');
});

run('Início do ciclo → crédito integral', function () {
    $r = MatriculaMigracaoService::calcularCreditoProporcional(100.0, '2026-06-01', '2026-06-30', '2026-06-01');
    assertEq(29, (float) $r['dias_restantes'], 'dias_restantes');
    assertEq(100.0, (float) $r['credito'], 'credito');
});

run('Parcela = novo plano - crédito', function () {
    $credito = 30.0;
    $novo = 200.0;
    $parcela = max(0, round($novo - min($credito, $novo), 2));
    assertEq(170.0, $parcela, 'parcela');
});

echo "\n{$passed}/{$total} testes passaram\n";
exit($passed === $total ? 0 : 1);
