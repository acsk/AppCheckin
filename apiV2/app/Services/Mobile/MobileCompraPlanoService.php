<?php

namespace App\Services\Mobile;

use App\Repositories\AlunoRepository;
use App\Repositories\MatriculaRepository;
use App\Repositories\UsuarioRepository;
use App\Services\MercadoPagoService;
use App\Services\PagamentoPlanoService;
use App\Support\MetodoPagamentoResolver;
use App\Support\MobilePagamentoMetodos;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobileCompraPlanoService
{
    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly AlunoRepository $alunos,
        private readonly MatriculaRepository $matriculas,
        private readonly PagamentoPlanoService $pagamentosPlano,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function comprar(int $userId, ?int $tenantId, array $data): array
    {
        if (! $tenantId) {
            return $this->erro(400, 'TENANT_NAO_SELECIONADO', 'Nenhum tenant selecionado');
        }

        if (empty($data['plano_id'])) {
            return $this->erro(400, 'PLANO_OBRIGATORIO', 'Plano é obrigatório');
        }

        $planoId = (int) $data['plano_id'];
        $planoCicloId = ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;
        $diaVencimento = isset($data['dia_vencimento']) ? (int) $data['dia_vencimento'] : 5;
        $metodoPagamento = strtolower(trim((string) ($data['metodo_pagamento'] ?? 'checkout')));

        if (! in_array($metodoPagamento, ['checkout', 'pix'], true)) {
            return $this->erro(400, 'METODO_PAGAMENTO_INVALIDO', 'Método de pagamento inválido. Use pix ou checkout.');
        }

        $pagamentoFlags = MobilePagamentoMetodos::flags($tenantId);
        $habilitarCartao = $pagamentoFlags['habilitar_cartao_credito'];
        $habilitarPix = $pagamentoFlags['habilitar_pix'];
        $metodoPagamento = MobilePagamentoMetodos::normalizarMetodo(
            $metodoPagamento,
            $habilitarCartao,
            $habilitarPix
        );

        if ($metodoPagamento === 'pix' && ! $habilitarPix) {
            return $this->erro(400, 'PIX_NAO_DISPONIVEL', 'Pagamento PIX não está habilitado para esta academia');
        }

        if ($metodoPagamento === 'checkout' && ! $habilitarCartao) {
            return $this->erro(400, 'CHECKOUT_NAO_DISPONIVEL', 'Pagamento por checkout não está disponível. Use PIX.');
        }

        if ($diaVencimento < 1 || $diaVencimento > 31) {
            return $this->erro(400, 'DIA_VENCIMENTO_INVALIDO', 'Dia de vencimento deve estar entre 1 e 31');
        }

        $alunoId = $this->alunos->findIdByUsuario($userId);
        if (! $alunoId) {
            return $this->erro(404, 'ALUNO_NAO_ENCONTRADO', 'Perfil de aluno não encontrado');
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return $this->erro(404, 'USUARIO_NAO_ENCONTRADO', 'Usuário não encontrado');
        }

        $plano = DB::table('planos as p')
            ->leftJoin('modalidades as m', 'p.modalidade_id', '=', 'm.id')
            ->where('p.id', $planoId)
            ->where('p.tenant_id', $tenantId)
            ->where('p.ativo', 1)
            ->first(['p.*', 'm.nome as modalidade_nome']);

        if (! $plano) {
            return $this->erro(404, 'PLANO_NAO_ENCONTRADO', 'Plano não encontrado ou inativo');
        }

        $plano = (array) $plano;
        $valorCompra = (float) $plano['valor'];
        $duracaoMeses = 1;
        $cicloNome = 'Mensal';
        $permiteRecorrenciaCiclo = false;

        if ($planoCicloId) {
            $ciclo = DB::table('plano_ciclos as pc')
                ->join('assinatura_frequencias as af', 'af.id', '=', 'pc.assinatura_frequencia_id')
                ->where('pc.id', $planoCicloId)
                ->where('pc.plano_id', $planoId)
                ->where('pc.tenant_id', $tenantId)
                ->where('pc.ativo', 1)
                ->first(['pc.*', 'af.nome as ciclo_nome']);

            if (! $ciclo) {
                return $this->erro(404, 'CICLO_NAO_ENCONTRADO', 'Ciclo de pagamento não encontrado');
            }

            $ciclo = (array) $ciclo;
            $valorCompra = (float) $ciclo['valor'];
            $duracaoMeses = (int) $ciclo['meses'];
            $cicloNome = $ciclo['ciclo_nome'];
            $permiteRecorrenciaCiclo = (bool) $ciclo['permite_recorrencia'];
        }

        $isRecorrente = MobilePagamentoMetodos::isRecorrenteEfetivo(
            $permiteRecorrenciaCiclo,
            $habilitarCartao
        );

        if ($valorCompra <= 0) {
            return $this->erro(400, 'PLANO_INVALIDO', 'Este plano não está disponível para compra');
        }

        if ($valorCompra < 0.50) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'VALOR_MINIMO',
                    'message' => 'O valor mínimo para pagamento é R$ 0,50',
                    'valor_atual' => $valorCompra,
                    'valor_minimo' => 0.50,
                ],
            ];
        }

        $modalidadeId = $plano['modalidade_id'];

        $matriculaAtiva = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('p.modalidade_id', $modalidadeId)
            ->where('sm.codigo', 'ativa')
            ->whereRaw('COALESCE(m.proxima_data_vencimento, m.data_vencimento) >= CURDATE()')
            ->exists();

        if ($matriculaAtiva) {
            return $this->erro(400, 'MATRICULA_ATIVA_EXISTENTE', 'Você já possui uma matrícula ativa nesta modalidade');
        }

        $pendente = DB::table('matriculas as m')
            ->join('planos as p', 'p.id', '=', 'm.plano_id')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.aluno_id', $alunoId)
            ->where('m.tenant_id', $tenantId)
            ->where('p.modalidade_id', $modalidadeId)
            ->where('sm.codigo', 'pendente')
            ->orderByDesc('m.updated_at')
            ->orderByDesc('m.id')
            ->first([
                'm.id', 'm.plano_id', 'm.plano_ciclo_id', 'm.valor', 'm.data_inicio',
                'm.data_vencimento', 'm.proxima_data_vencimento',
            ]);

        if ($pendente) {
            $pendente = (array) $pendente;
            $mesmaEscolha = (int) $pendente['plano_id'] === $planoId
                && ((int) ($pendente['plano_ciclo_id'] ?? 0) === (int) ($planoCicloId ?? 0));

            if ($mesmaEscolha) {
                return $this->respostaPendenteExistente(
                    $tenantId,
                    $userId,
                    $pendente,
                    $plano,
                    $metodoPagamento,
                    $usuario,
                    $alunoId,
                );
            }
        }

        try {
            $dataInicio = date('Y-m-d');
            $dataVencimento = new DateTime($dataInicio);
            if ($duracaoMeses > 1) {
                $dataVencimento->modify("+{$duracaoMeses} months");
            } else {
                $dataVencimento->modify('+'.(int) $plano['duracao_dias'].' days');
            }
            $proximaDataVencimento = clone $dataVencimento;

            $statusPendenteId = (int) (DB::table('status_matricula')->where('codigo', 'pendente')->value('id') ?? 5);
            $tipoCobrancaMatricula = $isRecorrente ? 'recorrente' : 'avulso';

            $matriculaExistente = DB::table('matriculas as m')
                ->join('planos as p', 'p.id', '=', 'm.plano_id')
                ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
                ->where('m.aluno_id', $alunoId)
                ->where('m.tenant_id', $tenantId)
                ->where('p.modalidade_id', $modalidadeId)
                ->orderByDesc('m.updated_at')
                ->orderByDesc('m.id')
                ->first([
                    'm.id', 'm.plano_id', 'm.plano_ciclo_id', 'm.valor',
                    'm.data_inicio', 'm.data_vencimento', 'm.proxima_data_vencimento',
                    'sm.codigo as status_codigo',
                ]);

            $reutilizandoMatricula = false;
            $planoAnteriorId = null;
            $motivoCodigo = 'nova';

            if ($matriculaExistente) {
                $matriculaExistente = (array) $matriculaExistente;
                $vencimento = $matriculaExistente['proxima_data_vencimento'] ?? $matriculaExistente['data_vencimento'];
                $vencidaPorData = $vencimento && $vencimento < date('Y-m-d');
                $statusCodigo = $matriculaExistente['status_codigo'];

                if ($vencidaPorData && $statusCodigo !== 'vencida') {
                    $statusVencidaId = DB::table('status_matricula')->where('codigo', 'vencida')->value('id');
                    if ($statusVencidaId) {
                        DB::table('matriculas')->where('id', $matriculaExistente['id'])->update([
                            'status_id' => $statusVencidaId,
                            'updated_at' => now(),
                        ]);
                        $statusCodigo = 'vencida';
                    }
                }

                if (in_array($statusCodigo, ['vencida', 'cancelada'], true) || $vencidaPorData) {
                    $reutilizandoMatricula = true;
                    $planoAnteriorId = (int) $matriculaExistente['plano_id'];
                    $motivoCodigo = $planoAnteriorId === $planoId
                        ? 'renovacao'
                        : ($valorCompra >= (float) $matriculaExistente['valor'] ? 'upgrade' : 'downgrade');
                }
            }

            $motivoId = (int) (DB::table('motivo_matricula')->where('codigo', $motivoCodigo)->value('id') ?? 1);

            if ($reutilizandoMatricula && $matriculaExistente) {
                $matriculaId = (int) $matriculaExistente['id'];
                Log::info("comprarPlano v2: reutilizando matrícula vencida #{$matriculaId}");

                DB::table('matriculas')->where('id', $matriculaId)->where('tenant_id', $tenantId)->update([
                    'plano_id' => $planoId,
                    'plano_ciclo_id' => $planoCicloId,
                    'tipo_cobranca' => $tipoCobrancaMatricula,
                    'valor' => $valorCompra,
                    'status_id' => $statusPendenteId,
                    'motivo_id' => $motivoId,
                    'plano_anterior_id' => $planoAnteriorId,
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento->format('Y-m-d'),
                    'proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'dia_vencimento' => $diaVencimento,
                    'criado_por' => $userId,
                    'updated_at' => now(),
                ]);

                try {
                    DB::table('historico_planos')->insert([
                        'usuario_id' => $userId,
                        'plano_anterior_id' => $planoAnteriorId,
                        'plano_novo_id' => $planoId,
                        'data_inicio' => $dataInicio,
                        'data_vencimento' => $dataVencimento->format('Y-m-d'),
                        'valor_pago' => $valorCompra,
                        'motivo' => $motivoCodigo,
                        'observacoes' => 'Atualização de matrícula vencida via app (api v2)',
                        'criado_por' => $userId,
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('comprarPlano v2: historico_planos: '.$e->getMessage());
                }

                $n = $this->pagamentosPlano->cancelarParcelasAbertas(
                    $tenantId,
                    $matriculaId,
                    'Cancelado por upgrade/renovação via app'
                );
                if ($n > 0) {
                    Log::info("comprarPlano v2: {$n} parcela(s) cancelada(s) na matrícula {$matriculaId}");
                }
            } else {
                $matriculaId = (int) DB::table('matriculas')->insertGetId([
                    'tenant_id' => $tenantId,
                    'aluno_id' => $alunoId,
                    'plano_id' => $planoId,
                    'plano_ciclo_id' => $planoCicloId,
                    'tipo_cobranca' => $tipoCobrancaMatricula,
                    'data_matricula' => $dataInicio,
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento->format('Y-m-d'),
                    'valor' => $valorCompra,
                    'status_id' => $statusPendenteId,
                    'motivo_id' => $motivoId,
                    'dia_vencimento' => $diaVencimento,
                    'periodo_teste' => 0,
                    'proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'criado_por' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->pagamentosPlano->garantirParcelaPendenteUnica(
                $tenantId,
                $alunoId,
                $matriculaId,
                $planoId,
                $valorCompra,
                $dataInicio,
                $userId
            );

            $academiaNome = DB::table('tenants')->where('id', $tenantId)->value('nome') ?? 'Academia';
            $descricaoCompra = $planoCicloId
                ? "{$plano['nome']} ({$cicloNome}) - {$plano['modalidade_nome']}"
                : "{$plano['nome']} - {$plano['modalidade_nome']}";

            $dadosPagamento = [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'aluno_id' => $alunoId,
                'usuario_id' => $userId,
                'aluno_nome' => $usuario['nome'],
                'aluno_email' => $usuario['email'],
                'aluno_telefone' => $usuario['telefone'] ?? '',
                'aluno_cpf' => $usuario['cpf'] ?? null,
                'plano_nome' => $plano['nome'],
                'descricao' => $descricaoCompra,
                'valor' => $valorCompra,
                'max_parcelas' => 12,
                'academia_nome' => $academiaNome,
                'apenas_cartao' => $isRecorrente,
            ];

            $mp = new MercadoPagoService($tenantId);
            $paymentUrl = null;
            $preferenceId = null;
            $tipoPagamento = 'pagamento_unico';
            $pixData = null;
            $mpError = null;
            $externalReference = "MAT-{$matriculaId}-".time();

            try {
                if ($metodoPagamento === 'pix') {
                    $cpfPix = preg_replace('/[^0-9]/', '', $usuario['cpf'] ?? '');
                    if (strlen($cpfPix) !== 11) {
                        return $this->erro(400, 'CPF_OBRIGATORIO_PIX', 'CPF válido é obrigatório para pagamento PIX');
                    }
                    $pixData = $mp->criarPagamentoPix($dadosPagamento);
                    $tipoPagamento = 'pix';
                    $paymentUrl = $pixData['ticket_url'] ?? null;
                    $preferenceId = $pixData['id'] ?? null;
                    $externalReference = $pixData['external_reference'] ?? $externalReference;
                    $this->salvarPixRegistro($tenantId, $matriculaId, $pixData);
                } elseif ($isRecorrente) {
                    $preferencia = $mp->criarPreferenciaAssinatura($dadosPagamento, $duracaoMeses);
                    $tipoPagamento = 'assinatura';
                    $paymentUrl = $preferencia['init_point'] ?? null;
                    $preferenceId = $preferencia['id'] ?? null;
                    $externalReference = $preferencia['external_reference'] ?? $externalReference;
                } else {
                    $preferencia = $mp->criarPreferenciaPagamento($dadosPagamento);
                    $tipoPagamento = 'pagamento_unico';
                    $paymentUrl = $preferencia['init_point'] ?? null;
                    $preferenceId = $preferencia['id'] ?? null;
                    $externalReference = $preferencia['external_reference'] ?? $externalReference;
                }

                $this->salvarAssinatura(
                    $tenantId,
                    $matriculaId,
                    $alunoId,
                    $planoId,
                    $valorCompra,
                    $isRecorrente,
                    $metodoPagamento,
                    $preferenceId,
                    $paymentUrl,
                    $externalReference,
                    $cicloNome,
                );
            } catch (\Throwable $e) {
                $mpError = $e->getMessage();
            }

            $body = [
                'success' => true,
                'message' => 'Matrícula criada. Complete o pagamento para ativar.',
                'data' => [
                    'matricula_id' => $matriculaId,
                    'plano_id' => $planoId,
                    'plano_ciclo_id' => $planoCicloId,
                    'plano_nome' => $plano['nome'],
                    'ciclo_nome' => $cicloNome,
                    'duracao_meses' => $duracaoMeses,
                    'modalidade' => $plano['modalidade_nome'],
                    'valor' => $valorCompra,
                    'valor_formatado' => 'R$ '.number_format($valorCompra, 2, ',', '.'),
                    'status' => 'pendente',
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'dia_vencimento' => $diaVencimento,
                    'payment_url' => $paymentUrl,
                    'preference_id' => $preferenceId,
                    'tipo_pagamento' => $tipoPagamento,
                    'metodo_pagamento' => $metodoPagamento,
                    'tipo_cobranca' => $isRecorrente ? 'recorrente' : 'avulso',
                    'recorrente' => $isRecorrente,
                    'pix' => $pixData ? [
                        'payment_id' => $pixData['id'] ?? null,
                        'status' => $pixData['status'] ?? null,
                        'qr_code' => $pixData['qr_code'] ?? null,
                        'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                        'ticket_url' => $pixData['ticket_url'] ?? null,
                        'expires_at' => $pixData['date_of_expiration'] ?? null,
                    ] : null,
                ],
            ];

            if ($mpError) {
                $body['mp_error'] = $mpError;
            }

            return ['status' => 200, 'body' => $body];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'type' => 'error',
                    'code' => 'ERRO_INTERNO',
                    'message' => 'Não foi possível processar sua compra. Tente novamente.',
                    'debug' => ['error' => $e->getMessage()],
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $pendente
     * @param  array<string, mixed>  $plano
     * @param  array<string, mixed>  $usuario
     * @return array{status: int, body: array<string, mixed>}
     */
    private function respostaPendenteExistente(
        int $tenantId,
        int $userId,
        array $pendente,
        array $plano,
        string $metodoPagamento,
        array $usuario,
        int $alunoId,
    ): array {
        $matriculaId = (int) $pendente['id'];
        $valorPendente = (float) $pendente['valor'];

        $this->pagamentosPlano->garantirParcelaPendenteUnica(
            $tenantId,
            $alunoId,
            $matriculaId,
            (int) $pendente['plano_id'],
            $valorPendente,
            $pendente['data_inicio'] ?? date('Y-m-d'),
            $userId
        );

        $assinaturaRow = DB::table('assinaturas')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->first();
        $assinatura = $assinaturaRow ? (array) $assinaturaRow : [];

        $pixData = null;
        if ($metodoPagamento === 'pix') {
            $pagamento = new MobilePagamentoService($this->matriculas);
            $pixResult = $pagamento->gerarPix($userId, $tenantId, ['matricula_id' => $matriculaId]);
            if ($pixResult['status'] === 200 && isset($pixResult['body']['data']['pix'])) {
                $pixData = $pixResult['body']['data']['pix'];
            }
            $assinaturaRow = DB::table('assinaturas')
                ->where('matricula_id', $matriculaId)
                ->where('tenant_id', $tenantId)
                ->orderByDesc('id')
                ->first();
            $assinatura = $assinaturaRow ? (array) $assinaturaRow : [];
        }

        $vencimento = $pendente['proxima_data_vencimento'] ?? $pendente['data_vencimento'];
        $tipoCobranca = $assinatura['tipo_cobranca'] ?? 'avulso';

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Já existe pagamento pendente. Reabra para concluir.',
                'data' => [
                    'matricula_id' => $matriculaId,
                    'plano_id' => (int) $pendente['plano_id'],
                    'plano_ciclo_id' => $pendente['plano_ciclo_id'] ? (int) $pendente['plano_ciclo_id'] : null,
                    'plano_nome' => $plano['nome'],
                    'modalidade' => $plano['modalidade_nome'],
                    'valor' => (float) $pendente['valor'],
                    'valor_formatado' => 'R$ '.number_format((float) $pendente['valor'], 2, ',', '.'),
                    'status' => 'pendente',
                    'data_inicio' => $pendente['data_inicio'],
                    'data_vencimento' => $vencimento,
                    'vencida' => $vencimento < date('Y-m-d'),
                    'payment_url' => $assinatura['payment_url'] ?? null,
                    'preference_id' => $assinatura['gateway_preference_id'] ?? null,
                    'tipo_pagamento' => $metodoPagamento === 'pix' ? 'pix' : 'pagamento_unico',
                    'metodo_pagamento' => $metodoPagamento,
                    'tipo_cobranca' => $tipoCobranca,
                    'recorrente' => $tipoCobranca === 'recorrente',
                    'pix' => $pixData,
                ],
            ],
        ];
    }

    private function salvarAssinatura(
        int $tenantId,
        int $matriculaId,
        int $alunoId,
        int $planoId,
        float $valor,
        bool $isRecorrente,
        string $metodoPagamento,
        ?string $preferenceId,
        ?string $paymentUrl,
        string $externalReference,
        string $cicloNome,
    ): void {
        $gatewayId = DB::table('assinatura_gateways')->where('codigo', 'mercadopago')->value('id') ?: 1;
        $statusId = DB::table('assinatura_status')->where('codigo', 'pendente')->value('id') ?: 1;
        $frequenciaId = DB::table('assinatura_frequencias')->where('codigo', strtolower($cicloNome))->value('id') ?: 4;

        $metodoPagamentoId = null;
        if ($isRecorrente) {
            $metodoPagamentoId = MetodoPagamentoResolver::resolve('credit_card', 1);
        } elseif ($metodoPagamento === 'pix') {
            $metodoPagamentoId = MetodoPagamentoResolver::resolve('pix');
        }

        $tipoCobranca = $isRecorrente ? 'recorrente' : 'avulso';

        $assinaturaId = DB::table('assinaturas')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->value('id');

        $payload = [
            'aluno_id' => $alunoId,
            'plano_id' => $planoId,
            'gateway_id' => $gatewayId,
            'gateway_assinatura_id' => $isRecorrente ? $preferenceId : null,
            'gateway_preference_id' => ! $isRecorrente ? $preferenceId : null,
            'external_reference' => $externalReference,
            'payment_url' => $paymentUrl,
            'status_id' => $statusId,
            'status_gateway' => 'pending',
            'valor' => $valor,
            'frequencia_id' => $frequenciaId,
            'dia_cobranca' => (int) date('d'),
            'data_inicio' => date('Y-m-d'),
            'proxima_cobranca' => $isRecorrente ? date('Y-m-d', strtotime('+1 month')) : null,
            'tipo_cobranca' => $tipoCobranca,
            'atualizado_em' => now(),
        ];

        if ($metodoPagamentoId !== null) {
            $payload['metodo_pagamento_id'] = $metodoPagamentoId;
        }

        if ($assinaturaId) {
            DB::table('assinaturas')->where('id', $assinaturaId)->update($payload);
        } else {
            DB::table('assinaturas')->insert(array_merge($payload, [
                'tenant_id' => $tenantId,
                'matricula_id' => $matriculaId,
                'criado_em' => now(),
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $pixData
     */
    private function salvarPixRegistro(int $tenantId, int $matriculaId, array $pixData): void
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
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'type' => 'error',
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
