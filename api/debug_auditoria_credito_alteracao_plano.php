<?php
/**
 * Auditoria em produção — mesmo bug da matrícula #91 em outras matrículas:
 *
 *  - Pagamento MP/integração pago cancelado e convertido em crédito (alteração/migração de plano)
 *  - Parcela fantasma R$ 0 "Migração de plano — crédito cobriu valor integral"
 *  - Crédito gerado com ciclo encerrado (matricula_origem_id)
 *  - Datas da matrícula deslocadas após migração
 *
 * Uso:
 *   php debug_auditoria_credito_alteracao_plano.php
 *   php debug_auditoria_credito_alteracao_plano.php --matricula=91
 *   php debug_auditoria_credito_alteracao_plano.php --aluno=43
 *   php debug_auditoria_credito_alteracao_plano.php --resumo   # só matrículas afetadas
 *   php debug_auditoria_credito_alteracao_plano.php --critico  # só padrão exato da #91
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$filtroAluno = null;
$filtroMatricula = null;
$somenteResumo = in_array('--resumo', $argv, true);
$somenteCritico = in_array('--critico', $argv, true);
$limite = 200;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--aluno=')) {
        $filtroAluno = (int) substr($arg, strlen('--aluno='));
    }
    if (str_starts_with($arg, '--matricula=')) {
        $filtroMatricula = (int) substr($arg, strlen('--matricula='));
    }
    if (str_starts_with($arg, '--limite=')) {
        $limite = max(10, (int) substr($arg, strlen('--limite=')));
    }
}

function conectarPdo(): PDO
{
    $useProd = getenv('PROD_DB_HOST') || getenv('PROD_DB_NAME');
    if ($useProd) {
        $host = getenv('PROD_DB_HOST') ?: 'localhost';
        $name = getenv('PROD_DB_NAME') ?: '';
        $user = getenv('PROD_DB_USER') ?: '';
        $pass = getenv('PROD_DB_PASS') ?: '';
        if ($name === '' || $user === '') {
            throw new RuntimeException('Defina PROD_DB_NAME, PROD_DB_USER e PROD_DB_PASS.');
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
        throw new RuntimeException('Falha ao obter PDO.');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function filtroSql(string $alias, ?int $aluno, ?int $matricula, array &$params): string
{
    $sql = '';
    if ($aluno) {
        $sql .= " AND {$alias}.aluno_id = ?";
        $params[] = $aluno;
    }
    if ($matricula) {
        $campo = match ($alias) {
            'm' => 'id',
            'ca' => 'matricula_origem_id',
            default => 'matricula_id',
        };
        $sql .= " AND {$alias}.{$campo} = ?";
        $params[] = $matricula;
    }

    return $sql;
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

try {
    $pdo = conectarPdo();
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

linha('Auditoria: bug alteração/migração de plano (padrão matrícula #91)');
linha('Data: ' . date('Y-m-d H:i:s'));
if ($filtroMatricula) {
    linha("Filtro: matrícula #{$filtroMatricula}");
}
if ($filtroAluno) {
    linha("Filtro: aluno #{$filtroAluno}");
}

/** @var array<int, array{aluno: string, problemas: list<string>}> */
$resumoMatriculas = [];

/** @var array<int, array{aluno: string, problemas: list<string>}> */
$resumoCritico = [];

function registrarResumo(array &$resumo, int $matriculaId, string $aluno, string $problema): void
{
    if (!isset($resumo[$matriculaId])) {
        $resumo[$matriculaId] = ['aluno' => $aluno, 'problemas' => []];
    }
    if (!in_array($problema, $resumo[$matriculaId]['problemas'], true)) {
        $resumo[$matriculaId]['problemas'][] = $problema;
    }
}

function registrarCritico(array &$critico, array &$geral, int $matriculaId, string $aluno, string $problema): void
{
    registrarResumo($geral, $matriculaId, $aluno, $problema);
    registrarResumo($critico, $matriculaId, $aluno, $problema);
}

// ── A) Pagamento MP cancelado e convertido em crédito ───────────────────────
if (!$somenteResumo) {
    secao('[A] Pagamento MP/integração cancelado → crédito (valor > 0)');
}

