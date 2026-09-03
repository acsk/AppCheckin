<?php

namespace App\Services\Admin;

use App\Repositories\AdminPacoteRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pacotes e contratos admin (paridade Slim PacoteController + AdminController pacote-contratos).
 */
class AdminPacoteService
{
    public function __construct(
        private readonly AdminPacoteRepository $pacotes,
        private readonly PacoteDescontoService $descontos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $tenantId): array
    {
        try {
            $pacotes = $this->pacotes->listarPacotes($tenantId);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'pacotes' => $pacotes,
                    'total' => count($pacotes),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::listar] Erro: '.$e->getMessage());

            return $this->erro('Erro ao listar pacotes', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $tenantId, array $data): array
    {
        try {
            foreach (['nome', 'valor_total', 'qtd_beneficiarios', 'plano_id'] as $field) {
                if (empty($data[$field])) {
                    return $this->erro("{$field} é obrigatório", 400);
                }
            }

            $id = $this->pacotes->inserirPacote([
                'tenant_id' => $tenantId,
                'nome' => $data['nome'],
                'descricao' => $data['descricao'] ?? null,
                'valor_total' => (float) $data['valor_total'],
                'qtd_beneficiarios' => (int) $data['qtd_beneficiarios'],
                'plano_id' => (int) $data['plano_id'],
                'plano_ciclo_id' => ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null,
            ]);

            return [
                'status' => 201,
                'body' => ['success' => true, 'pacote_id' => $id],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::criar] Erro: '.$e->getMessage());

            return $this->erro('Erro ao criar pacote', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $tenantId, int $pacoteId, array $data): array
    {
        try {
            if ($pacoteId <= 0) {
                return $this->erro('id inválido', 400);
            }

            if (! $this->pacotes->pacoteExiste($pacoteId, $tenantId)) {
                return $this->erro('Pacote não encontrado', 404);
            }

            $this->pacotes->atualizarPacote($pacoteId, $tenantId, [
                'nome' => $data['nome'] ?? '',
                'descricao' => $data['descricao'] ?? null,
                'valor_total' => (float) ($data['valor_total'] ?? 0),
                'qtd_beneficiarios' => (int) ($data['qtd_beneficiarios'] ?? 1),
                'plano_id' => (int) ($data['plano_id'] ?? 0),
                'plano_ciclo_id' => ! empty($data['plano_ciclo_id']) ? (int) $data['plano_ciclo_id'] : null,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]);

            return [
                'status' => 200,
                'body' => ['success' => true, 'message' => 'Pacote atualizado'],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::atualizar] Erro: '.$e->getMessage());

            return $this->erro('Erro ao atualizar pacote', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function contratar(int $tenantId, int $pacoteId, array $data): array
    {
        try {
            if ($pacoteId <= 0) {
                return $this->erro('pacoteId inválido', 400);
            }

            if (empty($data['pagante_usuario_id'])) {
                return $this->erro('pagante_usuario_id é obrigatório', 400);
            }

            $pacote = $this->pacotes->findPacoteAtivoComPlano($pacoteId, $tenantId);
            if (! $pacote) {
                return $this->erro('Pacote não encontrado', 404);
            }

            $beneficiarios = isset($data['beneficiarios']) ? (array) $data['beneficiarios'] : [];
            $beneficiarios = array_values(array_unique(array_filter(array_map('intval', $beneficiarios), fn ($id) => $id > 0)));

            if (count($beneficiarios) > (int) $pacote['qtd_beneficiarios']) {
                return $this->erro('Quantidade de beneficiários excede o limite do pacote', 400);
            }

            $paganteAlunoId = $this->pacotes->findAlunoIdDoPaganteNoTenant($tenantId, (int) $data['pagante_usuario_id']);
            if ($paganteAlunoId <= 0) {
                return $this->erro('Pagante não possui cadastro de aluno no tenant', 404);
            }

            $beneficiariosFinais = array_values(array_unique(array_merge([$paganteAlunoId], $beneficiarios)));
            $limiteTotalPessoas = (int) $pacote['qtd_beneficiarios'] + 1;

            if (count($beneficiariosFinais) > $limiteTotalPessoas) {
                return $this->erro("Quantidade total de pessoas excede o limite do pacote ({$limiteTotalPessoas})", 400);
            }

            $beneficiariosValidos = $this->pacotes->filtrarAlunosValidosNoTenant($tenantId, $beneficiariosFinais);
            $beneficiariosNaoEncontrados = array_values(array_diff($beneficiariosFinais, $beneficiariosValidos));

            if ($beneficiariosNaoEncontrados !== []) {
                return $this->erro(
                    'Beneficiários não encontrados no tenant: '.implode(', ', $beneficiariosNaoEncontrados),
                    404
                );
            }

            $result = DB::transaction(function () use ($tenantId, $pacoteId, $data, $pacote, $beneficiariosFinais, $paganteAlunoId) {
                $contratoId = $this->pacotes->inserirContrato(
                    $tenantId,
                    $pacoteId,
                    (int) $data['pagante_usuario_id'],
                    (float) $pacote['valor_total']
                );

                $dataInicio = date('Y-m-d');
                $dataFim = $this->calcularDataFim(
                    $dataInicio,
                    ! empty($pacote['plano_ciclo_id']) ? (int) $pacote['plano_ciclo_id'] : null,
                    $tenantId,
                    max(1, (int) ($pacote['duracao_dias'] ?? 30))
                );

                $statusPendenteId = $this->pacotes->statusMatriculaId('pendente', 5);
                $motivoNovaId = $this->pacotes->motivoMatriculaId('nova', 1);

                $totalPessoas = count($beneficiariosFinais);
                $valorTotal = (float) $pacote['valor_total'];
                $valorBaseRateio = round($valorTotal / max(1, $totalPessoas), 2);
                $valorAcumulado = 0.0;

                $valorCheio = $this->descontos->resolverValorCheio(
                    $tenantId,
                    (int) $pacote['plano_id'],
                    ! empty($pacote['plano_ciclo_id']) ? (int) $pacote['plano_ciclo_id'] : null
                );
                if ($valorCheio < 0.01) {
                    $valorCheio = $valorBaseRateio;
                }

                $matriculasCriadas = [];
                foreach ($beneficiariosFinais as $index => $alunoId) {
                    $isLast = ($index === ($totalPessoas - 1));
                    $valorRateado = $isLast
                        ? round($valorTotal - $valorAcumulado, 2)
                        : $valorBaseRateio;
                    $valorAcumulado += $valorRateado;

                    $diaVencimento = (int) date('d', strtotime($dataFim));

                    $matriculaId = $this->pacotes->inserirMatriculaContrato([
                        'tenant_id' => $tenantId,
                        'aluno_id' => (int) $alunoId,
                        'plano_id' => (int) $pacote['plano_id'],
                        'plano_ciclo_id' => ! empty($pacote['plano_ciclo_id']) ? (int) $pacote['plano_ciclo_id'] : null,
                        'pacote_contrato_id' => $contratoId,
                        'data_matricula' => $dataInicio,
                        'data_inicio' => $dataInicio,
                        'data_vencimento' => $dataFim,
                        'valor' => $valorCheio,
                        'valor_rateado' => $valorRateado,
                        'status_id' => $statusPendenteId,
                        'motivo_id' => $motivoNovaId,
                        'proxima_data_vencimento' => $dataFim,
                        'dia_vencimento' => $diaVencimento,
                    ]);

                    $this->pacotes->inserirBeneficiarioComMatricula(
                        $tenantId,
                        $contratoId,
                        (int) $alunoId,
                        $matriculaId,
                        $valorRateado
                    );

                    $this->descontos->criarPagamentoPacote(
                        $tenantId,
                        (int) $alunoId,
                        $matriculaId,
                        (int) $pacote['plano_id'],
                        $contratoId,
                        (string) ($pacote['nome'] ?? 'Pacote'),
                        $valorCheio,
                        $valorRateado,
                        $dataFim,
                        $dataInicio,
                        null,
                        'Pagamento do pacote'
                    );

                    $matriculasCriadas[] = [
                        'aluno_id' => (int) $alunoId,
                        'matricula_id' => $matriculaId,
                        'valor_rateado' => $valorRateado,
                        'is_pagante' => ((int) $alunoId === $paganteAlunoId),
                    ];
                }

                return [
                    'pacote_contrato_id' => $contratoId,
                    'matriculas' => $matriculasCriadas,
                    'total_matriculas' => count($matriculasCriadas),
                ];
            });

            return [
                'status' => 201,
                'body' => array_merge(['success' => true], $result),
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::contratar] Erro: '.$e->getMessage());

            return $this->erro('Erro ao contratar pacote', 500);
        }
    }

    /**
     * @param  list<int|string>  $beneficiarios
     * @return array{status: int, body: array<string, mixed>}
     */
    public function definirBeneficiarios(int $tenantId, int $contratoId, array $beneficiarios): array
    {
        try {
            if ($contratoId <= 0) {
                return $this->erro('contratoId inválido', 400);
            }

            $contrato = $this->pacotes->findContratoComLimite($contratoId, $tenantId);
            if (! $contrato) {
                return $this->erro('Contrato não encontrado', 404);
            }

            if (count($beneficiarios) > (int) $contrato['qtd_beneficiarios']) {
                return $this->erro('Quantidade de beneficiários excede o limite do pacote', 400);
            }

            DB::transaction(function () use ($tenantId, $contratoId, $beneficiarios) {
                $this->pacotes->deletarBeneficiarios($contratoId, $tenantId);

                foreach ($beneficiarios as $alunoId) {
                    $this->pacotes->inserirBeneficiario($tenantId, $contratoId, (int) $alunoId);
                }
            });

            return [
                'status' => 200,
                'body' => ['success' => true, 'message' => 'Beneficiários atualizados'],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::definirBeneficiarios] Erro: '.$e->getMessage());

            return $this->erro('Erro ao atualizar beneficiários', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function confirmarPagamento(int $tenantId, int $contratoId, array $data): array
    {
        try {
            if ($contratoId <= 0) {
                return $this->erro('contratoId inválido', 400);
            }

            $contrato = $this->pacotes->findContratoParaConfirmar($contratoId, $tenantId);
            if (! $contrato) {
                return $this->erro('Contrato não encontrado', 404);
            }

            $beneficiarios = $this->pacotes->listarBeneficiariosDoContrato($contratoId, $tenantId);
            if ($beneficiarios === []) {
                return $this->erro('Nenhum beneficiário definido', 400);
            }

            DB::transaction(function () use ($tenantId, $contratoId, $data, $contrato, $beneficiarios) {
                $valorTotal = (float) $contrato['valor_total'];
                $valorRateado = $valorTotal / max(1, count($beneficiarios));

                $dataInicio = date('Y-m-d');
                $dataFim = $this->calcularDataFim(
                    $dataInicio,
                    ! empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                    $tenantId,
                    max(1, $this->pacotes->duracaoDiasDoPlano((int) $contrato['plano_id'], $tenantId) ?: 30)
                );

                $this->pacotes->ativarContrato(
                    $contratoId,
                    $tenantId,
                    $data['pagamento_id'] ?? null,
                    $dataInicio,
                    $dataFim
                );

                $statusAtivaId = $this->pacotes->statusMatriculaId('ativa', 1);
                $motivoId = $this->pacotes->motivoMatriculaId('nova', 1);
                $statusPagoId = $this->pacotes->statusPagamentoId('aprovado', 2);

                $valorCheio = $this->descontos->resolverValorCheio(
                    $tenantId,
                    (int) $contrato['plano_id'],
                    ! empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null
                );
                if ($valorCheio < 0.01) {
                    $valorCheio = $valorRateado;
                }

                foreach ($beneficiarios as $ben) {
                    $matriculaId = $this->pacotes->inserirMatriculaPacote([
                        'tenant_id' => $tenantId,
                        'aluno_id' => (int) $ben['aluno_id'],
                        'plano_id' => (int) $contrato['plano_id'],
                        'plano_ciclo_id' => ! empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                        'tipo_cobranca' => 'avulso',
                        'data_matricula' => $dataInicio,
                        'data_inicio' => $dataInicio,
                        'data_vencimento' => $dataFim,
                        'valor' => $valorCheio,
                        'valor_rateado' => $valorRateado,
                        'status_id' => $statusAtivaId,
                        'motivo_id' => $motivoId,
                        'proxima_data_vencimento' => $dataFim,
                        'pacote_contrato_id' => $contratoId,
                    ]);

                    $this->pacotes->vincularBeneficiarioAMatricula(
                        (int) $ben['id'],
                        $tenantId,
                        $matriculaId,
                        $valorRateado
                    );

                    $this->descontos->criarPagamentoPacote(
                        $tenantId,
                        (int) $ben['aluno_id'],
                        $matriculaId,
                        (int) $contrato['plano_id'],
                        $contratoId,
                        (string) ($contrato['pacote_nome'] ?? 'Pacote'),
                        $valorCheio,
                        $valorRateado,
                        $dataFim,
                        $dataInicio,
                        null,
                        'Pacote rateado',
                        $statusPagoId
                    );
                }
            });

            return [
                'status' => 200,
                'body' => ['success' => true, 'message' => 'Pacote ativado e matrículas criadas'],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::confirmarPagamento] Erro: '.$e->getMessage());

            return $this->erro('Erro ao confirmar pagamento do pacote', 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluirContrato(int $tenantId, int $contratoId): array
    {
        try {
            if ($contratoId <= 0) {
                return $this->erro('contratoId inválido', 400);
            }

            $contrato = $this->pacotes->findContratoParaExcluir($contratoId, $tenantId);
            if (! $contrato) {
                return $this->erro('Contrato não encontrado', 404);
            }

            $detalhes = DB::transaction(function () use ($tenantId, $contratoId, $contrato) {
                $pagamentosRemovidos = $this->pacotes->deletarPagamentosDoContrato($contratoId, $tenantId);
                $matriculasRemovidas = $this->pacotes->deletarMatriculasDoContrato($contratoId, $tenantId);
                $beneficiariosRemovidos = $this->pacotes->deletarBeneficiarios($contratoId, $tenantId);
                $this->pacotes->deletarContrato($contratoId, $tenantId);

                error_log("[AdminPacoteService::excluirContrato] Contrato #{$contratoId} excluído em cascata: "
                    ."{$pagamentosRemovidos} pagamentos, {$matriculasRemovidas} matrículas, "
                    ."{$beneficiariosRemovidos} beneficiários removidos");

                return [
                    'contrato_id' => $contratoId,
                    'pacote_nome' => $contrato['pacote_nome'],
                    'pagamentos_removidos' => $pagamentosRemovidos,
                    'matriculas_removidas' => $matriculasRemovidas,
                    'beneficiarios_removidos' => $beneficiariosRemovidos,
                ];
            });

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Contrato excluído com sucesso',
                    'detalhes' => $detalhes,
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::excluirContrato] Erro: '.$e->getMessage());

            return $this->erro('Erro ao excluir contrato do pacote', 500);
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarContratos(int $tenantId, string $status): array
    {
        try {
            $status = strtolower(trim($status ?: 'pendente'));
            $statusValidos = ['pendente', 'ativo', 'cancelado', 'expirado'];

            if (! in_array($status, $statusValidos, true)) {
                return $this->erro('Status inválido. Use: '.implode(', ', $statusValidos), 400);
            }

            if (in_array($status, ['pendente', 'cancelado', 'expirado'], true)) {
                $contratos = $this->pacotes->listarContratosBasico($tenantId, $status);

                return [
                    'status' => 200,
                    'body' => [
                        'success' => true,
                        'status_filtro' => $status,
                        'contratos' => $contratos,
                        'total' => count($contratos),
                    ],
                ];
            }

            $contratos = $this->pacotes->listarContratosAtivos($tenantId, $status);
            $contratosFormatados = [];

            foreach ($contratos as $contrato) {
                $contratoId = (int) $contrato['contrato_id'];
                $matriculas = $this->pacotes->listarMatriculasGeradas($contratoId);
                $pagante = $this->pacotes->findAlunoDoUsuario((int) $contrato['pagante_usuario_id']);
                $beneficiarios = $this->pacotes->listarBeneficiariosComAluno($contratoId);

                $contratosFormatados[] = [
                    'contrato' => $contrato,
                    'pagante' => $pagante,
                    'beneficiarios' => $beneficiarios,
                    'matriculas_geradas' => $matriculas,
                    'qtd_pessoas' => count($beneficiarios) + ($pagante ? 1 : 0),
                    'qtd_matriculas_faltando' => max(0, (count($beneficiarios) + ($pagante ? 1 : 0)) - count($matriculas)),
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'status_filtro' => $status,
                    'contratos' => $contratosFormatados,
                    'total' => count($contratosFormatados),
                ],
            ];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::listarContratos] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao listar contratos',
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function gerarMatriculas(int $tenantId, int $contratoId): array
    {
        try {
            if ($contratoId <= 0) {
                return $this->erro('contratoId inválido', 400);
            }

            $contrato = $this->pacotes->findContratoParaGerarMatriculas($contratoId, $tenantId);
            if (! $contrato) {
                return $this->erro('Contrato não encontrado', 404);
            }

            if (($contrato['status'] ?? '') !== 'ativo') {
                return $this->erro('Contrato não está ativo. Status atual: '.$contrato['status'], 400);
            }

            $result = DB::transaction(function () use ($tenantId, $contratoId, $contrato) {
                $paganteUsuarioId = $contrato['pagante_usuario_id'] ?? null;
                $paganteAlunoId = $paganteUsuarioId
                    ? $this->pacotes->findPrimeiroAlunoIdDoUsuario((int) $paganteUsuarioId)
                    : 0;

                $beneficiarios = $this->pacotes->listarBeneficiariosDoContrato($contratoId, $tenantId);

                $todasAsMatriculas = [];
                $alunosJaAdicionados = [];

                if ($paganteAlunoId > 0) {
                    $todasAsMatriculas[] = [
                        'id' => 'pagante_'.$paganteUsuarioId,
                        'aluno_id' => $paganteAlunoId,
                        'tipo' => 'pagante',
                    ];
                    $alunosJaAdicionados[$paganteAlunoId] = true;
                }

                foreach ($beneficiarios as $b) {
                    $alunoId = (int) $b['aluno_id'];
                    if (isset($alunosJaAdicionados[$alunoId])) {
                        continue;
                    }
                    $todasAsMatriculas[] = [
                        'id' => $b['id'],
                        'aluno_id' => $alunoId,
                        'tipo' => 'beneficiario',
                    ];
                    $alunosJaAdicionados[$alunoId] = true;
                }

                if ($todasAsMatriculas === []) {
                    throw new \RuntimeException('Nenhuma matrícula para criar (sem pagante e sem beneficiários)');
                }

                $valorTotal = (float) $contrato['valor_total'];
                $valorRateado = $valorTotal / max(1, count($todasAsMatriculas));

                $dataInicio = $contrato['data_inicio'] ?? date('Y-m-d');
                $dataFim = $contrato['data_fim'];

                if (! $dataFim) {
                    if (! empty($contrato['plano_ciclo_id'])) {
                        $meses = $this->pacotes->mesesDoCicloSemTenant((int) $contrato['plano_ciclo_id']) ?: 1;
                        $dataFim = date('Y-m-d', strtotime("+{$meses} months", strtotime($dataInicio)));
                    } else {
                        $dataFim = date('Y-m-d', strtotime('+30 days', strtotime($dataInicio)));
                    }
                }

                $statusAtivaId = $this->pacotes->statusMatriculaId('ativa', 1);
                $motivoId = $this->pacotes->motivoMatriculaId('nova', 1);
                $tipoCobranca = (bool) ($contrato['permite_recorrencia'] ?? false) ? 'recorrente' : 'avulso';

                $assinaturaPacote = null;
                if (! empty($contrato['assinatura_id'])) {
                    $assinaturaPacote = $this->pacotes->findAssinaturaDoContrato((int) $contrato['assinatura_id'], $tenantId);
                }

                $matriculasCriadas = [];

                foreach ($todasAsMatriculas as $ben) {
                    $ehPagante = ($ben['tipo'] === 'pagante');
                    $matriculaExistente = $this->pacotes->findMatriculaDoAlunoNoContrato(
                        (int) $ben['aluno_id'],
                        $contratoId,
                        $tenantId
                    );

                    if ($matriculaExistente) {
                        $this->pacotes->atualizarMatriculaPacote(
                            (int) $matriculaExistente['id'],
                            $tenantId,
                            $statusAtivaId,
                            $dataFim,
                            $valorRateado
                        );
                        $matriculaId = (int) $matriculaExistente['id'];
                    } else {
                        $matriculaId = $this->pacotes->inserirMatriculaPacote([
                            'tenant_id' => $tenantId,
                            'aluno_id' => (int) $ben['aluno_id'],
                            'plano_id' => (int) $contrato['plano_id'],
                            'plano_ciclo_id' => ! empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null,
                            'tipo_cobranca' => $tipoCobranca,
                            'data_matricula' => $dataInicio,
                            'data_inicio' => $dataInicio,
                            'data_vencimento' => $dataFim,
                            'valor' => $valorRateado,
                            'valor_rateado' => $valorRateado,
                            'status_id' => $statusAtivaId,
                            'motivo_id' => $motivoId,
                            'proxima_data_vencimento' => $dataFim,
                            'pacote_contrato_id' => $contratoId,
                        ]);
                    }

                    $matriculasCriadas[] = [
                        'aluno_id' => $ben['aluno_id'],
                        'matricula_id' => $matriculaId,
                        'tipo' => $ben['tipo'],
                        'valor_rateado' => $valorRateado,
                        'vencimento' => $dataFim,
                    ];

                    if ($ehPagante && $assinaturaPacote) {
                        $this->pacotes->vincularAssinaturaAMatricula(
                            (int) $assinaturaPacote['id'],
                            $tenantId,
                            $matriculaId,
                            (int) $ben['aluno_id'],
                            $valorRateado
                        );
                    }
                }

                return [
                    'contrato_id' => $contratoId,
                    'assinatura_id' => $assinaturaPacote ? (int) $assinaturaPacote['id'] : null,
                    'matriculas_criadas' => count($matriculasCriadas),
                    'matriculas' => $matriculasCriadas,
                ];
            });

            return [
                'status' => 200,
                'body' => array_merge([
                    'success' => true,
                    'message' => 'Matrículas geradas com sucesso',
                ], $result),
            ];
        } catch (\RuntimeException $e) {
            return $this->erro($e->getMessage(), 400);
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::gerarMatriculas] Erro: '.$e->getMessage());

            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => 'Erro ao gerar matrículas',
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * POST /admin/matriculas com pacote_id (paridade MatriculaController::criarMatriculaPacote).
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criarMatriculaPacote(
        int $tenantId,
        ?int $adminId,
        int $alunoId,
        int $usuarioId,
        array $data
    ): array {
        try {
            $pacoteId = (int) $data['pacote_id'];
            $dependentesIds = isset($data['dependentes']) ? array_map('intval', (array) $data['dependentes']) : [];
            $diaVencimento = (int) ($data['dia_vencimento'] ?? 10);
            $observacoes = $data['observacoes'] ?? null;
            $dataInicio = ! empty($data['data_inicio']) ? (string) $data['data_inicio'] : date('Y-m-d');

            $pacote = $this->pacotes->findPacoteAtivoComPlano($pacoteId, $tenantId);
            if (! $pacote) {
                return ['status' => 404, 'body' => ['error' => 'Pacote não encontrado ou inativo']];
            }

            $beneficiariosIds = array_values(array_unique(array_merge([$alunoId], $dependentesIds)));
            $totalBeneficiarios = count($beneficiariosIds);
            $limiteTotal = (int) $pacote['qtd_beneficiarios'] + 1;

            if ($totalBeneficiarios > $limiteTotal) {
                return [
                    'status' => 400,
                    'body' => [
                        'error' => "Quantidade total de pessoas ({$totalBeneficiarios}) excede o limite do pacote ({$limiteTotal}: 1 pagante + {$pacote['qtd_beneficiarios']} beneficiário(s))",
                    ],
                ];
            }

            if ($dependentesIds !== []) {
                $depsValidos = $this->pacotes->filtrarAlunosValidosNoTenant($tenantId, $dependentesIds);
                $depsNaoEncontrados = array_diff($dependentesIds, $depsValidos);
                if ($depsNaoEncontrados !== []) {
                    return [
                        'status' => 404,
                        'body' => ['error' => 'Dependentes não encontrados: '.implode(', ', $depsNaoEncontrados)],
                    ];
                }
            }

            $valorTotal = (float) $pacote['valor_total'];
            $valorRateado = round($valorTotal / $totalBeneficiarios, 2);
            $planoId = (int) $pacote['plano_id'];
            $planoCicloId = (! empty($pacote['plano_ciclo_id']) && ! empty($pacote['ciclo_meses']))
                ? (int) $pacote['plano_ciclo_id']
                : null;

            $valorCheio = $this->descontos->resolverValorCheio($tenantId, $planoId, $planoCicloId);
            if ($valorCheio < 0.01) {
                $valorCheio = $valorRateado;
            }

            $dataInicioObj = new \DateTime($dataInicio);
            $proximaDataVencimento = clone $dataInicioObj;

            if (! empty($pacote['ciclo_meses']) && (int) $pacote['ciclo_meses'] > 0) {
                $mesesCiclo = (int) $pacote['ciclo_meses'];
                $proximaDataVencimento->modify("+{$mesesCiclo} months");
            } else {
                $duracaoDias = max(1, (int) ($pacote['duracao_dias'] ?? 30));
                $proximaDataVencimento->modify("+{$duracaoDias} days");
            }

            $dataVencimento = $proximaDataVencimento->format('Y-m-d');
            $statusId = $this->pacotes->statusMatriculaId('pendente', 5);
            $motivoId = $this->pacotes->motivoMatriculaId('nova', 1);

            $result = DB::transaction(function () use (
                $tenantId, $pacoteId, $adminId, $usuarioId, $alunoId, $beneficiariosIds,
                $pacote, $valorTotal, $valorRateado, $planoId, $planoCicloId, $valorCheio,
                $dataInicio, $dataVencimento, $statusId, $motivoId, $observacoes, $diaVencimento,
                $totalBeneficiarios, $proximaDataVencimento
            ) {
                $contratoId = $this->pacotes->inserirContrato(
                    $tenantId,
                    $pacoteId,
                    $usuarioId,
                    $valorTotal
                );

                $matriculasCriadas = [];

                foreach ($beneficiariosIds as $benAlunoId) {
                    $modalidadeId = isset($pacote['modalidade_id']) ? (int) $pacote['modalidade_id'] : null;
                    $duplicada = app(AdminMatriculaRepository::class)->buscarMatriculaDuplicadaMesmoPlanoCiclo(
                        $tenantId,
                        $benAlunoId,
                        $planoId,
                        $planoCicloId,
                        $modalidadeId
                    );

                    if ($duplicada) {
                        $nomeAluno = DB::table('alunos')->where('id', $benAlunoId)->value('nome') ?: "ID {$benAlunoId}";
                        throw new \RuntimeException(
                            "Aluno {$nomeAluno} ja possui matricula #{$duplicada['id']} com mesmo plano/ciclo/modalidade em status ".($duplicada['status_codigo'] ?? 'desconhecido')
                        );
                    }

                    $matriculaId = $this->pacotes->inserirMatriculaContrato([
                        'tenant_id' => $tenantId,
                        'aluno_id' => $benAlunoId,
                        'plano_id' => $planoId,
                        'plano_ciclo_id' => $planoCicloId,
                        'pacote_contrato_id' => $contratoId,
                        'data_matricula' => date('Y-m-d'),
                        'data_inicio' => $dataInicio,
                        'data_vencimento' => $dataVencimento,
                        'valor' => $valorCheio,
                        'valor_rateado' => $valorRateado,
                        'status_id' => $statusId,
                        'motivo_id' => $motivoId,
                        'proxima_data_vencimento' => $dataVencimento,
                        'dia_vencimento' => $diaVencimento,
                    ]);

                    $this->pacotes->inserirBeneficiarioComMatricula(
                        $tenantId,
                        $contratoId,
                        $benAlunoId,
                        $matriculaId,
                        $valorRateado
                    );

                    $this->descontos->criarPagamentoPacote(
                        $tenantId,
                        $benAlunoId,
                        $matriculaId,
                        $planoId,
                        $contratoId,
                        (string) ($pacote['nome'] ?? 'Pacote'),
                        $valorCheio,
                        $valorRateado,
                        $dataVencimento,
                        $dataInicio,
                        $adminId,
                        'Pagamento pacote - rateado'
                    );

                    $nomeAluno = DB::table('alunos')->where('id', $benAlunoId)->value('nome') ?: '';

                    $matriculasCriadas[] = [
                        'matricula_id' => $matriculaId,
                        'aluno_id' => $benAlunoId,
                        'aluno_nome' => $nomeAluno,
                        'valor_rateado' => $valorRateado,
                        'is_pagante' => ($benAlunoId === $alunoId),
                        'reutilizada' => false,
                    ];
                }

                DB::insert('
                    INSERT INTO historico_planos
                    (usuario_id, plano_novo_id, data_inicio, data_vencimento, valor_pago, motivo, observacoes, criado_por)
                    VALUES (?, ?, ?, ?, ?, \'nova\', ?, ?)
                ', [
                    $usuarioId,
                    $planoId,
                    $dataInicio,
                    $dataVencimento,
                    $valorTotal,
                    "Pacote: {$pacote['nome']} ({$totalBeneficiarios} beneficiários)",
                    $adminId,
                ]);

                return [
                    'contratoId' => $contratoId,
                    'matriculasCriadas' => $matriculasCriadas,
                ];
            });

            return [
                'status' => 201,
                'body' => [
                    'message' => "Pacote contratado com sucesso - {$totalBeneficiarios} matrícula(s) criada(s)",
                    'pacote_contrato_id' => $result['contratoId'],
                    'pacote' => [
                        'id' => $pacoteId,
                        'nome' => $pacote['nome'],
                        'valor_total' => $valorTotal,
                        'valor_rateado' => $valorRateado,
                        'plano_nome' => $pacote['plano_nome'] ?? null,
                        'qtd_beneficiarios' => $totalBeneficiarios,
                    ],
                    'matriculas' => $result['matriculasCriadas'],
                    'data_inicio' => $dataInicio,
                    'data_vencimento' => $dataVencimento,
                    'info' => 'Pacote ativo até '.$proximaDataVencimento->format('d/m/Y'),
                ],
            ];
        } catch (\RuntimeException $e) {
            return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::criarMatriculaPacote] Erro: '.$e->getMessage());

            return ['status' => 500, 'body' => ['error' => 'Erro ao criar matrícula de pacote: '.$e->getMessage()]];
        }
    }

    /**
     * POST /admin/matriculas/pacote-contrato/{contratoId}/baixa
     *
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function darBaixaPacote(int $contratoId, int $tenantId, ?int $adminId, array $data): array
    {
        try {
            $contrato = $this->pacotes->findContratoParaBaixa($contratoId, $tenantId);
            if (! $contrato) {
                return ['status' => 404, 'body' => ['error' => 'Contrato de pacote não encontrado']];
            }

            $dataPagamento = $data['data_pagamento'] ?? date('Y-m-d');
            $formaPagamentoId = $data['forma_pagamento_id'] ?? null;
            $observacoes = $data['observacoes'] ?? null;

            $result = DB::transaction(function () use (
                $contratoId, $tenantId, $adminId, $contrato, $dataPagamento, $formaPagamentoId, $observacoes
            ) {
                $pagamentos = $this->garantirPagamentosPendentesPacote($contratoId, $tenantId, $adminId, $contrato);

                if ($pagamentos === []) {
                    $jaPago = $this->pacotes->pacoteJaPossuiPagamentoPago($contratoId, $tenantId);

                    throw new \RuntimeException(
                        $jaPago
                            ? 'Este pacote já está pago'
                            : 'Nenhum pagamento pendente para este pacote. Verifique se as matrículas do contrato existem.'
                    );
                }

                $matriculasAtivadas = [];
                $proximasParcelas = [];

                foreach ($pagamentos as $pagamento) {
                    $this->pacotes->marcarPagamentoComoPago($pagamento['id'], [
                        'data_pagamento' => $dataPagamento,
                        'forma_pagamento_id' => $formaPagamentoId,
                        'observacoes' => $observacoes,
                        'baixado_por' => $adminId,
                    ]);

                    $this->pacotes->ativarMatriculaSePendenteOuVencida((int) $pagamento['matricula_id']);

                    $matriculasAtivadas[] = [
                        'matricula_id' => (int) $pagamento['matricula_id'],
                        'aluno_id' => (int) $pagamento['aluno_id'],
                        'aluno_nome' => $pagamento['aluno_nome'],
                        'valor' => (float) $pagamento['valor'],
                    ];

                    try {
                        $dataVencimentoAtual = new \DateTime($pagamento['data_vencimento']);
                        $mesesCiclo = $pagamento['ciclo_meses'] ?? $pagamento['frequencia_meses'] ?? null;

                        if ($mesesCiclo) {
                            $proximoVencimento = clone $dataVencimentoAtual;
                            $proximoVencimento->modify("+{$mesesCiclo} months");
                        } else {
                            $duracaoDias = max(1, (int) ($pagamento['duracao_dias'] ?? 30));
                            $proximoVencimento = clone $dataVencimentoAtual;
                            $proximoVencimento->add(new \DateInterval("P{$duracaoDias}D"));
                        }

                        $valorProxima = (float) ($pagamento['valor_original'] ?? $pagamento['matricula_valor'] ?? 0);
                        if ($valorProxima < 0.01) {
                            $valorProxima = (float) ($pagamento['valor'] ?? 0) + (float) ($pagamento['desconto'] ?? 0);
                        }

                        $proximaId = $this->pacotes->inserirProximaParcela(
                            $tenantId,
                            (int) $pagamento['aluno_id'],
                            (int) $pagamento['matricula_id'],
                            (int) ($pagamento['plano_id'] ?? 0) ?: null,
                            $valorProxima,
                            $proximoVencimento->format('Y-m-d'),
                            $adminId
                        );

                        $proximasParcelas[] = [
                            'id' => $proximaId,
                            'aluno_nome' => $pagamento['aluno_nome'],
                            'data_vencimento' => $proximoVencimento->format('Y-m-d'),
                            'valor' => $valorProxima,
                        ];

                        $this->pacotes->atualizarVencimentosMatricula(
                            (int) $pagamento['matricula_id'],
                            $dataVencimentoAtual->format('Y-m-d'),
                            $proximoVencimento->format('Y-m-d')
                        );
                    } catch (\Throwable $e) {
                        error_log("[darBaixaPacote] Erro próxima parcela matrícula {$pagamento['matricula_id']}: ".$e->getMessage());
                    }
                }

                $this->pacotes->ativarContratoComVigenciaDasMatriculas($contratoId, $tenantId);
                $this->pacotes->ativarBeneficiariosDoContrato($contratoId, $tenantId);

                return [
                    'matriculasAtivadas' => $matriculasAtivadas,
                    'proximasParcelas' => $proximasParcelas,
                ];
            });

            $valorTotal = array_sum(array_column($result['matriculasAtivadas'], 'valor'));

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Baixa do pacote realizada com sucesso',
                    'pacote' => $contrato['pacote_nome'],
                    'contrato_id' => $contratoId,
                    'valor_total' => $valorTotal,
                    'matriculas_ativadas' => $result['matriculasAtivadas'],
                    'proximas_parcelas' => $result['proximasParcelas'],
                    'total_beneficiarios' => count($result['matriculasAtivadas']),
                ],
            ];
        } catch (\RuntimeException $e) {
            return ['status' => 400, 'body' => ['error' => $e->getMessage()]];
        } catch (Throwable $e) {
            error_log('[AdminPacoteService::darBaixaPacote] Erro: '.$e->getMessage());

            return ['status' => 500, 'body' => ['error' => 'Erro ao dar baixa no pacote: '.$e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $contrato
     * @return list<array<string, mixed>>
     */
    private function garantirPagamentosPendentesPacote(
        int $contratoId,
        int $tenantId,
        ?int $adminId,
        array $contrato
    ): array {
        $pagamentos = $this->pacotes->buscarPagamentosPendentesPacote($contratoId, $tenantId);

        if ($pagamentos !== []) {
            foreach ($pagamentos as $pagamento) {
                if (empty($pagamento['pacote_contrato_id'])) {
                    $this->pacotes->vincularPagamentoAoContrato((int) $pagamento['id'], $tenantId, $contratoId);
                }
            }
        } else {
            $matriculas = $this->pacotes->listarMatriculasDoContrato($contratoId, $tenantId);
            if ($matriculas === []) {
                return [];
            }

            foreach ($matriculas as $matricula) {
                $existente = $this->pacotes->findUltimoPagamentoDaMatricula((int) $matricula['id'], $tenantId);
                if ($existente) {
                    if (empty($existente['pacote_contrato_id'])) {
                        $this->pacotes->vincularPagamentoAoContrato((int) $existente['id'], $tenantId, $contratoId);
                    }

                    continue;
                }

                $valor = (float) ($matricula['valor_rateado'] ?? $matricula['valor'] ?? 0);
                $vencimento = $matricula['data_vencimento'] ?: ($matricula['data_inicio'] ?: date('Y-m-d'));
                $planoId = (int) ($matricula['plano_id'] ?: ($contrato['plano_id'] ?? 0)) ?: null;

                $this->pacotes->inserirPagamentoPendentePacote(
                    $tenantId,
                    (int) $matricula['aluno_id'],
                    (int) $matricula['id'],
                    $planoId,
                    $valor,
                    (string) $vencimento,
                    $contratoId,
                    $adminId
                );
            }
        }

        $pagamentos = $this->pacotes->buscarPagamentosPendentesPacote($contratoId, $tenantId);
        if ($pagamentos === []) {
            return [];
        }

        $planoId = (int) ($contrato['plano_id'] ?? 0);
        $cicloId = ! empty($contrato['plano_ciclo_id']) ? (int) $contrato['plano_ciclo_id'] : null;
        $valorCheio = $this->descontos->resolverValorCheio($tenantId, $planoId, $cicloId);
        $pacoteNome = (string) ($contrato['pacote_nome'] ?? 'Pacote');

        foreach ($pagamentos as $pagamento) {
            $valorRateado = (float) ($pagamento['valor_rateado_beneficiario']
                ?? $pagamento['matricula_valor_rateado']
                ?? 0);
            if ($valorRateado < 0.01) {
                $valorRateado = (float) ($pagamento['valor'] ?? 0);
            }
            $valorBase = $valorCheio > 0.01 ? $valorCheio : (float) ($pagamento['matricula_valor'] ?? $valorRateado);
            $inicio = (string) ($pagamento['matricula_data_inicio']
                ?: ($contrato['data_inicio'] ?? $pagamento['data_vencimento']));
            $fim = (string) ($pagamento['data_vencimento'] ?? $inicio);

            $this->descontos->prepararParcelaPacote(
                $tenantId,
                (int) $pagamento['matricula_id'],
                $contratoId,
                $pacoteNome,
                $valorBase,
                $valorRateado,
                $inicio,
                $fim,
                (int) $pagamento['id'],
                $adminId
            );
        }

        return $this->pacotes->buscarPagamentosPendentesPacote($contratoId, $tenantId);
    }

    private function calcularDataFim(string $dataInicio, ?int $planoCicloId, int $tenantId, int $duracaoDias): string
    {
        if ($planoCicloId) {
            $meses = $this->pacotes->mesesDoCiclo($planoCicloId, $tenantId);
            if ($meses > 0) {
                return (new \DateTime($dataInicio))->modify("+{$meses} months")->format('Y-m-d');
            }
        }

        return date('Y-m-d', strtotime("+{$duracaoDias} days", strtotime($dataInicio)));
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    private function erro(string $message, int $status): array
    {
        return [
            'status' => $status,
            'body' => ['success' => false, 'message' => $message],
        ];
    }
}
