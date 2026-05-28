<?php
/**
 * Debug: por que a matrícula #49 fez MAIS check-ins do que o plano permite?
 *
 * Reproduz a mesma lógica de validação usada em CheckinController::create /
 * registrarPorAdmin (Checkin::obterLimiteCheckinsPlano, contarCheckinsNaSemana,
 * contarCheckinsNoMes) e mostra, mês a mês, onde e por que o limite foi furado.
 *
 * Uso: php debug_limite_checkins_49.php [matricula_id]
 *      (matricula_id é opcional; padrão = 49)
 */

$db = require __DIR__ . '/config/database.php';

$matriculaId = isset($argv[1]) ? (int) $argv[1] : 49;

echo "====== DEBUG LIMITE CHECK-INS — Matrícula #{$matriculaId} ======\n";
echo "Data/hora (BRT): " . date('Y-m-d H:i:s') . "\n";
echo "CURDATE() no banco: " . $db->query('SELECT CURDATE()')->fetchColumn() . "\n\n";

// ─── 1. Dados da matrícula, plano e ciclo ────────────────────────────────────
echo "1. MATRÍCULA, PLANO E CICLO\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT m.id, m.aluno_id, m.plano_id, m.status_id, m.plano_ciclo_id, m.tenant_id,
           m.data_inicio, m.data_vencimento, m.proxima_data_vencimento,
           sm.codigo AS status_codigo,
           p.nome AS plano_nome, p.checkins_semanais, p.modalidade_id, p.duracao_dias,
           modalidade.nome AS modalidade_nome,
           pc.permite_reposicao AS pc_permite_reposicao,
           a.nome AS aluno_nome, a.usuario_id
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN modalidades modalidade ON modalidade.id = p.modalidade_id
    LEFT JOIN plano_ciclos pc ON pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id
    WHERE m.id = :id
");
$stmt->execute(['id' => $matriculaId]);
$mat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mat) {
    echo "  ❌ Matrícula #{$matriculaId} não encontrada!\n";
    exit(1);
}

$alunoId    = (int) $mat['aluno_id'];
$usuarioId  = (int) $mat['usuario_id'];
$tenantId   = (int) $mat['tenant_id'];
$modalidade = $mat['modalidade_id'] !== null ? (int) $mat['modalidade_id'] : null;

echo "  Aluno              : {$mat['aluno_nome']} (aluno_id={$alunoId}, usuario_id={$usuarioId})\n";
echo "  Tenant             : {$tenantId}\n";
echo "  Plano              : {$mat['plano_nome']} (plano_id={$mat['plano_id']})\n";
echo "  Modalidade do plano: " . ($mat['modalidade_nome'] ?? 'TODAS') . " (id=" . ($modalidade ?? 'NULL') . ")\n";
echo "  Status matrícula   : {$mat['status_codigo']}\n";
echo "  duracao_dias       : {$mat['duracao_dias']}\n";
echo "  data_inicio        : {$mat['data_inicio']}\n";
echo "  data_vencimento    : {$mat['data_vencimento']}\n";
echo "  proxima_data_venc  : " . ($mat['proxima_data_vencimento'] ?? 'NULL') . "\n";
echo "  checkins_semanais  : {$mat['checkins_semanais']}\n";
echo "  plano_ciclo_id     : " . ($mat['plano_ciclo_id'] ?? 'NULL') . "\n";
echo "  permite_reposicao  : " . ($mat['pc_permite_reposicao'] === null ? 'NULL (sem ciclo vinculado)' : ($mat['pc_permite_reposicao'] ? 'SIM' : 'NÃO')) . "\n";

// ─── 2. permite_reposicao efetivo (mesma regra do obterLimiteCheckinsPlano) ───
echo "\n2. permite_reposicao EFETIVO (regra do Checkin::obterLimiteCheckinsPlano)\n";
echo str_repeat('-', 80) . "\n";

