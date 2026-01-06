<?php

namespace App\Models;

use PDO;

/**
 * Model para Planos do Sistema
 * Representa os planos de assinatura que as academias (tenants) contratam
 * Diferente de Plano.php que são os planos dos alunos dentro de cada academia
 */
class PlanoSistema
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Listar todos os planos do sistema
     */
    public function listarTodos(bool $apenasAtivos = false): array
    {
        $sql = "SELECT * FROM planos_sistema WHERE 1=1";
        
        if ($apenasAtivos) {
            $sql .= " AND ativo = 1";
        }
        
        $sql .= " ORDER BY ordem ASC, valor ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar apenas planos disponíveis para novos contratos (atual=true e ativo=true)
     */
    public function listarDisponiveis(): array
    {
        $sql = "SELECT * FROM planos_sistema 
                WHERE atual = 1 AND ativo = 1
                ORDER BY ordem ASC, valor ASC";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar plano por ID
     */
    public function buscarPorId(int $id): ?array
    {
        $sql = "SELECT * FROM planos_sistema WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $plano = $stmt->fetch(PDO::FETCH_ASSOC);
        return $plano ?: null;
    }

    /**
     * Criar novo plano do sistema
     */
    public function criar(array $data): int
    {
        $sql = "INSERT INTO planos_sistema 
                (nome, descricao, valor, duracao_dias, max_alunos, max_admins, features, ativo, atual, ordem) 
                VALUES 
                (:nome, :descricao, :valor, :duracao_dias, :max_alunos, :max_admins, :features, :ativo, :atual, :ordem)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nome' => $data['nome'],
            'descricao' => $data['descricao'] ?? null,
            'valor' => $data['valor'],
            'duracao_dias' => $data['duracao_dias'] ?? 30,
            'max_alunos' => $data['max_alunos'] ?? null,
            'max_admins' => $data['max_admins'] ?? 1,
            'features' => isset($data['features']) ? json_encode($data['features']) : null,
            'ativo' => $data['ativo'] ?? true,
            'atual' => $data['atual'] ?? true,
            'ordem' => $data['ordem'] ?? 0
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualizar plano do sistema
     * Quando houver contratos vinculados, apenas permite atualizar o campo 'atual'
     * Para outros campos, precisa marcar como histórico e criar novo plano
     */
    public function atualizar(int $id, array $data): bool
    {
        // Buscar dados atuais do plano
        $planoAtual = $this->buscarPorId($id);
        if (!$planoAtual) {
            throw new \Exception('Plano não encontrado');
        }

        // Verificar se há contratos vinculados
        $possuiContratos = $this->possuiContratos($id);
        
        if ($possuiContratos) {
            // Verificar quais campos realmente mudaram
            $camposMudados = [];
            
            foreach ($data as $campo => $valor) {
                // Pular campos que não estão na tabela
                if (!array_key_exists($campo, $planoAtual)) {
                    continue;
                }
                
                $valorAtual = $planoAtual[$campo];
                $valorNovo = $valor;
                
                // Normalizar valores para comparação
                // Booleanos
                if (is_bool($valorNovo)) {
                    $valorNovo = $valorNovo ? 1 : 0;
                }
                if (is_bool($valorAtual)) {
                    $valorAtual = $valorAtual ? 1 : 0;
                }
                
                // Números (valor, duracao_dias, max_alunos, max_admins, ordem)
                if (is_numeric($valorAtual) && is_numeric($valorNovo)) {
                    $valorAtual = (float) $valorAtual;
                    $valorNovo = (float) $valorNovo;
                }
                
                // Comparar valores
                if ($valorAtual !== $valorNovo) {
                    $camposMudados[] = $campo;
                }
            }
            
            // Se tiver contratos, só permite alterar o campo 'atual'
            $camposProibidos = array_diff($camposMudados, ['atual']);
            if (!empty($camposProibidos)) {
                throw new \Exception('Não é possível modificar este plano pois existem contratos vinculados a ele. Apenas o campo "Plano Atual" pode ser alterado. Para outras alterações, marque como histórico e crie um novo plano.');
            }
        }

        $fields = [];
        $params = [];

        if (isset($data['nome'])) {
            $fields[] = 'nome = :nome';
            $params['nome'] = $data['nome'];
        }
        if (isset($data['descricao'])) {
            $fields[] = 'descricao = :descricao';
            $params['descricao'] = $data['descricao'];
        }
        if (isset($data['valor'])) {
            $fields[] = 'valor = :valor';
            $params['valor'] = $data['valor'];
        }
        if (isset($data['duracao_dias'])) {
            $fields[] = 'duracao_dias = :duracao_dias';
            $params['duracao_dias'] = $data['duracao_dias'];
        }
        if (isset($data['max_alunos'])) {
            $fields[] = 'max_alunos = :max_alunos';
            $params['max_alunos'] = $data['max_alunos'];
        }
        if (isset($data['max_admins'])) {
            $fields[] = 'max_admins = :max_admins';
            $params['max_admins'] = $data['max_admins'];
        }
        if (isset($data['features'])) {
            $fields[] = 'features = :features';
            $params['features'] = json_encode($data['features']);
        }
        if (isset($data['ativo'])) {
            $fields[] = 'ativo = :ativo';
            $params['ativo'] = is_bool($data['ativo']) ? ($data['ativo'] ? 1 : 0) : $data['ativo'];
        }
        if (isset($data['atual'])) {
            $fields[] = 'atual = :atual';
            $params['atual'] = is_bool($data['atual']) ? ($data['atual'] ? 1 : 0) : $data['atual'];
        }
        if (isset($data['ordem'])) {
            $fields[] = 'ordem = :ordem';
            $params['ordem'] = $data['ordem'];
        }

        if (empty($fields)) {
            return false;
        }

        $params['id'] = $id;
        $sql = "UPDATE planos_sistema SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Verificar se o plano possui contratos ativos ou inativos (não cancelados)
     */
    public function possuiContratos(int $id): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM tenant_planos_sistema 
                WHERE plano_sistema_id = :id AND status_id != 3";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Marcar plano como histórico (atual=false)
     * Usado quando se deseja criar uma nova versão do plano
     */
    public function marcarComoHistorico(int $id): bool
    {
        $sql = "UPDATE planos_sistema SET atual = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Desativar plano (ativo=false)
     * Não permite se houver contratos ativos
     */
    public function desativar(int $id): bool
    {
        // Verificar se há contratos ativos (status_id = 1)
        $sql = "SELECT COUNT(*) as total
                FROM tenant_planos_sistema 
                WHERE plano_sistema_id = :id AND status_id = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        if ((int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0) {
            throw new \Exception('Não é possível desativar este plano pois existem contratos ativos vinculados a ele.');
        }

        $sql = "UPDATE planos_sistema SET ativo = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Contar quantos contratos ativos usam este plano
     */
    public function contarContratosAtivos(int $id): int
    {
        $sql = "SELECT COUNT(*) as total
                FROM tenant_planos_sistema 
                WHERE plano_sistema_id = :id AND status_id = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Listar academias (tenants) associadas a este plano
     */
    public function listarAcademias(int $id): array
    {
        $sql = "SELECT 
                    t.id,
                    t.nome,
                    t.cnpj,
                    t.email,
                    t.telefone,
                    t.ativo,
                    sc.nome as status_contrato,
                    tp.data_inicio,
                    tp.status_id,
                    tp.created_at as contrato_criado_em
                FROM tenants t
                INNER JOIN tenant_planos_sistema tp ON t.id = tp.tenant_id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                WHERE tp.plano_sistema_id = :id
                ORDER BY tp.status_id ASC, t.nome ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar se uma academia já possui um contrato ativo
     */
    public function academiaPossuiContratoAtivo(int $tenantId): bool
    {
        $sql = "SELECT COUNT(*) as total
                FROM tenant_planos_sistema 
                WHERE tenant_id = :tenant_id AND status_id = 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'] > 0;
    }

    /**
     * Buscar contrato ativo de uma academia
     */
    public function buscarContratoAtivo(int $tenantId): ?array
    {
        $sql = "SELECT tp.*, 
                       ps.nome as plano_nome,
                       sc.nome as status_nome
                FROM tenant_planos_sistema tp
                INNER JOIN planos_sistema ps ON tp.plano_sistema_id = ps.id
                INNER JOIN status_contrato sc ON tp.status_id = sc.id
                WHERE tp.tenant_id = :tenant_id AND tp.status_id = 1
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}