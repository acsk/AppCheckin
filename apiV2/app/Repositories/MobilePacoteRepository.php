<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

class MobilePacoteRepository
{
    public function findAlunoIdUsuario(int $userId, int $tenantId): int
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
        ', [$tenantId, $userId]);

        return $row ? (int) $row->id : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarContratosUsuario(int $tenantId, int $userId, int $alunoId, ?string $statusFiltro): array
    {
        $sql = 'SELECT DISTINCT pc.id as contrato_id, pc.status, pc.valor_total,
                pc.data_inicio, pc.data_fim, pc.payment_url, pc.payment_preference_id,
                pc.pagante_usuario_id, pc.created_at,
                p.nome as pacote_nome, p.plano_id, p.qtd_beneficiarios,
                pl.nome as plano_nome,
                u_pagante.nome as pagante_nome,
                CASE WHEN pc.pagante_usuario_id = ? THEN 1 ELSE 0 END as sou_pagante
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                LEFT JOIN planos pl ON pl.id = p.plano_id
                LEFT JOIN usuarios u_pagante ON u_pagante.id = pc.pagante_usuario_id
                LEFT JOIN pacote_beneficiarios pb ON pb.pacote_contrato_id = pc.id AND pb.tenant_id = pc.tenant_id
                WHERE pc.tenant_id = ?
                  AND (pc.pagante_usuario_id = ? OR pb.aluno_id = ?)';

        $params = [$userId, $tenantId, $userId, $alunoId];

        if ($statusFiltro !== null && in_array($statusFiltro, ['pendente', 'ativo', 'cancelado'], true)) {
            $sql .= ' AND pc.status = ?';
            $params[] = $statusFiltro;
        }

        $sql .= ' ORDER BY pc.created_at DESC';

        return array_map(
            static fn ($row) => (array) $row,
            DB::select($sql, $params),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarBeneficiariosContrato(int $contratoId, int $tenantId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select('
                SELECT pb.id, pb.aluno_id, pb.status, pb.valor_rateado,
                       a.nome as aluno_nome,
                       m.id as matricula_id,
                       sm.nome as matricula_status
                FROM pacote_beneficiarios pb
                INNER JOIN alunos a ON a.id = pb.aluno_id
                LEFT JOIN matriculas m ON m.id = pb.matricula_id
                LEFT JOIN status_matricula sm ON sm.id = m.status_id
                WHERE pb.pacote_contrato_id = ? AND pb.tenant_id = ?
            ', [$contratoId, $tenantId]),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPagamentosContrato(int $contratoId, int $tenantId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select('
                SELECT pp.id, pp.valor, pp.data_vencimento, pp.data_pagamento,
                       sp.nome as status_pagamento,
                       a.nome as aluno_nome
                FROM pagamentos_plano pp
                INNER JOIN alunos a ON a.id = pp.aluno_id
                LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
                WHERE pp.pacote_contrato_id = ? AND pp.tenant_id = ?
                ORDER BY pp.data_vencimento ASC
                LIMIT 20
            ', [$contratoId, $tenantId]),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPendentesPagante(int $tenantId, int $userId): array
    {
        return array_map(
            static fn ($row) => (array) $row,
            DB::select('
                SELECT pc.id as contrato_id, pc.status, pc.valor_total, pc.data_inicio, pc.data_fim,
                       pc.payment_url, pc.payment_preference_id,
                       p.nome as pacote_nome
                FROM pacote_contratos pc
                INNER JOIN pacotes p ON p.id = pc.pacote_id
                WHERE pc.tenant_id = ? AND pc.pagante_usuario_id = ? AND pc.status = \'pendente\'
                ORDER BY pc.created_at DESC
            ', [$tenantId, $userId]),
        );
    }

    public function findContratoParaPagar(int $contratoId, int $tenantId, int $userId): ?array
    {
        $row = DB::selectOne('
            SELECT pc.*, p.nome as pacote_nome, p.valor_total, p.plano_ciclo_id, p.plano_id as pacote_plano_id,
                   COALESCE(pc2.permite_recorrencia, 0) as permite_recorrencia
            FROM pacote_contratos pc
            INNER JOIN pacotes p ON p.id = pc.pacote_id
            LEFT JOIN plano_ciclos pc2 ON pc2.id = p.plano_ciclo_id
            WHERE pc.id = ? AND pc.tenant_id = ? AND pc.pagante_usuario_id = ?
            LIMIT 1
        ', [$contratoId, $tenantId, $userId]);

        return $row ? (array) $row : null;
    }

    public function findAssinaturaIdPorContrato(int $contratoId, int $tenantId): ?int
    {
        $id = DB::table('assinaturas')
            ->where('pacote_contrato_id', $contratoId)
            ->where('tenant_id', $tenantId)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function atualizarPagamentoContrato(
        int $contratoId,
        int $tenantId,
        ?string $paymentUrl,
        ?string $preferenceId,
        ?int $assinaturaId,
    ): void {
        DB::table('pacote_contratos')
            ->where('id', $contratoId)
            ->where('tenant_id', $tenantId)
            ->update([
                'payment_url' => $paymentUrl,
                'payment_preference_id' => $preferenceId,
                'assinatura_id' => $assinaturaId,
                'updated_at' => DB::raw('NOW()'),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function inserirAssinaturaPacote(array $data): int
    {
        return (int) DB::table('assinaturas')->insertGetId([
            'tenant_id' => $data['tenant_id'],
            'matricula_id' => $data['matricula_id'] ?? null,
            'plano_id' => $data['plano_id'] ?? null,
            'gateway_id' => $data['gateway_id'],
            'gateway_assinatura_id' => $data['gateway_assinatura_id'] ?? null,
            'gateway_preference_id' => $data['gateway_preference_id'] ?? null,
            'external_reference' => $data['external_reference'],
            'payment_url' => $data['payment_url'] ?? null,
            'status_id' => $data['status_id'],
            'status_gateway' => 'pending',
            'valor' => $data['valor'],
            'frequencia_id' => $data['frequencia_id'],
            'dia_cobranca' => $data['dia_cobranca'],
            'data_inicio' => DB::raw('CURDATE()'),
            'data_fim' => null,
            'proxima_cobranca' => null,
            'metodo_pagamento_id' => null,
            'tipo_cobranca' => $data['tipo_cobranca'],
            'pacote_contrato_id' => $data['pacote_contrato_id'],
            'criado_em' => DB::raw('NOW()'),
            'atualizado_em' => DB::raw('NOW()'),
        ]);
    }

    public function gatewayIdMercadoPago(): int
    {
        return (int) (DB::table('assinatura_gateways')->where('codigo', 'mercadopago')->value('id') ?: 1);
    }

    public function statusAssinaturaIdPendente(): int
    {
        return (int) (DB::table('assinatura_status')->where('codigo', 'pendente')->value('id') ?: 1);
    }

    public function frequenciaMensalId(): int
    {
        return (int) (DB::table('assinatura_frequencias')->where('codigo', 'mensal')->value('id') ?: 4);
    }

    public function mesesDoCiclo(int $cicloId, int $tenantId): int
    {
        $meses = DB::table('plano_ciclos')
            ->where('id', $cicloId)
            ->where('tenant_id', $tenantId)
            ->value('meses');

        return $meses !== null ? (int) $meses : 1;
    }

    public function planoIdDoPacote(int $pacoteId): ?int
    {
        $id = DB::table('pacotes')->where('id', $pacoteId)->value('plano_id');

        return $id !== null ? (int) $id : null;
    }

    public function nomeTenant(int $tenantId): string
    {
        return (string) (DB::table('tenants')->where('id', $tenantId)->value('nome') ?: 'Academia');
    }
}