// Quando a matrícula NÃO tem plano_ciclo_id, o código usa MAX(permite_reposicao)
// dos ciclos ativos do plano no tenant.
$stmt = $db->prepare("
    SELECT
        CASE
            WHEN :ciclo_id_a IS NOT NULL THEN COALESCE((
                SELECT pc.permite_reposicao FROM plano_ciclos pc
                WHERE pc.id = :ciclo_id_b AND pc.tenant_id = :tenant_a
            ), 0)
            ELSE COALESCE((
                SELECT MAX(pc2.permite_reposicao)
                FROM plano_ciclos pc2
                WHERE pc2.plano_id = :plano_id
                  AND pc2.tenant_id = :tenant_b
                  AND pc2.ativo = 1
            ), 0)
        END AS permite_reposicao
");
$stmt->execute([
    'ciclo_id_a' => $mat['plano_ciclo_id'],
    'ciclo_id_b' => $mat['plano_ciclo_id'],
    'tenant_a'   => $tenantId,
    'tenant_b'   => $tenantId,
    'plano_id'   => $mat['plano_id'],
]);
$permiteReposicao = (bool) $stmt->fetchColumn();

$checkinsSemanais = (int) $mat['checkins_semanais'];

echo "  permite_reposicao efetivo : " . ($permiteReposicao ? 'SIM → limite MENSAL' : 'NÃO → limite SEMANAL') . "\n";
echo "  checkins_semanais         : {$checkinsSemanais}\n";
if ($permiteReposicao) {
    echo "  → Limite aplicado: MENSAL = checkins_semanais × 4 = " . ($checkinsSemanais * 4) . "\n";
    echo "    (ATENÇÃO: no código o limite_mensal é fixo em ×4, sem bônus de 5ª semana)\n";
} else {
    echo "  → Limite aplicado: SEMANAL = {$checkinsSemanais} por semana (YEARWEEK do mês corrente)\n";
}

echo "\n  Ciclos do plano {$mat['plano_id']} (tenant {$tenantId}):\n";
$stmt = $db->prepare("
    SELECT pc.id, pc.ativo, pc.permite_reposicao, pc.tenant_id
    FROM plano_ciclos pc
    WHERE pc.plano_id = :plano_id
    ORDER BY pc.id DESC
");
$stmt->execute(['plano_id' => $mat['plano_id']]);
$ciclos = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$ciclos) {
    echo "    (nenhum ciclo cadastrado)\n";
} else {
    foreach ($ciclos as $c) {
        $ativo = $c['ativo'] ? '✅ ativo' : '❌ inativo';
        $rep   = $c['permite_reposicao'] ? 'reposicao=SIM' : 'reposicao=NÃO';
        echo "    Ciclo #{$c['id']} | {$ativo} | {$rep} | tenant={$c['tenant_id']}\n";
    }
}

// ─── 3. Todos os check-ins da matrícula (por data real = dias.data) ──────────
echo "\n3. CHECK-INS DO ALUNO (data real = dias.data)\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT c.id, DATE(d.data) AS data_aula, t.horario_inicio,
           t.modalidade_id, m2.nome AS modalidade,
           c.presente, c.registrado_por_admin, c.data_checkin_date, DATE(c.created_at) AS created_date
    FROM checkins c
    INNER JOIN turmas      t  ON t.id  = c.turma_id
    INNER JOIN dias        d  ON d.id  = t.dia_id
    LEFT  JOIN modalidades m2 ON m2.id = t.modalidade_id
    WHERE c.aluno_id = :aluno_id
    ORDER BY d.data ASC, t.horario_inicio ASC
");
$stmt->execute(['aluno_id' => $alunoId]);
$checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$checkins) {
    echo "  Nenhum check-in encontrado para o aluno.\n";
    exit(0);
}

// Agrupar por mês (com base na data real da aula) e por semana ISO.
$porMes    = [];   // 'YYYY-MM' => [checkins]
$porSemana = [];   // 'YYYYWW'  => [checkins]
foreach ($checkins as $c) {
    // Se a modalidade do plano é específica, a validação filtra por ela.
    if ($modalidade !== null && (int) $c['modalidade_id'] !== $modalidade) {
        continue;
    }
    // presente=0 (falta) NÃO conta no limite (libera crédito) — igual ao código.
    if ($c['presente'] !== null && (int) $c['presente'] === 0) {
        continue;
    }
    $mesKey    = substr($c['data_aula'], 0, 7);
    $semanaKey = (new DateTime($c['data_aula']))->format('oW'); // ISO year+week (modo 1)
    $porMes[$mesKey][]       = $c;
    $porSemana[$semanaKey][] = $c;
}

