<?php

namespace App\Services\Admin;

use App\Repositories\AdminPacoteRepository;

/**
 * Port de App\Services\PacoteDescontoService (Slim).
 *
 * Mantém a matrícula no valor cheio do plano e representa o rateio do pacote
 * como desconto recorrente (`matricula_descontos`) aplicado na parcela.
 */
class PacoteDescontoService
{
    public function __construct(
        private readonly AdminPacoteRepository $pacotes,
    ) {}

    public static function prefixoContrato(int $contratoId): string
    {
        return '[PACOTE#'.$contratoId.']';
    }

    public static function formatarDataBr(string $dataIso): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', substr($dataIso, 0, 10));

        return $dt ? $dt->format('d/m/Y') : $dataIso;
    }

    public static function montarMotivo(int $contratoId, string $pacoteNome, string $inicio, string $fim): string
    {
        $nome = trim($pacoteNome) !== '' ? trim($pacoteNome) : 'Pacote';

        return self::prefixoContrato($contratoId)
            ." {$nome} — vigência "
            .self::formatarDataBr($inicio)
            .' a '
            .self::formatarDataBr($fim);
    }

    public static function calcularValorDesconto(float $valorCheio, float $valorRateado): float
    {
        return round(max(0, $valorCheio - $valorRateado), 2);
    }

    public function resolverValorCheio(int $tenantId, int $planoId, ?int $planoCicloId): float
    {
        if ($planoCicloId) {
            $cicloValor = $this->pacotes->valorDoCiclo($planoCicloId, $planoId, $tenantId);
            if ($cicloValor !== null && $cicloValor > 0) {
                return round($cicloValor, 2);
            }
        }

        $planoValor = $this->pacotes->valorDoPlano($planoId, $tenantId);

        return round(max(0, (float) ($planoValor ?: 0)), 2);
    }

    /**
     * Cria ou atualiza o desconto recorrente do pacote na matrícula.
     */
    public function sincronizarDesconto(
        int $tenantId,
        int $matriculaId,
        int $contratoId,
        string $pacoteNome,
        float $valorDesconto,
        string $vigenciaInicio,
        string $vigenciaFim,
        ?int $adminId
    ): ?int {
        if ($valorDesconto < 0.01) {
            return null;
        }

        $motivo = self::montarMotivo($contratoId, $pacoteNome, $vigenciaInicio, $vigenciaFim);
        $existente = $this->buscarDescontoPacote($tenantId, $matriculaId, $contratoId);

        if ($existente) {
            $inicioAtual = (string) ($existente['vigencia_inicio'] ?? $vigenciaInicio);
            $inicio = ($inicioAtual !== '' && $inicioAtual < $vigenciaInicio) ? $inicioAtual : $vigenciaInicio;
            $fimAtual = (string) ($existente['vigencia_fim'] ?? '');
            $fim = ($fimAtual !== '' && $fimAtual > $vigenciaFim) ? $fimAtual : $vigenciaFim;

            $this->pacotes->atualizarDesconto($tenantId, (int) $existente['id'], [
                'tipo' => 'recorrente',
                'valor' => $valorDesconto,
                'percentual' => null,
                'vigencia_inicio' => $inicio,
                'vigencia_fim' => $fim,
                'parcelas_restantes' => null,
                'motivo' => self::montarMotivo($contratoId, $pacoteNome, $inicio, $fim),
                'ativo' => 1,
            ]);

            return (int) $existente['id'];
        }

        return $this->pacotes->inserirDesconto([
            'tenant_id' => $tenantId,
            'matricula_id' => $matriculaId,
            'tipo' => 'recorrente',
            'valor' => $valorDesconto,
            'percentual' => null,
            'vigencia_inicio' => $vigenciaInicio,
            'vigencia_fim' => $vigenciaFim,
            'parcelas_restantes' => null,
            'motivo' => $motivo,
            'criado_por' => $adminId,
            'autorizado_por' => $adminId,
        ]);
    }

    /**
     * Garante valor cheio na matrícula, desconto vigente e parcela no valor rateado.
     *
     * @return array{valor_original: float, desconto: float, valor: float, motivo_desconto: ?string, desconto_id: ?int}
     */
    public function prepararParcelaPacote(
        int $tenantId,
        int $matriculaId,
        int $contratoId,
        string $pacoteNome,
        float $valorCheio,
        float $valorRateado,
        string $vigenciaInicio,
        string $vigenciaFim,
        ?int $pagamentoId,
        ?int $adminId
    ): array {
        if ($valorCheio < 0.01) {
            $valorCheio = $valorRateado;
        }

        $valorDesconto = self::calcularValorDesconto($valorCheio, $valorRateado);

        $this->pacotes->atualizarValoresDaMatricula($matriculaId, $tenantId, $valorCheio, $valorRateado);

        if ($pagamentoId) {
            $this->pacotes->atualizarValorOriginalDoPagamento($pagamentoId, $tenantId, $valorCheio, $contratoId);
        }

        $descontoId = $this->sincronizarDesconto(
            $tenantId,
            $matriculaId,
            $contratoId,
            $pacoteNome,
            $valorDesconto,
            $vigenciaInicio,
            $vigenciaFim,
            $adminId
        );

        $existente = $this->buscarDescontoPacote($tenantId, $matriculaId, $contratoId);
        $inicioMotivo = (string) (($existente ?? [])['vigencia_inicio'] ?? $vigenciaInicio);
        $fimMotivo = (string) (($existente ?? [])['vigencia_fim'] ?? $vigenciaFim);
        $motivo = $descontoId
            ? self::montarMotivo($contratoId, $pacoteNome, $inicioMotivo, $fimMotivo)
            : null;
        $valorFinal = round(max(0, $valorCheio - $valorDesconto), 2);

        if ($pagamentoId) {
            $this->pacotes->aplicarDescontoNoPagamento($pagamentoId, $tenantId, [
                'valor_original' => $valorCheio,
                'desconto' => $valorDesconto,
                'valor' => $valorFinal,
                'motivo_desconto' => $motivo,
                'pacote_contrato_id' => $contratoId,
            ]);

            if ($descontoId && $valorDesconto >= 0.01) {
                $this->pacotes->limparDescontosAplicados($pagamentoId);
                $this->pacotes->salvarDescontoAplicado($pagamentoId, $descontoId, $valorDesconto);
            }
        }

        return [
            'valor_original' => $valorCheio,
            'desconto' => $valorDesconto,
            'valor' => $valorFinal,
            'motivo_desconto' => $motivo,
            'desconto_id' => $descontoId,
        ];
    }

    /**
     * @return array{id: int, valor_original: float, desconto: float, valor: float, motivo_desconto: ?string, desconto_id: ?int}
     */
    public function criarPagamentoPacote(
        int $tenantId,
        int $alunoId,
        int $matriculaId,
        int $planoId,
        int $contratoId,
        string $pacoteNome,
        float $valorCheio,
        float $valorRateado,
        string $dataVencimento,
        string $vigenciaInicio,
        ?int $adminId,
        string $observacoes = 'Pagamento do pacote',
        int $statusPagamentoId = 1
    ): array {
        $info = $this->prepararParcelaPacote(
            $tenantId,
            $matriculaId,
            $contratoId,
            $pacoteNome,
            $valorCheio,
            $valorRateado,
            $vigenciaInicio,
            $dataVencimento,
            null,
            $adminId
        );

        $pagamentoId = $this->pacotes->inserirPagamentoPacote([
            'tenant_id' => $tenantId,
            'aluno_id' => $alunoId,
            'matricula_id' => $matriculaId,
            'plano_id' => $planoId,
            'valor' => $info['valor'],
            'valor_original' => $info['valor_original'],
            'desconto' => $info['desconto'],
            'motivo_desconto' => $info['motivo_desconto'],
            'data_vencimento' => $dataVencimento,
            'status_pagamento_id' => $statusPagamentoId,
            'pacote_contrato_id' => $contratoId,
            'observacoes' => $observacoes,
            'criado_por' => $adminId,
        ]);

        if (! empty($info['desconto_id']) && $info['desconto'] >= 0.01) {
            $this->pacotes->salvarDescontoAplicado($pagamentoId, (int) $info['desconto_id'], $info['desconto']);
        }

        return array_merge($info, ['id' => $pagamentoId]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarDescontoPacote(int $tenantId, int $matriculaId, int $contratoId): ?array
    {
        return $this->pacotes->findDescontoPorPrefixoMotivo(
            $tenantId,
            $matriculaId,
            self::prefixoContrato($contratoId)
        );
    }
}
