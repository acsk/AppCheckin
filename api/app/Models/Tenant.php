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
     * Buscar tenant por CNPJ
     */
    public function findByCnpj(string $cnpj): ?array
    {
        $cnpjLimpo = preg_replace('/[^0-9]/', '', $cnpj);
        if (empty($cnpjLimpo)) {
            return null;
        }
        
        $stmt = $this->db->prepare(
            "SELECT * FROM tenants WHERE cnpj = :cnpj"
        );
        $stmt->execute(['cnpj' => $cnpjLimpo]);
        $tenant = $stmt->fetch();
        
        return $tenant ?: null;
    }

    /**
     * Buscar tenant por email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM tenants WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        $tenant = $stmt->fetch();
        
        return $tenant ?: null;
    }

    /**
     * Criar novo tenant
     */
    public function create(array $data): int
    {
        // Limpar CNPJ
        $cnpj = isset($data['cnpj']) ? preg_replace('/[^0-9]/', '', $data['cnpj']) : null;
        
        // Se CNPJ estiver vazio, usar NULL para evitar duplicidade de strings vazias
        if (empty($cnpj)) {
            $cnpj = null;
        }
        
        try {
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
                'cnpj' => $cnpj,
                'telefone' => $data['telefone'] ?? null,
                'responsavel_nome' => $data['responsavel_nome'] ?? null,
                'responsavel_cpf' => isset($data['responsavel_cpf']) ? preg_replace('/[^0-9]/', '', $data['responsavel_cpf']) : null,
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

            $tenantId = (int) $this->db->lastInsertId();

            // Inicializar configurações padrão de formas de pagamento
            $this->inicializarFormasPagamento($tenantId);

            return $tenantId;
            
        } catch (\PDOException $e) {
            // Tratar erros de duplicidade
            if ($e->getCode() == '23000') {
                if (strpos($e->getMessage(), 'unique_tenant_cnpj') !== false) {
                    throw new \Exception('CNPJ já cadastrado para outra academia', 409);
                }
                if (strpos($e->getMessage(), 'unique_tenant_email') !== false || strpos($e->getMessage(), 'email') !== false) {
                    throw new \Exception('Email já cadastrado para outra academia', 409);
                }
                if (strpos($e->getMessage(), 'slug') !== false) {
                    throw new \Exception('Slug já existe. Tente um nome diferente.', 409);
                }
                throw new \Exception('Dados duplicados: ' . $e->getMessage(), 409);
            }
            throw $e;
        }
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

    /**
     * Inicializar configurações padrão de formas de pagamento para o tenant
     */
    public function inicializarFormasPagamento(int $tenantId): void
    {
        // Verificar se já existem configurações
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total FROM tenant_formas_pagamento WHERE tenant_id = :tenant_id"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['total'] > 0) {
            return; // Já tem configurações, não precisa criar
        }

        // Criar configurações padrão baseadas nas formas de pagamento ativas
        $sql = "INSERT INTO tenant_formas_pagamento 
                (tenant_id, forma_pagamento_id, ativo, taxa_percentual, taxa_fixa, 
                 aceita_parcelamento, parcelas_minimas, parcelas_maximas, juros_parcelamento, 
                 parcelas_sem_juros, dias_compensacao, valor_minimo)
                SELECT 
                    :tenant_id,
                    id, 
                    0, 
                    percentual_desconto, 
                    0.00, 
                    0, 
                    1, 
                    12, 
                    0.00, 
                    1, 
                    0, 
                    0.00 
                FROM formas_pagamento 
                WHERE ativo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
    }

    /**
     * Verificar se tenant tem usuário Admin ativo
     */
    public function verificarUsuarioAdmin(int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total 
             FROM usuarios u
             INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = u.id AND tup.ativo = 1
             WHERE tup.tenant_id = :tenant_id 
             AND tup.papel_id IN (2, 3)
             AND u.ativo = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result['total'] > 0;
    }

    /**
     * Verificar se tenant tem contrato ativo
     */
    public function verificarContratoAtivo(int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total 
             FROM tenant_planos_sistema 
             WHERE tenant_id = :tenant_id 
             AND status_id = 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result['total'] > 0;
    }
}
