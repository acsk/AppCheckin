<?php
/**
 * Debug Matrícula #324
 *
 * Foco:
 * - Confirmar se a matrícula é diária e segue ativa.
 * - Verificar se existe check-in elegível para o job cancelar_diarias_apos_checkin.
 * - Verificar se existe check-in com presente=1, que é o gatilho da finalização
 *   no fluxo de confirmação de presença.
 *
 * Uso:
 *   php debug_matricula_324.php
 */

require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('America/Sao_Paulo');

$db = require __DIR__ . '/config/database.php';
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$matriculaId = 324;

function boolText(bool $value): string
{
    return $value ? 'SIM' : 'NAO';
}

echo "====== DEBUG MATRICULA #{$matriculaId} ======\n";
echo 'Data BRT: ' . date('Y-m-d H:i:s') . "\n\n";

$dbInfo = $db->query('SELECT DATABASE() AS database_name, @@hostname AS hostname')->fetch(PDO::FETCH_ASSOC);
echo "Ambiente conectado:\n";
echo '  database: ' . ($dbInfo['database_name'] ?? '-') . "\n";
echo '  hostname: ' . ($dbInfo['hostname'] ?? '-') . "\n\n";

$stmt = $db->prepare("
    SELECT
        m.*,
        sm.codigo AS status_codigo,
        sm.nome AS status_nome,
        a.nome AS aluno_nome,
        a.usuario_id,
        u.email AS aluno_email,
        p.nome AS plano_nome,
        p.duracao_dias,
        p.checkins_semanais,
        p.modalidade_id,
        mdl.nome AS modalidade_nome
    FROM matriculas m
    INNER JOIN alunos a ON a.id = m.aluno_id
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    LEFT JOIN modalidades mdl ON mdl.id = p.modalidade_id
    WHERE m.id = ?
    LIMIT 1
");
$stmt->execute([$matriculaId]);
$matricula = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$matricula) {
    echo "Matricula nao encontrada.\n";
    exit(1);
}

$alunoId = (int) $matricula['aluno_id'];
$tenantId = (int) $matricula['tenant_id'];
$dataInicio = $matricula['data_inicio'] ?: $matricula['data_matricula'];
$dataVencimento = $matricula['data_vencimento'];
$statusCodigo = (string) $matricula['status_codigo'];
$duracaoDias = (int) $matricula['duracao_dias'];
$ehDiaria = $duracaoDias === 1;

echo "1. MATRÍCULA\n";
echo str_repeat('-', 80) . "\n";
echo "Aluno:                  {$matricula['aluno_nome']} (aluno_id={$alunoId}, usuario_id={$matricula['usuario_id']})\n";
echo "Email:                  " . ($matricula['aluno_email'] ?: '-') . "\n";
echo "Tenant:                 {$tenantId}\n";
echo "Plano:                  {$matricula['plano_nome']} (plano_id={$matricula['plano_id']})\n";
echo "Modalidade:             " . ($matricula['modalidade_nome'] ?: '-') . "\n";
echo "duracao_dias:           {$duracaoDias}\n";
echo "tipo_cobranca:          " . ($matricula['tipo_cobranca'] ?: '-') . "\n";
echo "Status:                 {$matricula['status_nome']} ({$statusCodigo})\n";
echo "data_matricula:         {$matricula['data_matricula']}\n";
echo "data_inicio:            {$matricula['data_inicio']}\n";
echo "data_vencimento:        {$dataVencimento}\n";
echo "proxima_data_vencimento:" . ' ' . ($matricula['proxima_data_vencimento'] ?: 'NULL') . "\n";
echo "created_at:             {$matricula['created_at']}\n";
echo "updated_at:             {$matricula['updated_at']}\n";

echo "\n2. REGRAS DA DIARIA\n";
echo str_repeat('-', 80) . "\n";
echo 'Eh diaria?              ' . boolText($ehDiaria) . "\n";
echo 'Status ativa?           ' . boolText($statusCodigo === 'ativa') . "\n";

if ($matricula['data_inicio'] && $dataVencimento) {
    $diasVigencia = (int) (new DateTimeImmutable($matricula['data_inicio']))
        ->diff(new DateTimeImmutable($dataVencimento))
        ->format('%r%a');
    echo "Dias entre inicio/venc: {$diasVigencia}\n";
}

