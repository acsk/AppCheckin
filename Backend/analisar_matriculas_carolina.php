<?php
// Verificar estado das matrículas de Carolina

$db = require __DIR__ . '/config/database.php';

$email = 'carolina.ferreira@tenant4.com';

echo "=== ANÁLISE DE MATRÍCULAS ===\n\n";

// 1. Buscar usuário
$sqlUsuario = "SELECT id, nome FROM usuarios WHERE email = :email LIMIT 1";
$stmtUsuario = $db->prepare($sqlUsuario);
$stmtUsuario->execute(['email' => $email]);
$usuario = $stmtUsuario->fetch(\PDO::FETCH_ASSOC);

if (!$usuario) {
    echo "❌ Usuário não encontrado: $email\n";
    exit;
}

$usuarioId = $usuario['id'];
echo "✅ Usuário encontrado: {$usuario['nome']} (ID: {$usuarioId})\n\n";

// 2. Buscar todas as matrículas
$sqlMatriculas = "
    SELECT m.id, m.usuario_id, m.plano_id, m.status,
           p.nome as plano_nome, p.modalidade_id,
           mo.nome as modalidade_nome,
           p.frequencia, p.preco,
           m.created_at, m.updated_at
    FROM matriculas m
    INNER JOIN planos p ON m.plano_id = p.id
    INNER JOIN modalidades mo ON p.modalidade_id = mo.id
    WHERE m.usuario_id = :usuario_id
    ORDER BY m.created_at DESC
";

$stmtMatriculas = $db->prepare($sqlMatriculas);
$stmtMatriculas->execute(['usuario_id' => $usuarioId]);
$matriculas = $stmtMatriculas->fetchAll(\PDO::FETCH_ASSOC);

echo "Total de matrículas: " . count($matriculas) . "\n\n";

foreach ($matriculas as $m) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "ID Matrícula: {$m['id']}\n";
    echo "Plano: {$m['plano_nome']} ({$m['frequencia']}x)\n";
    echo "Modalidade: {$m['modalidade_nome']}\n";
    echo "Preço: R\$ {$m['preco']}\n";
    echo "Status: {$m['status']}\n";
    echo "Criada: {$m['created_at']}\n";
    echo "Atualizada: {$m['updated_at']}\n";
    
    // Buscar pagamentos desta matrícula
    $sqlPagamentos = "
        SELECT pp.id, pp.matricula_id, pp.data_vencimento, pp.data_pagamento, 
               sp.nome as status_pagamento
        FROM pagamentos_plano pp
        INNER JOIN status_pagamento sp ON pp.status_pagamento_id = sp.id
        WHERE pp.matricula_id = :matricula_id
        ORDER BY pp.data_vencimento ASC
    ";
    
    $stmtPagamentos = $db->prepare($sqlPagamentos);
    $stmtPagamentos->execute(['matricula_id' => $m['id']]);
    $pagamentos = $stmtPagamentos->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "\nPagamentos:\n";
    if (empty($pagamentos)) {
        echo "  (nenhum)\n";
    } else {
        foreach ($pagamentos as $p) {
            $dataVenc = new DateTime($p['data_vencimento']);
            $hoje = new DateTime(date('Y-m-d'));
            $dias = $dataVenc->diff($hoje)->days;
            $status = $dataVenc < $hoje ? "⚠️ VENCIDO" : "✓ OK";
            
            echo "  - Vencimento: {$p['data_vencimento']} $status";
            if ($p['data_pagamento']) {
                echo " | Pago em: {$p['data_pagamento']}";
            } else {
                echo " | Status: {$p['status_pagamento']}";
            }
            echo "\n";
        }
    }
    echo "\n";
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\nHoje é: " . date('Y-m-d') . " (11 de janeiro de 2026)\n";
echo "\nRECOMENDAÇÕES:\n";
echo "1. Matrículas com data de vencimento ANTES de hoje deveriam ser CANCELADAS\n";
echo "2. Manter apenas a matrícula que está vigente HOJE (11/01/2026)\n";
echo "3. Se houver múltiplas matrículas vigentes, manter a mais nova\n";