$paramsA = [];
$sqlA = "
    SELECT pp.id, pp.matricula_id, pp.aluno_id, a.nome AS aluno,
           pp.valor, pp.data_pagamento, pp.data_vencimento, pp.observacoes,
           tb.nome AS tipo_baixa
    FROM pagamentos_plano pp
    INNER JOIN alunos a ON a.id = pp.aluno_id
    LEFT JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.status_pagamento_id = 4
      AND pp.data_pagamento IS NOT NULL
      AND pp.valor > 0
      AND (pp.observacoes IS NULL OR pp.observacoes NOT LIKE '%DUPLICADO%')
      AND (
          (pp.observacoes LIKE '%Convertido em crédito%' AND (
              pp.observacoes LIKE '%migração%' OR pp.observacoes LIKE '%alteração%'
          ))
          OR (pp.tipo_baixa_id = 4 AND pp.observacoes LIKE '%Mercado Pago%')
      )
" . filtroSql('pp', $filtroAluno, $filtroMatricula, $paramsA) . "
    ORDER BY pp.id DESC
    LIMIT {$limite}
";

$stmt = $pdo->prepare($sqlA);
$stmt->execute($paramsA);
$rowsA = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rowsA as $r) {
    $msg = "pp#{$r['id']} MP/integração cancelado → crédito (R$ {$r['valor']}, {$r['data_pagamento']})";
    registrarCritico($resumoCritico, $resumoMatriculas, (int) $r['matricula_id'], $r['aluno'], $msg);
    if (!$somenteResumo) {
        linha(sprintf(
            '  pp#%d | mat#%d | %s | R$ %s | pago %s | %s',
            $r['id'],
            $r['matricula_id'],
            $r['aluno'],
            number_format((float) $r['valor'], 2, ',', '.'),
            $r['data_pagamento'],
            mb_substr($r['observacoes'] ?? '', 0, 90)
        ));
    }
}
if (!$somenteResumo) {
    linha($rowsA ? '  Total: ' . count($rowsA) : '  (nenhum caso encontrado)');
}

// ── B) Parcelas fantasma da migração (R$ 0 + crédito) ───────────────────────
if (!$somenteResumo) {
    secao('[B] Parcelas fantasma de migração (R$ 0, obs "Migração de plano —...")');
}

$paramsB = [];
$sqlB = "
    SELECT pp.id, pp.matricula_id, pp.aluno_id, a.nome AS aluno,
           pp.valor, pp.credito_aplicado, pp.data_pagamento, pp.data_vencimento,
           pp.observacoes, pp.status_pagamento_id, sp.nome AS status
    FROM pagamentos_plano pp
    INNER JOIN alunos a ON a.id = pp.aluno_id
    INNER JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.valor <= 0
      AND pp.data_pagamento IS NOT NULL
      AND (
          pp.observacoes LIKE 'Migração de plano —%'
          OR (COALESCE(pp.credito_aplicado, 0) > 0 AND pp.observacoes LIKE '%Migração de plano%')
      )
      AND pp.status_pagamento_id IN (2, 4)
" . filtroSql('pp', $filtroAluno, $filtroMatricula, $paramsB) . "
    ORDER BY pp.id DESC
    LIMIT {$limite}
";

$stmt = $pdo->prepare($sqlB);
$stmt->execute($paramsB);
$rowsB = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rowsB as $r) {
    $st = (int) $r['status_pagamento_id'] === 2 ? 'ativa' : 'cancelada';
    $msg = "pp#{$r['id']} parcela fantasma migração {$st} (crédito R$ {$r['credito_aplicado']})";
    registrarCritico($resumoCritico, $resumoMatriculas, (int) $r['matricula_id'], $r['aluno'], $msg);
    if (!$somenteResumo) {
        linha(sprintf(
            '  pp#%d | mat#%d | %s | R$ %s | crédito R$ %s | pago %s | %s',
            $r['id'],
            $r['matricula_id'],
            $r['aluno'],
            number_format((float) $r['valor'], 2, ',', '.'),
            number_format((float) ($r['credito_aplicado'] ?? 0), 2, ',', '.'),
            $r['data_pagamento'] ?? '-',
            mb_substr($r['observacoes'] ?? '', 0, 70)
        ));
    }
}
if (!$somenteResumo) {
    linha($rowsB ? '  Total: ' . count($rowsB) : '  (nenhum caso encontrado)');
}

// ── C) Créditos bug migração (ciclo encerrado / último pagamento) ───────────
if (!$somenteResumo) {
    secao('[C] Créditos do bug migração (ciclo encerrado / último pagamento)');
}