$hoje = date('Y-m-d');
echo "Hoje:                   {$hoje}\n";
echo 'Ja venceu?              ' . boolText(!empty($dataVencimento) && $dataVencimento < $hoje) . "\n";

$columnsStmt = $db->query('SHOW COLUMNS FROM checkins');
$checkinColumns = array_column($columnsStmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

$hasPresente = in_array('presente', $checkinColumns, true);
$hasMatriculaId = in_array('matricula_id', $checkinColumns, true);
$hasTenantId = in_array('tenant_id', $checkinColumns, true);
$hasDataCheckin = in_array('data_checkin', $checkinColumns, true);

$selectFields = ['c.id', 'c.aluno_id', 'c.created_at'];
if ($hasDataCheckin) {
    $selectFields[] = 'c.data_checkin';
}
if ($hasPresente) {
    $selectFields[] = 'c.presente';
}
if ($hasMatriculaId) {
    $selectFields[] = 'c.matricula_id';
}
if ($hasTenantId) {
    $selectFields[] = 'c.tenant_id';
}

$checkinSql = 'SELECT ' . implode(', ', $selectFields) . ' FROM checkins c WHERE c.aluno_id = :aluno_id';
if ($hasTenantId) {
    $checkinSql .= ' AND c.tenant_id = :tenant_id';
}
$checkinSql .= ' AND DATE(c.created_at) >= :data_inicio ORDER BY c.created_at ASC, c.id ASC LIMIT 100';

$stmt = $db->prepare($checkinSql);
$params = [
    'aluno_id' => $alunoId,
    'data_inicio' => $dataInicio,
];
if ($hasTenantId) {
    $params['tenant_id'] = $tenantId;
}
$stmt->execute($params);
$checkins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "\n3. CHECKINS APOS DATA_INICIO\n";
echo str_repeat('-', 80) . "\n";
echo 'Janela usada pelo job:  DATE(created_at) >= ' . ($dataInicio ?: 'NULL') . "\n";
echo 'Total encontrados:      ' . count($checkins) . "\n";

$checkinsComPresenca = 0;
foreach ($checkins as $checkin) {
    $linha = "  CK #{$checkin['id']} | created_at={$checkin['created_at']}";
    if ($hasDataCheckin) {
        $linha .= ' | data_checkin=' . ($checkin['data_checkin'] ?? 'NULL');
    }
    if ($hasPresente) {
        $presente = (int) ($checkin['presente'] ?? 0);
        $linha .= ' | presente=' . $presente;
        if ($presente === 1) {
            $checkinsComPresenca++;
        }
    }
    if ($hasMatriculaId) {
        $linha .= ' | matricula_id=' . ($checkin['matricula_id'] ?? 'NULL');
    }
    echo $linha . "\n";
}

if (!$hasPresente) {
    echo "Campo presente nao existe em checkins.\n";
}

echo "\n4. ELEGIBILIDADE NO JOB cancelar_diarias_apos_checkin\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("
    SELECT
        m.id AS matricula_id,
        MIN(DATE(c.created_at)) AS data_primeiro_checkin,
        COUNT(*) AS total_checkins_encontrados
    FROM matriculas m
    INNER JOIN planos p ON p.id = m.plano_id
    INNER JOIN status_matricula sm ON sm.id = m.status_id
    INNER JOIN checkins c ON c.aluno_id = m.aluno_id
                          AND DATE(c.created_at) >= m.data_inicio
    WHERE m.id = ?
      AND sm.codigo = 'ativa'
      AND p.duracao_dias = 1
      AND m.tenant_id IS NOT NULL
    GROUP BY m.id
");
$stmt->execute([$matriculaId]);
$jobRow = $stmt->fetch(PDO::FETCH_ASSOC);

if ($jobRow) {
    echo "Entraria no job?        SIM\n";
    echo "Primeiro checkin job:   {$jobRow['data_primeiro_checkin']}\n";
    echo "Qtd checkins no job:    {$jobRow['total_checkins_encontrados']}\n";
    echo "Diagnostico:            Se continua ativa, o job nao rodou, falhou, ou nao persistiu a atualizacao.\n";
} else {
    echo "Entraria no job?        NAO\n";
    if (!$ehDiaria) {
        echo "Motivo:                 Plano da matricula nao e diaria (duracao_dias != 1).\n";
    } elseif ($statusCodigo !== 'ativa') {
        echo "Motivo:                 Matricula nao esta com status ativa.\n";
    } elseif (empty($tenantId)) {
        echo "Motivo:                 tenant_id nulo.\n";
    } elseif (empty($checkins)) {
        echo "Motivo:                 Nenhum checkin com DATE(created_at) >= data_inicio.\n";
    } else {
        echo "Motivo:                 Nao bateu em alguma condicao do job; revisar dados acima.\n";
    }
}

echo "\n5. ELEGIBILIDADE NA FINALIZACAO POR PRESENCA\n";
echo str_repeat('-', 80) . "\n";

$stmt = $db->prepare("SELECT id, codigo, nome FROM status_matricula WHERE codigo IN ('concluida', 'finalizada') ORDER BY id");
$stmt->execute();
$statusConclusao = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'Status de conclusao:    ';
if (empty($statusConclusao)) {
    echo "NAO ENCONTRADO\n";
} else {
    echo implode(', ', array_map(static fn(array $row): string => $row['codigo'] . '=' . $row['id'], $statusConclusao)) . "\n";
}

echo 'Checkins presente=1:    ' . ($hasPresente ? $checkinsComPresenca : 'N/A') . "\n";
if ($hasPresente) {
    if ($checkinsComPresenca > 0 && $ehDiaria && $statusCodigo === 'ativa' && !empty($statusConclusao)) {
        echo "Diagnostico:            Havia base para o fluxo de presenca concluir a matricula.\n";
    } elseif ($checkinsComPresenca === 0) {
        echo "Diagnostico:            Nenhum checkin com presente=1; esse fluxo nao encerraria a matricula.\n";
    } elseif (empty($statusConclusao)) {
        echo "Diagnostico:            Sem status concluida/finalizada, o fluxo nao conseguiria atualizar.\n";
    } else {
        echo "Diagnostico:            Fluxo de presenca bloqueado por status/plano.\n";
    }
}

echo "\n6. PAGAMENTOS DA MATRICULA\n";
echo str_repeat('-', 80) . "\n";
$stmt = $db->prepare("
    SELECT
        pp.id,
        pp.data_vencimento,
        pp.data_pagamento,
        pp.status_pagamento_id,
        sp.nome AS status_nome,
        pp.valor,
        pp.observacoes
    FROM pagamentos_plano pp
    LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
    WHERE pp.matricula_id = ?
    ORDER BY pp.data_vencimento ASC, pp.id ASC
");
$stmt->execute([$matriculaId]);
$pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Total parcelas:         ' . count($pagamentos) . "\n";
foreach ($pagamentos as $pagamento) {
    echo '  Pag #' . $pagamento['id']
        . ' | venc=' . ($pagamento['data_vencimento'] ?: '-')
        . ' | pag=' . ($pagamento['data_pagamento'] ?: '-')
        . ' | status=' . ($pagamento['status_nome'] ?: $pagamento['status_pagamento_id'])
        . ' | valor=' . number_format((float) $pagamento['valor'], 2, ',', '.')
        . "\n";
    if (!empty($pagamento['observacoes'])) {
        echo '       obs: ' . $pagamento['observacoes'] . "\n";
    }
}

echo "\n7. CONCLUSAO\n";
echo str_repeat('-', 80) . "\n";
$conclusoes = [];

if (!$ehDiaria) {
    $conclusoes[] = 'A matricula 324 nao esta configurada como diaria.';
}

if (empty($checkins)) {
    $conclusoes[] = 'Nao ha checkin elegivel para o job; se a regra depende de checkin, a matricula seguir ativa e esperado.';
}

if ($hasPresente && $checkinsComPresenca === 0) {
    $conclusoes[] = 'Nao existe checkin com presente=1; o fluxo de confirmacao de presenca nao encerraria a diaria.';
}

if ($jobRow) {
    $conclusoes[] = 'A matricula entraria no job cancelar_diarias_apos_checkin; se continua ativa, investigar execucao/cron/logs do job.';
}

if (empty($conclusoes)) {
    $conclusoes[] = 'Nao apareceu um bloqueio obvio; revisar logs de execucao do job e historico de alteracoes da matricula.';
}

foreach ($conclusoes as $index => $conclusao) {
    echo ($index + 1) . '. ' . $conclusao . "\n";
}