echo "  Total de check-ins (presente!=0, modalidade do plano): "
    . array_sum(array_map('count', $porMes)) . "\n\n";

// ─── 4. Análise mês a mês vs limite mensal (caminho permite_reposicao=1) ─────
echo "4. ANÁLISE MENSAL (limite mensal = checkins_semanais × 4)\n";
echo str_repeat('-', 80) . "\n";
$limiteMensalCodigo = $checkinsSemanais * 4;
echo "  Limite mensal (regra do código): {$checkinsSemanais} × 4 = {$limiteMensalCodigo}\n\n";

ksort($porMes);
foreach ($porMes as $mesKey => $lista) {
    [$ano, $mes] = explode('-', $mesKey);

    // Limite "correto" com bônus de 5ª semana (referência de negócio)
    $primeiroDia       = new DateTime("{$ano}-{$mes}-01");
    $diaSemanaInicio   = (int) $primeiroDia->format('w');
    $diasNoMes         = (int) $primeiroDia->format('t');
    $semanasNoMes      = (int) ceil(($diasNoMes + $diaSemanaInicio) / 7);
    $bonus             = ($semanasNoMes >= 5) ? 1 : 0;
    $limiteComBonus    = $checkinsSemanais * 4 + $bonus;

    $total = count($lista);
    $excessoCodigo = $total - $limiteMensalCodigo;
    $excessoBonus  = $total - $limiteComBonus;

    $flag = $excessoCodigo > 0 ? '⚠️ ' : '   ';
    echo "  {$flag}{$mesKey}: {$total} check-ins"
        . " | limite código={$limiteMensalCodigo}"
        . " | limite c/ bônus 5ª sem={$limiteComBonus}"
        . " | excesso(código)=" . ($excessoCodigo > 0 ? "+{$excessoCodigo}" : $excessoCodigo) . "\n";

    if ($excessoCodigo > 0) {
        foreach ($lista as $i => $c) {
            $st    = $c['presente'] === null ? '⏳ pend' : '✅ pres';
            $admin = $c['registrado_por_admin'] ? ' [ADMIN]' : '';
            echo "        " . ($i + 1) . ". #{$c['id']} | {$c['data_aula']} {$c['horario_inicio']}"
                . " | {$c['modalidade']} | {$st}{$admin}\n";
        }
    }
}

// ─── 5. Análise semanal vs limite semanal (caminho permite_reposicao=0) ──────
echo "\n5. ANÁLISE SEMANAL (limite semanal = checkins_semanais)\n";
echo str_repeat('-', 80) . "\n";
echo "  Limite semanal: {$checkinsSemanais} check-ins por semana ISO\n\n";

ksort($porSemana);
foreach ($porSemana as $semanaKey => $lista) {
    $total = count($lista);
    $excesso = $total - $checkinsSemanais;
    if ($excesso <= 0) {
        continue; // só mostra semanas que estouraram
    }
    $ano  = substr($semanaKey, 0, 4);
    $sem  = substr($semanaKey, 4);
    $datas = implode(', ', array_map(fn($c) => $c['data_aula'], $lista));
    echo "  ⚠️  Semana ISO {$ano}-W{$sem}: {$total} check-ins (limite {$checkinsSemanais}, excesso +{$excesso})\n";
    echo "        datas: {$datas}\n";
}

// ─── 6. Diagnóstico: por que a validação NÃO bloqueou ────────────────────────
echo "\n6. DIAGNÓSTICO — POR QUE A VALIDAÇÃO NÃO BLOQUEOU\n";
echo str_repeat('-', 80) . "\n";

$hoje    = date('Y-m-d');
$proxima = $mat['proxima_data_vencimento'] ?? $mat['data_vencimento'];

