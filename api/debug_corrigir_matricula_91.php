<?php
/**
 * Diagnóstico e correção — matrícula #91 (Rafael Rezende / aluno 43)
 *
 * Problema: alteração de plano gerou crédito indevido, cancelou pagamento pago
 * via integração (MP) e renovou datas da matrícula (ativa até 09/2026).
 *
 * Uso (produção — defina credenciais no ambiente):
 *   PROD_DB_HOST=... PROD_DB_NAME=... PROD_DB_USER=... PROD_DB_PASS=... \
 *     php debug_corrigir_matricula_91.php
 *
 *   php debug_corrigir_matricula_91.php --dry-run     # só audita (padrão)
 *   php debug_corrigir_matricula_91.php --apply       # aplica correções
 *   php debug_corrigir_matricula_91.php --matricula=91
 *
 * Local (Docker): usa api/.env ou DB_* do config/database.php
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$matriculaId = 91;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--matricula=')) {
        $matriculaId = (int) substr($arg, strlen('--matricula='));
    }
}

$dryRun = !in_array('--apply', $argv, true);
$apply = !$dryRun;

// ── Conexão ─────────────────────────────────────────────────────────────────
function conectarPdo(): PDO
{
    $useProd = getenv('PROD_DB_HOST') || getenv('PROD_DB_NAME');
    if ($useProd) {
        $host = getenv('PROD_DB_HOST') ?: 'localhost';
        $name = getenv('PROD_DB_NAME') ?: '';
        $user = getenv('PROD_DB_USER') ?: '';
        $pass = getenv('PROD_DB_PASS') ?: '';
        if ($name === '' || $user === '') {
            throw new RuntimeException('Defina PROD_DB_NAME, PROD_DB_USER e PROD_DB_PASS no ambiente.');
        }
        return new PDO(
            "mysql:host={$host};port=3306;dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    $pdo = require __DIR__ . '/config/database.php';
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Falha ao obter PDO de config/database.php');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function linha(string $msg): void
{
    echo $msg . PHP_EOL;
}

function secao(string $titulo): void
{
    echo PHP_EOL . str_repeat('═', 72) . PHP_EOL;
    echo $titulo . PHP_EOL;
    echo str_repeat('─', 72) . PHP_EOL;
}

function statusPagamento(int $id): string
{
    return match ($id) {
        1 => 'Aguardando',
        2 => 'Pago',
        3 => 'Atrasado',
        4 => 'Cancelado',
        default => "id={$id}",
    };
}

function statusCredito(int $id): string
{
    return match ($id) {
        1 => 'Ativo',
        2 => 'Utilizado',
        3 => 'Cancelado',
        default => "id={$id}",
    };
}

/** @return list<string> */
function colunasTabela(PDO $pdo, string $tabela): array
{
    static $cache = [];
    if (!isset($cache[$tabela])) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tabela}`");
        $cache[$tabela] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    }

    return $cache[$tabela];
}

function tabelaExiste(PDO $pdo, string $tabela): bool
{
    try {
        $pdo->query("SELECT 1 FROM `{$tabela}` LIMIT 1");

        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function buscarAssinaturas(PDO $pdo, string $tabela, int $matriculaId, ?string $gatewayIdMp, int $limit = 3): array
{
    if (!tabelaExiste($pdo, $tabela)) {
        return [];
    }

    $cols = colunasTabela($pdo, $tabela);
    $wheres = [];
    $params = [];

    if (in_array('matricula_id', $cols, true)) {
        $wheres[] = 'matricula_id = ?';
        $params[] = $matriculaId;
    }

    if ($gatewayIdMp) {
        foreach (['gateway_assinatura_id', 'mp_preapproval_id', 'mp_payment_id', 'mp_plan_id'] as $col) {
            if (in_array($col, $cols, true)) {
                $wheres[] = "{$col} = ?";
                $params[] = $gatewayIdMp;
            }
        }
        if (in_array('external_reference', $cols, true)) {
            $wheres[] = 'external_reference LIKE ?';
            $params[] = "%{$gatewayIdMp}%";
        }
    }

    if (!$wheres) {
        return [];
    }

    $sql = 'SELECT * FROM `' . $tabela . '` WHERE ' . implode(' OR ', $wheres)
        . ' ORDER BY id DESC LIMIT ' . max(1, $limit);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function gatewayAssinatura(array $a): string
{
    return (string) ($a['gateway_assinatura_id'] ?? $a['mp_preapproval_id'] ?? $a['mp_payment_id'] ?? '-');
}

function statusAssinatura(array $a): string
{
    return (string) ($a['status_gateway'] ?? $a['status'] ?? '-');
}

/** Assinatura criada na migração (não reflete o pagamento original). */
function assinaturaIndevidaMigracao(?array $assinatura, ?array $pagamentoLegitimo): bool
{
    if (!$assinatura || !$pagamentoLegitimo || empty($pagamentoLegitimo['data_pagamento'])) {
        return false;
    }

    $pagoEm = (string) $pagamentoLegitimo['data_pagamento'];
    $assInicio = (string) ($assinatura['data_inicio'] ?? '');

    return $assInicio !== '' && $assInicio > $pagoEm;
}

function calcularVencimentoEsperado(
    string $dataInicio,
    int $mesesCiclo,
    int $duracaoDias
): string {
    $dt = new DateTime($dataInicio);
    if ($duracaoDias > 0 && $duracaoDias <= 31) {
        $dt->modify("+{$duracaoDias} days");
    } elseif ($mesesCiclo > 0) {
        $dt->modify("+{$mesesCiclo} months");
    } else {
        $dt->modify('+30 days');
    }

    return $dt->format('Y-m-d');
}

try {
    $pdo = conectarPdo();
} catch (Throwable $e) {
    fwrite(STDERR, '❌ Conexão: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

linha('Matrícula #' . $matriculaId . ($apply ? ' — MODO APPLY' : ' — MODO DRY-RUN (use --apply para corrigir)'));
linha('Data: ' . date('Y-m-d H:i:s'));

// ── 1. Matrícula ────────────────────────────────────────────────────────────
secao('1. MATRÍCULA');

$stmt = $pdo->prepare("
    SELECT m.*, sm.codigo AS status_codigo, sm.nome AS status_nome,
           p.nome AS plano_nome, p.duracao_dias,
           pc.meses AS ciclo_meses, af.meses AS frequencia_meses,
           a.nome AS aluno_nome, a.id AS aluno_id
    FROM matriculas m
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos p ON p.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    WHERE m.id = ?
");
$stmt->execute([$matriculaId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    linha("❌ Matrícula #{$matriculaId} não encontrada.");
    exit(1);
}

$alunoId = (int) $mat['aluno_id'];
$mesesCiclo = (int) ($mat['ciclo_meses'] ?? $mat['frequencia_meses'] ?? 0);

linha("Aluno:     {$mat['aluno_nome']} (id={$alunoId})");
linha("Plano:     {$mat['plano_nome']} | valor R$ " . number_format((float) $mat['valor'], 2, ',', '.'));
linha("Status:    {$mat['status_nome']} ({$mat['status_codigo']})");
linha("Início:    {$mat['data_inicio']}");
linha("Vencimento: {$mat['data_vencimento']} | Próxima: {$mat['proxima_data_vencimento']}");
linha("Atualizado: {$mat['updated_at']}");

// ── 2. Histórico de pagamentos (matrícula + aluno) ────────────────────────
secao("2. PAGAMENTOS — matrícula #{$matriculaId}");

$stmt = $pdo->prepare("
    SELECT pp.*, sp.nome AS status_nome, tb.nome AS tipo_baixa_nome
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    LEFT JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.id ASC
");
$stmt->execute([$matriculaId]);
$pagamentosMat = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pagamentosMat as $p) {
    $obs = $p['observacoes'] ?? '';
    $flag = '';
    if ((int) $p['status_pagamento_id'] === 4 && !empty($p['data_pagamento'])) {
        $flag = ' ⚠️ PAGO mas CANCELADO';
    }
    if (stripos($obs, 'Convertido em crédito') !== false) {
        $flag .= ' ⚠️ CONVERTIDO EM CRÉDITO';
    }
    if (stripos($obs, 'Mercado Pago') !== false || (int) ($p['tipo_baixa_id'] ?? 0) === 4) {
        $flag .= ' [MP/integração]';
    }
    linha(sprintf(
        '  #%d | %s | R$ %s | venc %s | pago %s | baixa: %s%s',
        $p['id'],
        $p['status_nome'] ?? statusPagamento((int) $p['status_pagamento_id']),
        number_format((float) $p['valor'], 2, ',', '.'),
        $p['data_vencimento'] ?? '-',
        $p['data_pagamento'] ?? '-',
        $p['tipo_baixa_nome'] ?? ($p['tipo_baixa_id'] ?? '-'),
        $flag
    ));
    if ($obs !== '') {
        linha('       obs: ' . mb_substr($obs, 0, 120));
    }
}

secao("3. PAGAMENTOS — todos do aluno #{$alunoId} (outras matrículas)");

$stmt = $pdo->prepare("
    SELECT pp.id, pp.matricula_id, pp.valor, pp.data_vencimento, pp.data_pagamento,
           sp.nome AS status, pl.nome AS plano
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    LEFT JOIN planos pl ON pl.id = pp.plano_id
    WHERE pp.aluno_id = ?
    ORDER BY pp.matricula_id, pp.id
");
$stmt->execute([$alunoId]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    if ((int) $p['matricula_id'] === $matriculaId) {
        continue;
    }
    linha(sprintf(
        '  mat#%s pp#%d | %s | R$ %s | venc %s',
        $p['matricula_id'],
        $p['id'],
        $p['status'],
        number_format((float) $p['valor'], 2, ',', '.'),
        $p['data_vencimento']
    ));
}

// ── 4. Créditos ───────────────────────────────────────────────────────────
secao('4. CRÉDITOS DO ALUNO');

$stmt = $pdo->prepare("
    SELECT ca.*, sca.codigo AS status_codigo
    FROM creditos_aluno ca
    LEFT JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
    WHERE ca.aluno_id = ?
    ORDER BY ca.id
");
$stmt->execute([$alunoId]);
$creditos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$creditos) {
    linha('  (nenhum crédito registrado)');
} else {
    foreach ($creditos as $c) {
        $saldo = (float) $c['valor'] - (float) $c['valor_utilizado'];
        $flag = ((int) $c['matricula_origem_id'] === $matriculaId) ? ' ← desta matrícula' : '';
        linha(sprintf(
            '  #%d | %s | R$ %s (usado %s, saldo %s) | mat_origem=%s%s',
            $c['id'],
            statusCredito((int) $c['status_credito_id']),
            number_format((float) $c['valor'], 2, ',', '.'),
            number_format((float) $c['valor_utilizado'], 2, ',', '.'),
            number_format($saldo, 2, ',', '.'),
            $c['matricula_origem_id'] ?? 'NULL',
            $flag
        ));
        linha('       ' . mb_substr($c['motivo'] ?? '', 0, 100));
    }
}

// ── 5. Assinatura / gateway MP ──────────────────────────────────────────────
secao('5. ASSINATURA / GATEWAY MERCADO PAGO');

$assinatura = null;
$gatewayIdMp = null;
if ($pagamentosMat) {
    foreach ($pagamentosMat as $p) {
        if (preg_match('/ID:\s*(\d+)/', (string) ($p['observacoes'] ?? ''), $m)) {
            $gatewayIdMp = $m[1];
            break;
        }
    }
}

foreach (['assinaturas', 'assinaturas_mercadopago'] as $tabelaAss) {
    foreach (buscarAssinaturas($pdo, $tabelaAss, $matriculaId, $gatewayIdMp) as $a) {
        $dataFim = $a['data_fim'] ?? null;
        linha(sprintf(
            '  [%s] ass#%d | mat=%s | gateway=%s | %s | R$ %s | %s → %s',
            $tabelaAss,
            $a['id'],
            $a['matricula_id'] ?? '-',
            gatewayAssinatura($a),
            statusAssinatura($a),
            number_format((float) ($a['valor'] ?? 0), 2, ',', '.'),
            $a['data_inicio'] ?? '-',
            $dataFim ?? '-'
        ));
        if (!$assinatura && !empty($dataFim) && (int) ($a['matricula_id'] ?? 0) === $matriculaId) {
            $assinatura = $a;
        }
    }
}

if (!$assinatura && !$gatewayIdMp) {
    linha('  (nenhuma assinatura/gateway vinculada)');
}

// ── 6. Calcular estado esperado ───────────────────────────────────────────
secao('6. DIAGNÓSTICO');

$anomalias = [];
$acoes = [];

// Pagamento legítimo: pago via MP com data_pagamento (mesmo que hoje esteja cancelado)
$pagamentoLegitimo = null;
foreach ($pagamentosMat as $p) {
    $obs = (string) ($p['observacoes'] ?? '');
    $temPagamento = !empty($p['data_pagamento']);
    $ehMp = stripos($obs, 'Mercado Pago') !== false || (int) ($p['tipo_baixa_id'] ?? 0) === 4;
    if ($temPagamento && $ehMp) {
        $pagamentoLegitimo = $p;
        break;
    }
}
if (!$pagamentoLegitimo) {
    foreach ($pagamentosMat as $p) {
        if (!empty($p['data_pagamento']) && (int) $p['status_pagamento_id'] === 2) {
            $pagamentoLegitimo = $p;
            break;
        }
    }
}

if (!$pagamentoLegitimo) {
    $anomalias[] = 'Não foi encontrado pagamento legítimo (MP/pago) para derivar o ciclo.';
} else {
    linha("Pagamento de referência: #{$pagamentoLegitimo['id']} pago em {$pagamentoLegitimo['data_pagamento']}");

    if ((int) $pagamentoLegitimo['status_pagamento_id'] === 4) {
        $anomalias[] = "Pagamento #{$pagamentoLegitimo['id']} está CANCELADO mas tem data_pagamento (integração MP).";
        $acoes[] = [
            'tipo' => 'restaurar_pagamento',
            'id' => (int) $pagamentoLegitimo['id'],
            'desc' => "Restaurar status Pago (2) e limpar obs de crédito",
        ];
    }
}

// Datas esperadas do ciclo pago — usar plano do pagamento legítimo (#187), não o plano atual da matrícula
$dataInicioEsperada = $pagamentoLegitimo['data_pagamento'] ?? $mat['data_inicio'];
$dataVencEsperada = null;
$mesesCicloPago = 0;
$duracaoDiasPlano = 0;
$proxParcelaVenc = null;

if ($pagamentoLegitimo) {
    // Ciclo do plano NO MOMENTO DO PAGAMENTO (plano_id da parcela), não o maior ciclo do plano
    $stmtCiclo = $pdo->prepare("
        SELECT pl.nome AS plano_nome, pl.duracao_dias,
               COALESCE(pc.meses, af.meses, 0) AS meses_ciclo
        FROM pagamentos_plano pp
        INNER JOIN planos pl ON pl.id = pp.plano_id
        LEFT JOIN matriculas m ON m.id = pp.matricula_id
        LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.plano_id = pp.plano_id
        LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
        WHERE pp.id = ?
        LIMIT 1
    ");
    $stmtCiclo->execute([(int) $pagamentoLegitimo['id']]);
    $cicloPago = $stmtCiclo->fetch(PDO::FETCH_ASSOC);
    if ($cicloPago) {
        $mesesCicloPago = (int) $cicloPago['meses_ciclo'];
        $duracaoDiasPlano = (int) ($cicloPago['duracao_dias'] ?? 0);
        if ($duracaoDiasPlano > 0 && $duracaoDiasPlano <= 31) {
            linha("Ciclo do pagamento #{$pagamentoLegitimo['id']} ({$cicloPago['plano_nome']}): {$duracaoDiasPlano} dias (mensal)");
        } elseif ($mesesCicloPago > 0) {
            linha("Ciclo do pagamento #{$pagamentoLegitimo['id']} ({$cicloPago['plano_nome']}): {$mesesCicloPago} meses");
        }
    }

    // Próxima parcela (inclui canceladas) indica fim do ciclo pago original
    $stmtProx = $pdo->prepare("
        SELECT data_vencimento FROM pagamentos_plano
        WHERE matricula_id = ? AND id > ?
          AND data_vencimento > ?
          AND observacoes NOT LIKE '%Migração de plano%'
        ORDER BY data_vencimento ASC LIMIT 1
    ");
    $stmtProx->execute([
        $matriculaId,
        (int) $pagamentoLegitimo['id'],
        $pagamentoLegitimo['data_pagamento'] ?? $pagamentoLegitimo['data_vencimento'],
    ]);
    $proxParcelaVenc = $stmtProx->fetchColumn();
    if ($proxParcelaVenc) {
        linha("Fim do ciclo pela próxima parcela: {$proxParcelaVenc}");
    }

    if (!$assinatura && preg_match('/ID:\s*(\d+)/', (string) ($pagamentoLegitimo['observacoes'] ?? ''), $mMp)) {
        foreach (['assinaturas', 'assinaturas_mercadopago'] as $tabelaAss) {
            $rows = buscarAssinaturas($pdo, $tabelaAss, $matriculaId, $mMp[1], 1);
            if ($rows) {
                $assinatura = $rows[0];
                break;
            }
        }
    }
}

// Prioridade: pagamento + duração do plano (NÃO assinatura da migração)
if ($pagamentoLegitimo) {
    if (!empty($proxParcelaVenc)) {
        $dataVencEsperada = (string) $proxParcelaVenc;
        linha('Vencimento derivado da próxima parcela do ciclo original.');
    } else {
        $dataVencEsperada = calcularVencimentoEsperado(
            $dataInicioEsperada,
            $mesesCicloPago,
            $duracaoDiasPlano
        );
        linha('Vencimento derivado do pagamento + duração do plano.');
    }
} elseif ($assinatura && !empty($assinatura['data_fim'])
    && !assinaturaIndevidaMigracao($assinatura, $pagamentoLegitimo)) {
    $dataVencEsperada = $assinatura['data_fim'];
}

if ($dataVencEsperada) {
    linha("Ciclo pago esperado: {$dataInicioEsperada} → {$dataVencEsperada}");

    $hoje = date('Y-m-d');
    $statusEsperado = ($hoje <= $dataVencEsperada) ? 'ativa' : 'vencida';

    if ($mat['data_inicio'] !== $dataInicioEsperada
        || $mat['data_vencimento'] !== $dataVencEsperada
        || $mat['proxima_data_vencimento'] !== $dataVencEsperada) {
        $anomalias[] = sprintf(
            'Datas da matrícula divergentes (atual: %s / %s / %s)',
            $mat['data_inicio'],
            $mat['data_vencimento'],
            $mat['proxima_data_vencimento']
        );
        $acoes[] = [
            'tipo' => 'corrigir_matricula',
            'data_inicio' => $dataInicioEsperada,
            'data_vencimento' => $dataVencEsperada,
            'status_codigo' => $statusEsperado,
            'desc' => "Alinhar datas e status → {$statusEsperado}",
        ];
    } elseif ($mat['status_codigo'] !== $statusEsperado) {
        $anomalias[] = "Status atual '{$mat['status_codigo']}' deveria ser '{$statusEsperado}'.";
        $acoes[] = [
            'tipo' => 'corrigir_matricula',
            'data_inicio' => $mat['data_inicio'],
            'data_vencimento' => $mat['data_vencimento'],
            'status_codigo' => $statusEsperado,
            'desc' => "Corrigir status → {$statusEsperado}",
        ];
    }
}

// Assinatura gerada na migração (ex.: ass#156 pending 10/07→09/08)
if ($assinatura && assinaturaIndevidaMigracao($assinatura, $pagamentoLegitimo)) {
    $anomalias[] = sprintf(
        'Assinatura #%s da migração (%s → %s) distorce o vencimento real',
        $assinatura['id'],
        $assinatura['data_inicio'] ?? '-',
        $assinatura['data_fim'] ?? '-'
    );
    $acoes[] = [
        'tipo' => 'cancelar_assinatura_migracao',
        'id' => (int) $assinatura['id'],
        'desc' => 'Cancelar assinatura #' . $assinatura['id'] . ' criada na migração',
    ];
}

// Créditos indevidos desta matrícula
foreach ($creditos as $c) {
    if ((int) ($c['matricula_origem_id'] ?? 0) !== $matriculaId) {
        continue;
    }
    if ((int) $c['status_credito_id'] === 1) {
        $anomalias[] = "Crédito #{$c['id']} ativo gerado por esta matrícula (R$ {$c['valor']}).";
        $acoes[] = [
            'tipo' => 'cancelar_credito',
            'id' => (int) $c['id'],
            'desc' => 'Cancelar crédito indevido',
        ];
    }
}

// Parcelas fantasma da migração de plano (ex.: #818 — R$ 0 pago com crédito)
foreach ($pagamentosMat as $p) {
    if ((int) $p['id'] === (int) ($pagamentoLegitimo['id'] ?? 0)) {
        continue;
    }

    $obs = (string) ($p['observacoes'] ?? '');
    $valor = (float) $p['valor'];
    $creditoAplicado = (float) ($p['credito_aplicado'] ?? 0);
    $ehMigracao = stripos($obs, 'Migração de plano') !== false
        || stripos($obs, 'migração de plano') !== false;

    if ($ehMigracao || ($valor <= 0 && $creditoAplicado > 0 && !empty($p['data_pagamento']))) {
        $anomalias[] = "Parcela #{$p['id']} gerada na migração (R$ {$valor}, crédito R$ {$creditoAplicado}) — não deveria existir.";
        $acoes[] = [
            'tipo' => 'remover_parcela_migracao',
            'id' => (int) $p['id'],
            'credito_id' => !empty($p['credito_id']) ? (int) $p['credito_id'] : null,
            'desc' => "Remover parcela #{$p['id']} da migração de plano",
        ];
        continue;
    }

    if ((int) $p['status_pagamento_id'] !== 1 && (int) $p['status_pagamento_id'] !== 3) {
        continue;
    }
    if (empty($p['data_pagamento'])) {
        $anomalias[] = "Parcela pendente #{$p['id']} (venc {$p['data_vencimento']}) — possível renovação indevida.";
        $acoes[] = [
            'tipo' => 'cancelar_parcela',
            'id' => (int) $p['id'],
            'desc' => 'Cancelar parcela pendente gerada indevidamente',
        ];
    }
}

if (!$anomalias) {
    linha('✅ Nenhuma anomalia detectada para esta matrícula.');
} else {
    linha('Anomalias:');
    foreach ($anomalias as $i => $a) {
        linha('  ' . ($i + 1) . '. ' . $a);
    }
}

// ── 7. Aplicar correções ───────────────────────────────────────────────────
secao('7. CORREÇÕES' . ($apply ? '' : ' (simuladas)'));

if (!$acoes) {
    linha('Nada a corrigir.');
    exit(0);
}

if ($apply) {
    $pdo->beginTransaction();
}

try {
foreach ($acoes as $acao) {
    linha('→ ' . $acao['desc']);

    if (!$apply) {
        continue;
    }

    switch ($acao['tipo']) {
        case 'restaurar_pagamento':
            $pdo->prepare("
                UPDATE pagamentos_plano
                SET status_pagamento_id = 2,
                    observacoes = TRIM(REPLACE(REPLACE(COALESCE(observacoes, ''),
                        '[Convertido em crédito na alteração de plano]', ''),
                        '[Convertido em crédito]', '')),
                    updated_at = NOW()
                WHERE id = ? AND matricula_id = ?
            ")->execute([$acao['id'], $matriculaId]);
            linha("   ✓ Pagamento #{$acao['id']} restaurado para Pago");
            break;

        case 'cancelar_credito':
            $pdo->prepare("
                UPDATE creditos_aluno
                SET status_credito_id = 3, updated_at = NOW()
                WHERE id = ? AND aluno_id = ?
            ")->execute([$acao['id'], $alunoId]);
            linha("   ✓ Crédito #{$acao['id']} cancelado");
            break;

        case 'cancelar_parcela':
            $pdo->prepare("
                UPDATE pagamentos_plano
                SET status_pagamento_id = 4,
                    observacoes = CONCAT(COALESCE(observacoes, ''), ' [Cancelado: correção script mat#91]'),
                    updated_at = NOW()
                WHERE id = ? AND matricula_id = ?
                  AND status_pagamento_id IN (1, 3) AND data_pagamento IS NULL
            ")->execute([$acao['id'], $matriculaId]);
            linha("   ✓ Parcela #{$acao['id']} cancelada");
            break;

        case 'remover_parcela_migracao':
            $parcelaId = $acao['id'];
            if (!empty($acao['credito_id'])) {
                $pdo->prepare("
                    UPDATE creditos_aluno
                    SET valor_utilizado = GREATEST(0, valor_utilizado - COALESCE(
                        (SELECT credito_aplicado FROM pagamentos_plano WHERE id = ?), 0
                    )),
                        updated_at = NOW()
                    WHERE id = ?
                ")->execute([$parcelaId, $acao['credito_id']]);
            }
            $pdo->prepare("DELETE FROM pagamentos_plano WHERE id = ? AND matricula_id = ?")
                ->execute([$parcelaId, $matriculaId]);
            linha("   ✓ Parcela #{$parcelaId} removida (migração indevida)");
            break;

        case 'cancelar_assinatura_migracao':
            try {
                $pdo->prepare("
                    UPDATE assinaturas
                    SET status_gateway = 'cancelled',
                        motivo_cancelamento = 'Cancelada: correção migração indevida',
                        updated_at = NOW()
                    WHERE id = ? AND matricula_id = ?
                ")->execute([$acao['id'], $matriculaId]);
                linha("   ✓ Assinatura #{$acao['id']} cancelada");
            } catch (Throwable $e) {
                linha("   ⚠️  Assinatura #{$acao['id']}: " . $e->getMessage());
            }
            break;

        case 'corrigir_matricula':
            $stmtSt = $pdo->prepare("SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1");
            $stmtSt->execute([$acao['status_codigo']]);
            $statusId = (int) $stmtSt->fetchColumn();
            if (!$statusId) {
                linha("   ❌ Status '{$acao['status_codigo']}' não encontrado");
                break;
            }
            $pdo->prepare("
                UPDATE matriculas
                SET data_inicio = ?,
                    data_vencimento = ?,
                    proxima_data_vencimento = ?,
                    status_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND aluno_id = ?
            ")->execute([
                $acao['data_inicio'],
                $acao['data_vencimento'],
                $acao['data_vencimento'],
                $statusId,
                $matriculaId,
                $alunoId,
            ]);
            linha("   ✓ Matrícula #{$matriculaId} → {$acao['status_codigo']} | {$acao['data_inicio']} → {$acao['data_vencimento']}");
            break;
    }
}

if ($apply) {
    $pdo->commit();
    secao('8. ESTADO FINAL');

    $stmtFinalMat = $pdo->prepare("
        SELECT m.data_inicio, m.data_vencimento, sm.codigo AS status_codigo
        FROM matriculas m
        INNER JOIN status_matricula sm ON sm.id = m.status_id
        WHERE m.id = ?
    ");
    $stmtFinalMat->execute([$matriculaId]);
    $matFinal = $stmtFinalMat->fetch(PDO::FETCH_ASSOC);
    linha("Status: {$matFinal['status_codigo']} | {$matFinal['data_inicio']} → {$matFinal['data_vencimento']}");

    $stmtFinalPp = $pdo->prepare("
        SELECT pp.id, pp.data_vencimento, pp.data_pagamento, sp.nome AS status_nome
        FROM pagamentos_plano pp
        LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
        WHERE pp.matricula_id = ?
        ORDER BY pp.id
    ");
    $stmtFinalPp->execute([$matriculaId]);
    foreach ($stmtFinalPp->fetchAll(PDO::FETCH_ASSOC) as $p) {
        linha(sprintf(
            '  pp#%d %s | venc %s | pago %s',
            $p['id'],
            $p['status_nome'],
            $p['data_vencimento'],
            $p['data_pagamento'] ?? '-'
        ));
    }
} else {
    linha(PHP_EOL . 'Execute com --apply para gravar as correções acima.');
}
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, '❌ Erro ao aplicar: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

linha('');
