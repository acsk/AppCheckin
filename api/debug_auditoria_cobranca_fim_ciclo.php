<?php
/**
 * Auditoria — cobrança com vencimento no FIM do ciclo (padrão #368 / #369)
 *
 * Problema:
 *  - Parcela paga deveria vencer na data de criação/início da matrícula
 *  - Acesso até / próxima cobrança = início + N meses (ciclo)
 *  - Bug: 1ª parcela recebeu data_vencimento = fim do ciclo (ex.: 07/07 → parcela 07/09)
 *  - Depois o MP gerava "próxima" somando ciclo de novo (ex.: mensal 06/08 → 06/09)
 *
 * Uso:
 *   php debug_auditoria_cobranca_fim_ciclo.php
 *   php debug_auditoria_cobranca_fim_ciclo.php --resumo
 *   php debug_auditoria_cobranca_fim_ciclo.php --sql          # imprime UPDATEs sugeridos
 *   php debug_auditoria_cobranca_fim_ciclo.php --matricula=366
 *   php debug_auditoria_cobranca_fim_ciclo.php --tenant=3
 *   php debug_auditoria_cobranca_fim_ciclo.php --limite=100
 *   php debug_auditoria_cobranca_fim_ciclo.php --ativas       # só ativa/vencida/pendente
 *
 * Produção (env):
 *   PROD_DB_HOST PROD_DB_NAME PROD_DB_USER PROD_DB_PASS [PROD_DB_PORT]
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$filtroMatricula = null;
$filtroTenant = null;
$somenteResumo = in_array('--resumo', $argv, true);
$imprimirSql = in_array('--sql', $argv, true);
$somenteAtivas = in_array('--ativas', $argv, true);
$limite = 300;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--matricula=')) {
        $filtroMatricula = (int) substr($arg, strlen('--matricula='));
    }
    if (str_starts_with($arg, '--tenant=')) {
        $filtroTenant = (int) substr($arg, strlen('--tenant='));
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
        $port = (int) (getenv('PROD_DB_PORT') ?: 3306);
        $name = getenv('PROD_DB_NAME') ?: '';
        $user = getenv('PROD_DB_USER') ?: '';
        $pass = getenv('PROD_DB_PASS') ?: '';
        if ($name === '' || $user === '') {
            throw new RuntimeException('Defina PROD_DB_NAME, PROD_DB_USER e PROD_DB_PASS.');
        }

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
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

function linha(string $msg): void
{
    echo $msg . PHP_EOL;
}

function secao(string $titulo): void
{
    echo PHP_EOL . str_repeat('═', 78) . PHP_EOL;
    echo $titulo . PHP_EOL;
    echo str_repeat('─', 78) . PHP_EOL;
}

function br(?string $iso): string
{
    if (!$iso) {
        return '-';
    }
    $ts = strtotime($iso);

    return $ts ? date('d/m/Y', $ts) : $iso;
}

function diffDias(string $a, string $b): int
{
    $da = new DateTime($a);
    $db = new DateTime($b);

    return (int) $da->diff($db)->format('%r%a');
}

function somarMeses(string $data, int $meses): string
{
    $dt = new DateTime($data);
    $dia = (int) $dt->format('d');
    $dt->modify('first day of this month');
    $dt->modify("+{$meses} months");
    $ultimo = (int) $dt->format('t');
    $dt->setDate((int) $dt->format('Y'), (int) $dt->format('m'), min($dia, $ultimo));

    return $dt->format('Y-m-d');
}

/** Mesma regra de comprarPlano: >1 mês usa months; mensal usa duracao_dias. */
function fimCiclo(string $inicio, int $meses, int $duracaoDias): string
{
    if ($meses > 1) {
        return somarMeses($inicio, $meses);
    }
    $dt = new DateTime($inicio);
    $dt->modify('+' . max(1, $duracaoDias) . ' days');

    return $dt->format('Y-m-d');
}

