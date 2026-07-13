<?php
/**
 * Auditoria em produção — pagamentos MP cancelados indevidamente + créditos gerados
 * na alteração de plano (bug abater_pagamento_anterior / ciclo encerrado).
 *
 * Uso:
 *   PROD_DB_HOST=... PROD_DB_NAME=... PROD_DB_USER=... PROD_DB_PASS=... \
 *     php debug_auditoria_credito_alteracao_plano.php
 *
 *   php debug_auditoria_credito_alteracao_plano.php --aluno=43
 *   php debug_auditoria_credito_alteracao_plano.php --matricula=91
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$filtroAluno = null;
$filtroMatricula = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--aluno=')) {
        $filtroAluno = (int) substr($arg, strlen('--aluno='));
    }
    if (str_starts_with($arg, '--matricula=')) {
        $filtroMatricula = (int) substr($arg, strlen('--matricula='));
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

try {
    $pdo = conectarPdo();
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Auditoria: crédito indevido / pagamento MP cancelado\n";
echo 'Data: ' . date('Y-m-d H:i:s') . "\n";
echo str_repeat('═', 72) . "\n";

// ── A) Pagamentos pagos via integração mas com status Cancelado ─────────────
echo "\n[A] Pagamentos com data_pagamento + status Cancelado (possível bug alteração plano)\n";
echo str_repeat('─', 72) . "\n";

$sqlA = "
    SELECT pp.id, pp.matricula_id, pp.aluno_id, a.nome AS aluno,
           pp.valor, pp.data_pagamento, pp.data_vencimento, pp.observacoes,
           tb.nome AS tipo_baixa
    FROM pagamentos_plano pp
    INNER JOIN alunos a ON a.id = pp.aluno_id
    LEFT JOIN tipos_baixa tb ON tb.id = pp.tipo_baixa_id
    WHERE pp.status_pagamento_id = 4
      AND pp.data_pagamento IS NOT NULL
      AND (
          pp.observacoes LIKE '%Convertido em crédito%'
          OR pp.tipo_baixa_id = 4
          OR pp.observacoes LIKE '%Mercado Pago%'
      )
";
$paramsA = [];
if ($filtroAluno) {
    $sqlA .= ' AND pp.aluno_id = ?';
    $paramsA[] = $filtroAluno;
}
if ($filtroMatricula) {
    $sqlA .= ' AND pp.matricula_id = ?';
    $paramsA[] = $filtroMatricula;
}
$sqlA .= ' ORDER BY pp.id DESC LIMIT 100';

$stmt = $pdo->prepare($sqlA);
$stmt->execute($paramsA);
$rowsA = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rowsA) {
    echo "  (nenhum caso encontrado)\n";
} else {
    foreach ($rowsA as $r) {
        echo sprintf(
            "  pp#%d | mat#%d | %s | R$ %s | pago %s | %s\n",
            $r['id'],
            $r['matricula_id'],
            $r['aluno'],
            number_format((float) $r['valor'], 2, ',', '.'),
            $r['data_pagamento'],
            mb_substr($r['observacoes'] ?? '', 0, 80)
        );
    }
    echo "  Total: " . count($rowsA) . "\n";
}

// ── B) Créditos ativos originados de matrícula com pagamento cancelado acima ─
echo "\n[B] Créditos ATIVOS vinculados a matrícula (matricula_origem_id)\n";
echo str_repeat('─', 72) . "\n";

$sqlB = "
    SELECT ca.id, ca.aluno_id, a.nome AS aluno, ca.matricula_origem_id,
           ca.pagamento_origem_id, ca.valor, ca.valor_utilizado, ca.motivo,
           ca.created_at
    FROM creditos_aluno ca
    INNER JOIN alunos a ON a.id = ca.aluno_id
    WHERE ca.status_credito_id = 1
      AND ca.matricula_origem_id IS NOT NULL
";
$paramsB = [];
if ($filtroAluno) {
    $sqlB .= ' AND ca.aluno_id = ?';
    $paramsB[] = $filtroAluno;
}
if ($filtroMatricula) {
    $sqlB .= ' AND ca.matricula_origem_id = ?';
    $paramsB[] = $filtroMatricula;
}
$sqlB .= ' ORDER BY ca.id DESC LIMIT 100';

$stmt = $pdo->prepare($sqlB);
$stmt->execute($paramsB);
$rowsB = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rowsB) {
    echo "  (nenhum crédito ativo com matricula_origem)\n";
} else {
    foreach ($rowsB as $r) {
        $saldo = (float) $r['valor'] - (float) $r['valor_utilizado'];
        echo sprintf(
            "  cr#%d | aluno %s (#%d) | mat#%s | pp_origem=%s | saldo R$ %s | %s\n",
            $r['id'],
            $r['aluno'],
            $r['aluno_id'],
            $r['matricula_origem_id'],
            $r['pagamento_origem_id'] ?? '-',
            number_format($saldo, 2, ',', '.'),
            mb_substr($r['motivo'] ?? '', 0, 60)
        );
    }
    echo "  Total: " . count($rowsB) . "\n";
}

// ── C) Matrículas com data_inicio posterior ao último pagamento MP pago ───────
echo "\n[C] Matrículas avulso: data_inicio > último pagamento pago (renovação suspeita)\n";
echo str_repeat('─', 72) . "\n";

$sqlC = "
    SELECT m.id AS matricula_id, m.aluno_id, a.nome AS aluno,
           m.data_inicio, m.data_vencimento, sm.codigo AS status,
           ult.data_pagamento AS ultimo_pago_em, ult.id AS ultimo_pp_id
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN (
        SELECT matricula_id, MAX(data_pagamento) AS data_pagamento, MAX(id) AS id
        FROM pagamentos_plano
        WHERE data_pagamento IS NOT NULL
          AND status_pagamento_id IN (2, 4)
        GROUP BY matricula_id
    ) ult ON ult.matricula_id = m.id
    WHERE m.tipo_cobranca = 'avulso'
      AND m.data_inicio > ult.data_pagamento
";
$paramsC = [];
if ($filtroAluno) {
    $sqlC .= ' AND m.aluno_id = ?';
    $paramsC[] = $filtroAluno;
}
if ($filtroMatricula) {
    $sqlC .= ' AND m.id = ?';
    $paramsC[] = $filtroMatricula;
}
$sqlC .= ' ORDER BY m.id DESC LIMIT 50';

$stmt = $pdo->prepare($sqlC);
$stmt->execute($paramsC);
$rowsC = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rowsC) {
    echo "  (nenhuma matrícula com data_inicio deslocada)\n";
} else {
    foreach ($rowsC as $r) {
        echo sprintf(
            "  mat#%d | %s | status %s | inicio %s > último pago %s (pp#%s) | venc %s\n",
            $r['matricula_id'],
            $r['aluno'],
            $r['status'],
            $r['data_inicio'],
            $r['ultimo_pago_em'],
            $r['ultimo_pp_id'],
            $r['data_vencimento']
        );
    }
    echo "  Total: " . count($rowsC) . "\n";
}

echo "\n" . str_repeat('═', 72) . "\n";
echo "Para corrigir matrícula #91: php debug_corrigir_matricula_91.php [--apply]\n";
