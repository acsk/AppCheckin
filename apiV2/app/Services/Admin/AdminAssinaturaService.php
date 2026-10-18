<?php

namespace App\Services\Admin;

use App\Repositories\AdminAssinaturaRepository;
use App\Repositories\AdminMatriculaRepository;
use App\Services\Asaas\AsaasPagamentoSync;
use App\Services\Asaas\AsaasService;
use App\Services\MercadoPagoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Assinaturas admin — paridade com painel assinaturaService.js.
 */
class AdminAssinaturaService
{
    public function __construct(
        private readonly AdminAssinaturaRepository $assinaturas,
        private readonly AdminMatriculaRepository $matriculas,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId, array $params): array
    {
        return $this->listarPaginado($tenantId, $params);
    }

    /**
     * Cobranças da assinatura no provedor (Asaas ou Mercado Pago) + parcelas locais.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cobrancas(int $tenantId, int $assinaturaId): array
    {
        $row = $this->assinaturas->findById($assinaturaId, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $matriculaId = (int) ($row['matricula_id'] ?? 0);
        $externalReference = trim((string) ($row['external_reference'] ?? ''));
        $preferenceId = trim((string) ($row['gateway_preference_id'] ?? ''));
        $gatewayCodigo = strtolower((string) ($row['gateway_codigo'] ?? ''));
        $gatewayNome = trim((string) ($row['gateway_nome'] ?? ''));
        $ehAsaas = str_contains($gatewayCodigo, 'asaas')
            || str_starts_with($preferenceId, 'pay_')
            || str_contains(strtolower($gatewayNome), 'asaas');
        if ($gatewayNome === '') {
            $gatewayNome = $ehAsaas ? 'Asaas' : 'Mercado Pago';
        }

        $provedorPagamentos = [];
        $erroProvedor = null;
        try {
            if ($ehAsaas) {
                $provedorPagamentos = $this->cobrancasAsaas($tenantId, $matriculaId, $externalReference, $preferenceId);
            } elseif ($externalReference !== '') {
                $mp = new MercadoPagoService($tenantId);
                $busca = $mp->buscarPagamentosPorExternalReference($externalReference);
                $provedorPagamentos = is_array($busca['pagamentos'] ?? null) ? $busca['pagamentos'] : [];
            }
        } catch (Throwable $e) {
            $erroProvedor = $e->getMessage();
        }

        $pagamentosPlano = $matriculaId > 0 ? $this->pagamentosPlanoDaMatricula($tenantId, $matriculaId) : [];
        $pagamentosMp = $externalReference !== '' ? $this->pagamentosMercadoPagoLocais($tenantId, $externalReference, $matriculaId) : [];
        $pagamentosPix = $matriculaId > 0 ? $this->pagamentosPixLocais($tenantId, $matriculaId) : [];
        $webhooks = $this->webhooksLocais($tenantId, $externalReference, $provedorPagamentos);
        $conciliacao = $this->montarConciliacao($ehAsaas, $gatewayNome, $provedorPagamentos, $pagamentosPlano);

        $invoiceUrl = null;
        foreach ($provedorPagamentos as $pagamento) {
            $url = trim((string) ($pagamento['invoice_url'] ?? ''));
            if ($url !== '') {
                $invoiceUrl = $url;
                break;
            }
        }
        if ($invoiceUrl === null) {
            $invoiceUrl = trim((string) ($row['payment_url'] ?? '')) ?: null;
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'sincronizado_em' => date('c'),
                'gateway' => [
                    'codigo' => $ehAsaas ? 'asaas' : ($gatewayCodigo !== '' ? $gatewayCodigo : 'mercadopago'),
                    'nome' => $gatewayNome,
                ],
                'provedor' => [
                    'nome' => $gatewayNome,
                    'pagamentos' => $provedorPagamentos,
                    'erro' => $erroProvedor,
                    'invoice_url' => $invoiceUrl,
                ],
                'mercadopago' => [
                    'total' => $ehAsaas ? 0 : count($provedorPagamentos),
                    'pagamentos' => $ehAsaas ? [] : $provedorPagamentos,
                ],
                'local' => [
                    'pagamentos_plano' => $pagamentosPlano,
                    'pagamentos_mercadopago' => $pagamentosMp,
                    'pagamentos_pix' => $pagamentosPix,
                    'webhook_payloads' => $webhooks,
                    'webhook_payloads_mercadopago' => $webhooks,
                ],
                'conciliacao' => $conciliacao,
                'dados_tecnicos' => [
                    'assinatura_id' => (int) $row['id'],
                    'matricula_id' => $matriculaId > 0 ? $matriculaId : null,
                    'aluno_id' => (int) ($row['aluno_id'] ?? 0),
                    'external_reference' => $externalReference !== '' ? $externalReference : null,
                    'preference_id' => $preferenceId !== '' ? $preferenceId : null,
                    'gateway_assinatura_id' => $row['mp_preapproval_id'] ?? null,
                    'status_gateway' => $row['status_gateway'] ?? null,
                    'payment_url' => $row['payment_url'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Super Admin: lista assinaturas de todos os tenants; filtro opcional tenant_id.
     *
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarSuperAdmin(array $params): array
    {
        $tenantId = ! empty($params['tenant_id']) ? (int) $params['tenant_id'] : null;

        return $this->listarPaginado($tenantId, $params);
    }

    /**
     * @deprecated Use listarSuperAdmin
     *
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarTodas(array $params): array
    {
        return $this->listarSuperAdmin($params);
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function buscar(int $id, int $tenantId): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'assinatura' => $this->assinaturas->mapearDetalhe($row),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, ?int $adminId, array $data): array
    {
        if (! empty($data['matricula_id'])) {
            $matriculaId = (int) $data['matricula_id'];
            $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
            if (! $matricula) {
                return $this->erro('Matrícula não encontrada', 404);
            }

            if ($this->assinaturas->findByMatriculaId($matriculaId, $tenantId)) {
                return $this->erro('Matrícula já possui assinatura vinculada', 400);
            }

            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                $matriculaId,
                (int) $matricula['aluno_id'],
                (int) $matricula['plano_id'],
                $matricula,
                $data,
                $adminId
            );

            if ($result === null) {
                return $this->erro('Erro ao criar assinatura', 500);
            }

            return [
                'status' => 201,
                'body' => [
                    'success' => true,
                    'message' => 'Assinatura criada com sucesso',
                    'assinatura' => $this->assinaturas->mapearDetalhe($result),
                ],
            ];
        }

        $alunoId = (int) ($data['aluno_id'] ?? 0);
        $planoId = (int) ($data['plano_id'] ?? 0);
        if ($alunoId <= 0 || $planoId <= 0) {
            return $this->erro('Informe matricula_id ou aluno_id + plano_id', 422);
        }

        if (! empty($data['criar_matricula'])) {
            $matriculaService = app(AdminMatriculaService::class);
            $criar = $matriculaService->criar($tenantId, $adminId, array_merge($data, [
                'aluno_id' => $alunoId,
                'plano_id' => $planoId,
            ]));

            if ($criar['status'] >= 400) {
                return $criar;
            }

            $matriculaId = (int) ($criar['body']['matricula']['id'] ?? $criar['body']['matriculas'][0]['id'] ?? 0);
            if ($matriculaId <= 0) {
                return $this->erro('Matrícula criada sem ID retornado', 500);
            }

            $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                $matriculaId,
                $alunoId,
                $planoId,
                $matricula ?? ['aluno_id' => $alunoId, 'plano_id' => $planoId],
                $data,
                $adminId
            );
        } else {
            $result = $this->assinaturas->criarParaMatricula(
                $tenantId,
                0,
                $alunoId,
                $planoId,
                [
                    'aluno_id' => $alunoId,
                    'plano_id' => $planoId,
                    'valor' => $data['valor'] ?? 0,
                    'data_inicio' => $data['data_inicio'] ?? date('Y-m-d'),
                ],
                $data,
                $adminId
            );
        }

        if ($result === null) {
            return $this->erro('Erro ao criar assinatura', 500);
        }

        return [
            'status' => 201,
            'body' => [
                'success' => true,
                'message' => 'Assinatura criada com sucesso',
                'assinatura' => $this->assinaturas->mapearDetalhe($result),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $id, int $tenantId, array $data): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $fields = [];
        foreach (['valor', 'data_inicio', 'data_fim', 'proxima_cobranca', 'ultima_cobranca'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = $data[$key];
            }
        }
        if (! empty($data['status'])) {
            $fields['status_codigo'] = (string) $data['status'];
        }

        $this->assinaturas->atualizar($id, $tenantId, $fields);
        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura atualizada',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function renovar(int $id, int $tenantId, array $data): array
    {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $meses = max(1, (int) ($data['meses'] ?? $row['ciclo_meses'] ?? 1));
        $base = $row['proxima_cobranca'] ?? $row['data_fim'] ?? date('Y-m-d');
        $novaProxima = date('Y-m-d', strtotime("+{$meses} months", strtotime((string) $base)));

        $this->assinaturas->atualizar($id, $tenantId, [
            'proxima_cobranca' => $novaProxima,
            'data_fim' => $novaProxima,
            'status_codigo' => 'ativa',
        ]);

        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura renovada',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function suspender(int $id, int $tenantId, string $motivo = ''): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'pausada', 'paused', $motivo ?: 'Suspensa pelo administrador');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $id, int $tenantId, string $motivo = ''): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'cancelada', 'cancelled', $motivo ?: 'Cancelada pelo administrador');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function reativar(int $id, int $tenantId): array
    {
        return $this->alterarStatusAssinatura($id, $tenantId, 'ativa', 'authorized');
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarProximasVencer(int $tenantId, int $dias = 30): array
    {
        $rows = $this->assinaturas->listarProximasVencer($tenantId, $dias, 200);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'dias' => $dias,
                'total' => count($rows),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $rows),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarHistoricoAluno(int $tenantId, int $alunoId): array
    {
        $rows = $this->assinaturas->listarPorAluno($tenantId, $alunoId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'aluno_id' => $alunoId,
                'total' => count($rows),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $rows),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarSemMatricula(int $tenantId, array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));
        $result = $this->assinaturas->listarSemMatricula($tenantId, $page, $perPage);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'total' => $result['total'],
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($result['total'] / $perPage),
                'assinaturas' => array_map(fn (array $r) => $this->mapearLinha($r), $result['rows']),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function relatorio(int $tenantId, array $params): array
    {
        $resumo = $this->assinaturas->relatorioResumo($tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'resumo' => $resumo,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function sincronizarComMatricula(int $assinaturaId, int $tenantId): array
    {
        $assinatura = $this->assinaturas->findById($assinaturaId, $tenantId);
        if (! $assinatura) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $matriculaId = (int) ($assinatura['matricula_id'] ?? 0);
        if ($matriculaId <= 0) {
            return $this->erro('Assinatura não possui matrícula vinculada', 400);
        }

        $matricula = $this->matriculas->findBasicoComStatus($matriculaId, $tenantId);
        if (! $matricula) {
            return $this->erro('Matrícula vinculada não encontrada', 404);
        }

        $statusMatricula = (string) ($matricula['status_codigo'] ?? 'pendente');
        $statusAssinatura = $this->mapearStatusMatriculaParaAssinatura($statusMatricula);

        $this->assinaturas->atualizarStatus(
            $assinaturaId,
            $tenantId,
            $statusAssinatura['codigo'],
            $statusAssinatura['gateway']
        );

        if ($matricula['proxima_data_vencimento'] ?? null) {
            $this->assinaturas->atualizar($assinaturaId, $tenantId, [
                'proxima_cobranca' => $matricula['proxima_data_vencimento'],
                'data_fim' => $matricula['proxima_data_vencimento'],
            ]);
        }

        $atualizada = $this->assinaturas->findById($assinaturaId, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Assinatura sincronizada com matrícula',
                'matricula_status' => $statusMatricula,
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $assinatura),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function obterStatusSincronizacao(int $assinaturaId, int $tenantId): array
    {
        $assinatura = $this->assinaturas->findById($assinaturaId, $tenantId);
        if (! $assinatura) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $matriculaId = (int) ($assinatura['matricula_id'] ?? 0);
        $matricula = $matriculaId > 0
            ? $this->matriculas->findBasicoComStatus($matriculaId, $tenantId)
            : null;

        $statusMatricula = $matricula['status_codigo'] ?? null;
        $statusAssinatura = $assinatura['status_codigo'] ?? $assinatura['status_gateway'] ?? null;
        $esperado = $statusMatricula
            ? $this->mapearStatusMatriculaParaAssinatura((string) $statusMatricula)['codigo']
            : null;

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'sincronizado' => $esperado === null || $statusAssinatura === $esperado,
                'matricula_id' => $matriculaId ?: null,
                'matricula_status' => $statusMatricula,
                'assinatura_status' => $statusAssinatura,
                'status_esperado_assinatura' => $esperado,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{status: int, body: array<string, mixed>}
     */
    private function listarPaginado(?int $tenantId, array $params): array
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($params['per_page'] ?? 20)));

            $filtros = [
                'status' => $params['status'] ?? '',
                'tipo_cobranca' => $params['tipo_cobranca'] ?? '',
                'busca' => $params['busca'] ?? '',
                'gateway' => $params['gateway'] ?? '',
            ];

            $result = $this->assinaturas->listar($tenantId, $filtros, $page, $perPage);
            $totalPages = (int) ceil($result['total'] / max(1, $perPage));

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'total' => $result['total'],
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'assinaturas' => array_map(fn (array $row) => $this->mapearLinha($row), $result['rows']),
                    'contagens' => $this->assinaturas->contagensPorStatus($tenantId, $filtros),
                    'resumo' => $this->assinaturas->resumoPainel($tenantId),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminAssinaturaService::listar] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'error' => 'Erro ao listar assinaturas',
                    'detail' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function alterarStatusAssinatura(
        int $id,
        int $tenantId,
        string $statusCodigo,
        string $statusGateway,
        ?string $motivo = null
    ): array {
        $row = $this->assinaturas->findById($id, $tenantId);
        if (! $row) {
            return $this->erro('Assinatura não encontrada', 404);
        }

        $this->assinaturas->atualizarStatus($id, $tenantId, $statusCodigo, $statusGateway, $motivo);
        $atualizada = $this->assinaturas->findById($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'message' => 'Status da assinatura atualizado',
                'assinatura' => $this->assinaturas->mapearDetalhe($atualizada ?? $row),
            ],
        ];
    }

    /**
     * @return array{codigo: string, gateway: string}
     */
    private function mapearStatusMatriculaParaAssinatura(string $statusMatricula): array
    {
        return match ($statusMatricula) {
            'ativa' => ['codigo' => 'ativa', 'gateway' => 'authorized'],
            'bloqueada', 'suspensa' => ['codigo' => 'pausada', 'gateway' => 'paused'],
            'cancelada' => ['codigo' => 'cancelada', 'gateway' => 'cancelled'],
            'vencida', 'finalizada' => ['codigo' => 'expirada', 'gateway' => 'finished'],
            default => ['codigo' => 'pendente', 'gateway' => 'pending'],
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapearLinha(array $row): array
    {
        return $this->assinaturas->mapearDetalhe($row);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cobrancasAsaas(int $tenantId, int $matriculaId, string $externalReference, string $preferenceId): array
    {
        $asaas = new AsaasService($tenantId);
        $porId = [];
        if ($externalReference !== '') {
            foreach ($asaas->listarPagamentosPorReferenciaExterna($externalReference) as $pagamento) {
                $id = (string) ($pagamento['id'] ?? '');
                if ($id !== '') {
                    $porId[$id] = $this->mapearCobrancaAsaas($pagamento);
                }
            }
        }
        $ids = $matriculaId > 0
            ? app(AsaasPagamentoSync::class)->paymentIdsDaMatricula($tenantId, $matriculaId)
            : [];
        if ($preferenceId !== '' && str_starts_with($preferenceId, 'pay_')) {
            array_unshift($ids, $preferenceId);
        }
        foreach (array_unique($ids) as $paymentId) {
            if (isset($porId[$paymentId])) {
                continue;
            }
            try {
                $pagamento = $asaas->obterPagamento($paymentId);
            } catch (Throwable) {
                continue;
            }
            if ($pagamento !== []) {
                $porId[$paymentId] = $this->mapearCobrancaAsaas($pagamento);
            }
        }

        return array_values($porId);
    }

    /**
     * @param  array<string, mixed>  $pagamento
     * @return array<string, mixed>
     */
    private function mapearCobrancaAsaas(array $pagamento): array
    {
        return [
            'id' => $pagamento['id'] ?? null,
            'status' => $pagamento['status'] ?? null,
            'status_detail' => $pagamento['status'] ?? null,
            'external_reference' => $pagamento['externalReference'] ?? null,
            'transaction_amount' => $pagamento['value'] ?? null,
            'payment_method_id' => $pagamento['billingType'] ?? null,
            'date_created' => $pagamento['dateCreated'] ?? null,
            'date_approved' => $pagamento['confirmedDate'] ?? $pagamento['paymentDate'] ?? null,
            'due_date' => $pagamento['dueDate'] ?? null,
            'invoice_url' => $pagamento['invoiceUrl'] ?? $pagamento['bankSlipUrl'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagamentosPlanoDaMatricula(int $tenantId, int $matriculaId): array
    {
        return array_map(static fn ($row) => (array) $row, DB::select(
            'SELECT pp.id, pp.matricula_id, pp.valor, pp.status_pagamento_id, sp.nome as status,
                    pp.data_pagamento, pp.data_vencimento, fp.nome as forma_pagamento_nome,
                    pp.observacoes, pp.created_at
             FROM pagamentos_plano pp
             LEFT JOIN status_pagamento sp ON sp.id = pp.status_pagamento_id
             LEFT JOIN formas_pagamento fp ON fp.id = pp.forma_pagamento_id
             WHERE pp.tenant_id = ? AND pp.matricula_id = ?
             ORDER BY pp.id DESC',
            [$tenantId, $matriculaId]
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagamentosMercadoPagoLocais(int $tenantId, string $externalReference, int $matriculaId): array
    {
        if (! Schema::hasTable('pagamentos_mercadopago')) {
            return [];
        }
        $q = DB::table('pagamentos_mercadopago')->where('tenant_id', $tenantId);
        $q->where(function ($inner) use ($externalReference, $matriculaId) {
            $inner->where('external_reference', $externalReference);
            if ($matriculaId > 0) {
                $inner->orWhere('matricula_id', $matriculaId);
            }
        });

        return array_map(static fn ($row) => (array) $row, $q->orderByDesc('id')->limit(50)->get()->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pagamentosPixLocais(int $tenantId, int $matriculaId): array
    {
        if (! Schema::hasTable('pagamentos_pix')) {
            return [];
        }

        return array_map(
            static fn ($row) => (array) $row,
            DB::table('pagamentos_pix')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->orderByDesc('id')
                ->limit(20)
                ->get()
                ->all()
        );
    }

    /**
     * @param  list<array<string, mixed>>  $provedorPagamentos
     * @param  list<array<string, mixed>>  $pagamentosPlano
     * @return array<string, mixed>
     */
    private function montarConciliacao(bool $ehAsaas, string $gatewayNome, array $provedorPagamentos, array $pagamentosPlano): array
    {
        $provedor = $this->escolherPagamentoProvedor($provedorPagamentos);
        $local = $this->escolherParcelaLocal($pagamentosPlano, $provedor);

        $provedorValor = $provedor === null ? null : (float) ($provedor['transaction_amount'] ?? 0);
        $localValor = $local === null ? null : (float) ($local['valor'] ?? 0);
        $provedorStatus = $provedor === null ? null : strtoupper((string) ($provedor['status'] ?? ''));
        $localStatusId = $local === null ? null : (int) ($local['status_pagamento_id'] ?? 0);
        $provedorForma = $this->normalizarForma((string) ($provedor['payment_method_id'] ?? ''));
        $localForma = $this->normalizarForma((string) ($local['forma_pagamento_nome'] ?? ''));
        $provedorVenc = $this->soData((string) ($provedor['due_date'] ?? $provedor['date_created'] ?? ''));
        $localVenc = $this->soData((string) ($local['data_vencimento'] ?? ''));

        $provedorPago = $this->statusProvedorPago($provedorStatus);
        $localPago = $localStatusId === 2;
        $valorOk = $provedor !== null && $local !== null && abs($provedorValor - $localValor) < 0.02;
        $situacaoOk = $provedor !== null && $local !== null && $provedorPago === $localPago;
        $formaOk = $provedor !== null && $local !== null && $provedorForma !== '' && $provedorForma === $localForma;
        $vencOk = $provedor !== null && $local !== null && $provedorVenc !== '' && $provedorVenc === $localVenc;
        $ok = $valorOk && $situacaoOk && $formaOk && $vencOk;

        $parcelaId = $local === null ? null : (int) $local['id'];
        $titulo = $ok ? 'Tudo conferido' : ($provedor === null || $local === null ? 'Sem par para conferir' : 'Há divergência');
        $detalhe = $ok
            ? sprintf('A cobrança no %s bate com a parcela %s da matrícula.', $gatewayNome, $parcelaId ? (string) $parcelaId : 'local')
            : 'Compare os valores do provedor com a parcela no Check-in.';

        $obs = strtolower((string) ($local['observacoes'] ?? ''));
        $baixa = 'Manual';
        if (str_contains($obs, 'asaas') || str_contains($obs, 'pay_') || str_contains($obs, 'automatic')) {
            $baixa = $ehAsaas ? 'Automática, pelo Asaas' : 'Automática, pelo provedor';
        } elseif (str_contains($obs, 'mercado')) {
            $baixa = 'Automática, pelo Mercado Pago';
        }

        return [
            'ok' => $ok,
            'titulo' => $titulo,
            'detalhe' => $detalhe,
            'provedor_nome' => $gatewayNome,
            'linhas' => [
                [
                    'campo' => 'valor',
                    'label' => 'Valor',
                    'provedor' => $provedor === null ? null : $provedorValor,
                    'checkin' => $local === null ? null : $localValor,
                    'ok' => $valorOk,
                ],
                [
                    'campo' => 'situacao',
                    'label' => 'Situação',
                    'provedor' => $provedor === null ? null : $this->rotuloStatusProvedor($provedorStatus, $ehAsaas),
                    'provedor_codigo' => $provedorStatus,
                    'checkin' => $local === null ? null : (string) ($local['status'] ?? ''),
                    'ok' => $situacaoOk,
                ],
                [
                    'campo' => 'forma',
                    'label' => 'Forma',
                    'provedor' => $provedorForma !== '' ? $provedorForma : null,
                    'checkin' => $localForma !== '' ? $localForma : null,
                    'ok' => $formaOk,
                ],
                [
                    'campo' => 'vencimento',
                    'label' => 'Vencimento',
                    'provedor' => $provedorVenc !== '' ? $provedorVenc : null,
                    'checkin' => $localVenc !== '' ? $localVenc : null,
                    'ok' => $vencOk,
                ],
            ],
            'parcela' => $local === null ? null : [
                'id' => $parcelaId,
                'baixa' => $baixa,
                'pago_em' => $local['data_pagamento'] ?? null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pagamentos
     * @return array<string, mixed>|null
     */
    private function escolherPagamentoProvedor(array $pagamentos): ?array
    {
        if ($pagamentos === []) {
            return null;
        }
        foreach ($pagamentos as $pagamento) {
            if ($this->statusProvedorPago(strtoupper((string) ($pagamento['status'] ?? '')))) {
                return $pagamento;
            }
        }

        return $pagamentos[0];
    }

    /**
     * @param  list<array<string, mixed>>  $parcelas
     * @param  array<string, mixed>|null  $provedor
     * @return array<string, mixed>|null
     */
    private function escolherParcelaLocal(array $parcelas, ?array $provedor): ?array
    {
        if ($parcelas === []) {
            return null;
        }
        $due = $this->soData((string) ($provedor['due_date'] ?? ''));
        if ($due !== '') {
            foreach ($parcelas as $parcela) {
                if ($this->soData((string) ($parcela['data_vencimento'] ?? '')) === $due) {
                    return $parcela;
                }
            }
        }
        foreach ($parcelas as $parcela) {
            if ((int) ($parcela['status_pagamento_id'] ?? 0) === 2) {
                return $parcela;
            }
        }

        return $parcelas[0];
    }

    private function statusProvedorPago(string $status): bool
    {
        $s = strtoupper($status);

        return in_array($s, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'APPROVED', 'ACCREDITED', 'PAID'], true);
    }

    private function rotuloStatusProvedor(string $status, bool $ehAsaas): string
    {
        return match (strtoupper($status)) {
            'RECEIVED', 'RECEIVED_IN_CASH' => 'Recebido',
            'CONFIRMED' => 'Confirmado',
            'PENDING', 'AWAITING_PAYMENT' => 'Pendente',
            'OVERDUE' => 'Vencido',
            'APPROVED', 'ACCREDITED' => 'Aprovado',
            'REJECTED' => 'Rejeitado',
            'CANCELLED', 'CANCELED' => 'Cancelado',
            default => $status !== '' ? $status : ($ehAsaas ? '—' : '—'),
        };
    }

    private function normalizarForma(string $raw): string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return '';
        }
        if (str_contains($s, 'pix')) {
            return 'Pix';
        }
        if (str_contains($s, 'boleto') || str_contains($s, 'bolbradesco') || $s === 'boleto') {
            return 'Boleto';
        }
        if (str_contains($s, 'card') || str_contains($s, 'cart') || str_contains($s, 'visa') || str_contains($s, 'master')) {
            return 'Cartão';
        }

        return ucfirst($raw);
    }

    private function soData(string $value): string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * @param  list<array<string, mixed>>  $provedorPagamentos
     * @return list<array<string, mixed>>
     */
    private function webhooksLocais(int $tenantId, string $externalReference, array $provedorPagamentos): array
    {
        if (! Schema::hasTable('webhook_payloads_mercadopago')) {
            return [];
        }

        $ids = [];
        foreach ($provedorPagamentos as $pagamento) {
            $id = trim((string) ($pagamento['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        if ($externalReference === '' && $ids === []) {
            return [];
        }

        $q = DB::table('webhook_payloads_mercadopago')->where('tenant_id', $tenantId);
        $q->where(function ($inner) use ($externalReference, $ids) {
            if ($externalReference !== '') {
                $inner->where('external_reference', $externalReference);
            }
            if ($ids !== []) {
                $method = $externalReference !== '' ? 'orWhereIn' : 'whereIn';
                $inner->{$method}('payment_id', $ids);
            }
        });

        return array_map(static fn ($row) => (array) $row, $q->orderByDesc('id')->limit(50)->get()->all());
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => ['success' => false, 'error' => $message],
        ];
    }
}
