<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Queries admin de pacotes / contratos de pacote (painel).
 * Paridade com PacoteController, AdminController::{listarContratosPackage,gerarMatriculasPackage}
 * e MatriculaController::darBaixaPacote da API Slim.
 */
class AdminPacoteRepository
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarPacotes(int $tenantId): array
    {
        $rows = DB::select('
            SELECT p.*
            FROM pacotes p
            WHERE p.tenant_id = ?
            ORDER BY p.created_at DESC
        ', [$tenantId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function inserirPacote(array $data): int
    {
        return (int) DB::table('pacotes')->insertGetId([
            'tenant_id' => $data['tenant_id'],
            'nome' => $data['nome'],
            'descricao' => $data['descricao'],
            'valor_total' => $data['valor_total'],
            'qtd_beneficiarios' => $data['qtd_beneficiarios'],
            'plano_id' => $data['plano_id'],
            'plano_ciclo_id' => $data['plano_ciclo_id'],
            'ativo' => 1,
        ]);
    }

    public function pacoteExiste(int $pacoteId, int $tenantId): bool
    {
        return DB::selectOne(
            'SELECT id FROM pacotes WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$pacoteId, $tenantId]
        ) !== null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function atualizarPacote(int $pacoteId, int $tenantId, array $data): void
    {
        DB::update('
            UPDATE pacotes
            SET nome = ?,
                descricao = ?,
                valor_total = ?,
                qtd_beneficiarios = ?,
                plano_id = ?,
                plano_ciclo_id = ?,
                ativo = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [
            $data['nome'],
            $data['descricao'],
            $data['valor_total'],
            $data['qtd_beneficiarios'],
            $data['plano_id'],
            $data['plano_ciclo_id'],
            $data['ativo'],
            $pacoteId,
            $tenantId,
        ]);
    }

    /**
     * Pacote ativo com dados do plano/ciclo (usado em contratar).
     *
     * @return array<string, mixed>|null
     */
    public function findPacoteAtivoComPlano(int $pacoteId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT p.*, pl.duracao_dias, pl.valor as plano_valor,
                   pc.valor as ciclo_valor, pc.meses as ciclo_meses
            FROM pacotes p
            INNER JOIN planos pl ON pl.id = p.plano_id
            LEFT JOIN plano_ciclos pc ON pc.id = p.plano_ciclo_id AND pc.tenant_id = p.tenant_id
            WHERE p.id = ? AND p.tenant_id = ? AND p.ativo = 1
            LIMIT 1
        ', [$pacoteId, $tenantId]);

        return $row ? (array) $row : null;
    }

    /**
     * Aluno do pagante dentro do tenant (papel aluno ativo).
     */
    public function findAlunoIdDoPaganteNoTenant(int $tenantId, int $usuarioId): int
    {
        $row = DB::selectOne('
            SELECT a.id
            FROM alunos a
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
                AND tup.tenant_id = ?
                AND tup.papel_id = 1
                AND tup.ativo = 1
            WHERE a.usuario_id = ?
            LIMIT 1
        ', [$tenantId, $usuarioId]);

        return $row ? (int) $row->id : 0;
    }

    /**
     * @param  list<int>  $alunoIds
     * @return list<int>
     */
    public function filtrarAlunosValidosNoTenant(int $tenantId, array $alunoIds): array
    {
        if ($alunoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($alunoIds), '?'));
        $rows = DB::select("
            SELECT a.id
            FROM alunos a
            INNER JOIN tenant_usuario_papel tup ON tup.usuario_id = a.usuario_id
                AND tup.tenant_id = ?
                AND tup.papel_id = 1
                AND tup.ativo = 1
            WHERE a.id IN ({$placeholders})
        ", array_merge([$tenantId], $alunoIds));

        return array_map(static fn ($r) => (int) $r->id, $rows);
    }

    public function inserirContrato(int $tenantId, int $pacoteId, int $paganteUsuarioId, float $valorTotal): int
    {
        DB::insert('
            INSERT INTO pacote_contratos
            (tenant_id, pacote_id, pagante_usuario_id, status, valor_total)
            VALUES (?, ?, ?, \'pendente\', ?)
        ', [$tenantId, $pacoteId, $paganteUsuarioId, $valorTotal]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function mesesDoCiclo(int $cicloId, int $tenantId): int
    {
        $row = DB::selectOne(
            'SELECT meses FROM plano_ciclos WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$cicloId, $tenantId]
        );

        return $row ? (int) $row->meses : 0;
    }

    public function mesesDoCicloSemTenant(int $cicloId): int
    {
        $row = DB::selectOne('SELECT meses FROM plano_ciclos WHERE id = ? LIMIT 1', [$cicloId]);

        return $row ? (int) $row->meses : 0;
    }

    public function duracaoDiasDoPlano(int $planoId, int $tenantId): int
    {
        $row = DB::selectOne(
            'SELECT duracao_dias FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$planoId, $tenantId]
        );

        return $row ? (int) $row->duracao_dias : 0;
    }

    public function statusMatriculaId(string $codigo, int $fallback): int
    {
        $row = DB::selectOne('SELECT id FROM status_matricula WHERE codigo = ? LIMIT 1', [$codigo]);

        return $row ? (int) $row->id : $fallback;
    }

    public function motivoMatriculaId(string $codigo, int $fallback): int
    {
        $row = DB::selectOne('SELECT id FROM motivo_matricula WHERE codigo = ? LIMIT 1', [$codigo]);

        return $row ? (int) $row->id : $fallback;
    }

    public function statusPagamentoId(string $codigo, int $fallback): int
    {
        $row = DB::selectOne('SELECT id FROM status_pagamento WHERE codigo = ? LIMIT 1', [$codigo]);

        return $row ? (int) $row->id : $fallback;
    }

    /**
     * INSERT de matrícula do fluxo "contratar" (inclui dia_vencimento).
     *
     * @param  array<string, mixed>  $data
     */
    public function inserirMatriculaContrato(array $data): int
    {
        DB::insert('
            INSERT INTO matriculas
            (tenant_id, aluno_id, plano_id, plano_ciclo_id, pacote_contrato_id, tipo_cobranca,
             data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
             status_id, motivo_id, proxima_data_vencimento, dia_vencimento, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, \'avulso\', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $data['tenant_id'],
            $data['aluno_id'],
            $data['plano_id'],
            $data['plano_ciclo_id'],
            $data['pacote_contrato_id'],
            $data['data_matricula'],
            $data['data_inicio'],
            $data['data_vencimento'],
            $data['valor'],
            $data['valor_rateado'],
            $data['status_id'],
            $data['motivo_id'],
            $data['proxima_data_vencimento'],
            $data['dia_vencimento'],
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * INSERT de matrícula dos fluxos "confirmar-pagamento" / "gerar-matriculas".
     *
     * @param  array<string, mixed>  $data
     */
    public function inserirMatriculaPacote(array $data): int
    {
        DB::insert('
            INSERT INTO matriculas
            (tenant_id, aluno_id, plano_id, plano_ciclo_id, tipo_cobranca,
             data_matricula, data_inicio, data_vencimento, valor, valor_rateado,
             status_id, motivo_id, proxima_data_vencimento, pacote_contrato_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $data['tenant_id'],
            $data['aluno_id'],
            $data['plano_id'],
            $data['plano_ciclo_id'],
            $data['tipo_cobranca'],
            $data['data_matricula'],
            $data['data_inicio'],
            $data['data_vencimento'],
            $data['valor'],
            $data['valor_rateado'],
            $data['status_id'],
            $data['motivo_id'],
            $data['proxima_data_vencimento'],
            $data['pacote_contrato_id'],
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function inserirBeneficiarioComMatricula(
        int $tenantId,
        int $contratoId,
        int $alunoId,
        int $matriculaId,
        float $valorRateado
    ): void {
        DB::insert('
            INSERT INTO pacote_beneficiarios
            (tenant_id, pacote_contrato_id, aluno_id, matricula_id, valor_rateado, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, \'pendente\', NOW(), NOW())
        ', [$tenantId, $contratoId, $alunoId, $matriculaId, $valorRateado]);
    }

    public function inserirBeneficiario(int $tenantId, int $contratoId, int $alunoId): void
    {
        DB::insert('
            INSERT INTO pacote_beneficiarios
            (tenant_id, pacote_contrato_id, aluno_id, status)
            VALUES (?, ?, ?, \'pendente\')
        ', [$tenantId, $contratoId, $alunoId]);
    }

    public function deletarBeneficiarios(int $contratoId, int $tenantId): int
    {
        return DB::delete(
            'DELETE FROM pacote_beneficiarios WHERE pacote_contrato_id = ? AND tenant_id = ?',
            [$contratoId, $tenantId]
        );
    }

    /**
     * Contrato + limite de beneficiários do pacote.
     *
     * @return array<string, mixed>|null
     */
    public function findContratoComLimite(int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.id, p.qtd_beneficiarios
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContratoParaConfirmar(int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total, p.qtd_beneficiarios, p.nome as pacote_nome
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarBeneficiariosDoContrato(int $contratoId, int $tenantId): array
    {
        $rows = DB::select('
            SELECT pb.id, pb.aluno_id
            FROM pacote_beneficiarios pb
            WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
        ', [$contratoId, $tenantId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    public function ativarContrato(
        int $contratoId,
        int $tenantId,
        ?string $pagamentoId,
        string $dataInicio,
        ?string $dataFim
    ): void {
        DB::update('
            UPDATE pacote_contratos
            SET status = \'ativo\',
                pagamento_id = ?,
                data_inicio = ?,
                data_fim = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [$pagamentoId, $dataInicio, $dataFim, $contratoId, $tenantId]);
    }

    public function vincularBeneficiarioAMatricula(
        int $beneficiarioId,
        int $tenantId,
        int $matriculaId,
        float $valorRateado
    ): void {
        DB::update('
            UPDATE pacote_beneficiarios
            SET matricula_id = ?, valor_rateado = ?, status = \'ativo\', updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [$matriculaId, $valorRateado, $beneficiarioId, $tenantId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContratoParaExcluir(int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.id, pc.status, pc.pagante_usuario_id,
                   p.nome as pacote_nome
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    public function deletarPagamentosDoContrato(int $contratoId, int $tenantId): int
    {
        return DB::delete('
            DELETE FROM pagamentos_plano
            WHERE pacote_contrato_id = ? AND tenant_id = ?
        ', [$contratoId, $tenantId]);
    }

    public function deletarMatriculasDoContrato(int $contratoId, int $tenantId): int
    {
        return DB::delete('
            DELETE FROM matriculas
            WHERE pacote_contrato_id = ? AND tenant_id = ?
        ', [$contratoId, $tenantId]);
    }

    public function deletarContrato(int $contratoId, int $tenantId): int
    {
        return DB::delete(
            'DELETE FROM pacote_contratos WHERE id = ? AND tenant_id = ?',
            [$contratoId, $tenantId]
        );
    }

    /**
     * Contratos com info básica (status pendente/cancelado/expirado).
     *
     * @return list<array<string, mixed>>
     */
    public function listarContratosBasico(int $tenantId, string $status): array
    {
        $rows = DB::select('
            SELECT
                pc.id as contrato_id,
                pc.status,
                pc.valor_total,
                pc.data_inicio,
                pc.data_fim,
                pc.created_at,
                pc.updated_at,
                p.nome as pacote_nome,
                p.qtd_beneficiarios,
                u.id as pagante_usuario_id,
                u.nome as pagante_nome,
                u.email as pagante_email,
                (
                    SELECT COUNT(DISTINCT pb2.id)
                    FROM pacote_beneficiarios pb2
                    WHERE pb2.pacote_contrato_id = pc.id
                ) as beneficiarios_adicionados
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            INNER JOIN usuarios u ON u.id = pc.pagante_usuario_id
            WHERE pc.tenant_id = ? AND pc.status = ?
            ORDER BY pc.created_at DESC
        ', [$tenantId, $status]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarContratosAtivos(int $tenantId, string $status): array
    {
        $rows = DB::select('
            SELECT
                pc.id as contrato_id,
                pc.status,
                pc.valor_total,
                pc.data_inicio,
                pc.data_fim,
                pc.created_at,
                pc.updated_at,
                p.id as pacote_id,
                p.nome as pacote_nome,
                p.qtd_beneficiarios,
                p.plano_id,
                pl.nome as plano_nome,
                u.id as pagante_usuario_id,
                u.nome as pagante_nome,
                u.email as pagante_email,
                (
                    SELECT COUNT(DISTINCT pb2.id)
                    FROM pacote_beneficiarios pb2
                    WHERE pb2.pacote_contrato_id = pc.id
                ) as beneficiarios_adicionados
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            LEFT JOIN planos pl ON pl.id = p.plano_id
            INNER JOIN usuarios u ON u.id = pc.pagante_usuario_id
            WHERE pc.tenant_id = ? AND pc.status = ?
            ORDER BY pc.data_inicio DESC
        ', [$tenantId, $status]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasGeradas(int $contratoId): array
    {
        $rows = DB::select('
            SELECT
                m.id as matricula_id,
                m.aluno_id,
                a.nome as aluno_nome,
                sm.codigo as status_codigo,
                m.data_inicio,
                m.data_vencimento
            FROM matriculas m
            INNER JOIN alunos a ON a.id = m.aluno_id
            INNER JOIN status_matricula sm ON sm.id = m.status_id
            WHERE m.pacote_contrato_id = ?
            ORDER BY m.created_at ASC
        ', [$contratoId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAlunoDoUsuario(int $usuarioId): ?array
    {
        $row = DB::selectOne('SELECT id, nome FROM alunos WHERE usuario_id = ? LIMIT 1', [$usuarioId]);

        return $row ? (array) $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarBeneficiariosComAluno(int $contratoId): array
    {
        $rows = DB::select('
            SELECT
                pb.id as beneficiario_id,
                pb.aluno_id,
                a.nome,
                a.usuario_id
            FROM pacote_beneficiarios pb
            INNER JOIN alunos a ON a.id = pb.aluno_id
            WHERE pb.pacote_contrato_id = ?
            ORDER BY pb.created_at ASC
        ', [$contratoId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * Contrato + permite_recorrencia do ciclo (usado em gerar-matriculas).
     *
     * @return array<string, mixed>|null
     */
    public function findContratoParaGerarMatriculas(int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.*, p.plano_id, p.plano_ciclo_id, p.valor_total,
                   COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    public function findPrimeiroAlunoIdDoUsuario(int $usuarioId): int
    {
        $row = DB::selectOne('
            SELECT id FROM alunos
            WHERE usuario_id = ?
            ORDER BY id ASC
            LIMIT 1
        ', [$usuarioId]);

        return $row ? (int) $row->id : 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAssinaturaDoContrato(int $assinaturaId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT id, status_id FROM assinaturas
            WHERE id = ? AND tenant_id = ?
            LIMIT 1
        ', [$assinaturaId, $tenantId]);

        return $row ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMatriculaDoAlunoNoContrato(int $alunoId, int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT id, status_id
            FROM matriculas
            WHERE aluno_id = ? AND pacote_contrato_id = ? AND tenant_id = ?
            ORDER BY id DESC LIMIT 1
        ', [$alunoId, $contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    public function atualizarMatriculaPacote(
        int $matriculaId,
        int $tenantId,
        int $statusId,
        string $dataFim,
        float $valorRateado
    ): void {
        DB::update('
            UPDATE matriculas
            SET status_id = ?, data_vencimento = ?, proxima_data_vencimento = ?,
                valor = ?, valor_rateado = ?
            WHERE id = ? AND tenant_id = ?
        ', [$statusId, $dataFim, $dataFim, $valorRateado, $valorRateado, $matriculaId, $tenantId]);
    }

    public function vincularAssinaturaAMatricula(
        int $assinaturaId,
        int $tenantId,
        int $matriculaId,
        int $alunoId,
        float $valor
    ): void {
        DB::update('
            UPDATE assinaturas
            SET matricula_id = ?, aluno_id = ?, status_id = (SELECT id FROM assinatura_status WHERE codigo = \'ativa\' LIMIT 1),
                status_gateway = \'approved\', valor = ?
            WHERE id = ? AND tenant_id = ?
        ', [$matriculaId, $alunoId, $valor, $assinaturaId, $tenantId]);
    }

    // ---------------------------------------------------------------------
    // Baixa do pacote (MatriculaController::darBaixaPacote na Slim)
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    public function findContratoParaBaixa(int $contratoId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.*, p.nome as pacote_nome, p.plano_id, p.plano_ciclo_id
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            WHERE pc.id = ? AND pc.tenant_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId]);

        return $row ? (array) $row : null;
    }

    /**
     * Parcelas em aberto do pacote (por contrato ou via matrícula).
     *
     * @return list<array<string, mixed>>
     */
    public function buscarPagamentosPendentesPacote(int $contratoId, int $tenantId): array
    {
        $rows = DB::select('
            SELECT pp.*, m.plano_ciclo_id, COALESCE(p.duracao_dias, 30) AS duracao_dias,
                   pc2.meses as ciclo_meses, af.meses as frequencia_meses,
                   a.nome as aluno_nome,
                   m.data_inicio as matricula_data_inicio,
                   m.valor as matricula_valor,
                   m.valor_rateado as matricula_valor_rateado,
                   pb.valor_rateado as valor_rateado_beneficiario
            FROM pagamentos_plano pp
            INNER JOIN matriculas m ON pp.matricula_id = m.id
            LEFT JOIN planos p ON p.id = COALESCE(pp.plano_id, m.plano_id)
            INNER JOIN alunos a ON pp.aluno_id = a.id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = m.plano_ciclo_id
            LEFT JOIN assinatura_frequencias af ON af.id = pc2.assinatura_frequencia_id
            LEFT JOIN pacote_beneficiarios pb
                ON pb.matricula_id = m.id
               AND pb.pacote_contrato_id = ?
               AND pb.tenant_id = pp.tenant_id
            WHERE pp.tenant_id = ?
              AND pp.status_pagamento_id IN (1, 3)
              AND (pp.pacote_contrato_id = ? OR m.pacote_contrato_id = ?)
            ORDER BY pp.id
        ', [$contratoId, $tenantId, $contratoId, $contratoId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    public function pacoteJaPossuiPagamentoPago(int $contratoId, int $tenantId): bool
    {
        $row = DB::selectOne('
            SELECT COUNT(*) as total
            FROM pagamentos_plano pp
            INNER JOIN matriculas m ON m.id = pp.matricula_id
            WHERE pp.tenant_id = ?
              AND pp.status_pagamento_id = 2
              AND (pp.pacote_contrato_id = ? OR m.pacote_contrato_id = ?)
        ', [$tenantId, $contratoId, $contratoId]);

        return $row !== null && (int) $row->total > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarMatriculasDoContrato(int $contratoId, int $tenantId): array
    {
        $rows = DB::select('
            SELECT m.id, m.aluno_id, m.plano_id, m.valor, m.valor_rateado,
                   m.data_inicio, m.data_vencimento
            FROM matriculas m
            WHERE m.pacote_contrato_id = ? AND m.tenant_id = ?
            ORDER BY m.id
        ', [$contratoId, $tenantId]);

        return array_map(static fn ($r) => (array) $r, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findUltimoPagamentoDaMatricula(int $matriculaId, int $tenantId): ?array
    {
        $row = DB::selectOne('
            SELECT id, status_pagamento_id, pacote_contrato_id
            FROM pagamentos_plano
            WHERE matricula_id = ? AND tenant_id = ? AND status_pagamento_id IN (1, 2, 3)
            ORDER BY id DESC
            LIMIT 1
        ', [$matriculaId, $tenantId]);

        return $row ? (array) $row : null;
    }

    public function vincularPagamentoAoContrato(int $pagamentoId, int $tenantId, int $contratoId): void
    {
        DB::update('
            UPDATE pagamentos_plano
            SET pacote_contrato_id = ?
            WHERE id = ? AND tenant_id = ? AND pacote_contrato_id IS NULL
        ', [$contratoId, $pagamentoId, $tenantId]);
    }

    public function inserirPagamentoPendentePacote(
        int $tenantId,
        int $alunoId,
        int $matriculaId,
        ?int $planoId,
        float $valor,
        string $dataVencimento,
        int $contratoId,
        ?int $adminId
    ): int {
        DB::insert('
            INSERT INTO pagamentos_plano (
                tenant_id, aluno_id, matricula_id, plano_id, valor,
                data_vencimento, status_pagamento_id, pacote_contrato_id,
                observacoes, criado_por, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, \'Pagamento do pacote\', ?, NOW(), NOW())
        ', [$tenantId, $alunoId, $matriculaId, $planoId, $valor, $dataVencimento, $contratoId, $adminId]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function marcarPagamentoComoPago(int $pagamentoId, array $data): void
    {
        DB::update('
            UPDATE pagamentos_plano
            SET status_pagamento_id = 2,
                data_pagamento = ?,
                forma_pagamento_id = ?,
                observacoes = ?,
                baixado_por = ?,
                tipo_baixa_id = 1,
                updated_at = NOW()
            WHERE id = ?
        ', [
            $data['data_pagamento'],
            $data['forma_pagamento_id'],
            $data['observacoes'],
            $data['baixado_por'],
            $pagamentoId,
        ]);
    }

    public function ativarMatriculaSePendenteOuVencida(int $matriculaId): void
    {
        DB::update('
            UPDATE matriculas
            SET status_id = (SELECT id FROM status_matricula WHERE codigo = \'ativa\' LIMIT 1),
                updated_at = NOW()
            WHERE id = ?
            AND status_id IN (
                SELECT id FROM status_matricula WHERE codigo IN (\'pendente\', \'vencida\')
            )
        ', [$matriculaId]);
    }

    public function inserirProximaParcela(
        int $tenantId,
        int $alunoId,
        int $matriculaId,
        ?int $planoId,
        float $valor,
        string $dataVencimento,
        ?int $adminId
    ): int {
        DB::insert('
            INSERT INTO pagamentos_plano (
                tenant_id, aluno_id, matricula_id, plano_id,
                valor, valor_original, desconto, motivo_desconto,
                data_vencimento, status_pagamento_id, pacote_contrato_id,
                observacoes, criado_por, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, 0, NULL, ?, 1, NULL, \'Parcela gerada automaticamente\', ?, NOW(), NOW())
        ', [$tenantId, $alunoId, $matriculaId, $planoId, $valor, $valor, $dataVencimento, $adminId]);

        return (int) DB::getPdo()->lastInsertId();
    }

    public function atualizarVencimentosMatricula(int $matriculaId, string $acessoAte, string $proximoVencimento): void
    {
        DB::update('
            UPDATE matriculas
            SET data_vencimento = ?,
                proxima_data_vencimento = ?,
                updated_at = NOW()
            WHERE id = ?
        ', [$acessoAte, $proximoVencimento, $matriculaId]);
    }

    /**
     * Marca contrato como ativo preenchendo vigência a partir das matrículas quando vazia.
     */
    public function ativarContratoComVigenciaDasMatriculas(int $contratoId, int $tenantId): void
    {
        DB::update('
            UPDATE pacote_contratos pc
            LEFT JOIN (
                SELECT pacote_contrato_id,
                       MIN(data_inicio) AS data_inicio_min,
                       MAX(data_vencimento) AS data_fim_max
                FROM matriculas
                WHERE pacote_contrato_id = ? AND tenant_id = ?
                GROUP BY pacote_contrato_id
            ) m ON m.pacote_contrato_id = pc.id
            SET pc.status = \'ativo\',
                pc.data_inicio = COALESCE(pc.data_inicio, m.data_inicio_min, CURDATE()),
                pc.data_fim = COALESCE(pc.data_fim, m.data_fim_max),
                pc.updated_at = NOW()
            WHERE pc.id = ? AND pc.tenant_id = ?
        ', [$contratoId, $tenantId, $contratoId, $tenantId]);
    }

    public function ativarBeneficiariosDoContrato(int $contratoId, int $tenantId): void
    {
        DB::update('
            UPDATE pacote_beneficiarios SET status = \'ativo\', updated_at = NOW()
            WHERE pacote_contrato_id = ? AND tenant_id = ?
        ', [$contratoId, $tenantId]);
    }

    // ---------------------------------------------------------------------
    // matricula_descontos / pagamentos_plano (PacoteDescontoService)
    // ---------------------------------------------------------------------

    public function valorDoCiclo(int $cicloId, int $planoId, int $tenantId): ?float
    {
        $row = DB::selectOne('
            SELECT valor FROM plano_ciclos
            WHERE id = ? AND plano_id = ? AND tenant_id = ?
            LIMIT 1
        ', [$cicloId, $planoId, $tenantId]);

        return $row === null ? null : (float) $row->valor;
    }

    public function valorDoPlano(int $planoId, int $tenantId): ?float
    {
        $row = DB::selectOne(
            'SELECT valor FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1',
            [$planoId, $tenantId]
        );

        return $row === null ? null : (float) $row->valor;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDescontoPorPrefixoMotivo(int $tenantId, int $matriculaId, string $prefixo): ?array
    {
        $row = DB::selectOne('
            SELECT *
            FROM matricula_descontos
            WHERE tenant_id = ?
              AND matricula_id = ?
              AND motivo LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ', [$tenantId, $matriculaId, $prefixo.'%']);

        return $row ? (array) $row : null;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function inserirDesconto(array $dados): int
    {
        DB::insert('
            INSERT INTO matricula_descontos
            (tenant_id, matricula_id, tipo, valor, percentual, vigencia_inicio, vigencia_fim,
             parcelas_restantes, motivo, ativo, criado_por, autorizado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
        ', [
            $dados['tenant_id'],
            $dados['matricula_id'],
            $dados['tipo'],
            $dados['valor'] ?? null,
            $dados['percentual'] ?? null,
            $dados['vigencia_inicio'],
            $dados['vigencia_fim'] ?? null,
            $dados['parcelas_restantes'] ?? null,
            $dados['motivo'],
            $dados['criado_por'] ?? null,
            $dados['autorizado_por'] ?? null,
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public function atualizarDesconto(int $tenantId, int $id, array $dados): void
    {
        $allowed = ['tipo', 'valor', 'percentual', 'vigencia_inicio', 'vigencia_fim',
            'parcelas_restantes', 'motivo', 'ativo', 'autorizado_por'];

        $sets = [];
        $bindings = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $dados)) {
                $sets[] = "{$field} = ?";
                $bindings[] = $dados[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $sets[] = 'updated_at = NOW()';
        $bindings[] = $tenantId;
        $bindings[] = $id;

        DB::update(
            'UPDATE matricula_descontos SET '.implode(', ', $sets).' WHERE tenant_id = ? AND id = ?',
            $bindings
        );
    }

    public function atualizarValoresDaMatricula(int $matriculaId, int $tenantId, float $valor, float $valorRateado): void
    {
        DB::update('
            UPDATE matriculas
            SET valor = ?, valor_rateado = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [$valor, $valorRateado, $matriculaId, $tenantId]);
    }

    public function atualizarValorOriginalDoPagamento(
        int $pagamentoId,
        int $tenantId,
        float $valorOriginal,
        int $contratoId
    ): void {
        DB::update('
            UPDATE pagamentos_plano
            SET valor_original = ?,
                pacote_contrato_id = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [$valorOriginal, $contratoId, $pagamentoId, $tenantId]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function aplicarDescontoNoPagamento(int $pagamentoId, int $tenantId, array $data): void
    {
        DB::update('
            UPDATE pagamentos_plano
            SET valor_original = ?,
                desconto = ?,
                valor = ?,
                motivo_desconto = ?,
                pacote_contrato_id = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ', [
            $data['valor_original'],
            $data['desconto'],
            $data['valor'],
            $data['motivo_desconto'],
            $data['pacote_contrato_id'],
            $pagamentoId,
            $tenantId,
        ]);
    }

    public function limparDescontosAplicados(int $pagamentoId): void
    {
        DB::delete('DELETE FROM pagamento_desconto_aplicado WHERE pagamento_plano_id = ?', [$pagamentoId]);
    }

    public function salvarDescontoAplicado(int $pagamentoId, int $descontoId, float $valorDesconto): void
    {
        DB::insert('
            INSERT INTO pagamento_desconto_aplicado (pagamento_plano_id, matricula_desconto_id, valor_desconto)
            VALUES (?, ?, ?)
        ', [$pagamentoId, $descontoId, $valorDesconto]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function inserirPagamentoPacote(array $data): int
    {
        DB::insert('
            INSERT INTO pagamentos_plano
            (tenant_id, aluno_id, matricula_id, plano_id, valor, valor_original, desconto, motivo_desconto,
             data_vencimento, status_pagamento_id, pacote_contrato_id, observacoes, criado_por, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ', [
            $data['tenant_id'],
            $data['aluno_id'],
            $data['matricula_id'],
            $data['plano_id'],
            $data['valor'],
            $data['valor_original'],
            $data['desconto'],
            $data['motivo_desconto'],
            $data['data_vencimento'],
            $data['status_pagamento_id'],
            $data['pacote_contrato_id'],
            $data['observacoes'],
            $data['criado_por'],
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }
}
