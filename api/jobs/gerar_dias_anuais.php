<?php

/**
 * Job para gerar dias do próximo ano automaticamente
 * 
 * Este job deve ser executado uma vez por ano (em 01 de janeiro ou próximo ao final do ano)
 * para preencher a tabela de dias com as datas do próximo ano.
 * 
 * Uso:
 * php jobs/gerar_dias_anuais.php
 */

class GerarDiasAnuaisJob
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Executa o job de geração de dias
     */
    public function executar(): bool
    {
        try {
            $dataInicio = new DateTime();
            $dataFim = new DateTime();
            $dataFim->modify('+1 year');

            echo "🕐 Iniciando geração de dias...\n";
            echo "📅 De: " . $dataInicio->format('d/m/Y') . "\n";
            echo "📅 Até: " . $dataFim->format('d/m/Y') . "\n";
            echo "---\n";

            $diasInseridos = 0;
            $diasDuplicados = 0;
            $dataAtual = clone $dataInicio;

            while ($dataAtual <= $dataFim) {
                $data = $dataAtual->format('Y-m-d');

                // Tentar inserir o dia
                try {
                    $stmt = $this->db->prepare(
                        "INSERT INTO dias (data, ativo, created_at, updated_at) 
                         VALUES (:data, 1, NOW(), NOW())"
                    );
                    
                    $stmt->execute(['data' => $data]);
                    $diasInseridos++;

                    // Log a cada 30 dias
                    if ($diasInseridos % 30 === 0) {
                        echo "✓ Inseridos: {$diasInseridos} dias\n";
                    }
                } catch (PDOException $e) {
                    // Ignorar erros de chave duplicada
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $diasDuplicados++;
                    } else {
                        throw $e;
                    }
                }

                $dataAtual->modify('+1 day');
            }

            echo "---\n";
            echo "✅ Job concluído com sucesso!\n";
            echo "📊 Estatísticas:\n";
            echo "   • Dias inseridos: {$diasInseridos}\n";
            echo "   • Dias duplicados (já existentes): {$diasDuplicados}\n";
            echo "   • Total processado: " . ($diasInseridos + $diasDuplicados) . " dias\n";

            return true;
        } catch (Exception $e) {
            echo "❌ Erro ao gerar dias: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Gera dias apenas para um período específico
     */
    public function gerarParaPeriodo(string $dataInicio, string $dataFim): bool
    {
        try {
            $inicio = new DateTime($dataInicio);
            $fim = new DateTime($dataFim);

            echo "🕐 Gerando dias para período específico...\n";
            echo "📅 De: " . $inicio->format('d/m/Y') . "\n";
            echo "📅 Até: " . $fim->format('d/m/Y') . "\n";
            echo "---\n";

            $diasInseridos = 0;
            $diasDuplicados = 0;
            $dataAtual = clone $inicio;

            while ($dataAtual <= $fim) {
                $data = $dataAtual->format('Y-m-d');

                try {
                    $stmt = $this->db->prepare(
                        "INSERT INTO dias (data, ativo, created_at, updated_at) 
                         VALUES (:data, 1, NOW(), NOW())"
                    );
                    
                    $stmt->execute(['data' => $data]);
                    $diasInseridos++;

                    if ($diasInseridos % 30 === 0) {
                        echo "✓ Inseridos: {$diasInseridos} dias\n";
                    }
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $diasDuplicados++;
                    } else {
                        throw $e;
                    }
                }

                $dataAtual->modify('+1 day');
            }

            echo "---\n";
            echo "✅ Período gerado com sucesso!\n";
            echo "📊 Dias inseridos: {$diasInseridos}\n";
            echo "📊 Dias duplicados: {$diasDuplicados}\n";

            return true;
        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Verifica quantos dias estão cadastrados
     */
    public function verificarStatus(): void
    {
        $stmt = $this->db->query(
            "SELECT 
                COUNT(*) as total_dias,
                MIN(data) as primeira_data,
                MAX(data) as ultima_data,
                CURDATE() as data_hoje,
                COUNT(CASE WHEN data >= CURDATE() THEN 1 END) as dias_futuros,
                COUNT(CASE WHEN data < CURDATE() THEN 1 END) as dias_passados
             FROM dias"
        );
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "\n📊 STATUS ATUAL DOS DIAS:\n";
        echo "════════════════════════════════════════\n";
        echo "Total de dias cadastrados: " . $resultado['total_dias'] . "\n";
        echo "Primeira data: " . ($resultado['primeira_data'] ? date('d/m/Y', strtotime($resultado['primeira_data'])) : 'N/A') . "\n";
        echo "Última data: " . ($resultado['ultima_data'] ? date('d/m/Y', strtotime($resultado['ultima_data'])) : 'N/A') . "\n";
        echo "Data hoje: " . date('d/m/Y', strtotime($resultado['data_hoje'])) . "\n";
        echo "---\n";
        echo "Dias futuros: " . $resultado['dias_futuros'] . "\n";
        echo "Dias passados: " . $resultado['dias_passados'] . "\n";
        echo "════════════════════════════════════════\n\n";
    }
}

// Executar o job
if (php_sapi_name() === 'cli') {
    // Conectar ao banco de dados
    $db = require __DIR__ . '/../config/database.php';
    
    // Verificar argumentos
    $opcoes = getopt('h', ['help', 'status', 'periodo:']);

    if (isset($opcoes['h']) || isset($opcoes['help'])) {
        echo "Uso: php jobs/gerar_dias_anuais.php [opções]\n\n";
        echo "Opções:\n";
        echo "  --help              Mostra esta mensagem de ajuda\n";
        echo "  --status            Mostra status atual dos dias cadastrados\n";
        echo "  --periodo=YYYY-MM-DD:YYYY-MM-DD  Gera dias para período específico\n";
        echo "\nExemplos:\n";
        echo "  php jobs/gerar_dias_anuais.php                # Gera dias para o próximo ano\n";
        echo "  php jobs/gerar_dias_anuais.php --status       # Verifica status\n";
        echo "  php jobs/gerar_dias_anuais.php --periodo=2026-01-01:2026-12-31\n";
        exit(0);
    }

    $job = new GerarDiasAnuaisJob($db);

    if (isset($opcoes['status'])) {
        $job->verificarStatus();
    } elseif (isset($opcoes['periodo'])) {
        $periodo = $opcoes['periodo'];
        $datas = explode(':', $periodo);
        
        if (count($datas) !== 2) {
            echo "❌ Formato de período inválido. Use: YYYY-MM-DD:YYYY-MM-DD\n";
            exit(1);
        }

        $job->gerarParaPeriodo($datas[0], $datas[1]);
    } else {
        $job->executar();
    }
}
