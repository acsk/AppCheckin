<?php

namespace App\Services\Mobile;

use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;

class MobileAssinaturaService
{
    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function minhasAssinaturas(int $userId, ?int $tenantId, ?int $alunoIdJwt): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Nenhum tenant selecionado',
                    'code' => 'TENANT_NAO_SELECIONADO',
                ],
            ];
        }

        $alunoId = $alunoIdJwt ?: DB::table('alunos')->where('usuario_id', $userId)->value('id');

        if (! $alunoId) {
            return [
                'status' => 200,
                'body' => ['success' => true, 'assinaturas' => [], 'total' => 0, 'pacotes' => []],
            ];
        }

        $rows = DB::table('assinaturas as a')
            ->leftJoin('assinatura_status as s', 's.id', '=', 'a.status_id')
            ->leftJoin('assinatura_cancelamento_tipos as ct', 'ct.id', '=', 'a.cancelado_por_id')
            ->leftJoin('assinatura_frequencias as f', 'f.id', '=', 'a.frequencia_id')
            ->leftJoin('assinatura_gateways as g', 'g.id', '=', 'a.gateway_id')
            ->leftJoin('planos as p', 'p.id', '=', 'a.plano_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'p.modalidade_id')
            ->leftJoin('matriculas as m', function ($join) {
                $join->on('m.id', '=', 'a.matricula_id')
                    ->on('m.tenant_id', '=', 'a.tenant_id');
            })
            ->leftJoin('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('a.aluno_id', $alunoId)
            ->where('a.tenant_id', $tenantId)
            ->orderByDesc('a.data_inicio')
            ->select([
                'a.id', 'a.matricula_id', 'a.status_id', 'a.valor', 'a.data_inicio', 'a.data_fim',
                'a.proxima_cobranca', 'a.ultima_cobranca', 'a.gateway_assinatura_id as mp_preapproval_id',
                'a.gateway_preference_id', 'a.external_reference', 'a.payment_url', 'a.tipo_cobranca',
                'a.status_gateway', 'a.cancelado_por_id',
                's.codigo as status_codigo', 's.nome as status_nome', 's.cor as status_cor',
                'ct.codigo as cancelado_por_codigo', 'ct.nome as cancelado_por_nome',
                'f.nome as ciclo_nome', 'f.meses as ciclo_meses', 'g.nome as gateway_nome',
                'p.nome as plano_nome', 'mo.nome as modalidade_nome',
                'm.data_inicio as matricula_data_inicio',
                'm.proxima_data_vencimento as matricula_proxima_vencimento',
                'sm.codigo as matricula_status_codigo',
                DB::raw('(
                    SELECT MIN(pp.data_vencimento)
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = a.matricula_id
                      AND pp.tenant_id = a.tenant_id
                      AND pp.status_pagamento_id IN (1, 3)
                ) as proxima_parcela_vencimento'),
                DB::raw('(
                    SELECT MAX(pp.data_vencimento)
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = a.matricula_id
                      AND pp.tenant_id = a.tenant_id
                      AND pp.status_pagamento_id = 2
                ) as ultimo_vencimento_pago'),
                DB::raw('(
                    SELECT MAX(DATE(pp.data_pagamento))
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = a.matricula_id
                      AND pp.tenant_id = a.tenant_id
                      AND pp.status_pagamento_id = 2
                      AND pp.data_pagamento IS NOT NULL
                ) as ultima_parcela_pagamento'),
                DB::raw('(
                    SELECT MAX(DATE(pm.date_approved))
                    FROM pagamentos_mercadopago pm
                    WHERE pm.matricula_id = a.matricula_id
                      AND pm.tenant_id = a.tenant_id
                      AND pm.status = \'approved\'
                      AND pm.date_approved IS NOT NULL
                ) as ultima_cobranca_mp'),
            ])
            ->get();

        $assinaturas = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $statusCodigo = $row['status_codigo'] ?? $row['status_gateway'] ?? 'pendente';
            $statusNome = $row['status_nome'] ?? $statusCodigo;
            $statusCor = $row['status_cor'] ?? '#FFA500';

            $canceladoPorId = (int) ($row['cancelado_por_id'] ?? 0);
            $canceladoPorCodigo = strtolower((string) ($row['cancelado_por_codigo'] ?? ''));
            $foiCanceladaPeloUsuario = $canceladoPorId === 1 || $canceladoPorCodigo === 'usuario';
            if ($foiCanceladaPeloUsuario) {
                $statusCodigo = 'cancelada';
                $statusNome = $row['cancelado_por_nome'] ?: 'Cancelado pelo Usuário';
                $statusCor = '#DC2626';
            }

            $matriculaId = (int) ($row['matricula_id'] ?? 0);

            $datas = $this->alinharDatasAssinaturaComFaturas($row);

            $temProximaParcela = ! empty($row['proxima_parcela_vencimento']);
            $matriculaAtiva = ($row['matricula_status_codigo'] ?? '') === 'ativa';
            if (
                ! $foiCanceladaPeloUsuario
                && in_array(strtolower((string) $statusCodigo), ['paga', 'pago', 'paid', 'approved'], true)
                && $temProximaParcela
                && $matriculaAtiva
            ) {
                $statusCodigo = 'ativa';
                $statusNome = 'Ativa';
                $statusCor = '#16A34A';
            }

            $isPendente = in_array($statusCodigo, ['pendente', 'pending'], true);
            $tipoCobranca = $row['tipo_cobranca'] ?? 'recorrente';
            $item = [
                'id' => (int) $row['id'],
                'matricula_id' => $matriculaId ?: null,
                'status' => [
                    'id' => (int) $row['status_id'],
                    'codigo' => $statusCodigo,
                    'nome' => $statusNome,
                    'cor' => $statusCor,
                ],
                'cancelamento' => [
                    'cancelado_por_id' => $canceladoPorId ?: null,
                    'cancelado_por_codigo' => $row['cancelado_por_codigo'] ?? null,
                    'cancelado_por_nome' => $row['cancelado_por_nome'] ?? null,
                ],
                'valor' => (float) $row['valor'],
                'tipo_cobranca' => $tipoCobranca,
                'recorrente' => $tipoCobranca === 'recorrente',
                'data_inicio' => $datas['data_inicio'],
                'data_fim' => $datas['data_fim'],
                'proxima_cobranca' => $datas['proxima_cobranca'],
                'ultima_cobranca' => $datas['ultima_cobranca'],
                'mp_preapproval_id' => $row['mp_preapproval_id'],
                'preference_id' => $row['gateway_preference_id'],
                'external_reference' => $row['external_reference'],
                'ciclo' => [
                    'nome' => $row['ciclo_nome'] ?? 'Mensal',
                    'meses' => (int) ($row['ciclo_meses'] ?? 1),
                ],
                'gateway' => ['nome' => $row['gateway_nome'] ?? 'Mercado Pago'],
                'plano' => [
                    'nome' => $row['plano_nome'] ?? '',
                    'modalidade' => $row['modalidade_nome'] ?? '',
                ],
                'pode_pagar' => $isPendente && ! empty($row['payment_url']),
            ];

            if ($isPendente && ! empty($row['payment_url'])) {
                $item['payment_url'] = $row['payment_url'];
            }

            $assinaturas[] = $item;
        }

        $pacotes = DB::table('pacote_contratos as pc')
            ->join('pacotes as p', 'p.id', '=', 'pc.pacote_id')
            ->where('pc.tenant_id', $tenantId)
            ->where('pc.pagante_usuario_id', $userId)
            ->orderByDesc('pc.created_at')
            ->get(['pc.id as contrato_id', 'pc.status', 'pc.valor_total', 'pc.data_inicio', 'pc.data_fim', 'p.nome as pacote_nome'])
            ->map(function ($pc) use ($tenantId) {
                $pc = (array) $pc;
                $beneficiarios = DB::table('pacote_beneficiarios as pb')
                    ->join('alunos as a', 'a.id', '=', 'pb.aluno_id')
                    ->where('pb.pacote_contrato_id', $pc['contrato_id'])
                    ->where('pb.tenant_id', $tenantId)
                    ->get(['a.id as aluno_id', 'a.nome as aluno_nome']);

                return [
                    'contrato_id' => (int) $pc['contrato_id'],
                    'status' => $pc['status'],
                    'valor_total' => (float) $pc['valor_total'],
                    'data_inicio' => $pc['data_inicio'],
                    'data_fim' => $pc['data_fim'],
                    'pacote_nome' => $pc['pacote_nome'],
                    'beneficiarios' => $beneficiarios->map(fn ($b) => [
                        'aluno_id' => (int) $b->aluno_id,
                        'nome' => $b->aluno_nome,
                    ])->all(),
                ];
            })
            ->all();

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'assinaturas' => $assinaturas,
                'total' => count($assinaturas),
                'pacotes' => $pacotes,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $userId, int $tenantId, int $assinaturaId, array $body): array
    {
        $assinatura = DB::table('assinaturas as ass')
            ->join('alunos as al', 'al.id', '=', 'ass.aluno_id')
            ->join('assinatura_status as s', 's.id', '=', 'ass.status_id')
            ->where('ass.id', $assinaturaId)
            ->where('ass.tenant_id', $tenantId)
            ->select(['ass.*', 'al.usuario_id', 's.codigo as status_codigo', 'ass.gateway_assinatura_id as mp_preapproval_id'])
            ->first();

        if (! $assinatura) {
            return ['status' => 404, 'body' => ['error' => 'Assinatura não encontrada']];
        }

        $assinatura = (array) $assinatura;

        if ((int) $assinatura['usuario_id'] !== $userId) {
            return ['status' => 403, 'body' => ['error' => 'Sem permissão para cancelar esta assinatura']];
        }

        if ($assinatura['status_codigo'] === 'cancelada') {
            return ['status' => 400, 'body' => ['error' => 'Assinatura já está cancelada']];
        }

        if (! empty($assinatura['mp_preapproval_id'])) {
            try {
                (new MercadoPagoService($tenantId))->cancelarAssinatura($assinatura['mp_preapproval_id']);
            } catch (\Throwable) {
            }
        }

        $statusCanceladaId = DB::table('assinatura_status')->where('codigo', 'cancelada')->value('id') ?: 4;
        $tipoCancelamentoId = DB::table('assinatura_cancelamento_tipos')->where('codigo', 'usuario')->value('id') ?: 1;

        DB::table('assinaturas')->where('id', $assinaturaId)->update([
            'status_id' => $statusCanceladaId,
            'status_gateway' => 'cancelled',
            'motivo_cancelamento' => $body['motivo'] ?? 'Cancelado pelo usuário',
            'cancelado_por_id' => $tipoCancelamentoId,
            'atualizado_em' => now(),
        ]);

        $statusMatriculaCanceladaId = DB::table('status_matricula')->where('codigo', 'cancelada')->value('id') ?: 3;

        $matriculasCanceladas = DB::table('matriculas')
            ->where('tenant_id', $tenantId)
            ->where('aluno_id', $assinatura['aluno_id'])
            ->whereIn('status_id', function ($q) {
                $q->select('id')->from('status_matricula')->whereIn('codigo', ['ativa', 'vencida']);
            })
            ->whereIn('id', function ($q) use ($assinaturaId) {
                $q->select('matricula_id')->from('assinaturas')->where('id', $assinaturaId)->whereNotNull('matricula_id');
            })
            ->update(['status_id' => $statusMatriculaCanceladaId, 'updated_at' => now()]);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura cancelada com sucesso',
                'matriculas_canceladas' => $matriculasCanceladas,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function aprovadasHoje(int $userId, int $tenantId, int $matriculaId): array
    {
        if ($matriculaId <= 0) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'matricula_id é obrigatório']];
        }

        $assinatura = DB::table('assinaturas as a')
            ->join('alunos as al', 'al.id', '=', 'a.aluno_id')
            ->leftJoin('assinatura_status as s', 's.id', '=', 'a.status_id')
            ->where('a.tenant_id', $tenantId)
            ->where('al.usuario_id', $userId)
            ->where('a.matricula_id', $matriculaId)
            ->where(function ($q) {
                $q->where('a.status_gateway', 'approved')
                    ->orWhereIn('s.codigo', ['ativa', 'paga']);
            })
            ->orderByDesc('a.atualizado_em')
            ->first([
                'a.id', 'a.matricula_id', 'a.tipo_cobranca', 'a.status_gateway',
                's.codigo as status_codigo', 's.nome as status_nome',
            ]);

        if ($assinatura) {
            $assinatura = (array) $assinatura;

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'approved' => true,
                    'data' => [
                        'assinatura_id' => (int) $assinatura['id'],
                        'matricula_id' => $matriculaId,
                        'status_gateway' => $assinatura['status_gateway'],
                        'status_codigo' => $assinatura['status_codigo'],
                        'status_nome' => $assinatura['status_nome'],
                    ],
                ],
            ];
        }

        $matricula = DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->join('alunos as al', 'al.id', '=', 'm.aluno_id')
            ->where('m.id', $matriculaId)
            ->where('al.usuario_id', $userId)
            ->where('m.tenant_id', $tenantId)
            ->select(['sm.codigo as status_codigo'])
            ->first();

        if ($matricula && $matricula->status_codigo === 'ativa') {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'approved' => true,
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'status_gateway' => 'approved',
                        'status_codigo' => 'ativa',
                        'status_nome' => 'Ativa',
                        'fonte' => 'matricula_ativa',
                    ],
                ],
            ];
        }

        $pendente = DB::table('assinaturas as a')
            ->join('alunos as al', 'al.id', '=', 'a.aluno_id')
            ->leftJoin('assinatura_status as s', 's.id', '=', 'a.status_id')
            ->where('a.tenant_id', $tenantId)
            ->where('al.usuario_id', $userId)
            ->where('a.matricula_id', $matriculaId)
            ->orderByDesc('a.atualizado_em')
            ->first([
                'a.id', 'a.external_reference', 'a.status_gateway',
                's.codigo as status_codigo', 's.nome as status_nome',
            ]);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'approved' => false,
                'data' => $pendente ? [
                    'assinatura_id' => (int) $pendente->id,
                    'matricula_id' => $matriculaId,
                    'status_gateway' => $pendente->status_gateway ?? 'pending',
                    'status_codigo' => $pendente->status_codigo ?? 'pendente',
                    'status_nome' => $pendente->status_nome ?? 'Pendente',
                ] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  string|null  $ultimaCobrancaFallback  data de pagamento do webhook (date_approved)
     * @return array{data_inicio: ?string, data_fim: ?string, proxima_cobranca: ?string, ultima_cobranca: ?string}
     */
    private function alinharDatasAssinaturaComFaturas(array $row, ?string $ultimaCobrancaFallback = null): array
    {
        $dataInicio = $row['matricula_data_inicio'] ?? $row['data_inicio'] ?? null;
        $proximaParcela = $row['proxima_parcela_vencimento'] ?? null;
        $proximaMatricula = $row['matricula_proxima_vencimento'] ?? null;
        $acessoPago = $proximaMatricula ?: ($row['ultimo_vencimento_pago'] ?? null);
        $isAvulso = ($row['tipo_cobranca'] ?? '') === 'avulso';

        if ($isAvulso) {
            // Avulso: fim = período pago; próxima cobrança = parcela aberta.
            $dataFim = $acessoPago ?: ($row['data_fim'] ?? null);
            $proximaCobranca = $proximaParcela ?: ($row['proxima_cobranca'] ?? null);
        } else {
            $proximaOficial = $proximaParcela ?: $acessoPago;
            $dataFim = $row['data_fim'] ?? null;
            $proximaCobranca = $proximaOficial ?: ($row['proxima_cobranca'] ?? null);
        }

        $ultimaCobranca = $row['ultima_parcela_pagamento'] ?? null;
        if (!$ultimaCobranca && $ultimaCobrancaFallback) {
            $parsed = strtotime($ultimaCobrancaFallback);
            if ($parsed !== false) {
                $ultimaCobranca = date('Y-m-d', $parsed);
            }
        } elseif (!$ultimaCobranca && ! empty($row['ultima_cobranca_mp'])) {
            $parsed = strtotime((string) $row['ultima_cobranca_mp']);
            if ($parsed !== false) {
                $ultimaCobranca = date('Y-m-d', $parsed);
            }
        } elseif (!$ultimaCobranca) {
            $ultimaCobranca = $row['ultima_cobranca'] ?? null;
        }
        if (is_string($ultimaCobranca) && str_contains($ultimaCobranca, ' ')) {
            $ultimaCobranca = substr($ultimaCobranca, 0, 10);
        }

        return [
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'proxima_cobranca' => $proximaCobranca,
            'ultima_cobranca' => $ultimaCobranca,
        ];
    }
}
