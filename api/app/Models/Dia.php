<?php

namespace App\Models;

use PDO;

class Dia
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAtivos(): array
    {
        $sql = "SELECT * FROM dias WHERE ativo = 1 AND data >= CURDATE() ORDER BY data ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM dias WHERE id = :id";
        $params = ['id' => $id];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $dia = $stmt->fetch();
        
        return $dia ?: null;
    }

    /**
     * Retorna dias ao redor de uma data específica
     * 
     * @param string|null $dataReferencia Data de referência (formato Y-m-d). Se null, usa data atual
     * @param int $diasAntes Quantidade de dias antes da referência
     * @param int $diasDepois Quantidade de dias depois da referência
     * @param int|null $tenantId ID do tenant
     * @return array
     */
    public function getDiasAoRedor(?string $dataReferencia = null, int $diasAntes = 2, int $diasDepois = 2, ?int $tenantId = null): array
    {
        if (!$dataReferencia) {
            $dataReferencia = date('Y-m-d');
        }

        // MySQL não aceita parâmetros de binding em INTERVAL, então usamos concatenação segura
        $sql = "SELECT * FROM dias 
                WHERE ativo = 1 
                AND data >= DATE_SUB(?, INTERVAL " . (int)$diasAntes . " DAY)
                AND data <= DATE_ADD(?, INTERVAL " . (int)$diasDepois . " DAY)
                ORDER BY data ASC";
        
        $params = [$dataReferencia, $dataReferencia];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Busca um dia específico pela data
     */
    public function findByData(string $data): ?array
    {
        $sql = "SELECT * FROM dias WHERE data = :data";
        $params = ['data' => $data];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $dia = $stmt->fetch();
        
        return $dia ?: null;
    }
}
