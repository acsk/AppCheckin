<?php

namespace App\Services;

use App\Repositories\MatriculaRepository;
use App\Support\AcademyDateTime;
use App\Support\StatusPagamentoPlano;
use Illuminate\Support\Facades\DB;

/**
 * Avaliação de aptidão para migração/alteração com crédito (sem depender da API Slim).
 */
class MatriculaMigracaoAptidaoService
{
    public function __construct(
        private readonly MatriculaRepository $matriculas,
    ) {}

    public function temParcelaAtrasada(int $matriculaId, int $tenantId): bool
    {
        return DB::table('pagamentos_plano')
            ->where('tenant_id', $tenantId)
            ->where('matricula_id', $matriculaId)
            ->whereIn('status_pagamento_id', StatusPagamentoPlano::EM_ABERTO)
            ->whereNull('data_pagamento')
            ->where('data_vencimento', '<', DB::raw('CURDATE()'))
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $matricula
     * @return array{apto: bool, gera_credito: bool, code: string, message: string, motivo: string|null}
     */
    public function avaliarAptidaoMigracao(array $matricula, int $tenantId): array
    {
        $status = strtolower(trim((string) ($matricula['status_codigo'] ?? '')));
        $matriculaId = (int) ($matricula['id'] ?? 0);
        // Normalizadas para Y-m-d: o banco pode devolver datetime ("2026-10-01 00:00:00").
        $acessoAte = AcademyDateTime::dateOnly($matricula['proxima_data_vencimento'] ?? null)
            ?? AcademyDateTime::dateOnly($matricula['data_vencimento'] ?? null);
        $cicloVigenteAte = AcademyDateTime::dateOnly($matricula['data_vencimento'] ?? null)
            ?? AcademyDateTime::dateOnly($matricula['proxima_data_vencimento'] ?? null);
        $hoje = AcademyDateTime::today();

        if (in_array($status, ['cancelada', 'vencida', 'finalizada'], true)) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_NAO_ATIVA',
                'message' => 'Matrícula cancelada, vencida ou finalizada não pode migrar com crédito. Regularize com uma nova contratação.',
                'motivo' => $status,
            ];
        }

        if ($status !== 'ativa') {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_NAO_ATIVA',
                'message' => 'Só é possível migrar com matrícula ativa e em dia.',
                'motivo' => $status !== '' ? $status : 'status_invalido',
            ];
        }

        if ($acessoAte !== null && $acessoAte < $hoje) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_VENCIDA',
                'message' => 'Matrícula vencida não gera crédito de migração. Regularize o pagamento primeiro.',
                'motivo' => 'acesso_vencido',
            ];
        }

        if ($matriculaId > 0 && $this->temParcelaAtrasada($matriculaId, $tenantId)) {
            return [
                'apto' => false,
                'gera_credito' => false,
                'code' => 'MATRICULA_EM_ATRASO',
                'message' => 'Há parcela em atraso. Quite o débito antes de migrar de plano.',
                'motivo' => 'parcela_atrasada',
            ];
        }

        if ($cicloVigenteAte !== null && $cicloVigenteAte <= $hoje) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'CICLO_ENCERRADO',
                'message' => 'Ciclo vigente encerrado: migração sem crédito.',
                'motivo' => 'ciclo_encerrado',
            ];
        }

        if ($matriculaId > 0 && $this->limiteCheckinsCicloEsgotado($matriculaId)) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'LIMITE_CHECKINS_ESGOTADO',
                'message' => 'Limite de check-ins do ciclo esgotado: migração sem crédito.',
                'motivo' => 'limite_checkins_ciclo',
            ];
        }

        if ($matriculaId > 0 && $this->buscarUltimoPagamentoPagoId($matriculaId, $tenantId) === null) {
            return [
                'apto' => true,
                'gera_credito' => false,
                'code' => 'SEM_PAGAMENTO_PAGO',
                'message' => 'Sem pagamento quitado neste ciclo: migração sem crédito.',
                'motivo' => 'sem_pagamento_pago',
            ];
        }

        return [
            'apto' => true,
            'gera_credito' => true,
            'code' => 'OK',
            'message' => 'Matrícula apta para migração',
            'motivo' => null,
        ];
    }

    private function limiteCheckinsCicloEsgotado(int $matriculaId): bool
    {
        return $this->matriculas->avaliarLimiteMensalPorMatricula($matriculaId, false) !== null;
    }

    private function buscarUltimoPagamentoPagoId(int $matriculaId, int $tenantId): ?int
    {
        $id = DB::table('pagamentos_plano')
            ->where('matricula_id', $matriculaId)
            ->where('tenant_id', $tenantId)
            ->where('status_pagamento_id', StatusPagamentoPlano::PAGO)
            ->orderByDesc('data_vencimento')
            ->value('id');

        return $id ? (int) $id : null;
    }
}
