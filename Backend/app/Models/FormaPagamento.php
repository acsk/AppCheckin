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
     * Listar todas as formas de pagamento ativas
     */
    public function listarTodas(): array
    {
        $sql = "SELECT id, nome, descricao, percentual_desconto
                FROM formas_pagamento 
                WHERE ativo = 1
                ORDER BY nome";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
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
