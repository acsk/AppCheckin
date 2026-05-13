<?php
/**
 * FIX: consolidar usuario duplicado mantendo apenas o usuario alvo.
 *
 * Caso solicitado:
 * - manter: usuario 252
 * - excluir: usuario 121
 *
 * O script:
 * 1) Faz diagnostico completo (dry-run por padrao)
 * 2) Em --execute, roda em transacao:
 *    - consolida vinculos tenant_usuario_papel
 *    - consolida aluno (e referencias por aluno_id)
 *    - move referencias por usuario_id para o alvo
 *    - tenta aplicar CPF do usuario removido no usuario alvo
 *    - exclui usuario duplicado
 *
 * Uso:
 *   php fix_usuario_duplicado_252.php
 *   php fix_usuario_duplicado_252.php --target=252 --source=121
 *   php fix_usuario_duplicado_252.php --target=252 --source=121 --execute
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = require __DIR__ . '/config/database.php';

$args = array_slice($argv ?? [], 1);
$targetUserId = 252;
$sourceUserId = 121;
$execute = false;

foreach ($args as $arg) {
    if (preg_match('/--target=(\d+)/', $arg, $m)) {
        $targetUserId = (int) $m[1];
    }
    if (preg_match('/--source=(\d+)/', $arg, $m)) {
        $sourceUserId = (int) $m[1];
    }
    if ($arg === '--execute') {
        $execute = true;
    }
}

if ($targetUserId === $sourceUserId) {
    fwrite(STDERR, "Erro: target e source nao podem ser iguais.\n");
    exit(1);
}

function title(string $text): void
{
    echo "\n" . str_repeat('=', 100) . "\n";
    echo $text . "\n";
    echo str_repeat('=', 100) . "\n";
}

function digitsOnly(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $v = preg_replace('/[^0-9]/', '', $value);
    return $v === '' ? null : $v;
}

function fetchUser(PDO $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchAlunoByUsuario(PDO $db, int $usuarioId): ?array
{
    $stmt = $db->prepare('SELECT * FROM alunos WHERE usuario_id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fkRefs(PDO $db, string $referencedTable): array
{
    $sql = "
        SELECT
            kcu.CONSTRAINT_NAME,
            kcu.TABLE_NAME,
            kcu.COLUMN_NAME,
            rc.UPDATE_RULE,
            rc.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE kcu
        INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
            ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
           AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
        WHERE kcu.REFERENCED_TABLE_SCHEMA = DATABASE()
          AND kcu.REFERENCED_TABLE_NAME = :table
        ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(['table' => $referencedTable]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function countRefs(PDO $db, string $table, string $column, int $value): int
{
    $sql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = :v";
    $stmt = $db->prepare($sql);
    $stmt->execute(['v' => $value]);
    return (int) $stmt->fetchColumn();
}

function logJson(string $label, $data): void
{
    echo $label . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

function updateRef(PDO $db, string $table, string $column, int $from, int $to): int
{
    $sql = "UPDATE `{$table}` SET `{$column}` = :to WHERE `{$column}` = :from";
    $stmt = $db->prepare($sql);
    $stmt->execute(['to' => $to, 'from' => $from]);
    return $stmt->rowCount();
}

title('FIX USUARIO DUPLICADO - MANTER #' . $targetUserId . ' E EXCLUIR #' . $sourceUserId);
echo 'Data/Hora: ' . date('Y-m-d H:i:s') . "\n";
echo 'Modo: ' . ($execute ? 'EXECUCAO' : 'DRY-RUN (sem alterar banco)') . "\n";

$target = fetchUser($db, $targetUserId);
$source = fetchUser($db, $sourceUserId);

if (!$target) {
    fwrite(STDERR, "Erro: usuario alvo #{$targetUserId} nao encontrado.\n");
    exit(1);
}
if (!$source) {
    fwrite(STDERR, "Erro: usuario origem #{$sourceUserId} nao encontrado.\n");
    exit(1);
}

title('1) USUARIOS ENVOLVIDOS');
logJson('target', [
    'id' => $target['id'],
    'nome' => $target['nome'],
    'email' => $target['email'],
    'cpf' => $target['cpf'],
    'telefone' => $target['telefone'],
    'ativo' => $target['ativo'] ?? null,
]);
logJson('source', [
    'id' => $source['id'],
    'nome' => $source['nome'],
    'email' => $source['email'],
    'cpf' => $source['cpf'],
    'telefone' => $source['telefone'],
    'ativo' => $source['ativo'] ?? null,
]);

$targetAluno = fetchAlunoByUsuario($db, $targetUserId);
$sourceAluno = fetchAlunoByUsuario($db, $sourceUserId);

title('2) ALUNOS ENVOLVIDOS');
logJson('target_aluno', $targetAluno ? [
    'id' => $targetAluno['id'],
    'usuario_id' => $targetAluno['usuario_id'],
    'nome' => $targetAluno['nome'] ?? null,
    'cpf' => $targetAluno['cpf'] ?? null,
    'telefone' => $targetAluno['telefone'] ?? null,
] : null);
logJson('source_aluno', $sourceAluno ? [
    'id' => $sourceAluno['id'],
    'usuario_id' => $sourceAluno['usuario_id'],
    'nome' => $sourceAluno['nome'] ?? null,
    'cpf' => $sourceAluno['cpf'] ?? null,
    'telefone' => $sourceAluno['telefone'] ?? null,
] : null);

$titleRefs = '3) REFERENCIAS FK -> usuarios.id para source #' . $sourceUserId;
title($titleRefs);
$userRefs = fkRefs($db, 'usuarios');
foreach ($userRefs as $ref) {
    $count = countRefs($db, $ref['TABLE_NAME'], $ref['COLUMN_NAME'], $sourceUserId);
    echo "{$ref['TABLE_NAME']}.{$ref['COLUMN_NAME']} => {$count} registro(s) | on_update={$ref['UPDATE_RULE']} on_delete={$ref['DELETE_RULE']}\n";
}

if ($sourceAluno) {
    title('4) REFERENCIAS FK -> alunos.id para source_aluno #' . $sourceAluno['id']);
    $alunoRefs = fkRefs($db, 'alunos');
    foreach ($alunoRefs as $ref) {
        $count = countRefs($db, $ref['TABLE_NAME'], $ref['COLUMN_NAME'], (int) $sourceAluno['id']);
        echo "{$ref['TABLE_NAME']}.{$ref['COLUMN_NAME']} => {$count} registro(s) | on_update={$ref['UPDATE_RULE']} on_delete={$ref['DELETE_RULE']}\n";
    }
} else {
    $alunoRefs = [];
    title('4) REFERENCIAS FK -> alunos.id');
    echo "Usuario source nao possui aluno vinculado.\n";
}

$sourceCpf = digitsOnly($source['cpf'] ?? null);
$targetCpf = digitsOnly($target['cpf'] ?? null);
$cpfPlan = null;

if ($sourceCpf && $sourceCpf !== $targetCpf) {
    $stmtCpf = $db->prepare('SELECT id FROM usuarios WHERE cpf = :cpf AND id NOT IN (:target, :source) LIMIT 1');
    $stmtCpf->bindValue(':cpf', $sourceCpf);
    $stmtCpf->bindValue(':target', $targetUserId, PDO::PARAM_INT);
    $stmtCpf->bindValue(':source', $sourceUserId, PDO::PARAM_INT);
    $stmtCpf->execute();
    $cpfConflict = $stmtCpf->fetch(PDO::FETCH_ASSOC);

    if ($cpfConflict) {
        title('5) PLANO CPF');
        echo "CPF {$sourceCpf} possui conflito com usuario #{$cpfConflict['id']} (alem dos dois usuarios do merge).\n";
        echo "Abortar merge para nao violar unique_cpf.\n";
        exit(1);
    }
    $cpfPlan = $sourceCpf;
}

if (!$execute) {
    title('DRY-RUN CONCLUIDO');
    echo "Nenhum dado alterado.\n";
    echo "Para executar de fato:\n";
    echo "php fix_usuario_duplicado_252.php --target={$targetUserId} --source={$sourceUserId} --execute\n";
    exit(0);
}

try {
    $db->beginTransaction();

    // Lock nos dois usuarios para evitar corrida.
    $stmtLock = $db->prepare('SELECT id FROM usuarios WHERE id IN (?, ?) FOR UPDATE');
    $stmtLock->execute([$targetUserId, $sourceUserId]);

    title('6) EXECUCAO - CONSOLIDANDO tenant_usuario_papel');
    $sqlInsertTup = "
        INSERT IGNORE INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
        SELECT tenant_id, :target, papel_id, ativo, created_at, NOW()
        FROM tenant_usuario_papel
        WHERE usuario_id = :source
    ";
    $stmtInsertTup = $db->prepare($sqlInsertTup);
    $stmtInsertTup->execute(['target' => $targetUserId, 'source' => $sourceUserId]);
    echo 'tenant_usuario_papel insert ignore concluido.\n';

    $sqlMergeAtivo = "
        UPDATE tenant_usuario_papel tgt
        INNER JOIN tenant_usuario_papel src
            ON src.tenant_id = tgt.tenant_id
           AND src.papel_id = tgt.papel_id
           AND src.usuario_id = :source
        SET tgt.ativo = GREATEST(tgt.ativo, src.ativo),
            tgt.updated_at = NOW()
        WHERE tgt.usuario_id = :target
    ";
    $stmtMergeAtivo = $db->prepare($sqlMergeAtivo);
    $stmtMergeAtivo->execute(['target' => $targetUserId, 'source' => $sourceUserId]);
    echo 'tenant_usuario_papel merge de ativo concluido.\n';

    $stmtDeleteTup = $db->prepare('DELETE FROM tenant_usuario_papel WHERE usuario_id = ?');
    $stmtDeleteTup->execute([$sourceUserId]);
    echo 'tenant_usuario_papel removidos do source: ' . $stmtDeleteTup->rowCount() . "\n";

    title('7) EXECUCAO - CONSOLIDANDO alunos e referencias por aluno_id');
    $targetAluno = fetchAlunoByUsuario($db, $targetUserId);
    $sourceAluno = fetchAlunoByUsuario($db, $sourceUserId);

    if ($sourceAluno && $targetAluno) {
        $sourceAlunoId = (int) $sourceAluno['id'];
        $targetAlunoId = (int) $targetAluno['id'];

        foreach ($alunoRefs as $ref) {
            if ($ref['TABLE_NAME'] === 'alunos') {
                continue;
            }
            $moved = updateRef($db, $ref['TABLE_NAME'], $ref['COLUMN_NAME'], $sourceAlunoId, $targetAlunoId);
            if ($moved > 0) {
                echo "movido {$moved} registro(s): {$ref['TABLE_NAME']}.{$ref['COLUMN_NAME']} ({$sourceAlunoId} -> {$targetAlunoId})\n";
            }
        }

        $stmtDeleteSourceAluno = $db->prepare('DELETE FROM alunos WHERE id = ?');
        $stmtDeleteSourceAluno->execute([$sourceAlunoId]);
        echo 'aluno source removido: ' . $stmtDeleteSourceAluno->rowCount() . "\n";
    } elseif ($sourceAluno && !$targetAluno) {
        $stmtMoveAluno = $db->prepare('UPDATE alunos SET usuario_id = :target, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmtMoveAluno->execute([
            'target' => $targetUserId,
            'id' => (int) $sourceAluno['id'],
        ]);
        echo 'aluno source reatribuido ao target.\n';
    } else {
        echo 'nada para consolidar em alunos.\n';
    }

    title('8) EXECUCAO - CONSOLIDANDO referencias por usuario_id');
    foreach ($userRefs as $ref) {
        if ($ref['TABLE_NAME'] === 'tenant_usuario_papel' || $ref['TABLE_NAME'] === 'alunos') {
            continue;
        }
        $moved = updateRef($db, $ref['TABLE_NAME'], $ref['COLUMN_NAME'], $sourceUserId, $targetUserId);
        if ($moved > 0) {
            echo "movido {$moved} registro(s): {$ref['TABLE_NAME']}.{$ref['COLUMN_NAME']} ({$sourceUserId} -> {$targetUserId})\n";
        }
    }

    title('9) EXECUCAO - AJUSTE DE CPF NO target');
    if ($cpfPlan !== null) {
        $stmtUpdateCpf = $db->prepare('UPDATE usuarios SET cpf = :cpf, updated_at = NOW() WHERE id = :id');
        $stmtUpdateCpf->execute(['cpf' => $cpfPlan, 'id' => $targetUserId]);
        echo "CPF do target atualizado para {$cpfPlan}.\n";
    } else {
        echo "CPF do target mantido (sem alteracao necessaria).\n";
    }

    title('10) EXECUCAO - EXCLUSAO DO USUARIO source');
    $stmtDeleteSourceUser = $db->prepare('DELETE FROM usuarios WHERE id = ?');
    $stmtDeleteSourceUser->execute([$sourceUserId]);
    $deletedUsers = $stmtDeleteSourceUser->rowCount();
    echo "usuarios removidos: {$deletedUsers}\n";

    if ($deletedUsers !== 1) {
        throw new RuntimeException('Falha ao remover usuario source.');
    }

    $db->commit();

    title('SUCESSO');
    echo "Merge finalizado: mantido #{$targetUserId} e removido #{$sourceUserId}.\n";
    echo "Valide no painel e rode o debug novamente para confirmar.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    title('ERRO - ROLLBACK EXECUTADO');
    echo get_class($e) . "\n";
    echo 'Codigo: ' . (string) $e->getCode() . "\n";
    echo 'Mensagem: ' . $e->getMessage() . "\n";
    exit(1);
}