try {
    $pdo = conectarPdo();
} catch (Throwable $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

linha('Auditoria: cobrança com vencimento no fim do ciclo (padrão #368 / #369)');
linha('Data: ' . date('Y-m-d H:i:s'));
if ($filtroMatricula) {
    linha("Filtro: matrícula #{$filtroMatricula}");
}
if ($filtroTenant) {
    linha("Filtro: tenant #{$filtroTenant}");
}
if ($somenteAtivas) {
    linha('Filtro: só ativa/vencida/pendente');
}

$params = [];
$sql = "
    SELECT
        m.id AS matricula_id,
        m.tenant_id,
        m.aluno_id,
        a.nome AS aluno_nome,
        sm.codigo AS status,
        m.tipo_cobranca,
        m.data_inicio,
        m.data_matricula,
        m.data_vencimento AS mat_venc,
        m.proxima_data_vencimento AS mat_prox,
        COALESCE(pc.meses, 1) AS ciclo_meses,
        af.nome AS ciclo_nome,
        pl.duracao_dias,
        pl.nome AS plano_nome,
        pp.id AS pagamento_id,
        pp.valor,
        pp.data_pagamento,
        pp.data_vencimento AS pp_venc,
        pp.observacoes,
        DATEDIFF(pp.data_vencimento, COALESCE(pp.data_pagamento, m.data_inicio)) AS dias_pago_ate_venc,
        DATEDIFF(pp.data_vencimento, m.data_inicio) AS dias_inicio_ate_venc
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos pl ON pl.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    INNER JOIN pagamentos_plano pp ON pp.matricula_id = m.id
        AND pp.status_pagamento_id = 2
        AND pp.valor > 0
        AND pp.data_pagamento IS NOT NULL
    INNER JOIN (
        SELECT matricula_id, MIN(id) AS primeiro_pago_id
        FROM pagamentos_plano
        WHERE status_pagamento_id = 2 AND valor > 0 AND data_pagamento IS NOT NULL
        GROUP BY matricula_id
    ) prim ON prim.matricula_id = m.id AND prim.primeiro_pago_id = pp.id
    WHERE m.tipo_cobranca = 'avulso'
      AND (pl.duracao_dias IS NULL OR pl.duracao_dias <> 1)
";

if ($filtroTenant) {
    $sql .= ' AND m.tenant_id = ?';
    $params[] = $filtroTenant;
}
if ($filtroMatricula) {
    $sql .= ' AND m.id = ?';
    $params[] = $filtroMatricula;
}
if ($somenteAtivas) {
    $sql .= " AND sm.codigo IN ('ativa', 'vencida', 'pendente')";
}

$sql .= "
    ORDER BY m.id DESC
    LIMIT {$limite}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/** @var list<array<string, mixed>> $casosA */
$casosA = [];
/** @var list<array<string, mixed>> $casosB */
$casosB = [];
/** @var list<array<string, mixed>> $casosC */
$casosC = [];
/** @var list<string> $sqls */
$sqls = [];

foreach ($rows as $r) {
    $matId = (int) $r['matricula_id'];
    $inicio = (string) ($r['data_inicio'] ?? '');
    $pagoEm = (string) ($r['data_pagamento'] ?? '');
    $ppVenc = (string) ($r['pp_venc'] ?? '');
    $matVenc = (string) ($r['mat_venc'] ?? '');
    $matProx = (string) ($r['mat_prox'] ?? '');
    $meses = max(1, (int) ($r['ciclo_meses'] ?? 1));
    $duracaoDias = (int) ($r['duracao_dias'] ?? 30);

    if ($inicio === '' || $ppVenc === '') {
        continue;
    }

    $fimEsperado = fimCiclo($inicio, $meses, $duracaoDias);
    // Também candidato: +N months mesmo no mensal
    $fimMeses = somarMeses($inicio, $meses);

    $cobrancaDeveriaSer = $inicio; // regra do produto
    $pagoPertoInicio = abs(diffDias($pagoEm !== '' ? $pagoEm : $inicio, $inicio)) <= 3;
    $vencNoFimCiclo = (
        abs(diffDias($ppVenc, $fimEsperado)) <= 2
        || abs(diffDias($ppVenc, $fimMeses)) <= 2
        || ($matVenc !== '' && abs(diffDias($ppVenc, $matVenc)) <= 1)
        || ($matProx !== '' && abs(diffDias($ppVenc, $matProx)) <= 1)
    );
    $vencLongeDoInicio = abs(diffDias($ppVenc, $inicio)) >= max(20, ($meses * 25) - 5);

    // A) 1ª parcela paga com vencimento no fim do ciclo (#368 / #369)
    if ($pagoPertoInicio && $vencNoFimCiclo && $vencLongeDoInicio && $ppVenc !== $cobrancaDeveriaSer) {
        $casosA[] = array_merge($r, [
            'cobranca_esperada' => $cobrancaDeveriaSer,
            'acesso_esperado' => $fimEsperado,
            'motivo' => '1ª parcela paga com vencimento ≈ fim do ciclo (deveria ser data_inicio)',
        ]);

        $sqls[] = "-- mat#{$matId} {$r['aluno_nome']}: corrigir cobrança #{$r['pagamento_id']}";
        $sqls[] = "UPDATE pagamentos_plano SET data_vencimento = '{$cobrancaDeveriaSer}', updated_at = NOW() WHERE id = {$r['pagamento_id']} AND matricula_id = {$matId};";
        $sqls[] = "UPDATE matriculas SET data_vencimento = '{$fimEsperado}', proxima_data_vencimento = '{$fimEsperado}', updated_at = NOW() WHERE id = {$matId};";
    }
}

// B) Próxima gerada no mesmo vencimento da paga e cancelada como duplicata
$paramsB = [];
$sqlB = "
    SELECT
        m.id AS matricula_id,
        a.nome AS aluno_nome,
        sm.codigo AS status,
        pp_pago.id AS pago_id,
        pp_pago.data_vencimento AS pago_venc,
        pp_pago.data_pagamento,
        pp_dup.id AS dup_id,
        pp_dup.data_vencimento AS dup_venc,
        pp_dup.status_pagamento_id AS dup_status,
        LEFT(pp_dup.observacoes, 120) AS dup_obs
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN pagamentos_plano pp_pago ON pp_pago.matricula_id = m.id
        AND pp_pago.status_pagamento_id = 2 AND pp_pago.valor > 0
    INNER JOIN pagamentos_plano pp_dup ON pp_dup.matricula_id = m.id
        AND pp_dup.id > pp_pago.id
        AND pp_dup.data_vencimento = pp_pago.data_vencimento
        AND ABS(pp_dup.valor - pp_pago.valor) < 0.01
        AND pp_dup.status_pagamento_id = 4
        AND (
            pp_dup.observacoes LIKE '%Duplicata%'
            OR pp_dup.observacoes LIKE '%gerado automaticamente após confirmação MP%'
        )
    WHERE m.tipo_cobranca = 'avulso'
";
if ($filtroTenant) {
    $sqlB .= ' AND m.tenant_id = ?';
    $paramsB[] = $filtroTenant;
}
if ($filtroMatricula) {
    $sqlB .= ' AND m.id = ?';
    $paramsB[] = $filtroMatricula;
}
if ($somenteAtivas) {
    $sqlB .= " AND sm.codigo IN ('ativa', 'vencida', 'pendente')";
}
$sqlB .= " ORDER BY m.id DESC LIMIT {$limite}";

$stmtB = $pdo->prepare($sqlB);
$stmtB->execute($paramsB);
$casosB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

foreach ($casosB as $r) {
    $matId = (int) $r['matricula_id'];
    // próxima cobrança correta = vencimento da parcela paga se ela já estava no fim;
    // se ainda vamos corrigir a paga para data_inicio, próxima = fim do ciclo — tratado no --sql do bloco A
    $sqls[] = "-- mat#{$matId}: reabrir próxima #{$r['dup_id']} (hoje duplicata cancelada em {$r['dup_venc']})";
    $sqls[] = "-- (Só reabra se após corrigir a paga o vencimento dela != fim do ciclo; senão regenere a próxima)";
}

// C) Sem parcela aguardando no fim do ciclo, mas deveria ter (após pagar)
$paramsC = [];
$sqlC = "
    SELECT
        m.id AS matricula_id,
        a.nome AS aluno_nome,
        sm.codigo AS status,
        m.data_inicio,
        m.data_vencimento AS mat_venc,
        m.proxima_data_vencimento AS mat_prox,
        COALESCE(pc.meses, 1) AS ciclo_meses,
        pago.id AS pago_id,
        pago.data_pagamento,
        pago.data_vencimento AS pago_venc,
        (
            SELECT COUNT(*) FROM pagamentos_plano px
            WHERE px.matricula_id = m.id AND px.status_pagamento_id IN (1, 3) AND px.data_pagamento IS NULL
        ) AS qtd_pendentes
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    INNER JOIN pagamentos_plano pago ON pago.id = (
        SELECT MAX(pp2.id) FROM pagamentos_plano pp2
        WHERE pp2.matricula_id = m.id AND pp2.status_pagamento_id = 2 AND pp2.valor > 0
    )
    WHERE m.tipo_cobranca = 'avulso'
      AND sm.codigo IN ('ativa', 'vencida')
      AND m.data_vencimento IS NOT NULL
      AND m.data_vencimento >= CURDATE()
";
if ($filtroTenant) {
    $sqlC .= ' AND m.tenant_id = ?';
    $paramsC[] = $filtroTenant;
}
if ($filtroMatricula) {
    $sqlC .= ' AND m.id = ?';
    $paramsC[] = $filtroMatricula;
}
$sqlC .= " HAVING qtd_pendentes = 0 ORDER BY m.id DESC LIMIT {$limite}";

$stmtC = $pdo->prepare($sqlC);
$stmtC->execute($paramsC);
foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $inicio = (string) $r['data_inicio'];
    $meses = max(1, (int) $r['ciclo_meses']);
    $duracaoDias = 30; // C query não traz duracao; fimCiclo mensal ~30; aproxima por mat_venc
    $fimEsperado = (string) ($r['mat_venc'] ?: $r['mat_prox'] ?: fimCiclo($inicio, $meses, $duracaoDias));
    $pagoVenc = (string) $r['pago_venc'];
    // Só sinaliza se a paga está no início (já corrigida) OU se já está no fim (caso #368 sem próxima)
    $pagaNoInicio = abs(diffDias($pagoVenc, $inicio)) <= 2;
    $acessoNoFim = abs(diffDias((string) $r['mat_venc'], $fimEsperado)) <= 2
        || abs(diffDias((string) $r['mat_prox'], $fimEsperado)) <= 2;

    if ($acessoNoFim && ($pagaNoInicio || abs(diffDias($pagoVenc, $fimEsperado)) <= 2)) {
        $casosC[] = array_merge($r, [
            'proxima_esperada' => $fimEsperado,
            'motivo' => $pagaNoInicio
                ? 'Paga no início e acesso no fim do ciclo, mas sem parcela aguardando renovação'
                : 'Acesso no fim do ciclo sem próxima cobrança (possível após cancelar duplicata)',
        ]);

        $matId = (int) $r['matricula_id'];
        $sqls[] = "-- mat#{$matId}: criar próxima cobrança em {$fimEsperado} (se ainda não existir)";
        $sqls[] = "-- INSERT INTO pagamentos_plano (tenant_id, aluno_id, matricula_id, plano_id, valor, data_vencimento, status_pagamento_id, observacoes, created_at, updated_at)";
        $sqls[] = "-- SELECT tenant_id, aluno_id, id, plano_id, valor, '{$fimEsperado}', 1, 'Pagamento gerado automaticamente após confirmação MP', NOW(), NOW() FROM matriculas WHERE id = {$matId};";
    }
}

