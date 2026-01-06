<?php

namespace App\Models;

use PDO;

class Tenant
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Buscar tenant por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tenants WHERE id = :id AND ativo = 1"
        );
        $stmt->execute(['id' => $id]);
        $tenant = $stmt->fetch();
        
        return $tenant ?: null;
    }

    /**
     * Buscar tenant por slug
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tenants WHERE slug = :slug AND ativo = 1"
        );
        $stmt->execute(['slug' => $slug]);
        $tenant = $stmt->fetch();
        
        return $tenant ?: null;
    }

    /**
     * Listar todos os tenants com filtros opcionais
     */
    public function getAll(array $filtros = []): array
    {
        $sql = "SELECT * FROM tenants WHERE id != 1";
        $params = [];
        
        // Filtro por busca (nome, email ou CNPJ)
        if (!empty($filtros['busca'])) {
            $sql .= " AND (nome LIKE :busca1 OR email LIKE :busca2 OR cnpj LIKE :busca3)";
            $buscaValue = '%' . $filtros['busca'] . '%';
            $params['busca1'] = $buscaValue;
            $params['busca2'] = $buscaValue;
            $params['busca3'] = $buscaValue;
        }
        
        // Filtro por status ativo
        if (isset($filtros['ativo'])) {
            $sql .= " AND ativo = :ativo";
            $params['ativo'] = $filtros['ativo'] ? 1 : 0;
        }
        
        $sql .= " ORDER BY nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Criar novo tenant
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO tenants (nome, slug, email, cnpj, telefone, 
                                responsavel_nome, responsavel_cpf, responsavel_telefone, responsavel_email,
                                endereco, cep, logradouro, numero, complemento, bairro, cidade, estado, ativo) 
             VALUES (:nome, :slug, :email, :cnpj, :telefone, 
                     :responsavel_nome, :responsavel_cpf, :responsavel_telefone, :responsavel_email,
                     :endereco, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, :ativo)"
        );
        
        $stmt->execute([
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'email' => $data['email'],
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => $data['responsavel_cpf'] ?? null,
            'responsavel_telefone' => $data['responsavel_telefone'] ?? null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'ativo' => $data['ativo'] ?? true
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar tenant
     */
    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tenants 
             SET nome = :nome, slug = :slug, email = :email, cnpj = :cnpj,
                 telefone = :telefone, 
                 responsavel_nome = :responsavel_nome, responsavel_cpf = :responsavel_cpf, 
                 responsavel_telefone = :responsavel_telefone, responsavel_email = :responsavel_email,
                 endereco = :endereco, 
                 cep = :cep, logradouro = :logradouro, numero = :numero,
                 complemento = :complemento, bairro = :bairro, cidade = :cidade, estado = :estado,
                 ativo = :ativo
             WHERE id = :id"
        );
        
        return $stmt->execute([
            'id' => $id,
            'nome' => $data['nome'],
            'slug' => $data['slug'],
            'email' => $data['email'],
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'responsavel_nome' => $data['responsavel_nome'] ?? null,
            'responsavel_cpf' => $data['responsavel_cpf'] ?? null,
            'responsavel_telefone' => $data['responsavel_telefone'] ?? null,
            'responsavel_email' => $data['responsavel_email'] ?? null,
            'endereco' => $data['endereco'] ?? null,
            'cep' => $data['cep'] ?? null,
            'logradouro' => $data['logradouro'] ?? null,
            'numero' => $data['numero'] ?? null,
            'complemento' => $data['complemento'] ?? null,
            'bairro' => $data['bairro'] ?? null,
            'cidade' => $data['cidade'] ?? null,
            'estado' => $data['estado'] ?? null,
            'ativo' => $data['ativo'] ?? true
        ]);
    }

    /**
     * Deletar tenant (soft delete)
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tenants SET ativo = 0 WHERE id = :id"
        );
        
        return $stmt->execute(['id' => $id]);
    }
}
