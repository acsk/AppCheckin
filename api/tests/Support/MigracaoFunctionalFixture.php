<?php

declare(strict_types=1);

/**
 * Fixture isolada para testes funcionais de migração de plano.
 * Cenário inspirado na matrícula #354 (1x → 2x, crédito do plano atual).
 */
final class MigracaoFunctionalFixture
{
    public int $tenantId = 2;

    public int $usuarioId = 0;

    public int $alunoId = 0;

    public int $matriculaId = 0;

    public int $pagamentoPagoId = 0;

    public int $pagamentoAbertoId = 0;

    /** plano 1x por Semana R$70 */
    public int $planoAtualId = 1;

    /** plano 2x por Semana R$100 */
    public int $planoNovoId = 2;

    public function seed(PDO $db): void
    {
        $suffix = (string) time();
        $email = "migracao.test.{$suffix}@appcheckin.test";
        $cpf = str_pad((string) (time() % 100000000000), 11, '0', STR_PAD_LEFT);

        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        $this->ensurePagamentoHabilitado($db);

        $db->prepare('
            INSERT INTO usuarios (nome, email, senha_hash, ativo, cpf, created_at, updated_at)
            VALUES (?, ?, ?, 1, ?, NOW(), NOW())
        ')->execute([
            "Aluno Migração {$suffix}",
            $email,
            password_hash('teste123', PASSWORD_BCRYPT),
            $cpf,
        ]);
        $this->usuarioId = (int) $db->lastInsertId();

        $papelId = (int) $db->query("SELECT id FROM papeis WHERE nome LIKE '%aluno%' OR id = 4 LIMIT 1")->fetchColumn();
        if ($papelId <= 0) {
            $papelId = 4;
        }

        $db->prepare('
            INSERT INTO tenant_usuario_papel (tenant_id, usuario_id, papel_id, ativo, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ')->execute([$this->tenantId, $this->usuarioId, $papelId]);

        $db->prepare('
            INSERT INTO alunos (usuario_id, nome, cpf, ativo, created_at, updated_at)
            VALUES (?, ?, ?, 1, NOW(), NOW())
        ')->execute([$this->usuarioId, "Aluno Migração {$suffix}", $cpf]);
        $this->alunoId = (int) $db->lastInsertId();

        $statusAtivaId = (int) $db->query("SELECT id FROM status_matricula WHERE codigo = 'ativa' LIMIT 1")->fetchColumn();
        $motivoNovaId = (int) $db->query("SELECT id FROM motivo_matricula WHERE codigo = 'nova' LIMIT 1")->fetchColumn();

        $dataInicio = date('Y-m-d', strtotime('-23 days'));
        $dataVencimento = date('Y-m-d', strtotime('+7 days'));
        $proximaVencimento = date('Y-m-d', strtotime('+37 days'));

        $db->prepare('
            INSERT INTO matriculas (
                tenant_id, aluno_id, plano_id, data_matricula, data_inicio, data_vencimento,
                proxima_data_vencimento, valor, status_id, motivo_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 70.00, ?, ?, NOW(), NOW())
        ')->execute([
            $this->tenantId,
            $this->alunoId,
            $this->planoAtualId,
            $dataInicio,
            $dataInicio,
            $dataVencimento,
            $proximaVencimento,
            $statusAtivaId,
            $motivoNovaId,
        ]);
        $this->matriculaId = (int) $db->lastInsertId();

        $db->prepare('
            INSERT INTO pagamentos_plano (
                tenant_id, matricula_id, aluno_id, plano_id, valor, data_vencimento,
                data_pagamento, status_pagamento_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 70.00, ?, ?, 2, NOW(), NOW())
        ')->execute([
            $this->tenantId,
            $this->matriculaId,
            $this->alunoId,
            $this->planoAtualId,
            $dataInicio,
            $dataInicio,
        ]);
        $this->pagamentoPagoId = (int) $db->lastInsertId();

        $db->prepare('
            INSERT INTO pagamentos_plano (
                tenant_id, matricula_id, aluno_id, plano_id, valor, data_vencimento,
                status_pagamento_id, created_at, updated_at
            ) VALUES (?, ?, ?, ?, 70.00, ?, 1, NOW(), NOW())
        ')->execute([
            $this->tenantId,
            $this->matriculaId,
            $this->alunoId,
            $this->planoAtualId,
            $proximaVencimento,
        ]);
        $this->pagamentoAbertoId = (int) $db->lastInsertId();

        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    public function matriculaRow(PDO $db): array
    {
        $stmt = $db->prepare('
            SELECT m.*, p.nome AS plano_nome, p.modalidade_id
            FROM matriculas m
            INNER JOIN planos p ON p.id = m.plano_id
            WHERE m.id = ?
        ');
        $stmt->execute([$this->matriculaId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function cleanup(PDO $db): void
    {
        if ($this->matriculaId <= 0) {
            return;
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        $db->prepare('DELETE FROM creditos_aluno WHERE matricula_origem_id = ?')->execute([$this->matriculaId]);
        $db->prepare('DELETE FROM pagamentos_plano WHERE matricula_id = ?')->execute([$this->matriculaId]);
        $db->prepare('DELETE FROM historico_planos WHERE usuario_id = ?')->execute([$this->usuarioId]);
        $db->prepare('DELETE FROM matriculas WHERE id = ?')->execute([$this->matriculaId]);

        if ($this->alunoId > 0) {
            $db->prepare('DELETE FROM alunos WHERE id = ?')->execute([$this->alunoId]);
        }
        if ($this->usuarioId > 0) {
            $db->prepare('DELETE FROM tenant_usuario_papel WHERE usuario_id = ?')->execute([$this->usuarioId]);
            $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$this->usuarioId]);
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=1');

        $this->matriculaId = 0;
        $this->pagamentoPagoId = 0;
        $this->pagamentoAbertoId = 0;
        $this->alunoId = 0;
        $this->usuarioId = 0;
    }

    private function ensurePagamentoHabilitado(PDO $db): void
    {
        foreach ([1 => '1', 2 => '1'] as $parametroId => $valor) {
            $stmt = $db->prepare('
                SELECT id FROM parametros_tenant
                WHERE tenant_id = ? AND parametro_id = ?
                LIMIT 1
            ');
            $stmt->execute([$this->tenantId, $parametroId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $db->prepare('
                    UPDATE parametros_tenant SET valor = ?, ativo = 1, updated_at = NOW() WHERE id = ?
                ')->execute([$valor, $existing]);
            } else {
                $db->prepare('
                    INSERT INTO parametros_tenant (tenant_id, parametro_id, valor, ativo, created_at, updated_at)
                    VALUES (?, ?, ?, 1, NOW(), NOW())
                ')->execute([$this->tenantId, $parametroId, $valor]);
            }
        }
    }
}