// D) Parcela aguardando com +1 ciclo a mais (padrão #366: mensal → 06/09 em vez de 06/08)
$casosD = [];
$paramsD = [];
$sqlD = "
    SELECT
        m.id AS matricula_id,
        m.tenant_id,
        a.nome AS aluno_nome,
        sm.codigo AS status,
        m.data_inicio,
        m.data_vencimento AS mat_venc,
        m.proxima_data_vencimento AS mat_prox,
        COALESCE(pc.meses, 1) AS ciclo_meses,
        af.nome AS ciclo_nome,
        pl.duracao_dias,
        pago.id AS pago_id,
        pago.data_vencimento AS pago_venc,
        pago.data_pagamento,
        pend.id AS pend_id,
        pend.data_vencimento AS pend_venc,
        pend.valor AS pend_valor
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN planos pl ON pl.id = m.plano_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id
    LEFT JOIN assinatura_frequencias af ON af.id = pc.assinatura_frequencia_id
    INNER JOIN pagamentos_plano pago ON pago.id = (
        SELECT pp2.id FROM pagamentos_plano pp2
        WHERE pp2.matricula_id = m.id AND pp2.status_pagamento_id = 2 AND pp2.valor > 0
        ORDER BY pp2.data_pagamento DESC, pp2.id DESC
        LIMIT 1
    )
    INNER JOIN pagamentos_plano pend ON pend.id = (
        SELECT pp3.id FROM pagamentos_plano pp3
        WHERE pp3.matricula_id = m.id AND pp3.status_pagamento_id IN (1, 3) AND pp3.data_pagamento IS NULL
        ORDER BY pp3.data_vencimento ASC, pp3.id ASC
        LIMIT 1
    )
    WHERE m.tipo_cobranca = 'avulso'
      AND (pl.duracao_dias IS NULL OR pl.duracao_dias <> 1)
