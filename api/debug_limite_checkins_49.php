<?php
/**
 * Debug: por que a matrícula #49 fez MAIS check-ins do que o plano permite?
 *
 * Enforcement ATUAL (fonte da verdade): limite mensal por CICLO DE COBRANÇA,
 * centralizado em Checkin::obterCicloCheckins / avaliarLimiteMensalReposicao
 * (ancorado no vencimento). A Seção 8 reflete essa regra. As Seções 4–7 são uma
 * VISÃO HISTÓRICA por mês de calendário (contexto) e podem apontar "excesso" que
 * NÃO existe pela regra por ciclo (ciclos cruzam o mês, ex.: 25→25).
 *
 * Uso: php debug_limite_checkins_49.php [matricula_id]
 *      (matricula_id é opcional; padrão = 49)
 */

$db = require __DIR__ . '/config/database.php';

// Autoload para podermos usar o model real (Checkin::obterCicloCheckins).
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

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
    echo "  → Limite aplicado: MENSAL por CICLO DE COBRANÇA (vencimento) = checkins_semanais × 4 + bônus 5ª semana.\n";
    echo "    Enforcement centralizado em Checkin::avaliarLimiteMensalReposicao — veja a Seção 8 (fonte da verdade).\n";
} else {
    echo "  → Limite aplicado: SEMANAL = {$checkinsSemanais} por semana ISO (+ bônus de mês com 5 semanas).\n";
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
           c.presente, c.registrado_por_admin,
           c.data_checkin_date,
           c.created_at AS created_full, DATE(c.created_at) AS created_date
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

// Helper: limite mensal EFETIVO aplicado pelo app,
// = checkins_semanais × 4 + bônus de mês "longo" (ceil(dias/7) >= 5).
// Usa a MESMA fórmula do enforcement (Checkin::obterCicloCheckins /
// avaliarLimiteMensalReposicao): ceil($diasNoMes / 7). Antes este helper somava
// $diaSemanaInicio (grade de calendário Dom-Sáb), divergindo do app e relatando
// bônus incorreto no diagnóstico.
$limiteMensalApp = function (string $mesKey) use ($checkinsSemanais): array {
    [$ano, $mes]     = explode('-', $mesKey);
    $primeiroDia     = new DateTime("{$ano}-{$mes}-01");
    $diasNoMes       = (int) $primeiroDia->format('t');
    $semanasNoMes    = (int) ceil($diasNoMes / 7);
    $bonus           = ($semanasNoMes >= 5) ? 1 : 0;
    return [
        'base'    => $checkinsSemanais * 4,
        'bonus'   => $bonus,
        'efetivo' => $checkinsSemanais * 4 + $bonus,
        'semanas' => $semanasNoMes,
    ];
};

// ─── 4. Análise mês a mês — VISÃO HISTÓRICA (mês de calendário) ──────────────
echo "4. ANÁLISE MENSAL — VISÃO HISTÓRICA por MÊS DE CALENDÁRIO (NÃO é o enforcement atual)\n";
echo str_repeat('-', 80) . "\n";
$limiteBase = $checkinsSemanais * 4;
echo "  ⚠️  O enforcement REAL hoje é por CICLO DE COBRANÇA (vencimento) — veja a Seção 8.\n";
echo "      Esta seção agrupa por mês de calendário só para contexto e pode apontar\n";
echo "      'excesso' que NÃO existe na regra por ciclo (ciclos cruzam o mês, ex.: 25→25).\n";
echo "  Limite base (×4): {$checkinsSemanais} × 4 = {$limiteBase}  (+ bônus se ceil(dias/7) >= 5)\n\n";

ksort($porMes);
foreach ($porMes as $mesKey => $lista) {
    $lim    = $limiteMensalApp($mesKey);
    $total  = count($lista);
    $excessoApp  = $total - $lim['efetivo'];   // vs ×4 + bônus (calendário)
    $excessoBase = $total - $lim['base'];       // vs ×4 base (calendário)

    $flag = $excessoApp > 0 ? '⛔' : ($excessoBase > 0 ? '⚠️ ' : '   ');
    echo "  {$flag}{$mesKey}: {$total} check-ins"
        . " | limite calendário (×4+bônus)={$lim['efetivo']} (base {$lim['base']} + bônus {$lim['bonus']}, {$lim['semanas']} semanas)"
        . " | excesso vs efetivo=" . ($excessoApp > 0 ? "+{$excessoApp}" : $excessoApp)
        . " | excesso vs ×4=" . ($excessoBase > 0 ? "+{$excessoBase}" : $excessoBase) . "\n";

    // Lista os check-ins quando há QUALQUER divergência (vs app OU vs ×4).
    if ($excessoApp > 0 || $excessoBase > 0) {
        foreach ($lista as $i => $c) {
            $st    = $c['presente'] === null ? '⏳ pend' : '✅ pres';
            $admin = $c['registrado_por_admin'] ? ' [ADMIN]' : '';

            $mesAula   = substr($c['data_aula'], 0, 7);
            $mesCriado = $c['created_full'] ? substr($c['created_full'], 0, 7) : '??';
            $divMes    = ($mesCriado !== '??' && $mesCriado !== $mesAula)
                ? " ⚠️ criado em mês diferente da aula" : '';

            echo "        " . ($i + 1) . ". #{$c['id']} | aula {$c['data_aula']} {$c['horario_inicio']}"
                . " | criado {$c['created_full']}"
                . " | data_checkin_date=" . ($c['data_checkin_date'] ?? 'NULL')
                . " | {$st}{$admin}{$divMes}\n";
        }
    }
}

// ─── 4b. SIMULAÇÃO PRECISA DA VALIDAÇÃO MENSAL ──────────────────────────────
// Reproduz a validação real no insert: no momento de cada check-in, conta os já
// existentes cujo MÊS DA AULA (dias.data) == mês de CURDATE (mês de criação) e
// compara com o teto. Mostra os dois tetos: app (×4+bônus) e CheckinController (×4).
echo "\n4b. SIMULAÇÃO DO INSERT por MÊS DE CALENDÁRIO (visão histórica/legada)\n";
echo str_repeat('-', 80) . "\n";
echo "  ⚠️  Apenas histórico: o enforcement atual conta por CICLO DE COBRANÇA (Seção 8),\n";
echo "      não por mês de calendário. Tetos abaixo são aproximação por mês:\n";
echo "    • base    = checkins_semanais × 4\n";
echo "    • efetivo = base + bônus de mês com 5 semanas (ceil(dias/7) >= 5)\n\n";

$todosContaveis = [];
foreach ($porMes as $lista) {
    foreach ($lista as $c) {
        $todosContaveis[] = $c;
    }
}
usort($todosContaveis, function ($a, $b) {
    $ca = $a['created_full'] ?? '';
    $cb = $b['created_full'] ?? '';
    return $ca === $cb ? ($a['id'] <=> $b['id']) : strcmp($ca, $cb);
});

$inseridos = [];
$passouApp = [];   // furou ATÉ o teto do app (×4+bônus) → excesso real
$passouWeb = [];   // furaria o teto do CheckinController (×4), mas o app deixou passar
foreach ($todosContaveis as $c) {
    $mesRef = $c['created_full'] ? substr($c['created_full'], 0, 7) : substr($c['data_aula'], 0, 7);

    $contagem = 0;
    foreach ($inseridos as $j) {
        if (substr($j['data_aula'], 0, 7) === $mesRef) {
            $contagem++;
        }
    }

    $lim = $limiteMensalApp($mesRef);
    if ($contagem >= $lim['efetivo']) {
        $passouApp[] = $c;
        echo "  ⛔ #{$c['id']} | aula {$c['data_aula']} | criado {$c['created_full']}"
            . " | havia {$contagem} no mês {$mesRef} ≥ teto efetivo {$lim['efetivo']} (calendário)"
            . " → confira pela regra por CICLO na Seção 8 (pode não ser furo real).\n";
    } elseif ($contagem >= $lim['base']) {
        $passouWeb[] = $c;
        echo "  ⚠️  #{$c['id']} | aula {$c['data_aula']} | criado {$c['created_full']}"
            . " | havia {$contagem} no mês {$mesRef}: ≥ {$lim['base']} (×4) mas < {$lim['efetivo']} (×4+bônus)"
            . " → dentro do mês graças ao bônus de 5ª semana ({$lim['semanas']} semanas no mês).\n";
    }

    $inseridos[] = $c;
}

if (!$passouApp && !$passouWeb) {
    echo "  Nenhum check-in atingiu sequer o teto base (×4) por mês de calendário.\n";
} elseif (!$passouApp) {
    echo "\n  ✅ Nenhum check-in furou o teto efetivo por mês de calendário (×4 + bônus).\n";
    echo "     Os marcados com ⚠️ só ultrapassam o ×4 base, mas couberam no bônus de 5ª semana.\n";
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

// ─── 5b. STATUS DE PRESENÇA — aulas sem presença marcada (pendentes) ─────────
// Pendente (presente=NULL) CONTA no teto; Falta (presente=0) NÃO conta (libera
// crédito de reposição). Uma aula deixada pendente, em vez de marcada como falta,
// mantém a vaga ocupada e pode fazer o mês "parecer" 1 a mais.
echo "\n5b. STATUS DE PRESENÇA POR MÊS (pendente x presente x falta)\n";
echo str_repeat('-', 80) . "\n";

// Reconta a partir de TODOS os check-ins do aluno na modalidade do plano,
// agora incluindo as faltas (presente=0) para o panorama de marcação.
$porMesPresenca = [];
foreach ($checkins as $c) {
    if ($modalidade !== null && (int) $c['modalidade_id'] !== $modalidade) {
        continue;
    }
    $mesKey = substr($c['data_aula'], 0, 7);
    $porMesPresenca[$mesKey][] = $c;
}
ksort($porMesPresenca);

$pendentesGlobais = [];
foreach ($porMesPresenca as $mesKey => $lista) {
    $pres = $pend = $falt = 0;
    $pendentesDoMes = [];
    foreach ($lista as $c) {
        if ($c['presente'] === null)      { $pend++; $pendentesDoMes[] = $c; $pendentesGlobais[] = $c; }
        elseif ((int) $c['presente'] === 1) { $pres++; }
        else                                { $falt++; }
    }
    $lim     = $limiteMensalApp($mesKey);
    $contam  = $pres + $pend;                 // o que entra no teto
    $semFalt = $pres;                          // se as pendentes virassem falta
    echo "  {$mesKey}: presentes={$pres} | pendentes(não marcada)={$pend} | faltas={$falt}"
        . " | conta no teto={$contam}/{$lim['efetivo']}\n";
    if ($pend > 0) {
        foreach ($pendentesDoMes as $c) {
            $admin = $c['registrado_por_admin'] ? ' [ADMIN]' : '';
            echo "        ⏳ PENDENTE #{$c['id']} | aula {$c['data_aula']} {$c['horario_inicio']}{$admin}"
                . "  → se marcada como FALTA, mês cairia para {$semFalt} (não contaria).\n";
        }
    }
}

if ($pendentesGlobais) {
    echo "\n  ⚠️  Há " . count($pendentesGlobais) . " aula(s) com presença NÃO marcada (pendente=NULL).\n";
    echo "      Pendente conta no teto igual a presente. Marcá-las como FALTA reduz a contagem\n";
    echo "      do mês e libera crédito de reposição — é provavelmente a 'margem' observada.\n";
} else {
    echo "\n  ✅ Nenhuma aula pendente: toda presença foi marcada (presente ou falta).\n";
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
    echo "      → limite mensal por CICLO DE COBRANÇA (vencimento) = ×4 + bônus 5ª semana,\n";
    echo "        contando só os check-ins dentro do ciclo atual. Veja a Seção 8.\n";
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
$excedeApp  = [];  // meses acima do teto REAL do app (×4 + bônus)
$excedeBase = [];  // meses acima só do ×4 (CheckinController), mas dentro do app
foreach ($porMes as $mesKey => $lista) {
    $lim = $limiteMensalApp($mesKey);
    $t   = count($lista);
    if ($t - $lim['efetivo'] > 0)      $excedeApp[$mesKey]  = $t - $lim['efetivo'];
    elseif ($t - $lim['base'] > 0)     $excedeBase[$mesKey] = $t - $lim['base'];
}

echo "  Matrícula #{$matriculaId} / Aluno {$mat['aluno_nome']} / Plano {$mat['plano_nome']}\n";
echo "  checkins_semanais={$checkinsSemanais} | permite_reposicao=" . ($permiteReposicao ? 'SIM' : 'NÃO') . "\n";
echo "  Meses acima do limite REAL do app (×4+bônus): " . (count($excedeApp) ? implode(', ', array_map(
        fn($k, $v) => "{$k} (+{$v})", array_keys($excedeApp), $excedeApp)) : 'NENHUM') . "\n";
echo "  Meses acima de ×4 mas DENTRO do bônus (5 semanas): " . (count($excedeBase) ? implode(', ', array_map(
        fn($k, $v) => "{$k} (+{$v})", array_keys($excedeBase), $excedeBase)) : 'nenhum') . "\n";

echo "\n  CONCLUSÃO:\n";
echo "   ℹ️  Os números acima são por MÊS DE CALENDÁRIO (visão histórica). O enforcement\n";
echo "       atual é por CICLO DE COBRANÇA — o VEREDITO vale pela Seção 8 abaixo.\n";
if (!$excedeApp && $excedeBase) {
    echo "   • Meses sinalizados só passam do ×4 base, mas cabem no bônus de 5ª semana — ok.\n";
} elseif ($excedeApp) {
    echo "   • Há meses de calendário acima de ×4+bônus, mas isso NÃO implica furo no ciclo:\n";
    echo "     ciclos ancorados no vencimento cruzam o mês (ex.: 25→25). Confira a Seção 8 —\n";
    echo "     se lá estiver 'dentro do limite', não houve excesso real.\n";
} else {
    echo "   • Dentro do limite em todos os meses de calendário.\n";
}

// ─── 8. CICLO DE COBRANÇA (nova regra) — Checkin::obterCicloCheckins ─────────
echo "\n8. CICLO DE CHECK-IN POR VENCIMENTO (nova regra centralizada)\n";
echo str_repeat('-', 80) . "\n";

if (class_exists(\App\Models\Checkin::class)) {
    $checkinModel = new \App\Models\Checkin($db);
    $ciclo = $checkinModel->obterCicloCheckins($usuarioId, $tenantId, $modalidade);

    if (empty($ciclo['tem_plano'])) {
        echo "  Sem plano ativo/vigente para o cálculo de ciclo (matrícula vencida?).\n";
        echo "  → Nesse estado o limite não é aplicado (igual à regra atual).\n";
    } else {
        $ini = date('d/m/Y', strtotime($ciclo['ciclo_inicio']));
        $fim = date('d/m/Y', strtotime($ciclo['ciclo_fim']));
        echo "  Plano               : {$ciclo['plano_nome']}\n";
        echo "  Ciclo atual         : {$ini}  →  {$fim}  (fim exclusivo)\n";
        echo "  Dias no ciclo       : {$ciclo['dias_no_ciclo']}\n";
        echo "  Semanas (ceil/7)    : {$ciclo['semanas']}" . ($ciclo['bonus_cinco_semanas'] ? "  (+1 bônus 5ª semana)" : "") . "\n";
        echo "  Limite do ciclo     : {$ciclo['limite_mensal']} ({$ciclo['checkins_semanais']}×4" . ($ciclo['bonus_cinco_semanas'] ? "+1" : "") . ")\n";
        echo "  Check-ins no ciclo  : {$ciclo['checkins_no_ciclo']}\n";
        $excesso = $ciclo['checkins_no_ciclo'] - $ciclo['limite_mensal'];
        echo "  Situação            : " . ($excesso >= 0
            ? "⛔ atingiu/excedeu o limite (excesso " . ($excesso > 0 ? "+{$excesso}" : "0, no teto") . ")"
            : "✅ dentro do limite (resta " . abs($excesso) . ")") . "\n";

        if (!empty($ciclo['dias_checkin'])) {
            echo "\n  Dias contabilizados no ciclo:\n";
            foreach ($ciclo['dias_checkin'] as $i => $d) {
                $st = $d['status'] === 'pendente' ? '⏳ pend' : '✅ pres';
                echo "    " . ($i + 1) . ". {$d['data']}" . ($d['horario'] ? " {$d['horario']}" : "")
                    . ($d['modalidade'] ? " · {$d['modalidade']}" : "") . " | {$st}\n";
            }
        }
    }
} else {
    echo "  (autoload indisponível — não foi possível instanciar o model)\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Fim do diagnóstico.\n";
