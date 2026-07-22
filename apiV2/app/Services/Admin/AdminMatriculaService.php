<?php

namespace App\Services\Admin;

use App\Repositories\AdminMatriculaRepository;
use App\Services\PagamentoPlanoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminMatriculaService
{
    public function __construct(
        private readonly AdminMatriculaRepository $matriculas,
        private readonly PagamentoPlanoService $pagamentosPlano,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: array<string, mixed>}
     */
    public function index(int $tenantId, array $query): array
    {
        $this->pagamentosPlano->marcarAtrasados($tenantId);

        $result = $this->matriculas->listar($tenantId, $query);
        $rows = $result['rows'];
        $meta = $result['meta'];

        if ($meta !== null) {
            return [
                'status' => 200,
                'body' => [
                    'matriculas' => $rows,
                    'total' => $meta['total'],
                    'pagina' => $meta['pagina'],
                    'por_pagina' => $meta['por_pagina'],
                    'total_paginas' => $meta['total_paginas'],
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'matriculas' => $rows,
                'total' => count($rows),
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function show(int $id, int $tenantId): array
    {
        $this->pagamentosPlano->marcarAtrasados($tenantId);

        $matricula = $this->matriculas->findDetalhe($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (! empty($matricula['plano_ciclo_id'])) {
            $matricula['plano_ciclo'] = [
                'id' => (int) $matricula['ciclo_id'],
                'meses' => $matricula['ciclo_meses'] !== null ? (int) $matricula['ciclo_meses'] : null,
                'valor' => $matricula['ciclo_valor'],
                'valor_mensal_equivalente' => $matricula['ciclo_valor_mensal_equivalente'],
                'desconto_percentual' => $matricula['ciclo_desconto_percentual'],
                'permite_recorrencia' => $matricula['ciclo_permite_recorrencia'] !== null
                    ? (bool) $matricula['ciclo_permite_recorrencia']
                    : null,
                'ativo' => $matricula['ciclo_ativo'] !== null ? (bool) $matricula['ciclo_ativo'] : null,
                'frequencia' => $matricula['ciclo_frequencia_id'] ? [
                    'id' => (int) $matricula['ciclo_frequencia_id'],
                    'codigo' => $matricula['ciclo_frequencia_codigo'],
                    'nome' => $matricula['ciclo_frequencia_nome'],
                ] : null,
            ];
        } else {
            $matricula['plano_ciclo'] = null;
        }

        if (! empty($matricula['pacote_contrato_id'])) {
            $paganteUsuarioId = (int) ($matricula['contrato_pagante_usuario_id'] ?? 0);
            $beneficiarios = $this->matriculas->beneficiariosPacote(
                (int) $matricula['pacote_contrato_id'],
                $tenantId,
                $paganteUsuarioId
            );
            $nomePagante = $paganteUsuarioId > 0
                ? $this->matriculas->findNomeUsuario($paganteUsuarioId)
                : null;

            $matricula['pacote'] = [
                'pacote_id' => $matricula['pacote_id'] ? (int) $matricula['pacote_id'] : null,
                'pacote_nome' => $matricula['pacote_nome'],
                'pacote_valor_total' => $matricula['pacote_valor_total']
                    ? (float) $matricula['pacote_valor_total']
                    : null,
                'pacote_qtd_beneficiarios' => $matricula['pacote_qtd_beneficiarios']
                    ? (int) $matricula['pacote_qtd_beneficiarios']
                    : null,
                'contrato_id' => (int) $matricula['contrato_id'],
                'contrato_status' => $matricula['contrato_status'],
                'contrato_data_inicio' => $matricula['contrato_data_inicio'],
                'contrato_data_fim' => $matricula['contrato_data_fim'],
                'contrato_valor_total' => $matricula['contrato_valor_total']
                    ? (float) $matricula['contrato_valor_total']
                    : null,
                'pagante_usuario_id' => $matricula['contrato_pagante_usuario_id']
                    ? (int) $matricula['contrato_pagante_usuario_id']
                    : null,
                'pagante_nome' => $nomePagante,
                'beneficiarios' => $beneficiarios,
            ];
        } else {
            $matricula['pacote'] = null;
        }

        unset(
            $matricula['ciclo_id'],
            $matricula['ciclo_meses'],
            $matricula['ciclo_valor'],
            $matricula['ciclo_valor_mensal_equivalente'],
            $matricula['ciclo_desconto_percentual'],
            $matricula['ciclo_permite_recorrencia'],
            $matricula['ciclo_ativo'],
            $matricula['ciclo_frequencia_id'],
            $matricula['ciclo_frequencia_codigo'],
            $matricula['ciclo_frequencia_nome'],
            $matricula['contrato_id'],
            $matricula['contrato_status'],
            $matricula['contrato_data_inicio'],
            $matricula['contrato_data_fim'],
            $matricula['contrato_valor_total'],
            $matricula['contrato_pagante_usuario_id'],
            $matricula['pacote_id'],
            $matricula['pacote_nome'],
            $matricula['pacote_qtd_beneficiarios'],
            $matricula['pacote_valor_total']
        );

        $pagamentos = PagamentoPlanoService::anexarNumeroParcela(
            $this->matriculas->listarPagamentosResumo($id)
        );
        $matricula['pagamentos'] = $pagamentos;
        $matricula['total_pagamentos'] = (float) array_sum(array_map(
            static fn ($p) => (int) ($p['status_pagamento_id'] ?? 0) === 4 ? 0.0 : (float) ($p['valor'] ?? 0),
            $pagamentos
        ));
        $matricula['total_pago'] = (float) array_sum(array_map(
            static fn ($p) => (int) ($p['status_pagamento_id'] ?? 0) === 2 ? (float) ($p['valor'] ?? 0) : 0.0,
            $pagamentos
        ));
        $matricula['total_pendente'] = (float) array_sum(array_map(
            static fn ($p) => in_array((int) ($p['status_pagamento_id'] ?? 0), [1, 3], true)
                ? (float) ($p['valor'] ?? 0)
                : 0.0,
            $pagamentos
        ));

        $matricula['mercadopago_payment_ids'] = [];
        $matricula['mercadopago_last_payment_id'] = null;
        if (($matricula['status_codigo'] ?? '') === 'pendente') {
            $mpIds = $this->matriculas->mpPaymentIds($id, $tenantId);
            $matricula['mercadopago_payment_ids'] = $mpIds;
            $matricula['mercadopago_last_payment_id'] = $mpIds[0] ?? null;
        }

        $creditos = $this->matriculas->creditosAluno($tenantId, (int) $matricula['aluno_id']);
        $outrasMatriculas = $this->matriculas->listarOutrasMatriculasDoAluno(
            (int) $matricula['aluno_id'],
            $tenantId,
            $id,
        );

        return [
            'status' => 200,
            'body' => [
                'matricula' => $matricula,
                'pagamentos' => $pagamentos,
                'total' => $matricula['total_pagamentos'],
                'total_pago' => $matricula['total_pago'],
                'total_pendente' => $matricula['total_pendente'],
                'creditos' => [
                    'saldo_total' => $creditos['saldo_total'],
                    'creditos_ativos' => $creditos['creditos_ativos'],
                ],
                'outras_matriculas' => $outrasMatriculas,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function pagamentos(int $id, int $tenantId): array
    {
        if (! $this->matriculas->exists($id, $tenantId)) {
            return $this->error('Matrícula não encontrada', 404);
        }

        $pagamentos = $this->matriculas->listarPagamentosCompletos($id, $tenantId);

        return [
            'status' => 200,
            'body' => [
                'pagamentos' => $pagamentos,
                'total' => count($pagamentos),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function bloquear(int $id, int $tenantId, ?int $adminId, array $data): array
    {
        $matricula = $this->matriculas->findBasicoComStatus($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (($matricula['status_codigo'] ?? '') === 'bloqueado') {
            return $this->error('Matrícula já está bloqueada', 400);
        }

        if (in_array($matricula['status_codigo'] ?? '', ['cancelada', 'finalizada'], true)) {
            return $this->error('Não é possível bloquear matrícula cancelada ou finalizada', 400);
        }

        $motivo = trim((string) ($data['motivo'] ?? 'Bloqueado pelo administrador'));
        if ($motivo === '') {
            $motivo = 'Bloqueado pelo administrador';
        }

        $observacoes = trim((string) ($matricula['observacoes'] ?? ''));
        $observacoesAtualizadas = $observacoes !== ''
            ? $observacoes."\n[Bloqueio ".date('d/m/Y H:i').'] '.$motivo
            : '[Bloqueio '.date('d/m/Y H:i').'] '.$motivo;

        $statusBloqueadoId = $this->matriculas->statusIdPorCodigo('bloqueado');
        if ($statusBloqueadoId === null) {
            return $this->error(
                "Status 'bloqueado' não configurado no sistema. Execute a migration de status_matricula.",
                500
            );
        }

        $this->matriculas->atualizarStatusObservacoes($id, $statusBloqueadoId, $observacoesAtualizadas);

        $matriculaAtualizada = $this->matriculas->findResposta($id);
        if (! $matriculaAtualizada || ($matriculaAtualizada['status_codigo'] ?? '') !== 'bloqueado') {
            return $this->error('Falha ao atualizar status da matrícula para bloqueado', 500);
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Matrícula bloqueada com sucesso',
                'matricula' => $matriculaAtualizada,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function desbloquear(int $id, int $tenantId, ?int $adminId): array
    {
        $matricula = $this->matriculas->findBasicoComStatus($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (($matricula['status_codigo'] ?? '') !== 'bloqueado') {
            return $this->error('Matrícula não está bloqueada', 400);
        }

        $hoje = date('Y-m-d');
        $acessoAte = $matricula['proxima_data_vencimento'] ?? $matricula['data_vencimento'] ?? null;
        $novoStatus = 'ativa';
        if ($acessoAte && $acessoAte < $hoje) {
            $novoStatus = 'vencida';
        }

        $observacoes = trim((string) ($matricula['observacoes'] ?? ''));
        $observacoesAtualizadas = $observacoes !== ''
            ? $observacoes."\n[Desbloqueio ".date('d/m/Y H:i')."] Restaurado para {$novoStatus}"
            : '[Desbloqueio '.date('d/m/Y H:i').'] Restaurado para '.$novoStatus;

        $statusRestauradoId = $this->matriculas->statusIdPorCodigo($novoStatus);
        if ($statusRestauradoId === null) {
            return $this->error("Status '{$novoStatus}' não configurado no sistema.", 500);
        }

        $this->matriculas->atualizarStatusObservacoes($id, $statusRestauradoId, $observacoesAtualizadas);

        $matriculaAtualizada = $this->matriculas->findResposta($id);
        if (! $matriculaAtualizada || ($matriculaAtualizada['status_codigo'] ?? '') !== $novoStatus) {
            return $this->error("Falha ao restaurar status da matrícula para '{$novoStatus}'", 500);
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Matrícula desbloqueada com sucesso',
                'matricula' => $matriculaAtualizada,
                'status_restaurado' => $novoStatus,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function cancelar(int $id, int $tenantId, ?int $adminId, array $data): array
    {
        $matricula = $this->matriculas->findBasicoComStatus($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (($matricula['status_codigo'] ?? '') === 'cancelada') {
            return $this->error('Matrícula já está cancelada', 400);
        }

        $motivoCancelamento = $data['motivo_cancelamento'] ?? 'Cancelado pelo admin';

        $this->matriculas->cancelar($id, $adminId, (string) $motivoCancelamento);
        $this->matriculas->desativarDescontosSeTabelaExiste($tenantId, $id);

        $usuarioId = $this->matriculas->findUsuarioIdPorAluno((int) $matricula['aluno_id']);
        if ($usuarioId) {
            $this->matriculas->clearUsuarioPlanoSeColunasExistem($usuarioId);
        }

        $matriculaAtualizada = $this->matriculas->findResposta($id);
        $pagamentos = $this->matriculas->listarPagamentosResumo($id);
        // cancelar response in Slim uses slightly different select (no forma_pagamento) — keep resumo without numero_parcela
        $pagamentosCancel = array_map(static function (array $p) {
            return [
                'id' => $p['id'],
                'valor' => $p['valor'],
                'data_vencimento' => $p['data_vencimento'],
                'data_pagamento' => $p['data_pagamento'],
                'status_pagamento_id' => $p['status_pagamento_id'],
                'status' => $p['status'] ?? null,
                'observacoes' => $p['observacoes'] ?? null,
            ];
        }, $pagamentos);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Matrícula cancelada com sucesso',
                'matricula' => $matriculaAtualizada,
                'pagamentos' => $pagamentosCancel,
                'total' => (float) array_sum(array_column($pagamentosCancel, 'valor')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizarProximaDataVencimento(int $id, int $tenantId, array $data): array
    {
        try {
            if ($tenantId <= 0) {
                return $this->error('Tenant não informado', 400);
            }

            if ($id <= 0) {
                return $this->error('ID da matrícula inválido', 422);
            }

            if (empty($data['proxima_data_vencimento'])) {
                return $this->error('Data de vencimento é obrigatória', 422);
            }

            $dataVencimento = trim((string) $data['proxima_data_vencimento']);
            $dataObj = \DateTime::createFromFormat('Y-m-d', $dataVencimento);
            if (! $dataObj || $dataObj->format('Y-m-d') !== $dataVencimento) {
                return $this->error('Formato de data inválido. Use YYYY-MM-DD', 422);
            }

            $matricula = $this->matriculas->findBasicoComStatus($id, $tenantId);
            if (! $matricula) {
                return $this->error('Matrícula não encontrada', 404);
            }

            $hoje = date('Y-m-d');
            $novoStatusCodigo = null;

            if ($dataVencimento < $hoje) {
                $novoStatusCodigo = 'vencida';
            } elseif ($dataVencimento >= $hoje && ($matricula['status_codigo'] ?? '') !== 'ativa') {
                $novoStatusCodigo = 'ativa';
            }

            $atualizarDataVencimento = ! empty($matricula['periodo_teste']);
            $novoStatusId = null;

            if ($novoStatusCodigo) {
                $novoStatusId = $this->matriculas->statusIdPorCodigo($novoStatusCodigo);
                if ($novoStatusId === null) {
                    return $this->error("Status '{$novoStatusCodigo}' não encontrado em status_matricula", 422);
                }
            }

            $result = $this->matriculas->atualizarProximaDataVencimento(
                $id,
                $tenantId,
                $dataVencimento,
                $novoStatusId,
                $atualizarDataVencimento
            );

            if ($result['ok']) {
                $statusAtualizado = $this->matriculas->statusAtual($id);

                return [
                    'status' => 200,
                    'body' => [
                        'message' => 'Data de vencimento atualizada com sucesso',
                        'matricula_id' => $id,
                        'proxima_data_vencimento_anterior' => $matricula['proxima_data_vencimento'],
                        'proxima_data_vencimento_nova' => $dataVencimento,
                        'status_anterior' => $matricula['status_codigo'],
                        'status_atual' => $statusAtualizado['status_codigo'] ?? $matricula['status_codigo'],
                        'status_nome' => $statusAtualizado['status_nome'] ?? null,
                    ],
                ];
            }

            return $this->error('Erro ao atualizar data de vencimento', 500);
        } catch (\Throwable $e) {
            $payload = ['error' => 'Erro interno ao atualizar data de vencimento'];
            if (app()->environment() !== 'production') {
                $payload['detalhe'] = $e->getMessage();
                $payload['arquivo'] = basename((string) $e->getFile());
                $payload['linha'] = (int) $e->getLine();
            }

            return ['status' => 500, 'body' => $payload];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function vencimentosHoje(int $tenantId): array
    {
        $hoje = date('Y-m-d');
        $vencimentos = $this->matriculas->vencimentosHoje($tenantId);

        return [
            'status' => 200,
            'body' => [
                'vencimentos' => $vencimentos,
                'total' => count($vencimentos),
                'data' => $hoje,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function proximosVencimentos(int $tenantId, int $dias = 7): array
    {
        $hoje = date('Y-m-d');
        $dataLimite = date('Y-m-d', strtotime("+{$dias} days"));
        $vencimentos = $this->matriculas->proximosVencimentos($tenantId, $dias);

        return [
            'status' => 200,
            'body' => [
                'vencimentos' => $vencimentos,
                'total' => count($vencimentos),
                'periodo' => [
                    'inicio' => $hoje,
                    'fim' => $dataLimite,
                    'dias' => $dias,
                ],
            ],
        ];
    }

    /**
     * Criar matrícula (ramo plano). Pacote ainda não migrado.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, ?int $adminId, array $data): array
    {
        $errors = [];
        $alunoIdInput = $data['aluno_id'] ?? null;
        $usuarioIdInput = $data['usuario_id'] ?? null;

        if (empty($alunoIdInput) && empty($usuarioIdInput)) {
            $errors[] = 'Aluno é obrigatório (envie aluno_id ou usuario_id)';
        }

        if (empty($data['plano_id']) && empty($data['pacote_id'])) {
            $errors[] = 'Plano ou Pacote é obrigatório (envie plano_id ou pacote_id)';
        }

        if (! array_key_exists('dia_vencimento', $data) || $data['dia_vencimento'] === null || $data['dia_vencimento'] === '') {
            $errors[] = 'Dia de vencimento é obrigatório';
        } else {
            $diaVencimento = (int) $data['dia_vencimento'];
            if ($diaVencimento < 1 || $diaVencimento > 31) {
                $errors[] = 'Dia de vencimento deve estar entre 1 e 31';
            }
        }

        if ($errors !== []) {
            return ['status' => 422, 'body' => ['errors' => $errors]];
        }

        if ($alunoIdInput) {
            $usuarioId = $this->matriculas->findUsuarioIdPorAluno((int) $alunoIdInput);
            if ($usuarioId === null) {
                return $this->error('Aluno não encontrado', 404);
            }
            $alunoId = (int) $alunoIdInput;
        } else {
            $usuarioId = (int) $usuarioIdInput;
            $alunoIdResolved = $this->matriculas->findAlunoIdPorUsuario($usuarioId);
            if ($alunoIdResolved === null) {
                return $this->error('Aluno não encontrado na tabela de alunos', 404);
            }
            $alunoId = $alunoIdResolved;
        }

        if (! empty($data['pacote_id'])) {
            return [
                'status' => 501,
                'body' => [
                    'error' => 'Criação de matrícula via pacote ainda não foi migrada para a API v2. Use plano_id ou a API Slim.',
                ],
            ];
        }

        $planoId = (int) $data['plano_id'];

        if (! $this->matriculas->findUsuarioAluno($usuarioId)) {
            return $this->error('Usuário não encontrado ou não é aluno', 404);
        }

        $this->matriculas->ensureVinculoAlunoTenant($usuarioId, $tenantId);

        if (! $this->matriculas->findUsuarioAlunoNoTenant($usuarioId, $tenantId)) {
            return $this->error('Erro ao vincular aluno ao tenant', 500);
        }

        $plano = $this->matriculas->findPlano($planoId, $tenantId);
        if (! $plano) {
            return $this->error('Plano não encontrado', 404);
        }

        $planoCicloId = ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;
        $planoCiclo = null;
        if ($planoCicloId) {
            $planoCiclo = $this->matriculas->findPlanoCicloAtivo($planoCicloId, $planoId, $tenantId);
            if (! $planoCiclo) {
                return $this->error('Ciclo de plano não encontrado ou inativo', 404);
            }
        }

        $periodoTeste = 0;
        $dataInicioCobranca = null;

        if ($planoCiclo) {
            $valorMatricula = $data['valor'] ?? $planoCiclo['valor'];
            $mesesCiclo = (int) ($planoCiclo['meses'] ?? $planoCiclo['frequencia_meses'] ?? 1);
            $duracaoDias = $mesesCiclo * 30;
        } else {
            $valorMatricula = $data['valor'] ?? $plano['valor'];
            $duracaoDias = (int) $plano['duracao_dias'];
        }

        $dataInicio = ! empty($data['data_inicio']) ? (string) $data['data_inicio'] : date('Y-m-d');
        $dataInicioObj = new \DateTime($dataInicio);
        $proximaDataVencimento = clone $dataInicioObj;

        if ($planoCiclo) {
            $mesesCiclo = (int) ($planoCiclo['meses'] ?? $planoCiclo['frequencia_meses'] ?? 1);
            $proximaDataVencimento->modify("+{$mesesCiclo} months");
        } else {
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }

        if ((float) $plano['valor'] == 0.0) {
            $periodoTeste = 1;
            if (! empty($data['data_inicio_cobranca'])) {
                $dataInicioCobranca = $data['data_inicio_cobranca'];
            } else {
                $dataInicioCobranca = (new \DateTime('first day of next month'))->format('Y-m-d');
            }
        }

        $matriculasAtivas = $this->matriculas->matriculasAtivasValidas($alunoId, $tenantId);
        $modalidadeAtual = $plano['modalidade_id'] !== null ? (int) $plano['modalidade_id'] : null;

        $matriculaDuplicada = $this->matriculas->buscarMatriculaDuplicadaMesmoPlanoCiclo(
            $tenantId,
            $alunoId,
            $planoId,
            $planoCicloId,
            $modalidadeAtual
        );
        if ($matriculaDuplicada) {
            $statusDuplicado = $matriculaDuplicada['status_codigo'] ?? 'desconhecido';

            return [
                'status' => 400,
                'body' => [
                    'error' => "Ja existe matricula #{$matriculaDuplicada['id']} com mesma modalidade, plano e ciclo em status {$statusDuplicado}. Reutilize ou ajuste o registro existente antes de criar outra.",
                ],
            ];
        }

        $matriculaMesmaModalidade = null;
        foreach ($matriculasAtivas as $mat) {
            if ((int) ($mat['modalidade_id'] ?? 0) === (int) $modalidadeAtual) {
                $matriculaMesmaModalidade = $mat;
                break;
            }
        }

        $matriculaVencida = $modalidadeAtual !== null
            ? $this->matriculas->ultimaMatriculaNaModalidade($alunoId, $tenantId, $modalidadeAtual)
            : null;
        $reutilizandoMatricula = false;

        if ($matriculaVencida) {
            $statusCodigo = $matriculaVencida['status_codigo'] ?? null;
            $vencimentoAtual = $matriculaVencida['proxima_data_vencimento'] ?? $matriculaVencida['data_vencimento'] ?? null;
            $hoje = date('Y-m-d');
            $vencidaPorData = $vencimentoAtual && $vencimentoAtual < $hoje;

            if ($vencidaPorData && $statusCodigo !== 'vencida') {
                $statusVencidaId = $this->matriculas->statusIdPorCodigo('vencida');
                if ($statusVencidaId !== null) {
                    $this->matriculas->marcarStatusMatricula((int) $matriculaVencida['id'], $tenantId, $statusVencidaId);
                    $statusCodigo = 'vencida';
                }
            }

            if ($statusCodigo === 'vencida' || $statusCodigo === 'cancelada' || $vencidaPorData) {
                $reutilizandoMatricula = true;
            }
        }

        if ($matriculaMesmaModalidade && (int) $matriculaMesmaModalidade['plano_id'] !== $planoId) {
            $dataVencimentoMatricula = $matriculaMesmaModalidade['data_vencimento'] ?? null;
            $hoje = date('Y-m-d');

            if ($dataVencimentoMatricula && $dataVencimentoMatricula >= $hoje) {
                $temPagamento = $this->matriculas->countPagamentoAtivoContasReceber($usuarioId, $tenantId);
                if ($temPagamento > 0) {
                    $dataVencimentoFormatada = date('d/m/Y', strtotime((string) $dataVencimentoMatricula));

                    return $this->error(
                        "Não é possível alterar o plano enquanto o aluno estiver ativo. O plano atual vence em {$dataVencimentoFormatada}. Aguarde o vencimento ou cancele a matrícula atual.",
                        400
                    );
                }
            }
        }

        date_default_timezone_set('America/Sao_Paulo');

        $dataMatricula = date('Y-m-d');
        $dataInicio = ! empty($data['data_inicio']) ? (string) $data['data_inicio'] : $dataMatricula;
        $dataVencimento = date('Y-m-d', strtotime($dataInicio." +{$duracaoDias} days"));
        $valor = $valorMatricula;
        $motivo = $data['motivo'] ?? 'nova';
        $matriculaAnteriorId = null;
        $planoAnteriorId = null;

        if ($matriculaMesmaModalidade) {
            $planoAnteriorId = (int) $matriculaMesmaModalidade['plano_id'];
            $matriculaAnteriorId = (int) $matriculaMesmaModalidade['id'];

            $dataVencimentoAtual = $matriculaMesmaModalidade['proxima_data_vencimento']
                ?? $matriculaMesmaModalidade['data_vencimento'];
            $hoje = date('Y-m-d');

            if ($hoje < $dataVencimentoAtual) {
                $dataFormatada = date('d/m/Y', strtotime((string) $dataVencimentoAtual));

                return [
                    'status' => 400,
                    'body' => [
                        'type' => 'error',
                        'message' => "Já existe um plano vigente nesta modalidade com vencimento em {$dataFormatada}. Aguarde o vencimento para renovar ou trocar de plano.",
                    ],
                ];
            }

            if ($planoId === $planoAnteriorId) {
                $motivo = 'renovacao';
            } else {
                $planoAnt = $this->matriculas->findPlano($planoAnteriorId, $tenantId);
                if ($planoAnt) {
                    $motivo = (float) $plano['valor'] > (float) $planoAnt['valor'] ? 'upgrade' : 'downgrade';
                }
            }

            $atrasos = $this->matriculas->countParcelasEmAtraso((int) $matriculaMesmaModalidade['id']);
            if ($atrasos > 0) {
                return [
                    'status' => 400,
                    'body' => [
                        'type' => 'error',
                        'message' => "Não é possível alterar o plano. Existem {$atrasos} parcela(s) em atraso na matrícula atual. Por favor, regularize os pagamentos antes de prosseguir.",
                    ],
                ];
            }

            $this->matriculas->finalizarMatricula((int) $matriculaMesmaModalidade['id']);
            $this->matriculas->desativarDescontosSeTabelaExiste($tenantId, (int) $matriculaMesmaModalidade['id']);
        }

        $codigoStatus = $periodoTeste === 1 ? 'ativa' : 'pendente';
        $statusId = $this->matriculas->statusIdPorCodigo($codigoStatus)
            ?? ($periodoTeste === 1 ? 1 : 5);
        $motivoId = $this->matriculas->motivoIdPorCodigo((string) $motivo);

        if ($reutilizandoMatricula && $matriculaVencida) {
            $matriculaId = (int) $matriculaVencida['id'];
            $planoAnteriorId = (int) $matriculaVencida['plano_id'];

            $this->matriculas->atualizarMatriculaReuse($matriculaId, $tenantId, [
                'plano_id' => $planoId,
                'plano_ciclo_id' => $planoCicloId,
                'data_matricula' => $dataMatricula,
                'data_inicio' => $dataInicio,
                'data_vencimento' => $dataVencimento,
                'valor' => $valorMatricula,
                'status_id' => $statusId,
                'motivo_id' => $motivoId,
                'plano_anterior_id' => $planoAnteriorId,
                'observacoes' => $data['observacoes'] ?? null,
                'criado_por' => $adminId,
                'dia_vencimento' => (int) $data['dia_vencimento'],
                'periodo_teste' => $periodoTeste,
                'data_inicio_cobranca' => $dataInicioCobranca,
                'proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
            ]);
        } else {
            $matriculaId = $this->matriculas->inserirMatricula([
                'tenant_id' => $tenantId,
                'aluno_id' => $alunoId,
                'plano_id' => $planoId,
                'plano_ciclo_id' => $planoCicloId,
                'data_matricula' => $dataMatricula,
                'data_inicio' => $dataInicio,
                'data_vencimento' => $dataVencimento,
                'valor' => $valorMatricula,
                'status_id' => $statusId,
                'motivo_id' => $motivoId,
                'matricula_anterior_id' => $matriculaAnteriorId,
                'plano_anterior_id' => $planoAnteriorId,
                'observacoes' => $data['observacoes'] ?? null,
                'criado_por' => $adminId,
                'dia_vencimento' => (int) $data['dia_vencimento'],
                'periodo_teste' => $periodoTeste,
                'data_inicio_cobranca' => $dataInicioCobranca,
                'proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
            ]);
        }

        $this->matriculas->inserirHistoricoPlanos([
            'usuario_id' => $usuarioId,
            'plano_anterior_id' => $planoAnteriorId,
            'plano_novo_id' => $planoId,
            'data_inicio' => $dataInicio,
            'data_vencimento' => $dataVencimento,
            'valor_pago' => $valor,
            'motivo' => $motivo,
            'observacoes' => $data['observacoes'] ?? null,
            'criado_por' => $adminId,
        ]);

        if ($periodoTeste !== 1) {
            try {
                // MatriculaDesconto ainda não migrado — cria 1ª parcela pelo valor cheio
                $this->matriculas->inserirPagamentoPlano([
                    'tenant_id' => $tenantId,
                    'aluno_id' => $alunoId,
                    'matricula_id' => $matriculaId,
                    'plano_id' => $planoId,
                    'valor' => (float) $valorMatricula,
                    'valor_original' => (float) $valorMatricula,
                    'desconto' => 0,
                    'motivo_desconto' => null,
                    'data_vencimento' => $dataInicio,
                    'status_pagamento_id' => 1,
                    'observacoes' => 'Primeiro pagamento da matrícula',
                    'criado_por' => $adminId,
                ]);
            } catch (\Throwable $e) {
                Log::error('AdminMatriculaService::criar pagamento_plano: '.$e->getMessage());
            }
        }

        $matricula = $this->matriculas->findMatriculaCriada($matriculaId);
        $pagamentos = $this->matriculas->listarPagamentosCriar($matriculaId);
        $totalPagamentos = (float) array_sum(array_column($pagamentos, 'valor'));

        return [
            'status' => 201,
            'body' => [
                'message' => 'Matrícula realizada com sucesso',
                'matricula' => $matricula,
                'pagamentos' => $pagamentos,
                'total_pagamentos' => $totalPagamentos,
                'info' => $periodoTeste === 1
                    ? "Período teste - Cobrança iniciará em {$dataInicioCobranca}. Acesso garantido até ".$proximaDataVencimento->format('d/m/Y')
                    : 'Acesso garantido até '.$proximaDataVencimento->format('d/m/Y'),
            ],
        ];
    }

    /**
     * Baixa manual em pagamentos_plano.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function darBaixaConta(int $pagamentoId, int $tenantId, ?int $adminId, array $data): array
    {
        $pagamento = $this->matriculas->findPagamentoParaBaixa($pagamentoId, $tenantId);
        if (! $pagamento) {
            return $this->error('Pagamento não encontrado', 404);
        }

        if ((int) $pagamento['status_pagamento_id'] === 2) {
            return $this->error('Pagamento já está marcado como pago', 400);
        }

        $dataVencimento = $data['data_vencimento'] ?? null;
        if (empty($dataVencimento)) {
            return $this->error('data_vencimento é obrigatória para baixa manual', 400);
        }

        try {
            new \DateTime((string) $dataVencimento);
        } catch (\Throwable) {
            return $this->error('data_vencimento inválida', 400);
        }

        $dataPagamento = ! empty($data['data_pagamento']) ? $data['data_pagamento'] : null;
        $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
        $observacoes = $data['observacoes'] ?? null;
        $tipoBaixaId = 1;

        // MatriculaDesconto ainda não migrado na v2 — baixa sem aplicar desconto
        Log::debug('AdminMatriculaService::darBaixaConta: pulando aplicação de desconto (modelo não migrado)');

        $this->matriculas->atualizarPagamentoBaixa($pagamentoId, [
            'status_pagamento_id' => 2,
            'data_vencimento' => $dataVencimento,
            'data_pagamento' => $dataPagamento ?: date('Y-m-d'),
            'forma_pagamento_id' => $formaPagamentoId,
            'observacoes' => $observacoes,
            'baixado_por' => $adminId,
            'tipo_baixa_id' => $tipoBaixaId,
        ]);

        $this->matriculas->ativarMatriculaSePendente((int) $pagamento['matricula_id']);

        $proximaParcela = null;

        try {
            $vencimentoBase = $dataVencimento ?? $pagamento['data_vencimento'];
            $pagamentoVencimento = null;
            if (! empty($vencimentoBase)) {
                try {
                    $pagamentoVencimento = new \DateTime((string) $vencimentoBase);
                } catch (\Throwable) {
                    $pagamentoVencimento = null;
                }
            }

            $pagoDate = null;
            if ($dataPagamento) {
                try {
                    $pagoDate = new \DateTime((string) $dataPagamento);
                } catch (\Throwable) {
                    $pagoDate = null;
                }
            }

            if ($pagoDate && $pagamentoVencimento) {
                $baseDate = ($pagoDate > $pagamentoVencimento) ? $pagoDate : $pagamentoVencimento;
            } elseif ($pagamentoVencimento) {
                $baseDate = $pagamentoVencimento;
            } elseif ($pagoDate) {
                $baseDate = $pagoDate;
            } else {
                $baseDate = new \DateTime('now');
            }

            $mesesCiclo = $pagamento['ciclo_meses'] ?? $pagamento['frequencia_meses'] ?? null;
            if ($mesesCiclo) {
                $proximoVencimento = clone $baseDate;
                $proximoVencimento->modify("+{$mesesCiclo} months");
            } else {
                $duracaoDias = (int) $pagamento['duracao_dias'];
                $proximoVencimento = clone $baseDate;
                $proximoVencimento->add(new \DateInterval("P{$duracaoDias}D"));
            }

            $valorProximaParcela = $pagamento['matricula_valor'] ?? $pagamento['valor'];

            if ((float) $valorProximaParcela > 0.001) {
                $proximaParcelaId = $this->matriculas->inserirPagamentoPlano([
                    'tenant_id' => $pagamento['tenant_id'],
                    'aluno_id' => $pagamento['aluno_id'],
                    'matricula_id' => $pagamento['matricula_id'],
                    'plano_id' => $pagamento['plano_id'],
                    'valor' => (float) $valorProximaParcela,
                    'valor_original' => (float) $valorProximaParcela,
                    'desconto' => 0,
                    'motivo_desconto' => null,
                    'data_vencimento' => $proximoVencimento->format('Y-m-d'),
                    'status_pagamento_id' => 1,
                    'observacoes' => 'Pagamento gerado automaticamente após confirmação',
                    'criado_por' => $adminId,
                ]);

                $proximaParcela = [
                    'id' => $proximaParcelaId,
                    'data_vencimento' => $proximoVencimento->format('Y-m-d'),
                    'valor' => (float) $valorProximaParcela,
                    'valor_original' => (float) $valorProximaParcela,
                    'desconto' => 0,
                    'status' => 'Aguardando',
                ];
            }

            $this->matriculas->atualizarProximaDataVencimentoSimples(
                (int) $pagamento['matricula_id'],
                $proximoVencimento->format('Y-m-d')
            );
        } catch (\Throwable $e) {
            Log::error('AdminMatriculaService::darBaixaConta proxima parcela: '.$e->getMessage());
        }

        $pagamentoAtualizado = $this->matriculas->findPagamentoParaBaixa($pagamentoId, $tenantId);
        $this->pagamentosPlano->atualizarStatusMatricula($tenantId, (int) $pagamento['matricula_id']);

        return [
            'status' => 200,
            'body' => [
                'message' => 'Baixa realizada com sucesso',
                'pagamento' => $pagamentoAtualizado,
                'proxima_parcela' => $proximaParcela,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function alterarPlano(int $id, int $tenantId, ?int $adminId, array $data): array
    {
        if (empty($data['plano_id'])) {
            return $this->error('plano_id é obrigatório', 422);
        }

        $novoPlanoId = (int) $data['plano_id'];
        $novoCicloId = ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null;

        $matricula = $this->matriculas->findParaAlterarPlano($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (($matricula['status_codigo'] ?? '') === 'finalizada') {
            return $this->error('Não é possível alterar plano de matrícula finalizada', 400);
        }

        $migracao = new \App\Services\Mobile\MobileMigracaoPlanoService();
        if ($migracao->temParcelaAtrasada($id, $tenantId)) {
            return $this->error('Há parcela em atraso. Quite o débito antes de alterar o plano.', 400);
        }

        $novoPlano = $this->matriculas->findPlano($novoPlanoId, $tenantId);
        if (! $novoPlano) {
            return $this->error('Novo plano não encontrado', 404);
        }

        $novoCiclo = null;
        if ($novoCicloId) {
            $novoCiclo = $this->matriculas->findCicloDoPlano($novoCicloId, $novoPlanoId, $tenantId);
            if (! $novoCiclo) {
                return $this->error('Ciclo não encontrado ou não pertence ao plano informado', 404);
            }
        }

        $mesmoPlanoCiclo = (int) $matricula['plano_id'] === $novoPlanoId
            && (int) ($matricula['plano_ciclo_id'] ?? 0) === ($novoCicloId ?? 0);
        $podeRenovar = in_array($matricula['status_codigo'] ?? '', ['cancelada', 'vencida'], true);

        if ($mesmoPlanoCiclo && ! $podeRenovar) {
            return $this->error('O plano e ciclo selecionados são iguais aos atuais', 400);
        }

        $ehRenovacao = $mesmoPlanoCiclo && $podeRenovar;

        if ($novoCiclo) {
            $valorNovo = $data['valor'] ?? $novoCiclo['valor'];
            $mesesCiclo = (int) ($novoCiclo['meses'] ?? $novoCiclo['frequencia_meses'] ?? 1);
            $duracaoDias = $mesesCiclo * 30;
        } else {
            $valorNovo = $data['valor'] ?? $novoPlano['valor'];
            $duracaoDias = (int) $novoPlano['duracao_dias'];
            $mesesCiclo = null;
        }

        date_default_timezone_set('America/Sao_Paulo');

        $dataInicio = ! empty($data['data_inicio']) ? trim((string) $data['data_inicio']) : date('Y-m-d');
        $dataInicioObj = \DateTime::createFromFormat('Y-m-d', $dataInicio);
        if (! $dataInicioObj || $dataInicioObj->format('Y-m-d') !== $dataInicio) {
            return $this->error('Formato de data inválido. Use YYYY-MM-DD', 422);
        }
        $proximaDataVencimento = clone $dataInicioObj;

        if ($mesesCiclo) {
            $proximaDataVencimento->modify("+{$mesesCiclo} months");
        } else {
            $proximaDataVencimento->modify("+{$duracaoDias} days");
        }

        $dataVencimento = date('Y-m-d', strtotime($dataInicio." +{$duracaoDias} days"));
        $diaVencimento = $data['dia_vencimento'] ?? $matricula['dia_vencimento'];

        if ($ehRenovacao) {
            $motivo = 'renovacao';
        } else {
            $valorAtual = (float) $matricula['valor'];
            $valorNovoFloat = (float) $valorNovo;
            if ($valorNovoFloat > $valorAtual) {
                $motivo = 'upgrade';
            } elseif ($valorNovoFloat < $valorAtual) {
                $motivo = 'downgrade';
            } else {
                $motivo = 'renovacao';
            }
        }

        $planoAnteriorId = (int) $matricula['plano_id'];
        $alunoId = (int) $matricula['aluno_id'];

        $creditosInfo = $this->matriculas->creditosAluno($tenantId, $alunoId);
        $saldoCreditosExistentes = $creditosInfo['saldo_total'];
        $creditosAtivosParaUsar = $creditosInfo['creditos_ativos'];

        $creditoValor = 0.0;
        $creditoMotivo = $data['motivo_credito'] ?? null;
        $creditoId = null;
        $pagamentoOrigemId = null;
        $creditoGerado = false;
        $diasRestantes = 0;

        $aptidaoCredito = $migracao->avaliarAptidaoMigracao($matricula, $tenantId);
        $podeGerarCreditoMigracao = (bool) ($aptidaoCredito['gera_credito'] ?? false);

        $usarCreditoExistente = ! empty($data['usar_credito_existente']) && $saldoCreditosExistentes > 0;

        if ($podeGerarCreditoMigracao && ! empty($data['abater_plano_anterior'])) {
            $creditoValor = (float) $matricula['valor'];
            if ($creditoValor > 0) {
                $creditoGerado = true;
                $creditoMotivo = $creditoMotivo ?? ('Crédito do plano anterior ('.$matricula['plano_nome'].' - R$'.number_format($creditoValor, 2, ',', '.').')');
            }
        } elseif ($podeGerarCreditoMigracao && ! empty($data['abater_pagamento_anterior'])) {
            $valorCicloAtual = (float) $matricula['valor'];
            $hoje = new \DateTime;
            $dataVencimentoAtual = new \DateTime((string) $matricula['data_vencimento']);
            $dataInicioAtual = new \DateTime((string) $matricula['data_inicio']);

            $totalDiasCicloAtual = max(1, (int) $dataInicioAtual->diff($dataVencimentoAtual)->days);
            $diasRestantes = max(0, (int) $hoje->diff($dataVencimentoAtual)->days);

            // Ciclo vigente: só o proporcional dos dias restantes.
            // Ciclo já encerrado: crédito zero (serviço já foi consumido) — NÃO usar o pagamento cheio.
            if ($diasRestantes > 0 && $hoje <= $dataVencimentoAtual) {
                $creditoValor = round(($valorCicloAtual / $totalDiasCicloAtual) * $diasRestantes, 2);
            } else {
                $creditoValor = 0.0;
                $diasRestantes = 0;
            }

            if ($creditoValor > 0) {
                $creditoGerado = true;
                $creditoMotivo = $creditoMotivo ?? ("Crédito proporcional do plano anterior ({$diasRestantes} dias restantes de R$".number_format($valorCicloAtual, 2, ',', '.').')');
            }
        } elseif ($podeGerarCreditoMigracao && isset($data['credito'])) {
            $creditoValor = (float) $data['credito'];
            $creditoGerado = true;
            $creditoMotivo = $creditoMotivo ?? 'Crédito manual na alteração de plano';
        }

        $totalCredito = $creditoValor;
        if ($usarCreditoExistente) {
            $totalCredito += $saldoCreditosExistentes;
        }

        $creditoAplicado = min($totalCredito, (float) $valorNovo);
        $valorParcela = max(0, (float) $valorNovo - $creditoAplicado);
        $planoGratuito = $valorParcela <= 0.001 && (float) $valorNovo <= 0.001;

        if ($planoGratuito) {
            $statusNovoId = $this->matriculas->statusIdAtiva();
        } else {
            $statusNovoId = $this->matriculas->statusIdPorCodigo('pendente');
        }

        if ($statusNovoId === null) {
            return $this->error('Status de matrícula não configurado no sistema', 500);
        }

        $motivoId = $this->matriculas->motivoIdPorCodigo($motivo);

        $usuarioId = $this->matriculas->findUsuarioIdPorAluno($alunoId);
        if ($usuarioId === null) {
            return $this->error('Aluno não encontrado', 404);
        }

        $creditosUsados = [];
        $creditoExistenteUtilizado = 0.0;
        $parcelasCanceladas = 0;
        $novoPagamentoId = null;

        try {
            DB::transaction(function () use (
                $id,
                $tenantId,
                $adminId,
                $data,
                $matricula,
                $novoPlanoId,
                $novoCicloId,
                $planoAnteriorId,
                $valorNovo,
                $dataInicio,
                $dataVencimento,
                $proximaDataVencimento,
                $diaVencimento,
                $statusNovoId,
                $motivoId,
                $motivo,
                $alunoId,
                $usuarioId,
                $usarCreditoExistente,
                $creditoAplicado,
                $creditoGerado,
                $creditoValor,
                $creditoMotivo,
                $pagamentoOrigemId,
                $creditosAtivosParaUsar,
                $planoGratuito,
                $valorParcela,
                &$creditoId,
                &$creditosUsados,
                &$creditoExistenteUtilizado,
                &$parcelasCanceladas,
                &$novoPagamentoId,
            ) {
                if (($matricula['status_codigo'] ?? '') === 'cancelada') {
                    $this->matriculas->desativarDescontosSeTabelaExiste($tenantId, $id);
                }

                $parcelasCanceladas = $this->pagamentosPlano->cancelarParcelasAbertas(
                    $tenantId,
                    $id,
                    'Cancelado por alteração de plano'
                );

                $this->matriculas->atualizarAposAlterarPlano($id, $tenantId, [
                    'plano_id' => $novoPlanoId,
                    'plano_ciclo_id' => $novoCicloId,
                    'plano_anterior_id' => $planoAnteriorId,
                    'valor' => $valorNovo,
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento,
                    'proxima_data_vencimento' => $proximaDataVencimento->format('Y-m-d'),
                    'dia_vencimento' => $diaVencimento,
                    'status_id' => $statusNovoId,
                    'motivo_id' => $motivoId,
                    'observacoes' => $data['observacoes'] ?? null,
                ]);

                $this->matriculas->inserirHistoricoPlanos([
                    'usuario_id' => $usuarioId,
                    'plano_anterior_id' => $planoAnteriorId,
                    'plano_novo_id' => $novoPlanoId,
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento,
                    'valor_pago' => $valorNovo,
                    'motivo' => $motivo,
                    'observacoes' => $data['observacoes'] ?? 'Alteração de plano via admin',
                    'criado_por' => $adminId,
                ]);

                if ($usarCreditoExistente && $creditoAplicado > 0) {
                    $restanteAplicar = $creditoAplicado;
                    if ($creditoGerado && $creditoValor > 0) {
                        $restanteAplicar = max(0, $creditoAplicado - $creditoValor);
                    }

                    foreach ($creditosAtivosParaUsar as $creditoExistente) {
                        if ($restanteAplicar <= 0.001) {
                            break;
                        }

                        $saldoDisponivel = (float) $creditoExistente['saldo'];
                        $valorUsar = min($saldoDisponivel, $restanteAplicar);

                        if ($this->matriculas->utilizarCredito((int) $creditoExistente['id'], $valorUsar)) {
                            $creditoExistenteUtilizado += $valorUsar;
                            $restanteAplicar -= $valorUsar;
                            $creditosUsados[] = [
                                'id' => (int) $creditoExistente['id'],
                                'valor_usado' => $valorUsar,
                            ];
                        }
                    }
                }

                if ($creditoGerado && $creditoValor > 0) {
                    $creditoId = $this->matriculas->inserirCredito([
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId,
                        'matricula_origem_id' => $id,
                        'pagamento_origem_id' => $pagamentoOrigemId,
                        'valor' => $creditoValor,
                        'motivo' => $creditoMotivo,
                        'criado_por' => $adminId,
                    ]);

                    if ($creditoId !== null) {
                        $novoCredUsado = min($creditoValor, $creditoAplicado - $creditoExistenteUtilizado);
                        $novoCredUsado = max(0, $novoCredUsado);

                        if ($novoCredUsado > 0) {
                            $saldoCredito = $creditoValor - $novoCredUsado;
                            $statusCreditoId = $saldoCredito <= 0.001 ? 2 : 1;
                            $this->matriculas->marcarCreditoUtilizadoParcial($creditoId, $novoCredUsado, $statusCreditoId);
                        }

                        if (! empty($data['abater_pagamento_anterior']) && $pagamentoOrigemId) {
                            $this->matriculas->cancelarPagamentoComoCredito($pagamentoOrigemId, $tenantId);
                        } elseif (! empty($data['abater_plano_anterior'])) {
                            $ultimoPagoB = $this->matriculas->ultimoPagamentoPago($id, $tenantId);
                            if ($ultimoPagoB) {
                                $this->matriculas->cancelarPagamentoComoCredito((int) $ultimoPagoB['id'], $tenantId);
                            }
                        }
                    }
                }

                if (! $planoGratuito) {
                    $novoPagamentoId = $this->matriculas->inserirPagamentoPlano([
                        'tenant_id' => $tenantId,
                        'aluno_id' => $alunoId,
                        'matricula_id' => $id,
                        'plano_id' => $novoPlanoId,
                        'valor' => $valorParcela,
                        'credito_id' => $creditoId,
                        'credito_aplicado' => $creditoAplicado > 0 ? $creditoAplicado : null,
                        'data_vencimento' => $dataInicio,
                        'status_pagamento_id' => 1,
                        'observacoes' => 'Primeiro pagamento - alteração de plano',
                        'criado_por' => $adminId,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error("AdminMatriculaService::alterarPlano #{$id}: ".$e->getMessage());

            return $this->error('Erro ao alterar plano: '.$e->getMessage(), 500);
        }

        $matriculaAtualizada = $this->matriculas->findAposAlterarPlano($id);
        $saldoFinal = $this->matriculas->creditosAluno($tenantId, $alunoId)['saldo_total'];

        return [
            'status' => 200,
            'body' => [
                'message' => 'Plano alterado com sucesso',
                'matricula' => $matriculaAtualizada,
                'plano_anterior' => $matricula['plano_nome'],
                'plano_novo' => $novoPlano['nome'],
                'parcelas_canceladas' => $parcelasCanceladas,
                'novo_pagamento_id' => $novoPagamentoId,
                'valor_plano_novo' => (float) $valorNovo,
                'valor_parcela' => $valorParcela,
                'credito' => $creditoAplicado > 0 ? [
                    'credito_gerado_id' => $creditoId,
                    'credito_gerado_valor' => $creditoGerado ? $creditoValor : 0,
                    'creditos_existentes_usados' => $creditosUsados,
                    'credito_existente_utilizado' => $creditoExistenteUtilizado,
                    'total_aplicado' => $creditoAplicado,
                    'saldo_creditos_restante' => $saldoFinal,
                    'motivo' => $creditoMotivo,
                ] : null,
                'motivo' => $motivo,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function deletePreview(int $id, int $tenantId): array
    {
        $matricula = $this->matriculas->findParaDeletePreview($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        $pagamentosPlano = $this->matriculas->listarPagamentosDeletePreview($id, $tenantId);
        $assinaturas = $this->matriculas->listarAssinaturasDeletePreview($id, $tenantId);
        $assinaturasMp = $this->matriculas->listarAssinaturasMpDeletePreview($id, $tenantId);
        $pagamentosMp = $this->matriculas->listarPagamentosMpDeletePreview($id, $tenantId);

        $totalPagamentosPlano = count($pagamentosPlano);
        $totalPagamentosMp = count($pagamentosMp);
        $totalAssinaturas = count($assinaturas);
        $totalAssinaturasMp = count($assinaturasMp);
        $valorTotalPlano = (float) array_sum(array_column($pagamentosPlano, 'valor'));
        $valorTotalMp = (float) array_sum(array_column($pagamentosMp, 'transaction_amount'));

        return [
            'status' => 200,
            'body' => [
                'resumo' => [
                    'matricula_id' => (int) $matricula['id'],
                    'aluno_id' => (int) $matricula['aluno_id'],
                    'usuario_id' => (int) $matricula['usuario_id'],
                    'status' => $matricula['status_codigo'],
                    'total_pagamentos_plano' => $totalPagamentosPlano,
                    'total_pagamentos_provedor' => $totalPagamentosMp,
                    'total_assinaturas' => $totalAssinaturas,
                    'total_assinaturas_mercadopago' => $totalAssinaturasMp,
                    'valor_total_pagamentos_plano' => $valorTotalPlano,
                    'valor_total_pagamentos_provedor' => $valorTotalMp,
                    'impacto' => [
                        'pagamentos_plano' => ['acao' => 'deletar', 'total' => $totalPagamentosPlano],
                        'pagamentos_mercadopago' => ['acao' => 'deletar', 'total' => $totalPagamentosMp],
                        'assinaturas' => ['acao' => 'desvincular', 'total' => $totalAssinaturas],
                        'assinaturas_mercadopago' => ['acao' => 'deletar', 'total' => $totalAssinaturasMp],
                    ],
                ],
                'matricula' => [
                    'id' => (int) $matricula['id'],
                    'tenant_id' => (int) $matricula['tenant_id'],
                    'aluno_id' => (int) $matricula['aluno_id'],
                    'usuario_id' => (int) $matricula['usuario_id'],
                    'plano_id' => (int) $matricula['plano_id'],
                    'plano_ciclo_id' => $matricula['plano_ciclo_id'],
                    'tipo_cobranca' => $matricula['tipo_cobranca'],
                    'data_matricula' => $matricula['data_matricula'],
                    'data_inicio' => $matricula['data_inicio'],
                    'data_vencimento' => $matricula['data_vencimento'],
                    'dia_vencimento' => $matricula['dia_vencimento'],
                    'periodo_teste' => (int) $matricula['periodo_teste'],
                    'data_inicio_cobranca' => $matricula['data_inicio_cobranca'],
                    'proxima_data_vencimento' => $matricula['proxima_data_vencimento'],
                    'valor' => $matricula['valor'],
                    'status_id' => (int) $matricula['status_id'],
                    'status_codigo' => $matricula['status_codigo'],
                    'status_nome' => $matricula['status_nome'],
                    'motivo_id' => $matricula['motivo_id'],
                    'motivo_codigo' => $matricula['motivo_codigo'],
                    'motivo_nome' => $matricula['motivo_nome'],
                    'matricula_anterior_id' => $matricula['matricula_anterior_id'],
                    'plano_anterior_id' => $matricula['plano_anterior_id'],
                    'observacoes' => $matricula['observacoes'],
                    'created_at' => $matricula['created_at'],
                    'updated_at' => $matricula['updated_at'],
                ],
                'aluno' => [
                    'id' => (int) $matricula['aluno_id'],
                    'usuario_id' => (int) $matricula['usuario_id'],
                    'nome' => $matricula['aluno_nome'],
                    'email' => $matricula['aluno_email'],
                    'telefone' => $matricula['aluno_telefone'],
                ],
                'plano' => [
                    'id' => (int) $matricula['plano_id'],
                    'nome' => $matricula['plano_nome'],
                    'valor' => $matricula['plano_valor'],
                    'duracao_dias' => (int) $matricula['duracao_dias'],
                    'checkins_semanais' => (int) $matricula['checkins_semanais'],
                    'modalidade' => [
                        'id' => $matricula['modalidade_id'],
                        'nome' => $matricula['modalidade_nome'],
                        'icone' => $matricula['modalidade_icone'],
                        'cor' => $matricula['modalidade_cor'],
                    ],
                ],
                'pagamentos_plano' => $pagamentosPlano,
                'assinaturas' => $assinaturas,
                'assinaturas_mercadopago' => $assinaturasMp,
                'pagamentos_mercadopago' => $pagamentosMp,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function destroy(int $id, int $tenantId): array
    {
        $matricula = $this->matriculas->findParaHardDelete($id, $tenantId);
        if (! $matricula) {
            return $this->error('Matrícula não encontrada', 404);
        }

        if (! empty($matricula['pacote_contrato_id'])) {
            return [
                'status' => 422,
                'body' => [
                    'success' => false,
                    'error' => 'Não é possível excluir esta matrícula diretamente pois ela faz parte de um pacote',
                    'message' => 'Para excluir, utilize a exclusão do contrato do pacote, que remove todas as matrículas vinculadas',
                    'pacote_contrato_id' => (int) $matricula['pacote_contrato_id'],
                ],
            ];
        }

        try {
            DB::transaction(function () use ($id, $tenantId, $matricula) {
                $this->matriculas->desvincularAssinaturas($id, $tenantId);
                $this->matriculas->deletarAssinaturasMercadopago($id, $tenantId);
                $this->matriculas->deletarPagamentosMercadopago($id, $tenantId);
                $this->matriculas->deletarPagamentosPlano($id, $tenantId);
                $this->matriculas->deletarMatricula($id, $tenantId);

                $usuarioId = $this->matriculas->findUsuarioIdPorAluno((int) $matricula['aluno_id']);
                if ($usuarioId) {
                    $this->matriculas->clearUsuarioPlanoSeColunasExistem($usuarioId);
                }
            });
        } catch (\Throwable $e) {
            Log::error("AdminMatriculaService::destroy #{$id}: ".$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'error' => 'Erro ao deletar matrícula',
                    'details' => $e->getMessage(),
                ],
            ];
        }

        return [
            'status' => 200,
            'body' => [
                'message' => 'Matrícula deletada com sucesso',
                'matricula_id' => $id,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function error(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => ['error' => $message],
        ];
    }
}
