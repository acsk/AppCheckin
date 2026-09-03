<?php

namespace App\Services\Mobile;

use App\Models\Parametro;
use App\Repositories\AdminPacoteRepository;
use App\Repositories\MobilePacoteRepository;
use App\Repositories\UsuarioRepository;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;

class MobilePacoteService
{
    public function __construct(
        private readonly MobilePacoteRepository $pacotes,
        private readonly AdminPacoteRepository $adminPacotes,
        private readonly UsuarioRepository $usuarios,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarContratos(?int $tenantId, int $userId, ?string $statusFiltro): array
    {
        if (! $tenantId) {
            return $this->failMessage('Nenhum tenant selecionado', 400);
        }

        $alunoId = $this->pacotes->findAlunoIdUsuario($userId, $tenantId);
        $contratosRaw = $this->pacotes->listarContratosUsuario($tenantId, $userId, $alunoId, $statusFiltro);

        $contratos = array_map(function (array $contrato) use ($tenantId): array {
            $cId = (int) $contrato['contrato_id'];
            $contrato['contrato_id'] = $cId;
            $contrato['valor_total'] = (float) ($contrato['valor_total'] ?? 0);
            $contrato['sou_pagante'] = (bool) $contrato['sou_pagante'];
            $contrato['qtd_beneficiarios'] = (int) ($contrato['qtd_beneficiarios'] ?? 0);
            $contrato['beneficiarios'] = $this->pacotes->listarBeneficiariosContrato($cId, $tenantId);
            $contrato['pagamentos'] = $this->pacotes->listarPagamentosContrato($cId, $tenantId);

            return $contrato;
        }, $contratosRaw);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'contratos' => $contratos,
                'total' => count($contratos),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarPendentes(?int $tenantId, int $userId): array
    {
        if (! $tenantId) {
            return $this->failMessage('Nenhum tenant selecionado', 400);
        }

        $rows = $this->pacotes->listarPendentesPagante($tenantId, $userId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'pacotes' => $rows,
                'total' => count($rows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     */
    public function pagar(?int $tenantId, int $userId, int $contratoId, array $body, ?string $forceNewQuery): array
    {
        if (! $tenantId) {
            return $this->failMessage('Nenhum tenant selecionado', 400);
        }

        if ($contratoId <= 0) {
            return $this->failMessage('contratoId inválido', 400);
        }

        $forceNew = ! empty($body['force_new']) || ! empty($forceNewQuery);

        $contrato = $this->pacotes->findContratoParaPagar($contratoId, $tenantId, $userId);
        if (! $contrato) {
            return $this->failMessage('Contrato não encontrado', 404);
        }

        if (($contrato['status'] ?? '') !== 'pendente') {
            return $this->failMessage('Contrato não está pendente', 400);
        }

        $valorTotal = (float) $contrato['valor_total'];
        $permiteRecorrencia = (bool) ($contrato['permite_recorrencia'] ?? false);
        $metodos = $this->metodosHabilitados($tenantId);

        if ($metodos === []) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Nenhum método de pagamento está habilitado. Entre em contato com a academia.',
                    'metodos_disponiveis' => [],
                ],
            ];
        }

        if ($permiteRecorrencia && ! $metodos['cartao']) {
            return [
                'status' => 400,
                'body' => [
                    'success' => false,
                    'message' => 'Pacotes recorrentes requerem cartão de crédito, que não está habilitado.',
                    'metodos_disponiveis' => $this->labelsMetodos($metodos),
                ],
            ];
        }

        if (! empty($contrato['payment_url']) && ! $forceNew) {
            $assinExistId = ! empty($contrato['assinatura_id'])
                ? (int) $contrato['assinatura_id']
                : $this->pacotes->findAssinaturaIdPorContrato($contratoId, $tenantId);

            $matriculasCriadas = $this->garantirMatriculasPacote($contratoId, $tenantId, $valorTotal);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Pagamento já gerado',
                    'data' => [
                        'contrato_id' => (int) $contrato['id'],
                        'assinatura_id' => $assinExistId,
                        'payment_url' => $contrato['payment_url'],
                        'preference_id' => $contrato['payment_preference_id'],
                        'valor_total' => $valorTotal,
                        'matriculas_criadas' => $matriculasCriadas,
                    ],
                ],
            ];
        }

        $usuario = $this->usuarios->findById($userId, $tenantId);
        if (! $usuario) {
            return $this->failMessage('Usuário não encontrado', 404);
        }

        $academiaNome = $this->pacotes->nomeTenant($tenantId);
        $externalReference = 'PAC-'.$contratoId.'-'.time();

        $dadosPagamento = [
            'tenant_id' => $tenantId,
            'matricula_id' => null,
            'aluno_id' => null,
            'usuario_id' => $userId,
            'aluno_nome' => $usuario['nome'] ?? '',
            'aluno_email' => $usuario['email'] ?? '',
            'aluno_telefone' => $usuario['telefone'] ?? '',
            'aluno_cpf' => $usuario['cpf'] ?? null,
            'plano_nome' => $contrato['pacote_nome'] ?? 'Pacote',
            'descricao' => 'Pacote: '.($contrato['pacote_nome'] ?? 'Pacote'),
            'valor' => $valorTotal,
            'item_id' => 'PACOTE_'.$contratoId,
            'external_reference' => $externalReference,
            'max_parcelas' => 12,
            'academia_nome' => $academiaNome,
            'apenas_cartao' => $permiteRecorrencia,
            'metadata_extra' => [
                'tipo' => 'pacote',
                'pacote_contrato_id' => $contratoId,
            ],
        ];

        $mercadoPago = new MercadoPagoService($tenantId);

        if ($permiteRecorrencia) {
            $duracaoMeses = 1;
            if (! empty($contrato['plano_ciclo_id'])) {
                $duracaoMeses = $this->pacotes->mesesDoCiclo((int) $contrato['plano_ciclo_id'], $tenantId);
            }
            $preferencia = $mercadoPago->criarPreferenciaAssinatura($dadosPagamento, $duracaoMeses);
        } else {
            $preferencia = $mercadoPago->criarPreferenciaPagamento($dadosPagamento);
        }

        if (empty($preferencia) || empty($preferencia['id'])) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao gerar pagamento do pacote',
                    'error' => 'Preferência do Mercado Pago não gerada corretamente',
                ],
            ];
        }

        $planoId = $this->pacotes->planoIdDoPacote((int) ($contrato['pacote_id'] ?? 0));
        $tipoCobranca = $permiteRecorrencia ? 'recorrente' : 'avulso';
        $paymentUrl = $preferencia['init_point'] ?? null;

        try {
            $assinaturaId = $this->pacotes->inserirAssinaturaPacote([
                'tenant_id' => $tenantId,
                'matricula_id' => null,
                'plano_id' => $planoId,
                'gateway_id' => $this->pacotes->gatewayIdMercadoPago(),
                'gateway_assinatura_id' => $permiteRecorrencia ? ($preferencia['id'] ?? null) : null,
                'gateway_preference_id' => ! $permiteRecorrencia ? ($preferencia['id'] ?? null) : null,
                'external_reference' => $externalReference,
                'payment_url' => $paymentUrl,
                'status_id' => $this->pacotes->statusAssinaturaIdPendente(),
                'valor' => $valorTotal,
                'frequencia_id' => $this->pacotes->frequenciaMensalId(),
                'dia_cobranca' => (int) date('d'),
                'tipo_cobranca' => $tipoCobranca,
                'pacote_contrato_id' => $contratoId,
            ]);
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao gerar pagamento do pacote',
                    'error' => $e->getMessage(),
                ],
            ];
        }

        if ($assinaturaId <= 0) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao gerar pagamento do pacote',
                    'error' => 'Falha ao obter ID da assinatura após INSERT',
                ],
            ];
        }

        $this->pacotes->atualizarPagamentoContrato(
            $contratoId,
            $tenantId,
            $paymentUrl,
            $preferencia['id'] ?? null,
            $assinaturaId,
        );

        $matriculasCriadas = $this->garantirMatriculasPacote($contratoId, $tenantId, $valorTotal);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'data' => [
                    'contrato_id' => $contratoId,
                    'assinatura_id' => $assinaturaId,
                    'payment_url' => $paymentUrl,
                    'preference_id' => $preferencia['id'] ?? null,
                    'valor_total' => $valorTotal,
                    'matriculas_criadas' => $matriculasCriadas,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function garantirMatriculasPacote(int $contratoId, int $tenantId, float $valorTotal): array
    {
        $matriculasCriadas = [];

        try {
            $contrato = DB::table('pacote_contratos as pc')
                ->join('pacotes as p', 'p.id', '=', 'pc.pacote_id')
                ->where('pc.id', $contratoId)
                ->where('pc.tenant_id', $tenantId)
                ->first(['pc.pacote_id', 'pc.pagante_usuario_id', 'p.plano_id', 'p.qtd_beneficiarios']);

            if (! $contrato) {
                return [];
            }

            $contrato = (array) $contrato;
            $planoId = (int) ($contrato['plano_id'] ?? 0);
            $paganteUsuarioId = (int) ($contrato['pagante_usuario_id'] ?? 0);

            if ($planoId === 0) {
                return [];
            }

            $statusPendente = $this->adminPacotes->statusMatriculaId('pendente', 1);
            $motivoPacote = $this->adminPacotes->motivoMatriculaId('compra_pacote', 1);

            $paganteAlunoId = $paganteUsuarioId > 0
                ? $this->adminPacotes->findAlunoIdDoPaganteNoTenant($tenantId, $paganteUsuarioId)
                : 0;

            if ($paganteAlunoId === 0 && $paganteUsuarioId > 0) {
                $existente = DB::table('alunos')->where('usuario_id', $paganteUsuarioId)->value('id');
                if ($existente) {
                    $paganteAlunoId = (int) $existente;
                } else {
                    $paganteAlunoId = (int) DB::table('alunos')->insertGetId([
                        'usuario_id' => $paganteUsuarioId,
                        'created_at' => DB::raw('NOW()'),
                        'updated_at' => DB::raw('NOW()'),
                    ]);
                }

                $temPapel = DB::table('tenant_usuario_papel')
                    ->where('tenant_id', $tenantId)
                    ->where('usuario_id', $paganteUsuarioId)
                    ->where('papel_id', 1)
                    ->exists();

                if (! $temPapel) {
                    DB::table('tenant_usuario_papel')->insert([
                        'tenant_id' => $tenantId,
                        'usuario_id' => $paganteUsuarioId,
                        'papel_id' => 1,
                        'created_at' => DB::raw('NOW()'),
                    ]);
                }
            }

            $beneficiarios = DB::table('pacote_beneficiarios')
                ->where('pacote_contrato_id', $contratoId)
                ->get(['id as beneficiario_id', 'aluno_id', 'matricula_id'])
                ->map(static fn ($row) => (array) $row)
                ->all();

            if ($beneficiarios === [] && $paganteAlunoId > 0) {
                $beneficiarioId = (int) DB::table('pacote_beneficiarios')->insertGetId([
                    'pacote_contrato_id' => $contratoId,
                    'aluno_id' => $paganteAlunoId,
                    'tenant_id' => $tenantId,
                    'created_at' => DB::raw('NOW()'),
                ]);
                $beneficiarios = [[
                    'beneficiario_id' => $beneficiarioId,
                    'aluno_id' => $paganteAlunoId,
                    'matricula_id' => null,
                ]];
            }

            $totalBeneficiarios = count($beneficiarios);
            if ($totalBeneficiarios === 0) {
                return [];
            }

            $valorRateado = round($valorTotal / $totalBeneficiarios, 2);
            $somaRateio = $valorRateado * ($totalBeneficiarios - 1);
            $valorUltimo = round($valorTotal - $somaRateio, 2);

            $dataInicio = date('Y-m-d');
            $dataVencimento = date('Y-m-d', strtotime('+1 month'));

            foreach ($beneficiarios as $i => $benef) {
                $alunoId = (int) ($benef['aluno_id'] ?? 0);
                $matriculaRefId = (int) ($benef['matricula_id'] ?? 0);
                $beneficiarioId = (int) ($benef['beneficiario_id'] ?? 0);
                $valor = ($i === $totalBeneficiarios - 1) ? $valorUltimo : $valorRateado;

                if ($matriculaRefId > 0 && DB::table('matriculas')->where('id', $matriculaRefId)->exists()) {
                    continue;
                }

                if ($matriculaRefId > 0) {
                    DB::table('pacote_beneficiarios')
                        ->where('id', $beneficiarioId)
                        ->update(['matricula_id' => null]);
                }

                $matriculaExistente = DB::table('matriculas')
                    ->where('aluno_id', $alunoId)
                    ->where('tenant_id', $tenantId)
                    ->where('pacote_contrato_id', $contratoId)
                    ->value('id');

                if ($matriculaExistente) {
                    if ($beneficiarioId > 0) {
                        DB::table('pacote_beneficiarios')
                            ->where('id', $beneficiarioId)
                            ->update(['matricula_id' => (int) $matriculaExistente]);
                    }

                    continue;
                }

                $novaMatriculaId = (int) DB::table('matriculas')->insertGetId([
                    'tenant_id' => $tenantId,
                    'aluno_id' => $alunoId,
                    'plano_id' => $planoId,
                    'status_id' => $statusPendente,
                    'motivo_id' => $motivoPacote,
                    'data_matricula' => $dataInicio,
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento,
                    'proxima_data_vencimento' => $dataVencimento,
                    'valor' => $valor,
                    'valor_rateado' => $valor,
                    'tipo_cobranca' => 'avulso',
                    'pacote_contrato_id' => $contratoId,
                    'periodo_teste' => 0,
                    'created_at' => DB::raw('NOW()'),
                    'updated_at' => DB::raw('NOW()'),
                ]);

                if ($beneficiarioId > 0) {
                    DB::table('pacote_beneficiarios')
                        ->where('id', $beneficiarioId)
                        ->update(['matricula_id' => $novaMatriculaId]);
                }

                $matriculasCriadas[] = [
                    'matricula_id' => $novaMatriculaId,
                    'aluno_id' => $alunoId,
                    'valor_rateado' => $valor,
                ];
            }

            if ($matriculasCriadas !== []) {
                $primeiraMatriculaId = $matriculasCriadas[0]['matricula_id'] ?? null;
                if ($primeiraMatriculaId) {
                    DB::table('assinaturas')
                        ->where('pacote_contrato_id', $contratoId)
                        ->where('tenant_id', $tenantId)
                        ->whereNull('matricula_id')
                        ->update(['matricula_id' => $primeiraMatriculaId]);
                }
            } else {
                $matriculaExistenteId = DB::table('matriculas')
                    ->where('pacote_contrato_id', $contratoId)
                    ->where('tenant_id', $tenantId)
                    ->orderBy('id')
                    ->value('id');

                if ($matriculaExistenteId) {
                    DB::table('assinaturas')
                        ->where('pacote_contrato_id', $contratoId)
                        ->where('tenant_id', $tenantId)
                        ->whereNull('matricula_id')
                        ->update(['matricula_id' => (int) $matriculaExistenteId]);
                }
            }
        } catch (\Throwable) {
            return $matriculasCriadas;
        }

        return $matriculasCriadas;
    }

    /**
     * @return array{pix: bool, cartao: bool, debito: bool, boleto: bool}
     */
    private function metodosHabilitados(int $tenantId): array
    {
        $parametro = new Parametro(DB::connection()->getPdo());

        return [
            'pix' => $parametro->isEnabled($tenantId, 'habilitar_pix'),
            'cartao' => $parametro->isEnabled($tenantId, 'habilitar_cartao_credito'),
            'debito' => $parametro->isEnabled($tenantId, 'habilitar_cartao_debito'),
            'boleto' => $parametro->isEnabled($tenantId, 'habilitar_boleto'),
        ];
    }

    /**
     * @param  array{pix: bool, cartao: bool, debito: bool, boleto: bool}  $metodos
     * @return list<string>
     */
    private function labelsMetodos(array $metodos): array
    {
        $labels = [];
        if ($metodos['pix']) {
            $labels[] = 'PIX';
        }
        if ($metodos['cartao']) {
            $labels[] = 'Cartão de Crédito';
        }
        if ($metodos['debito']) {
            $labels[] = 'Cartão de Débito';
        }
        if ($metodos['boleto']) {
            $labels[] = 'Boleto';
        }

        return $labels;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function failMessage(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'message' => $message,
            ],
        ];
    }
}