$paramsC = [];
$sqlC = "
    SELECT ca.id, ca.aluno_id, a.nome AS aluno, ca.matricula_origem_id,
           ca.pagamento_origem_id, ca.valor, ca.valor_utilizado,
           ca.status_credito_id, ca.motivo, ca.created_at
    FROM creditos_aluno ca
    INNER JOIN alunos a ON a.id = ca.aluno_id
    WHERE ca.matricula_origem_id IS NOT NULL
      AND (
          ca.motivo LIKE '%ciclo encerrado%'
          OR ca.motivo LIKE '%último pagamento%'
      )
      AND ca.motivo NOT LIKE '%proporcional%'
" . filtroSql('ca', $filtroAluno, $filtroMatricula, $paramsC) . "
    ORDER BY ca.id DESC
    LIMIT {$limite}
";

$stmt = $pdo->prepare($sqlC);
$stmt->execute($paramsC);
$rowsC = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusCredito = [1 => 'Ativo', 2 => 'Utilizado', 3 => 'Cancelado'];

foreach ($rowsC as $r) {
    $st = $statusCredito[(int) $r['status_credito_id']] ?? $r['status_credito_id'];
    $flag = (int) $r['status_credito_id'] === 1 ? ' ⚠️ ATIVO' : '';
    $msg = "cr#{$r['id']} crédito ciclo encerrado {$st} (R$ {$r['valor']}){$flag}";
    registrarCritico($resumoCritico, $resumoMatriculas, (int) $r['matricula_origem_id'], $r['aluno'], $msg);
    if (!$somenteResumo) {
        $saldo = (float) $r['valor'] - (float) $r['valor_utilizado'];
        linha(sprintf(
            '  cr#%d | mat#%s | %s | %s | saldo R$ %s | pp_origem=%s%s',
            $r['id'],
            $r['matricula_origem_id'],
            $r['aluno'],
            $st,
            number_format($saldo, 2, ',', '.'),
            $r['pagamento_origem_id'] ?? '-',
            $flag
        ));
        linha('       ' . mb_substr($r['motivo'] ?? '', 0, 80));
    }
}
if (!$somenteResumo) {
    linha($rowsC ? '  Total: ' . count($rowsC) : '  (nenhum caso encontrado)');
}

// ── D/E) Sinais fracos — só modo completo ───────────────────────────────────
if (!$somenteCritico) {
    secao('[D] Matrículas avulso com data_inicio após último pagamento (renovação suspeita)');
}

$paramsD = [];
$sqlD = "
    SELECT m.id AS matricula_id, m.aluno_id, a.nome AS aluno,
           m.data_inicio, m.data_vencimento, sm.codigo AS status,
           ult.data_pagamento AS ultimo_pago_em, ult.id AS ultimo_pp_id,
           ult.valor AS ultimo_valor
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN (
        SELECT matricula_id,
               MAX(data_pagamento) AS data_pagamento,
               SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY data_pagamento DESC, id DESC), ',', 1) AS id,
               SUBSTRING_INDEX(GROUP_CONCAT(valor ORDER BY data_pagamento DESC, id DESC), ',', 1) AS valor
        FROM pagamentos_plano
        WHERE data_pagamento IS NOT NULL
          AND valor > 0
          AND status_pagamento_id IN (2, 4)
        GROUP BY matricula_id
    ) ult ON ult.matricula_id = m.id
    WHERE m.tipo_cobranca = 'avulso'
      AND m.data_inicio > ult.data_pagamento
" . filtroSql('m', $filtroAluno, $filtroMatricula, $paramsD) . "
    ORDER BY m.id DESC
    LIMIT {$limite}
";

$stmt = $pdo->prepare($sqlD);
$stmt->execute($paramsD);
$rowsD = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rowsD as $r) {
    registrarResumo(
        $resumoMatriculas,
        (int) $r['matricula_id'],
        $r['aluno'],
        "datas deslocadas: início {$r['data_inicio']} > último pago {$r['ultimo_pago_em']} (pp#{$r['ultimo_pp_id']})"
    );
    if (!$somenteResumo) {
        linha(sprintf(
            '  mat#%d | %s | %s | inicio %s > pago %s (pp#%s R$ %s) | venc %s',
            $r['matricula_id'],
            $r['aluno'],
            $r['status'],
            $r['data_inicio'],
            $r['ultimo_pago_em'],
            $r['ultimo_pp_id'],
            number_format((float) $r['ultimo_valor'], 2, ',', '.'),
            $r['data_vencimento']
        ));
    }
}
if (!$somenteResumo) {
    linha($rowsD ? '  Total: ' . count($rowsD) : '  (nenhuma matrícula com data_inicio deslocada)');
}

