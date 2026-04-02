<?php
$db = require __DIR__ . '/../config/database.php';

// Dados da assinatura
$rows = $db->query("
    SELECT a.id as ass_id, a.matricula_id, a.data_inicio, a.dia_cobranca, a.proxima_cobranca, 
           a.status_id, a.data_fim,
           m.data_inicio as mat_inicio, m.data_vencimento as mat_venc, m.proxima_data_vencimento as mat_prox
    FROM assinaturas a
    INNER JOIN matriculas m ON m.id = a.matricula_id
    WHERE a.matricula_id = 266
")->fetchAll(PDO::FETCH_ASSOC);

echo "=== Assinatura da matrícula 266 ===\n";
print_r($rows);

// Simular o cálculo do script
if ($rows) {
    $ass = $rows[0];
    $dia = (int) $ass['dia_cobranca'];
    $hoje = new DateTime();
    echo "\nHoje: " . $hoje->format('Y-m-d') . "\n";
    echo "dia_cobranca: {$dia}\n";
    echo "data_inicio assinatura: {$ass['data_inicio']}\n";
    echo "data_fim assinatura: {$ass['data_fim']}\n";
    echo "proxima_cobranca assinatura: {$ass['proxima_cobranca']}\n";
    echo "status: {$ass['status_id']}\n\n";

    // Cálculo atual do script
    $mesAtual = (int) $hoje->format('m');
    $anoAtual = (int) $hoje->format('Y');
    $ultimoDiaMes = (int) (new DateTime("$anoAtual-$mesAtual-01"))->format('t');
    $diaReal = min($dia, $ultimoDiaMes);
    $proxData = new DateTime("$anoAtual-$mesAtual-$diaReal");
    if ($proxData <= $hoje) {
        $proxData->modify('+1 month');
        $ultimoDiaProxMes = (int) $proxData->format('t');
        $proxData->setDate((int) $proxData->format('Y'), (int) $proxData->format('m'), min($dia, $ultimoDiaProxMes));
    }
    echo "Script calcularia prox_venc: " . $proxData->format('Y-m-d') . "\n";
    echo "Mat atual prox_venc: {$ass['mat_prox']}\n";
    echo "Mat atual inicio: {$ass['mat_inicio']}\n";
    echo "Mat atual acesso_ate: {$ass['mat_venc']}\n";
    echo "Ass data_inicio: {$ass['data_inicio']}\n";
    
    // comparações
    echo "\n--- Comparações ---\n";
    echo "ass_inicio ({$ass['data_inicio']}) === mat_inicio ({$ass['mat_inicio']})? " . ($ass['data_inicio'] === $ass['mat_inicio'] ? 'SIM' : 'NÃO') . "\n";
    echo "proxCalc (" . $proxData->format('Y-m-d') . ") === mat_venc ({$ass['mat_venc']})? " . ($proxData->format('Y-m-d') === $ass['mat_venc'] ? 'SIM' : 'NÃO') . "\n";
    echo "proxCalc (" . $proxData->format('Y-m-d') . ") === mat_prox ({$ass['mat_prox']})? " . ($proxData->format('Y-m-d') === $ass['mat_prox'] ? 'SIM' : 'NÃO') . "\n";
}
