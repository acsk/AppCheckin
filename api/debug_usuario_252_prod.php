<?php
/**
 * Debug de atualizacao de usuario em producao (caso usuario #252)
 *
 * Objetivo:
 * - Diagnosticar porque o update do usuario falha
 * - Verificar conflitos de duplicidade (email/cpf/telefone)
 * - Verificar vinculos ativos/inativos em tenant_usuario_papel
 * - Simular update em transacao com rollback para capturar erro real sem alterar dados
 *
 * Uso:
 *   php debug_usuario_252_prod.php
 *   php debug_usuario_252_prod.php --user=252
 *   php debug_usuario_252_prod.php --tenant=3
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$db = require __DIR__ . '/config/database.php';

$args = array_slice($argv ?? [], 1);
$targetUserId = 252;
$tenantFiltro = null;

foreach ($args as $arg) {
    if (preg_match('/--user=(\d+)/', $arg, $m)) {
        $targetUserId = (int) $m[1];
    }
    if (preg_match('/--tenant=(\d+)/', $arg, $m)) {
        $tenantFiltro = (int) $m[1];
    }
}

$payload = [
    'nome' => 'ANNYELLE LUIZA RODRIGUES DA SILVA',
    'email' => 'annyelleluiza8@gmail.com',
    'telefone' => '82982247722',
    'bairro' => 'SANTA EDWIGES',
    'cep' => '57310-285',
    'cidade' => 'ARAPIRACA',
    'complemento' => 'LADO IMPAR',
    'cpf' => '120.450.764-31',
    'estado' => 'AL',
    'logradouro' => 'RUA MARINES NUNES DOS SANTOS',
    'numero' => '62',
    'papel_id' => 1,
];

function hr(string $title): void
{
    echo "\n" . str_repeat('=', 90) . "\n";
    echo $title . "\n";
    echo str_repeat('=', 90) . "\n";
}

function normDigits(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $v = preg_replace('/[^0-9]/', '', $value);
    return $v === '' ? null : $v;
}

function toUpper(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $trim = trim($value);
    if ($trim === '') {
        return null;
    }
    return mb_strtoupper($trim, 'UTF-8');
}

function printRows(array $rows): void
{
    if (!$rows) {
        echo "(sem registros)\n";
        return;
    }

    foreach ($rows as $idx => $row) {
        echo '[' . ($idx + 1) . '] ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}

hr('DEBUG UPDATE USUARIO EM PROD');
echo 'Data/Hora: ' . date('Y-m-d H:i:s') . "\n";
echo 'Usuario alvo: #' . $targetUserId . "\n";
if ($tenantFiltro !== null) {
    echo 'Tenant filtro: #' . $tenantFiltro . "\n";
}

hr('1) USUARIO ALVO (usuarios)');
$stmtUser = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
$stmtUser->execute([$targetUserId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Usuario nao encontrado.\n";
    exit(1);
}

printRows([$user]);

$targetEmail = strtolower((string) ($user['email'] ?? $payload['email']));
$targetCpf = normDigits((string) ($user['cpf'] ?? $payload['cpf']));
$targetTelefone = normDigits((string) ($user['telefone'] ?? $payload['telefone']));

hr('2) VINCULOS DO USUARIO ALVO (tenant_usuario_papel)');
$stmtVinculos = $db->prepare(
    'SELECT tup.id, tup.tenant_id, t.nome AS tenant_nome, tup.usuario_id, tup.papel_id, p.nome AS papel_nome, tup.ativo, tup.created_at, tup.updated_at
     FROM tenant_usuario_papel tup
     LEFT JOIN tenants t ON t.id = tup.tenant_id
     LEFT JOIN papeis p ON p.id = tup.papel_id
     WHERE tup.usuario_id = ?
     ORDER BY tup.ativo DESC, tup.tenant_id ASC'
);
$stmtVinculos->execute([$targetUserId]);
$vinculos = $stmtVinculos->fetchAll(PDO::FETCH_ASSOC);
printRows($vinculos);

$tenantIdsAtivos = [];
foreach ($vinculos as $v) {
    if ((int) ($v['ativo'] ?? 0) === 1) {
        $tenantIdsAtivos[] = (int) $v['tenant_id'];
    }
}
$tenantIdsAtivos = array_values(array_unique($tenantIdsAtivos));
if ($tenantFiltro !== null) {
    $tenantIdsAtivos = [$tenantFiltro];
}

hr('3) DUPLICIDADES POR EMAIL (usuarios + vinculos)');
$stmtDupEmail = $db->prepare(
    'SELECT
        u.id AS usuario_id,
        u.nome,
        u.email,
        u.email_global,
        u.cpf,
        u.telefone,
        u.ativo AS usuario_ativo,
        tup.tenant_id,
        tup.ativo AS vinculo_ativo,
        tup.papel_id,
        p.nome AS papel_nome
     FROM usuarios u
     LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
     LEFT JOIN papeis p ON p.id = tup.papel_id
     WHERE LOWER(u.email) = LOWER(:email)
     ORDER BY u.id ASC, tup.tenant_id ASC'
);
$stmtDupEmail->execute(['email' => $payload['email']]);
$dupEmail = $stmtDupEmail->fetchAll(PDO::FETCH_ASSOC);
printRows($dupEmail);

hr('4) DUPLICIDADES POR CPF (normalizado)');
$stmtDupCpf = $db->prepare(
    "SELECT
        u.id AS usuario_id,
        u.nome,
        u.email,
        u.cpf,
        u.ativo AS usuario_ativo,
        tup.tenant_id,
        tup.ativo AS vinculo_ativo,
        tup.papel_id,
        p.nome AS papel_nome
     FROM usuarios u
     LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
     LEFT JOIN papeis p ON p.id = tup.papel_id
    WHERE REPLACE(REPLACE(REPLACE(u.cpf, '.', ''), '-', ''), ' ', '') = :cpf
    ORDER BY u.id ASC, tup.tenant_id ASC"
);
$stmtDupCpf->execute(['cpf' => normDigits($payload['cpf'])]);
$dupCpf = $stmtDupCpf->fetchAll(PDO::FETCH_ASSOC);
printRows($dupCpf);

hr('5) DUPLICIDADES POR TELEFONE (normalizado)');
$stmtDupFone = $db->prepare(
    "SELECT
        u.id AS usuario_id,
        u.nome,
        u.email,
        u.telefone,
        u.ativo AS usuario_ativo,
        tup.tenant_id,
        tup.ativo AS vinculo_ativo,
        tup.papel_id,
        p.nome AS papel_nome
     FROM usuarios u
     LEFT JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
     LEFT JOIN papeis p ON p.id = tup.papel_id
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') = :telefone
    ORDER BY u.id ASC, tup.tenant_id ASC"
);
$stmtDupFone->execute(['telefone' => normDigits($payload['telefone'])]);
$dupFone = $stmtDupFone->fetchAll(PDO::FETCH_ASSOC);
printRows($dupFone);

hr('6) TESTE EXATO DA REGRA emailExists() POR TENANT');
if (!$tenantIdsAtivos) {
    echo "Usuario alvo nao tem vinculo ativo. Teste por tenant nao sera executado.\n";
} else {
    $stmtEmailExists = $db->prepare(
        'SELECT
            u.id AS usuario_id,
            u.nome,
            u.email,
            tup.tenant_id,
            tup.ativo
         FROM usuarios u
         INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
         WHERE u.email = :email
           AND tup.tenant_id = :tenant_id
           AND tup.ativo = 1
           AND u.id != :id
         ORDER BY u.id ASC'
    );

    foreach ($tenantIdsAtivos as $tenantId) {
        echo "Tenant #{$tenantId}:\n";
        $stmtEmailExists->execute([
            'email' => $payload['email'],
            'tenant_id' => $tenantId,
            'id' => $targetUserId,
        ]);
        $conflicts = $stmtEmailExists->fetchAll(PDO::FETCH_ASSOC);
        if (!$conflicts) {
            echo "  OK - sem conflito para regra emailExists().\n";
        } else {
            echo "  CONFLITO - regra emailExists() encontraria estes registros:\n";
            printRows($conflicts);
        }
    }
}

hr('7) INDICES UNICOS RELEVANTES (usuarios / tenant_usuario_papel / alunos)');
$stmtIndexes = $db->query(
    "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS colunas
     FROM information_schema.statistics
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN ('usuarios', 'tenant_usuario_papel', 'alunos')
     GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE
     ORDER BY TABLE_NAME, NON_UNIQUE ASC, INDEX_NAME ASC"
);
$indexes = $stmtIndexes->fetchAll(PDO::FETCH_ASSOC);
printRows($indexes);

hr('8) SIMULACAO DE UPDATE (COM ROLLBACK)');
$payloadNorm = [
    'nome' => toUpper($payload['nome']),
    'email' => $payload['email'],
    'telefone' => normDigits($payload['telefone']),
    'cpf' => normDigits($payload['cpf']),
    'cep' => normDigits($payload['cep']),
    'logradouro' => toUpper($payload['logradouro']),
    'numero' => $payload['numero'],
    'complemento' => toUpper($payload['complemento']),
    'bairro' => toUpper($payload['bairro']),
    'cidade' => toUpper($payload['cidade']),
    'estado' => toUpper($payload['estado']),
];

printRows([$payloadNorm]);

try {
    $db->beginTransaction();

    $sqlUpdateUsuario = "UPDATE usuarios SET
        nome = :nome,
        email = :email,
        telefone = :telefone,
        cpf = :cpf,
        cep = :cep,
        logradouro = :logradouro,
        numero = :numero,
        complemento = :complemento,
        bairro = :bairro,
        cidade = :cidade,
        estado = :estado
        WHERE id = :id";

    $stmtUpd = $db->prepare($sqlUpdateUsuario);
    $okUpd = $stmtUpd->execute([
        'nome' => $payloadNorm['nome'],
        'email' => $payloadNorm['email'],
        'telefone' => $payloadNorm['telefone'],
        'cpf' => $payloadNorm['cpf'],
        'cep' => $payloadNorm['cep'],
        'logradouro' => $payloadNorm['logradouro'],
        'numero' => $payloadNorm['numero'],
        'complemento' => $payloadNorm['complemento'],
        'bairro' => $payloadNorm['bairro'],
        'cidade' => $payloadNorm['cidade'],
        'estado' => $payloadNorm['estado'],
        'id' => $targetUserId,
    ]);

    echo 'UPDATE usuarios executado: ' . ($okUpd ? 'SIM' : 'NAO') . "\n";
    echo 'Linhas afetadas (usuarios): ' . $stmtUpd->rowCount() . "\n";

    $sqlUpdateAluno = "UPDATE alunos SET
        nome = :nome,
        telefone = :telefone,
        cpf = :cpf,
        cep = :cep,
        logradouro = :logradouro,
        numero = :numero,
        complemento = :complemento,
        bairro = :bairro,
        cidade = :cidade,
        estado = :estado,
        updated_at = CURRENT_TIMESTAMP
        WHERE usuario_id = :usuario_id";

    $stmtAluno = $db->prepare($sqlUpdateAluno);
    $okAluno = $stmtAluno->execute([
        'nome' => $payloadNorm['nome'],
        'telefone' => $payloadNorm['telefone'],
        'cpf' => $payloadNorm['cpf'],
        'cep' => $payloadNorm['cep'],
        'logradouro' => $payloadNorm['logradouro'],
        'numero' => $payloadNorm['numero'],
        'complemento' => $payloadNorm['complemento'],
        'bairro' => $payloadNorm['bairro'],
        'cidade' => $payloadNorm['cidade'],
        'estado' => $payloadNorm['estado'],
        'usuario_id' => $targetUserId,
    ]);

    echo 'UPDATE alunos executado: ' . ($okAluno ? 'SIM' : 'NAO') . "\n";
    echo 'Linhas afetadas (alunos): ' . $stmtAluno->rowCount() . "\n";

    $db->rollBack();
    echo "Simulacao concluida com ROLLBACK (nenhum dado foi alterado).\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "ERRO NA SIMULACAO: " . get_class($e) . "\n";
    echo "Codigo: " . (string) $e->getCode() . "\n";
    echo "Mensagem: " . $e->getMessage() . "\n";
}

hr('9) DIAGNOSTICO SINTETICO');

$confEmailAtivoMesmoTenant = 0;
if ($tenantIdsAtivos) {
    $stmtCountConflito = $db->prepare(
        'SELECT COUNT(*)
         FROM usuarios u
         INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id
         WHERE u.email = :email
           AND tup.tenant_id = :tenant_id
           AND tup.ativo = 1
           AND u.id != :id'
    );

    foreach ($tenantIdsAtivos as $tenantId) {
        $stmtCountConflito->execute([
            'email' => $payload['email'],
            'tenant_id' => $tenantId,
            'id' => $targetUserId,
        ]);
        $confEmailAtivoMesmoTenant += (int) $stmtCountConflito->fetchColumn();
    }
}

echo '- conflito emailExists no(s) tenant(s) ativo(s): ' . $confEmailAtivoMesmoTenant . "\n";
echo '- registros com mesmo email: ' . count($dupEmail) . "\n";
echo '- registros com mesmo cpf: ' . count($dupCpf) . "\n";
echo '- registros com mesmo telefone: ' . count($dupFone) . "\n";

echo "\nFim do debug.\n";