";
if ($filtroTenant) {
    $sqlD .= ' AND m.tenant_id = ?';
    $paramsD[] = $filtroTenant;
}
if ($filtroMatricula) {
    $sqlD .= ' AND m.id = ?';
    $paramsD[] = $filtroMatricula;
}
if ($somenteAtivas) {
    $sqlD .= " AND sm.codigo IN ('ativa', 'vencida', 'pendente')";
}
$sqlD .= " ORDER BY m.id DESC LIMIT {$limite}";

$stmtD = $pdo->prepare($sqlD);
$stmtD->execute($paramsD);
foreach ($stmtD->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $inicio = (string) $r['data_inicio'];
    $meses = max(1, (int) $r['ciclo_meses']);
    $duracaoDias = (int) ($r['duracao_dias'] ?? 30);
    $fimEsperado = fimCiclo($inicio, $meses, $duracaoDias);
    $pagoVenc = (string) $r['pago_venc'];
    $pendVenc = (string) $r['pend_venc'];
    $matVenc = (string) ($r['mat_venc'] ?? '');

    // Próxima correta:
    // - se paga no início → fim do ciclo
    // - se paga no fim → mesmo fim (não +1 ciclo)
    $pagaNoInicio = abs(diffDias($pagoVenc, $inicio)) <= 3;
    $proximaOk = $pagaNoInicio ? $fimEsperado : (
        abs(diffDias($pagoVenc, $fimEsperado)) <= 2 ? $pagoVenc : $fimEsperado
    );
    // Preferir alinhamento com matrícula se já estiver no fim esperado
    if ($matVenc !== '' && abs(diffDias($matVenc, $fimEsperado)) <= 2) {
        $proximaOk = $matVenc;
    }

    if (abs(diffDias($pendVenc, $proximaOk)) <= 2) {
        continue;
    }
    // Só flagra se pendente está claramente além (ex.: +~1 ciclo)
    if (diffDias($proximaOk, $pendVenc) < 20) {
        continue;
    }

    $casosD[] = array_merge($r, [
        'proxima_esperada' => $proximaOk,
        'motivo' => sprintf(
            'Próxima pp#%s em %s — esperada %s (ciclo %s, %d mês/es)',
            $r['pend_id'],
            $pendVenc,
            $proximaOk,
            $r['ciclo_nome'] ?? '—',
            $meses
        ),
    ]);

    $matId = (int) $r['matricula_id'];
    $sqls[] = "-- mat#{$matId} {$r['aluno_nome']}: ajustar próxima #{$r['pend_id']} {$pendVenc} → {$proximaOk}";
    $sqls[] = "UPDATE pagamentos_plano SET data_vencimento = '{$proximaOk}', updated_at = NOW() WHERE id = {$r['pend_id']} AND matricula_id = {$matId};";
    $sqls[] = "UPDATE matriculas SET data_vencimento = '{$proximaOk}', proxima_data_vencimento = '{$proximaOk}', updated_at = NOW() WHERE id = {$matId};";
}

