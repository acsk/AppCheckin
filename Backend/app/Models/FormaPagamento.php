<?php

namespace App\Models;

use PDO;

class FormaPagamento
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar todas as formas de pagamento ativas para o tenant
     */
    public function listarTodas(int $tenantId = null): array
    {
        if ($tenantId) {
            // Listar formas de pagamento ativas para o tenant específico
            $sql = "SELECT fp.id, fp.nome, fp.descricao, fp.percentual_desconto
                    FROM formas_pagamento fp
                    INNER JOIN tenant_formas_pagamento tfp ON fp.id = tfp.forma_pagamento_id
                    WHERE tfp.tenant_id = :tenant_id 
                    AND fp.ativo = 1 
                    AND tfp.ativo = 1
                    ORDER BY fp.nome";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['tenant_id' => $tenantId]);
        } else {
            // Fallback: listar formas globais ativas
            $sql = "SELECT id, nome, descricao, percentual_desconto
                    FROM formas_pagamento 
                    WHERE ativo = 1
                    ORDER BY nome";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar forma de pagamento por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM formas_pagamento WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
