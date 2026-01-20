<?php

/**
 * Job: Baixar Pagamentos com Valor Zero
 * 
 * DESCRIÇÃO:
 * Quando um contrato é criado, uma primeira fatura é gerada com o valor da aquisição.
 * Quando esse pagamento é baixado, uma próxima fatura é gerada para a cobrança mensal.
 * 
 * Porém, alguns pagamentos podem ser criados com valor 0 (por ajuste, bônus, etc).
 * Este job roda mensalmente e baixa automaticamente todos os pagamentos com valor 0
 * que ainda estão com status "Aguardando".
 * 
 * FREQUÊNCIA: Mensal (1º dia do mês às 03:00 AM)
 * 
 * CRONTAB:
 * 0 3 1 * * /usr/bin/php /caminho/api/jobs/BaixarPagamentosValorZero.php >> /var/log/appcheckin/jobs.log 2>&1
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Models\PagamentoContrato;

try {
    // Conectar ao banco de dados
    $db = require __DIR__ . '/../config/database.php';
    
    $pagamentoModel = new PagamentoContrato($db);
    
    // Log inicial
    $inicio = date('Y-m-d H:i:s');
    $logMsg = "\n" . str_repeat('=', 60) . "\n";
    $logMsg .= "🔄 INICIANDO JOB: Baixar Pagamentos com Valor Zero\n";
    $logMsg .= "Data/Hora: $inicio\n";
    $logMsg .= str_repeat('=', 60) . "\n\n";
    echo $logMsg;
    
    // 1. Buscar pagamentos com valor 0
    $pagamentosZero = $pagamentoModel->listarPagamentosComValorZero(1000);
    
    echo "📋 Pagamentos encontrados com valor 0: " . count($pagamentosZero) . "\n\n";
    
    if (empty($pagamentosZero)) {
        echo "✅ Nenhum pagamento com valor 0 encontrado.\n";
        echo "   Job finalizado com sucesso.\n";
        exit(0);
    }
    
    // 2. Agrupar por tenant para relatório
    $porTenant = [];
    foreach ($pagamentosZero as $pag) {
        $tenantId = $pag['tenant_id'];
        if (!isset($porTenant[$tenantId])) {
            $porTenant[$tenantId] = [
                'nome' => $pag['tenant_nome'],
                'pagamentos' => []
            ];
        }
        $porTenant[$tenantId]['pagamentos'][] = $pag;
    }
    
    // 3. Exibir resumo por tenant
    echo "📊 RESUMO POR ACADEMIA:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($porTenant as $tenantId => $dados) {
        echo sprintf("  • %s (ID: %d): %d pagamento(s)\n", 
            $dados['nome'], 
            $tenantId, 
            count($dados['pagamentos'])
        );
    }
    echo "\n";
    
    // 4. Baixar pagamentos
    echo "💰 PROCESSANDO BAIXAS:\n";
    echo str_repeat('-', 60) . "\n";
    
    $pagamentoIds = array_column($pagamentosZero, 'id');
    
    try {
        $resultado = $pagamentoModel->baixarPagamentosBatch($pagamentoIds);
        
        echo sprintf("✅ Pagamentos baixados com sucesso: %d\n", $resultado['sucesso']);
        echo sprintf("❌ Erros ao processar: %d\n", $resultado['erro']);
        echo sprintf("📈 Total processado: %d\n", $resultado['total']);
        
        // 5. Buscar próximos pagamentos gerados
        $proximosPagamentos = [];
        if ($resultado['sucesso'] > 0) {
            $sqlProximos = "SELECT pc.id, pc.tenant_plano_id, pc.valor, pc.data_vencimento,
                                   t.nome as tenant_nome
                            FROM pagamentos_contrato pc
                            INNER JOIN tenant_planos_sistema tp ON pc.tenant_plano_id = tp.id
                            INNER JOIN tenants t ON tp.tenant_id = t.id
                            WHERE pc.observacoes LIKE 'Gerado automaticamente após pagamento%'
                            AND pc.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                            ORDER BY pc.created_at DESC";
            
            $stmt = $pdo->prepare($sqlProximos);
            $stmt->execute();
            $proximosPagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (!empty($proximosPagamentos)) {
            echo "\n📋 PRÓXIMOS PAGAMENTOS GERADOS:\n";
            echo str_repeat('-', 60) . "\n";
            foreach ($proximosPagamentos as $prox) {
                echo sprintf("  • Pagamento ID: %d\n", $prox['id']);
                echo sprintf("    Academia: %s\n", $prox['tenant_nome']);
                echo sprintf("    Valor: R$ %.2f\n", $prox['valor']);
                echo sprintf("    Data Vencimento: %s\n", $prox['data_vencimento']);
                echo "\n";
            }
            echo sprintf("✨ Total de próximos pagamentos gerados: %d\n\n", count($proximosPagamentos));
        }
        
        // 6. Detalhes de cada pagamento baixado
        if ($resultado['sucesso'] > 0) {
            echo "📝 DETALHES DOS PAGAMENTOS BAIXADOS:\n";
            echo str_repeat('-', 60) . "\n";
            
            foreach ($pagamentosZero as $pag) {
                echo sprintf("  • Pagamento ID: %d\n", $pag['id']);
                echo sprintf("    Academia: %s\n", $pag['tenant_nome']);
                echo sprintf("    Contrato: %d\n", $pag['contrato_id']);
                echo sprintf("    Valor: R$ %.2f\n", $pag['valor']);
                echo sprintf("    Data Vencimento: %s\n", $pag['data_vencimento']);
                echo "\n";
            }
        }
        
        // 6. Conclusão
        $fim = date('Y-m-d H:i:s');
        $logMsg = str_repeat('=', 60) . "\n";
        $logMsg .= "✅ JOB FINALIZADO COM SUCESSO!\n";
        $logMsg .= "Fim: $fim\n";
        $logMsg .= str_repeat('=', 60) . "\n";
        echo $logMsg;
        
        exit(0);
        
    } catch (\Exception $e) {
        echo "❌ ERRO ao processar pagamentos:\n";
        echo "   Mensagem: " . $e->getMessage() . "\n";
        echo "   Arquivo: " . $e->getFile() . "\n";
        echo "   Linha: " . $e->getLine() . "\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "\n❌ ERRO CRÍTICO NO JOB:\n";
    echo "   Mensagem: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . "\n";
    echo "   Linha: " . $e->getLine() . "\n";
    exit(1);
}
?>