// ── E) Matrícula ativa/vencida com vencimento ≠ assinatura gateway ──────────
if (!$somenteResumo) {
    secao('[E] Matrícula com vencimento divergente da assinatura MP (tabela assinaturas)');
}

$paramsE = [];
$sqlE = "
    SELECT m.id AS matricula_id, m.aluno_id, a.nome AS aluno,
           m.data_vencimento, m.proxima_data_vencimento, sm.codigo AS status,
           ass.data_inicio AS ass_inicio, ass.data_fim AS ass_fim,
           ass.gateway_assinatura_id,
           ABS(DATEDIFF(m.data_vencimento, ass.data_fim)) AS diff_dias
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN assinaturas ass ON ass.matricula_id = m.id
    WHERE ass.data_fim IS NOT NULL
      AND ass.status_gateway IN ('approved', 'authorized')
      AND m.data_vencimento != ass.data_fim
      AND ABS(DATEDIFF(m.data_vencimento, ass.data_fim)) > 3
      AND NOT (sm.codigo = 'ativa' AND m.data_vencimento > ass.data_fim)
" . filtroSql('m', $filtroAluno, $filtroMatricula, $paramsE) . "
    ORDER BY diff_dias DESC, m.id DESC
    LIMIT {$limite}
";

$rowsE = [];
try {
    $stmt = $pdo->prepare($sqlE);
    $stmt->execute($paramsE);
    $rowsE = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if (!$somenteResumo) {
        linha('  (tabela assinaturas indisponível ou sem colunas esperadas)');
    }
}

foreach ($rowsE as $r) {
    registrarResumo(
        $resumoMatriculas,
        (int) $r['matricula_id'],
        $r['aluno'],
        "venc {$r['data_vencimento']} ≠ assinatura {$r['ass_fim']} (gateway {$r['gateway_assinatura_id']})"
    );
    if (!$somenteResumo) {
        linha(sprintf(
            '  mat#%d | %s | %s | mat venc %s | ass %s → %s | gateway %s',
            $r['matricula_id'],
            $r['aluno'],
            $r['status'],
            $r['data_vencimento'],
            $r['ass_inicio'],
            $r['ass_fim'],
            $r['gateway_assinatura_id']
        ));
    }
}
if (!$somenteResumo) {
    linha($rowsE ? '  Total: ' . count($rowsE) : '  (nenhuma divergência relevante com assinaturas)');
}
} // fim !somenteCritico

// ── RESUMO CRÍTICO (padrão exato #91) ───────────────────────────────────────
secao('[CRÍTICO] Mesmo bug da matrícula #91 — ação recomendada');

if (!$resumoCritico) {
    linha('✅ Nenhuma outra matrícula com o padrão crítico detectado.');
} else {
    ksort($resumoCritico);
    foreach ($resumoCritico as $matId => $info) {
        linha(sprintf('  mat#%d | %s | %d problema(s):', $matId, $info['aluno'], count($info['problemas'])));
        foreach ($info['problemas'] as $p) {
            linha('    • ' . $p);
        }
        linha("    → php debug_corrigir_matricula_91.php --matricula={$matId} [--apply]");
    }
    linha('');
    linha('Total CRÍTICO: ' . count($resumoCritico) . ' matrícula(s)');
}

// ── RESUMO GERAL (modo completo) ──────────────────────────────────────────
if (!$somenteCritico) {
    secao('[RESUMO] Todas as matrículas com algum sinal (inclui falsos positivos)');

    if (!$resumoMatriculas) {
        linha('✅ Nenhuma matrícula com padrão detectado.');
    } else {
        ksort($resumoMatriculas);
        foreach ($resumoMatriculas as $matId => $info) {
            $tag = isset($resumoCritico[$matId]) ? ' ⚠️ CRÍTICO' : '';
            linha(sprintf('  mat#%d | %s | %d problema(s)%s:', $matId, $info['aluno'], count($info['problemas']), $tag));
            foreach ($info['problemas'] as $p) {
                linha('    • ' . $p);
            }
        }
        linha('');
        linha('Total geral: ' . count($resumoMatriculas) . ' | Crítico: ' . count($resumoCritico));
    }
}

linha('');
