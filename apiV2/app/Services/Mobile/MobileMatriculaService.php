<?php

namespace App\Services\Mobile;

use App\Repositories\MatriculaRepository;
use Illuminate\Support\Facades\DB;

class MobileMatriculaService
{
    public function __construct(
        private readonly MatriculaRepository $matriculas,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function detalhe(int $userId, ?int $tenantId, int $matriculaId): array
    {
        if (! $tenantId) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'error' => 'Nenhum tenant selecionado',
                    'code' => 'TENANT_NAO_SELECIONADO',
                ],
            ];
        }

        if ($matriculaId <= 0) {
            return [
                'status' => 400,
                'body' => ['success' => false, 'error' => 'ID da matrícula não informado'],
            ];
        }

        $matricula = $this->matriculas->findDetalhePorUsuario($matriculaId, $userId, $tenantId);
        if (! $matricula) {
            return [
                'status' => 404,
                'body' => ['success' => false, 'error' => 'Matrícula não encontrada'],
            ];
        }

        $plano = $this->matriculas->findPlanoResumo((int) $matricula['plano_id']);
        $pagamentos = $this->matriculas->listarPagamentosMatricula($matriculaId);

        $pagamentosFormatados = array_map(fn (array $p) => [
            'id' => (int) $p['id'],
            'valor' => (float) $p['valor'],
            'data_vencimento' => $p['data_vencimento'],
            'data_pagamento' => $p['data_pagamento'],
            'status' => $p['status_pagamento_nome'],
            'forma_pagamento' => $p['forma_pagamento_nome'],
            'pendente' => $p['data_pagamento'] === null,
        ], $pagamentos);

        $totalPago = array_sum(array_map(
            fn ($p) => $p['data_pagamento'] ? (float) $p['valor'] : 0,
            $pagamentos,
        ));
        $totalPendente = array_sum(array_map(
            fn ($p) => ! $p['data_pagamento'] ? (float) $p['valor'] : 0,
            $pagamentos,
        ));

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'matricula' => [
                        'id' => (int) $matricula['id'],
                        'usuario' => $matricula['usuario_nome'],
                        'plano' => $plano ? [
                            'nome' => $plano['nome'],
                            'valor' => (float) $plano['valor'],
                            'duracao_dias' => (int) $plano['duracao_dias'],
                            'checkins_semanais' => (int) $plano['checkins_semanais'],
                        ] : null,
                        'datas' => [
                            'matricula' => $matricula['data_matricula'],
                            'inicio' => $matricula['data_inicio'],
                            'vencimento' => $matricula['data_vencimento'],
                        ],
                        'valor_total' => (float) $matricula['valor'],
                        'status' => $matricula['status'],
                        'motivo' => $matricula['motivo'],
                    ],
                    'pagamentos' => $pagamentosFormatados,
                    'resumo_financeiro' => [
                        'total_previsto' => (float) $matricula['valor'],
                        'total_pago' => (float) $totalPago,
                        'total_pendente' => (float) $totalPendente,
                        'quantidade_pagamentos' => count($pagamentos),
                        'pagamentos_realizados' => count(array_filter(
                            $pagamentos,
                            fn ($p) => $p['data_pagamento'] !== null,
                        )),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function reabrirPagamento(int $userId, int $tenantId, int $matriculaId): array
    {
        if ($matriculaId <= 0) {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'matriculaId inválido']];
        }

        $matricula = $this->matriculas->findPendenteReabrir($matriculaId, $userId, $tenantId);
        if (! $matricula) {
            return ['status' => 404, 'body' => ['success' => false, 'message' => 'Matrícula não encontrada']];
        }

        if (($matricula['status_codigo'] ?? '') !== 'pendente') {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'A matrícula não está pendente']];
        }

        $assinatura = $this->matriculas->findUltimaAssinatura($matriculaId, $tenantId);
        $pixSalvo = $this->matriculas->findUltimoPix($tenantId, $matriculaId);
        $vencimento = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'];

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Pagamento pendente encontrado',
                'data' => [
                    'matricula_id' => (int) $matricula['id'],
                    'plano_id' => (int) $matricula['plano_id'],
                    'plano_ciclo_id' => $matricula['plano_ciclo_id'] ? (int) $matricula['plano_ciclo_id'] : null,
                    'plano_nome' => $matricula['plano_nome'],
                    'modalidade' => $matricula['modalidade_nome'],
                    'valor' => (float) $matricula['valor'],
                    'valor_formatado' => 'R$ '.number_format((float) $matricula['valor'], 2, ',', '.'),
                    'status' => 'pendente',
                    'data_inicio' => $matricula['data_inicio'],
                    'data_vencimento' => $vencimento,
                    'vencida' => $vencimento < date('Y-m-d'),
                    'payment_url' => $assinatura['payment_url'] ?? null,
                    'preference_id' => $assinatura['gateway_preference_id'] ?? null,
                    'tipo_pagamento' => ($assinatura['tipo_cobranca'] ?? '') === 'avulso' ? 'pagamento_unico' : 'assinatura',
                    'tipo_cobranca' => $assinatura['tipo_cobranca'] ?? 'avulso',
                    'recorrente' => ($assinatura['tipo_cobranca'] ?? '') === 'recorrente',
                    'pix' => $pixSalvo ? [
                        'payment_id' => $pixSalvo['payment_id'] ?? null,
                        'status' => $pixSalvo['status'] ?? null,
                        'status_detail' => null,
                        'qr_code' => $pixSalvo['qr_code'] ?? null,
                        'qr_code_base64' => $pixSalvo['qr_code_base64'] ?? null,
                        'ticket_url' => $pixSalvo['ticket_url'] ?? null,
                        'expires_at' => $pixSalvo['expires_at'] ?? null,
                    ] : null,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function verificarPagamento(int $userId, ?int $tenantId, array $body): array
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

        $matriculaId = $body['matricula_id'] ?? null;
        if (! $matriculaId) {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'matricula_id é obrigatório']];
        }

        $matricula = $this->matriculas->findComStatusPorUsuario((int) $matriculaId, $userId, $tenantId);
        if (! $matricula) {
            return ['status' => 404, 'body' => ['success' => false, 'message' => 'Matrícula não encontrada']];
        }

        if ($matricula['status_codigo'] === 'ativa') {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Matrícula já está ativa',
                    'data' => ['matricula_id' => (int) $matriculaId, 'status' => 'ativa'],
                ],
            ];
        }

        if ($this->matriculas->ativarSePendente((int) $matriculaId)) {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Pagamento confirmado! Matrícula ativada com sucesso.',
                    'data' => ['matricula_id' => (int) $matriculaId, 'status' => 'ativa'],
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'success' => false,
                'message' => 'Não foi possível atualizar a matrícula. Status atual: '.$matricula['status_codigo'],
                'data' => ['matricula_id' => (int) $matriculaId, 'status' => $matricula['status_codigo']],
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelarDiaria(int $userId, int $tenantId, int $matriculaId): array
    {
        if ($matriculaId <= 0) {
            return ['status' => 400, 'body' => ['success' => false, 'message' => 'matricula_id inválido']];
        }

        try {
            $matricula = DB::table('matriculas as m')
                ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
                ->join('planos as p', 'p.id', '=', 'm.plano_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->where('m.id', $matriculaId)
                ->where('a.usuario_id', $userId)
                ->where('m.tenant_id', $tenantId)
                ->select([
                    'm.id', 'm.aluno_id', 'm.data_inicio', 'm.data_vencimento',
                    'm.tipo_cobranca', 'sm.codigo as status_codigo', 'p.duracao_dias', 'p.modalidade_id',
                ])
                ->first();

            if (! $matricula) {
                return ['status' => 404, 'body' => ['success' => false, 'message' => 'Matrícula não encontrada']];
            }

            $matricula = (array) $matricula;

            if (($matricula['tipo_cobranca'] ?? '') !== 'avulso' || (int) ($matricula['duracao_dias'] ?? 0) !== 1) {
                return ['status' => 400, 'body' => ['success' => false, 'message' => 'Apenas diárias avulsas podem ser canceladas']];
            }

            if (($matricula['status_codigo'] ?? '') === 'cancelada') {
                return ['status' => 200, 'body' => ['success' => true, 'message' => 'Matrícula já está cancelada']];
            }

            $checkins = DB::table('checkins as c')
                ->join('turmas as t', 'c.turma_id', '=', 't.id')
                ->join('dias as d', 't.dia_id', '=', 'd.id')
                ->where('c.aluno_id', $matricula['aluno_id'])
                ->where('c.tenant_id', $tenantId)
                ->where('t.modalidade_id', $matricula['modalidade_id'])
                ->whereBetween('d.data', [$matricula['data_inicio'], $matricula['data_vencimento'] ?? $matricula['data_inicio']])
                ->selectRaw('COUNT(*) as total_checkins, SUM(CASE WHEN c.presente = 1 THEN 1 ELSE 0 END) as presentes')
                ->first();

            if ((int) ($checkins->total_checkins ?? 0) > 0 || (int) ($checkins->presentes ?? 0) > 0) {
                return [
                    'status' => 400,
                    'body' => ['success' => false, 'message' => 'Não é possível cancelar: já existe check-in realizado para esta diária'],
                ];
            }

            DB::transaction(function () use ($matriculaId, $tenantId) {
                $statusCanceladaId = DB::table('status_matricula')->where('codigo', 'cancelada')->value('id');
                if ($statusCanceladaId === null) {
                    throw new \RuntimeException("Status 'cancelada' não encontrado");
                }

                DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)
                    ->update(['status_id' => (int) $statusCanceladaId, 'updated_at' => now()]);

                $statusAssCancelId = DB::table('assinatura_status')->where('codigo', 'cancelada')->value('id');
                if ($statusAssCancelId) {
                    DB::table('assinaturas')
                        ->where('matricula_id', $matriculaId)
                        ->where('tenant_id', $tenantId)
                        ->where('tipo_cobranca', 'avulso')
                        ->update(['status_id' => $statusAssCancelId, 'status_gateway' => 'cancelled', 'atualizado_em' => now()]);
                }

                $statusPagCancelId = DB::table('status_pagamento')->where('codigo', 'cancelado')->value('id')
                    ?: DB::table('status_pagamento')->where('nome', 'Cancelado')->value('id');
                if ($statusPagCancelId) {
                    DB::table('pagamentos_plano')
                        ->where('matricula_id', $matriculaId)
                        ->where('tenant_id', $tenantId)
                        ->update(['status_pagamento_id' => $statusPagCancelId, 'updated_at' => now()]);
                }
            });

            return ['status' => 200, 'body' => ['success' => true, 'message' => 'Compra da diária cancelada com sucesso']];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['success' => false, 'message' => 'Erro ao cancelar diária']];
        }
    }
}
