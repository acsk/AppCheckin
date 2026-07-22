<?php

namespace App\Services\Mobile;

use App\Repositories\MatriculaRepository;
use App\Services\MercadoPagoService;
use App\Services\PagamentoPlanoService;
use App\Support\MetodoPagamentoResolver;
use Illuminate\Support\Facades\DB;

class MobilePagamentoService
{
    public function __construct(
        private readonly MatriculaRepository $matriculas,
        private readonly PagamentoPlanoService $pagamentosPlano,
    ) {}

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function gerarPix(int $userId, ?int $tenantId, array $body): array
    {
        if (! $tenantId) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'Nenhum tenant selecionado']];
        }

        $matriculaId = (int) ($body['matricula_id'] ?? 0);
        if ($matriculaId <= 0) {
            return ['status' => 400, 'body' => ['success' => false, 'error' => 'matricula_id é obrigatório']];
        }

        try {
            $matricula = DB::table('matriculas as m')
                ->join('alunos as a', 'a.id', '=', 'm.aluno_id')
                ->join('usuarios as u', 'u.id', '=', 'a.usuario_id')
                ->join('planos as p', 'p.id', '=', 'm.plano_id')
                ->leftJoin('modalidades as md', 'md.id', '=', 'p.modalidade_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->where('m.id', $matriculaId)
                ->where('m.tenant_id', $tenantId)
                ->where('u.id', $userId)
                ->select([
                    'm.id', 'm.valor', 'm.tipo_cobranca', 'm.plano_id', 'm.plano_ciclo_id',
                    'm.proxima_data_vencimento', 'sm.codigo as status_codigo',
                    'p.nome as plano_nome', 'md.nome as modalidade_nome',
                    'a.id as aluno_id', 'u.id as usuario_id', 'u.nome as aluno_nome',
                    'u.email as aluno_email', 'u.telefone as aluno_telefone', 'u.cpf as aluno_cpf',
                ])
                ->first();

            if (! $matricula) {
                return ['status' => 404, 'body' => ['success' => false, 'error' => 'Matrícula não encontrada']];
            }

            $matricula = (array) $matricula;

            if (($matricula['tipo_cobranca'] ?? '') === 'recorrente') {
                return ['status' => 403, 'body' => ['success' => false, 'error' => 'PIX não disponível para matrícula recorrente']];
            }

            if (($matricula['status_codigo'] ?? '') === 'ativa') {
                $parcelaRen = DB::table('pagamentos_plano')
                    ->where('tenant_id', $tenantId)
                    ->where('matricula_id', $matriculaId)
                    ->whereIn('status_pagamento_id', [1, 3])
                    ->whereNull('data_pagamento')
                    ->orderBy('data_vencimento')
                    ->orderBy('id')
                    ->first(['id', 'data_vencimento']);

                $hoje = date('Y-m-d');
                $acessoAte = $matricula['proxima_data_vencimento'] ?? null;
                $parcelaVenc = $parcelaRen->data_vencimento ?? null;
                $liberar = ($acessoAte && $acessoAte <= $hoje)
                    || ($parcelaVenc && $parcelaVenc <= $hoje);

                if (! $liberar) {
                    $planoInfo = DB::table('matriculas as m')
                        ->join('planos as p', 'p.id', '=', 'm.plano_id')
                        ->leftJoin('plano_ciclos as pc', function ($join) {
                            $join->on('pc.id', '=', 'm.plano_ciclo_id')
                                ->on('pc.tenant_id', '=', 'm.tenant_id');
                        })
                        ->where('m.id', $matriculaId)
                        ->where('m.tenant_id', $tenantId)
                        ->first([
                            'p.checkins_semanais',
                            'p.modalidade_id',
                            DB::raw('CASE WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE(pc.permite_reposicao, 0) ELSE 1 END as permite_reposicao'),
                            DB::raw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) as vencimento'),
                        ]);

                    if ($planoInfo && (int) ($planoInfo->permite_reposicao ?? 0) === 1 && (int) ($planoInfo->checkins_semanais ?? 0) > 0) {
                        $venc = (string) ($planoInfo->vencimento ?? '');
                        if ($venc !== '' && $venc !== '0000-00-00') {
                            $addMonthsAnchor = static function (\DateTime $base, int $months, int $anchorDay): \DateTime {
                                $y = (int) $base->format('Y');
                                $m = (int) $base->format('m') + $months;
                                while ($m > 12) {
                                    $m -= 12;
                                    $y++;
                                }
                                while ($m < 1) {
                                    $m += 12;
                                    $y--;
                                }
                                $ultimo = (int) (new \DateTime(sprintf('%04d-%02d-01', $y, $m)))->format('t');
                                $dt = new \DateTime();
                                $dt->setDate($y, $m, min($anchorDay, $ultimo));

                                return $dt;
                            };

                            $vencDt = new \DateTime($venc);
                            $anchorDay = (int) $vencDt->format('j');
                            $hojeDt = new \DateTime($hoje);
                            $fim = clone $vencDt;
                            if ($fim <= $hojeDt) {
                                while ($fim <= $hojeDt) {
                                    $fim = $addMonthsAnchor($fim, 1, $anchorDay);
                                }
                            } else {
                                while (true) {
                                    $anterior = $addMonthsAnchor($fim, -1, $anchorDay);
                                    if ($anterior > $hojeDt) {
                                        $fim = $anterior;
                                    } else {
                                        break;
                                    }
                                }
                            }
                            $inicio = $addMonthsAnchor($fim, -1, $anchorDay);
                            $dias = (int) $inicio->diff($fim)->days;
                            $semanas = (int) ceil(max(1, $dias) / 7);
                            $bonus = ($semanas >= 5) ? 1 : 0;
                            $limite = ((int) $planoInfo->checkins_semanais) * 4 + $bonus;

                            $q = DB::table('checkins as c')
                                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                                ->join('turmas as t', 't.id', '=', 'c.turma_id')
                                ->join('dias as d', 'd.id', '=', 't.dia_id')
                                ->where('a.usuario_id', $userId)
                                ->where('d.data', '>=', $inicio->format('Y-m-d'))
                                ->where('d.data', '<', $fim->format('Y-m-d'))
                                ->where(function ($w) {
                                    $w->whereNull('c.presente')->orWhere('c.presente', 1);
                                });
                            if (! empty($planoInfo->modalidade_id)) {
                                $q->where('t.modalidade_id', (int) $planoInfo->modalidade_id);
                            }
                            $liberar = ((int) $q->count()) >= $limite;
                        }
                    }
                }

                if (! $liberar) {
                    return [
                        'status' => 403,
                        'body' => [
                            'success' => false,
                            'code' => 'RENOVACAO_AINDA_NAO_LIBERADA',
                            'error' => 'Sua matrícula ainda está ativa neste ciclo. Você pode renovar quando o limite de check-ins do ciclo acabar ou no vencimento.',
                            'detalhes' => [
                                'acesso_ate' => $acessoAte,
                                'parcela_vencimento' => $parcelaVenc,
                            ],
                        ],
                    ];
                }
            }

            $cpfPix = preg_replace('/[^0-9]/', '', $matricula['aluno_cpf'] ?? '');
            if (strlen($cpfPix) !== 11) {
                return ['status' => 400, 'body' => ['success' => false, 'error' => 'CPF válido é obrigatório para pagamento PIX']];
            }

            $pixSalvo = $this->matriculas->findUltimoPix($tenantId, $matriculaId);
            if ($pixSalvo && ! empty($pixSalvo['expires_at']) && strtotime($pixSalvo['expires_at']) >= time()) {
                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'data' => [
                            'matricula_id' => $matriculaId,
                            'valor' => (float) $matricula['valor'],
                            'pix' => $this->formatPixPayload($pixSalvo),
                        ],
                    ],
                ];
            }

            $dataVencParcela = $matricula['proxima_data_vencimento'] ?? date('Y-m-d');
            $this->pagamentosPlano->garantirParcelaPendenteUnica(
                $tenantId,
                (int) $matricula['aluno_id'],
                $matriculaId,
                (int) $matricula['plano_id'],
                (float) $matricula['valor'],
                $dataVencParcela,
                $userId
            );

            $academiaNome = DB::table('tenants')->where('id', $tenantId)->value('nome') ?? 'Academia';

            $dadosPagamento = [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'aluno_id' => (int) $matricula['aluno_id'],
                'usuario_id' => (int) $matricula['usuario_id'],
                'aluno_nome' => $matricula['aluno_nome'],
                'aluno_email' => $matricula['aluno_email'],
                'aluno_telefone' => $matricula['aluno_telefone'] ?? '',
                'aluno_cpf' => $matricula['aluno_cpf'],
                'plano_nome' => $matricula['plano_nome'],
                'descricao' => "{$matricula['plano_nome']} - {$matricula['modalidade_nome']}",
                'valor' => (float) $matricula['valor'],
                'academia_nome' => $academiaNome,
            ];

            $mercadoPago = new MercadoPagoService($tenantId);
            $pixData = $mercadoPago->criarPagamentoPix($dadosPagamento);

            $this->salvarPix($tenantId, $matriculaId, $pixData);
            $this->atualizarAssinaturaPix($tenantId, $matricula, $pixData);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [
                        'matricula_id' => $matriculaId,
                        'valor' => (float) $matricula['valor'],
                        'pix' => [
                            'payment_id' => $pixData['id'] ?? null,
                            'status' => $pixData['status'] ?? null,
                            'status_detail' => $pixData['status_detail'] ?? null,
                            'qr_code' => $pixData['qr_code'] ?? null,
                            'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                            'ticket_url' => $pixData['ticket_url'] ?? null,
                            'expires_at' => $pixData['date_of_expiration'] ?? null,
                        ],
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['success' => false, 'error' => 'Erro ao gerar PIX', 'message' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $pixData
     */
    private function salvarPix(int $tenantId, int $matriculaId, array $pixData): void
    {
        if (empty($pixData['id']) || empty($pixData['ticket_url'])) {
            return;
        }

        DB::table('pagamentos_pix')->insert([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'payment_id' => (string) $pixData['id'],
            'ticket_url' => $pixData['ticket_url'] ?? null,
            'qr_code' => $pixData['qr_code'] ?? null,
            'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
            'expires_at' => isset($pixData['date_of_expiration'])
                ? date('Y-m-d H:i:s', strtotime($pixData['date_of_expiration']))
                : null,
            'status' => $pixData['status'] ?? 'pending',
        ]);
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @param  array<string, mixed>  $pixData
     */
    private function atualizarAssinaturaPix(int $tenantId, array $matricula, array $pixData): void
    {
        $paymentIdPix = $pixData['id'] ?? null;
        $paymentUrlPix = $pixData['ticket_url'] ?? null;
        if (! $paymentIdPix || ! $paymentUrlPix) {
            return;
        }

        $externalReference = $pixData['external_reference'] ?? ('MAT-'.$matricula['id'].'-'.time());
        $assinaturaId = DB::table('assinaturas')
            ->where('matricula_id', $matricula['id'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->value('id');

        if ($assinaturaId) {
            DB::table('assinaturas')->where('id', $assinaturaId)->where('tenant_id', $tenantId)->update([
                'gateway_preference_id' => (string) $paymentIdPix,
                'external_reference' => $externalReference,
                'payment_url' => $paymentUrlPix,
                'status_gateway' => 'pending',
                'atualizado_em' => now(),
            ]);

            return;
        }

        $gatewayId = DB::table('assinatura_gateways')->where('codigo', 'mercadopago')->value('id') ?: 1;
        $statusId = DB::table('assinatura_status')->where('codigo', 'pendente')->value('id') ?: 1;
        $metodoPagamentoId = MetodoPagamentoResolver::resolve('pix');

        $insert = [
            'tenant_id' => $tenantId,
            'matricula_id' => (int) $matricula['id'],
            'aluno_id' => (int) $matricula['aluno_id'],
            'plano_id' => (int) $matricula['plano_id'],
            'gateway_id' => $gatewayId,
            'gateway_preference_id' => (string) $paymentIdPix,
            'external_reference' => $externalReference,
            'payment_url' => $paymentUrlPix,
            'status_id' => $statusId,
            'status_gateway' => 'pending',
            'valor' => (float) $matricula['valor'],
            'frequencia_id' => 4,
            'dia_cobranca' => (int) date('d'),
            'data_inicio' => date('Y-m-d'),
            'data_fim' => null,
            'proxima_cobranca' => $matricula['proxima_data_vencimento'] ?? null,
            'tipo_cobranca' => 'avulso',
            'criado_em' => now(),
        ];

        if ($metodoPagamentoId !== null) {
            $insert['metodo_pagamento_id'] = $metodoPagamentoId;
        }

        DB::table('assinaturas')->insert($insert);
    }

    /**
     * @param  array<string, mixed>  $pixSalvo
     * @return array<string, mixed>
     */
    private function formatPixPayload(array $pixSalvo): array
    {
        return [
            'payment_id' => $pixSalvo['payment_id'] ?? null,
            'status' => $pixSalvo['status'] ?? null,
            'status_detail' => null,
            'qr_code' => $pixSalvo['qr_code'] ?? null,
            'qr_code_base64' => $pixSalvo['qr_code_base64'] ?? null,
            'ticket_url' => $pixSalvo['ticket_url'] ?? null,
            'expires_at' => $pixSalvo['expires_at'] ?? null,
        ];
    }
}
