<?php

declare(strict_types=1);

use App\Support\AniversarioUtil;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Sao_Paulo');

$ok = true;

// Aniversário hoje
$ref = new DateTimeImmutable('2026-03-05', new DateTimeZone('America/Sao_Paulo'));
if (!AniversarioUtil::ehAniversarioHoje('2000-03-05', $ref)) {
    echo "FAIL: deveria ser aniversário em 05/03\n";
    $ok = false;
}
if (AniversarioUtil::ehAniversarioHoje('2000-03-06', $ref)) {
    echo "FAIL: não deveria ser aniversário em 06/03\n";
    $ok = false;
}

// Ano bissexto 29/02
$refFev = new DateTimeImmutable('2024-02-29', new DateTimeZone('America/Sao_Paulo'));
if (!AniversarioUtil::ehAniversarioHoje('2000-02-29', $refFev)) {
    echo "FAIL: 29/02 deveria ser aniversário\n";
    $ok = false;
}

$payload = AniversarioUtil::payload('1990-06-08', new DateTimeImmutable('2026-06-08'));
if (!$payload['aniversario_hoje'] || $payload['idade'] !== 36) {
    echo "FAIL: payload aniversário incorreto\n";
    $ok = false;
}

$payloadFora = AniversarioUtil::payload('1990-06-08', new DateTimeImmutable('2026-06-09'));
if ($payloadFora['aniversario_hoje'] || $payloadFora['idade'] !== 36) {
    echo "FAIL: idade no dia após aniversário (esperado 36, got " . var_export($payloadFora['idade'], true) . ")\n";
    $ok = false;
}

$payloadAntes = AniversarioUtil::payload('1990-06-08', new DateTimeImmutable('2026-06-07'));
if ($payloadAntes['aniversario_hoje'] || $payloadAntes['idade'] !== 35) {
    echo "FAIL: idade no dia antes do aniversário (esperado 35, got " . var_export($payloadAntes['idade'], true) . ")\n";
    $ok = false;
}

// Nascimento set-dez vs referência out-dez (comparação mês/dia numérica)
$payloadSet = AniversarioUtil::payload('1990-09-12', new DateTimeImmutable('2026-11-01'));
if ($payloadSet['idade'] !== 36) {
    echo "FAIL: nasc 12/09 ref 01/11 (esperado 36, got " . var_export($payloadSet['idade'], true) . ")\n";
    $ok = false;
}

$payloadDez = AniversarioUtil::payload('1990-12-01', new DateTimeImmutable('2026-10-15'));
if ($payloadDez['idade'] !== 35) {
    echo "FAIL: nasc 01/12 ref 15/10 (esperado 35, got " . var_export($payloadDez['idade'], true) . ")\n";
    $ok = false;
}

if ($ok) {
    echo "OK AniversarioUtil\n";
    exit(0);
}
exit(1);