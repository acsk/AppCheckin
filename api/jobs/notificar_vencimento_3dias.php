<?php
/**
 * Job: Notificar usuários sobre vencimento em 3 dias
 *
 * Busca parcelas (`pagamentos_plano`) com `data_vencimento = CURDATE() + 3` e
 * `status_pagamento_id = 1` (Aguardando) e cria uma notificação para o usuário
 * responsável (via tabela `alunos` -> `usuarios`).
 *
 * Opções:
 * --dry-run    Simula execução sem inserir notificações
 * --quiet      Modo silencioso (apenas erros)
 * --tenant=N   Processa apenas um tenant específico
 *
 * Agendamento recomendado: diário (ex.: 00:05)
 */

define('LOCK_FILE', '/tmp/notificar_vencimento_3dias.lock');
define('MAX_EXECUTION_TIME', 300);

$options = getopt('', ['dry-run', 'quiet', 'tenant:']);
$dryRun = isset($options['dry-run']);
$quiet = isset($options['quiet']);
$tenantId = isset($options['tenant']) ? (int)$options['tenant'] : null;

function logMsg($message, $isQuiet = false) {
    if (!$isQuiet) {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }
}

// Lock
if (file_exists(LOCK_FILE)) {
    $lockTime = filemtime(LOCK_FILE);
    if (time() - $lockTime > 600) {
        unlink(LOCK_FILE);
        logMsg("⚠️  Lock antigo removido", $quiet);
    } else {
        logMsg("❌ Job já está em execução (lock ativo)", $quiet);
        exit(1);
    }
}
file_put_contents(LOCK_FILE, getmypid());
set_time_limit(MAX_EXECUTION_TIME);

try {
    logMsg("🚀 Iniciando job: notificar vencimento em 3 dias", $quiet);
    if ($dryRun) logMsg("⚠️  MODO DRY-RUN (nenhuma alteração será feita)", $quiet);

    // Conectar DB
    require_once __DIR__ . '/../config/database.php';
    if (!isset($pdo)) throw new Exception('Erro ao conectar ao banco');

    // Carregar model (sem autoload garantido)
    require_once __DIR__ . '/../app/Models/Notificacao.php';

    $dataAlvo = date('Y-m-d', strtotime('+3 days'));
    logMsg("📅 Procurando pagamentos com data_vencimento = {$dataAlvo}", $quiet);

    $sql = "
        SELECT
            pp.id as pagamento_id,
            pp.tenant_id,
            pp.matricula_id,
            pp.aluno_id,
            pp.valor,
            pp.data_vencimento,
            a.usuario_id,
            u.nome as usuario_nome,
            u.email as usuario_email
        FROM pagamentos_plano pp
        LEFT JOIN alunos a ON a.id = pp.aluno_id
        LEFT JOIN usuarios u ON u.id = a.usuario_id
        WHERE pp.data_vencimento = :dataAlvo
        AND pp.status_pagamento_id = 1
    ";

    if ($tenantId) {
        $sql .= " AND pp.tenant_id = :tenant_id";
    }

    $stmt = $pdo->prepare($sql);
    $params = ['dataAlvo' => $dataAlvo];
    if ($tenantId) $params['tenant_id'] = $tenantId;

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMsg("   Encontrados: " . count($rows) . " pagamentos a notificar", $quiet);

    $model = new \App\Models\Notificacao($pdo);
    $contador = 0;
    foreach ($rows as $r) {
        $usuarioId = $r['usuario_id'] ? (int)$r['usuario_id'] : null;
        if (!$usuarioId) {
            logMsg("   ⚠️  Pagamento #{$r['pagamento_id']} sem usuário vinculado (aluno_id={$r['aluno_id']})", $quiet);
            continue;
        }

        $valor = number_format((float)$r['valor'], 2, ',', '.');
        $dataFmt = date('d/m/Y', strtotime($r['data_vencimento']));
        $titulo = 'Pagamento vence em 3 dias';
        $mensagem = "Seu pagamento de R$ {$valor} vence em {$dataFmt} (3 dias). Por favor, efetue o pagamento para evitar bloqueios.";

        if ($dryRun) {
            logMsg("   [DRY-RUN] Notificar usuario_id={$usuarioId} | matricula={$r['matricula_id']} | valor=R$ {$valor} | vencimento={$dataFmt}", $quiet);
            $contador++;
            continue;
        }

        $insertId = $model->create([
            'tenant_id' => $r['tenant_id'],
            'usuario_id' => $usuarioId,
            'tipo' => 'info',
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'dados' => [
                'pagamento_id' => $r['pagamento_id'],
                'matricula_id' => $r['matricula_id'],
                'valor' => $r['valor'],
                'data_vencimento' => $r['data_vencimento']
            ]
        ]);

        if ($insertId === null) {
            logMsg("   ❌ Erro ao criar notificação para usuario_id={$usuarioId} (pagamento {$r['pagamento_id']})", $quiet);
        } else {
            $contador++;
            logMsg("   ✅ Notificação criada id={$insertId} para usuario_id={$usuarioId}", $quiet);
        }
    }

    logMsg("\n✅ Total notificações processadas: {$contador}", $quiet);

    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg("Job finalizado", $quiet);
    exit(0);

} catch (Exception $e) {
    if (file_exists(LOCK_FILE)) unlink(LOCK_FILE);
    logMsg("❌ ERRO FATAL: " . $e->getMessage(), $quiet);
    exit(1);
}
