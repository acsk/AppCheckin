<?php
/**
 * EXEMPLO: Como atualizar Models para usar o novo sistema de Status
 * 
 * Este arquivo demonstra as mudanças necessárias em um Model
 * para usar tabelas de status ao invés de ENUMs
 */

namespace App\Models;

class ContaReceberModel
{
    private $db;
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    // ==========================================
    // ❌ VERSÃO ANTIGA (com ENUM)
    // ==========================================
    
    public function listarAntigo($tenantId)
    {
        $sql = "
            SELECT 
                id, 
                descricao, 
                valor, 
                data_vencimento,
                status  -- ❌ status como string: 'pendente', 'pago', etc
            FROM contas_receber
            WHERE tenant_id = :tenant_id
            ORDER BY data_vencimento
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        
        return $stmt->fetchAll();
        
        // Resultado antigo:
        // [
        //   {
        //     "id": 1,
        //     "descricao": "Mensalidade João",
        //     "status": "pendente"  ❌ apenas string
        //   }
        // ]
    }
    
    // ==========================================
    // ✅ VERSÃO NOVA (com FK + JOIN)
    // ==========================================
    
    public function listar($tenantId)
    {
        $sql = "
            SELECT 
                cr.id,
                cr.descricao,
                cr.valor,
                cr.data_vencimento,
                cr.status_id,
                -- ✅ Campos do status
                scr.codigo as status_codigo,
                scr.nome as status_nome,
                scr.descricao as status_descricao,
                scr.cor as status_cor,
                scr.icone as status_icone,
                scr.permite_edicao as status_permite_edicao,
                scr.permite_cancelamento as status_permite_cancelamento
            FROM contas_receber cr
            LEFT JOIN status_conta_receber scr ON cr.status_id = scr.id
            WHERE cr.tenant_id = :tenant_id
            ORDER BY cr.data_vencimento
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // ✅ Estruturar resposta com status_info
        return array_map(function($row) {
            return [
                'id' => $row['id'],
                'descricao' => $row['descricao'],
                'valor' => $row['valor'],
                'data_vencimento' => $row['data_vencimento'],
                'status_id' => $row['status_id'],
                // ✅ Objeto rico com todas as informações
                'status_info' => [
                    'id' => $row['status_id'],
                    'codigo' => $row['status_codigo'],
                    'nome' => $row['status_nome'],
                    'descricao' => $row['status_descricao'],
                    'cor' => $row['status_cor'],
                    'icone' => $row['status_icone'],
                    'permite_edicao' => (bool) $row['status_permite_edicao'],
                    'permite_cancelamento' => (bool) $row['status_permite_cancelamento']
                ]
            ];
        }, $results);
        
        // ✅ Resultado novo:
        // [
        //   {
        //     "id": 1,
        //     "descricao": "Mensalidade João",
        //     "status_id": 1,
        //     "status_info": {
        //       "id": 1,
        //       "codigo": "pendente",
        //       "nome": "Pendente",
        //       "cor": "#f59e0b",
        //       "icone": "clock",
        //       "permite_edicao": true
        //     }
        //   }
        // ]
    }
    
    // ==========================================
    // ✅ CRIAR com novo sistema
    // ==========================================
    
    public function criar($data, $tenantId)
    {
        // Opção 1: Receber status_id diretamente
        $sql = "
            INSERT INTO contas_receber 
            (tenant_id, descricao, valor, data_vencimento, status_id)
            VALUES
            (:tenant_id, :descricao, :valor, :data_vencimento, :status_id)
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'descricao' => $data['descricao'],
            'valor' => $data['valor'],
            'data_vencimento' => $data['data_vencimento'],
            'status_id' => $data['status_id'] ?? $this->getStatusIdPorCodigo('pendente')
        ]);
        
        return $this->db->lastInsertId();
    }
    
    // ==========================================
    // ✅ ATUALIZAR STATUS
    // ==========================================
    
    public function atualizarStatus($id, $novoStatusCodigo, $tenantId)
    {
        // Buscar ID do novo status pelo código
        $statusId = $this->getStatusIdPorCodigo($novoStatusCodigo);
        
        if (!$statusId) {
            throw new \Exception("Status '{$novoStatusCodigo}' não encontrado");
        }
        
        $sql = "
            UPDATE contas_receber
            SET status_id = :status_id
            WHERE id = :id AND tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status_id' => $statusId,
            'id' => $id,
            'tenant_id' => $tenantId
        ]);
    }
    
    // ==========================================
    // ✅ HELPER: Converter código → ID
    // ==========================================
    
    private function getStatusIdPorCodigo($codigo)
    {
        $sql = "
            SELECT id 
            FROM status_conta_receber 
            WHERE codigo = :codigo AND ativo = TRUE
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['codigo' => $codigo]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? (int) $result['id'] : null;
    }
    
    // ==========================================
    // ✅ BUSCAR COM STATUS
    // ==========================================
    
    public function buscar($id, $tenantId)
    {
        $sql = "
            SELECT 
                cr.*,
                scr.codigo as status_codigo,
                scr.nome as status_nome,
                scr.cor as status_cor,
                scr.icone as status_icone
            FROM contas_receber cr
            LEFT JOIN status_conta_receber scr ON cr.status_id = scr.id
            WHERE cr.id = :id AND cr.tenant_id = :tenant_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        // Estruturar resposta
        return [
            'id' => $row['id'],
            'descricao' => $row['descricao'],
            'valor' => $row['valor'],
            'status_info' => [
                'id' => $row['status_id'],
                'codigo' => $row['status_codigo'],
                'nome' => $row['status_nome'],
                'cor' => $row['status_cor'],
                'icone' => $row['status_icone']
            ]
        ];
    }
    
    // ==========================================
    // ✅ FILTRAR POR STATUS
    // ==========================================
    
    public function listarPorStatus($tenantId, $statusCodigo)
    {
        $sql = "
            SELECT 
                cr.*,
                scr.codigo as status_codigo,
                scr.nome as status_nome
            FROM contas_receber cr
            JOIN status_conta_receber scr ON cr.status_id = scr.id
            WHERE cr.tenant_id = :tenant_id 
            AND scr.codigo = :status_codigo
            ORDER BY cr.data_vencimento
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'tenant_id' => $tenantId,
            'status_codigo' => $statusCodigo
        ]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// ==========================================
// 📝 RESUMO DAS MUDANÇAS
// ==========================================

/*
1. ✅ Adicionar JOIN com tabela de status em todas as queries SELECT
2. ✅ Estruturar resposta com status_info { id, codigo, nome, cor, icone }
3. ✅ Usar status_id (INT) ao invés de status (ENUM) em INSERT/UPDATE
4. ✅ Criar helper getStatusIdPorCodigo() para conversão
5. ✅ Remover comparações diretas com strings ('pendente', 'pago')
6. ✅ Usar JOIN em filtros ao invés de WHERE status = 'valor'
*/

// ==========================================
// 🎯 CHECKLIST DE ATUALIZAÇÃO
// ==========================================

/*
Para cada Model que usa status:

[ ] Identificar todas as queries que usam status
[ ] Adicionar JOIN com tabela correspondente
[ ] Adicionar campos do status no SELECT
[ ] Estruturar status_info na resposta
[ ] Atualizar INSERT para usar status_id
[ ] Atualizar UPDATE para usar status_id
[ ] Criar helper getStatusIdPorCodigo()
[ ] Testar todos os endpoints
[ ] Validar resposta no frontend
*/
