<?php

declare(strict_types=1);

use App\Services\PacoteDescontoService;

require_once __DIR__ . '/../vendor/autoload.php';

$ok = true;

$prefixo = PacoteDescontoService::prefixoContrato(7);
if ($prefixo !== '[PACOTE#7]') {
    echo "FAIL: prefixoContrato\n";
    $ok = false;
}

$motivo = PacoteDescontoService::montarMotivo(
    7,
    'Plano Familia 2x Bim 720',
    '2026-08-13',
    '2026-10-13'
);
$esperado = '[PACOTE#7] Plano Familia 2x Bim 720 — vigência 13/08/2026 a 13/10/2026';
if ($motivo !== $esperado) {
    echo "FAIL: montarMotivo got {$motivo}\n";
    $ok = false;
}

if (PacoteDescontoService::calcularValorDesconto(200, 180) !== 20.0) {
    echo "FAIL: desconto 200-180\n";
    $ok = false;
}

if (PacoteDescontoService::calcularValorDesconto(180, 180) !== 0.0) {
    echo "FAIL: sem desconto quando valores iguais\n";
    $ok = false;
}

if (PacoteDescontoService::calcularValorDesconto(150, 180) !== 0.0) {
    echo "FAIL: desconto não pode ser negativo\n";
    $ok = false;
}

if ($ok) {
    echo "OK PacoteDescontoService\n";
    exit(0);
}

exit(1);