echo "  [a] obterLimiteCheckinsPlano só considera matrícula 'ativa' E\n";
echo "      COALESCE(proxima_data_vencimento, data_vencimento) >= CURDATE().\n";
echo "      status atual = {$mat['status_codigo']} | venc. efetivo = {$proxima} | hoje = {$hoje}\n";
if ($mat['status_codigo'] !== 'ativa') {
    echo "      ⚠️  Matrícula NÃO está 'ativa' agora → tem_plano=false → NENHUM limite é aplicado.\n";
}
if ($proxima < $hoje) {
    echo "      ⚠️  Vencimento no passado → durante esse período a query não retornava plano,\n";
    echo "          então o check-in passava SEM validação de limite.\n";
} else {
    echo "      ✅ Vencimento vigente — não foi (isoladamente) a causa.\n";
}

echo "\n  [b] contarCheckinsNaSemana / contarCheckinsNoMes só olham a semana/mês de CURDATE().\n";
echo "      Check-ins em meses/semanas já passados NÃO podem ser bloqueados retroativamente,\n";
echo "      e check-ins registrados pelo admin de forma retroativa furam o teto facilmente.\n";

$adminCount = 0;
foreach ($checkins as $c) { if ($c['registrado_por_admin']) $adminCount++; }
echo "      check-ins registrados por admin: {$adminCount} de " . count($checkins) . "\n";

echo "\n  [c] Caminho de validação efetivo desta matrícula: "
    . ($permiteReposicao ? "MENSAL (permite_reposicao=1)" : "SEMANAL (permite_reposicao=0)") . "\n";
if ($permiteReposicao) {
    echo "      → o limite mensal usado pelo código é ×4 fixo, sem bônus de 5ª semana.\n";
} else {
    echo "      → não há teto mensal: o aluno pode somar muito mais que (semanal×4) ao longo do mês\n";
    echo "        desde que respeite o teto de cada semana individualmente.\n";
}

echo "\n  [d] data usada na contagem:\n";
echo "      - contarCheckinsNaSemana usa COALESCE(data_checkin_date, DATE(created_at)).\n";
echo "      - contarCheckinsNoMes usa dias.data (data real da aula).\n";
echo "      Divergência entre essas datas pode contar o check-in em semana/mês diferente\n";
echo "      da aula, abrindo brecha no limite.\n";

// ─── 7. Resumo ───────────────────────────────────────────────────────────────
echo "\n7. RESUMO\n";
echo str_repeat('-', 80) . "\n";
$mesesExcedidos = [];
foreach ($porMes as $mesKey => $lista) {
    if (count($lista) - $limiteMensalCodigo > 0) {
        $mesesExcedidos[$mesKey] = count($lista) - $limiteMensalCodigo;
    }
}
$semanasExcedidas = 0;
foreach ($porSemana as $lista) {
    if (count($lista) - $checkinsSemanais > 0) $semanasExcedidas++;
}

echo "  Matrícula #{$matriculaId} / Aluno {$mat['aluno_nome']} / Plano {$mat['plano_nome']}\n";
echo "  checkins_semanais={$checkinsSemanais} | permite_reposicao=" . ($permiteReposicao ? 'SIM' : 'NÃO') . "\n";
echo "  Meses acima do limite mensal (×4): " . (count($mesesExcedidos) ? implode(', ', array_map(
        fn($k, $v) => "{$k} (+{$v})", array_keys($mesesExcedidos), $mesesExcedidos)) : 'nenhum') . "\n";
echo "  Semanas acima do limite semanal  : {$semanasExcedidas}\n";

echo "\n  Causas prováveis (verifique acima qual se aplica):\n";
echo "   1. Check-ins retroativos/registrados por admin não passam pela validação corrente.\n";
echo "   2. Em períodos com a matrícula vencida/inativa, obterLimiteCheckinsPlano não retorna\n";
echo "      plano e o limite deixa de ser aplicado.\n";
echo "   3. Com permite_reposicao=0 só há teto semanal — o mês pode acumular >semanal×4.\n";
echo "   4. Limite mensal do código é ×4 fixo (sem bônus de 5ª semana), então um mês com 5\n";
echo "      semanas pode parecer 'a mais' frente ao esperado de negócio.\n";

echo "\n" . str_repeat('=', 80) . "\n";
echo "Fim do diagnóstico.\n";
