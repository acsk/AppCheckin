<?php

namespace App\Models;

use PDO;

class TenantFormaPagamento
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar formas de pagamento configuradas do tenant
     */
    public function listar(int $tenantId, bool $apenasAtivas = false): array
    {
        $sql = "
            SELECT 
                tfp.*,
                fp.nome as forma_pagamento_nome,
                fp.descricao as forma_pagamento_descricao
            FROM tenant_formas_pagamento tfp
            INNER JOIN formas_pagamento fp ON tfp.forma_pagamento_id = fp.id
            WHERE tfp.tenant_id = :tenant_id
        ";

        if ($apenasAtivas) {
            $sql .= " AND tfp.ativo = 1 AND fp.ativo = 1";
        }

        $sql .= " ORDER BY fp.nome";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar configuração específica
     */
    public function buscar(int $id, int $tenantId): ?array
    {
        $sql = "
            SELECT tfp.*, fp.nome as forma_pagamento_nome
            FROM tenant_formas_pagamento tfp
            INNER JOIN formas_pagamento fp ON tfp.forma_pagamento_id = fp.id
            WHERE tfp.id = :id AND tfp.tenant_id = :tenant_id
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Calcular valor líquido após taxas (sem parcelamento)
     */
    public function calcularValorLiquido(int $tenantId, int $formaPagamentoId, float $valorBruto): array
    {
        $sql = "
            SELECT taxa_percentual, taxa_fixa 
            FROM tenant_formas_pagamento
            WHERE tenant_id = :tenant_id 
            AND forma_pagamento_id = :forma_pagamento_id
            AND ativo = 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':forma_pagamento_id', $formaPagamentoId, PDO::PARAM_INT);
        $stmt->execute();
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return [
                'valor_bruto' => $valorBruto,
                'taxa_percentual' => 0.00,
                'taxa_fixa' => 0.00,
                'valor_taxas' => 0.00,
                'valor_liquido' => $valorBruto
            ];
        }

        $taxaPercentual = ($valorBruto * $config['taxa_percentual']) / 100;
        $taxaFixa = (float) $config['taxa_fixa'];
        $valorTaxas = $taxaPercentual + $taxaFixa;
        $valorLiquido = $valorBruto - $valorTaxas;

        return [
            'valor_bruto' => number_format($valorBruto, 2, '.', ''),
            'taxa_percentual' => number_format($taxaPercentual, 2, '.', ''),
            'taxa_fixa' => number_format($taxaFixa, 2, '.', ''),
            'valor_taxas' => number_format($valorTaxas, 2, '.', ''),
            'valor_liquido' => number_format($valorLiquido, 2, '.', '')
        ];
    }

    /**
     * Calcular parcelas com juros
     */
    public function calcularParcelas(int $tenantId, int $formaPagamentoId, float $valorTotal, int $numeroParcelas = 1): array
    {
        $sql = "
            SELECT * FROM tenant_formas_pagamento
            WHERE tenant_id = :tenant_id 
            AND forma_pagamento_id = :forma_pagamento_id
            AND ativo = 1
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':forma_pagamento_id', $formaPagamentoId, PDO::PARAM_INT);
        $stmt->execute();
        
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return ['erro' => 'Forma de pagamento não configurada'];
        }

        // Verificar valor mínimo
        if ($valorTotal < $config['valor_minimo']) {
            return [
                'erro' => "Valor mínimo para esta forma de pagamento é R$ " . number_format($config['valor_minimo'], 2, ',', '.')
            ];
        }

        // Verificar se aceita parcelamento
        if (!$config['aceita_parcelamento'] && $numeroParcelas > 1) {
            return ['erro' => 'Esta forma de pagamento não aceita parcelamento'];
        }

        // Validar número de parcelas
        if ($numeroParcelas < $config['parcelas_minimas'] || $numeroParcelas > $config['parcelas_maximas']) {
            return [
                'erro' => "Número de parcelas deve estar entre {$config['parcelas_minimas']} e {$config['parcelas_maximas']}"
            ];
        }

        // Calcular taxas da operadora
        $taxaPercentual = ($valorTotal * $config['taxa_percentual']) / 100;
        $taxaFixa = (float) $config['taxa_fixa'];
        $valorComTaxas = $valorTotal + $taxaPercentual + $taxaFixa;

        // Calcular juros do parcelamento
        $aplicarJuros = $numeroParcelas > $config['parcelas_sem_juros'];
        $valorFinal = $valorComTaxas;
        $valorTotalJuros = 0;
        
        if ($aplicarJuros) {
            // Cálculo de juros compostos
            $taxaJuros = $config['juros_parcelamento'] / 100;
            $parcelasComJuros = $numeroParcelas - $config['parcelas_sem_juros'];
            $valorFinal = $valorComTaxas * pow(1 + $taxaJuros, $parcelasComJuros);
            $valorTotalJuros = $valorFinal - $valorComTaxas;
        }

        $valorParcela = $valorFinal / $numeroParcelas;

        return [
            'valor_original' => number_format($valorTotal, 2, '.', ''),
            'numero_parcelas' => $numeroParcelas,
            'parcelas_sem_juros' => (int) $config['parcelas_sem_juros'],
            'aplica_juros' => $aplicarJuros,
            'juros_percentual' => (float) $config['juros_parcelamento'],
            'taxa_operadora_percentual' => (float) $config['taxa_percentual'],
            'taxa_operadora_fixa' => number_format($config['taxa_fixa'], 2, '.', ''),
            'valor_total_taxas' => number_format($taxaPercentual + $taxaFixa, 2, '.', ''),
            'valor_total_juros' => number_format($valorTotalJuros, 2, '.', ''),
            'valor_final_total' => number_format($valorFinal, 2, '.', ''),
            'valor_por_parcela' => number_format($valorParcela, 2, '.', ''),
            'descricao_parcelamento' => $this->gerarDescricaoParcelamento(
                $numeroParcelas, 
                $valorParcela, 
                (int) $config['parcelas_sem_juros'],
                $aplicarJuros
            )
        ];
    }

    /**
     * Gerar descrição amigável do parcelamento
     */
    private function gerarDescricaoParcelamento(int $parcelas, float $valorParcela, int $parcelasSemJuros, bool $aplicaJuros): string
    {
        $descricao = "{$parcelas}x de R$ " . number_format($valorParcela, 2, ',', '.');
        
        if ($aplicaJuros) {
            $descricao .= " com juros";
        } else if ($parcelas <= $parcelasSemJuros) {
            $descricao .= " sem juros";
        }
        
        return $descricao;
    }

    /**
     * Atualizar configuração
     */
    public function atualizar(int $id, int $tenantId, array $dados): bool
    {
        $sql = "
            UPDATE tenant_formas_pagamento SET
                ativo = :ativo,
                taxa_percentual = :taxa_percentual,
                taxa_fixa = :taxa_fixa,
                aceita_parcelamento = :aceita_parcelamento,
                parcelas_minimas = :parcelas_minimas,
                parcelas_maximas = :parcelas_maximas,
                juros_parcelamento = :juros_parcelamento,
                parcelas_sem_juros = :parcelas_sem_juros,
                dias_compensacao = :dias_compensacao,
                valor_minimo = :valor_minimo,
                observacoes = :observacoes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id AND tenant_id = :tenant_id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindParam(':ativo', $dados['ativo'], PDO::PARAM_INT);
        $stmt->bindParam(':taxa_percentual', $dados['taxa_percentual']);
        $stmt->bindParam(':taxa_fixa', $dados['taxa_fixa']);
        $stmt->bindParam(':aceita_parcelamento', $dados['aceita_parcelamento'], PDO::PARAM_INT);
        $stmt->bindParam(':parcelas_minimas', $dados['parcelas_minimas'], PDO::PARAM_INT);
        $stmt->bindParam(':parcelas_maximas', $dados['parcelas_maximas'], PDO::PARAM_INT);
        $stmt->bindParam(':juros_parcelamento', $dados['juros_parcelamento']);
        $stmt->bindParam(':parcelas_sem_juros', $dados['parcelas_sem_juros'], PDO::PARAM_INT);
        $stmt->bindParam(':dias_compensacao', $dados['dias_compensacao'], PDO::PARAM_INT);
        $stmt->bindParam(':valor_minimo', $dados['valor_minimo']);
        $stmt->bindParam(':observacoes', $dados['observacoes']);
        
        return $stmt->execute();
    }
}
