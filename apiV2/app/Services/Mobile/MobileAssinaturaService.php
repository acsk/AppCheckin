<?php

namespace App\Services\Mobile;

use App\Services\Asaas\AsaasPagamentoSync;
use App\Services\MercadoPago\MercadoPagoService;
use App\Services\Pacote\PacoteConciliacaoService;
use App\Support\AcademyDateTime;
use App\Support\AsaasPagamentoStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            ->leftJoin('matriculas as m', function ($join) {
                $join->on('m.id', '=', 'a.matricula_id')
                    ->on('m.tenant_id', '=', 'a.tenant_id');
            })
            ->leftJoin('planos as p', 'p.id', '=', 'a.plano_id')
            ->leftJoin('modalidades as mo', 'mo.id', '=', 'p.modalidade_id')
            ->leftJoin('planos as mp', 'mp.id', '=', 'm.plano_id')
            ->leftJoin('modalidades as mmo', 'mmo.id', '=', 'mp.modalidade_id')
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
                'p.nome as plano_nome', 'p.modalidade_id', 'p.checkins_semanais',
                'mo.nome as modalidade_nome',
                'mp.nome as matricula_plano_nome', 'mmo.nome as matricula_modalidade_nome',
                DB::raw('CASE WHEN m.plano_ciclo_id IS NOT NULL THEN COALESCE((SELECT pc.permite_reposicao FROM plano_ciclos pc WHERE pc.id = m.plano_ciclo_id AND pc.tenant_id = m.tenant_id LIMIT 1), 0) ELSE 1 END as permite_reposicao'),
                'm.data_inicio as matricula_data_inicio',
                'm.proxima_data_vencimento as matricula_proxima_vencimento',
                'sm.codigo as matricula_status_codigo',
                DB::raw('(
                    SELECT MIN(pp.data_vencimento)
                    FROM pagamentos_plano pp
                    WHERE pp.matricula_id = a.matricula_id
                      AND pp.tenant_id = a.tenant_id
                      AND pp.status_pagamento_id IN (1, 3)
                      AND (pp.observacoes IS NULL OR pp.observacoes NOT LIKE \'%[MIGRACAO_SIMULADA%\')
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
            $pagamentoGateway = $this->sincronizarGatewayAsaasPendente(
                $tenantId,
                $matriculaId,
                (string) ($row['gateway_nome'] ?? ''),
                (string) ($row['gateway_preference_id'] ?? ''),
                $isPendente,
                $matriculaAtiva,
            );
            if (($pagamentoGateway['matricula_ativa'] ?? false) === true) {
                $matriculaAtiva = true;
                $statusCodigo = 'ativa';
                $statusNome = 'Ativa';
                $statusCor = '#16A34A';
                $isPendente = false;
            }
            if (is_array($pagamentoGateway)) {
                unset($pagamentoGateway['matricula_ativa']);
            }

            $tipoCobranca = $row['tipo_cobranca'] ?? 'recorrente';
            $item = [
                'id' => (int) $row['id'],
                'matricula_id' => $matriculaId ?: null,
                'modalidade_id' => isset($row['modalidade_id']) ? (int) $row['modalidade_id'] : null,
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
                'gateway' => ['nome' => $this->nomeGatewayAssinatura($row)],
                'plano' => [
                    'nome' => $matriculaAtiva
                        ? (string) ($row['matricula_plano_nome'] ?? $row['plano_nome'] ?? '')
                        : (string) ($row['plano_nome'] ?? ''),
                    'modalidade' => $matriculaAtiva
                        ? (string) ($row['matricula_modalidade_nome'] ?? $row['modalidade_nome'] ?? '')
                        : (string) ($row['modalidade_nome'] ?? ''),
                ],
                'pode_pagar' => false,
                'pagamento_gateway' => $pagamentoGateway,
            ];

            $asaasJaPago = is_array($pagamentoGateway)
                && AsaasPagamentoStatus::pago((string) ($pagamentoGateway['status'] ?? ''));
            $parcelaAindaAberta = $matriculaId > 0 && $this->temParcelaDoPlanoAberta($tenantId, $matriculaId);

            if ($isPendente && ! empty($row['payment_url']) && ! $asaasJaPago) {
                $item['payment_url'] = $row['payment_url'];
                $item['pode_pagar'] = true;
            } elseif (
                ! $isPendente
                && ! $foiCanceladaPeloUsuario
                && $tipoCobranca === 'avulso'
                && $matriculaAtiva
                && $matriculaId > 0
            ) {
                $hoje = AcademyDateTime::today();
                $acessoAte = $datas['data_fim'] ?? ($row['matricula_proxima_vencimento'] ?? null);
                $parcelaVenc = $row['proxima_parcela_vencimento'] ?? null;
                $liberar = $parcelaAindaAberta && (
                    ($acessoAte && $acessoAte <= $hoje) || ($parcelaVenc && $parcelaVenc <= $hoje)
                );

                if (! $liberar && ! empty($row['modalidade_id'])) {
                    // Espelho simples: se atingiu limite ×4 no ciclo [venc-1mês, venc)
                    $checkinsSemanais = (int) ($row['checkins_semanais'] ?? 0);
                    $permiteReposicao = (int) ($row['permite_reposicao'] ?? 0) === 1;
                    if ($permiteReposicao && $checkinsSemanais > 0 && $acessoAte) {
                        try {
                            $vencDt = new \DateTime((string) $acessoAte);
                            $inicio = (clone $vencDt)->modify('-1 month');
                            $limite = $checkinsSemanais * 4 + 1; // margem bônus
                            $usados = (int) DB::table('checkins as c')
                                ->join('alunos as a', 'a.id', '=', 'c.aluno_id')
                                ->join('turmas as t', 't.id', '=', 'c.turma_id')
                                ->join('dias as d', 'd.id', '=', 't.dia_id')
                                ->where('a.usuario_id', $userId)
                                ->where('t.modalidade_id', (int) $row['modalidade_id'])
                                ->where('d.data', '>=', $inicio->format('Y-m-d'))
                                ->where('d.data', '<', $vencDt->format('Y-m-d'))
                                ->where(function ($w) {
                                    $w->whereNull('c.presente')->orWhere('c.presente', 1);
                                })
                                ->count();
                            $liberar = $usados >= ($checkinsSemanais * 4);
                        } catch (\Throwable $e) {
                            // ignora — mantém liberar=false
                        }
                    }
                }

                if ($liberar) {
                    $item['pode_pagar'] = true;
                    $item['pode_renovar'] = true;
                    if (! empty($row['payment_url'])) {
                        $item['payment_url'] = $row['payment_url'];
                    }
                }
            }

            if ($matriculaAtiva && $matriculaId > 0 && $this->temMigracaoSimuladaAberta($tenantId, $matriculaId)) {
                $item['pode_pagar'] = true;
                if (empty($item['payment_url']) && ! empty($row['payment_url'])) {
                    $item['payment_url'] = $row['payment_url'];
                }
                if (empty($item['payment_url'])) {
                    $pixUrl = DB::table('pagamentos_pix')
                        ->where('tenant_id', $tenantId)
                        ->where('matricula_id', $matriculaId)
                        ->where('payment_id', 'like', 'pay_%')
                        ->orderByDesc('id')
                        ->value('ticket_url');
                    if (is_string($pixUrl) && $pixUrl !== '') {
                        $item['payment_url'] = $pixUrl;
                    }
                }
            }

            $assinaturas[] = $item;
        }

        // Histórico de pagamentos (manual + MP) por matrícula
        $matriculaIds = array_values(array_unique(array_filter(array_map(
            static fn ($a) => (int) ($a['matricula_id'] ?? 0),
            $assinaturas
        ))));
        $pagamentosPorMatricula = [];
        if ($matriculaIds !== []) {
            $colunasPagamento = [
                'pp.id',
                'pp.matricula_id',
                'pp.valor',
                'pp.data_vencimento',
                'pp.data_pagamento',
                'pp.status_pagamento_id',
                'sp.nome as status',
                'fp.nome as forma_pagamento_nome',
                'pp.baixado_por',
                'baixador.nome as baixado_por_nome',
                'pp.criado_por',
                'criador.nome as criado_por_nome',
                'tb.nome as tipo_baixa_nome',
                'pp.observacoes',
            ];
            if (Schema::hasColumn('pagamentos_plano', 'valor_liquido')) {
                $colunasPagamento[] = 'pp.valor_liquido';
            }
            if (Schema::hasColumn('pagamentos_plano', 'desconto_provedor')) {
                $colunasPagamento[] = 'pp.desconto_provedor';
            }

            $rowsPag = DB::table('pagamentos_plano as pp')
                ->join('status_pagamento as sp', 'sp.id', '=', 'pp.status_pagamento_id')
                ->leftJoin('formas_pagamento as fp', 'fp.id', '=', 'pp.forma_pagamento_id')
                ->leftJoin('usuarios as baixador', 'baixador.id', '=', 'pp.baixado_por')
                ->leftJoin('usuarios as criador', 'criador.id', '=', 'pp.criado_por')
                ->leftJoin('tipos_baixa as tb', 'tb.id', '=', 'pp.tipo_baixa_id')
                ->where('pp.tenant_id', $tenantId)
                ->whereIn('pp.matricula_id', $matriculaIds)
                ->where('pp.status_pagamento_id', '<>', 4)
                ->orderByRaw('COALESCE(pp.data_pagamento, pp.data_vencimento) DESC')
                ->orderByDesc('pp.id')
                ->get($colunasPagamento);

            $hoje = AcademyDateTime::today();
            foreach ($rowsPag as $pag) {
                $pag = (array) $pag;
                $mid = (int) $pag['matricula_id'];
                $statusPagamentoId = (int) ($pag['status_pagamento_id'] ?? 0);
                $vencimento = $pag['data_vencimento'] ?? null;

                // Parcela futura em aberto não vira card no mobile (só proxima_cobranca).
                if (in_array($statusPagamentoId, [1, 3], true) && is_string($vencimento) && $vencimento > $hoje) {
                    continue;
                }

                if (! isset($pagamentosPorMatricula[$mid])) {
                    $pagamentosPorMatricula[$mid] = [];
                }
                if (count($pagamentosPorMatricula[$mid]) >= 8) {
                    continue;
                }
                $obs = (string) ($pag['observacoes'] ?? '');
                $forma = $pag['forma_pagamento_nome'] ?? null;
                $origem = null;
                if (! empty($pag['baixado_por'])) {
                    $origem = 'manual';
                } elseif (stripos($obs, 'Asaas') !== false) {
                    $origem = 'asaas';
                } elseif ($forma && (stripos((string) $forma, 'Mercado') !== false || stripos($obs, 'Mercado Pago') !== false)) {
                    $origem = 'mercadopago';
                } elseif (stripos($obs, 'Mercado Pago') !== false) {
                    $origem = 'mercadopago';
                } elseif ($forma) {
                    $origem = 'manual';
                }
                $pagamentosPorMatricula[$mid][] = [
                    'id' => (int) $pag['id'],
                    'valor' => (float) $pag['valor'],
                    'valor_liquido' => isset($pag['valor_liquido']) ? (float) $pag['valor_liquido'] : null,
                    'desconto_provedor' => isset($pag['desconto_provedor']) ? (float) $pag['desconto_provedor'] : null,
                    'data_vencimento' => $pag['data_vencimento'],
                    'data_pagamento' => $pag['data_pagamento'],
                    'status' => $pag['status'],
                    'status_pagamento_id' => $statusPagamentoId,
                    'forma_pagamento' => $forma,
                    'baixado_por_nome' => $pag['baixado_por_nome'] ?? null,
                    'criado_por_nome' => $pag['criado_por_nome'] ?? null,
                    'tipo_baixa_nome' => $pag['tipo_baixa_nome'] ?? null,
                    'origem' => $origem,
                ];
            }
        }

        foreach ($assinaturas as &$assinaturaRef) {
            $mid = (int) ($assinaturaRef['matricula_id'] ?? 0);
            $assinaturaRef['pagamentos'] = $pagamentosPorMatricula[$mid] ?? [];
        }
        unset($assinaturaRef);

        $assinaturas = $this->sanitizarAssinaturasFinanceiroMobile($assinaturas);

        // Pagamento do pacote que chegou por fora (Mercado Pago na API Slim, baixa manual) vale para o grupo
        app(PacoteConciliacaoService::class)->conciliarAbertos($tenantId);
        $hoje = now()->toDateString();

        $pacotes = DB::table('pacote_contratos as pc')
            ->join('pacotes as p', 'p.id', '=', 'pc.pacote_id')
            ->where('pc.tenant_id', $tenantId)
            ->where('pc.pagante_usuario_id', $userId)
            ->orderByDesc('pc.created_at')
            ->get(['pc.id as contrato_id', 'pc.status', 'pc.valor_total', 'pc.data_inicio', 'pc.data_fim', 'p.nome as pacote_nome', 'p.valor_total as pacote_valor', 'p.ativo as pacote_ativo'])
            ->map(function ($pc) use ($tenantId, $hoje) {
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
                    // Renovação pelo pagante, a partir de N dias antes do fim (valor atual do pacote)
                    'renovacao_liberada_em' => $pc['status'] === 'ativo' ? MobilePacoteVendaService::renovacaoLiberadaEm($pc['data_fim']) : null,
                    'pode_renovar' => $pc['status'] === 'ativo' && (int) $pc['pacote_ativo'] === 1
                        && (($l = MobilePacoteVendaService::renovacaoLiberadaEm($pc['data_fim'])) === null || $l <= $hoje),
                    'valor_renovacao' => (float) $pc['pacote_valor'],
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
     * Financeiro do mobile: histórico focado em pagos.
     * - Esconde checkouts pendentes abandonados quando já existe ativa/paga na mesma modalidade.
     * - Em pendente com histórico pago, mostra só pagos e remove "Pagar agora".
     * - Em ativa/paga, omite parcelas futuras (Aguardando com vencimento > hoje).
     *
     * @param  list<array<string, mixed>>  $assinaturas
     * @return list<array<string, mixed>>
     */
    private function sanitizarAssinaturasFinanceiroMobile(array $assinaturas): array
    {
        $hoje = AcademyDateTime::today();
        $modalidadesComAcesso = [];

        foreach ($assinaturas as $assinatura) {
            $codigo = strtolower((string) ($assinatura['status']['codigo'] ?? ''));
            if (in_array($codigo, ['ativa', 'paga', 'pago', 'paid', 'approved'], true)) {
                $modId = (int) ($assinatura['modalidade_id'] ?? 0);
                if ($modId > 0) {
                    $modalidadesComAcesso[$modId] = true;
                }
            }
        }

        $resultado = [];
        foreach ($assinaturas as $assinatura) {
            $codigo = strtolower((string) ($assinatura['status']['codigo'] ?? ''));
            $isPendente = in_array($codigo, ['pendente', 'pending'], true);
            $modId = (int) ($assinatura['modalidade_id'] ?? 0);
            $pagamentos = is_array($assinatura['pagamentos'] ?? null) ? $assinatura['pagamentos'] : [];

            $pagos = [];
            $abertosVencidos = [];
            foreach ($pagamentos as $pagamento) {
                $statusId = (int) ($pagamento['status_pagamento_id'] ?? 0);
                $statusNome = strtolower((string) ($pagamento['status'] ?? ''));
                $estaPago = $statusId === 2
                    || ! empty($pagamento['data_pagamento'])
                    || str_contains($statusNome, 'pago');

                if ($estaPago) {
                    $pagos[] = $pagamento;
                    continue;
                }

                if (! in_array($statusId, [1, 3], true)) {
                    continue;
                }

                $venc = $pagamento['data_vencimento'] ?? null;
                // Atrasado (3) sempre; Aguardando (1) se vencido/hoje ou sem data (não some no limbo).
                // Parcelas futuras (venc > hoje) já foram filtradas antes ou ficam de fora aqui.
                if ($statusId === 3 || $venc === null || $venc === '' || $venc <= $hoje) {
                    $abertosVencidos[] = $pagamento;
                }
            }

            $temPago = $pagos !== [];
            $temAcessoMesmaModalidade = $modId > 0 && ! empty($modalidadesComAcesso[$modId]);

            // Checkout encerrado pela limpeza (expirado/cancelado) sem nenhum pagamento: não há o que mostrar
            if (! $temPago && in_array($codigo, ['expirada', 'cancelada'], true) && ($assinatura['cancelamento']['cancelado_por_codigo'] ?? null) === 'sistema') {
                continue;
            }

            if ($isPendente) {
                if ($temPago) {
                    $assinatura['pagamentos'] = $pagos;
                    $assinatura['pode_pagar'] = false;
                    unset($assinatura['payment_url'], $assinatura['pode_renovar'], $assinatura['motivo_pode_pagar']);
                    $resultado[] = $assinatura;
                    continue;
                }

                if ($temAcessoMesmaModalidade) {
                    continue;
                }

                // Primeira compra: só inclui se houver fatura aberta/vencida para exibir.
                // Evita enviar pendente com pagamentos vazios (frontend descartaria).
                if ($abertosVencidos === []) {
                    continue;
                }

                $assinatura['pagamentos'] = $abertosVencidos;
                $resultado[] = $assinatura;
                continue;
            }

            $visiveis = array_merge($pagos, $abertosVencidos);
            usort($visiveis, static function ($a, $b) {
                $da = $a['data_pagamento'] ?? $a['data_vencimento'] ?? '';
                $db = $b['data_pagamento'] ?? $b['data_vencimento'] ?? '';
                if ($da === $db) {
                    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
                }

                return strcmp((string) $db, (string) $da);
            });

            $assinatura['pagamentos'] = $visiveis;

            if ($abertosVencidos === [] && empty($assinatura['pode_renovar'])) {
                $assinatura['pode_pagar'] = false;
                unset($assinatura['payment_url']);
            }

            if ($visiveis === [] && ! in_array($codigo, ['ativa', 'paga', 'pago', 'paid', 'approved'], true)) {
                continue;
            }

            $resultado[] = $assinatura;
        }

        return $resultado;
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

        $snapshot = app(AsaasPagamentoSync::class)->sincronizar($tenantId, $matriculaId);

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

        $asaasPago = AsaasPagamentoStatus::pago((string) ($snapshot['status'] ?? ''));
        $gateway = $snapshot === [] ? null : array_merge($snapshot, [
            'aguardando_ativacao' => $asaasPago,
            'mensagem' => AsaasPagamentoStatus::mensagem($snapshot, false),
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
                    'pagamento_gateway' => $gateway,
                ] : [
                    'matricula_id' => $matriculaId,
                    'pagamento_gateway' => $gateway,
                ],
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
        $ultimoPago = $row['ultimo_vencimento_pago'] ?? null;
        $matriculaStatus = strtolower((string) ($row['matricula_status_codigo'] ?? ''));
        $isAvulso = ($row['tipo_cobranca'] ?? '') === 'avulso';

        if ($matriculaStatus === 'pendente') {
            $acessoPago = $ultimoPago ?: null;
        } else {
            $acessoPago = $proximaMatricula ?: $ultimoPago;
        }

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

    /**
     * @return array<string, mixed>|null
     */
    private function sincronizarGatewayAsaasPendente(
        int $tenantId,
        int $matriculaId,
        string $gatewayNome,
        string $preferenceId,
        bool $isPendente,
        bool $matriculaAtiva,
    ): ?array {
        $temAsaas = str_contains(strtolower($gatewayNome), 'asaas')
            || str_starts_with($preferenceId, 'pay_');
        if ($matriculaId <= 0 || ! $temAsaas) {
            return null;
        }

        $temParcelaAberta = $this->temParcelaDoPlanoAberta($tenantId, $matriculaId);
        $temSimulacaoAberta = $this->temMigracaoSimuladaAberta($tenantId, $matriculaId);
        $cobrancaNova = str_starts_with($preferenceId, 'pay_')
            && ! DB::table('pagamentos_plano')
                ->where('tenant_id', $tenantId)
                ->where('matricula_id', $matriculaId)
                ->where('status_pagamento_id', 2)
                ->where('observacoes', 'like', '%'.$preferenceId.'%')
                ->exists();
        if ($temSimulacaoAberta) {
            $cobrancaNova = false;
        }

        if (! $isPendente && $matriculaAtiva && ! $temParcelaAberta && ! $cobrancaNova && ! $temSimulacaoAberta) {
            return null;
        }

        $snap = app(AsaasPagamentoSync::class)->sincronizar($tenantId, $matriculaId);
        if ($snap === []) {
            return null;
        }

        $statusMat = (string) (DB::table('matriculas as m')
            ->join('status_matricula as sm', 'sm.id', '=', 'm.status_id')
            ->where('m.id', $matriculaId)
            ->where('m.tenant_id', $tenantId)
            ->value('sm.codigo') ?? '');
        $ativa = $statusMat === 'ativa';
        $pago = AsaasPagamentoStatus::pago((string) ($snap['status'] ?? ''));

        return array_merge($snap, [
            'aguardando_ativacao' => $pago && ! $ativa,
            'mensagem' => AsaasPagamentoStatus::mensagem($snap, $ativa),
            'matricula_ativa' => $ativa,
        ]);
    }

    private function temParcelaDoPlanoAberta(int $tenantId, int $matriculaId): bool
    {
        return DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])
            ->whereNull('data_pagamento')
            ->where(function ($q) {
                $q->whereNull('observacoes')
                    ->orWhere('observacoes', 'not like', '%[MIGRACAO_SIMULADA%');
            })
            ->exists();
    }

    private function temMigracaoSimuladaAberta(int $tenantId, int $matriculaId): bool
    {
        return DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', [1, 3])
            ->whereNull('data_pagamento')
            ->where('observacoes', 'like', '%[MIGRACAO_SIMULADA%')
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function nomeGatewayAssinatura(array $row): string
    {
        $pref = (string) ($row['gateway_preference_id'] ?? '');
        $url = strtolower((string) ($row['payment_url'] ?? ''));
        if (str_starts_with($pref, 'pay_') || str_contains($url, 'asaas')) {
            return 'Asaas';
        }

        $nome = trim((string) ($row['gateway_nome'] ?? ''));

        return $nome !== '' ? $nome : 'Mercado Pago';
    }
}