// ── Relatório ───────────────────────────────────────────────────────────────
secao('[A] 1ª parcela paga com vencimento no FIM do ciclo (padrão #368 / #369)');
if (!$casosA) {
    linha('✅ Nenhum caso encontrado.');
} else {
    linha('Total: ' . count($casosA));
    foreach ($casosA as $r) {
        if ($somenteResumo) {
            linha(sprintf(
                '  mat#%d | %s | %s | pp#%d venc %s → deveria %s | acesso ~%s',
                $r['matricula_id'],
                $r['aluno_nome'],
                $r['status'],
                $r['pagamento_id'],
                br($r['pp_venc']),
                br($r['cobranca_esperada']),
                br($r['acesso_esperado'])
            ));
            continue;
        }
        linha(sprintf(
            '  mat#%d | tenant %d | %s | %s | ciclo %s (%d mês/es)',
            $r['matricula_id'],
            $r['tenant_id'],
            $r['aluno_nome'],
            $r['status'],
            $r['ciclo_nome'] ?? '—',
            $r['ciclo_meses']
        ));
        linha(sprintf(
            '    início %s | pago em %s | pp#%d venc %s (ERRADO) | esperado cobrança %s | acesso %s',
            br($r['data_inicio']),
            br($r['data_pagamento']),
            $r['pagamento_id'],
            br($r['pp_venc']),
            br($r['cobranca_esperada']),
            br($r['acesso_esperado'])
        ));
        linha(sprintf(
            '    matrícula venc %s / próx %s | plano %s',
            br($r['mat_venc']),
            br($r['mat_prox']),
            $r['plano_nome']
        ));
        linha('    → ' . $r['motivo']);
    }
}

