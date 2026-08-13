<?php

namespace App\Services;

use App\Models\MatriculaDesconto;
use PDO;

class PacoteDescontoService
{
    private PDO $pdo;
    private MatriculaDesconto $descontos;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->descontos = new MatriculaDesconto($pdo);
    }

    public static function prefixoContrato(int $contratoId): string
    {
        return '[PACOTE#' . $contratoId . ']';
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
            . " {$nome} — vigência "
            . self::formatarDataBr($inicio)
            . ' a '
            . self::formatarDataBr($fim);
    }

    public static function calcularValorDesconto(float $valorCheio, float $valorRateado): float
    {
        return round(max(0, $valorCheio - $valorRateado), 2);
    }

    public function resolverValorCheio(int $tenantId, int $planoId, ?int $planoCicloId): float
    {
        if ($planoCicloId) {
            $stmt = $this->pdo->prepare("
                SELECT valor FROM plano_ciclos
                WHERE id = ? AND plano_id = ? AND tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$planoCicloId, $planoId, $tenantId]);
            $cicloValor = $stmt->fetchColumn();
            if ($cicloValor !== false && (float) $cicloValor > 0) {
                return round((float) $cicloValor, 2);
            }
        }

        $stmtPlano = $this->pdo->prepare("
            SELECT valor FROM planos WHERE id = ? AND tenant_id = ? LIMIT 1
        ");
        $stmtPlano->execute([$planoId, $tenantId]);
        $planoValor = $stmtPlano->fetchColumn();

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

            $this->descontos->atualizar($tenantId, (int) $existente['id'], [
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

        return $this->descontos->criar([
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

    public function desativarDescontoPacote(int $tenantId, int $matriculaId, int $contratoId): void
    {
        $prefixo = self::prefixoContrato($contratoId);
        $stmt = $this->pdo->prepare("
            UPDATE matricula_descontos
            SET ativo = 0, updated_at = NOW()
            WHERE tenant_id = ?
              AND matricula_id = ?
              AND ativo = 1
              AND motivo LIKE ?
        ");
        $stmt->execute([$tenantId, $matriculaId, $prefixo . '%']);
        $this->descontos->recalcularDescontosPendentes($tenantId, $matriculaId);
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

        $stmtMat = $this->pdo->prepare("
            UPDATE matriculas
            SET valor = ?, valor_rateado = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmtMat->execute([$valorCheio, $valorRateado, $matriculaId, $tenantId]);

        if ($pagamentoId) {
            $stmtBase = $this->pdo->prepare("
                UPDATE pagamentos_plano
                SET valor_original = ?,
                    pacote_contrato_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtBase->execute([$valorCheio, $contratoId, $pagamentoId, $tenantId]);
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
        $inicioMotivo = (string) ($existente['vigencia_inicio'] ?? $vigenciaInicio);
        $fimMotivo = (string) ($existente['vigencia_fim'] ?? $vigenciaFim);
        $motivo = $descontoId
            ? self::montarMotivo($contratoId, $pacoteNome, $inicioMotivo, $fimMotivo)
            : null;
        $valorFinal = round(max(0, $valorCheio - $valorDesconto), 2);

        if ($pagamentoId) {
            $stmtPag = $this->pdo->prepare("
                UPDATE pagamentos_plano
                SET valor_original = ?,
                    desconto = ?,
                    valor = ?,
                    motivo_desconto = ?,
                    pacote_contrato_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtPag->execute([
                $valorCheio,
                $valorDesconto,
                $valorFinal,
                $motivo,
                $contratoId,
                $pagamentoId,
                $tenantId,
            ]);

            if ($descontoId && $valorDesconto >= 0.01) {
                $this->pdo->prepare('DELETE FROM pagamento_desconto_aplicado WHERE pagamento_plano_id = ?')
                    ->execute([$pagamentoId]);
                $this->descontos->salvarDescontosAplicados($pagamentoId, [[
                    'matricula_desconto_id' => $descontoId,
                    'valor_desconto' => $valorDesconto,
                ]]);
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
     * @return array{id: int, valor_original: float, desconto: float, valor: float, motivo_desconto: ?string}
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

        $stmt = $this->pdo->prepare("
            INSERT INTO pagamentos_plano
            (tenant_id, aluno_id, matricula_id, plano_id, valor, valor_original, desconto, motivo_desconto,
             data_vencimento, status_pagamento_id, pacote_contrato_id, observacoes, criado_por, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $tenantId,
            $alunoId,
            $matriculaId,
            $planoId,
            $info['valor'],
            $info['valor_original'],
            $info['desconto'],
            $info['motivo_desconto'],
            $dataVencimento,
            $statusPagamentoId,
            $contratoId,
            $observacoes,
            $adminId,
        ]);
        $pagamentoId = (int) $this->pdo->lastInsertId();

        if (!empty($info['desconto_id']) && $info['desconto'] >= 0.01) {
            $this->descontos->salvarDescontosAplicados($pagamentoId, [[
                'matricula_desconto_id' => (int) $info['desconto_id'],
                'valor_desconto' => $info['desconto'],
            ]]);
        }

        return array_merge($info, ['id' => $pagamentoId]);
    }

    private function buscarDescontoPacote(int $tenantId, int $matriculaId, int $contratoId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM matricula_descontos
            WHERE tenant_id = ?
              AND matricula_id = ?
              AND motivo LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $matriculaId, self::prefixoContrato($contratoId) . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
