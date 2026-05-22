<?php

namespace App\Services\Mobile;

use App\Repositories\MatriculaRepository;
use App\Services\MercadoPagoService;
use App\Support\MetodoPagamentoResolver;
use Illuminate\Support\Facades\DB;

class MobilePagamentoService
{
    public function __construct(
        private readonly MatriculaRepository $matriculas,
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
                return ['status' => 403, 'body' => ['success' => false, 'error' => 'Matrícula já está ativa']];
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
