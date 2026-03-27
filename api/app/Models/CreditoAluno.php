<?php

namespace App\Models;

use PDO;

class CreditoAluno
{
    private PDO $pdo;

    // IDs da tabela status_creditos_aluno
    const STATUS_ATIVO = 1;
    const STATUS_UTILIZADO = 2;
    const STATUS_CANCELADO = 3;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Criar novo crédito
     */
    public function criar(array $dados): int
    {
        $sql = "INSERT INTO creditos_aluno 
            (tenant_id, aluno_id, matricula_origem_id, pagamento_origem_id, valor, motivo, criado_por)
            VALUES 
            (:tenant_id, :aluno_id, :matricula_origem_id, :pagamento_origem_id, :valor, :motivo, :criado_por)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tenant_id' => $dados['tenant_id'],
            'aluno_id' => $dados['aluno_id'],
            'matricula_origem_id' => $dados['matricula_origem_id'] ?? null,
            'pagamento_origem_id' => $dados['pagamento_origem_id'] ?? null,
            'valor' => $dados['valor'],
            'motivo' => $dados['motivo'] ?? null,
            'criado_por' => $dados['criado_por'] ?? null
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Buscar crédito por ID
     */
    public function buscarPorId(int $tenantId, int $id): ?array
    {
        $sql = "SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                       (ca.valor - ca.valor_utilizado) as saldo
                FROM creditos_aluno ca
                INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
                WHERE ca.tenant_id = :tenant_id AND ca.id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Listar créditos de um aluno (com saldo calculado)
     */
    public function listarPorAluno(int $tenantId, int $alunoId): array
    {
        $sql = "SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                       (ca.valor - ca.valor_utilizado) as saldo
                FROM creditos_aluno ca
                INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
                WHERE ca.tenant_id = :tenant_id AND ca.aluno_id = :aluno_id
                ORDER BY ca.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'aluno_id' => $alunoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Listar créditos ativos (com saldo > 0) de um aluno
     */
    public function listarAtivos(int $tenantId, int $alunoId): array
    {
        $sql = "SELECT ca.*, sca.codigo as status, sca.nome as status_nome,
                       (ca.valor - ca.valor_utilizado) as saldo
                FROM creditos_aluno ca
                INNER JOIN status_creditos_aluno sca ON sca.id = ca.status_credito_id
                WHERE ca.tenant_id = :tenant_id AND ca.aluno_id = :aluno_id 
                  AND ca.status_credito_id = " . self::STATUS_ATIVO . "
                  AND (ca.valor - ca.valor_utilizado) > 0
                ORDER BY ca.created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'aluno_id' => $alunoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Saldo total de créditos ativos de um aluno
     */
    public function saldoTotal(int $tenantId, int $alunoId): float
    {
        $sql = "SELECT COALESCE(SUM(valor - valor_utilizado), 0) as saldo
                FROM creditos_aluno 
                WHERE tenant_id = :tenant_id AND aluno_id = :aluno_id 
                  AND status_credito_id = " . self::STATUS_ATIVO . "
                  AND (valor - valor_utilizado) > 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'aluno_id' => $alunoId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Utilizar crédito (parcial ou total)
     * Atualiza valor_utilizado e muda status para 'utilizado' se totalmente consumido
     */
    public function utilizar(int $id, float $valorUtilizar): bool
    {
        $credito = $this->buscarPorIdInterno($id);
        if (!$credito) return false;

        $saldo = (float) $credito['valor'] - (float) $credito['valor_utilizado'];
        if ($valorUtilizar > $saldo) {
            $valorUtilizar = $saldo;
        }

        $novoUtilizado = (float) $credito['valor_utilizado'] + $valorUtilizar;
        $novoSaldo = (float) $credito['valor'] - $novoUtilizado;
        $novoStatusId = $novoSaldo <= 0.001 ? self::STATUS_UTILIZADO : self::STATUS_ATIVO;

        $sql = "UPDATE creditos_aluno 
                SET valor_utilizado = :valor_utilizado, status_credito_id = :status_credito_id, updated_at = NOW()
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'valor_utilizado' => round($novoUtilizado, 2),
            'status_credito_id' => $novoStatusId,
            'id' => $id
        ]);
    }

    /**
     * Cancelar crédito
     */
    public function cancelar(int $tenantId, int $id): bool
    {
        $sql = "UPDATE creditos_aluno SET status_credito_id = " . self::STATUS_CANCELADO . ", updated_at = NOW() 
                WHERE tenant_id = :tenant_id AND id = :id AND status_credito_id = " . self::STATUS_ATIVO;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function buscarPorIdInterno(int $id): ?array
    {
        $sql = "SELECT * FROM creditos_aluno WHERE id = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}
