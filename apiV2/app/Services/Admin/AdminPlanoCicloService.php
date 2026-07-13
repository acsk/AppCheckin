<?php

namespace App\Services\Admin;

use App\Repositories\AdminPlanoCicloRepository;
use App\Repositories\AdminPlanoRepository;

class AdminPlanoCicloService
{
    public function __construct(
        private readonly AdminPlanoCicloRepository $ciclos,
        private readonly AdminPlanoRepository $planos,
    ) {}

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listarFrequencias(): array
    {
        try {
            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => $this->ciclos->listarFrequencias(),
                ],
            ];
        } catch (\Throwable) {
            return ['status' => 500, 'body' => ['error' => 'Erro interno']];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function listar(int $planoId, int $tenantId, ?string $ativoParam): array
    {
        try {
            $plano = $this->planos->findBasico($planoId, $tenantId);
            if (! $plano) {
                return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
            }

            $filtroAtivo = null;
            if ($ativoParam !== null && $ativoParam !== '') {
                $filtroAtivo = (int) $ativoParam;
            }

            $raw = $this->ciclos->listarPorPlano($planoId, $tenantId, $filtroAtivo);
            $formatados = $this->formatarCiclosAdmin($raw);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'plano' => $plano,
                    'ciclos' => $formatados,
                    'total' => count($formatados),
                ],
            ];
        } catch (\Throwable) {
            return ['status' => 500, 'body' => ['error' => 'Erro interno']];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function criar(int $planoId, int $tenantId, array $data): array
    {
        try {
            $frequenciaId = $data['assinatura_frequencia_id'] ?? $data['tipo_ciclo_id'] ?? null;
            $errors = [];
            if (empty($frequenciaId)) {
                $errors[] = 'Frequência de assinatura é obrigatória';
            }
            if (! isset($data['valor']) || $data['valor'] < 0) {
                $errors[] = 'Valor é obrigatório';
            }
            if ($errors !== []) {
                return ['status' => 422, 'body' => ['errors' => $errors]];
            }

            $tipo = $this->ciclos->findFrequencia((int) $frequenciaId);
            if (! $tipo) {
                return ['status' => 404, 'body' => ['error' => 'Frequência de assinatura não encontrada']];
            }

            $plano = $this->planos->findBasico($planoId, $tenantId);
            if (! $plano) {
                return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
            }

            $permiteRecorrencia = isset($data['permite_recorrencia']) ? (int) $data['permite_recorrencia'] : 1;
            $permiteReposicao = isset($data['permite_reposicao']) ? (int) $data['permite_reposicao'] : 1;

            if ($this->ciclos->existeMesmaFrequenciaRecorrencia($planoId, (int) $frequenciaId, $permiteRecorrencia)) {
                $tipoCobranca = $permiteRecorrencia ? 'com recorrência' : 'sem recorrência (avulso)';

                return [
                    'status' => 400,
                    'body' => [
                        'error' => "Já existe um ciclo {$tipo['nome']} {$tipoCobranca} para este plano",
                    ],
                ];
            }

            $meses = (int) $tipo['meses'];
            $valorMensalBase = (float) $plano['valor'];
            $valorTotalSemDesconto = $valorMensalBase * $meses;
            $descontoPercentual = $valorTotalSemDesconto > 0
                ? round((($valorTotalSemDesconto - (float) $data['valor']) / $valorTotalSemDesconto) * 100, 2)
                : 0;

            $cicloId = $this->ciclos->create([
                'tenant_id' => $tenantId,
                'plano_id' => $planoId,
                'assinatura_frequencia_id' => (int) $frequenciaId,
                'meses' => $meses,
                'valor' => (float) $data['valor'],
                'desconto_percentual' => $descontoPercentual,
                'permite_recorrencia' => $permiteRecorrencia,
                'permite_reposicao' => $permiteReposicao,
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : 1,
            ]);

            return [
                'status' => 201,
                'body' => [
                    'success' => true,
                    'message' => 'Ciclo criado com sucesso',
                    'id' => $cicloId,
                    'permite_recorrencia' => (bool) $permiteRecorrencia,
                    'permite_reposicao' => (bool) $permiteReposicao,
                ],
            ];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['error' => 'Erro interno: '.$e->getMessage()]];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function atualizar(int $planoId, int $cicloId, int $tenantId, array $data): array
    {
        try {
            $ciclo = $this->ciclos->findCiclo($cicloId, $planoId, $tenantId);
            if (! $ciclo) {
                return ['status' => 404, 'body' => ['error' => 'Ciclo não encontrado']];
            }

            $valor = isset($data['valor']) ? (float) $data['valor'] : (float) $ciclo['valor'];
            $meses = (int) ($ciclo['tipo_meses'] ?? 1);
            if ($meses < 1) {
                $meses = 1;
            }
            $valorMensalBase = (float) ($ciclo['plano_valor'] ?? 0);
            $valorTotalSemDesconto = $valorMensalBase * $meses;
            $descontoPercentual = $valorTotalSemDesconto > 0
                ? round((($valorTotalSemDesconto - $valor) / $valorTotalSemDesconto) * 100, 2)
                : 0;
            $descontoPercentual = max(-999.99, min(999.99, $descontoPercentual));

            $this->ciclos->update($cicloId, $tenantId, [
                'valor' => $valor,
                'desconto_percentual' => $descontoPercentual,
                'permite_recorrencia' => isset($data['permite_recorrencia'])
                    ? (int) $data['permite_recorrencia']
                    : (int) $ciclo['permite_recorrencia'],
                'permite_reposicao' => isset($data['permite_reposicao'])
                    ? (int) $data['permite_reposicao']
                    : (int) $ciclo['permite_reposicao'],
                'ativo' => isset($data['ativo']) ? (int) $data['ativo'] : (int) $ciclo['ativo'],
            ]);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Ciclo atualizado com sucesso',
                    'desconto_percentual' => $descontoPercentual,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 500,
                'body' => ['error' => 'Erro ao atualizar ciclo: '.$e->getMessage()],
            ];
        }
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function excluir(int $planoId, int $cicloId, int $tenantId): array
    {
        try {
            $ciclo = $this->ciclos->findCiclo($cicloId, $planoId, $tenantId);
            if (! $ciclo) {
                return ['status' => 404, 'body' => ['error' => 'Ciclo não encontrado']];
            }

            $total = $this->ciclos->countMatriculas($cicloId);
            if ($total > 0) {
                return [
                    'status' => 400,
                    'body' => [
                        'error' => "Não é possível excluir. Existem {$total} matrícula(s) vinculada(s) a este ciclo.",
                    ],
                ];
            }

            $this->ciclos->delete($cicloId);

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'message' => 'Ciclo excluído com sucesso',
                ],
            ];
        } catch (\Throwable) {
            return ['status' => 500, 'body' => ['error' => 'Erro interno']];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{status: int, body: array<string, mixed>}
     */
    public function gerar(int $planoId, int $tenantId, array $data): array
    {
        try {
            $plano = $this->planos->findBasico($planoId, $tenantId);
            if (! $plano) {
                return ['status' => 404, 'body' => ['error' => 'Plano não encontrado']];
            }

            $existentes = $this->ciclos->ciclosExistentesComMatriculas($planoId, $tenantId);
            $ciclosPorFrequencia = [];
            $ciclosComMatriculas = [];
            foreach ($existentes as $ciclo) {
                $fid = (int) $ciclo['assinatura_frequencia_id'];
                $ciclosPorFrequencia[$fid] = $ciclo;
                if ((int) $ciclo['total_matriculas'] > 0) {
                    $ciclosComMatriculas[$fid] = (int) $ciclo['total_matriculas'];
                }
            }

            $tiposCiclo = $this->ciclos->listarTodasFrequenciasAtivas();
            if ($tiposCiclo === []) {
                return [
                    'status' => 400,
                    'body' => ['error' => 'Nenhuma frequência de assinatura cadastrada'],
                ];
            }

            $descontosDefault = [];
            foreach ($tiposCiclo as $tipo) {
                $meses = (int) $tipo['meses'];
                $descontosDefault[$tipo['codigo']] = match (true) {
                    $meses <= 1 => 0,
                    $meses == 2 => 10,
                    $meses == 3 => 15,
                    $meses == 4 => 20,
                    $meses >= 6 && $meses < 12 => 25,
                    $meses >= 12 => 30,
                    default => 0,
                };
            }

            $descontos = $descontosDefault;
            foreach ($tiposCiclo as $tipo) {
                $chave = 'desconto_'.$tipo['codigo'];
                if (isset($data[$chave])) {
                    $descontos[$tipo['codigo']] = (float) $data[$chave];
                }
            }

            $permiteReposicao = isset($data['permite_reposicao']) ? (int) $data['permite_reposicao'] : 1;
            $ciclosCriados = [];
            $ciclosIgnorados = [];

            foreach ($tiposCiclo as $tipo) {
                $meses = (int) $tipo['meses'];
                if ($meses < 1) {
                    continue;
                }

                $frequenciaId = (int) $tipo['id'];
                $desconto = $descontos[$tipo['codigo']] ?? 0;
                $valorBase = (float) $plano['valor'] * $meses;
                $valorComDesconto = round($valorBase * (1 - ($desconto / 100)), 2);

                if (isset($ciclosPorFrequencia[$frequenciaId])) {
                    $cicloExistente = $ciclosPorFrequencia[$frequenciaId];
                    if (isset($ciclosComMatriculas[$frequenciaId])) {
                        $ciclosIgnorados[] = [
                            'assinatura_frequencia_id' => $frequenciaId,
                            'nome' => $tipo['nome'],
                            'motivo' => "Possui {$ciclosComMatriculas[$frequenciaId]} matrícula(s) vinculada(s)",
                            'valor_atual' => (float) $cicloExistente['valor'],
                            'permite_reposicao' => (bool) $cicloExistente['permite_reposicao'],
                        ];
                        continue;
                    }

                    $this->ciclos->updateGerado(
                        (int) $cicloExistente['id'],
                        $valorComDesconto,
                        (float) $desconto,
                        $meses,
                        isset($data['permite_reposicao']) ? (int) $data['permite_reposicao'] : null,
                    );

                    $permiteReposicaoAtual = isset($data['permite_reposicao'])
                        ? (int) $data['permite_reposicao']
                        : (int) ($cicloExistente['permite_reposicao'] ?? 1);

                    $ciclosCriados[] = [
                        'assinatura_frequencia_id' => $frequenciaId,
                        'nome' => $tipo['nome'],
                        'meses' => $meses,
                        'valor' => $valorComDesconto,
                        'desconto' => $desconto,
                        'economia' => round($valorBase - $valorComDesconto, 2),
                        'acao' => 'atualizado',
                        'permite_reposicao' => (bool) $permiteReposicaoAtual,
                    ];
                } else {
                    $this->ciclos->create([
                        'tenant_id' => $tenantId,
                        'plano_id' => $planoId,
                        'assinatura_frequencia_id' => $frequenciaId,
                        'meses' => $meses,
                        'valor' => $valorComDesconto,
                        'desconto_percentual' => $desconto,
                        'permite_recorrencia' => 1,
                        'permite_reposicao' => $permiteReposicao,
                        'ativo' => 1,
                    ]);

                    $ciclosCriados[] = [
                        'assinatura_frequencia_id' => $frequenciaId,
                        'nome' => $tipo['nome'],
                        'meses' => $meses,
                        'valor' => $valorComDesconto,
                        'desconto' => $desconto,
                        'economia' => round($valorBase - $valorComDesconto, 2),
                        'acao' => 'criado',
                        'permite_reposicao' => (bool) $permiteReposicao,
                    ];
                }
            }

            $body = [
                'success' => true,
                'message' => 'Ciclos gerados com sucesso',
                'ciclos' => $ciclosCriados,
            ];
            if ($ciclosIgnorados !== []) {
                $body['ciclos_ignorados'] = $ciclosIgnorados;
                $body['aviso'] = 'Alguns ciclos não foram atualizados pois possuem matrículas vinculadas';
            }

            return ['status' => 200, 'body' => $body];
        } catch (\Throwable $e) {
            return ['status' => 500, 'body' => ['error' => 'Erro interno: '.$e->getMessage()]];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $ciclos
     * @return list<array<string, mixed>>
     */
    private function formatarCiclosAdmin(array $ciclos): array
    {
        $valorMensalReferencia = 0.0;
        foreach ($ciclos as $c) {
            if ((int) $c['meses'] === 1) {
                $valorMensalReferencia = (float) $c['valor_mensal_equivalente'];
                break;
            }
        }
        if ($valorMensalReferencia <= 0 && $ciclos !== []) {
            $menorMeses = PHP_INT_MAX;
            foreach ($ciclos as $c) {
                if ((int) $c['meses'] < $menorMeses) {
                    $menorMeses = (int) $c['meses'];
                    $valorMensalReferencia = (float) $c['valor_mensal_equivalente'];
                }
            }
        }

        $out = [];
        foreach ($ciclos as $ciclo) {
            $item = [
                'id' => (int) $ciclo['id'],
                'assinatura_frequencia_id' => (int) $ciclo['assinatura_frequencia_id'],
                'nome' => $ciclo['nome'],
                'codigo' => $ciclo['codigo'],
                'meses' => (int) $ciclo['meses'],
                'valor' => (float) $ciclo['valor'],
                'valor_mensal_equivalente' => (float) $ciclo['valor_mensal_equivalente'],
                'permite_recorrencia' => (bool) $ciclo['permite_recorrencia'],
                'permite_reposicao' => (bool) $ciclo['permite_reposicao'],
                'ativo' => (bool) $ciclo['ativo'],
                'ordem' => $ciclo['ordem'] ?? null,
                'valor_formatado' => 'R$ '.number_format((float) $ciclo['valor'], 2, ',', '.'),
                'valor_mensal_formatado' => 'R$ '.number_format((float) $ciclo['valor_mensal_equivalente'], 2, ',', '.'),
            ];

            $economiaPercentual = 0.0;
            $economiaValor = 0.0;
            if ($valorMensalReferencia > 0 && $item['valor_mensal_equivalente'] < $valorMensalReferencia) {
                $economiaPercentual = round((($valorMensalReferencia - $item['valor_mensal_equivalente']) / $valorMensalReferencia) * 100, 1);
                $economiaValor = round(($valorMensalReferencia - $item['valor_mensal_equivalente']) * $item['meses'], 2);
            }
            $item['desconto_percentual'] = $economiaPercentual;
            $item['economia_valor'] = $economiaValor;
            $item['economia_formatada'] = $economiaValor > 0
                ? 'R$ '.number_format($economiaValor, 2, ',', '.').' de economia'
                : null;

            $out[] = $item;
        }

        return $out;
    }
}