secao('[B] Próxima parcela cancelada como duplicata (mesmo vencimento da paga)');
if (!$casosB) {
    linha('✅ Nenhum caso encontrado.');
} else {
    linha('Total: ' . count($casosB));
    foreach ($casosB as $r) {
        linha(sprintf(
            '  mat#%d | %s | %s | pago pp#%d %s | dup pp#%d %s | %s',
            $r['matricula_id'],
            $r['aluno_nome'],
            $r['status'],
            $r['pago_id'],
            br($r['pago_venc']),
            $r['dup_id'],
            br($r['dup_venc']),
            mb_substr((string) ($r['dup_obs'] ?? ''), 0, 80)
        ));
    }
}

secao('[C] Ativa no fim do ciclo sem parcela aguardando renovação');
if (!$casosC) {
    linha('✅ Nenhum caso encontrado.');
} else {
    linha('Total: ' . count($casosC));
    foreach ($casosC as $r) {
        linha(sprintf(
            '  mat#%d | %s | %s | início %s | acesso %s | pago pp#%d venc %s | criar próx em %s',
            $r['matricula_id'],
            $r['aluno_nome'],
            $r['status'],
            br($r['data_inicio']),
            br($r['mat_venc']),
            $r['pago_id'],
            br($r['pago_venc']),
            br($r['proxima_esperada'])
        ));
        if (!$somenteResumo) {
            linha('    → ' . $r['motivo']);
        }
    }
}

secao('[D] Próxima parcela com ciclo a mais (padrão #366: 06/09 em vez de 06/08)');
if (!$casosD) {
    linha('✅ Nenhum caso encontrado.');
} else {
    linha('Total: ' . count($casosD));
    foreach ($casosD as $r) {
        linha(sprintf(
            '  mat#%d | %s | %s | ciclo %s | paga pp#%d %s | pend pp#%d %s → deveria %s',
            $r['matricula_id'],
            $r['aluno_nome'],
            $r['status'],
            $r['ciclo_nome'] ?? ($r['ciclo_meses'] . 'm'),
            $r['pago_id'],
            br($r['pago_venc']),
            $r['pend_id'],
            br($r['pend_venc']),
            br($r['proxima_esperada'])
        ));
        if (!$somenteResumo) {
            linha('    → ' . $r['motivo']);
        }
    }
}

// IDs únicos afetados
$ids = [];
foreach ($casosA as $r) {
    $ids[(int) $r['matricula_id']] = true;
}
foreach ($casosB as $r) {
    $ids[(int) $r['matricula_id']] = true;
}
foreach ($casosC as $r) {
    $ids[(int) $r['matricula_id']] = true;
}
foreach ($casosD as $r) {
    $ids[(int) $r['matricula_id']] = true;
}

secao('[RESUMO]');
linha('Matrículas únicas afetadas: ' . count($ids));
linha('  [A] cobrança no fim do ciclo: ' . count($casosA));
linha('  [B] duplicata MP cancelada:   ' . count($casosB));
linha('  [C] sem próxima renovação:    ' . count($casosC));
linha('  [D] próxima com ciclo a mais: ' . count($casosD));
if ($ids) {
    ksort($ids);
    linha('IDs: ' . implode(', ', array_keys($ids)));
}

if ($imprimirSql && $sqls) {
    secao('[SQL SUGERIDO] (revisar antes de aplicar)');
    foreach ($sqls as $s) {
        linha($s);
    }
}

linha('');
linha('Pronto.');
